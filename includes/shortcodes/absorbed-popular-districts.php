<?php

add_shortcode('estatesite-houzez-popular-districts', function(){
    global $wpdb;

    $output = '';

    $districts_qry = "
        SELECT t.*, tt.*, COUNT(tr.object_id) as count
        FROM {$wpdb->terms} AS t 
        INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
        INNER JOIN {$wpdb->term_relationships} AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
        INNER JOIN {$wpdb->posts} AS p ON tr.object_id = p.ID
        WHERE tt.taxonomy = 'property_area' AND p.post_type = 'property' AND p.post_status = 'publish'
        GROUP BY t.term_id
        ORDER BY count DESC
        LIMIT 5;";

    $popular_terms = $wpdb->get_results($districts_qry);
    $popular_terms_links = [];

    $output .= '<div class="widget-body"><ul class="children">';
    foreach($popular_terms as $popular_term){
        $the_term_link = get_term_link($popular_term, 'property_area');
        $popular_terms_links[] = '<li><a href="'.$the_term_link.'">'.$popular_term->name.'</a></li>';
    }
    $output .= implode('',$popular_terms_links);
    $output .= '</ul></div>';
    
    return $output;
});