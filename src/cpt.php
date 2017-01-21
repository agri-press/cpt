<?php
/**
* Class AgriPress_CPT
*
* @package AgriPress
* @since 1.0.0
*
*/

if ( ! class_exists( 'AgriPress_CPT' ) ) :

class AgriPress_CPT {

    /**
	 * @var The current post type name
	 */
    public $post_type_name;

    /**
	 * @var array The current post type arguments
	 */
    public $post_type_args;

    /**
	 * @var array The current post type labels
	 */
    public $post_type_labels;

    public function __construct( $name, $args = array(), $labels = array() ) {

        $this->post_type_name = self::uglify( $name );
        $this->post_type_args = $args;
        $this->post_type_labels  = $labels;
        
        // Add action to register the post type, if the post type does not already exist
        if( ! post_type_exists( $this->post_type_name ) )
        {
            add_action( 'init', array( &$this, 'register_post_type' ) );
        }

        // Listen for the save post hook
        $this->save();
    }

    public function register_post_type() {

        //Capitilize the words and make it plural
        $name       = self::beautify( $this->post_type_name );
        $plural     = self::pluralize( $name );
        
        // We set the default labels based on the post type name and plural. We overwrite them with the given labels.
        $labels = array_merge(
        
            // Default
            array(
                'name'                  => _x( $plural, 'post type general name' ),
                'singular_name'         => _x( $name, 'post type singular name' ),
                'add_new'               => _x( 'Add New', strtolower( $name ) ),
                'add_new_item'          => __( 'Add New ' . $name ),
                'edit_item'             => __( 'Edit ' . $name ),
                'new_item'              => __( 'New ' . $name ),
                'all_items'             => __( 'All ' . $plural ),
                'view_item'             => __( 'View ' . $name ),
                'search_items'          => __( 'Search ' . $plural ),
                'not_found'             => __( 'No ' . strtolower( $plural ) . ' found'),
                'not_found_in_trash'    => __( 'No ' . strtolower( $plural ) . ' found in Trash'), 
                'parent_item_colon'     => '',
                'menu_name'             => $plural
            ),
            
            // Given labels
            $this->post_type_labels
            
        );
        
        // Same principle as the labels. We set some defaults and overwrite them with the given arguments.
        $args = array_merge(
        
            // Default
            array(
                'label'                 => $plural,
                'labels'                => $labels,
                'public'                => true,
                'show_ui'               => true,
                'supports'              => array( 'title', 'editor' ),
                'show_in_nav_menus'     => true,
                '_builtin'              => false,
            ),
            
            // Given args
            $this->post_type_args
            
        );
        
        // Register the post type
        register_post_type( $this->post_type_name, $args );
    }

    public function add_taxonomy( $name, $args = array(), $labels = array() ) {
        if( ! empty( $name ) ) {
            // We need to know the post type name, so the new taxonomy can be attached to it.
            $post_type_name = $this->post_type_name;
    
            // Taxonomy properties
            $taxonomy_name      = self::uglify( $name );
            $taxonomy_labels    = $labels;
            $taxonomy_args      = $args;

            //Capitilize the words and make it plural
            $name       = self::beautify( $name );
            $plural     = self::pluralize( $name );
            
            // Default labels, overwrite them with the given labels.
            $labels = array_merge(
            
                // Default
                array(
                    'name'                  => _x( $plural, 'taxonomy general name' ),
                    'singular_name'         => _x( $name, 'taxonomy singular name' ),
                    'search_items'          => __( 'Search ' . $plural ),
                    'all_items'             => __( 'All ' . $plural ),
                    'parent_item'           => __( 'Parent ' . $name ),
                    'parent_item_colon'     => __( 'Parent ' . $name . ':' ),
                    'edit_item'             => __( 'Edit ' . $name ),
                    'update_item'           => __( 'Update ' . $name ),
                    'add_new_item'          => __( 'Add New ' . $name ),
                    'new_item_name'         => __( 'New ' . $name . ' Name' ),
                    'menu_name'             => __( $name ),
                ),
            
                // Given labels
                $taxonomy_labels
            
            );
            
            // Default arguments, overwritten with the given arguments
            $args = array_merge(
            
                // Default
                array(
                    'label'                 => $plural,
                    'labels'                => $labels,
                    'public'                => true,
                    'show_ui'               => true,
                    'show_in_nav_menus'     => true,
                    '_builtin'              => false,
                ),
            
                // Given
                $taxonomy_args
            
            );
            
            // Add the taxonomy to the post type
            add_action( 'init',
                function() use( $taxonomy_name, $post_type_name, $args ) {
                    register_taxonomy( $taxonomy_name, $post_type_name, $args );
                }
            );    
        } else {
            add_action( 'init',
                function() use( $taxonomy_name, $post_type_name ) {
                    register_taxonomy_for_object_type( $taxonomy_name, $post_type_name );
                }
            );
        }
    }

    public function add_meta_box( $title, $fields = array(), $context = 'normal', $priority = 'default' ) {
        if( ! empty( $title ) ) {
            // We need to know the Post Type name again
            $post_type_name = $this->post_type_name;
    
            // Meta variables
            $box_id         = self::uglify( $title );
            $box_title      = self::beautify( $title );
            $box_context    = $context;
            $box_priority   = $priority;
            
            // Make the fields global
            global $custom_fields;
            $custom_fields[$title] = $fields;
            
            add_action( 'admin_init',
                function() use( $box_id, $box_title, $post_type_name, $box_context, $box_priority, $fields ) {
                    add_meta_box(
                        $box_id,
                        $box_title,
                        function( $post, $data ) {
                            global $post;
                            
                            // Nonce field for some validation
                            wp_nonce_field( plugin_basename( __FILE__ ), 'agripress_post_type' );
                            
                            // Get all inputs from $data
                            $custom_fields = $data['args'][0];
                            
                            // Get the saved values
                            $meta = get_post_custom( $post->ID );
                            
                            // Check the array and loop through it
                            if( ! empty( $custom_fields ) ) {
                                /* Loop through $custom_fields */
                                foreach( $custom_fields as $label => $type ) {
                                    $field_id_name = self::uglify( $data['id'] ) . '_' . self::uglify( $label );
                                    
                                    $field = array();
                                    $field['name'] = $field_id_name;
                                    $field['type'] = $type;
                                    $field['label'] = $label;
                                    $field['value'] = $meta[$field_id_name][0];

                                    self::display_field( $field );

                                    //echo '<label for="' . $field_id_name . '">' . $label . '</label><input type="text" name="custom_meta[' . $field_id_name . ']" id="' . $field_id_name . '" value="' . $meta[$field_id_name][0] . '" />';
                                }
                            }                        
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

    public function save() {
        // Need the post type name again
        $post_type_name = $this->post_type_name;
    
        add_action( 'save_post',
            function() use( $post_type_name ) {
                // Deny the WordPress autosave function
                if( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    
                if ( ! wp_verify_nonce( $_POST['agripress_post_type'], plugin_basename(__FILE__) ) ) return;
            
                global $post;
                
                if( isset( $_POST ) && isset( $post->ID ) && get_post_type( $post->ID ) == $post_type_name ) {
                    global $custom_fields;
                    
                    // Loop through each meta box
                    foreach( $custom_fields as $title => $fields ) {
                        // Loop through all fields
                        foreach( $fields as $label => $type ) {
                            $field_id_name = self.uglify( $title ) . '_' . self::uglify( $label );                            
                            update_post_meta( $post->ID, $field_id_name, $_POST['custom_meta'][$field_id_name] );
                        }
                    
                    }
                }
            }
        );
    }

    /**
	 * Generate HTML for displaying fields
	 * @param  array $args Field data
	 * @return void
	 */
	public static function display_field( $args, $value, $text_domain = 'agripress' ) {

		$html = '';
		$data = '';
        $field_name = $field['name'];

		if( isset( $field['default'] ) ) {
			$data = $field['default'];
			if( $value ) {
				$data = $value;
			}
		}
		switch( $field['type'] ) {
			case 'text':
			case 'password':
			case 'number':
				$html .= '<input id="' . esc_attr( $field['id'] ) . '" type="' . $field['type'] . '" name="' . esc_attr( $field['name'] ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" value="' . $data . '"/>' . "\n";
			break;
			case 'text_secret':
				$html .= '<input id="' . esc_attr( $field['id'] ) . '" type="text" name="' . esc_attr( $field['name'] ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" value=""/>' . "\n";
			break;
			case 'textarea':
                $rows = 5;
                $cols = 50;
                if ( isset( $field['rows'] ) ) {
                    $rows = esc_attr( $field['rows'] );
                }
                if ( isset( $field['cols'] ) ) {
                    $cols = esc_attr( $field['cols'] );
                }
				$html .= '<textarea id="' . esc_attr( $field['id'] ) . '" rows="' . $rows . '" cols="' . $cols . '" name="' . esc_attr( $field['name'] ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '">' . $data . '</textarea><br/>'. "\n";
			break;
			case 'checkbox':
				$checked = '';
				if( $value && 'on' == $value ){
					$checked = 'checked="checked"';
				}
				$html .= '<input id="' . esc_attr( $field['id'] ) . '" type="' . $field['type'] . '" name="' . esc_attr( $field['name'] ) . '" ' . $checked . '/>' . "\n";
			break;
			case 'checkbox_multi':
				foreach( $field['options'] as $k => $v ) {
					$checked = false;
					if( in_array( $k, $data ) ) {
						$checked = true;
					}
					$html .= '<label for="' . esc_attr( $field['id'] . '_' . $k ) . '"><input type="checkbox" ' . checked( $checked, true, false ) . ' name="' . esc_attr( $field['name'] ) . '[]" value="' . esc_attr( $k ) . '" id="' . esc_attr( $field['id'] . '_' . $k ) . '" /> ' . $v . '</label> ';
				}
			break;
			case 'radio':
				foreach( $field['options'] as $k => $v ) {
					$checked = false;
					if( $k == $data ) {
						$checked = true;
					}
					$html .= '<label for="' . esc_attr( $field['id'] . '_' . $k ) . '"><input type="radio" ' . checked( $checked, true, false ) . ' name="' . esc_attr( $field['name'] ) . '" value="' . esc_attr( $k ) . '" id="' . esc_attr( $field['id'] . '_' . $k ) . '" /> ' . $v . '</label> ';
				}
			break;
			case 'select':
				$html .= '<select name="' . esc_attr( $field['name'] ) . '" id="' . esc_attr( $field['id'] ) . '">';
				foreach( $field['options'] as $k => $v ) {
					$selected = false;
					if( $k == $data ) {
						$selected = true;
					}
					$html .= '<option ' . selected( $selected, true, false ) . ' value="' . esc_attr( $k ) . '">' . $v . '</option>';
				}
				$html .= '</select> ';
			break;
			case 'select_multi':
				$html .= '<select name="' . esc_attr( $field['name'] ) . '[]" id="' . esc_attr( $field['id'] ) . '" multiple="multiple">';
				foreach( $field['options'] as $k => $v ) {
					$selected = false;
					if( in_array( $k, $data ) ) {
						$selected = true;
					}
					$html .= '<option ' . selected( $selected, true, false ) . ' value="' . esc_attr( $k ) . '" />' . $v . '</label> ';
				}
				$html .= '</select> ';
			break;
			case 'image':
				$image_thumb = '';
				if( $data ) {
					$image_thumb = wp_get_attachment_thumb_url( $data );
				}
                $option_name = esc_attr( $field['name'] );
				$html .= '<img id="' . $option_name . '_preview" class="image_preview" src="' . $image_thumb . '" /><br/>' . "\n";
				$html .= '<input id="' . $option_name . '_button" type="button" data-uploader_title="' . __( 'Upload an image' , $text_domain ) . '" data-uploader_button_text="' . __( 'Use image' , $text_domain ) . '" class="image_upload_button button" value="'. __( 'Upload new image' , $text_domain ) . '" />' . "\n";
				$html .= '<input id="' . $option_name . '_delete" type="button" class="image_delete_button button" value="'. __( 'Remove image' , $text_domain ) . '" />' . "\n";
				$html .= '<input id="' . $option_name . '" class="image_data_field" type="hidden" name="' . $option_name . '" value="' . $data . '"/><br/>' . "\n";
			break;
			case 'color':
				?><div class="color-picker" style="position:relative;">
			        <input type="text" name="<?php esc_attr_e( $option_name ); ?>" class="color" value="<?php esc_attr_e( $data ); ?>" />
			        <div style="position:absolute;background:#FFF;z-index:99;border-radius:100%;" class="colorpicker"></div>
			    </div>
			    <?php
			break;
		}
		switch( $field['type'] ) {
			case 'checkbox_multi':
			case 'radio':
			case 'select_multi':
				$html .= '<br/><span class="description">' . $field['description'] . '</span>';
			break;
			default:
				$html .= '<label for="' . esc_attr( $field['id'] ) . '"><span class="description">' . $field['description'] . '</span></label>' . "\n";
			break;
		}
		echo $html;
	}

    public static function beautify( $string ) {
        return ucwords( str_replace( '_', ' ', $string ) );
    }
    
    public static function uglify( $string ) {
        return strtolower( str_replace( ' ', '_', $string ) );
    }

    public static function pluralize( $string ) {
        $last = $string[strlen( $string ) - 1];
        
        if( $last == 'y' )
        {
            $cut = substr( $string, 0, -1 );
            //convert y to ies
            $plural = $cut . 'ies';
        }
        else
        {
            // just attach an s
            $plural = $string . 's';
        }
        
        return $plural;
    }
}

endif;

if ( ! function_exists( 'agripress_post_type' ) ):
/**
 * Access a single interface for post types
 *
 * @param $name string The name of the custom post type.
 * @param $args array The custom post type arguments
 * @param $labels array The custom post type labels
 *
 * @return AgriPress_CPT
 */
function agripress_post_type( $name, $args = array(), $labels = array() ) {
	return new AgriPress_CPT( $name, $args, $labels );
}
endif;

