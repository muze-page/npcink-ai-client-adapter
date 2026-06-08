<?php
/**
 * OpenClaw connection admin page.
 *
 * @package NpcinkOpenClawAdapter
 */

namespace Npcink\OpenClawAdapter\Admin;

use Npcink\OpenClawAdapter\Rest\Controller;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a read-only connection handoff surface for OpenClaw.
 */
final class Connection_Page {
	const PARENT_MENU_SLUG = 'npcink-ai';
	const MENU_SLUG        = 'npcink-openclaw-adapter';
	const MENU_CAPABILITY  = 'manage_options';
	const CREATE_ACTION    = 'npcink_openclaw_adapter_create_openclaw_password';
	const PAIR_MENU_SLUG   = 'npcink-openclaw-adapter-pair';
	const PAIR_ACTION      = 'npcink_openclaw_adapter_pairing_decision';
	const REVOKE_KEY_ACTION = 'npcink_openclaw_adapter_revoke_client_key';
	const PROPOSAL_LOOKUP_ACTION = 'npcink_openclaw_adapter_proposal_lookup';
	const PROPOSAL_LOOKUP_NONCE = '_npcink_openclaw_adapter_lookup_nonce';
	const DATETIME_DISPLAY_FORMAT = 'Y-m-d H:i:s';

	/**
	 * Admin page hook suffixes that should receive Adapter assets.
	 *
	 * @var array<int,string>
	 */
	private $admin_page_hooks = array();

	/**
	 * Registers the menu item.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->ensure_parent_menu();

		$connection_hook = add_submenu_page(
			self::PARENT_MENU_SLUG,
			__( 'Npcink OpenClaw Adapter', 'npcink-openclaw-adapter' ),
			__( 'Adapter', 'npcink-openclaw-adapter' ),
			self::MENU_CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render' ),
			20
		);

		$pairing_hook = add_submenu_page(
			null,
			__( 'Approve Npcink Client', 'npcink-openclaw-adapter' ),
			__( 'Approve Npcink Client', 'npcink-openclaw-adapter' ),
			self::MENU_CAPABILITY,
			self::PAIR_MENU_SLUG,
			array( $this, 'render_pairing_page' )
		);

		$this->admin_page_hooks = array_values( array_filter( array( $connection_hook, $pairing_hook ), 'is_string' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueues Adapter admin assets on owned admin pages.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, $this->admin_page_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'npcink-openclaw-adapter-admin',
			plugins_url( 'assets/admin.css', NPCINK_OPENCLAW_ADAPTER_FILE ),
			array(),
			NPCINK_OPENCLAW_ADAPTER_VERSION
		);
		wp_enqueue_script(
			'npcink-openclaw-adapter-admin',
			plugins_url( 'assets/admin.js', NPCINK_OPENCLAW_ADAPTER_FILE ),
			array(),
			NPCINK_OPENCLAW_ADAPTER_VERSION,
			true
		);
	}

	/**
	 * Ensures the shared Npcink parent menu exists.
	 *
	 * @return void
	 */
	private function ensure_parent_menu(): void {
		if ( $this->has_parent_menu() ) {
			return;
		}

		add_menu_page(
			__( 'Npcink', 'npcink-openclaw-adapter' ),
			__( 'Npcink', 'npcink-openclaw-adapter' ),
			self::MENU_CAPABILITY,
			self::PARENT_MENU_SLUG,
			array( $this, 'render_overview' ),
			'dashicons-superhero',
			58
		);

		add_submenu_page(
			self::PARENT_MENU_SLUG,
			__( 'Npcink Overview', 'npcink-openclaw-adapter' ),
			__( 'Overview', 'npcink-openclaw-adapter' ),
			self::MENU_CAPABILITY,
			self::PARENT_MENU_SLUG,
			array( $this, 'render_overview' ),
			0
		);
	}

	/**
	 * Returns whether another Npcink plugin already created the parent menu.
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
	 * Renders the shared Npcink overview page.
	 *
	 * @return void
	 */
	public function render_overview(): void {
		if ( ! current_user_can( self::MENU_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'npcink-openclaw-adapter' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Npcink', 'npcink-openclaw-adapter' ); ?></h1>
			<p><?php echo esc_html__( 'Local WordPress entry points for Npcink governance, connections, cloud access, and ability packages.', 'npcink-openclaw-adapter' ); ?></p>
			<h2><?php echo esc_html__( 'Installed Surfaces', 'npcink-openclaw-adapter' ); ?></h2>
			<table class="widefat striped" style="max-width: 860px;">
				<tbody>
					<?php
					$this->render_overview_row( __( 'Core', 'npcink-openclaw-adapter' ), __( 'Review proposals, approval decisions, commit preflight, audit, and Core app keys.', 'npcink-openclaw-adapter' ), 'npcink-governance-core' );
					$this->render_overview_row( __( 'Adapter', 'npcink-openclaw-adapter' ), __( 'Connect OpenClaw through the Adapter surface.', 'npcink-openclaw-adapter' ), self::MENU_SLUG );
					$this->render_overview_row( __( 'Abilities', 'npcink-openclaw-adapter' ), __( 'Verify WordPress Abilities API packages and demo ability controls.', 'npcink-openclaw-adapter' ), 'npcink-abilities-toolkit' );
					$this->render_overview_row( __( 'Cloud Addon', 'npcink-openclaw-adapter' ), __( 'Connect this site to Npcink Cloud without moving local control-plane truth.', 'npcink-openclaw-adapter' ), 'npcink-cloud-addon' );
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
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>"><?php echo esc_html__( 'Open', 'npcink-openclaw-adapter' ); ?></a>
				<?php else : ?>
					<span style="color: #646970;"><?php echo esc_html__( 'Not installed', 'npcink-openclaw-adapter' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Returns whether a Npcink submenu has been registered.
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
			wp_die( esc_html__( 'You do not have permission to view this page.', 'npcink-openclaw-adapter' ) );
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
		$lookup_id       = $this->proposal_lookup_id_from_request();
		$lookup_result   = '' !== $lookup_id ? $this->proposal_lookup( $lookup_id ) : null;
		?>
		<div class="wrap npcink-openclaw-adapter-connection" data-maa-copied-label="<?php echo esc_attr__( 'Copied', 'npcink-openclaw-adapter' ); ?>">
			<h1><?php echo esc_html__( 'Npcink OpenClaw Adapter', 'npcink-openclaw-adapter' ); ?></h1>
			<p class="description"><?php echo esc_html__( 'Connect OpenClaw to this WordPress site through the Adapter REST surface.', 'npcink-openclaw-adapter' ); ?></p>

			<div class="maa-summary">
				<div class="maa-summary-item">
					<span class="maa-label"><?php echo esc_html__( 'Status', 'npcink-openclaw-adapter' ); ?></span>
					<span class="maa-status maa-status-<?php echo esc_attr( $status['level'] ); ?>"><?php echo esc_html( $status['label'] ); ?></span>
				</div>
				<div class="maa-summary-item">
					<span class="maa-label"><?php echo esc_html__( 'Core capabilities', 'npcink-openclaw-adapter' ); ?></span>
					<span class="maa-value"><?php echo ! empty( $health['core_capabilities'] ) ? esc_html__( 'Available', 'npcink-openclaw-adapter' ) : esc_html__( 'Missing', 'npcink-openclaw-adapter' ); ?></span>
				</div>
				<div class="maa-summary-item">
					<span class="maa-label"><?php echo esc_html__( 'Abilities API', 'npcink-openclaw-adapter' ); ?></span>
					<span class="maa-value"><?php echo ! empty( $health['abilities_catalog'] ) ? esc_html__( 'Available', 'npcink-openclaw-adapter' ) : esc_html__( 'Missing', 'npcink-openclaw-adapter' ); ?></span>
				</div>
				<div class="maa-summary-item">
					<span class="maa-label"><?php echo esc_html__( 'Abilities Toolkit', 'npcink-openclaw-adapter' ); ?></span>
					<span class="maa-value"><?php echo ! empty( $health['abilities_toolkit'] ) ? esc_html__( 'Available', 'npcink-openclaw-adapter' ) : esc_html__( 'Missing', 'npcink-openclaw-adapter' ); ?></span>
				</div>
				<div class="maa-summary-item">
					<span class="maa-label"><?php echo esc_html__( 'Write execution', 'npcink-openclaw-adapter' ); ?></span>
					<span class="maa-value"><?php echo esc_html__( 'Proposal required', 'npcink-openclaw-adapter' ); ?></span>
				</div>
			</div>
			<?php if ( empty( $health['dependencies_ready'] ) && ! empty( $health['missing_dependencies'] ) && is_array( $health['missing_dependencies'] ) ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php echo esc_html__( 'Suite dependencies need attention.', 'npcink-openclaw-adapter' ); ?></strong>
						<?php echo esc_html( implode( ', ', array_map( 'sanitize_text_field', $health['missing_dependencies'] ) ) ); ?>
					</p>
				</div>
			<?php endif; ?>

			<div class="maa-workspace">
				<div class="maa-section maa-section-highlight">
					<h2><?php echo esc_html__( 'Simple connection', 'npcink-openclaw-adapter' ); ?></h2>
					<p class="maa-section-intro"><?php echo esc_html__( 'Use when the client has a dedicated secret field.', 'npcink-openclaw-adapter' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::CREATE_ACTION ); ?>" />
						<?php wp_nonce_field( self::CREATE_ACTION ); ?>
						<p>
							<label for="npcink-openclaw-adapter-password-name-compact"><span class="maa-label"><?php echo esc_html__( 'Application name', 'npcink-openclaw-adapter' ); ?></span></label>
							<input id="npcink-openclaw-adapter-password-name-compact" class="regular-text" type="text" name="application_name" value="OpenClaw via Npcink OpenClaw Adapter" />
						</p>
						<div class="maa-option">
							<label>
								<input type="checkbox" name="include_local_tls" value="1" <?php checked( $this->is_local_url( home_url() ) ); ?> />
								<span><?php echo esc_html__( 'Include LocalWP TLS setting', 'npcink-openclaw-adapter' ); ?></span>
							</label>
							<p class="description"><?php echo esc_html__( 'LocalWP TLS option. Use only for localhost or .local testing.', 'npcink-openclaw-adapter' ); ?></p>
						</div>
						<p class="maa-form-actions">
							<button type="submit" class="button button-primary" <?php disabled( ! $can_create_password ); ?>>
								<?php echo esc_html__( 'Create Application Password connection', 'npcink-openclaw-adapter' ); ?>
							</button>
						</p>
						<?php if ( ! $can_create_password ) : ?>
							<p class="description"><?php echo esc_html__( 'Application Passwords are not available for this user or site.', 'npcink-openclaw-adapter' ); ?></p>
						<?php endif; ?>
					</form>
					<p class="description"><?php echo esc_html__( 'The password is shown once. Store it only in the client secret field.', 'npcink-openclaw-adapter' ); ?></p>
				</div>

				<div class="maa-section">
					<h2><?php echo esc_html__( 'Connection values', 'npcink-openclaw-adapter' ); ?></h2>
					<div class="maa-copy-row">
						<div>
							<span class="maa-label"><?php echo esc_html__( 'Adapter Base URL', 'npcink-openclaw-adapter' ); ?></span>
							<code class="maa-copy-value" id="maa-base-url"><?php echo esc_html( $base_url ); ?></code>
						</div>
						<button type="button" class="button maa-copy-button" data-maa-copy-target="maa-base-url"><?php echo esc_html__( 'Copy', 'npcink-openclaw-adapter' ); ?></button>
					</div>
					<div class="maa-copy-row">
						<div>
							<span class="maa-label"><?php echo esc_html__( 'WordPress user', 'npcink-openclaw-adapter' ); ?></span>
							<code class="maa-copy-value" id="maa-username"><?php echo esc_html( $username ); ?></code>
						</div>
						<button type="button" class="button maa-copy-button" data-maa-copy-target="maa-username"><?php echo esc_html__( 'Copy', 'npcink-openclaw-adapter' ); ?></button>
					</div>
					<div class="maa-copy-row">
						<div>
							<span class="maa-label"><?php echo esc_html__( 'Connection manifest', 'npcink-openclaw-adapter' ); ?></span>
							<code class="maa-copy-value" id="maa-manifest-url"><?php echo esc_html( $manifest_url ); ?></code>
						</div>
						<button type="button" class="button maa-copy-button" data-maa-copy-target="maa-manifest-url"><?php echo esc_html__( 'Copy manifest URL', 'npcink-openclaw-adapter' ); ?></button>
					</div>
					<div class="maa-copy-row">
						<div>
							<span class="maa-label"><?php echo esc_html__( 'Client env placeholder', 'npcink-openclaw-adapter' ); ?></span>
							<p class="maa-inline-note"><?php echo esc_html__( 'Copies the Adapter URL, username, and password placeholder only.', 'npcink-openclaw-adapter' ); ?></p>
							<textarea id="maa-client-config" hidden readonly><?php echo esc_textarea( $client_config ); ?></textarea>
						</div>
						<button type="button" class="button maa-copy-button" data-maa-copy-target="maa-client-config"><?php echo esc_html__( 'Copy env placeholder', 'npcink-openclaw-adapter' ); ?></button>
					</div>
					<p class="description"><?php echo esc_html__( 'Writes require Core proposal approval before Adapter execution.', 'npcink-openclaw-adapter' ); ?></p>
				</div>
			</div>

			<details class="maa-section">
				<summary>
					<strong><?php echo esc_html__( 'Higher security: signed key-pair', 'npcink-openclaw-adapter' ); ?></strong>
					<span class="description"><?php echo esc_html__( 'Recommended when the client should not receive an Application Password.', 'npcink-openclaw-adapter' ); ?></span>
				</summary>
				<p><?php echo esc_html__( 'Run this in the same environment as OpenClaw. The private key stays local and Adapter stores only the approved public key.', 'npcink-openclaw-adapter' ); ?></p>
				<div class="maa-copy-row maa-command-row">
					<div>
						<span class="maa-label"><?php echo esc_html__( 'Connect command', 'npcink-openclaw-adapter' ); ?></span>
						<code class="maa-copy-value" id="maa-local-cli-connect-command"><?php echo esc_html( $local_cli_connect_command ); ?></code>
					</div>
					<button type="button" class="button maa-copy-button" data-maa-copy-target="maa-local-cli-connect-command"><?php echo esc_html__( 'Copy connect command', 'npcink-openclaw-adapter' ); ?></button>
				</div>
				<div class="maa-copy-row maa-command-row">
					<div>
						<span class="maa-label"><?php echo esc_html__( 'Status command', 'npcink-openclaw-adapter' ); ?></span>
						<code class="maa-copy-value" id="maa-local-cli-status-command"><?php echo esc_html( $local_cli_status_command ); ?></code>
					</div>
					<button type="button" class="button maa-copy-button" data-maa-copy-target="maa-local-cli-status-command"><?php echo esc_html__( 'Copy status command', 'npcink-openclaw-adapter' ); ?></button>
				</div>
				<details class="maa-inline-disclosure">
					<summary>
						<strong><?php echo esc_html__( 'Full OpenClaw instructions', 'npcink-openclaw-adapter' ); ?></strong>
						<span class="description"><?php echo esc_html__( 'Copy only when the client needs the longer setup text.', 'npcink-openclaw-adapter' ); ?></span>
					</summary>
					<p class="description"><?php echo esc_html__( 'Do not ask OpenClaw to read the local keypair profile file. Writes still require Core proposal, approval, and preflight.', 'npcink-openclaw-adapter' ); ?></p>
					<textarea id="maa-local-cli-setup" rows="14" readonly><?php echo esc_textarea( $local_cli_setup ); ?></textarea>
					<p class="maa-action-row">
						<button type="button" class="button maa-copy-button" data-maa-copy-target="maa-local-cli-setup"><?php echo esc_html__( 'Copy OpenClaw CLI instructions', 'npcink-openclaw-adapter' ); ?></button>
					</p>
				</details>
			</details>

			<details class="maa-section"<?php echo '' !== $lookup_id ? ' open' : ''; ?>>
				<summary>
					<strong><?php echo esc_html__( 'Proposal lookup', 'npcink-openclaw-adapter' ); ?></strong>
					<span class="description"><?php echo esc_html__( 'Check a proposal after OpenClaw creates one.', 'npcink-openclaw-adapter' ); ?></span>
				</summary>
				<p><?php echo esc_html__( 'Use the Proposal ID returned to OpenClaw to check Core status, open the Core approval screen, and continue execution from Adapter after approval.', 'npcink-openclaw-adapter' ); ?></p>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
					<?php wp_nonce_field( self::PROPOSAL_LOOKUP_ACTION, self::PROPOSAL_LOOKUP_NONCE, false ); ?>
					<p>
						<label for="npcink-openclaw-adapter-proposal-lookup"><span class="maa-label"><?php echo esc_html__( 'Proposal ID', 'npcink-openclaw-adapter' ); ?></span></label>
						<input id="npcink-openclaw-adapter-proposal-lookup" class="regular-text" type="text" name="adapter_proposal_id" value="<?php echo esc_attr( $lookup_id ); ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" />
					</p>
					<p class="maa-form-actions">
						<button type="submit" class="button"><?php echo esc_html__( 'Check status', 'npcink-openclaw-adapter' ); ?></button>
						<a class="button" href="<?php echo esc_url( rest_url( Controller::NAMESPACE . '/proposals' ) ); ?>"><?php echo esc_html__( 'Open proposal API', 'npcink-openclaw-adapter' ); ?></a>
					</p>
				</form>
				<?php $this->render_proposal_lookup_result( $lookup_id, $lookup_result ); ?>
			</details>

			<details class="maa-section">
				<summary>
					<strong><?php echo esc_html__( 'Advanced', 'npcink-openclaw-adapter' ); ?></strong>
					<span class="description"><?php echo esc_html__( 'Diagnostics, route catalog, examples, and boundary notes.', 'npcink-openclaw-adapter' ); ?></span>
				</summary>

				<div class="maa-advanced-group">
					<h3><?php echo esc_html__( 'Diagnostics URLs', 'npcink-openclaw-adapter' ); ?></h3>
					<p><span class="maa-label"><?php echo esc_html__( 'Health', 'npcink-openclaw-adapter' ); ?></span><code><?php echo esc_html( $health_url ); ?></code></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Help', 'npcink-openclaw-adapter' ); ?></span><code><?php echo esc_html( $help_url ); ?></code></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Capabilities', 'npcink-openclaw-adapter' ); ?></span><code><?php echo esc_html( $capabilities_url ); ?></code></p>
				</div>

				<div class="maa-advanced-group">
					<h3><?php echo esc_html__( 'Key pair clients', 'npcink-openclaw-adapter' ); ?></h3>
					<p><?php echo esc_html__( 'Device-paired clients sign Adapter requests.', 'npcink-openclaw-adapter' ); ?></p>
					<p><?php echo esc_html__( 'Phase 2 clients generate an Ed25519 key locally. Adapter stores only the public key after WordPress admin approval.', 'npcink-openclaw-adapter' ); ?></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Manifest', 'npcink-openclaw-adapter' ); ?></span><code><?php echo esc_html( $manifest_url ); ?></code></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Key pairs', 'npcink-openclaw-adapter' ); ?></span><code><?php echo esc_html( $key_pairs_url ); ?></code></p>
					<p><?php echo esc_html__( 'Revoke a public key to stop the matching local profile from authenticating. Adapter never stores the private key.', 'npcink-openclaw-adapter' ); ?></p>
					<?php $this->render_key_pair_clients_table( $key_records ); ?>
				</div>

				<div class="maa-advanced-group">
					<h3><?php echo esc_html__( 'Route catalog', 'npcink-openclaw-adapter' ); ?></h3>
					<p><strong><?php echo esc_html__( 'Proposal routes', 'npcink-openclaw-adapter' ); ?></strong></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Proposal list', 'npcink-openclaw-adapter' ); ?></span><code><?php echo esc_html( rest_url( Controller::NAMESPACE . '/proposals' ) ); ?></code></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Proposal detail', 'npcink-openclaw-adapter' ); ?></span><code><?php echo esc_html( rest_url( Controller::NAMESPACE . '/proposals/{proposal_id}' ) ); ?></code></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Plan to proposals', 'npcink-openclaw-adapter' ); ?></span><code><?php echo esc_html( rest_url( Controller::NAMESPACE . '/proposals/from-plan' ) ); ?></code></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Commit preflight', 'npcink-openclaw-adapter' ); ?></span><code><?php echo esc_html( rest_url( Controller::NAMESPACE . '/proposals/{proposal_id}/commit-preflight' ) ); ?></code></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Approve and execute', 'npcink-openclaw-adapter' ); ?></span><code><?php echo esc_html( rest_url( Controller::NAMESPACE . '/proposals/{proposal_id}/approve-and-execute' ) ); ?></code></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Approval disabled stub', 'npcink-openclaw-adapter' ); ?></span><code><?php echo esc_html( rest_url( Controller::NAMESPACE . '/proposals/{proposal_id}/approve' ) ); ?></code></p>
					<p><span class="maa-label"><?php echo esc_html__( 'Reject disabled stub', 'npcink-openclaw-adapter' ); ?></span><code><?php echo esc_html( rest_url( Controller::NAMESPACE . '/proposals/{proposal_id}/reject' ) ); ?></code></p>
					<h3><?php echo esc_html__( 'Read shortcuts', 'npcink-openclaw-adapter' ); ?></h3>
					<ul class="maa-route-list">
						<?php foreach ( $shortcuts as $route => $ability_id ) : ?>
							<li><code><?php echo esc_html( 'GET /wp-json/' . Controller::NAMESPACE . '/' . $route ); ?></code><br><span class="description"><?php echo esc_html( $ability_id ); ?></span></li>
						<?php endforeach; ?>
					</ul>
				</div>

				<div class="maa-advanced-group">
					<h3><?php echo esc_html__( 'Example requests', 'npcink-openclaw-adapter' ); ?></h3>
					<p><?php echo esc_html__( 'Use a dedicated administrator Application Password. Paste the password only into OpenClaw dedicated secret field, never into chat, tools, files, logs, or proposals.', 'npcink-openclaw-adapter' ); ?></p>
					<pre><?php echo esc_html( $example_request ); ?></pre>
					<pre><?php echo esc_html( $proposal_request ); ?></pre>
					<pre><?php echo esc_html( $proposal_status_request ); ?></pre>
				</div>

				<div class="maa-advanced-group">
					<h3><?php echo esc_html__( 'Handoff prompt', 'npcink-openclaw-adapter' ); ?></h3>
					<textarea readonly><?php echo esc_textarea( $handoff_prompt ); ?></textarea>
				</div>

				<div class="maa-advanced-group">
					<h3><?php echo esc_html__( 'Boundary', 'npcink-openclaw-adapter' ); ?></h3>
					<p><?php echo esc_html__( 'OpenClaw only connects to Adapter. Core approval admin is the human governance surface behind Adapter. Reads run only when Core marks an ability as direct_read on wp_abilities_rest. Writes create Core proposals and stop at commit preflight.', 'npcink-openclaw-adapter' ); ?></p>
					<p><code>approval_proxy_enabled=false</code></p>
					<p><code>core_proxy_execute=false</code></p>
					<p><code>commit_execution=false</code></p>
				</div>
			</details>
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
			wp_die( esc_html__( 'You do not have permission to approve device pairing.', 'npcink-openclaw-adapter' ) );
		}

		$user_code = strtoupper( $this->request_text_field( INPUT_GET, 'user_code' ) );
		$pairing   = ( new Controller() )->admin_device_pairing( $user_code );
		$client    = is_array( $pairing['client'] ?? null ) ? $pairing['client'] : array();
		$key       = is_array( $pairing['key'] ?? null ) ? $pairing['key'] : array();
		$scopes    = is_array( $pairing['scopes'] ?? null ) ? $pairing['scopes'] : array();
		$status    = (string) ( $pairing['status'] ?? 'pending' );
		?>
		<div class="wrap npcink-openclaw-adapter-connection" data-maa-copied-label="<?php echo esc_attr__( 'Copied', 'npcink-openclaw-adapter' ); ?>">
			<h1><?php echo esc_html__( 'Approve Npcink Client', 'npcink-openclaw-adapter' ); ?></h1>
			<?php if ( empty( $pairing ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html__( 'Device pairing code was not found or has expired.', 'npcink-openclaw-adapter' ); ?></p></div>
			<?php else : ?>
				<?php if ( 'approved' === $status ) : ?>
					<div class="notice notice-success">
						<p><strong><?php echo esc_html__( 'Connection approved.', 'npcink-openclaw-adapter' ); ?></strong></p>
						<p><?php echo esc_html__( 'Return to the terminal or local AI client. The client will finish polling and save its local profile. Adapter stores only the public key; the private key was never sent to WordPress.', 'npcink-openclaw-adapter' ); ?></p>
					</div>
				<?php elseif ( 'rejected' === $status ) : ?>
					<div class="notice notice-warning">
						<p><strong><?php echo esc_html__( 'Connection rejected.', 'npcink-openclaw-adapter' ); ?></strong></p>
						<p><?php echo esc_html__( 'Return to the terminal or local AI client. The client will stop polling with a rejected status.', 'npcink-openclaw-adapter' ); ?></p>
					</div>
				<?php else : ?>
					<p><?php echo esc_html__( 'Approve this local AI client only if you initiated the connection. Adapter stores only the public key; the private key stays on your computer.', 'npcink-openclaw-adapter' ); ?></p>
				<?php endif; ?>
				<table class="widefat striped" style="max-width: 860px;">
					<tbody>
						<tr><th scope="row"><?php echo esc_html__( 'User code', 'npcink-openclaw-adapter' ); ?></th><td><code><?php echo esc_html( $user_code ); ?></code></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Client', 'npcink-openclaw-adapter' ); ?></th><td><?php echo esc_html( (string) ( $client['name'] ?? '' ) ); ?></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Device', 'npcink-openclaw-adapter' ); ?></th><td><?php echo esc_html( (string) ( $client['device_name'] ?? '' ) ); ?></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Broker', 'npcink-openclaw-adapter' ); ?></th><td><?php echo esc_html( trim( (string) ( $client['broker'] ?? '' ) . ' ' . (string) ( $client['broker_version'] ?? '' ) ) ); ?></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Fingerprint', 'npcink-openclaw-adapter' ); ?></th><td><code><?php echo esc_html( (string) ( $key['fingerprint'] ?? '' ) ); ?></code></td></tr>
						<tr><th scope="row"><?php echo esc_html__( 'Scopes', 'npcink-openclaw-adapter' ); ?></th><td><?php echo esc_html( implode( ', ', $scopes ) ); ?></td></tr>
						<?php if ( 'approved' === $status ) : ?>
							<tr><th scope="row"><?php echo esc_html__( 'Connection ID', 'npcink-openclaw-adapter' ); ?></th><td><code><?php echo esc_html( (string) ( $pairing['connection_id'] ?? '' ) ); ?></code></td></tr>
							<tr><th scope="row"><?php echo esc_html__( 'Key ID', 'npcink-openclaw-adapter' ); ?></th><td><code><?php echo esc_html( (string) ( $pairing['key_id'] ?? '' ) ); ?></code></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
				<?php if ( 'pending' === $status ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 16px;">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::PAIR_ACTION ); ?>" />
						<input type="hidden" name="user_code" value="<?php echo esc_attr( $user_code ); ?>" />
						<?php wp_nonce_field( self::PAIR_ACTION . '_' . $user_code ); ?>
						<button type="submit" name="decision" value="approve" class="button button-primary"><?php echo esc_html__( 'Approve connection', 'npcink-openclaw-adapter' ); ?></button>
						<button type="submit" name="decision" value="reject" class="button"><?php echo esc_html__( 'Reject', 'npcink-openclaw-adapter' ); ?></button>
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
			wp_die( esc_html__( 'You do not have permission to approve device pairing.', 'npcink-openclaw-adapter' ) );
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
			wp_die( esc_html__( 'You do not have permission to revoke client keys.', 'npcink-openclaw-adapter' ) );
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
			wp_die( esc_html__( 'You do not have permission to create OpenClaw handoff credentials.', 'npcink-openclaw-adapter' ) );
		}

		check_admin_referer( self::CREATE_ACTION );

		if ( ! $this->can_create_application_password() || ! class_exists( 'WP_Application_Passwords' ) ) {
			wp_die( esc_html__( 'Application Passwords are not available for this user or site.', 'npcink-openclaw-adapter' ) );
		}

		$user_id            = get_current_user_id();
		$application_name   = isset( $_POST['application_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['application_name'] ) ) : '';
		$application_name   = '' !== $application_name ? $application_name : 'OpenClaw via Npcink OpenClaw Adapter';
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
				'label' => __( 'Unavailable', 'npcink-openclaw-adapter' ),
			);
		}

		if ( ! empty( $health['core_capabilities'] ) && ! empty( $health['abilities_catalog'] ) ) {
			return array(
				'level' => 'ok',
				'label' => __( 'Ready', 'npcink-openclaw-adapter' ),
			);
		}

		return array(
			'level' => 'warning',
			'label' => __( 'Needs dependencies', 'npcink-openclaw-adapter' ),
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
				'npcink_openclaw_adapter_proposal_lookup_failed',
				__( 'Adapter could not read this Core proposal status.', 'npcink-openclaw-adapter' ),
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
			<p class="maa-inline-note"><?php echo esc_html__( 'After OpenClaw creates a proposal, paste its Proposal ID here. Pending decisions stay in Core; Adapter handles status polling and approved execution routes.', 'npcink-openclaw-adapter' ); ?></p>
			<?php
			return;
		}

		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$status = is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;
			?>
			<div class="notice notice-error inline">
				<p><strong><?php echo esc_html__( 'Proposal not available.', 'npcink-openclaw-adapter' ); ?></strong></p>
				<p>
					<?php echo esc_html( $result->get_error_message() ); ?>
					<?php
					if ( $status > 0 ) {
						/* translators: %d: HTTP status code. */
						echo ' ' . esc_html( sprintf( __( 'HTTP %d', 'npcink-openclaw-adapter' ), $status ) );
					}
					?>
				</p>
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
				'page'        => 'npcink-governance-core',
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
					<th scope="row"><?php echo esc_html__( 'Proposal ID', 'npcink-openclaw-adapter' ); ?></th>
					<td><code><?php echo esc_html( $proposal_id ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Status', 'npcink-openclaw-adapter' ); ?></th>
					<td><span class="maa-status maa-status-<?php echo esc_attr( $this->proposal_status_level( $status ) ); ?>"><?php echo esc_html( '' !== $status ? $status : __( 'unknown', 'npcink-openclaw-adapter' ) ); ?></span></td>
				</tr>
				<?php if ( '' !== $title ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Title', 'npcink-openclaw-adapter' ); ?></th>
						<td><?php echo esc_html( $title ); ?></td>
					</tr>
				<?php endif; ?>
				<?php if ( '' !== $ability ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Ability', 'npcink-openclaw-adapter' ); ?></th>
						<td><code><?php echo esc_html( $ability ); ?></code></td>
					</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Created', 'npcink-openclaw-adapter' ); ?></th>
					<td><?php echo esc_html( '' !== $created ? $this->display_datetime( $created ) : __( 'unknown', 'npcink-openclaw-adapter' ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Updated', 'npcink-openclaw-adapter' ); ?></th>
					<td><?php echo esc_html( '' !== $updated ? $this->display_datetime( $updated ) : __( 'unknown', 'npcink-openclaw-adapter' ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Audit timeline', 'npcink-openclaw-adapter' ); ?></th>
					<td>
						<?php
						/* translators: %d: Number of audit timeline events. */
						echo esc_html( sprintf( __( '%d events', 'npcink-openclaw-adapter' ), count( $timeline ) ) );
						?>
					</td>
				</tr>
			</tbody>
		</table>
		<div class="maa-action-row">
			<a class="button button-primary" href="<?php echo esc_url( $core_url ); ?>"><?php echo esc_html__( 'Open in Core', 'npcink-openclaw-adapter' ); ?></a>
			<button type="button" class="button maa-copy-button" data-maa-copy-target="maa-proposal-status-url"><?php echo esc_html__( 'Copy status URL', 'npcink-openclaw-adapter' ); ?></button>
			<button type="button" class="button maa-copy-button" data-maa-copy-target="maa-proposal-execute-url"><?php echo esc_html__( 'Copy execute URL', 'npcink-openclaw-adapter' ); ?></button>
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
			return __( 'Next step: review this proposal in Core. Adapter should keep polling status and execute only after Core approval and commit preflight.', 'npcink-openclaw-adapter' );
		}

		if ( 'approved' === $status ) {
			return __( 'Next step: execute through Adapter. Adapter will still call Core commit preflight before any allowlisted WordPress ability execution.', 'npcink-openclaw-adapter' );
		}

		if ( 'rejected' === $status ) {
			return __( 'Next step: stop. Adapter should show the rejection and must not execute this proposal.', 'npcink-openclaw-adapter' );
		}

		if ( in_array( $status, array( 'expired', 'archived' ), true ) ) {
			return __( 'Next step: reopen or inspect this proposal in Core if it still needs a decision.', 'npcink-openclaw-adapter' );
		}

		return __( 'Next step: use Core as the approval truth and Adapter as the OpenClaw status and execution channel.', 'npcink-openclaw-adapter' );
	}

	/**
	 * Formats a stored UTC datetime for the site's WordPress timezone.
	 *
	 * @param string $datetime UTC datetime string.
	 * @return string
	 */
	private function display_datetime( string $datetime ): string {
		$datetime = trim( $datetime );
		if ( '' === $datetime ) {
			return '';
		}

		$has_timezone = (bool) preg_match( '/(?:Z|UTC|[+-]\d{2}:?\d{2})$/i', $datetime );
		$timestamp    = strtotime( $has_timezone ? $datetime : $datetime . ' UTC' );
		if ( false === $timestamp ) {
			return $datetime;
		}

		if ( function_exists( 'wp_date' ) ) {
			return wp_date( self::DATETIME_DISPLAY_FORMAT, $timestamp );
		}

		if ( function_exists( 'date_i18n' ) ) {
			return date_i18n( self::DATETIME_DISPLAY_FORMAT, $timestamp, true );
		}

		return gmdate( self::DATETIME_DISPLAY_FORMAT, $timestamp );
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
	 * Returns a nonce-verified proposal lookup id from the current GET request.
	 *
	 * @return string
	 */
	private function proposal_lookup_id_from_request(): string {
		$proposal_id = $this->request_text_field( INPUT_GET, 'adapter_proposal_id' );
		if ( '' === $proposal_id ) {
			return '';
		}

		$nonce = $this->request_text_field( INPUT_GET, self::PROPOSAL_LOOKUP_NONCE );
		return wp_verify_nonce( $nonce, self::PROPOSAL_LOOKUP_ACTION ) ? $proposal_id : '';
	}

	/**
	 * Returns a sanitized scalar request field without direct superglobal reads.
	 *
	 * @param int    $input_type One of the INPUT_* constants.
	 * @param string $key        Request field name.
	 * @return string
	 */
	private function request_text_field( int $input_type, string $key ): string {
		$value = filter_input( $input_type, $key, FILTER_UNSAFE_RAW );
		if ( null === $value || false === $value || is_array( $value ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( (string) $value ) );
	}

	/**
	 * Enqueues assets for the one-time credential handoff page.
	 *
	 * @return void
	 */
	private function enqueue_created_handoff_assets(): void {
		wp_enqueue_style(
			'npcink-openclaw-adapter-created-handoff',
			plugins_url( 'assets/created-handoff.css', NPCINK_OPENCLAW_ADAPTER_FILE ),
			array(),
			NPCINK_OPENCLAW_ADAPTER_VERSION
		);
		wp_enqueue_script(
			'npcink-openclaw-adapter-created-handoff',
			plugins_url( 'assets/created-handoff.js', NPCINK_OPENCLAW_ADAPTER_FILE ),
			array(),
			NPCINK_OPENCLAW_ADAPTER_VERSION,
			false
		);
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
		$this->enqueue_created_handoff_assets();
		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>" />
			<meta name="viewport" content="width=device-width, initial-scale=1" />
			<title><?php echo esc_html__( 'OpenClaw Handoff Created', 'npcink-openclaw-adapter' ); ?></title>
			<?php wp_print_styles( array( 'npcink-openclaw-adapter-created-handoff' ) ); ?>
		</head>
		<body data-maa-copied-label="<?php echo esc_attr__( 'Copied', 'npcink-openclaw-adapter' ); ?>">
			<main>
				<h1><?php echo esc_html__( 'OpenClaw Handoff Created', 'npcink-openclaw-adapter' ); ?></h1>
				<div class="notice">
					<p><?php echo esc_html__( 'Copy this Application Password now. WordPress shows it only once and stores only a hash.', 'npcink-openclaw-adapter' ); ?></p>
					<p><?php echo esc_html__( 'Paste it only into OpenClaw dedicated secret field. Do not paste it into chat, tool commands, logs, proposal payloads, files, or copied handoff text.', 'npcink-openclaw-adapter' ); ?></p>
					<p><?php echo esc_html__( 'Use this only for OpenClaw access through Npcink OpenClaw Adapter. Revoke it from the WordPress user profile when the client is retired.', 'npcink-openclaw-adapter' ); ?></p>
				</div>
				<table>
					<tbody>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Adapter URL', 'npcink-openclaw-adapter' ); ?></th>
							<td><code><?php echo esc_html( $base_url ); ?></code></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'WordPress user', 'npcink-openclaw-adapter' ); ?></th>
							<td><code><?php echo esc_html( $username ); ?></code></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Password UUID', 'npcink-openclaw-adapter' ); ?></th>
							<td><code><?php echo esc_html( $password_uuid ); ?></code></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Application Password', 'npcink-openclaw-adapter' ); ?></th>
							<td><textarea id="maa-application-password" rows="3" readonly><?php echo esc_textarea( $password ); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Connection manifest', 'npcink-openclaw-adapter' ); ?></th>
							<td>
								<textarea id="maa-connection-manifest" rows="16" readonly><?php echo esc_textarea( $this->openclaw_connection_manifest_text( $username, $password_uuid ) ); ?></textarea>
								<p class="inline-actions"><button type="button" class="button" data-maa-created-copy-target="maa-connection-manifest"><?php echo esc_html__( 'Copy manifest', 'npcink-openclaw-adapter' ); ?></button></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'OpenClaw env placeholder', 'npcink-openclaw-adapter' ); ?></th>
							<td><textarea rows="6" readonly><?php echo esc_textarea( $this->openclaw_env_text( $username, $include_local_tls ) ); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'WorkBuddy setup', 'npcink-openclaw-adapter' ); ?></th>
							<td>
								<textarea id="maa-workbuddy-setup" rows="18" readonly><?php echo esc_textarea( $this->workbuddy_handoff_text( $username, $password_uuid, $include_local_tls ) ); ?></textarea>
								<p class="inline-actions"><button type="button" class="button" data-maa-created-copy-target="maa-workbuddy-setup"><?php echo esc_html__( 'Copy WorkBuddy setup', 'npcink-openclaw-adapter' ); ?></button></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'OpenClaw handoff', 'npcink-openclaw-adapter' ); ?></th>
							<td><textarea rows="18" readonly><?php echo esc_textarea( $this->openclaw_created_handoff_text( $username, $password_uuid, $include_local_tls ) ); ?></textarea></td>
						</tr>
					</tbody>
				</table>
				<p class="actions"><a class="button" href="<?php echo esc_url( menu_page_url( self::MENU_SLUG, false ) ); ?>"><?php echo esc_html__( 'Back to Npcink OpenClaw Adapter', 'npcink-openclaw-adapter' ); ?></a></p>
			</main>
			<?php wp_print_scripts( array( 'npcink-openclaw-adapter-created-handoff' ) ); ?>
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
			. '  -d \'{"ability_id":"npcink-abilities-toolkit/create-draft","title":"Draft proposal","summary":"OpenClaw requests a governed draft proposal.","input":{"title":"OpenClaw draft","dry_run":true,"commit":false},"preview":{},"caller":{"external_thread_id":"OPENCLAW_THREAD_ID"}}\' \\' . "\n"
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
		return "Use this WordPress site through Npcink OpenClaw Adapter.\n"
			. "Adapter base URL: {$base_url}\n"
			. "Authenticate with WordPress REST Basic Auth using the manifest username and an Application Password stored only in OpenClaw's dedicated secret field.\n"
			. "Do not paste the secret into chat, tool commands, logs, proposal payloads, files, or copied handoff text.\n"
			. "OpenClaw only connects to Adapter. Do not connect OpenClaw directly to Npcink Governance Core.\n"
			. "Start by calling GET /health, GET /help, and GET /capabilities.\n"
			. "For direct_read abilities, call the matching read shortcut or POST /run-read-ability with the real ability_id and input object.\n"
			. "For SEO/GEO/AEO suggestions, the primary entrypoint is content-discoverability-brief through openclaw_recipes.content_discoverability_suggestions: validate Toolbox context, read Toolbox context, build one content_discoverability_brief, and return suggestions only.\n"
			. "Use article-writing-pack only for broad natural-language article requests such as \"help me write an article\": follow openclaw_recipes.ai_article_draft_with_discoverability, draft from the returned ai_article_writing_pack, then use Core proposals for reviewed final writes.\n"
			. "For proposal_required abilities, POST /proposals with the real ability_id, input, preview, and caller metadata. For read-only planning outputs, POST /proposals/from-plan to let Core create governed proposals.\n"
			. "Poll GET /proposals/{proposal_id} for Core status. For the unified user action, call POST /proposals/{proposal_id}/approve-and-execute so Adapter calls Core approve, Core commit-preflight, and one allowlisted final write. If status=rejected, stop and show the rejection status. If status=approved and execution is intended, call POST /proposals/{proposal_id}/execute. Adapter execute routes are final write paths and normalize ability input to dry_run=false and commit=true. Use Adapter commit-preflight only as an advanced diagnostic step; for dry-run-only verification, stop at commit-preflight and do not call execute.\n"
			. "When you have proposal_id or commit-preflight correlation_id, pass them as log_context on POST /run-read-ability or as query fields on read shortcuts so Adapter can add them to AI Request Logs context through wpai_request_log_context. Core Governance Audit is the governance log; AI Request Logs are the provider request log. Adapter context includes ability_id, adapter_request_id, adapter_route, ai_provider, ai_model, governance_source=npcink-governance-core, and nested npcink_governance_core identifiers.\n"
			. "POST /proposals/{proposal_id}/approve and POST /proposals/{proposal_id}/reject are disabled stubs that return approval_proxy_enabled=false. The only Adapter approval path is POST /proposals/{proposal_id}/approve-and-execute, currently allowlisted for npcink-abilities-toolkit/trash-post, npcink-abilities-toolkit/create-draft, npcink-abilities-toolkit/update-post, npcink-abilities-toolkit/patch-post-content, npcink-abilities-toolkit/update-post-blocks, npcink-abilities-toolkit/patch-setting-value, npcink-abilities-toolkit/set-post-seo-meta, npcink-abilities-toolkit/set-post-slug, npcink-abilities-toolkit/set-post-terms, npcink-abilities-toolkit/delete-term, npcink-abilities-toolkit/update-media-details, npcink-abilities-toolkit/upload-media-from-url, npcink-abilities-toolkit/set-post-featured-image, npcink-abilities-toolkit/optimize-media-asset, npcink-abilities-toolkit/replace-media-file, npcink-abilities-toolkit/adopt-cloud-media-derivative, npcink-abilities-toolkit/rename-media-file, npcink-abilities-toolkit/delete-media-permanently, npcink-abilities-toolkit/reply-comment, npcink-abilities-toolkit/trash-comment, and npcink-abilities-toolkit/approve-comment.\n"
			. "Handle failures by code: npcink_openclaw_adapter_approval_proxy_disabled means use approve-and-execute or Core admin; npcink_openclaw_adapter_execute_ability_not_allowed means stop because the ability is outside the Adapter execution allowlist; npcink_openclaw_adapter_proposal_rejected means stop and show the rejection; npcink_openclaw_adapter_preflight_not_authorized or npcink_openclaw_adapter_preflight_item_blocked means stop and show Core preflight details.\n"
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
			'NPCINK_OPENCLAW_ADAPTER_BASE_URL=' . rest_url( Controller::NAMESPACE ),
			'NPCINK_OPENCLAW_ADAPTER_USERNAME=' . $username,
			'NPCINK_OPENCLAW_ADAPTER_APPLICATION_PASSWORD=<store-in-openclaw-secret-vault>',
		);

		if ( $include_local_tls ) {
			$lines[] = 'NPCINK_OPENCLAW_ADAPTER_INSECURE_SSL=true';
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
		return "Npcink OpenClaw Adapter OpenClaw connection\n"
			. "Connection manifest\n"
			. $this->openclaw_connection_manifest_text( $username, $password_uuid ) . "\n\n"
			. "Optional env placeholders\n"
			. $this->openclaw_env_text( $username, $include_local_tls ) . "\n\n"
			. "Secret handling\n"
			. "Paste the secret only into OpenClaw's dedicated secret field. Do not paste it into chat, tool commands, logs, proposal payloads, files, or copied handoff text.\n\n"
			. "Agent rules\n"
			. "1. Connect to Npcink OpenClaw Adapter, not directly to Npcink Governance Core, for productized OpenClaw setup.\n"
			. "2. Authenticate with WordPress REST Basic Auth using the manifest username and the Application Password stored in OpenClaw's dedicated secret field.\n"
			. "3. Call GET /health first and require core_capabilities=true, abilities_catalog=true, approval_proxy_enabled=false, core_proxy_execute=false, and commit_execution=false.\n"
			. "4. Call GET /help to discover adapter routes, then GET /capabilities before reads or proposals and use only real ability_id values returned by Core.\n"
			. "5. For direct_read abilities, call a read shortcut or POST /run-read-ability.\n"
			. "5b. For SEO/GEO/AEO suggestions, the primary entrypoint is content-discoverability-brief: use content_discoverability_suggestions, call content-discoverability-validation, content-discoverability-context, then content-discoverability-brief for one post_id or supplied topic. Return suggestions only; do not write SEO meta, slug, excerpt, schema, media, or posts.\n"
			. "5c. Use article-writing-pack only for broad article requests like \"help me write an article\" or \"write an AI topic article\": use ai_article_draft_with_discoverability, draft only from the returned pack, and send any reviewed final write through Core proposal/preflight.\n"
			. "6. For proposal_required abilities, POST /proposals and poll GET /proposals/{proposal_id}. For read-only planning outputs, POST /proposals/from-plan.\n"
			. "7. For the unified user action, call POST /proposals/{proposal_id}/approve-and-execute. Adapter calls Core approve, Core commit-preflight, and one allowlisted final write. Current execution allowlist: npcink-abilities-toolkit/trash-post, npcink-abilities-toolkit/create-draft, npcink-abilities-toolkit/update-post, npcink-abilities-toolkit/patch-post-content, npcink-abilities-toolkit/update-post-blocks, npcink-abilities-toolkit/patch-setting-value, npcink-abilities-toolkit/set-post-seo-meta, npcink-abilities-toolkit/set-post-slug, npcink-abilities-toolkit/set-post-terms, npcink-abilities-toolkit/delete-term, npcink-abilities-toolkit/update-media-details, npcink-abilities-toolkit/upload-media-from-url, npcink-abilities-toolkit/set-post-featured-image, npcink-abilities-toolkit/optimize-media-asset, npcink-abilities-toolkit/replace-media-file, npcink-abilities-toolkit/adopt-cloud-media-derivative, npcink-abilities-toolkit/rename-media-file, npcink-abilities-toolkit/delete-media-permanently, npcink-abilities-toolkit/reply-comment, npcink-abilities-toolkit/trash-comment, npcink-abilities-toolkit/approve-comment.\n"
			. "7b. If status=rejected, stop and show the rejection status. If status=approved and execution is intended, call POST /proposals/{proposal_id}/execute. Adapter execute routes are final write paths and normalize ability input to dry_run=false and commit=true. Use Adapter commit-preflight only as an advanced diagnostic step; for dry-run-only verification, stop at commit-preflight and do not call execute.\n"
			. "8. Pass proposal_id and correlation_id as log_context or read shortcut query fields so AI Request Logs can correlate execution rows with Core audit. Core Governance Audit is the governance log; AI Request Logs are the provider request log. For provider smoke, POST /ai-provider-log-correlation-smoke with a configured text generation ai_provider and ai_model after commit-preflight; local Ollama examples use ai_provider=ollama and ai_model=qwen3.5:0.8b when available.\n"
			. "9. Treat POST /proposals/{proposal_id}/approve and POST /proposals/{proposal_id}/reject as disabled stubs. Approval without execution is handled in Npcink Governance Core admin.\n"
			. "10. Failure code handling: npcink_openclaw_adapter_approval_proxy_disabled => use approve-and-execute or Core admin; npcink_openclaw_adapter_execute_ability_not_allowed => stop; npcink_openclaw_adapter_proposal_rejected => stop; npcink_openclaw_adapter_preflight_not_authorized or npcink_openclaw_adapter_preflight_item_blocked => show Core preflight details and do not retry execution.\n"
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
		return "Npcink OpenClaw Adapter WorkBuddy connection\n"
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

		return "Npcink OpenClaw Adapter local CLI setup\n\n"
			. "Use this local CLI to call Adapter. Do not read, print, summarize, or copy ~/.npcink-openclaw-adapter/keypair-profiles/*.json.\n\n"
			. "Pairing command for the user terminal:\n"
			. $connect_command . "\n\n"
			. "Connection status:\n"
			. $status_command . "\n\n"
			. "Adapter requests:\n"
			. "{$request_prefix} GET /health\n"
			. "{$request_prefix} GET /capabilities\n"
			. "{$request_prefix} POST /proposals/from-plan --body-file=/tmp/magick-proposal.json\n"
			. "{$request_prefix} POST /proposals/PROPOSAL_ID/commit-preflight --intent=preflight\n"
			. "{$request_prefix} POST /proposals/PROPOSAL_ID/approve-and-execute --intent=commit\n\n"
			. "Rules for OpenClaw:\n"
			. "1. Do not read, cat, print, summarize, or copy the local keypair profile file.\n"
			. "2. Do not output private_key_jwk, public_key_jwk, Authorization, X-Npcink-Signature, or any signing headers.\n"
			. "3. POST bodies must contain only non-secret JSON. Use --body-file or --body-stdin.\n"
			. "4. Use only Adapter-relative routes such as /health, /capabilities, or /proposals.\n"
			. "5. WordPress writes must still go through Core proposal, approval, and preflight.\n"
			. "6. Dry-run or preflight-only verification must stop at commit-preflight; final execute routes require --intent=commit.";
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
			<p class="description"><?php echo esc_html__( 'No key-pair clients are registered for this administrator yet. Run the reconnect command, approve the browser prompt, then refresh this page.', 'npcink-openclaw-adapter' ); ?></p>
			<?php
			return;
		endif;
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Client', 'npcink-openclaw-adapter' ); ?></th>
					<th><?php echo esc_html__( 'Fingerprint', 'npcink-openclaw-adapter' ); ?></th>
					<th><?php echo esc_html__( 'Scopes', 'npcink-openclaw-adapter' ); ?></th>
					<th><?php echo esc_html__( 'Last used', 'npcink-openclaw-adapter' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'npcink-openclaw-adapter' ); ?></th>
					<th><?php echo esc_html__( 'Action', 'npcink-openclaw-adapter' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $key_records as $record ) : ?>
					<tr>
						<td><?php echo esc_html( (string) ( $record['client_name'] ?? '' ) ); ?><br><code><?php echo esc_html( (string) ( $record['key_id'] ?? '' ) ); ?></code></td>
						<td><code><?php echo esc_html( (string) ( $record['fingerprint'] ?? '' ) ); ?></code></td>
						<td><?php echo esc_html( implode( ', ', is_array( $record['scopes'] ?? null ) ? $record['scopes'] : array() ) ); ?></td>
						<td><?php echo esc_html( $this->display_datetime( (string) ( $record['last_used_at'] ?? '' ) ) ); ?></td>
						<td><?php echo '' === (string) ( $record['revoked_at'] ?? '' ) ? esc_html__( 'Active', 'npcink-openclaw-adapter' ) : esc_html__( 'Revoked', 'npcink-openclaw-adapter' ); ?></td>
						<td>
							<?php if ( '' === (string) ( $record['revoked_at'] ?? '' ) ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="<?php echo esc_attr( self::REVOKE_KEY_ACTION ); ?>" />
									<input type="hidden" name="key_id" value="<?php echo esc_attr( (string) ( $record['key_id'] ?? '' ) ); ?>" />
									<?php wp_nonce_field( self::REVOKE_KEY_ACTION . '_' . (string) ( $record['key_id'] ?? '' ) ); ?>
									<button type="submit" class="button"><?php echo esc_html__( 'Revoke', 'npcink-openclaw-adapter' ); ?></button>
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
		return 'cd ~ && npm exec --yes --package @npcink/openclaw-adapter-cli@0.1.0 -- npcink-openclaw-adapter';
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
