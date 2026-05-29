<?php

add_shortcode('estatesite-houzez-popular-property-types', function(){
    global $wpdb;

    $output = '';

    $property_types_qry = "
        SELECT t.*, tt.*, COUNT(tr.object_id) as count
        FROM {$wpdb->terms} AS t 
        INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
        INNER JOIN {$wpdb->term_relationships} AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
        INNER JOIN {$wpdb->posts} AS p ON tr.object_id = p.ID
        WHERE tt.taxonomy = 'property_type' AND p.post_type = 'property' AND p.post_status = 'publish'
        GROUP BY t.term_id
        ORDER BY count DESC
        LIMIT 5;";

    $popular_property_types = $wpdb->get_results($property_types_qry);
    $popular_property_types_links = [];

    $output .= '<div class="widget-body"><ul class="children">';
    foreach($popular_property_types as $popular_property_type){
        $the_term_link = get_term_link($popular_property_type, 'property_type');
        $popular_property_types_links[] = '<li><a href="'.$the_term_link.'">'.$popular_property_type->name.'</a></li>';
    }
    $output .= implode('',$popular_property_types_links);
    $output .= '</ul></div>';
    
    return $output;
});