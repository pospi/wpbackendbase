<?php
/**
 * Custom post type helper class
 *
 * Metabox datatype inputs are now controlled by FormIO - use formIO datatypes as field types in your add_meta_box options.
 *
 * Originally from http://wp.tutsplus.com/tutorials/creative-coding/custom-post-type-helper-class/
 * @author Gijs Jorissen
 *
 * @package wpBackendBase
 * @author Sam Pospischil <pospi@spadgos.com>
 */
class Custom_Post_Type
{
	const ERROR_SESSION_STORAGE = 'custom_post_errors';
	const NONCE_FIELD_NAME = 'custom_post_type';
	const META_POST_KEY = 'custom_meta';

	const TAX_POST_KEY = 'tax_input';			// used by media post types to reimplement taxonomies
	const TAX_DEFAULT_KEY = 'post_category';	// post variable for 'category' taxonomy is different

	const IS_USER_SAVE_FLAG = 'custom_post_type_is_user';

	public $post_type_name;
	public $post_type_name_plural;
	public $post_type_label;
	public $post_type_label_plural;
	public $post_type_args;
	public $post_type_superclass;

	public $meta_fields = array();
	public $taxonomies = array();

	public $list_columns = array();			// custom columns for admin lists
	public $removed_list_columns = array();
	private $displayActionPriority = 10;	// :NOTE: because we run all our columns together, they must all be next to one another

	public $row_actions = array();			// custom row actions for admin list columns

	public $formHandlers = array();	// FormIO instances used to render and validate each metabox

	private $saveCallbacks = array();	// user-defined callbacks for prehandling post metadata before it is saved

	public $statusTransitions = array();	// user-defined post status transition checks

	private static $postTypeRegister = array();	// all post types created, used to load them for record save / load handling

	// wordpress hooks to use. Overridden in some builtin cases when managing core WP datatypes.
	private $saveHooks = array('save_post');

	/* Class constructor */
	public function __construct( $name, $args = array(), $superClass = null )
	{
		// Set some important variables
		if (is_array($name)) {
			$this->post_type_name		= self::get_field_id_name($name[0]);
			$this->post_type_name_plural = self::get_field_id_name($name[1]);
			$this->post_type_label = $name[0];
			$this->post_type_label_plural = $name[1];
		} else {
			$this->post_type_name		= self::get_field_id_name($name);
			$this->post_type_label = $name;
		}
		$this->post_type_args = $args;

		if ($superClass) {
			$this->setSuperclass($superClass);
		}

		// Add action to register the post type, if the post type doesnt exist
		if( $this->post_type_name != 'user' && $this->post_type_name != 'attachment' && ! post_type_exists( $this->post_type_name ) )
		{
			add_action( 'init', array( &$this, 'register_post_type' ) );
		}

		// override some values when managing user metadata
		if ($this->post_type_name == 'user') {
			$this->saveHooks = array('profile_update', 'user_register');

			// also bind an action for adding an input to the user registration page.
			// :SHONK: The only place it can be done is in the form tag - hope to god nobody else is depending on this hack!!
			add_action('user_new_form_tag', function() {
				echo ">";
				Custom_Post_Type::outputSaveNonce();
				echo "<input type=\"hidden\" name=\"" . Custom_Post_Type::IS_USER_SAVE_FLAG . "\" value=\"1\" />";
				echo "<span></span";	// close off the trailing tag from the user-new template
			}, PHP_INT_MAX);
		}

		// Listen for the save post hook
		$this->save();

		// listen for any errors we might encounter
		add_action('admin_notices', array($this, 'showErrorNotifications'));

		// store ourselves in the loaded post types register so we can be retrieved again
		self::$postTypeRegister[$this->post_type_name] = $this;
	}

	/* Method which registers the post type */
	public function register_post_type()
	{
		//Capitilize the words and make it plural
		$name 	= $this->get_friendly_name();
		$plural = $this->get_friendly_name(true);

		// We set the default labels based on the post type name and plural. We overwrite them with the given labels.
		$labels = array_merge(

			// Default
			array(
				'name' 					=> _x( $plural, 'post type general name' ),
				'singular_name' 		=> _x( $name, 'post type singular name' ),
				'add_new' 				=> _x( 'Add New', strtolower( $name ) ),
				'add_new_item' 			=> __( 'Add New ' . $name ),
				'edit_item' 			=> __( 'Edit ' . $name ),
				'new_item' 				=> __( 'New ' . $name ),
				'all_items' 			=> __( 'All ' . $plural ),
				'view_item' 			=> __( 'View ' . $name ),
				'search_items' 			=> __( 'Search ' . $plural ),
				'not_found' 			=> __( 'No ' . strtolower( $plural ) . ' found'),
				'not_found_in_trash' 	=> __( 'No ' . strtolower( $plural ) . ' found in Trash'),
				'parent_item_colon' 	=> '',
				'menu_name' 			=> $plural
			),

			// Given labels
			isset($this->post_type_args['labels']) ? $this->post_type_args['labels'] : array()

		);

		// Same principle as the labels. We set some default and overwite them with the given arguments.
		$args = array_merge(

			// Default
			array(
				'label' 				=> $plural,
				'labels' 				=> $labels,
				'public' 				=> true,
				// 'publicly_queryable' => true,	// these all default to the setting of 'public'
				// 'show_ui' 				=> true,
				// 'show_in_nav_menus' 	=> true,
				// 'show_in_menu'		=> true,
				'query_var'			=> true,
				'rewrite'			=> true,
				'supports' 			=> array( 'title', 'editor' ),
				'_builtin' 			=> false,

				'hierarchical'		=> false,
			),

			// Given args
			$this->post_type_args

		);

		// Register the post type
		register_post_type( $this->post_type_name, $args );
	}

	/**
	 * Enables post type inheritance whilst being able to manipulate and view
	 * child post types from within their parent lists.
	 * :NOTE: that once set, superclass metadata cannot be removed as it will
	 * 		  already have been hooked into wordpress
	 */
	private function setSuperclass($superPostTypeName)
	{
		$this->post_type_superclass = $superPostTypeName;

		// register all metaboxes from the superclass with our own
		$superClass = self::get_post_type($superPostTypeName);
		if ($superClass->meta_fields) {
			foreach ($superClass->meta_fields as $title => $fields) {
				$this->add_meta_box($title, $fields);
			}
		}
	}

	//------------------------------------------------------------------------------------------------------------------------------------------------------------------
	// data configuration
	//------------------------------------------------------------------------------------------------------------------------------------------------------------------

	/* Method to attach the taxonomy to the post type */
	public function add_taxonomy( $name, $args = array(), $labels = array() )
	{
		if( ! empty( $name ) )
		{
			// We need to know the post type name, so the new taxonomy can be attached to it.
			$post_type_name = $this->post_type_name;

			// Taxonomy properties
			if (is_array($name)) {
				list($name, $plural) = $name;
			} else {
				$plural = $name . 's';
			}
			$taxonomy_name		= self::get_field_id_name($name);
			$taxonomy_labels	= $labels;
			$taxonomy_args		= $args;

			// register the taxonomy so we know to read its data for posts
			$this->taxonomies[] = $taxonomy_name;

			$tax_exists = taxonomy_exists( $taxonomy_name );

			if( !$tax_exists )
			{
				//Capitilize the words and make it plural
				$name 		= self::get_field_friendly_name($name);
				$plural 	= self::get_field_friendly_name($plural);

				// Default labels, overwrite them with the given labels.
				$labels = array_merge(

					// Default
					self::makeTaxonomyLabels($name, $plural),

					// Given labels
					$taxonomy_labels

				);

				// Default arguments, overwitten with the given arguments
				$args = array_merge(

					// Default
					array(
						'label'					=> $plural,
						'labels'				=> $labels,
						'public' 				=> true,
						'show_ui' 				=> true,
						'show_in_nav_menus' 	=> true,
						'_builtin' 				=> false,
					),

					// Given
					$taxonomy_args

				);

				// Add the taxonomy to the post type
				add_action( 'init',
					function() use( $taxonomy_name, $post_type_name, $args )
					{
						register_taxonomy( $taxonomy_name, $post_type_name, $args );
					}
				);
			}
			else
			{
				add_action( 'init',
					function() use( $taxonomy_name, $post_type_name )
					{
						register_taxonomy_for_object_type( $taxonomy_name, $post_type_name );
					}
				);
			}
		}
	}

	public static function makeTaxonomyLabels($name, $plural = null)
	{
		if (!isset($plural)) {
			$plural = $name . 's';
		}
		return array(
			'name' 					=> _x( $plural, 'taxonomy general name' ),
			'singular_name' 		=> _x( $name, 'taxonomy singular name' ),
		    'search_items' 			=> __( 'Search ' . $plural ),
		    'all_items' 			=> __( 'All ' . $plural ),
		    'parent_item' 			=> __( 'Parent ' . $name ),
		    'parent_item_colon' 	=> __( 'Parent ' . $name . ':' ),
		    'edit_item' 			=> __( 'Edit ' . $name ),
		    'update_item' 			=> __( 'Update ' . $name ),
		    'add_new_item' 			=> __( 'Add New ' . $name ),
		    'new_item_name' 		=> __( 'New ' . $name . ' Name' ),
		    'menu_name' 			=> __( $plural ),
		);
	}

	/* Attaches meta boxes to the post type */
	public function add_meta_box( $title, $fields = array(), $context = 'normal', $priority = 'default', $cbArgs = null )
	{
		if( ! empty( $title ) )
		{
			// We need to know the Post Type name again
			$post_type_name = $this->post_type_name;

			// Meta variables
			$box_id 		= self::get_field_id_name($title);
			$box_title		= self::get_field_friendly_name($title);
			$box_context	= $context;
			$box_priority	= $priority;

			// store the meta field so we know to save it
			$this->meta_fields[$title] = $fields;

			if (!function_exists('add_action')) {
				return;	// not in wordpress, so can't be in admin, so no need to register the metabox hooks
			}

			$that = $this;
			$metaboxDrawCb = null;

			// output the metabox for our post type on the admin screen
			if ($this->post_type_name != 'user' && (!self::$LEGACY_ATTACHMENT_EDITOR || (self::$LEGACY_ATTACHMENT_EDITOR && $this->post_type_name != 'attachment'))) {

				// --- STANDARD POST TYPE SCREEN ---

				$metaboxDrawCb = function( $post, $data ) use ($that) {
					// read metabox ID
					$metaBoxId = $data['id'];

					// Get the saved values
					$meta = $that->get_post_meta( $post->ID );

					// Write a nonce field for some validation
					Custom_Post_Type::outputSaveNonce();
					// add a hidden input to indicate a user is being update if necessary - this is not determinable easily otherwise
					if ($that->post_type_name == 'user') {
						echo "<input type=\"hidden\" name=\"" . Custom_Post_Type::IS_USER_SAVE_FLAG . "\" value=\"1\" />";
					}

					// draw the box's inputs
					echo $that->get_metabox_form_output($metaBoxId, $meta, $post);
				};
			} else if ($this->post_type_name != 'attachment') {

				// --- USER PROFILE EDITOR ---

				$metaboxDrawCb = function($user) use ($box_id, $box_title, $that) {
					// Write a nonce field for some validation
					Custom_Post_Type::outputSaveNonce();
					// add a hidden input to indicate a user is being update if necessary - this is not determinable easily otherwise
					if ($that->post_type_name == 'user') {
						echo "<input type=\"hidden\" name=\"" . Custom_Post_Type::IS_USER_SAVE_FLAG . "\" value=\"1\" />";
					}

					// get the logged in user's roles for assigning form classes
					$roles = array();
					if (!empty( $user->roles )) {
						foreach ($user->roles as $role) {
							$roles[] = $role;
						}
					}

					// Get the saved values
					$meta = $that->get_post_meta( $user->ID );

					// output the form section
					echo '<div class="formio ' . $box_id . ' ' . implode(' ', $roles) . '"><h3>' . $box_title . '</h3>';

					echo $that->get_metabox_form_output($box_id, $meta, $user);

					echo '</div>';
				};
			} else {

				// --- ATTACHMENT EDITOR FOR PRE-3.5 ---

				$metaboxDrawCb = function($formFields, $post) use ($box_id, $box_title, $that) {
					// Write a nonce field for some validation
					Custom_Post_Type::outputSaveNonce();
					// add a hidden input to indicate a user is being update if necessary - this is not determinable easily otherwise
					if ($that->post_type_name == 'user') {
						echo "<input type=\"hidden\" name=\"" . Custom_Post_Type::IS_USER_SAVE_FLAG . "\" value=\"1\" />";
					}

					// Get the saved values
					$meta = $that->get_post_meta( $post->ID );

					// build a string for our custom fields HTML
					$formStr = '<div class="formio ' . $box_id . '">';

					$formStr .= '<h3>' . $box_title . '</h3>';
					$formStr .= $that->get_metabox_form_output($box_id, $meta, $post);

					$formStr .= '</div>';

					// remove the builtin (useless) taxonomy inputs
					foreach ($that->taxonomies as $category) {
						unset($formFields[$category]);
					}
					// send back as the form footer - hopefully this is not widely used by other plugins
					$formFields['_final'] = isset($formFields['_final']) ? ($formFields['_final'] . $formStr) : $formStr;

					return $formFields;
				};
			}

			$this->raw_add_meta_box($box_id, $box_title, $post_type_name, $metaboxDrawCb, $box_context, $box_priority, $fields, $cbArgs);
		}
	}

	public function raw_add_meta_box($box_id, $box_title, $post_type_name, $box_cb, $box_context, $box_priority, $fields, $cbArgs = null)
	{
		// output the metabox for our post type on the admin screen
		if ($this->post_type_name != 'user' && (!self::$LEGACY_ATTACHMENT_EDITOR || (self::$LEGACY_ATTACHMENT_EDITOR && $this->post_type_name != 'attachment'))) {

			// --- STANDARD POST TYPE SCREEN ---

			add_action('admin_init', function() use( $box_id, $box_title, $post_type_name, $box_context, $box_priority, $fields, $box_cb )
			{
				add_meta_box(
					$box_id,
					$box_title,
					$box_cb,
					$post_type_name,
					$box_context,
					$box_priority,
					array( $fields )
				);
			});

			// also set the metabox's class for formIO input styling
			add_filter("postbox_classes_{$this->post_type_name}_{$box_id}", function($metaboxClasses) {
				$metaboxClasses[] = 'formio';
				return $metaboxClasses;
			});
		} else if ($this->post_type_name != 'attachment') {

			// --- USER PROFILE EDITOR ---

			$userUpdateCb = function($user) use ($box_cb, $cbArgs) {
				if (isset($cbArgs)) {
					call_user_func($box_cb, $user, array('args' => $cbArgs));
				} else {
					call_user_func($box_cb, $user);
				}
			};
			add_action('show_user_profile', $userUpdateCb);
			add_action('edit_user_profile', $userUpdateCb);
		} else {

			// --- ATTACHMENT EDITOR FOR PRE-3.5 ---

			add_filter('attachment_fields_to_edit', function($submittedData, $attach) use ($box_cb, $cbArgs) {
				if (isset($cbArgs)) {
					return call_user_func($box_cb, $attach, array('args' => $cbArgs));
				} else {
					return call_user_func($box_cb, $submittedData, $attach);
				}
			}, 10, 2);
		}
	}

	/**
	 * add columns to the post type's admin list page
	 *
	 * @param string $colId          	ID of the column in the post list. Must be globally unique.
	 * @param string $colLabel       	label of the column
	 * @param callable $displayHandler 	callback to handle display of the column's cells.
	 *                                  Accepts column ID, post ID and Custom_Post_Type instance as parameters.
	 * @param callable $orderHandler	callback to handle ordering of the column. When null, the column is not orderable.
	 *                               	Accepts a reference to the array of arguments to WP_Query for premodification.
	 * @param array  $filterHandlers	Array mapping GET parameter names to 'request' action hooks. These callbacks work in the same way as $orderHandler,
	 *                                 	but are executed based on the presence of the GET parameter indicated.
	 * @param array  $quickEditFields	An array of form input definitions to output for each column's quick edit inputs.
	 *                                  Values are keyed by metabox name and structured in the same fashion as with add_meta_box() - note that the combination
	 *                                  of metabox name & field name is combined with an underscore to generate the final input names - so you should mirror your
	 *                                  metabox definitions passed to add_meta_box() if you wish to manipulate the same attributes.
	 * @param int	 $displayActionPriority	priority parameter for manage_posts_custom_column hook used in column cell output.
	 */
	public function add_list_column($colId, $colLabel, $displayHandler, $orderHandler = null, $filterHandlers = null, $quickEditFields = null, $displayActionPriority = 10)
	{
		// for attachments - output quick edit data in our table since WP doesn't do this by default
		if ($this->post_type_name == 'attachment') {
			static $inlineDataOutput = false;
			if (!$inlineDataOutput) {
				// ensure the quick edit javascript is present
				add_action('admin_enqueue_scripts', array('AdminUI', 'ensureInlineEdit'));

				// output the quickedit template from the post ListTable class
				add_action('admin_footer', function() {
					list($table, $screen) = AdminUI::getPostTypeListTable('post');
					$table->inline_edit();
					if ($screen) {
						set_current_screen($screen->id);
					}
				});

				// output the quick edit data for each post in a hidden column
				$this->list_columns['hidden'] = array(
					'label' => '',
					'display' => function($colId, $postId, $postType) {
						get_inline_data(get_post($postId));
					}
				);
				$inlineDataOutput = true;
			}
		}

		// register the list column for adding when we're hooked
		$this->list_columns[$colId] = array(
			'label' => $colLabel,
			'display' => $displayHandler,
			'order' => $orderHandler,
			'filters' => $filterHandlers,
			'quickEdit' => $quickEditFields,
		);

		// set the column start offset for all our column output
		$this->displayActionPriority = $displayActionPriority;
	}

	public function remove_list_column($colId)
	{
		$this->removed_list_columns[] = $colId;
	}

	/**
	 * Adds a custom row action to the default list screen for this post type.
	 * Row actions are HTML snippets (usually links) which show up when the row is hovered.
	 * @param string   $actionName		key for the action ('edit', 'view', 'trash' are usually reserved)
	 * @param callable $actionRenderCB	a callback for rendering the action string for this action.
	 *                                  Receives the post object being rendered as a parameter.
	 */
	public function add_row_action($actionName, $actionRenderCB)
	{
		$this->row_actions[$actionName] = $actionRenderCB;
	}

	public function remove_row_action($actionName)
	{
		$this->add_row_action($actionName, null);
	}

	/**
	 * Add a custom callback to be called when records of this type are saved.
	 * The callback accepts the ID of the post being saved, the post's metadata
	 * array and the Custom_Post_Type instance as parameters.
	 *
	 * The metadata array should be modified as needed to change it before it is saved
	 * later by update_post_meta().
	 */
	public function add_save_handler($callback)
	{
		$this->saveCallbacks[] = $callback;
	}

	/**
	 * Adds a callback for performing some custom logic when the post's status is updated.
	 *
	 * The status arguments may be '*' to match any status, or any of:
	 *  'new', 'auto-draft', 'draft', 'pending', 'publish', 'future', 'private', 'inherit' or 'trash'
	 * and posts generally transition in roughly that order during their lifetime in the system.
	 *
	 * @param string $newStatus old post status
	 * @param string $oldStatus new post status
	 * @param callable $callback  callback to execute. Callbacks receive the new status, old status and post object as parameters.
	 */
	public function add_status_transition($newStatus, $oldStatus, $callback)
	{
		if (!isset($this->statusTransitions[$oldStatus][$newStatus])) {
			$this->statusTransitions[$oldStatus][$newStatus] = array();
		}
		$this->statusTransitions[$oldStatus][$newStatus][] = $callback;
	}

	//------------------------------------------------------------------------------------------------------------------------------------------------------------------
	// post data handling
	//------------------------------------------------------------------------------------------------------------------------------------------------------------------

	/* Listens for when the post type being saved */
	public function save()
	{
		if (!function_exists('add_action')) {
			return;	// not in wordpress, so can't be in admin, so no need to register the metabox hooks
		}

		// Need the post type name again
		$post_type_name = $this->post_type_name;

		// define save handler
		$that = $this;
		$saveMethod = function($postId, $notUsed1 = null, $notUsed2 = null) use( $that, $post_type_name )
		{
			// determine the type of posts, since we want to be able to target similar objects as posts...
			$thisPt = get_post_type($postId);
			$creatingUser = false;
			$post = get_post($postId);
			if ($thisPt == 'revision' || $thisPt == 'attachment') {
				$creatingUser = $thisPt == 'revision';		// :NOTE: users are created with attached posts. 'revision' means the user is new, 'attachment' means they are being updated.
				$thisPt = get_post_type($post->post_parent);
			}
			if ((!$thisPt || $thisPt === 'post') && !empty($_POST[Custom_Post_Type::IS_USER_SAVE_FLAG])) {
				$thisPt = 'user';
			}

			// If doing the wordpress autosave function, ignore...
			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

			// abort on bad nonce
			if ( isset($_POST[Custom_Post_Type::NONCE_FIELD_NAME]) && ! wp_verify_nonce( $_POST[Custom_Post_Type::NONCE_FIELD_NAME], plugin_basename(__FILE__) ) ) return;

			// ignore quick edit actions as full metadata won't be sent with these
			if (isset($_POST['action']) && $_POST['action'] == 'inline-save') return;

			if( $postId && $thisPt == $post_type_name && isset($_POST[Custom_Post_Type::META_POST_KEY]) ) {
				// skip validation if the post is in a draft state
				$skipValidation = $post && in_array($post->post_status, array('draft', 'new', 'auto-draft', 'trash'));

				$that->update_post_meta($postId, $_POST[Custom_Post_Type::META_POST_KEY], $skipValidation);

				// clear validation errors when creating users - they aren't supposed to be valid yet!
				if ($creatingUser && $thisPt == 'user') {
					$this->purgeErrors($postId);
				}
			}

			// special case for handling taxonomy updates for users & attachments - this is not builtin
			if ((isset($_POST[Custom_Post_Type::TAX_POST_KEY]) || isset($_POST[Custom_Post_Type::TAX_DEFAULT_KEY])) && ($thisPt == 'user' || $thisPt == 'attachment') && $thisPt == $post_type_name) {
				$taxonomies = array();
				if (isset($_POST[Custom_Post_Type::TAX_POST_KEY])) {
					foreach ($_POST[Custom_Post_Type::TAX_POST_KEY] as $tax => $termIds) {
						if (is_array($termIds)) {
							$termIds = array_map('intval', $termIds);
						}
						$taxonomies[$tax] = $termIds;
					}
				}
				if (isset($_POST[Custom_Post_Type::TAX_DEFAULT_KEY])) {
					$categories = array();
					foreach ($_POST[Custom_Post_Type::TAX_DEFAULT_KEY] as $catId) {
						$categories[] = intval($catId);
					}
					$taxonomies['category'] = $categories;
				}
				$that->update_post_terms($postId, $taxonomies);
			}
		};

		// bind to appropriate save hook
		if ($this->post_type_name != 'attachment') {
			foreach ($this->saveHooks as $hook) {
				if ($hook == 'user_register') {
					add_action($hook, $saveMethod, 10, 3);
				} else {
					add_action($hook, $saveMethod);
				}
			}
		} else {
			$attachmentSaveMethod = function($post, $attachment) use ($saveMethod) {
				$saveMethod($post['ID']);
				return $post;
			};
			add_filter('attachment_fields_to_save', $attachmentSaveMethod, 10, 2);
		}

		// bind post status transition hooks
		if ($post_type_name != 'user') {
 			add_action('transition_post_status', function($new, $old, $post) use ($that) {
 				if ($post->post_type != $that->post_type_name) {
 					return;
 				}
 				foreach ($that->statusTransitions as $oldVal => $hooks) {
 					if ($oldVal != $old && $oldVal != '*') continue;

 					foreach ($hooks as $newVal => $callbacks) {
 						if ($newVal != $new && $newVal != '*') continue;

 						foreach ($callbacks as $method) {
 							call_user_func($method, $new, $old, $post);
 						}
 					}
 				}
 			}, 10, 3);
 		}

		// also bind post type column UI hooks
		$headerHook = "manage_edit-{$this->post_type_name}_columns";
		$cellHook = 'manage_posts_custom_column';
		$sortHook = "manage_edit-{$this->post_type_name}_sortable_columns";
		$rowActionHook = "post_row_actions";
		switch ($this->post_type_name) {
			case 'attachment':
				$headerHook = 'manage_media_columns';
				$cellHook = 'manage_media_custom_column';
				$sortHook = "manage_upload_sortable_columns";
				$rowActionHook = "media_row_actions";
				break;
			case 'user':
				$headerHook = 'manage_users_columns';
				$cellHook = 'manage_users_custom_column';
				$sortHook = "manage_users_sortable_columns";
				$rowActionHook = "user_row_actions";
				break;
			case 'page':
				$cellHook = 'manage_pages_custom_column';
				$rowActionHook = "page_row_actions";
				break;
		}

		add_filter($headerHook, function($defaults) use ($that) {
			foreach ($that->removed_list_columns as $colId) {
				unset($defaults[$colId]);
			}
			foreach ($that->list_columns as $colId => $args) {
				$defaults[$colId] = $args['label'];
			}
			return $defaults;
		});

		if ($this->post_type_name != 'user') {
			// normal list cell hooks simply echo the data - we echo them ourselves to standardise the display callbacks
			add_action($cellHook, function($columnId, $postId) use ($that) {
				if (isset($that->list_columns[$columnId])) {
					$args = $that->list_columns[$columnId];
					echo call_user_func($args['display'], $columnId, $postId, $that);
				}
			}, $this->displayActionPriority, 2);
		} else {
			// user list cell hooks must return the data, so we pass the return value of the display callback back
			add_action($cellHook, function($val, $columnId, $userId) use ($that) {
				if (isset($that->list_columns[$columnId])) {
					$args = $that->list_columns[$columnId];
					return call_user_func($args['display'], $columnId, $userId, $that);
				}
				return $val;
			}, $this->displayActionPriority, 3);
		}

		add_filter($sortHook, function($columns) use ($that) {
			foreach ($that->list_columns as $colId => $args) {
				if ($args['order']) {
					$columns[$colId] = $colId;
				}
			}
			return $columns;
		});

		add_filter('request', function($vars) use ($that) {
			// FILTERING ACTIONS

			// process any filters if their GET parameter is present
			foreach ($that->list_columns as $colId => $args) {
				if (isset($args['filters'])) {
					foreach ($args['filters'] as $param => $handler) {
						if (isset($_GET[$param])) {
							$vars = call_user_func($handler, $vars);
						}
					}
				}
			}

			// ORDERING ACTIONS

			// check that we're dealing with an ordering that our post type supports
			if (!isset($vars['post_type']) || $vars['post_type'] != $that->post_type_name || !isset($vars['orderby']) || !isset($that->list_columns[$vars['orderby']]['order'])) {
				return $vars;
			}

			// return the query with order parameters added
			return call_user_func($that->list_columns[$vars['orderby']]['order'], $vars);
		});

		// LIST ROW QUICK ACTIONS
		add_filter($rowActionHook, function($actions, $post) use ($that) {
			// ignore if not our post type
			if ($post->post_type != $that->post_type_name) {
				return $actions;
			}

			// process all columns for row actions
			foreach ($that->row_actions as $action => $aCallback) {
				if (!isset($aCallback)) {
					// action removed
					unset($actions[$action]);
					continue;
				}
				// action added
				$actions[$action] = call_user_func($aCallback, $post);
			}
			return $actions;
		}, 10, 2);

		// QUICK EDIT HOOKS
		add_action('quick_edit_custom_box', function($columnName, $postType) use ($that) {
			if ($postType != $that->post_type_name) {
				// not our post type - ignore
				return;
			}

			// process relevant column for quick edit definitions if there is one
			if (isset($that->list_columns[$columnName]) && isset($that->list_columns[$columnName]['quickEdit'])) {
				AdminUI::renderQuickEditInput($that->list_columns[$columnName]['quickEdit']);
			}
		}, 10, 2);
	}

	/**
	 * Read full data for a post (excluding basic info retrieved with its core data)
	 */
	public function get_post_data($postId)
	{
		return array(
			'meta'	=> $this->get_post_meta($postId),
			'terms'	=> $this->get_post_terms($postId),
		);
	}

	/**
	 * Read taxonomy terms for a post, but only taxonomies of this post type's.
	 */
	public function get_post_terms($postId)
	{
		// create an array to hold the terms of each taxonomy registered for the post type
		$taxonomies = array_combine($this->taxonomies, array_fill(0, count($this->taxonomies), array()));

		foreach ($taxonomies as $taxonomy => &$terms) {
			$terms = get_the_terms( $postId, $taxonomy );
		}

		// load superclass taxonomies and merge our data on top, if present
		if ($this->post_type_superclass) {
			$superType = self::get_post_type($this->post_type_superclass);
			return array_merge($superType->get_post_terms($postId), $taxonomies);
		}

		return $taxonomies;
	}

	/**
	 * Read metadata for a post, but only metadata of this post type's.
	 * This method also normalises single element arrays into their first value.
	 */
	public function get_post_meta($postId)
	{
		// get all metadata for this post
		if ($this->post_type_name == 'user') {
			$meta = get_user_meta($postId);
		} else if ($parentId = wp_is_post_revision($postId)) {
			$meta = get_post_custom($parentId);
		} else {
			$meta = get_post_custom($postId);
		}

		$ourMeta = array();

		// Loop through each meta box
		foreach( $this->meta_fields as $title => $fields ) {
			// Loop through all fields
			foreach( $fields as $label => $type ) {
				if (is_array($type) && isset($type[1]['meta_field_name_override'])) {
					$fldName = $type[1]['meta_field_name_override'];
				} else {
					$fldName = self::get_field_id_name($title) . '_' . self::get_field_id_name($label);
				}
				$ourMeta[$fldName] = true;
			}
		}

		// filter out metadata not belonging to us
		$postMeta = array_intersect_key($meta, $ourMeta);

		// normalise single element metadata arrays
		foreach ($postMeta as &$meta) {
			if (is_array($meta) && count($meta) == 1) {
				$meta = $meta[0];
			}

			// unserialise metadata too
			$meta = maybe_unserialize($meta);
		}

		// load superclass metadata and merge our data on top, if present
		if ($this->post_type_superclass) {
			$superType = self::get_post_type($this->post_type_superclass);
			return array_merge($superType->get_post_meta($postId), $postMeta);
		}

		return $postMeta;
	}

	/**
	 * Update metadata for a post, but only metadata of this post type's.
	 * @param  int    $postId     ID of post to update metadata for
	 * @param  Array  $rawMetaFields Flat array of metadata keys/values.
	 *                               Key names are given by lowerase concatenation of the metabox's title and field name with underscores.
	 */
	public function update_post_meta($postId, Array $rawMetaFields, $skipValidation = false)
	{
		$rawMetaFields = $this->prehandlePostMeta($postId, $rawMetaFields);
		// we force load existing meta fields to be sure that we're getting fresh info - other plugins
		// or code may have updated the metadata during this save phase
		$metaFields = $this->get_post_meta($postId);

		// load submission handlers
		$this->init_form_handlers($rawMetaFields, $postId, true);

		// index our raw metadata by field names used within the form
		$indexedMetaFields = array();
		foreach ($rawMetaFields as $i => $f) {
			$indexedMetaFields[self::META_POST_KEY . '[' . $i . ']'] = $f;
		}

		// Loop through each meta box
		foreach ($this->meta_fields as $title => $fields) {
			// load the form handler responsible for this metabox and validate the data against it
			$inputHandler = $this->formHandlers[self::get_field_id_name($title)];

			// tell the form that it's already had default data provided to stop values being cleared with defaults
			$inputHandler->submitted = true;

			// bring in the subset of data relevant to this metabox section
			$inputHandler->importData($indexedMetaFields, true);

			// read back pre-validated data in case of an error
			$prevalidationData = $inputHandler->getData();

			if (!$skipValidation && !$inputHandler->validate()) {
				// store the form's errors so that we can pull them back after the redirect
				$this->logErrors($postId, $title, $inputHandler->getErrors(), $prevalidationData);
				continue;
			}

			// merge validated data from formIO subinstance back onto metadata
			$validData = $inputHandler->getData();

			$metaData = array();
			foreach ($validData as $k => $v) {
				$metaData[preg_replace('/^' . self::META_POST_KEY . '\[(.*)\]$/', '$1', $k)] = $v;
			}
			$metaFields = array_merge($metaFields, $metaData);

			// Loop through all fields
			foreach ($fields as $label => $type) {
				if (is_array($type) && isset($type[1]['meta_field_name_override'])) {
					$field_id_name = $type[1]['meta_field_name_override'];
				} else {
					$field_id_name = self::get_field_id_name($title) . '_' . self::get_field_id_name($label);
				}

				if ($this->post_type_name != 'user') {
					if (!isset($metaFields[$field_id_name]) || $metaFields[$field_id_name] === '') {
						delete_post_meta($postId, $field_id_name);
					} else {
						update_post_meta($postId, $field_id_name, $metaFields[$field_id_name]);
					}
				} else {
					if (!isset($metaFields[$field_id_name]) || $metaFields[$field_id_name] === '') {
						delete_user_meta($postId, $field_id_name);
					} else {
						update_user_meta($postId, $field_id_name, $metaFields[$field_id_name]);
					}
				}
			}
		}

		// save superclass metadata and merge our data on top, if present
		if ($this->post_type_superclass) {
			$superType = self::get_post_type($this->post_type_superclass);
			$superType->update_post_meta($postId, $metaFields, $skipValidation);
		}
	}

	/**
	 * Update taxonomy terms for a post, but only those provided
	 *
	 * @param  int    $postId ID of the post to update
	 * @param  Array  $terms  array of term IDs keyed by taxonomy name, or array of comma separated list of term names
	 * @param  bool	  $append if false, existing term values will be erased
	 */
	public function update_post_terms($postId, Array $taxTerms, $append = false)
	{
		foreach ($taxTerms as $taxonomy => $terms) {
			if (!$terms) {
				continue;
			}
			if (!is_array($terms)) {
				$terms = array($terms);
			}
			foreach ($terms as &$t) {
				if (is_numeric($t)) {
					$t = intval($t);
				}
			}
			wp_set_object_terms($postId, $terms, $taxonomy, $append);
		}
	}

	//------------------------------------------------------------------------------------------------------------------------------------------------------------------
	// post type interface accessors
	//------------------------------------------------------------------------------------------------------------------------------------------------------------------

	public function get_post_meta_fields()
	{
		$fields = array();

		foreach ($this->meta_fields as $title => $boxFields) {
			foreach ($boxFields as $label => $type) {
				if (is_array($type) && isset($type[1]['meta_field_name_override'])) {
					$fields[] = $type[1]['meta_field_name_override'];
				} else {
					$fields[] = self::get_field_id_name($title) . '_' . self::get_field_id_name($label);
				}
			}
		}

		// read superclass field names as well if present
		if ($this->post_type_superclass) {
			$superType = self::get_post_type($this->post_type_superclass);
			return array_merge($superType->get_post_meta_fields(), $fields);
		}

		return $fields;
	}

	public function get_all_terms($taxonomy)
	{
		return get_terms($taxonomy, array('hide_empty' => 0));
	}

	//------------------------------------------------------------------------------------------------------------------------------------------------------------------
	// save submission handling
	//------------------------------------------------------------------------------------------------------------------------------------------------------------------

	public function get_metabox_form_output($boxName, Array $meta = null, $post = null)
	{
		$metaBoxId = self::get_field_id_name($boxName);

		if ($meta || !isset($this->formHandlers[$metaBoxId])) {		// ensure form handler is created and initialised with the correct values for the passed metadata
			$this->init_form_handlers($meta, $post);
		}

		$errors = $this->getErrors($post->ID, true);

		// if there was no error in last submission, we are done
		if (!$errors || !isset($errors[$metaBoxId])) {
			return $this->formHandlers[$metaBoxId]->getFieldsHTML();
		}

		// load the errors from session back into the form
		$handler = $this->formHandlers[$metaBoxId];
		foreach ($errors[$metaBoxId]['errors'] as $field => $errorDetails) {
			$handler->addError($field, $errorDetails);
		}
		foreach ($errors[$metaBoxId]['data'] as $field => $badValue) {
			$handler[$field] = $badValue;
		}

		return $handler->getFieldsHTML();
	}

	/**
	 * Initialises the form handlers used to save this post type's data.
	 * Useful for external code wishing to access internal attributes of variable submission inputs
	 * @see posttype-autocomplete.php
	 *
	 * @param	array $meta		input metadata to prefill the form with. This will be a flat array concatenated with get_field_id_name().
	 * @param	bool  $postId	the post the form is being loaded for
	 * @param	bool  $force	if true, form handlers will be recreated even if already loaded
	 */
	public function init_form_handlers(Array $meta = null, $post = null, $force = false)
	{
		if (!isset($meta)) $meta = array();

		$this->formHandlers = self::generateMetaboxForms($this->post_type_name, $post, $this->meta_fields, $meta, $force, $this->formHandlers);

		// process superclass as well
		if ($this->post_type_superclass) {
			$superType = self::get_post_type($this->post_type_superclass);
			$superType->init_form_handlers($meta, $post, $force);
		}
	}

	/**
	 * Converts an array of metabox & field definitions into FormIO instances for handling
	 * the submission and rendering of those metaboxes.
	 * @param  string  $postType		name of the post type we're generating for. Needed to setup autocomplete for custom post type inputs.
	 * @param  object  $post			the post object we're currently saving, or NULL if the form should be shown blank
	 * @param  Array   $metabox_defs    metabox definition array. This matches the internal format of $this->meta_fields.
	 * @param  Array   $meta            existing metadata
	 * @param  boolean $force           if true, regenerate form handlers if already present
	 * @param  Array   $boxFormHandlers existing form handlers for detection of existing handlers when $force = false
	 * @return array of FormIO objects corresponding to each metabox
	 */
	public static function generateMetaboxForms($postType, $post, Array $metabox_defs, Array $meta, $force = false, Array $boxFormHandlers = null)
	{
		if (!isset($boxFormHandlers)) {
			$boxFormHandlers = array();
		}

		foreach ($metabox_defs as $title => $meta_fields) {
			$metaBoxId = self::get_field_id_name($title);			// metabox ID used for form ID

			// create a formIO instance for managing this box's metadata if not already present
			if ($force || !isset($boxFormHandlers[$metaBoxId])) {
				$form = new FormIO('', 'POST');

				// add all box's fields to the form
				foreach ($meta_fields as $label => $type) {
					// interpret input options or just field type string
					if (is_array($type)) {
						list($type, $options) = $type;
					} else {
						$options = array();
					}

					$metaFieldId = self::get_field_id_name($label);
					$metaKeyName = isset($options['meta_field_name_override']) ? $options['meta_field_name_override'] : $metaBoxId . '_' . $metaFieldId;
					$fieldName = self::META_POST_KEY . '[' . $metaKeyName . ']';

					$form->addField($fieldName, $label, $type);
					$field = $form->getLastField();

					self::handleMetaboxConfig($type, $options, $field, $post, $meta, $metaBoxId, $fieldName, $postType);

					// set passed or default value (:WARNING: must be done after calling setQueryArgs() due to post title lookups for prefilling the list's values)
					if ($field instanceof FormIOField_Checkbox) {
						if (!isset($meta[$metaKeyName])) {
							$field->setValue(isset($options['default']) ? $options['default'] : false);
						} else {
							$field->setValue((is_array($meta[$metaKeyName]) && count($meta[$metaKeyName])) ? true : $meta[$metaKeyName]);
						}
					} else if (isset($meta[$metaKeyName]) && $field instanceof FormIOField_Text) {
						$field->setValue($meta[$metaKeyName]);
					} else if (isset($options['default'])) {
						$field->setValue($options['default']);
					}
				}

				$boxFormHandlers[$metaBoxId] = $form;
			}
		}

		return $boxFormHandlers;
	}

	/**
	 * Digests input field definitions to add_meta_box and creates FormIO fields for managing them
	 * @param  string 		$type  input type being created
	 * @param  array		$options	options for the field
	 * @param  FormIO_Field	$field  FormIO field being created
	 * @param  bool			$post	the post the form is being loaded for
	 * @param  array		$meta  loaded metadata array from the post we're displaying
	 */
	public static function handleMetaboxConfig($type, $options, $field, $post, $meta, $metaBoxId, $fieldName, $postTypeName)
	{
		// set any field validators that need setting
		if (isset($options['validators'])) {
			foreach ($options['validators'] as $validator => $valArgs) {
				$field->addValidator($validator, is_array($valArgs) ? $valArgs : array($valArgs));
			}
			unset($options['validators']);
		}
		if (isset($options['custom_validators'])) {
			foreach ($options['custom_validators'] as $valOpts) {
				$field->addValidator($valOpts['callback'],
					isset($valOpts['args']) ? (is_array($valOpts['args']) ? $valOpts['args'] : array($valOpts['args'])) : array(),
					true, $valOpts['msg']
				);
			}
			unset($options['custom_validators']);
		}

		// set any dependencies present
		if (isset($options['dependencies'])) {
			foreach ($options['dependencies'] as $expectedVal => $visibleField) {
				// duplicate all dependencies with the 'real' underlying field name so that data is correctly cleared when hidden by a dependency
				if (!is_array($visibleField)) {
					$visibleField = array($visibleField);
				}
				$visibleFields = array();
				foreach ($visibleField as $f) {
					$visibleFields[] = $f;
					$visibleFields[] = self::META_POST_KEY . '[' . $f . ']';
				}
				$field->addDependency($expectedVal, $visibleFields);
			}
			unset($options['dependencies']);
		}

		// set field to required if desired
		if (!empty($options['required'])) {
			$field->setRequired();
			unset($options['required']);
		}

		// allow overriding field description
		if (!empty($options['description'])) {
			$field->setAttribute('desc', $options['description']);
			unset($options['description']);
		}

		// add field options if this is a multiple input type
		if (FormIO::fieldIsInstanceOfAny($type, array('dropdown', 'radiogroup', 'checkgroup', 'survey')) && isset($options['values'])) {
			$useKey = !empty($options['values-assoc']);
			foreach ($options['values'] as $k => $v) {
				$field->setOption($useKey ? $k : $v, $v);
			}
			unset($options['values']);
		}

		// add subfields for group inputs
		else if (FormIO::fieldIsInstanceOf($type, 'group') && !FormIO::fieldIsInstanceOf($type, 'repeater')) {
			if (isset($options['fields'])) {
				foreach ($options['fields'] as $name => $f) {
					if (is_array($f)) {
						list($f, $subOpts) = $f;
					} else {
						$subOpts = array();
					}
					$subField = $field->createSubField($f, self::get_field_id_name($name), $name);

					self::handleMetaboxConfig($f, $subOpts, $subField, $post, $meta, $metaBoxId, $fieldName, $postTypeName);
				}
			}
			unset($options['fields']);
		}

		// set the field type for repeater inputs
		else if (FormIO::fieldIsInstanceOf($type, 'repeater')) {
			$field->setRepeaterType($options['fieldtype']);
			$field->setAttribute('reserve_empty_input', false);
			unset($options['fieldtype']);
		}

		// set post type and query options for post type fields
		else if (FormIO::fieldIsInstanceOfAny($type, array('posttypes', 'links', 'attachments', 'users'))) {
			// handle query arg callbacks
			if (isset($options['query_args']) && $options['query_args'] instanceof Closure) {
				$args = $options['query_args'];
				$args = $args($post, $meta);
			} else {
				$args = isset($options['query_args']) ? $options['query_args'] : array();
			}

			// merge in metadata used to identify this field for its autocomplete data
			$args = array_merge(array(
				'metabox' => $metaBoxId, // required for loading fields in post data autocomplete script
				'metakey' => $fieldName,
				'hostposttype' => $postTypeName,
			), $args);

			$field->setQueryArgs(isset($options['post_type']) ? $options['post_type'] : null, $args);

			if (!empty($options['single'])) {
				$field->setSingle();
			}

			unset($options['post_type']);
			unset($options['query_args']);
			unset($options['single']);
		}

		// pass the post data to taxonomy input types
		else if (FormIO::fieldIsInstanceOf($type, 'taxonomy')) {
			if (isset($options['taxonomy'])) {
				$field->setTaxonomy($options['taxonomy']);
			}
			$field->setActivePost($post);
		}

		// pass the metabox and field name to plupload fields
		else if (FormIO::fieldIsInstanceOf($type, 'richupload')) {
			$field->setAttribute('metabox', $metaBoxId);
			$field->setAttribute('posttype', $postTypeName);
		}

		// handle extradata parameter callback for autocomplete fields
		if (isset($options['extradata']) && is_callable($options['extradata'])) {
			$options['extradata'] = call_user_func($options['extradata'], $post, $meta);
		}

		// add all remaining options as field display attributes
		foreach ($options as $opt => $val) {
			$field->setAttribute($opt, $val);
		}
	}

	private function prehandlePostMeta($postId, $metaFields)
	{
		if (!count($this->saveCallbacks)) {
			return $metaFields;
		}
		foreach ($this->saveCallbacks as $cb) {
			$metaFields = call_user_func($cb, $postId, $metaFields, $this);
		}
		return $metaFields;
	}

	/**
	 * Writes out a save nonce which will simultaneously activate the custom post type
	 * code and validate the security of its submission.
	 */
	public function outputSaveNonce()
	{
		static $printNonce = true;
		if ($printNonce) {
			$printNonce = false;
			wp_nonce_field(plugin_basename( __FILE__ ), self::NONCE_FIELD_NAME);
		}
	}

	//------------------------------------------------------------------------------------------------------------------------------------------------------------------
	// Error handling & invalid state persistence
	//------------------------------------------------------------------------------------------------------------------------------------------------------------------

	private $handledPostErrors = array();

	public function showErrorNotifications()
	{
		global $post;
		if (!$post || $post->post_type != $this->post_type_name) {
			return;
		}

		$errors = $this->getErrors($post->ID);

		if (!$errors) {
			return;
		}

		foreach ($errors as $metabox => $formErrs) {
			$boxErrorStr = sprintf( __('%d error(s) saving \'%s\' data. Please <a href="#%s">review</a> this section.'),
								count($formErrs['errors']), $metabox, self::get_field_id_name($metabox)
							);
			echo '<div class="error"><p>' . $boxErrorStr . '</p></div>';
		}

		$this->handledPostErrors[] = $post->ID;
		add_action('shutdown', array($this, 'purgePostErrors'));
	}

	public function purgePostErrors()
	{
		foreach ($this->handledPostErrors as $postId) {
			$this->purgeErrors($postId);
		}
	}

	public function logErrors($postId, $metabox, $errors, $invalidData = array())
	{
		$existing = $this->getErrors($postId);

		$existing[$metabox] = array(
			'errors' => $errors,
			'data' => $invalidData,
		);

		return update_option(self::getErrorOptionName($postId), $existing);
	}

	protected function purgeErrors($postId)
	{
		return delete_option(self::getErrorOptionName($postId));
	}

	public function getErrors($postId, $indexByMetaboxIds = false)
	{
		$errs = get_option(self::getErrorOptionName($postId));
		if ($errs === false) {
			$errs = array();
		}

		if (!$indexByMetaboxIds) {
			return $errs;
		}

		$idErrs = array();
		foreach ($errs as $k => $v) {
			$idErrs[self::get_field_id_name($k)] = $v;
		}
		return $idErrs;
	}

	private static function getErrorOptionName($postId)
	{
		global $current_user;
		get_currentuserinfo();

		return "pbe_{$current_user->ID}_{$postId}";
	}

	//------------------------------------------------------------------------------------------------------------------------------------------------------------------
	// name / ID conversion helpers
	//------------------------------------------------------------------------------------------------------------------------------------------------------------------

	public function get_friendly_name($plural = false)
	{
		$name = $this->post_type_label;
		if ($plural) {
			return isset($this->post_type_label_plural) ? $this->post_type_label_plural : ($name . 's');
		}
		return $name;
	}

	// much the same as sanitize_title, except that we use underscores as spaces instead of dashes
	public static function get_field_id_name($title)
	{
		$title = strip_tags($title);
		// Preserve escaped octets.
		$title = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title);
		// Remove percent signs that are not part of an octet.
		$title = str_replace('%', '', $title);
		// Restore octets.
		$title = preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title);

		if (function_exists('seems_utf8') && seems_utf8($title)) {
			if (function_exists('mb_strtolower')) {
				$title = mb_strtolower($title, 'UTF-8');
			}
			$title = utf8_uri_encode($title, 200);
		}

		$title = strtolower($title);
		$title = preg_replace('/&.+?;/', '', $title); // kill entities
		$title = str_replace('.', '-', $title);

		$title = preg_replace('/[^%a-z0-9 _-]/', '', $title);
		$title = preg_replace('/\s+/', '_', $title);
		$title = preg_replace('|-+|', '-', $title);
		$title = trim($title, '-');

		return $title;
	}

	public static function get_field_friendly_name($label)
	{
		return ucwords( str_replace( '_', ' ', $label ) );
	}

	//------------------------------------------------------------------------------------------------------------------------------------------------------------------
	//	accessor to read post type objects created via this interface
	//------------------------------------------------------------------------------------------------------------------------------------------------------------------

	public static function get_post_type($name)
	{
		if (isset(self::$postTypeRegister[$name])) {
			return self::$postTypeRegister[$name];
		} else if ($name == 'user') {
			return new Custom_Post_Type('user');	// create a wrapper for users automatically if not already inited
		} else if (post_type_exists($name)) {
			return new Custom_Post_Type($name);		// same, for builtin post types
		}
		return null;
	}

	//------------------------------------------------------------------------------------------------------------------------------------------------------------------
	//	Compatibility layer
	//------------------------------------------------------------------------------------------------------------------------------------------------------------------

	private static $LEGACY_ATTACHMENT_EDITOR;

	public static function checkCompat()
	{
		self::$LEGACY_ATTACHMENT_EDITOR = !AdminUI::is_wp_version('3.5');
	}
}
