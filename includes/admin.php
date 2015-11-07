<?php

class WPTweetImporterAdmin extends WPTweetImporter {

    static function plugin_menu() {
        add_management_page(self::$plugin_name, self::$plugin_name, 'activate_plugins', 'WPTweetImporterAdmin', array('WPTweetImporterAdmin', 'plugin_options'));
    }

    static function activate_import() {
        if (get_option(self::$plugin_name."_ACTIVE") == false) {
            self::add_message("Import of tweets has been activated.");
            if (get_option(self::$plugin_name."_FETCHED_ALL_TWEETS") == false) {
                update_option(self::$plugin_name."_STATUS", date("Y-m-d H:i:s") . " - Plugin is completing the import of your tweets.");
            } else {
                update_option(self::$plugin_name."_STATUS", date("Y-m-d H:i:s") . " - Plugin is fetching new tweets hourly.");
            }
        }
        update_option(self::$plugin_name."_ACTIVE", true);
    }
    
    
    static function purge_tweets() {

        // deactivate import
        self::deactivate_import();

        // reset the twitter page counter to 0
        update_option(self::$plugin_name."_CURRENT_PAGE", 0);
        update_option(self::$plugin_name."_FETCHED_ALL_TWEETS", false);

        // start aggressive schedule again
        self::start_aggressive_schedule();

        global $table_prefix;
        global $wpdb;

        $sql = 'SELECT DISTINCT post_id FROM ' . $table_prefix . 'postmeta WHERE meta_key = "WPTweetImporter"';
        $rows = $wpdb->get_results($sql, 'ARRAY_A');
        foreach ($rows as $row) {
            wp_delete_post($row["post_id"], true);
        }

        self::add_message("All tweets have been deleted, and importing has been deactivated.");
    }

    static function deactivate_import() {
        if (get_option(self::$plugin_name."_ACTIVE") == true) {
            self::add_message("Import of tweets has been deactivated.");
            update_option(self::$plugin_name."_STATUS", date("Y-m-d H:i:s") . " - Plugin is not fetching new tweets.");
        }
        update_option(self::$plugin_name."_ACTIVE", false);
    }

    static function plugin_options() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // save all settings
        $optionsArr = array(self::$plugin_name."_TWEET_AS", self::$plugin_name."_TWITTER_CATEGORY", self::$plugin_name."_TWITTER_SCREEN_NAME", self::$plugin_name."_TWITTER_CONSUMER_KEY", self::$plugin_name."_TWITTER_CONSUMER_SECRET", self::$plugin_name."_TWITTER_ACCESS_TOKEN", self::$plugin_name."_TWITTER_ACCESS_TOKEN_SECRET", self::$plugin_name."_NUMBER_OF_TWEETS");
        foreach ($optionsArr as $value) {
            if (isset($_POST[$value])) {
                update_option($value, $_POST[$value]);
            }
        }

        if (isset($_POST["ACTIVE"])) {
            if ($_POST["ACTIVE"] == 'activated') {
                self::activate_import();
            }

            if ($_POST["ACTIVE"] == 'deactivated') {
                self::deactivate_import();
            }
        }


        if (isset($_POST[self::$plugin_name."_PURGE_TWEETS"])) {
            do_action('WPTweetImporter_purge_tweets');
        }

        $force_import_checked = "";
        if (isset($_POST[self::$plugin_name."_FORCE_IMPORT"])) {
            do_action('WPTweetImporter_update');
            $force_import_checked = ' checked="checked"';
        }


        // debug
        if (self::$debug) {
            echo '<pre>';
            if (!is_callable('WPTagSanitizer::sanitizeTags')) {
                echo 'WPTagSanitizer::sanitizeTags is not installed.' . PHP_EOL;
            } else {
                echo 'WPTagSanitizer::sanitizeTags installed.' . PHP_EOL;
            }
            echo 'get_option(self::$plugin_name."_ACTIVE")=' . get_option(self::$plugin_name."_ACTIVE") . PHP_EOL;
            echo 'get_option(self::$plugin_name."_CURRENT_PAGE")=' . get_option(self::$plugin_name."_CURRENT_PAGE") . PHP_EOL;
            echo 'get_option(self::$plugin_name."_NUMBER_OF_TWEETS")=' . get_option(self::$plugin_name."_NUMBER_OF_TWEETS") . PHP_EOL;
            echo 'get_option(self::$plugin_name."_STATUS")=' . get_option(self::$plugin_name."_STATUS") . PHP_EOL;
            echo 'get_option(self::$plugin_name."_FETCHED_ALL_TWEETS")=' . get_option(self::$plugin_name."_FETCHED_ALL_TWEETS") . PHP_EOL;
            echo 'get_option(self::$plugin_name."_MESSAGES")=' . print_r(get_option(self::$plugin_name."_MESSAGES")) . PHP_EOL;
            echo 'get_option(self::$plugin_name."_TWEET_AS")=' . print_r(get_option(self::$plugin_name."_TWEET_AS")) . PHP_EOL;
            echo 'get_option(self::$plugin_name."_TWITTER_CATEGORY")=' . print_r(get_option(self::$plugin_name."_TWITTER_CATEGORY")) . PHP_EOL;



            echo '</pre>';
        }

        // print the admin page
        echo '<div class="wrap">';
        echo '<h2>'.self::$plugin_name.'</h2>';

        $messages = get_option(self::$plugin_name."_MESSAGES");

        while (!empty($messages)) {
            $message = array_shift($messages);
            echo '<div id="setting-error-settings_updated" class="updated settings-error notice is-dismissible"><p><strong>' . $message . '</strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Afvis denne meddelelse.</span></button></div>';
        }

        // since the messages has been shown, purge them.
        update_option(self::$plugin_name."_MESSAGES", []);


        echo '<h3 class="title">Settings</h3>';
        echo '';
        echo '<form method="post" action="">';
        echo '<table class="form-table"><tbody>';

        echo '<tr valign="top"><th scope="row">Status:</th><td><p>' . get_option('WPTweetImporter_STATUS') . '</p></td></tr>';


        echo '<tr valign="top"><th scope="row"><label for="TWITTER_CATEGORY">Add tweets to this category</label></th><td>';

        wp_dropdown_categories(array('hide_empty' => 0, 'name' => 'WPTweetImporter_TWITTER_CATEGORY', 'orderby' => 'name', 'selected' => get_option('WPTweetImporter_TWITTER_CATEGORY'), 'hierarchical' => true));

        echo '</td></tr>';

        echo '<tr valign="top"><th scope="row"><label for="TWITTER_CATEGORY">Add tweets as this user</label></th><td>';

        wp_dropdown_users(array('hide_empty' => 0, 'name' => 'WPTweetImporter_TWEET_AS', 'orderby' => 'name', 'selected' => get_option('WPTweetImporter_TWEET_AS')));

        echo '</td></tr>';

        echo '<tr valign="top"><th scope="row">Delete tweets</th><td><fieldset><legend class="screen-reader-text"><span>Purge Tweets</span></legend><label for="PURGE_TWEETS"><input id="PURGE_TWEETS" name="WPTweetImporter_PURGE_TWEETS" type="checkbox"></label><p class="description">Will delete all tweets from blog.</p></fieldset></td></tr>';

        echo '<tr valign="top"><th scope="row">Force import</th><td><fieldset><legend class="screen-reader-text"><span>Force import</span></legend><label for="FORCE_IMPORT"><input id="FORCE_IMPORT" name="WPTweetImporter_FORCE_IMPORT" type="checkbox" ' . $force_import_checked . '></label><p class="description">Force import of tweets, instead of waiting for the scheduled update.</p></fieldset></td></tr>';


        echo '<tr valign="top"><th scope="row">Activate import</th><td><fieldset><legend class="screen-reader-text"><span>Activate</span></legend>';

        if (get_option('WPTweetImporter_ACTIVE') == true) {
            echo '<label for="ACTIVE"><input checked="checked" id="ACTIVE" name="ACTIVE" type="radio" value="activated"> Import of tweets is active.</label><br /><legend class="screen-reader-text"><span>Dectivate</span></legend><label for="DEACTIVE"><input id="DEACTIVE" name="ACTIVE" type="radio" value="deactivated"> Import of tweets is deactivated.</label>';
        } else {
            echo '<label for="ACTIVE"><input id="ACTIVE" name="ACTIVE" type="radio" value="activated"> Import of tweets is active.</label><br /><legend class="screen-reader-text"><span>Dectivate</span></legend><label for="DEACTIVE"><input checked="checked" id="DEACTIVE" name="ACTIVE" type="radio" value="deactivated"> Import of tweets is deactivated.</label>';
        }

        echo '<p class="description">When activated this plugin will import 50 tweets every minute until all tweets have been imported. When all tweets have been imported, it will fetch your ' . self::$fetchThisManyNewTweetsHourly . ' latest tweets every hour.</p>';
        echo '</fieldset></td></tr>';

        echo '</tbody></table>';

        echo '<h3 class="title">Twitter Account</h3>';
        echo '<table class="form-table"><tbody>';
        echo '<tr valign="top"><th scope="row"><label for="TWITTER_SCREEN_NAME">Twitter name (Screen name)</label></th><td><input id="TWITTER_SCREEN_NAME" name="WPTweetImporter_TWITTER_SCREEN_NAME" type="text" value="' . get_option(self::$plugin_name."_TWITTER_SCREEN_NAME") . '" class="regular-text"></td></tr>';
        echo '<tr valign="top"><th scope="row"></th><td><p>You must obtain the following 4 keys from Twitter to enable this plugin to work. Login and create a new app <a href="https://dev.twitter.com/apps">here</a>.</p></td></tr>';
        echo '<tr valign="top"><th scope="row"><label for="TWITTER_CONSUMER_KEY">Consumer key</label></th><td><input id="TWITTER_CONSUMER_KEY" name="WPTweetImporter_TWITTER_CONSUMER_KEY" type="text" value="' . get_option(self::$plugin_name."_TWITTER_CONSUMER_KEY") . '" class="regular-text"></td></tr>';
        echo '<tr valign="top"><th scope="row"><label for="TWITTER_CONSUMER_SECRET">Consumer secret</label></th><td><input id="TWITTER_CONSUMER_SECRET" name="WPTweetImporter_TWITTER_CONSUMER_SECRET" type="text" value="' . get_option(self::$plugin_name."_TWITTER_CONSUMER_SECRET") . '" class="regular-text"></td></tr>';
        echo '<tr valign="top"><th scope="row"><label for="TWITTER_ACCESS_TOKEN">Access token</label></th><td><input id="TWITTER_ACCESS_TOKEN" name="WPTweetImporter_TWITTER_ACCESS_TOKEN" type="text" value="' . get_option(self::$plugin_name."_TWITTER_ACCESS_TOKEN") . '" class="regular-text"></td></tr>';
        echo '<tr valign="top"><th scope="row"><label for="TWITTER_ACCESS_TOKEN_SECRET">Access token secret</label></th><td><input id="TWITTER_ACCESS_TOKEN_SECRET" name="WPTweetImporter_TWITTER_ACCESS_TOKEN_SECRET" type="text" value="' . get_option(self::$plugin_name."_TWITTER_ACCESS_TOKEN_SECRET") . '" class="regular-text"></td></tr>';
        echo '</tbody></table>';


        echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>';
        echo '</form></div>';
    }
}

// register wp hooks
add_action('admin_menu', 'WPTweetImporterAdmin::plugin_menu');

// define custom actions
add_action('WPTweetImporter_purge_tweets', 'WPTweetImporterAdmin::purge_tweets');