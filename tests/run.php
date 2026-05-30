<?php
/**
 * Static contracts for Magick AI Adapter.
 *
 * @package MagickAIAdapter
 */

$root = dirname( __DIR__ );

/**
 * Assertion helper.
 *
 * @param bool   $condition Condition.
 * @param string $message Message.
 * @return void
 */
function maa_adapter_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, '[fail] ' . $message . "\n" );
		exit( 1 );
	}
}

/**
 * Reads a file.
 *
 * @param string $path Path.
 * @return string
 */
function maa_adapter_read( string $path ): string {
	$contents = is_readable( $path ) ? file_get_contents( $path ) : false;
	return is_string( $contents ) ? $contents : '';
}

$main = maa_adapter_read( $root . '/magick-ai-adapter.php' );
maa_adapter_assert( false !== strpos( $main, 'Plugin Name: Magick AI Adapter' ), 'Main plugin has WordPress plugin header.' );
maa_adapter_assert( false !== strpos( $main, 'plugins_loaded' ), 'Main plugin boots on plugins_loaded.' );

$controller = maa_adapter_read( $root . '/includes/Rest/Controller.php' );
foreach (
	array(
		'magick-ai-adapter/v1',
		'/health',
		'/help',
		'/capabilities',
		'/run-read-ability',
		'/site-info',
		'/site-summary',
		'/wp-diagnostics-summary',
		'workflow-recipes',
		'/workflow-recipe',
		"'media'",
		"'pages'",
		"'site-operations-dashboard'",
		'/proposals',
		'list_proposals',
		'get_proposal',
		'approval_proxy_disabled',
		'/proposals/(?P<proposal_id>[A-Za-z0-9_-]+)/approve',
		'/proposals/(?P<proposal_id>[A-Za-z0-9_-]+)/reject',
		'/commit-preflight',
		"current_user_can( 'manage_options' )",
		'/magick-ai-core/v1/capabilities',
		'/magick-ai-core/v1/proposals',
		"caller_type' => 'openclaw_adapter'",
		"'via'         => 'magick-ai-adapter'",
		'/wp-abilities/v1/abilities/',
		'governance_mode',
		'direct_read',
		'proposal_required',
		'wp_abilities_rest',
		'adapter_after_core_preflight',
		'core_proxy_execute',
		'commit_execution',
		'adapter_base_url',
		'help_url',
		'proposal_list_url',
		'proposal_detail_url',
		'read_shortcuts',
		'wordpress_rest_application_password',
		'proposal_status',
		'proposals:read',
		'approval_proxy_enabled',
		'approval_surface',
		'magick_ai_core_admin',
		'log_context',
		'wpai_request_log_context',
		'append_ai_request_log_context',
		'ai_request_log_context',
		'proposal_id',
		'correlation_id',
		'magick_ai_adapter',
		'proposal_status_routes',
		'core_app_token_configured',
		'core_app_token_required_scopes',
		'GET /proposals',
		'GET /proposals/{proposal_id}',
		'POST /proposals/{proposal_id}/approve',
		'POST /proposals/{proposal_id}/reject',
		'magick_ai_adapter_approval_proxy_disabled',
		'Approval is handled in Magick AI Core admin.',
		'MAGICK_AI_ADAPTER_CORE_APP_TOKEN',
		'magick_ai_adapter_core_app_token',
		'x-magick-ai-core-app-token',
		'magick_ai_adapter_proposal_required',
		'magick-ai/site-info',
		'magick-ai-abilities/site-summary',
		'magick-ai-abilities/wp-diagnostics-summary',
		'magick-ai-abilities/list-workflow-recipes',
		'magick-ai-abilities/get-workflow-recipe',
		'magick-ai/list-media',
		'magick-ai/list-pages',
		'magick-ai/get-site-operations-dashboard',
	) as $required
) {
	maa_adapter_assert( false !== strpos( $controller, $required ), 'Controller contains required text: ' . $required );
}
maa_adapter_assert( false === strpos( $controller, 'can_approve_proposals' ), 'Adapter does not call Core proposal approval permission path.' );
maa_adapter_assert( false === strpos( $controller, 'can_reject_proposals' ), 'Adapter does not call Core proposal rejection permission path.' );
maa_adapter_assert( false === strpos( $controller, 'approve_proposal' ), 'Adapter does not call Core proposal approval callback.' );
maa_adapter_assert( false === strpos( $controller, 'reject_proposal' ), 'Adapter does not call Core proposal rejection callback.' );
maa_adapter_assert( false === strpos( $controller, 'proposals:approve' ), 'Adapter does not request proposal approval scope.' );
maa_adapter_assert( false === strpos( $controller, 'proposals:reject' ), 'Adapter does not request proposal rejection scope.' );

$plugin = maa_adapter_read( $root . '/includes/Plugin.php' );
foreach (
	array(
		'admin_menu',
		'admin_post_magick_ai_adapter_create_openclaw_password',
		'register_admin_page',
		'handle_create_openclaw_password',
		'Admin\\Connection_Page',
	) as $required
) {
	maa_adapter_assert( false !== strpos( $plugin, $required ), 'Plugin registers admin page text: ' . $required );
}

$connection_page = maa_adapter_read( $root . '/includes/Admin/Connection_Page.php' );
foreach (
	array(
		'OpenClaw Connection',
		'OpenClaw Handoff Created',
		'Create OpenClaw handoff',
		'add_options_page',
		'WP_Application_Passwords::create_new_application_password',
		'Local testing',
		'Include LocalWP TLS test setting in OpenClaw env and handoff.',
		'This only changes copied client configuration',
		'Adapter endpoints',
		'Proposal list',
		'Proposal detail',
		'Approval disabled stub',
		'Reject disabled stub',
		'Authentication handoff',
		'Example requests',
		'Handoff prompt',
		'MAGICK_AI_ADAPTER_APPLICATION_PASSWORD',
		'Copy this Application Password now.',
		'GET /help',
		'GET /proposals/{proposal_id}',
		'approval_proxy_enabled=false',
		'Approval is handled in Magick AI Core admin.',
		'AI Request Logs context',
		'wpai_request_log_context',
		'log_context',
		'proposal_id',
		'correlation_id',
		'core_proxy_execute=false',
		'commit_execution=false',
		'APPLICATION_PASSWORD',
		'Controller::read_shortcuts',
	) as $required
) {
	maa_adapter_assert( false !== strpos( $connection_page, $required ), 'Connection page contains required text: ' . $required );
}

$readme = maa_adapter_read( $root . '/README.md' );
foreach (
	array(
		'thin OpenClaw channel plugin',
		'read Magick AI Core capability guidance',
		'run approved direct-read abilities through WordPress Abilities API',
		'create Core proposals',
		'does not define abilities',
		'execute final write mutations',
		'Settings -> OpenClaw Connection',
		'docs/openclaw-quickstart.md',
		'Create OpenClaw handoff',
		'only its hash',
		'Application Password handoff',
		'OpenClaw only connects to Adapter',
		'Approval is handled in Magick AI Core admin',
		'GET /wp-json/magick-ai-adapter/v1/help',
		'GET /wp-json/magick-ai-adapter/v1/site-summary',
		'GET /wp-json/magick-ai-adapter/v1/media',
		'GET /wp-json/magick-ai-adapter/v1/site-operations-dashboard',
		'GET /wp-json/magick-ai-adapter/v1/proposals',
		'GET /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}',
		'POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/approve',
		'POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/reject',
		'MAGICK_AI_ADAPTER_CORE_APP_TOKEN',
		'magick_ai_adapter_core_app_token',
		'magick_ai_adapter_approval_proxy_disabled',
		'approval_proxy_enabled=false',
		'approval_surface=magick_ai_core_admin',
		'POST /wp-json/magick-ai-adapter/v1/run-read-ability',
		'proposals:read',
		'audit_timeline',
		'governance_mode=direct_read',
		'governance_mode=proposal_required',
		'core_proxy_execute=false',
		'commit_execution=false',
	) as $required
) {
	maa_adapter_assert( false !== strpos( $readme, $required ), 'README contains required text: ' . $required );
}

$quickstart = maa_adapter_read( $root . '/docs/openclaw-quickstart.md' );
foreach (
	array(
		'OpenClaw Quickstart',
		'OpenClaw only connects to Adapter',
		'https://magick-ai.local',
		'WordPress administrator username: `1`',
		'WordPress administrator password: `1`',
		'Application Password',
		'Create OpenClaw handoff',
		'GET /health',
		'GET /help',
		'GET /capabilities',
		'/proposals?limit=10',
		'/proposals/PROPOSAL_ID',
		'proposals:read',
		'audit_timeline',
		'MAGICK_AI_ADAPTER_CORE_APP_TOKEN',
		'magick_ai_adapter_core_app_token',
		'magick_ai_adapter_approval_proxy_disabled',
		'Approval is handled in Magick AI Core admin',
		'approval_proxy_enabled=false',
		'approval_surface=magick_ai_core_admin',
		'log_context',
		'wpai_request_log_context',
		'proposal_id',
		'correlation_id',
		'/site-info',
		'/media?per_page=1',
		'/pages?per_page=1',
		'Proposal-Required Write Flow',
		'core_proxy_execute=false',
		'commit_execution=false',
		'does not execute final WordPress writes',
	) as $required
) {
	maa_adapter_assert( false !== strpos( $quickstart, $required ), 'Quickstart contains required text: ' . $required );
}

$contract = maa_adapter_read( $root . '/docs/openclaw-adapter-contract.md' );
foreach (
	array(
		'initial productization contract',
		'magick-ai-abilities',
		'magick-ai-core',
		'core_proxy_execute',
		'commit_execution',
		'Settings -> OpenClaw Connection',
		'Application Password Handoff',
		'Proposal Status Read Proxy',
		'Approval Disabled Stub Contract',
		'OpenClaw only connects to Adapter',
		'Approval is handled in Magick AI Core admin.',
		'show the raw password',
		'It must not store raw Application Passwords',
		'username `1` and password `1`',
		'Proposal-Required Write Flow',
		'GET /wp-json/magick-ai-adapter/v1/help',
		'GET /wp-json/magick-ai-adapter/v1/proposals',
		'GET /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}',
		'POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/approve',
		'POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/reject',
		'magick_ai_adapter_approval_proxy_disabled',
		'approval_proxy_enabled=false',
		'approval_surface=magick_ai_core_admin',
		'proposals:read',
		'audit_timeline',
		'AI Request Logs',
		'wpai_request_log_context',
		'log_context',
		'proposal_id',
		'correlation_id',
		'MAGICK_AI_ADAPTER_CORE_APP_TOKEN',
		'magick_ai_adapter_core_app_token',
		'It does not execute abilities marked `proposal_required`.',
		'The adapter does not approve proposals',
		'It does not store proposal state.',
		'All routes require `manage_options`',
	) as $required
) {
	maa_adapter_assert( false !== strpos( $contract, $required ), 'Adapter contract contains required text: ' . $required );
}

$agents = maa_adapter_read( $root . '/AGENTS.md' );
foreach (
	array(
		'thin OpenClaw channel layer',
		'does not own',
		'magick-ai-abilities',
		'magick-ai-core',
		'core_proxy_execute=false',
		'commit_execution=false',
	) as $required
) {
maa_adapter_assert( false !== strpos( $agents, $required ), 'AGENTS.md contains required text: ' . $required );
}

$smoke_sh = maa_adapter_read( $root . '/tests/smoke-wp.sh' );
foreach (
	array(
		'wp-content/plugins/magick-ai-adapter',
		'wp plugin activate magick-ai-adapter',
		'eval-file "$ROOT_DIR/tests/smoke-wp.php"',
	) as $required
) {
	maa_adapter_assert( false !== strpos( $smoke_sh, $required ), 'Smoke shell contains required text: ' . $required );
}

$smoke_wp = maa_adapter_read( $root . '/tests/smoke-wp.php' );
foreach (
	array(
		'/magick-ai-adapter/v1/health',
		'/magick-ai-adapter/v1/help',
		'/magick-ai-adapter/v1/capabilities',
		'/magick-ai-adapter/v1/site-summary',
		'/magick-ai-adapter/v1/wp-diagnostics-summary',
		'/magick-ai-adapter/v1/workflow-recipes',
		'/magick-ai-adapter/v1/workflow-recipe',
		'/magick-ai-adapter/v1/proposals',
		'magick-ai-abilities/site-summary',
		'magick-ai-abilities/wp-diagnostics-summary',
		'magick-ai-abilities/list-workflow-recipes',
		'magick-ai-abilities/get-workflow-recipe',
		'adapter refuses direct execution for proposal-required ability',
		'adapter help exposes proposal list route',
		'adapter help exposes proposal detail route',
		'adapter help keeps approval proxy disabled',
		'adapter help keeps rejection proxy disabled',
		'adapter health exposes Core app token configured state without token value',
		'adapter help exposes Core app token configured state without token value',
		'adapter returns created proposal in Core proposal list',
		'adapter returns proposal detail through Core',
		'adapter proposal detail preserves audit timeline',
		'adapter read response exposes AI request log context',
		'adapter read log context carries proposal id',
		'adapter read log context carries correlation id',
		'adapter approval stub returns disabled response',
		'adapter rejection stub returns disabled response',
		'adapter approval stub does not change Core proposal status',
		'adapter rejection stub does not change Core proposal status',
		'adapter status smoke cleaned created proposal records',
	) as $required
) {
	maa_adapter_assert( false !== strpos( $smoke_wp, $required ), 'WordPress smoke contains required text: ' . $required );
}

echo "Static contracts: ok\n";
