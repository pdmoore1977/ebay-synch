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

	<div id="poststuff">
		<div id="post-body" class="metabox-holder columns-2">

			<div id="postbox-container-1" class="postbox-container">
				<div id="side-sortables" class="meta-box">


					<!-- first sidebox -->
					<div class="postbox" id="submitdiv">
						<!--<div title="Click to toggle" class="handlediv"><br></div>-->
						<h3 class="hndle"><span><?php echo __( 'Sync Status', 'wp-lister-for-ebay' ); ?></span></h3>
						<div class="inside">

							<div id="submitpost" class="submitbox">

								<div id="misc-publishing-actions">
									<div class="misc-pub-section">
									<?php if ( empty( WPLE()->accounts ) ): ?>
										<p><?php echo __( 'No eBay account has been set up yet.', 'wp-lister-for-ebay' ) ?></p>
									<?php elseif ( $wpl_option_cron_auctions && $wpl_option_handle_stock ): ?>
										<p><?php echo __( 'Sync is enabled.', 'wp-lister-for-ebay' ) ?></p>
										<p><?php echo __( 'Sales will be synchronized between WooCommerce and eBay.', 'wp-lister-for-ebay' ) ?></p>
									<?php elseif ( WPLE_IS_LITE_VERSION ): ?>
										<p><?php echo __( 'Sync is not available in WP-Lister Lite.', 'wp-lister-for-ebay' ) ?></p>
										<p><?php echo __( 'To synchronize sales across eBay and WooCommerce you need to upgrade to WP-Lister Pro.', 'wp-lister-for-ebay' ) ?></p>
									<?php else: ?>
										<p><?php echo __( 'Sync is currently disabled.', 'wp-lister-for-ebay' ) ?></p>
										<p><?php echo __( 'eBay and WooCommerce sales will not be synchronized!', 'wp-lister-for-ebay' ) ?></p>
									<?php endif; ?>
									</div>
								</div>

								<div id="major-publishing-actions">

									<div id="publishing-action">
										<input type="submit" value="<?php echo __( 'Save Settings', 'wp-lister-for-ebay' ); ?>" id="save_settings" class="button-primary" name="save">
									</div>
									<div class="clear"></div>
								</div>

							</div>

						</div>
					</div>

					<?php if ( $wpl_is_staging_site ) : ?>
					<div class="postbox" id="StagingSiteBox">
						<h3 class="hndle"><span><?php echo __( 'Staging Site', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">
							<p>
								<span style="color:darkred; font-weight:bold">
									Note: Automatic background updates and order creation have been disabled on this staging site.
								</span>
							</p>
						</div>
					</div>
					<?php endif; ?>

					<?php if ( get_option( 'wplister_cron_auctions' ) ) : ?>
					<div class="postbox" id="UpdateScheduleBox">
						<h3 class="hndle"><span><?php echo __( 'Update Schedule', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">

							<p>
							<?php if ( wp_next_scheduled( 'wplister_update_auctions' ) ) : ?>
								<?php echo __( 'Next scheduled update', 'wp-lister-for-ebay' ); ?> 
								<?php echo human_time_diff( wp_next_scheduled( 'wplister_update_auctions' ), current_time('timestamp',1) ) ?>
								<?php echo wp_next_scheduled( 'wplister_update_auctions' ) < current_time('timestamp',1) ? 'ago' : '' ?>
							<?php elseif ( $wpl_option_cron_auctions == 'external' ) : ?>
								<?php echo __( 'Background updates are handled by an external cron job.', 'wp-lister-for-ebay' ); ?> 
								<a href="#TB_inline?height=420&width=900&inlineId=cron_setup_instructions" class="thickbox">
									<?php echo __( 'Details', 'wp-lister-for-ebay' ); ?>
								</a>

								<div id="cron_setup_instructions" style="display: none;">
									<h2>
										<?php echo __( 'How to set up an external cron job', 'wp-lister-for-ebay' ); ?>
									</h2>
									<p>
										<?php echo __( 'Luckily, you don\'t have to be a server admin to set up an external cron job.', 'wp-lister-for-ebay' ); ?>
										<?php echo __( 'You can ask your server admin to set up a cron job on your own server - or use a 3rd party web based cron service, which provides a user friendly interface and additional features for a small annual fee.', 'wp-lister-for-ebay' ); ?>
									</p>

									<h3>
										<?php echo __( 'Option A: Web cron service', 'wp-lister-for-ebay' ); ?>
									</h3>
									<p>
										<?php $ec_link = '<a href="https://www.easycron.com/" target="_blank">www.easycron.com</a>' ?>
										<?php echo sprintf( __( 'The easiest way to set up a cron job is to sign up with %s and use the following URL to create a new task.', 'wp-lister-for-ebay' ), $ec_link ); ?><br>
									</p>
									<code>
										<?php echo bloginfo('url') ?>/wp-admin/admin-ajax.php?action=wplister_run_scheduled_tasks
									</code>

									<h3>
										<?php echo __( 'Option B: Server cron job', 'wp-lister-for-ebay' ); ?>
									</h3>
									<p>
										<?php echo __( 'If you prefer to set up a cron job on your own server you can create a cron job that will execute the following command:', 'wp-lister-for-ebay' ); ?>
									</p>

									<code style="font-size:0.8em;">
										wget -q -O - <?php echo bloginfo('url') ?>/wp-admin/admin-ajax.php?action=wplister_run_scheduled_tasks >/dev/null 2>&1
									</code>

									<p>
										<?php echo __( 'Note: Your cron job should run at least every 15 minutes but not more often than every 5 minutes.', 'wp-lister-for-ebay' ); ?>
									</p>
								</div>

							<?php else: ?>
								<span style="color:darkred; font-weight:bold">
									Warning: Update schedule is disabled.
								</span></p><p>
								Please click the "Save Settings" button above in order to reset the update schedule.
							<?php endif; ?>
							</p>

							<?php if ( get_option('wplister_cron_last_run') ) : ?>
							<p>
								<?php echo __( 'Last run', 'wp-lister-for-ebay' ); ?>: 
								<?php echo human_time_diff( get_option('wplister_cron_last_run'), current_time('timestamp',1) ) ?> ago
							</p>
							<?php endif; ?>

						</div>
					</div>
					<?php endif; ?>

				</div>
			</div> <!-- #postbox-container-1 -->


			<!-- #postbox-container-2 -->
			<div id="postbox-container-2" class="postbox-container">
				<div class="meta-box-sortables ui-sortable">
					
				<form method="post" id="settingsForm" action="<?php echo $wpl_form_action; ?>">
                    <?php wp_nonce_field( 'wplister_save_settings' ); ?>
					<input type="hidden" name="action" value="save_wplister_settings" >

					<div class="postbox" id="UpdateOptionBox">
						<h3 class="hndle"><span><?php echo __( 'Background Tasks', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">
							<!-- <p><?php echo __( 'Enable to update listings and transactions using WP-Cron.', 'wp-lister-for-ebay' ); ?></p> -->

							<label for="wpl-option-cron_auctions" class="text_label">
								<?php echo __( 'Update interval', 'wp-lister-for-ebay' ) ?>
                                <?php wplister_tooltip(__('Select how often WP-Lister should run background jobs like checking for new sales on eBay, fetching messages, updating ended items, processing items scheduled for auto relist, etc.<br><br>It is recommended to use an external cron job or set this interval to 5 - 15 minutes.<br><br>Setting the update interval to <i>manually</i> will disable all background tasks and should only be used for testing and debugging but never on a live production site.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-option-cron_auctions" name="wpl_e2e_option_cron_auctions" class=" required-entry select">
                                <?php
                                wple_render_pro_select_option( 'five_min', __('5 min.', 'wp-lister-for-ebay'), $wpl_option_cron_auctions == 'five_min' );
                                wple_render_pro_select_option( 'ten_min', __('15 min.', 'wp-lister-for-ebay'), $wpl_option_cron_auctions == 'ten_min' );
                                ?>
								<option value="fifteen_min" <?php if ( $wpl_option_cron_auctions == 'fifteen_min' ): ?>selected="selected"<?php endif; ?>><?php echo __( '15 min.', 'wp-lister-for-ebay' ) ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="thirty_min"  <?php if ( $wpl_option_cron_auctions == 'thirty_min'  ): ?>selected="selected"<?php endif; ?>><?php echo __( '30 min.', 'wp-lister-for-ebay' ) ?></option>
								<option value="hourly"      <?php if ( $wpl_option_cron_auctions == 'hourly'      ): ?>selected="selected"<?php endif; ?>><?php echo __( 'hourly', 'wp-lister-for-ebay' ) ?></option>
								<option value="daily"       <?php if ( $wpl_option_cron_auctions == 'daily'       ): ?>selected="selected"<?php endif; ?>><?php echo __( 'daily', 'wp-lister-for-ebay' ) ?> (<?php _e('not recommended', 'wp-lister-for-ebay' ) ?>)</option>
								<option value=""            <?php if ( $wpl_option_cron_auctions == ''            ): ?>selected="selected"<?php endif; ?>><?php echo __( 'manually', 'wp-lister-for-ebay' ) ?> (<?php _e('not recommended', 'wp-lister-for-ebay' ) ?>)</option>
								<option value="external"    <?php if ( $wpl_option_cron_auctions == 'external'    ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Use external cron job', 'wp-lister-for-ebay' ) ?> (<?php _e('recommended', 'wp-lister-for-ebay' ) ?>)</option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Select how often to run background jobs, like checking for new sales on eBay.', 'wp-lister-for-ebay' ); ?>
							</p>

                            <label for="wpl-background_revisions" class="text_label">
                                <?php echo __( 'Push changes to eBay', 'wp-lister-for-ebay' ) ?>
                                <?php wplister_tooltip(__('With this option enabled, WP-Lister will periodically scan for and automatically revise changed listings in the background.<br><br>With this option turned off, only <i>locked</i> listings will be revised automatically. All other items will stay "changed" when modified, until they are manually revised by the user.', 'wp-lister-for-ebay')) ?>
                            </label>
                            <select id="wpl-background_revisions" name="wpl_e2e_background_revisions" class="required-entry select">
                                <option value="0" <?php selected( $wpl_background_revisions, 0 ); ?>><?php echo __( 'Off', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                <option value="1" <?php selected( $wpl_background_revisions, 1 ); ?>><?php echo __( 'Yes, push changes automatically', 'wp-lister-for-ebay' ); ?></option>
                            </select>
                            <p class="desc" style="display: block;">
                                <?php echo __( 'Enable this to revise changed items automatically in the background.', 'wp-lister-for-ebay' ); ?>
                            </p>

                            <div class="wple-field" style="clear:both;">
                                <?php wple_maybe_display_pro_overlay(); ?>
                                <label for="wpl-option-handle_stock" class="text_label">
		                            <?php echo __( 'Synchronize sales', 'wp-lister-for-ebay' ) ?>
		                            <?php wplister_tooltip(__('Do you want WP-Lister to reduce the stock quantity in WooCommerce when an item is sold on eBay - and vice versa?', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-handle_stock" name="wpl_e2e_option_handle_stock" class=" required-entry select" <?php if ( ! $wpl_license_activated ) : ?>disabled<?php endif; ?>>
                                    <option value="1" <?php if ( $wpl_option_handle_stock == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?> (<?php _e('recommended', 'wp-lister-for-ebay' ) ?>)</option>
                                    <option value="0" <?php if ( $wpl_option_handle_stock != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                </select>
	                            <?php if ( $wpl_license_activated ) : ?>
                                    <p class="desc" style="display: block;">
			                            <?php echo __( 'Automatically reduce the stock level in WooCommerce when an item is sold on eBay, and vice versa.', 'wp-lister-for-ebay' ); ?>
                                    </p>
	                            <?php else : ?>
                                    <p class="desc" style="display: block;">
			                            <?php echo __( 'To enable this option, please activate WP-Lister with a valid license.', 'wp-lister-for-ebay' ); ?>
                                    </p>
	                            <?php endif; ?>
                            </div>

							<!-- ## END PRO ## -->

						</div>
					</div>


					<div class="postbox" id="OrderOptionsBox">
						<h3 class="hndle"><span><?php echo __( 'WooCommerce Orders', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-create_orders" class="text_label">
                                    <?php echo __( 'Create orders', 'wp-lister-for-ebay' ) ?>
                                    <?php wplister_tooltip(__('Enable this if you want WP-Lister to create orders in WooCommerce from sales on eBay.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-create_orders" name="wpl_e2e_option_create_orders" class=" required-entry select">
                                    <option value="1" <?php if ( $wpl_option_create_orders == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?> (<?php _e('recommended', 'wp-lister-for-ebay' ) ?>)</option>
                                    <option value="0" <?php if ( $wpl_option_create_orders != '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                </select>
                                <p class="desc" style="display: block;">
                                    <?php echo __( 'Enable this to create orders in WooCommerce from sales on eBay.', 'wp-lister-for-ebay' ); ?>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-shipped_order_status" class="text_label">
                                    <?php echo __( 'Status for shipped orders', 'wp-lister-for-ebay' ) ?>
                                    <?php wplister_tooltip(__('Select the WooCommerce order status for orders which have been marked as shipped on eBay.<br><br>The default status is <i>Completed</i>.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-shipped_order_status" name="wpl_e2e_option_shipped_order_status" class=" required-entry select">
                                    <?php if ( function_exists('wc_get_order_statuses') ) : ?>
                                        <?php foreach ( wc_get_order_statuses() as $status_slug => $status_name ) : ?>
                                            <?php $status_slug = str_replace( 'wc-', '', $status_slug ); ?>
                                            <option value="<?php echo $status_slug ?>" <?php if ( $wpl_option_shipped_order_status == $status_slug ): ?>selected="selected"<?php endif; ?>><?php echo $status_name ?>
                                            <?php if ( 'completed' == $status_slug ): ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>) <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <option value="completed" 	<?php if ( $wpl_option_shipped_order_status == 'completed' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'completed', 'wp-lister-for-ebay' ); ?></option>
                                        <option value="processing"  <?php if ( $wpl_option_shipped_order_status != 'completed' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'processing', 'wp-lister-for-ebay' ); ?></option>
                                    <?php endif; ?>
                                </select>
                                <p class="desc" style="display: block;">
                                    <?php echo __( 'Select the WooCommerce order status for orders which have been marked as shipped on eBay.', 'wp-lister-for-ebay' ); ?>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-new_order_status" class="text_label">
                                    <?php echo __( 'Status for paid orders', 'wp-lister-for-ebay' ) ?>
                                    <?php wplister_tooltip(__('Select the WooCommerce order status for orders where payment has been completed on eBay.<br><br>The default status is <i>Processing</i>.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-new_order_status" name="wpl_e2e_option_new_order_status" class=" required-entry select">
                                    <?php if ( function_exists('wc_get_order_statuses') ) : ?>
                                        <?php foreach ( wc_get_order_statuses() as $status_slug => $status_name ) : ?>
                                            <?php $status_slug = str_replace( 'wc-', '', $status_slug ); ?>
                                            <option value="<?php echo $status_slug ?>" <?php if ( $wpl_option_new_order_status == $status_slug ): ?>selected="selected"<?php endif; ?>><?php echo $status_name ?>
                                            <?php if ( 'processing' == $status_slug ): ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>) <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <option value="completed" 	<?php if ( $wpl_option_new_order_status == 'completed' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'completed', 'wp-lister-for-ebay' ); ?></option>
                                        <option value="processing"  <?php if ( $wpl_option_new_order_status != 'completed' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'processing', 'wp-lister-for-ebay' ); ?></option>
                                    <?php endif; ?>
                                </select>
                                <p class="desc" style="display: block;">
                                    <?php echo __( 'Select the WooCommerce order status for orders where payment has been completed on eBay.', 'wp-lister-for-ebay' ); ?>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-unpaid_order_status" class="text_label">
                                    <?php echo __( 'Status for unpaid orders', 'wp-lister-for-ebay' ) ?>
                                    <?php wplister_tooltip(__('Select the WooCommerce order status for orders which are still unpaid on eBay.<br><br>The default status is <i>On Hold</i>.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-unpaid_order_status" name="wpl_e2e_option_unpaid_order_status" class=" required-entry select">
                                    <?php if ( function_exists('wc_get_order_statuses') ) : ?>
                                        <?php foreach ( wc_get_order_statuses() as $status_slug => $status_name ) : ?>
                                            <?php $status_slug = str_replace( 'wc-', '', $status_slug ); ?>
                                            <option value="<?php echo $status_slug ?>" <?php if ( $wpl_option_unpaid_order_status == $status_slug ): ?>selected="selected"<?php endif; ?>><?php echo $status_name ?>
                                            <?php if ( 'on-hold' == $status_slug ): ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>) <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <option value="completed" 	<?php if ( $wpl_option_unpaid_order_status == 'completed' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'completed', 'wp-lister-for-ebay' ); ?></option>
                                        <option value="on-hold" 	<?php if ( $wpl_option_unpaid_order_status == 'on-hold'   ): ?>selected="selected"<?php endif; ?>><?php echo __( 'on-hold', 'wp-lister-for-ebay' ); ?></option>
                                        <option value="pending"  	<?php if ( $wpl_option_unpaid_order_status == 'pending'   ): ?>selected="selected"<?php endif; ?>><?php echo __( 'pending', 'wp-lister-for-ebay' ); ?></option>
                                    <?php endif; ?>
                                </select>
                                <p class="desc" style="display: block;">
                                    <?php echo __( 'Select the WooCommerce order status for orders which are still unpaid on eBay.', 'wp-lister-for-ebay' ); ?>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-cancelled_order_status" class="text_label">
                                    <?php echo __( 'Status for cancelled orders', 'wp-lister-for-ebay' ) ?>
                                    <?php wplister_tooltip(__('Select the WooCommerce order status for orders which are cancelled on eBay.<br><br>The default status is <i>Cancelled</i>.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-cancelled_order_status" name="wpl_e2e_option_cancelled_order_status" class=" required-entry select">
                                    <?php if ( function_exists('wc_get_order_statuses') ) : ?>
                                        <?php foreach ( wc_get_order_statuses() as $status_slug => $status_name ) : ?>
                                            <?php $status_slug = str_replace( 'wc-', '', $status_slug ); ?>
                                            <option value="<?php echo $status_slug ?>" <?php if ( $wpl_option_cancelled_order_status == $status_slug ): ?>selected="selected"<?php endif; ?>><?php echo $status_name ?>
                                            <?php if ( 'cancelled' == $status_slug ): ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>) <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <option value="completed" 	<?php if ( $wpl_option_cancelled_order_status == 'completed' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'completed', 'wp-lister-for-ebay' ); ?></option>
                                        <option value="on-hold" 	<?php if ( $wpl_option_cancelled_order_status == 'on-hold'   ): ?>selected="selected"<?php endif; ?>><?php echo __( 'on-hold', 'wp-lister-for-ebay' ); ?></option>
                                        <option value="pending"  	<?php if ( $wpl_option_cancelled_order_status == 'pending'   ): ?>selected="selected"<?php endif; ?>><?php echo __( 'pending', 'wp-lister-for-ebay' ); ?></option>
                                        <option value="cancelled"  	<?php if ( $wpl_option_cancelled_order_status == 'cancelled'   ): ?>selected="selected"<?php endif; ?>><?php echo __( 'cancelled', 'wp-lister-for-ebay' ); ?></option>
                                    <?php endif; ?>
                                </select>
                                <p class="desc" style="display: block;">
                                    <?php echo __( 'Select the WooCommerce order status for orders which are cancelled on eBay.', 'wp-lister-for-ebay' ); ?>
                                </p>
                            </div>

                            <div class="wple-field">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-orders_default_payment_method" class="text_label">
                                    <?php echo __( 'Payment gateway to use', 'wp-lister-for-ebay' ) ?>
                                    <?php wplister_tooltip(__('Select the WooCommerce payment gateway to assign the created orders to.', 'wp-lister-for-ebay')) ?>
                                </label>

                                <select id="wpl-orders_default_payment_method" name="wpl_e2e_orders_default_payment_method" class=" required-entry select">
                                    <option value=""  <?php selected( $wpl_orders_default_payment_method, '' ); ?>><?php echo __( 'Import from eBay', 'wp-lister-for-ebay' ); ?> (<?php echo __('default', 'wp-lister-for-ebay' ) ?>)</option>
                                    <option value="other"  <?php selected( $wpl_orders_default_payment_method, 'other' ); ?>><?php echo __( 'Other', 'wp-lister-for-ebay' ); ?></option>
                                    <?php foreach ( $wpl_payment_methods as $method ): ?>
                                        <option value="<?php esc_attr_e( $method->id ); ?>" <?php selected( $wpl_orders_default_payment_method, $method->id ); ?>><?php echo $method->title; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="desc" style="display: block;">
                                    <?php echo __( 'Select the WooCommerce payment gateway to assign the created orders to.', 'wp-lister-for-ebay' ); ?>
                                </p>
                            </div>

                            <div class="wple-field show-if-custom-payment-gateway">
	                            <?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-text-orders_default_payment_title" class="text_label show-if-custom-payment-gateway">
                                    <?php echo __( 'Custom payment title', 'wp-lister-for-ebay' ); ?>
                                    <?php wplister_tooltip(__('The payment method in eBay orders often defaults to "Other". Enter your own payment title here which will be used instead of "Other" when creating orders in WooCommerce.', 'wp-lister-for-ebay')) ?>
                                </label>
                                <input type="text" name="wpl_e2e_orders_default_payment_title" id="wpl-text-orders_default_payment_title" value="<?php echo $wpl_orders_default_payment_title; ?>" placeholder="Other" class="text_input show-if-custom-payment-gateway" />
                                <p class="desc show-if-custom-payment-gateway" style="display: block;">
                                    <?php echo __( 'Enter your own payment title here which will be used instead of "Other" when creating orders in WooCommerce.', 'wp-lister-for-ebay' ); ?>
                                </p>
                            </div>
						</div>
					</div>

					<div class="postbox" id="OtherSettingsBox">
						<h3 class="hndle"><span><?php echo __( 'Other Options', 'wp-lister-for-ebay' ) ?></span></h3>
						<div class="inside">

							<label for="wpl-enable_grid_editor" class="text_label">
								<?php echo __( 'Enable Grid Editor', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('The grid editor is still under active development and should be considered beta, which is why it is disabled by default.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-enable_grid_editor" name="wpl_e2e_enable_grid_editor" class="required-entry select">
								<option value=""  <?php if ( $wpl_enable_grid_editor == ''  ): ?>selected="selected"<?php endif; ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
								<option value="1" <?php if ( $wpl_enable_grid_editor == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?> (beta)</option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Enable the grid editor.', 'wp-lister-for-ebay' ); ?>
								<?php echo __( 'Please report any issues to support.', 'wp-lister-for-ebay' ); ?>
							</p>

							<div class="wple-field">
								<?php wple_maybe_display_pro_overlay(); ?>

                                <label for="wpl-option-enable_messages_page" class="text_label">
									<?php echo __( 'Enable eBay Messages page', 'wp-lister-for-ebay' ); ?>
									<?php wplister_tooltip(__('When this option is enabled, WP-Lister will fetch new messages from eBay each time it checks for new orders.<br><br>You can view these messages on a separate Messages page, similar to how orders are handled.<br><br><i>This option is only available in WP-Lister Pro.</i>', 'wp-lister-for-ebay')) ?>
                                </label>
                                <select id="wpl-option-enable_messages_page" name="wpl_e2e_enable_messages_page" class=" required-entry select">
                                    <option value="0" <?php if ( $wpl_enable_messages_page == '0' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Disabled', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                    <option value="1" <?php if ( $wpl_enable_messages_page == '1' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Enabled', 'wp-lister-for-ebay' ); ?></option>
                                </select>
                                <p class="desc" style="display: block;">
									<?php echo __( 'Enable this to access eBay messages within WP-Lister.', 'wp-lister-for-ebay' ); ?>
                                </p>
                            </div>

							<label for="wpl-local_auction_display" class="text_label">
								<?php echo __( 'Link auctions to eBay', 'wp-lister-for-ebay' ); ?>
                                <?php wplister_tooltip(__('In order to prevent selling an item in WooCommerce which is currently on auction, WP-Lister can replace the "Add to cart" button with a "View on eBay" button.', 'wp-lister-for-ebay')) ?>
							</label>
							<select id="wpl-local_auction_display" name="wpl_e2e_local_auction_display" class=" required-entry select">
								<option value="off" 	<?php if ( $wpl_local_auction_display == 'off'    ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Off', 'wp-lister-for-ebay' ); ?> (<?php _e('default', 'wp-lister-for-ebay' ); ?>)</option>
                                <?php wple_render_pro_select_option( 'if_bid', __( 'Only if there are bids on eBay or the auction ends within 12 hours', 'wp-lister-for-ebay' ), $wpl_local_auction_display == 'if_bid' ); ?>
								<option value="always"  <?php if ( $wpl_local_auction_display == 'always' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Always show link to eBay for products on auction', 'wp-lister-for-ebay' ); ?></option>
								<option value="forced"  <?php if ( $wpl_local_auction_display == 'forced' ): ?>selected="selected"<?php endif; ?>><?php echo __( 'Always show link to eBay for auctions and fixed price items', 'wp-lister-for-ebay' ); ?> (<?php _e('not recommended', 'wp-lister-for-ebay' ); ?>)</option>
							</select>
							<p class="desc" style="display: block;">
								<?php echo __( 'Enable this to modify the product details page for items currently on auction.', 'wp-lister-for-ebay' ); ?>
							</p>

						</div>
					</div>


				</form>

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






	<script type="text/javascript">
		jQuery( document ).ready(
			function () {
		
				// save changes button
				jQuery('#save_settings').click( function() {					

					// // handle input fields outside of form
					// var paypal_address = jQuery('#wpl-text_paypal_email-field').first().attr('value');
					// jQuery('#wpl_text_paypal_email').attr('value', paypal_address );

					jQuery('#settingsForm').first().submit();
					
				});

                jQuery('#wpl-orders_default_payment_method').change(function() {
                    if ( jQuery(this).val() == "other" ) {
                        jQuery(".show-if-custom-payment-gateway").show();
                    } else {
                        jQuery(".show-if-custom-payment-gateway").hide();
                    }
                }).change();

			}
		);
	
	</script>


</div>