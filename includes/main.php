<?php

// require oAuth
require_once('twitteroauth/twitteroauth.php');

class WPTweetImporter {

    static $plugin_name = "WPTweetImporter";
    static $debug = false;
    static $fetchThisManyNewTweetsHourly = 15;

    static function activation() {

        // reset the twitter page counter to 0
        update_option(self::$plugin_name . "_CURRENT_PAGE", 0);
        update_option(self::$plugin_name . "_FETCHED_ALL_TWEETS", false);
        update_option(self::$plugin_name . "_NUMBER_OF_TWEETS", 50);
        update_option(self::$plugin_name . "_ACTIVE", false);
        update_option(self::$plugin_name . "_STATUS", date("Y-m-d H:i:s") . " - Plugin is awaiting configuration by you. When you have configured the plugin, don't forget to activate the import option.");
        update_option(self::$plugin_name . "_MESSAGES", []);
        update_option(self::$plugin_name . "_INFOCLASS", "updated");

        // add category tweet to wp
        $term = term_exists('Tweet', 'category');
        if ($term == 0 or $term == null) {
            $tweet_cat = array(
                'cat_name' => 'Tweet',
                'category_description' => 'Tweets imported from twitter',
                'category_nicename' => 'tweet',
                'category_parent' => ''
            );

            // Create the category
            $tweet_id = wp_insert_category($tweet_cat);

            // Set the option as default
            update_option(self::$plugin_name . "_TWITTER_CATEGORY", $tweet_id);
        }

        // start aggressive schedule
        self::start_aggressive_schedule();
    }

    static function deactivation() {

        // unschedule previous schedule
        self::clear_schedule();

        // deactivate
        update_option(self::$plugin_name . "_ACTIVE", false);
    }

    static function add_message($message) {

        $messages = get_option(self::$plugin_name . "_MESSAGES");
        array_push($messages, date("Y-m-d H:i:s") . " - " . $message);

        // keep the amount of messages below 10
        if (count($messages) > 10) {
            $temp = array_shift($messages);
        }

        update_option(self::$plugin_name . "_MESSAGES", $messages);
    }

    static function start_aggressive_schedule() {
        // unschedule previous schedule
        self::clear_schedule();

        //gives the unix timestamp for today's date + 1 minute
        $start = time() + (1 * 60);

        // schedule aggressive updates until all tweets are fetched
        wp_schedule_event($start, 'everyMinute', self::$plugin_name . '_update');
    }

    static function start_moderate_schedule() {
        // unschedule previous schedule
        self::clear_schedule();

        //gives the unix timestamp for today's date + 60 minutes
        $start = time() + (60 * 60);

        // schedule moderate updates interval
        wp_schedule_event($start, 'hourly', self::$plugin_name . '_update');
    }

    static function clear_schedule() {
        // unschedule previous schedule
        wp_clear_scheduled_hook(self::$plugin_name . '_update');
    }

    static function additional_schedule($schedules) {
        // interval in seconds
        $schedules['everyMinute'] = array('interval' => 60, 'display' => 'Every minute');
        return $schedules;
    }

    static function import_twitter_feed() {

        // if import is not activated exit
        if (get_option(self::$plugin_name . "_ACTIVE") == true) {

            // fetched all tweets
            $fetchedAllTweets = get_option(self::$plugin_name . "_FETCHED_ALL_TWEETS");

            if ($fetchedAllTweets) {

                // then there are no more tweets to import, instead update latest twitters
                self::update_twitter_feed(0, self::$fetchThisManyNewTweetsHourly);
            } else {

                // get current page feed number from database
                $count = get_option(self::$plugin_name . "_NUMBER_OF_TWEETS");

                // advance page by one this done here since first page is 1 not 0
                $current_page = strval(get_option(self::$plugin_name . "_CURRENT_PAGE")) + 1;

                self::update_twitter_feed($current_page, $count);
            }
        }
    }

    static function update_twitter_feed($current_page, $count) {

        // Create a TwitterOauth object with consumer/user tokens.
        $connection = new TwitterOAuth(get_option(self::$plugin_name . "_TWITTER_CONSUMER_KEY"), get_option(self::$plugin_name . "_TWITTER_CONSUMER_SECRET"), get_option(self::$plugin_name . "_TWITTER_ACCESS_TOKEN"), get_option(self::$plugin_name . "_TWITTER_ACCESS_TOKEN_SECRET"));
        $content = $connection->get('account/verify_credentials');
        $timeline = $connection->get("statuses/user_timeline", array("screen_name" => get_option(self::$plugin_name . "_TWITTER_SCREEN_NAME"), "count" => $count, "page" => $current_page));

        if (isset($timeline->errors)) {

            // something went wrong, change the status to reflect this. But do not store the new current_page, as we want to retry it.
            self::add_message("Something went wrong, when trying to import tweet " . (($current_page - 1) * $count) . " to " . ((($current_page - 1) * $count) + $count) . ". Error: " . $timeline->errors[0]->message);
            update_option(self::$plugin_name . "_STATUS", date("Y-m-d H:i:s") . " - Something went wrong, when trying to import tweet " . (($current_page - 1) * $count) . " to " . ((($current_page - 1) * $count) + $count) . ". Error: " . $timeline->errors[0]->message);
            update_option(self::$plugin_name . "_INFOCLASS", "error");
        } else {

            if (empty($timeline)) {

                // we reached the end of all tweets
                update_option(self::$plugin_name . "_CURRENT_PAGE", 0);
                update_option(self::$plugin_name . "_FETCHED_ALL_TWEETS", true);
                self::add_message("Plugin has imported all of your tweets, and is now actively importing new tweets hourly.");
                update_option(self::$plugin_name . "_STATUS", date("Y-m-d H:i:s") . " - Plugin has imported all of your tweets, and is now actively importing new tweets hourly.");
                update_option(self::$plugin_name . "_INFOCLASS", "updated");

                // schedule less agressive update interval
                self::start_moderate_schedule();
            } else {

                if (get_option(self::$plugin_name . "_FETCHED_ALL_TWEETS") == false) {
                    update_option(self::$plugin_name . "_STATUS", date("Y-m-d H:i:s") . " - Plugin is completing the import of your tweets.");
                } else {
                    update_option(self::$plugin_name . "_STATUS", date("Y-m-d H:i:s") . " - Plugin is fetching new tweets hourly.");
                }

                $succesfullyImportedNumberOfTweets = 0;

                foreach ($timeline as $tweet) {
                    if (self::insert_twitter_post($tweet)) {
                        $succesfullyImportedNumberOfTweets++;
                    }
                }

                // if everything went well store the new current_page 
                update_option(self::$plugin_name . "_CURRENT_PAGE", $current_page);

                if (get_option(self::$plugin_name . "_FETCHED_ALL_TWEETS") == false) {
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
    
    /*
     * Truncates long URLs to shoter ones.
     */
    static function truncate_url($matches){
        $textLength = strlen($matches[0]);
        $maxChars = 25;
        if ($textLength > $maxChars){
            $result = substr_replace($matches[0], '...', $maxChars, $textLength);
        } else {
            $result = $matches[0];
        }
        return '<a target="_blank" href="'.$matches[0].'">'.$result.'</a>';
    }
    
    /*
     * Find links in the text and make them links.
     */
    static function addLinksToText($string){
        
        //find links and make them hyperlinks
        $string = preg_replace_callback('@(https?://([-\w\.]+)+(/([\w/_\.]*(\?\S+)?(#\S+)?)?)?)@', 'self::truncate_url', $string);
        
        //find @username
        $string = preg_replace('/@(\w+)/', '<a target="_blank" href="http://twitter.com/$1">@$1</a>', $string);
        
        //find #hashtags for search on twitter
        $string = preg_replace('/#(\w+)/', ' <a target="_blank" href="http://twitter.com/search?q=%23$1">#$1</a>', $string);
        
        return $string;
    }

    static function insert_twitter_post($tweet) {

        // check for duplicate
        if (self::twitter_exists($tweet->id_str)) {
            return false;
        }

        // store original text
        $original_text = $tweet->text;

        $tags = [];

        // if wpTagSanitizer is installed, use it to extract tags from tweet
        if (is_callable('WPTagSanitizer::getTagsFromString')) {
            $tags = WPTagSanitizer::getTagsFromString($original_text);
        }

        $tweet->text = self::addLinksToText($tweet->text);

        // build the post
        $post = array(
            'post_title' => $original_text,
            'comment_status' => 'closed', // 'closed' means no comments.
            'ping_status' => 'closed', // 'closed' means pingbacks or trackbacks turned off
            'post_author' => get_option(self::$plugin_name . "_TWEET_AS"), //The user ID number of the author.
            'post_content' => $tweet->text, //The full text of the post.
            'post_date' => date("Y-m-d H:i:s", strtotime($tweet->created_at)), //The time post was made.
            'post_date_gmt' => gmdate("Y-m-d H:i:s", strtotime($tweet->created_at)), //The time post was made, in GMT.
            'post_status' => 'publish', //Set the status of the new post. 
            'post_type' => 'post', //You may want to insert a regular post, page, link, a menu item or some custom post type
            'post_category' => array(get_option(self::$plugin_name . '_TWITTER_CATEGORY')),
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
        add_post_meta($insert, 'WPTweetImporter', 'true', true);

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

}

// add schedule to import every minute
add_filter('cron_schedules', 'WPTweetImporter::additional_schedule');

// add action to update twitter feed
add_action('WPTweetImporter_update', 'WPTweetImporter::import_twitter_feed');
