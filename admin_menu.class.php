<?php
/**
 * Admin menu manager class
 *
 * Wrapper functionality to more easily manage the admin menu UI hierarchy
 *
 * @author Sam Pospischil <pospi@spadgos.com>
 * @since 1/7/12
 */
abstract class AdminMenu
{
	private static $registered = false;	// has menu hook been registered with WP?

	private static $menusToAdd = array();		// array of menu titles and arguments for adding / overriding
	private static $menusToRemove = array();	// array of menu slug names to remove
	private static $menusToOverride = array();	// array of builtin (or autogenerated) menu slug names to url overrides

	//--------------------------------------------------------------------------
	// Hooks & menu handling

	public static function __handle()
	{
		// remove top-level menu entries, or subpages (we don't need to do both for nodes on the same branch)
		foreach (self::$menusToRemove as $menuSlug => $args) {
			if (count($args['subpages'])) {
				foreach ($args['subpages'] as $subMenuSlug) {
					remove_submenu_page($menuSlug, $subMenuSlug);
				}
				continue;
			}

			remove_menu_page($menuSlug);
		}

		// add all new menu entries
		foreach (self::$menusToAdd as $title => $args) {
			// build notifications output
			$notificationStr = self::getNotificationStr(isset($args['notification_count']) ? $args['notification_count'] : 0, isset($args['child_notifications']) ? $args['child_notifications'] : 0);

			if (empty($args['preexisting'])) {
				// add top-level entries not already present
				add_menu_page($title, $args['menu_title'] . $notificationStr, $args['capability'], $args['slug'], $args['function'], $args['icon_url'], $args['position']);
			} else if ($notificationStr) {
				// add notification output suffixes to preexisting menu entries
				global $menu;
				foreach ($menu as $k => &$entry) {
					if ($entry[2] == $title) {
						$entry[0] .= $notificationStr;
						break;
					}
				}
			}

			if ($args['subpages']) {
				foreach ($args['subpages'] as $sTitle => $sArgs) {
					$notificationStr = self::getNotificationStr(isset($sArgs['notification_count']) ? $sArgs['notification_count'] : 0);
					add_submenu_page(isset($args['slug']) ? $args['slug'] : Custom_Post_Type::get_field_id_name($title), $sTitle, $sArgs['menu_title'] . $notificationStr, $sArgs['capability'], $sArgs['slug'], $sArgs['function']);
				}
			}
		}

		// override any builtin (or newly created) menu entries that need direct modification
		global $submenu;
		foreach (self::$menusToOverride as $menuSlug => $submenus) {
			if (!isset($submenu[$menuSlug])) {
				// ignore if the menu didn't exist
				continue;
			}
			foreach ($submenus as $subTitle => $newData) {
				// locate the submenu entry
				foreach ($submenu[$menuSlug] as $id => $data) {
					if (Custom_Post_Type::get_field_id_name($data[0]) == Custom_Post_Type::get_field_id_name($subTitle)) {
						$notificationStr = self::getNotificationStr(isset($newData['notification_count']) ? $newData['notification_count'] : 0);
						// found it - change its options
						if (!empty($newData['preexisting'])) {
							$submenu[$menuSlug][$id][0] .= $notificationStr;
						} else {
							$submenu[$menuSlug][$id] = array(
								$newData['title'],
								$newData['capability'],
								$newData['url'],
								$newData['menu_title'] . $notificationStr,
							);
						}
						break;
					}
				}
			}
		}
	}

	private static function registerHooks()
	{
		if (!self::$registered) {
			add_action('admin_menu', 'AdminMenu::__handle');
			self::$registered = true;
		}
	}

	//--------------------------------------------------------------------------
	// Menu builder helpers

	public static function addMenu($label, $urlOrCb, $capability = 'manage_options', $position = null, $iconUrl = null, $menuTitle = null)
	{
		list($slug, $cb) = self::handleMenuCallback($label, $urlOrCb);

		self::$menusToAdd[$label] = array(
			'slug' => $slug,
			'function' => $cb,

			'capability' => $capability,
			'position' => $position,
			'menu_title' => isset($menuTitle) ? $menuTitle : $label,
			'icon_url' => $iconUrl,

			'subpages' => array(),
		);

		self::registerHooks();
	}

	public static function addSubmenu($menuLabel, $submenuLabel, $urlOrCb, $capability = 'manage_options', $menuTitle = null)
	{
		list($slug, $cb) = self::handleMenuCallback($submenuLabel, $urlOrCb);

		$subpage = array(
			'slug' => $slug,
			'function' => $cb,

			'capability' => $capability,
			'menu_title' => isset($menuTitle) ? $menuTitle : $submenuLabel,
		);

		// fill out parent menu entry if not overridden
		$menuLabel = Custom_Post_Type::get_field_id_name($menuLabel);
		if (!isset(self::$menusToAdd[$menuLabel])) {
			self::$menusToAdd[$menuLabel] = array('preexisting' => true, 'subpages' => array());
		}

		// store the new subpage
		self::$menusToAdd[$menuLabel]['subpages'][$submenuLabel] = $subpage;

		self::registerHooks();
	}

	private static function handleMenuCallback($label, $urlOrCb)
	{
		if (is_string($urlOrCb)) {
			if (file_exists($urlOrCb)) {
				// local file path - include it
				return array(Custom_Post_Type::get_field_id_name($label), function() use ($urlOrCb) {
					require_once($urlOrCb);
				});
			} else if (preg_match('/^https?:/', $urlOrCb) || preg_match('/\.php(\?|$)/', $urlOrCb)) {
				// absolute URL or internal PHP file url. don't think these will actually work, but they shouldn't be called via require() in any case!
				return array($urlOrCb, null);
			} else {
				return array(Custom_Post_Type::get_field_id_name($label), $urlOrCb);
			}
		} else {
			return array(Custom_Post_Type::get_field_id_name($label), $urlOrCb);
		}
	}

	public static function removeMenu($menuLabel)
	{
		$slug = Custom_Post_Type::get_field_id_name($menuLabel);

		self::$menusToRemove[$slug] = array('subpages' => array());
		if (isset(self::$menusToAdd[$menuLabel])) {
			unset(self::$menusToAdd[$menuLabel]);
		}

		self::registerHooks();
	}

	public static function removeSubmenu($menuLabel, $submenuLabel)
	{
		$slug = Custom_Post_Type::get_field_id_name($menuLabel);

		if (!isset(self::$menusToRemove[$slug])) {
			self::$menusToRemove[$slug] = array('subpages' => array());
		}
		self::$menusToRemove[$slug]['subpages'][] = Custom_Post_Type::get_field_id_name($submenuLabel);

		self::registerHooks();
	}

	public static function overrideSubmenu($menuSlug, $existingTitle, $newTitle, $urlOrCb, $capability = 'manage_options', $newMenuTitle = null)
	{
		if (!isset(self::$menusToOverride[Custom_Post_Type::get_field_id_name($menuSlug)])) {
			self::$menusToOverride[Custom_Post_Type::get_field_id_name($menuSlug)] = array();
		}
		self::$menusToOverride[Custom_Post_Type::get_field_id_name($menuSlug)][$existingTitle] = array(
			'title' => $newTitle,
			'menu_title' => isset($newMenuTitle) ? $newMenuTitle : $newTitle,
			'capability' => $capability,

			'url' => is_string($urlOrCb) ? $urlOrCb : null,
			'function' => is_string($urlOrCb) ? null : $urlOrCb,
		);
	}

	// more helpers...

	public static function addDashboardSubmenu($submenuLabel, $urlOrCb, $capability = 'manage_options', $menuTitle = null) {
		self::addSubmenu('index.php', $submenuLabel, $urlOrCb, $capability, $menuTitle);
	}
	public static function addPostsSubmenu($submenuLabel, $urlOrCb, $capability = 'manage_options', $menuTitle = null) {
		self::addSubmenu('edit.php', $submenuLabel, $urlOrCb, $capability, $menuTitle);
	}
	public static function addMediaSubmenu($submenuLabel, $urlOrCb, $capability = 'manage_options', $menuTitle = null) {
		self::addSubmenu('upload.php', $submenuLabel, $urlOrCb, $capability, $menuTitle);
	}
	public static function addLinksSubmenu($submenuLabel, $urlOrCb, $capability = 'manage_options', $menuTitle = null) {
		self::addSubmenu('link-manager.php', $submenuLabel, $urlOrCb, $capability, $menuTitle);
	}
	public static function addCommentsSubmenu($submenuLabel, $urlOrCb, $capability = 'manage_options', $menuTitle = null) {
		self::addSubmenu('edit-comments.php', $submenuLabel, $urlOrCb, $capability, $menuTitle);
	}
	public static function addAppearanceSubmenu($submenuLabel, $urlOrCb, $capability = 'manage_options', $menuTitle = null) {
		self::addSubmenu('themes.php', $submenuLabel, $urlOrCb, $capability, $menuTitle);
	}
	public static function addPluginsSubmenu($submenuLabel, $urlOrCb, $capability = 'manage_options', $menuTitle = null) {
		self::addSubmenu('plugins.php', $submenuLabel, $urlOrCb, $capability, $menuTitle);
	}
	public static function addUsersSubmenu($submenuLabel, $urlOrCb, $capability = 'manage_options', $menuTitle = null) {
		self::addSubmenu('users.php', $submenuLabel, $urlOrCb, $capability, $menuTitle);
	}
	public static function addToolsSubmenu($submenuLabel, $urlOrCb, $capability = 'manage_options', $menuTitle = null) {
		self::addSubmenu('tools.php', $submenuLabel, $urlOrCb, $capability, $menuTitle);
	}
	public static function addSettingsSubmenu($submenuLabel, $urlOrCb, $capability = 'manage_options', $menuTitle = null) {
		self::addSubmenu('options-general.php', $submenuLabel, $urlOrCb, $capability, $menuTitle);
	}
	public static function addPagesSubmenu($submenuLabel, $urlOrCb, $capability = 'manage_options', $menuTitle = null) {
		self::addPostTypeSubmenu('page', $submenuLabel, $urlOrCb, $capability, $menuTitle);
	}
	public static function addPostTypeSubmenu($postType, $label, $urlOrCb, $capability = 'manage_options', $menuTitle = null) {
		self::addSubmenu(self::getListUrl($postType), $label, $urlOrCb, $capability, $menuTitle);
	}

	//--------------------------------------------------------------------------
	// Custom post type submenu helpers

	public static function overridePostTypeList($postType, $label, $urlOrCb, $capability = 'manage_options', $menuTitle = null)
	{
		$ptClass = Custom_Post_Type::get_post_type($postType);
		self::overrideSubmenu(self::getListUrl($postType), 'All ' . $ptClass->get_friendly_name(true), $label, $urlOrCb, $capability, $menuTitle);
	}

	public static function overridePostTypeAddNewPage($postType, $label, $urlOrCb, $capability = 'manage_options', $menuTitle = null)
	{
		$ptClass = Custom_Post_Type::get_post_type($postType);
		self::overrideSubmenu(self::getListUrl($postType), 'Add New', $label, $urlOrCb, $capability, $menuTitle);
	}

	//--------------------------------------------------------------------------
	// notification bubble helpers

	public static function addMenuNotification($menuLabel, $count, $isChildPageIndicator = false)
	{
		$slug = Custom_Post_Type::get_field_id_name($menuLabel);

		if (!isset(self::$menusToAdd[$menuLabel])) {
			self::$menusToAdd[$menuLabel] = array(
				'preexisting' => true,
			);
		}

		if ($isChildPageIndicator) {
			self::$menusToAdd[$menuLabel]['child_notifications'] = (isset(self::$menusToAdd[$menuLabel]['child_notifications']) ? self::$menusToAdd[$menuLabel]['child_notifications'] : 0) + $count;
		} else {
			self::$menusToAdd[$menuLabel]['notification_count'] = (isset(self::$menusToAdd[$menuLabel]['notification_count']) ? self::$menusToAdd[$menuLabel]['notification_count'] : 0) + $count;
		}
	}

	public static function addSubmenuNotification($menuLabel, $submenuLabel, $count, $showInParent = true)
	{
		$parentSlug = Custom_Post_Type::get_field_id_name($menuLabel);
		$slug = Custom_Post_Type::get_field_id_name($submenuLabel);

		if (isset(self::$menusToAdd[$menuLabel]['subpages'][$submenuLabel])) {
			self::$menusToAdd[$menuLabel]['subpages'][$submenuLabel]['notification_count'] = (isset(self::$menusToAdd[$menuLabel]['subpages'][$submenuLabel]['notification_count']) ? self::$menusToAdd[$menuLabel]['subpages'][$submenuLabel]['notification_count'] : 0) + $count;
		} else if (isset(self::$menusToOverride[$parentSlug][$submenuLabel])) {
			self::$menusToOverride[$parentSlug][$submenuLabel]['notification_count'] = (isset(self::$menusToOverride[$parentSlug][$submenuLabel]['notification_count']) ? self::$menusToOverride[$parentSlug][$submenuLabel]['notification_count'] : 0) + $count;
		} else {
			self::$menusToOverride[$parentSlug][$submenuLabel] = array(
				'preexisting' => true,
				'notification_count' => $count,
			);
		}

		if ($showInParent) {
			self::addMenuNotification($menuLabel, $count, true);
		}
	}

	private static function getNotificationStr($count, $childCount = 0)
	{
		if (!$count && !$childCount) {
			return '';
		}

		$onlyChildren = $childCount && !$count;
		return ' <span class="update-plugins ' . ($onlyChildren ? 'faded ' : '') . 'count-' . ($count+$childCount) . '"><span class="plugin-count"' . ($childCount ? ' title="'.$childCount.' items require your attention in child pages"' : '') . '>' . ($childCount ? ($count ? "$count ($childCount)" : $childCount) : $count) . '</span></span>';
	}

	//--------------------------------------------------------------------------
	// url helpers

	public static function getNewUrl($postType)
	{
		if ($postType == 'attachment') {
			return 'media-new.php';
		} else if ($postType == 'link') {
			return 'link-add.php';
		} else if ($postType == 'user') {
			return 'user-new.php';
		}
		return 'post-new.php?post_type=' . $postType;
	}

	public static function getListUrl($postType)
	{
		if ($postType == 'attachment') {
			return 'upload.php?';
		} else if ($postType == 'user') {
			return 'users.php?';
		}
		return 'edit.php?post_type=' . $postType;
	}

	public static function getEditUrl($post)
	{
		if (isset($post->link_id)) {
			// post is a link
			return 'link.php?action=edit&link_id=' . $post->link_id;
		} else if ($post instanceof WP_User) {
			// post is a user
			return 'user-edit.php?user_id=' . $post->ID;
		} else if ($post) {
			// post is an attachment
			return 'media.php?action=edit&attachment_id=' . $post->ID;
		}
		// post is a normal post or custom post type
		return 'post.php?action=edit&post=' . $post->ID;
	}

	public static function getTaxonomyManagementUrl($postType, $taxonomy)
	{
		return 'edit-tags.php?taxonomy=' . $taxonomy . '&post_type=' . $postType;
	}
}
