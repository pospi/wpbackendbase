<?php
/*
Plugin Name: Pospi's Wordpress backend base plugin
Plugin URI: http://pospi.spadgos.com/libs/wpbackendbase
Description: Helpers for managing custom post & object data, user input and the admin UI.
Version: 1.0
Author: pospi
Author URI: http://pospi.spadgos.com
License: MIT
*/

// other plugin menu items
add_filter('plugin_row_meta', function($links, $file) {
	$base = basename(__FILE__);

	if (basename($file) == $base) {
		$links[] = '<a href="https://github.com/pospi/wpbackendbase">Github</a>';
	}

	return $links;
}, 10, 2);

define('POSPI_PLUGIN_BASE', dirname(__FILE__));

// form validation & submission engine
require_once(POSPI_PLUGIN_BASE . "/formio/form_io.class.php");
// some additional useful form inputs
require_once(POSPI_PLUGIN_BASE . "/field_types/formio_field-richedit.class.php");
require_once(POSPI_PLUGIN_BASE . "/field_types/formio_field-taxonomy.class.php");
require_once(POSPI_PLUGIN_BASE . "/field_types/formio_field-facebook_user.class.php");
require_once(POSPI_PLUGIN_BASE . "/field_types/formio_field-twitter_user.class.php");
require_once(POSPI_PLUGIN_BASE . "/field_types/formio_field-displaylink.class.php");
require_once(POSPI_PLUGIN_BASE . "/field_types/formio_field-filesize.class.php");
// custom post data & post type inputs
require_once(POSPI_PLUGIN_BASE . "/custom_post_type.class.php");
require_once(POSPI_PLUGIN_BASE . "/field_types/formio_field-posttypes.class.php");
require_once(POSPI_PLUGIN_BASE . "/field_types/formio_field-attachments.class.php");
require_once(POSPI_PLUGIN_BASE . "/field_types/formio_field-links.class.php");
require_once(POSPI_PLUGIN_BASE . "/field_types/formio_field-users.class.php");
// admin UI helper classes
require_once(POSPI_PLUGIN_BASE . "/admin_menu.class.php");	// menu builders
require_once(POSPI_PLUGIN_BASE . "/admin_ui.class.php");	// interface builders & custom page handlers

if (is_admin()) {
	add_action(FormIOField_Posttypes::AJAX_HOOK_NAME, 'FormIOField_Posttypes::__responseHandler');

	// option to make your site's commit history visible to clients
	define('SITE_GIT_REPO_DIR', ABSPATH . 'wp-content/');
	// define('SITE_GIT_REPO_DIR', false);
	define('SITE_GIT_SUBMODULE_HISTORY', true);

	if (SITE_GIT_REPO_DIR) {
		AdminMenu::addPluginsSubmenu('Site Changelog', dirname(__FILE__) . '/commit_log.php');
	}
}

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

	wp_register_script('jcparallax', plugins_url('jcparallax/jcparallax.js', __FILE__), array('jquery'));
	wp_enqueue_script('jcparallax');
	wp_register_script('jcparallax-t', plugins_url('jcparallax/jcp-transitioninterval.js', __FILE__), array('jquery', 'jcparallax'));
	wp_enqueue_script('jcparallax-t');
	wp_register_script('jcparallax-a', plugins_url('jcparallax/jcp-animator.js', __FILE__), array('jquery', 'jcparallax-t'));
	wp_enqueue_script('jcparallax-a');
	wp_register_script('jcparallax-l', plugins_url('jcparallax/jcp-layer.js', __FILE__), array('jquery', 'jcparallax-a'));
	wp_enqueue_script('jcparallax-l');
	wp_register_script('jcparallax-vp', plugins_url('jcparallax/jcp-viewport.js', __FILE__), array('jquery', 'jcparallax-l'));
	wp_enqueue_script('jcparallax-vp');

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

	wp_register_style('jcparallax_css', plugins_url('jcparallax/jcparallax.css', __FILE__));
	wp_enqueue_style('jcparallax_css');

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
