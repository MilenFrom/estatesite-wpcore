<?php
/**
 * Auto-ported from Houzez Redux to CSF.
 * Source: wp-content/themes/houzez/framework/options/
 */

defined( 'ABSPATH' ) || exit;

global $estatesite_opt_prefix;
$prefix = $estatesite_opt_prefix;

global $houzez_opt_name, $allowed_html_array, $custom_fields_array;
CSF::createSection( $prefix, array(
    'title'  => esc_html__( 'Membership', 'houzez' ),
    'id'     => 'payment-membership',
    'icon'   => 'el-icon-credit-card el-icon-small',
    'fields'        => array(
        array(
            'id'       => 'enable_paid_submission',
            'type'     => 'select',
            'title'    => esc_html__('Enable Paid Submission', 'houzez'),
            'desc' => '',
            'desc'     => esc_html__('Select the submission type.', 'houzez'),
            'options'  => array(
                'no'   => esc_html__( 'No', 'houzez' ),
                'free_paid_listing'   => esc_html__( 'Free (Pay For Featured)', 'houzez' ),
                'per_listing'   => esc_html__( 'Per Listing', 'houzez' ),
                'membership'   => esc_html__( 'Membership', 'houzez' )
            ),
            'default'  => 'no',
        ),

        array(
            'id'       => 'houzez_disable_recurring',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Enable Recurring Payments', 'houzez' ),
            'required' => array( 'enable_paid_submission', '=', 'membership' ),
            'desc'     => esc_html__( 'Enable or disable recurring option for PayPal & Stripe.', 'houzez' ),
            'desc' => '',
            'default'  => 0,
            'on'       => esc_html__( 'Enabled', 'houzez' ),
            'off'      => esc_html__( 'Disabled', 'houzez' ),
        ),

        array(
            'id'       => 'houzez_auto_recurring',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Auto Recurring Payments', 'houzez' ),
            'required' => array( 'houzez_disable_recurring', '=', '1' ),
            'desc'     => esc_html__( 'Enable auto recurring payments for PayPal & Stripe.', 'houzez' ),
            'desc' => '',
            'default'  => 0,
            'on'       => esc_html__( 'Enabled', 'houzez' ),
            'off'      => esc_html__( 'Disabled', 'houzez' ),
        ),

        array(
            'id'       => 'per_listing_expire_unlimited',
            'type'     => 'switcher',
            //'required' => array('enable_paid_submission', '=', 'per_listing'),
            'title'    => esc_html__( 'Expire Days', 'houzez' ),
            'desc'     => esc_html__( 'Only for "Per Listings" and "Free (Pay for Featured)"', 'houzez' ),
            'desc' => esc_html__('Want to set single listing expire days?', 'houzez'),
            'default'  => 0,
            'on'       => 'Yes',
            'off'      => 'No',
        ),
        array(
            'id'       => 'per_listing_expire',
            'type'     => 'text',
            'required' => array( 'per_listing_expire_unlimited', '=', '1' ),
            'title'    => esc_html__('Number Of Expiring Days', 'houzez'),
            'desc' => esc_html__('It starts from the moment the property is published on the website', 'houzez'),
            'desc'     => esc_html__('Enter the number of days', 'houzez'),
            'default'  => '30',
        ),
        array(
            'id'       => 'featured_listing_expire',
            'type'     => 'text',
            'title'    => esc_html__('Featured Listings - Expiring Days', 'houzez'),
            'desc' => esc_html__('It starts from the moment the property is set to featured', 'houzez'),
            'desc'     => esc_html__('Enter the number of days', 'houzez'),
            'required' => array('enable_paid_submission', '=', 'free_paid_listing'),
        ),

        array(
            'id'       => 'auto_delete_expired_listings',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Auto Delete Expired Listings.', 'houzez' ),
            'desc' => esc_html__( 'Enable/Disable auto delete expired listings.', 'houzez' ),
            'default'  => 0,
            'on'       => esc_html__( 'Enabled', 'houzez' ),
            'off'      => esc_html__( 'Disabled', 'houzez' ),
        ),

        array(
            'id'       => 'auto_delete_expired_listings_days',
            'type'     => 'text',
            'title'    => esc_html__('Auto Delete Days', 'houzez'),
            'desc' => esc_html__('Enter number of days after expired listings will be deleted', 'houzez'),
            'required' => array('auto_delete_expired_listings', '=', '1'),
            'validate' => 'numeric'
        ),

        array(
            'id'       => 'refund_listing_on_delete',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Restore Package Listing on Delete', 'houzez' ),
            'desc' => esc_html__( 'When a user deletes a property, restore the listing back to their remaining package quota.', 'houzez' ),
            'required' => array( 'enable_paid_submission', '=', 'membership' ),
            'default'  => 1,
            'on'       => esc_html__( 'Enabled', 'houzez' ),
            'off'      => esc_html__( 'Disabled', 'houzez' ),
        ),

        array(
            'id'       => 'currency_paid_submission',
            'type'     => 'select',
            'required' => array( 'enable_paid_submission', '!=', 'no' ),
            'title'    => esc_html__('Currency', 'houzez'),
            'desc' => esc_html__('Note: AED, BHD, KWD, SAR currencies not supported by PayPal.', 'houzez'),
            'desc'     => esc_html__('Select the currency to use for paid submissions', 'houzez'),
            'options'  => array(
                'USD'  => 'USD',
                'EUR'  => 'EUR',
                'AED'  => 'AED', // PayPal not support AED
                'AUD'  => 'AUD',
                'ARS'  => 'ARS',
                'AZN'  => 'AZN',
                'BHD'  => 'BHD', // PayPal not support BHD, 3 digit
                'BRL'  => 'BRL',
                'BTD'  => 'BTD',
                'CAD'  => 'CAD',
                'CHF'  => 'CHF',
                'COP'  => 'COP',
                'CZK'  => 'CZK',
                'DKK'  => 'DKK',
                'DOP'  => 'DOP',
                'BDT'  => 'BDT',
                'HKD'  => 'HKD',
                'HUF'  => 'HUF',
                'IDR'  => 'IDR',
                'ILS'  => 'ILS',
                'INR'  => 'INR',
                'JMD'  => 'JMD',
                'JPY'  => 'JPY',
                'KOR'  => 'KOR',
                'KSH'  => 'KSH',
                'KWD'  => 'KWD', // PayPal not support KWD, 3 digit
                'LKR'  => 'LKR',
                'MYR'  => 'MYR',
                'MXN'  => 'MXN',
                'MUR'  => 'MUR',
                'NGN'  => 'NGN',
                'NOK'  => 'NOK',
                'NZD'  => 'NZD',
                'PEN'  => 'PEN',
                'PHP'  => 'PHP',
                'PLN'  => 'PLN',
                'GTQ'  => 'GTQ',
                'GEL'  => 'GEL',
                'GBP'  => 'GBP',
                'RON'  => 'RON',
                'RUB'  => 'RUB',
                'SAR'  => 'SAR', // PayPal not support SAR
                'SGD'  => 'SGD',
                'SEK'  => 'SEK',
                'TWD'  => 'TWD',
                'THB'  => 'THB',
                'TRY'  => 'TRY',
                'VND'  => 'VND',
                'ZAR'  => 'ZAR'
            ),
            'default'  => 'USD',
        ),
        array(
            'id'       => 'price_listing_submission',
            'type'     => 'text',
            'required' => array( 'enable_paid_submission', '=', 'per_listing' ),
            'title'    => esc_html__('Submission Price', 'houzez'),
            'desc' => '',
            'desc'     => esc_html__('Enter the price to list a property', 'houzez'),
            'default'  => '',
        ),
        array(
            'id'       => 'price_featured_listing_submission',
            'type'     => 'text',
            'required' => array( 'enable_paid_submission', '!=', 'no' ),
            'title'    => esc_html__('Featured Price', 'houzez'),
            'desc' => '',
            'desc'     => esc_html__('Enter the price to feature a property', 'houzez'),
            'default'  => '',
        ),
        array(
            'id'       => 'tax_percentage_per_listing',
            'type'     => 'text',
            'required' => array( 'enable_paid_submission', '=', 'per_listing' ),
            'title'    => esc_html__('Tax Percentage for Per Listing', 'houzez'),
            'desc' => '',
            'desc'     => esc_html__('Enter the tax percentage for per listing submission (Only digits, e.g., 10 for 10%)', 'houzez'),
            'default'  => '',
        ),
        array(
            'id'       => 'tax_percentage_featured',
            'type'     => 'text',
            'required' => array( 'enable_paid_submission', '!=', 'no' ),
            'title'    => esc_html__('Tax Percentage for Featured Listing', 'houzez'),
            'desc' => '',
            'desc'     => esc_html__('Enter the tax percentage for featured listing (Only digits, e.g., 10 for 10%)', 'houzez'),
            'default'  => '',
        ),

        array(
            'id'       => 'paypal_api',
            'type'     => 'select',
            'required' => array( 'enable_paid_submission', '!=', 'no' ),
            'title'    => esc_html__('Paypal, Stripe and 2Checkout Api', 'houzez'),
            'desc' => esc_html__('Sandbox = test API. LIVE = real payments API', 'houzez'),
            'desc'     => esc_html__('Update PayPal, Stripe and 2Checkout settings according to API type selection', 'houzez'),
            'options'  => array(
                'sandbox'=> 'Sandbox',
                'live'   => 'Live',
            ),
            'default'  => 'sandbox',
        ),
        array(
            'id'       => 'payment_terms_condition',
            'type'     => 'select',
            'data'     => 'pages',
            'ajax'     => true,
            'title'    => esc_html__( 'Terms & Conditions', 'houzez' ),
            'desc' => esc_html__( 'Select which page to use for Terms & Conditions', 'houzez' ),
            //'desc'     => '',
        ),
    ),
));


CSF::createSection( $prefix, array(
    'title'  => esc_html__( 'Thank You Page', 'houzez' ),
    'id'     => 'mem-thankyou',
    'subsection' => true,
    'fields' => array(
        array(
            'id'       => 'thankyou_title',
            'type'     => 'text',
            'title'    => esc_html__( 'Title', 'houzez' ),
            'desc'     => esc_html__( 'Enter the page title', 'houzez' ),
            'desc' => '',
            'default'  => esc_html__( 'Thank you for your business with us', 'houzez' ),
        ),
        array(
            'id'       => 'thankyou_des',
            'type'     => 'wp_editor',
            'title'    => esc_html__('Message', 'houzez'),
            'desc' => '',
            'desc'     => esc_html__( 'Enter the page message', 'houzez' ),
            'default'  => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer in augue rhoncus, congue neque eu, consequat quam. Maecenas in cursus dui, sed tempor est. Duis varius nibh in lorem venenatis, in tincidunt nunc scelerisque.',
            'args'   => array(
                'teeny'            => true,
                'textarea_rows'    => 10
            )
        ),

    )
));

CSF::createSection( $prefix, array(
    'title'  => esc_html__( 'Payment Gateways', 'houzez' ),
    'id'     => 'payment-gateways',
    'icon'   => 'el-icon-credit-card el-icon-small',
    'fields' => array(
        array(
            'id'       => 'houzez_payment_gateways',
            'type'     => 'button_set',
            'title'    => __('Choose Payment gateway type', 'houzez'),
            'desc' => '',
            //Must provide key => value pairs for options
            'options' => array(
                'houzez_custom_gw' => 'Houzez Custom Gateways', 
                'gw_woocommerce' => 'WooCommerce', 
             ), 
            'default' => 'houzez_custom_gw'
        ),

        array(
            'id'     => 'woocommerce-info',
            'type'   => 'info',
            'notice' => false,
            'style'  => 'info',
            'title'  => wp_kses(__( 'Follow <a target="_blank" href="https://favethemes.zendesk.com/hc/en-us/articles/360045293072">WooCommerce Documentation</a>', 'houzez' ), $allowed_html_array),
            'desc' => __('"houzez-woo-addon" and "woocommerce" plugin required', 'houzez'),
            'required' => array('houzez_payment_gateways', '=', 'gw_woocommerce')
        ),
    )


));

CSF::createSection( $prefix, array(
    'title'  => esc_html__( 'Paypal Settings', 'houzez' ),
    'id'     => 'mem-paypal-settings',
    'subsection' => true,
    'fields' => array(
        array(
            'id'       => 'enable_paypal',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Enable PayPal', 'houzez' ),
            
            'desc'     => esc_html__( 'Enable or disable PayPal', 'houzez' ),
            'desc' => '',
            'default'  => 0,
            'on'       => esc_html__( 'Enabled', 'houzez' ),
            'off'      => esc_html__( 'Disabled', 'houzez' ),
        ),
        array(
            'id'       => 'paypal_client_id',
            'type'     => 'text',
            'required' => array( 'enable_paypal', '=', '1' ),
            'title'    => esc_html__('Client ID', 'houzez'),
            'desc' => '',
            'desc'    => esc_html__('Enter the PayPal client ID', 'houzez'),
            'default'  => '',
        ),
        array(
            'id'       => 'paypal_client_secret_key',
            'type'     => 'text',
            'required' => array( 'enable_paypal', '=', '1' ),
            'title'    => esc_html__('Client Secret Key', 'houzez'),
            'desc' => '',
            'desc'    => esc_html__('Enter the PayPal client secret key', 'houzez'),
            'default'  => '',
        ),
        array(
            'id'       => 'paypal_receiving_email',
            'type'     => 'text',
            'required' => array( 'enable_paypal', '=', '1' ),
            'title'    => esc_html__('Receiving Email', 'houzez'),
            'desc' => '',
            'desc'    => esc_html__('Enter the PayPal receiving email account', 'houzez'),
            'default'  => '',
        ),
        array(
            'id'       => 'paypal_webhook_id',
            'type'     => 'text',
            'required' => array( 'enable_paypal', '=', '1' ),
            'title'    => esc_html__('Webhook ID', 'houzez'),
            'desc' => '',
            'desc'    => esc_html__('Enter the PayPal Webhook ID for recurring payment notifications. Create a webhook in PayPal Developer Dashboard pointing to your IPN/Webhook page URL with events: PAYMENT.SALE.COMPLETED, BILLING.SUBSCRIPTION.CANCELLED, BILLING.SUBSCRIPTION.SUSPENDED, BILLING.SUBSCRIPTION.EXPIRED', 'houzez'),
            'default'  => '',
        ),
    )
));

CSF::createSection( $prefix, array(
    'title'  => esc_html__( 'Stripe Settings', 'houzez' ),
    'id'     => 'mem-stripe-settings',
    'subsection' => true,
    'fields' => array(
        array(
            'id'       => 'enable_stripe',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Enable Stripe', 'houzez' ),
            
            'desc'     => esc_html__( 'Enable or disable Stripe', 'houzez' ),
            'desc' => '',
            'default'  => 0,
            'on'       => esc_html__( 'Enabled', 'houzez' ),
            'off'      => esc_html__( 'Disabled', 'houzez' ),
        ),
        array(
            'id'       => 'stripe_secret_key',
            'type'     => 'text',
            'required' => array( 'enable_stripe', '=', '1' ),
            'title'    => esc_html__('Secret Key', 'houzez'),
            'desc' => esc_html__('Info is taken from your account at https://dashboard.stripe.com/login', 'houzez'),
            'desc'    => esc_html__('Enter the Stripe secret key', 'houzez'),
            'default'  => '',
        ),
        array(
            'id'       => 'stripe_publishable_key',
            'type'     => 'text',
            'required' => array( 'enable_stripe', '=', '1' ),
            'title'    => esc_html__('Publishable Key', 'houzez'),
            'desc' => esc_html__('Info is taken from your account at https://dashboard.stripe.com/login', 'houzez'),
            'desc'    => esc_html__('Enter the Stripe publishable key', 'houzez'),
            'default'  => '',
        ),
    )
));

CSF::createSection( $prefix, array(
    'title'  => esc_html__( 'Direct Payment / Wire Payment', 'houzez' ),
    'id'     => 'mem-wire-payment',
    'subsection' => true,
    'fields' => array(
        array(
            'id'       => 'enable_wireTransfer',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Enable Wire Transfer', 'houzez' ),
            
            'desc'    => esc_html__('Enable or disable the Wire Transfert', 'houzez'),
            'desc' => '',
            'default'  => 0,
            'on'       => esc_html__( 'Enabled', 'houzez' ),
            'off'      => esc_html__( 'Disabled', 'houzez' ),
        ),
        array(
            'id'       => 'direct_payment_instruction',
            'type'     => 'wp_editor',
            'required' => array( 'enable_wireTransfer', '=', '1' ),
            'title'    => esc_html__('Wire instructions', 'houzez'),
            'desc' => '',
            'desc'    => esc_html__('Enter the wire instructions and detail to send the payment', 'houzez'),
            'default'  => '',
            'args'   => array(
                'teeny'            => true,
                'textarea_rows'    => 10
            )
        ),
        array(
            'id'     => 'direct-pay-info',
            'type'   => 'info',
            'notice' => false,
            'style'  => 'info',
            'title'  => wp_kses(__( '<span class="font24">Direct pay / Wire Transfer</span>', 'houzez' ), $allowed_html_array),
            'desc'   => ''
        ),

        array(
            'id'       => 'thankyou_wire_title',
            'type'     => 'text',
            'title'    => esc_html__( 'Title', 'houzez' ),
            'desc'     => esc_html__( 'Enter the page title', 'houzez' ),
            'desc' => '',
            'default'  => 'Thank you. Your order has been received',
        ),
        array(
            'id'       => 'thankyou_wire_des',
            'type'     => 'wp_editor',
            'title'    => esc_html__('Message', 'houzez'),
            'desc' => '',
            'desc'     => esc_html__( 'Enter the page message', 'houzez' ),
            'default'  => 'Make your payment directly into our bank account. Please use your Order ID as payment reference.',
            'args'   => array(
                'teeny'            => true,
                'textarea_rows'    => 10
            )
        ),
    )
));