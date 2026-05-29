<?php
/**
 * Plugin bootstrap singleton.
 *
 * @package EstateSite\Core
 */

namespace EstateSite\Core;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/** @var string Active compat mode (resolved on boot). */
	public $compat_mode = '';

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	private function __construct() {}

	private function boot(): void {
		// Resolve compat mode early — most subsystems branch on it.
		$this->compat_mode = Compat_Mode::get();

		add_action( 'init', [ $this, 'load_textdomain' ], 1 );

		// CPT + taxonomy registration. Priority 5 to land before themes/plugins
		// at default priority that might look for the post types.
		add_action( 'init', [ CPT::class, 'register' ], 5 );

		// Compat shim — only active in legacy_fave or migrating modes.
		if ( Compat_Mode::is_legacy() || Compat_Mode::is_migrating() ) {
			Compat\Houzez_Compat::register();
		}

		// Nomenclatures: register cron + provides dropdown data for theme/widgets.
		Nomenclatures::register();

		// Note: Template catalog (Templates class) lives in estatesite-wpelementor
		// since templates ARE Elementor element trees and only make sense when
		// Elementor is loaded. Wiring is in that package's Plugin::boot().

		// Helper function files — pull in ported procedural helpers.
		// Loaded outside is_admin() so front-end templates can call them.
		$this->load_helpers();

		// Metabox registrations — Meta Box plugin is bundled and already
		// initialized via lib/meta-box/meta-box.php. Loading our metabox
		// files registers them via rwmb_meta_boxes filter.
		if ( is_admin() ) {
			$this->load_metaboxes();
		}

		// Core ported classes (search, submit, verification, query, data-source).
		// Each self-instantiates via static init() or hook registration at file end.
		$this->load_core_classes();

		// Login / register / social-auth / roles / user-approval — ported from
		// the former `houzez-login-register` plugin. Self-registers hooks at
		// file scope. Roles are registered here on every load (idempotent in
		// WP — add_role is a no-op if the role exists).
		$this->load_login_register();

		// Absorbed functionality from the former `estatesite-houzez` plugin
		// (sunset per FORK_PLAN §4.1). Each file self-registers via add_action /
		// add_filter at include time. Includes: agency sidebar metabox,
		// agency token metabox, agent/agency CPT visibility filter,
		// Elementor Pro display conditions, property row actions.
		$this->load_absorbed_houzez();

		// Widgets — registered via widgets_init hook (WP requires this timing).
		add_action( 'widgets_init', [ $this, 'load_widgets' ] );

		// Shortcodes — registered immediately, available throughout WP lifecycle.
		$this->load_shortcodes();

		// Admin shell (placeholder menu).
		if ( is_admin() ) {
			new Admin\Admin();
		}

		// WP-CLI commands (loaded only under WP-CLI).
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once ESCORE_DIR . 'includes/class-cli.php';
		}

		// CSF options panel — loader.php runs at file scope so option files
		// inherit the global scope properly. Hooked at csf_loaded so CSF's
		// CSF::createOptions/createSection calls are registered.
		add_action( 'csf_loaded', static function () {
			$loader = ESCORE_DIR . 'includes/options/loader.php';
			if ( is_readable( $loader ) ) {
				// Required from a static closure so variables go to global scope.
				require_once $loader;
			}
		} );

		do_action( 'estatesite_core_loaded', $this );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'estatesite-wpcore',
			false,
			dirname( ESCORE_BASENAME ) . '/languages'
		);
	}

	/**
	 * Load ported procedural helper files.
	 * Each file declares its functions in `function_exists` guards so it's
	 * safe to include alongside any environment.
	 */
	private function load_helpers(): void {
		// Aliases first — helpers call fave_option / houzez_option / etc.
		require_once ESCORE_DIR . 'includes/compat/houzez-function-aliases.php';

		// Load order matters — helpers that others depend on come first.
		// helper_functions.php has many primitives the rest reference.
		$helper_files = [
			'helper_functions.php',
			'price_functions.php',
			'property_functions.php',
			'profile_functions.php',
			'agency_agents.php',
			'taxonomy-helper.php',
			'template-functions.php',
			'menu-walker.php',
			'mobile-menu-walker.php',
			'map-functions.php',
			'blog-functions.php',
			'captcha-functions.php',
			'emails-functions.php',
			'messages_functions.php',
			'membership-functions.php',
			'cron-functions.php',
			'property-expirator.php',
			'property_rating.php',
			'property_schema.php',
			'review.php',
			'stats.php',
			// 'woocommerce.php',  // Houzez Woo overrides — we dropped Woo support, skip
		];

		foreach ( $helper_files as $file ) {
			$path = ESCORE_DIR . 'includes/functions/' . $file;
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}

		// Theme action wiring (ported from houzez/framework/template-hooks.php).
		// MUST load after template-functions.php so the callbacks it registers
		// already exist. Without these add_action() calls, themes that call
		// do_action('houzez_header') / do_action('houzez_footer') render
		// blank chrome.
		require_once ESCORE_DIR . 'includes/template-hooks.php';
	}

	/**
	 * Load metabox declaration files. Registered via Meta Box's
	 * `rwmb_meta_boxes` filter. Files declare metaboxes for property, agent,
	 * agency, taxonomies, pages, etc.
	 */
	private function load_metaboxes(): void {
		$metabox_files = [
			'property-metaboxes.php',
			'property-additional-metaboxes.php',
			'agent-metaboxes.php',
			'agency-metaboxes.php',
			'project-metaboxes.php',
			'packages-metaboxes.php',
			'partner-metaboxes.php',
			'testimonials-metaboxes.php',
			'reviews-metaboxes.php',
			'posts-metaboxes.php',
			'taxonomies-metaboxes.php',
			'header-search-metaboxes.php',
			'transparent-menu-metaboxes.php',
			'listings-templates-metaboxes.php',
			'listings-templates-metaboxes-classic-editor.php',
			'page-header-metaboxes.php',
			'page-header-metaboxes-classic-editor.php',
			'page-template-metaboxes.php',
			// Term-level metaboxes
			'type-meta.php',
			'status-meta.php',
			'label-meta.php',
			'cities-meta.php',
			'area-meta.php',
			'state-meta.php',
		];

		foreach ( $metabox_files as $file ) {
			$path = ESCORE_DIR . 'includes/metaboxes/' . $file;
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * Load ported core classes. Each self-registers its hooks at file end:
	 *   Houzez_Data_Source::init()
	 *   Houzez_Property_Search::init()
	 *   Houzez_Property_Submit::init()
	 *   Houzez_LazyLoad_Images via add_action('init', ...)
	 *   Houzez_Query — methods called from templates, no auto-init
	 *   Houzez_User_Verification — instantiated as $GLOBALS['houzez_user_verification']
	 *
	 * Order matters — data-source + query are foundations the others use.
	 */
	private function load_core_classes(): void {
		$class_files = [
			'class-houzez-data-source.php',
			'class-houzez-query.php',
			'class-houzez-lazy-load.php',
			'class-houzez-property-search.php',
			'class-houzez-property-submit.php',
			'class-houzez-user-verification.php',
		];

		foreach ( $class_files as $file ) {
			$path = ESCORE_DIR . 'includes/classes/' . $file;
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * Load + register classic WordPress widgets. Called on widgets_init.
	 */
	public function load_widgets(): void {
		$widget_files = glob( ESCORE_DIR . 'includes/widgets/*.php' );
		foreach ( $widget_files as $file ) {
			require_once $file;
		}
	}

	/**
	 * Load shortcode declarations. Each file self-registers via add_shortcode().
	 */
	private function load_shortcodes(): void {
		$shortcode_files = glob( ESCORE_DIR . 'includes/shortcodes/*.php' );
		foreach ( $shortcode_files as $file ) {
			require_once $file;
		}
	}

	/**
	 * Load login/register/roles/social-auth/user-approval files.
	 * Ported verbatim from `houzez-login-register/includes/` — functions
	 * use `if ( ! function_exists() )` guards so co-existence with the
	 * legacy plugin (during migration) is safe.
	 *
	 * Order matters:
	 *   1. helper.php — tiny utility
	 *   2. roles.php — role definitions (must be registered before role-functions)
	 *   3. roles-functions.php — capability helpers
	 *   4. class-user-approval.php — user-approval workflow class
	 *   5. login_register.php — the big one (1237 lines): forms, AJAX, redirects
	 *   6. social_login.php — Facebook/Google/Twitter/OpenID auth
	 */
	private function load_login_register(): void {
		$base = ESCORE_DIR . 'includes/login-register/';

		// Constant for social SDK path — referenced by social_login.php.
		if ( ! defined( 'ESCORE_LOGIN_SOCIAL_PATH' ) ) {
			define( 'ESCORE_LOGIN_SOCIAL_PATH', $base . 'social/' );
		}
		// BC alias so any unported code referring to HOUZEZ_LOGIN_SOCIAL_PATH
		// still resolves.
		if ( ! defined( 'HOUZEZ_LOGIN_SOCIAL_PATH' ) ) {
			define( 'HOUZEZ_LOGIN_SOCIAL_PATH', ESCORE_LOGIN_SOCIAL_PATH );
		}

		$files = [
			'helper.php',
			'roles.php',
			'roles-functions.php',
			'class-user-approval.php',
			'login_register.php',
			'social_login.php',
		];
		foreach ( $files as $file ) {
			$path = $base . $file;
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * Load files absorbed from the former `estatesite-houzez` plugin.
	 *
	 * These are admin-side conveniences that don't fit neatly elsewhere:
	 *   - agency-functions.php           sidebar metabox of agency-related actions
	 *   - agency-meta-boxes.php          EstateAssistant token metabox
	 *   - agent-cpt-mod.php              forces agent/agency CPTs public+admin-visible
	 *   - eas-display-conditions.php     Elementor Pro display condition (post content empty)
	 *   - property-quick-actions.php     "Update Property" row action on Properties list
	 */
	private function load_absorbed_houzez(): void {
		$files = glob( ESCORE_DIR . 'includes/absorbed/*.php' );
		foreach ( $files as $file ) {
			// Skip class files that are loaded lazily by their own siblings
			// (e.g. eas-display-conditions-class.php only loads when Pro's DC
			// register hook fires; loading it now would fatal because its
			// parent class doesn't exist yet).
			if ( str_contains( $file, '-class.php' ) ) {
				continue;
			}
			require_once $file;
		}
	}
}
