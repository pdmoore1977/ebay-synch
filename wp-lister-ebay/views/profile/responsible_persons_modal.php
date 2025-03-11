<div id="persons_list">
    <h3><?php _e('Existing Persons', 'wp-lister-for-ebay'); ?></h3>
    <?php
    $persons = wple_get_responsible_persons();

    if ( empty( $persons ) ):
    ?>
        <div id="no_persons" class="info">
            <p><em>No records found</em></p>
        </div>
    <?php
    else:
        foreach ( $persons as $person ):
    ?>
        <div class="address">
            <div class="id">ID: <?php echo $person->getId(); ?></div>
            <h4><?php echo $person->getCompany(); ?></h4>
            <p><?php echo $person->getStreet1() .' '. $person->getStreet2() .', '. $person->getCity() .' '. $person->getState() .', '. $person->getCountry(); ?></p>
            <p><?php echo $person->getPhone() .' / '. $person->getEmail(); ?></p>
            <a class="delete delete-person" data-id="<?php echo $person->getId(); ?>" href="#">Delete</a>
        </div>
    <?php
        endforeach;
    endif;
    ?>
</div>
<div id="form">
    <h3><?php _e('Add a Person', 'wp-lister-for-ebay'); ?></h3>
	<form id="persons_frm" method="post">
        <div class="form-field">
            <label for="company"><?php _e( 'Company', 'wp-lister-for-ebay' ); ?> *</label>
            <input type="text" name="company" id="person_company" required />
        </div>
        <div class="form-field">
            <label for="phone"><?php _e( 'Phone', 'wp-lister-for-ebay' ); ?> *</label>
            <input type="text" name="phone" id="person_phone" required />
        </div>
        <div class="form-field">
            <label for="email"><?php _e( 'Email', 'wp-lister-for-ebay' ); ?> *</label>
            <input type="text" name="email" id="person_email" required />
        </div>
        <div class="form-field">
            <label for="street1"><?php _e( 'Street 1', 'wp-lister-for-ebay' ); ?> *</label>
            <input type="text" name="street1" id="person_street1" required />
        </div>
        <div class="form-field">
            <label for="street2"><?php _e( 'Street 2', 'wp-lister-for-ebay' ); ?></label>
            <input type="text" name="street2" id="person_street2" />
        </div>
        <div class="form-field">
            <label for="city"><?php _e( 'City', 'wp-lister-for-ebay' ); ?> *</label>
            <input type="text" name="city" id="person_city" required />
        </div>
        <div class="form-field">
            <label for="state"><?php _e( 'State / Province', 'wp-lister-for-ebay' ); ?> *</label>
            <input type="text" name="state" id="person_state" required />
        </div>
        <div class="form-field">
            <label for="postcode"><?php _e( 'Postal Code', 'wp-lister-for-ebay' ); ?> *</label>
            <input type="text" name="postcode" id="person_postcode" required />
        </div>
        <div class="form-field">
            <label for="country"><?php _e( 'Country', 'wp-lister-for-ebay' ); ?> *</label>
            <select name="country" id="person_country" required>
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
			<input type="submit" class="button-primary" value="<?php _e('Add Person', 'wp-lister-for-ebay' ); ?>" />
		</p>
	</form>
</div>