<?php
/**
 * add ebay options metaboxes to product edit page
 */

class WpLister_Product_MetaBox {

	var $_ebay_item = null;
	var $_listing_profile = null;

	function __construct() {
	    add_action( 'admin_head', array( $this, 'render_custom_css' ) );
	    add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 30 );

		add_action( 'add_meta_boxes', array( &$this, 'add_meta_boxes' ) );
		add_action( 'woocommerce_process_product_meta', array( &$this, 'save_meta_box' ), 0, 2 );

        // add options to variable products
        add_action('woocommerce_product_after_variable_attributes', array(&$this, 'woocommerce_variation_options'), 1, 3);
        add_action('woocommerce_product_after_variable_attributes', array(&$this, 'woocommerce_custom_variation_meta_fields'), 2, 3);
        add_action('woocommerce_process_product_meta_variable', array(&$this, 'process_custom_variation_meta_fields'), 10, 1);

        add_action('woocommerce_process_product_meta_variable', array(&$this, 'process_product_meta_variable'), 10, 1);
		add_action('woocommerce_ajax_save_product_variations',  array( $this, 'process_product_meta_variable') ); // WC2.4

		if ( get_option( 'wplister_external_products_inventory' ) == 1 ) {
			add_action( 'woocommerce_process_product_meta_external', array( &$this, 'save_external_inventory' ) );
		}

        // show warning message if max_input_vars limit was exceeded
        add_action( 'admin_notices', array( &$this, 'show_admin_post_vars_warning' ), 5 );

		// remove ebay specific meta data from duplicated products
		//add_action( 'woocommerce_duplicate_product', array( &$this, 'woocommerce_duplicate_product' ), 0, 2 );
	}

	function render_custom_css() {
        $screen = get_current_screen();

        if ( $screen && $screen->id == 'product' ) {
            // fix scrolling issue  in modal boxes #50569
            echo '<style>
                /* Fix 3rd-party plugins removing overflow to thickbox windows */
                div#TB_ajaxContent {
                    overflow: auto !important;
                }
            </style>';
        }
    }

	function enqueue_scripts() {
	    $screen = get_current_screen();

	    if ( $screen && $screen->id == 'product' ) {
	        // tagify
            if ( ! wp_style_is( 'tagify', 'registered' ) ) {
                wp_register_style( 'tagify', WPLE_PLUGIN_URL .'js/tagify/dist/tagify.css' );
            }

            if ( !wp_script_is( 'tagify', 'registered' ) ) {
                wp_register_script( 'tagify', WPLE_PLUGIN_URL .'js/tagify/dist/tagify.min.js' );
            }

            wp_enqueue_style( 'tagify' );
            wp_enqueue_script( 'tagify' );

		    if ( !wp_script_is( 'wple_gpsr', 'registered' ) ) {
			    wp_register_script( 'wple_gpsr', WPLE_PLUGIN_URL .'js/classes/GPSR.js' );
		    }
		    wp_enqueue_script( 'wple_gpsr' );

        }
    }

	function add_meta_boxes() {

		// check if current user can prepare listings (fixed in #35147)
		if ( ! current_user_can('prepare_ebay_listings') ) return;

		$title = __( 'eBay Options', 'wp-lister-for-ebay' );
		add_meta_box( 'wplister-ebay-details', $title, array( &$this, 'meta_box_basic' ), 'product', 'normal', 'default');

		$title = __( 'eBay Product Identifiers', 'wp-lister-for-ebay' );
		add_meta_box( 'wplister-ebay-gtins', $title, array( &$this, 'meta_box_gtins' ), 'product', 'normal', 'default');

		$title = __( 'Advanced eBay Options', 'wp-lister-for-ebay' );
		add_meta_box( 'wplister-ebay-advanced', $title, array( &$this, 'meta_box_advanced' ), 'product', 'normal', 'default');

		$title = __( 'eBay Categories and Item Specifics', 'wp-lister-for-ebay' );
		add_meta_box( 'wplister-ebay-categories', $title, array( &$this, 'meta_box_categories' ), 'product', 'normal', 'default');

		$title = __( 'eBay General Product Safety Regulation', 'wp-lister-for-ebay' );
		add_meta_box( 'wplister-ebay-gpsr', $title, array( &$this, 'meta_box_gpsr' ), 'product', 'normal', 'default');

		$title = __( 'eBay Part Compatibility', 'wp-lister-for-ebay' );
		add_meta_box( 'wplister-ebay-compat', $title, array( &$this, 'meta_box_compat' ), 'product', 'normal', 'default');

		$title = __( 'eBay Shipping Options', 'wp-lister-for-ebay' );
		add_meta_box( 'wplister-ebay-shipping', $title, array( &$this, 'meta_box_shipping' ), 'product', 'normal', 'default');

		$this->enqueueFileTree();

	}

	function meta_box_basic( $post ) {
        ?>
        <style type="text/css">

        	/* new color scheme v2.5 */
			#wplister-ebay-details,
			#wplister-ebay-advanced,
			#wplister-ebay-gtins,
			#wplister-ebay-categories,
			#wplister-ebay-compat,
			#wplister-ebay-shipping,
			#wplister-ebay-details,
            #wplister-ebay-gpsr {
			    background-color: #fafafa;
			}
			#wplister-ebay-details h2.hndle,
			#wplister-ebay-advanced h2.hndle,
			#wplister-ebay-gtins h2.hndle,
			#wplister-ebay-categories h2.hndle,
			#wplister-ebay-compat h2.hndle,
			#wplister-ebay-shipping h2.hndle,
			#wplister-ebay-details h2.hndle,
			#wplister-ebay-gpsr h2.hndle {
			    background-color: #f6f7f8;
			}
			#wplister-ebay-details .inside,
			#wplister-ebay-advanced .inside,
			#wplister-ebay-gtins .inside,
			#wplister-ebay-categories .inside,
			#wplister-ebay-compat .inside,
			#wplister-ebay-shipping .inside,
			#wplister-ebay-gpsr .inside,
			#wplister-ebay-details .inside {
				margin-top:    20px;
				margin-bottom: 10px;
			}

            #wplister-ebay-gpsr label {
                float: left;
                width: 33%;
                line-height: 2em;
            }
            #wplister-ebay-gpsr input.long {
                width: 60%;
            }
            #wplister-ebay-gpsr input,
            #wplister-ebay-gpsr select {
                width: 31%;
            }
            #wplister-ebay-gpsr input.checkbox {
                width:auto;
            }

            #wplister-ebay-gpsr .description {
                clear: both;
                display: block;
                margin-left: 33%;
            }

            #wplister-ebay-details label {
            	float: left;
            	width: 33%;
            	line-height: 2em;
            }
            #wplister-ebay-details input {
            	width: 62%;
            }
            #wplister-ebay-details .description {
            	clear: both;
            	display: block;
            	margin-left: 33%;
            }
            #wplister-ebay-details .de.input_specs,
            #wplister-ebay-details .de.select_specs {
            	clear: both;
            	display: block;
            	margin-left: 33%;
            }

			.branch-3-8 div.update-nag {
				border-left: 4px solid #ffba00;
			}

            #wplister-ebay-details .woocommerce-help-tip,
            #wplister-ebay-advanced .woocommerce-help-tip,
            #wplister-ebay-gtins .woocommerce-help-tip {
            	float: right;
            	margin-top: 5px;
            	margin-right: 10px;
            	font-size: 1.4em;
            }
            /* Fix WP-Smushit CSS conflict with the jqueryFileTree plugin */
            #ebay_categories_tree_container .jqueryFileTree li { display: block; }
            #ebay_categories_tree_container .jqueryFileTree li A { display: inline; }

			/* adjust chosen field height on edit product page */
			#wplister-ebay-shipping .chosen-container-multi .chosen-choices li.search-field input[type=text] {
				height: 23px;
			}
			#wplister-ebay-shipping .chosen-container-multi .chosen-choices  {
				border: 1px solid #ccc;
			}

        </style>
        <?php
		do_action('wple_before_basic_ebay_options');

		wp_nonce_field( 'wple_save_product', 'wple_save_product_nonce' );

		woocommerce_wp_text_input( array(
			'id' 				=> 'wpl_ebay_title',
			'label' 			=> __( 'Listing title', 'wp-lister-for-ebay' ),
			'placeholder' 		=> __( 'Custom listing title', 'wp-lister-for-ebay' ),
			'description' 		=> __( 'Leave empty to generate title from product name.', 'wp-lister-for-ebay' ) . ' ' .
			                       __( 'Template shortcodes can be used.', 'wp-lister-for-ebay' ),
			'custom_attributes' => array( 'maxlength' => 80 ),
			'value'				=> get_post_meta( $post->ID, '_ebay_title', true )
		) );

		woocommerce_wp_text_input( array(
			'id' 				=> 'wpl_ebay_subtitle',
			'label' 			=> __( 'Listing subtitle', 'wp-lister-for-ebay' ),
			'placeholder' 		=> __( 'Custom listing subtitle', 'wp-lister-for-ebay' ),
			'description' 		=> __( 'Leave empty to use the product excerpt.', 'wp-lister-for-ebay' ),
			'custom_attributes' => array( 'maxlength' => 55 ),
			'value'				=> get_post_meta( $post->ID, '_ebay_subtitle', true )
		) );

		woocommerce_wp_text_input( array(
			'id' 			=> 'wpl_ebay_start_price',
			'label' 		=> __( 'Price / Start Price', 'wp-lister-for-ebay' ),
			'placeholder' 	=> __( 'Start Price', 'wp-lister-for-ebay' ),
			'class' 		=> 'wc_input_price',
			'value'			=> wc_format_localized_price( get_post_meta( $post->ID, '_ebay_start_price', true ) )
		) );

		woocommerce_wp_select( array(
			'id' 			=> 'wpl_ebay_auction_type',
			'label' 		=> __( 'Listing Type', 'wp-lister-for-ebay' ),
			'options' 		=> array(
					''               => __( '-- use profile setting --', 'wp-lister-for-ebay' ),
					'Chinese'        => __( 'Auction', 'wp-lister-for-ebay' ),
					'FixedPriceItem' => __( 'Fixed Price', 'wp-lister-for-ebay' )
				),
			'value'			=> get_post_meta( $post->ID, '_ebay_auction_type', true )
		) );

		woocommerce_wp_select( array(
			'id' 			=> 'wpl_ebay_listing_duration',
			'label' 		=> __( 'Listing Duration', 'wp-lister-for-ebay' ),
			'options' 		=> array(
					''               => __( '-- use profile setting --', 'wp-lister-for-ebay' ),
					'Days_1'         => '1 ' . __( 'Day', 'wp-lister-for-ebay' ),
					'Days_3'         => '3 ' . __( 'Days', 'wp-lister-for-ebay' ),
					'Days_5'         => '5 ' . __( 'Days', 'wp-lister-for-ebay' ),
					'Days_7'         => '7 ' . __( 'Days', 'wp-lister-for-ebay' ),
					'Days_10'        => '10 ' . __( 'Days', 'wp-lister-for-ebay' ),
					'Days_30'        => '30 ' . __( 'Days', 'wp-lister-for-ebay' ),
					'Days_60'        => '60 ' . __( 'Days', 'wp-lister-for-ebay' ),
					'Days_90'        => '90 ' . __( 'Days', 'wp-lister-for-ebay' ),
					'GTC'            =>  __( 'Good Till Canceled', 'wp-lister-for-ebay' )
				),
			'value'			=> get_post_meta( $post->ID, '_ebay_listing_duration', true )
		) );

		$this->showItemConditionOptions( $post );
		$this->include_character_count_script();
		do_action('wple_after_basic_ebay_options');

	} // meta_box_basic()

	function showItemConditionOptions( $post ) {

		// default conditions - used when no primary category has been selected
		$default_conditions = array(
			''   => __( '-- use profile setting --', 'wp-lister-for-ebay' ),
			1000 => __( 'New', 'wp-lister-for-ebay' ),
			1000 => __( 'New', 'wp-lister-for-ebay' ),
			1500 => __( 'New other', 'wp-lister-for-ebay' ),
			1750 => __( 'New with defects', 'wp-lister-for-ebay' ),
			2500 => __( 'Seller refurbished', 'wp-lister-for-ebay' ) . ' (deprecated as of 09/2021)',
			3000 => __( 'Used', 'wp-lister-for-ebay' ),
			4000 => __( 'Very Good', 'wp-lister-for-ebay' ),
			5000 => __( 'Good', 'wp-lister-for-ebay' ),
			6000 => __( 'Acceptable', 'wp-lister-for-ebay' ),
			7000 => __( 'For parts or not working', 'wp-lister-for-ebay' ),
			2000 => __( 'Manufacturer refurbished', 'wp-lister-for-ebay' ) . ' (deprecated since 01/2021)',
		);

		$listing        = $this->get_current_ebay_item( $post );
		$profile_data   = $listing ? json_decode( $listing->profile_data ) : null;

		// get listing object
		$wpl_account_id = $listing && $listing->account_id ? $listing->account_id : get_option( 'wplister_default_account_id' );
		$wpl_site_id    = $listing                         ? $listing->site_id    : get_option( 'wplister_ebay_site_id' );

		// do we have a primary category?
		$ib = new ItemBuilderModel();
		$mapped_categories = $ib->getMappedCategories( $post->ID, $wpl_account_id );

		if ( get_post_meta( $post->ID, '_ebay_category_1_id', true ) ) {
			$primary_category_id = get_post_meta( $post->ID, '_ebay_category_1_id', true );
		} elseif ( !empty($mapped_categories['primary'] ) ) {
		    $primary_category_id = $mapped_categories['primary'];
		} elseif ( !empty($profile_data->details->ebay_category_1_id) ) {
			$primary_category_id = $profile_data->details->ebay_category_1_id;
		} else {
			// if not use default category
		    $primary_category_id = get_option('wplister_default_ebay_category_id');
		}

		// get listing object

		$wpl_account_id = $listing && $listing->account_id ? $listing->account_id : get_option( 'wplister_default_account_id' );
		$wpl_site_id    = $listing                         ? $listing->site_id    : get_option( 'wplister_ebay_site_id' );

		// fetch updated available conditions array
		$item_conditions = EbayCategoriesModel::getConditionsForCategory( $primary_category_id, $wpl_site_id, $wpl_account_id );
		$product_condition_id = get_post_meta( $post->ID, '_ebay_condition_id', true );

		$actual_condition_id = $product_condition_id;
		$actual_category_id  = $primary_category_id;

		if ( is_array( $item_conditions ) && ! empty( $item_conditions ) ) {
		    // check if conditions are available for this category - or fall back to default

			// get available conditions and add default value "use profile setting" to the beginning
		    $available_conditions = array('' => __( '-- use profile setting --', 'wp-lister-for-ebay' )) + $item_conditions;
		} else {
			$available_conditions = $default_conditions;
		}

		woocommerce_wp_select( array(
			'id' 			=> 'wpl_ebay_condition_id',
			'label' 		=> __( 'Condition', 'wp-lister-for-ebay' ),
			'options' 		=> $available_conditions,
			// 'description' 	=> __( 'Available conditions may vary for different categories.', 'wp-lister-for-ebay' ),
			'value'			=> $product_condition_id
		) );

        if ( isset( $profile_data->details ) && !empty( $profile_data->details->condition_id ) ) {
            $actual_condition_id = $profile_data->details->condition_id;
        }

        if ( isset( $profile_data->details ) && !empty( $profile_data->details->ebay_category_1_id ) ) {
            $actual_category_id = $profile_data->details->ebay_category_1_id;
        }

		if ( in_array( $actual_category_id, EbayCategoriesModel::getTradingCardsCategories() ) ) {
            $condition_descriptions = EbayCategoriesModel::getTradingCardsConditionDescriptions();
            $available_condition_descriptions = $condition_descriptions[ $actual_category_id ] ?? [];
            $descriptors = EbayCategoriesModel::getTradingCardsDescriptorFields();

            $available_condition_descriptions = array('' => __( '-- use profile setting --', 'wp-lister-for-ebay' )) + $available_condition_descriptions;

            woocommerce_wp_select( array(
                'id' 			=> 'wpl_ebay_condition_description',
                'label' 		=> __( 'Condition description', 'wp-lister-for-ebay' ),
                'options' 		=> $available_condition_descriptions,
                // 'description' 	=> __( 'Available conditions may vary for different categories.', 'wp-lister-for-ebay' ),
                'value'			=> get_post_meta( $post->ID, '_ebay_condition_description', true )
            ) );

            woocommerce_wp_select( array(
                'id' 			=> 'wpl_ebay_professional_grader',
                'class'         => 'select short wple_descriptor_field',
                'label' 		=> __( 'Professional Grader', 'wp-lister-for-ebay' ),
                'options' 		=> array('' => __( '-- use profile setting --', 'wp-lister-for-ebay' )) + $descriptors[ 27501 ]['grader_ids'][$actual_category_id],
                'value'			=> get_post_meta( $post->ID, '_ebay_professional_grader', true )
            ) );

			woocommerce_wp_select( array(
				'id' 			=> 'wpl_ebay_grade',
				'class'         => 'select short wple_descriptor_field',
				'label' 		=> __( 'Grade', 'wp-lister-for-ebay' ),
				'options' 		=> array('' => __( '-- use profile setting --', 'wp-lister-for-ebay' )) + $descriptors[ 27502 ]['grade_ids'],
				'value'			=> get_post_meta( $post->ID, '_ebay_grade', true )
			) );

			woocommerce_wp_text_input( array(
				'id' 			=> 'wpl_ebay_certification_number',
				'class'         => 'select short wple_descriptor_field',
				'label' 		=> __( 'Certification Number', 'wp-lister-for-ebay' ),
				'value'			=> get_post_meta( $post->ID, '_ebay_certification_number', true )
			) );

        } else {
            woocommerce_wp_text_input( array(
                'id' 			=> 'wpl_ebay_condition_description',
                'label' 		=> __( 'Condition description', 'wp-lister-for-ebay' ),
                'placeholder' 	=> __( 'Condition description', 'wp-lister-for-ebay' ),
                'description' 	=> __( 'This field should only be used to further clarify the condition of used items.', 'wp-lister-for-ebay' ),
                'value'			=> get_post_meta( $post->ID, '_ebay_condition_description', true )
            ) );
        }

	} // showItemConditionOptions()


	function meta_box_gtins( $post ) {
        $available_attributes  = ProductWrapper::getAttributeTaxonomies();
        $wpl_custom_attributes = wple_get_custom_attributes();
        ?>
        <style type="text/css">
            #wplister-ebay-gtins label {
            	float: left;
            	width: 33%;
            	line-height: 2em;
            }
            #wplister-ebay-gtins input.long {
                width: 60%;
            }
            #wplister-ebay-gtins input,
            #wplister-ebay-gtins select {
            	width: 31%;
            }
            #wplister-ebay-gtins input.checkbox {
            	width:auto;
            }

            #wplister-ebay-gtins .description {
            	clear: both;
            	display: block;
            	margin-left: 33%;
            }
        </style>
        <?php

		// woocommerce_wp_text_input( array(
		// 	'id' 			=> 'wpl_ebay_epid',
		// 	'label' 		=> __( 'eBay Product ID', 'wp-lister-for-ebay' ),
		// 	'placeholder' 	=> __( 'Enter a eBay Product ID (EPID) or click the search icon on the right.', 'wp-lister-for-ebay' ),
		// 	'value'			=> get_post_meta( $post->ID, '_ebay_epid', true )
		// ) );

		// $tb_url    = 'admin-ajax.php?action=wple_show_product_matches&id='.$post->ID.'&width=640&height=420'; // width parameter causes 404 error on some themes
		$tb_url    = wp_nonce_url( 'admin-ajax.php?action=wple_show_product_matches&id='.$post->ID.'&height=420', 'wple_match_product_ajax_nonce' );
		$match_btn = '<a href="'.$tb_url.'" class="thickbox" title="'.__( 'Find matching product on eBay', 'wp-lister-for-ebay' ).'" style="margin-left:9px;"><img src="'.WPLE_PLUGIN_URL.'img/search3.png" alt="search" /></a>';
        //$match_btn = '';

		?>
		<p class="form-field wpl_ebay_epid_field ">
		 	<label for="wpl_ebay_epid">EPID</label>
            <?php wplister_tooltip( __( 'Set the EPID for this product, if applicable.', 'wp-lister-for-ebay' ) ); ?></span>

		 	<input type="text" class="long" name="wpl_ebay_epid" id="wpl_ebay_epid"
		 		   value="<?php echo get_post_meta( $post->ID, '_ebay_epid', true ) ?>"
		 		   placeholder="<?php _e( 'Enter an eBay Product ID (EPID) or click the search icon on the right.', 'wp-lister-for-ebay' ) ?>">
			<?php echo $match_btn ?>
		</p>

        <p class="form-field wpl_ebay_upc_field show_if_simple show_if_external">
            <label for="wpl_ebay_upc"><?php _e( 'UPC', 'wp-lister-for-ebay' ); ?></label>
            <?php wplister_tooltip( __('As of 2015, eBay requires product identifiers (UPC or EAN) in selected categories.<br><br>If your products do have neither UPCs nor EANs, leave this empty and enable the "Missing Product Identifiers" option on the advanced settings page.', 'wp-lister-for-ebay' ) ); ?></span>

            <input type="text" class="short" name="wpl_ebay_upc" id="wpl_ebay_upc"
                   value="<?php echo get_post_meta( $post->ID, '_ebay_upc', true ) ?>"
                   placeholder="<?php _e( 'Enter the UPC, if applicable.', 'wp-lister-for-ebay' ) ?>">

            <select id="select_attrib_upc" class="select_attrib" data-for="wpl_ebay_upc" style="float: right;">
                <option value="">-- Pull from Attribute --</option>
                <?php foreach ( $available_attributes as $attribute ): ?>
                    <option value="<?php esc_attr_e( $attribute->name ); ?>"><?php echo esc_html( $attribute->label ); ?></option>
                <?php endforeach; ?>
                <optgroup label="<?php _e( 'Custom Attributes', 'wp-lister-for-ebay' ); ?>">
                    <?php foreach ( $wpl_custom_attributes as $attribute ): ?>
                        <option value="<?php esc_attr_e( $attribute->name ); ?>"><?php echo esc_html( $attribute->label ); ?></option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
        </p>

        <p class="form-field wpl_ebay_ean_field show_if_simple show_if_external">
            <label for="wpl_ebay_ean"><?php _e( 'EAN', 'wp-lister-for-ebay' ); ?></label>
            <?php wplister_tooltip( __('As of 2015, eBay requires product identifiers (UPC or EAN) in selected categories.<br><br>If your products do have neither UPCs nor EANs, leave this empty and enable the "Missing Product Identifiers" option on the advanced settings page.', 'wp-lister-for-ebay' ) ); ?></span>

            <input type="text" class="short" name="wpl_ebay_ean" id="wpl_ebay_ean"
                   value="<?php echo get_post_meta( $post->ID, '_ebay_ean', true ) ?>"
                   placeholder="<?php _e( 'Enter the EAN, if applicable.', 'wp-lister-for-ebay' ) ?>">

            <select id="select_attrib_ean" class="select_attrib" data-for="wpl_ebay_ean" style="float: right;">
                <option value=""><?php _e( '-- Pull from Attribute --', 'wp-lister-for-ebay' ); ?></option>
                <?php foreach ( $available_attributes as $attribute ): ?>
                    <option value="<?php esc_attr_e( $attribute->name ); ?>"><?php echo esc_html( $attribute->label ); ?></option>
                <?php endforeach; ?>
                <optgroup label="<?php _e( 'Custom Attributes', 'wp-lister-for-ebay' ); ?>">
		            <?php foreach ( $wpl_custom_attributes as $attribute ): ?>
                        <option value="<?php esc_attr_e( $attribute->name ); ?>"><?php echo esc_html( $attribute->label ); ?></option>
		            <?php endforeach; ?>
                </optgroup>
            </select>
        </p>

        <?php if ( get_option( 'wplister_enable_mpn_and_isbn_fields', 2 ) != 0 ): ?>

        <p class="form-field wpl_ebay_isbn_field show_if_simple show_if_external">
            <label for="wpl_ebay_isbn"><?php _e( 'ISBN', 'wp-lister-for-ebay' ); ?></label>
            <?php wplister_tooltip( __('As of 2015, eBay requires product identifiers (UPC, EAN, MPN or ISBN) in selected categories.<br><br>If your product does not have an ISBN, leave this empty.', 'wp-lister-for-ebay' ) ); ?></span>

            <input type="text" class="short" name="wpl_ebay_isbn" id="wpl_ebay_isbn"
                   value="<?php echo get_post_meta( $post->ID, '_ebay_isbn', true ) ?>"
                   placeholder="<?php _e( 'Enter the ISBN, if applicable.', 'wp-lister-for-ebay' ) ?>">

            <select id="select_attrib_isbn" class="select_attrib" data-for="wpl_ebay_isbn" style="float: right;">
                <option value=""><?php _e( '-- Pull from Attribute --', 'wp-lister-for-ebay' ); ?></option>
                <?php foreach ( $available_attributes as $attribute ): ?>
                    <option value="<?php esc_attr_e( $attribute->name ); ?>"><?php echo esc_html( $attribute->label ); ?></option>
                <?php endforeach; ?>
                <optgroup label="<?php _e( 'Custom Attributes', 'wp-lister-for-ebay' ); ?>">
		            <?php foreach ( $wpl_custom_attributes as $attribute ): ?>
                        <option value="<?php esc_attr_e( $attribute->name ); ?>"><?php echo esc_html( $attribute->label ); ?></option>
		            <?php endforeach; ?>
                </optgroup>
            </select>
        </p>

        <p class="form-field wpl_ebay_mpn_field show_if_simple show_if_external">
            <label for="wpl_ebay_mpn"><?php _e( 'MPN', 'wp-lister-for-ebay' ); ?></label>
            <?php wplister_tooltip( __('As of 2015, eBay requires product identifiers (UPC, EAN or Brand/MPN) in selected categories.<br><br>If your product does not have an MPN, leave this empty.', 'wp-lister-for-ebay' ) ); ?></span>

            <input type="text" class="short" name="wpl_ebay_mpn" id="wpl_ebay_mpn"
                   value="<?php echo get_post_meta( $post->ID, '_ebay_mpn', true ) ?>"
                   placeholder="<?php _e( 'Enter the MPN, if applicable.', 'wp-lister-for-ebay' ) ?>">

            <select id="select_attrib_mpn" class="select_attrib" data-for="wpl_ebay_mpn" style="float: right;">
                <option value=""><?php _e( '-- Pull from Attribute --', 'wp-lister-for-ebay' ); ?></option>
                <?php foreach ( $available_attributes as $attribute ): ?>
                    <option value="<?php esc_attr_e( $attribute->name ); ?>"><?php echo esc_html( $attribute->label ); ?></option>
                <?php endforeach; ?>
                <optgroup label="<?php _e( 'Custom Attributes', 'wp-lister-for-ebay' ); ?>">
		            <?php foreach ( $wpl_custom_attributes as $attribute ): ?>
                        <option value="<?php esc_attr_e( $attribute->name ); ?>"><?php echo esc_html( $attribute->label ); ?></option>
		            <?php endforeach; ?>
                </optgroup>
            </select>
        </p>

        <?php endif; ?>

        <p class="form-field wpl_ebay_brand_field show_if_simple show_if_external">
            <label for="wpl_ebay_brand"><?php _e( 'Brand', 'wp-lister-for-ebay' ); ?></label>
            <?php wplister_tooltip( __('As of 2015, eBay requires product identifiers (UPC, EAN or Brand/MPN) in selected categories.<br><br>If your product has an MPN, you need to enter both brand and MPN.', 'wp-lister-for-ebay' ) ); ?></span>

            <input type="text" class="short" name="wpl_ebay_brand" id="wpl_ebay_brand"
                   value="<?php echo get_post_meta( $post->ID, '_ebay_brand', true ) ?>"
                   placeholder="<?php _e( 'Enter the brand, if applicable.', 'wp-lister-for-ebay' ) ?>">

            <select id="select_attrib_brand" class="select_attrib" data-for="wpl_ebay_brand" style="float: right;">
                <option value=""><?php _e( '-- Pull from Attribute --', 'wp-lister-for-ebay' ); ?></option>
                <?php foreach ( $available_attributes as $attribute ): ?>
                    <option value="<?php esc_attr_e( $attribute->name ); ?>"><?php echo esc_html( $attribute->label ); ?></option>
                <?php endforeach; ?>
                <optgroup label="<?php _e( 'Custom Attributes', 'wp-lister-for-ebay' ); ?>">
		            <?php foreach ( $wpl_custom_attributes as $attribute ): ?>
                        <option value="<?php esc_attr_e( $attribute->name ); ?>"><?php echo esc_html( $attribute->label ); ?></option>
		            <?php endforeach; ?>
                </optgroup>
            </select>
        </p>
        <script>
            jQuery( document ).ready(function () {
                jQuery('#wplister-ebay-gtins select.select_attrib').change(function() {
                    const element = jQuery(this).data("for");
                    jQuery("#"+element).val("[[attribute_"+ jQuery(this).val() +"]]");
                    jQuery(this).val("");
                });

                jQuery("#wpl_ebay_condition_id").change( function() {
                    jQuery("p.wpl_ebay_condition_description_field").hide();
                    jQuery("p.wpl_ebay_professional_grader_field").hide();
                    jQuery("p.wpl_ebay_grade_field").hide();
                    jQuery("p.wpl_ebay_certification_number_field").hide();
                    console.log(jQuery(this).val());
                    switch (jQuery(this).val()) {
                        case "2750":
                            jQuery("p.wpl_ebay_condition_description_field").hide();
                            jQuery("p.wpl_ebay_professional_grader_field").show();
                            jQuery("p.wpl_ebay_grade_field").show();
                            jQuery("p.wpl_ebay_certification_number_field").show();
                        break;

                        default:
                            jQuery("p.wpl_ebay_condition_description_field").show();
                            jQuery("p.wpl_ebay_professional_grader_field").hide();
                            jQuery("p.wpl_ebay_grade_field").hide();
                            jQuery("p.wpl_ebay_certification_number_field").hide();
                        break;
                    }
                }).change();
            });
        </script>
        <?php

	} // meta_box_gtins()


	function meta_box_advanced( $post ) {
        ?>
        <style type="text/css">
            #wplister-ebay-advanced label {
            	float: left;
            	width: 33%;
            	line-height: 2em;
            }
            #wplister-ebay-advanced input,
            #wplister-ebay-advanced select.select {
            	width: 62%;
            }
            /* fix layout issue with woocommerce-etsy-integration-admin.css 1.0.0 */
            #wplister-ebay-advanced select.select.short {
            	width: 62% !important;
            }
            #wplister-ebay-advanced input.checkbox {
            	width:auto;
            }
            #wplister-ebay-advanced input.input_specs,
            #wplister-ebay-advanced input.select_specs {
            	width:100%;
            }

            #wplister-ebay-advanced .description {
            	clear: both;
            	display: block;
            	margin-left: 33%;
            }
            #wplister-ebay-advanced .wpl_ebay_hide_from_unlisted_field .description,
            #wplister-ebay-advanced .wpl_ebay_global_shipping_field .description,
            #wplister-ebay-advanced .wpl_ebay_ebayplus_enabled_field .description,
            #wplister-ebay-advanced .wpl_ebay_bestoffer_enabled_field .description {
            	margin-left: 0.3em;
				height: 1.4em;
				display: inline-block;
            	vertical-align: bottom;
            }

        </style>
        <?php
		do_action('wple_before_advanced_ebay_options');

		woocommerce_wp_text_input( array(
			'id' 			=> 'wpl_ebay_buynow_price',
			'label' 		=> __( 'Buy Now Price', 'wp-lister-for-ebay' ),
			'placeholder' 	=> __( 'Buy Now Price', 'wp-lister-for-ebay' ),
			'description' 	=> __( 'The optional Buy Now Price is only used for auction style listings. It has no effect on fixed price listings.', 'wp-lister-for-ebay' ),
			'desc_tip'		=>  true,
			'class' 		=> 'wc_input_price',
			'value'			=> wc_format_localized_price( get_post_meta( $post->ID, '_ebay_buynow_price', true ) )
		) );

		woocommerce_wp_text_input( array(
			'id' 			=> 'wpl_ebay_reserve_price',
			'label' 		=> __( 'Reserve Price', 'wp-lister-for-ebay' ),
			'placeholder' 	=> __( 'Reserve Price', 'wp-lister-for-ebay' ),
			'description' 	=> __( 'The lowest price at which you are willing to sell the item. Not all categories support a reserve price.<br>Note: This only applies to auction style listings.<br><br>Note: Setting a Reserve Price may incur additional listing fees.', 'wp-lister-for-ebay' ),
			'desc_tip'		=>  true,
			'class' 		=> 'wc_input_price',
			'value'			=> wc_format_localized_price( get_post_meta( $post->ID, '_ebay_reserve_price', true ) )
		) );

		woocommerce_wp_text_input( array(
			'id' 			=> 'wpl_ebay_gallery_image_url',
			'label' 		=> __( 'Gallery Image URL', 'wp-lister-for-ebay' ),
			'placeholder' 	=> __( 'Enter an URL if you want to use a custom gallery image on eBay.', 'wp-lister-for-ebay' ),
			'value'			=> get_post_meta( $post->ID, '_ebay_gallery_image_url', true )
		) );

		woocommerce_wp_checkbox( array(
			'id'    		=> 'wpl_ebay_hide_from_unlisted',
			'label' 		=> __( 'Hide from eBay', 'wp-lister-for-ebay' ),
			'description' 	=> __( 'Hide this product from the list of products currently not listed on eBay.', 'wp-lister-for-ebay' ),
			'value' 		=> get_post_meta( $post->ID, '_ebay_hide_from_unlisted', true )
		) );

		woocommerce_wp_checkbox( array(
			'id'    		=> 'wpl_ebay_global_shipping',
			'label' 		=> __( 'Global Shipping', 'wp-lister-for-ebay' ),
			'description' 	=> __( 'Enable eBay\'s Global Shipping Program for this product.', 'wp-lister-for-ebay' ),
			'value' 		=> get_post_meta( $post->ID, '_ebay_global_shipping', true )
		) );

		woocommerce_wp_checkbox( array(
			'id'    		=> 'wpl_ebay_ebayplus_enabled',
			'label' 		=> __( 'eBay Plus', 'wp-lister-for-ebay' ),
			'description' 	=> __( 'Enable this product to be offered via the eBay Plus program.', 'wp-lister-for-ebay' ),
			'value' 		=> get_post_meta( $post->ID, '_ebay_ebayplus_enabled', true )
		) );

		/*woocommerce_wp_checkbox( array(
			'id'    		=> 'wpl_ebay_bestoffer_enabled',
			'label' 		=> __( 'Best Offer', 'wp-lister-for-ebay' ),
			'description' 	=> __( 'Enable Best Offer to allow a buyer to make a lower-priced binding offer.', 'wp-lister-for-ebay' ),
			'value' 		=> get_post_meta( $post->ID, '_ebay_bestoffer_enabled', true )
		) );*/
        $bestoffer_options = array(
            ''  => __( '-- use profile setting --', 'wp-lister-for-ebay' ),
            'yes' => __( 'Yes', 'wp-lister-for-ebay' ),
            'no' => __( 'No', 'wp-lister-for-ebay' )
        );
		woocommerce_wp_select( array(
		    'id'            => 'wpl_ebay_bestoffer_enabled',
            'label'         => __( 'Best Offer', 'wp-lister-for-ebay' ),
            'description'  	=> __( 'Enable Best Offer to allow a buyer to make a lower-priced binding offer.', 'wp-lister-for-ebay' ),
            'desc_tip'      => true,
            'options' 		=> $bestoffer_options,
            'value' 		=> get_post_meta( $post->ID, '_ebay_bestoffer_enabled', true )
        ) );

		woocommerce_wp_text_input( array(
			'id' 			=> 'wpl_ebay_bo_autoaccept_price',
			'label' 		=> __( 'Auto accept price', 'wp-lister-for-ebay' ),
			'placeholder' 	=> __( 'The price at which Best Offers are automatically accepted.', 'wp-lister-for-ebay' ),
			'value'			=> wc_format_localized_price( get_post_meta( $post->ID, '_ebay_bo_autoaccept_price', true ) )
		) );

		woocommerce_wp_text_input( array(
			'id' 			=> 'wpl_ebay_bo_minimum_price',
			'label' 		=> __( 'Minimum price', 'wp-lister-for-ebay' ),
			'placeholder' 	=> __( 'Specifies the minimum acceptable Best Offer price.', 'wp-lister-for-ebay' ),
			'value'			=> wc_format_localized_price( get_post_meta( $post->ID, '_ebay_bo_minimum_price', true ) )
		) );


		## BEGIN PRO ##
		$autopay_options = array(
			''  => __( '-- use profile setting --', 'wp-lister-for-ebay' ),
			'1' => __( 'Yes, require immediate payment through PayPal', 'wp-lister-for-ebay' ),
			'0' => __( 'No', 'wp-lister-for-ebay' )
		);
		woocommerce_wp_select( array(
			'id' 			=> 'wpl_ebay_autopay',
			'label' 		=> __( 'Immediate payment', 'wp-lister-for-ebay' ),
			'options' 		=> $autopay_options,
			'value'			=> get_post_meta( $post->ID, '_ebay_autopay', true )
		) );
		## END PRO ##


		// get listing object
		$listing        = $this->get_current_ebay_item( $post );
		$wpl_account_id = $listing && $listing->account_id ? $listing->account_id : get_option( 'wplister_default_account_id' );
		$wpl_site_id    = $listing                         ? $listing->site_id    : get_option( 'wplister_ebay_site_id' );

		// get available seller profiles
		$wpl_seller_profiles_enabled	= get_option('wplister_ebay_seller_profiles_enabled');
		$wpl_seller_shipping_profiles	= get_option('wplister_ebay_seller_shipping_profiles');
		$wpl_seller_payment_profiles	= get_option('wplister_ebay_seller_payment_profiles');
		$wpl_seller_return_profiles		= get_option('wplister_ebay_seller_return_profiles');

		if ( isset( WPLE()->accounts[ $wpl_account_id ] ) ) {
			$account = WPLE()->accounts[ $wpl_account_id ];
			$wpl_seller_profiles_enabled  = $account->seller_profiles;
			$wpl_seller_shipping_profiles = maybe_unserialize( $account->shipping_profiles );
			$wpl_seller_payment_profiles  = maybe_unserialize( $account->payment_profiles );
			$wpl_seller_return_profiles   = maybe_unserialize( $account->return_profiles );
		}


		// $wpl_seller_profiles_enabled	= get_option('wplister_ebay_seller_profiles_enabled');
		if ( $wpl_seller_profiles_enabled ) {

			// $wpl_seller_shipping_profiles	= get_option('wplister_ebay_seller_shipping_profiles');
			// $wpl_seller_payment_profiles	= get_option('wplister_ebay_seller_payment_profiles');
			// $wpl_seller_return_profiles		= get_option('wplister_ebay_seller_return_profiles');
			// echo "<pre>";print_r($wpl_seller_payment_profiles);echo"</pre>";#die();

			if ( is_array( $wpl_seller_payment_profiles ) ) {

				$seller_payment_profiles = array( '' => __( '-- use profile setting --', 'wp-lister-for-ebay' ) );
				foreach ( $wpl_seller_payment_profiles as $seller_profile ) {
					$seller_payment_profiles[ $seller_profile->ProfileID ] = $seller_profile->ProfileName . ' - ' . $seller_profile->ShortSummary;
				}

				woocommerce_wp_select( array(
					'id' 			=> 'wpl_ebay_seller_payment_profile_id',
					'label' 		=> __( 'Payment policy', 'wp-lister-for-ebay' ),
					'options' 		=> $seller_payment_profiles,
					// 'description' 	=> __( 'Available conditions may vary for different categories.', 'wp-lister-for-ebay' ),
					'value'			=> get_post_meta( $post->ID, '_ebay_seller_payment_profile_id', true )
				) );

			}

			if ( is_array( $wpl_seller_return_profiles ) ) {

				$seller_return_profiles = array( '' => __( '-- use profile setting --', 'wp-lister-for-ebay' ) );
				foreach ( $wpl_seller_return_profiles as $seller_profile ) {
					$seller_return_profiles[ $seller_profile->ProfileID ] = $seller_profile->ProfileName . ' - ' . $seller_profile->ShortSummary;
				}

				woocommerce_wp_select( array(
					'id' 			=> 'wpl_ebay_seller_return_profile_id',
					'label' 		=> __( 'Return policy', 'wp-lister-for-ebay' ),
					'options' 		=> $seller_return_profiles,
					// 'description' 	=> __( 'Available conditions may vary for different categories.', 'wp-lister-for-ebay' ),
					'value'			=> get_post_meta( $post->ID, '_ebay_seller_return_profile_id', true )
				) );

			}

		}


		woocommerce_wp_textarea_input( array(
			'id'    => 'wpl_ebay_payment_instructions',
			'label' => __( 'Payment Instructions', 'wp-lister-for-ebay' ),
			'value' => get_post_meta( $post->ID, '_ebay_payment_instructions', true )
		) );

		// $this->showCompatibilityTable();
		// WPL_WooFrontEndIntegration::showCompatibilityList();

		if ( get_option( 'wplister_external_products_inventory' ) == 1 ) {
			$this->enabledInventoryOnExternalProducts( $post );
		}

		// woocommerce_wp_checkbox( array( 'id' => 'wpl_update_ebay_on_save', 'wrapper_class' => 'update_ebay', 'label' => __( 'Update on save?', 'wp-lister-for-ebay' ) ) );
		do_action('wple_after_advanced_ebay_options');

	} // meta_box_advanced()


	function meta_box_categories( $post ) {
        ?>
        <style type="text/css">

            #wplister-ebay-categories label {
            	float: left;
            	width: 33%;
            	line-height: 3em;
            }
            /*
            #wplister-ebay-categories input,
            #wplister-ebay-categories select.select {
            	width: 62%;
            }
            #wplister-ebay-categories input.checkbox {
            	width:auto;
            }
            #wplister-ebay-categories input.input_specs,
            #wplister-ebay-categories input.select_specs {
            	width:100%;
            } */

            #wplister-ebay-categories #ItemSpecifics_container input,
            #wplister-ebay-categories #ItemSpecifics_container select.select_specs {
            	width:90%;
            }
            #wplister-ebay-categories #ItemSpecifics_container input.select_specs_attrib {
            	width:100%;
            }
            #wplister-ebay-categories #ItemSpecifics_container th {
            	text-align: center;
            }
            #wplister-ebay-categories #EbayItemSpecificsBox .inside {
            	margin:0;
            	padding:0;
            }

            #wplister-ebay-categories .ebay_item_specifics_wrapper h4 {
            	padding-top: 0.5em;
            	padding-bottom: 0.5em;
            	margin-top: 1em;
            	margin-bottom: 0;
            	border-top: 1px solid #555;
            	border-top: 2px dashed #ddd;
            }

        </style>
        <?php

		$this->showCategoryOptions( $post );
		$this->showItemSpecifics( $post );

	} // meta_box_categories()

    function meta_box_gpsr( $post ) {
        $this->showGpsrForm( $post );
    }


	function include_character_count_script() {
		?>
		<script type="text/javascript">

			jQuery( document ).ready( function () {

				// ebay title character count
				jQuery('p.wpl_ebay_title_field').append('<span id="wpl_ebay_title_character_count" class="description" style="display:none"></span>');
				jQuery('#wpl_ebay_title').keyup( function(event) {
					var current_value = jQuery(this).val();
					var max_length    = jQuery(this).prop('maxlength');
					var msg           = ( max_length - current_value.length ) + ' characters left';
					jQuery('#wpl_ebay_title_character_count').html(msg).show();
				});

				// ebay subtitle character count
				jQuery('p.wpl_ebay_subtitle_field').append('<span id="wpl_ebay_subtitle_character_count" class="description" style="display:none"></span>');
				jQuery('#wpl_ebay_subtitle').keyup( function(event) {
					var current_value = jQuery(this).val();
					var max_length    = jQuery(this).prop('maxlength');
					var msg           = ( max_length - current_value.length ) + ' characters left';
					jQuery('#wpl_ebay_subtitle_character_count').html(msg).show();
				});

			});

		</script>
		<?php
	} // include_character_count_script()

	function meta_box_compat( $post ) {
		$this->showCompatibilityTable( $post );
	}

	function showCategoryOptions( $post ) {

		// get listing object
		$listing        = $this->get_current_ebay_item( $post ); /* @todo See how this affects posts with multiple listings linked to it */
		$wpl_account_id = $listing && $listing->account_id ? $listing->account_id : get_option( 'wplister_default_account_id' );
		$wpl_site_id    = $listing                         ? $listing->site_id    : get_option( 'wplister_ebay_site_id' );

        $ib = new ItemBuilderModel();
        $mapped_categories = $ib->getMappedCategories( $post->ID, $wpl_account_id );

		$default_text = '<span style="color:silver"><i>&mdash; ' . __( 'will be assigned automatically', 'wp-lister-for-ebay' ) . ' &mdash;</i></span>';

		// primary ebay category
		$ebay_category_1_id   = get_post_meta( $post->ID, '_ebay_category_1_id', true );
		$ebay_category_1_name = $ebay_category_1_id ? EbayCategoriesModel::getFullEbayCategoryName( $ebay_category_1_id, $wpl_site_id ) : $default_text;

		// Store the eBay Category that will be used for pulling the Item Specifics
        $item_specs_category_id = $ebay_category_1_id;

			// secondary ebay category
		$ebay_category_2_id   = get_post_meta( $post->ID, '_ebay_category_2_id', true );
		$ebay_category_2_name = $ebay_category_2_id ? EbayCategoriesModel::getFullEbayCategoryName( $ebay_category_2_id, $wpl_site_id ) : $default_text;

		// primary store category
		$store_category_1_id   = get_post_meta( $post->ID, '_ebay_store_category_1_id', true );
		$store_category_1_name = $store_category_1_id ? EbayCategoriesModel::getFullStoreCategoryName( $store_category_1_id, $wpl_account_id ) : $default_text;

		// secondary store category
		$store_category_2_id   = get_post_meta( $post->ID, '_ebay_store_category_2_id', true );
		$store_category_2_name = $store_category_2_id ? EbayCategoriesModel::getFullStoreCategoryName( $store_category_2_id, $wpl_account_id ) : $default_text;

		// if no eBay category selected on product level, check profile
		$profile = $this->get_current_listing_profile( $post );

		if ( !$listing ) {
            // use default category
            $primary_category_id = get_option('wplister_default_ebay_category_id');

		    // New products/non-listings should already show the default mapped category
            if ( $primary_category_id ) {
                $item_specs_category_id = $primary_category_id;
                $ebay_category_1_name = EbayCategoriesModel::getFullEbayCategoryName( $primary_category_id, $wpl_site_id);
                $ebay_category_1_name = '<span style="color:silver">Default category: ' . $ebay_category_1_name . ' </span>';
            }
        }

		if ( $profile && ( empty($ebay_category_1_id) || empty($ebay_category_2_id) ) ) {
			if ( ! $ebay_category_1_id ) {
				if ( $mapped_categories['primary'] ) {
					$ebay_category_1_name = EbayCategoriesModel::getFullEbayCategoryName( $mapped_categories['primary'], $wpl_site_id);
					$ebay_category_1_name = '<span style="color:silver">Mapped category: ' . $ebay_category_1_name . ' </span>';
                    $item_specs_category_id = $mapped_categories['primary'];
				} else {
					if ($profile['details']['ebay_category_1_id']) {
						$ebay_category_1_name = EbayCategoriesModel::getFullEbayCategoryName( $profile['details']['ebay_category_1_id'], $wpl_site_id );
						$ebay_category_1_name = '<span style="color:silver">Profile category: ' . $ebay_category_1_name . ' </span>';
                        $item_specs_category_id = $profile['details']['ebay_category_1_id'];
					}
				}
			}

			if ( ! $ebay_category_2_id && $profile['details']['ebay_category_2_id'] ) {
				$ebay_category_2_name = EbayCategoriesModel::getFullEbayCategoryName( $profile['details']['ebay_category_2_id'], $wpl_site_id );
				$ebay_category_2_name = '<span style="color:silver">Profile category: ' . $ebay_category_2_name . ' </span>';
			}
		}

		// if no Store category selected on product level, check profile
		if ( $profile && ( empty($store_category_1_id) || empty($store_category_2_id) ) ) {
			if ( ! $store_category_1_id && $profile['details']['store_category_1_id'] ) {
				$store_category_1_name = EbayCategoriesModel::getFullStoreCategoryName( $profile['details']['store_category_1_id'], $wpl_account_id );
				$store_category_1_name = '<span style="color:silver">Profile category: ' . $store_category_1_name . ' </span>';
			}
			if ( ! $store_category_2_id && $profile['details']['store_category_2_id'] ) {
				$store_category_2_name = EbayCategoriesModel::getFullStoreCategoryName( $profile['details']['store_category_2_id'], $wpl_account_id );
				$store_category_2_name = '<span style="color:silver">Profile category: ' . $store_category_2_name . ' </span>';
			}
		}


		$store_categories_message  = 'Note: eBay <i>Store</i> categories are selected automatically based on the product categories assigned and your ';
		$store_categories_message .= '<a href="admin.php?page=wplister-settings&tab=categories" target="_blank">category settings</a>.';

		// if ( $profile && ( $profile['details']['store_category_1_id'] || $profile['details']['store_category_2_id'] ) ) {
		// 	// $store_categories_message .= ' - unless you set specific store categories in your listing profile or on this page. ';
		// } else {
		// 	// $store_categories_message .= '. Your listing profile <b>'.$profile['profile_name'].'</b> does not use any store categories.';
		// 	// $store_categories_message .= '.';
		// }

		?>

		<h4><?php echo __( 'eBay categories', 'wp-lister-for-ebay' ) ?></h4>

		<div style="position:relative; margin: 0 5px;">
			<label for="wpl-text-ebay_category_1_name" class="text_label"><?php echo __( 'Primary eBay category', 'wp-lister-for-ebay' ); ?></label>
			<input type="hidden" name="wpl_ebay_category_1_id" id="ebay_category_id_1" value="<?php echo $ebay_category_1_id ?>" class="" />
			<input type="hidden" name="wpl_ebay_item_specifics_category_id" id="ebay_item_specifics_category_id" value="<?php echo $item_specs_category_id; ?>" class="" />
			<span  id="ebay_category_name_1" class="text_input" style="width:45%;float:left;line-height:3em;"><?php echo $ebay_category_1_name ?></span>
			<div class="category_row_actions">
				<input type="button" value="<?php echo __( 'select', 'wp-lister-for-ebay' ); ?>" class="button btn_select_ebay_category" onclick="">
				<input type="button" value="<?php echo __( 'remove', 'wp-lister-for-ebay' ); ?>" class="button btn_remove_ebay_category" onclick="">
			</div>
		</div>
		<br style="clear:both" />
		<div style="position:relative; margin: 0 5px;">
			<label for="wpl-text-ebay_category_2_name" class="text_label"><?php echo __( 'Secondary eBay category', 'wp-lister-for-ebay' ); ?></label>
			<input type="hidden" name="wpl_ebay_category_2_id" id="ebay_category_id_2" value="<?php echo $ebay_category_2_id ?>" class="" />
			<span  id="ebay_category_name_2" class="text_input" style="width:45%;float:left;line-height:3em;"><?php echo $ebay_category_2_name ?></span>
			<div class="category_row_actions">
				<input type="button" value="<?php echo __( 'select', 'wp-lister-for-ebay' ); ?>" class="button btn_select_ebay_category" onclick="">
				<input type="button" value="<?php echo __( 'remove', 'wp-lister-for-ebay' ); ?>" class="button btn_remove_ebay_category" onclick="">
			</div>
		</div>
		<br style="clear:both" />

		<h4><?php echo __( 'Store categories', 'wp-lister-for-ebay' ) ?></h4>

		<div style="position:relative; margin: 0 5px;">
			<label for="wpl-text-store_category_1_name" class="text_label">
				<?php echo __( 'Store category', 'wp-lister-for-ebay' ); ?> 1
            	<?php wplister_tooltip(__('<b>Store category</b><br>A custom category that the seller created in their eBay Store.<br><br>
            							eBay Stores sellers can create up to three levels of custom categories for their stores. Items can only be listed in root categories, or categories that have no child categories (subcategories).', 'wp-lister-for-ebay') ) ?>
			</label>
			<input type="hidden" name="wpl_ebay_store_category_1_id" id="store_category_id_1" value="<?php echo $store_category_1_id; ?>" class="" />
			<span  id="store_category_name_1" class="text_input" style="width:45%;float:left;line-height:3em;"><?php echo $store_category_1_name; ?></span>
			<div class="category_row_actions">
				<input type="button" value="<?php echo __( 'select', 'wp-lister-for-ebay' ); ?>" class="button btn_select_store_category" onclick="">
				<input type="button" value="<?php echo __( 'remove', 'wp-lister-for-ebay' ); ?>" class="button btn_remove_store_category" onclick="">
			</div>
		</div>

		<div style="position:relative; margin: 0 5px; clear:both">
			<label for="wpl-text-store_category_2_name" class="text_label">
				<?php echo __( 'Store category', 'wp-lister-for-ebay' ); ?> 2
            	<?php wplister_tooltip(__('<b>Store category</b><br>A custom category that the seller created in their eBay Store.<br><br>
            							eBay Stores sellers can create up to three levels of custom categories for their stores. Items can only be listed in root categories, or categories that have no child categories (subcategories).', 'wp-lister-for-ebay')) ?>
			</label>
			<input type="hidden" name="wpl_ebay_store_category_2_id" id="store_category_id_2" value="<?php echo $store_category_2_id; ?>" class="" />
			<span  id="store_category_name_2" class="text_input" style="width:45%;float:left;line-height:3em;"><?php echo $store_category_2_name; ?></span>
			<div class="category_row_actions">
				<input type="button" value="<?php echo __( 'select', 'wp-lister-for-ebay' ); ?>" class="button btn_select_store_category" onclick="">
				<input type="button" value="<?php echo __( 'remove', 'wp-lister-for-ebay' ); ?>" class="button btn_remove_store_category" onclick="">
			</div>
		</div>
		<br style="clear:both" />

		<p>
			<small><?php echo $store_categories_message ?></small>
		</p>


		<!-- hidden ajax categories tree -->
		<div id="ebay_categories_tree_wrapper">
			<div id="ebay_categories_tree_container"></div>
		</div>
		<!-- hidden ajax categories tree -->
		<div id="store_categories_tree_wrapper">
			<div id="store_categories_tree_container"></div>
		</div>

		<style type="text/css">

			#ebay_categories_tree_wrapper,
			#store_categories_tree_wrapper {
				/*max-height: 320px;*/
				/*margin-left: 35%;*/
				overflow: auto;
				width: 65%;
				display: none;
			}

			#wplister-ebay-categories .category_row_actions {
				position: absolute;
				top: 0;
				right: 0;
			}
            #wplister-ebay-categories .category_row_actions input {
            	width: auto;
            }


			a.link_select_category {
				float: right;
				padding-top: 3px;
				text-decoration: none;
			}
			a.link_remove_category {
				padding-left: 3px;
				text-decoration: none;
			}

		</style>

		<script type="text/javascript">

			var wpl_site_id    = '<?php echo $wpl_site_id ?>';
			var wpl_account_id = '<?php echo $wpl_account_id ?>';
			var wple_ajax_nonce = '<?php echo wp_create_nonce( 'wple_ajax_nonce' ); ?>';

			/* recusive function to gather the full category path names */
	        function wpl_getCategoryPathName( pathArray, depth ) {
				var pathname = '';
				if (typeof depth == 'undefined' ) depth = 0;

	        	// get name
		        if ( depth == 0 ) {
		        	var cat_name = jQuery('[rel=' + pathArray.join('\\\/') + ']').html();
		        } else {
			        var cat_name = jQuery('[rel=' + pathArray.join('\\\/') +'\\\/'+ ']').html();
		        }

		        // console.log('path...: ', pathArray.join('\\\/') );
		        // console.log('catname: ', cat_name);
		        // console.log('pathArray: ', pathArray);

		        // strip last (current) item
		        popped = pathArray.pop();
		        // console.log('popped: ',popped);

		        // call self with parent path
		        if ( pathArray.length > 2 ) {
			        pathname = wpl_getCategoryPathName( pathArray, depth + 1 ) + ' &raquo; ' + cat_name;
		        } else if ( pathArray.length > 1 ) {
			        pathname = cat_name;
		        }

		        return pathname;

	        }

			jQuery( document ).ready(
				function () {
					// select ebay category button
					jQuery('input.btn_select_ebay_category').click( function(event) {
						// var cat_id = jQuery(this).parent()[0].id.split('sel_ebay_cat_id_')[1];
						e2e_selecting_cat = ('ebay_category_name_1' == jQuery(this).parent().parent().first().find('.text_input')[0].id) ? 1 : 2;

						var tbHeight = tb_getPageSize()[1] - 120;
						var tbURL = "#TB_inline?height="+tbHeight+"&width=753&inlineId=ebay_categories_tree_wrapper";
	        			tb_show("Select a category", tbURL);

					});
					// remove ebay category button
					jQuery('input.btn_remove_ebay_category').click( function(event) {
						var cat_id = ('ebay_category_name_1' == jQuery(this).parent().parent().first().find('.text_input')[0].id) ? 1 : 2;

						jQuery('#ebay_category_id_'+cat_id).prop('value','');
						jQuery('#ebay_category_name_'+cat_id).html('');
					});

					// select store category button
					jQuery('input.btn_select_store_category').click( function(event) {
						// var cat_id = jQuery(this).parent()[0].id.split('sel_store_cat_id_')[1];
						e2e_selecting_cat = ('store_category_name_1' == jQuery(this).parent().parent().first().find('.text_input')[0].id) ? 1 : 2;

						var tbHeight = tb_getPageSize()[1] - 120;
						var tbURL = "#TB_inline?height="+tbHeight+"&width=753&inlineId=store_categories_tree_wrapper";
	        			tb_show("Select a category", tbURL);

					});
					// remove store category button
					jQuery('input.btn_remove_store_category').click( function(event) {
						var cat_id = ('store_category_name_1' == jQuery(this).parent().parent().first().find('.text_input')[0].id) ? 1 : 2;

						jQuery('#store_category_id_'+cat_id).prop('value','');
						jQuery('#store_category_name_'+cat_id).html('');
					});


					// jqueryFileTree 1 - ebay categories
				    jQuery('#ebay_categories_tree_container').fileTree({
				        root: '/0/',
				        script: ajaxurl+'?action=wple_get_ebay_categories_tree&site_id='+wpl_site_id+'&_wpnonce='+wple_ajax_nonce,
				        expandSpeed: 400,
				        collapseSpeed: 400,
				        loadMessage: 'loading eBay categories...',
				        multiFolder: false
				    }, function(catpath) {

						// get cat id from full path
				        var cat_id = catpath.split('/').pop(); // get last item - like php basename()

				        // get name of selected category
				        var cat_name = '';

				        var pathname = wpl_getCategoryPathName( catpath.split('/') );
						// console.log('pathname: ',pathname);

				        // update fields
				        jQuery('#ebay_category_id_'+e2e_selecting_cat).prop( 'value', cat_id );
				        jQuery('#ebay_category_name_'+e2e_selecting_cat).html( pathname );

				        // close thickbox
				        tb_remove();

				        if ( e2e_selecting_cat == 1 ) {
				        	updateItemSpecifics();
				        // 	updateItemConditions();
				        }

				    });

					// jqueryFileTree 2 - store categories
				    jQuery('#store_categories_tree_container').fileTree({
				        root: '/0/',
				        script: ajaxurl+'?action=wple_get_store_categories_tree&account_id='+wpl_account_id+'&_wpnonce='+wple_ajax_nonce,
				        expandSpeed: 400,
				        collapseSpeed: 400,
				        loadMessage: 'loading store categories...',
				        multiFolder: false
				    }, function(catpath) {

						// get cat id from full path
				        var cat_id = catpath.split('/').pop(); // get last item - like php basename()

				        // get name of selected category
				        var cat_name = '';

				        var pathname = wpl_getCategoryPathName( catpath.split('/') );
						// console.log('pathname: ',pathname);

						if ( pathname.indexOf('[use this category]') > -1 ) {
							catpath = catpath + '/';
							pathname = wpl_getCategoryPathName( catpath.split('/') );
						}

				        // update fields
				        jQuery('#store_category_id_'+e2e_selecting_cat).prop( 'value', cat_id );
				        jQuery('#store_category_name_'+e2e_selecting_cat).html( pathname );

				        // close thickbox
				        tb_remove();

				    });

                    updateItemSpecifics();

				}
			);


		</script>

		<?php

	} // showCategoryOptions()

	// show editable parts compatibility table
	function showCompatibilityTable( $post ) {

		$has_compat_table = true;

		// get compatibility list and names
		$compatibility_list   = wple_get_compatibility_list( $post->ID );
		$compatibility_names  = wple_get_compatibility_names( $post->ID );
		// echo "<pre>cols: ";print_r($compatibility_names);echo"</pre>";#die();
		// echo "<pre>rows: ";print_r($compatibility_list);echo"</pre>";#die();

		// return if there is no compatibility list
		// if ( ( ! is_array($compatibility_list) ) || ( sizeof($compatibility_list) == 0 ) ) return;

		// empty default table
		if ( ( ! is_array($compatibility_list) ) || ( sizeof($compatibility_list) == 0 ) ) {
			// if ( ! get_option( 'wplister_enable_compatibility_table' ) ) return;

			// $compatibility_names = array('Make','Model','Year');
			// $compatibility_list  = array();
			$has_compat_table = false;
		}

		?>
			<div class="ebay_item_compatibility_table_wrapper" style="<?php echo $has_compat_table ? '' : 'display:none' ?>">

				<?php if ( $has_compat_table ) : ?>
				<table class="ebay_item_compatibility_table">

					<tr>
						<?php foreach ($compatibility_names as $name) : ?>
							<th><?php echo $name ?></th>
						<?php endforeach; ?>
						<th><?php echo 'Notes' ?></th>
					</tr>

					<?php foreach ($compatibility_list as $comp) : ?>

						<tr>
							<?php foreach ($compatibility_names as $name) : ?>

								<td><?php echo $comp->applications[ $name ]->value ?></td>

							<?php endforeach; ?>

							<td><?php echo $comp->notes ?></td>

						</tr>

					<?php endforeach; ?>
				</table>
				<?php endif; ?>

				<div style="float:right; margin-top:1em;">
					<a href="#" id="wpl_btn_remove_compatibility_table" class="button"><?php echo __( 'Clear all', 'wp-lister-for-ebay' ) ?></a>
					<a href="#" id="wpl_btn_add_compatibility_row" class="button"><?php echo __( 'Add row', 'wp-lister-for-ebay' ) ?></a>
				</div>
				<p>
					<?php echo __( 'To remove a row empty the first column and update.', 'wp-lister-for-ebay' ) ?>
				</p>

			</div>

			<a href="#" id="wpl_btn_add_compatibility_table" class="button" style="<?php echo $has_compat_table ? 'display:none' : '' ?>">
				<?php echo __( 'Add compatibility table', 'wp-lister-for-ebay' ) ?>
			</a>

			<input type="hidden" name="wpl_e2e_compatibility_list"   id="wpl_e2e_compatibility_list"   value='<?php #echo json_encode($compatibility_list)  ?>' />
			<input type="hidden" name="wpl_e2e_compatibility_names"  id="wpl_e2e_compatibility_names"  value='<?php #echo json_encode($compatibility_names) ?>' />
			<input type="hidden" name="wpl_e2e_compatibility_remove" id="wpl_e2e_compatibility_remove" value='' />

			<style type="text/css">

				.ebay_item_compatibility_table {
					width: 100%;
				}
				.ebay_item_compatibility_table tr th {
					text-align: left;
					border-bottom: 3px double #bbb;
				}
				.ebay_item_compatibility_table tr td {
					border-bottom: 1px solid #ccc;
				}
				#wpl_btn_add_compatibility_row {
					/*float: right;*/
				}

			</style>

			<script type="text/javascript">

				jQuery( document ).ready( function () {

					// make table editable
					wpl_initCompatTable();

					// handle add row button
					jQuery('#wpl_btn_add_compatibility_row').on('click', function(evt) {

						// clone the last row and append to table
						jQuery('table.ebay_item_compatibility_table tr:last').last().clone().insertAfter('table.ebay_item_compatibility_table tr:last');

						// update listener
						jQuery('table.ebay_item_compatibility_table td').on('change', function(evt, newValue) {
							wpl_updateTableData();
						});

						return false; // reject change
					});

					// handle remove table button
					jQuery('#wpl_btn_remove_compatibility_table').on('click', function(evt) {
						var confirmed = confirm("<?php echo __( 'Are you sure you want to remove the entire table?', 'wp-lister-for-ebay' ) ?>");
						if ( confirmed ) {

							// remove table
							jQuery('table.ebay_item_compatibility_table').remove();

							// hide table wrapper
							jQuery('.ebay_item_compatibility_table_wrapper').slideUp();

							// show add table button
							jQuery('#wpl_btn_add_compatibility_table').show();

							// clear data
				            jQuery('#wpl_e2e_compatibility_list'  ).prop('value', '' );
				            jQuery('#wpl_e2e_compatibility_names' ).prop('value', '' );
				            jQuery('#wpl_e2e_compatibility_remove').prop('value', 'yes' );

						}
						return false;
					});

					// handle add table button
					jQuery('#wpl_btn_add_compatibility_table').on('click', function(evt) {

						// var default_headers = ['Make','Model','Year'];
						var default_headers = prompt('Please enter the table columns separated by comma:','Make,Model,Year').split(',');

						// create table
						jQuery('div.ebay_item_compatibility_table_wrapper').prepend('<table class="ebay_item_compatibility_table"></table>');
						jQuery('table.ebay_item_compatibility_table').append('<tr></tr>');
						jQuery('table.ebay_item_compatibility_table').append('<tr></tr>');
						for (var i = default_headers.length - 1; i >= 0; i--) {
							var col_name = default_headers[i];
							jQuery('table.ebay_item_compatibility_table tr:first').prepend('<th>'+jQuery.trim(col_name)+'</th>');
							jQuery('table.ebay_item_compatibility_table tr:last' ).prepend('<td>Enter '+col_name+'...</td>');
						};
						jQuery('table.ebay_item_compatibility_table tr:first').append('<th>'+'Notes'+'</th>');
						jQuery('table.ebay_item_compatibility_table tr:last' ).append('<td></td>');

						// show table
						jQuery('.ebay_item_compatibility_table_wrapper').slideToggle();

						// hide button
						jQuery('#wpl_btn_add_compatibility_table').hide();

						// make table editable
						wpl_initCompatTable();

						return false; // reject change
					});

				});


		        function wpl_initCompatTable() {

					// make table editable
					jQuery('table.ebay_item_compatibility_table').editableTableWidget();

					// listen to submit
					// jQuery('form#post').on('submit', function(evt, value) {
					// 	console.log(evt);
					// 	console.log(value);
					// 	alert( evt + value );
					// 	return false;
					// });

					// listen to changes
					jQuery('table.ebay_item_compatibility_table td').on('change', function(evt, newValue) {
						// update hidden data fields
						wpl_updateTableData();
						// return false; // reject change
					});

				};


		        function wpl_updateTableData() {
		            var row = 0, data = [], cols = [];

		            jQuery('table.ebay_item_compatibility_table').find('tr').each(function () {

		                row += 1;
		                data[row] = [];

		                jQuery(this).find('td').each(function () {
		                    data[row].push(jQuery(this).html());
		                });

		                jQuery(this).find('th').each(function () {
		                    cols.push(jQuery(this).html());
		                });
		            });

		            // Remove undefined
		            data.splice(0, 2);

		            console.log('data',data);
		            // console.log('string', JSON.stringify(data) );
		            // alert(data);

		            // update hidden field
		            jQuery('#wpl_e2e_compatibility_list').prop('value', JSON.stringify(data) );
		            jQuery('#wpl_e2e_compatibility_names').prop('value', JSON.stringify(cols) );
		            jQuery('#wpl_e2e_compatibility_remove').prop('value', '' );

		            // return data;
		        }


			</script>

		<?php

		wp_enqueue_script( 'jquery-editable-table' );

	} // showCompatibilityTable()

	function showItemSpecifics( $post ) {


		// get data
		$wpl_available_attributes     = ProductWrapper::getAttributeTaxonomies();
		$wpl_default_ebay_category_id = 0;

		// $specifics contains all available item specifics for the selected category
		// $item_specifics contains values set for this particular product / profile
		$specifics                    = array();
		$item_specifics               = get_post_meta( $post->ID, '_ebay_item_specifics', true );


		// get listing object
		$listing        = $this->get_current_ebay_item( $post );
		$wpl_account_id = $listing && $listing->account_id ? $listing->account_id : get_option( 'wplister_default_account_id' );
		$wpl_site_id    = $listing                         ? $listing->site_id    : get_option( 'wplister_ebay_site_id' );
		$post_id        = $post->ID;
		$profile        = $this->get_current_listing_profile( $post );

        $wpl_default_ebay_category_id   = ( $wpl_account_id && !empty( WPLE()->accounts[ $wpl_account_id ] ) ) ? WPLE()->accounts[ $wpl_account_id ]->default_ebay_category_id : get_option('wplister_default_ebay_category_id');

        if ( $listing ) {
	        $listing_obj            = new \WPLab\Ebay\Listings\Listing( $listing->id );
	        $primary_ebay_category  = $listing_obj->getPrimaryCategory( $post_id );
        }

		// load specifics if we have a category
		if ( !empty( $primary_ebay_category ) ) {
			$specifics = EbayCategoriesModel::getItemSpecificsForCategory( $primary_ebay_category, false, $wpl_account_id );
		} elseif ( $wpl_default_ebay_category_id ) {
			$specifics = EbayCategoriesModel::getItemSpecificsForCategory( $wpl_default_ebay_category_id, false, $wpl_account_id );
		}


		// process custom attributes
		$wpl_custom_attributes = wple_get_custom_attributes();

		// Toggles the enhanced item specifics UI
        $enhanced_ui = get_option( 'wplister_enhanced_item_specifics_ui', 0 );

		echo '<div class="ebay_item_specifics_wrapper">';
		echo '<h4>'.  __( 'Item Specifics', 'wp-lister-for-ebay' ) . '</h4>';
		include( WPLE_PLUGIN_PATH . '/views/profile/edit_item_specifics.php' );

		// let the user know which category the available item specifics are based on
		if ( $profile && isset( $profile['details']['ebay_category_1_id'] ) ) {
			$profile_link = '<a href="admin.php?page=wplister-profiles&action=edit&profile='.$profile['profile_id'].'" target="_blank">'.$profile['profile_name'].'</a>';
			echo '<small>These options are based on the selected profile <b>'.$profile_link.'</b> and its primary eBay category <b>'.$profile['details']['ebay_category_1_name'].'</b>.</small>';
		} elseif ( !empty( $primary_ebay_category ) && isset($categories_map_ebay) ) {
			$category_path = EbayCategoriesModel::getFullEbayCategoryName( $primary_ebay_category, $wpl_site_id );
			echo '<small>Item specifics are based on the eBay category <b>'.$category_path.'</b> according to your category settings.</small>';
		}

		echo '</div>';

	} // showItemSpecifics()

    function showGpsrForm( $post ) {
	    $listing        = $this->get_current_ebay_item( $post );
	    $wpl_account_id = $listing && $listing->account_id ? $listing->account_id : get_option( 'wplister_default_account_id' );
	    $wpl_site_id    = $listing                         ? $listing->site_id    : get_option( 'wplister_ebay_site_id' );
        $wpl_site = new WPLE_eBaySite( $wpl_site_id );
	    $hazardous_materials_labels = maybe_unserialize( $wpl_site->HazardousMaterialsLabels );
        $product_safety_labels      = maybe_unserialize( $wpl_site->ProductSafetyLabels );
	    $available_attributes       = ProductWrapper::getAttributeTaxonomies();

        $item_details = [
            'gpsr_enabled'                              => '',
            'gpsr_documents'                            => [],
            'gpsr_repair_score'                         => '',
            'gpsr_energy_efficiency_image'              => '',
            'gpsr_energy_efficiency_image_eps'          => '',
            'gpsr_energy_efficiency_label_description'  => '',
            'gpsr_energy_efficiency_sheet_image'        => '',
            'gpsr_energy_efficiency_sheet_image_eps'    => '',
            'gpsr_hazmat_component'                     => '',
            'gpsr_hazmat_pictograms'                    => [],
            'gpsr_hazmat_signalword'                    => '',
            'gpsr_hazmat_statements'                    => [],
            'gpsr_manufacturer'                         => '',
            'gpsr_product_safety_component'             => '',
            'gpsr_product_safety_pictograms'            => [],
            'gpsr_product_safety_statements'            => [],
            'gpsr_responsible_persons'                  => []
        ];

        foreach ( $item_details as $key => $value ) {
            $value = get_post_meta( $post->ID, '_ebay_'.$key, true );

            if ( $value !== "" ) {

                if (in_array( $key, ['gpsr_hazmat_pictograms','gpsr_hazmat_statements','gpsr_product_safety_pictograms','gpsr_product_safety_statements'])) {
                    if ( !is_array( $value ) ) {
	                    $value = array_map( 'trim', explode(',', $value) );
                    }
                }

                $item_details[ $key ] = $value;
            }
        }
	    ?>
        <style>
            #persons_frm .form-field, #manufacturers_frm .form-field, #documents_frm .form-field {
                margin: 1em 0;
            }

            #persons_frm .form-field select, #manufacturers_frm .form-field select, #documents_frm .form-field select {
                width: 95%;
            }

            #gpsr_container a.button {
                vertical-align: middle;
            }

            .wple_select {
                width: 65%;
            }
            .select2-container, .chosen-container {
                margin: 4px;
            }
            .select2-container--default .select2-results__option--highlighted[aria-selected], .select2-container--default .select2-results__option--highlighted[data-selected] {
                color: #000;
            }

            a.trash-image {
                color: red;
                vertical-align: bottom;
            }
            .gpsr-custom-data {
                float: left;
                width: 65%;
            }
            .gpsr-image-block {
                float: left;
            }

            .hidden {
                display: none;
            }

            #responsible_persons_modal, #manufacturers_modal, #documents_modal {
                display: none;
            }

            #responsible_persons_modal_container #persons_list, #manufacturers_modal_container #manufacturers_list {
                float: right;
                width: 55%;
            }
            #responsible_persons_modal_container #persons_list .address, #manufacturers_modal_container #manufacturers_list .address {
                border: 1px solid #EEE;
                padding: 5px !important;
                margin: 5px 0;
            }
            #responsible_persons_modal_container #persons_list .address h4, #responsible_persons_modal_container #persons_list .address p,
            #manufacturers_modal_container #manufacturers_list .address h4, #manufacturers_modal_container #manufacturers_list .address p{
                margin: 0;
            }
            #responsible_persons_modal_container #persons_list .address a.delete,
            #manufacturers_modal_container #manufacturers_list .address a.delete {
                color: #b32d2e;
            }
            #responsible_persons_modal_container #form, #manufacturers_modal_container #form {
                float: left;
                width: 45%;
            }
            #responsible_persons_modal_container #form label, #manufacturers_modal_container #form label, #documents_modal_container #new_document_form label {
                display: block;
            }
            .wpl-address-box {
                position: relative;
                width: 250px;
                border: 1px solid #ccc;
                background-color: #fff;
                border-radius: 5px;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
                padding: 10px;
            }
        </style>

        <p>This section is only applicable if you are shipping to EU and NI buyers.</p>

        <p class="form-field wpl_ebay_gpsr_enabled_field ">
            <label for="wpl-text-gpsr-enabled" class="text_label">
			    <?php echo __( 'Enable GPSR', 'wp-lister-for-ebay' ); ?>
			    <?php wplister_tooltip(__('Enable this to include the GPSR data in your listings.', 'wp-lister-for-ebay')) ?>
            </label>
            <select id="wpl-text-gpsr_enabled" name="wpl_e2e_gpsr_enabled" title="General Product Safety Regulation" class=" required-entry select">
                <option value="" <?php selected( $item_details['gpsr_enabled'], '' ) ?>><?php echo __( '-- use profile setting --', 'wp-lister-for-ebay' ); ?></option>
                <option value="0" <?php selected( $item_details['gpsr_enabled'], 0 ) ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
                <option value="1" <?php selected( $item_details['gpsr_enabled'], 1 ); ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
            </select>
            <br class="clear" />
        </p>

        <div id="gpsr_container">

            <label for="wpl-text-gpsr-repair-score" class="text_label">
			    <?php echo __( 'Repair Score', 'wp-lister-for-ebay' ); ?>
			    <?php wplister_tooltip(__('The repair index identifies the manufacturer\'s repair score for a product (how easy is it to repair the product). This field is a floating point value between 0 and 10 but may only have one digit beyond the decimal point, for example: 7.9.<br/><br/>Note: 0 should not be used as a default value, as it implies that the product is not repairable.', 'wp-lister-for-ebay')) ?>
            </label>
            <input type="text" placeholder="e.g. 7.9" name="wpl_e2e_gpsr_repair_score" size="5" value="<?php echo esc_attr($item_details['gpsr_repair_score'] ?? ''); ?>" id="wpl-text-gpsr-repair-score" >
            <br class="clear" />

            <h4><?php _e('Energy Efficiency Label', 'wp-lister-for-ebay' ); ?></h4>

            <label for="wpl_gpsr_energy_efficiency_image" class="text_label">
			    <?php echo __( 'Label Image', 'wp-lister-for-ebay' ); ?>
			    <?php wplister_tooltip(__('The Energy Efficiency Label image that is applicable to an item. This field is required if an Energy Efficiency Label is provided.', 'wp-lister-for-ebay')); ?>
            </label>

            <?php
            $custom_url = get_post_meta( $post->ID, '_ebay_gpsr_energy_efficiency_image_url', true );

            if ( $custom_url ):
            ?>
                <input type="text" name="wpl_e2e_gpsr_energy_efficiency_image_url" id="wpl_gpsr_energy_efficiency_image_url" value="<?php echo esc_attr( $custom_url ); ?>" class="text_input" />
                <input type="hidden" name="wpl_e2e_gpsr_energy_efficiency_image_eps" value="<?php echo esc_attr( $item_details['gpsr_energy_efficiency_image_eps'] ?? '' ); ?>" class="regular-text" />
            <?php else: ?>
                <div class="gpsr-image-block">
                    <?php
                    $src = '#';
                    if ( !empty( $item_details['gpsr_energy_efficiency_image'] ) && $url = wp_get_attachment_image_url( $item_details['gpsr_energy_efficiency_image'] ) ) {
                        $src = $url;
                    }
                    ?>
                    <input type="hidden" name="wpl_e2e_gpsr_energy_efficiency_image" id="wpl_gpsr_energy_efficiency_image" value="<?php echo esc_attr( $item_details['gpsr_energy_efficiency_image'] ?? '' ); ?>" class="regular-text" />
                    <input type="hidden" name="wpl_e2e_gpsr_energy_efficiency_image_eps" value="<?php echo esc_attr( $item_details['gpsr_energy_efficiency_image_eps'] ?? '' ); ?>" class="regular-text" />
                    <image src="<?php echo $src; ?>" id="gpsr_energy_efficiency_image" height="80" class="photo gpsr-photo <?php echo !empty($item_details['gpsr_energy_efficiency_image']) ? '' : 'hidden'; ?> " />
                    <br/>
                    <a href="#" id="trash_gpsr_energy_efficiency_image" class="trash-image wple-delete-upload <?php echo !empty($item_details['gpsr_energy_efficiency_image']) ? '' : 'hidden'; ?>" data-target="gpsr_energy_efficiency_image"><?php esc_attr_e( 'Remove image', 'wp-lister-for-ebay' ); ?></a>
                    <a href="#" type='button' class="button wple-uploader" data-target="gpsr_energy_efficiency_image" id="wple_media_manager"><?php _e( 'Select a image', 'wp-lister-for-ebay' ); ?></a>
                </div>
            <?php endif; ?>

            <br class="clear" />

            <label for="wpl-text-gpsr-energy-efficiency-label" class="text_label">
			    <?php echo __( 'Image Description', 'wp-lister-for-ebay' ); ?>
			    <?php wplister_tooltip(__('A brief verbal summary of the information included on the Energy Efficiency Label for an item. For example, <em>On a scale of A to G the rating is E</em>.', 'wp-lister-for-ebay')) ?>
            </label>
            <input type="text" name="wpl_e2e_gpsr_energy_efficiency_label_description" id="wpl-text-gpsr_energy_efficiency_label_description" class="text_input" value="<?php esc_attr_e( $item_details['gpsr_energy_efficiency_label_description'] ?? '' ); ?>" />
            <br class="clear" />

            <label for="wpl-text-gpsr-energy-efficiency-sheet-image" class="text_label">
			    <?php echo __( 'Product Information Sheet Image', 'wp-lister-for-ebay' ); ?>
			    <?php wplister_tooltip(  __('The Product Information Sheet that provides complete manufacturer-provided efficiency information about an item. This field is required if an Energy Efficiency Label is provided.', 'wp-lister-for-ebay') ); ?>
            </label>

	        <?php
	        $custom_url = get_post_meta( $post->ID, '_ebay_gpsr_energy_efficiency_sheet_image_url', true );

	        if ( $custom_url ):
	        ?>
                <input type="text" name="wpl_e2e_gpsr_energy_efficiency_sheet_image_url" id="wpl_gpsr_energy_efficiency_sheet_image_url" value="<?php echo esc_attr( $custom_url ); ?>" class="text_input" />
                <input type="hidden" name="wpl_e2e_gpsr_energy_efficiency_sheet_image_eps" value="<?php echo esc_attr( $item_details['gpsr_energy_efficiency_sheet_image_eps'] ?? '' ); ?>" class="regular-text" />
            <?php else: ?>
                <div class="gpsr-image-block">
			        <?php
			        $src = '#';
			        if ( !empty( $item_details['gpsr_energy_efficiency_sheet_image'] ) && $url = wp_get_attachment_image_url( $item_details['gpsr_energy_efficiency_sheet_image'] ) ) {
				        $src = $url;
			        }
			        ?>
                    <input type="hidden" name="wpl_e2e_gpsr_energy_efficiency_sheet_image" id="wpl_gpsr_energy_efficiency_sheet_image" value="<?php echo esc_attr( $item_details['gpsr_energy_efficiency_sheet_image'] ?? '' ); ?>" class="regular-text" />
                    <input type="hidden" name="wpl_e2e_gpsr_energy_efficiency_sheet_image_eps" value="<?php echo esc_attr( $item_details['gpsr_energy_efficiency_sheet_image_eps'] ?? '' ); ?>" class="regular-text" />
                    <image src="<?php echo $src; ?>" id="gpsr_energy_efficiency_sheet_image" height="80" class="gpsr-photo photo <?php echo !empty($item_details['gpsr_energy_efficiency_sheet_image']) ? '' : 'hidden'; ?>" />
                    <br/>
                    <a href="#" id="trash_gpsr_energy_efficiency_sheet_image" class="trash-image wple-delete-upload <?php echo !empty($item_details['gpsr_energy_efficiency_sheet_image']) ? '' : 'hidden'; ?>" data-target="gpsr_energy_efficiency_sheet_image"><?php esc_attr_e( 'Remove image', 'wp-lister-for-ebay' ); ?></a>
                </div>
                <a href="#" class="button wple-uploader" data-target="gpsr_energy_efficiency_sheet_image"><?php _e( 'Select a image', 'wp-lister-for-ebay' ); ?></a>
            <?php endif; ?>

            <br class="clear" />

            <h4><?php _e('Hazmat', 'wp-lister-for-ebay' ); ?></h4>

            <label for="wpl-text-gpsr-hazmat-component" class="text_label">
			    <?php echo __( 'Component', 'wp-lister-for-ebay' ); ?>
			    <?php wplister_tooltip( __('This field is used to provide component information for the listing. For example, component information can provide the specific material of Hazmat concern.', 'wp-lister-for-ebay') ); ?>
            </label>
            <input type="text" name="wpl_e2e_gpsr_hazmat_component" id="wpl-text-gpsr_hazmat_component" class="text_input" value="<?php esc_attr_e( $item_details['gpsr_hazmat_component'] ?? '' ); ?>" />
            <br class="clear" />

            <label for="wpl-text-gpsr-hazmat-pictograms" class="text_label">
			    <?php echo __( 'Pictograms', 'wp-lister-for-ebay' ); ?>
            </label>
            <select id="wpl-text-gpsr_hazmat_pictograms" name="wpl_e2e_gpsr_hazmat_pictograms[]" class="wple_chosen_select" data-placeholder="<?php _e('Select up to 4 items', 'wp-lister-for-ebay'); ?>" multiple style="width:50%">
                <option value=""></option>
			    <?php
			    foreach ( (array)$hazardous_materials_labels['pictograms'] as $pictogram ):
				    $hazmat_pictograms = $item_details['gpsr_hazmat_pictograms'] ?? [];
				    $selected = in_array( $pictogram['pictogram_id'], (array)$hazmat_pictograms );
				    ?>
                    <option <?php selected( $selected, true ); ?> value="<?php esc_attr_e( $pictogram['pictogram_id'] ); ?>"><?php esc_attr_e( $pictogram['pictogram_description'] ); ?></option>
			    <?php endforeach; ?>
            </select>
            <br class="clear" />

            <label for="wpl-text-gpsr-hazmat-signalword" class="text_label">
			    <?php echo __( 'Signal Word', 'wp-lister-for-ebay' ); ?>
            </label>
            <select id="wpl-text-gpsr_hazmat_signalword" name="wpl_e2e_gpsr_hazmat_signalword" class="wple_chosen_select" >
			    <?php
			    foreach ( (array)$hazardous_materials_labels['signal_words'] as $signal_word ):
				    ?>
                    <option <?php selected( $signal_word['signal_word_id'], $item_details['gpsr_hazmat_signalword'] ?? '' ); ?> value="<?php esc_attr_e( $signal_word['signal_word_id'] ); ?>"><?php esc_attr_e( $signal_word['signal_word_description'] ); ?></option>
			    <?php endforeach; ?>
            </select>
            <br class="clear" />

            <label for="wpl-text-gpsr-hazmat-statements" class="text_label">
			    <?php echo __( 'Statements', 'wp-lister-for-ebay' ); ?>
            </label>
            <select id="wpl-text-gpsr_hazmat-statements" name="wpl_e2e_gpsr_hazmat_statements[]" class="wple_chosen_select" data-placeholder="Select up to 8 items" multiple style="width:50%">
			    <?php
			    foreach ( (array)$hazardous_materials_labels['statements'] as $statement ):
				    $hazmat_statements = $item_details['gpsr_hazmat_statements'] ?? [];
				    $selected = in_array( $statement['statement_id'], (array)$hazmat_statements );
				    ?>
                    <option <?php selected($selected,true); ?> value="<?php esc_attr_e( $statement['statement_id'] ); ?>"><?php esc_attr_e( $statement['statement_description'] ); ?></option>
			    <?php endforeach; ?>
            </select>
            <br class="clear" />

            <h4><?php _e('Manufacturer', 'wp-lister-for-ebay' ); ?></h4>

	        <?php
	        $product_manufacturer_street1   = get_post_meta( $post->ID, '_ebay_gpsr_manufacturer_street1', true );
	        $product_manufacturer_city      = get_post_meta( $post->ID, '_ebay_gpsr_manufacturer_city', true );
	        $product_manufacturer_country   = get_post_meta( $post->ID, '_ebay_gpsr_manufacturer_country', true );

	        //if ( $product_manufacturer_street1 && $product_manufacturer_city && $product_manufacturer_country ):
	        $product_manufacturer_street2    = get_post_meta( $post->ID, '_ebay_gpsr_manufacturer_street2', true );
	        $product_manufacturer_state      = get_post_meta( $post->ID, '_ebay_gpsr_manufacturer_state', true );
	        $product_manufacturer_postcode   = get_post_meta( $post->ID, '_ebay_gpsr_manufacturer_postcode', true );
	        $product_manufacturer_company    = get_post_meta( $post->ID, '_ebay_gpsr_manufacturer_company', true );
	        $product_manufacturer_phone      = get_post_meta( $post->ID, '_ebay_gpsr_manufacturer_phone', true );
	        $product_manufacturer_email      = get_post_meta( $post->ID, '_ebay_gpsr_manufacturer_email', true );
	        ?>
            <script>
                const gpsr_custom_manufacturer = <?php echo ( $product_manufacturer_street1 && $product_manufacturer_city && $product_manufacturer_country ) ? 'true' : 'false'; ?>;
            </script>
            <div class="gpsr-custom-manufacturer">
                <label class="text_label">
		            <?php _e( 'Found Manufacturer from Meta', 'wp-lister-for-ebay'); ?>
                    (<a class="button-link-delete wpl-remove-custom-manufacturer" href="#">Remove</a>)
                </label>
                <div class="gpsr-custom-data" style="">
                    <div class="wpl-address-box">
			            <?php
			            $wc_countries = new WC_Countries();
			            echo $wc_countries->get_formatted_address([
				            'company'   => $product_manufacturer_company,
				            'address_1' => $product_manufacturer_street1,
				            'address_2' => $product_manufacturer_street2,
				            'city'  => $product_manufacturer_city,
				            'state' => $product_manufacturer_state,
				            'country'   => $product_manufacturer_country,
				            'postcode'  => $product_manufacturer_postcode,
				            'phone'     => $product_manufacturer_phone
			            ]);
			            echo '<br/>'. $product_manufacturer_phone;
			            ?>
                    </div>
                    <input type="hidden" name="wpl_e2e_gpsr_manufacturer_street1" value="<?php esc_attr_e( $product_manufacturer_street1 ); ?>" />
                    <input type="hidden" name="wpl_e2e_gpsr_manufacturer_street2" value="<?php esc_attr_e( $product_manufacturer_street2 ); ?>" />
                    <input type="hidden" name="wpl_e2e_gpsr_manufacturer_city" value="<?php esc_attr_e( $product_manufacturer_city ); ?>" />
                    <input type="hidden" name="wpl_e2e_gpsr_manufacturer_state" value="<?php esc_attr_e( $product_manufacturer_state ); ?>" />
                    <input type="hidden" name="wpl_e2e_gpsr_manufacturer_postcode" value="<?php esc_attr_e( $product_manufacturer_postcode ); ?>" />
                    <input type="hidden" name="wpl_e2e_gpsr_manufacturer_country" value="<?php esc_attr_e( $product_manufacturer_country ); ?>" />
                    <input type="hidden" name="wpl_e2e_gpsr_manufacturer_company" value="<?php esc_attr_e( $product_manufacturer_company ); ?>" />
                    <input type="hidden" name="wpl_e2e_gpsr_manufacturer_phone" value="<?php esc_attr_e( $product_manufacturer_phone ); ?>" />
                </div>
                <span class="description"><?php _e('To select another manufacturer, simply remove the current one by clicking on the Remove link.', 'wp-lister-for-ebay'); ?></span>
                <div class="clear"></div>
            </div>
            <div class="gpsr-wpl-manufacturer">
	            <?php
	            //else:
	            $manufacturers = wple_get_manufacturers();
	            ?>
                <label class="text_label"><?php _e( 'Select a Manufacturer', 'wp-lister-for-ebay'); ?></label>
                <select id="wpl-text-gpsr_manufacturer" name="wpl_e2e_gpsr_manufacturer" class="wple_chosen_select" style="width:40%;">
                    <optgroup label="Saved Manufacturers">
                        <?php foreach ( $manufacturers as $manufacturer ): ?>
                            <option <?php selected( $manufacturer->getId(), $item_details['gpsr_manufacturer'] ?? '' ); ?> value="<?php esc_attr_e( $manufacturer->getId() ); ?>"><?php esc_attr_e( $manufacturer->getCompany() .' - '. $manufacturer->getCity() ); ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="From Attributes">
	                    <?php
	                    foreach ( $available_attributes as $attribute ):
		                    $select_name = '[[attribute_'. $attribute->name .']]';
		                    ?>
                            <option <?php selected( $select_name, $item_details['gpsr_manufacturer'] ?? '' ); ?> value="<?php echo $select_name; ?>"><?php echo __('Attribute: ', 'wp-lister-for-ebay') . $attribute->name; ?></option>
	                    <?php endforeach; ?>
                    </optgroup>
                </select>
                <a href="#" class="button" id="show_manufacturers_modal"><?php _e( 'Manage', 'wp-lister-for-ebay' ); ?></a>
	            <?php
	            //endif;
	            ?>
            </div>

            <h4><?php _e('Product Safety', 'wp-lister-for-ebay' ); ?></h4>

            <label class="text_label"><?php _e( 'Component', 'wp-lister-for-ebay'); ?></label>
            <input type="text" name="wpl_e2e_gpsr_product_safety_component" size="30" value="<?php echo esc_attr($item_details['gpsr_product_safety_component'] ?? ''); ?>" id="title" autocomplete="off" style="width:65%;">

            <label class="text_label"><?php _e('Pictograms', 'wp-lister-for-ebay'); ?></label>
            <select name="wpl_e2e_gpsr_product_safety_pictograms[]" class="wple_chosen_select" data-placeholder="<?php _e('Select up to 2', 'wp-lister-for-ebay'); ?>" multiple style="width:50%">
			    <?php
			    foreach ( (array)$product_safety_labels['pictograms'] as $pictogram ):
				    $safety_pictograms_array = $item_details['gpsr_product_safety_pictograms'] ?? [];
				    $selected = in_array( $pictogram['pictogram_id'], (array)$safety_pictograms_array );
				    ?>
                    <option <?php selected($selected, true); ?> value="<?php esc_attr_e( $pictogram['pictogram_id'] ); ?>"><?php esc_attr_e( $pictogram['pictogram_description'] ); ?></option>
			    <?php endforeach; ?>
            </select>

            <label class="text_label"><?php _e('Statements', 'wp-lister-for-ebay'); ?></label>
            <select name="wpl_e2e_gpsr_product_safety_statements[]" class="wple_chosen_select" data-placeholder="<?php _e('Select up to 8', 'wp-lister-for-ebay'); ?>" multiple style="width:50%">
			    <?php
			    $safety_statements_array = $item_details['gpsr_product_safety_statements'] ?? [];
			    foreach ( (array)$product_safety_labels['statements'] as $statement ):
				    $selected = in_array( $statement['statement_id'], (array)$safety_statements_array );
				    ?>
                    <option <?php selected($selected,true); ?> value="<?php esc_attr_e( $statement['statement_id'] ); ?>"><?php esc_attr_e( $statement['statement_description'] ); ?></option>
			    <?php endforeach; ?>
            </select>

            <h4><?php _e('Responsible Persons', 'wp-lister-for-ebay' ); ?></h4>

	        <?php
	        $person_street1   = get_post_meta( $post->ID, '_ebay_gpsr_responsible_persons_1_street1', true );
	        $person_city      = get_post_meta( $post->ID, '_ebay_gpsr_responsible_persons_1_city', true );
	        $person_country   = get_post_meta( $post->ID, '_ebay_gpsr_responsible_persons_1_country', true );

	        //if ( $product_manufacturer_street1 && $product_manufacturer_city && $product_manufacturer_country ):
	        $person_street2    = get_post_meta( $post->ID, '_ebay_gpsr_responsible_persons_1_street2', true );
	        $person_state      = get_post_meta( $post->ID, '_ebay_gpsr_responsible_persons_1_state', true );
	        $person_postcode   = get_post_meta( $post->ID, '_ebay_gpsr_responsible_persons_1_postcode', true );
	        $person_company    = get_post_meta( $post->ID, '_ebay_gpsr_responsible_persons_1_company', true );
	        $person_phone      = get_post_meta( $post->ID, '_ebay_gpsr_responsible_persons_1_phone', true );
	        $person_email      = get_post_meta( $post->ID, '_ebay_gpsr_responsible_persons_1_email', true );
	        ?>
            <script>
                const gpsr_custom_responsible_persons = <?php echo ( $person_street1 && $person_city && $person_country ) ? 'true' : 'false'; ?>;
            </script>

            <div class="gpsr-custom-responsible-persons">
                <label class="text_label">
			        <?php _e( 'Found Responsible Persons from Meta', 'wp-lister-for-ebay'); ?>
                    (<a class="button-link-delete wpl-remove-custom-responsible-persons" href="#">Remove</a>)
                </label>
                <div class="gpsr-custom-data" style="">
                    <?php
                    $i = 1;
                    while ( $person_street1 && $person_city && $person_country ):
                    ?>
                    <div class="wpl-address-box">
				        <?php
				        $wc_countries = new WC_Countries();
				        echo $wc_countries->get_formatted_address([
					        'company'   => $person_company,
					        'address_1' => $person_street1,
					        'address_2' => $person_street2,
					        'city'      => $person_city,
					        'state'     => $person_state,
					        'country'   => $person_country,
					        'postcode'  => $person_postcode,
					        'phone'     => $person_phone
				        ]);
				        echo '<br/>'. $person_phone;
				        ?>
                    </div>
                    <input type="hidden" name="wpl_e2e_gpsr_responsible_persons_1_street1" value="<?php esc_attr_e( $person_street1 ); ?>" />
                    <input type="hidden" name="wpl_e2e_gpsr_responsible_persons_1_street2" value="<?php esc_attr_e( $person_street1 ); ?>" />
                    <input type="hidden" name="wpl_e2e_gpsr_responsible_persons_1_city" value="<?php esc_attr_e( $person_city ); ?>" />
                    <input type="hidden" name="wpl_e2e_gpsr_responsible_persons_1_state" value="<?php esc_attr_e( $person_state ); ?>" />
                    <input type="hidden" name="wpl_e2e_gpsr_responsible_persons_1_postcode" value="<?php esc_attr_e( $person_postcode ); ?>" />
                    <input type="hidden" name="wpl_e2e_gpsr_responsible_persons_1_country" value="<?php esc_attr_e( $person_country ); ?>" />
                    <input type="hidden" name="wpl_e2e_gpsr_responsible_persons_1_company" value="<?php esc_attr_e( $person_company ); ?>" />
                    <input type="hidden" name="wpl_e2e_gpsr_responsible_persons_1_phone" value="<?php esc_attr_e( $person_phone ); ?>" />
                    <?php

                        $i++;
	                    $person_street1   = get_post_meta( $post->ID, '_ebay_gpsr_responsible_persons_'. $i .'_street1', true );
	                    $person_city      = get_post_meta( $post->ID, '_ebay_gpsr_responsible_persons_'. $i .'_city', true );
	                    $person_country   = get_post_meta( $post->ID, '_ebay_gpsr_responsible_persons_'. $i .'_country', true );

	                    //if ( $product_manufacturer_street1 && $product_manufacturer_city && $product_manufacturer_country ):
	                    $person_street2    = get_post_meta( $post->ID, '_ebay_gpsr_responsible_persons_'. $i .'_street2', true );
	                    $person_state      = get_post_meta( $post->ID, '_ebay_gpsr_responsible_persons_'. $i .'_state', true );
	                    $person_postcode   = get_post_meta( $post->ID, '_ebay_gpsr_responsible_persons_'. $i .'_postcode', true );
	                    $person_company    = get_post_meta( $post->ID, '_ebay_gpsr_responsible_persons_'. $i .'_company', true );
	                    $person_phone      = get_post_meta( $post->ID, '_ebay_gpsr_responsible_persons_'. $i .'_phone', true );
	                    $person_email      = get_post_meta( $post->ID, '_ebay_gpsr_responsible_persons_'. $i .'_email', true );
                    endwhile;
                    ?>
                </div>
                <span class="description"><?php _e('To select another person, simply remove the current one by clicking on the Remove link.', 'wp-lister-for-ebay'); ?></span>
                <div class="clear"></div>
            </div>
            <div class="gpsr-wpl-responsible-persons">
                <label class="text_label">
		            <?php _e( 'Set Responsible Persons', 'wp-lister-for-ebay'); ?>
                </label>
                <select id="wpl-text-gpsr_responsible_persons" name="wpl_e2e_gpsr_responsible_persons[]" class="wple_chosen_select" data-placeholder="Select up to 5 persons" multiple style="width:50%">
                    <optgroup label="Saved Responsible Persons">
	                    <?php
	                    $persons = wple_get_responsible_persons();
	                    $responsible_persons_array = $item_details['gpsr_responsible_persons'] ?? [];

	                    foreach ( $persons as $person ):
		                    $selected = in_array( $person->getId(), (array)$responsible_persons_array );
		                    ?>
                            <option <?php selected( $selected, true ); ?> value="<?php esc_attr_e( $person->getId() ); ?>"><?php esc_attr_e( $person->getCompany() .' - '. $person->getCity() ); ?></option>
	                    <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="From Attributes">
	                    <?php
	                    foreach ( $available_attributes as $attribute ):
		                    $select_name = '[[attribute_'. $attribute->name .']]';
		                    ?>
                            <option <?php selected( true, in_array( $select_name, $responsible_persons_array ) ); ?> value="<?php echo $select_name; ?>"><?php echo __('Attribute: ', 'wp-lister-for-ebay') . $attribute->name; ?></option>
	                    <?php endforeach; ?>
                    </optgroup>
                </select>
                <a href="#" class="button" id="show_persons_modal"><?php _e( 'Manage', 'wp-lister-for-ebay' ); ?></a>
            </div>

        </div>

        <div id="documents_modal">
            <div id="documents_modal_container">
			    <?php
			    include WPLE_PLUGIN_PATH.'/views/profile/documents_modal.php';
			    ?>
            </div>
        </div>

        <div id="responsible_persons_modal">
            <div id="responsible_persons_modal_container">
			    <?php
			    include WPLE_PLUGIN_PATH.'/views/profile/responsible_persons_modal.php';
			    ?>
            </div>
        </div>

        <div id="manufacturers_modal">
            <div id="manufacturers_modal_container">
			    <?php
			    include WPLE_PLUGIN_PATH.'/views/profile/manufacturers_modal.php';
			    ?>
            </div>
        </div>

        <?php
    }

	function enabledInventoryOnExternalProducts( $post ) {

		$product = ProductWrapper::getProduct( $post->ID );

        ?>
		<script type="text/javascript">

			jQuery( document ).ready( function () {

				// add show_id_external class to inventory tab and fields
				jQuery('.product_data_tabs .inventory_tab').addClass('show_if_external');
				jQuery('#inventory_product_data .show_if_simple').addClass('show_if_external');

				<?php if ( $product->is_type( 'external' ) ) : ?>

				// show inventory tab if this is an external product
				jQuery('.product_data_tabs .inventory_tab').show();
				jQuery('#inventory_product_data .show_if_simple').show();

				<?php endif; ?>

			});

		</script>
		<?php

	} // enabledInventoryOnExternalProducts()

	function meta_box_shipping( $post ) {

		// enqueue chosen.js from WooCommerce (removed in WC2.6)
		/*if ( version_compare( WC_VERSION, '2.6.0', '>=' ) ) {
			wp_register_style( 'chosen_css', WPLE_PLUGIN_URL.'js/chosen/chosen.css' );
			wp_enqueue_style( 'chosen_css' );
			wp_register_script( 'chosen', WPLE_PLUGIN_URL.'js/chosen/chosen.jquery.min.js', array( 'jquery' ) );
		}
	   	wp_enqueue_script( 'chosen' );*/

        ?>
		<script type="text/javascript">
			jQuery( document ).ready( function () {

				// enable chosen.js
				//jQuery("select.wple_chosen_select").chosen();
				jQuery("select.wple_chosen_select").selectWoo();

			});
		</script>

        <style type="text/css">
            #wplister-ebay-shipping label {
            	float: left;
            	width: 33%;
            	line-height: 2em;
            }
            #wplister-ebay-shipping label img.help_tip {
				vertical-align: bottom;
            	float: right;
				margin: 0;
				margin-top: 0.5em;
				margin-right: 0.5em;
            }
            #wplister-ebay-shipping input.text_input,
            #wplister-ebay-shipping select.select {
            	width: 65%;
            }
            #wplister-ebay-shipping .service_table {
            	width: 100%;
            }
            #wplister-ebay-shipping .description {
            	/*clear: both;*/
            	/*display: block;*/
            	/*margin-left: 33%;*/
            }
            #wplister-ebay-shipping .ebay_shipping_options_wrapper h4 {
            	padding-top: 0.5em;
            	padding-bottom: 0.5em;
            	margin-top: 1em;
            	margin-bottom: 0;
            	border-top: 1px solid #555;
            	border-top: 2px dashed #ddd;
            }

        </style>
        <?php

		$this->showShippingOptions( $post );

	} // meta_box_shipping()

	function showShippingOptions( $post ) {

		// get listing object
		$listing        = $this->get_current_ebay_item( $post );
		$wpl_account_id = $listing && $listing->account_id ? $listing->account_id : get_option( 'wplister_default_account_id' );
		$wpl_site_id    = $listing                         ? $listing->site_id    : get_option( 'wplister_ebay_site_id' );

		$wpl_loc_flat_shipping_options = EbayShippingModel::getAllLocal( $wpl_site_id, 'flat' );
		$wpl_int_flat_shipping_options = EbayShippingModel::getAllInternational( $wpl_site_id, 'flat' );
		$wpl_shipping_locations        = EbayShippingModel::getShippingLocations( $wpl_site_id );
		$wpl_exclude_locations         = EbayShippingModel::getExcludeShippingLocations( $wpl_site_id );
		$wpl_countries                 = EbayShippingModel::getEbayCountries( $wpl_site_id );

		$wpl_loc_calc_shipping_options   = EbayShippingModel::getAllLocal( $wpl_site_id, 'calculated' );
		$wpl_int_calc_shipping_options   = EbayShippingModel::getAllInternational( $wpl_site_id, 'calculated' );
		$wpl_calc_shipping_enabled       = in_array( get_option('wplister_ebay_site_id'), array(0,2,15,100) );
		// $wpl_available_shipping_packages = get_option('wplister_ShippingPackageDetails');
		$wpl_available_shipping_packages = WPLE_eBaySite::getSiteObj( $wpl_site_id )->getShippingPackageDetails();


		// get available seller profiles
		$wpl_seller_profiles_enabled	= get_option('wplister_ebay_seller_profiles_enabled');
		$wpl_seller_shipping_profiles	= get_option('wplister_ebay_seller_shipping_profiles');
		$wpl_seller_payment_profiles	= get_option('wplister_ebay_seller_payment_profiles');
		$wpl_seller_return_profiles		= get_option('wplister_ebay_seller_return_profiles');
	    $ShippingDiscountProfiles       = get_option('wplister_ShippingDiscountProfiles', array() );

		if ( isset( WPLE()->accounts[ $wpl_account_id ] ) ) {
			$account = WPLE()->accounts[ $wpl_account_id ];
			$wpl_seller_profiles_enabled  = $account->seller_profiles;
			$wpl_seller_shipping_profiles = maybe_unserialize( $account->shipping_profiles );
			$wpl_seller_payment_profiles  = maybe_unserialize( $account->payment_profiles );
			$wpl_seller_return_profiles   = maybe_unserialize( $account->return_profiles );
			$ShippingDiscountProfiles     = maybe_unserialize( $account->shipping_discount_profiles );
		}


		// fetch available shipping discount profiles
		$wpl_shipping_flat_profiles = array();
		$wpl_shipping_calc_profiles = array();
	    // $ShippingDiscountProfiles = get_option('wplister_ShippingDiscountProfiles', array() );
		if ( isset( $ShippingDiscountProfiles['FlatShippingDiscount'] ) ) {
			$wpl_shipping_flat_profiles = $ShippingDiscountProfiles['FlatShippingDiscount'];
		}
		if ( isset( $ShippingDiscountProfiles['CalculatedShippingDiscount'] ) ) {
			$wpl_shipping_calc_profiles = $ShippingDiscountProfiles['CalculatedShippingDiscount'];
		}

		// make sure that at least one payment and shipping option exist
		$item_details['loc_shipping_options'] = ProfilesModel::fixShippingArray( get_post_meta( $post->ID, '_ebay_loc_shipping_options', true ) );
		$item_details['int_shipping_options'] = ProfilesModel::fixShippingArray( get_post_meta( $post->ID, '_ebay_int_shipping_options', true ) );

		$item_details['shipping_loc_calc_profile']           = get_post_meta( $post->ID, '_ebay_shipping_loc_calc_profile', true );
		$item_details['shipping_loc_flat_profile']           = get_post_meta( $post->ID, '_ebay_shipping_loc_flat_profile', true );
		$item_details['shipping_int_calc_profile']           = get_post_meta( $post->ID, '_ebay_shipping_int_calc_profile', true );
		$item_details['shipping_int_flat_profile']           = get_post_meta( $post->ID, '_ebay_shipping_int_flat_profile', true );
		$item_details['seller_shipping_profile_id']          = get_post_meta( $post->ID, '_ebay_seller_shipping_profile_id', true );
		$item_details['PackagingHandlingCosts']              = get_post_meta( $post->ID, '_ebay_PackagingHandlingCosts', true );
		$item_details['InternationalPackagingHandlingCosts'] = get_post_meta( $post->ID, '_ebay_InternationalPackagingHandlingCosts', true );
		$item_details['shipping_service_type']               = get_post_meta( $post->ID, '_ebay_shipping_service_type', true );
		$item_details['shipping_package']   				 = get_post_meta( $post->ID, '_ebay_shipping_package', true );
		$item_details['shipping_loc_enable_free_shipping']   = get_post_meta( $post->ID, '_ebay_shipping_loc_enable_free_shipping', true );
		$item_details['ShipToLocations']   					 = get_post_meta( $post->ID, '_ebay_shipping_ShipToLocations', true );
		$item_details['ExcludeShipToLocations']   			 = get_post_meta( $post->ID, '_ebay_shipping_ExcludeShipToLocations', true );
		if ( ! $item_details['shipping_service_type'] ) $item_details['shipping_service_type'] = 'disabled';

		?>
			<!-- service type selector -->
			<label for="wpl-text-loc_shipping_service_type" class="text_label"><?php echo __( 'Custom shipping options', 'wp-lister-for-ebay' ); ?></label>
			<select name="wpl_e2e_shipping_service_type" id="wpl-text-loc_shipping_service_type"
					class="required-entry select select_shipping_type" style="width:auto;"
					onchange="handleShippingTypeSelectionChange(this)">
				<option value="disabled" <?php if ( @$item_details['shipping_service_type'] == 'disabled' ): ?>selected="selected"<?php endif; ?>><?php echo __( '-- use profile setting --', 'wp-lister-for-ebay' ); ?></option>
				<option value="flat"     <?php if ( @$item_details['shipping_service_type'] == 'flat' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Use Flat Shipping', 'wp-lister-for-ebay' ); ?></option>
				<option value="calc"     <?php if ( @$item_details['shipping_service_type'] == 'calc' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Use Calculated Shipping', 'wp-lister-for-ebay' ); ?></option>
				<option value="FlatDomesticCalculatedInternational" <?php if ( @$item_details['shipping_service_type'] == 'FlatDomesticCalculatedInternational' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Use Flat Domestic and Calculated International Shipping', 'wp-lister-for-ebay' ); ?></option>
				<option value="CalculatedDomesticFlatInternational" <?php if ( @$item_details['shipping_service_type'] == 'CalculatedDomesticFlatInternational' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Use Calculated Domestic and Flat International Shipping', 'wp-lister-for-ebay' ); ?></option>
				<option value="FreightFlat" <?php if ( @$item_details['shipping_service_type'] == 'FreightFlat' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Use Freight Shipping', 'wp-lister-for-ebay' ); ?></option>
			</select>
		<?php


		echo '<div class="ebay_shipping_options_wrapper">';
		if ( isset($account) ) echo '<small>The options below are based on the selected account <b>'.$account->title.'</b> ('.$account->site_code.').</small>';
		echo '<h4>'.  __( 'Domestic shipping', 'wp-lister-for-ebay' ) . '</h4>';
		include( WPLE_PLUGIN_PATH . '/views/profile/edit_shipping_loc.php' );

		echo '<h4>'.  __( 'International shipping', 'wp-lister-for-ebay' ) . '</h4>';
		include( WPLE_PLUGIN_PATH . '/views/profile/edit_shipping_int.php' );
		echo '</div>';

		echo '<script>';
		include( WPLE_PLUGIN_PATH . '/views/profile/edit_shipping.js' );
		echo '</script>';

	} // showShippingOptions()

	function enqueueFileTree() {

		// jqueryFileTree
		wp_register_style('jqueryFileTree_style', WPLE_PLUGIN_URL.'js/jqueryFileTree/jqueryFileTree.css' );
		wp_enqueue_style('jqueryFileTree_style');

		// jqueryFileTree
		wp_register_script( 'jqueryFileTree', WPLE_PLUGIN_URL.'js/jqueryFileTree/jqueryFileTree.js', array( 'jquery' ) );
		wp_enqueue_script( 'jqueryFileTree' );

		// mustache template engine
		wp_register_script( 'mustache', WPLE_PLUGIN_URL.'js/template/mustache.js', array( 'jquery' ) );
		wp_enqueue_script( 'mustache' );

		// jQuery UI Autocomplete
		wp_enqueue_script( 'jquery-ui-button' );
		wp_enqueue_script( 'jquery-ui-autocomplete' );

		// mustache template engine
		wp_register_script( 'jquery-editable-table', WPLE_PLUGIN_URL.'js/editable-table/mindmup-editabletable.js', array( 'jquery' ) );
	}

	function show_admin_post_vars_warning() {

		// check if there was a problem saving values
		$post_var_count = get_option('wplister_last_post_var_count');
		if ( ! $post_var_count ) return;

		// ignore if max_input_vars is not set (php52?)
		$max_input_vars = ini_get('max_input_vars');
		if ( ! $max_input_vars ) return;

    	$estimate = intval( $post_var_count / 100 ) * 100;
    	$msg  = '<b>Warning: Your server has a limit of '.$max_input_vars.' input fields set for PHP</b> (max_input_vars)';
    	$msg .= '<br><br>';
    	$msg .= 'This page submitted more than '.$estimate.' fields, which means that either some data is already discarded by your server when this product is updated - or it will be when you add a few more variations to your product. ';
    	$msg .= '<br><br>';
    	$msg .= 'Please contact your hosting provider and have them increase the <code>max_input_vars</code> PHP setting to at least '.($max_input_vars*2).' to prevent any issues updating your products.';
    	wple_show_message( $msg, 'warn' );

    	// only show this warning once
    	update_option('wplister_last_post_var_count', '' );

	} // show_admin_post_vars_warning()

	static function check_max_post_vars() {

		// count total number of post parameters - to show warning when running into max_input_vars limit ( or close: limit - 100 )
		$max_input_vars = ini_get('max_input_vars');
        $post_var_count = 0;
        foreach ( $_POST as $parameter ) {
            $post_var_count += is_array( $parameter ) ? sizeof( $parameter ) : 1;
        }
    	// remember post_var_count and trigger warning message on page refresh
        if ( $post_var_count > $max_input_vars - 100 ) {
        	update_option('wplister_last_post_var_count', $post_var_count );
        } else {
        	update_option('wplister_last_post_var_count', '' );
        }

	} // check_max_post_vars()

	function save_meta_box( $post_id, $post ) {

		// check if current user can manage listings
		if ( ! current_user_can('prepare_ebay_listings') ) return;

		// check nonce
		if ( ! isset( $_POST['wple_save_product_nonce'] ) || ! wp_verify_nonce( $_POST['wple_save_product_nonce'], 'wple_save_product' ) ) return;

		self::check_max_post_vars();


		// get field values
		$wpl_ebay_title                 = wple_clean( @$_POST['wpl_ebay_title'] );
		$wpl_ebay_subtitle              = wple_clean( @$_POST['wpl_ebay_subtitle'] );
		$wpl_ebay_global_shipping       = wple_clean( @$_POST['wpl_ebay_global_shipping'] );
		$wpl_ebay_ebayplus_enabled      = wple_clean( $_POST['wpl_ebay_ebayplus_enabled'] ?? 0 );
		$wpl_ebay_payment_instructions  = wple_clean( @$_POST['wpl_ebay_payment_instructions'] );
		$wpl_ebay_condition_description = wple_clean( @$_POST['wpl_ebay_condition_description'] );
		$wpl_ebay_condition_id 			= wple_clean( @$_POST['wpl_ebay_condition_id'] );
		$wpl_ebay_professional_grader	= wple_clean( $_POST['wpl_ebay_professional_grader'] ?? '' );
		$wpl_ebay_grade	                = wple_clean( $_POST['wpl_ebay_grade'] ?? '' );
		$wpl_ebay_certification_number  = wple_clean( $_POST['wpl_ebay_certification_number'] ?? '' );
		$wpl_ebay_auction_type          = wple_clean( @$_POST['wpl_ebay_auction_type'] );
		$wpl_ebay_listing_duration      = wple_clean( @$_POST['wpl_ebay_listing_duration'] );
		$wpl_ebay_start_price           = wple_clean( @$_POST['wpl_ebay_start_price'] );
		$wpl_ebay_reserve_price         = wple_clean( @$_POST['wpl_ebay_reserve_price'] );
		$wpl_ebay_buynow_price          = wple_clean( @$_POST['wpl_ebay_buynow_price'] );
		$wpl_ebay_upc          			= wple_clean( @$_POST['wpl_ebay_upc'] );
		$wpl_ebay_ean          			= wple_clean( @$_POST['wpl_ebay_ean'] );
		$wpl_ebay_isbn          		= wple_clean( @$_POST['wpl_ebay_isbn'] );
		$wpl_ebay_mpn          			= wple_clean( @$_POST['wpl_ebay_mpn'] );
		$wpl_ebay_brand        			= wple_clean( @$_POST['wpl_ebay_brand'] );
		$wpl_ebay_epid          		= wple_clean( @$_POST['wpl_ebay_epid'] );
		$wpl_ebay_hide_from_unlisted  	= wple_clean( $_POST['wpl_ebay_hide_from_unlisted'] ?? 0 );
		$wpl_ebay_category_1_id      	= wple_clean( @$_POST['wpl_ebay_category_1_id'] );
		$wpl_ebay_category_2_id      	= wple_clean( @$_POST['wpl_ebay_category_2_id'] );
		$wpl_store_category_1_id      	= wple_clean( @$_POST['wpl_ebay_store_category_1_id'] );
		$wpl_store_category_2_id      	= wple_clean( @$_POST['wpl_ebay_store_category_2_id'] );
		$wpl_ebay_gallery_image_url   	= wple_clean( @$_POST['wpl_ebay_gallery_image_url'] );

		$wpl_amazon_id_type   			= wple_clean( @$_POST['wpl_amazon_id_type'] );
		$wpl_amazon_product_id   		= wple_clean( @$_POST['wpl_amazon_product_id'] );

		// sanitize prices - convert decimal comma to decimal point
		$wpl_ebay_start_price			= wc_format_decimal( $wpl_ebay_start_price );
		$wpl_ebay_reserve_price			= wc_format_decimal( $wpl_ebay_reserve_price );
		$wpl_ebay_buynow_price			= wc_format_decimal( $wpl_ebay_buynow_price );

		// use UPC from WPLA, if currently empty
		if ( apply_filters( 'wple_use_amazon_upc', true ) && empty( $wpl_ebay_upc ) && 'UPC' == $wpl_amazon_id_type ) {
			$wpl_ebay_upc = $wpl_amazon_product_id;
		}

		// use EAN from WPLA, if currently empty
		if ( apply_filters( 'wple_use_amazon_ean', true ) && empty( $wpl_ebay_ean ) && 'EAN' == $wpl_amazon_id_type ) {
			$wpl_ebay_ean = $wpl_amazon_product_id;
		}

		// Update product data
		update_post_meta( $post_id, '_ebay_title', $wpl_ebay_title );
		update_post_meta( $post_id, '_ebay_subtitle', $wpl_ebay_subtitle );
		update_post_meta( $post_id, '_ebay_global_shipping', $wpl_ebay_global_shipping );
		update_post_meta( $post_id, '_ebay_ebayplus_enabled', $wpl_ebay_ebayplus_enabled );
		update_post_meta( $post_id, '_ebay_payment_instructions', $wpl_ebay_payment_instructions );
		update_post_meta( $post_id, '_ebay_condition_id', $wpl_ebay_condition_id );
		update_post_meta( $post_id, '_ebay_condition_description', $wpl_ebay_condition_description );
		update_post_meta( $post_id, '_ebay_professional_grader', $wpl_ebay_professional_grader );
		update_post_meta( $post_id, '_ebay_grade', $wpl_ebay_grade );
		update_post_meta( $post_id, '_ebay_certification_number', $wpl_ebay_certification_number );
		update_post_meta( $post_id, '_ebay_listing_duration', $wpl_ebay_listing_duration );
		update_post_meta( $post_id, '_ebay_auction_type', $wpl_ebay_auction_type );
		update_post_meta( $post_id, '_ebay_start_price', $wpl_ebay_start_price );
		update_post_meta( $post_id, '_ebay_reserve_price', $wpl_ebay_reserve_price );
		update_post_meta( $post_id, '_ebay_buynow_price', $wpl_ebay_buynow_price );
		update_post_meta( $post_id, '_ebay_upc', $wpl_ebay_upc );
		update_post_meta( $post_id, '_ebay_ean', $wpl_ebay_ean );
		update_post_meta( $post_id, '_ebay_isbn', $wpl_ebay_isbn );
		update_post_meta( $post_id, '_ebay_mpn', $wpl_ebay_mpn );
		update_post_meta( $post_id, '_ebay_brand', $wpl_ebay_brand );
		update_post_meta( $post_id, '_ebay_epid', $wpl_ebay_epid );
		update_post_meta( $post_id, '_ebay_hide_from_unlisted', $wpl_ebay_hide_from_unlisted );
		update_post_meta( $post_id, '_ebay_category_1_id', $wpl_ebay_category_1_id );
		update_post_meta( $post_id, '_ebay_category_2_id', $wpl_ebay_category_2_id );
		update_post_meta( $post_id, '_ebay_store_category_1_id', $wpl_store_category_1_id );
		update_post_meta( $post_id, '_ebay_store_category_2_id', $wpl_store_category_2_id );
		update_post_meta( $post_id, '_ebay_gallery_image_url', $wpl_ebay_gallery_image_url );

		update_post_meta( $post_id, '_ebay_seller_payment_profile_id', 	wple_clean( @$_POST['wpl_ebay_seller_payment_profile_id'] ) );
		update_post_meta( $post_id, '_ebay_seller_return_profile_id', 	wple_clean( @$_POST['wpl_ebay_seller_return_profile_id'] ) );
		update_post_meta( $post_id, '_ebay_bestoffer_enabled', 			wple_clean( @$_POST['wpl_ebay_bestoffer_enabled'] ) );
		update_post_meta( $post_id, '_ebay_bo_autoaccept_price', 		wple_clean( wc_format_decimal( @$_POST['wpl_ebay_bo_autoaccept_price'] ) ) );
		update_post_meta( $post_id, '_ebay_bo_minimum_price', 			wple_clean( wc_format_decimal( @$_POST['wpl_ebay_bo_minimum_price'] ) ) );

		// shipping options
		$ebay_shipping_service_type = wple_clean( @$_POST['wpl_e2e_shipping_service_type'] );

		if ( $ebay_shipping_service_type && $ebay_shipping_service_type != 'disabled' ) {

			update_post_meta( $post_id, '_ebay_shipping_service_type', $ebay_shipping_service_type );

			$details = ProfilesPage::getPreprocessedPostDetails();
			update_post_meta( $post_id, '_ebay_loc_shipping_options', $details['loc_shipping_options'] );
			update_post_meta( $post_id, '_ebay_int_shipping_options', $details['int_shipping_options'] );

			update_post_meta( $post_id, '_ebay_shipping_package', wple_clean( @$_POST['wpl_e2e_shipping_package'] ) );
			update_post_meta( $post_id, '_ebay_PackagingHandlingCosts', wple_clean( @$_POST['wpl_e2e_PackagingHandlingCosts'] ) );
			update_post_meta( $post_id, '_ebay_InternationalPackagingHandlingCosts', wple_clean( @$_POST['wpl_e2e_InternationalPackagingHandlingCosts'] ) );

			update_post_meta( $post_id, '_ebay_shipping_loc_flat_profile', wple_clean( @$_POST['wpl_e2e_shipping_loc_flat_profile'] ) );
			update_post_meta( $post_id, '_ebay_shipping_int_flat_profile', wple_clean( @$_POST['wpl_e2e_shipping_int_flat_profile'] ) );
			update_post_meta( $post_id, '_ebay_shipping_loc_calc_profile', wple_clean( @$_POST['wpl_e2e_shipping_loc_calc_profile'] ) );
			update_post_meta( $post_id, '_ebay_shipping_int_calc_profile', wple_clean( @$_POST['wpl_e2e_shipping_int_calc_profile'] ) );
			update_post_meta( $post_id, '_ebay_seller_shipping_profile_id', wple_clean( @$_POST['wpl_e2e_seller_shipping_profile_id'] ) );

			$loc_free_shipping = strstr( 'calc', strtolower($ebay_shipping_service_type) ) ? wple_clean(@$_POST['wpl_e2e_shipping_loc_calc_free_shipping']) : wple_clean(@$_POST['wpl_e2e_shipping_loc_flat_free_shipping']);
			update_post_meta( $post_id, '_ebay_shipping_loc_enable_free_shipping', $loc_free_shipping );

			update_post_meta( $post_id, '_ebay_shipping_ShipToLocations', wple_clean(@$_POST['wpl_e2e_ShipToLocations']) );
			update_post_meta( $post_id, '_ebay_shipping_ExcludeShipToLocations', wple_clean(@$_POST['wpl_e2e_ExcludeShipToLocations']) );

		} else {

			delete_post_meta( $post_id, '_ebay_shipping_service_type' );
			delete_post_meta( $post_id, '_ebay_loc_shipping_options' );
			delete_post_meta( $post_id, '_ebay_int_shipping_options' );
			delete_post_meta( $post_id, '_ebay_shipping_package' );
			delete_post_meta( $post_id, '_ebay_PackagingHandlingCosts' );
			delete_post_meta( $post_id, '_ebay_InternationalPackagingHandlingCosts' );
			delete_post_meta( $post_id, '_ebay_shipping_loc_flat_profile' );
			delete_post_meta( $post_id, '_ebay_shipping_int_flat_profile' );
			delete_post_meta( $post_id, '_ebay_shipping_loc_calc_profile' );
			delete_post_meta( $post_id, '_ebay_shipping_int_calc_profile' );

			delete_post_meta( $post_id, '_ebay_seller_shipping_profile_id' );
			delete_post_meta( $post_id, '_ebay_shipping_loc_enable_free_shipping' );
			delete_post_meta( $post_id, '_ebay_shipping_ShipToLocations' );
			delete_post_meta( $post_id, '_ebay_shipping_ExcludeShipToLocations' );

		}


		// get listing object
		$listing        = $this->get_current_ebay_item( $post );
		$wpl_account_id = $listing && $listing->account_id ? $listing->account_id : get_option( 'wplister_default_account_id' );
		$wpl_site_id    = $listing                         ? $listing->site_id    : get_option( 'wplister_ebay_site_id' );

		// process item specifics
		$item_specifics  = array();
		$itmSpecs_name   = wple_clean(@$_POST['itmSpecs_name']);
		$itmSpecs_value  = wple_clean(@$_POST['itmSpecs_value']);
		$itmSpecs_attrib = wple_clean(@$_POST['itmSpecs_attrib']);

		// process JSON values from the Tagify library #51065
        if ( is_array( $itmSpecs_value ) ) {
            foreach ( $itmSpecs_value as $key => $value ) {
                $value = wp_unslash( $value );
                $json = json_decode( $value );
                $json_values = array();

                if ( is_array( $json ) ) {
                    foreach ( $json as $json_value ) {
                        $json_values[] = $json_value->value;
                    }

                    $itmSpecs_value[ $key ] = implode('|',  $json_values );
                }
            }
        }

		if ( is_array( $itmSpecs_name ) )
		foreach ($itmSpecs_name as $key => $name) {

			#$name = str_replace('\\\\', '', $name );
			$name = stripslashes( $name );

			if ( is_array( $itmSpecs_value[ $key ] ) ) {
			    $itmSpecs_value[ $key ] = implode( '|', $itmSpecs_value[ $key ] );
            }

			$value = is_array( $itmSpecs_value[ $key ] )? $itmSpecs_value[ $key ] : trim( $itmSpecs_value[$key] );
			$attribute = trim( $itmSpecs_attrib[$key] );

			if ( ( $value != '') || ( $attribute != '' ) ) {
				// $spec = new stdClass();
				// $spec->name = $name;
				// $spec->value = $value;
				// $spec->attribute = $attribute;

				$spec = array();
				$spec['name']      = $name;
				$spec['value']     = $value;
				$spec['attribute'] = $attribute;
				$item_specifics[]  = $spec;
			}

		}
		update_post_meta( $post_id, '_ebay_item_specifics', $item_specifics );

        // GPSR
		$gpsr_fields = [
			'gpsr_enabled',
			'gpsr_documents',
			'gpsr_repair_score',
			'gpsr_energy_efficiency_image',
			'gpsr_energy_efficiency_image_url',
			'gpsr_energy_efficiency_image_eps',
            'gpsr_energy_efficiency_label_description',
			'gpsr_energy_efficiency_sheet_image',
			'gpsr_energy_efficiency_sheet_image_url',
			'gpsr_energy_efficiency_sheet_image_eps',
			'gpsr_hazmat_component',
			'gpsr_hazmat_pictograms',
			'gpsr_hazmat_signalword',
			'gpsr_hazmat_statements',
			'gpsr_manufacturer',
			'gpsr_manufacturer_street1',
			'gpsr_manufacturer_street2',
			'gpsr_manufacturer_city',
			'gpsr_manufacturer_state',
			'gpsr_manufacturer_postcode',
			'gpsr_manufacturer_country',
			'gpsr_manufacturer_company',
			'gpsr_manufacturer_phone',
			'gpsr_manufacturer_email',
			'gpsr_product_safety_component',
			'gpsr_product_safety_pictograms',
			'gpsr_product_safety_statements',
			'gpsr_responsible_persons',
			'gpsr_responsible_persons_1_street1',
			'gpsr_responsible_persons_1_street2',
			'gpsr_responsible_persons_1_city',
			'gpsr_responsible_persons_1_state',
			'gpsr_responsible_persons_1_postcode',
			'gpsr_responsible_persons_1_country',
			'gpsr_responsible_persons_1_company',
			'gpsr_responsible_persons_1_phone',
			'gpsr_responsible_persons_1_email',
			'gpsr_responsible_persons_2_street1',
			'gpsr_responsible_persons_2_street2',
			'gpsr_responsible_persons_2_city',
			'gpsr_responsible_persons_2_state',
			'gpsr_responsible_persons_2_postcode',
			'gpsr_responsible_persons_2_country',
			'gpsr_responsible_persons_2_company',
			'gpsr_responsible_persons_2_phone',
			'gpsr_responsible_persons_2_email',
			'gpsr_responsible_persons_3_street1',
			'gpsr_responsible_persons_3_street2',
			'gpsr_responsible_persons_3_city',
			'gpsr_responsible_persons_3_state',
			'gpsr_responsible_persons_3_postcode',
			'gpsr_responsible_persons_3_country',
			'gpsr_responsible_persons_3_company',
			'gpsr_responsible_persons_3_phone',
			'gpsr_responsible_persons_3_email',
			'gpsr_responsible_persons_4_street1',
			'gpsr_responsible_persons_4_street2',
			'gpsr_responsible_persons_4_city',
			'gpsr_responsible_persons_4_state',
			'gpsr_responsible_persons_4_postcode',
			'gpsr_responsible_persons_4_country',
			'gpsr_responsible_persons_4_company',
			'gpsr_responsible_persons_4_phone',
			'gpsr_responsible_persons_4_email',
		];

        foreach ( $gpsr_fields as $key ) {
            if ( isset( $_POST['wpl_e2e_'. $key ] ) ) {
	            $value = wple_clean( @$_POST['wpl_e2e_'. $key] );

                // If Enable GPSR is not set to YES, remove all product-level GPSR data
                if ( $_POST['wpl_e2e_gpsr_enabled'] != 1 ) {
                    $value = '';
                }
                update_post_meta( $post_id, '_ebay_'. $key, $value );
            }
        }


		## BEGIN PRO ##

		update_post_meta( $post_id, '_ebay_autopay', 			wple_clean( @$_POST['wpl_ebay_autopay'] ) );


		// process compatibility list
		$compatible_applications = array();
		$compatibility_list      = wple_clean(@$_POST['wpl_e2e_compatibility_list']);
		$compatibility_names     = wple_clean(@$_POST['wpl_e2e_compatibility_names']);
		$compatibility_remove    = wple_clean(@$_POST['wpl_e2e_compatibility_remove']);
		// echo "<pre>POST: ";print_r($compatibility_list);echo"</pre>";#die();

		// decode json
		if ( ! empty( $compatibility_list ) )	$compatibility_list  = json_decode( stripslashes( $compatibility_list ) );
		if ( ! empty( $compatibility_names ) )	$compatibility_names = json_decode( stripslashes( $compatibility_names ) );
		// echo "<pre>json_decode: ";print_r($compatibility_names);echo"</pre>";#die();
		// echo "<pre>json_decode: ";print_r($compatibility_list);echo"</pre>";#die();

		if ( ! empty( $compatibility_list ) ) {

			// loop rows
			foreach ( $compatibility_list as $row ) {

				$compatible_app               = new stdClass();
				$compatible_app->notes        = '';
				$compatible_app->applications = array();

				// skip empty rows
				if ( empty( $row[0] ) ) continue;

				// each column
				foreach ($row as $col_index => $value) {
					$value = stripslashes( $value );
					$name  = $compatibility_names[ $col_index ];

					if ( $name == 'Notes' ) {
						$compatible_app->notes = $value;
					} else {

						$property = new stdClass();
						$property->name  = $name;
						$property->value = $value;

						$compatible_app->applications[ $name ] = $property;
					}

				}

				// add to array
				$compatible_applications[] = $compatible_app;

			}

			// remove Notes column
			$notes_index = array_search('Notes', $compatibility_names);
			unset( $compatibility_names[$notes_index] );

			// debug: show previous data:
			// $compatibility_list   = get_post_meta( $post->ID, '_ebay_item_compatibility_list', true );
			// $compatibility_names  = get_post_meta( $post->ID, '_ebay_item_compatibility_names', true );
			// echo "<pre>";print_r($compatible_applications );echo"</pre>";#die();
			// echo "<pre>";print_r($compatibility_list);echo"</pre>";die();

            wple_set_compatibility_list( $post_id, $compatible_applications );
			wple_set_compatibility_names( $post_id, $compatibility_names );

		} elseif ( $compatibility_remove ) {
		    wple_set_compatibility_list( $post_id, '' );
		    wple_set_compatibility_names( $post_id, '' );
		}

		## END PRO ##

	} // save_meta_box()



	// // deprecated
    // function get_updated_item_specifics_for_product_and_category( $post_id, $primary_category_id, $account_id  ) {

	// 	// fetch category specifics for primary category
	// 	$saved_specifics = maybe_unserialize( get_post_meta( $post_id, '_ebay_category_specifics', true ) );

	// 	// fetch required item specifics for primary category
	// 	if ( ( isset( $saved_specifics[ $primary_category_id ] ) ) && ( $saved_specifics[ $primary_category_id ] != 'none' ) ) {

	// 		$specifics = $saved_specifics;

	// 	} elseif ( (int)$primary_category_id != 0 ) {

	// 		$site_id = WPLE()->accounts[ $account_id ]->site_id;

	// 		WPLE()->initEC( $account_id );
	// 		$specifics = WPLE()->EC->getCategorySpecifics( $primary_category_id, $site_id );
	// 		WPLE()->EC->closeEbay();

	// 	} else {

	// 		$specifics = array();

	// 	}

	// 	// store available item specific as product meta
	// 	update_post_meta( $post_id, '_ebay_category_specifics', $specifics );

	// 	return $specifics;
	// } // get_updated_item_specifics_for_product_and_category()






	/* show additional fields for variations */
    function woocommerce_variation_options( $loop, $variation_data, $variation ) {
        // echo "<pre>";print_r($variation_data);echo"</pre>";#die();

		// check if current user can manage listings
		if ( ! current_user_can('prepare_ebay_listings') ) return;

		// current values
		// $_ebay_start_price	= isset( $variation_data['_ebay_start_price'][0] )	? $variation_data['_ebay_start_price'][0]	: '';
		// $_ebay_is_disabled	= isset( $variation_data['_ebay_is_disabled'][0] )	? $variation_data['_ebay_is_disabled'][0]	: '';

		// get variation post_id - WC2.3
		$variation_post_id = $variation ? $variation->ID : $variation_data['variation_post_id']; // $variation exists since WC2.2 (at least)

		// get current values - WC2.3
		$_ebay_start_price       = get_post_meta( $variation_post_id, '_ebay_start_price'  		, true );
		$_ebay_is_disabled       = get_post_meta( $variation_post_id, '_ebay_is_disabled'  		, true );
		$_ebay_upc    		     = get_post_meta( $variation_post_id, '_ebay_upc'  				, true );
		$_ebay_ean    		     = get_post_meta( $variation_post_id, '_ebay_ean'  				, true );
		$_ebay_mpn    		     = get_post_meta( $variation_post_id, '_ebay_mpn'  				, true );
		$_ebay_isbn    		     = get_post_meta( $variation_post_id, '_ebay_isbn' 				, true );

        ?>
            <div>
	        	<h4 style="border-bottom: 1px solid #ddd; margin:0; padding-top:1em; clear:both;"><?php _e( 'eBay Options', 'wp-lister-for-ebay' ); ?></h4>
                <p class="form-row form-row-first">
                    <label>
                        <?php _e( 'UPC', 'wp-lister-for-ebay' ); ?>
                        <a class="tips" data-tip="eBay will require product identifiers (UPC/EAN) for variations in selected categories starting September 2015.<br><br>If your products do not have a UPC or EAN, leave this empty and enable the <i>Missing Product Identifiers</i> option on the advanced settings page." href="#">[?]</a>
                    </label>
                    <input type="text" name="variable_ebay_upc[<?php echo $loop; ?>]" class="" value="<?php echo $_ebay_upc ?>" />
                </p>
                <p class="form-row form-row-last">
                    <label>
                        <?php _e( 'EAN', 'wp-lister-for-ebay' ); ?>
                        <a class="tips" data-tip="eBay will require product identifiers (UPC/EAN) for variations in selected categories starting September 2015.<br><br>If your products do not have a UPC or EAN, leave this empty and enable the <i>Missing Product Identifiers</i> option on the advanced settings page." href="#">[?]</a>
                    </label>
                    <input type="text" name="variable_ebay_ean[<?php echo $loop; ?>]" class="" value="<?php echo $_ebay_ean ?>" />
                </p>
            </div>

            <?php if ( get_option( 'wplister_enable_mpn_and_isbn_fields', 2 ) == 1 ) : ?>
            <div>
                <p class="form-row form-row-first">
                    <label>
                        <?php _e( 'MPN', 'wp-lister-for-ebay' ); ?>
                        <a class="tips" data-tip="eBay will require product identifiers (UPC, EAN or Brand/MPN) for variations in selected categories starting September 2015.<br><br>If your products do not have an MPN, leave this empty." href="#">[?]</a>
                    </label>
                    <input type="text" name="variable_ebay_mpn[<?php echo $loop; ?>]" class="" value="<?php echo $_ebay_mpn ?>" />
                </p>
                <p class="form-row form-row-last">
                    <label>
                        <?php _e( 'ISBN', 'wp-lister-for-ebay' ); ?>
                        <a class="tips" data-tip="eBay will require product identifiers (UPC/EAN/MPN/ISBN) for variations in selected categories starting September 2015.<br><br>If your products do not have an ISBN, leave this empty." href="#">[?]</a>
                    </label>
                    <input type="text" name="variable_ebay_isbn[<?php echo $loop; ?>]" class="" value="<?php echo $_ebay_isbn ?>" />
                </p>
            </div>
	        <?php endif; ?>

            <?php if ( get_option( 'wplister_enable_custom_product_prices', 1 ) == 1 ) : ?>
            <div>
                <p class="form-row form-row-first">
                    <label>
                        <?php _e( 'eBay Price', 'wp-lister-for-ebay' ); ?>
                        <a class="tips" data-tip="Custom price to be used when listing this variation on eBay. This will override price modifier settings in your listing profile." href="#">[?]</a>
                    </label>
                    <input type="text" name="variable_ebay_start_price[<?php echo $loop; ?>]" class="wc_input_price" value="<?php echo wc_format_localized_price( $_ebay_start_price ); ?>" />
                </p>
                <p class="form-row form-row-last">
                    <label style="display: block;">
                        <?php _e( 'eBay Visibility', 'wp-lister-for-ebay' ); ?>
                        <a class="tips" data-tip="Tick the checkbox below to omit this particular variation when this product is listed on eBay." href="#">[?]</a>
                    </label>
                	<label style="line-height: 2.6em;">
                		<input type="checkbox" class="checkbox" name="variable_ebay_is_disabled[<?php echo $loop; ?>]" style="margin-top: 9px !important; margin-right: 9px !important;"
                			<?php if ( $_ebay_is_disabled ) echo 'checked="checked"' ?> >
                		<?php _e( 'Hide on eBay', 'wp-lister-for-ebay' ); ?>
                	</label>
                </p>
            </div>
	        <?php endif; ?>
        <?php

    } // woocommerce_variation_options()

    /* show custom meta fields for variations */
    function woocommerce_custom_variation_meta_fields( $loop, $variation_data, $variation ) {

        // get variation post_id - WC2.3
        $variation_post_id = $variation ? $variation->ID : false;

        // handle custom variation meta fields
        $variation_meta_fields = get_option('wplister_variation_meta_fields', array() );
        foreach ( $variation_meta_fields as $key => $varmeta ) :

            // $meta_key    = 'meta_'.$key;
            $field_label = $varmeta['label'];

            // get current value
            $current_value = get_post_meta( $variation_post_id, $key, true );
            ?>

            <div>
                <p class="form-row form-row-full">
                    <label>
                        <?php echo $field_label ?>
                    </label>
                    <input type="text" name="variable_ebay_<?php echo $key; ?>[<?php echo $loop; ?>]" class="" value="<?php echo $current_value ?>" placeholder="" />
                </p>
            </div>

        <?php
        endforeach;

    } // woocommerce_custom_variation_meta_fields()

    public function process_product_meta_variable( $post_id ) {
        // echo "<pre>";print_r($_POST);echo"</pre>";die();

		// check if current user can manage listings
		if ( ! current_user_can('prepare_ebay_listings') ) return;

        if (isset($_POST['variable_sku'])) {

			$variable_post_id              = wple_clean($_POST['variable_post_id']);
			$variable_ebay_start_price     = isset( $_POST['variable_ebay_start_price'] )  ? wple_clean($_POST['variable_ebay_start_price'])  : '';
			$variable_ebay_is_disabled     = isset( $_POST['variable_ebay_is_disabled'] )  ? wple_clean($_POST['variable_ebay_is_disabled'])	: '';
			$variable_ebay_upc     	       = isset( $_POST['variable_ebay_upc'] ) 		   ? wple_clean($_POST['variable_ebay_upc']) 		  	: '';
			$variable_ebay_ean     	       = isset( $_POST['variable_ebay_ean'] ) 		   ? wple_clean($_POST['variable_ebay_ean']) 		  	: '';
			$variable_ebay_mpn     	       = isset( $_POST['variable_ebay_mpn'] ) 		   ? wple_clean($_POST['variable_ebay_mpn']) 		  	: '';
			$variable_ebay_isbn     	   = isset( $_POST['variable_ebay_isbn'] ) 		   ? wple_clean($_POST['variable_ebay_isbn']) 	  	: '';

			$variable_amazon_id_type       = isset( $_POST['variable_amazon_id_type'] )    ? wple_clean($_POST['variable_amazon_id_type'])    : '';
			$variable_amazon_product_id    = isset( $_POST['variable_amazon_product_id'] ) ? wple_clean($_POST['variable_amazon_product_id']) : '';

			// sanitize price - convert decimal comma to decimal point
			//$variable_ebay_start_price	   = str_replace( ',', '.', $variable_ebay_start_price );

            $max_loop = max( array_keys( wple_clean($_POST['variable_post_id']) ) );

            for ( $i=0; $i <= $max_loop; $i++ ) {

                if ( ! isset( $variable_post_id[$i] ) ) continue;
                $variation_id = (int) $variable_post_id[$i];

                // Update post meta
                update_post_meta( $variation_id, '_ebay_start_price', isset( $variable_ebay_start_price[$i] ) ? wc_format_decimal( $variable_ebay_start_price[$i] ) : '' );
                update_post_meta( $variation_id, '_ebay_is_disabled', isset( $variable_ebay_is_disabled[$i] ) ? $variable_ebay_is_disabled[$i] : '' );
                // update_post_meta( $variation_id, '_ebay_upc', 		  isset( $variable_ebay_upc[$i] ) 		  ? $variable_ebay_upc[$i] 		   : '' );
                // update_post_meta( $variation_id, '_ebay_ean', 		  isset( $variable_ebay_ean[$i] ) 		  ? $variable_ebay_ean[$i] 		   : '' );


				// use UPC or EAN from WPLA, if currently empty in WPLE
                $ebay_upc    = isset( $variable_ebay_upc[$i] )          ? $variable_ebay_upc[$i]          : '';
                $ebay_ean    = isset( $variable_ebay_ean[$i] )          ? $variable_ebay_ean[$i]          : '';
                $amz_id_type = isset( $variable_amazon_id_type[$i] )    ? $variable_amazon_id_type[$i]    : '';
                $amz_upc_ean = isset( $variable_amazon_product_id[$i] ) ? $variable_amazon_product_id[$i] : '';

                if ( empty( $ebay_upc ) && $amz_id_type == 'UPC' )		$ebay_upc = $amz_upc_ean;
                if ( empty( $ebay_ean ) && $amz_id_type == 'EAN' )		$ebay_ean = $amz_upc_ean;

                update_post_meta( $variation_id, '_ebay_upc', 		  $ebay_upc );
                update_post_meta( $variation_id, '_ebay_ean', 		  $ebay_ean );


            	if ( get_option( 'wplister_enable_mpn_and_isbn_fields', 2 ) == 1 ) {
                	update_post_meta( $variation_id, '_ebay_mpn', 	  isset( $variable_ebay_mpn[$i] ) 		  ? $variable_ebay_mpn[$i] 		   : '' );
                	update_post_meta( $variation_id, '_ebay_isbn', 	  isset( $variable_ebay_isbn[$i] ) 		  ? $variable_ebay_isbn[$i] 	   : '' );
                }

            } // each variation

        } // if product has variations

    } // process_product_meta_variable()

    public function process_custom_variation_meta_fields( $post_id ) {

        // get custom variation meta fields
        $variation_meta_fields = get_option('wplister_variation_meta_fields', array() );
        if ( ! is_array($variation_meta_fields) ) return;

        foreach ( $variation_meta_fields as $key => $varmeta ) {
            $this->process_single_custom_variation_meta_field( $post_id, $key );
        }

    } // process_custom_variation_meta_fields()

    public function process_single_custom_variation_meta_field( $post_id, $key ) {
        if ( ! isset($_POST['variable_ebay_'.$key]) ) return;

        $variable_post_id       = wple_clean($_POST['variable_post_id']);
        $variable_VALUES        = wple_clean($_POST['variable_ebay_'.$key]);

        $max_loop = max( array_keys( wple_clean($_POST['variable_post_id']) ) );
        for ( $i=0; $i <= $max_loop; $i++ ) {

            if ( ! isset( $variable_post_id[$i] ) ) continue;
            $variation_id = (int) $variable_post_id[$i];

            // Update post meta
            update_post_meta( $variation_id, $key, $variable_VALUES[$i] );

        } // each variation

    } // process_single_custom_variation_meta_field()

    /**
     * @deprecated
     * @param $new_id
     * @param $post
     */
	function woocommerce_duplicate_product( $new_id, $post ) {

		// remove ebay specific meta data from duplicated products
		// delete_post_meta( $new_id, '_ebay_title' 			);
		// delete_post_meta( $new_id, '_ebay_start_price' 		);
		delete_post_meta( $new_id, '_ebay_upc' 				);
		delete_post_meta( $new_id, '_ebay_ean' 				);
		delete_post_meta( $new_id, '_ebay_mpn' 				);
		delete_post_meta( $new_id, '_ebay_isbn' 				);
		delete_post_meta( $new_id, '_ebay_epid' 			);
		delete_post_meta( $new_id, '_ebay_gallery_image_url');
		delete_post_meta( $new_id, '_ebay_item_id'			); // created by importer add-on
		delete_post_meta( $new_id, '_ebay_item_source'		); // created by importer add-on

	} // woocommerce_duplicate_product()

	function save_external_inventory( $post_id ) {

		if ( ! isset( $_POST['_stock'] ) ) return;

		// Update order data
		// see woocommerce/admin/post-types/writepanels/writepanel-product_data.php
        update_post_meta( $post_id, '_stock', 		 wple_clean( $_POST['_stock'] ) );
        update_post_meta( $post_id, '_stock_status', wple_clean( $_POST['_stock_status'] ) );
        update_post_meta( $post_id, '_backorders',   wple_clean( $_POST['_backorders'] ) );
        update_post_meta( $post_id, '_manage_stock', 'yes' );

        // a quantity of zero means out of stock
        if ( intval( $_POST['_stock'] ) == 0 ) {
	        update_post_meta( $post_id, '_stock_status', 'outofstock' );
        }

	}

	function get_current_ebay_item( $post ) {

		if ( $this->_ebay_item === null ) {
			$listings         = WPLE_ListingQueryHelper::getAllListingsFromPostID( $post->ID );
			$this->_ebay_item = is_array($listings) && !empty($listings) ? $listings[0] : false;
		}

		return $this->_ebay_item;
	}

	function get_current_listing_profile( $post ) {

		if ( $this->_listing_profile === null ) {

			// get listing object
			$listing        = $this->get_current_ebay_item( $post );
			$profile_id     = $listing && $listing->profile_id ? $listing->profile_id : false;

			// get profile
			$pm                     = new ProfilesModel();
			$profile                = $profile_id ? $pm->getItem( $profile_id ) : false;
			$this->_listing_profile = is_array($profile) ? $profile : false;
		}

		return $this->_listing_profile;
	}

} // class WpLister_Product_MetaBox
$WpLister_Product_MetaBox = new WpLister_Product_MetaBox();
