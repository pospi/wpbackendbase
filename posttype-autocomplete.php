<?php
/**
 * Autocomplete handler for post type, attachment & link form fields
 *
 * @author Sam Pospischil <pospi@spadgos.com>
 */

// boot up wordpress in admin mode with all plugins
require('../../../wp-load.php');
require('../../../wp-admin/includes/admin.php');
do_action('admin_init');

// load args
$postType = isset($_GET['pt']) ? $_GET['pt'] : 'post';
$metaBox = $_GET['form'] ? $_GET['form'] : null;
$metaKey = $_GET['field'] ? $_GET['field'] : null;

// load post type class & ensure form inputs have been setup
$postType = Custom_Post_Type::get_post_type($postType);
$postType->init_form_handlers();

// load field by name
$field = $postType->formHandlers[$metaBox]->getField($metaKey);

// run field query & output it
header('Content-type: application/json');
echo json_encode(array_values($field->runRequest($_GET['term'])));
