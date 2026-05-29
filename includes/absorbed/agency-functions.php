<?php

/**
 * Register the sidebar meta box for agency actions.
 */
function register_ea_agency_sidebar_actions_meta_box() {
    add_meta_box(
        'ea_agency_sidebar_actions_meta_box',    // ID of the meta box
        'Agency Actions',                        // Title of the meta box
        'ea_agency_sidebar_actions_meta_box_callback', // Callback function
        'houzez_agency',                         // Screen (post type)
        'side',                                  // Context
        'default'                                // Priority
    );
}
add_action( 'add_meta_boxes', 'register_ea_agency_sidebar_actions_meta_box' );

/**
 * Callback to render the sidebar meta box content.
 */
function ea_agency_sidebar_actions_meta_box_callback( $post ) {
    $agency_token = get_post_meta( $post->ID, 'eas_agency_token', true );

    // Get agency stats
    $agency_wp_id = $post->ID;

    // Count brokers/agents for this agency
    $brokers_count = 0;
    $brokers_args = array(
        'post_type' => 'houzez_agent',
        'posts_per_page' => -1,
        'post_status' => array('publish', 'draft', 'pending'),
        'meta_query' => array(
            array(
                'key' => 'eas_agency_token',
                'value' => $agency_token,
                'compare' => '='
            )
        ),
        'fields' => 'ids'
    );
    $brokers_query = new WP_Query($brokers_args);
    $brokers_count = $brokers_query->found_posts;
    wp_reset_postdata();

    // Count properties for this agency
    $properties_count = 0;
    $properties_args = array(
        'post_type' => 'property',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'meta_query' => array(
            array(
                'key' => 'eas_agency_wp_id',
                'value' => $agency_wp_id,
                'compare' => '='
            )
        ),
        'fields' => 'ids'
    );
    $properties_query = new WP_Query($properties_args);
    $properties_count = $properties_query->found_posts;
    wp_reset_postdata();

    ?>
    <div style="display:flex;flex-direction:column;gap:15px;margin-top:15px;">
        <button type="button" data-agency-wp-id="<?php echo $post->ID; ?>" data-agency-token="<?php echo $agency_token; ?>" id="eas_import_brokers" name="eas_import_brokers" class="button button-hero">Import all brokers</button>
        <button type="button" data-agency-wp-id="<?php echo $post->ID; ?>" data-agency-token="<?php echo $agency_token; ?>" id="eas_import_properties" name="eas_import_properties" class="button button-hero">Import all properties</button>
        <button type="button" data-agency-wp-id="<?php echo $post->ID; ?>" data-agency-token="<?php echo $agency_token; ?>" id="eas_update_existing_properties" name="eas_update_existing_properties" class="button button-hero">Update existing properties</button>
        <button type="button" data-agency-wp-id="<?php echo $post->ID; ?>" data-agency-token="<?php echo $agency_token; ?>" id="eas_handle_archived_offers" name="eas_handle_archived_offers" class="button button-hero">Handle archived offers</button>
        <button type="button" data-agency-wp-id="<?php echo $post->ID; ?>" data-agency-token="<?php echo $agency_token; ?>" id="eas_delete_all" name="eas_delete_all" class="button button-hero">Delete all offers + images</button>

        <!-- Agency Stats -->
        <div style="margin-top: 10px; padding: 15px; background: #f6f7f7; border-radius: 4px; border-left: 4px solid #2271b1;">
            <h4 style="margin: 0 0 10px 0; font-size: 13px; color: #1d2327;">Agency Statistics</h4>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 13px; color: #50575e;">
                        <span class="dashicons dashicons-groups" style="font-size: 16px; vertical-align: middle; color: #2271b1;"></span>
                        Brokers:
                    </span>
                    <strong style="font-size: 14px; color: #1d2327;"><?php echo number_format($brokers_count); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 13px; color: #50575e;">
                        <span class="dashicons dashicons-admin-multisite" style="font-size: 16px; vertical-align: middle; color: #2271b1;"></span>
                        Properties:
                    </span>
                    <strong style="font-size: 14px; color: #1d2327;"><?php echo number_format($properties_count); ?></strong>
                </div>
            </div>
        </div>
    </div>
    <?php
}