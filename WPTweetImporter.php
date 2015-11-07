<?php

/*
  Plugin Name: WPTweetImporter
  Plugin URI: https://github.com/larjen/WPTweetImporter
  Description: Imports twitter messages as posts in your Wordpress blog
  Author: Lars Jensen
  Version: 1.0.3
  Author URI: http://exenova.dk/
 */


include_once(__DIR__ . DIRECTORY_SEPARATOR . "includes". DIRECTORY_SEPARATOR . "main.php");

if (is_admin()) {
    
    // include admin ui
    include_once(__DIR__ . DIRECTORY_SEPARATOR . "includes". DIRECTORY_SEPARATOR . "admin.php");

    // register activation and deactivation
    register_activation_hook(__FILE__, 'WPTweetImporter::activation');
    register_deactivation_hook(__FILE__, 'WPTweetImporter::deactivation');
    
}


