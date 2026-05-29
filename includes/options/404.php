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
    'title'  => esc_html__( 'Page 404', 'houzez' ),
    'id'     => 'page-404',
    'icon'   => 'el-icon-error el-icon-small',
    'fields'        => array(

        array(
            'id'       => '404-title',
            'type'     => 'text',
            'title'    => esc_html__( 'Title', 'houzez' ),
            'desc'     => esc_html__( 'Enter the page title', 'houzez' ),
            'default'  => 'Oh oh! Page not found.'
        ),
        array(
            'id'        => '404-des',
            'type'      => 'textarea',
            'title'     => esc_html__( 'Description', 'houzez' ),
            'desc'     => esc_html__( 'Enter the page content', 'houzez' ),
            'default'   => "We're sorry, but the page you are looking for doesn't exist. You can search your topic using the box below or return to the homepage."
        )
    ),
));