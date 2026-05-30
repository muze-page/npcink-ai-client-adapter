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
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_post_magick_ai_adapter_create_openclaw_password', array( $this, 'handle_create_openclaw_password' ) );
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

	/**
	 * Registers the read-only OpenClaw connection page.
	 *
	 * @return void
	 */
	public function register_admin_page(): void {
		$page = new Admin\Connection_Page();
		$page->register();
	}

	/**
	 * Handles OpenClaw Application Password creation.
	 *
	 * @return void
	 */
	public function handle_create_openclaw_password(): void {
		$page = new Admin\Connection_Page();
		$page->handle_create_openclaw_password();
	}
}
