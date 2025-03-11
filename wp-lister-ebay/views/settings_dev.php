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
    #log_container {
        display: none;
        overflow: auto;
        max-height: 500px;
        border: 1px solid #ccc;
        padding: 5px;
    }

</style>

<div class="wrap wplister-page">
	<div class="icon32" style="background: url(<?php echo $wpl_plugin_url; ?>img/hammer-32x32.png) no-repeat;" id="wpl-icon"><br /></div>
    <?php include_once( dirname(__FILE__).'/settings_tabs.php' ); ?>
    <?php echo $wpl_message ?>

    <!-- <br class="clear"> -->
    <!-- <h2><?php _e( 'Developer Settings', 'wp-lister-for-ebay' ); ?></h2> -->

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
										<p><?php echo __( 'This page contains some special options intended for developers and debugging.', 'wp-lister-for-ebay' ) ?></p>
										<p><?php echo sprintf( __( 'The daily maintenance ran %s ago.', 'wp-lister-for-ebay' ), human_time_diff( get_option('wple_daily_cron_last_run') ) ) ?></p>
									</div>
								</div>

								<div id="major-publishing-actions">
									<div id="publishing-action">
                                        <?php wp_nonce_field( 'wplister_save_devsettings' ); ?>
										<input type="hidden" name="action" value="save_wplister_devsettings" >
										<input type="submit" value="<?php echo __( 'Save Settings', 'wp-lister-for-ebay' ); ?>" id="save_settings" class="button-primary" name="save">
									</div>
									<div class="clear"></div>
								</div>

							</div>

						</div>
					</div>

					<?php if ( ( ! is_multisite() ) || ( is_main_site() ) ) : ?>
					<div class="postbox" id="UpdateSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Beta testers', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">
                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-update_channel" class="text_label"><?php echo __( 'Update channel', 'wp-lister-for-ebay' ); ?></label>
                                <select id="wpl-option-update_channel" name="wpl_e2e_update_channel" title="Update channel" class=" required-entry select">
                                    <option value="stable"  <?php if ( $wpl_update_channel == 'stable'  ): ?>selected="selected"<?php endif; ?>><?php _e('stable', 'wp-lister-for-ebay' );  ?></option>
                                    <option value="beta"    <?php if ( $wpl_update_channel == 'beta'    ): ?>selected="selected"<?php endif; ?>><?php _e('beta', 'wp-lister-for-ebay' );    ?></option>
                                    <option value="nightly" <?php if ( $wpl_update_channel == 'nightly' ): ?>selected="selected"<?php endif; ?>><?php _e('nightly', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <p class="desc">
		                            <?php echo __( 'If you want to test new features before they are released, select the "beta" channel.', 'wp-lister-for-ebay' ); ?>
                                </p>
                            </div>

						</div>
					</div>
					<?php endif; ?>

					<div class="postbox dev_box" id="VersionInfoBox" style="<?php echo defined('WPLISTER_RESELLER_VERSION') ? 'display:none' : ''; ?>">
						<h3 class="hndle"><span><?php echo __( 'Version Info', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">

							<table style="width:100%">
								<tr><td>WP-Lister</td><td>	<?php echo WPLE_PLUGIN_VERSION ?> </td></tr>
								<tr><td>Database</td><td> <?php echo get_option('wplister_db_version') ?> </td></tr>
								<tr><td>WordPress</td><td> <?php global $wp_version; echo $wp_version ?> </td></tr>
								<tr><td>WooCommerce</td><td> <?php echo defined('WC_VERSION') ? WC_VERSION : WOOCOMMERCE_VERSION ?> </td></tr>
								<?php if ( defined('WPLISTER_RESELLER_VERSION') ) : ?>
									<tr><td>Reseller Add-On</td><td> <?php echo WPLISTER_RESELLER_VERSION ?> </td></tr>
								<?php endif; ?>
							</table>

						</div>
					</div>

				</div>
			</div> <!-- #postbox-container-1 -->


			<!-- #postbox-container-2 -->
			<div id="postbox-container-2" class="postbox-container">
				<div class="meta-box-sortables ui-sortable">


					<div class="postbox" id="DbLoggingBox">
						<h3 class="hndle"><span><?php echo __( 'Logging and Maintenance', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">

							<label for="wpl-option-log_to_db" class="text_label">
								<?php echo __( 'Log to database', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('If you have any issues or want support to look into a specific error message from eBay then please enable logging, repeat the steps and send the resulting log record to support.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-option-log_to_db" name="wpl_e2e_option_log_to_db" title="Logging" class=" required-entry select">
								<option value="1" <?php if ( $wpl_option_log_to_db == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
								<option value="0" <?php if ( $wpl_option_log_to_db != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> <?php _e( '(default)', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Enable to log all communication with eBay to the database.', 'wp-lister-for-ebay' ); ?>
							</p>

							<label for="wpl-option-log_days_limit" class="text_label">
								<?php echo __( 'Keep log records for', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Select how long log records should be kept. Older records are removed automatically. The default is 30 days.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-option-log_days_limit" name="wpl_e2e_log_days_limit" class=" required-entry select">
								<option value="7"  <?php if ( $wpl_log_days_limit == '7' ):  ?>selected="selected"<?php endif; ?>><?php _e('7 days', 'wp-lister-for-ebay' ); ?></option>
								<option value="14"  <?php if ( $wpl_log_days_limit == '14' ):  ?>selected="selected"<?php endif; ?>><?php _e('14 days', 'wp-lister-for-ebay' ); ?></option>
								<option value="30"  <?php if ( $wpl_log_days_limit == '30' ):  ?>selected="selected"<?php endif; ?>><?php _e('30 days', 'wp-lister-for-ebay' ); ?> <?php _e( '(default)', 'wp-lister-for-ebay' ); ?></option>
								<option value="60"  <?php if ( $wpl_log_days_limit == '60' ):  ?>selected="selected"<?php endif; ?>><?php _e('60 days', 'wp-lister-for-ebay' ); ?></option>
								<option value="90"  <?php if ( $wpl_log_days_limit == '90' ):  ?>selected="selected"<?php endif; ?>><?php _e('90 days', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Select how long log records should be kept.', 'wp-lister-for-ebay' ); ?>
							</p>

							<label for="wpl-option-orders_days_limit" class="text_label">
								<?php echo __( 'Keep sales data for', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Select how long eBay orders should be kept. Older orders are removed from WP-Lister automatically but will remain in WooCommerce. The default is not to remove sales data at all as WooCommerce sales data is not removed automatically either, but if you prefer to minimize your database footprint we recommend a retention time of 90 days to ensure that all order updates and shipment updates are matched on both platforms.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-option-orders_days_limit" name="wpl_e2e_orders_days_limit" class=" required-entry select">
								<option value=""    <?php if ( $wpl_orders_days_limit == ''   ):  ?>selected="selected"<?php endif; ?>><?php _e('forever', 'wp-lister-for-ebay' ); ?> <?php _e( '(default)', 'wp-lister-for-ebay' ); ?></option>
                                <?php wple_render_pro_select_option( 14, __('14 days', 'wp-lister-for-ebay'), $wpl_orders_days_limit == 14 ); ?>
								<option value="30"  <?php if ( $wpl_orders_days_limit == '30' ):  ?>selected="selected"<?php endif; ?>><?php _e( '30 days', 'wp-lister-for-ebay' ); ?></option>
								<option value="60"  <?php if ( $wpl_orders_days_limit == '60' ):  ?>selected="selected"<?php endif; ?>><?php _e( '60 days', 'wp-lister-for-ebay' ); ?></option>
								<option value="90"  <?php if ( $wpl_orders_days_limit == '90' ):  ?>selected="selected"<?php endif; ?>><?php _e( '90 days', 'wp-lister-for-ebay' ); ?> <?php _e( '(recommended)', 'wp-lister-for-ebay' ); ?></option>
								<option value="180" <?php if ( $wpl_orders_days_limit == '180' ): ?>selected="selected"<?php endif; ?>><?php _e( '180 days', 'wp-lister-for-ebay' ); ?></option>
								<option value="365" <?php if ( $wpl_orders_days_limit == '365' ): ?>selected="selected"<?php endif; ?>><?php _e( '1 year', 'wp-lister-for-ebay' ); ?></option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Select how long eBay orders should be kept.', 'wp-lister-for-ebay' ); ?>
							</p>

							<label for="wpl-archive_days_limit" class="text_label">
								<?php echo __( 'Keep archived items for', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Select how long archived listings should be kept. Older records are removed automatically. The default is 90 days.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-archive_days_limit" name="wpl_e2e_archive_days_limit" class=" required-entry select">
								<option value="7"  <?php if ( $wpl_archive_days_limit == '7' ):  ?>selected="selected"<?php endif; ?>><?php _e('7 days', 'wp-lister-for-ebay'); ?></option>
								<option value="14"  <?php if ( $wpl_archive_days_limit == '14' ):  ?>selected="selected"<?php endif; ?>><?php _e('14 days', 'wp-lister-for-ebay'); ?></option>
								<option value="30"  <?php if ( $wpl_archive_days_limit == '30' ):  ?>selected="selected"<?php endif; ?>><?php _e('30 days', 'wp-lister-for-ebay'); ?></option>
								<option value="60"  <?php if ( $wpl_archive_days_limit == '60' ):  ?>selected="selected"<?php endif; ?>><?php _e('60 days', 'wp-lister-for-ebay'); ?></option>
								<option value="90"  <?php if ( $wpl_archive_days_limit == '90' ):  ?>selected="selected"<?php endif; ?>><?php _e('90 days', 'wp-lister-for-ebay'); ?> <?php _e('(default)', 'wp-lister-for-ebay'); ?></option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Select how long archived listings should be kept.', 'wp-lister-for-ebay' ); ?>
							</p>

						</div>
					</div>

					<div class="postbox" id="NotifactionSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Monitoring and Notifications', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">
                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-enable_order_notify" class="text_label">
		                            <?php echo __( 'Notify on order irregularities', 'wp-lister-for-ebay' ); ?>
                                </label>
                                <select id="wpl-option-enable_order_notify" name="wpl_e2e_enable_order_notify" class=" required-entry select">
                                    <option value="1" <?php if ( $wpl_enable_order_notify == 1 ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?> <?php _e('(recommended)', 'wp-lister-for-ebay'); ?></option>
                                    <option value="0" <?php if ( $wpl_enable_order_notify == 0 ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> <?php _e('(default)', 'wp-lister-for-ebay'); ?></option>
                                </select>
                                <p class="desc">
		                            <?php echo __( 'To make sure you get notified in case there is an issue that WP-Lister can not fix itself, you should enable notifications.', 'wp-lister-for-ebay' ); ?><br>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-notify_custom_email" class="text_label">
		                            <?php echo __( 'Send notifications to', 'wp-lister-for-ebay' ) ?>
		                            <?php $tip_msg  = __( 'Enter your email address, or multiple email addresses separated by comma.', 'wp-lister-for-ebay' ); ?>
		                            <?php wplister_tooltip($tip_msg) ?>
                                </label>
                                <input type="text" name="wpl_e2e_notify_custom_email" id="wpl-notify_custom_email" value="<?php echo esc_attr($wpl_notify_custom_email); ?>" class="text_input" placeholder="<?php esc_attr_e( get_bloginfo('admin_email' ) ); ?>" />
                                <p class="desc" style="display: block;">
		                            <?php _e('Enter the email where the report will be sent to.', 'wp-lister-for-ebay'); ?>
                                </p>
                            </div>
						</div>
					</div>

					<div class="postbox" id="StagingSiteSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Staging site', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">

							<p>
								<?php echo __( 'If you frequently clone your WordPress installation to a staging site, you can make WP-Lister automatically disable background updates and order creation when running on the staging site.', 'wp-lister-for-ebay' ); ?>
								<?php echo __( 'Enter a unique part of your staging domain below to activate this feature.', 'wp-lister-for-ebay' ); ?><br>
							</p>
							<label for="wpl-staging_site_pattern" class="text_label">
								<?php echo __( 'Staging site pattern', 'wp-lister-for-ebay' ) ?>
								<?php $tip_msg  = __( 'You do not need to enter the full domain name of your staging site.', 'wp-lister-for-ebay' ); ?>
								<?php $tip_msg .= __( 'If your staging domain is mydomain.staging.wpengine.com enter staging.wpengine.com as a general pattern.', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip($tip_msg) ?>
							</label>
							<input type="text" name="wpl_e2e_staging_site_pattern" id="wpl-staging_site_pattern" value="<?php echo esc_attr($wpl_staging_site_pattern); ?>" class="text_input" />
							<p class="desc" style="display: block;">
								Example: staging.wpengine.com
							</p>

						</div>
					</div>

					<div class="postbox" id="ErrorHandlingBox">
						<h3 class="hndle"><span><?php echo __( 'Debug options', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">

							<p>
								<?php echo __( 'Warning: These options are for debugging purposes only. Please do not change them unless our support told you to do so.', 'wp-lister-for-ebay' ); ?>
							</p>

							<label for="wpl-option-php_error_handling" class="text_label">
								<?php echo __( 'PHP error handling', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Please leave this set to Production unless told otherwise by support.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-option-php_error_handling" name="wpl_e2e_php_error_handling" class=" required-entry select">
								<option value="0" <?php if ( $wpl_php_error_handling == '0' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Production Mode', 'wp-lister-for-ebay' ); ?> (default)</option>
								<option value="9" <?php if ( $wpl_php_error_handling == '9' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Production Mode', 'wp-lister-for-ebay' ); ?> (forced)</option>
								<option value="1" <?php if ( $wpl_php_error_handling == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Show all errors inline', 'wp-lister-for-ebay' ); ?></option>
								<option value="2" <?php if ( $wpl_php_error_handling == '2' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Show fatal errors on shutdown', 'wp-lister-for-ebay' ); ?></option>
								<option value="3" <?php if ( $wpl_php_error_handling == '3' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Show errors inline and on shutdown', 'wp-lister-for-ebay' ); ?></option>
								<option value="6" <?php if ( $wpl_php_error_handling == '6' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Show fatal and non-fatal errors on shutdown', 'wp-lister-for-ebay' ); ?></option>
							</select>

							<label for="wpl-option-ajax_error_handling" class="text_label">
								<?php echo __( 'AJAX error handling', 'wp-lister-for-ebay' ); ?>
								<?php $tip_msg = __( '404 errors for admin-ajax.php should actually never happen and are generally a sign of incorrect server configuration.', 'wp-lister-for-ebay' ) .' '. __('This setting is just a workaround. You should consider moving to a proper hosting provider instead.', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip($tip_msg) ?>
							</label>
							<select id="wpl-option-ajax_error_handling" name="wpl_e2e_ajax_error_handling" class=" required-entry select">
								<option value="halt" <?php if ( $wpl_ajax_error_handling == 'halt' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Halt on error', 'wp-lister-for-ebay' ); ?> <?php _e('(default)', 'wp-lister-for-ebay'); ?></option>
								<option value="skip" <?php if ( $wpl_ajax_error_handling == 'skip' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Continue with next item', 'wp-lister-for-ebay' ); ?></option>
								<option value="retry" <?php if ( $wpl_ajax_error_handling == 'retry' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Try again', 'wp-lister-for-ebay' ); ?></option>
							</select>

							<label for="wpl-option-eps_xfer_mode" class="text_label">
								<?php echo __( 'EPS transfer mode', 'wp-lister-for-ebay' ); ?>
								<?php $tip_msg = __( 'If you have trouble uploading your images to EPS (eBay Picture Service), set the transfer mode to active.', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip($tip_msg) ?>
							</label>
							<select id="wpl-option-eps_xfer_mode" name="wpl_e2e_eps_xfer_mode" class=" required-entry select">
								<option value="passive" <?php if ( $wpl_eps_xfer_mode == 'passive' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Passive', 'wp-lister-for-ebay' ); ?> <?php _e('(default)', 'wp-lister-for-ebay'); ?></option>
								<option value="active"  <?php if ( $wpl_eps_xfer_mode == 'active'  ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Active', 'wp-lister-for-ebay' ); ?></option>
							</select>

							<label for="wpl-option-disable_variations" class="text_label">
								<?php echo __( 'Disable variations', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('This is intended to work around an issue with the eBay API and will force using AddItem instead of AddFixedPriceItem, RelistItem instead of RelistFixedPriceItem, etc.<br>Do not enable this unless you do not want to list variations!', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-option-disable_variations" name="wpl_e2e_disable_variations" class=" required-entry select">
								<option value="0" <?php if ( $wpl_disable_variations == '0' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> <?php _e('(default)', 'wp-lister-for-ebay'); ?></option>
								<option value="1" <?php if ( $wpl_disable_variations == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
							</select>

							<label for="wpl-option-disable_compat_list" class="text_label">
								<?php echo __( 'Disable parts compatibility', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('This is intended to work around an issue with the eBay API and will omit Parts Compatibility Lists from being submitted to eBay when an item is listed or revised.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-option-disable_compat_list" name="wpl_e2e_disable_compat_list" class=" required-entry select">
								<option value="0" <?php if ( $wpl_disable_compat_list == '0' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> <?php _e('(default)', 'wp-lister-for-ebay'); ?></option>
								<option value="1" <?php if ( $wpl_disable_compat_list == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
							</select>

							<label for="wpl-option-enable_item_edit_link" class="text_label">
								<?php echo __( 'Allow direct editing', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Shows an additional "edit" link on the listing page, which allows you to edit the listing database fields directly. It is not recommended to use this option at all - all your changes will be overwritten when the linked product is updated again!', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-option-enable_item_edit_link" name="wpl_e2e_enable_item_edit_link" class=" required-entry select">
								<option value="0" <?php if ( $wpl_enable_item_edit_link == '0' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> <?php _e('(default)', 'wp-lister-for-ebay'); ?></option>
								<option value="1" <?php if ( $wpl_enable_item_edit_link == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
							</select>

							<label for="wpl-option-force_table_items_limit" class="text_label">
								<?php echo __( 'Limit displayed items', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('If you can not open the listings or orders page or receive a timeout error when doing so, you can use this option to temporarily limit the maxmimum number of displayed listings or orders.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-option-force_table_items_limit" name="wpl_e2e_force_table_items_limit" class=" required-entry select">
								<option value=""><?php echo __( 'No limit', 'wp-lister-for-ebay' ); ?> <?php _e('(default)', 'wp-lister-for-ebay'); ?> </option>
								<option value="1" <?php if ( $wpl_force_table_items_limit == '1' ): ?>selected="selected"<?php endif; ?>><?php _e('1 item', 'wp-lister-for-ebay'); ?></option>
								<option value="2" <?php if ( $wpl_force_table_items_limit == '2' ): ?>selected="selected"<?php endif; ?>><?php _e('2 items', 'wp-lister-for-ebay'); ?></option>
								<option value="3" <?php if ( $wpl_force_table_items_limit == '3' ): ?>selected="selected"<?php endif; ?>><?php _e('3 items', 'wp-lister-for-ebay'); ?></option>
								<option value="5" <?php if ( $wpl_force_table_items_limit == '5' ): ?>selected="selected"<?php endif; ?>><?php _e('5 items', 'wp-lister-for-ebay'); ?></option>
								<option value="10" <?php if ( $wpl_force_table_items_limit == '10' ): ?>selected="selected"<?php endif; ?>><?php _e('10 items', 'wp-lister-for-ebay'); ?></option>
							</select>

							<label for="wpl-option-apply_profile_batch_size" class="text_label">
								<?php echo __( 'Apply profiles in batches of', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('If your server times out or runs out of memory when applying a profile (or template) to a huge number of items you may have to lower this setting.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-option-apply_profile_batch_size" name="wpl_e2e_apply_profile_batch_size" class=" required-entry select">
								<option value="20"   <?php if ( $wpl_apply_profile_batch_size == '20'   ): ?>selected="selected"<?php endif; ?>><?php _e('20 items', 'wp-lister-for-ebay'); ?></option>
								<option value="50"   <?php if ( $wpl_apply_profile_batch_size == '50'   ): ?>selected="selected"<?php endif; ?>><?php _e('50 items', 'wp-lister-for-ebay'); ?></option>
								<option value="100"  <?php if ( $wpl_apply_profile_batch_size == '100'  ): ?>selected="selected"<?php endif; ?>><?php _e('100 items', 'wp-lister-for-ebay'); ?></option>
								<option value="200"  <?php if ( $wpl_apply_profile_batch_size == '200'  ): ?>selected="selected"<?php endif; ?>><?php _e('200 items', 'wp-lister-for-ebay'); ?></option>
								<option value="500"  <?php if ( $wpl_apply_profile_batch_size == '500'  ): ?>selected="selected"<?php endif; ?>><?php _e('500 items', 'wp-lister-for-ebay'); ?></option>
								<option value="1000" <?php if ( $wpl_apply_profile_batch_size == '1000' ): ?>selected="selected"<?php endif; ?>><?php _e('1000 items', 'wp-lister-for-ebay'); ?> <?php _e('(default)', 'wp-lister-for-ebay'); ?></option>
							</select>

							<label for="wpl-option-inventory_check_batch_size" class="text_label">
								<?php echo __( 'Check inventory in batches of', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('If your server times out or runs out of memory when using the inventory check tool you may have to lower this setting.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-option-inventory_check_batch_size" name="wpl_e2e_inventory_check_batch_size" class=" required-entry select">
								<option value="20"   <?php if ( $wpl_inventory_check_batch_size == '20'   ): ?>selected="selected"<?php endif; ?>><?php _e('20 items', 'wp-lister-for-ebay'); ?></option>
								<option value="50"   <?php if ( $wpl_inventory_check_batch_size == '50'   ): ?>selected="selected"<?php endif; ?>><?php _e('50 items', 'wp-lister-for-ebay'); ?></option>
								<option value="100"  <?php if ( $wpl_inventory_check_batch_size == '100'  ): ?>selected="selected"<?php endif; ?>><?php _e('100 items', 'wp-lister-for-ebay'); ?></option>
								<option value="200"  <?php if ( $wpl_inventory_check_batch_size == '200'  ): ?>selected="selected"<?php endif; ?>><?php _e('200 items', 'wp-lister-for-ebay'); ?> <?php _e('(default)', 'wp-lister-for-ebay'); ?></option>
								<option value="500"  <?php if ( $wpl_inventory_check_batch_size == '500'  ): ?>selected="selected"<?php endif; ?>><?php _e('500 items', 'wp-lister-for-ebay'); ?></option>
							</select>

							<label for="wpl-option-fetch_orders_page_size" class="text_label">
								<?php echo __( 'Limit scheduled order fetching', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('If your server times out or runs out of memory when fetching orders from eBay you may have to lower this setting.<br><br>This only applies to the automatic background process of fetching orders. It has no effect on fetching orders manually from the Orders page.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-option-fetch_orders_page_size" name="wpl_e2e_fetch_orders_page_size" class=" required-entry select">
								<option value="25"   <?php if ( $wpl_fetch_orders_page_size == '25'   ): ?>selected="selected"<?php endif; ?>><?php _e('25 orders', 'wp-lister-for-ebay'); ?></option>
								<option value="50"   <?php if ( $wpl_fetch_orders_page_size == '50'   ): ?>selected="selected"<?php endif; ?>><?php _e('50 orders', 'wp-lister-for-ebay'); ?> <?php _e('(default)', 'wp-lister-for-ebay'); ?></option>
								<option value="75"   <?php if ( $wpl_fetch_orders_page_size == '75'   ): ?>selected="selected"<?php endif; ?>><?php _e('75 orders', 'wp-lister-for-ebay'); ?></option>
								<option value="100"  <?php if ( $wpl_fetch_orders_page_size == '100'  ): ?>selected="selected"<?php endif; ?>><?php _e('100 orders', 'wp-lister-for-ebay'); ?></option>
							</select>

							<label for="wpl-option-grid_page_size" class="text_label">
								<?php echo __( 'Grid Editor page size', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Set the maximum number of items to load into the grid editor at once.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-option-grid_page_size" name="wpl_e2e_grid_page_size" class=" required-entry select">
								<option value="100"    <?php if ( $wpl_grid_page_size == '100'    ): ?>selected="selected"<?php endif; ?>><?php _e('100 items', 'wp-lister-for-ebay'); ?></option>
								<option value="200"    <?php if ( $wpl_grid_page_size == '200'    ): ?>selected="selected"<?php endif; ?>><?php _e('200 items', 'wp-lister-for-ebay'); ?></option>
								<option value="500"    <?php if ( $wpl_grid_page_size == '500'    ): ?>selected="selected"<?php endif; ?>><?php _e('500 items', 'wp-lister-for-ebay'); ?></option>
								<option value="1000"   <?php if ( $wpl_grid_page_size == '1000'   ): ?>selected="selected"<?php endif; ?>><?php _e('1000 items', 'wp-lister-for-ebay'); ?></option>
								<option value="2000"   <?php if ( $wpl_grid_page_size == '2000'   ): ?>selected="selected"<?php endif; ?>><?php _e('2000 items', 'wp-lister-for-ebay'); ?></option>
								<option value="5000"   <?php if ( $wpl_grid_page_size == '5000'   ): ?>selected="selected"<?php endif; ?>><?php _e('5000 items', 'wp-lister-for-ebay'); ?></option>
								<option value="10000"  <?php if ( $wpl_grid_page_size == '10000'  ): ?>selected="selected"<?php endif; ?>><?php _e('10000 items', 'wp-lister-for-ebay'); ?> <?php _e('(default)', 'wp-lister-for-ebay'); ?></option>
							</select>

                            <label for="wpl-option-disable_profile_popup_errors" class="text_label">
                                <?php _e( 'Disable profile pop-up errors', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip( __('Disable the errors that pop up when saving a profile', 'wp-lister-for-ebay') ); ?>
                            </label>
                            <select id="wpl-option-disable_profile_popup_errors" name="wpl_e2e_disable_profile_popup_errors" class=" required-entry select">
                                <option value="0" <?php selected( $wpl_disable_profile_popup_errors, 0 ); ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> <?php _e('(default)', 'wp-lister-for-ebay'); ?></option>
                                <option value="1" <?php selected( $wpl_disable_profile_popup_errors, 1 ); ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                            </select>

                            <label for="wpl-option-item_specifics_cache" class="text_label">
                                <?php _e( 'Disable Item Specifics Cache', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip( __('Disable the cache to force WP-Lister to pull the item specifics from eBay.', 'wp-lister-for-ebay') ); ?>
                            </label>
                            <select id="wpl-option-item_specifics_cache" name="wpl_e2e_disable_item_specifics_cache" class=" required-entry select">
                                <option value="0" <?php selected( $wpl_disable_item_specifics_cache, 0 ); ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> <?php _e('(default)', 'wp-lister-for-ebay'); ?></option>
                                <option value="1" <?php selected( $wpl_disable_item_specifics_cache, 1 ); ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
                            </select>

                            <label for="wpl-option-item_specifics_limit" class="text_label">
                                <?php _e( 'Item Specifics Limit', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip( __('Set the limit of the number of item specifics to pull from eBay', 'wp-lister-for-ebay') ); ?>
                            </label>
                            <input type="number" min="0" value="<?php echo esc_attr( $wpl_item_specifics_limit ); ?>" placeholder="0" id="wpl-option-item_specifics_limit" name="wpl_e2e_item_specifics_limit" class="text_input" />

                            <label for="wpl-option-revise_all_listings_limit" class="text_label">
                                <?php _e( 'Revise Changed Items Limit', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip( __('Add a limit to the Revise All Changed Listings action if you are getting errors due to the large amount of changed listings to process.', 'wp-lister-for-ebay') ); ?>
                            </label>
                            <input type="number" min="0" value="<?php echo esc_attr( $wpl_revise_all_listings_limit ); ?>" placeholder="0" id="wpl-option-revise_all_listings_limit" name="wpl_e2e_revise_all_listings_limit" class="text_input" />

							<label for="wpl-option-log_record_limit" class="text_label">
								<?php echo __( 'Log entry size limit', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Limit the maximum size of a single log record. The default value is 4k.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-option-log_record_limit" name="wpl_e2e_log_record_limit" class=" required-entry select">
								<option value="4096"  <?php if ( $wpl_log_record_limit == '4096' ):  ?>selected="selected"<?php endif; ?>>4 kb <?php _e('(default)', 'wp-lister-for-ebay'); ?></option>
								<option value="8192"  <?php if ( $wpl_log_record_limit == '8192' ):  ?>selected="selected"<?php endif; ?>>8 kb</option>
								<option value="64000" <?php if ( $wpl_log_record_limit == '64000' ): ?>selected="selected"<?php endif; ?>>64 kb</option>
							</select>

							<label for="wpl-option-xml_formatter" class="text_label">
								<?php echo __( 'XML Beautifier', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Select which XML formatter should be used to display log records. If the default settings does not work, switch to the built in formatter.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-option-xml_formatter" name="wpl_e2e_xml_formatter" class=" required-entry select">
								<option value="default" <?php if ( $wpl_xml_formatter == 'default' ): ?>selected="selected"<?php endif; ?>><?php _e('auto detect', 'wp-lister-for-ebay'); ?></option>
								<option value="custom"  <?php if ( $wpl_xml_formatter == 'custom' ):  ?>selected="selected"<?php endif; ?>><?php _e('force built in XML formatter', 'wp-lister-for-ebay'); ?> <?php _e('(default)', 'wp-lister-for-ebay'); ?></option>
							</select>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-multi_threading_limit" class="text_label">
		                            <?php _e( 'Multithreading Limit', 'wp-lister-for-ebay' ); ?>
		                            <?php wplister_tooltip( __('Use multithreading to process multiple requests simultaneously to speed up the process of verifying or revising listings or when updating the listing status from eBay.<br>Up to 10 simultaneous threads are allowed.', 'wp-lister-for-ebay') ); ?>
                                </label>
                                <input type="number" min="1" max="10" value="<?php echo esc_attr( $wpl_multi_threading_limit ); ?>" placeholder="1" id="wpl-option-multi_threading_limit" name="wpl_e2e_multi_threading_limit" class="text_input" />
                            </div>

						</div>
					</div>

					<div class="postbox dev_box" id="DeveloperToolBox" style="display:none;">
						<h3 class="hndle"><span><?php echo __( 'Developer options', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">

							<label for="wpl-text-log_level" class="text_label"><?php echo __( 'Log to logfile', 'wp-lister-for-ebay' ); ?></label>
							<select id="wpl-text-log_level" name="wpl_e2e_text_log_level" title="Logging" class=" required-entry select">
								<option value=""> -- <?php echo __( 'no logfile', 'wp-lister-for-ebay' ); ?> -- </option>
								<option value="2" <?php if ( $wpl_text_log_level == '2' ): ?>selected="selected"<?php endif; ?>>Error</option>
								<option value="3" <?php if ( $wpl_text_log_level == '3' ): ?>selected="selected"<?php endif; ?>>Critical</option>
								<option value="4" <?php if ( $wpl_text_log_level == '4' ): ?>selected="selected"<?php endif; ?>>Warning</option>
								<option value="5" <?php if ( $wpl_text_log_level == '5' ): ?>selected="selected"<?php endif; ?>>Notice</option>
								<option value="6" <?php if ( $wpl_text_log_level == '6' ): ?>selected="selected"<?php endif; ?>>Info</option>
								<option value="7" <?php if ( $wpl_text_log_level == '7' ): ?>selected="selected"<?php endif; ?>>Debug</option>
								<option value="9" <?php if ( $wpl_text_log_level == '9' ): ?>selected="selected"<?php endif; ?>>All</option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Write debug information to logfile.', 'wp-lister-for-ebay' ); ?>
								<?php if ( $wpl_text_log_level > 1 ): ?>
									<?php $wpl_log_size = file_exists( WPLE()->logger->file ) ? filesize( WPLE()->logger->file ) : 0; ?>
									<?php echo __( 'Current log file size', 'wp-lister-for-ebay' ); ?>: <?php echo round($wpl_log_size/1024/1024,1) ?> mb
								<?php endif; ?>
							</p>

							<label for="wpl-option-log_include_authinfo" class="text_label">
								<?php echo __( 'Include auth debug info in log', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('Collect additional debug information when the database log option is enabled.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-option-log_include_authinfo" name="wpl_e2e_log_include_authinfo" class=" required-entry select">
								<option value="0" <?php if ( $wpl_log_include_authinfo == '0' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Disabled', 'wp-lister-for-ebay' ); ?> (default)</option>
								<option value="1" <?php if ( $wpl_log_include_authinfo == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Enabled', 'wp-lister-for-ebay' ); ?></option>
							</select>

							<label for="wpl-wple_instance" class="text_label"><?php echo __( 'Instance ID', 'wp-lister-for-ebay' ); ?></label>
							<input type="text" name="wpl_e2e_wple_instance" id="wpl-wple_instance" value="<?php echo esc_attr(get_option('wple_instance')) ?>" class="text_input" />
							<p class="desc" style="display: block;">
								Don't change this unless you migrated your installation to a different domain.
							</p>

						</div>
					</div>

                    <div class="postbox dev_box" id="DeveloperToolBox" style="display:none;">
                        <h3 class="hndle"><span><?php echo __( 'Logs', 'wp-lister-for-ebay' ) ?></span></h3>
                        <div class="inside">
                            <label for="wpl-text-log_file" class="text_label"><?php echo __( 'Log file', 'wp-lister-for-ebay' ); ?></label>
                            <select id="log_file" class=" required-entry select" style="width: auto;">
                                <option value="" selected>-- Select a log file --</option>
                                <?php foreach ( $wpl_log_files as $file ): ?>
                                <option name="<?php esc_attr_e( basename( $file ) ); ?>"><?php echo basename( $file ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="button" id="view_log"><?php _e( 'Download Log', 'wp-lister-for-ebay' ); ?></button>
                            <button type="button" class="button" id="delete_log"><?php _e( 'Delete', 'wp-lister-for-ebay' ); ?></button>
                        </div>
                    </div>


				</div> <!-- .meta-box-sortables -->
			</div> <!-- #postbox-container-1 -->



		</div> <!-- #post-body -->
		<br class="clear">
	</div> <!-- #poststuff -->

	</form>
    <script>
        jQuery( document ).on("ready", function() {
            jQuery('#view_log').on('click', function() {
                const log_file = jQuery('#log_file').val();
                document.location = ajaxurl + "?action=wple_download_log_file&file="+ log_file +"&_wpnonce=<?php echo wp_create_nonce('wple_download_log_file'); ?>";
            });
        });
    </script>

</div>
