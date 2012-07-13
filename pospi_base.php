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

define('POSPI_PLUGIN_BASE', dirname(__FILE__));

require_once(POSPI_PLUGIN_BASE . "/formio/form_io.class.php");
// some additional useful form inputs
require_once(POSPI_PLUGIN_BASE . "/field_types/formio_field-richedit.class.php");
require_once(POSPI_PLUGIN_BASE . "/field_types/formio_field-facebook_user.class.php");
require_once(POSPI_PLUGIN_BASE . "/field_types/formio_field-twitter_user.class.php");
require_once(POSPI_PLUGIN_BASE . "/field_types/formio_field-displaylink.class.php");
require_once(POSPI_PLUGIN_BASE . "/field_types/formio_field-filesize.class.php");
// post type inputs
require_once(POSPI_PLUGIN_BASE . "/custom_post_type.class.php");
require_once(POSPI_PLUGIN_BASE . "/field_types/formio_field-posttypes.class.php");
require_once(POSPI_PLUGIN_BASE . "/field_types/formio_field-attachments.class.php");
require_once(POSPI_PLUGIN_BASE . "/field_types/formio_field-links.class.php");
require_once(POSPI_PLUGIN_BASE . "/field_types/formio_field-users.class.php");

// Custom Javascript
add_action('admin_enqueue_scripts',function(){
	// wp_register_script("jquery","https://ajax.googleapis.com/ajax/libs/jquery/1.6.4/jquery.min.js",array(),"1.6.4");
	// wp_enqueue_script("jquery");
	wp_register_script("jquery_ui","http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/jquery-ui.min.js",array('jquery'),"1.8.18");
	wp_enqueue_script("jquery_ui");
	wp_register_script("jquery_tokeninput", plugins_url('formio/lib/jquery-tokeninput/src/jquery.tokeninput.js', __FILE__), array('jquery_ui'), '1.6.0');
	wp_enqueue_script("jquery_tokeninput");
	wp_register_script('formio', plugins_url('formio/formio.js', __FILE__), array('jquery_tokeninput'));
	wp_enqueue_script('formio');

	wp_register_script('jquery.event.frame', includes_url('jparallax/js/jquery.event.frame.js', __FILE__), array('jquery'));
	wp_enqueue_script('jquery.event.frame');
	wp_register_script('jparallax', includes_url('jparallax/js/jquery.parallax.js', __FILE__), array('jquery.event.frame'));
	wp_enqueue_script('jparallax');

	wp_register_script('pospi-admin-js', plugins_url('pospi_wp_admin.js', __FILE__), array('formio'));
	wp_enqueue_script('pospi-admin-js');
});

// Custom Css
add_action( 'admin_init', function() {
	wp_register_style('jquery_ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/themes/base/jquery-ui.css');
	wp_enqueue_style('jquery_ui');
	wp_register_style('jquery_tokeninput', plugins_url('formio/lib/jquery-tokeninput/styles/token-input.css', __FILE__));
	wp_enqueue_style('jquery_tokeninput');
    wp_register_style('formio_css', plugins_url('formio/formio.css', __FILE__));
    wp_enqueue_style('formio_css');
    wp_register_style('formio_theme_css', plugins_url('formio/themes/wordpress.css', __FILE__));
    wp_enqueue_style('formio_theme_css');

    wp_register_style('pospi_admin_base_css', plugins_url('pospi_base_admin.css', __FILE__));
    wp_enqueue_style('pospi_admin_base_css');

    // we also need to initialize sessions in order to handle submission errors on custom post metadata
    session_start();
});

// plugin activation hook
register_activation_hook(__FILE__, function(){
    flush_rewrite_rules();
});

// fire an event when we're done loading so that other plugins can load as dependencies
function pospi_base_loaded() {
    do_action('pospi_base_loaded');
}
add_action('plugins_loaded', 'pospi_base_loaded');
