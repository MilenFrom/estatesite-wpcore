<?php
/**
 * Auto-ported from Houzez framework/functions/ to EstateSite Core.
 * Direct fave_* meta access has been rewritten to use \EstateSite\Core\Property::get/set.
 *
 * @package EstateSite\Core\Functions
 */

defined( 'ABSPATH' ) || exit;

/*-----------------------------------------------------------------------------------*/
/*	Space
/*-----------------------------------------------------------------------------------*/
function houzez_space_shortcode( $atts, $content = null ) {
	extract( shortcode_atts( array(
		'height' => '50'
    ), $atts ) );
   return '<div style="clear:both; width:100%; height:'.$height.'px"></div>';
}
add_shortcode( 'houzez-space', 'houzez_space_shortcode' );
?>