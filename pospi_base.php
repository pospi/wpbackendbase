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
}

// Custom Javascript
add_action('admin_enqueue_scripts',function(){
	$scheme = is_ssl() ? 'https://' : 'http://';

	// builtin jQuery is too old to work with our scripts, so force an update :/
	wp_deregister_script("jquery");
	wp_deregister_script("jquery-ui");
	wp_deregister_script("jquery-ui-core");
	wp_deregister_script("jquery-ui-widget");
	wp_deregister_script("jquery-ui-mouse");
	wp_deregister_script("jquery-ui-accordion");
	wp_deregister_script("jquery-ui-autocomplete");
	wp_deregister_script("jquery-ui-slider");
	wp_deregister_script("jquery-ui-tabs");
	wp_deregister_script("jquery-ui-sortable");
	wp_deregister_script("jquery-ui-draggable");
	wp_deregister_script("jquery-ui-droppable");
	wp_deregister_script("jquery-ui-selectable");
	wp_deregister_script("jquery-ui-datepicker");
	wp_deregister_script("jquery-ui-resizable");
	wp_deregister_script("jquery-ui-dialog");
	wp_deregister_script("jquery-ui-button");
	wp_register_script("jquery", "{$scheme}ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js", array(), '1.8.2', false);
	wp_enqueue_script('jquery');
	wp_register_script('jqNoConflict', plugins_url('jquery.noconflict.js', __FILE__), array('jquery'), null, false);
	wp_enqueue_script('jqNoConflict');
	wp_register_script("jquery-ui", "{$scheme}ajax.googleapis.com/ajax/libs/jqueryui/1.8.23/jquery-ui.min.js", array('jquery', 'jqNoConflict'), '1.8.23', true);
	wp_enqueue_script('jquery-ui');

	wp_register_script("jquery_tokeninput", plugins_url('formio/lib/jquery-tokeninput/src/jquery.tokeninput.js', __FILE__), array('jquery-ui'), '1.6.0', true);
	wp_enqueue_script("jquery_tokeninput");
	wp_register_script('formio', plugins_url('formio/formio.js', __FILE__), array('jquery_tokeninput'), null, true);
	wp_enqueue_script('formio');

	wp_register_script('jcparallax', plugins_url('jcparallax/jcparallax.js', __FILE__), array('jquery'), null, true);
	wp_enqueue_script('jcparallax');
	wp_register_script('jcparallax-t', plugins_url('jcparallax/jcp-transitioninterval.js', __FILE__), array('jquery', 'jcparallax'), null, true);
	wp_enqueue_script('jcparallax-t');
	wp_register_script('jcparallax-a', plugins_url('jcparallax/jcp-animator.js', __FILE__), array('jquery', 'jcparallax-t'), null, true);
	wp_enqueue_script('jcparallax-a');
	wp_register_script('jcparallax-l', plugins_url('jcparallax/jcp-layer.js', __FILE__), array('jquery', 'jcparallax-a'), null, true);
	wp_enqueue_script('jcparallax-l');
	wp_register_script('jcparallax-vp', plugins_url('jcparallax/jcp-viewport.js', __FILE__), array('jquery', 'jcparallax-l'), null, true);
	wp_enqueue_script('jcparallax-vp');

	wp_register_script('pospi-admin-js', plugins_url('pospi_wp_admin.js', __FILE__), array('formio'), null, true);
	wp_enqueue_script('pospi-admin-js');
});

// Custom Css
add_action( 'admin_init', function() {
	$scheme = is_ssl() ? 'https://' : 'http://';

	wp_register_style('jquery-ui-base', "{$scheme}ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/themes/base/jquery-ui.css");
	wp_enqueue_style('jquery-ui-base');

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
