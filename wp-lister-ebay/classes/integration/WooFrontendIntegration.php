<?php
/**
 * hooks to alter the WooCommerce frontend
 */

class WPL_WooFrontendIntegration {

	function __construct() {

		add_action( 'woocommerce_single_product_summary', array( &$this, 'show_single_product_info' ), 10 );
		add_filter( 'woocommerce_loop_add_to_cart_link', array( &$this, 'handle_add_to_cart_link' ), 10, 3 );

		// add item compatibility table tab
        add_filter( 'woocommerce_product_tabs', array( &$this, 'add_custom_product_tabs' ) );

        if ( get_option( 'wplister_display_item_condition', 0 ) ) {
            add_action( 'woocommerce_product_additional_information', array( $this, 'display_condition_data'), 20, 1 );
        }

        add_action( 'init', array($this, 'register_shortcodes') );

	}

    public function register_shortcodes() {
        add_shortcode( 'ebay_gpsr_manufacturer', array( $this, 'sc_gpsr_manufacturer') );
        add_shortcode( 'ebay_gpsr_responsible_persons', array( $this, 'sc_gpsr_responsible_persons') );
    }

    public function sc_gpsr_manufacturer( $args = [] ) {
        global $post;

	    $defaults = [
		    'show_header'   => true,
		    'header'        => __('Manufacturer', 'wp-lister-for-ebay'),
	    ];
	    $args = wp_parse_args( $args, $defaults );

	    if ( function_exists( 'is_product' ) && is_product() ) {
            $product = wc_get_product( $post->ID );

            if ( $product ) {
                $listings = WPLE_ListingQueryHelper::getWhere( 'post_id', $post->ID );

                if ( empty($listings) ) {
	                return '';
                }
                $current = current( $listings );

	            $listing = new \WPLab\Ebay\Listings\Listing( $current->id );
                $manufacturer = $listing->getGpsrManufacturer();

                if ( $manufacturer ) {
                    add_filter( 'woocommerce_formatted_address_replacements', 'WPL_WooFrontendIntegration::formattedAddressReplacements', 10, 2 );
                    add_filter( 'woocommerce_localisation_address_formats', 'WPL_WooFrontendIntegration::localizationAddressFormats' );

	                $html = '<div class="wpl_gpsr_manufacturer">';

	                if ( $args['show_header'] ) {
		                $html .= '<h3>'. $args['header'] .'</h3>';
	                }

                    $address = WC()->countries->get_formatted_address([
	                    'company'    => $manufacturer->getCompanyName(),
	                    'address_1'  => $manufacturer->getStreet1(),
	                    'address_2'  => $manufacturer->getStreet2(),
	                    'city'       => $manufacturer->getCityName(),
	                    'state'      => $manufacturer->getStateOrProvince(),
	                    'postcode'   => $manufacturer->getPostalCode(),
	                    'country'    => $manufacturer->getCountry(),
                        'phone'      => $manufacturer->getPhone(),
                        'email'      => $manufacturer->getEmail()
                    ]);

	                remove_filter( 'woocommerce_formatted_address_replacements', 'WPL_WooFrontendIntegration::formattedAddressReplacements' );
	                remove_filter( 'woocommerce_localisation_address_formats', 'WPL_WooFrontendIntegration::localizationAddressFormats' );

	                $html .= '<div class="manufacturer-address">'. $address .'</div>';
                    $html .= '</div>';

                    return $html;
                }
            }
	    }
    }

	public function sc_gpsr_responsible_persons( $args = [] ) {
		global $post;

        $defaults = [
            'show_header'   => true,
            'header'        => __('Responsible Persons', 'wp-lister-for-ebay'),
        ];
        $args = wp_parse_args( $args, $defaults );

		if ( function_exists( 'is_product' ) && is_product() ) {
			$product = wc_get_product( $post->ID );

			if ( $product ) {
				$listings = WPLE_ListingQueryHelper::getWhere( 'post_id', $post->ID );

				if ( empty($listings) ) {
					return '';
				}
				$current = current( $listings );

				$listing = new \WPLab\Ebay\Listings\Listing( $current->id );
				$persons = $listing->getGpsrResponsiblePersons();

				if ( $persons ) {
					add_filter( 'woocommerce_formatted_address_replacements', 'WPL_WooFrontendIntegration::formattedAddressReplacements', 10, 2 );
					add_filter( 'woocommerce_localisation_address_formats', 'WPL_WooFrontendIntegration::localizationAddressFormats' );

                    $html = '<div class="wpl_gpsr_responsible_persons">';

                    if ( $args['show_header'] ) {
                        $html .= '<h3>'. $args['header'] .'</h3>';
                    }

                    $i = 1;
                    foreach ( $persons->getResponsiblePerson() as $person ) {

	                    $address = WC()->countries->get_formatted_address([
		                    'company'    => $person->getCompanyName(),
		                    'address_1'  => $person->getStreet1(),
		                    'address_2'  => $person->getStreet2(),
		                    'city'       => $person->getCityName(),
		                    'state'      => $person->getStateOrProvince(),
		                    'postcode'   => $person->getPostalCode(),
		                    'country'    => $person->getCountry(),
		                    'phone'      => $person->getPhone(),
		                    'email'      => $person->getEmail()
	                    ]);

                        $html .= '<div class="responsible-person-address responsible-person-'.$i .'">'. $address .'</div>';
                        $i++;
                    }

                    $html .= '</div>';

					remove_filter( 'woocommerce_formatted_address_replacements',    'WPL_WooFrontendIntegration::formattedAddressReplacements' );
					remove_filter( 'woocommerce_localisation_address_formats',      'WPL_WooFrontendIntegration::localizationAddressFormats' );

					return $html;
				}
			}
		}
	}

    // Add Email and Phone fields to the WC Formatted Address string
    public static function localizationAddressFormats( $formats ) {
        foreach( $formats as $key => $format ) {
            $formats[ $key ] .= "\n{email}\n{phone}";
        }

        return $formats;
    }

	/**
	 * @param $replacements
	 * @param $args
	 *
	 * @return mixed
	 */
    public static function formattedAddressReplacements( $replacements, $args ) {
        $replacements['{phone}'] = $args['phone'] ?? '';
        $replacements['{email}'] = $args['email'] ?? '';
        return $replacements;
    }

	// show current ebay status - WooCommerce 2.0 only
	function handle_add_to_cart_link( $html, $product, $link = false ) {
	    $product_id = wple_get_product_meta( $product, 'id' );

		$auction_display_mode = get_option( 'wplister_local_auction_display', 'off' );

		if ( $auction_display_mode == 'forced' ) {

			if ( $listing = $this->is_published_on_ebay( $product_id ) ) {

				// replace add to cart button with view details button
				$html = sprintf('<a href="%s" class="add_to_cart_button button product_type_simple">%s</a>', get_permalink( $product_id ), __( 'View details', 'wp-lister-for-ebay' ) );

			}

		} elseif ( $auction_display_mode != 'off' ) {

			if ( $listing = $this->is_on_auction( $product_id ) ) {

				// replace add to cart button with view details button
				$html = sprintf('<a href="%s" class="add_to_cart_button button product_type_simple">%s</a>', get_permalink( $product_id ), __( 'View details', 'wp-lister-for-ebay' ) );

			}

		}

		return $html;
	}


	// show current ebay status
	function show_single_product_info() {
		global $post;

		$auction_display_mode = get_option( 'wplister_local_auction_display', 'off' );

		if ( $auction_display_mode == 'forced' ) {

			if ( $listing = $this->is_published_on_ebay( $post->ID ) ) {

				// view on ebay button
				echo '<p>';
				echo sprintf('<a href="%s" class="single_add_to_cart_button button alt" target="_blank">%s</a>', $listing->ViewItemURL, __( 'View on eBay', 'wp-lister-for-ebay' ) );
				echo '</p>';

				// hide woo elements
				echo '<style> form.cart { display:none } </style>';

			}

		} elseif ( $auction_display_mode != 'off' ) {

			if ( $listing = $this->is_on_auction( $post->ID ) ) {
				// echo "<pre>";print_r($listing);echo"</pre>";die();

				$details = $this->getItemDetails( $listing->ebay_id );

				if ( $details['BidCount'] == 0 ) {
					
					// do nothing if "only if bids" is enabled and there are more than 12 hours left
					// $auction_display_mode = get_option( 'wplister_local_auction_display', 'off' );
					$hours_left           = ( strtotime($listing->end_date) - gmdate('U') ) / 3600;
					if ( ( $hours_left > 12 ) && ( $auction_display_mode == 'if_bid' ) ) return;

					// start price
					echo '<p itemprop="price" class="price startprice">'.__( 'Starting bid', 'wp-lister-for-ebay' ).': <span class="amount">'.wc_price($listing->price).'</span></p>';
				} else {
					// current price
					echo '<p itemprop="price" class="price startprice">'.__( 'Current bid', 'wp-lister-for-ebay' ).': <span class="amount">'.wc_price($details['CurrentPrice']).'</span>';
					echo ' ('.$details['BidCount']. __( 'bids', 'wp-lister-for-ebay' ).')';
					echo '</p>';
				}

				// auction message
				if ( $listing->end_date ) {
					$msg = __( 'This item is currently on auction and will end in %s', 'wp-lister-for-ebay' );
					$msg = sprintf( $msg, human_time_diff( strtotime( $listing->end_date ) ) );
				} else {
					$msg = __( 'This item is currently on auction on eBay.', 'wp-lister-for-ebay' );
				}
				echo '<p>'.$msg.'</p>';

				// view on ebay button
				echo '<p>';
				echo sprintf('<a href="%s" class="single_add_to_cart_button button alt" target="_blank">%s</a>', $listing->ViewItemURL, __( 'View on eBay', 'wp-lister-for-ebay' ) );
				echo '</p>';

				// hide woo elements
				echo '<style> form.cart, p.price { display:none }  p.startprice { display:inline }  </style>';

			}

		}

	} // show_single_product_info()


	// get current details
	function getItemDetails( $ebay_id ) {

		$transient_key = 'wplister_ebay_details_'.$ebay_id;

		$details = get_transient( $transient_key );
		if ( empty( $details ) ){
		   
			// fetch ebay details and update transient
			$item_details = $this->updateItemDetails( $ebay_id );

			$details = array(
				'StartTime'     => $item_details->ListingDetails->StartTime,
				'EndTime'       => $item_details->ListingDetails->EndTime,
				'Quantity'      => $item_details->Quantity,
				'QuantitySold'  => $item_details->SellingStatus->QuantitySold,
				'BidCount'      => $item_details->SellingStatus->BidCount,
				'CurrentPrice'  => $item_details->SellingStatus->CurrentPrice->value,
				'ListingStatus' => $item_details->SellingStatus->ListingStatus,
			);

			set_transient($transient_key, $details, 60 );
		}

		return $details;

	} // getItemDetails()


	// update current details from ebay
	function updateItemDetails( $ebay_id ) {

		WPLE()->initEC();

		$lm = new ListingsModel();
		$details = $lm->getLatestDetails( $ebay_id, WPLE()->EC->session );

		return $details;

	} // updateItemDetails()


	// check if product is currently on auction
	function is_on_auction( $post_id ) {

		$listings = WPLE_ListingQueryHelper::getAllListingsFromPostID( $post_id );
		foreach ($listings as $listing) {

			// check listing type on product level
			if ( get_post_meta( $post_id, '_ebay_auction_type', true ) != 'Chinese' ) {

				// check listing type on listing level
				if ( $listing->auction_type != 'Chinese') continue;

			}

			// check status
			if ( ! in_array( $listing->status, array('published','changed') ) )
				 continue;

			// check end date
			if ( $listing->end_date )
				if ( strtotime( $listing->end_date ) < time() ) continue;

			return $listing;
		}

		return false;

	} // is_on_auction()

	// check if product is currently published on ebay
	function is_published_on_ebay( $post_id ) {

		$listings = WPLE_ListingQueryHelper::getAllListingsFromPostID( $post_id );
		foreach ($listings as $listing) {

			// check status
			if ( ! in_array( $listing->status, array('published','changed') ) )
				 continue;

			// check end date
			if ( $listing->end_date )
				if ( strtotime( $listing->end_date ) < time() ) continue;

			return $listing;
		}

		return false;

	} // is_published_on_ebay()

    public function add_custom_product_tabs( $tabs ) {
		global $post;

		// check if compatibility tab is enabled
		if ( ! get_option( 'wplister_enable_item_compat_tab', 1 ) ) return $tabs;
		if ( ! $post ) return $tabs;

		// don't add tab if there is no compatibility list
        $compatibility_list   = wple_get_compatibility_list( $post->ID );

        if ( ( ! is_array($compatibility_list) ) || ( sizeof($compatibility_list) == 0 ) ) return $tabs;

        $tabs[ 'ebay_item_compatibility_list' ] = array(
                'title'    => __( 'Compatibility', 'wp-lister-for-ebay' ),
                'priority' => 25,
                'callback' => array( $this, 'showCompatibilityList' ),
                // 'content'  => $tab['content'],  // custom field
        );

        return $tabs;
    }

    public function display_condition_data( $product ) {
        $product_id = wple_get_product_meta( $product, 'id' );

        $condition_id = get_post_meta( $product_id, '_ebay_condition_id', true );
        $description  = get_post_meta( $product_id, '_ebay_condition_description', true );

        if ( !$condition_id ) return;

        WPLE()->logger->info( 'Found condition_id: '. $condition_id .' ('. $description .')' );

        // default conditions - used when no primary category has been selected
        $default_conditions = array(
            1000 => __('New', 						'wplister'),
            1500 => __('New other', 				'wplister'),
            1750 => __('New with defects', 			'wplister'),
            2000 => __('Manufacturer refurbished', 	'wplister'),
            2500 => __('Seller refurbished', 		'wplister'),
            3000 => __('Used', 						'wplister'),
            4000 => __('Very Good', 				'wplister'),
            5000 => __('Good', 						'wplister'),
            6000 => __('Acceptable', 				'wplister'),
            7000 => __('For parts or not working', 	'wplister'),
        );

        if ( !$condition_id || !isset( $default_conditions[ $condition_id ] ) ) {
            $condition = 'n/a';
        } else {
            $condition = $default_conditions[ $condition_id ];
        }

        ?>
        <table class="shop_attributes">
            <tr>
                <th><?php _e('Condition', 'wplister'); ?></th>
                <td><?php echo $condition; ?></td>
            </tr>
            <?php if ( $description ): ?>
                <tr>
                    <th>Notes</th>
                    <td><?php echo wp_kses_post( $description ); ?></td>
                </tr>
            <?php endif; ?>
        </table>
        <?php
    }


	function showCompatibilityList() {
		global $post;

		// get compatibility list and names
		$compatibility_list   = wple_get_compatibility_list( $post->ID );
		$compatibility_names  = wple_get_compatibility_names( $post->ID );
		#echo "<pre>";print_r($compatibility_names);echo"</pre>";#die();

		// return if there is no compatibility list
		if ( ( ! is_array($compatibility_list) ) || ( sizeof($compatibility_list) == 0 ) ) return;

		do_action( 'wplister_before_item_compatibility_list', $post->ID );

		echo '<h2>'.  __( 'Item Compatibility List', 'wp-lister-for-ebay' ) . '</h2>';

		?>
			<table class="ebay_item_compatibility_list">

				<tr>
					<?php foreach ($compatibility_names as $name) :
                            $name =  apply_filters_deprecated( 'wplister_compatibility_heading', array($name), '2.8.4', 'wple_compatibility_heading' );
                            $name =  apply_filters( 'wple_compatibility_heading', $name );
                        ?>

						<th><?php echo $name; ?></th>

					<?php endforeach; ?>

					<th>	
						<?php echo 'Notes' ?>
					</th>

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

			<style type="text/css">

				.ebay_item_compatibility_list {
					width: 100%;
				}
				.ebay_item_compatibility_list tr th {
					text-align: left;
					border-bottom: 3px double #bbb;
				}
				.ebay_item_compatibility_list tr td {
					border-bottom: 1px solid #ccc;
				}
				
			</style>

		<?php

		do_action( 'wplister_after_item_compatibility_list', $post->ID );

	}


} // class WPL_WooFrontendIntegration
$WPL_WooFrontendIntegration = new WPL_WooFrontendIntegration();
