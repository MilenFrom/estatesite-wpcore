<?php
/**
 * Auto-ported from Houzez Redux to CSF.
 * Source: wp-content/themes/houzez/framework/options/
 */

defined( 'ABSPATH' ) || exit;

global $estatesite_opt_prefix;
$prefix = $estatesite_opt_prefix;

global $houzez_opt_name;

CSF::createSection( $prefix, array(
    'title'            => esc_html__( 'Top Bar', 'houzez' ),
    'id'               => 'header-top-bar',
    'subsection'       => false,
    'fields'           => array(
        array(
            'id'       => 'top_bar',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Enable/Disable header top bar', 'houzez' ),
            'desc' => '',
            'default'  => 0,
            'on'       => esc_html__( 'Enabled', 'houzez' ),
            'off'      => esc_html__( 'Disabled', 'houzez' ),
        ),
        array(
            'id'       => 'top_bar_width',
            'type'     => 'select',
            'title'    => esc_html__( 'Layout', 'houzez' ),
            'desc' => '',
            'options'   => array(
                'container' => esc_html__( 'Boxed', 'houzez' ),
                'container-fluid'   => esc_html__( 'Full Width', 'houzez' )
            ),
            'default'  => 'container'// 1 = on | 0 = off
        ),
        array(
            'id'       => 'top_bar_mobile',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Hide Top Bar in Mobile?', 'houzez' ),
            'desc' => '',
            'default'  => 0,
            'on'       => esc_html__( 'Yes', 'houzez' ),
            'off'      => esc_html__( 'No', 'houzez' ),
        ),
        array(
            'id'       => 'top_bar_left',
            'type'     => 'select',
            'title'    => esc_html__( 'Top Bar Left Area', 'houzez' ),
            'desc' => esc_html__( 'What would you like to show on top bar left area.', 'houzez' ),
            'options'   => array(
                'none'   => esc_html__( 'Nothing', 'houzez' ),
                'menu_bar'    => esc_html__( 'Menu ( Create and assing menu under Appearance -> Menus )', 'houzez' ),
                'social_icons'    => esc_html__( 'Social Icons', 'houzez' ),
                'contact_info'    => esc_html__( 'Contact Info', 'houzez' ),
                'slogan'    => esc_html__( 'Slogan', 'houzez' ),
                'houzez_switchers'    => esc_html__( 'Currency Switcher + Area Switcher', 'houzez' )
            ),
            'default'  => 'none'
        ),
        array(
            'id'       => 'top_bar_right',
            'type'     => 'select',
            'title'    => esc_html__( 'Top Bar Right Area', 'houzez' ),
            'desc' => esc_html__( 'What would you like to show on top bar right area.', 'houzez' ),
            'options'   => array(
                'none'   => esc_html__( 'Nothing', 'houzez' ),
                'menu_bar'    => esc_html__( 'Menu ( Create and assing menu under Appearance -> Menus )', 'houzez' ),
                'social_icons'    => esc_html__( 'Social Icons', 'houzez' ),
                'contact_info'    => esc_html__( 'Contact Info', 'houzez' ),
                'slogan'    => esc_html__( 'Slogan', 'houzez' ),
                'houzez_switchers'    => esc_html__( 'Currency Switcher + Area Switcher', 'houzez' )
            ),
            'default'  => 'none'
        ),
        array(
            'id'        => 'top_bar_phone',
            'type'      => 'text',
            'default'   => '',
            'title'     => esc_html__( 'Phone Number', 'houzez' ),
            'desc'  => '',
        ),
        array(
            'id'        => 'top_bar_email',
            'type'      => 'text',
            'default'   => '',
            'title'     => esc_html__( 'Email Address', 'houzez' ),
            'desc'  => '',
        ),
        array(
            'id'        => 'top_bar_slogan',
            'type'      => 'textarea',
            'default'   => '',
            'title'     => esc_html__( 'Slogan', 'houzez' ),
            'desc'  => esc_html__( 'Enter website slogan', 'houzez' )
        )
    )
) );



// Currency Switcher
if ( class_exists( 'FCC_Rates' ) ) {    // if wp-currencies plugins is active

    // get all currency codes
    $currencies = Fcc_get_currencies();
    $currency_codes = array();
    if ( 0 < count( $currencies ) ) {
        foreach( $currencies as $currency_code => $currency ) {
            $currency_codes[$currency_code] = $currency_code;
        }
    }

    CSF::createSection( $prefix, array(
        'title' => esc_html__('Currency Switcher', 'houzez'),
        'id' => 'currency-switcher',
        'parent'           => 'header-top-bar',
        'fields' => array(
            array(
                'id'       => 'currency_switcher_enable',
                'type'     => 'switcher',
                'title'    => esc_html__( 'Enable/Disable currency switcher in top bar', 'houzez' ),
                'desc' => '',
                'default'  => 0,
                'on'       => esc_html__( 'Enabled', 'houzez' ),
                'off'      => esc_html__( 'Disabled', 'houzez' ),
            ),
            array(
                'id' => 'currency_switcher_info',
                'type' => 'info',
                'title' => esc_html__('Guide', 'houzez'),
                'style' => 'info',
                'desc' => __('Please find full list of available currencies at <a target="_blank" href="https://openexchangerates.org/currencies">https://openexchangerates.org/currencies</a><br/>Add api key under Houzez -> Currency Converter API', 'houzez')
            ),
            array(
                'id' => 'houzez_base_currency',
                'type' => 'select',
                'title' => esc_html__('Base Currency', 'houzez'),
                'desc' => esc_html__('Please select base currency which will use as base currency for all conversions.', 'houzez'),
                'read-only' => false,
                'options' => $currency_codes,
                'default' => 'USD'
            ),
            array(
                'id' => 'houzez_supported_currencies',
                'type' => 'textarea',
                'title' => esc_html__('Your Supported Currencies.', 'houzez'),
                'desc' => esc_html__('Please provide comma separated currencies code in Capital Letters.', 'houzez'),
                'default' => 'AUD,CAD,CHF,EUR,GBP,HKD,JPY,NOK,SEK,USD,NGN'
            ),
            array(
                'id' => 'houzez_currency_expiry',
                'title' => esc_html__('Expiry time','houzez'),
                'desc' => esc_html__('Select expiry time for selected currency.','houzez'),
                'default' => '3600',
                'type' => "radio",
                'options' => array(
                    '3600' => esc_html__('One Hour','houzez'),
                    '86400' => esc_html__('One Day','houzez'),
                    '604800' => esc_html__('One Week','houzez'),
                    '18144000' => esc_html__('One Month','houzez'),
                )
            )
        ),
    ));
}

CSF::createSection( $prefix, array(
    'title' => esc_html__('Area Switcher', 'houzez'),
    'id' => 'area-switcher',
    'parent'           => 'header-top-bar',
    'fields' => array(
        array(
            'id'       => 'area_switcher_enable',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Enable/Disable area switcher in top bar', 'houzez' ),
            'desc' => '',
            'default'  => 0,
            'on'       => esc_html__( 'Enabled', 'houzez' ),
            'off'      => esc_html__( 'Disabled', 'houzez' ),
        ),

        array(
            'id' => 'houzez_base_area',
            'type' => 'select',
            'title' => esc_html__('Base Area', 'houzez'),
            'desc' => esc_html__('Selected area will be used as base area for all conversions.', 'houzez'),
            'read-only' => false,
            'options' => array(
                'sqft' => esc_html( 'Square Feet', 'houzez' ),
                'sq_meter' => esc_html( 'Square Meters', 'houzez' )
            ),
            'default' => 'sqft'
        )
    ),
));