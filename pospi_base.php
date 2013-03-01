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
require_once(POSPI_PLUGIN_BASE . "/field_types/formio_field-youtube_user.class.php");
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
	add_action(FormIOField_Posttypes::AJAX_HOOK_NAME, array('FormIOField_Posttypes', '__responseHandler'));
}

// Custom Javascript
add_action('admin_enqueue_scripts',function(){
	wp_enqueue_script('jquery');	// ensure that jQuery has loaded before our custom one, since much of the backend depends on it

	// :NOTE: you can't include two versions of jQuery UI in Wordpress as multiple instances were only fixed in 1.8, and WP currently runs 1.7.something
	//	we include this into the base library and then reference important parts out into our own jQuery instance.
	$coreJqueryScripts = array('jquery',
		'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-mouse', 'jquery-ui-position', 'jquery-ui-accordion', 'jquery-ui-autocomplete',
		'jquery-ui-slider', 'jquery-ui-tabs', 'jquery-ui-dialog', 'jquery-ui-button', 'jquery-ui-datepicker', 'jquery-ui-progressbar',
		'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable', 'jquery-ui-selectable', 'jquery-ui-resizable');

	foreach ($coreJqueryScripts as $i => $script) {
		if (!wp_script_is($script, 'registered')) {
			unset($coreJqueryScripts[$i]);
			continue;
		}
		wp_enqueue_script($script);
	}

	$scheme = is_ssl() ? 'https://' : 'http://';

	wp_register_script('jqnc-pospi-pre', plugins_url('jquery.noconflict.pre.js', __FILE__), $coreJqueryScripts, null, false);
	wp_enqueue_script('jqnc-pospi-pre');

	// builtin jQuery is too old to work with our scripts, so include our own version
	wp_register_script("jquery-pospi", "{$scheme}ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js", array('jqnc-pospi-pre'), '1.8.2', false);
	wp_enqueue_script('jquery-pospi');

	wp_register_script("jquery-tokeninput", plugins_url('formio/lib/jquery-tokeninput/src/jquery.tokeninput.js', __FILE__), array('jquery-pospi'), '1.6.0', true);
	wp_enqueue_script("jquery-tokeninput");
	wp_register_script("jquery-sortable", plugins_url('formio/lib/html5sortable/jquery.sortable.js', __FILE__), array('jquery-tokeninput'), '0.1', true);
	wp_enqueue_script("jquery-sortable");
	wp_register_script('formio', plugins_url('formio/formio.js', __FILE__), array('jquery-tokeninput', "jquery-sortable"), null, true);
	wp_enqueue_script('formio');

	wp_register_script('jcparallax', plugins_url('jcparallax/jcparallax.js', __FILE__), array('jquery-pospi'), null, true);
	wp_enqueue_script('jcparallax');
	wp_register_script('jcparallax-t', plugins_url('jcparallax/jcp-transitioninterval.js', __FILE__), array('jcparallax'), null, true);
	wp_enqueue_script('jcparallax-t');
	wp_register_script('jcparallax-a', plugins_url('jcparallax/jcp-animator.js', __FILE__), array('jcparallax-t'), null, true);
	wp_enqueue_script('jcparallax-a');
	wp_register_script('jcparallax-l', plugins_url('jcparallax/jcp-layer.js', __FILE__), array('jcparallax-a'), null, true);
	wp_enqueue_script('jcparallax-l');
	wp_register_script('jcparallax-vp', plugins_url('jcparallax/jcp-viewport.js', __FILE__), array('jcparallax-l'), null, true);
	wp_enqueue_script('jcparallax-vp');

	wp_register_script('pospi-admin-js', plugins_url('pospi_wp_admin.js', __FILE__), array('formio', 'jcparallax-vp'), null, true);
	wp_enqueue_script('pospi-admin-js');

	wp_register_script('jqnc-pospi', plugins_url('jquery.noconflict.js', __FILE__), array('pospi-admin-js'), null, false);
	wp_enqueue_script('jqnc-pospi');
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
