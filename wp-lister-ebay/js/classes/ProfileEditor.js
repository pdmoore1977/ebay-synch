jQuery( document ).ready(function () {
    let disable_errors = jQuery("#disable_popups").val() == 1;

    // enable chosen.js
    //jQuery("select.wple_chosen_select").chosen();
    jQuery("select.wple_chosen_select").selectWoo();
    jQuery("select.wple_select").selectWoo({
        placeholder: "Select or enter a custom value",
        text: "Select or enter a custom value",
        tags: true
    });

    // hide fixed price field for fixed price listings
    // (fixed price listings only use StartPrice)
    jQuery('#wpl-text-auction_type').change(function() {
        if ( jQuery('#wpl-text-auction_type').val() == 'Chinese' ) {
            jQuery('#wpl-text-fixed_price_container').show();
        } else {
            jQuery('#wpl-text-fixed_price_container').hide();
        }
        if ( jQuery('#wpl-text-auction_type').val() == 'ClassifiedAd' ) {
            // jQuery('#wpl-option-PayPerLeadEnabled_container').show();
        } else {
            // jQuery('#wpl-option-PayPerLeadEnabled_container').hide();
        }
    });
    jQuery('#wpl-text-auction_type').change();

    // hide condition description field for "new" conditions (Condition IDs 1000-1499)
    jQuery('#wpl-text-condition_id').change(function() {
        const condition_id = jQuery('#wpl-text-condition_id').val();

        jQuery('#wpl-ungraded_condition_description_container').hide();
        jQuery('#wpl-graded_condition_description_container').hide();

        if ( condition_id == 4000 ) {
            jQuery('#wpl-ungraded_condition_description_container').show();
        } else if ( condition_id == 2750 ) {
            jQuery('#wpl-graded_condition_description_container').show();
        }

    });
    jQuery('#wpl-text-condition_id').change();

    // set Return Policy details visibility
    jQuery('#wpl-text-returns_accepted').change(function() {
        if ( jQuery('#wpl-text-returns_accepted').val() == 1 ) {
            jQuery('#returns_details_container').slideDown(200);
        } else {
            jQuery('#returns_details_container').slideUp(200);
        }
    });
    jQuery('#wpl-text-returns_accepted').change();


    // set Tax Mode options visibility
    jQuery('#wpl-text-tax_mode').change(function() {
        if ( jQuery('#wpl-text-tax_mode').val() == 'fix' ) {
            jQuery('#tax_mode_fixed_options_container').show();
        } else {
            jQuery('#tax_mode_fixed_options_container').hide();
        }
    });
    jQuery('#wpl-text-tax_mode').change();

    // set Subtitle options visibility
    jQuery('#wpl-text-subtitle_enabled').change(function() {
        if ( jQuery('#wpl-text-subtitle_enabled').val() == 1 ) {
            jQuery('#subtitle_options_container').show();
        } else {
            jQuery('#subtitle_options_container').hide();
        }
    });
    jQuery('#wpl-text-subtitle_enabled').change();

    // set Best Offer options visibility
    jQuery('#wpl-text-bestoffer_enabled').change(function() {
        if ( jQuery('#wpl-text-bestoffer_enabled').val() == 1 ) {
            jQuery('#best_offer_options_container').slideDown(200);
        } else {
            jQuery('#best_offer_options_container').slideUp(200);
        }
    });
    jQuery('#wpl-text-bestoffer_enabled').change();

    // set Schedule Time details visibility
    jQuery('#wpl-text-schedule_time').change(function() {
        if ( jQuery('#wpl-text-schedule_time').val() != '' ) {
            jQuery('#schedule_time_details_container').show();
        } else {
            jQuery('#schedule_time_details_container').hide();
        }
    });
    jQuery('#wpl-text-schedule_time').change();

    // set Auto Relist options visibility
    jQuery('#wpl-text-autorelist_enabled').change(function() {
        if ( jQuery('#wpl-text-autorelist_enabled').val() == 1 ) {
            jQuery('#autorelist_options_container').slideDown(200);
        } else {
            jQuery('#autorelist_options_container').slideUp(200);
        }
    });
    jQuery('#wpl-text-autorelist_enabled').change();

    // update ended items automatically when deactivating autorelist option - after calling .change()
    jQuery('#wpl-text-autorelist_enabled').change(function() {
        if ( jQuery('#wpl-text-autorelist_enabled').val() == 0 ) {
            jQuery('#wpl_e2e_apply_changes_to_all_ended').prop('checked','checked');
        }
    });

    // set Selling Manager Pro options visibility
    jQuery('#wpl-text-sellingmanager_enabled').change(function() {
        if ( jQuery('#wpl-text-sellingmanager_enabled').val() == 1 ) {
            jQuery('#sm_auto_relist_options_container').slideDown(200);
        } else {
            jQuery('#sm_auto_relist_options_container').slideUp(200);
        }
    });
    jQuery('#wpl-text-sellingmanager_enabled').change();

    // set custom quantity options visibility
    jQuery('#wpl-custom_quantity_enabled').change(function() {
        if ( jQuery('#wpl-custom_quantity_enabled').val() != '' ) {
            jQuery('#wpl-custom_quantity_container').show();
        } else {
            jQuery('#wpl-custom_quantity_container').hide();
        }
    });
    jQuery('#wpl-custom_quantity_enabled').change();


    //
    // Validation
    //
    // check required values on submit
    jQuery('.wplister-page form').on('submit', function() {

        // duration is required
        if ( jQuery('#wpl-text-listing_duration')[0].value == '' && !disable_errors ) {
            alert('Please select a listing duration.'); return false;
        }

        // dispatch time is required
        if ( jQuery('#wpl-text-dispatch_time')[0].value == '' && !disable_errors ) {
            alert('Please enter a handling time.'); return false;
        }

        // location required
        if ( jQuery('#wpl-text-location')[0].value == '' && !disable_errors ) {
            alert('Please enter a location.'); return false;
        }

        // country required
        if ( jQuery('#wpl-text-country')[0].value == '' && !disable_errors ) {
            alert('Please select a country.'); return false;
        }


        // validate shipping options
        var shipping_type = jQuery('.select_shipping_type')[0] ? jQuery('.select_shipping_type')[0].value : 'disabled';
        var seller_profile = jQuery('#wpl-text-seller_shipping_profile_id')[0] ? jQuery('#wpl-text-seller_shipping_profile_id')[0].value : false;

        if ( ! seller_profile ) {

            // check domestic shipping options
            if ( shipping_type == 'flat' || shipping_type == 'FreightFlat' || shipping_type == 'FlatDomesticCalculatedInternational' ) {

                // local flat shipping option required
                if ( jQuery('#loc_shipping_options_table_flat .select_service_name')[0].value == ''  && !disable_errors) {
                    alert('Please select at least one domestic shipping service for eBay.'); return false;
                }

                // local flat shipping price required
                if ( jQuery('#loc_shipping_options_table_flat input.price_input')[0].value == ''  && !disable_errors ) {
                    alert('Please enter a shipping fee for eBay.'); return false;
                }

                // max 5 shipping service options
                if ( jQuery('#loc_shipping_options_table_flat .select_service_name').length > 5  && !disable_errors ) {
                    alert('You have selected more than 5 local shipping services, which is not allowed by eBay.'); return false;
                }

            } else if ( shipping_type == 'calc' || shipping_type == 'CalculatedDomesticFlatInternational' ) {

                // local calc shipping option required
                if ( jQuery('#loc_shipping_options_table_calc .select_service_name')[0].value == ''  && !disable_errors ) {
                    alert('Please select at least one domestic shipping service for eBay.'); return false;
                }

                // max 5 shipping service options
                if ( jQuery('#loc_shipping_options_table_calc .select_service_name').length > 5  && !disable_errors ) {
                    alert('You have selected more than 5 local shipping services, which is not allowed by eBay.'); return false;
                }

            }

            // max 5 international shipping service options
            if ( shipping_type == 'flat' || shipping_type == 'FreightFlat' || shipping_type == 'CalculatedDomesticFlatInternational' ) {
                if ( jQuery('#int_shipping_options_table_flat .select_service_name').length > 5  && !disable_errors ) {
                    alert('You have selected more than 5 international shipping services, which is not allowed by eBay.'); return false;
                }
            } else if ( shipping_type == 'calc' || shipping_type == 'FlatDomesticCalculatedInternational' ) {
                if ( jQuery('#int_shipping_options_table_calc .select_service_name').length > 5  && !disable_errors ) {
                    alert('You have selected more than 5 international shipping services, which is not allowed by eBay.'); return false;
                }
            }

        }

        // template is required
        var template_options = jQuery("input[name='wpl_e2e_template']");
        if( template_options.filter(':checked').length == 0 && !disable_errors){
            alert('Please select a listing template.'); return false;
        }

        return true;
    })

});

// load item conditions on primary category change



// handle new primary category
// update item conditions
function updateItemConditions() {
    var primary_category_id = jQuery('#ebay_category_id_1')[0].value;

    // jQuery('#EbayItemSpecificsBox .inside').slideUp(500);
    // jQuery('#EbayItemSpecificsBox .loadingMsg').slideDown(500);

    // fetch category conditions
    var params = {
        action: 'wple_getCategoryConditions',
        id: primary_category_id,
        site_id: wpl_site_id,
        account_id: wpl_account_id,
        _wpnonce: wpl_CategoryConditionsNonce
    };
    var jqxhr = jQuery.getJSON(
        ajaxurl,
        params,
        function( response ) {

            // append to log
            // console.log( 'response: ', response );
            CategoryConditionsData = response;

            buildItemConditions();
            // jQuery('#EbayItemConditionsBox .inside').slideDown(500);
            // jQuery('#EbayItemConditionsBox .loadingMsg').slideUp(500);

        }
    )
        .fail( function(e,xhr,error) {
            console.log( "error", xhr, error );
            console.log( e.responseText );
        });
}

// built item conditions table
function buildItemConditions() {

    var primary_category_id = jQuery('#ebay_category_id_1')[0].value;
    var conditions = CategoryConditionsData;

    if ( ( ! conditions ) || ( conditions == 'none' ) ) {
        jQuery('#wpl-text-condition_id').children().remove();
        jQuery('#wpl-text-condition_id').append( jQuery('<option/>').val( 'none' ).html( 'none' ) );
        return;
    }

    // save current selection
    var selected_condition_id = jQuery('#wpl-text-condition_id')[0].value;

    // clear options
    jQuery('#wpl-text-condition_id').children().remove();

    // add options
    for (var condition_id in conditions ) {
        // console.log('condition_id ',condition_id);
        // console.log('condition_name ',conditions[condition_id]);
        condition_name = conditions[condition_id];
        jQuery('#wpl-text-condition_id').append( jQuery('<option/>').val( condition_id ).html( condition_name ) );
    }

    // restore current selection
    jQuery("#wpl-text-condition_id option[value='"+selected_condition_id+"']").prop('selected',true);
}