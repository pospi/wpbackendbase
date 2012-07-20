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
	/**
	 * Add an admin post list page to the UI under the specified parent
	 * @param [string] $parentMenu    	parent menu item url/ID
	 * @param [string] $menuLabel     	label for the page in the menu
	 * @param [string] $postTypeName  	name of the post type we're adding a list page for.
	 *                                  Note that you could always display combinations of post types or other information by advanced modification of the request args in $queryModifyCb.
	 * @param [callable] $queryModifyCb callback hook to handle extra arguments to the WP_Query instance used to display this page's table. This hook is only added on the relevant page.
	 * @param [string] $pageTitle     	title of the page as displayed in the header
	 * @param [string] $capability		permission required to access the page
	 * @param [WP_List_Table] $listTable  if specified, use this custom WP_List_Table instance to render the page instead of one of the builtin ones.
	 */
	public static function addFilteredListPage($parentMenu, $menuLabel, $postTypeName, $queryModifyCb, $pageTitle = null, $capability = 'manage_options', WP_List_Table $listTable = null)
	{
		AdminMenu::addSubmenu($parentMenu, $menuLabel, function() use ($postTypeName, $queryModifyCb, $pageTitle, $menuLabel)
		{
			// store the current screen variable, then set it to one the list understands
			$resetScreen = false;
			if (isset($listTable)) {
				$wp_list_table = $listTable;
			} else {
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
			}
			$wp_list_table->prepare_items();

			// output the table
			wp_enqueue_script('inline-edit-post');

			global $wp, $post_type;
?>
<h2><?php echo isset($pageTitle) ? $pageTitle : $menuLabel; ?></h2>
<form id="posts-filter" action="<?php echo add_query_arg( $wp->query_string, '', home_url( $wp->request ) ); ?>" method="post">
<input type="hidden" name="post_status" class="post_status_page" value="<?php echo !empty($_REQUEST['post_status']) ? esc_attr($_REQUEST['post_status']) : 'all'; ?>" />
<input type="hidden" name="post_type" class="post_type_page" value="<?php echo $post_type; ?>" />
<?php
			$wp_list_table->display();
?>
</form>
<?php
			if ( $wp_list_table->has_items() ) {
				$wp_list_table->inline_edit();
			}
?>
<div id="ajax-response"></div>
<?php
			// restore the current screen as it was
			if ($resetScreen) {
				set_current_screen($resetScreen->id);
			}
		});

		// if we're viewing our approval page, add a filter to the query SQL to filter out the approved posts
		if (isset($_GET['page']) && $_GET['page'] == Custom_Post_Type::get_field_id_name($menuLabel)) {
			add_filter('request', function($vars) use ($postTypeName, $queryModifyCb) {
				if ($vars['post_type'] == $postTypeName) {
					return call_user_func($queryModifyCb, $vars, $postTypeName);
				}
				return $vars;
			});
		}
	}
}