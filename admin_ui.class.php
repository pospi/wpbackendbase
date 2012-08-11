<?php
/**
 * Admin UI builder interface
 *
 * Contains functionality for outputting your own custom pages:
 * 	- filtered admin post lists
 *
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
	 */
	public static function addFilteredListPage($parentMenu, $menuLabel, $postTypeName, $queryModifyCb, $pageTitle = null, $capability = 'manage_options', WP_List_Table $listTable = null)
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

		// if we're viewing our page, add the query filter to the request
		if (isset($_GET['page']) && $_GET['page'] == Custom_Post_Type::get_field_id_name($menuLabel)) {
			add_filter('request', function($vars) use ($postTypeName, $queryModifyCb) {
				if ($vars['post_type'] == $postTypeName) {
					return call_user_func($queryModifyCb, $vars, $postTypeName);
				}
				return $vars;
			});
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
		if (!function_exists('_get_list_table')) {
			// not in admin, can't do it.
			return null;
		}

		// set the current screen so we get the correct hooks executed for the table
		$resetScreen = get_current_screen();

		switch ($postTypeName) {
			case 'attachment':
				set_current_screen('media');
				$wp_list_table = _get_list_table('WP_Media_List_Table');	// :IMPORTANT: table must be constructed AFTER screen is set!
				break;
			case 'user':
				set_current_screen('users');
				$wp_list_table = _get_list_table('WP_Users_List_Table');
				break;
			case 'page':
				set_current_screen('pages');
				$wp_list_table = _get_list_table('WP_Posts_List_Table');
				break;
			default:
				set_current_screen('edit-' . $postTypeName);
				$wp_list_table = _get_list_table('WP_Posts_List_Table');
				break;
		}
		$wp_list_table->prepare_items();

		return array($wp_list_table, $resetScreen);
	}

	//--------------------------------------------------------------------------
	// Screen helpers
	//--------------------------------------------------------------------------

	/**
	 * Renders out a list table with all its controls, wrapping form elements and scripts
	 * :WARNING: this method uses output buffering
	 */
	public static function renderListTablePage(WP_List_Table $listTable, $pageTitle)
	{
		ob_start();
		// output the table
		wp_enqueue_script('inline-edit-post');

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
}
