<?php
/**
 * Deactivation hook handler.
 *
 * @package EstateSite\Core
 */

namespace EstateSite\Core;

defined( 'ABSPATH' ) || exit;

final class Deactivator {

	public static function deactivate(): void {
		flush_rewrite_rules();
		// Intentionally do NOT delete options here. uninstall.php handles full removal.
	}
}
