# WPTweetImporter

WordPress plugin that imports tweets as posts in your Wordpress blog.

## Installation

1. Download to your Wordpress plugin folder.
2. Activate plugin.
3. Create a Twitter Application https://apps.twitter.com/.
4. Find and create "Your access token", make sure to set privileges to read only.
5. Configure the plugin with your Twitter screen name, and the 4 keys obtained from creating the Twitter app.
6. Activate the import of tweets.
7. All of your tweets are now accesible as posts.

## Changelog

### 1.0.4
* Now truncating very long urls.

### 1.0.3
* Refactoring plugin for better performance.
* Now optionally parses hash-tags in tweets if WPTagSanitizer is installed.

### 1.0.2
* Automatically adds category Tweets as the category tweets are imported to. This can be overridden in the options panel.

### 1.0.1
* Now uses WPTagSanitizer for normalizing tags, if WPTagSanitizer is not installed, all tags will just be imported as is with no modification.

### 1.0.0
* Uploaded plugin.

[//]: title (WPTweetImporter)
[//]: category (work)
[//]: start_date (20151021)
[//]: end_date (#)
[//]: excerpt (WordPress plugin that imports tweets as posts in your Wordpress blog.)
[//]: tag (GitHub)
[//]: tag (WordPress)
[//]: tag (PHP)
[//]: tag (Twitter)
[//]: url_github (https://github.com/larjen/WPTweetImporter)
[//]: url_demo (#) 
[//]: url_wordpress (https://wordpress.org/plugins/wptweetimporter/)
[//]: url_download (https://github.com/larjen/WPTweetImporter/archive/master.zip)