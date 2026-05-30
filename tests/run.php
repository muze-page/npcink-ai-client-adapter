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
		'/ai-provider-log-correlation-smoke',
		'ai_provider_log_correlation_smoke',
		'/site-info',
		'/site-summary',
			'/wp-diagnostics-summary',
			'/wp-ops-diagnostics-detail',
			'active-plugins-detail',
			'plugin-conflict-diagnostics',
			'current-user-permissions',
			'php-extensions',
			'object-cache-status',
			'rewrite-rules-status',
			'ssl-https-status',
			'recent-error-log',
			'recent-error-log-tail',
			'database-info',
			'cron-events-detail',
			'custom-post-types',
			'roles-capabilities',
			'widgets-sidebars',
			'block-theme-assets',
			'search-index-status',
			'server-info',
			'integrations-status',
			'seo-summary',
			'security-summary',
			'performance-summary',
			'read_shortcut_definitions',
			'ops_diagnostics_input',
			'ops_diagnostics_plugin_conflict_input',
			'ops_diagnostics_log_input',
			'include_log_contents',
			'include_active_plugins',
			'include_inactive_plugins',
			'include_plugin_updates',
			'include_must_use_plugins',
			'include_dropins',
			'max_plugins_per_group',
			'tail_lines',
			'since_minutes',
			'not explicitly requested',
			'inactive plugin rows are not requested by default',
			'route_groups',
			'help_route_groups',
			'help_routes_flat',
			'help_route_purpose',
			'GET /term?id={id}',
			'normalize_shortcut_input',
			"'term_id'",
			'plugin_conflict_input',
			'plugin_group_fields',
			'error_log_summary_fields',
			'workflow-recipes',
		'/workflow-recipe',
		"'media'",
		"'posts'",
		"'post-context'",
		"'users'",
		"'menu'",
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
		'with_ai_request_log_context',
		'ai_request_log_context',
		'proposal_id',
		'correlation_id',
		'adapter_request_id',
		'adapter_route',
		'ai_provider',
		'ai_model',
		'governance_source',
		'magick_ai_core',
		'wp_ai_client_prompt',
		'qwen3.5:0.8b',
		'POST /ai-provider-log-correlation-smoke',
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
			'magick-ai-abilities/wp-ops-diagnostics-detail',
			'magick-ai-abilities/list-workflow-recipes',
		'magick-ai-abilities/get-workflow-recipe',
		'magick-ai/list-posts',
		'magick-ai/get-post-context',
		'magick-ai/list-media',
		'magick-ai/list-users',
		'magick-ai/get-menu',
		'magick-ai/list-pages',
		'magick-ai/get-site-operations-dashboard',
	) as $required
) {
	maa_adapter_assert( false !== strpos( $controller, $required ), 'Controller contains required text: ' . $required );
}
maa_adapter_assert( false === strpos( $controller, 'include_log_tail' ), 'Adapter does not implement old include_log_tail compatibility.' );
maa_adapter_assert( false === strpos( $controller, 'include_error_log' ), 'Adapter does not use old include_error_log diagnostics input.' );
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
		'docs/openclaw-consumer-acceptance.md',
		'Create OpenClaw handoff',
		'only its hash',
		'Application Password handoff',
		'OpenClaw only connects to Adapter',
		'Approval is handled in Magick AI Core admin',
		'GET /wp-json/magick-ai-adapter/v1/help',
		'POST /wp-json/magick-ai-adapter/v1/ai-provider-log-correlation-smoke',
			'GET /wp-json/magick-ai-adapter/v1/site-summary',
			'GET /wp-json/magick-ai-adapter/v1/active-plugins-detail',
			'GET /wp-json/magick-ai-adapter/v1/plugin-conflict-diagnostics',
			'GET /wp-json/magick-ai-adapter/v1/recent-error-log',
			'GET /wp-json/magick-ai-adapter/v1/recent-error-log-tail',
			'GET /wp-json/magick-ai-adapter/v1/current-user-permissions',
			'GET /wp-json/magick-ai-adapter/v1/php-extensions',
			'GET /wp-json/magick-ai-adapter/v1/object-cache-status',
			'GET /wp-json/magick-ai-adapter/v1/database-info',
			'GET /wp-json/magick-ai-adapter/v1/rewrite-rules-status',
			'GET /wp-json/magick-ai-adapter/v1/cron-events-detail',
			'GET /wp-json/magick-ai-adapter/v1/server-info',
			'GET /wp-json/magick-ai-adapter/v1/integrations-status',
			'GET /wp-json/magick-ai-adapter/v1/seo-summary',
			'GET /wp-json/magick-ai-adapter/v1/security-summary',
			'GET /wp-json/magick-ai-adapter/v1/performance-summary',
			'GET /wp-json/magick-ai-adapter/v1/posts',
			'GET /wp-json/magick-ai-adapter/v1/post-context',
			'GET /wp-json/magick-ai-adapter/v1/users',
			'GET /wp-json/magick-ai-adapter/v1/menu',
			'GET /wp-json/magick-ai-adapter/v1/media',
			'include_log_contents',
			'include_inactive_plugins',
			'max_plugins_per_group',
			'tail_lines',
			'since_minutes',
			'not explicitly requested',
			'error_log.tail_entries',
			'error_log.summary',
			'fatal_count',
			'deprecated_count',
			'summary_source',
			'error_log.summary.by_severity',
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
		'Core Governance Audit is the governance log',
		'AI Request Logs are the provider request log',
		'adapter_request_id',
		'adapter_route',
		'ai_provider=ollama',
		'ai_model=qwen3.5:0.8b',
		'governance_source=magick-ai-core',
		'magick_ai_core.proposal_id',
		'magick_ai_core.correlation_id',
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
		'adapter_request_id',
		'adapter_route',
		'ai_provider',
		'ai_model',
		'qwen3.5:0.8b',
		'/ai-provider-log-correlation-smoke',
		'Core Governance Audit is the governance log',
		'AI Request Logs are the provider request log',
			'/site-info',
			'/active-plugins-detail',
			'/plugin-conflict-diagnostics',
			'/recent-error-log',
			'/recent-error-log-tail',
			'/current-user-permissions',
			'/database-info',
			'/posts?author_id=1',
			'/terms?taxonomy=category&include_sample_posts=1',
			'/menu?location=primary',
			'/media?per_page=1',
		'/pages?per_page=1',
		'wp-diagnostics-summary` to decide whether plugin details',
		'include_inactive_plugins=true',
		'Default inactive plugin rows are not missing',
		'error_log.summary.fatal_count',
		'not explicitly requested',
		'contents_included=true',
		'error_log.summary.by_severity',
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
		'GET /wp-json/magick-ai-adapter/v1/recent-error-log-tail',
		'GET /wp-json/magick-ai-adapter/v1/plugin-conflict-diagnostics',
		'GET /wp-json/magick-ai-adapter/v1/posts',
		'GET /wp-json/magick-ai-adapter/v1/post-context',
		'GET /wp-json/magick-ai-adapter/v1/users',
		'GET /wp-json/magick-ai-adapter/v1/menu',
		'POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/approve',
		'POST /wp-json/magick-ai-adapter/v1/proposals/{proposal_id}/reject',
		'magick_ai_adapter_approval_proxy_disabled',
		'approval_proxy_enabled=false',
		'approval_surface=magick_ai_core_admin',
		'proposals:read',
		'audit_timeline',
		'AI Request Logs',
		'wp-diagnostics-summary` is only a quick overview',
		'POST /wp-json/magick-ai-adapter/v1/ai-provider-log-correlation-smoke',
		'Core Governance Audit is the governance log',
		'AI Request Logs are the provider request log',
		'adapter_request_id',
		'adapter_route',
		'ai_provider',
		'ai_model',
		'qwen3.5:0.8b',
		'governance_source=magick-ai-core',
		'magick_ai_core.proposal_id',
		'magick_ai_core.correlation_id',
		'include_log_contents',
		'include_inactive_plugins',
		'max_plugins_per_group',
		'tail_lines',
		'since_minutes',
		'not explicitly requested',
		'Inactive plugin rows are not requested by default',
		'fatal_count',
		'warning_count',
		'deprecated_count',
		'notice_count',
		'summary_source',
		'error_log.tail_entries',
		'error_log.summary.by_severity',
		'include_log_tail',
		'Magick AI runtime, MCP, or cloud status',
		'plugin_file',
		'network_active',
		'dependency_count',
		'magick_ai_permissions',
		'severity_filter',
		'since_minutes',
		'author_id',
		'include_sample_posts',
		'author_profile',
		'attached_to',
		'magick-ai/get-menu',
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

$acceptance = maa_adapter_read( $root . '/docs/openclaw-consumer-acceptance.md' );
foreach (
	array(
		'OpenClaw Consumer Acceptance',
		'OpenClaw must not connect directly to Magick AI Core for productized use.',
		'GET /health',
		'GET /help',
		'GET /capabilities',
		'GET /proposals',
		'GET /proposals/{proposal_id}',
		'POST /proposals',
		'POST /proposals/{proposal_id}/commit-preflight',
		'active-plugins-detail',
		'plugin-conflict-diagnostics',
		'current-user-permissions',
		'database-info',
		'recent-error-log-tail',
		'wp-ops-diagnostics-detail',
		'include_log_contents=false',
		'include_inactive_plugins=false',
		'include_inactive_plugins=true',
		'not explicitly',
		'error_log.summary.fatal_count',
		'error_log.tail_entries',
		'error_log.summary.by_severity',
		'Core Governance Audit',
		'AI Request Logs',
		'POST /ai-provider-log-correlation-smoke',
		'ai_provider=ollama',
		'ai_model=qwen3.5:0.8b',
		'adapter_request_id',
		'governance_source=magick-ai-core',
		'provider request log',
		'magick_ai_adapter_approval_proxy_disabled',
		'commit_execution=false',
		'no new runtime ownership',
	) as $required
) {
	maa_adapter_assert( false !== strpos( $acceptance, $required ), 'OpenClaw acceptance doc contains required text: ' . $required );
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
			'/magick-ai-adapter/v1/active-plugins-detail',
			'/magick-ai-adapter/v1/plugin-conflict-diagnostics',
			'/magick-ai-adapter/v1/recent-error-log',
			'/magick-ai-adapter/v1/recent-error-log-tail',
			'/magick-ai-adapter/v1/current-user-permissions',
			'/magick-ai-adapter/v1/database-info',
			'/magick-ai-adapter/v1/cron-events-detail',
			'/magick-ai-adapter/v1/workflow-recipes',
		'/magick-ai-adapter/v1/workflow-recipe',
		'/magick-ai-adapter/v1/proposals',
		'/magick-ai-adapter/v1/ai-provider-log-correlation-smoke',
		'/ai/v1/logs',
		'/magick-ai-core/v1/audit',
			'magick-ai-abilities/site-summary',
			'magick-ai-abilities/wp-diagnostics-summary',
			'magick-ai-abilities/wp-ops-diagnostics-detail',
			'magick-ai-abilities/list-workflow-recipes',
		'magick-ai-abilities/get-workflow-recipe',
		'magick-ai/list-posts',
		'magick-ai/get-post-context',
		'magick-ai/list-users',
		'magick-ai/get-menu',
		'include_log_contents',
		'include_inactive_plugins',
		'max_plugins_per_group',
		'tail_entries',
		'by_severity',
		'plugin conflict diagnostic requests inactive plugin rows',
		'default error log diagnostic exposes severity summary without log contents',
		'adapter_request_id',
		'adapter_route',
		'ai_provider',
		'ai_model',
		'governance_source',
		'magick_ai_core',
		'qwen3.5:0.8b',
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
			'adapter active plugins shortcut uses ops diagnostics detail',
			'adapter runs active plugins diagnostic shortcut through ops detail',
			'adapter runs current user permissions diagnostic shortcut through ops detail',
			'adapter runs explicit error log tail diagnostic through ops detail',
			'default error log diagnostic does not include log contents',
			'explicit error log tail diagnostic exposes redacted tail entries',
			'adapter runs database diagnostic shortcut',
			'adapter runs cron events diagnostic shortcut',
		'adapter approval stub returns disabled response',
		'adapter rejection stub returns disabled response',
		'adapter approval stub does not change Core proposal status',
		'adapter rejection stub does not change Core proposal status',
		'adapter local Ollama provider smoke succeeds',
		'AI Request Logs context contains proposal id',
		'AI Request Logs context contains correlation id',
		'AI Request Logs context contains explicit provider even if provider column is blank',
		'AI Request Logs context contains explicit model even if provider column is blank',
		'Core Governance Audit filters by the same provider smoke correlation id',
		'adapter status smoke cleaned created proposal records',
	) as $required
) {
	maa_adapter_assert( false !== strpos( $smoke_wp, $required ), 'WordPress smoke contains required text: ' . $required );
}

echo "Static contracts: ok\n";
