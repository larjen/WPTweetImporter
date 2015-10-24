# WPTweetImporter

A simple automated Wordpress plugin that imports tweets as posts.

## Installation

1. Download to your Wordpress plugin folder.
2. Activate plugin.
3. Create a Twitter Application https://apps.twitter.com/.
4. Find and create "Your access token", make sure to set privileges to read only.
5. Configure the plugin with your Twitter screen name, and the 4 keys obtained from creating the Twitter app.
6. Activate the import of tweets.
7. All of your tweets are now accesible as posts.

== Changelog ==

= 1.0.1 =
* Now uses WPTagSanitizer for normalizing tags, if WPTagSanitizer is not installed, all tags will just be imported as is with no modification.

= 1.0.0 =
* Uploaded plugin.