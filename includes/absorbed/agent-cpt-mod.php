<?php

add_filter('register_post_type_args', 'make_post_type_public', 10, 2);
function make_post_type_public($args, $post_type) {
	if ('houzez_agent' === $post_type || 'houzez_agency' === $post_type || 'property' === $post_type) {
		$args['public'] = true;
		$args['show_ui'] = true;
		$args['show_in_rest'] = true;
		$args['show_in_nav_menus'] = true;
		$args['show_in_menu'] = true;
		$args['show_in_admin_bar'] = true;
		$args['has_archive'] = true;
	}
	return $args;
}