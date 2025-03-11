<style type="text/css">
    @import "<?php echo WPLE_PLUGIN_URL; ?>css/lite.css";

	#poststuff #side-sortables .postbox input.text_input,
	#poststuff #side-sortables .postbox select.select {
	    width: 35%;
	}
	#poststuff #side-sortables .postbox label.text_label {
	    width: 60%;
	}

	#poststuff #side-sortables .postbox .inside p.desc {
		margin-left: 2%;
	}

	/* backwards compatibility to WP 3.3 */
	#poststuff #post-body.columns-2 {
	    margin-right: 300px;
	}
	#poststuff #post-body {
	    padding: 0;
	}
	#post-body.columns-2 #postbox-container-1 {
	    float: right;
	    margin-right: -300px;
	    width: 280px;
	}
	#poststuff .postbox-container {
	    width: 100%;
	}
	#major-publishing-actions {
	    border-top: 1px solid #F5F5F5;
	    clear: both;
	    margin-top: -2px;
	    padding: 10px 10px 8px;
	}
	#post-body .misc-pub-section {
	    max-width: 100%;
	    border-right: none;
	}

    #post-body table#sizemap {
        width: 100%;
        margin-top:20px;
    }
    #post-body table td {
        text-align: center;
    }

</style>




					<!-- first sidebox -->
					<div class="postbox" id="submitdiv">
						<!--<div title="Click to toggle" class="handlediv"><br></div>-->
						<h3 class="hndle"><span><?php echo __( 'Update', 'wp-lister-for-ebay' ); ?></span></h3>
						<div class="inside">

							<div id="submitpost" class="submitbox">

								<div id="misc-publishing-actions">
									<div class="misc-pub-section">
									<!-- optional save and apply to all prepared listings already using this profile -->
									<?php
                                    $activated_checkboxes = apply_filters( 'wple_activate_profile_update_checkboxes', true );
                                    if ( $wpl_total_listings_count > get_option( 'wplister_apply_profile_batch_size', 1000 ) ):
                                    ?>

										<input type="hidden" name="wple_delay_profile_application" value="yes" />
										<?php $_GET['return_to'] = 'listings'; ?>

										<p><?php printf( __( 'There are %s prepared, %s verified and %s published items using this profile.', 'wp-lister-for-ebay' ), $wpl_prepared_listings_count, $wpl_verified_listings_count, $wpl_published_listings_count ) ?></p>

										<input <?php if ($activated_checkboxes !== false) echo 'disabled'; ?> type="checkbox" name="wpl_e2e_apply_changes_to_all_prepared" value="yes" id="apply_changes_to_all_prepared" <?php if ( $activated_checkboxes !== false && $wpl_prepared_listings_count ) echo 'checked' ?>/>
										<label for="apply_changes_to_all_prepared"><?php printf( __( 'update %s prepared items', 'wp-lister-for-ebay' ), $wpl_prepared_listings_count ) ?></label>
										<br class="clear" />

										<input  <?php if ($activated_checkboxes !== false) echo 'disabled'; ?> type="checkbox" name="wpl_e2e_apply_changes_to_all_verified" value="yes" id="apply_changes_to_all_verified" <?php if ($activated_checkboxes !== false && $wpl_verified_listings_count) echo 'checked' ?>/>
										<label for="apply_changes_to_all_verified"><?php printf( __( 'update %s verified items', 'wp-lister-for-ebay' ), ($wpl_verified_listings_count) ) ?></label>
										<br class="clear" />

										<input  <?php if ($activated_checkboxes !== false) echo 'disabled'; ?> type="checkbox" name="wpl_e2e_apply_changes_to_all_published" value="yes" id="apply_changes_to_all_published" <?php if ($activated_checkboxes !== false && $wpl_published_listings_count) echo 'checked' ?>/>
										<label for="apply_changes_to_all_published"><?php printf( __( 'update %s published items', 'wp-lister-for-ebay' ), ($wpl_published_listings_count) ) ?></label>
										<br class="clear" />

										<input  <?php if ($activated_checkboxes !== false) echo 'disabled'; ?> type="checkbox" id="wpl_e2e_apply_changes_to_all_ended" name="wpl_e2e_apply_changes_to_all_ended" value="yes" id="apply_changes_to_all_ended" <?php #if ($wpl_ended_listings_count) echo 'checked' ?>/>
										<label for="apply_changes_to_all_ended"><?php printf( __( 'update %s ended items', 'wp-lister-for-ebay' ), ($wpl_ended_listings_count) ) ?></label>
										<br class="clear" />

										<!-- <input disabled type="checkbox" name="wpl_e2e_apply_changes_to_all_locked" value="yes" id="apply_changes_to_all_locked" <?php #if ($wpl_locked_listings_count) echo 'checked' ?>/> -->
										<!-- <label for="apply_changes_to_all_locked"><?php printf( __( 'update %s locked items', 'wp-lister-for-ebay' ), ($wpl_locked_listings_count) ) ?></label> -->

										<p><?php printf( __( 'This profile will be applied to %s listings.', 'wp-lister-for-ebay' ), $wpl_total_listings_count ) ?></p>

									<?php elseif ( $wpl_total_listings_count || $wpl_ended_listings_count ): ?>
										<p><?php printf( __( 'There are %s prepared, %s verified and %s published items using this profile.', 'wp-lister-for-ebay' ), $wpl_prepared_listings_count, $wpl_verified_listings_count, $wpl_published_listings_count ) ?></p>

										<input type="checkbox" name="wpl_e2e_apply_changes_to_all_prepared" value="yes" id="apply_changes_to_all_prepared" <?php if ( $activated_checkboxes !== false && $wpl_prepared_listings_count ) echo 'checked' ?>/>
										<label for="apply_changes_to_all_prepared"><?php printf( __( 'update %s prepared items', 'wp-lister-for-ebay' ), $wpl_prepared_listings_count ) ?></label>
										<br class="clear" />

										<input type="checkbox" name="wpl_e2e_apply_changes_to_all_verified" value="yes" id="apply_changes_to_all_verified" <?php if ( $activated_checkboxes !== false && $wpl_verified_listings_count) echo 'checked' ?>/>
										<label for="apply_changes_to_all_verified"><?php printf( __( 'update %s verified items', 'wp-lister-for-ebay' ), ($wpl_verified_listings_count) ) ?></label>
										<br class="clear" />

										<input type="checkbox" name="wpl_e2e_apply_changes_to_all_published" value="yes" id="apply_changes_to_all_published" <?php if ( $activated_checkboxes !== false && $wpl_published_listings_count) echo 'checked' ?>/>
										<label for="apply_changes_to_all_published"><?php printf( __( 'update %s published items', 'wp-lister-for-ebay' ), ($wpl_published_listings_count) ) ?></label>
										<br class="clear" />

										<input type="checkbox" id="wpl_e2e_apply_changes_to_all_ended" name="wpl_e2e_apply_changes_to_all_ended" value="yes" id="apply_changes_to_all_ended" <?php #if ($wpl_ended_listings_count) echo 'checked' ?>/>
										<label for="apply_changes_to_all_ended"><?php printf( __( 'update %s ended items', 'wp-lister-for-ebay' ), ($wpl_ended_listings_count) ) ?></label>
										<br class="clear" />

										<input type="checkbox" name="wpl_e2e_apply_changes_to_all_locked" value="yes" id="apply_changes_to_all_locked" <?php #if ($wpl_locked_listings_count) echo 'checked' ?>/>
										<label for="apply_changes_to_all_locked"><?php printf( __( 'update %s locked items', 'wp-lister-for-ebay' ), ($wpl_locked_listings_count) ) ?></label>

									<?php else: ?>
										<p>There are no prepared items using this profile.</p>
									<?php endif; ?>
									</div>
								</div>

								<div id="major-publishing-actions">
									<div id="publishing-action">
										<input type="hidden" name="action" value="save_listing_profile" />
										<input type="hidden" name="wpl_e2e_profile_id" value="<?php echo esc_attr( $wpl_item['profile_id'] ); ?>" />
										<input type="hidden" name="return_to" value="<?php if ( !empty( $_GET['return_to'] ) ) echo esc_attr( sanitize_key( $_GET['return_to'] ) ) ; ?>" />
										<input type="hidden" name="listing_status" value="<?php if ( !empty( $_GET['listing_status'] ) ) echo esc_attr( sanitize_key($_GET['listing_status']) ); ?>" />
										<input type="hidden" name="s" value="<?php if ( !empty( $_GET['s'] ) ) echo esc_attr($_GET['s']); ?>" />
                                        <?php wp_nonce_field( 'wplister_save_profile' ); ?>
										<input type="submit" value="<?php echo __( 'Save profile', 'wp-lister-for-ebay' ); ?>" id="publish" class="button-primary" name="save">
									</div>
									<div class="clear"></div>
								</div>

							</div>

						</div>
					</div>


					<div class="postbox" id="LocationSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Location and Taxes', 'wp-lister-for-ebay' ); ?></span></h3>
						<div class="inside">

							<label for="wpl-text-location" class="text_label">
								<?php echo __( 'Location', 'wp-lister-for-ebay' ); ?> *
                                <?php wplister_tooltip(__('The geographical location of the item to be displayed on eBay listing pages.<br>If you do not specify Location, you <em>must</em> specify a Postal Code.', 'wp-lister-for-ebay')) ?>
							</label>
							<input type="text" name="wpl_e2e_location" id="wpl-text-location" value="<?php echo esc_attr( $item_details['location'] ?? '' ); ?>" class="text_input" />
							<br class="clear" />

							<label for="wpl-text-postcode" class="text_label">
								<?php echo __( 'Postal code', 'wp-lister-for-ebay' ); ?> *
                                <?php wplister_tooltip(__('Postal code of the place where the item is located. This value is used for proximity searches.<br>If you do not specify Postal Code, you <em>must</em> specify a Location.', 'wp-lister-for-ebay')) ?>
							</label>
							<input type="text" name="wpl_e2e_postcode" id="wpl-text-postcode" value="<?php echo esc_attr( $item_details['postcode'] ?? '' ); ?>" class="text_input" />
							<br class="clear" />

							<label for="wpl-text-country" class="text_label">
								<?php echo __( 'Country', 'wp-lister-for-ebay' ); ?> *
                                <?php wplister_tooltip(__('Select the country where your products are located and shipped from.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-text-country" name="wpl_e2e_country" title="Country" class=" required-entry select">
								<option value="">-- <?php echo __( 'Please select', 'wp-lister-for-ebay' ); ?> --</option>
								<?php foreach ($wpl_countries as $country => $desc) : ?>
									<option value="<?php echo $country ?>" 
										<?php if ( $item_details['country'] == $country ) : ?>
											selected="selected"
										<?php endif; ?>
										><?php echo $desc ?></option>
								<?php endforeach; ?>
							</select>
							<br class="clear" />


							<label for="wpl-text-currency" class="text_label">
								<?php echo __( 'Currency', 'wp-lister-for-ebay' ); ?> *
                                <?php wplister_tooltip(__('Select the currency you want to use to list your products on eBay.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-text-currency" name="wpl_e2e_currency" title="Currency" class=" required-entry select">
								<option value="USD" <?php if ( $item_details['currency'] == 'USD' ): ?>selected="selected"<?php endif; ?>>USD</option>
								<option value="CAD" <?php if ( $item_details['currency'] == 'CAD' ): ?>selected="selected"<?php endif; ?>>CAD</option>
								<option value="EUR" <?php if ( $item_details['currency'] == 'EUR' ): ?>selected="selected"<?php endif; ?>>EUR</option>
								<option value="GBP" <?php if ( $item_details['currency'] == 'GBP' ): ?>selected="selected"<?php endif; ?>>GBP</option>
								<option value="SEK" <?php if ( $item_details['currency'] == 'SEK' ): ?>selected="selected"<?php endif; ?>>SEK</option>
								<option value="CHF" <?php if ( $item_details['currency'] == 'CHF' ): ?>selected="selected"<?php endif; ?>>CHF</option>
								<option value="AUD" <?php if ( $item_details['currency'] == 'AUD' ): ?>selected="selected"<?php endif; ?>>AUD</option>
								<option value="HKD" <?php if ( $item_details['currency'] == 'HKD' ): ?>selected="selected"<?php endif; ?>>HKD</option>
								<option value="INR" <?php if ( $item_details['currency'] == 'INR' ): ?>selected="selected"<?php endif; ?>>INR</option>
								<option value="MYR" <?php if ( $item_details['currency'] == 'MYR' ): ?>selected="selected"<?php endif; ?>>MYR</option>
								<option value="PHP" <?php if ( $item_details['currency'] == 'PHP' ): ?>selected="selected"<?php endif; ?>>PHP</option>
								<option value="PLN" <?php if ( $item_details['currency'] == 'PLN' ): ?>selected="selected"<?php endif; ?>>PLN</option>
								<option value="SGD" <?php if ( $item_details['currency'] == 'SGD' ): ?>selected="selected"<?php endif; ?>>SGD</option>
							</select>
							<br class="clear" />

							<label for="wpl-text-tax_mode" class="text_label">
								<?php echo __( 'Taxes', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Select if you want a fixed tax rate (VAT), use the Sales Tax Table from your eBay account, or use no taxes at all.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-text-tax_mode" name="wpl_e2e_tax_mode" title="Taxes" class=" required-entry select">
								<!--<option value="">-- <?php echo __( 'Please select', 'wp-lister-for-ebay' ); ?> --</option>-->
								<option value="none" <?php if ( $item_details['tax_mode'] == 'none' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'no taxes', 'wp-lister-for-ebay' ); ?></option>
								<option value="fix" <?php if ( $item_details['tax_mode'] == 'fix' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'fixed tax rate', 'wp-lister-for-ebay' ); ?> (VAT)</option>
								<option value="ebay_table" <?php if ( $item_details['tax_mode'] == 'ebay_table' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'use Sales Tax Table', 'wp-lister-for-ebay' ); ?></option>
								<!--<option value="product" <?php if ( $item_details['tax_mode'] == 'product' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'apply product tax', 'wp-lister-for-ebay' ); ?> (beta!)</option>-->
							</select>
							<br class="clear" />

							<div id="tax_mode_fixed_options_container">
								<label for="wpl-text-vat_percent" class="text_label">
									<?php echo __( 'Tax rate (VAT)', 'wp-lister-for-ebay' ); ?>
                                	<?php wplister_tooltip(__('<b>VAT rate for the item.</b><br>Since VAT rates vary depending on the item and on the user\'s country of residence, a seller is responsible for entering the correct VAT rate; it is not calculated by eBay. To specify a VAT, a seller must have a VAT-ID registered with eBay and must be listing the item on a VAT-enabled site.', 'wp-lister-for-ebay')) ?>
								</label>
								<input type="text" name="wpl_e2e_vat_percent" id="wpl-text-vat_percent" value="<?php echo esc_attr( $item_details['vat_percent'] ?? '' ); ?>" class="text_input" />
								<br class="clear" />
							</div>

						</div>
					</div>


					<div class="postbox" id="QuantityBox">
						<h3 class="hndle"><span><?php echo __( 'Quantity Override', 'wp-lister-for-ebay' ); ?></span></h3>
						<div class="inside">

							<?php $custom_quantity_enabled = ( @$item_details['quantity'] || @$item_details['max_quantity'] ) ? 1 : 0; ?>
							<label for="wpl-custom_quantity_enabled" class="text_label">
								<?php echo __( 'Quantity', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('By default WP-Lister uses the current stock level quantity from WooCommerce and keeps it in sync with eBay automatically.<br><br>If you wish to use a custom quantity, you can do so - but please keep in mind that syncing inventory and sales might not work as expected!', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-custom_quantity_enabled" name="wpl_e2e_custom_quantity_enabled" class="select">
								<option value=""  <?php if ( $custom_quantity_enabled != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'auto sync', 'wp-lister-for-ebay' ); ?></option>
								<option value="1" <?php if ( $custom_quantity_enabled == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'custom qty', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<br class="clear" />

							<div id="wpl-custom_quantity_container">

								<label for="wpl-text-max-max_quantity" class="text_label">
									<?php #echo __( 'Maximum quantity', 'wp-lister-for-ebay' ); ?>
									<?php echo __( 'Max. quantity', 'wp-lister-for-ebay' ); ?>
	                                <?php wplister_tooltip(__('If you wish to limit your available stock on eBay, you can set a maximum quantity here.<br><br>Use this where you want to create demand or when you have listing limitation. This option will not limit fixed quantities.', 'wp-lister-for-ebay')) ?>
								</label>
								<input type="number" name="wpl_e2e_max_quantity" id="wpl-text-max_quantity" value="<?php echo esc_attr( $item_details['max_quantity'] ?? '' ); ?>" class="text_input" placeholder="0" step="any" min="0" />
								<br class="clear" />
								
								<label for="wpl-text-quantity" class="text_label">
									<?php echo __( 'Fixed quantity', 'wp-lister-for-ebay' ); ?>
	                                <?php wplister_tooltip(__('If you do not use stock management in WooCommerce at all, you can set a fixed quantity for your products here.<br><br>Use this with care - WP-Lister Pro can\'t sync the inventory properly when a fixed quantity is set.', 'wp-lister-for-ebay')) ?>
								</label>
								<input type="number" name="wpl_e2e_quantity" id="wpl-text-quantity" value="<?php echo esc_attr( $item_details['quantity'] ?? '' ); ?>" class="text_input" placeholder="0" step="any" min="0" />
								<br class="clear" />


                                <label for="wpl-text-restrict_fixed_quantity" class="text_label">
                                    <?php echo __( 'Fixed quantity rules', 'wp-lister-for-ebay' ); ?>
                                    <?php wplister_tooltip(__('Set how the fixed quantity is applied.<br><br><b>All</b> - Apply to all listings<br><br><b>In Stock</b> - Only apply to products marked as In Stock<br><br><b>Unmanaged Stock</b> - Only apply to products with Managed Stock disabled', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-restrict_fixed_quantity" name="wpl_e2e_restrict_fixed_quantity" class="select">
                                    <option value=""  <?php if ( !isset($item_details['restrict_fixed_quantity']) || $item_details['restrict_fixed_quantity'] == '' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'All', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="1" <?php if ( isset($item_details['restrict_fixed_quantity']) && $item_details['restrict_fixed_quantity'] == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'In Stock', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="2" <?php if ( isset($item_details['restrict_fixed_quantity']) && $item_details['restrict_fixed_quantity'] == '2' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Unmanaged Stock', 'wp-lister-for-ebay' ); ?></option>
                                </select>

                                <br class="clear" />

                                <p class="x-desc" style="display: block;">
                                    <?php #echo __( 'Leave this empty to list all available items.', 'wp-lister-for-ebay' ); ?>
                                    <?php #echo __( '"Fixed quantity" should be empty to use inventory sync, "Maximum quantity" is effective only with inventory sync.', 'wp-lister-for-ebay' ); ?>
                                    <?php #echo __( 'Custom quantities do not apply to locked listings.', 'wp-lister-for-ebay' ); // they do as of 2.0.9.15 ?>
                                    <?php echo __( 'Leave this empty if you wish to synchronize inventory and sales!', 'wp-lister-for-ebay' ); ?>
                                </p>

                                <div class="wple-field">
                                    <?php  wple_maybe_display_pro_overlay(); ?>
                                    <label for="wpl-auto_replenish" class="text_label">
		                                <?php echo __( 'Auto replenish', 'wp-lister-for-ebay' ); ?>
		                                <?php wplister_tooltip(__('If you set a maxmimum quantity, WP-Lister Pro can automatically replenish the stock level on eBay when a unit is sold.', 'wp-lister-for-ebay')) ?>
                                    </label>
                                    <select id="wpl-auto_replenish" name="wpl_e2e_auto_replenish" class="select">
                                        <option value=""  <?php if ( !isset($item_details['auto_replenish']) || $item_details['auto_replenish'] != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
                                        <option value="1" <?php if ( isset($item_details['auto_replenish']) && $item_details['auto_replenish'] == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                                    </select>
                                    <br class="clear" />
                                </div>

							</div>
	
						</div>
					</div>


					<div class="postbox" id="TemplatesBox">
						<h3 class="hndle"><span><?php echo __( 'Template', 'wp-lister-for-ebay' ); ?></span></h3>
						<div class="inside">
							<?php foreach ($wpl_template_files as $tpl) : ?>
								<?php
									$tpl_name = $tpl['template_name'];
									$tpl_path = $tpl['template_path'];
									$checked  = ( isset($item_details['template'] ) && $item_details['template'] == $tpl_path ) ? 'checked="checked"' : '';
								?>

								<input type="radio" value="<?php echo $tpl_path ?>" id="template-<?php echo basename($tpl_path) ?>" name="wpl_e2e_template" class="post-format" <?php echo $checked ?> > 
								<label for="template-<?php echo basename($tpl_path) ?>"><?php echo $tpl_name ?></label><br>

							<?php endforeach; ?>							
						</div>
					</div>

					<?php if ( WPLE()->multi_account ) : ?>
					<div class="postbox" id="AccountsBox">
						<h3 class="hndle"><span><?php echo __( 'Account', 'wp-lister-for-ebay' ); ?></span></h3>
						<div class="inside">
							<?php foreach ( WPLE()->accounts as $account) : ?>
								<?php
									$account_id = $account->id;
									$checked    = $wpl_item['account_id'] == $account_id ? 'checked="checked"' : '';
									$disabled   = $account->active ? '' : 'disabled="disabled"';
								?>

								<input type="radio" value="<?php echo $account_id ?>" id="account-<?php echo $account_id ?>" name="wpl_e2e_account_id" class="post-format" <?php echo $checked ?> <?php echo $disabled ?> > 
								<label for="account-<?php echo $account_id ?>"><?php echo $account->title ?></label><br>

							<?php endforeach; ?>							
							<p><small><?php echo __( 'When you change the account, you need to save the profile before you make any changes.', 'wp-lister-for-ebay' ); ?></small></p>
						</div>
					</div>
					<?php else : ?>							
						<input type="hidden" name="wpl_e2e_account_id" value="<?php echo esc_attr( $wpl_item['account_id'] ?? '' ); ?>" />
					<?php endif; ?>							


					<div class="postbox" id="TitleSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Title and Subtitle', 'wp-lister-for-ebay' ); ?></span></h3>
						<div class="inside">

							<label for="wpl-text-title_prefix" class="text_label">
								<?php echo __( 'Title prefix', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('This text will automatically be prepended to the listing title.', 'wp-lister-for-ebay')) ?>
							</label>
							<input type="text" name="wpl_e2e_title_prefix" id="wpl-text-title_prefix" value="<?php echo esc_attr( $item_details['title_prefix'] ?? '' ); ?>" class="text_input" />
							<br class="clear" />

							<label for="wpl-text-title_suffix" class="text_label">
								<?php echo __( 'Title suffix', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('This text will automatically be appended to the listing title.<br>You can use this to add keywords and attributes to improve your search visibility.', 'wp-lister-for-ebay')) ?>
							</label>
							<input type="text" name="wpl_e2e_title_suffix" id="wpl-text-title_suffix" value="<?php echo esc_attr( $item_details['title_suffix'] ?? '' ); ?>" class="text_input" />
							<br class="clear" />

							<p class="desc" style="display: block;">
								<?php echo __( 'Add keywords to your listing title.', 'wp-lister-for-ebay' ); ?>
								<a href="#" onclick="jQuery('#title_help_msg').slideToggle('fast');return false;">
									<?php echo __( 'How?', 'wp-lister-for-ebay' ); ?>
								</a>
							</p>
							<p id="title_help_msg" class="desc_toggle" style="display: none;">
								<?php echo __( 'You can use a subset of the available listing shortcodes in title prefix and suffix.', 'wp-lister-for-ebay' ); ?>
								<br><br>
								<?php echo __( 'Example', 'wp-lister-for-ebay' ); ?>: 
								If you have a product attribute "Size", use the following shortcode to include the products size in the listing title:
								<br><br>
								<code>[[attribute_Size]]</code><br>
								<hr>
							</p>

							<label for="wpl-text-bold_title" class="text_label">
								<?php echo __( 'Bold title', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Select whether you want the listing title to be in boldface type.<br><br><b>This option might increase your listing fees depending on the eBay site and your subscription plan.</b><br><br>Not applicable to eBay Motors.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-text-bold_title" name="wpl_e2e_bold_title" title="Use additional product description as subtitle" class=" required-entry select">
								<option value="1" <?php if ( isset($item_details['bold_title']) && $item_details['bold_title'] == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?> (<?php echo __('extra fees', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="0" <?php if ( !isset($item_details['bold_title']) || $item_details['bold_title'] != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<br class="clear" />

							<label for="wpl-text-subtitle_enabled" class="text_label">
								<?php echo __( 'Enable subtitle', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Select whether you want your listings to have a subtitle.<br><br><b>This option might increase your listing fees depending on the eBay site and your subscription plan.</b>', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-text-subtitle_enabled" name="wpl_e2e_subtitle_enabled" title="Use additional product description as subtitle" class=" required-entry select">
								<option value="1" <?php if ( isset($item_details['subtitle_enabled']) && $item_details['subtitle_enabled'] == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?> (<?php echo __('extra fees', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="0" <?php if ( !isset($item_details['subtitle_enabled']) || $item_details['subtitle_enabled'] != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<br class="clear" />

							<div id="subtitle_options_container">

								<label for="wpl-text-custom_subtitle" class="text_label">
									<?php echo __( 'Custom subtitle', 'wp-lister-for-ebay' ); ?>
	                                <?php wplister_tooltip(__('Leave this empty to use the short description as subtitle, or enter a custom eBay subtitle on your products.<br>Note: The subtitle will be truncated after 55 characters.', 'wp-lister-for-ebay')) ?>
								</label>
								<input type="text" name="wpl_e2e_custom_subtitle" id="wpl-text-custom_subtitle" value="<?php echo esc_attr( $item_details['custom_subtitle'] ?? '' ); ?>" maxlength="55" class="text_input" />
								<br class="clear" />

								<p class="desc" style="display: none;">
									<?php echo __( 'Leave this empty to use the short description as subtitle.', 'wp-lister-for-ebay' ); ?>
									<?php echo __( 'Will be truncated after 55 characters.', 'wp-lister-for-ebay' ); ?>
								</p>

							</div>

						</div>
					</div>


					<div class="postbox" id="VariationsSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Variations', 'wp-lister-for-ebay' ); ?></span></h3>
						<div class="inside">

							<label for="wpl-text-variations_mode" class="text_label">
								<?php echo __( 'Variation mode', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('By default WP-Lister will attempt to list variable products as variations on eBay. However, if eBay does not allow variations in your product category you can either <b>split</b> them and list as single listings, or <b>flatten</b>them into just one single listing.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-text-variations_mode" name="wpl_e2e_variations_mode" title="Variation Mode" class=" required-entry select">
								<option value="default" <?php if ( !isset( $item_details['variations_mode'] ) || $item_details['variations_mode'] == 'default' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'list as variations', 'wp-lister-for-ebay' ); ?></option>
								<option value="flat"    <?php if ( isset( $item_details['variations_mode'] ) && $item_details['variations_mode'] == 'flat' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'flatten variations', 'wp-lister-for-ebay' ); ?></option>
                                <?php wple_render_pro_select_option( 'split', __( 'split variations', 'wp-lister-for-ebay' ), selected($item_details['variations_mode'] ?? '', 'split', false) ); ?>
							</select>
							<br class="clear" />

							<label for="wpl-text-with_variation_images" class="text_label">
								<?php echo __( 'Var. images', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Enable this if you have assigned different product images to your variations.<br>Note: eBay accepts variation images only for a single attribute.<br>So if you sell T-Shirts in different colors and sizes, you can have a different image for each color, but (unlike WooCommerce) not for each size at the same time.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-text-with_variation_images" name="wpl_e2e_with_variation_images" class=" required-entry select">
								<option value="1" <?php if ( isset( $item_details['with_variation_images'] ) && $item_details['with_variation_images'] == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
								<option value="0" <?php if ( !isset( $item_details['with_variation_images'] ) || $item_details['with_variation_images'] != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<br class="clear" />

							<label for="wpl-text-variation_image_attribute" class="text_label">
								<?php echo __( 'Image attribute', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Select which attribute is used for variation images.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-text-variation_image_attribute" name="wpl_e2e_variation_image_attribute" class=" required-entry select">
								<option value="" <?php if ( !isset( $item_details['variation_image_attribute'] ) || $item_details['variation_image_attribute'] == '' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Default', 'wp-lister-for-ebay' ); ?></option>
								<?php if ( isset( $wpl_available_attributes ) && is_array( $wpl_available_attributes ) ): ?>
									<?php
                                    foreach ($wpl_available_attributes as $attribute) :
                                        $name = $attribute->name;
                                        $label = $attribute->label;

                                        // qTranslate support - translate title and description
                                        if ( function_exists( 'qtranxf_use' ) ) {
                                            $lang = WPLE_eBayAccount::getAccountLocale( $account_id );

                                            $name = qtranxf_use( $lang, $name );
                                            $label = qtranxf_use( $lang, $label );
                                        }
                                    ?>
										<option value="<?php echo esc_attr($name) ?>"
											<?php if ( isset($item_details['variation_image_attribute']) && $item_details['variation_image_attribute'] == $name ) : ?>
												selected="selected"
											<?php endif; ?>
											><?php echo $label ?></option>
									<?php endforeach; ?>
								<?php endif; ?>
							</select>
							<br class="clear" />

							<label for="wpl-text-add_variations_table" class="text_label">
								<?php echo __( 'Add var. table', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('<b>Add variations table</b><br>This will append a list (HTML table) of all available variations to the product description which can be customized via CSS.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-text-add_variations_table" name="wpl_e2e_add_variations_table" title="Add variations list as HTML table to item description" class=" required-entry select">
								<option value="1" <?php if ( isset($item_details['add_variations_table']) && $item_details['add_variations_table'] == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
								<option value="0" <?php if ( !isset($item_details['add_variations_table']) || $item_details['add_variations_table'] != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<br class="clear" />

                            <label for="wpl-text-enable-attribute-mapping" class="text_label">
                                <?php _e( 'Attribute Mapping', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip( __('Enable to map matching variation attributes to the profile\'s item specifics fields. Disable if you are getting Variation Specifics Mismatch errors when revising your listings.', 'wp-lister-for-ebay')); ?>
                            </label>
                            <select id="wpl-text-enable-attribute-mapping" name="wpl_e2e_enable_attribute_mapping" class="required-entry select">
                                <option value="1" <?php selected( !isset($item_details['enable_attribute_mapping']) || $item_details['enable_attribute_mapping'], 1 ); ?>><?php _e( 'Yes', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                <option value="0" <?php selected( isset($item_details['enable_attribute_mapping']) && $item_details['enable_attribute_mapping'], 0 ); ?>><?php _e( 'No', 'wp-lister-for-ebay' ); ?></option>
                            </select>
						</div>
					</div>

                    <div class="postbox" id="SizeMapSettingsBox">
                        <h3 class="hndle"><span><?php echo __( 'Variation Size Map', 'wp-lister-for-ebay' ); ?></span></h3>
                        <div class="inside">
                            <label for="wpl-text-sizemap_field" class="text_label">
                                <?php echo __( 'Size Field', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('The name of the size attribute in WooCommerce', 'wp-lister-for-ebay')) ?>
                            </label>
                            <input type="text" name="wpl_e2e_sizemap_field" id="wpl-text-sizemap_field" value="<?php echo esc_attr( $item_details['sizemap_field'] ?? '' ); ?>" class="text_input" />
                            <br class="clear" />

                            <table id="sizemap">
                                <tr>
                                    <th>WC Value</th>
                                    <th>eBay Value</th>
                                </tr>
                                <tbody id="sizemap_body">
                                <?php
                                if (!empty( $item_details['sizemap_wc'])):
                                    foreach ( $item_details['sizemap_wc'] as $idx => $value ):
                                        if ( empty( $item_details['sizemap_wc'][$idx] ) && empty( $item_details['sizemap_ebay'][$idx] ) ) {
                                            continue;
                                        }
                                ?>
                                <tr>
                                    <td>
                                        <input type="text" class="sizemap-wc" name="wpl_e2e_sizemap_wc[]" value="<?php esc_attr_e( $value ); ?>" size="8" />
                                    </td>
                                    <td>
                                        <input type="text" class="sizemap-ebay" name="wpl_e2e_sizemap_ebay[]" value="<?php esc_attr_e( $item_details['sizemap_ebay'][$idx] ); ?>" size="8" />
                                    </td>
                                </tr>
                                <?php
                                    endforeach;
                                else:
                                ?>
                                <tr>
                                    <td>
                                        <input type="text" class="sizemap-wc" name="wpl_e2e_sizemap_wc[]" value="" size="8" />
                                    </td>
                                    <td>
                                        <input type="text" class="sizemap-ebay" name="wpl_e2e_sizemap_ebay[]" value="" size="8" />
                                    </td>
                                </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                            <p><button id="add_size_row" class="button">&plus; Add Row</button></p>
                        </div>
                    </div>


					<div class="postbox" id="LayoutSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Images', 'wp-lister-for-ebay' ); ?></span></h3>
						<div class="inside">

							<label for="wpl-text-with_gallery_image" class="text_label">
								<?php echo __( 'Gallery image', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Select whether listing images are included in the search results (in the Picture Gallery and List Views).<br><br>This option might increase your listing fees depending on the eBay site and your subscription plan.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-text-with_gallery_image" name="wpl_e2e_with_gallery_image" title="Gallery image" class=" required-entry select">
								<option value="1" <?php if ( isset($item_details['with_gallery_image']) && $item_details['with_gallery_image'] == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
								<option value="0" <?php if ( !isset($item_details['with_gallery_image']) || $item_details['with_gallery_image'] == '0' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<br class="clear" />

							<label for="wpl-text-gallery_type" class="text_label">
								<?php echo __( 'Gallery type', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Specifies the Gallery enhancement type for the listing. If you use Plus, you also get the features of Gallery and if you use Featured, you get all the features of Gallery and Plus.<br><br>This option might increase your listing fees depending on the eBay site and your subscription plan.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-text-gallery_type" name="wpl_e2e_gallery_type" title="Gallery image" class=" required-entry select">
								<option value="Gallery"  <?php if ( !isset($item_details['gallery_type']) || $item_details['gallery_type'] == 'Gallery'  ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Gallery Standard', 'wp-lister-for-ebay' ); ?></option>
								<option value="Plus"     <?php if ( isset($item_details['gallery_type']) && $item_details['gallery_type'] == 'Plus'     ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Gallery Plus', 'wp-lister-for-ebay' ); ?>     (<?php echo __('extra fees', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="Featured" <?php if ( isset($item_details['gallery_type']) && $item_details['gallery_type'] == 'Featured' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Gallery Featured', 'wp-lister-for-ebay' ); ?> (<?php echo __('considerable extra fees', 'wp-lister-for-ebay' ); ?>)</option>
							</select>
							<br class="clear" />

							<div class="wple-field">
                                <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-text-with_additional_images" class="text_label">
		                            <?php echo __( 'Upload to EPS', 'wp-lister-for-ebay' ); ?>
		                            <?php wplister_tooltip(__('Select whether WP-Lister should upload your product images to eBay, in order to use all eBay Gallery features like additional images and zoom.<br>Note: Uploading images to eBay Picture Service (EPS) will slow down the listing process.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-text-with_additional_images" name="wpl_e2e_with_additional_images" title="Additional images" class=" required-entry select">
                                    <option value="0" <?php if ( !isset($item_details['with_additional_images']) || $item_details['with_additional_images'] == '0' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="1" <?php if ( isset($item_details['with_additional_images']) && $item_details['with_additional_images'] == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Upload all images', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="2" <?php if ( isset($item_details['with_additional_images']) && $item_details['with_additional_images'] == '2' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Upload first 24 images', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="3" <?php if ( isset($item_details['with_additional_images']) && $item_details['with_additional_images'] == '3' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Upload only main image', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <br class="clear" />
                                <p class="desc" style="display: none;">
		                            <?php echo __( 'Note: Uploading images to eBay Picture Service (EPS) will slow down the listing process.', 'wp-lister-for-ebay' ); ?>
                                </p>
                            </div>

						</div>
					</div>

					<div class="postbox" id="BestOfferSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Best Offer', 'wp-lister-for-ebay' ); ?></span></h3>
						<div class="inside">
                            <div class="wple-field">
                                <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-text-bestoffer_enabled" class="text_label">
		                            <?php echo __( 'Enable', 'wp-lister-for-ebay' ); ?>
		                            <?php wplister_tooltip(__('If you are selling a fixed-price item (in a category for which Best Offer is enabled) you can enable the Best Offer feature to allow a buyer to make a lower-priced binding offer on that item.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-text-bestoffer_enabled" name="wpl_e2e_bestoffer_enabled" class=" required-entry select">
                                    <option value="1" <?php if ( isset($item_details['bestoffer_enabled']) && $item_details['bestoffer_enabled'] == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="0" <?php if ( !isset($item_details['bestoffer_enabled']) || $item_details['bestoffer_enabled'] != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <br class="clear" />
                            </div>

							<div id="best_offer_options_container">
                                <label for="wpl-text-bo_autoaccept_price" class="text_label">
                                    <?php echo __( 'Auto accept price', 'wp-lister-for-ebay' ); ?>
                                    <?php wplister_tooltip(__('The price at which Best Offers are automatically accepted. If a buyer submits a Best Offer that is above this value, the offer is automatically accepted by the seller.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <input type="text" name="wpl_e2e_bo_autoaccept_price" id="wpl-text-bo_autoaccept_price" value="<?php echo esc_attr( $item_details['bo_autoaccept_price'] ?? '' ); ?>" class="text_input" />
                                <br class="clear" />

                                <label for="wpl-text-bo_minimum_price" class="text_label">
                                    <?php echo __( 'Minimum price', 'wp-lister-for-ebay' ); ?>
                                    <?php wplister_tooltip(__('Specifies the minimum acceptable Best Offer price. If a buyer submits a Best Offer that is below this value, the offer is automatically declined by the seller.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <input type="text" name="wpl_e2e_bo_minimum_price" id="wpl-text-bo_minimum_price" value="<?php echo esc_attr( $item_details['bo_minimum_price'] ?? '' ); ?>" class="text_input" />
                                <br class="clear" />
							</div>

						</div>
					</div>

					<div class="postbox" id="ScheduleSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Schedule Listing', 'wp-lister-for-ebay' ); ?></span></h3>
						<div class="inside">

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-text-schedule_time" class="text_label">
		                            <?php echo __( 'Schedule hour', 'wp-lister-for-ebay' ); ?>
		                            <?php wplister_tooltip(__('Allows you to specify the date and time that the listing becomes active on eBay.<br>To schedule the listing start time, specify a time in the future <b>in GMT format</b>.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-text-schedule_time" name="wpl_e2e_schedule_time" title="Schedule listing" class=" required-entry select">
                                    <option value="" <?php if ( !isset( $item_details['schedule_time'] ) || $item_details['schedule_time'] == '' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'None', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="00:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '00:00' ): ?>selected="selected"<?php endif; ?>>00:00 GMT</option>
                                    <option value="01:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '01:00' ): ?>selected="selected"<?php endif; ?>>01:00 GMT</option>
                                    <option value="02:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '02:00' ): ?>selected="selected"<?php endif; ?>>02:00 GMT</option>
                                    <option value="03:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '03:00' ): ?>selected="selected"<?php endif; ?>>03:00 GMT</option>
                                    <option value="04:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '04:00' ): ?>selected="selected"<?php endif; ?>>04:00 GMT</option>
                                    <option value="05:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '05:00' ): ?>selected="selected"<?php endif; ?>>05:00 GMT</option>
                                    <option value="06:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '06:00' ): ?>selected="selected"<?php endif; ?>>06:00 GMT</option>
                                    <option value="07:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '07:00' ): ?>selected="selected"<?php endif; ?>>07:00 GMT</option>
                                    <option value="08:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '08:00' ): ?>selected="selected"<?php endif; ?>>08:00 GMT</option>
                                    <option value="09:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '09:00' ): ?>selected="selected"<?php endif; ?>>09:00 GMT</option>
                                    <option value="10:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '10:00' ): ?>selected="selected"<?php endif; ?>>10:00 GMT</option>
                                    <option value="11:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '11:00' ): ?>selected="selected"<?php endif; ?>>11:00 GMT</option>
                                    <option value="12:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '12:00' ): ?>selected="selected"<?php endif; ?>>12:00 GMT</option>
                                    <option value="13:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '13:00' ): ?>selected="selected"<?php endif; ?>>13:00 GMT</option>
                                    <option value="14:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '14:00' ): ?>selected="selected"<?php endif; ?>>14:00 GMT</option>
                                    <option value="15:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '15:00' ): ?>selected="selected"<?php endif; ?>>15:00 GMT</option>
                                    <option value="16:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '16:00' ): ?>selected="selected"<?php endif; ?>>16:00 GMT</option>
                                    <option value="17:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '17:00' ): ?>selected="selected"<?php endif; ?>>17:00 GMT</option>
                                    <option value="18:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '18:00' ): ?>selected="selected"<?php endif; ?>>18:00 GMT</option>
                                    <option value="19:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '19:00' ): ?>selected="selected"<?php endif; ?>>19:00 GMT</option>
                                    <option value="20:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '20:00' ): ?>selected="selected"<?php endif; ?>>20:00 GMT</option>
                                    <option value="21:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '21:00' ): ?>selected="selected"<?php endif; ?>>21:00 GMT</option>
                                    <option value="22:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '22:00' ): ?>selected="selected"<?php endif; ?>>22:00 GMT</option>
                                    <option value="23:00" <?php if ( isset( $item_details['schedule_time'] ) && $item_details['schedule_time'] == '23:00' ): ?>selected="selected"<?php endif; ?>>23:00 GMT</option>
                                </select>
                                <br class="clear" />
                            </div>

							<div id="schedule_time_details_container">
								<label for="wpl-text-schedule_minute" class="text_label">
									<?php echo __( 'Schedule minute', 'wp-lister-for-ebay' ); ?>
								</label>
								<select id="wpl-text-schedule_minute" name="wpl_e2e_schedule_minute" title="Schedule listing" class=" required-entry select">
									<?php for ($i=0; $i < 60; $i++) : ?>
										<?php $minute = $i < 10 ? '0'.$i : $i; ?> 
										<option value="<?php echo $minute ?>" <?php if ( isset( $item_details['schedule_minute'] ) && $item_details['schedule_minute'] == $minute ): ?>selected="selected"<?php endif; ?>><?php echo $minute ?></option>
									<?php endfor; ?>
								</select>
								<br class="clear" />

								<label for="wpl-text-schedule_days" class="text_label">
									<?php _e( 'Schedule day', 'wp-lister-for-ebay' ); ?>
								</label>
								<select id="wpl-text-schedule_days" name="wpl_e2e_schedule_days" class="required-entry select">
									<option value="0" <?php selected( isset( $item_details['schedule_days'] ) && $item_details['schedule_days'], 0 ); ?>><?php _e( '+0 days', 'wp-lister-for-ebay' ); ?></option>
									<option value="1" <?php selected( isset( $item_details['schedule_days'] ) && $item_details['schedule_days'], 1 ); ?>><?php _e( '+1 day', 'wp-lister-for-ebay' ); ?></option>
									<option value="2" <?php selected( isset( $item_details['schedule_days'] ) && $item_details['schedule_days'], 2 ); ?>><?php _e( '+2 days', 'wp-lister-for-ebay' ); ?></option>
									<option value="3" <?php selected( isset( $item_details['schedule_days'] ) && $item_details['schedule_days'], 3 ); ?>><?php _e( '+3 days', 'wp-lister-for-ebay' ); ?></option>
									<option value="4" <?php selected( isset( $item_details['schedule_days'] ) && $item_details['schedule_days'], 4 ); ?>><?php _e( '+4 days', 'wp-lister-for-ebay' ); ?></option>
									<option value="5" <?php selected( isset( $item_details['schedule_days'] ) && $item_details['schedule_days'], 5 ); ?>><?php _e( '+5 days', 'wp-lister-for-ebay' ); ?></option>
									<option value="6" <?php selected( isset( $item_details['schedule_days'] ) && $item_details['schedule_days'], 6 ); ?>><?php _e( '+6 days', 'wp-lister-for-ebay' ); ?></option>
									<option value="7" <?php selected( isset( $item_details['schedule_days'] ) && $item_details['schedule_days'], 7 ); ?>><?php _e( '+7 days', 'wp-lister-for-ebay' ); ?></option>
									<option value="8" <?php selected( isset( $item_details['schedule_days'] ) && $item_details['schedule_days'], 8 ); ?>><?php _e( '+8 days', 'wp-lister-for-ebay' ); ?></option>
									<option value="9" <?php selected( isset( $item_details['schedule_days'] ) && $item_details['schedule_days'], 9 ); ?>><?php _e( '+9 days', 'wp-lister-for-ebay' ); ?></option>
									<option value="10" <?php selected( isset( $item_details['schedule_days'] ) && $item_details['schedule_days'], 10 ); ?>><?php _e( '+10 days', 'wp-lister-for-ebay' ); ?></option>
									<option value="11" <?php selected( isset( $item_details['schedule_days'] ) && $item_details['schedule_days'], 11 ); ?>><?php _e( '+11 days', 'wp-lister-for-ebay' ); ?></option>
									<option value="12" <?php selected( isset( $item_details['schedule_days'] ) && $item_details['schedule_days'], 12 ); ?>><?php _e( '+12 days', 'wp-lister-for-ebay' ); ?></option>
									<option value="13" <?php selected( isset( $item_details['schedule_days'] ) && $item_details['schedule_days'], 13 ); ?>><?php _e( '+13 days', 'wp-lister-for-ebay' ); ?></option>
									<option value="14" <?php selected( isset( $item_details['schedule_days'] ) && $item_details['schedule_days'], 14 ); ?>><?php _e( '+14 days', 'wp-lister-for-ebay' ); ?></option>
									<option value="15" <?php selected( isset( $item_details['schedule_days'] ) && $item_details['schedule_days'], 15 ); ?>><?php _e( '+15 days', 'wp-lister-for-ebay' ); ?></option>
									<option value="16" <?php selected( isset( $item_details['schedule_days'] ) && $item_details['schedule_days'], 16 ); ?>><?php _e( '+16 days', 'wp-lister-for-ebay' ); ?></option>
									<option value="17" <?php selected( isset( $item_details['schedule_days'] ) && $item_details['schedule_days'], 17 ); ?>><?php _e( '+17 days', 'wp-lister-for-ebay' ); ?></option>
									<option value="18" <?php selected( isset( $item_details['schedule_days'] ) && $item_details['schedule_days'], 18 ); ?>><?php _e( '+18 days', 'wp-lister-for-ebay' ); ?></option>
									<option value="19" <?php selected( isset( $item_details['schedule_days'] ) && $item_details['schedule_days'], 19 ); ?>><?php _e( '+19 days', 'wp-lister-for-ebay' ); ?></option>
									<option value="20" <?php selected( isset( $item_details['schedule_days'] ) && $item_details['schedule_days'], 20 ); ?>><?php _e( '+20 days', 'wp-lister-for-ebay' ); ?></option>
								</select>
								<br class="clear" />

								<p class="desc" style="display: block;">
									<?php #echo __( 'Note: Schedule time must be set as GMT.', 'wp-lister-for-ebay' ); ?><!br>
									<?php echo __( 'The current time is', 'wp-lister-for-ebay' ); ?> 
									<b><?php echo gmdate('H:i', time() ); ?> GMT</b>
								</p>
							</div>

						</div>
					</div>

					<div class="postbox" id="AutoRelistSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Auto Relist', 'wp-lister-for-ebay' ); ?></span></h3>
						<div class="inside">

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-text-autorelist_enabled" class="text_label">
		                            <?php echo __( 'Auto Relist', 'wp-lister-for-ebay' ); ?>
		                            <?php wplister_tooltip(__('Automatically relist your listings.<br>This feature is currently in beta.<br><br><b>Note:</b> You should only use this option if you are not allowed to use <i>Good Till Cancelled</i> (GTC) for your products or category.<br><b>If possible, always use GTC instead.</b>', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-text-autorelist_enabled" name="wpl_e2e_autorelist_enabled" class=" required-entry select">
                                    <option value="1" <?php if ( isset( $item_details['autorelist_enabled'] ) && $item_details['autorelist_enabled'] == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="0" <?php if ( !isset( $item_details['autorelist_enabled'] ) || $item_details['autorelist_enabled'] != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <br class="clear" />
                            </div>

							<div id="autorelist_options_container">

							<label for="wpl-text-autorelist_condition" class="text_label">
								<?php echo __( 'Relist Condition', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Select if you want your item relisted immediately, <i>after</i> a specific time or <i>at</i> a specific time of day.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-text-autorelist_condition" name="wpl_e2e_autorelist_condition" class=" required-entry select">
								<option value="" <?php if ( !isset( $item_details['autorelist_condition'] ) || $item_details['autorelist_condition'] == '' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'None', 'wp-lister-for-ebay' ); ?></option>
								<option value="RelistImmediately" <?php if ( isset( $item_details['autorelist_condition'] ) && $item_details['autorelist_condition'] == 'RelistImmediately' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Relist immediately', 'wp-lister-for-ebay' ); ?></option>
								<option value="RelistAfterHours"  <?php if ( isset( $item_details['autorelist_condition'] ) && $item_details['autorelist_condition'] == 'RelistAfterHours' ):  ?>selected="selected"<?php endif; ?>><?php echo __( 'Relist after X hours', 'wp-lister-for-ebay' ); ?></option>
								<option value="RelistAtTimeOfDay" <?php if ( isset( $item_details['autorelist_condition'] ) && $item_details['autorelist_condition'] == 'RelistAtTimeOfDay' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Relist at specific time of day', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<br class="clear" />

							<label for="wpl-text-autorelist_after_hours" class="text_label">
								<?php echo __( 'Hours', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('The number of hours after the item ends that it should be relisted.', 'wp-lister-for-ebay')) ?>
							</label>
							<input type="text" name="wpl_e2e_autorelist_after_hours" id="wpl-text-autorelist_after_hours" value="<?php echo esc_attr( $item_details['autorelist_after_hours'] ?? '' ); ?>" class="text_input" />
							<br class="clear" />

							<label for="wpl-text-autorelist_at_timeofday" class="text_label">
								<?php echo __( 'Time of day', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('The time of day the item should be relisted in GMT.<br>Format: HH:MM<br>(e.g. 19:30)', 'wp-lister-for-ebay')) ?>
							</label>
							<input type="text" name="wpl_e2e_autorelist_at_timeofday" id="wpl-text-autorelist_at_timeofday" value="<?php echo esc_attr( $item_details['autorelist_at_timeofday'] ?? '' ); ?>" class="text_input" />
							<br class="clear" />

							</div>

						</div>
					</div>

					<?php if ( !empty( $item_details['sellingmanager_enabled'] ) && $item_details['sellingmanager_enabled'] == '1' ): ?>
					<div class="postbox" id="SellingManagerSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Selling Manager Pro', 'wp-lister-for-ebay' ); ?></span></h3>
						<div class="inside">

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-text-sellingmanager_enabled" class="text_label">
		                            <?php echo __( 'Enable', 'wp-lister-for-ebay' ); ?>
		                            <?php wplister_tooltip(__('Use a Selling Manager automation rule to automatically relist your listings. To use this feature, you must be a Selling Manager Pro subscriber.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-text-sellingmanager_enabled" name="wpl_e2e_sellingmanager_enabled" class=" required-entry select">
                                    <option value="1" <?php if ( isset( $item_details['sellingmanager_enabled'] ) && $item_details['sellingmanager_enabled'] == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="0" <?php if ( !isset( $item_details['sellingmanager_enabled'] ) || $item_details['sellingmanager_enabled'] != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <br class="clear" />
                            </div>

							<div id="sm_auto_relist_options_container">

							<label for="wpl-text-AutomatedRelistingRule_Type" class="text_label">
								<?php echo __( 'Auto Relist Type', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Select if and when eBay should stop relisting the item.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-text-AutomatedRelistingRule_Type" name="wpl_e2e_AutomatedRelistingRule_Type" class=" required-entry select">
								<option value=""                            <?php if ( !isset( $item_details['AutomatedRelistingRule_Type'] ) || $item_details['AutomatedRelistingRule_Type'] == '' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'None', 'wp-lister-for-ebay' ); ?></option>
								<option value="RelistContinuously"          <?php if ( isset( $item_details['AutomatedRelistingRule_Type'] ) && $item_details['AutomatedRelistingRule_Type'] == 'RelistContinuously' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Relist continuously', 'wp-lister-for-ebay' ); ?></option>
								<option value="RelistContinuouslyUntilSold" <?php if ( isset( $item_details['AutomatedRelistingRule_Type'] ) && $item_details['AutomatedRelistingRule_Type'] == 'RelistContinuouslyUntilSold' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Relist continuously until sold', 'wp-lister-for-ebay' ); ?></option>
								<option value="RelistOnceIfNotSold"         <?php if ( isset( $item_details['AutomatedRelistingRule_Type'] ) && $item_details['AutomatedRelistingRule_Type'] == 'RelistOnceIfNotSold' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Relist once if not sold', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<br class="clear" />

							<label for="wpl-text-AutomatedRelistingRule_RelistCondition" class="text_label">
								<?php echo __( 'Relist Condition', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Select if you want your item relisted immediately, <i>after</i> a specific time or <i>at</i> a specific time of day.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-text-AutomatedRelistingRule_RelistCondition" name="wpl_e2e_AutomatedRelistingRule_RelistCondition" class=" required-entry select">
								<option value=""                            <?php if ( !isset( $item_details['AutomatedRelistingRule_RelistCondition'] ) || $item_details['AutomatedRelistingRule_RelistCondition'] == '' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'None', 'wp-lister-for-ebay' ); ?></option>
								<option value="RelistImmediately"           <?php if ( isset( $item_details['AutomatedRelistingRule_RelistCondition'] ) && $item_details['AutomatedRelistingRule_RelistCondition'] == 'RelistImmediately' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Relist immediately', 'wp-lister-for-ebay' ); ?></option>
								<option value="RelistAfterDaysHours"        <?php if ( isset( $item_details['AutomatedRelistingRule_RelistCondition'] ) && $item_details['AutomatedRelistingRule_RelistCondition'] == 'RelistAfterDaysHours' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Relist after days / hours', 'wp-lister-for-ebay' ); ?></option>
								<option value="RelistAtSpecificTimeOfDay"   <?php if ( isset( $item_details['AutomatedRelistingRule_RelistCondition'] ) && $item_details['AutomatedRelistingRule_RelistCondition'] == 'RelistAtSpecificTimeOfDay' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Relist at specific time of day', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<br class="clear" />

							<label for="wpl-text-RelistAfterDays" class="text_label">
								<?php echo __( 'Days', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('The number of days after the item ends that it should be relisted.', 'wp-lister-for-ebay')) ?>
							</label>
							<input type="text" name="wpl_e2e_RelistAfterDays" id="wpl-text-RelistAfterDays" value="<?php echo esc_attr( $item_details['RelistAfterDays'] ?? '' ); ?>" class="text_input" />
							<br class="clear" />

							<label for="wpl-text-RelistAfterHours" class="text_label">
								<?php echo __( 'Hours', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('The number of hours after the item ends that it should be relisted.', 'wp-lister-for-ebay')) ?>
							</label>
							<input type="text" name="wpl_e2e_RelistAfterHours" id="wpl-text-RelistAfterHours" value="<?php echo esc_attr( $item_details['RelistAfterHours'] ?? '' ); ?>" class="text_input" />
							<br class="clear" />

							<label for="wpl-text-RelistAtSpecificTimeOfDay" class="text_label">
								<?php echo __( 'Time of day', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('The time of day the item should be relisted in GMT.<br>Format: THH:MM:SS.SSSZ<br>(e.g. T19:30:00.000Z)', 'wp-lister-for-ebay')) ?>
							</label>
							<input type="text" name="wpl_e2e_RelistAtSpecificTimeOfDay" id="wpl-text-RelistAtSpecificTimeOfDay" value="<?php echo esc_attr( $item_details['RelistAtSpecificTimeOfDay'] ?? '' ); ?>" class="text_input" />
							<br class="clear" />

							<label for="wpl-text-ListingHoldInventoryLevel" class="text_label">
								<?php echo __( 'Hold inventory level', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Do not list if inventory levels on the associated product are at or below the specified amount.', 'wp-lister-for-ebay')) ?>
							</label>
							<input type="text" name="wpl_e2e_ListingHoldInventoryLevel" id="wpl-text-ListingHoldInventoryLevel" value="<?php echo esc_attr( $item_details['ListingHoldInventoryLevel'] ?? '' ); ?>" class="text_input" />
							<br class="clear" />

							</div>

						</div>
					</div>
					<?php endif; ?>

					<div class="postbox" id="OtherSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Other options', 'wp-lister-for-ebay' ); ?></span></h3>
						<div class="inside">

							<div class="wple-field">
								<?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-text-cross_border_trade" class="text_label">
									<?php echo __( 'Int. Site Visibility', 'wp-lister-for-ebay' ); ?>
									<?php wplister_tooltip(__('<b>International Site Visibility <br>(Cross Border Trade)</b><br><br>
                                						This is used by sellers who want their listing to be returned in the search results for other eBay sites. This feature is currently only supported by the US, UK, eBay Canada, and eBay Ireland sites.<br>
                                						There is a small listing fee for each country specified as a Cross Border Trade country. <br><br>
                                						US listings that offer shipping to Canada, North America, or worldwide are automatically returned on eBay.ca at no extra charge, so US listings that offer shipping to these locations do not need to specify Canada as a Cross Border Trade country.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-text-cross_border_trade" name="wpl_e2e_cross_border_trade" title="Cross Border Trade" class=" required-entry select">
                                    <option value="None"><?php echo __( 'None', 'wp-lister-for-ebay' ); ?></option>
                                    <option value="UK"              <?php if ( isset( $item_details['cross_border_trade'] ) && $item_details['cross_border_trade'] == 'UK' ): ?>selected="selected"<?php endif; ?>>UK</option>
                                    <option value="North America"   <?php if ( isset( $item_details['cross_border_trade'] ) && $item_details['cross_border_trade'] == 'North America' ): ?>selected="selected"<?php endif; ?>>North America</option>
                                </select>
                                <br class="clear" />
                            </div>

							<label for="wpl-text-global_shipping" class="text_label">
								<?php echo __( 'Global Shipping', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Select whether eBay\'s Global Shipping Program is offered for the listing.<br><br>
                                						If set to Yes, the Global Shipping Program is the default international shipping option for the listing, and eBay sets the international shipping service to International Priority Shipping. <br><br>
                                						If set to "No", the seller is responsible for specifying an international shipping service for the listing if desired.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-text-global_shipping" name="wpl_e2e_global_shipping" class=" required-entry select">
								<option value="1" <?php if ( isset( $item_details['global_shipping'] ) && $item_details['global_shipping'] == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
								<option value="0" <?php if ( !isset( $item_details['global_shipping'] ) || $item_details['global_shipping'] != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<br class="clear" />

							<label for="wpl-text-store_pickup" class="text_label">
								<?php echo __( 'Store Pickup', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Enable the listing for In-Store Pickup.<br><br>
                                						To enable In-Store Pickup, set this option to <i>yes</i>. A seller must be eligible for the In-Store Pickup feature to use this option. <br><br>
                                						At this time, the In-Store Pickup feature is generally only available to large retail merchants, and can only be applied to multi-quantity, fixed-price listings.<br><br>
                                                        Unfortunately, eBay has deprecated this feature in the latest version 1049 of their API, so it can not be used anymore. If you need this feature, please contact eBay and ask them to give developers an alternative way to mark a listing as eligible for store pick-up in a future version of their API.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-text-store_pickup" name="wpl_e2e_store_pickup" class=" required-entry select">
								<option value="1" <?php if ( isset( $item_details['store_pickup'] ) && $item_details['store_pickup'] == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
								<option value="0" <?php if ( !isset( $item_details['store_pickup'] ) || $item_details['store_pickup'] != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<br class="clear" />

							<label for="wpl-text-private_listing" class="text_label">
								<?php echo __( 'Private listing', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('This option can be used to obscure item title, item ID, and item price from post-order Feedback comments left by buyers.<br><br>
                                						Typically, it is not advisable that sellers use the Private Listing feature, but using this feature may be appropriate for sellers listing in Adults Only categories, or for high-priced and/or celebrity items.<br><br>
                                						Unfortunately, eBay has deprecated this feature in the latest version 1045 of their API, so it can not be used anymore. If you need this feature, please contact eBay and ask them to give developers an alternative way to mark a listing as private in a future version of their API.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-text-private_listing" name="wpl_e2e_private_listing" class=" required-entry select">
								<option value="1" <?php if ( isset( $item_details['private_listing'] ) && $item_details['private_listing'] == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?> (disabled!)</option>
								<option value="0" <?php if ( !isset( $item_details['private_listing'] ) || $item_details['private_listing'] != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<br class="clear" />

							<label for="wpl-text-use_sku_as_upc" class="text_label">
								<?php echo __( 'Use SKU as UPC', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('This is a workaround for users who use actual UPCs as SKU in their shop. <br><br>
                                						The recommended way of listing products by UPC in order to fetch product details from the eBay catalog is by setting the UPC on the edit product page.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-text-use_sku_as_upc" name="wpl_e2e_use_sku_as_upc" class=" required-entry select">
								<option value="1" <?php if ( isset( $item_details['use_sku_as_upc'] ) && $item_details['use_sku_as_upc'] == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
								<option value="0" <?php if ( !isset( $item_details['use_sku_as_upc'] ) || $item_details['use_sku_as_upc'] != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<br class="clear" />

                            <label for="wpl-text-use_sku_as_ean" class="text_label">
                                <?php echo __( 'Use SKU as EAN', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('This is a workaround for users who use actual EANs as SKU in their shop.', 'wp-lister-for-ebay')) ?>
                            </label>
                            <select id="wpl-text-use_sku_as_ean" name="wpl_e2e_use_sku_as_ean" class=" required-entry select">
                                <option value="1" <?php if ( isset( $item_details['use_sku_as_ean'] ) && $item_details['use_sku_as_ean'] == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                                <option value="0" <?php if ( !isset( $item_details['use_sku_as_ean'] ) || $item_details['use_sku_as_ean'] != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
                            </select>
                            <br class="clear" />

                            <label for="wpl-text-use_sku_as_mpn" class="text_label">
                                <?php echo __( 'Use SKU as MPN', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('This is a workaround for users who use actual MPNs as SKU in their shop.', 'wp-lister-for-ebay')) ?>
                            </label>
                            <select id="wpl-text-use_sku_as_mpn" name="wpl_e2e_use_sku_as_mpn" class=" required-entry select">
                                <option value="1" <?php if ( isset( $item_details['use_sku_as_mpn'] ) && $item_details['use_sku_as_mpn'] == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                                <option value="0" <?php if ( !isset( $item_details['use_sku_as_mpn'] ) || $item_details['use_sku_as_mpn'] != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
                            </select>
                            <br class="clear" />

							<label for="wpl-text-include_prefilled_info" class="text_label">
								<?php echo __( 'Use Catalog Details', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('<b>Use Catalog Product Details</b><br>
                                						Disable this if you do not want to include the prefilled product information from the eBay catalog in your listings.<br><br>
                                						(Applies only to products from the eBay catalog which are listed by UPC, EAN or ePID.)', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-text-include_prefilled_info" name="wpl_e2e_include_prefilled_info" class=" required-entry select">
								<option value="1" <?php if ( isset( $item_details['include_prefilled_info'] ) && $item_details['include_prefilled_info'] == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
								<option value="0" <?php if ( !isset( $item_details['include_prefilled_info'] ) || $item_details['include_prefilled_info'] == '0' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<br class="clear" />

                            <label for="wpl-text-secondary_category" class="text_label">
                                <?php _e( 'Secondary Category', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Disable this if you only want to list items to the primary category.', 'wp-lister-for-ebay')); ?>
                            </label>
                            <select id="wpl-text-secondary_category" name="wpl_e2e_secondary_category" class="required-entry select">
                                <option value="1" <?php selected( isset( $item_details['secondary_category'] ) && $item_details['secondary_category'], 1 ); ?>><?php _e( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                                <option value="0" <?php selected( !isset( $item_details['secondary_category'] ) || $item_details['secondary_category'] == 0, true ); ?>><?php _e( 'No', 'wp-lister-for-ebay' ); ?></option>
                            </select>

							<label for="wpl-text-strikethrough_pricing" class="text_label">
								<?php echo __( 'Strikethrough price', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('<b>Strikethrough Price (STP)</b><br>Enable this if you want products on sale have their regular price be displayed on eBay as the original retail price / strikethrough price.<br><br>Alternatively, you can use an existing MSRP from WooCommerce as STP, if you have the WooCommerce MSRP extension installed.<br><br>Note: Strikethrough Pricing is available on selected eBay sites only. These sites include eBay US, UK, Germany, Canada, Australia, France, Italy and Spain and possibly more sites in the future.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-text-strikethrough_pricing" name="wpl_e2e_strikethrough_pricing" class=" required-entry select">
								<option value="0" <?php if ( !isset( $item_details['strikethrough_pricing'] ) || $item_details['strikethrough_pricing'] == '0' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
								<option value="1" <?php if ( isset( $item_details['strikethrough_pricing'] ) && $item_details['strikethrough_pricing'] == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
								<?php if ( class_exists( 'woocommerce_msrp_admin' ) || class_exists( 'WPLA_MSRP_Addon' ) ) : ?>
								<option value="2" <?php if ( isset( $item_details['strikethrough_pricing'] ) && $item_details['strikethrough_pricing'] == '2' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'MSRP', 'wp-lister-for-ebay' ); ?></option>
								<?php endif; ?>
							</select>
							<br class="clear" />

                            <label for="wpl-text-map_pricing" class="text_label">
                                <?php echo __( 'MAP Pricing', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('<b>Minimum Advertised Price (MAP)</b><br>If a seller prices an item below the minimum advertised price, applications cannot display the price on any page until the buyer takes further action (such as clicking a button or link).<br/><br/>Minimum Advertised Price is supported on the eBay US site only.', 'wp-lister-for-ebay')) ?>
                            </label>
                            <select id="wpl-text-map_pricing" name="wpl_e2e_map_pricing" class=" required-entry select">
                                <option value="0" <?php selected( $item_details['map_pricing'] ?? 0, 0 ); ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
                                <option value="1" <?php selected( $item_details['map_pricing'] ?? 0, 1 ); ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                            </select>
                            <br class="clear" />

                            <label for="wpl-text-map_exposure" class="text_label">
                                <?php echo __( 'MAP Exposure', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('<b>DuringCheckout</b><br/>The discounted price must be shown on the eBay checkout flow page.<br/><br/><b>None</b><br/>The discount price is not shown via either PreCheckout nor DuringCheckout.<br/><br/><b>PreCheckout</b><br/>The buyer must click a link (or a button) to navigate to a separate page (or window) that displays the discount price. eBay displays the discounted item price in a pop-up window.', 'wp-lister-for-ebay')) ?>
                            </label>
                            <select id="wpl-text-map_exposure" name="wpl_e2e_map_exposure" class=" required-entry select">
                                <option value="DuringCheckout" <?php selected( $item_details['map_exposure'] ?? 'None', 'DuringCheckout' ); ?>><?php echo __( 'DuringCheckout', 'wp-lister-for-ebay' ); ?></option>
                                <option value="None" <?php selected( $item_details['map_exposure'] ?? 'None', 'None' ); ?>><?php echo __( 'None', 'wp-lister-for-ebay' ); ?></option>
                                <option value="PreCheckout" <?php selected( $item_details['map_exposure'] ?? 'None', 'PreCheckout' ); ?>><?php echo __( 'PreCheckout', 'wp-lister-for-ebay' ); ?></option>
                            </select>
                            <br class="clear" />

							<label for="wpl-text-b2b_only" class="text_label">
								<?php echo __( 'B2B only', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('<b>Restrict to Business (B2B)</b><br>Enable this option if you want to offer the item exclusively to business users.<br><br>Applicable only to business sellers residing in Germany, Austria, or Switzerland who are listing in a B2B VAT-enabled category on the eBay DE, AT or CH sites.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-text-b2b_only" name="wpl_e2e_b2b_only" class=" required-entry select">
								<option value="0" <?php if ( !isset( $item_details['b2b_only'] ) || $item_details['b2b_only'] == '0' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
								<option value="1" <?php if ( isset( $item_details['b2b_only'] ) && $item_details['b2b_only'] == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<br class="clear" />

							<label for="wpl-text-ebayplus_enabled" class="text_label">
								<?php echo __( 'eBay Plus', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('This enables your products to be offered via the eBay Plus program.<br><br>eBay Plus is a premium account option for buyers, which provides benefits such as fast free domestic shipping and free returns on selected items. Top-Rated eBay sellers must opt in to eBay Plus to be able to offer the program on qualifying listings. Sellers must commit to next-day delivery of those items.<br><br><b>Note:</b> Currently, eBay Plus is available only to buyers in Germany (DE), but this program is scheduled to come to the Austria and Australia marketplaces in the near future.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-text-ebayplus_enabled" name="wpl_e2e_ebayplus_enabled" class=" required-entry select">
								<option value="0" <?php if ( !isset( $item_details['ebayplus_enabled'] ) || $item_details['ebayplus_enabled'] == '0' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
								<option value="1" <?php if ( isset( $item_details['ebayplus_enabled'] ) && $item_details['ebayplus_enabled'] == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<br class="clear" />

							<label for="wpl-text-sort_order" class="text_label">
								<?php echo __( 'Sort order', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Enter any numeric value to specify a custom sort order for your profiles.<br>Leave this empty to display profiles alphabetically.', 'wp-lister-for-ebay')) ?>
							</label>
							<input type="text" name="wpl_e2e_sort_order" id="wpl-text-sort_order" value="<?php echo esc_attr( $wpl_item['sort_order'] ?? '' ); ?>" class="text_input" />
							<br class="clear" />

						</div>
					</div>

					<?php #if ( ! get_option('wpl_reseller_enable_whitelabel' ) ) : ?>
					<?php if ( ! defined('WPLISTER_RESELLER_VERSION') ) : ?>
					<div class="postbox" id="HelpBox">
						<h3 class="hndle"><span><?php echo __( 'Help', 'wp-lister-for-ebay' ); ?></span></h3>
						<div class="inside">
							<!-- 
							<p> 
								Profiles can be complicated. But you only set it up once - and apply it to as many products as you wish.
							</p>
							-->
							<p>
								<!-- <b>Tip of the Day:</b><br> -->
								You can enter weight mapping as shipping costs
								like this: <br>
								<code>[weight|0:6.75|5:12.5|20:19.95]</code><br>
							</p>
							<p>
								This would set the shipping cost to <br>
								-  6.75 for weight below 5 kg<br>
								- 12.50 for weight above 5 kg<br>
								- 19.95 for weight above 20 kg<br>
							</p>
							<p>
								For more information visit the 
								<a href="https://www.wplab.com/plugins/wp-lister/faq/" target="_blank">FAQ</a>.
							</p>
						</div>
					</div>
					<?php endif; ?>
<script type="text/javascript">
    jQuery(document).ready(function($) {
        const sizemap_el = '<tr><td><input type="text" class="sizemap-wc" name="wpl_e2e_sizemap_wc[]" value="" size="8" /></td><td><input type="text" class="sizemap-ebay" name="wpl_e2e_sizemap_ebay[]" value="" size="8" /> </td> </tr>';
        $("#add_size_row").on("click", function(e) {
            e.preventDefault();

            $('#sizemap tbody#sizemap_body').append( sizemap_el );
        });
    });
</script>


