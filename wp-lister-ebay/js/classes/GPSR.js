jQuery( document ).ready(function () {

    if ( gpsr_custom_manufacturer ) {
        jQuery('div.gpsr-custom-manufacturer').show();
        jQuery('div.gpsr-wpl-manufacturer').hide();
    } else {
        jQuery('div.gpsr-custom-manufacturer').hide();
        jQuery('div.gpsr-wpl-manufacturer').show();
    }

    if ( gpsr_custom_responsible_persons ) {
        jQuery('div.gpsr-custom-responsible-persons').show();
        jQuery('div.gpsr-wpl-responsible-persons').hide();
    } else {
        jQuery('div.gpsr-custom-responsible-persons').hide();
        jQuery('div.gpsr-wpl-responsible-persons').show();
    }

    jQuery('.wpl-remove-custom-manufacturer').on('click', function(e) {
        e.preventDefault();
        jQuery('input[name^=wpl_e2e_gpsr_manufacturer]').each(function(el) {
            jQuery(this).val('');
            jQuery('.gpsr-custom-manufacturer').hide();
            jQuery('.gpsr-wpl-manufacturer').show();
        });
    });

    jQuery('.wpl-remove-custom-responsible-persons').on('click', function(e) {
        e.preventDefault();
        jQuery('input[name^=wpl_e2e_gpsr_responsible_persons_]').each(function(el) {
            jQuery(this).val('');
            jQuery('.gpsr-custom-responsible-persons').hide();
            jQuery('.gpsr-wpl-responsible-persons').show();
        });
    });

    jQuery('#responsible_persons_modal_container').on('click', '.delete-person', function(e) {
        e.preventDefault();

        const el = jQuery(this);
        let data = {
            action:     'wple_delete_responsible_person',
            id:         jQuery(this).data('id')
        };

        jQuery
            .post( ajaxurl, data, null, 'json' )
            .done( function( response ) {
                if ( response.success ) {
                    el.parent('.address').remove();
                    reloadPersons();
                } else {
                    alert( 'Error while removing the record. Please try again later.' );
                }
            })
            .fail( function(e,xhr,error) {
                alert( "There was a problem saving this record. The server responded:\n\n" + e.responseText );
            });

        return false;
    });

    jQuery('#manufacturers_modal_container').on('click', '.delete-manufacturer', function(e) {
        e.preventDefault();

        const el = jQuery(this);
        let data = {
            action:     'wple_delete_manufacturer',
            id:         jQuery(this).data('id')
        };

        jQuery
            .post( ajaxurl, data, null, 'json' )
            .done( function( response ) {
                if ( response.success ) {
                    el.parent('.address').remove();
                    reloadManufacturers();
                } else {
                    alert( 'Error while removing the record. Please try again later.' );
                }
            })
            .fail( function(e,xhr,error) {
                alert( "There was a problem saving this record. The server responded:\n\n" + e.responseText );
            });

        return false;
    });

    jQuery('#show_documents_modal').on('click', function(e) {
        e.preventDefault();

        const sep   = ajaxurl.indexOf('?') > 0 ? '&' : '?'; // fix for ajaxurl altered by WPML: /wp-admin/admin-ajax.php?lang=en
        const tbHeight = tb_getPageSize()[1] - 120;
        const tbURL = "#TB_inline?height="+tbHeight+"&width=750&inlineId=documents_modal";
        //const tbUrl = ajaxurl + sep + "action=wple_show_responsible_persons_modal&width=800&height=400";
        tb_show( "Manage Documents", tbURL );
    });

    jQuery('#show_persons_modal').on('click', function(e) {
        e.preventDefault();

        const sep   = ajaxurl.indexOf('?') > 0 ? '&' : '?'; // fix for ajaxurl altered by WPML: /wp-admin/admin-ajax.php?lang=en
        const tbHeight = tb_getPageSize()[1] - 120;
        const tbURL = "#TB_inline?height="+tbHeight+"&width=750&inlineId=responsible_persons_modal";
        //const tbUrl = ajaxurl + sep + "action=wple_show_responsible_persons_modal&width=800&height=400";
        tb_show( "Manage Responsible Persons", tbURL );
    });

    jQuery('#show_manufacturers_modal').on('click', function(e) {
        e.preventDefault();

        const sep   = ajaxurl.indexOf('?') > 0 ? '&' : '?'; // fix for ajaxurl altered by WPML: /wp-admin/admin-ajax.php?lang=en
        const tbHeight = tb_getPageSize()[1] - 120;
        const tbURL = "#TB_inline?height="+tbHeight+"&width=750&inlineId=manufacturers_modal";
        //const tbUrl = ajaxurl + sep + "action=wple_show_responsible_persons_modal&width=800&height=400";
        tb_show( "Manage Manufacturers", tbURL );
    });

    jQuery('#gpsr_container').hide();
    jQuery('#wpl-text-gpsr_enabled').on('change', function() {
        console.log(jQuery(this).val());
        if (jQuery(this).val() == 1 ) {
            jQuery('#gpsr_container').show();
        } else {
            jQuery('#gpsr_container').hide();
        }
    }).change();

    jQuery("#persons_frm").on('submit', function() {
        jQuery("#persons_frm :input").prop("disabled", true);

        let data = {
            action:     'wple_add_responsible_person',
            company:    jQuery('#person_company').val(),
            phone:      jQuery('#person_phone').val(),
            email:      jQuery('#person_email').val(),
            street1:    jQuery('#person_street1').val(),
            street2:    jQuery('#person_street2').val(),
            city:       jQuery('#person_city').val(),
            state:      jQuery('#person_state').val(),
            postcode:   jQuery('#person_postcode').val(),
            country:    jQuery('#person_country').val()
        };
        jQuery
            .post( ajaxurl, data, null, 'json' )
            .done( function( response ) {
                if ( response.success ) {
                    reloadPersons();
                    tb_remove();
                } else {
                    alert( "There was a problem saving this record. The server responded:\n\n" + response.error );
                }

                jQuery("#persons_frm :input").prop("disabled", false);
            })
            .fail( function(e,xhr,error) {
                try {
                    let resp = JSON.parse( e.responseText );

                    if ( !resp.success ) {
                        alert( "There was a problem saving this record.\n\n" + resp.error );
                    }
                } catch (e) {
                    alert( "There was a problem completing this request. Please try again later or contact support." );
                }
                jQuery("#persons_frm :input").prop("disabled", false);
            });

        return false;
    });

    jQuery("#manufacturers_frm").on('submit', function() {
        jQuery("#manufacturers_frm :input").prop("disabled", true);

        let data = {
            action:     'wple_add_manufacturer',
            company:    jQuery('#manufacturer_company').val(),
            phone:      jQuery('#manufacturer_phone').val(),
            email:      jQuery('#manufacturer_email').val(),
            street1:    jQuery('#manufacturer_street1').val(),
            street2:    jQuery('#manufacturer_street2').val(),
            city:       jQuery('#manufacturer_city').val(),
            state:      jQuery('#manufacturer_state').val(),
            postcode:   jQuery('#manufacturer_postcode').val(),
            country:    jQuery('#manufacturer_country').val()
        };
        jQuery
            .post( ajaxurl, data, null, 'json' )
            .done( function( response ) {
                if ( response.success ) {
                    reloadManufacturers();
                    tb_remove();
                } else {
                    alert( "There was a problem saving this record. The server responded:\n\n" + response.error );
                }
                jQuery("#manufacturers_frm :input").prop("disabled", false);
            })
            .fail( function(e,xhr,error) {
                try {
                    let resp = JSON.parse( e.responseText );

                    if ( !resp.success ) {
                        alert( "There was a problem saving this record.\n\n" + resp.error );
                    }
                } catch (e) {
                    alert( "There was a problem completing this request. Please try again later or contact support." );
                }

                jQuery("#manufacturers_frm :input").prop("disabled", false);
            });

        return false;
    });

    let wpleOpenGallery;
    jQuery('.wple-document-uploader').on('click', function(e) {
        e.preventDefault()

        const target = jQuery(this).data('target');

        wpleOpenGallery({
            title: 'Select files',
            fileType: '*',
            multiple: true,
            currentValue: ''
        }, function(data) {

            if ( data.length ) {
                const   container = jQuery('#wple_document_filename');

                container.html(data[0].filename);
                jQuery('#wpl_gpsr_document').val( data[0].id );
            }
        });
    });
    jQuery('.wple-uploader').on( 'click', function(e) {
        e.preventDefault();

        const target = jQuery(this).data('target');
        let target_img = '#'+ target;
        let target_txt = '#wpl_' + target;
        let target_trash = '#trash_' + target;

        wpleOpenGallery(null, function(data) {
            jQuery(target_img).attr( 'src', data[0].url );
            jQuery(target_img).removeClass('hidden');
            jQuery(target_txt).val(data[0].id)
            jQuery(target_trash).removeClass('hidden');

            jQuery( target_img ).parent('div.gpsr-image-block' ).show();
        });
    });

    jQuery('#gpsr_container').on('click', '.wple-delete-multi-upload', function(e) {
        e.preventDefault();

        const target = jQuery(this).data('target');
        const id = jQuery(this).data('id');

        jQuery( this ).parent('div.gpsr-multi-upload-block' ).remove();

    });

    jQuery('.wple-delete-upload').on('click', function(e) {
        e.preventDefault();

        const target = jQuery(this).data('target');
        let target_img = '#'+ target;
        let target_txt = '#wpl_' + target;

        jQuery( target_img ).parent('div.gpsr-image-block' ).hide();
        jQuery(target_txt).val('');
        jQuery(target_img).attr('src', '' );
    });

    wpleOpenGallery = function(o, callback) {
        const options = (typeof o === 'object') ? o : {};

        // Predefined settings
        const defaultOptions = {
            title: 'Select Media',
            fileType: 'image',
            multiple: false,
            currentValue: '',
        };

        const opt = { ...defaultOptions, ...options };

        let image_frame;

        if(image_frame){
            image_frame.open();
        }

        // Define image_frame as wp.media object
        image_frame = wp.media({
            title: opt.title,
            multiple : opt.multiple,
            library : {
                type : opt.fileType,
            }
        });

        image_frame.on('open',function() {
            // On open, get the id from the hidden input
            // and select the appropiate images in the media manager
            const selection =  image_frame.state().get('selection');
            const ids = opt.currentValue.split(',');

            ids.forEach(function(id) {
                const attachment = wp.media.attachment(id);
                attachment.fetch();
                selection.add( attachment ? [ attachment ] : [] );
            });
        });

        image_frame.on('close',function() {
            // On close, get selections and save to the hidden input
            // plus other AJAX stuff to refresh the image preview
            const selection =  image_frame.state().get('selection');
            const files = [];

            selection.each(function(attachment) {
                if ( attachment.attributes.id != "" ) {
                    files.push({
                        id: attachment.attributes.id,
                        filename: attachment.attributes.filename,
                        url: attachment.attributes.url,
                        type: attachment.attributes.type,
                        subtype: attachment.attributes.subtype,
                        sizes: attachment.attributes.sizes,
                    });
                }
            });

            callback(files);
        });

        image_frame.open();
    }
});

function reloadPersons() {
    // fetch category conditions
    const params = {
        action: 'wple_get_responsible_persons',
        //_wpnonce: wpl_EditProfileNonce
    };
    let jqxhr = jQuery.getJSON(
        ajaxurl,
        params,
        function( persons ) {
            redrawPersonsDropdown(persons);
            redrawPersonsList(persons);
        }
    )
        .fail( function(e,xhr,error) {
            console.log( "error", xhr, error );
            console.log( e.responseText );
        });
}

function redrawPersonsDropdown( persons ) {
    const dropdown = jQuery( '#wpl-text-gpsr_responsible_persons' );
    let selected_persons = dropdown.val();

    // remove options then recreate them with new ones
    dropdown.empty();

    jQuery.each(persons, function() {
        let option = jQuery("<option />").val(this.id).text(this.company +' - '+ this.city);

        if ( jQuery.inArray( this.id, selected_persons ) !== -1 ) {
            option.attr("selected", true);
        }

        dropdown.append(option);
    });
}

function redrawPersonsList( persons ) {
    jQuery('#persons_list').empty();
    jQuery.each( persons, function() {
        let html = '<div class="address">\n' +
            '            <a class="delete button delete-person" data-id="'+ this.id +'" href="#">Delete</a>\n' +
            '            <h4>'+ this.company +'</h4>\n' +
            '            <p>'+ this.street1 +' '+ this.street2 +', '+ this.city +' '+ this.state +', '+ this.country +'</p>\n' +
            '            <p>'+ this.phone +' / '+ this.email +'</p>\n' +
            '        </div>';
        jQuery('#persons_list').append(html);
    } );
}

function reloadManufacturers() {
    // fetch category conditions
    const params = {
        action: 'wple_get_manufacturers',
        //_wpnonce: wpl_EditProfileNonce
    };
    let jqxhr = jQuery.getJSON(
        ajaxurl,
        params,
        function( manufacturers ) {
            redrawManufacturersDropdown(manufacturers);
            redrawManufacturersList(manufacturers);
        }
    )
        .fail( function(e,xhr,error) {
            console.log( "error", xhr, error );
            console.log( e.responseText );
        });
}

function redrawManufacturersDropdown( manufacturers ) {
    const dropdown = jQuery( '#wpl-text-gpsr_manufacturer' );
    let selected = dropdown.val();

    // remove options then recreate them with new ones
    dropdown.empty();

    jQuery.each(manufacturers, function() {
        let option = jQuery("<option />").val(this.id).text(this.company +' - '+ this.city);

        if ( selected == this.id ) {
            option.attr("selected", true);
        }

        dropdown.append(option);
    });
}

function redrawManufacturersList( manufacturers ) {
    jQuery('#manufacturers_list').empty();
    jQuery.each( manufacturers, function() {
        let html = '<div class="address">\n' +
            '            <a class="delete button delete-manufacturer" data-id="'+ this.id +'" href="#">Delete</a>\n' +
            '            <h4>'+ this.company +'</h4>\n' +
            '            <p>'+ this.street1 +' '+ this.street2 +', '+ this.city +' '+ this.state +', '+ this.country +'</p>\n' +
            '            <p>'+ this.phone +' / '+ this.email +'</p>\n' +
            '        </div>';
        jQuery('#manufacturers_list').append(html);
    } );
}