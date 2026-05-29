<?php
function estatesite_houzez_agent_info_shortcode($atts) {
    // Extract shortcode attributes
    $atts = shortcode_atts(
        array(
            'id' => get_the_ID(), // Default to current post ID if no ID is provided
        ),
        $atts,
        'estatesite_houzez_agent_info'
    );
    $post_id = $atts['id'];

    // Get agent information
    $agent_id = get_post_meta($post_id, 'fave_agents', true);

    if (!$agent_id) {
        return "Debug Info: $debug_info\nNo agent associated with this post.";
    }

    $agent_name = get_the_title($agent_id);
    $agent_phone = get_post_meta($agent_id, 'fave_agent_mobile', true);
    $agent_email = get_post_meta($agent_id, 'fave_agent_email', true);
    $agent_picture = get_the_post_thumbnail_url($agent_id);

    // Generate initials placeholder if no picture is available
    if (!$agent_picture) {
        # Set a placeholder?
        #$agent_picture = '';
    }

    // Build the HTML output
    $output = '<div class="houzez-agent-info">';
    if ($agent_picture) {
        $output .= '<img style="max-width:100%;" src="' . $agent_picture . '" alt="' . esc_attr($agent_name) . '" class="agent-picture">';
    } else {
        # Think of something if we want a placeholder image
    }
    $output .= '<h3 class="agent-name" style="margin-bottom:10px;margin-top:10px;">' . esc_html($agent_name) . '</h3>';
    if ($agent_phone) {
        $output .= '<p class="agent-phone"><i class="houzez-icon icon-phone mr-1"></i> <a href="tel:'.esc_html($agent_phone).'">' . esc_html($agent_phone) . '</a></p>';
    }
    if ($agent_email) {
        $output .= '<p style="word-break: break-all;" class="agent-email"><i class="houzez-icon icon-envelope mr-1"></i> <a href="mailto:' . esc_attr($agent_email) . '">' . esc_html($agent_email) . '</a></p>';
    }
    $output .= '</div>';

    return $output;
}

add_shortcode('estatesite_houzez_agent_info', 'estatesite_houzez_agent_info_shortcode');