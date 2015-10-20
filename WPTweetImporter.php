<?php

/*
  Plugin Name: WPTweetImporter
  Plugin URI: https://github.com/larjen/WPTweetImporter
  Description: Imports twitter messages as posts in your Wordpress blog
  Author: Lars Jensen
  Version: 1.0.0
  Author URI: http://exenova.dk/
 */

// require oAuth
require_once('twitteroauth/twitteroauth.php');

class TwitterToWordpress {

    static $debug = false;
    static $fetchThisManyNewTweetsHourly = 15;

    static function activation() {

        // reset the twitter page counter to 0
        update_option("TwitterToWordpress_CURRENT_PAGE", 0);
        update_option("TwitterToWordpress_FETCHED_ALL_TWEETS", false);
        update_option("TwitterToWordpress_NUMBER_OF_TWEETS", 50);
        update_option("TwitterToWordpress_ACTIVE", false);
        update_option("TwitterToWordpress_STATUS", date("Y-m-d H:i:s") . " - Plugin is awaiting configuration by you. When you have configured the plugin, don't forget to activate the import option.");
        update_option("TwitterToWordpress_MESSAGES", []);

        update_option("TwitterToWordpress_INFOCLASS", "updated");

        self::start_aggressive_schedule();
    }

    static function add_message($message) {

        $messages = get_option("TwitterToWordpress_MESSAGES");
        array_push($messages, date("Y-m-d H:i:s") . " - ".$message);

        // keep the amount of messages below 10
        if (count($messages) > 10) {
            $temp = array_shift($messages);
        }

        update_option("TwitterToWordpress_MESSAGES", $messages);
    }

    static function purge_tweets() {

        // reset the twitter page counter to 0
        update_option("TwitterToWordpress_CURRENT_PAGE", 0);
        update_option("TwitterToWordpress_FETCHED_ALL_TWEETS", false);
        
        // deactivate the plugin
        update_option("TwitterToWordpress_ACTIVE", false);

        // start aggressive schedule again
        self::start_aggressive_schedule();

        global $table_prefix;
        global $wpdb;

        $sql = 'SELECT DISTINCT post_id FROM ' . $table_prefix . 'postmeta WHERE meta_key = "twittertowordpress"';
        $rows = $wpdb->get_results($sql, 'ARRAY_A');
        foreach ($rows as $row) {
            wp_delete_post($row["post_id"], true);
        }

        self::add_message("All tweets have been deleted, and importing has been deactivated.");
    }

    static function start_aggressive_schedule() {
        // unschedule previous schedule
        self::clear_schedule();

        //gives the unix timestamp for today's date + 1 minute
        $start = strtotime(date('D M Y')) + (1 * 60);

        // schedule aggressive updates until all tweets are fetched
        wp_schedule_event($start, 'everyMinute', 'TwitterToWordpress_update');
    }

    static function start_moderate_schedule() {
        // unschedule previous schedule
        self::clear_schedule();

        //gives the unix timestamp for today's date + 1 minute
        $start = strtotime(date('D M Y')) + (1 * 60);

        // schedule moderate updates interval
        wp_schedule_event($start, 'hourly', 'TwitterToWordpress_update');
    }

    static function clear_schedule() {
        // unschedule previous schedule
        wp_clear_scheduled_hook('TwitterToWordpress_update');
    }

    static function additional_schedule($schedules) {
        // interval in seconds
        $schedules['everyMinute'] = array('interval' => 60, 'display' => 'Every minute');
        return $schedules;
    }

    static function deactivation() {

        // unschedule previous schedule
        self::clear_schedule();

        // deactivate
        update_option("TwitterToWordpress_ACTIVE", false);
    }

    static function import_twitter_feed() {

        // if import is not activated exit
        if (get_option("TwitterToWordpress_ACTIVE") == true) {

            // fetched all tweets
            $fetchedAllTweets = get_option("TwitterToWordpress_FETCHED_ALL_TWEETS");

            if ($fetchedAllTweets) {

                // then there are no more tweets to import, instead update latest twitters
                self::update_twitter_feed(0, self::$fetchThisManyNewTweetsHourly);
            } else {

                // get current page feed number from database
                $count = get_option("TwitterToWordpress_NUMBER_OF_TWEETS");

                // advance page by one this done here since first page is 1 not 0
                $current_page = strval(get_option("TwitterToWordpress_CURRENT_PAGE")) + 1;

                self::update_twitter_feed($current_page, $count);
            }
        }
    }

    static function update_twitter_feed($current_page, $count) {

        // Create a TwitterOauth object with consumer/user tokens.
        $connection = new TwitterOAuth(get_option("TwitterToWordpress_TWITTER_CONSUMER_KEY"), get_option("TwitterToWordpress_TWITTER_CONSUMER_SECRET"), get_option("TwitterToWordpress_TWITTER_ACCESS_TOKEN"), get_option("TwitterToWordpress_TWITTER_ACCESS_TOKEN_SECRET"));
        $content = $connection->get('account/verify_credentials');
        $timeline = $connection->get("statuses/user_timeline", array("screen_name" => get_option("TwitterToWordpress_TWITTER_SCREEN_NAME"), "count" => $count, "page" => $current_page));

        if (isset($timeline->errors)) {

            // something went wrong, change the status to reflect this. But do not store the new current_page, as we want to retry it.
            self::add_message("Something went wrong, when trying to import tweet " . (($current_page - 1) * $count) . " to " . ((($current_page - 1) * $count) + $count) . ". Error: " . $timeline->errors[0]->message);
            update_option("TwitterToWordpress_STATUS", date("Y-m-d H:i:s") . " - Something went wrong, when trying to import tweet " . (($current_page - 1) * $count) . " to " . ((($current_page - 1) * $count) + $count) . ". Error: " . $timeline->errors[0]->message);
            update_option("TwitterToWordpress_INFOCLASS", "error");
        } else {

            if (empty($timeline)) {

                // we reached the end of all tweets
                update_option("TwitterToWordpress_CURRENT_PAGE", 0);
                update_option("TwitterToWordpress_FETCHED_ALL_TWEETS", true);
                self::add_message("Plugin has imported all of your tweets, and is now actively importing new tweets hourly.");
                update_option("TwitterToWordpress_STATUS", date("Y-m-d H:i:s") . " - Plugin has imported all of your tweets, and is now actively importing new tweets hourly.");
                update_option("TwitterToWordpress_INFOCLASS", "updated");

                // schedule less agressive update interval
                self::start_moderate_schedule();
            } else {

                if (get_option("TwitterToWordpress_FETCHED_ALL_TWEETS") == false) {
                    update_option("TwitterToWordpress_STATUS", date("Y-m-d H:i:s") . " - Plugin is completing the import of your tweets.");
                }

                $succesfullyImportedNumberOfTweets = 0;

                foreach ($timeline as $tweet) {
                    if (self::insert_twitter_post($tweet)) {
                        $succesfullyImportedNumberOfTweets++;
                    }
                }

                // if everything went well store the new current_page 
                update_option("TwitterToWordpress_CURRENT_PAGE", $current_page);

                if (get_option("TwitterToWordpress_FETCHED_ALL_TWEETS") == false) {
                    self::add_message("Succesfully imported " . $succesfullyImportedNumberOfTweets . " tweet(s). From " . (($current_page - 1) * $count) . " to " . ((($current_page - 1) * $count) + $count) . ".");
                } else {
                    if ($succesfullyImportedNumberOfTweets != 0) {
                        self::add_message("Succesfully imported " . $succesfullyImportedNumberOfTweets . " new tweet(s).");
                    } else {
                        self::add_message("No new tweets detected. Will try again within the hour.");
                    }
                }
            }
        }
    }

    static function get_tags($text) {
        $stringArray = explode(" ", $text);
        $returnArray = array();
        foreach ($stringArray as $key => $value) {
            if (substr($value, 0, 1) == "#") {

                // we found a tag, add it to tags
                $tag = substr($value, 1, (strlen($value) - 1));

                // check if last char in tag is , if it is delete it
                $tag = trim($tag);
                $tag = trim($tag, ",");
                $tag = trim($tag, ".");
                $tag = trim($tag, ":");

                //normalize tags
                if (strtoupper($tag) == 'HTML5' || strtoupper($tag) == 'HTML' || strtoupper($tag) == 'XHTML') {
                    $tag = 'HTML';
                }
                if (strtoupper($tag) == 'CSS3' || strtoupper($tag) == 'CSS') {
                    $tag = 'CSS';
                }
                if (strtoupper($tag) == 'JS' || strtoupper($tag) == 'JAVASCRIPT') {
                    $tag = 'JavaScript';
                }

                array_push($returnArray, $tag);
            }
        }
        return $returnArray;
    }

    static function insert_twitter_post($tweet) {

        // check for duplicate
        if (self::twitter_exists($tweet->id_str)) {
            return false;
        }

        // store original text
        $original_text = $tweet->text;

        // find #tags and make them tags
        $tags = self::get_tags($original_text);

        //find links and make them hyperlinks
        $tweet->text = preg_replace('@(https?://([-\w\.]+)+(/([\w/_\.]*(\?\S+)?(#\S+)?)?)?)@', '<a target="_blank" href="$1">$1</a>', $tweet->text);

        //find @username
        $tweet->text = preg_replace('/@(\w+)/', '<a target="_blank" href="http://twitter.com/$1">@$1</a>', $tweet->text);

        //find #hashtags for search on twitter
        $tweet->text = preg_replace('/#(\w+)/', ' <a target="_blank" href="http://twitter.com/search?q=%23$1">#$1</a>', $tweet->text);

        // build the post
        $post = array(
            'post_title' => $original_text,
            'comment_status' => 'closed', // 'closed' means no comments.
            'ping_status' => 'closed', // 'closed' means pingbacks or trackbacks turned off
            'post_author' => get_option("TwitterToWordpress_TWEET_AS"), //The user ID number of the author.
            'post_content' => $tweet->text, //The full text of the post.
            'post_date' => date("Y-m-d H:i:s", strtotime($tweet->created_at)), //The time post was made.
            'post_date_gmt' => gmdate("Y-m-d H:i:s", strtotime($tweet->created_at)), //The time post was made, in GMT.
            'post_status' => 'publish', //Set the status of the new post. 
            'post_type' => 'post', //You may want to insert a regular post, page, link, a menu item or some custom post type
            'post_category' => array(get_option('TwitterToWordpress_TWITTER_CATEGORY')),
            'tags_input' => $tags //For tags.
        );

        // Insert the post
        $insert = wp_insert_post($post);

        // Add meta tags to post
        add_post_meta($insert, 'text', $original_text, true);
        add_post_meta($insert, 'contributors', $tweet->contributors, true);
        add_post_meta($insert, 'coordinates', $tweet->coordinates, true);
        add_post_meta($insert, 'in_reply_to_user_id', $tweet->in_reply_to_user_id, true);
        add_post_meta($insert, 'in_reply_to_user_id_str', $tweet->in_reply_to_user_id_str, true);
        add_post_meta($insert, 'retweet_count', $tweet->retweet_count, true);
        add_post_meta($insert, 'truncated', $tweet->truncated, true);
        add_post_meta($insert, 'created_at', $tweet->created_at, true);
        add_post_meta($insert, 'id_str', $tweet->id_str, true);
        add_post_meta($insert, 'place', $tweet->place, true);
        add_post_meta($insert, 'favorited', $tweet->favorited, true);
        add_post_meta($insert, 'source', $tweet->source, true);
        add_post_meta($insert, 'in_reply_to_screen_name', $tweet->in_reply_to_screen_name, true);
        add_post_meta($insert, 'retweeted', $tweet->retweeted, true);
        add_post_meta($insert, 'geo', $tweet->geo, true);
        add_post_meta($insert, 'id', $tweet->id, true);
        add_post_meta($insert, 'twittertowordpress', 'true', true);

        return true;
    }

    static function twitter_exists($id) {
        global $table_prefix;
        global $wpdb;
        $sql = "SELECT * FROM " . $table_prefix . "postmeta WHERE meta_key = 'id_str' AND meta_value = '" . $id . "'";
        $rows = $wpdb->get_row($sql, 'ARRAY_A');
        if ($rows == 0) {
            return false;
        }
        return true;
    }

    static function plugin_menu() {
        add_management_page('Twitter To Wordpress', 'Twitter To Wordpress', 'activate_plugins', 'twitter_to_wordpress', array('TwitterToWordpress', 'plugin_options'));
    }

    static function plugin_options() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // save all settings
        $optionsArr = array("TwitterToWordpress_TWEET_AS", "TwitterToWordpress_TWITTER_CATEGORY", "TwitterToWordpress_TWITTER_SCREEN_NAME", "TwitterToWordpress_TWITTER_CONSUMER_KEY", "TwitterToWordpress_TWITTER_CONSUMER_SECRET", "TwitterToWordpress_TWITTER_ACCESS_TOKEN", "TwitterToWordpress_TWITTER_ACCESS_TOKEN_SECRET", "TwitterToWordpress_NUMBER_OF_TWEETS");
        foreach ($optionsArr as $value) {
            if (isset($_POST[$value])) {
                update_option($value, $_POST[$value]);
            }
        }
        
        if (isset($_POST["ACTIVE"])) {
            if ($_POST["ACTIVE"] == 'activated') {
                if (get_option("TwitterToWordpress_ACTIVE") == false) {
                    self::add_message("Import of tweets has been activated.");
                }
                update_option("TwitterToWordpress_ACTIVE", true);
            }

            if ($_POST["ACTIVE"] == 'deactivated') {
                if (get_option("TwitterToWordpress_ACTIVE") == true) {
                    self::add_message("Import of tweets has been deactivated.");
                }
                update_option("TwitterToWordpress_ACTIVE", false);
            }
        }


        if (isset($_POST["TwitterToWordpress_PURGE_TWEETS"])) {
            do_action('TwitterToWordpress_purge_tweets');
        }

        $force_import_checked = "";
        if (isset($_POST["TwitterToWordpress_FORCE_IMPORT"])) {
            do_action('TwitterToWordpress_update');
            $force_import_checked = ' checked="checked"';
        }


        // debug
        if (self::$debug) {
            echo '<pre>';
            echo 'get_option("TwitterToWordpress_ACTIVE")=' . get_option("TwitterToWordpress_ACTIVE") . PHP_EOL;
            echo 'get_option("TwitterToWordpress_CURRENT_PAGE")=' . get_option("TwitterToWordpress_CURRENT_PAGE") . PHP_EOL;
            echo 'get_option("TwitterToWordpress_NUMBER_OF_TWEETS")=' . get_option("TwitterToWordpress_NUMBER_OF_TWEETS") . PHP_EOL;
            echo 'get_option("TwitterToWordpress_STATUS")=' . get_option("TwitterToWordpress_STATUS") . PHP_EOL;
            echo 'get_option("TwitterToWordpress_FETCHED_ALL_TWEETS")=' . get_option("TwitterToWordpress_FETCHED_ALL_TWEETS") . PHP_EOL;
            echo 'get_option("TwitterToWordpress_MESSAGES")=' . print_r(get_option("TwitterToWordpress_MESSAGES")) . PHP_EOL;
            echo '</pre>';
        }

        // print the admin page
        echo '<div class="wrap">';
        echo '<h2>Twitter To Wordpress</h2>';
        
        $messages = get_option("TwitterToWordpress_MESSAGES");

        while (!empty($messages)) {
            $message = array_shift($messages);
            echo '<div id="setting-error-settings_updated" class="updated settings-error notice is-dismissible"><p><strong>' . $message . '</strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Afvis denne meddelelse.</span></button></div>';
        }
        
        // since the messages has been shown, purge them.
        update_option("TwitterToWordpress_MESSAGES", []);


        echo '<h3 class="title">Settings</h3>';
        echo '';
        echo '<form method="post" action="">';
        echo '<table class="form-table"><tbody>';

        echo '<tr valign="top"><th scope="row">Status:</th><td><p>'. get_option('TwitterToWordpress_STATUS') .'</p></td></tr>';

        
        echo '<tr valign="top"><th scope="row"><label for="TWITTER_CATEGORY">Add tweets to this category</label></th><td>';

        wp_dropdown_categories(array('hide_empty' => 0, 'name' => 'TwitterToWordpress_TWITTER_CATEGORY', 'orderby' => 'name', 'selected' => get_option('TwitterToWordpress_TWITTER_CATEGORY'), 'hierarchical' => true));

        echo '</td></tr>';

        echo '<tr valign="top"><th scope="row"><label for="TWITTER_CATEGORY">Add tweets as this user</label></th><td>';

        wp_dropdown_users(array('hide_empty' => 0, 'name' => 'TwitterToWordpress_TWEET_AS', 'orderby' => 'name', 'selected' => get_option('TwitterToWordpress_TWEET_AS')));

        echo '</td></tr>';

        echo '<tr valign="top"><th scope="row">Delete tweets</th><td><fieldset><legend class="screen-reader-text"><span>Purge Tweets</span></legend><label for="PURGE_TWEETS"><input id="PURGE_TWEETS" name="TwitterToWordpress_PURGE_TWEETS" type="checkbox"></label><p class="description">Will delete all tweets from blog.</p></fieldset></td></tr>';

        echo '<tr valign="top"><th scope="row">Force import</th><td><fieldset><legend class="screen-reader-text"><span>Force import</span></legend><label for="FORCE_IMPORT"><input id="FORCE_IMPORT" name="TwitterToWordpress_FORCE_IMPORT" type="checkbox" ' . $force_import_checked . '></label><p class="description">Force import of tweets, instead of waiting for the scheduled update.</p></fieldset></td></tr>';


        echo '<tr valign="top"><th scope="row">Activate import</th><td><fieldset><legend class="screen-reader-text"><span>Activate</span></legend>';

        if (get_option('TwitterToWordpress_ACTIVE') == true) {
            echo '<label for="ACTIVE"><input checked="checked" id="ACTIVE" name="ACTIVE" type="radio" value="activated"> Import of tweets is active.</label><br /><legend class="screen-reader-text"><span>Dectivate</span></legend><label for="DEACTIVE"><input id="DEACTIVE" name="ACTIVE" type="radio" value="deactivated"> Import of tweets is deactivated.</label>';
        } else {
            echo '<label for="ACTIVE"><input id="ACTIVE" name="ACTIVE" type="radio" value="activated"> Import of tweets is active.</label><br /><legend class="screen-reader-text"><span>Dectivate</span></legend><label for="DEACTIVE"><input checked="checked" id="DEACTIVE" name="ACTIVE" type="radio" value="deactivated"> Import of tweets is deactivated.</label>';
        }

        echo '<p class="description">When activated this plugin will import 50 tweets every minute until all tweets have been imported. When all tweets have been imported, it will fetch your ' . self::$fetchThisManyNewTweetsHourly . ' latest tweets every hour.</p>';
        echo '</fieldset></td></tr>';

        echo '</tbody></table>';

        echo '<h3 class="title">Twitter Account</h3>';
        echo '<table class="form-table"><tbody>';
        echo '<tr valign="top"><th scope="row"><label for="TWITTER_SCREEN_NAME">Twitter name (Screen name)</label></th><td><input id="TWITTER_SCREEN_NAME" name="TwitterToWordpress_TWITTER_SCREEN_NAME" type="text" value="' . get_option("TwitterToWordpress_TWITTER_SCREEN_NAME") . '" class="regular-text"></td></tr>';
        echo '<tr valign="top"><th scope="row"></th><td><p>You must obtain the following 4 keys from Twitter to enable this plugin to work. Login and create a new app <a href="https://dev.twitter.com/apps">here</a>.</p></td></tr>';
        echo '<tr valign="top"><th scope="row"><label for="TWITTER_CONSUMER_KEY">Consumer key</label></th><td><input id="TWITTER_CONSUMER_KEY" name="TwitterToWordpress_TWITTER_CONSUMER_KEY" type="text" value="' . get_option("TwitterToWordpress_TWITTER_CONSUMER_KEY") . '" class="regular-text"></td></tr>';
        echo '<tr valign="top"><th scope="row"><label for="TWITTER_CONSUMER_SECRET">Consumer secret</label></th><td><input id="TWITTER_CONSUMER_SECRET" name="TwitterToWordpress_TWITTER_CONSUMER_SECRET" type="text" value="' . get_option("TwitterToWordpress_TWITTER_CONSUMER_SECRET") . '" class="regular-text"></td></tr>';
        echo '<tr valign="top"><th scope="row"><label for="TWITTER_ACCESS_TOKEN">Access token</label></th><td><input id="TWITTER_ACCESS_TOKEN" name="TwitterToWordpress_TWITTER_ACCESS_TOKEN" type="text" value="' . get_option("TwitterToWordpress_TWITTER_ACCESS_TOKEN") . '" class="regular-text"></td></tr>';
        echo '<tr valign="top"><th scope="row"><label for="TWITTER_ACCESS_TOKEN_SECRET">Access token secret</label></th><td><input id="TWITTER_ACCESS_TOKEN_SECRET" name="TwitterToWordpress_TWITTER_ACCESS_TOKEN_SECRET" type="text" value="' . get_option("TwitterToWordpress_TWITTER_ACCESS_TOKEN_SECRET") . '" class="regular-text"></td></tr>';
        echo '</tbody></table>';


        echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>';
        echo '</form></div>';
    }

}

// add schedule to import every minute
add_filter('cron_schedules', 'TwitterToWordpress::additional_schedule');

// register activation and deactivation
register_activation_hook(__FILE__, 'TwitterToWordpress::activation');
register_deactivation_hook(__FILE__, 'TwitterToWordpress::deactivation');

// register wp hooks
add_action('admin_menu', 'TwitterToWordpress::plugin_menu');

// define custom actions
add_action('TwitterToWordpress_update', 'TwitterToWordpress::import_twitter_feed');
add_action('TwitterToWordpress_purge_tweets', 'TwitterToWordpress::purge_tweets');
