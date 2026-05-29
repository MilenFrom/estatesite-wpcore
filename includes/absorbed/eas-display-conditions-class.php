<?php
/**
 * Elementor Pro display condition: post content is empty / not empty.
 * Loaded lazily by eas-display-conditions.php — DO NOT include directly.
 */

namespace EstateSiteHouzez\DisplayConditions;

use Elementor\Controls_Manager;
use ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class EAS_Post_Content_Condition extends Condition_Base {

	public function get_name()   { return 'eas_post_content'; }
	public function get_label()  { return __( 'EAS Post Content', 'estatesite-wpcore' ); }
	public function get_group()  { return 'other'; }
	public static function get_priority() { return 99; }

	public function get_options() {
		$this->add_control(
			'comparator',
			[
				'label'   => __( 'Condition', 'estatesite-wpcore' ),
				'type'    => Controls_Manager::SELECT,
				'options' => [
					'empty'     => __( 'Is empty',     'estatesite-wpcore' ),
					'not_empty' => __( 'Is not empty', 'estatesite-wpcore' ),
				],
				'default' => 'not_empty',
			]
		);
	}

	public function check( $args ): bool {
		$post = get_post();
		$content = $post ? trim( wp_strip_all_tags( $post->post_content ) ) : '';
		$is_empty = ( $content === '' );

		return ( 'empty' === $args['comparator'] ) ? $is_empty : ! $is_empty;
	}
}
