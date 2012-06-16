<?php
/**
 * Custom post type helper class
 *
 * Metabox datatype inputs are now controlled by FormIO - use formIO datatypes as field types in your add_meta_box options.
 *
 * Originally from http://wp.tutsplus.com/tutorials/creative-coding/custom-post-type-helper-class/
 * @author Gijs Jorissen
 * @author Sam Pospischil <pospi@spadgos.com>
 */
class Custom_Post_Type
{
	public $post_type_name;
	public $post_type_name_plural;
	public $post_type_args;
	public $post_type_labels;

	public $meta_fields = array();
	public $taxonomies = array();

	public $formHandlers = array();	// FormIO instances used to render and validate each metabox

	private static $postTypeRegister = array();

	/* Class constructor */
	public function __construct( $name, $args = array(), $labels = array() )
	{
		// Set some important variables
		if (is_array($name)) {
			$this->post_type_name		= strtolower( str_replace( ' ', '_', $name[0] ) );
			$this->post_type_name_plural = strtolower( str_replace( ' ', '_', $name[1] ) );
		} else {
			$this->post_type_name		= strtolower( str_replace( ' ', '_', $name ) );
		}
		$this->post_type_args 		= $args;
		$this->post_type_labels 	= $labels;

		// Add action to register the post type, if the post type doesnt exist
		if (function_exists('post_type_exists')) {
			if( ! post_type_exists( $this->post_type_name ) )
			{
				add_action( 'init', array( &$this, 'register_post_type' ) );
			}
		} else {
			if (!WP_Core::post_type_exists( $this->post_type_name ) ) {
				$this->register_post_type();
			}
		}

		// Listen for the save post hook
		$this->save();

		// store ourselves in the loaded post types register so we can be retrieved again
		self::$postTypeRegister[$this->post_type_name] = $this;
	}

	/* Method which registers the post type */
	public function register_post_type()
	{
		//Capitilize the words and make it plural
		$name 		= ucfirst( str_replace( '_', ' ', $this->post_type_name ) );
		$plural 	= ucfirst( str_replace( '_', ' ', isset($this->post_type_name_plural) ? $this->post_type_name_plural : $name . 's') );

		// We set the default labels based on the post type name and plural. We overwrite them with the given labels.
		$labels = array_merge(

			// Default
			array(
				'name' 					=> function_exists('_x') ? _x( $plural, 'post type general name' ) : WP_Core::_x( $plural, 'post type general name' ),
				'singular_name' 		=> function_exists('_x') ? _x( $name, 'post type singular name' ) : WP_Core::_x( $name, 'post type singular name' ),
				'add_new' 				=> function_exists('_x') ? _x( 'Add New', strtolower( $name ) ) : WP_Core::_x( 'Add New', strtolower( $name ) ),
				'add_new_item' 			=> function_exists('__') ? __( 'Add New ' . $name ) : WP_Core::__( 'Add New ' . $name ),
				'edit_item' 			=> function_exists('__') ? __( 'Edit ' . $name ) : WP_Core::__( 'Edit ' . $name ),
				'new_item' 				=> function_exists('__') ? __( 'New ' . $name ) : WP_Core::__( 'New ' . $name ),
				'all_items' 			=> function_exists('__') ? __( 'All ' . $plural ) : WP_Core::__( 'All ' . $plural ),
				'view_item' 			=> function_exists('__') ? __( 'View ' . $name ) : WP_Core::__( 'View ' . $name ),
				'search_items' 			=> function_exists('__') ? __( 'Search ' . $plural ) : WP_Core::__( 'Search ' . $plural ),
				'not_found' 			=> function_exists('__') ? __( 'No ' . strtolower( $plural ) . ' found') : WP_Core::__( 'No ' . strtolower( $plural ) . ' found'),
				'not_found_in_trash' 	=> function_exists('__') ? __( 'No ' . strtolower( $plural ) . ' found in Trash') : WP_Core::__( 'No ' . strtolower( $plural ) . ' found in Trash'),
				'parent_item_colon' 	=> '',
				'menu_name' 			=> $plural
			),

			// Given labels
			$this->post_type_labels

		);

		// Same principle as the labels. We set some default and overwite them with the given arguments.
		$args = array_merge(

			// Default
			array(
				'label' 				=> $plural,
				'labels' 				=> $labels,
				'public' 				=> true,
				'publicly_queryable' => true,
				'query_var'			=> true,
				'rewrite'			=> true,
				'show_ui' 				=> true,
				'supports' 				=> array( 'title', 'editor' ),
				'show_in_menu'		=> true,
				'show_in_nav_menus' 	=> true,
				'menu_position'		=> 15,
				'_builtin' 				=> false,

				'hierarchical'		=> false,
			),

			// Given args
			$this->post_type_args

		);

		// Register the post type
		if (function_exists('register_post_type')) {
			register_post_type( $this->post_type_name, $args );
		} else {
			WP_Core::register_post_type( $this->post_type_name, $args );
		}
	}

	/* Method to attach the taxonomy to the post type */
	public function add_taxonomy( $name, $args = array(), $labels = array() )
	{
		if( ! empty( $name ) )
		{
			// We need to know the post type name, so the new taxonomy can be attached to it.
			$post_type_name = $this->post_type_name;

			// Taxonomy properties
			$taxonomy_name		= strtolower( str_replace( ' ', '_', $name ) );
			$taxonomy_labels	= $labels;
			$taxonomy_args		= $args;

			// register the taxonomy so we know to read its data for posts
			$this->taxonomies[] = $taxonomy_name;

			if (function_exists('register_post_type')) {
				$tax_exists = taxonomy_exists( $taxonomy_name );
			} else {
				$tax_exists = WP_Taxonomy::taxonomy_exists( $taxonomy_name );
			}

			if( !$tax_exists )
			{
				//Capitilize the words and make it plural
				$name 		= ucwords( str_replace( '_', ' ', $name ) );
				$plural 	= $name . 's';

				// Default labels, overwrite them with the given labels.
				$labels = array_merge(

					// Default
					array(
						'name' 					=> function_exists('_x') ? _x( $plural, 'taxonomy general name' ) : WP_Core::_x( $plural, 'taxonomy general name' ),
						'singular_name' 		=> function_exists('_x') ? _x( $name, 'taxonomy singular name' ) : WP_Core::_x( $name, 'taxonomy singular name' ),
					    'search_items' 			=> function_exists('__') ? __( 'Search ' . $plural ) : WP_Core::__( 'Search ' . $plural ),
					    'all_items' 			=> function_exists('__') ? __( 'All ' . $plural ) : WP_Core::__( 'All ' . $plural ),
					    'parent_item' 			=> function_exists('__') ? __( 'Parent ' . $name ) : WP_Core::__( 'Parent ' . $name ),
					    'parent_item_colon' 	=> function_exists('__') ? __( 'Parent ' . $name . ':' ) : WP_Core::__( 'Parent ' . $name . ':' ),
					    'edit_item' 			=> function_exists('__') ? __( 'Edit ' . $name ) : WP_Core::__( 'Edit ' . $name ),
					    'update_item' 			=> function_exists('__') ? __( 'Update ' . $name ) : WP_Core::__( 'Update ' . $name ),
					    'add_new_item' 			=> function_exists('__') ? __( 'Add New ' . $name ) : WP_Core::__( 'Add New ' . $name ),
					    'new_item_name' 		=> function_exists('__') ? __( 'New ' . $name . ' Name' ) : WP_Core::__( 'New ' . $name . ' Name' ),
					    'menu_name' 			=> function_exists('__') ? __( $name ) : WP_Core::__( $name ),
					),

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
				if (function_exists('register_taxonomy')) {
					add_action( 'init',
						function() use( $taxonomy_name, $post_type_name, $args )
						{
							register_taxonomy( $taxonomy_name, $post_type_name, $args );
						}
					);
				} else {
					WP_Taxonomy::register_taxonomy( $taxonomy_name, $post_type_name, $args );
				}
			}
			else
			{
				if (function_exists('register_taxonomy_for_object_type')) {
					add_action( 'init',
						function() use( $taxonomy_name, $post_type_name )
						{
							register_taxonomy_for_object_type( $taxonomy_name, $post_type_name );
						}
					);
				} else {
					WP_Taxonomy::register_taxonomy_for_object_type( $taxonomy_name, $post_type_name );
				}
			}
		}
	}

	/* Attaches meta boxes to the post type */
	public function add_meta_box( $title, $fields = array(), $context = 'normal', $priority = 'default' )
	{
		if( ! empty( $title ) )
		{
			// We need to know the Post Type name again
			$post_type_name = $this->post_type_name;

			// Meta variables
			$box_id 		= strtolower( str_replace( ' ', '_', $title ) );
			$box_title		= ucwords( str_replace( '_', ' ', $title ) );
			$box_context	= $context;
			$box_priority	= $priority;

			// store the meta field so we know to save it
			$this->meta_fields[$title] = $fields;

			if (!function_exists('add_action')) {
				return;	// not in wordpress, so can't be in admin, so no need to register the metabox hooks
			}

			$that = $this;
			add_action( 'admin_init',
				function() use( $box_id, $box_title, $post_type_name, $box_context, $box_priority, $fields, $that )
				{
					add_meta_box(
						$box_id,
						$box_title,
						function( $post, $data ) use ($that)
						{
							$metaBoxId = $data['id'];			// metabox ID used for form ID
							$meta_fields = $data['args'][0];	// Get all inputs from $data

							// Get the saved values
							$meta = get_post_custom( $post->ID );

							// create a formIO instance for managing this box's metadata
							if (!isset($that->formHandlers[$metaBoxId])) {
								$form = new FormIO('', 'POST');

								// insert all metabox fields into the form
								if (!empty($meta_fields)) {
									foreach ($meta_fields as $label => $type) {
										if (is_array($type)) {
											list($type, $options) = $type;
										} else {
											$options = array();
										}

										$metaKeyName = $metaBoxId . '_' . Custom_Post_Type::get_field_id_name($label);
										$form->addField('custom_meta[' . $metaKeyName . ']', $label, $type, isset($meta[$metaKeyName][0]) ? $meta[$metaKeyName][0] : (isset($options['default']) ? $options['default'] : null));

										// add field options if this is a multiple input type
										if (in_array($type, array('dropdown', 'radiogroup', 'checkgroup', 'survey')) && isset($options['values'])) {
											foreach ($options['values'] as $v) {
												$form->addOption(Custom_Post_Type::get_field_id_name($v), $v);
											}
										}
									}
								}

								$that->formHandlers[$metaBoxId] = $form;
							} else {
								$form = $that->formHandlers[$metaBoxId];
							}

							// Write a nonce field for some validation
							wp_nonce_field( plugin_basename( __FILE__ ), 'custom_post_type' );

							// output the metabox edit form HTML
							echo $form->getFieldsHTML();
						},
						$post_type_name,
						$box_context,
						$box_priority,
						array( $fields )
					);
				}
			);
		}

	}

	/* Listens for when the post type being saved */
	public function save()
	{
		if (!function_exists('add_action')) {
			return;	// not in wordpress, so can't be in admin, so no need to register the metabox hooks
		}

		// Need the post type name again
		$post_type_name = $this->post_type_name;

		$that = $this;
		add_action( 'save_post',
			function($postId) use( $that, $post_type_name )
			{
				// Deny the wordpress autosave function
				if( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;

				if ( isset($_POST['custom_post_type']) && ! wp_verify_nonce( $_POST['custom_post_type'], plugin_basename(__FILE__) ) ) return;

				if( isset( $_POST['custom_meta'] ) && $postId && get_post_type($postId) == $post_type_name ) {
					$that->update_post_meta($postId, $_POST['custom_meta']);
				}
			}
		);
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
			if (function_exists('get_the_terms')) {
				$terms = get_the_terms( $postId, $taxonomy );
			} else {
				$terms = WP_Taxonomy::get_the_terms( $postId, $taxonomy );
			}
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
		if (function_exists('get_post_custom')) {
			$meta = get_post_custom( $postId );
		} else {
			$meta = WP_Core::get_post_custom( $postId );
		}

		$ourMeta = array();

		// Loop through each meta box
		foreach( $this->meta_fields as $title => $fields ) {
			// Loop through all fields
			foreach( $fields as $label => $type ) {
				$ourMeta[self::get_field_id_name($title) . '_' . self::get_field_id_name($label)] = true;
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
			if (function_exists('maybe_unserialize')) {
				$meta = maybe_unserialize($meta);
			} else {
				$meta = WP_Core::maybe_unserialize($meta);
			}
		}

		return $postMeta;
	}

	/**
	 * Update metadata for a post, but only metadata of this post type's.
	 * @param  int    $postId     ID of post to update metadata for
	 * @param  Array  $metaFields Flat array of metadata keys/values.
	 *                            Key names are given by lowerase concatenation of the metabox's title and field name with underscores.
	 */
	public function update_post_meta($postId, Array $metaFields)
	{
		// Loop through each meta box
		foreach ($this->meta_fields as $title => $fields) {
			// Loop through all fields
			foreach ($fields as $label => $type) {
				$field_id_name = self::get_field_id_name($title) . '_' . self::get_field_id_name($label);

				update_post_meta($postId, $field_id_name, isset($metaFields[$field_id_name]) ? $metaFields[$field_id_name] : null);
			}
		}
	}

	public function get_post_meta_fields()
	{
		$fields = array();

		foreach ($this->meta_fields as $title => $boxFields) {
			foreach ($boxFields as $label => $type) {
				$fields[] = self::get_field_id_name($title) . '_' . self::get_field_id_name($label);
			}
		}

		return $fields;
	}

	public static function get_field_id_name($label)
	{
		return strtolower( str_replace( ' ', '_', $label ) );
	}

	//----------------------------------------------------------------------------------------------------------------------------------------------------
	//	read post types created via this interface

	public static function get_post_type($name)
	{
		if (isset(self::$postTypeRegister[$name])) {
			return self::$postTypeRegister[$name];
		}
		return null;
	}
}
