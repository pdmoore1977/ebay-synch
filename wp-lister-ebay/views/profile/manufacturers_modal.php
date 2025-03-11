<div id="manufacturers_list">
    <h3><?php _e('Existing Manufacturers', 'wp-lister-for-ebay'); ?></h3>
    <?php
    $manufacturers = wple_get_manufacturers();

    if ( empty( $manufacturers ) ):
    ?>
        <div id="no_records" class="info">
            <p><em>No records found</em></p>
        </div>
    <?php
    else:
        foreach ( $manufacturers as $row ):
    ?>
        <div class="address">
            <div class="id">ID: <?php echo $row->getId(); ?></div>
            <h4><?php echo $row->getCompany(); ?></h4>
            <p><?php echo $row->getStreet1() .' '. $row->getStreet2() .', '. $row->getCity() .' '. $row->getState() .', '. $row->getCountry(); ?></p>
            <p><?php echo $row->getPhone() .' / '. $row->getEmail(); ?></p>
            <p><a class="delete delete-manufacturer" data-id="<?php echo $row->getId(); ?>" href="#">Delete</a></p>
        </div>
        <div class="clear"></div>
    <?php
        endforeach;
    endif;
    ?>
</div>
<div id="form">
    <h3><?php _e('New Manufacturer', 'wp-lister-for-ebay'); ?></h3>
	<form id="manufacturers_frm" method="post">
        <div class="form-field">
            <label for="company"><?php _e( 'Company', 'wp-lister-for-ebay' ); ?> *</label>
            <input type="text" name="company" id="manufacturer_company" required />
        </div>
        <div class="form-field">
            <label for="phone"><?php _e( 'Phone', 'wp-lister-for-ebay' ); ?> *</label>
            <input type="text" name="phone" id="manufacturer_phone" required />
        </div>
        <div class="form-field">
            <label for="email"><?php _e( 'Email', 'wp-lister-for-ebay' ); ?> *</label>
            <input type="text" name="email" id="manufacturer_email" required />
        </div>
        <div class="form-field">
            <label for="street1"><?php _e( 'Street 1', 'wp-lister-for-ebay' ); ?> *</label>
            <input type="text" name="street1" id="manufacturer_street1" required />
        </div>
        <div class="form-field">
            <label for="street2"><?php _e( 'Street 2', 'wp-lister-for-ebay' ); ?></label>
            <input type="text" name="street2" id="manufacturer_street2" />
        </div>
        <div class="form-field">
            <label for="city"><?php _e( 'City', 'wp-lister-for-ebay' ); ?> *</label>
            <input type="text" name="city" id="manufacturer_city" required />
        </div>
        <div class="form-field">
            <label for="state"><?php _e( 'State / Province', 'wp-lister-for-ebay' ); ?> *</label>
            <input type="text" name="state" id="manufacturer_state" required />
        </div>
        <div class="form-field">
            <label for="postcode"><?php _e( 'Postal Code', 'wp-lister-for-ebay' ); ?> *</label>
            <input type="text" name="postcode" id="manufacturer_postcode" required />
        </div>
        <div class="form-field">
            <label for="country"><?php _e( 'Country', 'wp-lister-for-ebay' ); ?> *</label>
            <select name="country" id="manufacturer_country" required>
		        <?php
		        $wc_countries = new WC_Countries();
		        $countries = $wc_countries->get_countries();
		        $default = $wpl_item['details']['country'] ?? 'US';

		        foreach ( $countries as $code => $country ):
			        ?>
                    <option <?php selected( $default, $code ); ?> value="<?php esc_attr_e($code);?>"><?php esc_attr_e( $country ); ?></option>
		        <?php endforeach; ?>
                <select>
        </div>

		<p class="submit">
			<input type="submit" class="button-primary" value="<?php _e('Add Manufacturer', 'wp-lister-for-ebay' ); ?>" />
		</p>
	</form>
</div>