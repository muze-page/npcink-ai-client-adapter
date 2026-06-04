<?php
/**
 * OpenClaw connection admin page.
 *
 * @package MagickAIAdapter
 */

namespace MagickAI\Adapter\Admin;

use MagickAI\Adapter\Rest\Controller;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a read-only connection handoff surface for OpenClaw.
 */
final class Connection_Page {
	const PARENT_MENU_SLUG = 'magick-ai';
	const MENU_SLUG        = 'magick-ai-adapter';
	const MENU_CAPABILITY  = 'manage_options';
	const CREATE_ACTION    = 'magick_ai_adapter_create_openclaw_password';
	const PAIR_MENU_SLUG   = 'magick-ai-adapter-pair';
	const PAIR_ACTION      = 'magick_ai_adapter_pairing_decision';
	const REVOKE_KEY_ACTION = 'magick_ai_adapter_revoke_client_key';

	/**
	 * Registers the menu item.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->ensure_parent_menu();

		add_submenu_page(
			self::PARENT_MENU_SLUG,
			__( 'Magick AI Adapter', 'magick-ai-adapter' ),
			__( 'Adapter', 'magick-ai-adapter' ),
			self::MENU_CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render' ),
			20
		);

		add_submenu_page(
			null,
			__( 'Approve Magick AI Client', 'magick-ai-adapter' ),
			__( 'Approve Magick AI Client', 'magick-ai-adapter' ),
			self::MENU_CAPABILITY,
			self::PAIR_MENU_SLUG,
			array( $this, 'render_pairing_page' )
		);
	}

	/**
	 * Ensures the shared Magick AI parent menu exists.
	 *
	 * @return void
	 */
	private function ensure_parent_menu(): void {
		if ( $this->has_parent_menu() ) {
			return;
		}

		add_menu_page(
			__( 'Magick AI', 'magick-ai-adapter' ),
			__( 'Magick AI', 'magick-ai-adapter' ),
			self::MENU_CAPABILITY,
			self::PARENT_MENU_SLUG,
			array( $this, 'render_overview' ),
			'dashicons-superhero',
			58
		);

		add_submenu_page(
			self::PARENT_MENU_SLUG,
			__( 'Magick AI Overview', 'magick-ai-adapter' ),
			__( 'Overview', 'magick-ai-adapter' ),
			self::MENU_CAPABILITY,
			self::PARENT_MENU_SLUG,
			array( $this, 'render_overview' ),
			0
		);
	}

	/**
	 * Returns whether another Magick AI plugin already created the parent menu.
	 *
	 * @return bool
	 */
	private function has_parent_menu(): bool {
		global $menu;

		foreach ( (array) $menu as $item ) {
			if ( isset( $item[2] ) && self::PARENT_MENU_SLUG === $item[2] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Renders the shared Magick AI overview page.
	 *
	 * @return void
	 */
	public function render_overview(): void {
		if ( ! current_user_can( self::MENU_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'magick-ai-adapter' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Magick AI', 'magick-ai-adapter' ); ?></h1>
			<p><?php echo esc_html__( 'Local WordPress entry points for Magick AI governance, connections, cloud access, and ability packages.', 'magick-ai-adapter' ); ?></p>
			<h2><?php echo esc_html__( 'Installed Surfaces', 'magick-ai-adapter' ); ?></h2>
			<table class="widefat striped" style="max-width: 860px;">
				<tbody>
					<?php
					$this->render_overview_row( __( 'Core', 'magick-ai-adapter' ), __( 'Review proposals, approval decisions, commit preflight, audit, and Core app keys.', 'magick-ai-adapter' ), 'magick-ai-core' );
					$this->render_overview_row( __( 'Adapter', 'magick-ai-adapter' ), __( 'Connect OpenClaw through the Adapter surface.', 'magick-ai-adapter' ), self::MENU_SLUG );
					$this->render_overview_row( __( 'Abilities', 'magick-ai-adapter' ), __( 'Verify WordPress Abilities API packages and demo ability controls.', 'magick-ai-adapter' ), 'magick-ai-abilities' );
					$this->render_overview_row( __( 'Cloud Addon', 'magick-ai-adapter' ), __( 'Connect this site to Magick AI Cloud without moving local control-plane truth.', 'magick-ai-adapter' ), 'magick-ai-cloud-addon' );
					?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Renders one overview row.
	 *
	 * @param string $label       Row label.
	 * @param string $description Row description.
	 * @param string $slug        Menu page slug.
	 * @return void
	 */
	private function render_overview_row( string $label, string $description, string $slug ): void {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td><?php echo esc_html( $description ); ?></td>
			<td>
				<?php if ( $this->is_submenu_registered( $slug ) ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>"><?php echo esc_html__( 'Open', 'magick-ai-adapter' ); ?></a>
				<?php else : ?>
					<span style="color: #646970;"><?php echo esc_html__( 'Not installed', 'magick-ai-adapter' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Returns whether a Magick AI submenu has been registered.
	 *
	 * @param string $slug Menu page slug.
	 * @return bool
	 */
	private function is_submenu_registered( string $slug ): bool {
		global $submenu;

		foreach ( (array) ( $submenu[ self::PARENT_MENU_SLUG ] ?? array() ) as $item ) {
			if ( isset( $item[2] ) && $slug === $item[2] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Renders the page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'magick-ai-adapter' ) );
		}

		$base_url        = rest_url( Controller::NAMESPACE );
		$health_url      = rest_url( Controller::NAMESPACE . '/health' );
		$help_url        = rest_url( Controller::NAMESPACE . '/help' );
		$capabilities_url = rest_url( Controller::NAMESPACE . '/capabilities' );
		$manifest_url    = rest_url( Controller::NAMESPACE . '/connection/manifest' );
		$key_pairs_url   = rest_url( Controller::NAMESPACE . '/connection/key-pairs' );
		$health          = $this->health();
		$status          = $this->status( $health );
		$shortcuts       = Controller::read_shortcuts();
		$example_request = $this->example_request( $health_url );
		$proposal_request = $this->proposal_request( rest_url( Controller::NAMESPACE . '/proposals' ) );
		$proposal_status_request = $this->proposal_status_request( rest_url( Controller::NAMESPACE . '/proposals/PROPOSAL_ID' ) );
		$handoff_prompt  = $this->handoff_prompt( $base_url );
		$can_create_password = $this->can_create_application_password();
		$user            = wp_get_current_user();
		$username        = $user->exists() ? (string) $user->user_login : '';
		$include_local_tls = $this->is_local_url( home_url() );
		$client_config   = $this->openclaw_env_text( $username, $include_local_tls );
		$local_cli_setup = $this->local_cli_setup_text( $include_local_tls );
		$local_cli_connect_command = $this->local_cli_connect_command( $include_local_tls );
		$local_cli_status_command = $this->local_cli_status_command( $include_local_tls );
		$key_records     = ( new Controller() )->admin_client_keys( get_current_user_id() );
		$lookup_id       = isset( $_GET['adapter_proposal_id'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['adapter_proposal_id'] ) ) : '';
		$lookup_result   = '' !== $lookup_id ? $this->proposal_lookup( $lookup_id ) : null;
		?>
		<div class="wrap magick-ai-adapter-connection">
			<h1><?php echo esc_html__( 'Magick AI Adapter', 'magick-ai-adapter' ); ?></h1>
			<p class="description"><?php echo esc_html__( 'Connect OpenClaw to this WordPress site through the Adapter REST surface.', 'magick-ai-adapter' ); ?></p>

			<style>
				.magick-ai-adapter-connection {
					max-width: 1180px;
				}
				.magick-ai-adapter-connection .maa-tabs {
					display: none;
				}
				.magick-ai-adapter-connection .maa-tab {
					margin: 0;
					padding: 9px 14px;
					border: 1px solid transparent;
					border-bottom: 0;
					background: transparent;
					color: #1d2327;
					cursor: pointer;
					font-weight: 600;
				}
				.magick-ai-adapter-connection .maa-tab:hover {
					color: #135e96;
				}
				.magick-ai-adapter-connection .maa-tab.is-active {
					margin-bottom: -1px;
					border-color: #c3c4c7;
					background: #fff;
					color: #1d2327;
				}
				.magick-ai-adapter-connection .maa-tab-panel {
					padding-top: 0;
				}
				.magick-ai-adapter-connection .maa-tab-panel.is-active {
					display: block;
				}
				.magick-ai-adapter-connection .maa-workspace {
					display: grid;
					grid-template-columns: minmax(0, 1fr) minmax(320px, 420px);
					gap: 16px;
					align-items: start;
				}
				.magick-ai-adapter-connection .maa-workspace > * {
					min-width: 0;
				}
				.magick-ai-adapter-connection .maa-summary {
					display: flex;
					flex-wrap: wrap;
					gap: 10px 18px;
					align-items: center;
					margin: 18px 0;
					border: 1px solid #dcdcde;
					background: #fff;
					padding: 10px 14px;
				}
				.magick-ai-adapter-connection .maa-section {
					background: #fff;
				}
				.magick-ai-adapter-connection .maa-summary-item {
					display: flex;
					gap: 6px;
					align-items: center;
					min-width: 0;
				}
				.magick-ai-adapter-connection .maa-summary-item:first-child {
					padding-right: 18px;
					border-right: 1px solid #dcdcde;
				}
				.magick-ai-adapter-connection .maa-label {
					display: block;
					margin-bottom: 4px;
					color: #646970;
					font-size: 12px;
					line-height: 1.4;
				}
				.magick-ai-adapter-connection .maa-value {
					font-weight: 600;
				}
				.magick-ai-adapter-connection .maa-status {
					display: inline-block;
					padding: 2px 8px;
					border-radius: 3px;
					font-size: 12px;
					font-weight: 600;
				}
				.magick-ai-adapter-connection .maa-status-ok {
					background: #edfaef;
					color: #008a20;
				}
				.magick-ai-adapter-connection .maa-status-warning {
					background: #fcf9e8;
					color: #996800;
				}
				.magick-ai-adapter-connection .maa-status-error {
					background: #fcf0f1;
					color: #b32d2e;
				}
				.magick-ai-adapter-connection .maa-section {
					box-sizing: border-box;
					border: 1px solid #dcdcde;
					padding: 16px;
					margin-bottom: 16px;
					min-width: 0;
				}
				.magick-ai-adapter-connection .maa-section-highlight {
					border-left: 4px solid #2271b1;
				}
				.magick-ai-adapter-connection .maa-section h2 {
					margin: 0 0 10px;
					font-size: 16px;
				}
				.magick-ai-adapter-connection .maa-section h3 {
					margin: 18px 0 8px;
					font-size: 14px;
				}
				.magick-ai-adapter-connection .maa-section-intro {
					margin: 0 0 14px;
					color: #50575e;
				}
				.magick-ai-adapter-connection details.maa-section > summary {
					display: flex;
					flex-wrap: wrap;
					align-items: center;
					justify-content: space-between;
					gap: 8px;
					margin: -16px;
					padding: 16px;
					cursor: pointer;
				}
				.magick-ai-adapter-connection details.maa-section > summary:hover {
					background: #f6f7f7;
				}
				.magick-ai-adapter-connection details.maa-section > summary strong {
					display: block;
					font-size: 16px;
				}
				.magick-ai-adapter-connection details.maa-section > summary::after {
					content: "+";
					color: #2271b1;
					font-size: 18px;
					font-weight: 600;
				}
				.magick-ai-adapter-connection details.maa-section[open] > summary::after {
					content: "-";
				}
				.magick-ai-adapter-connection .maa-inline-disclosure {
					margin-top: 18px;
					border-top: 1px solid #dcdcde;
				}
				.magick-ai-adapter-connection .maa-inline-disclosure > summary {
					display: flex;
					flex-wrap: wrap;
					align-items: center;
					justify-content: space-between;
					gap: 8px;
					padding: 12px 0;
					cursor: pointer;
				}
				.magick-ai-adapter-connection .maa-inline-disclosure > summary strong {
					font-size: 14px;
				}
				.magick-ai-adapter-connection .maa-inline-disclosure > summary::after {
					content: "+";
					color: #2271b1;
					font-size: 18px;
					font-weight: 600;
				}
				.magick-ai-adapter-connection .maa-inline-disclosure[open] > summary::after {
					content: "-";
				}
				.magick-ai-adapter-connection code,
				.magick-ai-adapter-connection pre,
				.magick-ai-adapter-connection textarea {
					font-family: Consolas, Monaco, monospace;
				}
				.magick-ai-adapter-connection code {
					overflow-wrap: anywhere;
					word-break: break-word;
				}
				.magick-ai-adapter-connection pre {
					overflow: auto;
					margin: 10px 0 0;
					padding: 12px;
					background: #f6f7f7;
					border: 1px solid #dcdcde;
					white-space: pre-wrap;
				}
				.magick-ai-adapter-connection textarea {
					width: 100%;
					min-height: 180px;
				}
				.magick-ai-adapter-connection input[type="text"] {
					box-sizing: border-box;
					max-width: 100%;
					width: 100%;
				}
				.magick-ai-adapter-connection .maa-copy-row {
					display: grid;
					grid-template-columns: minmax(0, 1fr) auto;
					gap: 8px;
					align-items: center;
					margin: 14px 0;
				}
				.magick-ai-adapter-connection .maa-copy-row > div {
					min-width: 0;
				}
				.magick-ai-adapter-connection .maa-command-row {
					grid-template-columns: 1fr;
					margin: 16px 0;
				}
				.magick-ai-adapter-connection .maa-command-row .button {
					justify-self: start;
				}
				.magick-ai-adapter-connection .maa-copy-value {
					display: block;
					padding: 8px 10px;
					background: #f6f7f7;
					border: 1px solid #dcdcde;
				}
				.magick-ai-adapter-connection .maa-inline-note {
					margin: 8px 0 0;
					color: #646970;
				}
				.magick-ai-adapter-connection .maa-option {
					margin: 16px 0;
				}
				.magick-ai-adapter-connection .maa-option label {
					display: flex;
					gap: 8px;
					align-items: flex-start;
					font-weight: 600;
				}
				.magick-ai-adapter-connection .maa-option input {
					margin-top: 2px;
				}
				.magick-ai-adapter-connection .maa-route-list {
					margin: 0;
				}
				.magick-ai-adapter-connection .maa-route-list li {
					margin-bottom: 8px;
				}
				.magick-ai-adapter-connection .maa-form-actions {
					margin: 12px 0 0;
				}
				.magick-ai-adapter-connection .maa-status-table th {
					width: 160px;
				}
				.magick-ai-adapter-connection .maa-action-row {
					display: flex;
					flex-wrap: wrap;
					gap: 8px;
					align-items: center;
					margin-top: 12px;
				}
				.magick-ai-adapter-connection .maa-advanced-group {
					margin-top: 16px;
					padding-top: 14px;
					border-top: 1px solid #dcdcde;
				}
				.magick-ai-adapter-connection .maa-advanced-group:first-of-type {
					margin-top: 0;
					padding-top: 0;
					border-top: 0;
				}
				@media (max-width: 960px) {
					.magick-ai-adapter-connection .maa-workspace {
						grid-template-columns: 1fr;
					}
				}
				@media (max-width: 782px) {
					.magick-ai-adapter-connection .maa-summary {
						display: block;
					}
					.magick-ai-adapter-connection .maa-summary-item {
						margin: 8px 0;
					}
					.magick-ai-adapter-connection .maa-summary-item:first-child {
						padding-right: 0;
						border-right: 0;
					}
				}
				@media (max-width: 480px) {
					.magick-ai-adapter-connection .maa-copy-row {
						grid-template-columns: 1fr;
					}
				}
			</style>

			<div class="maa-summary">
				<div class="maa-summary-item">
					<span class="maa-label"><?php echo esc_html__( 'Status', 'magick-ai-adapter' ); ?></span>
					<span class="maa-status maa-status-<?php echo esc_attr( $status['level'] ); ?>"><?php echo esc_html( $status['label'] ); ?></span>
				</div>
				<div class="maa-summary-item">
					<span class="maa-label"><?php echo esc_html__( 'Core capabilities', 'magick-ai-adapter' ); ?></span>
					<span class="maa-value"><?php echo ! empty( $health['core_capabilities'] ) ? esc_html__( 'Available', 'magick-ai-adapter' ) : esc_html__( 'Missing', 'magick-ai-adapter' ); ?></span>
				</div>
				<div class="maa-summary-item">
					<span class="maa-label"><?php echo esc_html__( 'Abilities API', 'magick-ai-adapter' ); ?></span>
					<span class="maa-value"><?php echo ! empty( $health['abilities_catalog'] ) ? esc_html__( 'Available', 'magick-ai-adapter' ) : esc_html__( 'Missing', 'magick-ai-adapter' ); ?></span>
				</div>
				<div class="maa-summary-item">
					<span class="maa-label"><?php echo esc_html__( 'Write execution', 'magick-ai-adapter' ); ?></span>
					<span class="maa-value"><?php echo esc_html__( 'Proposal required', 'magick-ai-adapter' ); ?></span>
				</div>
			</div>

			<div class="maa-workspace">
				<div class="maa-section maa-section-highlight">
					<h2><?php echo esc_html__( 'Simple connection', 'magick-ai-adapter' ); ?></h2>
					<p class="maa-section-intro"><?php echo esc_html__( 'Use when the client has a dedicated secret field.', 'magick-ai-adapter' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::CREATE_ACTION ); ?>" />
						<?php wp_nonce_field( self::CREATE_ACTION ); ?>
						<p>
							<label for="magick-ai-adapter-password-name-compact"><span class="maa-label"><?php echo esc_html__( 'Application name', 'magick-ai-adapter' ); ?></span></label>
							<input id="magick-ai-adapter-password-name-compact" class="regular-text" type="text" name="application_name" value="OpenClaw via Magick AI Adapter" />
						</p>
						<div class="maa-option">
							<label>
								<input type="checkbox" name="include_local_tls" value="1" <?php checked( $this->is_local_url( home_url() ) ); ?> />
								<span><?php echo esc_html__( 'Include LocalWP TLS setting', 'magick-ai-adapter' ); ?></span>
							</label>
							<p class="description"><?php echo esc_html__( 'LocalWP TLS option. Use only for localhost or .local testing.', 'magick-ai-adapter' ); ?></p>
						</div>
						<p class="maa-form-actions">
							<button type="submit" class="button button-primary" <?php disabled( ! $can_create_password ); ?>>
								<?php echo esc_html__( 'Create Application Password connection', 'magick-ai-adapter' ); ?>
							</button>
						</p>
						<?php if ( ! $can_create_password ) : ?>
							<p class="description"><?php echo esc_html__( 'Application Passwords are not available for this user or site.', 'magick-ai-adapter' ); ?></p>
						<?php endif; ?>
					</form>
					<p class="description"><?php echo esc_html__( 'The password is shown once. Store it only in the client secret field.', 'magick-ai-adapter' ); ?></p>
				</div>

				<div class="maa-section">
					<h2><?php echo esc_html__( 'Connection values', 'magick-ai-adapter' ); ?></h2>
					<div class="maa-copy-row">
						<div>
							<span class="maa-label"><?php echo esc_html__( 'Adapter Base URL', 'magick-ai-adapter' ); ?></span>
							<code class="maa-copy-value" id="maa-base-url"><?php echo esc_html( $base_url ); ?></code>
						</div>
						<button type="button" class="button maa-copy-button" data-maa-copy-target="maa-base-url"><?php echo esc_html__( 'Copy', 'magick-ai-adapter' ); ?></button>
					</div>
					<div class="maa-copy-row">
						<div>
							<span class="maa-label"><?php echo esc_html__( 'WordPress user', 'magick-ai-adapter' ); ?></span>
							<code class="maa-copy-value" id="maa-username"><?php echo esc_html( $username ); ?></code>
						</div>
						<button type="button" class="button maa-copy-button" data-maa-copy-target="maa-username"><?php echo esc_html__( 'Copy', 'magick-ai-adapter' ); ?></button>
					</div>
					<div class="maa-copy-row">
						<div>
							<span class="maa-label"><?php echo esc_html__( 'Connection manifest', 'magick-ai-adapter' ); ?></span>
							<code class="maa-copy-value" id="maa-manifest-url"><?php echo esc_html( $manifest_url ); ?></code>
						</div>
						<button type="button" class="button maa-copy-button" data-maa-copy-target="maa-manifest-url"><?php echo esc_html__( 'Copy manifest URL', 'magick-ai-adapter' ); ?></button>
					</div>
					<div class="maa-copy-row">
						<div>
							<span class="maa-label"><?php echo esc_html__( 'Client env placeholder', 'magick-ai-adapter' ); ?></span>
							<p class="maa-inline-note"><?php echo esc_html__( 'Copies the Adapter URL, username, and password placeholder only.', 'magick-ai-adapter' ); ?></p>
							<textarea id="maa-client-config" hidden readonly><?php echo esc_textarea( $client_config ); ?></textarea>
						</div>
						<button type="button" class="button maa-copy-button" data-maa-copy-target="maa-client-config"><?php echo esc_html__( 'Copy env placeholder', 'magick-ai-adapter' ); ?></button>
					</div>
					<p class="description"><?php echo esc_html__( 'Writes require Core proposal approval before Adapter execution.', 'magick-ai-adapter' ); ?></p>
				</div>
			</div>

			<details class="maa-section">
				<summary>
					<strong><?php echo esc_html__( 'Higher security: signed key-pair', 'magick-ai-adapter' ); ?></strong>
					<span class="description"><?php echo esc_html__( 'Recommended when the client should not receive an Application Password.', 'magick-ai-adapter' ); ?></span>
				</summary>
				<p><?php echo esc_html__( 'Run this in the same environment as OpenClaw. The private key stays local and Adapter stores only the approved public key.', 'magick-ai-adapter' ); ?></p>
				<div class="maa-copy-row maa-command-row">
					<div>
						<span class="maa-label"><?php echo esc_html__( 'Connect command', 'magick-ai-adapter' ); ?></span>
						<code class="maa-copy-value" id="maa-local-cli-connect-command"><?php echo esc_html( $local_cli_connect_command ); ?></code>
					</div>
					<button type="button" class="button maa-copy-button" data-maa-copy-target="maa-local-cli-connect-command"><?php echo esc_html__( 'Copy connect command', 'magick-ai-adapter' ); ?></button>
				</div>
				<div class="maa-copy-row maa-command-row">
					<div>
						<span class="maa-label"><?php echo esc_html__( 'Status command', 'magick-ai-adapter' ); ?></span>
						<code class="maa-copy-value" id="maa-local-cli-status-command"><?php echo esc_html( $local_cli_status_command ); ?></code>
					</div>
					<button type="button" class="button maa-copy-button" data-maa-copy-target="maa-local-cli-status-command"><?php echo esc_html__( 'Copy status command', 'magick-ai-adapter' ); ?></button>
				</div>
				<details class="maa-inline-disclosure">
					<summary>
						<strong><?php echo esc_html__( 'Full OpenClaw instructions', 'magick-ai-adapter' ); ?></strong>
						<span class="description"><?php echo esc_html__( 'Copy only when the client needs the longer setup text.', 'magick-ai-adapter' ); ?></span>
					</summary>
					<p class="description"><?php echo esc_html__( 'Do not ask OpenClaw to read the local keypair profile file. Writes still require Core proposal, approval, and preflight.', 'magick-ai-adapter' ); ?></p>
					<textarea id="maa-local-cli-setup" rows="14" readonly><?php echo esc_textarea( $local_cli_setup ); ?></textarea>
					<p class="maa-action-row">
						<button type="button" class="button maa-copy-button" data-maa-copy-target="maa-local-cli-setup"><?php echo esc_html__( 'Copy OpenClaw CLI instructions', 'magick-ai-adapter' ); ?></button>
					</p>
				</details>
			</details>

			<details class="maa-section"<?php echo '' !== $lookup_id ? ' open' : ''; ?>>
				<summary>
					<strong><?php echo esc_html__( 'Proposal lookup', 'magick-ai-adapter' ); ?></strong>
					<span class="description"><?php echo esc_html__( 'Check a proposal after OpenClaw creates one.', 'magick-ai-adapter' ); ?></span>
				</summary>
				<p><?php echo esc_html__( 'Use the Proposal ID returned to OpenClaw to check Core status, open the Core approval screen, and continue execution from Adapter after approval.', 'magick-ai-adapter' ); ?></p>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
					<p>
						<label for="magick-ai-adapter-proposal-lookup"><span class="maa-label"><?php echo esc_html__( 'Proposal ID', 'magick-ai-adapter' ); ?></span></label>
						<input id="magick-ai-adapter-proposal-lookup" class="regular-text" type="text" name="adapter_proposal_id" value="<?php echo esc_attr( $lookup_id ); ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" />
					</p>
					<p class="maa-form-actions">
						<button type="submit" class="button"><?php echo esc_html__( 'Check status', 'magick-ai-adapter' ); ?></button>
						<a class="button" href="<?php echo esc_url( rest_url( Controller::NAMESPACE . '/proposals' ) ); ?>"><?php echo esc_html__( 'Open proposal API', 'magick-ai-adapter' ); ?></a>
					</p>
				</form>
				<?php $this->render_proposal_lookup_result( $lookup_id, $lookup_result ); ?>
			</details>

			<details class="maa-section">
				<summary>
					<strong><?php echo esc_html__( 'Advanced', 'magick-ai-adapter' ); ?></strong>
					<span class="description"><?php echo esc_html__( 'Diagnostics, route catalog, examples, and boundary notes.', 'magick-ai-adapter' ); ?></span>
				</summary>

				<div class="maa-advanced-group">
					<h3><?php echo esc_html__( 'Diagnostics URLs', 'magick-ai-adapter' ); ?></h3>
					<p><span class="maa-label"><?php echo esc_html__( 'Health', 'magick-ai-adapter' ); ?></span><code><?php echo esc_html( $health_url ); ?></code></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Help', 'magick-ai-adapter' ); ?></span><code><?php echo esc_html( $help_url ); ?></code></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Capabilities', 'magick-ai-adapter' ); ?></span><code><?php echo esc_html( $capabilities_url ); ?></code></p>
				</div>

				<div class="maa-advanced-group">
					<h3><?php echo esc_html__( 'Key pair clients', 'magick-ai-adapter' ); ?></h3>
					<p><?php echo esc_html__( 'Device-paired clients sign Adapter requests.', 'magick-ai-adapter' ); ?></p>
					<p><?php echo esc_html__( 'Phase 2 clients generate an Ed25519 key locally. Adapter stores only the public key after WordPress admin approval.', 'magick-ai-adapter' ); ?></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Manifest', 'magick-ai-adapter' ); ?></span><code><?php echo esc_html( $manifest_url ); ?></code></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Key pairs', 'magick-ai-adapter' ); ?></span><code><?php echo esc_html( $key_pairs_url ); ?></code></p>
					<p><?php echo esc_html__( 'Revoke a public key to stop the matching local profile from authenticating. Adapter never stores the private key.', 'magick-ai-adapter' ); ?></p>
					<?php $this->render_key_pair_clients_table( $key_records ); ?>
				</div>

				<div class="maa-advanced-group">
					<h3><?php echo esc_html__( 'Route catalog', 'magick-ai-adapter' ); ?></h3>
					<p><strong><?php echo esc_html__( 'Proposal routes', 'magick-ai-adapter' ); ?></strong></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Proposal list', 'magick-ai-adapter' ); ?></span><code><?php echo esc_html( rest_url( Controller::NAMESPACE . '/proposals' ) ); ?></code></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Proposal detail', 'magick-ai-adapter' ); ?></span><code><?php echo esc_html( rest_url( Controller::NAMESPACE . '/proposals/{proposal_id}' ) ); ?></code></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Plan to proposals', 'magick-ai-adapter' ); ?></span><code><?php echo esc_html( rest_url( Controller::NAMESPACE . '/proposals/from-plan' ) ); ?></code></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Commit preflight', 'magick-ai-adapter' ); ?></span><code><?php echo esc_html( rest_url( Controller::NAMESPACE . '/proposals/{proposal_id}/commit-preflight' ) ); ?></code></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Approve and execute', 'magick-ai-adapter' ); ?></span><code><?php echo esc_html( rest_url( Controller::NAMESPACE . '/proposals/{proposal_id}/approve-and-execute' ) ); ?></code></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Approval disabled stub', 'magick-ai-adapter' ); ?></span><code><?php echo esc_html( rest_url( Controller::NAMESPACE . '/proposals/{proposal_id}/approve' ) ); ?></code></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Reject disabled stub', 'magick-ai-adapter' ); ?></span><code><?php echo esc_html( rest_url( Controller::NAMESPACE . '/proposals/{proposal_id}/reject' ) ); ?></code></p>
					<h3><?php echo esc_html__( 'Read shortcuts', 'magick-ai-adapter' ); ?></h3>
					<ul class="maa-route-list">
						<?php foreach ( $shortcuts as $route => $ability_id ) : ?>
							<li><code><?php echo esc_html( 'GET /wp-json/' . Controller::NAMESPACE . '/' . $route ); ?></code><br><span class="description"><?php echo esc_html( $ability_id ); ?></span></li>
						<?php endforeach; ?>
					</ul>
				</div>

				<div class="maa-advanced-group">
					<h3><?php echo esc_html__( 'Example requests', 'magick-ai-adapter' ); ?></h3>
					<p><?php echo esc_html__( 'Use a dedicated administrator Application Password. Paste the password only into OpenClaw dedicated secret field, never into chat, tools, files, logs, or proposals.', 'magick-ai-adapter' ); ?></p>
					<pre><?php echo esc_html( $example_request ); ?></pre>
					<pre><?php echo esc_html( $proposal_request ); ?></pre>
					<pre><?php echo esc_html( $proposal_status_request ); ?></pre>
				</div>

				<div class="maa-advanced-group">
					<h3><?php echo esc_html__( 'Handoff prompt', 'magick-ai-adapter' ); ?></h3>
					<textarea readonly><?php echo esc_textarea( $handoff_prompt ); ?></textarea>
				</div>

				<div class="maa-advanced-group">
					<h3><?php echo esc_html__( 'Boundary', 'magick-ai-adapter' ); ?></h3>
					<p><?php echo esc_html__( 'OpenClaw only connects to Adapter. Core approval admin is the human governance surface behind Adapter. Reads run only when Core marks an ability as direct_read on wp_abilities_rest. Writes create Core proposals and stop at commit preflight.', 'magick-ai-adapter' ); ?></p>
					<p><code>approval_proxy_enabled=false</code></p>
					<p><code>core_proxy_execute=false</code></p>
					<p><code>commit_execution=false</code></p>
				</div>
			</details>

			<script>
				(function () {
					var root = document.querySelector('.magick-ai-adapter-connection');
					if (!root) {
						return;
					}

					root.querySelectorAll('[data-maa-copy-target]').forEach(function (button) {
						button.addEventListener('click', function () {
							var target = document.getElementById(button.getAttribute('data-maa-copy-target'));
							var text = target ? (target.value || target.textContent || '') : '';
							if (!text || !window.navigator.clipboard) {
								return;
							}

							window.navigator.clipboard.writeText(text).then(function () {
								var oldText = button.textContent;
								button.textContent = '<?php echo esc_js( __( 'Copied', 'magick-ai-adapter' ) ); ?>';
								window.setTimeout(function () {
									button.textContent = oldText;
								}, 1500);
							});
						});
					});
				})();
			</script>
		</div>
		<?php
	}

	/**
	 * Renders the WordPress admin device pairing approval page.
	 *
	 * @return void
	 */
	public function render_pairing_page(): void {
		if ( ! current_user_can( self::MENU_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to approve device pairing.', 'magick-ai-adapter' ) );
		}

		$user_code = isset( $_GET['user_code'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_GET['user_code'] ) ) ) : '';
		$pairing   = ( new Controller() )->admin_device_pairing( $user_code );
		$client    = is_array( $pairing['client'] ?? null ) ? $pairing['client'] : array();
		$key       = is_array( $pairing['key'] ?? null ) ? $pairing['key'] : array();
		$scopes    = is_array( $pairing['scopes'] ?? null ) ? $pairing['scopes'] : array();
		$status    = (string) ( $pairing['status'] ?? 'pending' );
		?>
		<div class="wrap magick-ai-adapter-connection">
			<h1><?php echo esc_html__( 'Approve Magick AI Client', 'magick-ai-adapter' ); ?></h1>
			<?php if ( empty( $pairing ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html__( 'Device pairing code was not found or has expired.', 'magick-ai-adapter' ); ?></p></div>
			<?php else : ?>
				<?php if ( 'approved' === $status ) : ?>
					<div class="notice notice-success">
						<p><strong><?php echo esc_html__( 'Connection approved.', 'magick-ai-adapter' ); ?></strong></p>
						<p><?php echo esc_html__( 'Return to the terminal or local AI client. The client will finish polling and save its local profile. Adapter stores only the public key; the private key was never sent to WordPress.', 'magick-ai-adapter' ); ?></p>
					</div>
				<?php elseif ( 'rejected' === $status ) : ?>
					<div class="notice notice-warning">
						<p><strong><?php echo esc_html__( 'Connection rejected.', 'magick-ai-adapter' ); ?></strong></p>
						<p><?php echo esc_html__( 'Return to the terminal or local AI client. The client will stop polling with a rejected status.', 'magick-ai-adapter' ); ?></p>
					</div>
				<?php else : ?>
					<p><?php echo esc_html__( 'Approve this local AI client only if you initiated the connection. Adapter stores only the public key; the private key stays on your computer.', 'magick-ai-adapter' ); ?></p>
				<?php endif; ?>
				<table class="widefat striped" style="max-width: 860px;">
					<tbody>
						<tr><th scope="row"><?php echo esc_html__( 'User code', 'magick-ai-adapter' ); ?></th><td><code><?php echo esc_html( $user_code ); ?></code></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Client', 'magick-ai-adapter' ); ?></th><td><?php echo esc_html( (string) ( $client['name'] ?? '' ) ); ?></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Device', 'magick-ai-adapter' ); ?></th><td><?php echo esc_html( (string) ( $client['device_name'] ?? '' ) ); ?></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Broker', 'magick-ai-adapter' ); ?></th><td><?php echo esc_html( trim( (string) ( $client['broker'] ?? '' ) . ' ' . (string) ( $client['broker_version'] ?? '' ) ) ); ?></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Fingerprint', 'magick-ai-adapter' ); ?></th><td><code><?php echo esc_html( (string) ( $key['fingerprint'] ?? '' ) ); ?></code></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Scopes', 'magick-ai-adapter' ); ?></th><td><?php echo esc_html( implode( ', ', $scopes ) ); ?></td></tr>
						<?php if ( 'approved' === $status ) : ?>
							<tr><th scope="row"><?php echo esc_html__( 'Connection ID', 'magick-ai-adapter' ); ?></th><td><code><?php echo esc_html( (string) ( $pairing['connection_id'] ?? '' ) ); ?></code></td></tr>
							<tr><th scope="row"><?php echo esc_html__( 'Key ID', 'magick-ai-adapter' ); ?></th><td><code><?php echo esc_html( (string) ( $pairing['key_id'] ?? '' ) ); ?></code></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
				<?php if ( 'pending' === $status ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 16px;">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::PAIR_ACTION ); ?>" />
						<input type="hidden" name="user_code" value="<?php echo esc_attr( $user_code ); ?>" />
						<?php wp_nonce_field( self::PAIR_ACTION . '_' . $user_code ); ?>
						<button type="submit" name="decision" value="approve" class="button button-primary"><?php echo esc_html__( 'Approve connection', 'magick-ai-adapter' ); ?></button>
						<button type="submit" name="decision" value="reject" class="button"><?php echo esc_html__( 'Reject', 'magick-ai-adapter' ); ?></button>
					</form>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handles device pairing approval or rejection.
	 *
	 * @return void
	 */
	public function handle_pairing_decision(): void {
		if ( ! current_user_can( self::MENU_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to approve device pairing.', 'magick-ai-adapter' ) );
		}

		$user_code = isset( $_POST['user_code'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_POST['user_code'] ) ) ) : '';
		check_admin_referer( self::PAIR_ACTION . '_' . $user_code );

		$decision   = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( (string) $_POST['decision'] ) ) : '';
		$controller = new Controller();
		if ( 'approve' === $decision ) {
			$result = $controller->approve_device_pairing( $user_code );
			if ( is_wp_error( $result ) ) {
				wp_die( esc_html( $result->get_error_message() ) );
			}
			$result_status = 'approved';
		} else {
			$controller->reject_device_pairing( $user_code );
			$result_status = 'rejected';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => self::PAIR_MENU_SLUG,
					'user_code' => $user_code,
					'result'    => $result_status,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handles client key revocation from the admin page.
	 *
	 * @return void
	 */
	public function handle_revoke_client_key(): void {
		if ( ! current_user_can( self::MENU_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to revoke client keys.', 'magick-ai-adapter' ) );
		}

		$key_id = isset( $_POST['key_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['key_id'] ) ) : '';
		check_admin_referer( self::REVOKE_KEY_ACTION . '_' . $key_id );

		$result = ( new Controller() )->revoke_client_key_by_id( $key_id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG ) );
		exit;
	}

	/**
	 * Handles OpenClaw Application Password creation.
	 *
	 * @return void
	 */
	public function handle_create_openclaw_password(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to create OpenClaw handoff credentials.', 'magick-ai-adapter' ) );
		}

		check_admin_referer( self::CREATE_ACTION );

		if ( ! $this->can_create_application_password() || ! class_exists( 'WP_Application_Passwords' ) ) {
			wp_die( esc_html__( 'Application Passwords are not available for this user or site.', 'magick-ai-adapter' ) );
		}

		$user_id            = get_current_user_id();
		$application_name   = isset( $_POST['application_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['application_name'] ) ) : '';
		$application_name   = '' !== $application_name ? $application_name : 'OpenClaw via Magick AI Adapter';
		$include_local_tls  = ! empty( $_POST['include_local_tls'] );
		$created            = \WP_Application_Passwords::create_new_application_password(
			$user_id,
			array(
				'name'   => $application_name,
				'app_id' => wp_generate_uuid4(),
			)
		);

		if ( is_wp_error( $created ) ) {
			$this->render_created_handoff_error( $created );
			exit;
		}

		status_header( 200 );
		nocache_headers();
		$this->render_created_handoff( (string) $created[0], is_array( $created[1] ?? null ) ? $created[1] : array(), $include_local_tls );
		exit;
	}

	/**
	 * Returns current adapter health data.
	 *
	 * @return array<string,mixed>
	 */
	private function health(): array {
		$request  = new WP_REST_Request( 'GET', '/' . Controller::NAMESPACE . '/health' );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Converts health into one status row.
	 *
	 * @param array<string,mixed> $health Health response.
	 * @return array{level:string,label:string}
	 */
	private function status( array $health ): array {
		if ( empty( $health ) ) {
			return array(
				'level' => 'error',
				'label' => __( 'Unavailable', 'magick-ai-adapter' ),
			);
		}

		if ( ! empty( $health['core_capabilities'] ) && ! empty( $health['abilities_catalog'] ) ) {
			return array(
				'level' => 'ok',
				'label' => __( 'Ready', 'magick-ai-adapter' ),
			);
		}

		return array(
			'level' => 'warning',
			'label' => __( 'Needs dependencies', 'magick-ai-adapter' ),
		);
	}

	/**
	 * Looks up one Core proposal through Adapter's read-only status route.
	 *
	 * @param string $proposal_id Proposal id.
	 * @return array<string,mixed>|WP_Error
	 */
	private function proposal_lookup( string $proposal_id ) {
		$request  = new WP_REST_Request( 'GET', '/' . Controller::NAMESPACE . '/proposals/' . rawurlencode( $proposal_id ) );
		$response = rest_do_request( $request );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = $response->get_data();
		if ( $response->get_status() >= 400 ) {
			return new WP_Error(
				'magick_ai_adapter_proposal_lookup_failed',
				__( 'Adapter could not read this Core proposal status.', 'magick-ai-adapter' ),
				array(
					'status' => $response->get_status(),
					'data'   => $data,
				)
			);
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Renders proposal lookup results for the Adapter connection page.
	 *
	 * @param string                         $proposal_id Proposal id.
	 * @param array<string,mixed>|WP_Error|null $result Lookup result.
	 * @return void
	 */
	private function render_proposal_lookup_result( string $proposal_id, $result ): void {
		if ( '' === $proposal_id ) {
			?>
			<p class="maa-inline-note"><?php echo esc_html__( 'After OpenClaw creates a proposal, paste its Proposal ID here. Pending decisions stay in Core; Adapter handles status polling and approved execution routes.', 'magick-ai-adapter' ); ?></p>
			<?php
			return;
		}

		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$status = is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;
			?>
			<div class="notice notice-error inline">
				<p><strong><?php echo esc_html__( 'Proposal not available.', 'magick-ai-adapter' ); ?></strong></p>
				<p><?php echo esc_html( $result->get_error_message() ); ?><?php echo $status > 0 ? ' ' . esc_html( sprintf( __( 'HTTP %d', 'magick-ai-adapter' ), $status ) ) : ''; ?></p>
			</div>
			<?php
			return;
		}

		$proposal = is_array( $result ) ? $result : array();
		$status   = sanitize_key( (string) ( $proposal['status'] ?? '' ) );
		$ability  = (string) ( $proposal['ability_id'] ?? '' );
		$title    = (string) ( $proposal['title'] ?? '' );
		$created  = (string) ( $proposal['created_at'] ?? '' );
		$updated  = (string) ( $proposal['updated_at'] ?? '' );
		$timeline = is_array( $proposal['audit_timeline'] ?? null ) ? $proposal['audit_timeline'] : array();
		$core_url = add_query_arg(
			array(
				'page'        => 'magick-ai-core',
				'proposal_id' => $proposal_id,
			),
			admin_url( 'admin.php' )
		);
		$status_url = rest_url( Controller::NAMESPACE . '/proposals/' . rawurlencode( $proposal_id ) );
		$execute_url = rest_url( Controller::NAMESPACE . '/proposals/' . rawurlencode( $proposal_id ) . '/execute' );
		$approve_execute_url = rest_url( Controller::NAMESPACE . '/proposals/' . rawurlencode( $proposal_id ) . '/approve-and-execute' );
		?>
		<table class="widefat striped maa-status-table" style="margin-top: 14px;">
			<tbody>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Proposal ID', 'magick-ai-adapter' ); ?></th>
					<td><code><?php echo esc_html( $proposal_id ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Status', 'magick-ai-adapter' ); ?></th>
					<td><span class="maa-status maa-status-<?php echo esc_attr( $this->proposal_status_level( $status ) ); ?>"><?php echo esc_html( '' !== $status ? $status : __( 'unknown', 'magick-ai-adapter' ) ); ?></span></td>
				</tr>
				<?php if ( '' !== $title ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Title', 'magick-ai-adapter' ); ?></th>
						<td><?php echo esc_html( $title ); ?></td>
					</tr>
				<?php endif; ?>
				<?php if ( '' !== $ability ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Ability', 'magick-ai-adapter' ); ?></th>
						<td><code><?php echo esc_html( $ability ); ?></code></td>
					</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Created', 'magick-ai-adapter' ); ?></th>
					<td><?php echo esc_html( '' !== $created ? $created : __( 'unknown', 'magick-ai-adapter' ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Updated', 'magick-ai-adapter' ); ?></th>
					<td><?php echo esc_html( '' !== $updated ? $updated : __( 'unknown', 'magick-ai-adapter' ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Audit timeline', 'magick-ai-adapter' ); ?></th>
					<td><?php echo esc_html( sprintf( __( '%d events', 'magick-ai-adapter' ), count( $timeline ) ) ); ?></td>
				</tr>
			</tbody>
		</table>
		<div class="maa-action-row">
			<a class="button button-primary" href="<?php echo esc_url( $core_url ); ?>"><?php echo esc_html__( 'Open in Core', 'magick-ai-adapter' ); ?></a>
			<button type="button" class="button maa-copy-button" data-maa-copy-target="maa-proposal-status-url"><?php echo esc_html__( 'Copy status URL', 'magick-ai-adapter' ); ?></button>
			<button type="button" class="button maa-copy-button" data-maa-copy-target="maa-proposal-execute-url"><?php echo esc_html__( 'Copy execute URL', 'magick-ai-adapter' ); ?></button>
		</div>
		<p class="maa-inline-note"><?php echo esc_html( $this->proposal_next_step_text( $status ) ); ?></p>
		<textarea id="maa-proposal-status-url" hidden readonly><?php echo esc_textarea( $status_url ); ?></textarea>
		<textarea id="maa-proposal-execute-url" hidden readonly><?php echo esc_textarea( 'approved' === $status ? $execute_url : $approve_execute_url ); ?></textarea>
		<?php
	}

	/**
	 * Maps Core proposal status to Adapter UI status level.
	 *
	 * @param string $status Proposal status.
	 * @return string
	 */
	private function proposal_status_level( string $status ): string {
		if ( 'approved' === $status ) {
			return 'ok';
		}

		if ( in_array( $status, array( 'rejected', 'expired', 'archived' ), true ) ) {
			return 'error';
		}

		return 'warning';
	}

	/**
	 * Returns the next operator action for a Core proposal status.
	 *
	 * @param string $status Proposal status.
	 * @return string
	 */
	private function proposal_next_step_text( string $status ): string {
		if ( 'pending' === $status ) {
			return __( 'Next step: review this proposal in Core. Adapter should keep polling status and execute only after Core approval and commit preflight.', 'magick-ai-adapter' );
		}

		if ( 'approved' === $status ) {
			return __( 'Next step: execute through Adapter. Adapter will still call Core commit preflight before any allowlisted WordPress ability execution.', 'magick-ai-adapter' );
		}

		if ( 'rejected' === $status ) {
			return __( 'Next step: stop. Adapter should show the rejection and must not execute this proposal.', 'magick-ai-adapter' );
		}

		if ( in_array( $status, array( 'expired', 'archived' ), true ) ) {
			return __( 'Next step: reopen or inspect this proposal in Core if it still needs a decision.', 'magick-ai-adapter' );
		}

		return __( 'Next step: use Core as the approval truth and Adapter as the OpenClaw status and execution channel.', 'magick-ai-adapter' );
	}

	/**
	 * Builds a curl health example.
	 *
	 * @param string $health_url Health URL.
	 * @return string
	 */
	private function example_request( string $health_url ): string {
		return 'curl -sS --user "OPENCLAW_USERNAME:<openclaw-secret-field-value>" \\' . "\n"
			. '  ' . $health_url;
	}

	/**
	 * Renders one-time OpenClaw credential handoff.
	 *
	 * @param string              $password Application Password.
	 * @param array<string,mixed> $item Application Password item.
	 * @param bool                $include_local_tls Whether to include local TLS hints.
	 * @return void
	 */
	private function render_created_handoff( string $password, array $item, bool $include_local_tls ): void {
		$user          = wp_get_current_user();
		$username      = $user->exists() ? (string) $user->user_login : '';
		$base_url      = rest_url( Controller::NAMESPACE );
		$password_uuid = (string) ( $item['uuid'] ?? '' );
		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>" />
			<meta name="viewport" content="width=device-width, initial-scale=1" />
			<title><?php echo esc_html__( 'OpenClaw Handoff Created', 'magick-ai-adapter' ); ?></title>
			<style>
				body { margin: 0; background: #f0f0f1; color: #1d2327; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
				main { max-width: 960px; margin: 32px auto; padding: 0 24px; }
				h1 { font-size: 24px; margin: 0 0 16px; }
				.notice { background: #fff8e5; border-left: 4px solid #dba617; margin: 0 0 20px; padding: 12px 16px; }
				table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #c3c4c7; }
				th, td { border-bottom: 1px solid #dcdcde; padding: 12px; text-align: left; vertical-align: top; }
				th { width: 180px; font-weight: 600; }
				code, textarea { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
				textarea { box-sizing: border-box; width: 100%; min-height: 96px; padding: 10px; border: 1px solid #8c8f94; background: #fff; color: #1d2327; }
				.actions { margin-top: 20px; }
				.inline-actions { margin: 8px 0 0; }
				.button { display: inline-block; background: #2271b1; border: 1px solid #2271b1; border-radius: 3px; color: #fff; padding: 8px 14px; text-decoration: none; cursor: pointer; }
				@media (max-width: 720px) { main { padding: 0 12px; } th, td { display: block; width: auto; } }
			</style>
		</head>
		<body>
			<main>
				<h1><?php echo esc_html__( 'OpenClaw Handoff Created', 'magick-ai-adapter' ); ?></h1>
				<div class="notice">
					<p><?php echo esc_html__( 'Copy this Application Password now. WordPress shows it only once and stores only a hash.', 'magick-ai-adapter' ); ?></p>
					<p><?php echo esc_html__( 'Paste it only into OpenClaw dedicated secret field. Do not paste it into chat, tool commands, logs, proposal payloads, files, or copied handoff text.', 'magick-ai-adapter' ); ?></p>
					<p><?php echo esc_html__( 'Use this only for OpenClaw access through Magick AI Adapter. Revoke it from the WordPress user profile when the client is retired.', 'magick-ai-adapter' ); ?></p>
				</div>
				<table>
					<tbody>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Adapter URL', 'magick-ai-adapter' ); ?></th>
							<td><code><?php echo esc_html( $base_url ); ?></code></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'WordPress user', 'magick-ai-adapter' ); ?></th>
							<td><code><?php echo esc_html( $username ); ?></code></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Password UUID', 'magick-ai-adapter' ); ?></th>
							<td><code><?php echo esc_html( $password_uuid ); ?></code></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Application Password', 'magick-ai-adapter' ); ?></th>
							<td><textarea id="maa-application-password" rows="3" readonly><?php echo esc_textarea( $password ); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Connection manifest', 'magick-ai-adapter' ); ?></th>
							<td>
								<textarea id="maa-connection-manifest" rows="16" readonly><?php echo esc_textarea( $this->openclaw_connection_manifest_text( $username, $password_uuid ) ); ?></textarea>
								<p class="inline-actions"><button type="button" class="button" data-maa-created-copy-target="maa-connection-manifest"><?php echo esc_html__( 'Copy manifest', 'magick-ai-adapter' ); ?></button></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'OpenClaw env placeholder', 'magick-ai-adapter' ); ?></th>
							<td><textarea rows="6" readonly><?php echo esc_textarea( $this->openclaw_env_text( $username, $include_local_tls ) ); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'WorkBuddy setup', 'magick-ai-adapter' ); ?></th>
							<td>
								<textarea id="maa-workbuddy-setup" rows="18" readonly><?php echo esc_textarea( $this->workbuddy_handoff_text( $username, $password_uuid, $include_local_tls ) ); ?></textarea>
								<p class="inline-actions"><button type="button" class="button" data-maa-created-copy-target="maa-workbuddy-setup"><?php echo esc_html__( 'Copy WorkBuddy setup', 'magick-ai-adapter' ); ?></button></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'OpenClaw handoff', 'magick-ai-adapter' ); ?></th>
							<td><textarea rows="18" readonly><?php echo esc_textarea( $this->openclaw_created_handoff_text( $username, $password_uuid, $include_local_tls ) ); ?></textarea></td>
						</tr>
					</tbody>
				</table>
				<p class="actions"><a class="button" href="<?php echo esc_url( menu_page_url( self::MENU_SLUG, false ) ); ?>"><?php echo esc_html__( 'Back to Magick AI Adapter', 'magick-ai-adapter' ); ?></a></p>
			</main>
			<script>
				(function () {
					document.querySelectorAll('[data-maa-created-copy-target]').forEach(function (button) {
						button.addEventListener('click', function () {
							var target = document.getElementById(button.getAttribute('data-maa-created-copy-target'));
							var text = target ? (target.value || target.textContent || '') : '';
							if (!text || !window.navigator.clipboard) {
								return;
							}

							window.navigator.clipboard.writeText(text).then(function () {
								var oldText = button.textContent;
								button.textContent = '<?php echo esc_js( __( 'Copied', 'magick-ai-adapter' ) ); ?>';
								window.setTimeout(function () {
									button.textContent = oldText;
								}, 1500);
							});
						});
					});
				})();
			</script>
		</body>
		</html>
		<?php
	}

	/**
	 * Renders Application Password creation failure.
	 *
	 * @param WP_Error $error Error.
	 * @return void
	 */
	private function render_created_handoff_error( WP_Error $error ): void {
		$data   = $error->get_error_data();
		$status = is_array( $data ) ? (int) ( $data['status'] ?? 500 ) : 500;
		status_header( $status );
		wp_die( esc_html( $error->get_error_message() ) );
	}

	/**
	 * Builds a curl proposal example.
	 *
	 * @param string $proposal_url Proposal URL.
	 * @return string
	 */
	private function proposal_request( string $proposal_url ): string {
		return 'curl -sS --user "OPENCLAW_USERNAME:<openclaw-secret-field-value>" \\' . "\n"
			. '  -H "Content-Type: application/json" \\' . "\n"
			. '  -d \'{"ability_id":"magick-ai/create-draft","title":"Draft proposal","summary":"OpenClaw requests a governed draft proposal.","input":{"title":"OpenClaw draft","dry_run":true,"commit":false},"preview":{},"caller":{"external_thread_id":"OPENCLAW_THREAD_ID"}}\' \\' . "\n"
			. '  ' . $proposal_url;
	}

	/**
	 * Builds a curl proposal status example.
	 *
	 * @param string $proposal_detail_url Proposal detail URL.
	 * @return string
	 */
	private function proposal_status_request( string $proposal_detail_url ): string {
		return 'curl -sS --user "OPENCLAW_USERNAME:<openclaw-secret-field-value>" \\' . "\n"
			. '  ' . $proposal_detail_url;
	}

	/**
	 * Builds the OpenClaw handoff prompt.
	 *
	 * @param string $base_url Adapter base URL.
	 * @return string
	 */
	private function handoff_prompt( string $base_url ): string {
		return "Use this WordPress site through Magick AI Adapter.\n"
			. "Adapter base URL: {$base_url}\n"
			. "Authenticate with WordPress REST Basic Auth using the manifest username and an Application Password stored only in OpenClaw's dedicated secret field.\n"
			. "Do not paste the secret into chat, tool commands, logs, proposal payloads, files, or copied handoff text.\n"
			. "OpenClaw only connects to Adapter. Do not connect OpenClaw directly to Magick AI Core.\n"
			. "Start by calling GET /health, GET /help, and GET /capabilities.\n"
			. "For direct_read abilities, call the matching read shortcut or POST /run-read-ability with the real ability_id and input object.\n"
			. "For SEO/GEO/AEO suggestions, the primary entrypoint is content-discoverability-brief through openclaw_recipes.content_discoverability_suggestions: validate Toolbox context, read Toolbox context, build one content_discoverability_brief, and return suggestions only.\n"
			. "Use article-writing-pack only for broad natural-language article requests such as \"help me write an article\": follow openclaw_recipes.ai_article_draft_with_discoverability, draft from the returned ai_article_writing_pack, then use Core proposals for reviewed final writes.\n"
			. "For proposal_required abilities, POST /proposals with the real ability_id, input, preview, and caller metadata. For read-only planning outputs, POST /proposals/from-plan to let Core create governed proposals.\n"
			. "Poll GET /proposals/{proposal_id} for Core status. For the unified user action, call POST /proposals/{proposal_id}/approve-and-execute so Adapter calls Core approve, Core commit-preflight, and one allowlisted final write. If status=rejected, stop and show the rejection status. If status=approved and execution is intended, call POST /proposals/{proposal_id}/execute. Adapter execute routes are final write paths and normalize ability input to dry_run=false and commit=true. Use Adapter commit-preflight only as an advanced diagnostic step; for dry-run-only verification, stop at commit-preflight and do not call execute.\n"
			. "When you have proposal_id or commit-preflight correlation_id, pass them as log_context on POST /run-read-ability or as query fields on read shortcuts so Adapter can add them to AI Request Logs context through wpai_request_log_context. Core Governance Audit is the governance log; AI Request Logs are the provider request log. Adapter context includes ability_id, adapter_request_id, adapter_route, ai_provider, ai_model, governance_source=magick-ai-core, and nested magick_ai_core identifiers.\n"
			. "POST /proposals/{proposal_id}/approve and POST /proposals/{proposal_id}/reject are disabled stubs that return approval_proxy_enabled=false. The only Adapter approval path is POST /proposals/{proposal_id}/approve-and-execute, currently allowlisted for magick-ai/trash-post, magick-ai/create-draft, magick-ai/update-post, magick-ai/patch-post-content, magick-ai/patch-setting-value, magick-ai/set-post-seo-meta, magick-ai/set-post-slug, magick-ai/set-post-terms, magick-ai/delete-term, magick-ai/update-media-details, magick-ai/upload-media-from-url, magick-ai/set-post-featured-image, magick-ai/optimize-media-asset, magick-ai/replace-media-file, magick-ai/adopt-cloud-media-derivative, magick-ai/rename-media-file, magick-ai/delete-media-permanently, magick-ai/reply-comment, magick-ai/trash-comment, and magick-ai/approve-comment.\n"
			. "Handle failures by code: magick_ai_adapter_approval_proxy_disabled means use approve-and-execute or Core admin; magick_ai_adapter_execute_ability_not_allowed means stop because the ability is outside the Adapter execution allowlist; magick_ai_adapter_proposal_rejected means stop and show the rejection; magick_ai_adapter_preflight_not_authorized or magick_ai_adapter_preflight_item_blocked means stop and show Core preflight details.\n"
			. "Do not ask the adapter to store approval state, run workflows, batch destructive actions, or execute abilities outside the approve-and-execute allowlist. Preserve approval_proxy_enabled=false, core_proxy_execute=false, and commit_execution=false.";
	}

	/**
	 * Returns whether the current user can create an Application Password.
	 *
	 * @return bool
	 */
	private function can_create_application_password(): bool {
		if ( ! function_exists( 'wp_is_application_passwords_available_for_user' ) || ! class_exists( 'WP_Application_Passwords' ) ) {
			return false;
		}

		$user = wp_get_current_user();
		return $user->exists() && wp_is_application_passwords_available_for_user( $user );
	}

	/**
	 * Builds OpenClaw env text.
	 *
	 * @param string $username WordPress username.
	 * @param bool   $include_local_tls Whether to include local TLS hints.
	 * @return string
	 */
	private function openclaw_env_text( string $username, bool $include_local_tls ): string {
		$lines = array(
			'MAGICK_AI_ADAPTER_BASE_URL=' . rest_url( Controller::NAMESPACE ),
			'MAGICK_AI_ADAPTER_USERNAME=' . $username,
			'MAGICK_AI_ADAPTER_APPLICATION_PASSWORD=<store-in-openclaw-secret-vault>',
		);

		if ( $include_local_tls ) {
			$lines[] = 'MAGICK_AI_ADAPTER_INSECURE_SSL=true';
		}

		return implode( "\n", $lines );
	}

	/**
	 * Builds the non-secret OpenClaw connection manifest.
	 *
	 * @param string $username WordPress username.
	 * @param string $password_uuid Application Password UUID.
	 * @return string
	 */
	private function openclaw_connection_manifest_text( string $username, string $password_uuid ): string {
		$manifest = array(
			'connection_id'    => 'local-wordpress',
			'adapter_base_url' => rest_url( Controller::NAMESPACE ),
			'username'         => $username,
			'auth'             => array(
				'type'          => 'wordpress_application_password',
				'password_uuid' => $password_uuid,
			),
			'urls'             => array(
				'health'       => rest_url( Controller::NAMESPACE . '/health' ),
				'help'         => rest_url( Controller::NAMESPACE . '/help' ),
				'capabilities' => rest_url( Controller::NAMESPACE . '/capabilities' ),
			),
			'note'             => 'Secret must be stored through OpenClaw credential store or dedicated secret field, not chat, tools, files, logs, proposal payloads, or copied handoff text.',
		);

		$json = wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		return is_string( $json ) ? $json : '{}';
	}

	/**
	 * Builds one-time OpenClaw handoff text.
	 *
	 * @param string $username WordPress username.
	 * @param string $password_uuid Application Password UUID.
	 * @param bool   $include_local_tls Whether to include local TLS hints.
	 * @return string
	 */
	private function openclaw_created_handoff_text( string $username, string $password_uuid, bool $include_local_tls ): string {
		return "Magick AI Adapter OpenClaw connection\n"
			. "Connection manifest\n"
			. $this->openclaw_connection_manifest_text( $username, $password_uuid ) . "\n\n"
			. "Optional env placeholders\n"
			. $this->openclaw_env_text( $username, $include_local_tls ) . "\n\n"
			. "Secret handling\n"
			. "Paste the secret only into OpenClaw's dedicated secret field. Do not paste it into chat, tool commands, logs, proposal payloads, files, or copied handoff text.\n\n"
			. "Agent rules\n"
			. "1. Connect to Magick AI Adapter, not directly to Magick AI Core, for productized OpenClaw setup.\n"
			. "2. Authenticate with WordPress REST Basic Auth using the manifest username and the Application Password stored in OpenClaw's dedicated secret field.\n"
			. "3. Call GET /health first and require core_capabilities=true, abilities_catalog=true, approval_proxy_enabled=false, core_proxy_execute=false, and commit_execution=false.\n"
			. "4. Call GET /help to discover adapter routes, then GET /capabilities before reads or proposals and use only real ability_id values returned by Core.\n"
			. "5. For direct_read abilities, call a read shortcut or POST /run-read-ability.\n"
			. "5b. For SEO/GEO/AEO suggestions, the primary entrypoint is content-discoverability-brief: use content_discoverability_suggestions, call content-discoverability-validation, content-discoverability-context, then content-discoverability-brief for one post_id or supplied topic. Return suggestions only; do not write SEO meta, slug, excerpt, schema, media, or posts.\n"
			. "5c. Use article-writing-pack only for broad article requests like \"help me write an article\" or \"write an AI topic article\": use ai_article_draft_with_discoverability, draft only from the returned pack, and send any reviewed final write through Core proposal/preflight.\n"
			. "6. For proposal_required abilities, POST /proposals and poll GET /proposals/{proposal_id}. For read-only planning outputs, POST /proposals/from-plan.\n"
			. "7. For the unified user action, call POST /proposals/{proposal_id}/approve-and-execute. Adapter calls Core approve, Core commit-preflight, and one allowlisted final write. Current execution allowlist: magick-ai/trash-post, magick-ai/create-draft, magick-ai/update-post, magick-ai/patch-post-content, magick-ai/patch-setting-value, magick-ai/set-post-seo-meta, magick-ai/set-post-slug, magick-ai/set-post-terms, magick-ai/delete-term, magick-ai/update-media-details, magick-ai/upload-media-from-url, magick-ai/set-post-featured-image, magick-ai/optimize-media-asset, magick-ai/replace-media-file, magick-ai/adopt-cloud-media-derivative, magick-ai/rename-media-file, magick-ai/delete-media-permanently, magick-ai/reply-comment, magick-ai/trash-comment, magick-ai/approve-comment.\n"
			. "7b. If status=rejected, stop and show the rejection status. If status=approved and execution is intended, call POST /proposals/{proposal_id}/execute. Adapter execute routes are final write paths and normalize ability input to dry_run=false and commit=true. Use Adapter commit-preflight only as an advanced diagnostic step; for dry-run-only verification, stop at commit-preflight and do not call execute.\n"
			. "8. Pass proposal_id and correlation_id as log_context or read shortcut query fields so AI Request Logs can correlate execution rows with Core audit. Core Governance Audit is the governance log; AI Request Logs are the provider request log. For provider smoke, POST /ai-provider-log-correlation-smoke with a configured text generation ai_provider and ai_model after commit-preflight; local Ollama examples use ai_provider=ollama and ai_model=qwen3.5:0.8b when available.\n"
			. "9. Treat POST /proposals/{proposal_id}/approve and POST /proposals/{proposal_id}/reject as disabled stubs. Approval without execution is handled in Magick AI Core admin.\n"
			. "10. Failure code handling: magick_ai_adapter_approval_proxy_disabled => use approve-and-execute or Core admin; magick_ai_adapter_execute_ability_not_allowed => stop; magick_ai_adapter_proposal_rejected => stop; magick_ai_adapter_preflight_not_authorized or magick_ai_adapter_preflight_item_blocked => show Core preflight details and do not retry execution.\n"
			. "11. Do not ask the adapter to store approval state, run workflows, batch destructive actions, or execute abilities outside the approve-and-execute allowlist.\n"
			. "12. Do not execute writes without Core commit preflight.\n"
			. "13. Do not store or print the secret in logs, proposal payloads, prompts, files, or copied handoff text.\n\n"
			. "Example checks\n"
			. "curl -sS --user \"{$username}:<openclaw-secret-field-value>\" " . rest_url( Controller::NAMESPACE . '/health' ) . "\n"
			. "curl -sS --user \"{$username}:<openclaw-secret-field-value>\" " . rest_url( Controller::NAMESPACE . '/help' ) . "\n"
			. "curl -sS --user \"{$username}:<openclaw-secret-field-value>\" " . rest_url( Controller::NAMESPACE . '/capabilities' );
	}

	/**
	 * Builds WorkBuddy setup text without embedding secrets.
	 *
	 * @param string $username WordPress username.
	 * @param string $password_uuid Application Password UUID.
	 * @param bool   $include_local_tls Whether to include local TLS hints.
	 * @return string
	 */
	private function workbuddy_handoff_text( string $username, string $password_uuid, bool $include_local_tls ): string {
		return "Magick AI Adapter WorkBuddy connection\n"
			. "Paste this setup into WorkBuddy. It contains no Application Password value.\n\n"
			. "Connection manifest\n"
			. $this->openclaw_connection_manifest_text( $username, $password_uuid ) . "\n\n"
			. "Secret field\n"
			. "Name: wordpress_application_password\n"
			. "Value: paste the one-time Application Password shown in WordPress only into WorkBuddy's secret field.\n\n"
			. "Optional env placeholders\n"
			. $this->openclaw_env_text( $username, $include_local_tls ) . "\n\n"
			. "Connection check\n"
			. "1. GET /health and require core_capabilities=true, abilities_catalog=true, approval_proxy_enabled=false, core_proxy_execute=false, and commit_execution=false.\n"
			. "2. GET /help for route discovery.\n"
			. "3. GET /capabilities before reads or proposals.\n"
			. "4. Use direct_read routes for reads. Use /proposals and Core approval/preflight for writes.\n"
			. "5. Do not put the secret into chat, tool commands, logs, proposal payloads, files, or copied setup text.";
	}

	/**
	 * Builds the local CLI prompt for OpenClaw-style clients.
	 *
	 * @param bool $include_local_tls Whether to include the local TLS flag.
	 * @return string
	 */
	private function local_cli_setup_text( bool $include_local_tls ): string {
		$status_command = $this->local_cli_status_command( $include_local_tls );
		$connect_command = $this->local_cli_connect_command( $include_local_tls );
		$request_prefix = $this->local_cli_request_prefix( $include_local_tls );

		return "Magick AI Adapter local CLI setup\n\n"
			. "Use this local CLI to call Adapter. Do not read, print, summarize, or copy ~/.magick-ai-adapter/keypair-profiles/*.json.\n\n"
			. "Pairing command for the user terminal:\n"
			. $connect_command . "\n\n"
			. "Connection status:\n"
			. $status_command . "\n\n"
			. "Adapter requests:\n"
			. "{$request_prefix} GET /health\n"
			. "{$request_prefix} GET /capabilities\n"
			. "{$request_prefix} POST /proposals/from-plan --body-file=/tmp/magick-proposal.json\n\n"
			. "Rules for OpenClaw:\n"
			. "1. Do not read, cat, print, summarize, or copy the local keypair profile file.\n"
			. "2. Do not output private_key_jwk, public_key_jwk, Authorization, X-Magick-Signature, or any signing headers.\n"
			. "3. POST bodies must contain only non-secret JSON. Use --body-file or --body-stdin.\n"
			. "4. Use only Adapter-relative routes such as /health, /capabilities, or /proposals.\n"
			. "5. WordPress writes must still go through Core proposal, approval, and preflight.";
	}

	/**
	 * Renders registered local key-pair clients.
	 *
	 * @param array<int,array<string,mixed>> $key_records Key records.
	 * @return void
	 */
	private function render_key_pair_clients_table( array $key_records ): void {
		if ( empty( $key_records ) ) :
			?>
			<p class="description"><?php echo esc_html__( 'No key-pair clients are registered for this administrator yet. Run the reconnect command, approve the browser prompt, then refresh this page.', 'magick-ai-adapter' ); ?></p>
			<?php
			return;
		endif;
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Client', 'magick-ai-adapter' ); ?></th>
					<th><?php echo esc_html__( 'Fingerprint', 'magick-ai-adapter' ); ?></th>
					<th><?php echo esc_html__( 'Scopes', 'magick-ai-adapter' ); ?></th>
					<th><?php echo esc_html__( 'Last used', 'magick-ai-adapter' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'magick-ai-adapter' ); ?></th>
					<th><?php echo esc_html__( 'Action', 'magick-ai-adapter' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $key_records as $record ) : ?>
					<tr>
						<td><?php echo esc_html( (string) ( $record['client_name'] ?? '' ) ); ?><br><code><?php echo esc_html( (string) ( $record['key_id'] ?? '' ) ); ?></code></td>
						<td><code><?php echo esc_html( (string) ( $record['fingerprint'] ?? '' ) ); ?></code></td>
						<td><?php echo esc_html( implode( ', ', is_array( $record['scopes'] ?? null ) ? $record['scopes'] : array() ) ); ?></td>
						<td><?php echo esc_html( (string) ( $record['last_used_at'] ?? '' ) ); ?></td>
						<td><?php echo '' === (string) ( $record['revoked_at'] ?? '' ) ? esc_html__( 'Active', 'magick-ai-adapter' ) : esc_html__( 'Revoked', 'magick-ai-adapter' ); ?></td>
						<td>
							<?php if ( '' === (string) ( $record['revoked_at'] ?? '' ) ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="<?php echo esc_attr( self::REVOKE_KEY_ACTION ); ?>" />
									<input type="hidden" name="key_id" value="<?php echo esc_attr( (string) ( $record['key_id'] ?? '' ) ); ?>" />
									<?php wp_nonce_field( self::REVOKE_KEY_ACTION . '_' . (string) ( $record['key_id'] ?? '' ) ); ?>
									<button type="submit" class="button"><?php echo esc_html__( 'Revoke', 'magick-ai-adapter' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Builds the local CLI connect command.
	 *
	 * @param bool $include_local_tls Whether to include the local TLS flag.
	 * @return string
	 */
	private function local_cli_connect_command( bool $include_local_tls ): string {
		return $this->local_cli_prefix() . ' connect --site=' . home_url() . ' --profile=local' . $this->local_cli_tls_flag( $include_local_tls );
	}

	/**
	 * Builds the local CLI status command.
	 *
	 * @param bool $include_local_tls Whether to include the local TLS flag.
	 * @return string
	 */
	private function local_cli_status_command( bool $include_local_tls ): string {
		return $this->local_cli_prefix() . ' status --profile=local' . $this->local_cli_tls_flag( $include_local_tls );
	}

	/**
	 * Builds the local CLI request command prefix.
	 *
	 * @param bool $include_local_tls Whether to include the local TLS flag.
	 * @return string
	 */
	private function local_cli_request_prefix( bool $include_local_tls ): string {
		return $this->local_cli_prefix() . ' request --profile=local' . $this->local_cli_tls_flag( $include_local_tls );
	}

	/**
	 * Builds the local CLI executable prefix.
	 *
	 * @return string
	 */
	private function local_cli_prefix(): string {
		return 'cd ~ && npm exec --yes --package @npcink/magick-ai-adapter-cli -- magick-adapter';
	}

	/**
	 * Builds the local TLS flag for LocalWP URLs.
	 *
	 * @param bool $include_local_tls Whether to include the local TLS flag.
	 * @return string
	 */
	private function local_cli_tls_flag( bool $include_local_tls ): string {
		return $include_local_tls ? ' --insecure-local-tls' : '';
	}

	/**
	 * Returns whether a URL points at a local testing host.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private function is_local_url( string $url ): bool {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) ) {
			return false;
		}

		$host = strtolower( trim( $host, '[]' ) );
		return 'localhost' === $host || '127.0.0.1' === $host || '::1' === $host || substr( $host, -6 ) === '.local';
	}
}
