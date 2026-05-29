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
    'title'      => esc_html__( 'Custom Code', 'houzez' ),
    'id'         => 'custom_code',
    'icon'       => 'el el-cog el-icon-small',
    'fields'     => array(
        array(
            'id'       => 'custom_css',
            'type'     => 'code_editor',
            'title'    => esc_html__( 'CSS Code', 'houzez' ),
            'desc' => esc_html__( 'Paste your CSS code here.', 'houzez' ),
            'mode'     => 'css',
            'theme'    => 'monokai',
            'default'  => ""
        ),
        array(
            'id'       => 'custom_js_header',
            'type'     => 'code_editor',
            'title'    => esc_html__( 'Custom JS Code', 'houzez' ),
            'desc' => esc_html__( 'Custom JavaScript/Analytics Header.', 'houzez' ),
            'mode'     => 'text',
            'theme'    => 'chrome',
            'default'  => ""
        ),
        array(
            'id'       => 'custom_js_footer',
            'type'     => 'code_editor',
            'title'    => esc_html__( 'Custom JS Code', 'houzez' ),
            'desc' => esc_html__( 'Custom JavaScript/Analytics Footer.', 'houzez' ),
            'mode'     => 'text',
            'theme'    => 'chrome',
            'default'  => ""
        )
    )
) );