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
    'title'  => esc_html__( 'Agents', 'houzez' ),
    'id'     => 'houzez-agents',
    'icon'   => 'el-icon-user el-icon-small',
    'fields'        => array(
        array(
            'id'       => 'agents-template-layout',
            'type'     => 'image_select',
            'title'    => esc_html__('Agents Layout', 'houzez'),
            'desc' => '',
            'options'  => array(
                'v1' => HOUZEZ_IMAGE . 'all-agents-style-1.jpg',
                'v2' => HOUZEZ_IMAGE . 'all-agents-style-2.jpg',
                'v3' => HOUZEZ_IMAGE . 'all-agents-style-3.jpg',
            ),
            'default'  => 'v1',
        ),
        array(
            'id'       => 'num_of_agents',
            'type'     => 'text',
            'title'    => esc_html__( 'Number of Agents', 'houzez' ),
            'desc'    => esc_html__( 'Number of agents to display on the All Agents page template', 'houzez' ),
            'desc'    => esc_html__( 'Enter the number of agents', 'houzez' ),
            'default'  => '9'
        ),
        
        array(
            'id'        => 'houzez_agent_placeholder',
            'url'       => false,
            'type'      => 'media',
            'title'     => esc_html__( 'Placeholder', 'houzez' ),
            'default'   => array( 'url' => '' ),
            'desc'  => esc_html__( 'Upload default placeholder. Recommended Size 500 x 500 pixels', 'houzez' ),
        ), 

        array(
            'id'       => 'agent_header_search',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Agent Header Search', 'houzez' ),
            'desc' => '',
            'default'  => 1,
            'on'       => 'Enabled',
            'off'      => 'Disabled',
        ),
        array(
            'id'       => 'agent_mobile',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Mobile', 'houzez' ),
            'desc' => '',
            'default'  => 1,
            'on'       => 'Enabled',
            'off'      => 'Disabled',
        ),
        array(
            'id'       => 'agent_phone',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Office Phone', 'houzez' ),
            'desc' => '',
            'default'  => 1,
            'on'       => 'Enabled',
            'off'      => 'Disabled',
        ),

         array(
            'id'       => 'agent_fax',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Fax', 'houzez' ),
            'desc' => '',
            'default'  => 1,
            'on'       => 'Enabled',
            'off'      => 'Disabled',
        ),

         array(
            'id'       => 'agent_email',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Email', 'houzez' ),
            'desc' => '',
            'default'  => 1,
            'on'       => 'Enabled',
            'off'      => 'Disabled',
        ),

         array(
            'id'       => 'agent_website',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Website', 'houzez' ),
            'desc' => '',
            'default'  => 1,
            'on'       => 'Enabled',
            'off'      => 'Disabled',
        ),

         array(
            'id'       => 'agent_social',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Social', 'houzez' ),
            'desc' => '',
            'default'  => 1,
            'on'       => 'Enabled',
            'off'      => 'Disabled',
        ),
    ),
    
));

CSF::createSection( $prefix, array(
    'title'  => esc_html__( 'Agent Detail Page', 'houzez' ),
    'id'     => 'agent-detail-page',
    'subsection' => true,
    'fields' => array(
        array(
            'id'       => 'agent-detail-layout',
            'type'     => 'image_select',
            'title'    => esc_html__('Single Agent Layout', 'houzez'),
            'desc' => '',
            'options'  => array(
                'v1' => HOUZEZ_IMAGE . 'agent-detail-page-style-1.jpg',
                'v2' => HOUZEZ_IMAGE . 'agent-detail-page-style-2.jpg',
            ),
            'default'  => 'v1',
        ),
        array(
            'id'       => 'agent_tabs',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Tabs', 'houzez' ),
            'desc' => esc_html__('Property status tabs displayed in the agent detail page', 'houzez'),
            'desc' => esc_html__( 'Enable or disable the tabs on agent detail page', 'houzez' ),
            'default'  => 0,
            'on'       => 'Enabled',
            'off'      => 'Disabled',
        ),
        array(
            'id'       => 'agent_detail_tab_1',
            'type'     => 'select',
            'title'    => esc_html__('Tab 1', 'houzez'),
            'desc' => esc_html__('Property status tab in the agent detail page', 'houzez'),
            'desc'     => esc_html__('Select the status', 'houzez'),
            'data'     => 'terms',
            'required' => array('agent_tabs', '=', '1'),
            'args'        =>  array('taxonomy'=>'property_status'),
            'default' => ''
        ),
        array(
            'id'       => 'agent_detail_tab_2',
            'type'     => 'select',
            'title'    => esc_html__('Tab 2', 'houzez'),
            'desc' => esc_html__('Property status tab in the agent detail page', 'houzez'),
            'desc'     => esc_html__('Select the status', 'houzez'),
            'required' => array('agent_tabs', '=', '1'),
            'data'        => 'terms',
            'args'        =>  array('taxonomy'=>'property_status'),
            'default' => ''
        ),

        array(
            'id'       => 'agent_listings_layout',
            'type'     => 'select',
            'title'    => __('Listings Layout', 'houzez'),
            'desc' => __('Select the listings layout for the agent detail page', 'houzez'),
            'desc'     => esc_html__('Select the layout', 'houzez'),
            'options'  => array(
                'Listings Version 1' => array(
                    'list-view-v1' => 'List View',
                    'grid-view-v1' => 'Grid View',
                ),
                'Listings Version 2' => array(
                    'list-view-v2' => 'List View',
                    'grid-view-v2' => 'Grid View',
                ),

                'Listings Version 3' => array(
                    'grid-view-v3' => 'Grid View',
                ),

                'Listings Version 4' => array(
                    'grid-view-v4' => 'Grid View',
                ),

                'Listings Version 5' => array(
                    'grid-view-v5' => 'Grid View',
                ),

                'Listings Version 6' => array(
                    'grid-view-v6' => 'Grid View',
                ),

                'Listings Version 7' => array(
                    'list-view-v7' => 'List View',
                    'grid-view-v7' => 'Grid View',
                ),
            ),
            'default' => 'list-view-v1'
        ),
        array(
            'id'       => 'agent_listings_grid_columns',
            'type'     => 'select',
            'title'    => esc_html__( 'Grid Columns', 'houzez' ),
            'desc' => esc_html__( "Select the number of columns to display for similar properties in grid view", 'houzez' ),
            'options'  => array(
                '3' => '3 Columns',
                '2' => '2 Columns',
            ),
            'default' => '2'
        ),
        array(
            'id'       => 'num_of_agent_listings',
            'type'     => 'text',
            'title'    => esc_html__( 'Number of Listings', 'houzez' ),
            'desc'    => esc_html__( 'Number of listings to display on the agent detail page', 'houzez' ),
            'desc'    => esc_html__( 'Enter the number of listings', 'houzez' ),
            'default'  => '10'
        ),
        array(
            'id'       => 'agent_listings_order',
            'type'     => 'select',
            'title'    => __('Default Order', 'houzez'),
            'desc' => __('Listings order on the agent detail page', 'houzez'),
            'desc' => __('Select the listings order.', 'houzez'),
            'options'  => array(
                'default' => esc_html__( 'Default', 'houzez' ),
                'a_title' => esc_html__( 'Title - ASC', 'houzez' ),
                'd_title' => esc_html__( 'Title - DESC', 'houzez' ),
                'd_date' => esc_html__( 'Date New to Old', 'houzez' ),
                'a_date' => esc_html__( 'Date Old to New', 'houzez' ),
                'd_price' => esc_html__( 'Price (High to Low)', 'houzez' ),
                'a_price' => esc_html__( 'Price (Low to High)', 'houzez' ),
                'featured_first' => esc_html__( 'Show Featured Listings on Top', 'houzez' ),
            ),
            'default' => 'default'
        ),

        array(
            'id'       => 'agent_listing_pagination',
            'type'     => 'button_set',
            'title'    => esc_html__( 'Pagination', 'houzez' ),
            'desc' => '',
            'default'  => '_loadmore',
            'options' => array(
                '_loadmore' => esc_html__('Load More', 'houzez'), 
                '_infinite' => esc_html__('Infinite Scroll', 'houzez'), 
            ), 
        ),

        array(
            'id'       => 'agent_stats',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Stats', 'houzez' ),
            'desc' => esc_html__('Enable or disable the stats on agent detail page', 'houzez'),
            'default'  => 1,
            'on'       => 'Enabled',
            'off'      => 'Disabled',
        ),
        array(
            'id'       => 'agent_review',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Review & Rating', 'houzez' ),
            'desc' => '',
            'default'  => 1,
            'on'       => 'Enabled',
            'off'      => 'Disabled',
        ),
        array(
            'id'       => 'agent_listings',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Listings', 'houzez' ),
            'desc' => '',
            'default'  => 1,
            'on'       => 'Enabled',
            'off'      => 'Disabled',
        ),
        array(
            'id'       => 'agent_bio',
            'type'     => 'switcher',
            'title'    => esc_html__( 'About Agent', 'houzez' ),
            'desc' => '',
            'default'  => 1,
            'on'       => 'Enabled',
            'off'      => 'Disabled',
        ),
        array(
            'id'       => 'agent_sidebar',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Agent Sidebar', 'houzez' ),
            'desc' => '',
            'default'  => 1,
            'on'       => 'Enabled',
            'off'      => 'Disabled',
        ),
        array(
            'id'       => 'agent_sidebar_map',
            'type'     => 'switcher',
            'title'    => esc_html__( 'Agent Map', 'houzez' ),
            'desc' => '',
            'default'  => 1,
            'on'       => 'Enabled',
            'off'      => 'Disabled',
        ),
    )
));