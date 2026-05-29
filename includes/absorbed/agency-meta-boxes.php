<?php

// Register the meta box for houzez_agency posts.
function register_eas_agency_token_meta_box() {
    add_meta_box(
        'eas_agency_token_meta_box',          // ID of the meta box
        'Estate Assistant Settings',                      // Title of the meta box
        'eas_agency_token_meta_box_callback', // Callback function
        'houzez_agency',                     // Screen (post type)
        'normal',                            // Context
        'default'                            // Priority
    );
}
add_action( 'add_meta_boxes', 'register_eas_agency_token_meta_box' );

// Callback to render the meta box content.
function eas_agency_token_meta_box_callback( $post ) {
    // Add a nonce field so we can check for it later.
    wp_nonce_field( 'save_eas_agency_token_data', 'eas_agency_token_nonce' );

    // Retrieve existing value from post meta.
    $value = get_post_meta( $post->ID, 'eas_agency_token', true );

    // Display the form, using the current value.
    echo '<p>';
    echo '<label for="eas_agency_token_field">Token:</label> ';
    echo '<input type="text" id="eas_agency_token_field" name="eas_agency_token_field" value="' . esc_attr( $value ) . '" size="25" />';
    echo '</p>';
    
    // Add property code prefix field
    $prefix_value = get_post_meta( $post->ID, 'agency_property_code_prefix', true );
    echo '<p>';
    echo '<label for="agency_property_code_prefix_field">Property Code Prefix:</label> ';
    echo '<input type="text" id="agency_property_code_prefix_field" name="agency_property_code_prefix_field" value="' . esc_attr( $prefix_value ) . '" size="25" />';
    echo '<br><small>This prefix will be prepended to property codes (e.g., ABC-12345)</small>';
    echo '</p>';
}

// Save the meta box's data.
function save_eas_agency_token_data( $post_id ) {
    // Check if our nonce is set.
    if ( ! isset( $_POST['eas_agency_token_nonce'] ) ) {
        return;
    }

    // Verify that the nonce is valid.
    if ( ! wp_verify_nonce( $_POST['eas_agency_token_nonce'], 'save_eas_agency_token_data' ) ) {
        return;
    }

    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Check the user's permissions.
    if ( isset( $_POST['post_type'] ) && 'houzez_agency' == $_POST['post_type'] ) {
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
    }

    // Make sure that it is set.
    if ( ! isset( $_POST['eas_agency_token_field'] ) ) {
        return;
    }

    // Sanitize user input.
    $agency_token = sanitize_text_field( $_POST['eas_agency_token_field'] );

    // Update the meta field in the database.
    update_post_meta( $post_id, 'eas_agency_token', $agency_token );
    
    // Save property code prefix if it's set
    if ( isset( $_POST['agency_property_code_prefix_field'] ) ) {
        $property_code_prefix = sanitize_text_field( $_POST['agency_property_code_prefix_field'] );
        update_post_meta( $post_id, 'agency_property_code_prefix', $property_code_prefix );
    }
}
add_action( 'save_post', 'save_eas_agency_token_data' );