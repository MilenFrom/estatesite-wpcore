<?php
/**
 * Custom post type + taxonomy registration.
 *
 * In legacy_fave mode, the existing Houzez companion plugins already register
 * `property`, `houzez_agent`, etc. We DO NOT re-register them — that would
 * conflict. We only register what's missing.
 *
 * In native_esc mode (fresh install or migrated), we register everything
 * ourselves with EstateSite-prefixed names where appropriate.
 *
 * @package EstateSite\Core
 */

namespace EstateSite\Core;

defined( 'ABSPATH' ) || exit;

final class CPT {

	/**
	 * Logical entity → [legacy_name, native_name].
	 *
	 * Property + project + partners + testimonials keep bare names in BOTH modes
	 * because renaming would break EA Sync, estate-site-blocks, every search
	 * form, every REST consumer. Only houzez-branded CPTs get rebranded.
	 */
	private static $cpts = [
		'property'      => [ 'property',               'property' ],
		'agent'         => [ 'houzez_agent',           'es_agent' ],
		'agency'        => [ 'houzez_agency',          'es_agency' ],
		'invoice'       => [ 'houzez_invoices',        'es_invoices' ],
		'membership'    => [ 'houzez_membership',      'es_membership' ],
		'reviews'       => [ 'houzez_reviews',         'es_reviews' ],
		'user_packages' => [ 'houzez_user_packages',   'es_user_packages' ],
		'project'       => [ 'project',                'project' ],
		'partner'       => [ 'partners',               'partners' ],
		'testimonial'   => [ 'testimonials',           'testimonials' ],
	];

	/**
	 * Get the active post-type name for a logical entity.
	 */
	public static function name( string $logical ): string {
		if ( ! isset( self::$cpts[ $logical ] ) ) {
			return $logical;
		}
		return Compat_Mode::is_native()
			? self::$cpts[ $logical ][1]
			: self::$cpts[ $logical ][0];
	}

	/**
	 * Wire into init. Only registers what's not already registered.
	 */
	public static function register(): void {
		// Property — only register if Houzez companion isn't doing it.
		if ( ! post_type_exists( self::name( 'property' ) ) ) {
			self::register_property();
		}
		if ( ! post_type_exists( self::name( 'agent' ) ) ) {
			self::register_agent();
		}
		if ( ! post_type_exists( self::name( 'agency' ) ) ) {
			self::register_agency();
		}

		self::register_taxonomies();
	}

	private static function register_property(): void {
		register_post_type( self::name( 'property' ), [
			'labels'       => self::labels(
				__( 'Property', 'estatesite-wpcore' ),
				__( 'Properties', 'estatesite-wpcore' )
			),
			'public'       => true,
			'has_archive'  => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-admin-home',
			'menu_position'=> 4,
			'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'author', 'comments' ],
			'rewrite'      => [ 'slug' => apply_filters( 'estatesite_property_slug', 'property' ) ],
			'taxonomies'   => [
				'property_type', 'property_status', 'property_city',
				'property_area', 'property_country', 'property_state',
				'property_label', 'property_feature',
			],
		] );
	}

	private static function register_agent(): void {
		register_post_type( self::name( 'agent' ), [
			'labels'       => self::labels(
				__( 'Agent', 'estatesite-wpcore' ),
				__( 'Agents', 'estatesite-wpcore' )
			),
			'public'       => true,
			'has_archive'  => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-businessperson',
			'supports'     => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
			'rewrite'      => [ 'slug' => apply_filters( 'estatesite_agent_slug', 'agent' ) ],
		] );
	}

	private static function register_agency(): void {
		register_post_type( self::name( 'agency' ), [
			'labels'       => self::labels(
				__( 'Agency', 'estatesite-wpcore' ),
				__( 'Agencies', 'estatesite-wpcore' )
			),
			'public'       => true,
			'has_archive'  => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-building',
			'supports'     => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
			'rewrite'      => [ 'slug' => apply_filters( 'estatesite_agency_slug', 'agency' ) ],
		] );
	}

	private static function register_taxonomies(): void {
		$property_pt = self::name( 'property' );

		$hier_args = [
			'hierarchical'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'show_ui'           => true,
			'public'            => true,
		];
		$flat_args = array_merge( $hier_args, [ 'hierarchical' => false ] );

		$taxonomies = [
			'property_type'    => [ __( 'Type', 'estatesite-wpcore' ),    'types',         $hier_args ],
			'property_status'  => [ __( 'Status', 'estatesite-wpcore' ),  'statuses',      $hier_args ],
			'property_city'    => [ __( 'City', 'estatesite-wpcore' ),    'cities',        $hier_args ],
			'property_area'    => [ __( 'Area', 'estatesite-wpcore' ),    'areas',         $hier_args ],
			'property_country' => [ __( 'Country', 'estatesite-wpcore' ), 'countries',     $hier_args ],
			'property_state'   => [ __( 'State', 'estatesite-wpcore' ),   'states',        $hier_args ],
			'property_label'   => [ __( 'Label', 'estatesite-wpcore' ),   'labels',        $flat_args ],
			'property_feature' => [ __( 'Feature', 'estatesite-wpcore' ), 'features',      $flat_args ],
		];

		foreach ( $taxonomies as $slug => [ $singular, $rewrite_slug, $args ] ) {
			if ( taxonomy_exists( $slug ) ) {
				continue; // Don't double-register if Houzez companion handles it.
			}
			$args['labels']  = self::tax_labels( $singular );
			$args['rewrite'] = [ 'slug' => apply_filters( "estatesite_{$slug}_slug", $rewrite_slug ) ];
			register_taxonomy( $slug, $property_pt, $args );
		}
	}

	private static function labels( string $singular, string $plural ): array {
		return [
			'name'               => $plural,
			'singular_name'      => $singular,
			'menu_name'          => $plural,
			'add_new'            => sprintf( __( 'Add %s', 'estatesite-wpcore' ),       $singular ),
			'add_new_item'       => sprintf( __( 'Add New %s', 'estatesite-wpcore' ),   $singular ),
			'edit_item'          => sprintf( __( 'Edit %s', 'estatesite-wpcore' ),      $singular ),
			'new_item'           => sprintf( __( 'New %s', 'estatesite-wpcore' ),       $singular ),
			'view_item'          => sprintf( __( 'View %s', 'estatesite-wpcore' ),      $singular ),
			'all_items'          => sprintf( __( 'All %s', 'estatesite-wpcore' ),       $plural ),
			'search_items'       => sprintf( __( 'Search %s', 'estatesite-wpcore' ),    $plural ),
			'not_found'          => sprintf( __( 'No %s found.', 'estatesite-wpcore' ), strtolower( $plural ) ),
			'not_found_in_trash' => sprintf( __( 'No %s in trash.', 'estatesite-wpcore' ), strtolower( $plural ) ),
		];
	}

	private static function tax_labels( string $singular ): array {
		return [
			'name'          => $singular,
			'singular_name' => $singular,
			'all_items'     => sprintf( __( 'All %s', 'estatesite-wpcore' ),     $singular ),
			'edit_item'     => sprintf( __( 'Edit %s', 'estatesite-wpcore' ),    $singular ),
			'add_new_item'  => sprintf( __( 'Add New %s', 'estatesite-wpcore' ), $singular ),
			'search_items'  => sprintf( __( 'Search %s', 'estatesite-wpcore' ),  $singular ),
		];
	}
}
