<?php
/**
 * Auto-ported from Houzez Redux to CSF.
 * Source: wp-content/themes/houzez/framework/options/
 */

defined( 'ABSPATH' ) || exit;

global $estatesite_opt_prefix;
$prefix = $estatesite_opt_prefix;

global $houzez_opt_name, $allowed_html_array;
CSF::createSection( $prefix, array(
    'title'  => esc_html__( 'Blog', 'houzez' ),
    'id'     => 'blog',
    'icon'   => 'el-icon-edit el-icon-small',
    'fields'        => array(
        array(
            'id'       => 'blog_pages_s_layout',
            'type'     => 'image_select',
            'title'    => __('Page Layout', 'houzez'),
            'desc' => '',
            'options'  => array(
                'left-sidebar' => HOUZEZ_IMAGE. '2cl.png',
                'right-sidebar' => HOUZEZ_IMAGE. '2cr.png'
            ),
            'default' => 'right-sidebar'
        ),
        array(
            'id'       => 'blog_single_layout',
            'type'     => 'image_select',
            'title'    => __('Single Post Layout', 'houzez'),
            'desc' => '',
            'options'  => array(
                'no-sidebar' => ReduxFramework::$_url.'assets/img/1c.png',
                'left-sidebar' => HOUZEZ_IMAGE. '2cl.png',
                'right-sidebar' => HOUZEZ_IMAGE. '2cr.png'
            ),
            'default' => 'right-sidebar'
        ),
        array(
            'id'       => 'masorny_num_posts',
            'type'     => 'text',
            'title'    => esc_html__( 'Masonry Blog Template', 'houzez' ),
            'desc' => esc_html__( 'Number of posts to display on the Masonry blog pages', 'houzez' ),
            'desc'     => esc_html__( 'Enter the number of posts', 'houzez' ),
            'default'  => '12'
        ),
        array(
            'id'       => 'blog_featured_image',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Featured Image', 'houzez' ),
            'desc'     => esc_html__( 'Enable or disable the featured image', 'houzez' ),
            'desc' => esc_html__( 'Displayed on the single post page', 'houzez' ),
            'default'  => 1,
            'on'       => 'Enabled',
            'off'      => 'Disabled',
        ),

        array(
            'id'       => 'blog_date',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Post Date', 'houzez' ),
            'desc'     => esc_html__( 'Enable or disable the post date', 'houzez' ),
            'desc' => esc_html__( 'Displayed on the blog, archive and single post page', 'houzez' ),
            'default'  => 1,
            'on'       => 'Enabled',
            'off'      => 'Disabled',
        ),

        array(
            'id'       => 'blog_author',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Posts Author', 'houzez' ),
            'desc'     => esc_html__( 'Enable or disable the post author', 'houzez' ),
            'desc' => esc_html__( 'Displayed on the blog, archive and single post page', 'houzez' ),
            'default'  => 1,
            'on'       => 'Enabled',
            'off'      => 'Disabled',
        ),

        array(
            'id'       => 'blog_tags',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Tags', 'houzez' ),
            'default'  => 1,
            'on'       => 'Enabled',
            'off'      => 'Disabled',
        ),

        array(
            'id'       => 'blog_author_box',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Author Box', 'houzez' ),
            'desc'     => esc_html__( 'Enable or disable the author box', 'houzez' ),
            'desc' => esc_html__( 'Displayed on the single post page', 'houzez' ),
            'default'  => 1,
            'on'       => 'Enabled',
            'off'      => 'Disabled',
        ),
        array(
            'id'       => 'blog_next_prev',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Next/Prev Post', 'houzez' ),
            'default'  => 1,
            'on'       => 'Enabled',
            'off'      => 'Disabled',
        ),
        array(
            'id'       => 'blog_related_posts',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Related Posts', 'houzez' ),
            'default'  => 1,
            'on'       => 'Enabled',
            'off'      => 'Disabled',
        ),

    ),
));