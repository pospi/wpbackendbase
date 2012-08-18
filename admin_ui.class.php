<?php
/**
 * Admin UI builder interface
 *
 * Contains functionality for outputting your own custom pages:
 * 	- filtered admin post lists
 *
 * @package wpBackendBase
 * @author Sam Pospischil <pospi@spadgos.com>
 * @since 19/7/12
 */

if(!class_exists('WP_List_Table')){
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

abstract class AdminUI
{
	//--------------------------------------------------------------------------
	// Page helpers
	//--------------------------------------------------------------------------

	/**
	 * Add an admin post list page to the UI under the specified parent
	 * @param [string] $parentMenu    	parent menu item url/ID
	 * @param [string] $menuLabel     	label for the page in the menu
	 * @param [string] $postTypeName  	name of the post type we're adding a list page for.
	 *                                  Note that you could always display combinations of post types or other information by advanced modification of the request args in $queryModifyCb.
	 * @param [callable] $queryModifyCb callback hook to handle extra arguments to the WP_Query instance used to display this page's table. This hook is only added on the relevant page.
	 * @param [string] $pageTitle     	title of the page as displayed in the header
	 * @param [string] $capability		permission required to access the page
	 * @param [WP_List_Table] $listTable  if specified, use this custom WP_List_Table instance to render the page instead of one of the builtin one for the post type.
	 * @param [array]  $replacementActions An array mapping the row actions to HTML (usually an anchor tag) for any overridden or new actions. To remove existing actions, pass NULL as the value for that action.
	 * @param [array]  $quickEditFields An array of form input definitions to output for each column's quick edit inputs.
	 *                                  Top-level keys in this array correspond to column IDs from the list. Subarrays are keyed by metabox name and structured
	 *                                  in the same fashion as with Custom_Post_Type::add_meta_box() - note that the combination of metabox name & field name
	 *                                  is combined with an underscore to generate the final input names - so you should mirror your metabox definitions passed to add_meta_box().
	 */
	public static function addFilteredListPage($parentMenu, $menuLabel, $postTypeName, $queryModifyCb, $pageTitle = null, $capability = 'manage_options', WP_List_Table $listTable = null, $replacementActions = null, $quickEditFields = null)
	{
		$resetScreen = false;
		if (!isset($listTable)) {
			list($listTable, $resetScreen) = self::getPostTypeListTable($postTypeName);
		} else {
			$listTable->prepare_items();
		}

		AdminMenu::addSubmenu($parentMenu, $menuLabel, function() use ($pageTitle, $menuLabel, $listTable, $resetScreen) {
			echo AdminUI::renderListTablePage($listTable, isset($pageTitle) ? $pageTitle : $menuLabel);

			// restore the current screen as it was after rendering
			if ($resetScreen) {
				set_current_screen($resetScreen->id);
			}
		});

		// if we're viewing our page, hookup other view management callbacks
		if (isset($_GET['page']) && $_GET['page'] == Custom_Post_Type::get_field_id_name($menuLabel)) {
			// add the request filter for querying the table's contents
			add_filter('request', function($vars) use ($postTypeName, $queryModifyCb) {
				if ($vars['post_type'] == $postTypeName) {
					return call_user_func($queryModifyCb, $vars, $postTypeName);
				}
				return $vars;
			});

			// replace out any row actions defined
			if (isset($replacementActions)) {
				add_filter('post_row_actions', function($actions, $post) use ($replacementActions) {
					foreach ($replacementActions as $action => $output) {
						if (!isset($output)) {
							unset($actions[$action]);
							continue;
						}
						$actions[$action] = $output;
					}
					return $actions;
				}, 10, 2);
			}

			// add the quickedit filter hook if needed
			if (isset($quickEditFields)) {
				add_action('quick_edit_custom_box', function($columnName, $postType) use ($quickEditFields) {
					if (isset($quickEditFields[$columnName])) {
						AdminUI::renderQuickEditInput($quickEditFields[$columnName]);
					}
				}, 10, 2);
			}
		}
	}

	/**
	 * Add an additional taxonomy input (either hierarchical or tag-based) to the
	 * given post type's edit page.
	 *
	 * Mostly of use in adding taxonomies to wordpress Users & Attachments.
	 *
	 * @param string $postType post type to add the metabox to
	 * @param string $taxonomy taxonomy to display the options for
	 */
	public static function addTaxonomyMetabox($postType, $taxonomy)
	{
		$taxonomyObj = get_taxonomy($taxonomy);

		if ($taxonomyObj === false) {
			add_action('registered_taxonomy', function($regdTaxonomy) use ($postType, $taxonomy) {
				if ($taxonomy == $regdTaxonomy) {
					AdminUI::addTaxonomyMetabox($postType, $taxonomy);
				}
			});
			return;
		}

		// allow passing a CPT object or post type name
		if (!$postType instanceof Custom_Post_Type) {
			$postType = Custom_Post_Type::get_post_type($postType);
		}

		// read taxonomy name for the metabox title
		$label = $taxonomyObj->labels->name;

		// ensure we have the callbacks loaded
		require_once(ABSPATH . 'wp-admin/includes/meta-boxes.php');
		// add the metabox
		if ( !is_taxonomy_hierarchical($taxonomy) ) {
			$postType->raw_add_meta_box('tagsdiv-' . $taxonomy, $label, $postType->post_type_name, 'post_tags_meta_box', 'side', 'core', array(), array( 'taxonomy' => $taxonomy ));
		} else {
			$postType->raw_add_meta_box($taxonomy . 'div', $label, $postType->post_type_name, 'post_categories_meta_box', 'side', 'core', array(), array( 'taxonomy' => $taxonomy ));
		}

		// ensure the post edit script is enqueued for taxonomy UI controls
		wp_enqueue_script('post');
	}

	//--------------------------------------------------------------------------
	// UI object helpers
	//--------------------------------------------------------------------------

	/**
	 * Retrieves the appropriate WP_List_Table object for the specified post type
	 * used in drawing that post list in the admin UI.
	 *
	 * The list table will come with its items already populated so that the current screen
	 * can be reset after processing.
	 *
	 * @param  string $postTypeName post type (or object type if 'user', 'attachment') to get the table object for
	 * @return array of the list table used to draw those posts at index 0, and the
	 *               previous screen object at index 1. Wordpress' mess of globals requires that you have the current
	 *               screen set whilst displaying your table, so it is your responsibility to set_current_screen($resetScreen->id)
	 *               once you've called ->display() on your table object.
	 */
	public function getPostTypeListTable($postTypeName)
	{
		// set the current screen so we get the correct hooks executed for the table
		$resetScreen = get_current_screen();

		if (!class_exists('WP_Posts_List_Table') || !class_exists('WP_Media_List_Table') || !class_exists('WP_Users_List_Table')) {
			require_once(ABSPATH . '/wp-admin/includes/class-wp-list-table.php');
			require_once(ABSPATH . '/wp-admin/includes/class-wp-posts-list-table.php');
			require_once(ABSPATH . '/wp-admin/includes/class-wp-media-list-table.php');
			require_once(ABSPATH . '/wp-admin/includes/class-wp-users-list-table.php');
		}

		switch ($postTypeName) {
			case 'attachment':
				set_current_screen('media');
				$wp_list_table = new WP_Media_List_Table();	// :IMPORTANT: table must be constructed AFTER screen is set!
				break;
			case 'user':
				set_current_screen('users');
				$wp_list_table = new WP_Users_List_Table();
				break;
			case 'page':
				set_current_screen('pages');
				$wp_list_table = new WP_Posts_List_Table();
				break;
			default:
				set_current_screen('edit-' . $postTypeName);
				$wp_list_table = new WP_Posts_List_Table();
				break;
		}
		$wp_list_table->prepare_items();

		return array($wp_list_table, $resetScreen);
	}

	//--------------------------------------------------------------------------
	// Screen helpers
	//--------------------------------------------------------------------------

	public static function ensureInlineEdit()
	{
		wp_enqueue_script('inline-edit-post');
	}

	/**
	 * Renders out a list table with all its controls, wrapping form elements and scripts.
	 *
	 * The method attempts to enqueue the inline edit javascript for inclusion in wordpress' headers, but
	 * if you are using it later in a script you will need to add these yourself in your own init code.
	 *
	 * :WARNING: this method uses output buffering
	 */
	public static function renderListTablePage(WP_List_Table $listTable, $pageTitle)
	{
		// load the inline edit script in case of a post type which doesn't automatically include it
		add_action('admin_enqueue_scripts', array('AdminUI', 'ensureInlineEdit'));

		// draw the table
		ob_start();

		global $wp, $post_type;
?>
<h2><?php echo $pageTitle; ?></h2>
<form id="posts-filter" action="<?php echo add_query_arg( $wp->query_string, '', home_url( $wp->request ) ); ?>" method="post">
<input type="hidden" name="post_status" class="post_status_page" value="<?php echo !empty($_REQUEST['post_status']) ? esc_attr($_REQUEST['post_status']) : 'all'; ?>" />
<input type="hidden" name="post_type" class="post_type_page" value="<?php echo $post_type; ?>" />
<?php
		$listTable->display();
?>
</form>
<?php
		if ( $listTable->has_items() ) {
			$listTable->inline_edit();
		}
?>
<div id="ajax-response"></div>
<?php
		return ob_get_clean();
	}

	/**
	 * Renders out a taxonomy input.
	 * :WARNING: this method uses output buffering
	 */
	public static function getTaxonomyInput($taxonomyName, $postObj, $hierarchical = false)
	{
		// ensure we have the callbacks loaded
		require_once(ABSPATH . 'wp-admin/includes/meta-boxes.php');
		// ensure the post edit script is enqueued for taxonomy UI controls
		wp_enqueue_script('post');

		$taxonomyObj = get_taxonomy($taxonomyName);

		// allow passing a CPT object or post type name
		if (!$postType instanceof Custom_Post_Type) {
			$postType = Custom_Post_Type::get_post_type($postType);
		}

		// read taxonomy name for the metabox title
		$label = $taxonomyObj->labels->name;

		// output the metabox
		$callback = $hierarchical ? 'post_categories_meta_box' : 'post_tags_meta_box';

		ob_start();
		call_user_func($callback, $postObj, array(
			'args' => array('taxonomy' => $taxonomyName),
			'title' => $label,
		));
		return ob_get_clean();
	}

	/**
	 * Renders input sections for post 'quick edit' on the list screens
	 * @param  Array  $metaBoxDef metabox definitions to render. This is effectively an array of form groups to output - the values take
	 *                            the same format as the second argument passed to Custom_Post_Type::add_meta_box().
	 *                            An additional element '__fieldgrouppos' may be present to override the default position specified on a per-group basis.
	 * @param  string $fieldPos   CSS layout class name for the field group (left, right, center or bottom)
	 */
	public static function renderQuickEditInput(Array $metaBoxDef, $fieldPos = 'left')
	{
		Custom_Post_Type::outputSaveNonce();

		// generate form handlers for all quick edit inputs and echo them out
		// :NOTE: we don't output any data since this is filled by JS
		$forms = Custom_Post_Type::generateMetaboxForms($metaBoxDef, array());

		foreach ($forms as $metaboxId => $form) {
?>
<fieldset class="inline-edit-col-<?php echo isset($form['__fieldgrouppos']) ? $form['__fieldgrouppos'] : $fieldPos; ?> formio">
	<div class="inline-edit-col inline-edit-<?php echo $metaboxId ?>">
<?php

			echo $form->getFieldsHTML();
?>
	</div>
</fieldset>
<?php
		}
	}
}
