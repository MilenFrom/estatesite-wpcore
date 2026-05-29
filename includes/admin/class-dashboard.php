<?php
/**
 * EstateSite Dashboard — the welcome page at `/wp-admin/admin.php?page=estatesite`.
 *
 * Status hub with system health, content stats, quick actions, and recent activity.
 * Designed to be glance-able: an admin should know in 5 seconds whether everything's
 * loaded, how much data they have, and where to go next.
 *
 * @package EstateSite\Core\Admin
 */

namespace EstateSite\Core\Admin;

use EstateSite\Core\Compat_Mode;
use EstateSite\Core\CPT;
use EstateSite\Core\Migrator;
use EstateSite\Core\Nomenclatures;

defined( 'ABSPATH' ) || exit;

final class Dashboard {

	/**
	 * Render the full dashboard page.
	 * Called from Admin::render_placeholder_page (which now delegates here).
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'estatesite-wpcore' ) );
		}

		$data = self::collect_data();

		self::render_styles();
		?>
		<div class="wrap esc-dashboard">

			<?php self::render_hero( $data ); ?>

			<div class="esc-dash-section">
				<h2 class="esc-dash-section-title"><?php esc_html_e( 'System health', 'estatesite-wpcore' ); ?></h2>
				<div class="esc-dash-grid esc-dash-grid--4">
					<?php self::render_health_cards( $data ); ?>
				</div>
			</div>

			<div class="esc-dash-section">
				<h2 class="esc-dash-section-title"><?php esc_html_e( 'Content', 'estatesite-wpcore' ); ?></h2>
				<div class="esc-dash-grid esc-dash-grid--4">
					<?php self::render_content_cards( $data ); ?>
				</div>
			</div>

			<div class="esc-dash-section">
				<h2 class="esc-dash-section-title"><?php esc_html_e( 'Quick actions', 'estatesite-wpcore' ); ?></h2>
				<div class="esc-dash-grid esc-dash-grid--4">
					<?php self::render_action_cards( $data ); ?>
				</div>
			</div>

			<div class="esc-dash-section esc-dash-section--two-col">
				<div>
					<h2 class="esc-dash-section-title"><?php esc_html_e( 'Recent properties', 'estatesite-wpcore' ); ?></h2>
					<?php self::render_recent_properties( $data ); ?>
				</div>
				<div>
					<h2 class="esc-dash-section-title"><?php esc_html_e( 'Resources', 'estatesite-wpcore' ); ?></h2>
					<?php self::render_resources(); ?>
				</div>
			</div>

		</div>
		<?php
	}

	// ---------------------------------------------------------------------
	// Data collection
	// ---------------------------------------------------------------------

	private static function collect_data(): array {
		$property_pt = CPT::name( 'property' );
		$agent_pt    = CPT::name( 'agent' );
		$agency_pt   = CPT::name( 'agency' );

		$properties = wp_count_posts( $property_pt );
		$agents     = wp_count_posts( $agent_pt );
		$agencies   = wp_count_posts( $agency_pt );

		$nom_meta = Nomenclatures::meta();
		$nom_data = Nomenclatures::all();

		return [
			'nomenclatures_loaded'    => $nom_data !== null,
			'nomenclatures_freshness' => Nomenclatures::freshness(),
			'nomenclatures_meta'      => $nom_meta,
			'nomenclatures_count'     => $nom_data ? array_sum( array_map( 'count', array_filter( $nom_data, 'is_array' ) ) ) : 0,
			'nomenclatures_next_run'  => wp_next_scheduled( Nomenclatures::CRON_HOOK ),
			'ea_token_set'            => (bool) Nomenclatures::get_token(),
			'mode'                => Compat_Mode::get(),
			'version'             => ESCORE_VERSION,
			'theme'               => wp_get_theme(),
			'csf_loaded'          => class_exists( 'CSF' ),
			'rwmb_loaded'         => defined( 'RWMB_VER' ),
			'elementor_loaded'    => did_action( 'elementor/loaded' ) > 0,
			'core_active'         => defined( 'ESCORE_VERSION' ),
			'elementor_pkg_active'=> defined( 'ESELE_VERSION' ),
			'classic_theme_active'=> get_stylesheet() === 'estatesite-classic',
			'options_count'       => count( (array) get_option( Compat_Mode::is_legacy() ? 'houzez_options' : 'estatesite_options', [] ) ),
			'properties_published'=> (int) ( $properties->publish ?? 0 ),
			'properties_draft'    => (int) ( $properties->draft ?? 0 ),
			'properties_total'    => (int) array_sum( (array) $properties ),
			'agents'              => (int) ( $agents->publish ?? 0 ),
			'agencies'            => (int) ( $agencies->publish ?? 0 ),
			'property_types'      => (int) wp_count_terms( [ 'taxonomy' => 'property_type', 'hide_empty' => false ] ),
			'cities'              => (int) wp_count_terms( [ 'taxonomy' => 'property_city', 'hide_empty' => false ] ),
			'recent_properties'   => get_posts( [
				'post_type'      => $property_pt,
				'posts_per_page' => 5,
				'orderby'        => 'date',
				'order'          => 'DESC',
			] ),
			'migration_status'    => Migrator::status(),
			'property_pt'         => $property_pt,
			'agent_pt'            => $agent_pt,
			'agency_pt'           => $agency_pt,
		];
	}

	// ---------------------------------------------------------------------
	// Renderers
	// ---------------------------------------------------------------------

	private static function render_hero( array $data ): void {
		$mode       = $data['mode'];
		$mode_label = self::mode_label( $mode );
		$mode_class = 'esc-badge--' . esc_attr( $mode );
		?>
		<div class="esc-hero">
			<div class="esc-hero-main">
				<div class="esc-hero-brand">
					<span class="esc-hero-logo dashicons dashicons-admin-home"></span>
					<div>
						<h1 class="esc-hero-title">EstateSite</h1>
						<p class="esc-hero-subtitle">
							<?php
							printf(
								/* translators: %s: version */
								esc_html__( 'Core v%s — Real estate management for WordPress', 'estatesite-wpcore' ),
								esc_html( $data['version'] )
							);
							?>
						</p>
					</div>
				</div>
				<div class="esc-hero-meta">
					<span class="esc-badge <?php echo esc_attr( $mode_class ); ?>">
						<?php echo esc_html( $mode_label ); ?>
					</span>
				</div>
			</div>
		</div>
		<?php
	}

	private static function render_health_cards( array $data ): void {
		$cards = [
			[
				'title' => __( 'EstateSite Core', 'estatesite-wpcore' ),
				'ok'    => $data['core_active'],
				'value' => $data['core_active'] ? 'v' . $data['version'] : __( 'inactive', 'estatesite-wpcore' ),
				'icon'  => 'admin-plugins',
				'sub'   => sprintf(
					'%s · %s',
					$data['csf_loaded']  ? '✓ CodeStar' : '— CodeStar',
					$data['rwmb_loaded'] ? '✓ Meta Box' : '— Meta Box'
				),
			],
			[
				'title' => __( 'EstateSite Classic theme', 'estatesite-wpcore' ),
				'ok'    => $data['classic_theme_active'],
				'value' => $data['theme']->get( 'Name' ),
				'icon'  => 'admin-appearance',
				'sub'   => 'v' . $data['theme']->get( 'Version' ),
			],
			[
				'title' => __( 'EstateSite Elementor', 'estatesite-wpcore' ),
				'ok'    => $data['elementor_pkg_active'] && $data['elementor_loaded'],
				'value' => $data['elementor_pkg_active']
					? ( $data['elementor_loaded'] ? __( 'loaded', 'estatesite-wpcore' ) : __( 'Elementor not active', 'estatesite-wpcore' ) )
					: __( 'inactive', 'estatesite-wpcore' ),
				'icon'  => 'editor-paste-word',
				'sub'   => $data['elementor_loaded'] ? __( 'Elementor detected', 'estatesite-wpcore' ) : __( 'Install Elementor to enable widgets', 'estatesite-wpcore' ),
			],
			self::nomenclatures_card( $data ),
		];

		foreach ( $cards as $c ) {
			?>
			<div class="esc-card esc-card--health <?php echo $c['ok'] ? 'esc-card--ok' : 'esc-card--warn'; ?>">
				<div class="esc-card-icon"><span class="dashicons dashicons-<?php echo esc_attr( $c['icon'] ); ?>"></span></div>
				<div class="esc-card-body">
					<div class="esc-card-title"><?php echo esc_html( $c['title'] ); ?></div>
					<div class="esc-card-value"><?php echo esc_html( $c['value'] ); ?></div>
					<div class="esc-card-sub"><?php echo esc_html( $c['sub'] ); ?></div>
				</div>
				<div class="esc-card-status">
					<span class="dashicons dashicons-<?php echo $c['ok'] ? 'yes-alt' : 'warning'; ?>"></span>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Build the nomenclatures health card.
	 */
	private static function nomenclatures_card( array $data ): array {
		$meta       = $data['nomenclatures_meta'];
		$freshness  = $data['nomenclatures_freshness'];
		$loaded     = $data['nomenclatures_loaded'];
		$token_set  = $data['ea_token_set'];

		if ( ! $token_set ) {
			return [
				'title' => __( 'Nomenclatures', 'estatesite-wpcore' ),
				'ok'    => false,
				'value' => __( 'No EA token', 'estatesite-wpcore' ),
				'icon'  => 'admin-network',
				'sub'   => __( 'Configure EstateAssistant token to fetch data', 'estatesite-wpcore' ),
			];
		}

		if ( ! $loaded ) {
			return [
				'title' => __( 'Nomenclatures', 'estatesite-wpcore' ),
				'ok'    => false,
				'value' => __( 'Not fetched yet', 'estatesite-wpcore' ),
				'icon'  => 'admin-network',
				'sub'   => __( 'Will refresh on next cron tick', 'estatesite-wpcore' ),
			];
		}

		// We have data — describe its freshness.
		$last     = $meta['last_success_at'] ?? 0;
		$age      = human_time_diff( $last, time() );
		$count    = $data['nomenclatures_count'];

		return [
			'title' => __( 'Nomenclatures', 'estatesite-wpcore' ),
			'ok'    => $freshness === 'fresh',
			'value' => sprintf(
				/* translators: %d: total entry count */
				__( '%s entries', 'estatesite-wpcore' ),
				number_format_i18n( $count )
			),
			'icon'  => 'admin-network',
			'sub'   => sprintf(
				/* translators: %1$s: freshness label, %2$s: time-ago */
				__( '%1$s · %2$s ago', 'estatesite-wpcore' ),
				ucfirst( $freshness ),
				$age
			),
		];
	}

	private static function render_content_cards( array $data ): void {
		$cards = [
			[
				'label'  => __( 'Properties', 'estatesite-wpcore' ),
				'count'  => $data['properties_published'],
				'sub'    => $data['properties_draft']
					? sprintf( __( '%d drafts', 'estatesite-wpcore' ), $data['properties_draft'] )
					: __( 'all published', 'estatesite-wpcore' ),
				'icon'   => 'admin-home',
				'href'   => admin_url( 'edit.php?post_type=' . $data['property_pt'] ),
				'accent' => 'blue',
			],
			[
				'label'  => __( 'Agents', 'estatesite-wpcore' ),
				'count'  => $data['agents'],
				'sub'    => __( 'across all agencies', 'estatesite-wpcore' ),
				'icon'   => 'businessperson',
				'href'   => admin_url( 'edit.php?post_type=' . $data['agent_pt'] ),
				'accent' => 'green',
			],
			[
				'label'  => __( 'Agencies', 'estatesite-wpcore' ),
				'count'  => $data['agencies'],
				'sub'    => __( 'registered', 'estatesite-wpcore' ),
				'icon'   => 'building',
				'href'   => admin_url( 'edit.php?post_type=' . $data['agency_pt'] ),
				'accent' => 'purple',
			],
			[
				'label'  => __( 'Categories', 'estatesite-wpcore' ),
				'count'  => $data['property_types'] + $data['cities'],
				'sub'    => sprintf(
					/* translators: %1$d: property types count, %2$d: cities count */
					__( '%1$d types · %2$d cities', 'estatesite-wpcore' ),
					$data['property_types'],
					$data['cities']
				),
				'icon'   => 'category',
				'href'   => admin_url( 'edit-tags.php?taxonomy=property_type&post_type=' . $data['property_pt'] ),
				'accent' => 'amber',
			],
		];

		foreach ( $cards as $c ) {
			?>
			<a class="esc-card esc-card--content esc-card--<?php echo esc_attr( $c['accent'] ); ?>" href="<?php echo esc_url( $c['href'] ); ?>">
				<div class="esc-card-icon"><span class="dashicons dashicons-<?php echo esc_attr( $c['icon'] ); ?>"></span></div>
				<div class="esc-card-body">
					<div class="esc-card-count"><?php echo number_format_i18n( $c['count'] ); ?></div>
					<div class="esc-card-label"><?php echo esc_html( $c['label'] ); ?></div>
					<div class="esc-card-sub"><?php echo esc_html( $c['sub'] ); ?></div>
				</div>
			</a>
			<?php
		}
	}

	private static function render_action_cards( array $data ): void {
		$actions = [
			[
				'title' => __( 'Add a property', 'estatesite-wpcore' ),
				'desc'  => __( 'Create a new listing', 'estatesite-wpcore' ),
				'icon'  => 'plus-alt2',
				'href'  => admin_url( 'post-new.php?post_type=' . $data['property_pt'] ),
				'primary' => true,
			],
			[
				'title' => __( 'EstateSite Options', 'estatesite-wpcore' ),
				'desc'  => sprintf(
					/* translators: %d: option count */
					__( '%d settings across 13 sections', 'estatesite-wpcore' ),
					$data['options_count']
				),
				'icon'  => 'admin-settings',
				'href'  => admin_url( 'admin.php?page=estatesite_options' ),
			],
			[
				'title' => __( 'Data Migration', 'estatesite-wpcore' ),
				'desc'  => $data['mode'] === 'native_esc'
					? __( 'You are in native mode', 'estatesite-wpcore' )
					: __( 'Legacy → native', 'estatesite-wpcore' ),
				'icon'  => 'database-export',
				'href'  => admin_url( 'admin.php?page=estatesite-migration' ),
			],
			[
				'title' => __( 'Documentation', 'estatesite-wpcore' ),
				'desc'  => __( 'Migration guide, porting notes', 'estatesite-wpcore' ),
				'icon'  => 'book',
				'href'  => 'https://estatesite.eu/docs/',
				'external' => true,
			],
		];

		foreach ( $actions as $a ) {
			$target = ! empty( $a['external'] ) ? ' target="_blank" rel="noopener"' : '';
			$class  = 'esc-card esc-card--action' . ( ! empty( $a['primary'] ) ? ' esc-card--primary' : '' );
			?>
			<a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( $a['href'] ); ?>"<?php echo $target; ?>>
				<div class="esc-card-icon"><span class="dashicons dashicons-<?php echo esc_attr( $a['icon'] ); ?>"></span></div>
				<div class="esc-card-body">
					<div class="esc-card-title"><?php echo esc_html( $a['title'] ); ?></div>
					<div class="esc-card-sub"><?php echo esc_html( $a['desc'] ); ?></div>
				</div>
				<div class="esc-card-arrow"><span class="dashicons dashicons-arrow-right-alt2"></span></div>
			</a>
			<?php
		}
	}

	private static function render_recent_properties( array $data ): void {
		if ( empty( $data['recent_properties'] ) ) {
			echo '<div class="esc-card esc-card--empty">';
			esc_html_e( 'No properties yet. Create your first listing to get started.', 'estatesite-wpcore' );
			echo '</div>';
			return;
		}
		?>
		<div class="esc-card esc-card--list">
			<ul class="esc-list">
				<?php foreach ( $data['recent_properties'] as $post ) :
					$edit_link = get_edit_post_link( $post->ID );
					$view_link = get_permalink( $post->ID );
					?>
					<li class="esc-list-item">
						<div class="esc-list-main">
							<a class="esc-list-title" href="<?php echo esc_url( $edit_link ); ?>">
								<?php echo esc_html( wp_trim_words( $post->post_title, 12 ) ?: __( '(no title)', 'estatesite-wpcore' ) ); ?>
							</a>
							<div class="esc-list-meta">
								<span><?php echo esc_html( get_the_date( '', $post ) ); ?></span>
								<span>·</span>
								<span><?php echo esc_html( ucfirst( $post->post_status ) ); ?></span>
							</div>
						</div>
						<div class="esc-list-actions">
							<a class="esc-list-action" href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'Edit', 'estatesite-wpcore' ); ?></a>
							<a class="esc-list-action" href="<?php echo esc_url( $view_link ); ?>" target="_blank"><?php esc_html_e( 'View', 'estatesite-wpcore' ); ?></a>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
			<div class="esc-list-footer">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $data['property_pt'] ) ); ?>">
					<?php esc_html_e( 'View all properties →', 'estatesite-wpcore' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	private static function render_resources(): void {
		$links = [
			[
				'label' => __( 'Migration guide', 'estatesite-wpcore' ),
				'desc'  => __( 'Move existing Houzez site to EstateSite', 'estatesite-wpcore' ),
				'icon'  => 'media-document',
				'href'  => esc_url( admin_url( 'admin.php?page=estatesite-migration' ) ),
			],
			[
				'label' => __( 'EstateSite Options', 'estatesite-wpcore' ),
				'desc'  => __( 'Theme settings, currency, search, emails', 'estatesite-wpcore' ),
				'icon'  => 'admin-tools',
				'href'  => admin_url( 'admin.php?page=estatesite_options' ),
			],
			[
				'label' => __( 'EstateAssistant Sync', 'estatesite-wpcore' ),
				'desc'  => __( 'Property data syncing', 'estatesite-wpcore' ),
				'icon'  => 'update',
				'href'  => admin_url( 'admin.php?page=ea_main_settings' ),
			],
		];
		?>
		<div class="esc-card esc-card--list">
			<ul class="esc-list">
				<?php foreach ( $links as $l ) : ?>
					<li class="esc-list-item">
						<span class="esc-list-icon"><span class="dashicons dashicons-<?php echo esc_attr( $l['icon'] ); ?>"></span></span>
						<div class="esc-list-main">
							<a class="esc-list-title" href="<?php echo esc_url( $l['href'] ); ?>"><?php echo esc_html( $l['label'] ); ?></a>
							<div class="esc-list-meta"><?php echo esc_html( $l['desc'] ); ?></div>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	private static function mode_label( string $mode ): string {
		switch ( $mode ) {
			case Compat_Mode::LEGACY_FAVE:  return __( 'Legacy mode (Houzez-compatible)', 'estatesite-wpcore' );
			case Compat_Mode::NATIVE_ESC:   return __( 'Native mode', 'estatesite-wpcore' );
			case Compat_Mode::MIGRATING:    return __( 'Migrating', 'estatesite-wpcore' );
			default:                         return $mode;
		}
	}

	// ---------------------------------------------------------------------
	// Styles
	// ---------------------------------------------------------------------

	private static function render_styles(): void {
		// Inline because dashboard is a single rare-load page; avoids enqueue overhead.
		?>
		<style>
		.esc-dashboard { max-width: 1280px; margin-top: 1rem; }
		.esc-dashboard h2.esc-dash-section-title { font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em; color: #50575e; margin: 2rem 0 0.75rem; font-weight: 600; }

		/* Hero */
		.esc-hero { background: linear-gradient(135deg, #2563eb 0%, #4338ca 100%); color: #fff; border-radius: 8px; padding: 1.75rem 2rem; margin-top: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
		.esc-hero-main { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
		.esc-hero-brand { display: flex; align-items: center; gap: 1rem; }
		.esc-hero-logo { font-size: 40px !important; width: 40px !important; height: 40px !important; opacity: 0.9; }
		.esc-hero-title { color: #fff; font-size: 28px; margin: 0; line-height: 1.1; font-weight: 700; }
		.esc-hero-subtitle { color: rgba(255,255,255,0.85); margin: 4px 0 0; font-size: 14px; }
		.esc-hero-meta { display: flex; gap: 0.5rem; }

		/* Badges */
		.esc-badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 600; background: rgba(255,255,255,0.2); color: #fff; }
		.esc-badge--legacy_fave { background: rgba(255,255,255,0.2); }
		.esc-badge--native_esc  { background: #16a34a; }
		.esc-badge--migrating   { background: #f59e0b; }

		/* Grid system */
		.esc-dash-section { margin-bottom: 1rem; }
		.esc-dash-section--two-col { display: grid; grid-template-columns: 1.5fr 1fr; gap: 1.5rem; }
		@media (max-width: 900px) { .esc-dash-section--two-col { grid-template-columns: 1fr; } }
		.esc-dash-grid { display: grid; gap: 1rem; }
		.esc-dash-grid--3 { grid-template-columns: repeat(3, 1fr); }
		.esc-dash-grid--4 { grid-template-columns: repeat(4, 1fr); }
		@media (max-width: 1100px) { .esc-dash-grid--4 { grid-template-columns: repeat(2, 1fr); } }
		@media (max-width: 700px)  { .esc-dash-grid--3, .esc-dash-grid--4 { grid-template-columns: 1fr; } }

		/* Cards */
		.esc-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 1.1rem 1.25rem; display: flex; align-items: center; gap: 1rem; text-decoration: none; color: inherit; transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s; }
		a.esc-card:hover { border-color: #2563eb; box-shadow: 0 4px 12px rgba(37,99,235,0.08); transform: translateY(-1px); text-decoration: none; }

		.esc-card-icon { flex-shrink: 0; width: 40px; height: 40px; border-radius: 8px; background: #f1f5f9; color: #475569; display: flex; align-items: center; justify-content: center; }
		.esc-card-icon .dashicons { font-size: 22px !important; width: 22px !important; height: 22px !important; }
		.esc-card-body { flex: 1; min-width: 0; }
		.esc-card-title { font-weight: 600; font-size: 14px; color: #0f172a; }
		.esc-card-value { font-size: 13px; color: #334155; margin-top: 2px; }
		.esc-card-count { font-size: 28px; font-weight: 700; color: #0f172a; line-height: 1; }
		.esc-card-label { font-size: 13px; font-weight: 600; color: #334155; margin-top: 4px; }
		.esc-card-sub { font-size: 12px; color: #64748b; margin-top: 2px; }
		.esc-card-status { flex-shrink: 0; }
		.esc-card-status .dashicons { font-size: 24px !important; width: 24px !important; height: 24px !important; }
		.esc-card-arrow { flex-shrink: 0; color: #94a3b8; }

		/* Health card variants */
		.esc-card--ok .esc-card-status .dashicons { color: #16a34a; }
		.esc-card--warn .esc-card-status .dashicons { color: #f59e0b; }

		/* Content card accents */
		.esc-card--blue   .esc-card-icon { background: #dbeafe; color: #2563eb; }
		.esc-card--green  .esc-card-icon { background: #dcfce7; color: #16a34a; }
		.esc-card--purple .esc-card-icon { background: #ede9fe; color: #7c3aed; }
		.esc-card--amber  .esc-card-icon { background: #fef3c7; color: #d97706; }

		/* Primary action */
		.esc-card--primary { border-color: #2563eb; background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%); }
		.esc-card--primary .esc-card-icon { background: #2563eb; color: #fff; }
		.esc-card--primary .esc-card-title { color: #1e40af; }

		/* Lists */
		.esc-card--list { display: block; padding: 0; }
		.esc-list { list-style: none; margin: 0; padding: 0; }
		.esc-list-item { display: flex; align-items: center; gap: 1rem; padding: 0.85rem 1.25rem; border-bottom: 1px solid #f1f5f9; }
		.esc-list-item:last-child { border-bottom: none; }
		.esc-list-icon .dashicons { color: #94a3b8; font-size: 18px !important; width: 18px !important; height: 18px !important; }
		.esc-list-main { flex: 1; min-width: 0; }
		.esc-list-title { font-weight: 600; color: #0f172a; text-decoration: none; display: block; line-height: 1.3; }
		.esc-list-title:hover { color: #2563eb; }
		.esc-list-meta { font-size: 12px; color: #64748b; margin-top: 2px; display: flex; gap: 6px; align-items: center; }
		.esc-list-actions { display: flex; gap: 0.75rem; flex-shrink: 0; }
		.esc-list-action { font-size: 12px; color: #2563eb; text-decoration: none; }
		.esc-list-action:hover { text-decoration: underline; }
		.esc-list-footer { padding: 0.75rem 1.25rem; background: #f8fafc; border-top: 1px solid #f1f5f9; text-align: center; font-size: 13px; }
		.esc-list-footer a { text-decoration: none; color: #2563eb; font-weight: 500; }

		.esc-card--empty { color: #64748b; font-style: italic; padding: 2rem 1.25rem; text-align: center; }
		</style>
		<?php
	}
}
