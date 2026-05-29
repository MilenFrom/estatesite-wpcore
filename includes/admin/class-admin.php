<?php
/**
 * Admin shell — registers the EstateSite top-level menu.
 *
 * Phase 0: placeholder page. Phase 2 mounts CSF options pages underneath.
 *
 * @package EstateSite\Core\Admin
 */

namespace EstateSite\Core\Admin;

defined( 'ABSPATH' ) || exit;

final class Admin {

	public function __construct() {
		// Priority 9 so our top-level menu registers before CSF (default 10)
		// attaches its options panel as a submenu.
		add_action( 'admin_menu',    [ $this, 'register_menu' ], 9 );
		add_action( 'admin_notices', [ $this, 'maybe_show_welcome' ] );

		// Migration admin page (submenu under EstateSite).
		new Migration_Page();
	}

	public function register_menu(): void {
		add_menu_page(
			__( 'EstateSite', 'estatesite-wpcore' ),
			__( 'EstateSite', 'estatesite-wpcore' ),
			'manage_options',
			'estatesite',
			[ Dashboard::class, 'render' ],
			'dashicons-admin-home',
			3
		);

		// Make sure the top-level menu link reads "Dashboard" in the submenu list,
		// since WP auto-adds the parent slug as the first submenu with the menu_title.
		add_submenu_page(
			'estatesite',
			__( 'Dashboard', 'estatesite-wpcore' ),
			__( 'Dashboard', 'estatesite-wpcore' ),
			'manage_options',
			'estatesite',
			[ Dashboard::class, 'render' ]
		);
	}

	public function maybe_show_welcome(): void {
		$activated = get_option( 'estatesite_activated_at' );
		if ( ! $activated ) {
			return;
		}
		// Show for 5 minutes after activation, once.
		if ( ( time() - (int) $activated ) > 300 ) {
			return;
		}
		if ( get_user_meta( get_current_user_id(), 'estatesite_dismissed_welcome', true ) ) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<strong><?php esc_html_e( 'EstateSite Core activated.', 'estatesite-wpcore' ); ?></strong>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=estatesite' ) ); ?>">
					<?php esc_html_e( 'View status →', 'estatesite-wpcore' ); ?>
				</a>
			</p>
		</div>
		<?php
		update_user_meta( get_current_user_id(), 'estatesite_dismissed_welcome', 1 );
	}
}
