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

    #responsible_persons_modal_container #persons_list .address .id, #manufacturers_modal_container #manufacturers_list .address .id {
        float: right;
        background: #969696;
        padding: 2px 8px;
        margin: -6px -6px 0 0 !important;
        color: #fff;
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
</style>

<p>This section is only applicable if you are shipping to EU and NI buyers.</p>

<p class="form-field wpl_ebay_buynow_price_field ">
    <label for="wpl-text-gpsr-enabled" class="text_label">
		<?php echo __( 'Enable GPSR', 'wp-lister-for-ebay' ); ?>
		<?php wplister_tooltip(__('Enable this to include the GPSR data in your listings.', 'wp-lister-for-ebay')) ?>
    </label>
    <select id="wpl-text-gpsr_enabled" name="wpl_e2e_gpsr_enabled" title="General Product Safety Regulation" class=" required-entry select">
        <option value="0" <?php selected( $item_details['gpsr_enabled'], 0 ) ?>><?php echo __( 'No', 'wp-lister-for-ebay' ); ?></option>
        <option value="1" <?php selected( $item_details['gpsr_enabled'], 1 ); ?>><?php echo __( 'Yes', 'wp-lister-for-ebay' ); ?></option>
    </select>
    <br class="clear" />
</p>

<div id="gpsr_container">

    <!--<label for="wpl-text-gpsr-documents" class="text_label">
        <?php echo __( 'Documents', 'wp-lister-for-ebay' ); ?>
        <?php wplister_tooltip(__('Regulatory documents associated with the listing.', 'wp-lister-for-ebay') ); ?>
    </label>

    <select id="wpl-text-gpsr_documents" name="wpl_e2e_gpsr_documents[]" class="wple_chosen_select" data-placeholder="Select documents" multiple style="width:50%">
        <?php
        $documents = wple_get_documents( $wpl_account_id );
        foreach ( $documents as $document ):
            $selected = in_array( $document->getId(), (array)$item_details['gpsr_documents'] );
            ?>
            <option <?php selected( $selected, true ); ?> value="<?php esc_attr_e( $document->getId() ); ?>"><?php esc_attr_e( $document->getAttachment()->filename .' - '. $document->getDocumentType() ); ?></option>
        <?php endforeach; ?>
    </select>
    <a href="#" class="button" id="show_documents_modal"><?php _e( 'Manage', 'wp-lister-for-ebay' ); ?></a>
    <br class="clear" />-->

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
    </div>
    <a href="#" type='button' class="button wple-uploader" data-target="gpsr_energy_efficiency_image" id="wple_media_manager"><?php _e( 'Select a image', 'wp-lister-for-ebay' ); ?></a>

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
        foreach ( $wpl_hazardous_materials_labels['pictograms'] as $pictogram ):
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
        foreach ( $wpl_hazardous_materials_labels['signal_words'] as $signal_word ):
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
        foreach ( $wpl_hazardous_materials_labels['statements'] as $statement ):
            $hazmat_statements = $item_details['gpsr_hazmat_statements'] ?? [];
            $selected = in_array( $statement['statement_id'], (array)$hazmat_statements );
        ?>
            <option <?php selected($selected,true); ?> value="<?php esc_attr_e( $statement['statement_id'] ); ?>"><?php esc_attr_e( $statement['statement_description'] ); ?></option>
        <?php endforeach; ?>
    </select>
    <br class="clear" />

    <h4><?php _e('Manufacturer', 'wp-lister-for-ebay' ); ?></h4>
    <?php
    $manufacturers = wple_get_manufacturers();
    ?>
    <label class="text_label"><?php _e( 'Select a Manufacturer', 'wp-lister-for-ebay'); ?></label>
    <select id="wpl-text-gpsr_manufacturer" name="wpl_e2e_gpsr_manufacturer" class="wple_chosen_select" style="width:40%;">
        <optgroup label="Saved Manufacturers">
            <option value=""></option>
	        <?php foreach ( $manufacturers as $manufacturer ): ?>
                <option <?php selected( $manufacturer->getId(), $item_details['gpsr_manufacturer'] ?? '' ); ?> value="<?php esc_attr_e( $manufacturer->getId() ); ?>"><?php esc_attr_e( $manufacturer->getCompany() .' - '. $manufacturer->getCity() ); ?></option>
	        <?php endforeach; ?>
        </optgroup>
        <optgroup label="From Attributes">
            <?php
            foreach ( $wpl_available_attributes as $attribute ):
                $select_name = '[[attribute_'. $attribute->name .']]';
            ?>
            <option <?php selected( $select_name, $item_details['gpsr_manufacturer'] ?? '' ); ?> value="<?php echo $select_name; ?>"><?php echo __('Attribute: ', 'wp-lister-for-ebay') . $attribute->name; ?></option>
            <?php endforeach; ?>
        </optgroup>
    </select>
    <a href="#" class="button" id="show_manufacturers_modal"><?php _e( 'Manage', 'wp-lister-for-ebay' ); ?></a>

    <h4><?php _e('Product Safety', 'wp-lister-for-ebay' ); ?></h4>

    <label class="text_label"><?php _e( 'Component', 'wp-lister-for-ebay'); ?></label>
    <input type="text" name="wpl_e2e_gpsr_product_safety_component" size="30" value="<?php echo esc_attr($item_details['gpsr_product_safety_component'] ?? ''); ?>" id="title" autocomplete="off" style="width:65%;">

    <label class="text_label"><?php _e('Pictograms', 'wp-lister-for-ebay'); ?></label>
    <select name="wpl_e2e_gpsr_product_safety_pictograms[]" class="wple_chosen_select" data-placeholder="<?php _e('Select up to 2', 'wp-lister-fo-ebay'); ?>" multiple style="width:50%">
        <?php
        foreach ( $wpl_product_safety_labels['pictograms'] as $pictogram ):
            $safety_pictograms_array = $item_details['gpsr_product_safety_pictograms'] ?? [];
            $selected = in_array( $pictogram['pictogram_id'], (array)$safety_pictograms_array );
            ?>
            <option <?php selected($selected, true); ?> value="<?php esc_attr_e( $pictogram['pictogram_id'] ); ?>"><?php esc_attr_e( $pictogram['pictogram_description'] ); ?></option>
        <?php endforeach; ?>
    </select>

    <label class="text_label"><?php _e('Statements', 'wp-lister-for-ebay'); ?></label>
    <select name="wpl_e2e_gpsr_product_safety_statements[]" class="wple_chosen_select" data-placeholder="<?php _e('Select up to 8', 'wp-lister-fo-ebay'); ?>" multiple style="width:50%">
        <?php
        $safety_statements_array = $item_details['gpsr_product_safety_statements'] ?? [];
        foreach ( $wpl_product_safety_labels['statements'] as $statement ):
            $selected = in_array( $statement['statement_id'], (array)$safety_statements_array );
        ?>
            <option <?php selected($selected,true); ?> value="<?php esc_attr_e( $statement['statement_id'] ); ?>"><?php esc_attr_e( $statement['statement_description'] ); ?></option>
        <?php endforeach; ?>
    </select>

    <h4><?php _e('Responsible Persons', 'wp-lister-for-ebay' ); ?></h4>

    <label class="text_label">
        <?php _e( 'Set Responsible Persons', 'wp-lister-for-ebay'); ?>
    </label>
    <select id="wpl-text-gpsr_responsible_persons" name="wpl_e2e_gpsr_responsible_persons[]" class="wple_chosen_select" data-placeholder="Select up to 5 persons" multiple style="width:50%">
        <optgroup label="Saved Responsible Persons">
            <?php
            $persons = wple_get_responsible_persons();
            foreach ( $persons as $person ):
                $responsible_persons_array = $item_details['gpsr_responsible_persons'] ?? [];
                $selected = in_array( $person->getId(), (array)$responsible_persons_array );
                ?>
                <option <?php selected( $selected, true ); ?> value="<?php esc_attr_e( $person->getId() ); ?>"><?php esc_attr_e( $person->getCompany() .' - '. $person->getCity() ); ?></option>
            <?php endforeach; ?>
        </optgroup>
        <optgroup label="From Attributes">
		    <?php
		    foreach ( $wpl_available_attributes as $attribute ):
			    $select_name = '[[attribute_'. $attribute->name .']]';
			    ?>
                <option <?php selected( true, in_array( $select_name, (array)$item_details['gpsr_responsible_persons'] ) ); ?> value="<?php echo $select_name; ?>"><?php echo __('Attribute: ', 'wp-lister-for-ebay') . $attribute->name; ?></option>
		    <?php endforeach; ?>
        </optgroup>
    </select>
    <a href="#" class="button" id="show_persons_modal"><?php _e( 'Manage', 'wp-lister-for-ebay' ); ?></a>

</div>