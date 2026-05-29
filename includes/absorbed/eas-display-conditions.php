<?php
/**
 * Condition: Post content is empty / is not empty
 * File: eas-display-conditions.php
 *
 * Defers class declaration + registration until Elementor Pro's
 * Condition_Base parent class is actually loaded, so we don't fatal
 * when Pro is inactive or hasn't initialized its display-conditions
 * module yet.
 *
 * Absorbed from the former `estatesite-houzez` plugin during the v1 fork.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Defer the class load + registration to Pro's DC register hook. The class
// definition lives in its own file so the parser doesn't choke when
// Condition_Base isn't loaded yet — we only `require_once` it inside the
// hook callback, by which time Pro has loaded its base classes.
add_action( 'elementor-pro/display-conditions/register', static function ( $conditions_manager ) {
    if ( ! class_exists( '\ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base' ) ) {
        return;
    }

    require_once __DIR__ . '/eas-display-conditions-class.php';

    if ( method_exists( $conditions_manager, 'register_condition_instance' ) ) {
        $conditions_manager->register_condition_instance( new \EstateSiteHouzez\DisplayConditions\EAS_Post_Content_Condition() );
    } elseif ( method_exists( $conditions_manager, 'register' ) ) {
        $conditions_manager->register( new \EstateSiteHouzez\DisplayConditions\EAS_Post_Content_Condition() );
    }
} );
