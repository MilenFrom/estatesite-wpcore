<?php

/**
 * Add a custom "Update Property" action link to the row actions
 * for posts of type 'property'.
 */
function my_add_update_property_row_action( $actions, $post ) {
    // Only add this action for the 'property' post type (adjust to your CPT slug).
    if ( 'property' === $post->post_type ) {

        // Create a nonce for security
        $nonce = wp_create_nonce( 'update_property_nonce' );

        // Add the link to the existing row actions with data attributes for AJAX
        $actions['update_property'] = sprintf(
            '<a href="#" class="eas-update-property" data-property-id="%d" data-nonce="%s">%s</a>',
            $post->ID,
            esc_attr( $nonce ),
            __( 'Update Property', 'estatesite-houzez' )
        );
    }

    return $actions;
}
add_filter( 'page_row_actions', 'my_add_update_property_row_action', 10, 2 );

/**
 * Enqueue AJAX scripts for property update
 */
function eas_enqueue_property_update_scripts( $hook ) {
    // Only load on properties listing page
    if ( 'edit.php' !== $hook || ! isset( $_GET['post_type'] ) || $_GET['post_type'] !== 'property' ) {
        return;
    }

    // Enqueue script
    wp_enqueue_script(
        'eas-property-update',
        plugin_dir_url( __FILE__ ) . 'js/property-update.js',
        array( 'jquery' ),
        '1.3.15',
        true
    );

    // Localize script with AJAX URL and translations
    wp_localize_script(
        'eas-property-update',
        'easPropertyUpdate',
        array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'updating' => __( 'Updating...', 'estatesite-houzez' ),
            'success' => __( 'Property updated successfully!', 'estatesite-houzez' ),
            'error' => __( 'Update failed', 'estatesite-houzez' ),
        )
    );

    // Add inline styles
    wp_add_inline_style( 'wp-admin', '
        .eas-update-property.updating {
            opacity: 0.5;
            pointer-events: none;
        }
        .eas-update-property.updating::after {
            content: " ⟳";
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .eas-property-notice {
            padding: 10px 15px;
            margin: 10px 0;
            border-left: 4px solid;
            background: #fff;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
        }
        .eas-property-notice.success {
            border-left-color: #46b450;
            background: #ecf7ed;
        }
        .eas-property-notice.error {
            border-left-color: #dc3232;
            background: #f9e9e9;
        }
    ' );
}
add_action( 'admin_enqueue_scripts', 'eas_enqueue_property_update_scripts' );

/**
 * AJAX handler for property update
 */
function eas_ajax_update_property() {
    // Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'update_property_nonce' ) ) {
        wp_send_json_error( array(
            'message' => __( 'Security check failed. Please refresh the page and try again.', 'estatesite-houzez' )
        ) );
    }

    // Check user permissions
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array(
            'message' => __( 'You do not have permission to perform this action.', 'estatesite-houzez' )
        ) );
    }

    // Get property ID
    $property_id = isset( $_POST['property_id'] ) ? absint( $_POST['property_id'] ) : 0;

    if ( ! $property_id ) {
        wp_send_json_error( array(
            'message' => __( 'Invalid property ID.', 'estatesite-houzez' )
        ) );
    }

    // Verify property exists and is correct post type
    $property = get_post( $property_id );
    if ( ! $property || $property->post_type !== 'property' ) {
        wp_send_json_error( array(
            'message' => __( 'Property not found.', 'estatesite-houzez' )
        ) );
    }

    // Get EA UID and check if this is a synced property
    $ea_uid = get_post_meta( $property_id, 'ea_uid', true );
    $sync_source = get_post_meta( $property_id, 'sync_source', true );

    if ( empty( $ea_uid ) || $sync_source !== 'estateassistant' ) {
        wp_send_json_error( array(
            'message' => __( 'This property is not synced from EstateAssistant and cannot be updated via API.', 'estatesite-houzez' ),
            'debug' => array(
                'ea_uid' => $ea_uid,
                'sync_source' => $sync_source
            )
        ) );
    }

    // Get agency token - try multiple methods
    $agency_token = '';
    $token_source = '';

    // Method 1: Try from property meta (eas_agency_token)
    $agency_token = get_post_meta( $property_id, 'eas_agency_token', true );
    if ( ! empty( $agency_token ) ) {
        $token_source = 'property_meta';
    }

    // Method 2: Try from agent's agency
    if ( empty( $agency_token ) ) {
        $agent_ids = get_post_meta( $property_id, 'fave_agents', true );
        if ( ! empty( $agent_ids ) ) {
            $agent_id_array = explode( ',', $agent_ids );
            $first_agent_id = absint( $agent_id_array[0] );
            if ( $first_agent_id ) {
                $agency_ids = get_post_meta( $first_agent_id, 'fave_agent_agencies', true );
                if ( ! empty( $agency_ids ) ) {
                    $agency_id_array = explode( ',', $agency_ids );
                    $agency_id = absint( $agency_id_array[0] );
                    if ( $agency_id ) {
                        $agency_token = get_post_meta( $agency_id, 'eas_agency_token', true );
                        if ( ! empty( $agency_token ) ) {
                            $token_source = 'agent_agency';
                        }
                    }
                }
            }
        }
    }

    // Method 3: Use global token from settings
    if ( empty( $agency_token ) ) {
        $agency_token = get_option( 'estate_assistant_token', '' );
        if ( ! empty( $agency_token ) ) {
            $token_source = 'global_settings';
        }
    }

    if ( empty( $agency_token ) ) {
        wp_send_json_error( array(
            'message' => __( 'Could not find EstateAssistant API token. Please check your sync settings.', 'estatesite-houzez' ),
            'debug' => array(
                'property_id' => $property_id,
                'ea_uid' => $ea_uid,
                'checked_methods' => array('property_meta', 'agent_agency', 'global_settings')
            )
        ) );
    }

    // Use the WordPress REST API endpoint (same as EstateAssistant Sync plugin)
    // This internally handles the API call and returns processed data
    $request = new WP_REST_Request( 'GET', '/estateassistant/v1/get-estate/' );
    $request->set_param( 'estateid', $ea_uid );
    $request->set_param( 'token', $agency_token );
    $request->set_param( 'rt', 'internal' );

    // Log request for debugging
    error_log( '[EAS Update Property] Calling REST API endpoint for property: ' . $ea_uid );

    // Call the handler function directly (defined in ea-sync.php)
    if ( ! function_exists( 'handle_estate' ) ) {
        wp_send_json_error( array(
            'message' => __( 'EstateAssistant Sync plugin handler not found. Please ensure the plugin is active.', 'estatesite-houzez' ),
            'debug' => array(
                'missing_function' => 'handle_estate'
            )
        ) );
    }

    // Execute the update
    $response = handle_estate( $request );

    // Check if response is error
    if ( is_wp_error( $response ) ) {
        error_log( '[EAS Update Property] Error: ' . $response->get_error_message() );
        wp_send_json_error( array(
            'message' => sprintf(
                __( 'Failed to update property: %s', 'estatesite-houzez' ),
                $response->get_error_message()
            ),
            'debug' => array(
                'error' => $response->get_error_message(),
                'ea_uid' => $ea_uid,
                'token_source' => $token_source
            )
        ) );
    }

    // Check response data
    if ( ! $response || ! is_array( $response ) ) {
        error_log( '[EAS Update Property] Invalid response structure' );
        wp_send_json_error( array(
            'message' => __( 'Invalid response from update handler.', 'estatesite-houzez' ),
            'debug' => array(
                'response_type' => gettype( $response ),
                'response_data' => is_scalar( $response ) ? $response : 'complex_type'
            )
        ) );
    }

    // Get property code for success message
    $property_code = get_post_meta( $property_id, 'fave_property_id', true );

    error_log( '[EAS Update Property] Success for property ID: ' . $property_id );
    error_log( '[EAS Update Property] Response: ' . print_r( $response, true ) );

    // Return success response
    wp_send_json_success( array(
        'message' => sprintf(
            __( 'Property <strong>%s</strong> (ID: %d) has been successfully updated from EstateAssistant API.', 'estatesite-houzez' ),
            esc_html( $property_code ),
            $property_id
        ),
        'property_id' => $property_id,
        'property_code' => $property_code,
        'token_source' => $token_source,
        'handler_response' => $response
    ) );
}
add_action( 'wp_ajax_eas_update_single_property', 'eas_ajax_update_property' );
