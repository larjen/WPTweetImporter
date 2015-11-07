<?php

//if uninstall not called from WordPress exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) 
    exit();

include_once(__DIR__ . DIRECTORY_SEPARATOR . "includes". DIRECTORY_SEPARATOR . "main.php");

class WPTweetImporterUninstall extends WPTweetImporter {
    static function uninstall() {
        delete_option(self::$plugin_name . "_MESSAGES");
    }
}

WPTweetImporterUninstall::uninstall();