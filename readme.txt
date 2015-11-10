=== WPTweetImporter ===
Contributors: larjen
Donate link: http://exenova.dk/
Tags: Twitter
Requires at least: 4.3.1
Tested up to: 4.3.1
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A simple automated Wordpress plugin that imports tweets as posts.

== Description ==

A simple automated Wordpress plugin that imports tweets as posts.

== Installation ==

1. Download to your Wordpress plugin folder.
1. Activate plugin.
1. Create a Twitter Application https://apps.twitter.com/.
1. Find and create "Your access token", make sure to set privileges to read only.
1. Configure the plugin with your Twitter screen name, and the 4 keys obtained from creating the Twitter app.
1. Activate the import of tweets.
1. All of your tweets are now accesible as posts.

== Frequently Asked Questions ==

= Do I use this at my own risk? =

Yes.

== Screenshots ==

== Changelog ==

= 1.0.4 =
* Now truncating very long urls.

= 1.0.3 =
* Refactoring plugin for better performance.
* Now optionally parses hash-tags in tweets if WPTagSanitizer is installed.

= 1.0.2 =
* Automatically adds category Tweets as the category tweets are imported to. This can be overridden in the options panel.

= 1.0.1 =
* Now uses WPTagSanitizer for normalizing tags, if WPTagSanitizer is not installed, all tags will just be imported as is with no modification.

= 1.0.0 =
* Uploaded plugin.

== Upgrade Notice ==
