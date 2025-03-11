<?php
// echo "<pre>";print_r($wpl_profiles);echo"</pre>";die();
?><html>
<head>
    <title>request details</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style type="text/css">
        pre {
        	background-color: #eee;
        	border: 1px solid #ccc;
        	padding: 20px;
        }
        table.wple_profile_results {
            border-bottom: 1px solid #ccc;
            border-spacing: 0;
            margin-top: 1em;
        }
        .wple_profile_results td {
            /*background-color:#ffc;*/
            /*vertical-align: top;*/
            border-top: 1px solid #ccc;
        }
        .wple_profile_results td.info {
            padding: 10px 15px;
        }
        .wple_profile_results td.info a {
            /*margin-top: 0.5em;*/
        }
        .wple_profile_results tr:hover td.hover {
        	background-color:#ffe;
        }
        .search_bar {
            float: right;
            margin-top: 20px;
        }


    </style>
</head>

<body>

    <?php if ( ! empty( $wpl_profiles ) ) : ?>
        <div class="search_bar">
            <input id="searchInput" value="" placeholder="<?php esc_attr_e( __( 'Search profiles', 'wp-lister-for-amazon' ) ); ?>" />
        </div>

        <h3 class="wple_tb_title"><?php echo __( 'Select Profile', 'wp-lister-for-ebay' ) ?></h3>
 
        <table class="wple_profile_results" style="width:100%">
        <?php foreach ($wpl_profiles as $profile ) : ?>

            <tr><td class="search-field info hover">
                <big>
                    <?php echo $profile['profile_name'] ?>
                    <?php if ( WPLE()->multi_account ) : ?>
                        &nbsp;<span style="color:silver;"><?php echo WPLE()->accounts[ $profile['account_id'] ]->title ?></span>
                    <?php endif; ?>
                </big><br>

                <small><?php echo $profile['profile_description'] ?></small>
                <br>

            </td><td class="info hover" style="text-align:right">
       
                <a href="#" onclick="WPLE.ProfileSelector.select(this,'<?php echo $profile['profile_id'] ?>');return false;" class="button button-primary">
                    <?php echo __( 'Select Profile', 'wp-lister-for-ebay' ) ?>
                </a>

            </td></tr>

        <?php endforeach; ?>

        </table>
    
    <?php else : ?>

        <h3 class="wple_tb_title">No profiles found</h3>
 
        <p>
            You need to create a listing profile and assign a suitable feed template before you can start listing new products on eBay.
        </p>

    <?php endif; ?>

    <!-- <h3>Debug</h3> -->
    <!-- <pre><?php #print_r( $wpl_profiles ) ?></pre> -->


    <script>
        jQuery( document ).ready( function($) {
            $("#searchInput").keyup(function () {
                //split the current value of searchInput
                var data = this.value.toUpperCase().split(" ");
                //create a jquery object of the rows
                var jo = $(".wple_profile_results").find("tr");

                if (this.value == "") {
                    jo.show();
                    return;
                }
                //hide all the rows
                jo.hide();

                //Recusively filter the jquery object to get results.
                jo.filter(function (i, v) {
                    var $t = $(this);
                    for (var d = 0; d < data.length; ++d) {
                        if ($t.text().toUpperCase().indexOf(data[d]) > -1) {
                            return true;
                        }
                    }
                    return false;
                })
                    //show the rows that match.
                    .show();
            }).focus(function () {
                this.value = "";
                $(this).css({
                    "color": "black"
                });
                $(this).unbind('focus');
            }).css({
                "color": "#C0C0C0"
            });
        } );

    </script>

</body>
</html>
