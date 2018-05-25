<?php
/*
  Plugin Name: WooCommerce Neue Produkte
  Plugin URI: http:/webpremiere.de
  Version: 0.4
  Description: Label für kürzlich veröffentlichte WooCommerce Produkte
  Author: Axel Nitzschner
  Author URI: http:/webpremiere.de
  Text Domain: woocommerce-new-badge
  Domain Path: /languages/

  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Check if WooCommerce is active
 * */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	/**
	 * Localisation (with WPML support)
	 * */
	add_action( 'init', 'plugin_init' );

	function plugin_init() {
		load_plugin_textdomain( 'woocommerce-new-badge', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	function may_be_filtered_post() {
		return ( get_queried_object() && is_shop() && isset( $_GET['new-products'] ) && is_options_auto() ) ? TRUE : FALSE;
	}

	function is_options_auto() {
        
		return ( "no" === get_option( 'wc_nb_auto' ) ) ? FALSE : TRUE;
        
	}

	function get_wc_nb_label() {

		return ( "" == get_option( 'wc_nb_label' )) ? WC_nb::$default_label : get_option( 'wc_nb_label' );
	}
	
	function get_main_category_id() {

		return get_option( 'wc_nb_categories' );
	}

	function init_new_products( $q ) {


		
		// return w/o filtering but still attach the correct label
		if ( !is_options_auto() ) {
			
			/*
			 * Since we are not in auto mode, we must add a badge to each new product via javascript
			 */
			$params = array(
				'label' => get_wc_nb_label()
			);
			// pass args to the document
			wp_localize_script( 'new_cat_badge', 'new_badge_params', $params );

			// take care all new products are labeled also when Auto option isi disabled
			wp_register_script( 'new_cat_badge', plugins_url( '/assets/js/new_cat_badge.js', __FILE__ ), array( 'jquery' ), '1.0', true );
			wp_enqueue_script( 'new_cat_badge' );
			
			return;
			
		}
				
		// rewrite urls to point to the correct New Products Page
		wp_register_script( 'fix_url', plugins_url( '/assets/js/fix_url.js', __FILE__ ), array( 'jquery' ), '1.0', true );
		wp_enqueue_script( 'fix_url' );


		if ( may_be_filtered_post() ) {

			// hide shop page description
			wp_register_script( 'hide_description', plugins_url( '/assets/js/hide_description.js', __FILE__ ), array( 'jquery' ), '1.0', true );
			wp_enqueue_script( 'hide_description' );

			add_filter( 'woocommerce_page_title', 'new_products_title', 10, 2 );
			add_action( 'woocommerce_archive_description', 'new_archive_term_description' );
			add_action( 'woocommerce_before_main_content', 'new_archive_term_image', 20 );

			// start filtering
			add_filter( 'posts_where', 'filter_new_products' );
		}
	}

	function new_products_title( $title ) {

		$title = get_the_category_by_ID( get_main_category_id() );

		return $title;
	}

	function filter_new_products( $where ) {

		if ( is_page() )
			return;

		$days = get_option( 'wc_nb_newness' );

		$date = date( 'Y-m-d', strtotime( '-' . $days . ' days' ) );
		$where .= " AND post_date > '" . $date . "'";

		return $where;
	}

	function new_archive_term_description() {

//		if ( is_product_taxonomy() && 0 === absint( get_query_var( 'paged' ) ) ) {
		if ( may_be_filtered_post() && 0 === absint( get_query_var( 'paged' ) ) ) {
			$description = wc_format_content( category_description( get_main_category_id() ) );
			if ( $description ) {
				echo '<div class="term-description">' . $description . '</div>';
			}
		}
//		var_dump(get_term('1343', 'product_cat'));
	}

	function new_archive_term_image() {
		if ( may_be_filtered_post() ) {
			global $wp_query;
			$thumbnail_id = get_woocommerce_term_meta( get_main_category_id(), 'thumbnail_id', true );
			if ( $thumbnail_id ) {
				$thumbnail_post = get_post( $thumbnail_id );
				$image = wp_get_attachment_url( $thumbnail_id );
				if ( $image ) {
					?>
					<div class="cat-thumb">
						<?php if ( !empty( $thumbnail_post->post_title ) || !empty( $thumbnail_post->post_excerpt ) ) : ?>
							<div class="cat-thumb-overlay">
								<?php echo (!empty( $thumbnail_post->post_title ) ? '<h4>' . $thumbnail_post->post_title . '</h4>' : '' ); ?>
								<?php echo (!empty( $thumbnail_post->post_excerpt ) ? '<p>' . $thumbnail_post->post_excerpt . '</p>' : '' ); ?>
							</div>
						<?php endif; ?>
						<img src="<?php echo $image ?>" alt="" />
					</div>
					<?php
				}
			}
		}
	}

	add_action( 'pre_get_posts', 'init_new_products' );

	/**
	 * New Badge class
	 * */
	if ( !class_exists( 'WC_nb' ) ) {

		class WC_nb {

			public static $default_label = "New";
			
			public function __construct() {
                if( !is_admin() ) {
                    add_action( 'wp_enqueue_scripts', array( $this, 'setup_styles' ), 999999 );  // Enqueue the styles
                }
                
				if ( is_options_auto() ) {
					add_action( 'woocommerce_before_shop_loop_item_title', array( $this, 'show_product_loop_new_badge' ), 30 );
					add_filter('woocommerce_before_single_product_summary', array( $this, 'show_single_product_new_badge' ), 30 );
				}
				
				add_action( 'init', array($this, 'init_settings') );
				
			}

			function init_settings() {
				
				$cat_args = array(
					'taxonomy' => 'product_cat',
					'orderby' => 'name',
					'order' => 'DESC',
					'hierarchical' => FALSE,
					'hide_empty' => false,
				);
				
				$terms = get_terms( $cat_args );
				$product_categories = array();
				foreach ( $terms as $category ) {
					$product_categories[$category->term_id] = $category->name;
				}
				
				$this->settings = array(
					array(
						'title' => __( 'Neue Produkte', 'woocommerce' ),
						'type' => 'title',
						'desc' => sprintf( __( 'Produkte können als "Neu" gekennzeichnet werden, bis das hier angegebene Höchstalter erreicht ist.' ) ),
						'id' => 'wc_nb_options',
					),
					array(
						'title' => __( 'Ermittlung nach Höchstalter', 'woocommerce-new-badge' ),
						'desc' => __( "Neue Produkte nach Höchstalter ermitteln.", 'woocommerce-new-badge' ),
						'id' => 'wc_nb_auto',
						'type' => 'checkbox',
						'default' => 'false',
						'checkboxgroup'   => 'start',
						'show_if_checked' => 'option',
					),
					array(
						'title' => "Name der Kategorie für neue Produkte",
						'id' => 'wc_nb_categories',
						'type' => 'select',
						'options' => $product_categories,
					),
					array(
						'title' => __( 'Höchstalter (in Tagen)', 'woocommerce-new-badge' ),
						'desc' => __( 'Anzahl der Tage ab Veröffentlichung, die das Produkt als <strong>neu</strong> gelten soll', 'woocommerce-new-badge' ),
						'desc_tip' => true,
						'id' => 'wc_nb_newness',
						'type' => 'number',
						'show_if_checked' => 'yes',
					),
					array(
						'title' => __( 'Label (Standard: ' . WC_nb::$default_label . ')', 'woocommerce-new-badge' ),
						'desc_tip' => 'Text der für die Kennzeichnung neuer Produkte verwendet werden soll',
						'placeholder' => WC_nb::$default_label,
						'id' => 'wc_nb_label',
						'type' => 'text',
					),
					array(
						'type' => 'sectionend',
						'id' => 'wc_nb_options',
					),
				);
				
				// Default options
				add_option( 'wc_nb_newness', '30' );


				// Admin
				add_filter( 'woocommerce_get_sections_products', array( $this, 'admin_sections' ), 20 );
				add_filter( 'woocommerce_get_settings_products', array( $this, 'admin_settings' ), 20, 2 );
			}

				
			
			/* ----------------------------------------------------------------------------------- */
			/* Class Functions */
			/* ----------------------------------------------------------------------------------- */

			// Load the sections
			function admin_sections( $sections ) {
				$sections[ 'new_products'] = __( 'Neue Produkte', 'woocommerce' );
				return  $sections;
			}

			// Load / Save the settings
			function admin_settings( $array, $current_section ) {
				if( 'new_products' == $current_section ) {
					return $this->settings;
				} else {
					return $array;
				}
			}

			// Setup styles
			function setup_styles() {
				if ( apply_filters( 'woocommerce_new_badge_enqueue_styles', true ) ) {
                    wp_enqueue_style( 'nb-styles', plugins_url( '/assets/css/style.css', __FILE__ ), array(), '0.5' );
				}
			}
			
			function get_default_label() {
				return esc_html( self::$default_label);
			}
			/* ----------------------------------------------------------------------------------- */
			/* Frontend Functions */
			/* ----------------------------------------------------------------------------------- */
			function  is_new() {
				
				$postdate = get_the_time( 'Y-m-d' );   // Post date
				$postdatestamp = strtotime( $postdate );   // Timestamped post date
				$newness = get_option( 'wc_nb_newness' );  // Newness in days as defined by option
				$date = strtotime( date( 'Y-m-d', strtotime( '-' . $newness . ' days' ) ) );
				return $date <= $postdatestamp;
				
			}
			function output($classes) {
				
				$label = get_wc_nb_label(); // label text as defined by option
				echo '<span class="wc-new-badge badge ' . $classes . '">' . __( $label, 'woocommerce-new-badge' ) . '</span>';
				
			}
			// Display the NEW badge for loop items
			function show_product_loop_new_badge() {
//				
				// If the product was published within the newness time frame display the new badge
				if ( $this->is_new() ) {
					$this->output('left');
				}
			}
			// Display the NEW badge for single products
			function show_single_product_new_badge() {
                if ( $this->is_new() ) {
					$this->output('right');
				}
			}

		}

		$WC_nb = new WC_nb();
	}
}
