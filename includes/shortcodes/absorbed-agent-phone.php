<?php
/**
 * Agent Phone Shortcode
 *
 * Provides [property_agent_phone] shortcode to display agent mobile phone
 * from the related houzez_agent post when viewing a property.
 *
 * @package EstateSiteHouzez
 * @since 1.3.13
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Property Agent Phone Shortcode
 *
 * Usage:
 * [property_agent_phone] - Basic usage, returns raw phone number
 * [property_agent_phone format="formatted"] - Returns formatted phone (0888 123 456)
 * [property_agent_phone format="link"] - Returns clickable tel: link
 * [property_agent_phone prefix="Tel: "] - Adds prefix before phone
 * [property_agent_phone suffix=" (mobile)"] - Adds suffix after phone
 * [property_agent_phone fallback="No phone available"] - Text if no phone found
 * [property_agent_phone agent_index="1"] - Get second agent's phone (0-indexed)
 *
 * @param array $atts Shortcode attributes
 * @return string Agent phone number or fallback text
 */
function estatesite_property_agent_phone_shortcode($atts) {
    // Parse shortcode attributes
    $atts = shortcode_atts(array(
        'property_id' => get_the_ID(),
        'agent_index' => 0,      // Which agent if multiple (0 = first)
        'format'      => 'raw',  // raw, link, or formatted
        'prefix'      => '',     // Text before phone
        'suffix'      => '',     // Text after phone
        'fallback'    => '',     // Text if no phone found
    ), $atts, 'property_agent_phone');

    $property_id = intval($atts['property_id']);

    // Validate we have a property
    if (!$property_id || get_post_type($property_id) !== 'property') {
        return esc_html($atts['fallback']);
    }

    // Get agent phone
    $phone = estatesite_get_property_agent_phone($property_id, intval($atts['agent_index']));

    if (empty($phone)) {
        return esc_html($atts['fallback']);
    }

    // Format the output
    $formatted_phone = estatesite_format_agent_phone($phone, $atts['format']);
    $output = esc_html($atts['prefix']) . $formatted_phone . esc_html($atts['suffix']);

    return $output;
}
add_shortcode('property_agent_phone', 'estatesite_property_agent_phone_shortcode');

/**
 * Get agent phone number for a property
 *
 * @param int $property_id Property post ID
 * @param int $agent_index Which agent to get (for properties with multiple agents)
 * @return string Agent phone number or empty string
 */
function estatesite_get_property_agent_phone($property_id, $agent_index = 0) {
    // Get the agent ID(s) associated with this property
    $agent_ids = get_post_meta($property_id, 'fave_agents', true);

    if (empty($agent_ids)) {
        return '';
    }

    // Handle both single value and array
    if (!is_array($agent_ids)) {
        $agent_ids = array($agent_ids);
    }

    // Get the requested agent (default to first)
    if (!isset($agent_ids[$agent_index])) {
        return '';
    }

    $agent_id = intval($agent_ids[$agent_index]);

    // Validate agent exists and is correct post type
    if (!$agent_id || get_post_type($agent_id) !== 'houzez_agent') {
        return '';
    }

    // Get the agent's mobile number
    $agent_mobile = get_post_meta($agent_id, 'fave_agent_mobile', true);

    return sanitize_text_field($agent_mobile);
}

/**
 * Format phone number based on format parameter
 *
 * @param string $phone Phone number
 * @param string $format Format type (raw, link, formatted)
 * @return string Formatted phone number
 */
function estatesite_format_agent_phone($phone, $format) {
    switch ($format) {
        case 'link':
            // Create tel: link
            $clean_phone = preg_replace('/[^0-9+]/', '', $phone);
            return '<a href="tel:' . esc_attr($clean_phone) . '">' . esc_html($phone) . '</a>';

        case 'formatted':
            // Format phone nicely (Bulgarian format: 0888 123 456)
            $formatted = preg_replace('/(\d{4})(\d{3})(\d{3})/', '$1 $2 $3', $phone);
            return esc_html($formatted);

        case 'raw':
        default:
            return esc_html($phone);
    }
}

/**
 * Get all agents data for a property (helper function for advanced usage)
 *
 * @param int $property_id Property post ID
 * @return array Array of agent data including id, name, mobile, phone, email, whatsapp
 */
function estatesite_get_property_agents($property_id) {
    $agent_ids = get_post_meta($property_id, 'fave_agents', true);

    if (empty($agent_ids)) {
        return array();
    }

    if (!is_array($agent_ids)) {
        $agent_ids = array($agent_ids);
    }

    $agents = array();
    foreach ($agent_ids as $agent_id) {
        $agent_id = intval($agent_id);
        if ($agent_id && get_post_type($agent_id) === 'houzez_agent') {
            $agents[] = array(
                'id'       => $agent_id,
                'name'     => get_the_title($agent_id),
                'mobile'   => get_post_meta($agent_id, 'fave_agent_mobile', true),
                'phone'    => get_post_meta($agent_id, 'fave_agent_office_num', true),
                'email'    => get_post_meta($agent_id, 'fave_agent_email', true),
                'whatsapp' => get_post_meta($agent_id, 'fave_agent_whatsapp', true),
            );
        }
    }

    return $agents;
}
