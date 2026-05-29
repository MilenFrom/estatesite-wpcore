<?php
/**
 * Auto-ported from Houzez framework/functions/ to EstateSite Core.
 * Direct fave_* meta access has been rewritten to use \EstateSite\Core\Property::get/set.
 *
 * @package EstateSite\Core\Functions
 */

defined( 'ABSPATH' ) || exit;

/**
 * File Name: Membership Functions
 * Created by PhpStorm.
 * User: waqasriaz
 * Date: 26/03/16
 * Time: 5:38 PM
 */

/*-----------------------------------------------------------------------------------*/
// Houzez Register user with membership
/*-----------------------------------------------------------------------------------*/
add_action( 'wp_ajax_nopriv_houzez_register_user_with_membership', 'houzez_register_user_with_membership' );
add_action( 'wp_ajax_houzez_register_user_with_membership', 'houzez_register_user_with_membership' );

if( !function_exists('houzez_register_user_with_membership') ) {
    function houzez_register_user_with_membership() {

        check_ajax_referer('houzez_register_nonce2', 'houzez_register_security2');

        $allowed_html = array();

        $is_submit_listing = isset($_POST['is_submit_listing']) ? $_POST['is_submit_listing'] : '';

        $username          = trim( sanitize_text_field( wp_kses( $_POST['username'], $allowed_html ) ));
        $email             = trim( sanitize_text_field( wp_kses( $_POST['useremail'], $allowed_html ) ));
        $first_name        = trim( sanitize_text_field( wp_kses( $_POST['first_name'], $allowed_html ) ));
        $last_name         = trim( sanitize_text_field( wp_kses( $_POST['last_name'], $allowed_html ) ));

        $user_roles = array ( 'houzez_agency', 'houzez_agent', 'houzez_buyer', 'houzez_seller', 'houzez_owner' );

        if( isset( $_POST['user_role'] ) && empty($_POST['user_role']) ) {
            
            echo json_encode( array( 'success' => false, 'msg' => esc_html__(' The type field is empty.', 'houzez') ) );
            wp_die();
           
        } else {
            $user_role = apply_filters( 'houzez_user_role_with_membership', get_option( 'default_role' ) );

            if( $user_role == 'administrator' ) {
                $user_role = 'subscriber';
            }
            
            if( isset( $_POST['user_role'] ) && in_array( $_POST['user_role'], $user_roles ) ) {
                $user_role = isset( $_POST['user_role'] ) ? sanitize_text_field( wp_kses( $_POST['user_role'], $allowed_html ) ) : $user_role;
            }
        }

        if( houzez_option('header_register') != 1 ) {
            echo json_encode( array( 'success' => false, 'msg' => esc_html__('Access denied.', 'houzez') ) );
            wp_die();
        }

        if( get_option('users_can_register') != 1 ) {
            echo json_encode( array( 'success' => false, 'msg' => esc_html__('Access denied.', 'houzez') ) );
            wp_die();
        }

        if( empty( $username ) ) {
            echo json_encode( array( 'success' => false, 'msg' => esc_html__(' The username field is empty.', 'houzez') ) );
            wp_die();
        }
        if( strlen( $username ) < 3 ) {
            echo json_encode( array( 'success' => false, 'msg' => esc_html__('Minimum 3 characters required', 'houzez') ) );
            wp_die();
        }
        if (preg_match("/^[0-9A-Za-z_]+$/", $username) == 0) {
            echo json_encode( array( 'success' => false, 'msg' => esc_html__('Invalid username (do not use special characters or spaces)!', 'houzez') ) );
            wp_die();
        }
        if( empty( $email ) ) {
            echo json_encode( array( 'success' => false, 'msg' => esc_html__('The email field is empty.', 'houzez') ) );
            wp_die();
        }
        if( username_exists( $username ) ) {
            echo json_encode( array( 'success' => false, 'msg' => esc_html__('This username is already registered.', 'houzez') ) );
            wp_die();
        }
        if( email_exists( $email ) ) {
            echo json_encode( array( 'success' => false, 'msg' => esc_html__('This email address is already registered.', 'houzez') ) );
            wp_die();
        }

        if( !is_email( $email ) ) {
            echo json_encode( array( 'success' => false, 'msg' => esc_html__('Invalid email address.', 'houzez') ) );
            wp_die();
        }

        $phone_number = isset( $_POST['phone_number'] ) ? $_POST['phone_number'] : '';
        if( isset( $_POST['phone_number'] ) && empty($phone_number) && houzez_option('register_mobile', 0) == 1 ) {
            echo json_encode( array( 'success' => false, 'msg' => esc_html__('Please enter your number', 'houzez') ) );
            wp_die();
        }

        if( empty($is_submit_listing)) {
            $user_pass = trim(sanitize_text_field(wp_kses($_POST['register_pass'], $allowed_html)));
            $user_pass_retype = trim(sanitize_text_field(wp_kses($_POST['register_pass_retype'], $allowed_html)));

            if ($user_pass == '' || $user_pass_retype == '') {
                echo json_encode(array('success' => false, 'msg' => esc_html__('One of the password field is empty!', 'houzez')));
                wp_die();
            }

            if ($user_pass !== $user_pass_retype) {
                echo json_encode(array('success' => false, 'msg' => esc_html__('Passwords do not match', 'houzez')));
                wp_die();
            }
        } else {
            $user_pass = wp_generate_password( $length=12, $include_standard_special_chars=false );
        }

        do_action('houzez_before_register');

        // Validate captcha (Google reCaptcha or Cloudflare Turnstile)
        houzez_google_recaptcha_callback();

        // Rate limiting - prevent spam/abuse
        houzez_check_rate_limit('user_register');

        $user_id = wp_create_user( $username, $user_pass, $email );


        $user = get_user_by( 'id', $user_id );

        if( $user_id ) {
            update_user_meta( $user_id, 'first_name', $first_name );
            update_user_meta( $user_id, 'last_name', $last_name );


            // assign role
            $user = new WP_User( $user_id );
            $user->set_role( $user_role );

            houzez_wp_new_user_notification( $user_id, $user_pass, $phone_number );

            do_action('houzez_after_register', $user_id);

            $user_as_agent = houzez_option('user_as_agent');
            if( $user_as_agent == 'yes' ) {
                if ($user_role == 'houzez_agent' || $user_role == 'author') {
                    houzez_register_as_agent($username, $email, $user_id, $phone_number);

                } else if ($user_role == 'houzez_agency') {
                    houzez_register_as_agency($username, $email, $user_id, $phone_number);
                }
            }

            if( $user_role == 'houzez_agency' ) {
                update_user_meta( $user_id, 'fave_author_phone', $phone_number);
            } else {
                update_user_meta( $user_id, 'fave_author_mobile', $phone_number);
            }

            if( !is_wp_error($user) ) {
                
                wp_clear_auth_cookie();
                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID);
                //do_action( 'wp_login', $user->user_login );
                do_action( 'wp_login', $user->user_login, $user);

                echo json_encode( array( 'success' => true, 'msg' => esc_html__('Register successful, redirecting...', 'houzez') ) );
                wp_die();
            }
        }
        wp_die();

    }
}

/* -----------------------------------------------------------------------------------------------------------
 *  Set Listings as expire for per listing - keep
 -------------------------------------------------------------------------------------------------------------*/
if( !function_exists('houzez_listing_set_to_expire') ):
    function houzez_listing_set_to_expire($post_id){
        $prop = array(
            'ID'            => $post_id,
            'post_type'     => 'property',
            'post_status'   => 'expired'
        );

        wp_update_post($prop );
    
        houzez_listing_expire_meta($post_id);

        $user_id    =   houzez_get_author_by_post_id( $post_id );
        $user       =   get_user_by('id', $user_id);
        $user_email =   $user->user_email;

        $args = array(
            'expired_listing_url'  => get_permalink($post_id),
            'expired_listing_name' => get_the_title($post_id)
        );
        houzez_email_type( $user_email, 'free_listing_expired', $args );


    }
endif;

/* -----------------------------------------------------------------------------------------------------------
 *  Set Listings as expire for per listing - keep
 -------------------------------------------------------------------------------------------------------------*/
if( !function_exists('houzez_listing_expire_meta') ):
    function houzez_listing_expire_meta($post_id) {

        delete_post_meta( $post_id, 'houzez_manual_expire' );
        delete_post_meta( $post_id, '_houzez_expiration_date' );
        delete_post_meta( $post_id, '_houzez_expiration_date_status' );
        delete_post_meta( $post_id, '_houzez_expiration_date_options' );
        \EstateSite\Core\Property::set( $post_id, 'featured', 0 );
        update_post_meta( $post_id, 'houzez_featured_listing_date', '' );
        update_post_meta( $post_id, 'houzez_expired_listing_date', current_time( 'mysql' ));
        \EstateSite\Core\Property::set( $post_id, 'payment_status', 'not_paid' );
        \EstateSite\Core\Property::set( $post_id, 'payment_status', 'not_paid' );
    }
endif;

/* -----------------------------------------------------------------------------------------------------------
*  2checkout payment Membership
-------------------------------------------------------------------------------------------------------------*/
if( !function_exists('houzez_2checkout_payment_membership') ) {
    function houzez_2checkout_payment_membership() {

        global $current_user;

        $current_user = wp_get_current_user();
        $userID       =   $current_user->ID;
        $user_email   =   $current_user->user_email;
        $display_name =   $current_user->display_name;
        $user_mobile  = \EstateSite\Core\Property::get( $userID, 'mobile', null, 'user' );
        $privateKey = houzez_option('tco_private_key');
        $publishableKey = houzez_option('tco_publishable_key');
        $sellerId = houzez_option('tco_sellerID');
        $paymentAPI = houzez_option('paypal_api');

        require_once( get_template_directory() . '/framework/2checkout/lib/Twocheckout.php' );
    ?>
        <p class="" style="display:none" id="twocheckout_error_creditcard">
            <?php esc_html_e('Credit Card details are incorrect, please try again.', 'houzez');?>
        </p>

        <p class="alert alert-danger" style="display:none" id="twocheckout_error_required"></p>

        <fieldset>

            <input id="sellerId" type="hidden" maxlength="16" width="20" value="">
            <input id="publishableKey" type="hidden" width="20" value="">
            <input id="token" name="token" type="hidden" value="">
            <input type="hidden" name="houzez_2checkout" value="membership">


            <div class="row">
                <div class="col-sm-6 col-xs-12">
                    <div class="form-group">
                        <label for="tc_chname"><?php echo __( 'Card holder’s name', 'houzez' ) ?> <span class="required">*</span></label>
                        <input type="text" class="input-text form-control" maxlength="128" name="tc_chname" id="tc_chname" required autocomplete="off" value="<?php echo esc_attr($display_name);?>" />
                    </div>
                </div>
                <div class="col-sm-6 col-xs-12">
                    <div class="form-group">
                        <label for="tc_chaddress"><?php echo __( 'Street address (64 characters max)', 'houzez' ) ?> <span class="required">*</span></label>
                        <input type="text" class="input-text form-control" maxlength="64" name="tc_chaddress" id="tc_chaddress" required autocomplete="off" value="" />
                    </div>
                </div>
                <div class="col-sm-3 col-xs-6">
                    <div class="form-group">
                        <label for="tc_chcity"><?php echo __( 'City', 'houzez' ) ?> <span class="required">*</span></label>
                        <input type="text" class="input-text form-control" maxlength="64" name="tc_chcity" id="tc_chcity" required autocomplete="off" value="" />
                    </div>
                </div>
                <div class="col-sm-3 col-xs-6">
                    <div class="form-group">
                        <label for="tc_chstate"><?php echo __( 'State', 'houzez' ) ?> <span class="required">*</span></label>
                        <input type="text" class="input-text form-control" maxlength="64" required name="tc_chstate" id="tc_chstate" autocomplete="off" value="" />
                    </div>
                </div>
                <div class="col-sm-3 col-xs-6">
                    <div class="form-group">
                        <label for="tc_chzipCode"><?php echo __( 'zipCode', 'houzez' ) ?> <span class="required">*</span></label>
                        <input type="text" class="input-text form-control" maxlength="14" required name="tc_chzipCode" id="tc_chzipCode" autocomplete="off" value="" />
                    </div>
                </div>
                <div class="col-sm-3 col-xs-6">
                    <div class="form-group">
                        <label for="tc_chcountry"><?php echo __( 'Country', 'houzez' ) ?> <span class="required">*</span></label>
                        <select name="tc_chcountry" id="tc_chcountry" class="selectpicker" data-live-search="true">
                            <?php
                            foreach( houzez_countries_list() as $key=>$country ) {
                                echo '<option value="'.$key.'">'.$country.'</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="col-sm-6 col-xs-12">
                    <div class="form-group">
                        <label for="tc_chemail"><?php echo __( 'Email Address', 'houzez' ) ?> <span class="required">*</span></label>
                        <input type="email" class="input-text form-control" name="tc_chemail" id="tc_chemail" required autocomplete="off" value="<?php echo $user_email; ?>" />
                    </div>
                </div>
                <div class="col-sm-6 col-xs-12">
                    <div class="form-group">
                        <label for="tc_chphone"><?php echo __( 'Phone Number', 'houzez' ) ?></label>
                        <input type="text" class="input-text form-control" name="tc_chphone" id="tc_chphone" autocomplete="off" value="<?php echo esc_attr($user_mobile);?>" />
                    </div>
                </div>
            </div>

            <hr>

            <div class="row">
                <!-- Credit card number -->
                <div class="col-sm-6 col-xs-6">
                    <div class="form-group">
                        <label for="ccNo"><?php echo __( 'Credit Card number', 'houzez' ) ?> <span class="required">*</span></label>
                        <input type="text" class="input-text form-control" id="ccNo" required autocomplete="off" value="" />
                    </div>
                </div>
                <!-- Credit card expiration -->
                <div class="col-sm-3 col-xs-12">
                    <div class="form-group">
                        <label for="cc-expire-month"><?php echo __( 'Expiration date', 'houzez') ?> <span class="required">*</span></label>
                        <select id="expMonth" class="houzez-select houzez-cc-month form-control">
                            <option value=""><?php _e( 'Month', 'houzez' ) ?></option><?php
                            $months = array();
                            for ( $i = 1; $i <= 12; $i ++ ) {
                                $timestamp = mktime( 0, 0, 0, $i, 1 );
                                $months[ date( 'n', $timestamp ) ] = date( 'F', $timestamp );
                            }
                            foreach ( $months as $num => $name ) {
                                printf( '<option value="%02d">%s</option>', $num, $name );
                            } ?>
                        </select>
                    </div>
                </div>
                <div class="col-sm-3 col-xs-12">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <select id="expYear" class="houzez-select houzez-cc-year form-control">
                            <option value=""><?php _e( 'Year', 'houzez' ) ?></option>
                            <?php
                            $years = array();
                            for ( $i = date( 'y' ); $i <= date( 'y' ) + 15; $i ++ ) {
                                printf( '<option value="20%u">20%u</option>', $i, $i );
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <!-- Credit card security code -->
                <div class="col-sm-12 col-xs-12">
                    <label for="cvv"><?php _e( 'Card security code', 'houzez' ) ?> <span class="required">*</span></label>
                    <input type="text" class="input-text form-control" id="cvv" autocomplete="off" maxlength="4" style="width:55px">
                </div>
                <div class="col-sm-12 col-xs-12">
                    <span class="help-block" style="text-align: left"><?php _e( '3 or 4 digits usually found on the signature strip.', 'houzez' ) ?></span>
                </div>
            </div>

        </fieldset>

        <script type="text/javascript">
            var formName = "payment_review";
            var myForm = document.getElementsByName('houzez_checkout')[0];
            if(myForm) {
                myForm.id = "houzez_2checkout_form";
                formName = "houzez_2checkout_form";
            }
            jQuery('#' + formName).on("click", function(){
                jQuery('#houzez_complete_membership_2checkout').unbind('click');
                jQuery('#houzez_complete_membership_2checkout').click(function(e) {
                    if( houzez_tc_required () ) {
                        e.preventDefault();
                        retrieveToken();
                    }
                });
            });

            function successCallback(data) {
                clearPaymentFields();

                var myForm = document.getElementById('houzez_2checkout_form');
                // Set the token as the value for the token input
                myForm.token.value = data.response.token.token;
                // IMPORTANT: Here we call `submit()` on the form element directly instead of using jQuery to prevent and infinite token request loop.
                myForm.submit();
            }

            function errorCallback(data) {
                if (data.errorCode === 200) {
                    TCO.requestToken(successCallback, errorCallback, formName);
                } else if(data.errorCode == 401) {
                    clearPaymentFields();
                    jQuery('#houzez_complete_membership_2checkout').click(function(e) {
                        e.preventDefault();
                        retrieveToken();
                    });
                    jQuery("#twocheckout_error_creditcard").show();

                } else{
                    clearPaymentFields();
                    jQuery('#houzez_complete_membership_2checkout').click(function(e) {
                        e.preventDefault();
                        retrieveToken();
                    });
                    alert(data.errorMsg);
                }
            }

            var retrieveToken = function () {
                jQuery("#twocheckout_error_creditcard").hide();
                if (jQuery('div.payment_method_twocheckout:first').css('display') === 'block') {
                    var args = {
                        sellerId: '<?php echo $sellerId; ?>',
                        publishableKey: '<?php echo $publishableKey; ?>',
                        ccNo: jQuery("#ccNo").val(),
                        cvv: jQuery("#cvv").val(),
                        expMonth: jQuery("#expMonth").val(),
                        expYear: jQuery("#expYear").val()
                    };
                    // Make the token request
                    TCO.requestToken(successCallback, errorCallback, args);
                } else {
                    jQuery('#houzez_complete_membership_2checkout').unbind('click');
                    jQuery('#houzez_complete_membership_2checkout').click(function(e) {
                        return true;
                    });
                    jQuery('#houzez_complete_membership_2checkout').click();
                }
            }

            function clearPaymentFields() {
                jQuery('#ccNo').val('');
                jQuery('#cvv').val('');
                jQuery('#expMonth').val('');
                jQuery('#expYear').val('');
            }

            function houzez_tc_required() {

                var errorMsg = "";
                var tc_chname = document.getElementById("tc_chname");
                var tc_chemail = document.getElementById("tc_chemail");
                var tc_chaddress = document.getElementById("tc_chaddress");
                var tc_chcity = document.getElementById("tc_chcity");
                var tc_chstate = document.getElementById("tc_chstate");
                var tc_chzipcode = document.getElementById("tc_chzipcode");
                var tc_chcountry = document.getElementById("tc_chcountry");

                if (tc_chname.checkValidity() == false) {
                    errorMsg = '<?php echo __( 'Card holder’s name required', 'houzez' ) ?>';
                    jQuery("#twocheckout_error_required").show();
                    document.getElementById("twocheckout_error_required").innerHTML = errorMsg;
                    return false;
                }
                if (tc_chaddress.checkValidity() == false) {
                    errorMsg = '<?php echo __( 'Street address required', 'houzez' ) ?>';
                    jQuery("#twocheckout_error_required").show();
                    document.getElementById("twocheckout_error_required").innerHTML = errorMsg;
                    return false;
                }
                if (tc_chcity.checkValidity() == false) {
                    errorMsg = '<?php echo __( 'City required', 'houzez' ) ?>';
                    jQuery("#twocheckout_error_required").show();
                    document.getElementById("twocheckout_error_required").innerHTML = errorMsg;
                    return false;
                }
                if (tc_chstate.checkValidity() == false) {
                    errorMsg = '<?php echo __( 'State required', 'houzez' ) ?>';
                    jQuery("#twocheckout_error_required").show();
                    document.getElementById("twocheckout_error_required").innerHTML = errorMsg;
                    return false;
                }
                if (tc_chzipcode.checkValidity() == false) {
                    errorMsg = '<?php echo __( 'Zipcode required', 'houzez' ) ?>';
                    jQuery("#twocheckout_error_required").show();
                    document.getElementById("twocheckout_error_required").innerHTML = errorMsg;
                    return false;
                }
                if (tc_chcountry.checkValidity() == false) {
                    errorMsg = '<?php echo __( 'Country field required', 'houzez' ) ?>';
                    jQuery("#twocheckout_error_required").show();
                    document.getElementById("twocheckout_error_required").innerHTML = errorMsg;
                    return false;
                }
                if (tc_chemail.checkValidity() == false) {
                    errorMsg = '<?php echo __( 'Valid email address required', 'houzez' ) ?>';
                    jQuery("#twocheckout_error_required").show();
                    document.getElementById("twocheckout_error_required").innerHTML = errorMsg;
                    return false;
                }
                jQuery("#twocheckout_error_required").hide();
                return true;
            }

        </script>
        <?php if ( $paymentAPI == 'sandbox'): ?>
            <script type="text/javascript" src="https://sandbox.2checkout.com/checkout/api/script/publickey/<?php echo $sellerId ?>"></script>
            <script type="text/javascript" src="https://sandbox.2checkout.com/checkout/api/2co.js"></script>
        <?php else: ?>
            <script type="text/javascript" src="https://www.2checkout.com/checkout/api/script/publickey/<?php echo $sellerId ?>"></script>
            <script type="text/javascript" src="https://www.2checkout.com/checkout/api/2co.js"></script>
        <?php endif ?>

  <?php
    }
}

/* -----------------------------------------------------------------------------------------------------------
*  2checkout payment per listing
-------------------------------------------------------------------------------------------------------------*/
if( !function_exists('houzez_2checkout_payment') ) {
    function houzez_2checkout_payment() {

        global $current_user;

        $current_user = wp_get_current_user();
        $userID       =   $current_user->ID;
        $user_email   =   $current_user->user_email;
        $display_name =   $current_user->display_name;
        $user_mobile  = \EstateSite\Core\Property::get( $userID, 'mobile', null, 'user' );
        $privateKey = houzez_option('tco_private_key');
        $publishableKey = houzez_option('tco_publishable_key');
        $sellerId = houzez_option('tco_sellerID');
        $paymentAPI = houzez_option('paypal_api');

        require_once( get_template_directory() . '/framework/2checkout/lib/Twocheckout.php' );
        ?>
        <p class="alert alert-danger" style="display:none" id="twocheckout_error_creditcard">
            <?php esc_html_e('Credit Card details are incorrect, please try again.', 'houzez');?>
        </p>

        <p class="alert alert-danger" style="display:none" id="twocheckout_error_required"></p>

        <fieldset>

            <input id="sellerId" type="hidden" maxlength="16" width="20" value="">
            <input id="publishableKey" type="hidden" width="20" value="">
            <input id="token" name="token" type="hidden" value="">
            <input type="hidden" name="houzez_2checkout" value="per_listing">

            <div class="row">
                <div class="col-sm-6 col-xs-12">
                    <div class="form-group">
                        <label for="tc_chname"><?php echo __( 'Card holder’s name', 'houzez' ) ?> <span class="required">*</span></label>
                        <input type="text" class="input-text form-control" maxlength="128" name="tc_chname" id="tc_chname" required autocomplete="off" value="<?php echo esc_attr($display_name);?>" />
                    </div>
                </div>
                <div class="col-sm-6 col-xs-12">
                    <div class="form-group">
                        <label for="tc_chaddress"><?php echo __( 'Street address (64 characters max)', 'houzez' ) ?> <span class="required">*</span></label>
                        <input type="text" class="input-text form-control" maxlength="64" name="tc_chaddress" id="tc_chaddress" required autocomplete="off" value="" />
                    </div>
                </div>
                <div class="col-sm-3 col-xs-6">
                    <div class="form-group">
                        <label for="tc_chcity"><?php echo __( 'City', 'houzez' ) ?> <span class="required">*</span></label>
                        <input type="text" class="input-text form-control" maxlength="64" name="tc_chcity" id="tc_chcity" required autocomplete="off" value="" />
                    </div>
                </div>
                <div class="col-sm-3 col-xs-6">
                    <div class="form-group">
                        <label for="tc_chstate"><?php echo __( 'State', 'houzez' ) ?> <span class="required">*</span></label>
                        <input type="text" class="input-text form-control" maxlength="64" required name="tc_chstate" id="tc_chstate" autocomplete="off" value="" />
                    </div>
                </div>
                <div class="col-sm-3 col-xs-6">
                    <div class="form-group">
                        <label for="tc_chzipCode"><?php echo __( 'zipCode', 'houzez' ) ?> <span class="required">*</span></label>
                        <input type="text" class="input-text form-control" maxlength="14" required name="tc_chzipCode" id="tc_chzipCode" autocomplete="off" value="" />
                    </div>
                </div>
                <div class="col-sm-3 col-xs-6">
                    <div class="form-group">
                        <label for="tc_chcountry"><?php echo __( 'Country', 'houzez' ) ?> <span class="required">*</span></label>
                        <select name="tc_chcountry" id="tc_chcountry" class="selectpicker" data-live-search="true">
                            <?php
                            foreach( houzez_countries_list() as $key=>$country ) {
                                echo '<option value="'.$key.'">'.$country.'</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="col-sm-6 col-xs-12">
                    <div class="form-group">
                        <label for="tc_chemail"><?php echo __( 'Email Address', 'houzez' ) ?> <span class="required">*</span></label>
                        <input type="email" class="input-text form-control" name="tc_chemail" id="tc_chemail" required autocomplete="off" value="<?php echo $user_email; ?>" />
                    </div>
                </div>
                <div class="col-sm-6 col-xs-12">
                    <div class="form-group">
                        <label for="tc_chphone"><?php echo __( 'Phone Number', 'houzez' ) ?></label>
                        <input type="text" class="input-text form-control" name="tc_chphone" id="tc_chphone" autocomplete="off" value="<?php echo esc_attr($user_mobile);?>" />
                    </div>
                </div>
            </div>

            <hr>
            <div class="row">
                <!-- Credit card number -->
                <div class="col-sm-6 col-xs-12">
                    <div class="form-group">
                        <label for="ccNo"><?php echo __( 'Credit Card number', 'houzez' ) ?> <span class="required">*</span></label>
                        <input type="text" class="input-text form-control" id="ccNo" required autocomplete="off" value="" />
                    </div>
                </div>
                <!-- Credit card expiration -->
                <div class="col-sm-3 col-xs-12">
                    <div class="form-group">
                        <label for="cc-expire-month"><?php echo __( 'Expiration date', 'houzez') ?> <span class="required">*</span></label>
                        <select id="expMonth" class="houzez-select houzez-cc-month form-control">
                            <option value=""><?php _e( 'Month', 'houzez' ) ?></option><?php
                            $months = array();
                            for ( $i = 1; $i <= 12; $i ++ ) {
                                $timestamp = mktime( 0, 0, 0, $i, 1 );
                                $months[ date( 'n', $timestamp ) ] = date( 'F', $timestamp );
                            }
                            foreach ( $months as $num => $name ) {
                                printf( '<option value="%02d">%s</option>', $num, $name );
                            } ?>
                        </select>
                    </div>
                </div>
                <div class="col-sm-3 col-xs-12">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <select id="expYear" class="houzez-select houzez-cc-year form-control">
                            <option value=""><?php _e( 'Year', 'houzez' ) ?></option>
                            <?php
                            $years = array();
                            for ( $i = date( 'y' ); $i <= date( 'y' ) + 15; $i ++ ) {
                                printf( '<option value="20%u">20%u</option>', $i, $i );
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <!-- Credit card security code -->
                <div class="col-sm-12 col-xs-12">
                    <label for="cvv"><?php _e( 'Card security code', 'houzez' ) ?> <span class="required">*</span></label>
                    <input type="text" class="input-text form-control" id="cvv" autocomplete="off" maxlength="4" style="width:55px">
                </div>
                <div class="col-sm-12 col-xs-12">
                    <span class="help-block" style="text-align: left"><?php _e( '3 or 4 digits usually found on the signature strip.', 'houzez' ) ?></span>
                </div>
            </div>

        </fieldset>

        <script type="text/javascript">
            var formName = "payment_review";
            var myForm = document.getElementsByName('houzez_checkout')[0];
            if(myForm) {
                myForm.id = "houzez_2checkout_form";
                formName = "houzez_2checkout_form";
            }

            jQuery('#' + formName).on("click", function(){
                jQuery('#houzez_complete_order_2checkout').unbind('click');
                jQuery('#houzez_complete_order_2checkout').click(function (e) {
                    if( houzez_tc_required () ) {
                        e.preventDefault();
                        retrieveToken();
                    }
                });
            });

            function successCallback(data) {
                clearPaymentFields();

                var myForm = document.getElementById('houzez_2checkout_form');
                // Set the token as the value for the token input
                myForm.token.value = data.response.token.token;
                // IMPORTANT: Here we call `submit()` on the form element directly instead of using jQuery to prevent and infinite token request loop.
                myForm.submit();
            }

            function errorCallback(data) {
                if (data.errorCode === 200) {
                    TCO.requestToken(successCallback, errorCallback, formName);
                } else if(data.errorCode == 401) {
                    clearPaymentFields();
                    jQuery('#houzez_complete_order_2checkout').click(function(e) {
                        e.preventDefault();
                        retrieveToken();
                    });
                    jQuery("#twocheckout_error_creditcard").show();

                } else{
                    clearPaymentFields();
                    jQuery('#houzez_complete_order_2checkout').click(function(e) {
                        e.preventDefault();
                        retrieveToken();
                    });
                    alert(data.errorMsg);
                }
            }

            var retrieveToken = function () {
                jQuery("#twocheckout_error_creditcard").hide();
                if (jQuery('div.payment_method_twocheckout:first').css('display') === 'block') {
                    var args = {
                        sellerId: '<?php echo $sellerId; ?>',
                        publishableKey: '<?php echo $publishableKey; ?>',
                        ccNo: jQuery("#ccNo").val(),
                        cvv: jQuery("#cvv").val(),
                        expMonth: jQuery("#expMonth").val(),
                        expYear: jQuery("#expYear").val()
                    };
                    // Make the token request
                    TCO.requestToken(successCallback, errorCallback, args);
                } else {
                    jQuery('#houzez_complete_order_2checkout').unbind('click');
                    jQuery('#houzez_complete_order_2checkout').click(function(e) {
                        return true;
                    });
                    jQuery('#houzez_complete_order_2checkout').click();
                }
            }

            function clearPaymentFields() {
                jQuery('#ccNo').val('');
                jQuery('#cvv').val('');
                jQuery('#expMonth').val('');
                jQuery('#expYear').val('');
            }

            function houzez_tc_required() {

                var errorMsg = "";
                var tc_chname = document.getElementById("tc_chname");
                var tc_chemail = document.getElementById("tc_chemail");
                var tc_chaddress = document.getElementById("tc_chaddress");
                var tc_chcity = document.getElementById("tc_chcity");
                var tc_chstate = document.getElementById("tc_chstate");
                var tc_chzipcode = document.getElementById("tc_chzipcode");
                var tc_chcountry = document.getElementById("tc_chcountry");

                if (tc_chname.checkValidity() == false) {
                    errorMsg = '<?php echo __( 'Card holder’s name required', 'houzez' ) ?>';
                    jQuery("#twocheckout_error_required").show();
                    document.getElementById("twocheckout_error_required").innerHTML = errorMsg;
                    return false;
                }
                if (tc_chaddress.checkValidity() == false) {
                    errorMsg = '<?php echo __( 'Street address required', 'houzez' ) ?>';
                    jQuery("#twocheckout_error_required").show();
                    document.getElementById("twocheckout_error_required").innerHTML = errorMsg;
                    return false;
                }
                if (tc_chcity.checkValidity() == false) {
                    errorMsg = '<?php echo __( 'City required', 'houzez' ) ?>';
                    jQuery("#twocheckout_error_required").show();
                    document.getElementById("twocheckout_error_required").innerHTML = errorMsg;
                    return false;
                }
                if (tc_chstate.checkValidity() == false) {
                    errorMsg = '<?php echo __( 'State required', 'houzez' ) ?>';
                    jQuery("#twocheckout_error_required").show();
                    document.getElementById("twocheckout_error_required").innerHTML = errorMsg;
                    return false;
                }
                if (tc_chzipcode.checkValidity() == false) {
                    errorMsg = '<?php echo __( 'Zipcode required', 'houzez' ) ?>';
                    jQuery("#twocheckout_error_required").show();
                    document.getElementById("twocheckout_error_required").innerHTML = errorMsg;
                    return false;
                }
                if (tc_chcountry.checkValidity() == false) {
                    errorMsg = '<?php echo __( 'Country field required', 'houzez' ) ?>';
                    jQuery("#twocheckout_error_required").show();
                    document.getElementById("twocheckout_error_required").innerHTML = errorMsg;
                    return false;
                }
                if (tc_chemail.checkValidity() == false) {
                    errorMsg = '<?php echo __( 'Valid email address required', 'houzez' ) ?>';
                    jQuery("#twocheckout_error_required").show();
                    document.getElementById("twocheckout_error_required").innerHTML = errorMsg;
                    return false;
                }
                jQuery("#twocheckout_error_required").hide();
                return true;
            }

        </script>
        <?php if ( $paymentAPI == 'sandbox'): ?>
            <script type="text/javascript" src="https://sandbox.2checkout.com/checkout/api/script/publickey/<?php echo $sellerId ?>"></script>
            <script type="text/javascript" src="https://sandbox.2checkout.com/checkout/api/2co.js"></script>
        <?php else: ?>
            <script type="text/javascript" src="https://www.2checkout.com/checkout/api/script/publickey/<?php echo $sellerId ?>"></script>
            <script type="text/javascript" src="https://www.2checkout.com/checkout/api/2co.js"></script>
        <?php endif ?>

        <?php
    }
}


if( ! function_exists( 'houzez_stripe_product_exists' ) ) {
    function houzez_stripe_product_exists( $product_id ) {
        
        require_once( get_template_directory() . '/framework/stripe-php/init.php' );
        $stripe_secret_key = houzez_option('stripe_secret_key');

        if ( ! empty ( $stripe_secret_key ) ) {
            try {
                $stripe = new \Stripe\StripeClient( $stripe_secret_key );
                $product = $stripe->products->retrieve($product_id);

                // If the product is retrieved successfully, it exists.
                return true;
                
            } catch(\Stripe\Exception\InvalidRequestException $e) {
                // Invalid parameters were supplied to Stripe's API, meaning the product does not exist.
                return false;

            } catch(\Stripe\Exception\AuthenticationException $e) {
                // Authentication with Stripe's API failed, print the error and return false.
                echo $e->getMessage();
                return false;

            } catch(\Stripe\Exception\ApiConnectionException $e) {
                // Network communication with Stripe failed, print the error and return false.
                echo $e->getMessage();
                return false;

            } catch(\Stripe\Exception\ApiErrorException $e) {
                // A Stripe API error occurred, print the error and return false.
                echo $e->getMessage();
                return false;

            } catch(Exception $e) {
                // Some other error occurred, print the error and return false.
                echo $e->getMessage();
                return false;
            }
        }
    }
}


/*-----------------------------------------------------------------------------------*/
// Check stripe plan, if not exist then create
/*-----------------------------------------------------------------------------------*/
if( ! function_exists( 'houzez_stripe_create_plan' ) ) {
    function houzez_stripe_create_plan( $package_id ) {

        require_once( get_template_directory() . '/framework/stripe-php/init.php' );
        $stripe_secret_key = houzez_option('stripe_secret_key');
        $stripe_publishable_key = houzez_option('stripe_publishable_key');
        $package_price = get_post_meta( $package_id, 'fave_package_price', true ) /* TODO unmapped fave_ key: fave_package_price */;
        $billing_frequency = get_post_meta( $package_id, 'fave_billing_unit', true ) /* TODO unmapped fave_ key: fave_billing_unit */;
        $billing_period = get_post_meta( $package_id, 'fave_billing_time_unit', true ) /* TODO unmapped fave_ key: fave_billing_time_unit */;
        $submission_currency = houzez_option('currency_paid_submission');

        $stripe_product_id = get_option('houzez_stripe_product_id_' . $package_id);

        $check_stripe_product_exists = houzez_stripe_product_exists($stripe_product_id);

        //We have to create stripe product if already not created
        if ( ! $check_stripe_product_exists ) {
            $data = array(
                "name" => get_the_title($package_id),
                "type" => "service",
            );

            if ( ! empty ( $stripe_secret_key ) ) {

                try {
                    $stripe = new \Stripe\StripeClient( $stripe_secret_key );

                    $stripe_product_info = $stripe->products->create($data);

                    $stripe_product_id = isset($stripe_product_info->id) ? $stripe_product_info->id : -1;
                    if ( $stripe_product_id != -1 ) {
                        update_option('houzez_stripe_product_id_' . $package_id, $stripe_product_id);
                    }
                } catch(Exception $e) {  
                    $product_error = $e->getMessage();  
                    print_r($product_error);
                } 
            }
        } // end product id


        $stripe_plan_id = get_option( 'houzez_stripe_plan_id_'. $package_id. '_'.$submission_currency.'_'.$package_price.'_'.$billing_frequency.'_'.$billing_period );

        if ( ! empty(trim($stripe_product_id)) && empty( trim($stripe_plan_id)) ) {
            //create plan on product

            $interval_unit = get_post_meta( $package_id, 'fave_billing_time_unit', true ) /* TODO unmapped fave_ key: fave_billing_time_unit */;
            $billing_frequency = get_post_meta( $package_id, 'fave_billing_unit', true ) /* TODO unmapped fave_ key: fave_billing_unit */;
            

            if( in_array( $submission_currency, houzez_stripe_zero_decimal_currencies() ) ) {
                $package_price_for_stripe = $package_price;
            } else if( in_array( $submission_currency, houzez_stripe_3digits_currencies() ) ) {
                $package_price_for_stripe = round($package_price * 100) * 10;
            } else {
                $package_price_for_stripe = round( $package_price * 100, 2 );
            }

            $stripeData = array(
                'amount' => $package_price_for_stripe,
                'currency' => $submission_currency,
                'interval' => strtolower($interval_unit),
                'interval_count' => (int)$billing_frequency,
                'product' => $stripe_product_id,
            );

            try {
                $stripe = new \Stripe\StripeClient( $stripe_secret_key );

                $productInfo = $stripe->plans->create($stripeData);
                update_option( 'houzez_stripe_plan_id_'. $package_id. '_'.$submission_currency.'_'.$package_price.'_'.$billing_frequency.'_'.$billing_period,  $productInfo->id );
                update_post_meta($package_id, 'fave_package_stripe_id', $productInfo->id) /* TODO unmapped fave_ key: fave_package_stripe_id */;

            } catch(Exception $e) {  
                $api_error = $e->getMessage();  
                print_r($api_error);
            } 
            
        }
        
    }
}


/*-----------------------------------------------------------------------------------*/
// Property Stripe payment for package - Modified 12 June 2024
/*-----------------------------------------------------------------------------------*/
add_action('wp_ajax_houzez_stripe_package_payment', 'houzez_stripe_package_payment');
if( ! function_exists( 'houzez_stripe_package_payment' ) ) {
    function houzez_stripe_package_payment() {

        if ( ! is_user_logged_in() || ! houzez_check_role() ) {
            echo json_encode( array(
                'status'  => false,
                'success' => false,
                'message' => esc_html__( 'You are not allowed to purchase a membership package.', 'houzez' ),
                'msg'     => esc_html__( 'You are not allowed to purchase a membership package.', 'houzez' ),
            ) );
            wp_die();
        }

        require_once( get_template_directory() . '/framework/stripe-php/init.php' );
        $stripe_secret_key = houzez_option('stripe_secret_key');
        $stripe_publishable_key = houzez_option('stripe_publishable_key');

        $stripe = array(
            "secret_key" => $stripe_secret_key,
            "publishable_key" => $stripe_publishable_key
        );

        \Stripe\Stripe::setApiKey($stripe['secret_key']);

        $stripe = new \Stripe\StripeClient( $stripe['secret_key'] );

        $currency    = houzez_option('currency_paid_submission');
        $blogInfo    = get_bloginfo('name');
        $userID      = get_current_user_id();
        $package_id  = intval($_POST['package_id']);
        $user_email  = wp_get_current_user()->user_email;

        $planId = get_post_meta( $package_id, 'fave_package_stripe_id', true ) /* TODO unmapped fave_ key: fave_package_stripe_id */;
        $tax_rate_id = get_post_meta( $package_id, 'fave_stripe_taxId', true );
        $is_stripe_recurring   = sanitize_text_field( wp_unslash( $_POST['is_stripe_recurring'] ) );

        $return_link  =  houzez_get_template_link('template/template-stripe-charge.php');
        $cancelled_link  =  houzez_get_template_link('template/template-payment.php');
        $product_title     = get_the_title( $package_id );
        $product_image_url = get_the_post_thumbnail_url( $package_id, 'large' );

        $api_error = '';

        try {

            $stripe_customer_id = \EstateSite\Core\Property::get( $userID, 'stripe_user_profile', null, 'user' );

            if( $is_stripe_recurring == "true" && ! empty( $planId ) ) {

                // Check for existing subscription

                if( ! empty($stripe_customer_id) ) {
                    $subscriptions = $stripe->subscriptions->all(['customer' => $stripe_customer_id, 'status' => 'active']);

                    if (count($subscriptions->data) > 0) {
                        $existing_subscription = $subscriptions->data[0];
                        // Cancel existing subscription and create a new one
                        $stripe->subscriptions->cancel($existing_subscription->id);
                    }
                }

                $data = [
                    'success_url' => $return_link . '?houzez_stripe_recurring=1&is_houzez_membership=1&mode=package&pack_id='.esc_attr($package_id).'&success=1&session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => $cancelled_link . '?selected_package=' .$package_id. '&cancel=1',
                    'payment_method_types' => [
                      'card',
                    ],
                    'subscription_data' => [
                        'items' => [[
                            'plan' => $planId,
                        ]],
                        'metadata' => [
                            'payment_type' => 'subscription_fee',
                            'userID' => get_current_user_id(),
                            'package_id' => esc_attr($package_id)
                        ],
                    ],
                    'locale' => 'auto',
                    "metadata" => [
                        'payment_type' => 'subscription_fee',
                        'userID' => get_current_user_id(),
                        'package_id' => esc_attr($package_id)
                    ],
                ];

                if( $stripe_customer_id != '' ) {
                     $data['customer'] = $stripe_customer_id;
                }

                if($tax_rate_id != null && !empty(trim($tax_rate_id))){
                    $data['subscription_data']['default_tax_rates'] = [$tax_rate_id];
                }

                $checkout_session = \Stripe\Checkout\Session::create($data);

            } else {

                $amount  = get_post_meta( $package_id, 'fave_package_price', true ) /* TODO unmapped fave_ key: fave_package_price */;

                if( in_array( $currency, houzez_stripe_zero_decimal_currencies() ) ) {
                    $amount = $amount;
                } else if( in_array( $currency, houzez_stripe_3digits_currencies() ) ) {
                    $amount = round($amount * 100) * 10;
                } else {
                    $amount = round( $amount * 100, 2 );
                }

                $product_data = array( 'name' => esc_html( $product_title ) );
                if ( ! empty( $product_image_url ) ) {
                    $product_data['images'] = array( esc_url( $product_image_url ) );
                }

                $data = array(
                    'payment_method_types' => array( 'card' ),
                    'line_items' => array(
                        array(
                            'price_data' => array(
                                'currency'     => $currency,
                                'unit_amount'  => $amount,
                                'product_data' => $product_data,
                            ),
                            'quantity'   => 1,
                        ),
                    ),
                    'locale' => 'auto',
                    "metadata" => [
                        'user_id'     => get_current_user_id(),
                        "package_id"  => $package_id,
                        "title"       => get_the_title($package_id),
                    ],
                    'mode'        => 'payment',
                    'success_url' => $return_link . '?is_houzez_membership=0&mode=simple_package&pack_id='.esc_attr($package_id).'&success=1&session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => $cancelled_link . '?selected_package=' .$package_id. '&cancel=1',
                );

                if( $stripe_customer_id != '' ) {
                     $data['customer'] = $stripe_customer_id;
                }

                if($tax_rate_id != null && !empty(trim($tax_rate_id))){
                    $data['line_items'][0]['tax_rates'] = [$tax_rate_id];
                }

                $checkout_session = \Stripe\Checkout\Session::create($data);
                

            }
        } catch(Exception $e) {  
            $api_error = $e->getMessage();  
        } 

        if( empty($api_error) && $checkout_session ) { 
            $response = array( 
                'status' => true, 
                'message' => esc_html__('Checkout Session created successfully!', 'houzez'), 
                'sessionId' => $checkout_session['id'],
                'paymeny_link' => $checkout_session->url
            ); 
        }else{ 
            $response = array( 
                'status' => false, 
                'message' => esc_html__('Checkout Session creation failed!', 'houzez').' '.$api_error
            ); 
        } 
        update_user_meta( get_current_user_id(), 'houzez_stripe_temp_session_id', $checkout_session->id );
        echo json_encode($response);
        wp_die();

    }
}


/*-----------------------------------------------------------------------------------*/
// Property Stripe payment for per listing
/*-----------------------------------------------------------------------------------*/
add_action('wp_ajax_houzez_property_stripe_payment', 'houzez_property_stripe_payment');
if( !function_exists('houzez_property_stripe_payment') ) {
    function houzez_property_stripe_payment() {
        require_once( get_template_directory() . '/framework/stripe-php/init.php' );
        $stripe_secret_key = houzez_option('stripe_secret_key');
        $stripe_publishable_key = houzez_option('stripe_publishable_key');

        $stripe = array(
            "secret_key" => $stripe_secret_key,
            "publishable_key" => $stripe_publishable_key
        );

        \Stripe\Stripe::setApiKey($stripe['secret_key']);

        $propID        =   intval($_POST['prop_id']);
        $is_prop_featured   =   intval($_POST['is_prop_featured']);
        $is_prop_upgrade    =   intval($_POST['is_prop_upgrade']);
        $relist_mode    =   isset( $_POST['relist_mode'] ) ? esc_attr($_POST['relist_mode']) : '';
        $price_per_submission = houzez_option('price_listing_submission');
        $price_featured_submission = houzez_option('price_featured_listing_submission');
        $currency = houzez_option('currency_paid_submission');

        $blogInfo = get_bloginfo('name');

        $userID =   get_current_user_id();
        $post   =   get_post($propID);

        if( $post->post_author != $userID ){
            wp_die('Are you kidding?');
        }

        $price_per_submission       =   floatval( $price_per_submission );
        $price_featured_submission  =   floatval( $price_featured_submission );
        $submission_currency         =   esc_html( $currency );
        $payment_description        =   esc_html__('Listing payment on ','houzez').$blogInfo;

        // Get tax percentages
        $tax_percentage_per_listing = floatval(houzez_option('tax_percentage_per_listing'));
        $tax_percentage_featured = floatval(houzez_option('tax_percentage_featured'));
        
        // Calculate taxes
        $tax_per_listing = 0;
        $tax_featured = 0;
        
        if( !empty($tax_percentage_per_listing) && !empty($price_per_submission) ) {
            $tax_per_listing = ($tax_percentage_per_listing / 100) * $price_per_submission;
            $tax_per_listing = round($tax_per_listing, 2);
        }
        
        if( !empty($tax_percentage_featured) && !empty($price_featured_submission) ) {
            $tax_featured = ($tax_percentage_featured / 100) * $price_featured_submission;
            $tax_featured = round($tax_featured, 2);
        }

        $with_featured = 0;
        $is_upgrade = 0;

        if( $is_prop_featured == 0 ) {
            $total_price = $price_per_submission + $tax_per_listing;
            $total_price =  number_format( $total_price, 2, '.','' );
        } else {
            $total_price = $price_per_submission + $tax_per_listing + $price_featured_submission + $tax_featured;
            $total_price = number_format( $total_price, 2, '.','' );
            $payment_description = __('Submission & Featured Payment on ','houzez').$blogInfo;
            $with_featured = 1;

        }

        if ( $is_prop_upgrade == 1 ) {
            $total_price = $price_featured_submission + $tax_featured;
            $total_price =  number_format($total_price, 2, '.','');
            $payment_description =   esc_html__('Upgrade to featured listing on ','houzez').$blogInfo;
            $is_upgrade = 1;
        }

        if( in_array( $submission_currency, houzez_stripe_zero_decimal_currencies() ) ) {
            $total_price = $total_price;
        } else if( in_array( $submission_currency, houzez_stripe_3digits_currencies() ) ) {
            $total_price = round($total_price * 100) * 10;
        } else {
            $total_price = round( $total_price * 100, 2 );
        }

        $return_link  =  houzez_get_template_link('template/template-stripe-charge.php');
        $cancelled_link  =  houzez_get_template_link('template/template-payment.php');

        $api_error = '';

        try {
          $checkout_session = \Stripe\Checkout\Session::create([
            'success_url' => $return_link . '?session_id={CHECKOUT_SESSION_ID}&mode=per_listing',
            'cancel_url' => $cancelled_link . '?prop-id=' .$propID,
            'payment_method_types' => [
              'card',
              // 'alipay',
              // 'ideal',
              // 'sepa_debit',
              // 'giropay',
            ],
            'mode' => 'payment',
            'locale' => 'auto',
            "metadata" => [
                'user_id'        => $userID,
                "property_id"    => $propID,
                "title"          => get_the_title($propID),
                "submission_pay" => 1,
                "with_featured"  => $with_featured,
                "is_upgrade"     => $is_upgrade,
                'relist_mode'    => $relist_mode
            ],
            'line_items' => [[
              'price_data' => [
                'product_data' => [ 
                    'name' => $payment_description, 
                ], 
                'unit_amount' => $total_price,
                'currency' => $submission_currency, 
              ],
              'quantity' => 1,
            ]]
          ]);
        } catch(Exception $e) {  
            $api_error = $e->getMessage();  
        } 

        if( empty($api_error) && $checkout_session ) { 
            $response = array( 
                'status' => true, 
                'message' => esc_html__('Checkout Session created successfully!', 'houzez'), 
                'sessionId' => $checkout_session['id'],
                'paymeny_link' => $checkout_session->url
            ); 
        }else{ 
            $response = array( 
                'status' => false, 
                'message' => esc_html__('Checkout Session creation failed!', 'houzez').' '.$api_error
            ); 
        } 
        echo json_encode($response);
        wp_die();

    }
}


/*-----------------------------------------------------------------------------------*/
// Property paypal payment
/*-----------------------------------------------------------------------------------*/
add_action('wp_ajax_houzez_property_paypal_payment', 'houzez_property_paypal_payment');
if( !function_exists('houzez_property_paypal_payment') ) {
    function houzez_property_paypal_payment() {
        global $current_user;
        $propID        =   intval($_POST['prop_id']);
        $is_prop_featured   =   intval($_POST['is_prop_featured']);
        $is_prop_upgrade    =   intval($_POST['is_prop_upgrade']);
        $relist_mode    =   isset( $_POST['relist_mode'] ) ? esc_attr($_POST['relist_mode']) : '';
        $price_per_submission = houzez_option('price_listing_submission');
        $price_featured_submission = houzez_option('price_featured_listing_submission');
        $currency = houzez_option('currency_paid_submission');

        $blogInfo = esc_url( home_url() );

        wp_get_current_user();
        $userID =   $current_user->ID;
        $post   =   get_post($propID);

        if( $post->post_author != $userID ){
            wp_die('Are you kidding?');
        }

        $is_paypal_live             =   houzez_option('paypal_api');
        $host                       =   'https://api.sandbox.paypal.com';
        $price_per_submission       =   floatval( $price_per_submission );
        $price_featured_submission  =   floatval( $price_featured_submission );
        $submission_curency         =   esc_html( $currency );
        $payment_description        =   esc_html__('Listing payment on ','houzez').$blogInfo;

        if( $is_prop_featured == 0 ) {
            $total_price =  number_format( $price_per_submission, 2, '.','' );
        } else {
            $total_price = $price_per_submission + $price_featured_submission;
            $total_price = number_format( $total_price, 2, '.','' );
        }

        if ( $is_prop_upgrade == 1 ) {
            $total_price     =  number_format($price_featured_submission, 2, '.','');
            $payment_description =   esc_html__('Upgrade to featured listing on ','houzez').$blogInfo;
        }

        // Check if payal live
        if( $is_paypal_live =='live'){
            $host='https://api.paypal.com';
        }

        $url             =   $host.'/v1/oauth2/token';
        $postArgs        =   'grant_type=client_credentials';

        // Get Access token
        $paypal_token    =   houzez_get_paypal_access_token( $url, $postArgs );
        $url             =   $host.'/v2/checkout/orders';
        $cancel_link     =   houzez_dashboard_listings();
        $return_link     =   houzez_get_template_link('template/template-thankyou.php');

        /* Determine item name
        *--------------------------------------*/
        if( $is_prop_upgrade == 1 ) {
            $item_name = esc_html__('Upgrade to Featured Listing','houzez');
            $item_sku  = 'Upgrade Listing';
        } else if( $is_prop_featured == 1 ) {
            $item_name = esc_html__('Listing with Featured Payment option','houzez');
            $item_sku  = 'Featured Paid Listing';
        } else {
            $item_name = esc_html__('Listing Payment','houzez');
            $item_sku  = 'Paid Listing';
        }

        $order = array(
            'intent' => 'CAPTURE',
            'purchase_units' => array(
                array(
                    'description' => $payment_description,
                    'amount' => array(
                        'currency_code' => $submission_curency,
                        'value'         => $total_price,
                        'breakdown'     => array(
                            'item_total' => array(
                                'currency_code' => $submission_curency,
                                'value'         => $total_price,
                            ),
                        ),
                    ),
                    'items' => array(
                        array(
                            'name'        => $item_name,
                            'quantity'    => '1',
                            'unit_amount' => array(
                                'currency_code' => $submission_curency,
                                'value'         => $total_price,
                            ),
                            'sku' => $item_sku,
                        ),
                    ),
                ),
            ),
            'application_context' => array(
                'return_url' => $return_link,
                'cancel_url' => $cancel_link,
            ),
        );

        $jsonEncode = json_encode($order);
        $json_response = houzez_execute_paypal_request( $url, $jsonEncode, $paypal_token );

        $payment_approval_url = '';
        $order_id = isset($json_response['id']) ? $json_response['id'] : '';

        if( !empty($json_response['links']) ) {
            foreach ($json_response['links'] as $link) {
                if($link['rel'] == 'approve'){
                    $payment_approval_url = $link['href'];
                }
            }
        }

        // Save data in database for further use on processor page
        $output['order_id']            = $order_id;
        $output['paypal_token']        = $paypal_token;
        $output['property_id']         = $propID;
        $output['is_prop_featured']    = $is_prop_featured;
        $output['is_prop_upgrade']     = $is_prop_upgrade;
        $output['relist_mode']         = $relist_mode;

        $save_output[$current_user->ID]   =   $output;
        update_option('houzez_paypal_transfer',$save_output);

        print $payment_approval_url;

        wp_die();

    }
}

add_action( 'wp_ajax_houzez_paypal_package_payment', 'houzez_paypal_package_payment' );

if( !function_exists('houzez_paypal_package_payment') ) {
    function houzez_paypal_package_payment() {
        global $current_user;
        wp_get_current_user();
        $userID = $current_user->ID;

        if ( ! is_user_logged_in() || ! houzez_check_role() ) {
            wp_die( esc_html__( 'You are not allowed to purchase a membership package.', 'houzez' ) );
        }

        $total_taxes = 0;
        $allowed_html =   array();
        $blogInfo = esc_url( home_url() );

        $houzez_package_id      =   intval($_POST['houzez_package_id']);
        $houzez_package_name    =   wp_kses($_POST['houzez_package_name'],$allowed_html);
        $houzez_package_price   =   floatval(get_post_meta( $houzez_package_id, 'fave_package_price', true ) /* TODO unmapped fave_ key: fave_package_price */);
        

        $pack_tax = floatval(get_post_meta( $houzez_package_id, 'fave_package_tax', true ) /* TODO unmapped fave_ key: fave_package_tax */);
        if( !empty($pack_tax) && !empty($houzez_package_price) ) {
            $total_taxes = floatval($pack_tax)/100 * floatval($houzez_package_price);
            $total_taxes = round($total_taxes, 2);
        }
        $houzez_package_price = $houzez_package_price + $total_taxes;

        if( empty($houzez_package_price) && empty( $houzez_package_id ) ) {
            exit();
        }
        
        $houzez_package_price = number_format($houzez_package_price, 2, '.', '');


        $currency            = houzez_option('currency_paid_submission');
        $payment_description = $houzez_package_name.' '.__('Membership payment on ','houzez').$blogInfo;

        $is_paypal_live      = houzez_option('paypal_api');
        $host                = 'https://api.sandbox.paypal.com';

        if( $is_paypal_live =='live'){
            $host = 'https://api.paypal.com';
        }

        $url             =   $host.'/v1/oauth2/token';
        $postArgs        =   'grant_type=client_credentials';
        $access_token    =   houzez_get_paypal_access_token( $url, $postArgs );
        $url             =   $host.'/v2/checkout/orders';
        $return_url      = houzez_get_template_link('template/template-thankyou.php');
        $dash_profile_link   =  houzez_get_dashboard_profile_link();

        $order = array(
            'intent' => 'CAPTURE',
            'purchase_units' => array(
                array(
                    'description' => $payment_description,
                    'amount' => array(
                        'currency_code' => $currency,
                        'value'         => $houzez_package_price,
                        'breakdown'     => array(
                            'item_total' => array(
                                'currency_code' => $currency,
                                'value'         => $houzez_package_price,
                            ),
                        ),
                    ),
                    'items' => array(
                        array(
                            'name'        => __('Membership Payment ','houzez'),
                            'quantity'    => '1',
                            'unit_amount' => array(
                                'currency_code' => $currency,
                                'value'         => $houzez_package_price,
                            ),
                            'sku' => $houzez_package_name.' '.__('Membership Payment ','houzez'),
                        ),
                    ),
                ),
            ),
            'application_context' => array(
                'return_url' => $return_url,
                'cancel_url' => $dash_profile_link,
            ),
        );

        // Convert PHP array into json format
        $jsonEncode = json_encode($order);
        $json_response = houzez_execute_paypal_request( $url, $jsonEncode, $access_token );

        $payment_approval_url = '';
        $order_id = isset($json_response['id']) ? $json_response['id'] : '';

        if( !empty($json_response['links']) ) {
            foreach ($json_response['links'] as $link) {
                if($link['rel'] == 'approve'){
                    $payment_approval_url = $link['href'];
                }
            }
        }

        // Save data in database for further use on processor page
        $output['order_id']            = $order_id;
        $output['access_token']        = $access_token;
        $output['package_id']          = $houzez_package_id;

        $save_output[$userID]   =   $output;
        update_option('houzez_paypal_package_transfer', $save_output);
        update_user_meta( $userID, 'houzez_paypal_package', $output);

        print $payment_approval_url;

        wp_die();

    }
}

/* -----------------------------------------------------------------------------------------------------------
*  Recurring paypal payment Rest API
-------------------------------------------------------------------------------------------------------------*/
add_action( 'wp_ajax_houzez_recuring_paypal_package_payment', 'houzez_recuring_paypal_package_payment' );

if( !function_exists('houzez_recuring_paypal_package_payment') ) {
    function houzez_recuring_paypal_package_payment() {

            global $current_user;
            wp_get_current_user();
            $userID = $current_user->ID;

            if ( !is_user_logged_in() ) {
                wp_die('are you kidding?');
            }

            if( $userID === 0 ) {
                wp_die('are you kidding?');
            }

            if ( ! houzez_check_role() ) {
                wp_die( esc_html__( 'You are not allowed to purchase a membership package.', 'houzez' ) );
            }

            $houzez_package_id    = intval($_POST['houzez_package_id']);
            $is_package_exist     = get_posts('post_type=houzez_packages&p='.$houzez_package_id);

            if( !empty ( $is_package_exist ) ) {

                $access_token = '';

                $is_paypal_live      = houzez_option('paypal_api');
                $host                = 'https://api.sandbox.paypal.com';
                if( $is_paypal_live =='live'){
                    $host = 'https://api.paypal.com';
                }

                $url             =   $host.'/v1/oauth2/token';
                $postArgs        =   'grant_type=client_credentials';

                if(function_exists('houzez_get_paypal_access_token')){
                    $access_token    =   houzez_get_paypal_access_token( $url, $postArgs );
                }

                // Get existing billing plan
                $billing_plan = get_post_meta($houzez_package_id, 'houzez_paypal_billing_plan_'.$is_paypal_live, true);

                // Create new plan if one doesn't exist
                if( empty($billing_plan['id']) || empty($billing_plan) || !is_array($billing_plan) ) {
                    $plan_id = houzez_create_billing_plan($houzez_package_id, $access_token);
                    if( empty($plan_id) ) {
                        wp_die('Failed to create PayPal billing plan.');
                    }
                } else {
                    $plan_id = $billing_plan['id'];
                }

                echo houzez_create_paypal_agreement($houzez_package_id, $access_token, $plan_id);
                wp_die();
            }
            wp_die();

    }
}


/* -----------------------------------------------------------------------------------------------------------
*  Create PayPal Billing Plan — uses Subscriptions API /v1/billing/plans
-------------------------------------------------------------------------------------------------------------*/
if(!function_exists('houzez_create_billing_plan')) {
    function houzez_create_billing_plan($package_id, $access_token) {
        $blogInfo = esc_url( home_url() );
        $total_taxes = 0;
        $packPrice          =  get_post_meta( $package_id, 'fave_package_price', true ) /* TODO unmapped fave_ key: fave_package_price */;
        $packName           =  get_the_title($package_id);
        $billingPeriod      =  get_post_meta( $package_id, 'fave_billing_time_unit', true ) /* TODO unmapped fave_ key: fave_billing_time_unit */;
        $billingFreq        =  intval( get_post_meta( $package_id, 'fave_billing_unit', true ) /* TODO unmapped fave_ key: fave_billing_unit */ );
        $submissionCurency  =  houzez_option('currency_paid_submission');
        $plan_description   =  $packName.' '.esc_html__('Membership payment on ','houzez').$blogInfo;

        $pack_tax = get_post_meta( $package_id, 'fave_package_tax', true ) /* TODO unmapped fave_ key: fave_package_tax */;
        if( !empty($pack_tax) && !empty($packPrice) ) {
            $total_taxes = intval($pack_tax)/100 * $packPrice;
            $total_taxes = round($total_taxes, 2);
        }
        $packPrice = $packPrice + $total_taxes;
        $packPrice = number_format($packPrice, 2, '.', '');

        $is_paypal_live = houzez_option('paypal_api');
        $host = 'https://api.sandbox.paypal.com';
        if( $is_paypal_live == 'live' ){
            $host = 'https://api.paypal.com';
        }

        // Map Houzez billing period to PayPal interval_unit
        $interval_unit = strtoupper($billingPeriod); // Day->DAY, Week->WEEK, Month->MONTH, Year->YEAR

        $url = $host.'/v1/billing/plans';

        $plan_data = array(
            'product_id'  => houzez_get_paypal_product_id($package_id, $access_token, $host),
            'name'        => $packName,
            'description' => substr($plan_description, 0, 127),
            'status'      => 'ACTIVE',
            'billing_cycles' => array(
                array(
                    'frequency' => array(
                        'interval_unit'  => $interval_unit,
                        'interval_count' => $billingFreq,
                    ),
                    'tenure_type'  => 'REGULAR',
                    'sequence'     => 1,
                    'total_cycles' => 0, // Infinite
                    'pricing_scheme' => array(
                        'fixed_price' => array(
                            'value'         => $packPrice,
                            'currency_code' => $submissionCurency,
                        ),
                    ),
                ),
            ),
            'payment_preferences' => array(
                'auto_bill_outstanding'     => true,
                'payment_failure_threshold' => 3,
            ),
        );

        $jsonEncode = json_encode($plan_data);
        $json_response = houzez_execute_paypal_request( $url, $jsonEncode, $access_token );

        // If plan creation failed, product ID may be stale — clear cache and retry once
        if( empty($json_response['id']) ) {
            delete_option('houzez_paypal_product_id_'.$is_paypal_live);
            $plan_data['product_id'] = houzez_get_paypal_product_id($package_id, $access_token, $host);
            if( !empty($plan_data['product_id']) ) {
                $jsonEncode = json_encode($plan_data);
                $json_response = houzez_execute_paypal_request( $url, $jsonEncode, $access_token );
            }
        }

        if( !empty($json_response['id']) && isset($json_response['status']) && $json_response['status'] == 'ACTIVE' ) {
            $billing_info = array();
            $billing_info['id']          = $json_response['id'];
            $billing_info['name']        = $json_response['name'];
            $billing_info['description'] = $json_response['description'] ?? '';
            $billing_info['status']      = 'ACTIVE';

            update_post_meta($package_id, 'houzez_paypal_billing_plan_'.$is_paypal_live, $billing_info);
            return $json_response['id'];
        }

        return false;
    }
}

/* -----------------------------------------------------------------------------------------------------------
*  Get or create PayPal Product for Subscriptions API (required by /v1/billing/plans)
-------------------------------------------------------------------------------------------------------------*/
if(!function_exists('houzez_get_paypal_product_id')) {
    function houzez_get_paypal_product_id($package_id, $access_token, $host) {
        $is_paypal_live = houzez_option('paypal_api');
        $product_id = get_option('houzez_paypal_product_id_'.$is_paypal_live);

        if( !empty($product_id) ) {
            return $product_id;
        }

        $url = $host.'/v1/catalogs/products';
        $product_data = array(
            'name'        => get_bloginfo('name').' '.esc_html__('Membership', 'houzez'),
            'description' => esc_html__('Membership packages', 'houzez'),
            'type'        => 'SERVICE',
            'category'    => 'SOFTWARE',
        );

        $jsonEncode = json_encode($product_data);
        $json_response = houzez_execute_paypal_request($url, $jsonEncode, $access_token);

        if( !empty($json_response['id']) ) {
            update_option('houzez_paypal_product_id_'.$is_paypal_live, $json_response['id']);
            return $json_response['id'];
        }

        return '';
    }
}


/* -----------------------------------------------------------------------------------------------------------
*  PayPal create subscription — uses Subscriptions API /v1/billing/subscriptions
-------------------------------------------------------------------------------------------------------------*/
function houzez_create_paypal_agreement($package_id, $access_token, $plan_id) {
    global $current_user;
    wp_get_current_user();
    $userID = $current_user->ID;

    $host = 'https://api.sandbox.paypal.com';
    $is_paypal_live = houzez_option('paypal_api');
    if( $is_paypal_live == 'live' ){
        $host = 'https://api.paypal.com';
    }

    $date = date_i18n( get_option('date_format').' '.get_option('time_format') );
    $return_url = houzez_get_template_link('template/template-thankyou.php');
    $cancel_url = houzez_get_dashboard_profile_link();

    $url = $host.'/v1/billing/subscriptions';

    $subscription_data = array(
        'plan_id'    => $plan_id,
        'application_context' => array(
            'brand_name'          => get_bloginfo('name'),
            'user_action'         => 'SUBSCRIBE_NOW',
            'return_url'          => $return_url,
            'cancel_url'          => $cancel_url,
        ),
    );

    $json      = json_encode($subscription_data);
    $json_resp = houzez_execute_paypal_request($url, $json, $access_token);

    $payment_approval_url = '';
    $subscription_id = isset($json_resp['id']) ? $json_resp['id'] : '';

    if( !empty($json_resp['links']) ) {
        foreach ($json_resp['links'] as $link) {
            if($link['rel'] == 'approve'){
                $payment_approval_url = $link['href'];
            }
        }
    }

    $output = array();
    $output['subscription_id']  = $subscription_id;
    $output['access_token']     = $access_token;
    $output['package_id']       = $package_id;
    $output['recursive']        = 1;
    $output['date']             = $date;

    $save_output[$userID] = $output;
    update_option('houzez_paypal_package_transfer', $save_output);
    update_user_meta( $userID, 'houzez_paypal_package', $output);

    return $payment_approval_url;
}


/* -----------------------------------------------------------------------------------------------------------
*  Free Membership package
-------------------------------------------------------------------------------------------------------------*/
add_action( 'wp_ajax_nopriv_houzez_free_membership_package', 'houzez_free_membership_package' );
add_action( 'wp_ajax_houzez_free_membership_package', 'houzez_free_membership_package' );

if( !function_exists('houzez_free_membership_package') ) {

    function houzez_free_membership_package() {

        global $current_user;
        $current_user = wp_get_current_user();

        if (!is_user_logged_in()) {
            exit('Are you kidding?');
        }

        if ( ! houzez_check_role() ) {
            exit( esc_html__( 'You are not allowed to purchase a membership package.', 'houzez' ) );
        }

        $userID = $current_user->ID;
        $user_email = $current_user->user_email;
        $selected_pack = intval($_POST['selected_package']);
        $total_price = get_post_meta($selected_pack, 'fave_package_price', true) /* TODO unmapped fave_ key: fave_package_price */;
        $currency = esc_html(houzez_option('currency_symbol'));
        $where_currency = esc_html(houzez_option('currency_position'));
        $wire_payment_instruction = houzez_option('direct_payment_instruction');
        $is_featured = 0;
        $is_upgrade = 0;
        $paypal_tax_id = '';
        $paymentMethod = '--';
        $time = time();
        $date = date('Y-m-d H:i:s', $time);

        if ($total_price != 0) {
            if ($where_currency == 'before') {
                $total_price = $currency . ' ' . $total_price;
            } else {
                $total_price = $total_price . ' ' . $currency;
            }
        }

        // insert invoice
        $invoiceID = houzez_generate_invoice('package', 'one_time', $selected_pack, $date, $userID, $is_featured, $is_upgrade, $paypal_tax_id, $paymentMethod, 1);

        houzez_save_user_packages_record($userID, $selected_pack);
        houzez_update_membership_package($userID, $selected_pack);
        update_post_meta( $invoiceID, 'invoice_payment_status', 1 );
        update_user_meta( $userID, 'user_had_free_package', 'yes' );


        $admin_email      =  get_bloginfo('admin_email');

        $args = array(
            'invoice_no'      =>  $invoiceID,
            'total_price'     =>  $total_price,
        );


        $thankyou_page_link = houzez_get_template_link('template/template-thankyou.php');

        if (!empty($thankyou_page_link)) {
            $separator = (parse_url($thankyou_page_link, PHP_URL_QUERY) == NULL) ? '?' : '&';
            $parameter = 'free_package='.$invoiceID;
            print $thankyou_page_link . $separator . $parameter;
        }
        wp_die();
    }
}


/* -----------------------------------------------------------------------------------------------------------
 *  Mollie Payment gateway
 -------------------------------------------------------------------------------------------------------------*/
add_action( 'wp_ajax_nopriv_houzez_mollie_package_payment', 'houzez_mollie_package_payment' );
add_action( 'wp_ajax_houzez_mollie_package_payment', 'houzez_mollie_package_payment' );

if( !function_exists('houzez_mollie_package_payment') ) {
    function houzez_mollie_package_payment()
    {
        if ( ! is_user_logged_in() || ! houzez_check_role() ) {
            wp_die( esc_html__( 'You are not allowed to purchase a membership package.', 'houzez' ) );
        }

        require_once( get_template_directory() . '/framework/mollie-api-php/src/Mollie/API/Autoloader.php' );

        $mollie = new Mollie_API_Client;
        $mollie->setApiKey("test_Rm8HhW8y3sexP6whAUCtDUn2u2TQ32");

        $order_id = time();

        global $current_user;
        wp_get_current_user();
        $userID = $current_user->ID;

        $allowed_html = array();
        $blogInfo = esc_url(home_url());
        $return_url      = houzez_get_template_link('template/template-thankyou.php');
        $webhookUrl      = houzez_get_template_link('template/template-mollie.php');
        $houzez_package_name = wp_kses($_POST['houzez_package_name'], $allowed_html);
        $houzez_package_price = $_POST['houzez_package_price'];
        $houzez_package_id = $_POST['houzez_package_id'];

        if (empty($houzez_package_price) && empty($houzez_package_id)) {
            exit();
        }

        $currency = houzez_option('currency_paid_submission');
        $payment_description = $houzez_package_name . ' ' . esc_html__('Membership payment on ', 'houzez') . $blogInfo;

        /*
         * Payment parameters:
         *   amount        Amount in EUROs. This example creates a € 10,- payment.
         *   description   Description of the payment.
         *   redirectUrl   Redirect location. The customer will be redirected there after the payment.
         *   webhookUrl    Webhook location, used to report when the payment changes state.
         *   metadata      Custom metadata that is stored with the payment.
         */
        $payment = $mollie->payments->create(array(
            "amount"       => $houzez_package_price,
            "method"       => Mollie_API_Object_Method::IDEAL,
            "description"  => $payment_description,
            "redirectUrl"  => $return_url,
            "webhookUrl"   => $webhookUrl,
            "metadata"     => array(
                "order_id" => $order_id,
                "user_id"   => $userID,
                "package_id"   => $houzez_package_id,
            ),
        ));

        // Save data in database for further use on processor page
        $output['payment_execute_url'] = $payment->getPaymentUrl();
        $output['package_id']          = $houzez_package_id;
        $output['id']                  = $payment->id;
        $output['order_id']            = $order_id;
        $output['status']              = $payment->status;


        $save_output[$userID]   =   $output;
        update_option('houzez_mollie_package', $save_output);
        update_user_meta( $userID, 'houzez_mollie_package', $output);

        print $payment->getPaymentUrl();
        wp_die();
    }
}

/* -----------------------------------------------------------------------------------------------------------
 *  Make Property Featured
 -------------------------------------------------------------------------------------------------------------*/
add_action( 'wp_ajax_houzez_make_prop_featured', 'houzez_make_prop_featured' );

if( !function_exists('houzez_make_prop_featured') ):
    function  houzez_make_prop_featured(){
        $userID = get_current_user_id();

        $packageUserId = $userID;
        $agent_agency_id = houzez_get_agent_agency_id( $userID );
        if( $agent_agency_id ) {
            $packageUserId = $agent_agency_id;
        }

        $agencyAgentsArray = array();
        $agencyAgents = houzez_get_agency_agents( $userID );
        if( $agencyAgents ) {
            $agencyAgentsArray = $agencyAgents;
        }

        $prop_id = intval( $_POST['propid'] );
        $prop_type = $_POST['prop_type'];
        $post = get_post( $prop_id );
        $post_author = $post->post_author;
        
        if( $post_author == $userID || in_array($post_author, $agencyAgentsArray)) {
            if( $prop_type == 'membership' ) {
                if (houzez_get_featured_remaining_listings($packageUserId) > 0) {
                    houzez_update_package_featured_listings($packageUserId);
                    \EstateSite\Core\Property::set( $prop_id, 'featured', 1 );
                    echo json_encode(array('success' => true, 'msg' => ''));
                    wp_die();
                } else {
                    echo json_encode(array('success' => false, 'msg' => ''));
                    wp_die();
                }
            } elseif( $prop_type == 'free' ) {
                \EstateSite\Core\Property::set( $prop_id, 'featured', 1 );
                update_post_meta( $prop_id, 'houzez_featured_listing_date', current_time( 'mysql' ) );
                echo json_encode(array('success' => true, 'msg' => ''));
                wp_die();
            }
        }
        wp_die();
    }
endif; // end

/* -----------------------------------------------------------------------------------------------------------
 *  Remove Property Featured
 -------------------------------------------------------------------------------------------------------------*/
add_action( 'wp_ajax_nopriv_houzez_remove_prop_featured', 'houzez_remove_prop_featured');
add_action( 'wp_ajax_houzez_remove_prop_featured', 'houzez_remove_prop_featured' );

if( !function_exists('houzez_remove_prop_featured') ):
    function  houzez_remove_prop_featured(){
        $userID = get_current_user_id();

        $packageUserId = $userID;
        $agent_agency_id = houzez_get_agent_agency_id( $userID );
        if( $agent_agency_id ) {
            $packageUserId = $agent_agency_id;
        }

        $agencyAgentsArray = array();
        $agencyAgents = houzez_get_agency_agents( $userID );
        if( $agencyAgents ) {
            $agencyAgentsArray = $agencyAgents;
        }

        $prop_id = intval( $_POST['propid'] );
        $post = get_post( $prop_id );
        $post_author = $post->post_author;

        if( $post_author == $userID || in_array($post_author, $agencyAgentsArray)) {
            \EstateSite\Core\Property::set( $prop_id, 'featured', 0 );
            update_post_meta( $prop_id, 'houzez_featured_listing_date', '' );
            $package_id = get_the_author_meta('package_id', $packageUserId );
            $user_featured_listings = get_the_author_meta('package_featured_listings', $packageUserId );
            $package_featured_lists = get_post_meta($package_id, 'fave_package_featured_listings', true) /* TODO unmapped fave_ key: fave_package_featured_listings */;

            if( $user_featured_listings < $package_featured_lists ) {
                update_user_meta( $packageUserId, 'package_featured_listings', $user_featured_listings+1 );
            }
            echo json_encode(array('success' => true, 'msg' => ''));
            wp_die();
        }
        wp_die();
    }
endif; // end

/* -----------------------------------------------------------------------------------------------------------
 *  Houzez property actions
 -------------------------------------------------------------------------------------------------------------*/
// add_action( 'wp_ajax_houzez_property_actions', 'houzez_property_actions' );

// if( !function_exists('houzez_property_actions') ):
//     function  houzez_property_actions(){
//         $userID = get_current_user_id();

//         $packageUserId = $userID;
//         $agent_agency_id = houzez_get_agent_agency_id( $userID );
//         if( $agent_agency_id ) {
//             $packageUserId = $agent_agency_id;
//         }


//         $prop_id = intval( $_POST['propid'] );
//         $type = $_POST['type'];

//         if( $type == 'set_featured' ) {
//             \EstateSite\Core\Property::set( $prop_id, 'featured', 1 );
//         } else if ( $type == 'remove_featured' ) {
//             \EstateSite\Core\Property::set( $prop_id, 'featured', 0 );

//         } else if ( $type == 'approve' ) {

//             $listing_status = get_post_status($prop_id); // get listing status before publish.

//             $listing_data = array(
//                 'ID' => $prop_id,
//                 'post_status' => 'publish'
//             );
//             wp_update_post($listing_data);

//             $author_id  = get_post_field ('post_author', $prop_id);
//             $user       = get_user_by('id', $author_id );
//             $user_email = $user->user_email;

//             $args = array(
//                 'listing_title' => get_the_title($prop_id),
//                 'listing_url' => get_permalink($prop_id)
//             );
//             houzez_email_type( $user_email,'listing_approved', $args );

//             if( $listing_status == 'disapproved' && houzez_get_remaining_listings($author_id) > 0 ) {
//                 houzez_update_package_listings($author_id);
//             }

//         } else if ( $type == 'disapprove' ) {

//             $listing_data = array(
//                 'ID' => $prop_id,
//                 'post_status' => 'disapproved'
//             );
//             wp_update_post($listing_data);

//             $author_id  = get_post_field ('post_author', $prop_id);
//             $user       = get_user_by('id', $author_id );
//             $user_email = $user->user_email;

//             $args = array(
//                 'listing_title' => get_the_title($prop_id),
//                 'listing_url' => get_permalink($prop_id)
//             );
//             houzez_email_type( $user_email,'listing_disapproved', $args );

//             $package_id = get_the_author_meta('package_id', $author_id );
//             $user_package_listings = get_the_author_meta('package_listings', $author_id );
//             $packagelistings = get_post_meta($package_id, 'fave_package_listings', true) /* TODO unmapped fave_ key: fave_package_listings */;

//             if( $user_package_listings < $packagelistings ) {
//                 update_user_meta( $author_id, 'package_listings', $user_package_listings+1 );
//             }

//         } else if ( $type == 'expire' ) {

//             $listing_data = array(
//                 'ID' => $prop_id,
//                 'post_status' => 'expired'
//             );
//             wp_update_post($listing_data);

//             houzez_listing_expire_meta($prop_id);

//             $author_id   = get_post_field ('post_author', $prop_id);
//             $user        = get_user_by('id', $author_id );
//             $user_email  = $user->user_email;

//             $args = array(
//                 'listing_title' => get_the_title($prop_id),
//                 'listing_url' => get_permalink($prop_id)
//             );
//             houzez_email_type( $user_email,'listing_expired', $args );

//         } else if ( $type == 'publish' ) {

//             $listing_data = array(
//                 'ID' => $prop_id,
//                 'post_status' => 'publish',
//                 'post_date' => current_time('mysql'), // Update to current local date and time
//                 'post_date_gmt' => get_gmt_from_date(current_time('mysql')) // Update to current GMT date and time
//             );
//             wp_update_post($listing_data);
//             \EstateSite\Core\Property::set( $prop_id, 'featured', '0' );
//         }

//         echo json_encode(array('success' => true, 'msg' => ''));
//         wp_die();

//     }
// endif; // end

add_action( 'wp_ajax_houzez_property_actions', 'houzez_property_actions' );
if( !function_exists('houzez_property_actions') ) {
    function houzez_property_actions() {
        // TODO: re-enable when the dashboard JS is updated to pass `nonce`.
        // check_ajax_referer( 'houzez_property_actions_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'houzez' ) ) );
        }

        $user_id = get_current_user_id();
        $prop_id = isset( $_POST['propid'] ) ? intval( $_POST['propid'] ) : 0;
        $type    = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';

        if ( ! $prop_id || get_post_type( $prop_id ) !== 'property' ) {
            wp_send_json_error( array( 'message' => __( 'Invalid property ID.', 'houzez' ) ) );
        }

        if ( ! current_user_can( 'edit_post', $prop_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'houzez' ) ) );
        }

        // Lock down moderation / billing-bypass actions to administrators / editors.
        // Authors retain only `submit_listing` (re-submit their own draft for review).
        $admin_only_types = array( 'approve', 'disapprove', 'expire', 'publish', 'set_featured', 'remove_featured' );
        if ( in_array( $type, $admin_only_types, true ) && ! houzez_is_admin() && ! houzez_is_editor() ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'houzez' ) ) );
        }

        $result = houzez_process_property_action( $prop_id, $type, $user_id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        } else {
            wp_send_json_success( $result );
        }
    }
}



 /**
 * Process a property action.
 *
 * @param int    $prop_id The property ID.
 * @param string $type    The action type.
 * @param int    $user_id The current user ID.
 *
 * @return array|WP_Error Result array on success, WP_Error on failure.
 */
 if( ! function_exists('houzez_process_property_action') ) {
    function houzez_process_property_action( $prop_id, $type, $user_id ) {
        // Retrieve property details
        $author_id     = get_post_field( 'post_author', $prop_id );
        $listing_title = get_the_title( $prop_id );
        $listing_url   = get_permalink( $prop_id );

        switch ( $type ) {

            case 'set_featured':
                \EstateSite\Core\Property::set( $prop_id, 'featured', 1 );
                break;

            case 'remove_featured':
                \EstateSite\Core\Property::set( $prop_id, 'featured', 0 );
                break;

            case 'approve':
                // Store current status to check later
                $listing_status = get_post_status( $prop_id );

                // Publish the property
                wp_update_post( array(
                    'ID'          => $prop_id,
                    'post_status' => 'publish',
                ) );

                // Send approval email
                $args = array(
                    'listing_title' => $listing_title,
                    'listing_url'   => $listing_url,
                );
                houzez_email_type( get_userdata( $author_id )->user_email, 'listing_approved', $args );

                // If previously disapproved and user has available listings, update package
                if ( 'disapproved' === $listing_status && houzez_get_remaining_listings( $author_id ) > 0 ) {
                    houzez_update_package_listings( $author_id );
                }
                break;

            case 'disapprove':
                wp_update_post( array(
                    'ID'          => $prop_id,
                    'post_status' => 'disapproved',
                ) );

                $args = array(
                    'listing_title' => $listing_title,
                    'listing_url'   => $listing_url,
                );
                houzez_email_type( get_userdata( $author_id )->user_email, 'listing_disapproved', $args );

                // Adjust package listings if below package limit
                $package_id            = get_the_author_meta( 'package_id', $author_id );
                $user_package_listings = (int) get_the_author_meta( 'package_listings', $author_id );
                $packagelistings       = (int) get_post_meta( $package_id, 'fave_package_listings', true ) /* TODO unmapped fave_ key: fave_package_listings */;
                if ( $user_package_listings < $packagelistings ) {
                    update_user_meta( $author_id, 'package_listings', $user_package_listings + 1 );
                }
                break;

            case 'expire':
                wp_update_post( array(
                    'ID'          => $prop_id,
                    'post_status' => 'expired',
                ) );

                houzez_listing_expire_meta( $prop_id );

                $args = array(
                    'listing_title' => $listing_title,
                    'listing_url'   => $listing_url,
                );
                houzez_email_type( get_userdata( $author_id )->user_email, 'listing_expired', $args );
                break;

            case 'publish':
                wp_update_post( array(
                    'ID'             => $prop_id,
                    'post_status'    => 'publish',
                    'post_date'      => current_time( 'mysql' ),
                    'post_date_gmt'  => get_gmt_from_date( current_time( 'mysql' ) ),
                ) );
                \EstateSite\Core\Property::set( $prop_id, 'featured', 0 );
                break;

            case 'submit_listing':
                // For non-admin users submitting draft properties
                $listings_admin_approved = houzez_option('listings_admin_approved');

                if (houzez_is_admin() || houzez_is_editor()) {
                    $new_status = 'publish';
                } else {
                    $new_status = ($listings_admin_approved === 'yes') ? 'pending' : 'publish';
                }

                wp_update_post(array(
                    'ID'             => $prop_id,
                    'post_status'    => $new_status,
                    'post_date'      => current_time('mysql'),
                    'post_date_gmt'  => get_gmt_from_date(current_time('mysql')),
                ));

                // Send notification email if submitted for approval
                if ($new_status === 'pending') {
                    $args = array(
                        'listing_title' => $listing_title,
                        'listing_url'   => $listing_url,
                    );
                    houzez_email_type(get_userdata($author_id)->user_email, 'listing_submitted', $args);
                }
                break;

            default:
                return new WP_Error( 'invalid_action', __( 'Invalid action type.', 'houzez' ) );
        }

        return array( 'success' => true, 'message' => __( 'Action completed successfully.', 'houzez' ) );
    }
}


if( ! function_exists( 'houzez_update_membership_package' ) ) {
    function houzez_update_membership_package( $user_id, $package_id ) {

        // Get selected package listings
        $pack_listings            =   get_post_meta( $package_id, 'fave_package_listings', true ) /* TODO unmapped fave_ key: fave_package_listings */;
        $pack_featured_listings   =   get_post_meta( $package_id, 'fave_package_featured_listings', true ) /* TODO unmapped fave_ key: fave_package_featured_listings */;
        $pack_unlimited_listings  =   get_post_meta( $package_id, 'fave_unlimited_listings', true ) /* TODO unmapped fave_ key: fave_unlimited_listings */;
        if( $pack_featured_listings == '' ) {
            $pack_featured_listings = 0;
        }

        $user_current_posted_listings           =   houzez_get_user_num_posted_listings ( $user_id ); // get user current number of posted listings ( no expired )
        $user_current_posted_featured_listings  =   houzez_get_user_num_posted_featured_listings( $user_id ); // get user number of posted featured listings ( no expired )


        if( houzez_check_user_existing_package_status_for_update_package( $user_id, $package_id ) ) {
            $new_pack_listings           =  $pack_listings - $user_current_posted_listings;
            $new_pack_featured_listings  =  $pack_featured_listings -  $user_current_posted_featured_listings;
        } else {
            $new_pack_listings           =  $pack_listings;
            $new_pack_featured_listings  =  $pack_featured_listings;
        }

        if( $new_pack_listings < 0 ) {
            $new_pack_listings = 0;
        }

        if( $new_pack_featured_listings < 0 ) {
            $new_pack_featured_listings = 0;
        }

        if ( $pack_unlimited_listings == 1 ) {
            $new_pack_listings = -1 ;
        }



        update_user_meta( $user_id, 'package_listings', $new_pack_listings);
        update_user_meta( $user_id, 'package_featured_listings', $new_pack_featured_listings);


        // Use for user who submit property without having account and membership
        $user_submit_has_no_membership = get_the_author_meta( 'user_submit_has_no_membership', $user_id );
        if( !empty( $user_submit_has_no_membership ) ) {
            houzez_update_package_listings( $user_id );
            houzez_update_property_from_draft( $user_submit_has_no_membership ); // change property status from draft to pending or publish
            delete_user_meta( $user_id, 'user_submit_has_no_membership' );
        }


        $time = time();
        $date = date('Y-m-d H:i:s',$time);
        $date2 = date_i18n( get_option('date_format').' '.get_option('time_format') );
        update_user_meta( $user_id, 'package_activation', $date );
        update_user_meta( $user_id, 'package_activation_local', $date2 );
        update_user_meta( $user_id, 'package_id', $package_id );
        update_user_meta( $user_id, 'houzez_membership_id', $package_id);

    }
}

if( !function_exists('houzez_user_has_membership') ) {
    function houzez_user_has_membership( $user_id ) {
        $has_package = get_the_author_meta( 'package_id', $user_id );
        $has_listing = get_the_author_meta( 'package_listings', $user_id );

        if( houzez_is_admin() ) {
            return true;

        } else if( !empty( $has_package ) && ( $has_listing != 0 || $has_listing != '' ) ) {
            
            return true;
        }
        return false;
    }
}

if( !function_exists('houzez_downgrade_package') ):
    function houzez_downgrade_package( $user_id, $pack_id ) {

        $pack_listings           =  get_post_meta( $pack_id, 'pack_listings', true );
        $pack_featured_listings  =  get_post_meta( $pack_id, 'pack_featured_listings', true );

        update_user_meta( $user_id, 'package_listings', $pack_listings );
        update_user_meta( $user_id, 'package_featured_listings', $pack_featured_listings );

        $args = array(
            'post_type'   => 'property',
            'post_status' => 'any'
        );

        $agency_agents = houzez_get_agency_agents( $user_id );
        $package_permission = houzez_can_agent_user_agency_package( $user_id );

        if( $agency_agents && $package_permission ) {
            $agency_agents[] = $user_id;
            $args['author__in'] = $agency_agents;
        } else {
            $args['author'] = $user_id;
        }

        $query = new WP_Query( $args );
        global $post;
        while( $query->have_posts()){
            $query->the_post();

            $property = array(
                'ID'          => $post->ID,
                'post_type'   => 'property',
                'post_status' => 'expired'
            );

            wp_update_post( $property );
            \EstateSite\Core\Property::set( $post->ID, 'featured', 0 );
            update_post_meta( $post->ID, 'houzez_featured_listing_date', '' );
        }
        wp_reset_postdata();

        $user = get_user_by( 'id', $user_id );
        $user_email = $user->user_email;

        $headers = 'From: No Reply <noreply@'.$_SERVER['HTTP_HOST'].'>' . "\r\n";
        $message  = esc_html__('Account Downgraded,','houzez') . "\r\n\r\n";
        $message .= sprintf( __("Hello, You downgraded your subscription on  %s. Because your listings number was greater than what the actual package offers, we set the status of all your listings to \"expired\". You will need to choose which listings you want live and send them again for approval. Thank you!",'houzez'), get_option('blogname')) . "\r\n\r\n";

        wp_mail($user_email,
            sprintf(esc_html__('[%s] Account Downgraded','houzez'), get_option('blogname')),
            $message,
            $headers);
    }
endif;

/* -----------------------------------------------------------------------------------------------------------
 *  Save user package record in custom Post type
 -------------------------------------------------------------------------------------------------------------*/
if( !function_exists('houzez_save_user_packages_record') ) {

    function houzez_save_user_packages_record( $userID, $pack_id = "" ) {
       
        $args = array(
            'author'        =>  $userID,
            'post_type' => 'user_packages',
            'posts_per_page' => 1
        );
        $current_user_posts = get_posts( $args );

        if( !empty( $current_user_posts ) ) {
            foreach ($current_user_posts as $post) {
                $postID = $post->ID;
            }

            $args = array(
                'ID'           => $postID,
                'post_title' => 'Package ' . $userID,
                'post_type' => 'user_packages',
            );

            // Update the post into the database
            wp_update_post( $args );

        } else {

            $args = array(
                'post_title' => 'Package ' . $userID,
                'post_type' => 'user_packages',
                'post_status' => 'publish'
            );
            // Insert the post into the database
            $post_id = wp_insert_post($args);
            update_post_meta($post_id, 'user_packages_userID', $userID);
            update_post_meta($post_id, 'user_packages_id', $pack_id);
        }
    }
}

/* -----------------------------------------------------------------------------------------------------------
 *  Resend Property for Approval
 -------------------------------------------------------------------------------------------------------------*/
add_action( 'wp_ajax_houzez_resend_for_approval', 'houzez_resend_for_approval' );
if( !function_exists('houzez_resend_for_approval') ) {

    function houzez_resend_for_approval() {

        $prop_id = intval($_POST['propid']);
        $userID = get_current_user_id();
        $post = get_post($prop_id);
        $post_author = get_post_field( 'post_author', $prop_id );

        $packageUserId = $userID;
        $agent_agency_id = houzez_get_agent_agency_id( $userID );
        if( $agent_agency_id ) {
            $packageUserId = $agent_agency_id;
        }

        $agencyAgentsArray = array();
        $agencyAgents = houzez_get_agency_agents( $userID );
        if( $agencyAgents ) {
            $agencyAgentsArray = $agencyAgents;
        }

        if ( $post_author != $userID && ! in_array($post_author, $agencyAgentsArray) ) {
            wp_die('no kidding');
        }

        $available_listings = get_user_meta($packageUserId, 'package_listings', true);

        if ($available_listings > 0 || $available_listings == -1) {
            $time = current_time('mysql');
            $prop = array(
                'ID' => $prop_id,
                'post_type' => 'property',
                'post_date'     => current_time( 'mysql' ),
                'post_date_gmt' => get_gmt_from_date( $time )
            );

            if( houzez_option('re-activate_listings_admin_approved') == 'yes' ) {
                $prop['post_status'] = 'pending';
            } else {
                $prop['post_status'] = 'publish';
            }

            wp_update_post($prop);
            \EstateSite\Core\Property::set( $prop_id, 'featured', 0 );
            update_post_meta($prop_id, 'houzez_featured_listing_date', '');

            if ($available_listings != -1) { // if !unlimited
                update_user_meta($packageUserId, 'package_listings', $available_listings - 1);
            }
            echo json_encode(array('success' => true, 'msg' => esc_html__('Reactivated', 'houzez')));

            $submit_title = get_the_title($prop_id);

            $args = array(
                'submission_title' => $submit_title,
                'submission_url' => get_permalink($prop_id)
            );
            //houzez_email_type(get_option('admin_email'), 'admin_expired_listings', $args);


        } else {
            echo json_encode(array('success' => false, 'msg' => esc_html__('No listings available', 'houzez')));
            wp_die();
        }
        wp_die();

    }
}

/* -----------------------------------------------------------------------------------------------------------
 *  Put on hold - package
 -------------------------------------------------------------------------------------------------------------*/
add_action( 'wp_ajax_houzez_property_on_hold_package', 'houzez_property_on_hold_package' );
if( !function_exists('houzez_property_on_hold_package') ) {

    function houzez_property_on_hold_package()
    {

        global $current_user;
        $prop_id = intval($_POST['propid']);

        wp_get_current_user();
        $userID = $current_user->ID;
        $post = get_post($prop_id);

        if ($post->post_author != $userID) {
            wp_die('no kidding');
        }

        $available_listings = get_user_meta($userID, 'package_listings', true);

        //if ($available_listings > 0 || $available_listings == -1) {
            
            $post_status = get_post_status( $prop_id );

            if($post_status == 'publish') { 
                $post = array(
                    'ID'            => $prop_id,
                    'post_status'   => 'on_hold'
                );
                /*if ($available_listings != -1) { // if !unlimited
                    update_user_meta($userID, 'package_listings', $available_listings + 1);
                }*/
            } elseif ($post_status == 'on_hold') {
                $post = array(
                    'ID'            => $prop_id,
                    'post_status'   => 'publish'
                );
                /*if ($available_listings != -1) { // if !unlimited
                    update_user_meta($userID, 'package_listings', $available_listings - 1);
                }*/
            }
            $prop_id =  wp_update_post($post);

            echo json_encode(array('success' => true, 'msg' => esc_html__('Listings set on hold', 'houzez')));

        /*} else {
            echo json_encode(array('success' => false, 'msg' => esc_html__('No listings available', 'houzez')));
            wp_die();
        }*/
        wp_die();

    }
}

if( !function_exists('houzez_get_user_current_package') ) {
    function houzez_get_user_current_package( $user_id ) {

        $remaining_listings = houzez_get_remaining_listings( $user_id );
        $pack_featured_remaining_listings = houzez_get_featured_remaining_listings( $user_id );
        $package_id = houzez_get_user_package_id( $user_id );
        $packages_page_link = houzez_get_template_link('template/template-packages.php');

        if( $remaining_listings == -1 ) {
            $remaining_listings = esc_html__('Unlimited', 'houzez');
        }

        if( !empty( $package_id ) ) {

            $seconds = 0;
            $pack_title = get_the_title( $package_id );
            $pack_listings = get_post_meta( $package_id, 'fave_package_listings', true ) /* TODO unmapped fave_ key: fave_package_listings */;
            $pack_unmilited_listings = get_post_meta( $package_id, 'fave_unlimited_listings', true ) /* TODO unmapped fave_ key: fave_unlimited_listings */;
            $pack_featured_listings = get_post_meta( $package_id, 'fave_package_featured_listings', true ) /* TODO unmapped fave_ key: fave_package_featured_listings */;
            $pack_billing_period = get_post_meta( $package_id, 'fave_billing_time_unit', true ) /* TODO unmapped fave_ key: fave_billing_time_unit */;
            $pack_billing_frequency = get_post_meta( $package_id, 'fave_billing_unit', true ) /* TODO unmapped fave_ key: fave_billing_unit */;
            $pack_date =  get_user_meta( $user_id, 'package_activation',true );
            $never_expire = get_post_meta( $package_id, 'fave_never_expire', true ) /* TODO unmapped fave_ key: fave_never_expire */;

            if( $pack_billing_period == 'Day')
                $pack_billing_period = 'days';
            elseif( $pack_billing_period == 'Week')
                $pack_billing_period = 'weeks';
            elseif( $pack_billing_period == 'Month')
                $pack_billing_period = 'months';
            elseif( $pack_billing_period == 'Year')
                $pack_billing_period = 'years';

            if( $never_expire == 1 ) {
                $expired_date = esc_html__( 'Never', 'houzez' );
            } else {
                $expired_date = strtotime($pack_date. ' + '.$pack_billing_frequency.' '.$pack_billing_period);
                $expired_date = date_i18n( get_option('date_format').' '.get_option('time_format'),  $expired_date );
            }
           
            echo '<div class="membership-inner d-flex align-items-center justify-content-between mb-4">';
            echo '<h5>'.esc_html__( 'Your Current Package', 'houzez' ).'</h5>';
            echo '<span class="dashboard-label bg-info">'.esc_attr( $pack_title ).'</span>';
            echo '</div>';
            
            echo '<ul class="list-group list-group-flush">';
            
            if( $pack_unmilited_listings == 1 ) {
                echo '<li class="list-group-item d-flex justify-content-between align-items-center">'.esc_html__('Listings Included: ','houzez').'<span class="badge bg-info rounded-pill">'.esc_html__('Unlimited','houzez').'</span></li>';
                echo '<li class="list-group-item d-flex justify-content-between align-items-center">'.esc_html__('Listings Remaining: ','houzez').'<span class="badge bg-info rounded-pill">'.esc_html__('Unlimited','houzez').'</span></li>';
            } else {
                echo '<li class="list-group-item d-flex justify-content-between align-items-center">'.esc_html__('Listings Included: ','houzez').'<span class="badge bg-info rounded-pill">'.esc_attr( $pack_listings ).'</span></li>';
                echo '<li class="list-group-item d-flex justify-content-between align-items-center">'.esc_html__('Listings Remaining: ','houzez').'<span class="badge bg-info rounded-pill">'.esc_attr( $remaining_listings ).'</span></li>';
            }
            
            echo '<li class="list-group-item d-flex justify-content-between align-items-center">'.esc_html__('Featured Included: ','houzez').'<span class="badge bg-info rounded-pill">'.esc_attr( $pack_featured_listings ).'</span></li>';
            echo '<li class="list-group-item d-flex justify-content-between align-items-center">'.esc_html__('Featured Remaining: ','houzez').'<span class="badge bg-info rounded-pill">'.esc_attr( $pack_featured_remaining_listings ).'</span></li>';
            echo '<li class="list-group-item d-flex justify-content-between align-items-center">'.esc_html__('Ends On','houzez').' <span>'.esc_attr( $expired_date ).'</span></li>';
            echo '</ul>';

        }
    }
}

/* -----------------------------------------------------------------------------------------------------------
*  Wire Transfer Per Listing
-------------------------------------------------------------------------------------------------------------*/
add_action( 'wp_ajax_nopriv_houzez_direct_pay_per_listing', 'houzez_direct_pay_per_listing' );
add_action( 'wp_ajax_houzez_direct_pay_per_listing', 'houzez_direct_pay_per_listing' );

if( !function_exists('houzez_direct_pay_per_listing') ) {
    function houzez_direct_pay_per_listing() {
        $current_user = wp_get_current_user();
        if ( !is_user_logged_in() ) {
            exit('Are you kidding?');
        }

        $userID        = $current_user->ID;
        $user_email    = $current_user->user_email ;

        $price_listing_submission = houzez_option('price_listing_submission');
        $price_featured_listing_submission = houzez_option('price_featured_listing_submission');

        $listing_id                = intval($_POST['prop_id']);
        $is_featured               = intval($_POST['is_featured']);
        $is_upgrade                = intval($_POST['is_upgrade']);
        $payment_status            = \EstateSite\Core\Property::get( $listing_id, 'payment_status' );
        $price_submission          = floatval( $price_listing_submission );
        $price_featured_submission = floatval( $price_featured_listing_submission );
        $currency                  = esc_html( houzez_option('currency_symbol') );
        $where_currency            = esc_html( houzez_option('currency_position') );
        $wire_payment_instruction  = houzez_option('direct_payment_instruction');
        $paymentMethod = 'Direct Bank Transfer';
        
        // Get tax percentages
        $tax_percentage_per_listing = floatval(houzez_option('tax_percentage_per_listing'));
        $tax_percentage_featured = floatval(houzez_option('tax_percentage_featured'));
        
        // Calculate taxes
        $tax_per_listing = 0;
        $tax_featured = 0;
        
        if( !empty($tax_percentage_per_listing) && !empty($price_submission) ) {
            $tax_per_listing = ($tax_percentage_per_listing / 100) * $price_submission;
            $tax_per_listing = round($tax_per_listing, 2);
        }
        
        if( !empty($tax_percentage_featured) && !empty($price_featured_submission) ) {
            $tax_featured = ($tax_percentage_featured / 100) * $price_featured_submission;
            $tax_featured = round($tax_featured, 2);
        }

        $total_price = 0;
        $time = time();
        $date = date('Y-m-d H:i:s', $time);

        if($is_featured == 1 ) {
            $invoiceID = houzez_generate_invoice( 'Publish Listing with Featured', 'one_time', $listing_id, $date, $userID, 1, 0, '', $paymentMethod );
            $total_price = $price_submission + $tax_per_listing + $price_featured_submission + $tax_featured;
        } else if( $is_upgrade == 1 ) {
            $invoiceID = houzez_generate_invoice( 'Upgrade to Featured', 'one_time', $listing_id, $date, $userID, 0, 1, '', $paymentMethod );
            $total_price = $price_featured_submission + $tax_featured;
        } else {
            $invoiceID = houzez_generate_invoice( 'Listing', 'one_time', $listing_id, $date, $userID, 0, 0, '', $paymentMethod );
            $total_price = $price_submission + $tax_per_listing;

        }

        if ( $total_price != 0 ) {

            if ($where_currency == 'before') {
                $total_price = $currency . ' ' . $total_price;
            } else {
                $total_price = $total_price . ' ' . $currency;
            }
        }

        if (function_exists('icl_translate') ){
            $mes_wire         =  strip_tags( $wire_payment_instruction );
            $payment_details  =  icl_translate('houzez','houzez_wire_payment_instruction_text', $mes_wire );
        }else{
            $payment_details =  strip_tags( $wire_payment_instruction );
        }

        $admin_email   =  get_bloginfo('admin_email');

        // Set Payment status Not Paid
        update_post_meta( $invoiceID, 'invoice_payment_status', 0 );

        $args = array(
            'invoice_no'      =>  $invoiceID,
            'total_price'     =>  $total_price,
            'payment_details' =>  $payment_details,
        );

        /*
         * Send email
         * */
        houzez_email_type( $user_email, 'new_wire_transfer', $args);
        houzez_email_type( $admin_email, 'admin_new_wire_transfer', $args);

        $thankyou_page_link = houzez_get_template_link('template/template-thankyou.php');

        if (!empty($thankyou_page_link)) {
            $separator = (parse_url($thankyou_page_link, PHP_URL_QUERY) == NULL) ? '?' : '&';
            $parameter = 'directy_pay='.$invoiceID;
            print $thankyou_page_link . $separator . $parameter;
        }

        wp_die();
    }
}

/* -----------------------------------------------------------------------------------------------------------
 *  Wire Transfer Activate Purchase Listing
 -------------------------------------------------------------------------------------------------------------*/
add_action( 'wp_ajax_houzez_activate_purchase_listing', 'houzez_activate_purchase_listing' );

if( !function_exists('houzez_activate_purchase_listing') ):
    function houzez_activate_purchase_listing(){
        if ( !is_user_logged_in() ) {
            exit('are you kidding?');
        }
        if ( ! is_admin() ) {
            exit('are you kidding?');
        }

        $itemID         =   intval($_POST['item_id']);
        $invoiceID      =   intval($_POST['invoice_id']);
        $purchase_type  =   intval($_POST['purchase_type']);
        $ownerID         = get_post_meta($invoiceID, 'HOUZEZ_invoice_buyer', true);

        $user           =   get_user_by('id', $ownerID );
        $user_email     =   $user->user_email;

        if ( $purchase_type == 1 ) {
            \EstateSite\Core\Property::set( $itemID, 'payment_status', 'paid' );

            $post_args = array(
                'ID'            => $itemID,
                'post_status'   => 'publish'
            );

            $post_args['post_date'] = current_time( 'mysql' );
            $postID =  wp_update_post( $post_args );

        } elseif ( $purchase_type == 2 ) {
            \EstateSite\Core\Property::set( $itemID, 'featured', 1 );
            update_post_meta( $itemID, 'houzez_featured_listing_date', current_time( 'mysql' ) );

        } elseif ( $purchase_type == 3 ) {
            \EstateSite\Core\Property::set( $itemID, 'payment_status', 'paid' );
            \EstateSite\Core\Property::set( $itemID, 'featured', 1 );
            update_post_meta( $itemID, 'houzez_featured_listing_date', current_time( 'mysql' ) );

            $post_args = array(
                'ID'            => $itemID,
                'post_status'   => 'publish'
            );
            $postID =  wp_update_post( $post_args );

        }

        update_post_meta( $invoiceID, 'invoice_payment_status', 1 );
        $args = array();

        houzez_email_type( $user_email,'purchase_activated', $args );
        wp_die();
    }

endif;

/* Inline --- Deprecated since v1.5.0
----------------------------------------------------------------
*/
add_action( 'wp_ajax_nopriv_houzez_wire_transfer_per_listing', 'houzez_wire_transfer_per_listing' );
add_action( 'wp_ajax_houzez_wire_transfer_per_listing', 'houzez_wire_transfer_per_listing' );

if( !function_exists('houzez_wire_transfer_per_listing') ) {
    function houzez_wire_transfer_per_listing() {
        $current_user = wp_get_current_user();
        if ( !is_user_logged_in() ) {
            exit('Are you kidding?');
        }

        $userID                     = $current_user->ID;
        $user_email                 = $current_user->user_email ;

        $price_listing_submission = houzez_option('price_listing_submission');
        $price_featured_listing_submission = houzez_option('price_featured_listing_submission');

        $listing_id                 = intval($_POST['prop_id']);
        $is_featured                = intval($_POST['is_featured']);
        $payment_status             = \EstateSite\Core\Property::get( $listing_id, 'payment_status' );
        $price_submission           = floatval( $price_listing_submission );
        $price_featured_submission  = floatval( $price_featured_listing_submission );
        $currency                   = esc_html( houzez_option('currency_symbol') );
        $where_currency             = esc_html( houzez_option('currency_position') );
        $wire_payment_instruction   = houzez_option('direct_payment_instruction');
        $paymentMethod = 'Direct Bank Transfer';

        $total_price = 0;
        $time = time();
        $date = date('Y-m-d H:i:s', $time);

        if($is_featured == 1 ) {
            if( $payment_status=='paid' ){
                $invoiceID = houzez_generate_invoice( 'Upgrade to Featured', 'one_time', $listing_id, $date, $userID, 0, 1, '', $paymentMethod );
                $total_price = $price_featured_submission;
                //houzez_email_to_admin('email_upgrade');

            }else{
                $invoiceID = houzez_generate_invoice( 'Publish Listing with Featured', 'one_time', $listing_id, $date, $userID, 1, 0, '', $paymentMethod );
                $total_price = $price_submission + $price_featured_submission;
                //houzez_email_to_admin('simple');
            }
        } else {
            $invoiceID = houzez_generate_invoice( 'Listing', 'one_time', $listing_id, $date, $userID, 0, 0, '', $paymentMethod );
            $total_price = $price_submission;
            //houzez_email_to_admin('simple');

        }

        if ( $total_price != 0 ) {

            if ($where_currency == 'before') {
                $total_price = $currency . ' ' . $total_price;
            } else {
                $total_price = $total_price . ' ' . $currency;
            }
        }

        if (function_exists('icl_translate') ){
            $mes_wire         =  strip_tags( $wire_payment_instruction );
            $payment_details  =  icl_translate('houzez','houzez_wire_payment_instruction_text', $mes_wire );
        }else{
            $payment_details =  strip_tags( $wire_payment_instruction );
        }

        $admin_email   =  get_bloginfo('admin_email');

        // Set Payment status Not Paid
        update_post_meta( $invoiceID, 'invoice_payment_status', 0 );

        $args = array(
            'invoice_no'      =>  $invoiceID,
            'total_price'     =>  $total_price,
            'payment_details' =>  $payment_details,
        );

        /*
         * Send email
         * */
        houzez_email_type( $user_email, 'new_wire_transfer', $args);
        houzez_email_type( $admin_email, 'admin_new_wire_transfer', $args);

        wp_die();
    }
}



/* -----------------------------------------------------------------------------------------------------------
*  Wire Transfer direct pay package
-------------------------------------------------------------------------------------------------------------*/
add_action( 'wp_ajax_nopriv_houzez_direct_pay_package', 'houzez_direct_pay_package' );
add_action( 'wp_ajax_houzez_direct_pay_package', 'houzez_direct_pay_package' );

if( !function_exists('houzez_direct_pay_package') ) {

    function houzez_direct_pay_package() {
        global $current_user;

        $current_user = wp_get_current_user();

        if (!is_user_logged_in()) {
            exit('Are you kidding?');
        }

        if ( ! houzez_check_role() ) {
            exit( esc_html__( 'You are not allowed to purchase a membership package.', 'houzez' ) );
        }

        $userID = $current_user->ID;
        $user_email = $current_user->user_email;
        $selected_pack = intval($_POST['selected_package']);
        $total_price = get_post_meta($selected_pack, 'fave_package_price', true) /* TODO unmapped fave_ key: fave_package_price */;
        $currency = esc_html(houzez_option('currency_symbol'));
        $where_currency = esc_html(houzez_option('currency_position'));
        $wire_payment_instruction = houzez_option('direct_payment_instruction');
        $is_featured = 0;
        $is_upgrade = 0;
        $paypal_tax_id = '';
        $paymentMethod = 'Direct Bank Transfer';
        $time = time();
        $date = date('Y-m-d H:i:s', $time);


        $pack_tax = floatval(get_post_meta( $selected_pack, 'fave_package_tax', true ) /* TODO unmapped fave_ key: fave_package_tax */);
        if( !empty($pack_tax) && !empty($total_price) ) {
            $total_taxes = floatval($pack_tax)/100 * floatval($total_price);
            $total_taxes = round($total_taxes, 2);
        }
        $total_price = $total_price + $total_taxes;

        if ($total_price != 0) {
            if ($where_currency == 'before') {
                $total_price = $currency . ' ' . $total_price;
            } else {
                $total_price = $total_price . ' ' . $currency;
            }
        }

        // insert invoice
        $invoiceID = houzez_generate_invoice('package', 'one_time', $selected_pack, $date, $userID, $is_featured, $is_upgrade, $paypal_tax_id, $paymentMethod, 1);


        if (function_exists('icl_translate')) {
            $mes_wire = strip_tags($wire_payment_instruction);
            $payment_details = icl_translate('houzez', 'houzez_wire_payment_instruction_text', $mes_wire);
        } else {
            $payment_details = strip_tags($wire_payment_instruction);
        }

        update_post_meta($invoiceID, 'invoice_payment_status', 0);
        $admin_email      =  get_bloginfo('admin_email');

        $args = array(
            'invoice_no'      =>  $invoiceID,
            'total_price'     =>  $total_price,
            'payment_details' =>  $payment_details,
        );

        /*
         * Send email
         * */
        houzez_email_type( $user_email, 'new_wire_transfer', $args);
        houzez_email_type( $admin_email, 'admin_new_wire_transfer', $args);

        $thankyou_page_link = houzez_get_template_link('template/template-thankyou.php');

        if (!empty($thankyou_page_link)) {
            $separator = (parse_url($thankyou_page_link, PHP_URL_QUERY) == NULL) ? '?' : '&';
            $parameter = 'directy_pay='.$invoiceID;
            print $thankyou_page_link . $separator . $parameter;
        }
        wp_die();
    }
}


/* -----------------------------------------------------------------------------------------------------------
*  Recurring paypal payment [Deprecated]
-------------------------------------------------------------------------------------------------------------*/
add_action( 'wp_ajax_nopriv_houzez_recuring_paypal_package_payment_deprecated', 'houzez_recuring_paypal_package_payment_deprecated' );
add_action( 'wp_ajax_houzez_recuring_paypal_package_payment_deprecated', 'houzez_recuring_paypal_package_payment_deprecated' );

if( !function_exists('houzez_recuring_paypal_package_payment_deprecated') ) {
    function houzez_recuring_paypal_package_payment_deprecated(){
        global $current_user;

        $current_user = wp_get_current_user();
        $userID = $current_user->ID;

        if ( ! is_user_logged_in() || ! houzez_check_role() ) {
            wp_die( esc_html__( 'You are not allowed to purchase a membership package.', 'houzez' ) );
        }

        if ( !is_user_logged_in() ) {
            wp_die('are you kidding?');
        }

        if( $userID === 0 ) {
            wp_die('are you kidding?');
        }

        $allowed_html=array();
        $houzez_package_name  = wp_kses($_POST['houzez_package_name'],$allowed_html);
        $houzez_package_id    = intval($_POST['houzez_package_id']);
        $is_package_exist     = get_posts('post_type=houzez_packages&p='.$houzez_package_id);
        $submission_curency   = houzez_option('currency_paid_submission');
        $dash_profile_link    = houzez_get_dashboard_profile_link();
        $thankyou_page_link = houzez_get_template_link('template/template-thankyou.php');

        $paypal_api_username = houzez_option('paypal_api_username');
        $paypal_api_password = houzez_option('paypal_api_password');
        $paypal_api_signature = houzez_option('paypal_api_signature');

        if( !empty ( $is_package_exist ) ) {

            require( get_template_directory() . '/framework/paypal-recurring/class.paypal.recurring.php' );
            global $current_user;

            $packPrice          =  get_post_meta( $houzez_package_id, 'fave_package_price', true ) /* TODO unmapped fave_ key: fave_package_price */;
            $billingPeriod      =  get_post_meta( $houzez_package_id, 'fave_billing_time_unit', true ) /* TODO unmapped fave_ key: fave_billing_time_unit */;
            $billingFreq        =  intval( get_post_meta( $houzez_package_id, 'fave_billing_unit', true ) /* TODO unmapped fave_ key: fave_billing_unit */ );
            $submissionCurency  =  esc_html( $submission_curency );
            $environment        = houzez_option('paypal_api');

            $obj = new houzez_paypal_recurring;

            $obj->environment               =   esc_html( $environment );
            $obj->paymentType               =   urlencode('Sale');
            $obj->productDesc               =   urlencode( $houzez_package_name.__(' package on ','houzez').get_bloginfo('name') );
            $time                           =   time();
            $date                           =   date('Y-m-d H:i:s',$time);
            //$date                           =   date_i18n( get_option('date_format').' '.get_option('time_format') );
            $obj->startDate                 =   urlencode($date);
            $obj->billingPeriod             =   urlencode($billingPeriod);
            $obj->billingFreq               =   urlencode($billingFreq);
            $obj->paymentAmount             =   urlencode($packPrice);
            $obj->currencyID                =   urlencode($submissionCurency);
            $obj->API_UserName              =   urlencode( $paypal_api_username );
            $obj->API_Password              =   urlencode( $paypal_api_password );
            $obj->API_Signature             =   urlencode( $paypal_api_signature );
            $obj->API_Endpoint              =   "https://api-3t.paypal.com/nvp";
            $obj->returnURL                 =   urlencode($thankyou_page_link);
            $obj->cancelURL                 =   urlencode($dash_profile_link);
            $executor['payment_execute_url'] =   '';
            $executor['access_token']       =   '';
            $executor['package_id']            =   $houzez_package_id;
            $executor['recursive']          =   1;
            $executor['date']               =   $date;
            $save_data[$current_user->ID ]  =   $executor;
            update_option('houzez_paypal_package_transfer', $save_data);
            update_user_meta($userID, 'houzez_paypal_package', $save_data);

            $obj->setExpressCheckout();
        }
    }
}

/* -----------------------------------------------------------------------------------------------------------
*  Active direct pay package
-------------------------------------------------------------------------------------------------------------*/
add_action( 'wp_ajax_nopriv_houzez_activate_pack_purchase', 'houzez_activate_pack_purchase' );
add_action( 'wp_ajax_houzez_activate_pack_purchase', 'houzez_activate_pack_purchase' );

if( !function_exists('houzez_activate_pack_purchase') ) {
    function houzez_activate_pack_purchase()
    {
        if (!is_user_logged_in()) {
            exit('are you kidding?');
        }
        if (!is_admin()) {
            exit('are you kidding?');
        }


        $packID = intval($_POST['item_id']);
        $invoiceID = intval($_POST['invoice_id']);
        $userID = get_post_meta($invoiceID, 'HOUZEZ_invoice_buyer', true);

        $user           =   get_user_by('id', $userID );
        $user_email     =   $user->user_email;

        houzez_save_user_packages_record($userID, $packID);
        if( houzez_check_user_existing_package_status( $userID, $packID) ){
            houzez_downgrade_package( $userID, $packID );
            houzez_update_membership_package($userID, $packID);
        }else{
            houzez_update_membership_package($userID, $packID);
        }

        update_post_meta($invoiceID, 'invoice_payment_status', 1);

        $args = array();

        houzez_email_type( $user_email,'purchase_activated_pack', $args );
        wp_die();
    }
}

if( !function_exists('houzez_get_remaining_listings') ) {
    function houzez_get_remaining_listings($user_id) {
        return get_the_author_meta( 'package_listings' , $user_id );
    }
}

if( !function_exists('houzez_get_featured_remaining_listings') ) {
    function houzez_get_featured_remaining_listings($user_id) {
        return get_the_author_meta( 'package_featured_listings' , $user_id );
    }
}

if( !function_exists('houzez_get_user_package_id') ) {
    function houzez_get_user_package_id($user_id) {
        return get_the_author_meta( 'package_id', $user_id );
    }
}

if( !function_exists('houzez_update_package_listings') ) {
    function houzez_update_package_listings($user_id) {
        $package_listings = get_the_author_meta( 'package_listings' , $user_id );
        $user_submit_has_no_membership = get_the_author_meta( 'user_submit_has_no_membership', $user_id );
        $user_submitted_without_membership = get_the_author_meta( 'user_submitted_without_membership', $user_id );
        $package_listings = intval($package_listings);

        if ( $package_listings - 1 >= 0 ) {
            if($user_submitted_without_membership == 'yes') {
                update_user_meta($user_id, 'package_listings', $package_listings - 1);
            } else if( empty($user_submit_has_no_membership) ) {
                update_user_meta($user_id, 'package_listings', $package_listings - 1);
            } else {
                update_user_meta($user_id, 'package_listings', $package_listings );
            }
        } else if( $package_listings == 0 ) {
            update_user_meta( $user_id, 'package_listings', 0 );
        }
    }
}

if( !function_exists('houzez_plusone_package_listings') ) {
    function houzez_plusone_package_listings($user_id) {

        $user_package_id = houzez_get_user_package_id($user_id);

        $active_listings = houzez_get_user_num_posted_listings($user_id);
        $active_featured_listings = houzez_get_user_num_posted_featured_listings($user_id);

        $package_listings = get_post_meta( $user_package_id, 'fave_package_listings', true ) /* TODO unmapped fave_ key: fave_package_listings */;
        $user_package_listings = get_the_author_meta( 'package_listings' , $user_id );

        $pack_unlimited_listings  =   get_post_meta( $user_package_id, 'fave_unlimited_listings', true ) /* TODO unmapped fave_ key: fave_unlimited_listings */;

        $package_featured_listings = get_post_meta( $user_package_id, 'fave_package_featured_listings', true ) /* TODO unmapped fave_ key: fave_package_featured_listings */;
        $user_package_featured_listings = get_the_author_meta( 'package_featured_listings' , $user_id );

        $user_package_listings = intval($user_package_listings);
        $package_listings = intval($package_listings);

        $user_package_featured_listings = intval($user_package_featured_listings);
        $package_featured_listings = intval($package_featured_listings);

        // Update simple listings record
        if( ( $active_listings < $package_listings ) && $package_listings >= 0 ) {
            $remaining_listings = $package_listings - $active_listings;
            $new_pack_listings = $remaining_listings;
        } else if( $package_listings == 0 ) {
            $new_pack_listings = 0;
        }

        if ( $pack_unlimited_listings == 1 ) {
            $new_pack_listings = -1 ;
        }

        update_user_meta( $user_id, 'package_listings', $new_pack_listings );

        // Update featured listings style
        if( ( $active_featured_listings < $package_featured_listings ) && $package_featured_listings >= 0 ) {
            $remaining_featured_listings = $package_featured_listings - $active_featured_listings;
            update_user_meta($user_id, 'package_featured_listings', $remaining_featured_listings);
        } else if( $package_featured_listings == 0 ) {
            update_user_meta( $user_id, 'package_featured_listings', 0 );
        }
    }
}

if( !function_exists('houzez_refund_listing_after_delete') ) {
    /**
     * Recompute a user's remaining package quota after a property is deleted.
     *
     * Must be called AFTER wp_delete_post() so the active listing count
     * reflects the deletion. Honors the "refund_listing_on_delete" theme
     * option and the agent -> agency shared-package relationship.
     *
     * @param int $property_author_id The author ID of the deleted property.
     */
    function houzez_refund_listing_after_delete( $property_author_id ) {

        $property_author_id = intval( $property_author_id );
        if ( $property_author_id <= 0 ) {
            return;
        }

        if ( houzez_option( 'enable_paid_submission' ) !== 'membership' ) {
            return;
        }

        if ( ! houzez_option( 'refund_listing_on_delete', 1 ) ) {
            return;
        }

        // Resolve to the agency package owner when the author is an agent
        // using the agency's shared package.
        $packageUserId   = $property_author_id;
        $agent_agency_id = houzez_get_agent_agency_id( $property_author_id );
        if ( $agent_agency_id ) {
            $packageUserId = $agent_agency_id;
        }

        if ( ! houzez_get_user_package_id( $packageUserId ) ) {
            return;
        }

        houzez_plusone_package_listings( $packageUserId );
    }
}

if( !function_exists('houzez_user_had_free_package') ) {
    function houzez_user_had_free_package($user_id) {
        $free_package = get_the_author_meta( 'user_had_free_package' , $user_id );

        if ( $free_package == 'yes' ) {
            return false;
        }
        return true;
    }
}

if( !function_exists('houzez_update_user_recuring_paypal_profile') ) {
    function houzez_update_user_recuring_paypal_profile( $profileID, $userID ) {
        $profileID = str_replace('-', 'xxx', $profileID);
        $profileID = str_replace('%2d', 'xxx', $profileID);

        update_user_meta( $userID, 'fave_paypal_profile', $profileID );

    }
}

if( !function_exists('houzez_update_package_featured_listings') ) {
    function houzez_update_package_featured_listings($user_id) {
        $package_featured_listings = get_the_author_meta( 'package_featured_listings' , $user_id );

        if ( $package_featured_listings-1 >= 0 ) {
            update_user_meta( $user_id, 'package_featured_listings', $package_featured_listings - 1 );
        } else if( $package_featured_listings == 0 ) {
            update_user_meta( $user_id, 'package_featured_listings', 0 ) ;
        }
    }
}


if( !function_exists('houzez_check_user_existing_package_status') ) {
    function  houzez_check_user_existing_package_status( $userID, $packID ) {

        $pack_listings            =  get_post_meta( $packID, 'fave_package_listings', true ) /* TODO unmapped fave_ key: fave_package_listings */;
        $pack_featured_listings   =  get_post_meta( $packID, 'fave_package_featured_listings', true ) /* TODO unmapped fave_ key: fave_package_featured_listings */;
        $pack_unlimited_listings  =  get_post_meta( $packID, 'fave_unlimited_listings', true ) /* TODO unmapped fave_ key: fave_unlimited_listings */;

        $user_num_posted_listings = houzez_get_user_num_posted_listings( $userID );
        $user_num_posted_featured_listings = houzez_get_user_num_posted_featured_listings( $userID );

        $current_listings =  get_user_meta( $userID, 'package_listings', true ) ;

        if( $pack_unlimited_listings == 1 ) {
            return false;
        }

        // if is unlimited and go to non unlimited pack
        if ( $current_listings == -1 && $pack_unlimited_listings != 1 ) {
            return true;
        }

        if ( ( $user_num_posted_listings > $pack_listings ) || ( $user_num_posted_featured_listings > $pack_featured_listings ) ) {
            return true;
        } else {
            return false;
        }


    }
}

if( !function_exists('houzez_check_user_existing_package_status_for_update_package') ) {
    function  houzez_check_user_existing_package_status_for_update_package( $userID, $packID ) {

        $pack_listings            =  get_post_meta( $packID, 'fave_package_listings', true ) /* TODO unmapped fave_ key: fave_package_listings */;
        $pack_featured_listings   =  get_post_meta( $packID, 'fave_package_featured_listings', true ) /* TODO unmapped fave_ key: fave_package_featured_listings */;
        $pack_unlimited_listings  =  get_post_meta( $packID, 'fave_unlimited_listings', true ) /* TODO unmapped fave_ key: fave_unlimited_listings */;

        $user_num_posted_listings = houzez_get_user_num_posted_listings( $userID );
        $user_num_posted_featured_listings = houzez_get_user_num_posted_featured_listings( $userID );

        $current_listings =  get_user_meta( $userID, 'package_listings', true ) ;

        if( $pack_unlimited_listings == 1 ) {
            return false;
        }

        if( $user_num_posted_listings > 0 && $pack_unlimited_listings != 1 ) {
            return true;
        }

        // if is unlimited and go to non unlimited pack
        if ( $current_listings == -1 && $pack_unlimited_listings != 1 ) {
            return true;
        }

        if ( ( $user_num_posted_listings > $pack_listings ) || ( $user_num_posted_featured_listings > $pack_featured_listings ) ) {
            return true;
        } else {
            return false;
        }


    }
}

if( !function_exists('houzez_get_agency_agents_total_listings') ):
    function houzez_get_agency_agents_total_listings( $userID ) {
        $args = array(
            'post_type'   => 'property',
            'post_status' => array('publish', 'pending', 'on_hold'),

        );

        $agency_agents = houzez_get_agency_agents( $userID );

        if( $agency_agents ) {
            $args['author__in'] = $agency_agents;
        } else {
            $args['author'] = $userID;
        }

        $posts = new WP_Query( $args );
        return $posts->found_posts;
        wp_reset_postdata();
    }
endif;

if( !function_exists('houzez_get_user_num_posted_listings') ):
    function houzez_get_user_num_posted_listings( $userID ) {
        $args = array(
            'post_type'   => 'property',
            'post_status' => array('publish', 'pending', 'on_hold'),

        );

        $agency_agents = houzez_get_agency_agents( $userID );
        $package_permission = houzez_can_agent_user_agency_package( $userID );

        if( $agency_agents && $package_permission ) {
            $agency_agents[] = $userID;
            $args['author__in'] = $agency_agents;
        } else {
            $args['author'] = $userID;
        }

        $posts = new WP_Query( $args );
        return $posts->found_posts;
        wp_reset_postdata();
    }
endif;

/* -----------------------------------------------------------------------------------------------------------
 *  Get user current featured listings
 -------------------------------------------------------------------------------------------------------------*/
if( !function_exists('houzez_get_user_num_posted_featured_listings') ):
    function houzez_get_user_num_posted_featured_listings( $userID ) {

        $args = array(
            'post_type'     =>  'property',
            'post_status'   =>  array('publish', 'pending', 'on_hold'),
            'meta_query'    =>  array(
                array(
                    'key' => \EstateSite\Core\Property::key( 'featured' ),
                    'value' => 1,
                    'meta_compare '=>'='
                )
            )
        );

        $agency_agents = houzez_get_agency_agents( $userID );
        $package_permission = houzez_can_agent_user_agency_package( $userID );

        if( $agency_agents && $package_permission ) {
            $agency_agents[] = $userID;
            $args['author__in'] = $agency_agents;
        } else {
            $args['author'] = $userID;
        }

        $posts = new WP_Query( $args );
        return $posts->found_posts;
        wp_reset_postdata();

    }
endif;

if( !function_exists('houzez_retrive_user_by_profile') ) {
    function houzez_retrive_user_by_profile($recurring_payment_id)
    {
        if ($recurring_payment_id != '') {
            $arg = array(
                'meta_key' => 'houzez_paypal_recurring_profile_id',
                'meta_value' => $recurring_payment_id,
                'meta_compare' => '='
            );

            $userid = 0;
            $houzezusers = get_users($arg);
            foreach ($houzezusers as $user) {
                $userid = $user->ID;
            }
            return $userid;
        } else {
            return 0;
        }
    }
}

if( !function_exists('houzez_retrive_invoice_by_taxid') ) {
    function houzez_retrive_invoice_by_taxid($tax_id)
    {
        $args = array(
            'post_type' => 'houzez_invoice',
            'meta_query' => array(
                array(
                    'key' => 'HOUZEZ_paypal_txn_id',
                    'value' => $tax_id,
                    'compare' => '='
                )
            )
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            return true;
        } else {
            return false;
        }
    }
}

/* -----------------------------------------------------------------------------------------------------------
 *  Check membership expire cron
 -------------------------------------------------------------------------------------------------------------*/
if( !function_exists('houzez_check_membership_expire_cron') ):
    function houzez_check_membership_expire_cron() {

    $args = array(
        'meta_query' => array(
            array(
                'key'     => 'package_id',
                'value'   => '',
                'compare' => '!='
            )
        )
     );
    $user_query = new WP_User_Query( $args );

    if ( ! empty( $user_query->get_results() ) ) {
        foreach ( $user_query->get_results() as $user  ) {
            $user_id = $user->ID;

            $pack_id = get_user_meta ( $user_id, 'package_id', true );
            $is_recurring_membership = get_user_meta ( $user_id, 'houzez_is_recurring_membership', true );

            // Check if user has package
            if( $pack_id != '' && $is_recurring_membership != 1 ) {

                $never_expire = get_post_meta( $pack_id, 'fave_never_expire', true ) /* TODO unmapped fave_ key: fave_never_expire */;
                if( $never_expire == 1 ) {
                    continue;
                }

                $date           =  strtotime ( get_user_meta( $user_id, 'package_activation',true) );
                $billingPeriod  =  get_post_meta( $pack_id, 'fave_billing_time_unit', true ) /* TODO unmapped fave_ key: fave_billing_time_unit */;
                $billingFreq    =  intval( get_post_meta( $pack_id, 'fave_billing_unit', true ) /* TODO unmapped fave_ key: fave_billing_unit */ );
                $seconds = 0;

                switch ( $billingPeriod ){
                    case 'Day':
                        $seconds = 60*60*25;
                        break;
                    case 'Week':
                        $seconds = 60*60*24*7;
                        break;
                    case 'Month':
                        $seconds = 60*60*24*30;
                        break;
                    case 'Year':
                        $seconds = 60*60*24*365;
                        break;
                }
                $time_frame = $seconds*$billingFreq;
                $now = time();

                if( $now > $date + $time_frame ) {
                    houzez_cancel_user_membership( $user_id, $pack_id );
                }

            } // endif if pack not free

        } // end foreach
    } // $user_query->get_results()
}
endif;


if( !function_exists('houzez_cancel_user_membership') ):
    function houzez_cancel_user_membership( $user_id = 0, $membership_id = 0 ) {
        global $post;

        $user_id       = intval( $user_id );
        $membership_id = intval( $membership_id );

        $current_package_id = get_user_meta( $user_id, 'package_id', true );
        $current_package_id = intval( $current_package_id );

        if ( $current_package_id !== $membership_id ) {
            return;
        }

        /**
         * Before membership cancelled
         *
         * @param int $user_id       - User ID.
         * @param int $membership_id - Package ID that's being cancelled.
         */
        do_action( 'houzez_before_delete_user_membership', $user_id, $membership_id );

        delete_user_meta( $user_id, 'package_id', '' );
        delete_user_meta( $user_id, 'package_listings', '' );
        delete_user_meta( $user_id, 'package_activation', '' );
        delete_user_meta( $user_id, 'package_featured_listings', '' );

        update_user_meta( $user_id, 'houzez_subscription_detail_status', 'expired');
        delete_user_meta( $user_id, 'fave_stripe_user_profile' );
        delete_user_meta( $user_id, 'houzez_stripe_subscription_id' );
        delete_user_meta( $user_id, 'houzez_stripe_subscription_start' );
        delete_user_meta( $user_id, 'houzez_stripe_subscription_due' );
        update_user_meta( $user_id, 'houzez_has_stripe_recurring', 0 );
        update_user_meta( $user_id, 'houzez_is_recurring_membership', 0 );

        delete_user_meta( $user_id, 'houzez_subscription_order_number' );
        delete_user_meta( $user_id, 'houzez_subscription_session_id' );
        delete_user_meta( $user_id, 'houzez_subscription_plan_id' );
        delete_user_meta( $user_id, 'houzez_membership_id' );
        delete_user_meta( $user_id, 'houzez_payment_method' );

        $args = array(
            'post_type'   => 'property',
            'posts_per_page' => -1,
            'post_status' => 'any'
        );

        $agency_agents = houzez_get_agency_agents( $user_id );
        $package_permission = houzez_can_agent_user_agency_package( $user_id );

        if( $agency_agents && $package_permission ) {
            $agency_agents[] = $user_id;
            $args['author__in'] = $agency_agents;
        } else {
            $args['author'] = $user_id;
        }

        $query = new WP_Query( $args );

        while( $query->have_posts()) {
            $query->the_post();

            $houzez_manual_expire = get_post_meta( $post->ID, 'houzez_manual_expire', true );

            // Check if manual expire date enable
            if( empty( $houzez_manual_expire )) {
                $prop = array(
                    'ID' => $post->ID,
                    'post_type' => 'property',
                    'post_status' => 'expired'
                );

                wp_update_post($prop);
                houzez_listing_expire_meta($post->ID);
            }
        }
        wp_reset_query();

        $user = get_user_by( 'id', $user_id );
        $user_email = $user->user_email;

        /**
         * after membership cancelled
         *
         * @param int $user_id       - User ID.
         * @param int $membership_id - Package ID that's being cancelled.
         */
        do_action( 'houzez_after_user_membership_cancelled', $user_id, $membership_id );

        $args = array();

        houzez_email_type( $user_email, 'membership_cancelled', $args );
        wp_die();
    }
endif;

if( !function_exists('houzez_stripe_cancel_subscription') ) {
    function houzez_stripe_cancel_subscription($user_id = 0, $membership_id = 0) {
        $user_id       = intval( $user_id );
        $membership_id = intval( $membership_id );

        $current_package_id = get_user_meta( $user_id, 'package_id', true );
        $current_package_id = intval( $current_package_id );

        if ( $current_package_id !== $membership_id ) {
            return;
        }

        update_user_meta( $user_id, 'houzez_stripe_subscription_id', '');
        // Stripe customer record persists after subscription deletion, so we
        // keep fave_stripe_user_profile so a future checkout reuses the same
        // customer instead of creating a new one for the same email.
        update_user_meta( $user_id, 'houzez_is_recurring_membership', 0);
        update_user_meta( $user_id, 'houzez_has_stripe_recurring', 0 );
        update_user_meta( $user_id, 'houzez_subscription_detail_status', 'expired');
    }
}

/*---------------------------------------------------------------------------
Cancel stripe membership
-----------------------------------------------------------------------------*/
add_action( 'wp_ajax_houzez_cancel_stripe', 'houzez_cancel_stripe' );
if( !function_exists('houzez_cancel_stripe') ) {
    function houzez_cancel_stripe() {

        require_once( get_template_directory() . '/framework/stripe-php/init.php' );

        global $current_user;
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;

        if (!is_user_logged_in()) {
            exit('ko');
        }
        if ( $userID === 0 ) {
            exit('out pls');
        }

        $stripe_customer_id = \EstateSite\Core\Property::get( $user_id, 'stripe_user_profile', null, 'user' );
        $subscription_id = get_user_meta($user_id, 'houzez_stripe_subscription_id', true);

        $stripe_secret_key = houzez_option('stripe_secret_key');
        $stripe_publishable_key = houzez_option('stripe_publishable_key');

        $stripe = array(
            "secret_key"      => $stripe_secret_key,
            "publishable_key" => $stripe_publishable_key
        );
        \Stripe\Stripe::setApiKey($stripe['secret_key']);

        $sub = \Stripe\Customer::retrieve($stripe_customer_id);
        $subscription = \Stripe\Subscription::retrieve($subscription_id);
        \Stripe\Subscription::update(
            $subscription_id,
            array(
                'cancel_at_period_end' => true,
            )
        );
        $subscription->cancel();

        
        delete_user_meta( $user_id, 'houzez_subscription_detail_status', 'expired');
        //delete_user_meta( $user_id, 'fave_stripe_user_profile' );
        delete_user_meta( $user_id, 'houzez_stripe_subscription_id' );
        delete_user_meta( $user_id, 'houzez_stripe_subscription_start' );
        delete_user_meta( $user_id, 'houzez_stripe_subscription_due' );
        update_user_meta( $user_id, 'houzez_has_stripe_recurring', 0 );
        update_user_meta( $user_id, 'houzez_is_recurring_membership', 0 );

        delete_user_meta( $user_id, 'houzez_subscription_order_number' );
        delete_user_meta( $user_id, 'houzez_subscription_session_id' );
        delete_user_meta( $user_id, 'houzez_subscription_plan_id' );
        //delete_user_meta( $user_id, 'houzez_membership_id' );
        //delete_user_meta( $user_id, 'houzez_payment_method' );

        wp_die();
    }
}

/*---------------------------------------------------------------------------
Cancel paypal membership
-----------------------------------------------------------------------------*/
add_action( 'wp_ajax_houzez_cancel_paypal', 'houzez_cancel_paypal' );
if( !function_exists('houzez_cancel_paypal') ) {
    function houzez_cancel_paypal() {

        $user_id = get_current_user_id();

        if (!is_user_logged_in()) {
            exit('ko');
        }
        if ( $userID === 0 ) {
            exit('out pls');
        }

        $subscription_id = get_user_meta($user_id, 'houzez_paypal_recurring_profile_id', true);

        $host = 'https://api.sandbox.paypal.com';
        $is_paypal_live = houzez_option('paypal_api');
        if( $is_paypal_live =='live'){
            $host = 'https://api.paypal.com';
        }

        $url             =   $host.'/v1/oauth2/token';
        $postArgs        =   'grant_type=client_credentials';

        if(function_exists('houzez_get_paypal_access_token')){
            $access_token = houzez_get_paypal_access_token( $url, $postArgs );
        }

        $url = $host.'/v1/billing/subscriptions/'.$subscription_id.'/cancel';

        $json_resp  = houzez_execute_paypal_request_2($url, $access_token);

        update_user_meta( $user_id, 'houzez_is_recurring_membership', 0 );
        update_user_meta( $user_id, 'houzez_paypal_recurring_profile_id', '' );
        wp_die();
    }
}