<?php include_once( dirname(__FILE__).'/common_header.php' ); ?>

<style type="text/css">
    @import "<?php echo WPLE_PLUGIN_URL; ?>css/lite.css";

	#poststuff #side-sortables .postbox input.text_input,
	#poststuff #side-sortables .postbox select.select {
	    width: 50%;
	}
	#poststuff #side-sortables .postbox label.text_label {
	    width: 45%;
	}
	#poststuff #side-sortables .postbox p.desc {
	    margin-left: 5px;
	}

</style>

<div class="wrap wplister-page">
	<div class="icon32" style="background: url(<?php echo $wpl_plugin_url; ?>img/hammer-32x32.png) no-repeat;" id="wpl-icon"><br /></div>

	<?php include_once( dirname(__FILE__).'/settings_tabs.php' ); ?>
	<?php echo $wpl_message ?>

	<form method="post" id="settingsForm" action="<?php echo $wpl_form_action; ?>">

	<div id="poststuff">
		<div id="post-body" class="metabox-holder columns-2">

			<div id="postbox-container-1" class="postbox-container">
				<div id="side-sortables" class="meta-box">


					<!-- first sidebox -->
					<div class="postbox" id="submitdiv">
						<!--<div title="Click to toggle" class="handlediv"><br></div>-->
						<h3 class="hndle"><span><?php echo __( 'Update', 'wp-lister-for-ebay' ); ?></span></h3>
						<div class="inside">

							<div id="submitpost" class="submitbox">

								<div id="misc-publishing-actions">
									<div class="misc-pub-section">
										<p><?php echo __( 'This page contains some advanced options for special use cases.', 'wp-lister-for-ebay' ) ?></p>
									</div>
								</div>

								<div id="major-publishing-actions">
									<div id="publishing-action">
                                        <?php wp_nonce_field( 'wplister_save_advanced_settings' ); ?>
										<input type="hidden" name="action" value="save_wplister_advanced_settings" >
										<input type="submit" value="<?php echo __( 'Save Settings', 'wp-lister-for-ebay' ); ?>" id="save_settings" class="button-primary" name="save">
									</div>
									<div class="clear"></div>
								</div>

							</div>

						</div>
					</div>

					<?php if ( ( ! is_multisite() ) || ( is_main_site() ) ) : ?>
					<div class="postbox" id="UninstallSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Uninstall on deactivation', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">

							<label for="wpl-option-uninstall" class="text_label"><?php echo __( 'Uninstall', 'wp-lister-for-ebay' ); ?></label>
							<select id="wpl-option-uninstall" name="wpl_e2e_option_uninstall" class="required-entry select">
								<option value="0" <?php if ( $wpl_option_uninstall != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
								<option value="1" <?php if ( $wpl_option_uninstall == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Enable to completely remove listings, transactions and settings when deactivating the plugin.', 'wp-lister-for-ebay' ); ?><br><br>
								<?php echo __( 'To remove your listing templates as well, please delete the folder <code>wp-content/uploads/wp-lister/templates/</code>.', 'wp-lister-for-ebay' ); ?>
								<!-- ## BEGIN PRO ## -->
								<br><br>
								<?php echo __( 'Please deactivate your license first.', 'wp-lister-for-ebay' ); ?>
								<!-- ## END PRO ## -->
							</p>

						</div>
					</div>
					<?php endif; ?>

					<?php #include('profile/edit_sidebar.php') ?>
				</div>
			</div> <!-- #postbox-container-1 -->

			<!-- #postbox-container-3 -->
			<?php if ( ( ! is_multisite() || is_main_site() ) && apply_filters( 'wpl_enable_capabilities_options', true ) ) : ?>
			<div id="postbox-container-3" class="postbox-container">
				<div class="meta-box-sortables ui-sortable">

					<div class="postbox" id="PermissionsSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Roles and Capabilities', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">

							<?php
								$wpl_caps = array(
									'manage_ebay_listings'  => __( 'Manage Listings', 'wp-lister-for-ebay' ),
									'manage_ebay_options'   => __( 'Manage Settings', 'wp-lister-for-ebay' ),
									'prepare_ebay_listings' => __( 'Prepare Listings', 'wp-lister-for-ebay' ),
									'publish_ebay_listings' => __( 'Publish Listings', 'wp-lister-for-ebay' ),
								);
							?>

							<table style="width:100%">
                            <?php foreach ($wpl_available_roles as $role => $role_name) : ?>
                            	<tr>
                            		<th style="text-align: left">
		                                <?php echo $role_name; ?>
		                            </th>

		                            <?php foreach ($wpl_caps as $cap => $cap_name ) : ?>
                            		<td>
		                                <input type="checkbox"
		                                    	name="wpl_permissions[<?php echo $role ?>][<?php echo $cap ?>]"
		                                       	id="wpl_permissions_<?php echo $role.'_'.$cap ?>" class="checkbox_cap"
		                                       	<?php if ( isset( $wpl_wp_roles[ $role ]['capabilities'][ $cap ] ) ) : ?>
		                                       		checked
		                                   		<?php endif; ?>
		                                       	/>
		                                       	<label for="wpl_permissions_<?php echo $role.'_'.$cap ?>">
				                               		<?php echo $cap_name; ?>
				                               	</label>
			                            </td>
		                            <?php endforeach; ?>

		                        </tr>
                            <?php endforeach; ?>
                        	</table>


						</div>
					</div>

				</div>
			</div> <!-- #postbox-container-1 -->
			<?php endif; ?>


			<!-- #postbox-container-2 -->
			<div id="postbox-container-2" class="postbox-container">
				<div class="meta-box-sortables ui-sortable">

					<?php do_action( 'wple_before_advanced_settings' ) ?>

					<div class="postbox" id="TemplateSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Listing Settings', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">

                            <label for="wpl-accepted_product_status" class="text_label">
                                <?php echo __( 'Allowed Product Status', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Select the accepted product statuses that WP-Lister will include when preparing products to be listed on eBay.','wp-lister-for-ebay')) ?>
                            </label>
                            <select id="wpl-allowed_product_status" name="wpl_e2e_allowed_product_status[]" class="required-entry wple_chosen_select" multiple="multiple" style="min-width: 60%;">
                                <?php
                                $statuses = [
                                    'publish'   => __( 'Published' ),
                                    'private'   => __( 'Private' ),
                                    'draft'     => __( 'Draft' ),
                                    'pending'   => __('Pending' )
                                ];
                                foreach ( $statuses as $key => $value ):
                                ?>
                                    <option value="<?php esc_attr_e( $key ); ?>" <?php selected( true, in_array( $key, $wpl_allowed_product_status ) ); ?>><?php echo esc_html($value); ?></option>
                                <?php
                                endforeach;
                                ?>
                            </select>
                            <p class="desc" style="display: block;">
                                <?php echo __( 'Select the product statuses that WP-Lister will include when publishing products to eBay.', 'wp-lister-for-ebay' ); ?><br>
                            </p>

							<label for="wpl-process_shortcodes" class="text_label">
								<?php echo __( 'Shortcode processing', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('By default, WP-Lister runs your product description through the usual WordPress content filters which enabled you to use shortcodes in your product descriptions.<br>If a plugin causes trouble by adding unwanted HTML to your description on eBay, you should try setting this to "off".','wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-process_shortcodes" name="wpl_e2e_process_shortcodes" class="required-entry select">
								<option value="off"     <?php if ( $wpl_process_shortcodes == 'off' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'off', 'wp-lister-for-ebay' ); ?></option>
								<option value="content" <?php if ( $wpl_process_shortcodes == 'content' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'only in product description', 'wp-lister-for-ebay' ); ?></option>
								<option value="full"    <?php if ( $wpl_process_shortcodes == 'full' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'in description and listing template', 'wp-lister-for-ebay' ); ?></option>
								<option value="remove"  <?php if ( $wpl_process_shortcodes == 'remove' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'remove all shortcodes from description', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Enable this if you want to use WordPress shortcodes in your product description or your listing template.', 'wp-lister-for-ebay' ); ?><br>
							</p>

                            <label for="wpl-do_template_autop" class="text_label">
                                <?php echo __( 'Convert line breaks to paragraphs', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('All line breaks in the product description are converted into paragraphs by default for a cleaner look.', 'wp-lister-for-ebay')) ?>
                            </label>
                            <select id="wpl-do_template_autop" name="wpl_e2e_do_template_autop" class="required-entry select">
                                <option value="1" <?php selected( $wpl_do_template_autop, 1 ); ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay'); ?>)</option>
                                <option value="0" <?php selected( $wpl_do_template_autop, 0 ); ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
                            </select>

							<label for="wpl-remove_links" class="text_label">
								<?php echo __( 'Link handling', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Should WP-Lister replace links within the product description with plain text?', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-remove_links" name="wpl_e2e_remove_links" class="required-entry select">
								<option value="default"         <?php selected( 'default', $wpl_remove_links ); ?>><?php echo __( 'remove all links from description', 'wp-lister-for-ebay' ); ?></option>
								<option value="remove_external" <?php selected( 'remove_external', $wpl_remove_links ); ?>><?php echo __( 'remove all non-eBay links from description', 'wp-lister-for-ebay' ); ?></option>
								<option value="allow_all"       <?php selected( 'allow_all', $wpl_remove_links ); ?>><?php echo __( 'allow all links', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Links are removed from product descriptions by default to avoid violating the eBay Links policy.', 'wp-lister-for-ebay' ); ?>
								<?php echo __( 'Specifically you are not allowed to advertise products that you list on eBay by linking to their product pages on your site.', 'wp-lister-for-ebay' ); ?>

								<?php echo __( 'Read more about eBay\'s Link policy', 'wp-lister-for-ebay' ); ?>
								<a href="<?php echo __( 'http://pages.ebay.com/help/policies/listing-links.html', 'wp-lister-for-ebay' ); ?>" target="_blank"><?php echo __('here', 'wp-lister-for-ebay' ); ?></a>
							</p>

							<label for="wpl-template_ssl_mode" class="text_label">
								<?php echo __( 'HTTPS conversion', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Enable this to make sure all image links in your listing template use HTTPS.<br>If your site supports SSL, it is recommended to set this option to "Use HTTPS".', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-template_ssl_mode" name="wpl_e2e_template_ssl_mode" class="required-entry select">
								<option value=""           <?php if ( $wpl_template_ssl_mode == ''          ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Off', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="https"      <?php if ( $wpl_template_ssl_mode == 'https'     ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Use HTTPS', 'wp-lister-for-ebay' ); ?> (<?php _e('recommended', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="enforce"    <?php if ( $wpl_template_ssl_mode == 'enforce'   ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Convert all HTTP content to HTTPS', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Enable this if your site supports HTTPS.', 'wp-lister-for-ebay' ); ?>
							</p>

							<label for="wpl-wc2_gallery_fallback" class="text_label">
								<?php echo __( 'Product Gallery', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('In order to find additional product images, WP-Lister first checks if there is a dedicated <i>Product Gallery</i> (WC 2.0+).<br>
                                						If there\'s none, it can use all images which were uploaded (attached) to the product - as it was the default behaviour in WooCommerce 1.x.','wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-wc2_gallery_fallback" name="wpl_e2e_wc2_gallery_fallback" class="required-entry select">
								<option value="attached" <?php if ( $wpl_wc2_gallery_fallback == 'attached' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'use attached images if no Gallery found', 'wp-lister-for-ebay' ); ?></option>
								<option value="none"     <?php if ( $wpl_wc2_gallery_fallback == 'none'     ): ?>selected="selected"<?php endif; ?>><?php echo __( 'use Product Gallery images', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
							</select>
							<?php if ( $wpl_wc2_gallery_fallback == 'attached' ): ?>
							<p class="desc" style="display: block;">
								<?php echo __( 'If you find unwanted images in your listings try disabling this option.', 'wp-lister-for-ebay' ); ?>
							</p>
							<?php else : ?>
							<p class="desc" style="display: block;">
								<?php echo __( 'It is recommended to keep the default setting.', 'wp-lister-for-ebay' ); ?><br>
							</p>
							<?php endif; ?>

							<label for="wpl-default_image_size" class="text_label">
								<?php echo __( 'Default image size', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Select the image size WP-Lister should use on eBay. It is recommended to set this to "full size".', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-default_image_size" name="wpl_e2e_default_image_size" class="required-entry select">
								<option value="full"    <?php if ( $wpl_default_image_size == 'full'   ): ?>selected="selected"<?php endif; ?>><?php echo __( 'full size', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="large"   <?php if ( $wpl_default_image_size == 'large'  ): ?>selected="selected"<?php endif; ?>><?php echo __( 'large size', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'It is recommended to keep the default setting.', 'wp-lister-for-ebay' ); ?><br>
							</p>

							<label for="wpl-gallery_items_limit" class="text_label">
								<?php echo __( 'Gallery Widget limit', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Limit the number of items displayed by the gallery widgets in your listing template - like <i>recent additions</i> or <i>ending soon</i>. The default is 12 items.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-gallery_items_limit" name="wpl_e2e_gallery_items_limit" class="required-entry select">
								<option value="3" <?php if ( $wpl_gallery_items_limit == '3' ): ?>selected="selected"<?php endif; ?>>3 <?php echo __( 'items', 'wp-lister-for-ebay' ); ?></option>
								<option value="6" <?php if ( $wpl_gallery_items_limit == '6' ): ?>selected="selected"<?php endif; ?>>6 <?php echo __( 'items', 'wp-lister-for-ebay' ); ?></option>
								<option value="9" <?php if ( $wpl_gallery_items_limit == '9' ): ?>selected="selected"<?php endif; ?>>9 <?php echo __( 'items', 'wp-lister-for-ebay' ); ?></option>
								<option value="12" <?php if ( $wpl_gallery_items_limit == '12' ): ?>selected="selected"<?php endif; ?>>12 <?php echo __( 'items', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="15" <?php if ( $wpl_gallery_items_limit == '15' ): ?>selected="selected"<?php endif; ?>>15 <?php echo __( 'items', 'wp-lister-for-ebay' ); ?></option>
								<option value="24" <?php if ( $wpl_gallery_items_limit == '24' ): ?>selected="selected"<?php endif; ?>>24 <?php echo __( 'items', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'The maximum number of items shown by listings template gallery widgets.', 'wp-lister-for-ebay' ); ?>
							</p>

						</div>
					</div>

					<div class="postbox" id="UISettingsBox">
						<h3 class="hndle"><span><?php echo __( 'User Interface', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">

							<?php if ( ! defined('WPLISTER_RESELLER_VERSION') ) : ?>
							<label for="wpl-text-admin_menu_label" class="text_label">
								<?php echo __( 'Menu label', 'wp-lister-for-ebay' ) ?>
                                <?php wplister_tooltip(__('You can change the main admin menu label in your dashboard from WP-Lister to anything you like.', 'wp-lister-for-ebay')) ?>
							</label>
							<input type="text" name="wpl_e2e_text_admin_menu_label" id="wpl-text-admin_menu_label" value="<?php echo esc_attr($wpl_text_admin_menu_label); ?>" class="text_input" />
							<p class="desc" style="display: block;">
								<?php echo __( 'Customize the main admin menu label of WP-Lister.', 'wp-lister-for-ebay' ); ?><br>
							</p>
							<?php endif; ?>

							<label for="wpl-option-preview_in_new_tab" class="text_label">
								<?php echo __( 'Open preview in new tab', 'wp-lister-for-ebay' ) ?>
                                <?php wplister_tooltip(__('WP-Lister uses a Thickbox modal window to display the preview by default. However, this can cause issues in rare cases where you embed some JavaScript code (like NivoSlider) - or you might just want more screen estate to preview your listings.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-option-preview_in_new_tab" name="wpl_e2e_option_preview_in_new_tab" class="required-entry select">
								<option value="0" <?php if ( $wpl_option_preview_in_new_tab != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="1" <?php if ( $wpl_option_preview_in_new_tab == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Select if you want the listing preview open in a new tab by default.', 'wp-lister-for-ebay' ); ?><br>
							</p>

							<label for="wpl-option-thumbs_display_size" class="text_label">
								<?php echo __( 'Listing thumbnails', 'wp-lister-for-ebay' ) ?>
                                <?php wplister_tooltip(__('Select the thumbnail size on the Listings page.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-option-thumbs_display_size" name="wpl_e2e_thumbs_display_size" class="required-entry select">
								<option value="0" <?php if ( $wpl_thumbs_display_size == '0' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Small', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="1" <?php if ( $wpl_thumbs_display_size == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Medium', 'wp-lister-for-ebay' ); ?></option>
								<option value="2" <?php if ( $wpl_thumbs_display_size == '2' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Large', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Select the thumbnail size on the Listings page.', 'wp-lister-for-ebay' ); ?><br>
							</p>

                            <label for="wpl-option-listing_sku_sorting" class="text_label">
                                <?php echo __( 'Enable searching &amp; sorting by SKU', 'wp-lister-for-ebay' ) ?>
                                <?php wplister_tooltip(__('Enable or disable searching and sorting by SKU.<br><br>This is disabled by default as it can negatively impact the load time of the Listings page for stores with thousands of products.', 'wp-lister-for-ebay')) ?>
                            </label>
                            <select id="wpl-option-listing_sku_sorting" name="wpl_e2e_listing_sku_sorting" class="required-entry select">
                                <option value="1" <?php selected( $wpl_listing_sku_sorting, 1 ); ?>><?php echo __( 'Enabled', 'wp-lister-for-ebay' ); ?></option>
                                <option value="0" <?php selected( $wpl_listing_sku_sorting, 0 ); ?>><?php echo __( 'Disabled', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                            </select>
                            <p class="desc" style="display: block;">
                                <?php echo __( 'Enable this to make the SKU column sortable and searchable. Can affect load time of the Listings page.', 'wp-lister-for-ebay' ); ?><br>
                            </p>

							<label for="wpl-enable_custom_product_prices" class="text_label">
								<?php echo __( 'Enable custom price field', 'wp-lister-for-ebay' ) ?>
                                <?php wplister_tooltip(__('If do not use custom prices in eBay and prefer less options when editing a product, you can disable the custom price fields here.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-enable_custom_product_prices" name="wpl_e2e_enable_custom_product_prices" class=" required-entry select">
								<option value="0" <?php if ( $wpl_enable_custom_product_prices == '0' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
								<option value="1" <?php if ( $wpl_enable_custom_product_prices == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="2" <?php if ( $wpl_enable_custom_product_prices == '2' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Hide for variations', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Show or hide the custom eBay price field.', 'wp-lister-for-ebay' ); ?><br>
							</p>

							<label for="wpl-enable_mpn_and_isbn_fields" class="text_label">
								<?php echo __( 'Enable MPN and ISBN fields', 'wp-lister-for-ebay' ) ?>
                                <?php wplister_tooltip(__('If your variable products have MPNs or ISBNs, set this option to <i>Yes</i>.<br><br>If you need MPNs or ISBNs only on simple products, leave it at the default setting.<br><br>If you never use MPNs nor ISBNs, set it to <i>No</i>.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-enable_mpn_and_isbn_fields" name="wpl_e2e_enable_mpn_and_isbn_fields" class=" required-entry select">
								<option value="0" <?php if ( $wpl_enable_mpn_and_isbn_fields == '0' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
								<option value="1" <?php if ( $wpl_enable_mpn_and_isbn_fields == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
								<option value="2" <?php if ( $wpl_enable_mpn_and_isbn_fields == '2' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Hide for variations', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Show or hide the MPN and ISBN fields.', 'wp-lister-for-ebay' ); ?><br>
							</p>

							<label for="wpl-enable_categories_page" class="text_label">
								<?php echo __( 'Categories in main menu', 'wp-lister-for-ebay' ) ?>
                                <?php wplister_tooltip(__('This will add a <em>Categories</em> submenu entry visible to users who can manage listings.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-enable_categories_page" name="wpl_e2e_enable_categories_page" class="required-entry select">
								<option value="0" <?php if ( $wpl_enable_categories_page != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="1" <?php if ( $wpl_enable_categories_page == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Enable this to make category settings available to users without access to other eBay settings.', 'wp-lister-for-ebay' ); ?><br>
							</p>

                            <label for="wpl-store_categories_sorting" class="text_label">
                                <?php echo __( 'Store Categories Order', 'wp-lister-for-ebay' ) ?>
                                <?php wplister_tooltip(__('Choose whether to display the store categories using the manual order from eBay, or sort them alphabetically.', 'wp-lister-for-ebay')) ?>
                            </label>
                            <select id="wpl-store_categories_sorting" name="wpl_e2e_store_categories_sorting" class="required-entry select">
                                <option value="default" <?php selected( $wpl_store_categories_sorting, 'default' ); ?>><?php echo __( 'Manual sort order', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                <option value="alphabetical" <?php selected( $wpl_store_categories_sorting, 'alphabetical' ); ?>><?php echo __( 'Sort alphabetically', 'wp-lister-for-ebay' ); ?></option>
                            </select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Select whether you want your store categories to be sorted alphabetically.', 'wp-lister-for-ebay' ); ?><br>
							</p>

							<label for="wpl-enable_accounts_page" class="text_label">
								<?php echo __( 'Accounts in main menu', 'wp-lister-for-ebay' ) ?>
                                <?php wplister_tooltip(__('This will add a <em>Accounts</em> submenu entry visible to users who can manage listings.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-enable_accounts_page" name="wpl_e2e_enable_accounts_page" class="required-entry select">
								<option value="0" <?php if ( $wpl_enable_accounts_page != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="1" <?php if ( $wpl_enable_accounts_page == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Enable this to make account settings available to users without access to other eBay settings.', 'wp-lister-for-ebay' ); ?><br>
							</p>

							<label for="wpl-option-disable_wysiwyg_editor" class="text_label">
								<?php echo __( 'Disable WYSIWYG editor', 'wp-lister-for-ebay' ) ?>
                                <?php wplister_tooltip(__('Depending in your listing template content, you might want to disable the built in WP editor to edit your template content.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-option-disable_wysiwyg_editor" name="wpl_e2e_option_disable_wysiwyg_editor" class="required-entry select">
								<option value="0" <?php if ( $wpl_option_disable_wysiwyg_editor != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="1" <?php if ( $wpl_option_disable_wysiwyg_editor == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Select the editor you want to use to edit listing templates.', 'wp-lister-for-ebay' ); ?><br>
							</p>

							<label for="wpl-hide_dupe_msg" class="text_label">
								<?php echo __( 'Hide duplicates warning', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Technically, WP-Lister allows you to list the same product multiple times on eBay - in order to increase your visibility. However, this is not recommended as WP-Lister Pro would not be able to decrease the stock on eBay accordingly when the product is sold in WooCommerce.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-hide_dupe_msg" name="wpl_e2e_hide_dupe_msg" class="required-entry select">
								<option value=""  <?php if ( $wpl_hide_dupe_msg == ''  ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('recommended', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="1" <?php if ( $wpl_hide_dupe_msg == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes, I know what I am doing.', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'If you do not plan to use the synchronize sales feature, you can safely list one product multiple times.', 'wp-lister-for-ebay' ); ?>
							</p>

                            <label for="wpl-option-display_product_counts" class="text_label">
                                <?php _e( 'Show eBay product totals', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('This will display the total number of products <i>On eBay</i> and <i>Not on eBay</i> on the Products admin page in WooCommerce.<br><br>Please note: Enabling this option requires some complex database queries which might slow down loading the Products admin page.<br><br>If the Products page is taking too long to load, you should disable this option or move to a more powerful hosting/server.', 'wp-lister-for-ebay')); ?>
                            </label>
                            <select id="wpl-option-display_product_counts" name="wpl_e2e_display_product_counts" class="required-entry select">
                                <option value="0" <?php selected( $wpl_display_product_counts, 0 ); ?>><?php _e( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                <option value="1" <?php selected( $wpl_display_product_counts, 1 ); ?>><?php _e( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                            </select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Enable this to display the total number of products on eBay / not on eBay in WooCommerce.', 'wp-lister-for-ebay' ); ?>
							</p>

                            <label for="wpl-option-enhanced_item_specifics_ui" class="text_label">
                                <?php _e( 'Enable enhanced Item Specifics interface', 'wp-lister-for-ebay' ); ?>

                            </label>
                            <select id="wpl-option-enhanced_item_specifics_ui" name="wpl_e2e_enhanced_item_specifics_ui" class="required-entry select">
                                <option value="0" <?php selected( $wpl_enhanced_item_specifics_ui, 0 ); ?>><?php _e( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                <option value="1" <?php selected( $wpl_enhanced_item_specifics_ui, 1 ); ?>><?php _e( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                            </select>
                            <p class="desc" style="display: block;">
                                <?php echo __( 'Enable this to use the new interface in setting Item Specifics in Product pages. Please note that enabling this may cause some slight performance issues depending on the number of recommendations from eBay.', 'wp-lister-for-ebay' ); ?>
                            </p>

						</div>
					</div>

					<div class="postbox" id="OrderCreationSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Creating orders in WooCommerce', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-use_ebay_order_number" class="text_label">
		                            <?php echo __( 'Use eBay Order Number', 'wp-lister-for-ebay' ); ?>
		                            <?php wplister_tooltip(__('Enable this if you want WP-Lister to use the order number from eBay when creating new WC orders.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-use_ebay_order_number" name="wpl_e2e_use_ebay_order_number" class="required-entry select">
                                    <option value="0" <?php selected( $wpl_use_ebay_order_number, 0 ); ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="1" <?php selected( $wpl_use_ebay_order_number, 1 ); ?>><?php echo __( 'Use eBay Order ID', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="2" <?php selected( $wpl_use_ebay_order_number, 2 ); ?>><?php echo __( 'Use Extended Order ID', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'Use the original order number from eBay for new orders in WooCommerce.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-ebay_order_ids_storage" class="text_label">
		                            <?php echo __( 'Store eBay order ID as', 'wp-lister-for-ebay' ); ?>
		                            <?php wplister_tooltip(__('Select where WP-Lister should store the eBay User and Sales IDs when creating orders in WooCommerce.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-ebay_order_ids_storage" name="wpl_e2e_ebay_order_ids_storage" class=" required-entry select">
                                    <option value="notes" <?php selected( $wpl_ebay_order_ids_storage, 'notes' ); ?>><?php _e( 'Order Notes', 'wp-lister-for-ebay'); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="excerpt" <?php selected( $wpl_ebay_order_ids_storage, 'excerpt' ); ?>><?php _e( 'Customer Note', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="both" <?php selected( $wpl_ebay_order_ids_storage, 'both' ); ?>><?php _e( 'Order Notes and Customer Note', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'Select where to store the eBay User and Sales ID when creating orders in WooCommerce.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-record_cod_cost" class="text_label">
		                            <?php echo __( 'Store COD cost as fee', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('Enable this to check and include the COD cost from eBay into WooCommerce orders', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-record_cod_cost" name="wpl_e2e_record_cod_cost" class=" required-entry select">
                                    <option value="0" <?php selected( $wpl_record_cod_cost, 0 ); ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="1" <?php selected( $wpl_record_cod_cost, 1 ); ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'Enable this to store Cash On Delivery costs as a fee row in WooCommerce.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-record_ebay_fee" class="text_label">
		                            <?php echo __( 'Store eBay fee', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('Enable this to include the eBay fees into WooCommerce orders', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-record_ebay_fee" name="wpl_e2e_record_ebay_fee" class=" required-entry select">
                                    <option value="no" <?php selected( $wpl_record_ebay_fee, 'no' ); ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="fee" <?php selected( $wpl_record_ebay_fee, 'fee' ); ?>><?php echo __( 'Yes, store as an order fee', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="discount" <?php selected( $wpl_record_ebay_fee, 'discount' ); ?>><?php echo __( 'Yes, deduct fee from the order total', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="meta" <?php selected( $wpl_record_ebay_fee, 'meta' ); ?>><?php echo __( 'Yes, store as order line item meta', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'Enable this to store eBay fees in WooCommerce orders.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-store_sku_as_order_meta" class="text_label">
		                            <?php echo __( 'Store SKU as line item meta field', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('An order in WooCommerce usually does not store the SKU for each order line item but only a reference to the product from which WooCommerce pulls the SKU to display on the order details page.<br><br>This can lead to problems when WP-Lister creates an order for a product which does not exist in WooCommerce, or if the SKU is changed, so by default the SKU is stored as a separate order line item meta field.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-store_sku_as_order_meta" name="wpl_e2e_store_sku_as_order_meta" class=" required-entry select">
                                    <option value="1" <?php if ( $wpl_store_sku_as_order_meta == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="0" <?php if ( $wpl_store_sku_as_order_meta != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'Disable this option if you do not want the SKU to appear in a separate row in WooCommerce.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-process_multileg_orders" class="text_label">
		                            <?php echo __( 'Global Shipping Program', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('Select whether you want international orders which use Global Shipping Program to be handled in a special way.<br><br>If you choose to use the shipping address of the eBay shipping center, the order total of the created order in WooCommerce will not include the shipping fee.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-process_multileg_orders" name="wpl_e2e_process_multileg_orders" class=" required-entry select">
                                    <option value="0" <?php selected( 0, $wpl_process_multileg_orders ); ?>><?php echo __( 'Use buyer shipping address', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="1" <?php selected( 1, $wpl_process_multileg_orders ); ?>><?php echo __( 'Use shipping address of eBay shipping center (ignore shipping fee)', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="2" <?php selected( 2, $wpl_process_multileg_orders ); ?>><?php echo __( 'Use shipping address of eBay shipping center (record shipping fee)', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'How international orders using eBay\'s Global Shipping Program are created in WooCommerce.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-remove_tracking_from_address" class="text_label">
		                            <?php echo __( 'Remove tracking data from address', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('eBay added tracking data to address line one of all eBay transactions. Set this to YES to have WP-Lister remove the tracking data if you don\'t need it.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-remove_tracking_from_address" name="wpl_e2e_remove_tracking_from_address" class=" required-entry select">
                                    <option value="0" <?php selected( $wpl_remove_tracking_from_address, 0 ); ?>><?php _e( 'No', 'wp-lister-for-ebay'); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="1" <?php selected( $wpl_remove_tracking_from_address, 1 ); ?>><?php _e( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'Enable this to remove the tracking data and the ABN code added by eBay to the shipping address.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-create_orders_without_email" class="text_label">
		                            <?php echo __( 'Leave email address empty', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('Enable this option to create WooCommerce orders without email addresses.<br><br>The email address provided by eBay are not real customer email address and should not be used for marketing purposes.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-create_orders_without_email" name="wpl_e2e_create_orders_without_email" class=" required-entry select">
                                    <option value="0" <?php selected( $wpl_create_orders_without_email, 0 ); ?>><?php _e( 'No', 'wp-lister-for-ebay'); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="1" <?php selected( $wpl_create_orders_without_email, 1 ); ?>><?php _e( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( ' Create orders without email addresses to make sure that no plugin sends marketing emails via eBay. ', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

						</div>
					</div>

                    <div class="postbox" id="OrderAttributionSettingsBox">
                        <h3 class="hndle"><span><?php echo __( 'WooCommerce Order Attribution Tracking', 'wp-lister-for-ebay' ) ?></span></h3>
                        <div class="inside">

                            <div class="wple-field">
		                        <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-order_utm_source" class="text_label">
			                        <?php echo __( 'UTM Source', 'wp-lister-for-ebay' ) ?>
                                </label>
                                <input type="text" name="wpl_e2e_order_utm_source" id="wpl-order_utm_source" value="<?php echo esc_attr($wpl_order_utm_source); ?>" class="text_input" />
                            </div>

                            <div class="wple-field">
		                        <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-order_utm_campaign" class="text_label">
			                        <?php echo __( 'UTM Campaign', 'wp-lister-for-ebay' ) ?>
                                </label>
                                <input type="text" name="wpl_e2e_order_utm_campaign" id="wpl-order_utm_campaign" value="<?php echo esc_attr($wpl_order_utm_campaign); ?>" class="text_input" />
                            </div>

                            <div class="wple-field">
		                        <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-order_utm_medium" class="text_label">
			                        <?php echo __( 'UTM Medium', 'wp-lister-for-ebay' ) ?>
                                </label>
                                <input type="text" name="wpl_e2e_order_utm_medium" id="wpl-order_utm_medium" value="<?php echo esc_attr($wpl_order_utm_medium); ?>" class="text_input" />
                            </div>

                        </div>
                    </div>

					<div class="postbox" id="OrderEmailSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Order Processing', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">
                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-auto_complete_sales" class="text_label">
		                            <?php echo __( 'Complete sale on eBay automatically', 'wp-lister-for-ebay' ); ?>
		                            <?php wplister_tooltip(__('This completes an eBay order with the default feedback text and shipping date set to today when the order status is changed to completed.<br>Not applicable if default new order status is <em>Completed</em>.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-auto_complete_sales" name="wpl_e2e_auto_complete_sales" class="required-entry select">
                                    <option value=""  <?php if ( $wpl_auto_complete_sales == ''  ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="1" <?php if ( $wpl_auto_complete_sales == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?> (<?php _e('recommended', 'wp-lister-for-ebay' ); ?>)</option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'Automatically complete the sale on eBay when an order is completed in WooCommerce.', 'wp-lister-for-ebay' ); ?>
		                            <?php if ( $wpl_auto_complete_sales && get_option( 'wplister_new_order_status', 'processing' ) == 'completed' ) : ?>
                                        <br><b><?php echo 'This option will have no effect as long as the default status for new orders is set to Completed!' ?></b>
		                            <?php endif; ?>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-complete_sale_in_background" class="text_label show-if-complete-sale">
		                            <?php echo __( 'Complete sale in the background', 'wp-lister-for-ebay' ); ?>
		                            <?php wplister_tooltip(__('Run the CompleteSale request in the background to prevent time outs for bulk order updates.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-complete_sale_in_background" name="wpl_e2e_complete_sale_in_background" class="required-entry select show-if-complete-sale">
                                    <option value="0"  <?php selected( $wpl_complete_sale_in_background, 0 ); ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="1" <?php selected( $wpl_complete_sale_in_background, 1 ); ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-default_feedback_text" class="text_label">
		                            <?php echo __( 'Default feedback text', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('Default feedback text to be used when auto complete option above is enabled. Leave empty to skip sending feedback.<br>Maximum length: 80 characters<br>Note: Seller feedback is always positive.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <input type="text" name="wpl_e2e_default_feedback_text" id="wpl-default_feedback_text" value="<?php echo esc_attr($wpl_default_feedback_text); ?>" maxlength="80" class="text_input" />
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'This is what will be sent as your seller feedback when sales are completed automatically.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

							<div class="wple-field">
								<?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-default_shipping_service" class="text_label">
									<?php echo __( 'Default order shipping service', 'wp-lister-for-ebay' ) ?>
									<?php wplister_tooltip(__('Default shipping service to be used when completing orders on eBay.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-default_shipping_service" name="wpl_e2e_default_shipping_service" class="required-entry select">
                                    <option value="" <?php selected( '', $wpl_default_shipping_service ); ?>><?php _e( 'No default provider', 'wp-lister-for-ebay' ); ?></option>
									<?php
                                    $providers = class_exists('WpLister_Order_MetaBox') ? array(WpLister_Order_MetaBox::getProviders()) : [];
									$shipping_providers = apply_filters_deprecated( 'wplister_available_shipping_providers', $providers, '2.8.4', 'wple_available_shipping_providers' );
									$shipping_providers = apply_filters( 'wple_available_shipping_providers', $shipping_providers );
									foreach ( $shipping_providers as $provider ):
										?>
                                        <option value="<?php echo esc_attr( $provider ); ?>" <?php selected( $provider, $wpl_default_shipping_service ); ?>><?php echo $provider; ?></option>
									<?php
									endforeach;
									?>
                                </select>
                                <p class="desc" style="display: block;">
									<?php echo __( 'Select the default shipping service to be used when completing orders on eBay.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-handle_ebay_refunds" class="text_label">
		                            <?php echo __( 'Automatically handle refunds', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('Enable this to record refund line items in WooCommerce when the original eBay order is refunded.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-handle_ebay_refunds" name="wpl_e2e_handle_ebay_refunds" class=" required-entry select">
                                    <option value="1" <?php selected( $wpl_handle_ebay_refunds, 1 ); ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="2" <?php selected( $wpl_handle_ebay_refunds, 2 ); ?>><?php echo __( 'Yes, including line taxes', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="0" <?php selected( $wpl_handle_ebay_refunds, 0 ); ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'Record refund line items in WooCommerce when the original eBay order is refunded.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

							<div class="wple-field">
								<?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-revert_stock_changes" class="text_label">
									<?php echo __( 'Revert stock changes on cancelled orders', 'wp-lister-for-ebay' ) ?>
									<?php wplister_tooltip(__('WP-Lister reverts all stock changes by default when an order gets cancelled on eBay. The exception to this feature is if the status of the WooCommerce order is already <code>refunded</code> or <code>cancelled</code>', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-revert_stock_changes" name="wpl_e2e_revert_stock_changes" class=" required-entry select">
                                    <option value="1" <?php selected( $wpl_revert_stock_changes, 1 ); ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="0" <?php selected( $wpl_revert_stock_changes, 0 ); ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <p class="desc" style="display: block;">
									<?php echo __( 'Disable this if you are overselling due to the stocks getting added back when cancelling an order on eBay', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-skip_foreign_site_orders" class="text_label">
		                            <?php echo __( 'Skip orders from foreign sites', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('If you use the same eBay account to sell on multiple sites, please enable this option to only process orders from the site selected in settings.<br>Otherwise you might have orders in the wrong currency as WooCommerce does not support multiple currencies.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-skip_foreign_site_orders" name="wpl_e2e_skip_foreign_site_orders" class=" required-entry select">
                                    <option value="0" <?php if ( $wpl_skip_foreign_site_orders != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="1" <?php if ( $wpl_skip_foreign_site_orders == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'Enable this option to process orders only from the selected eBay site.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-skip_foreign_item_orders" class="text_label">
		                            <?php echo __( 'Skip orders for foreign items', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('If you have items listed on eBay which do not exist in WP-Lister, you can enable this option to skip orders which do not contain any known order line items.<br><br>If enabled, orders which contain both known and foreign items will still be created in WooCommerce.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-skip_foreign_item_orders" name="wpl_e2e_skip_foreign_item_orders" class=" required-entry select">
                                    <option value="0" <?php if ( $wpl_skip_foreign_item_orders != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="1" <?php if ( $wpl_skip_foreign_item_orders == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'Enable this option to create orders in WooCommerce only for items which exist in WP-Lister.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-ignore_orders_before_ts" class="text_label">
		                            <?php echo __( 'Skip importing orders placed before', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('This is where WP-Lister remembers when it was connected to your eBay account. Orders placed before that date will be ignored.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <input type="text" name="wpl_e2e_ignore_orders_before_ts" id="wpl-ignore_orders_before_ts" value="<?php echo esc_attr($wpl_ignore_orders_before_ts) ? gmdate('Y-m-d H:i:s T',$wpl_ignore_orders_before_ts) : ''; ?>" class="text_input" />
                                <p class="desc" style="display: block;">
                                    Example: <?php echo gmdate('Y-m-d H:i:s T') ?>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-filter_orders_older_than" class="text_label">
		                            <?php echo __( 'Skip order updates for orders older than', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('eBay sometimes sends random updates for older orders and these orders get recorded', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-filter_orders_older_than" name="wpl_e2e_filter_orders_older_than" class=" required-entry select">
                                    <option value="0" <?php selected ( $wpl_filter_orders_older_than, 0 ); ?>><?php echo __( 'No, process all order updates', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="3" <?php selected ( $wpl_filter_orders_older_than, 3 ); ?>><?php echo __( '3 months', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="6" <?php selected ( $wpl_filter_orders_older_than, 6 ); ?>><?php echo __( '6 months', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="12" <?php selected ( $wpl_filter_orders_older_than, 12 ); ?>><?php echo __( '1 year', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="24" <?php selected ( $wpl_filter_orders_older_than, 24 ); ?>><?php echo __( '2 years', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="36" <?php selected ( $wpl_filter_orders_older_than, 36 ); ?>><?php echo __( '3 years', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'Updates for orders older than the selected option will be ignored', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-match_sales_by_sku" class="text_label">
		                            <?php echo __( 'Use SKU to match sold items', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('Normally when processing a new eBay sale, WP-Lister looks up the eBay Item ID in its database to find the right WooCommerce product to update the stock level. For most users this is the most reliable way of syncing eBay sales back to WooCommerce.<br><br>In some rare use cases, for example when listings are automatically translated and replicated across international eBay sites via a third party service like Webinterpret, the same product might be linked to multiple eBay Item IDs and WP-Lister would have to use the SKU instead to identify the right product in WooCommerce.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-match_sales_by_sku" name="wpl_e2e_match_sales_by_sku" class=" required-entry select">
                                    <option value="1" <?php selected( $wpl_match_sales_by_sku, 1 ); ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="0" <?php selected( $wpl_match_sales_by_sku, 0 ); ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'This option should only be enabled in rare use cases. Read the tooltip for more details.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-use_local_product_name_in_orders" class="text_label">
		                            <?php echo __( 'Use local product name/title in orders', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('Enable this option load and use local WooCommerce product names when creating orders from eBay.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-use_local_product_name_in_orders" name="wpl_e2e_use_local_product_name_in_orders" class=" required-entry select">
                                    <option value="0" <?php selected( $wpl_use_local_product_name_in_orders, 0 ); ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="1" <?php selected( $wpl_use_local_product_name_in_orders, 1 ); ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-create_customers" class="text_label">
		                            <?php echo __( 'Create customers', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('Enable this if you want WP-Lister to create eBay customers as WordPress users when creating orders.<br><br>Note: This is a rarely used option that should be kept disabled on most sites.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-create_customers" name="wpl_e2e_create_customers" class=" required-entry select">
                                    <option value="0" <?php if ( $wpl_create_customers != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="1" <?php if ( $wpl_create_customers == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'Enable this to create eBay customers as WordPress users when creating orders.', 'wp-lister-for-ebay' ); ?>
                                </p>
                            </div>

						</div>
					</div>

					<div class="postbox" id="OrderNotificationSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Order Notifications', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">
                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-overdue_orders_check" class="text_label">
		                            <?php echo __( 'Late shipment notification', 'wp-lister-for-ebay' ); ?>
		                            <?php wplister_tooltip(__('Enable this so WP-Lister keeps track of your order shipment deadlines and notifies you when a shipment is overdue.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-overdue_orders_check" name="wpl_e2e_overdue_orders_check" class="required-entry select">
                                    <option value="1" <?php selected( $wpl_overdue_orders_check, 1 ); ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="0" <?php selected( $wpl_overdue_orders_check, 0  ); ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'Enable this to display an error message when WP-Lister finds orders that are past their Ship By date.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

							<p>
								<?php echo __( 'WooCommerce sends out various notifications when an order status is changed.', 'wp-lister-for-ebay' ); ?>
								<?php echo __( 'You can disable these emails when eBay orders are created in WooCommerce.', 'wp-lister-for-ebay' ); ?>
							</p>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-disable_new_order_emails" class="text_label">
		                            <?php echo __( 'Disable New Order emails', 'wp-lister-for-ebay' ); ?>
		                            <?php wplister_tooltip(__('Disable New Order notifications being sent to the admin when an eBay order is created.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-disable_new_order_emails" name="wpl_e2e_disable_new_order_emails" class="required-entry select">
                                    <option value=""  <?php if ( $wpl_disable_new_order_emails == ''  ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="1" <?php if ( $wpl_disable_new_order_emails == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?> (<?php _e('recommended', 'wp-lister-for-ebay' ); ?>)</option>
                                </select>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-disable_processing_order_emails" class="text_label">
		                            <?php echo __( 'Disable Processing Order emails', 'wp-lister-for-ebay' ); ?>
		                            <?php wplister_tooltip(__('Disable email notifications being sent to the customer when an eBay order is created with status processing.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-disable_processing_order_emails" name="wpl_e2e_disable_processing_order_emails" class="required-entry select">
                                    <option value=""  <?php if ( $wpl_disable_processing_order_emails == ''  ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="1" <?php if ( $wpl_disable_processing_order_emails == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?> (<?php _e('recommended', 'wp-lister-for-ebay' ); ?>)</option>
                                </select>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-disable_completed_order_emails" class="text_label">
		                            <?php echo __( 'Disable Completed Order emails', 'wp-lister-for-ebay' ); ?>
		                            <?php wplister_tooltip(__('Disable email notifications being sent to the customer when an eBay order is created with status completed.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-disable_completed_order_emails" name="wpl_e2e_disable_completed_order_emails" class="required-entry select">
                                    <option value=""  <?php if ( $wpl_disable_completed_order_emails == ''  ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="1" <?php if ( $wpl_disable_completed_order_emails == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?> (<?php _e('recommended', 'wp-lister-for-ebay' ); ?>)</option>
                                </select>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-disable_changed_order_emails" class="text_label">
		                            <?php echo __( 'Disable emails on status change', 'wp-lister-for-ebay' ); ?>
		                            <?php wplister_tooltip(__('Disable email notifications being sent to the customer when the order status of an eBay order is changed manually.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-disable_changed_order_emails" name="wpl_e2e_disable_changed_order_emails" class="required-entry select">
                                    <option value=""  <?php if ( $wpl_disable_changed_order_emails == ''  ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="1" <?php if ( $wpl_disable_changed_order_emails == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?> (<?php _e('recommended', 'wp-lister-for-ebay' ); ?>)</option>
                                </select>
                            </div>

						</div>
					</div>

					<div class="postbox" id="OrderTaxSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Order Taxes', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">
                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-orders_autodetect_tax_rates" class="text_label">
		                            <?php echo __( 'Auto Detect Tax Rates', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('Automatically calculate line item taxes based on the purchased product\'s tax class.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-orders_autodetect_tax_rates" name="wpl_e2e_orders_autodetect_tax_rates" class="required-entry select">
                                    <option value="0" <?php selected( $wpl_orders_autodetect_tax_rates, 0 ); ?>><?php _e( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="1" <?php selected( $wpl_orders_autodetect_tax_rates, 1 ); ?>><?php _e( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'Automatically calculate line item taxes based on the purchased product\'s tax class.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-process_order_sales_tax_rate_id" class="text_label fixed_tax">
		                            <?php echo __( 'Sales tax rate', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('This tax rate will used for creating orders if the options below are enabled.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-process_order_sales_tax_rate_id" name="wpl_e2e_process_order_sales_tax_rate_id" class="required-entry select fixed_tax">
                                    <option value="">-- <?php echo __( 'no tax rate', 'wp-lister-for-ebay' ); ?> --</option>
		                            <?php foreach ($wpl_tax_rates as $rate) : ?>
                                        <option value="<?php echo esc_attr($rate->tax_rate_id) ?>" <?php if ( $wpl_process_order_sales_tax_rate_id == $rate->tax_rate_id ): ?>selected="selected"<?php endif; ?>><?php echo $rate->tax_rate_name ?> <?php echo $rate->tax_rate_class ? '('.$rate->tax_rate_class.')' : '' ?></option>
		                            <?php endforeach; ?>
                                </select>
                                <p class="desc fixed_tax" style="display: block;">
		                            <?php echo __( 'Select the tax rate to assign to created orders.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-process_order_tax_rate_id" class="text_label fixed_tax">
		                            <?php echo __( 'VAT tax rate', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('This tax rate will used for creating orders if the options below are enabled.<br><br>Note: If you do not select a WooCommerce tax rate here, WP-Lister will create all orders without applying any taxes.', 'wp-lister-for-ebay') ) ?>
                                </label>
                                <select id="wpl-option-process_order_tax_rate_id" name="wpl_e2e_process_order_tax_rate_id" class="required-entry select fixed_tax">
                                    <option value="">-- <?php echo __( 'no tax rate', 'wp-lister-for-ebay' ); ?> --</option>
		                            <?php foreach ($wpl_tax_rates as $rate) : ?>
                                        <option value="<?php echo esc_attr($rate->tax_rate_id) ?>" <?php if ( $wpl_process_order_tax_rate_id == $rate->tax_rate_id ): ?>selected="selected"<?php endif; ?>><?php echo $rate->tax_rate_name ?> <?php echo $rate->tax_rate_class ? '('.$rate->tax_rate_class.')' : '' ?></option>
		                            <?php endforeach; ?>
                                </select>
                                <p class="desc fixed_tax" style="display: block;">
		                            <?php echo __( 'Select the tax rate to assign to created orders.', 'wp-lister-for-ebay' ); ?> <?php echo __('Required to use the options below.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-text-orders_fixed_vat_rate" class="text_label fixed_tax">
		                            <?php echo __( 'VAT rate (percent)', 'wp-lister-for-ebay' ); ?>
		                            <?php wplister_tooltip(__('To apply VAT to created orders, enter the tax rate here.<br>Example: For 19% VAT enter "19".<br><br>This option applies to shipping fees and order items where no profile could be found. If a VAT rate is defined in your profile it will be used instead.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <input type="text" name="wpl_e2e_orders_fixed_vat_rate" id="wpl-text-orders_fixed_vat_rate" value="<?php echo esc_attr($wpl_orders_fixed_vat_rate); ?>" class="text_input fixed_tax" />
                                <p class="desc fixed_tax" style="display: block;">
		                            <?php echo __( 'Enter a default tax rate to be applied to order items and shipping fees.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-process_order_vat" class="text_label fixed_tax">
		                            <?php echo __( 'Create orders using profile VAT', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('With this option is enabled, WP-Lister will add a VAT tax row to created orders if the listing profile has VAT enabled.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-process_order_vat" name="wpl_e2e_process_order_vat" class=" required-entry select fixed_tax">
                                    <option value="0" <?php if ( $wpl_process_order_vat != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="1" <?php if ( $wpl_process_order_vat == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                </select>
                                <p class="desc fixed_tax" style="display: block;">
		                            <?php echo __( 'Process and add VAT to created orders if enabled in the listing profile.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-ebay_sales_tax_action" class="text_label">
		                            <?php _e( 'eBay Sales Tax action', 'wp-lister-for-ebay' ); ?>
		                            <?php wplister_tooltip(__('With eBay collecting taxes on behalf of the sellers, the order totals often become inaccurate. Use this setting to control how the Sales Tax should be handled in your WooCommerce orders.<br><br><b>Ignore:</b> Sales tax will be ignored and orders will be left as is.<br/><b>Remove:</b> Sales tax amount will be deducted from the order total.<br/><b>Record:</b> The sales tax will be recorded as an order tax.','wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-ebay_sales_tax_action" name="wpl_e2e_ebay_sales_tax_action" class="required-entry select">
                                    <option value="ignore" <?php selected( $wpl_ebay_sales_tax_action, 'ignore' ); ?>><?php _e( 'Ignore', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay'); ?>)</option>
                                    <option value="remove" <?php selected( $wpl_ebay_sales_tax_action, 'remove' ); ?>><?php _e( 'Remove', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="record" <?php selected( $wpl_ebay_sales_tax_action, 'record' ); ?>><?php _e( 'Record', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-ebay_ioss_action" class="text_label">
		                            <?php _e( 'eBay IOSS (Import One-Stop Shop) action', 'wp-lister-for-ebay' ); ?>
		                            <?php wplister_tooltip(__('Choose how you want the import fees collected by eBay to be handled by WP-Lister.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-ebay_ioss_action" name="wpl_e2e_ebay_ioss_action" class="required-entry select">
                                    <option value="ignore" <?php selected( $wpl_ebay_ioss_action, 'ignore' ); ?>><?php _e( 'Ignore', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay'); ?>)</option>
                                    <option value="record" <?php selected( $wpl_ebay_ioss_action, 'record' ); ?>><?php _e( 'Record as order fee', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-ebay_force_prices_include_tax" class="text_label">
		                            <?php _e( 'Prices Include Tax Override', 'wp-lister-for-ebay' ); ?>
		                            <?php wplister_tooltip(__('This is applicable if your order totals do not match or the line item totals are incorrect.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-ebay_force_prices_include_tax" name="wpl_e2e_ebay_force_prices_include_tax" class="required-entry select">
                                    <option value="ignore" <?php selected( $wpl_ebay_force_prices_include_tax, 'ignore' ); ?>><?php _e( 'Use WooCommerce Setting', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay'); ?>)</option>
                                    <option value="force_yes" <?php selected( $wpl_ebay_force_prices_include_tax, 'force_yes' ); ?>><?php _e( 'Prices inclusive of tax', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="force_no" <?php selected( $wpl_ebay_force_prices_include_tax, 'force_no' ); ?>><?php _e( 'Prices exclusive of tax', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'Choose if you need WP-Lister to include taxes in the prices coming from eBay.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-ebay_include_vat_in_order_total" class="text_label">
		                            <?php _e( 'Include Taxes in Order Total', 'wp-lister-for-ebay' ); ?>
		                            <?php wplister_tooltip(__('Set this to NO if your order totals have double the tax value.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-ebay_include_vat_in_order_total" name="wpl_e2e_ebay_include_vat_in_order_total" class="required-entry select">
                                    <option value="1" <?php selected( $wpl_ebay_include_vat_in_order_total, '1' ); ?>><?php _e( 'Yes', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay'); ?>)</option>
                                    <option value="0" <?php selected( $wpl_ebay_include_vat_in_order_total, '0' ); ?>><?php _e( 'No', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                            </div>


						</div>
					</div>

					<div class="postbox" id="InventorySettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Inventory Options', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-enable_out_of_stock_threshold" class="text_label">
		                            <?php echo __( 'Out Of Stock Threshold', 'wp-lister-for-ebay' ); ?>
		                            <?php wplister_tooltip(__('Enable this to automatically reduce the quantity sent to eBay by the value you entered as "Out Of Stock Threshold" in WooCommerce.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-enable_out_of_stock_threshold" name="wpl_e2e_enable_out_of_stock_threshold" class=" required-entry select">
                                    <option value="0" <?php selected( 0, $wpl_enable_out_of_stock_threshold ); ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="1" <?php selected( 1, $wpl_enable_out_of_stock_threshold ); ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'Enable this if you use the "Out Of Stock Threshold" option in WooCommerce.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-external_products_inventory" class="text_label">
		                            <?php echo __( 'External products inventory', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('Enable inventory management on external products. External products have no inventory by default in WooCommerce. <br>Note: This feature is still experimental.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-external_products_inventory" name="wpl_e2e_external_products_inventory" class=" required-entry select">
                                    <option value="0" <?php if ( $wpl_external_products_inventory != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="1" <?php if ( $wpl_external_products_inventory == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'Enable inventory management on external products.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-allow_backorders" class="text_label">
		                            <?php echo __( 'Ignore backorders', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('Since eBay relies on each item having a definitive quantity, allowing backorders for WooCommerce products can cause issues when the last item is sold. WP-Lister can force WooCommerce to mark an product as out of stock when the quantity reaches zero, even with backorders allowed.<br><br>It is recommended to leave this setting at the default.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-allow_backorders" name="wpl_e2e_option_allow_backorders" class="required-entry select">
                                    <option value="0" <?php if ( $wpl_option_allow_backorders != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="1" <?php if ( $wpl_option_allow_backorders == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'Should a product be marked as out of stock even when it has backorders enabled?', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

						</div>
					</div>

                    <div class="postbox" id="InventorySyncSettingsBox">
                        <h3 class="hndle"><span><?php echo __( 'Background Inventory Check', 'wp-lister-for-ebay' ) ?></span></h3>
                        <div class="inside">
                            <label for="wpl-option-run_background_inventory_check" class="text_label">
                                <?php echo __( 'Run background inventory checks', 'wp-lister-for-ebay' ) ?>
                                <?php wplister_tooltip(__('Download an inventory report from eBay regularly and compare inventory between eBay and your WooCommerce store.', 'wp-lister-for-ebay')) ?>
                            </label>
                            <select id="wpl-option-run_background_inventory_check" name="wpl_e2e_run_background_inventory_check" class="required-entry select">
                                <option value="1" <?php selected( $wpl_run_background_inventory_check, 1 ); ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                <option value="0" <?php selected( $wpl_run_background_inventory_check, 0 ); ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
                            </select>
                            <p class="desc" style="display: block;"><?php echo __( 'Download an inventory report from eBay regularly and compare inventory between eBay and your WooCommerce store.', 'wp-lister-for-ebay' ); ?><br></p>

                            <label for="wpl-option-inventory_check_frequency" class="text_label show-if-inventory-check">
                                <?php _e( 'Inventory check frequency', 'wp-lister-for-ebay' ); ?>
                            </label>
                            <select id="wpl-option-inventory_check_frequency" name="wpl_e2e_inventory_check_frequency" class="required-entry select show-if-inventory-check">
                                <?php
                                wple_render_pro_select_option( 1, __('Hourly', 'wp-lister-for-ebay'), $wpl_inventory_check_frequency == 1 );
                                wple_render_pro_select_option( 3, __('Every 3 hours', 'wp-lister-for-ebay'), $wpl_inventory_check_frequency == 3 );
                                wple_render_pro_select_option( 6, __('Every 6 hours', 'wp-lister-for-ebay'), $wpl_inventory_check_frequency == 6 );
                                wple_render_pro_select_option( 12, __('Every 12 hours', 'wp-lister-for-ebay'), $wpl_inventory_check_frequency == 12 );
                                ?>
                                <option value="24" <?php selected( $wpl_inventory_check_frequency, 24 ); ?>><?php _e( 'Every 24 hours', 'wp-lister-for-ebay' ); ?></option>
                            </select>
                            <p class="desc show-if-inventory-check" style="display: block;">
                                <?php
                                echo __( 'Set how often to download inventory reports to compare against your local inventory.', 'wp-lister-for-ebay' );

                                if ( WPLE_IS_LITE_VERSION ) {
                                    echo __( ' PRO users can set this to as often as once an hour', 'wp-lister-for-ebay' );
                                }
                                ?><br/>
                            </p>

                            <label for="wpl-option-inventory_check_notification_email" class="text_label show-if-inventory-check">
                                <?php _e( 'Send inventory reports to', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip( sprintf( __('Defaults to your admin email address (%s)', 'wp-lister-for-ebay'), get_bloginfo( 'admin_email' ) ) ); ?>
                            </label>
                            <input type="email" id="wpl-option_inventory_check_notification_email" name="wpl_e2e_inventory_check_notification_email" value="<?php esc_attr_e( $wpl_inventory_check_notification_email ); ?>" placeholder="<?php esc_attr_e( get_bloginfo( 'admin_email' ) ); ?>" class="text_input show-if-inventory-check" />
                            <p class="desc show-if-inventory-check" style="display: block;"><?php echo __( 'Set the email address where inventory reports will be sent when inventory inconsistencies are found.', 'wp-lister-for-ebay' ); ?><br>
                            </p>
						</div>
					</div>

					<div class="postbox" id="AttributeSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Units, Attributes and Item Specifics', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">

							<label for="wpl-send_weight_and_size" class="text_label">
								<?php echo __( 'Send weight and dimensions', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('By default, product weight and dimensions are only sent to eBay when calculated shipping is used.<br>Enable this option to send weight and dimensions for all listings.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-send_weight_and_size" name="wpl_e2e_send_weight_and_size" class=" required-entry select">
								<option value="default" <?php if ( $wpl_send_weight_and_size == 'default'): ?>selected="selected"<?php endif; ?>><?php echo __( 'Only for calculated shipping services', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="always"  <?php if ( $wpl_send_weight_and_size == 'always' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Always send weight and dimensions if set', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Enable this if eBay requires package weight or dimensions for flat shipping.', 'wp-lister-for-ebay' ); ?>
							</p>

							<label for="wpl-convert_dimensions" class="text_label">
								<?php echo __( 'Dimension Unit Conversion', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('WP-Lister assumes that you use the same dimension unit in WooCommerce as on eBay. Enable this to convert length, width and height from one unit to another.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-convert_dimensions" name="wpl_e2e_convert_dimensions" class="required-entry select">
								<option value=""  <?php if ( $wpl_convert_dimensions == ''  ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No conversion', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="cm-in" <?php if ( $wpl_convert_dimensions == 'cm-in' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Convert centimeters to inches', 'wp-lister-for-ebay' ); ?> ( cm &raquo; in )</option>
								<option value="in-cm" <?php if ( $wpl_convert_dimensions == 'in-cm' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Convert inches to centimeters', 'wp-lister-for-ebay' ); ?> ( in &raquo; cm )</option>
								<option value="mm-cm" <?php if ( $wpl_convert_dimensions == 'mm-cm' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Convert milimeters to centimeters', 'wp-lister-for-ebay' ); ?> ( mm &raquo; cm )</option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Convert length, width and height to the unit required by eBay.', 'wp-lister-for-ebay' ); ?>
							</p>

							<label for="wpl-convert_attributes_mode" class="text_label">
								<?php echo __( 'Use attributes as item specifics', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('The default is to convert all WooCommerce product attributes to item specifics on eBay.<br><br>If you disable this option, only the item specifics defined in your listing profile will be sent to eBay.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-convert_attributes_mode" name="wpl_e2e_convert_attributes_mode" class="required-entry select">
								<option value="all"    <?php if ( $wpl_convert_attributes_mode == 'all'    ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Convert all attributes to item specifics', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="single" <?php if ( $wpl_convert_attributes_mode == 'single' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Convert all attributes, but disable multi value attributes', 'wp-lister-for-ebay' ); ?></option>
								<option value="none"   <?php if ( $wpl_convert_attributes_mode == 'none'   ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Disabled', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Disable this option if you do not want all product attributes to be sent to eBay.', 'wp-lister-for-ebay' ); ?>
							</p>

							<label for="wpl-exclude_attributes" class="text_label">
								<?php echo __( 'Exclude attributes', 'wp-lister-for-ebay' ) ?>
                                <?php wplister_tooltip(__('If you want to hide certain product attributes from eBay enter their names separated by commas here.<br>Example: Brand,Size,MPN', 'wp-lister-for-ebay')) ?>
							</label>
							<input type="text" name="wpl_e2e_exclude_attributes" id="wpl-exclude_attributes" value="<?php echo esc_attr($wpl_exclude_attributes); ?>" class="text_input" />
							<p class="desc" style="display: block;">
								<?php echo __( 'Enter a comma separated list of product attributes to exclude from eBay.', 'wp-lister-for-ebay' ); ?><br>
							</p>

							<label for="wpl-exclude_variation_values" class="text_label">
								<?php echo __( 'Exclude variations', 'wp-lister-for-ebay' ) ?>
                                <?php wplister_tooltip(__('If you want to hide certain variations from eBay enter their attribute values separated by commas here.<br>Example: Brown,Blue,Orange', 'wp-lister-for-ebay')) ?>
							</label>
							<input type="text" name="wpl_e2e_exclude_variation_values" id="wpl-exclude_variation_values" value="<?php echo esc_attr($wpl_exclude_variation_values); ?>" class="text_input" />
							<p class="desc" style="display: block;">
								<?php echo __( 'Enter a comma separated list of variation attribute values to exclude from eBay.', 'wp-lister-for-ebay' ); ?><br>
							</p>

						</div>
					</div>


					<div class="postbox" id="OtherSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Misc Options', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">
                            <label for="wpl-enable_template_uploads" class="text_label">
								<?php echo __( 'Enable Template Uploads', 'wp-lister-for-ebay' ); ?>
								<?php wplister_tooltip(__('Item compatibility lists are currently only created for imported products. Future versions of WP-Lister Pro will allow to define compatibility lists in WooCommerce.', 'wp-lister-for-ebay')) ?>
                            </label>
                            <select id="wpl-enable_template_uploads" name="wpl_e2e_enable_template_uploads" class="required-entry select">
                                <option value="0"  <?php selected( $wpl_enable_template_uploads, 0 ); ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                <option value="1" <?php selected( $wpl_enable_template_uploads, 1 ); ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                            </select>
                            <p class="desc" style="display: block;">
								<?php echo __( 'Enabling template uploading could pose a security risk since it permits the uploading of PHP files to your web server. Keep this disabled if you do not utilize this feature.', 'wp-lister-for-ebay' ); ?>
                            </p>

							<label for="wpl-autofill_missing_gtin" class="text_label">
								<?php echo __( 'Missing Product Identifiers', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('eBay requires product identifiers (UPC/EAN) in selected categories starting 2015 - missing EANs/UPCs can cause the revise process to fail.<br><br>If your products do not have either UPCs or EANs, please use this option.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-autofill_missing_gtin" name="wpl_e2e_autofill_missing_gtin" class="required-entry select">
								<option value=""  <?php if ( $wpl_autofill_missing_gtin == ''  ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Do nothing', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="upc" <?php if ( $wpl_autofill_missing_gtin == 'upc' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'If UPC is empty use "Does not apply" instead', 'wp-lister-for-ebay' ); ?></option>
								<option value="ean" <?php if ( $wpl_autofill_missing_gtin == 'ean' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'If EAN is empty use "Does not apply" instead', 'wp-lister-for-ebay' ); ?></option>
								<option value="both" <?php if ( $wpl_autofill_missing_gtin == 'both' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'If both fields are empty use "Does not apply" instead', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Enable this option if your products do not have UPCs or EANs.', 'wp-lister-for-ebay' ); ?>
							</p>

							<label for="wpl-option-local_timezone" class="text_label">
								<?php echo __( 'Local timezone', 'wp-lister-for-ebay' ) ?>
                                <?php wplister_tooltip(__('This is currently used to convert the order creation date from UTC to local time.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-option-local_timezone" name="wpl_e2e_option_local_timezone" class="required-entry select">
								<option value="">-- <?php echo __( 'no timezone selected', 'wp-lister-for-ebay' ); ?> --</option>
								<?php foreach ($wpl_timezones as $tz_id => $tz_name) : ?>
									<option value="<?php echo esc_attr($tz_id) ?>" <?php if ( $wpl_option_local_timezone == $tz_id ): ?>selected="selected"<?php endif; ?>><?php echo $tz_name ?></option>
								<?php endforeach; ?>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Select your local timezone.', 'wp-lister-for-ebay' ); ?><br>
							</p>

							<label for="wpl-enable_item_compat_tab" class="text_label">
								<?php echo __( 'Enable Item Compatibility tab', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Item compatibility lists are currently only created for imported products. Future versions of WP-Lister Pro will allow to define compatibility lists in WooCommerce.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-enable_item_compat_tab" name="wpl_e2e_enable_item_compat_tab" class="required-entry select">
								<option value=""  <?php if ( $wpl_enable_item_compat_tab == ''  ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
								<option value="1" <?php if ( $wpl_enable_item_compat_tab == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Show eBay Item Compatibility List as new tab on single product page.', 'wp-lister-for-ebay' ); ?>
							</p>

                            <label for="wpl-display-item-condition" class="text_label">
                                <?php echo __( 'Display Item Condition', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('If you imported your products from your eBay store using the Import from eBay plugin, you can enable this setting to display the conditions and condition descriptions from eBay.', 'wp-lister-for-ebay')) ?>
                            </label>
                            <select id="wpl-display-item-condition" name="wpl_e2e_display_item_condition" class="required-entry select">
                                <option value="0" <?php selected( $wpl_display_item_condition, 0  ); ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                <option value="1" <?php selected( $wpl_display_item_condition, 1 ); ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                            </select>
                            <p class="desc" style="display: block;">
                                <?php echo __( 'Display the item conditions imported from eBay in the Additional Information tab on single product page.', 'wp-lister-for-ebay' ); ?>
                            </p>

							<label for="wpl-disable_sale_price" class="text_label">
								<?php echo __( 'Use sale price', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Set this to No if you want your sale prices to be ignored. You can still use a relative profile price to increase your prices by a percentage.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-disable_sale_price" name="wpl_e2e_disable_sale_price" class="required-entry select">
								<option value="0" <?php if ( $wpl_disable_sale_price != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="1" <?php if ( $wpl_disable_sale_price == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Should sale prices be used automatically on eBay?', 'wp-lister-for-ebay' ); ?><br>
							</p>

							<label for="wpl-apply_profile_to_ebay_price" class="text_label">
								<?php echo __( 'Apply profile to eBay price', 'wp-lister-for-ebay' ); ?>
								<?php wplister_tooltip(__('By default, a custom eBay price (set on the product level) takes precendence over any other prices, including regular prices, sale prices and prices in your listing profile.<br><br>So if you use a profile to reduce all prices by 10% - using the price modifier "-10%" - and you want this to be applied to custom eBay prices as well, please enable this option.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-apply_profile_to_ebay_price" name="wpl_e2e_apply_profile_to_ebay_price" class="required-entry select">
								<option value="0" <?php selected( $wpl_apply_profile_to_ebay_price, 0 ); ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="1" <?php selected( $wpl_apply_profile_to_ebay_price, 1 ); ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Enable this to allow your listing profile to modify a custom eBay price set on the product level.', 'wp-lister-for-ebay' ); ?><br>
							</p>

                            <label for="wpl-description_blacklist" class="text_label">
                                <?php echo __( 'Product description blacklist', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('One entry per line. Compare the product description against this blacklist to remove all copyrighted text.', 'wp-lister-for-ebay')) ?>
                            </label>
                            <textarea id="wpl-description_blacklist" name="wpl_e2e_description_blacklist" rows="5"><?php echo esc_attr($wpl_description_blacklist); ?></textarea>
                            <p class="desc" style="display: block;">
                                <?php echo __( 'Run the product description through this list and all matching lines will be removed prior to publishing on eBay.', 'wp-lister-for-ebay' ); ?><br>
                            </p>

						</div>
					</div>

					<div class="postbox" id="DeprecatedSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Deprecated Options', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">

							<p>
								<?php echo __( 'These options can be ignored and should not be used anymore.', 'wp-lister-for-ebay' ); ?>
							</p>

							<label for="wpl-auto_update_ended_items" class="text_label">
								<?php echo __( 'Auto update ended items', 'wp-lister-for-ebay' ) ?>
                                <?php wplister_tooltip(__('This can be helpful if you manually relisted items on eBay - which is not recommended.<br><br>We recommend against using this option as it might cause performance issues and other unexpected results.<br><br>If you experience any problems with this option enabled, please disable it again and see if it solves the problem.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-auto_update_ended_items" name="wpl_e2e_auto_update_ended_items" class="required-entry select">
								<option value="0" <?php if ( $wpl_auto_update_ended_items != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="1" <?php if ( $wpl_auto_update_ended_items == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?> (<?php _e('not recommended', 'wp-lister-for-ebay' ); ?>)</option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Automatically update item details from eBay when a listing has ended.', 'wp-lister-for-ebay' ); ?> (experimental!)
							</p>

							<div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-create_incomplete_orders" class="text_label">
		                            <?php echo __( 'Create orders when', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('It is highly recommended to create orders when the eBay purchase has been completed, especially if you have enabled the <i>Combined Invoices</i> option on eBay, setting this option to <i>immediately</i> can cause duplicates or incomplete orders!<br><br>Also, if you set this to <i>immediately</i>, WP-Lister could possibly create WooCommerce orders for cancelled eBay orders.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-create_incomplete_orders" name="wpl_e2e_create_incomplete_orders" class=" required-entry select">
                                    <option value="0" <?php if ( $wpl_create_incomplete_orders != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'When purchase has been completed', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="1" <?php if ( $wpl_create_incomplete_orders == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Immediately', 'wp-lister-for-ebay' ); ?> (<?php _e('Not recommended', 'wp-lister-for-ebay' ); ?>!)</option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'Warning: Please leave this at the default unless instructed otherwise by support.', 'wp-lister-for-ebay' ); ?>
		                            <?php if ( ( $wpl_create_incomplete_orders == '1' ) && ( get_option( 'woocommerce_hold_stock_minutes',false) ) ): ?>
                                        <span style="color:#C00">
										Warning: WooCommerce is set to cancel incomplete orders after <?php echo get_option( 'woocommerce_hold_stock_minutes') ?> minutes.
									</span>
		                            <?php endif; ?>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-sync_incomplete_orders" class="text_label">
		                            <?php echo __( 'Reduce stock when', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('It is highly recommended to update stock levels when the eBay purchase has been completed, especially if you have enabled the <i>Combined Invoices</i> option on eBay, as setting this option to <i>immediately</i> can cause discrepancies in stock levels!', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-sync_incomplete_orders" name="wpl_e2e_sync_incomplete_orders" class=" required-entry select">
                                    <option value="0" <?php if ( $wpl_sync_incomplete_orders != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'When purchase has been completed', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="1" <?php if ( $wpl_sync_incomplete_orders == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Immediately', 'wp-lister-for-ebay' ); ?> (<?php _e('Not recommended', 'wp-lister-for-ebay' ); ?>!)</option>
                                </select>
                                <p class="desc" style="display: block;">
		                            <?php echo __( 'Warning: Please leave this at the default unless instructed otherwise by support.', 'wp-lister-for-ebay' ); ?>
                                </p>
                            </div>

						</div>
					</div>

					<?php do_action( 'wple_after_advanced_settings' ) ?>


				<?php if ( ( is_multisite() ) && ( is_main_site() ) ) : ?>
				<p>
					<b>Warning:</b> Deactivating WP-Lister on a multisite network will remove all settings and data from all sites.
				</p>
				<?php endif; ?>


				</div> <!-- .meta-box-sortables -->
			</div> <!-- #postbox-container-1 -->


		</div> <!-- #post-body -->
		<br class="clear">
	</div> <!-- #poststuff -->

	</form>

    <script type="text/javascript">
        jQuery( document ).ready( function($) {
            $('#wpl-option-run_background_inventory_check').change(function () {
                if ($('#wpl-option-run_background_inventory_check').val() != 1) {
                    $('.show-if-inventory-check').hide();
                } else {
                    $('.show-if-inventory-check').show();
                }
            }).change();

            $('#wpl-auto_complete_sales').change(function () {
                if ($('#wpl-auto_complete_sales').val() != 1) {
                    $('.show-if-complete-sale').hide();
                } else {
                    $('.show-if-complete-sale').show();
                }
            }).change();

            // Toggle Fixed Tax elements
            $('#wpl-option-orders_autodetect_tax_rates').change(function() {

                // initially hide the elements
                $('.fixed_tax').hide();

                if ( $(this).val() == 0 ) {
                    $('.fixed_tax').show();
                }
            }).change();

            // jQuery("select.wple_chosen_select").chosen();
            jQuery("select.wple_chosen_select").selectWoo();
        });
    </script>

</div>
