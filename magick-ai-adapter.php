<?php
/**
 * Plugin Name: Magick AI Adapter
 * Description: Thin OpenClaw adapter for Magick AI Core governance and WordPress Abilities execution.
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Requires at least: 6.9
 * Author: Magick AI
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: magick-ai-adapter
 *
 * @package MagickAIAdapter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MAGICK_AI_ADAPTER_VERSION', '0.1.0' );
define( 'MAGICK_AI_ADAPTER_FILE', __FILE__ );
define( 'MAGICK_AI_ADAPTER_DIR', plugin_dir_path( __FILE__ ) );

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'MagickAI\\Adapter\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$path     = MAGICK_AI_ADAPTER_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		$plugin = new MagickAI\Adapter\Plugin();
		$plugin->boot();
	}
);
