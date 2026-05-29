<?php
/**
 * Admin UI for the legacy_fave → native_esc migration.
 *
 * Mounted as a submenu under the EstateSite top-level menu. Provides buttons
 * for the same operations as the WP-CLI commands, with confirmation prompts
 * for destructive actions.
 *
 * @package EstateSite\Core\Admin
 */

namespace EstateSite\Core\Admin;

use EstateSite\Core\Compat_Mode;
use EstateSite\Core\Migrator;

defined( 'ABSPATH' ) || exit;

final class Migration_Page {

	private const ACTION_NONCE = 'estatesite_migration_action';

	public function __construct() {
		add_action( 'admin_menu',                       [ $this, 'register_menu' ], 11 );
		add_action( 'admin_post_estatesite_migrate',    [ $this, 'handle_action' ] );
		add_action( 'wp_ajax_estatesite_migrate_batch', [ $this, 'ajax_run_batch' ] );
	}

	public function register_menu(): void {
		add_submenu_page(
			'estatesite',
			__( 'Migration', 'estatesite-wpcore' ),
			__( 'Migration', 'estatesite-wpcore' ),
			'manage_options',
			'estatesite-migration',
			[ $this, 'render' ]
		);
	}

	/**
	 * Render the migration admin page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'estatesite-wpcore' ) );
		}

		$mode    = Compat_Mode::get();
		$status  = Migrator::status();
		$audit   = Migrator::audit();
		$flash   = get_transient( 'estatesite_migration_flash' );
		if ( $flash ) {
			delete_transient( 'estatesite_migration_flash' );
		}

		$action_url = admin_url( 'admin-post.php' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'EstateSite Data Migration', 'estatesite-wpcore' ); ?></h1>

			<?php if ( $flash ) : ?>
				<div class="notice notice-<?php echo esc_attr( $flash['type'] ); ?> is-dismissible">
					<p><?php echo esc_html( $flash['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<div class="card" style="max-width:none;">
				<h2><?php esc_html_e( 'Current Status', 'estatesite-wpcore' ); ?></h2>
				<table class="widefat striped" style="max-width:600px;">
					<tr><th><?php esc_html_e( 'Compat mode', 'estatesite-wpcore' ); ?></th>
						<td><code><?php echo esc_html( $mode ); ?></code></td></tr>
					<tr><th><?php esc_html_e( 'Phase', 'estatesite-wpcore' ); ?></th>
						<td><code><?php echo esc_html( $status['phase'] ); ?></code></td></tr>
					<tr><th><?php esc_html_e( 'Options copied', 'estatesite-wpcore' ); ?></th>
						<td><?php echo $status['options_copied'] ? '✓' : '—'; ?></td></tr>
					<?php foreach ( $status['cursors'] as $entity => $cursor ) : ?>
						<tr>
							<th><?php echo esc_html( ucfirst( $entity ) ); ?></th>
							<td>
								<?php
								printf(
									/* translators: %1$d: processed count, %2$d: last id cursor */
									esc_html__( 'Processed: %1$d, last ID: %2$d', 'estatesite-wpcore' ),
									(int) $status['processed'][ $entity ],
									(int) $cursor
								);
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>

			<div class="card" style="max-width:none;">
				<h2><?php esc_html_e( 'Pre-migration audit', 'estatesite-wpcore' ); ?></h2>
				<p class="description"><?php esc_html_e( 'What would be touched by a migration:', 'estatesite-wpcore' ); ?></p>
				<table class="widefat striped" style="max-width:600px;">
					<?php foreach ( $audit['posts_by_type'] as $pt => $count ) : ?>
						<tr><th><code><?php echo esc_html( $pt ); ?></code></th>
							<td><?php echo (int) $count; ?> <?php esc_html_e( 'posts', 'estatesite-wpcore' ); ?></td></tr>
					<?php endforeach; ?>
					<tr><th><?php esc_html_e( 'Pages with custom Houzez meta', 'estatesite-wpcore' ); ?></th>
						<td><?php echo (int) $audit['pages_with_fave_meta']; ?></td></tr>
					<tr><th><?php esc_html_e( 'Users with custom Houzez meta', 'estatesite-wpcore' ); ?></th>
						<td><?php echo (int) $audit['users_with_fave_meta']; ?></td></tr>
					<tr><th><?php esc_html_e( 'Terms with custom Houzez meta', 'estatesite-wpcore' ); ?></th>
						<td><?php echo (int) $audit['terms_with_fave_meta']; ?></td></tr>
					<tr><th><code>houzez_options</code></th>
						<td><?php echo $audit['options_legacy_present'] ? '✓ ' . esc_html__( 'present', 'estatesite-wpcore' ) : '—'; ?></td></tr>
					<tr><th><code>estatesite_options</code></th>
						<td><?php echo $audit['options_native_present'] ? '✓ ' . esc_html__( 'present', 'estatesite-wpcore' ) : '—'; ?></td></tr>
				</table>
			</div>

			<div class="card" style="max-width:none;">
				<h2><?php esc_html_e( 'Migration Actions', 'estatesite-wpcore' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Run these in order. Each action is reversible; you can rollback at any point.', 'estatesite-wpcore' ); ?>
				</p>

				<form method="post" action="<?php echo esc_url( $action_url ); ?>" style="margin-bottom:1.5em;">
					<?php wp_nonce_field( self::ACTION_NONCE ); ?>
					<input type="hidden" name="action" value="estatesite_migrate" />
					<input type="hidden" name="op" value="prepare" />
					<p>
						<strong>1. <?php esc_html_e( 'Prepare', 'estatesite-wpcore' ); ?></strong>
						— <?php esc_html_e( 'Switch into MIGRATING mode (new writes dual-write to both fave_* and esc_* keys).', 'estatesite-wpcore' ); ?>
					</p>
					<?php submit_button( __( 'Prepare migration', 'estatesite-wpcore' ), 'primary',
						'submit_prepare', false,
						[ 'disabled' => $mode === Compat_Mode::NATIVE_ESC ] ); ?>
				</form>

				<form method="post" action="<?php echo esc_url( $action_url ); ?>" style="margin-bottom:1.5em;">
					<?php wp_nonce_field( self::ACTION_NONCE ); ?>
					<input type="hidden" name="action" value="estatesite_migrate" />
					<input type="hidden" name="op" value="run" />
					<p>
						<strong>2. <?php esc_html_e( 'Run', 'estatesite-wpcore' ); ?></strong>
						— <?php esc_html_e( 'Copy existing fave_* meta into esc_* keys across all entity types. Idempotent and resumable.', 'estatesite-wpcore' ); ?>
					</p>
					<button type="button" class="button button-primary" id="estatesite-migrate-run"
						<?php disabled( $mode !== Compat_Mode::MIGRATING ); ?>>
						<?php esc_html_e( 'Run migration', 'estatesite-wpcore' ); ?>
					</button>
					<span class="estatesite-migrate-progress" style="margin-left:1em;"></span>
				</form>

				<form method="post" action="<?php echo esc_url( $action_url ); ?>" style="margin-bottom:1.5em;"
					onsubmit="return confirm('<?php echo esc_js( __( 'Cutover to native_esc mode? All reads will switch to esc_* keys (with fave_* fallback for any unmigrated data).', 'estatesite-wpcore' ) ); ?>');">
					<?php wp_nonce_field( self::ACTION_NONCE ); ?>
					<input type="hidden" name="action" value="estatesite_migrate" />
					<input type="hidden" name="op" value="cutover" />
					<p>
						<strong>3. <?php esc_html_e( 'Cutover', 'estatesite-wpcore' ); ?></strong>
						— <?php esc_html_e( 'Switch to native_esc mode. Reads now prefer esc_* keys.', 'estatesite-wpcore' ); ?>
					</p>
					<?php submit_button( __( 'Cutover to native', 'estatesite-wpcore' ), 'primary',
						'submit_cutover', false,
						[ 'disabled' => $mode !== Compat_Mode::MIGRATING ] ); ?>
				</form>

				<form method="post" action="<?php echo esc_url( $action_url ); ?>" style="margin-bottom:1em;"
					onsubmit="return confirm('<?php echo esc_js( __( 'Roll back to legacy_fave mode? esc_* meta will be preserved but unused.', 'estatesite-wpcore' ) ); ?>');">
					<?php wp_nonce_field( self::ACTION_NONCE ); ?>
					<input type="hidden" name="action" value="estatesite_migrate" />
					<input type="hidden" name="op" value="rollback" />
					<p>
						<strong><?php esc_html_e( 'Rollback', 'estatesite-wpcore' ); ?></strong>
						— <?php esc_html_e( 'Switch back to legacy_fave mode at any time. Safe and reversible.', 'estatesite-wpcore' ); ?>
					</p>
					<?php submit_button( __( 'Rollback to legacy', 'estatesite-wpcore' ), 'secondary',
						'submit_rollback', false,
						[ 'disabled' => $mode === Compat_Mode::LEGACY_FAVE ] ); ?>
				</form>
			</div>
		</div>

		<script>
		(function () {
			var $btn      = document.getElementById('estatesite-migrate-run');
			var $progress = document.querySelector('.estatesite-migrate-progress');
			if (!$btn) return;

			$btn.addEventListener('click', function () {
				$btn.disabled = true;
				$progress.textContent = '<?php echo esc_js( __( 'Starting…', 'estatesite-wpcore' ) ); ?>';
				runBatch();
			});

			function runBatch() {
				var body = new URLSearchParams();
				body.append('action', 'estatesite_migrate_batch');
				body.append('_wpnonce', '<?php echo esc_js( wp_create_nonce( 'estatesite_migrate_batch' ) ); ?>');

				fetch(ajaxurl, {
					method: 'POST',
					credentials: 'same-origin',
					body: body
				})
				.then(function (r) { return r.json(); })
				.then(function (j) {
					if (!j.success) {
						$progress.textContent = j.data && j.data.message ? j.data.message : 'Error';
						$btn.disabled = false;
						return;
					}
					$progress.textContent =
						'<?php echo esc_js( __( 'Processed', 'estatesite-wpcore' ) ); ?> ' +
						j.data.entity + ': ' + j.data.processed + ' (' +
						j.data.remaining + ' <?php echo esc_js( __( 'remaining', 'estatesite-wpcore' ) ); ?>)';
					if (j.data.done) {
						$progress.textContent = '<?php echo esc_js( __( 'Migration complete. Reload page to refresh status.', 'estatesite-wpcore' ) ); ?>';
						$btn.disabled = false;
						return;
					}
					setTimeout(runBatch, 50);
				})
				.catch(function (e) {
					$progress.textContent = 'Error: ' + e.message;
					$btn.disabled = false;
				});
			}
		})();
		</script>
		<?php
	}

	/**
	 * Form POST handler — prepare/cutover/rollback (synchronous, fast).
	 */
	public function handle_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'estatesite-wpcore' ) );
		}
		check_admin_referer( self::ACTION_NONCE );

		$op = $_POST['op'] ?? '';
		$result = match ( $op ) {
			'prepare'  => Migrator::prepare(),
			'cutover'  => Migrator::cutover(),
			'rollback' => Migrator::rollback(),
			default    => [ 'status' => 'error', 'message' => __( 'Unknown operation.', 'estatesite-wpcore' ) ],
		};

		set_transient( 'estatesite_migration_flash', [
			'type'    => $result['status'] === 'ok' ? 'success' : 'error',
			'message' => $result['message'] ?? '',
		], MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'admin.php?page=estatesite-migration' ) );
		exit;
	}

	/**
	 * AJAX handler — run one batch and return progress JSON.
	 * Called repeatedly by the front-end button to walk through entities.
	 */
	public function ajax_run_batch(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'No permission.', 'estatesite-wpcore' ) ] );
		}
		check_ajax_referer( 'estatesite_migrate_batch' );

		$r = Migrator::run( 100, false );
		if ( $r['status'] !== 'ok' ) {
			wp_send_json_error( [ 'message' => $r['message'] ?? __( 'Migration error.', 'estatesite-wpcore' ) ] );
		}
		wp_send_json_success( [
			'entity'    => $r['entity'],
			'processed' => $r['processed'],
			'remaining' => $r['remaining'],
			'last_id'   => $r['last_id'],
			'done'      => ( $r['entity'] === 'all' && $r['done'] ),
		] );
	}
}
