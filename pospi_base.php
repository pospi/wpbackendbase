<?php
/*
Plugin Name: Pospi wordpress admin UI base plugin
Plugin URI: http://pospi.spadgos.com
Description: Helpers for managing custom post types & user input of post data
Version: 1.0
Author: pospi
Author URI: http://pospi.spadgos.com
License: MIT
*/

//Setup some secondary variable "constants"
define('POSPI_PLUGIN_BASE', dirname(__FILE__));

require_once(POSPI_PLUGIN_BASE . "/custom_post_type.class.php");

//Custom Javascript
//add_action('admin_enqueue_scripts',function(){
//   wp_register_script("jquery","https://ajax.googleapis.com/ajax/libs/jquery/1.6.4/jquery.min.js",array(),"1.6.4");
//});

//Custom Css
add_action( 'admin_init', function(){
    wp_register_style( 'pospi_admin_base_css', plugins_url('css/admin.css', __FILE__) );
    wp_enqueue_style( 'pospi_admin_base_css' );
});

// plugin activation hook
register_activation_hook(__FILE__, function(){
    flush_rewrite_rules();
});
