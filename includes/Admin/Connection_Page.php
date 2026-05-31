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
		$grant_url       = rest_url( Controller::NAMESPACE . '/connections/grants' );
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
		$client_config   = $this->openclaw_env_text( $username, $this->is_local_url( home_url() ) );
		$rest_nonce      = wp_create_nonce( 'wp_rest' );
		?>
		<div class="wrap magick-ai-adapter-connection">
			<h1><?php echo esc_html__( 'Magick AI Adapter', 'magick-ai-adapter' ); ?></h1>
			<p class="description"><?php echo esc_html__( 'Connect OpenClaw to this WordPress site through the Adapter REST surface.', 'magick-ai-adapter' ); ?></p>

			<style>
				.magick-ai-adapter-connection {
					max-width: 1180px;
				}
				.magick-ai-adapter-connection .maa-tabs {
					display: flex;
					gap: 4px;
					margin: 18px 0 0;
					border-bottom: 1px solid #c3c4c7;
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
					display: none;
					padding-top: 16px;
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
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
					gap: 1px;
					margin: 18px 0;
					border: 1px solid #dcdcde;
					background: #dcdcde;
				}
				.magick-ai-adapter-connection .maa-summary-item,
				.magick-ai-adapter-connection .maa-section {
					background: #fff;
				}
				.magick-ai-adapter-connection .maa-summary-item {
					padding: 12px 14px;
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
				@media (max-width: 960px) {
					.magick-ai-adapter-connection .maa-workspace {
						grid-template-columns: 1fr;
					}
				}
				@media (max-width: 480px) {
					.magick-ai-adapter-connection .maa-copy-row {
						grid-template-columns: 1fr;
					}
					.magick-ai-adapter-connection .maa-tab {
						flex: 1;
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

			<div class="maa-tabs" role="tablist" aria-label="<?php echo esc_attr__( 'Adapter sections', 'magick-ai-adapter' ); ?>">
				<button type="button" class="maa-tab is-active" id="maa-tab-connection" role="tab" aria-selected="true" aria-controls="maa-panel-connection" data-maa-tab="connection"><?php echo esc_html__( 'Connection', 'magick-ai-adapter' ); ?></button>
				<button type="button" class="maa-tab" id="maa-tab-advanced" role="tab" aria-selected="false" aria-controls="maa-panel-advanced" data-maa-tab="advanced"><?php echo esc_html__( 'Advanced', 'magick-ai-adapter' ); ?></button>
			</div>

			<div id="maa-panel-connection" class="maa-tab-panel is-active" role="tabpanel" aria-labelledby="maa-tab-connection">
				<div class="maa-workspace">
					<div class="maa-section maa-section-highlight">
						<h2><?php echo esc_html__( 'Create OpenClaw handoff', 'magick-ai-adapter' ); ?></h2>
						<p><?php echo esc_html__( 'Create a one-time Application Password and non-secret OpenClaw connection manifest for the current administrator.', 'magick-ai-adapter' ); ?></p>
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
								<p class="description"><?php echo esc_html__( 'Use only for localhost or .local testing. This only changes copied client configuration.', 'magick-ai-adapter' ); ?></p>
							</div>
							<p class="maa-form-actions">
								<button type="submit" class="button button-primary" <?php disabled( ! $can_create_password ); ?>>
									<?php echo esc_html__( 'Create OpenClaw handoff', 'magick-ai-adapter' ); ?>
								</button>
							</p>
							<?php if ( ! $can_create_password ) : ?>
								<p class="description"><?php echo esc_html__( 'Application Passwords are not available for this user or site.', 'magick-ai-adapter' ); ?></p>
							<?php endif; ?>
						</form>
					</div>

					<div class="maa-section">
						<h2><?php echo esc_html__( 'OpenClaw endpoint', 'magick-ai-adapter' ); ?></h2>
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
								<span class="maa-label"><?php echo esc_html__( 'Client config', 'magick-ai-adapter' ); ?></span>
								<p class="maa-inline-note"><?php echo esc_html__( 'Copies the Adapter URL, username, and password placeholder. Paste the real password only into OpenClaw dedicated secret field.', 'magick-ai-adapter' ); ?></p>
								<textarea id="maa-client-config" hidden readonly><?php echo esc_textarea( $client_config ); ?></textarea>
							</div>
							<button type="button" class="button maa-copy-button" data-maa-copy-target="maa-client-config"><?php echo esc_html__( 'Copy env', 'magick-ai-adapter' ); ?></button>
						</div>
						<p class="description"><?php echo esc_html__( 'Writes require Core proposal approval before Adapter execution.', 'magick-ai-adapter' ); ?></p>
					</div>
				</div>
			</div>

			<div id="maa-panel-advanced" class="maa-tab-panel" role="tabpanel" aria-labelledby="maa-tab-advanced" hidden>
				<details class="maa-section">
					<summary>
						<strong><?php echo esc_html__( 'Diagnostics URLs', 'magick-ai-adapter' ); ?></strong>
						<span class="description"><?php echo esc_html__( 'Health, help, and capabilities endpoints.', 'magick-ai-adapter' ); ?></span>
					</summary>
					<p><span class="maa-label"><?php echo esc_html__( 'Health', 'magick-ai-adapter' ); ?></span><code><?php echo esc_html( $health_url ); ?></code></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Help', 'magick-ai-adapter' ); ?></span><code><?php echo esc_html( $help_url ); ?></code></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Capabilities', 'magick-ai-adapter' ); ?></span><code><?php echo esc_html( $capabilities_url ); ?></code></p>
				</details>

				<details class="maa-section">
					<summary>
						<strong><?php echo esc_html__( 'Local broker validation', 'magick-ai-adapter' ); ?></strong>
						<span class="description"><?php echo esc_html__( 'Create a short-lived grant for a broker running on 127.0.0.1.', 'magick-ai-adapter' ); ?></span>
					</summary>
					<p><?php echo esc_html__( 'For WorkBuddy MVP testing, run the local Node broker on this computer, then click connect. The browser sends only the non-secret manifest and a one-time grant; the Application Password is created during broker redeem and returned encrypted to the broker public key.', 'magick-ai-adapter' ); ?></p>
					<pre><?php echo esc_html( 'node /Users/muze/gitee/magick-ai-adapter/tools/workbuddy-local-broker.mjs --port=9981' . ( $this->is_local_url( home_url() ) ? ' --insecure-local-tls' : '' ) ); ?></pre>
					<p><button type="button" class="button" id="maa-local-broker-connect"><?php echo esc_html__( 'Connect local broker', 'magick-ai-adapter' ); ?></button></p>
					<p class="description" id="maa-local-broker-status"><?php echo esc_html__( 'Waiting for local broker.', 'magick-ai-adapter' ); ?></p>
				</details>

				<details class="maa-section">
					<summary>
						<strong><?php echo esc_html__( 'Route catalog', 'magick-ai-adapter' ); ?></strong>
						<span class="description"><?php echo esc_html__( 'Proposal endpoints, disabled stubs, and read shortcut mappings.', 'magick-ai-adapter' ); ?></span>
					</summary>
					<h3><?php echo esc_html__( 'Proposal routes', 'magick-ai-adapter' ); ?></h3>
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
				</details>

				<details class="maa-section">
					<summary>
						<strong><?php echo esc_html__( 'Example requests', 'magick-ai-adapter' ); ?></strong>
						<span class="description"><?php echo esc_html__( 'Curl examples for health and proposal checks.', 'magick-ai-adapter' ); ?></span>
					</summary>
					<p><?php echo esc_html__( 'Use a dedicated administrator Application Password. Paste the password only into OpenClaw dedicated secret field, never into chat, tools, files, logs, or proposals.', 'magick-ai-adapter' ); ?></p>
					<pre><?php echo esc_html( $example_request ); ?></pre>
					<pre><?php echo esc_html( $proposal_request ); ?></pre>
					<pre><?php echo esc_html( $proposal_status_request ); ?></pre>
				</details>

				<details class="maa-section">
					<summary>
						<strong><?php echo esc_html__( 'Handoff prompt', 'magick-ai-adapter' ); ?></strong>
						<span class="description"><?php echo esc_html__( 'Verbose instructions for the OpenClaw environment.', 'magick-ai-adapter' ); ?></span>
					</summary>
					<textarea readonly><?php echo esc_textarea( $handoff_prompt ); ?></textarea>
				</details>

				<details class="maa-section">
					<summary>
						<strong><?php echo esc_html__( 'Boundary', 'magick-ai-adapter' ); ?></strong>
						<span class="description"><?php echo esc_html__( 'Adapter ownership and non-goals.', 'magick-ai-adapter' ); ?></span>
					</summary>
					<p><?php echo esc_html__( 'OpenClaw only connects to Adapter. Core approval admin is the human governance surface behind Adapter. Reads run only when Core marks an ability as direct_read on wp_abilities_rest. Writes create Core proposals and stop at commit preflight.', 'magick-ai-adapter' ); ?></p>
					<p><code>approval_proxy_enabled=false</code></p>
					<p><code>core_proxy_execute=false</code></p>
					<p><code>commit_execution=false</code></p>
				</details>
			</div>

			<script>
				(function () {
					var root = document.querySelector('.magick-ai-adapter-connection');
					if (!root) {
						return;
					}
					var localBrokerConfig = {
						brokerBaseUrls: [
							'http://127.0.0.1:9981',
							'http://localhost:9981'
						],
						manifestUrl: '<?php echo esc_js( $manifest_url ); ?>',
						grantUrl: '<?php echo esc_js( $grant_url ); ?>',
						restNonce: '<?php echo esc_js( $rest_nonce ); ?>'
					};

					function fetchJson(url, options) {
						return window.fetch(url, options).then(function (response) {
							return response.json().catch(function () {
								return {};
							}).then(function (data) {
								if (!response.ok) {
									throw new Error(data.message || data.error || ('HTTP ' + response.status));
								}
								return data;
							});
						});
					}

					function requestBrokerHandshake(baseUrls, index) {
						if (index >= baseUrls.length) {
							return Promise.reject(new Error('<?php echo esc_js( __( 'Local broker is not reachable on 127.0.0.1:9981 or localhost:9981.', 'magick-ai-adapter' ) ); ?>'));
						}

						return fetchJson(baseUrls[index] + '/request?t=' + Date.now(), {
							method: 'GET',
							mode: 'cors',
							credentials: 'omit',
							cache: 'no-store',
							headers: {
								'Accept': 'application/json'
							}
						}).then(function (brokerRequest) {
							return {
								baseUrl: baseUrls[index],
								brokerRequest: brokerRequest
							};
						}).catch(function () {
							return requestBrokerHandshake(baseUrls, index + 1);
						});
					}

					function setTab(tabName) {
						root.querySelectorAll('[data-maa-tab]').forEach(function (tab) {
							var active = tab.getAttribute('data-maa-tab') === tabName;
							tab.classList.toggle('is-active', active);
							tab.setAttribute('aria-selected', active ? 'true' : 'false');
						});

						root.querySelectorAll('.maa-tab-panel').forEach(function (panel) {
							var active = panel.id === 'maa-panel-' + tabName;
							panel.classList.toggle('is-active', active);
							panel.hidden = !active;
						});
					}

					root.querySelectorAll('[data-maa-tab]').forEach(function (tab) {
						tab.addEventListener('click', function () {
							setTab(tab.getAttribute('data-maa-tab'));
						});
					});

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

					var localBrokerButton = document.getElementById('maa-local-broker-connect');
					var localBrokerStatus = document.getElementById('maa-local-broker-status');
					if (localBrokerButton && localBrokerStatus) {
						localBrokerButton.addEventListener('click', function () {
							localBrokerButton.disabled = true;
							localBrokerStatus.textContent = '<?php echo esc_js( __( 'Contacting local broker...', 'magick-ai-adapter' ) ); ?>';

							requestBrokerHandshake(localBrokerConfig.brokerBaseUrls, 0)
								.then(function (session) {
									localBrokerStatus.textContent = '<?php echo esc_js( __( 'Reading non-secret manifest...', 'magick-ai-adapter' ) ); ?>';
									return fetchJson(localBrokerConfig.manifestUrl, {
										method: 'GET',
										credentials: 'same-origin',
										cache: 'no-store',
										headers: {
											'X-WP-Nonce': localBrokerConfig.restNonce,
											'Accept': 'application/json'
										}
									}).then(function (manifest) {
										session.manifest = manifest;
										return session;
									});
								})
								.then(function (session) {
									localBrokerStatus.textContent = '<?php echo esc_js( __( 'Creating short-lived grant...', 'magick-ai-adapter' ) ); ?>';
									return fetchJson(localBrokerConfig.grantUrl, {
										method: 'POST',
										credentials: 'same-origin',
										headers: {
											'Content-Type': 'application/json',
											'X-WP-Nonce': localBrokerConfig.restNonce,
											'Accept': 'application/json'
										},
										body: JSON.stringify({
											manifest_sha256: session.manifest.integrity && session.manifest.integrity.manifest_sha256,
											state: session.brokerRequest.state,
											broker: session.brokerRequest.broker,
											requested_scopes: session.brokerRequest.requested_scopes
										})
									}).then(function (grant) {
										session.grant = grant;
										return session;
									});
								})
								.then(function (session) {
									localBrokerStatus.textContent = '<?php echo esc_js( __( 'Sending grant to local broker. Confirm in the broker terminal if prompted...', 'magick-ai-adapter' ) ); ?>';
									return fetchJson(session.baseUrl + '/grant', {
										method: 'POST',
										mode: 'cors',
										credentials: 'omit',
										headers: {
											'Content-Type': 'application/json',
											'Accept': 'application/json'
										},
										body: JSON.stringify({
											manifest: session.manifest,
											grant: session.grant
										})
									});
								})
								.then(function (result) {
									localBrokerStatus.textContent = '<?php echo esc_js( __( 'Connected. Credential saved by local broker:', 'magick-ai-adapter' ) ); ?>' + ' ' + (result.output_path || result.connection_id || '');
								})
								.catch(function (error) {
									localBrokerStatus.textContent = '<?php echo esc_js( __( 'Local broker connection failed:', 'magick-ai-adapter' ) ); ?>' + ' ' + error.message;
								})
								.finally(function () {
									localBrokerButton.disabled = false;
								});
						});
					}
				})();
			</script>
		</div>
		<?php
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
			. '  -d \'{"ability_id":"magick-ai/create-draft","title":"Draft proposal","summary":"OpenClaw requests a governed draft proposal.","input":{"dry_run":true,"commit":false},"preview":{},"caller":{"external_thread_id":"OPENCLAW_THREAD_ID"}}\' \\' . "\n"
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
			. "For proposal_required abilities, POST /proposals with the real ability_id, input, preview, and caller metadata. For read-only planning outputs, POST /proposals/from-plan to let Core create governed proposals.\n"
			. "Poll GET /proposals/{proposal_id} for Core status. For the unified user action, call POST /proposals/{proposal_id}/approve-and-execute so Adapter calls Core approve, Core commit-preflight, and one allowlisted execution. If status=rejected, stop and show the rejection status. If status=approved and using the lower-level split path, call POST /proposals/{proposal_id}/commit-preflight.\n"
			. "When you have proposal_id or commit-preflight correlation_id, pass them as log_context on POST /run-read-ability or as query fields on read shortcuts so Adapter can add them to AI Request Logs context through wpai_request_log_context. Core Governance Audit is the governance log; AI Request Logs are the provider request log. Adapter context includes ability_id, adapter_request_id, adapter_route, ai_provider, ai_model, governance_source=magick-ai-core, and nested magick_ai_core identifiers.\n"
			. "POST /proposals/{proposal_id}/approve and POST /proposals/{proposal_id}/reject are disabled stubs that return approval_proxy_enabled=false. The only Adapter approval path is POST /proposals/{proposal_id}/approve-and-execute, currently allowlisted only for magick-ai/trash-post.\n"
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
			. "6. For proposal_required abilities, POST /proposals and poll GET /proposals/{proposal_id}. For read-only planning outputs, POST /proposals/from-plan.\n"
			. "7. For the unified user action, call POST /proposals/{proposal_id}/approve-and-execute. Adapter calls Core approve, Core commit-preflight, and one allowlisted execution. Current execution allowlist: magick-ai/trash-post.\n"
			. "7b. If status=rejected, stop and show the rejection status. If status=approved and using the lower-level split path, call POST /proposals/{proposal_id}/commit-preflight.\n"
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
