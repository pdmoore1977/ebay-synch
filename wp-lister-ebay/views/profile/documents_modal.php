<style>
    #new_document_form {
        display: none;
        background: #EFEFEF;
        padding: 10px;
    }

    #new_document_form h3 {
        margin-top: 0;
    }

    #wple_document_filename {
        vertical-align: middle;
    }

    #documents_list .page-title-action {
        float: right;
    }

    table#documents_table {
        border: 1px solid #ccc;
        border-collapse: collapse;
        margin: 0;
        padding: 0;
        width: 100%;
        table-layout: fixed;
    }

    table#documents_table caption {
        font-size: 1.5em;
        margin: .5em 0 .75em;
    }

    table#documents_table tr {
        background-color: #f8f8f8;
        border: 1px solid #ddd;
        padding: .35em;
    }

    table#documents_table th,
    table#documents_table td {
        padding: .625em;
        text-align: center;
    }

    table#documents_table th {
        font-size: .85em;
        letter-spacing: .1em;
        text-transform: uppercase;
    }

    table#documents_table th.checkbox-col {
        width: 50px;
    }

    table#documents_table th.id-col {
        width: 100px;
    }

    table#documents_table th.date-col {
        width: 200px;
    }

    table#documents_table th.actions-col {
        width: 100px;
    }

    @media screen and (max-width: 600px) {
        table#documents_table {
            border: 0;
        }

        table#documents_table caption {
            font-size: 1.3em;
        }

        table#documents_table thead {
            border: none;
            clip: rect(0 0 0 0);
            height: 1px;
            margin: -1px;
            overflow: hidden;
            padding: 0;
            position: absolute;
            width: 1px;
        }

        table#documents_table tr {
            border-bottom: 3px solid #ddd;
            display: block;
            margin-bottom: .625em;
        }

        table#documents_table td {
            border-bottom: 1px solid #ddd;
            display: block;
            font-size: .8em;
            text-align: right;
        }

        table#documents_table td::before {
            /*
			* aria-label has no advantage, it won't be read inside a table
			content: attr(aria-label);
			*/
            content: attr(data-label);
            float: left;
            font-weight: bold;
            text-transform: uppercase;
        }

        table#documents_table td:last-child {
            border-bottom: 0;
        }
    }

    /* general styling */
    body table#documents_table {
        font-family: "Open Sans", sans-serif;
        line-height: 1.25;
    }
</style>
<script>
    jQuery(document).ready(function() {
        function show_add_document_form() {
            jQuery('#new_document_form').slideDown();
        }
        function hide_add_document_form() {
            jQuery('#new_document_form').slideUp();
        }
        function disable_add_document_form() {
            jQuery("#new_document_form :input").prop("disabled", true);
        }
        function enable_add_document_form() {
            jQuery("#new_document_form :input").prop("disabled", false);
        }

        jQuery('#show_add_document_form').click(function(e) {
            e.preventDefault();

            show_add_document_form()
        });

        jQuery('#close_add_document_form').click(function() {
            hide_add_document_form();
        })

        jQuery('#documents_frm').on('submit', function() {
            let data = {
                action:     'wple_add_document',
                file:       jQuery('#wpl_gpsr_document').val(),
                type:       jQuery('#wpl_document_type').val(),
                account:    jQuery('#wpl_document_account_id').val()
            }

            disable_add_document_form();

            jQuery
                .post( ajaxurl, data, null, 'json' )
                .done( function( response ) {
                    enable_add_document_form();

                    if ( response.success ) {
                        console.log(response);
                        //reloadDocuments();
                        hide_add_document_form();
                    } else {
                        alert('Unable to add this document. Please try again later or contact support.');
                    }
                })
                .fail( function(e,xhr,error) {
                    alert( "There was a problem saving this record. The server responded:\n\n" + e.responseText );
                    enable_add_document_form();
                });

            return false;
        });

    });
</script>
<div id="new_document_form">
    <h3><?php _e('New Document', 'wp-lister-for-ebay'); ?></h3>
    <form id="documents_frm" method="post">
        <div class="form-field">
            <label for="document"><?php _e( 'Document', 'wp-lister-for-ebay' ); ?> *</label>
            <input type="hidden" name="wpl_e2e_gpsr_document" id="wpl_gpsr_document" value="<?php echo esc_attr( $item_details['gpsr_documents'] ?? '' ); ?>" class="regular-text" />
            <input type="hidden" name="" id="wpl_document_account_id" value="<?php echo esc_attr( $wpl_account_id ); ?>" class="regular-text" />
            <input type='button' class="button wple-document-uploader" data-target="gpsr_document" value="<?php esc_attr_e( 'Select a document', 'wp-lister-for-ebay' ); ?>" id="wple_media_manager"/>
            <span id="wple_document_filename"></span>
        </div>
        <div class="form-field">
            <label for="wpl_document_type"><?php _e( 'Document Type', 'wp-lister-for-ebay' ); ?> *</label>
            <select name="" id="wpl_document_type" required>
				<?php
				foreach ( \WPLab\Ebay\Models\EbayDocument::DOCUMENT_TYPES_ENUM as $code => $label ):
					?>
                    <option value="<?php esc_attr_e($code);?>"><?php esc_attr_e( $label ); ?></option>
				<?php endforeach; ?>
                <select>
        </div>

        <p class="submit">
            <input type="submit" class="button-primary" value="<?php _e('Add Document', 'wp-lister-for-ebay' ); ?>" />
            <input type="button" class="button-secondary" id="close_add_document_form" value="<?php _e('Cancel', 'wp-lister-for-ebay' ); ?>" />
        </p>
    </form>
</div>
<div id="documents_list">
    <a href="#" class="page-title-action button-secondary" id="show_add_document_form">Add New</a>
	<h3 class="wp-heading-inline"><?php _e('Manage Documents', 'wp-lister-for-ebay'); ?></h3>

    <table id="documents_table">
        <thead>
        <tr>
            <th class="id-col">ID</th>
            <th class="document-filename">Filename</th>
            <th class="document-type">Type</th>
            <th class="date-col">Date Added</th>
            <th class="actions-col"></th>
        </tr>
        </thead>
        <tbody>
        <?php
        $documents = wple_get_documents( $wpl_account_id );

        if ( empty( $documents ) ):
	        ?>
        <tr>
            <td colspan="5"><em>No records found</em></td>
        </tr>
        <?php
        else:
	        foreach ( $documents as $row ):
		        ?>
            <tr>
                <td><?php echo $row->getId(); ?></td>
                <td><?php echo $row->getAttachment()->filename; ?></td>
                <td><?php echo $row->getDocumentType(); ?></td>
                <td><?php echo $row->getDateAdded()->format(get_option('date_format') ); ?></td>
            </tr>
	        <?php
	        endforeach;
        endif;
        ?>
        </tbody>
    </table>


</div>
