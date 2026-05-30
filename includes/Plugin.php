<?php
/**
 * Plugin bootstrap.
 *
 * @package MagickAIAdapter
 */

namespace MagickAI\Adapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers plugin hooks.
 */
final class Plugin {
	/**
	 * Boots the plugin.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		$controller = new Rest\Controller();
		$controller->register_routes();
	}
}
