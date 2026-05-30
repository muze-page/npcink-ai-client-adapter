<?php
/**
 * Real WordPress smoke test for Magick AI Adapter.
 *
 * @package MagickAIAdapter
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress is not loaded.\n" );
	exit( 1 );
}

/**
 * Assertion helper.
 *
 * @param bool   $condition Condition.
 * @param string $message Message.
 * @return void
 */
function maa_adapter_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, '[fail] ' . $message . "\n" );
		exit( 1 );
	}

	echo '[ok] ' . $message . "\n";
}

/**
 * Dispatches an adapter REST request.
 *
 * @param string              $method HTTP method.
 * @param string              $route REST route.
 * @param array<string,mixed> $params Params.
 * @return array<string,mixed>
 */
function maa_adapter_smoke_rest( string $method, string $route, array $params = array() ): array {
	$result = maa_adapter_smoke_rest_result( $method, $route, $params );
	maa_adapter_smoke_assert( $result['status'] >= 200 && $result['status'] < 300, $method . ' ' . $route . ' returned HTTP ' . $result['status'] );

	return is_array( $result['data'] ) ? $result['data'] : array();
}

/**
 * Finds an AI Request Logs item by adapter context identifiers.
 *
 * @param string $proposal_id Proposal id.
 * @param string $correlation_id Correlation id.
 * @return array<string,mixed>|null
 */
function maa_adapter_smoke_find_ai_request_log( string $proposal_id, string $correlation_id ): ?array {
	$logs = maa_adapter_smoke_rest(
		'GET',
		'/ai/v1/logs',
		array(
			'type'     => 'ai_client',
			'status'   => 'success',
			'per_page' => 100,
		)
	);

	foreach ( $logs as $log ) {
		if ( ! is_array( $log ) ) {
			continue;
		}

		$context = is_array( $log['context'] ?? null ) ? $log['context'] : array();
		if (
			$proposal_id === (string) ( $context['proposal_id'] ?? '' )
			&& $correlation_id === (string) ( $context['correlation_id'] ?? '' )
		) {
			return $log;
		}
	}

	return null;
}

/**
 * Dispatches an adapter REST request and returns status/data.
 *
 * @param string              $method HTTP method.
 * @param string              $route REST route.
 * @param array<string,mixed> $params Params.
 * @return array{status:int,data:mixed}
 */
function maa_adapter_smoke_rest_result( string $method, string $route, array $params = array() ): array {
	wp_set_current_user( 1 );

	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}

	$response = rest_do_request( $request );

	return array(
		'status' => (int) $response->get_status(),
		'data'   => $response->get_data(),
	);
}

/**
 * Returns capability rows keyed by ability id.
 *
 * @param array<string,mixed> $capabilities Capabilities response.
 * @return array<string,array<string,mixed>>
 */
function maa_adapter_smoke_capabilities_by_id( array $capabilities ): array {
	$by_id = array();
	foreach ( (array) ( $capabilities['items'] ?? array() ) as $item ) {
		if ( is_array( $item ) && '' !== (string) ( $item['ability_id'] ?? '' ) ) {
			$by_id[ (string) $item['ability_id'] ] = $item;
		}
	}

	return $by_id;
}

$health = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/health' );
maa_adapter_smoke_assert( true === (bool) ( $health['core_capabilities'] ?? false ), 'adapter sees Core capabilities route' );
maa_adapter_smoke_assert( true === (bool) ( $health['abilities_catalog'] ?? false ), 'adapter sees WordPress Abilities catalog route' );
maa_adapter_smoke_assert( false === (bool) ( $health['core_proxy_execute'] ?? true ), 'adapter keeps Core proxy execution disabled' );
maa_adapter_smoke_assert( false === (bool) ( $health['commit_execution'] ?? true ), 'adapter keeps Core commit execution disabled' );
maa_adapter_smoke_assert( false === (bool) ( $health['approval_proxy_enabled'] ?? true ), 'adapter health keeps approval proxy disabled' );
maa_adapter_smoke_assert( 'magick_ai_core_admin' === (string) ( $health['approval_surface'] ?? '' ), 'adapter health exposes Core admin approval surface' );
maa_adapter_smoke_assert( array_key_exists( 'core_app_token_configured', $health ), 'adapter health exposes Core app token configured state without token value' );
maa_adapter_smoke_assert( isset( $health['read_shortcuts']['media'] ), 'adapter health exposes expanded read shortcuts' );
maa_adapter_smoke_assert( 'magick-ai-abilities/wp-ops-diagnostics-detail' === (string) ( $health['read_shortcuts']['active-plugins-detail'] ?? '' ), 'adapter active plugins shortcut uses ops diagnostics detail' );
maa_adapter_smoke_assert( 'magick-ai-abilities/wp-ops-diagnostics-detail' === (string) ( $health['read_shortcuts']['recent-error-log-tail'] ?? '' ), 'adapter explicit log shortcut uses ops diagnostics detail' );
maa_adapter_smoke_assert( 'magick-ai/list-posts' === (string) ( $health['read_shortcuts']['posts'] ?? '' ), 'adapter health exposes posts shortcut' );
maa_adapter_smoke_assert( 'magick-ai/get-post-context' === (string) ( $health['read_shortcuts']['post-context'] ?? '' ), 'adapter health exposes post context shortcut' );
maa_adapter_smoke_assert( 'magick-ai/list-users' === (string) ( $health['read_shortcuts']['users'] ?? '' ), 'adapter health exposes users shortcut' );
maa_adapter_smoke_assert( 'magick-ai/get-menu' === (string) ( $health['read_shortcuts']['menu'] ?? '' ), 'adapter health exposes menu shortcut' );
maa_adapter_smoke_assert( false === (bool) ( $health['diagnostics']['default_input']['include_log_contents'] ?? true ), 'adapter health exposes diagnostics default without log contents' );
maa_adapter_smoke_assert( true === (bool) ( $health['diagnostics']['explicit_log_input']['include_log_contents'] ?? false ), 'adapter health exposes explicit diagnostics log input' );
maa_adapter_smoke_assert( in_array( 'adapter_request_id', (array) ( $health['ai_request_log_context_fields'] ?? array() ), true ), 'adapter health exposes provider log adapter request id context' );
maa_adapter_smoke_assert( in_array( 'ai_provider', (array) ( $health['ai_request_log_context_fields'] ?? array() ), true ), 'adapter health exposes provider context field' );
maa_adapter_smoke_assert( in_array( 'ai_model', (array) ( $health['ai_request_log_context_fields'] ?? array() ), true ), 'adapter health exposes model context field' );
maa_adapter_smoke_assert( in_array( 'magick_ai_core.correlation_id', (array) ( $health['ai_request_log_context_fields'] ?? array() ), true ), 'adapter health exposes nested Core correlation context field' );
maa_adapter_smoke_assert( 'wordpress_rest_application_password' === (string) ( $health['auth']['type'] ?? '' ), 'adapter health exposes Application Password auth handoff' );
maa_adapter_smoke_assert( 'proposals:read' === (string) ( $health['supported_guidance']['proposal_status']['core_required_scope'] ?? '' ), 'adapter health documents proposal status read scope' );
maa_adapter_smoke_assert( in_array( 'GET /proposals/{proposal_id}', (array) ( $health['proposal_status_routes'] ?? array() ), true ), 'adapter health exposes proposal status routes' );

$help = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/help' );
maa_adapter_smoke_assert( in_array( 'GET /proposals', (array) ( $help['routes']['proposal_status'] ?? array() ), true ), 'adapter help exposes proposal list route' );
maa_adapter_smoke_assert( in_array( 'GET /proposals/{proposal_id}', (array) ( $help['routes']['proposal_status'] ?? array() ), true ), 'adapter help exposes proposal detail route' );
maa_adapter_smoke_assert( in_array( 'POST /proposals/{proposal_id}/approve', (array) ( $help['routes']['governance'] ?? array() ), true ), 'adapter help exposes approval disabled stub route' );
maa_adapter_smoke_assert( in_array( 'POST /proposals/{proposal_id}/reject', (array) ( $help['routes']['governance'] ?? array() ), true ), 'adapter help exposes rejection disabled stub route' );
maa_adapter_smoke_assert( in_array( 'POST /ai-provider-log-correlation-smoke', (array) ( $help['routes']['provider_log_correlation'] ?? array() ), true ), 'adapter help exposes provider log correlation smoke route' );
maa_adapter_smoke_assert( false === (bool) ( $help['approval_proxy_enabled'] ?? true ), 'adapter help keeps approval proxy disabled' );
maa_adapter_smoke_assert( 'magick_ai_core_admin' === (string) ( $help['approval_surface'] ?? '' ), 'adapter help exposes Core admin approval surface' );
maa_adapter_smoke_assert( array_key_exists( 'core_app_token_configured', $help ), 'adapter help exposes Core app token configured state without token value' );
maa_adapter_smoke_assert( false === (bool) ( $help['non_goals']['approval_proxy_enabled'] ?? true ), 'adapter help keeps approval proxy disabled' );
maa_adapter_smoke_assert( false === (bool) ( $help['non_goals']['reject_proxy_enabled'] ?? true ), 'adapter help keeps rejection proxy disabled' );

$capabilities = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/capabilities' );
$by_id        = maa_adapter_smoke_capabilities_by_id( $capabilities );
maa_adapter_smoke_assert( isset( $by_id['magick-ai-abilities/site-summary'] ), 'adapter exposes site-summary capability through Core' );
maa_adapter_smoke_assert( isset( $by_id['magick-ai-abilities/wp-diagnostics-summary'] ), 'adapter exposes diagnostics capability through Core' );
maa_adapter_smoke_assert( isset( $by_id['magick-ai-abilities/wp-ops-diagnostics-detail'] ), 'adapter exposes ops diagnostics capability through Core' );
maa_adapter_smoke_assert( isset( $by_id['magick-ai-abilities/list-workflow-recipes'] ), 'adapter exposes workflow recipe list capability through Core' );
maa_adapter_smoke_assert( isset( $by_id['magick-ai-abilities/get-workflow-recipe'] ), 'adapter exposes workflow recipe detail capability through Core' );
maa_adapter_smoke_assert( 'direct_read' === (string) ( $by_id['magick-ai-abilities/site-summary']['governance_mode'] ?? '' ), 'site-summary is direct read' );

$site_summary = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/site-summary' );
maa_adapter_smoke_assert( 'magick-ai-abilities/site-summary' === (string) ( $site_summary['ability_id'] ?? '' ), 'adapter runs site-summary read ability' );
maa_adapter_smoke_assert( is_array( $site_summary['result'] ?? null ), 'site-summary returns a result object' );

$diagnostics = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/wp-diagnostics-summary' );
maa_adapter_smoke_assert( 'magick-ai-abilities/wp-diagnostics-summary' === (string) ( $diagnostics['ability_id'] ?? '' ), 'adapter runs diagnostics read ability' );
maa_adapter_smoke_assert( is_array( $diagnostics['result'] ?? null ), 'diagnostics returns a result object' );

$active_plugins = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/active-plugins-detail' );
maa_adapter_smoke_assert( 'magick-ai-abilities/wp-ops-diagnostics-detail' === (string) ( $active_plugins['ability_id'] ?? '' ), 'adapter runs active plugins diagnostic shortcut through ops detail' );
maa_adapter_smoke_assert( is_array( $active_plugins['result']['plugins'] ?? null ), 'active plugins diagnostic returns plugin details object' );
maa_adapter_smoke_assert( is_array( $active_plugins['result']['plugins']['active'] ?? null ), 'active plugins diagnostic returns active plugin details' );
maa_adapter_smoke_assert( array_key_exists( 'update_available', (array) ( $active_plugins['result']['plugins'] ?? array() ) ), 'active plugins diagnostic returns plugin update details' );

$current_user_permissions = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/current-user-permissions' );
maa_adapter_smoke_assert( 'magick-ai-abilities/wp-ops-diagnostics-detail' === (string) ( $current_user_permissions['ability_id'] ?? '' ), 'adapter runs current user permissions diagnostic shortcut through ops detail' );
maa_adapter_smoke_assert( is_array( $current_user_permissions['result']['current_user'] ?? null ), 'current user permissions diagnostic returns user capability object' );
maa_adapter_smoke_assert( array_key_exists( 'capabilities', (array) ( $current_user_permissions['result']['current_user'] ?? array() ) ), 'current user permissions diagnostic returns capability details' );

$recent_error_log = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/recent-error-log' );
maa_adapter_smoke_assert( 'magick-ai-abilities/wp-ops-diagnostics-detail' === (string) ( $recent_error_log['ability_id'] ?? '' ), 'adapter runs default error log diagnostic through ops detail' );
maa_adapter_smoke_assert( false === (bool) ( $recent_error_log['result']['error_log']['contents_included'] ?? true ), 'default error log diagnostic does not include log contents' );
maa_adapter_smoke_assert( array_key_exists( 'tail_entries', (array) ( $recent_error_log['result']['error_log'] ?? array() ) ), 'default error log diagnostic still exposes tail entries field' );
maa_adapter_smoke_assert( is_array( $recent_error_log['result']['error_log']['summary']['by_severity'] ?? null ), 'default error log diagnostic exposes severity summary' );

$recent_error_log_tail = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/recent-error-log-tail' );
maa_adapter_smoke_assert( 'magick-ai-abilities/wp-ops-diagnostics-detail' === (string) ( $recent_error_log_tail['ability_id'] ?? '' ), 'adapter runs explicit error log tail diagnostic through ops detail' );
maa_adapter_smoke_assert( true === (bool) ( $recent_error_log_tail['result']['error_log']['contents_included'] ?? false ), 'explicit error log tail diagnostic includes log contents' );
maa_adapter_smoke_assert( is_array( $recent_error_log_tail['result']['error_log']['tail_entries'] ?? null ), 'explicit error log tail diagnostic exposes redacted tail entries' );
maa_adapter_smoke_assert( is_array( $recent_error_log_tail['result']['error_log']['summary']['by_severity'] ?? null ), 'explicit error log tail diagnostic exposes severity summary' );

$database_info = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/database-info' );
maa_adapter_smoke_assert( 'magick-ai-abilities/wp-ops-diagnostics-detail' === (string) ( $database_info['ability_id'] ?? '' ), 'adapter runs database diagnostic shortcut' );
maa_adapter_smoke_assert( is_array( $database_info['result']['database'] ?? null ), 'database diagnostic returns database object' );
maa_adapter_smoke_assert( is_array( $database_info['result']['php']['extensions']['loaded'] ?? null ), 'database diagnostic result preserves PHP extension details' );
maa_adapter_smoke_assert( is_array( $database_info['result']['object_cache'] ?? null ), 'database diagnostic result preserves object cache details' );
maa_adapter_smoke_assert( is_array( $database_info['result']['rewrite'] ?? null ), 'database diagnostic result preserves rewrite details' );
maa_adapter_smoke_assert( is_array( $database_info['result']['server'] ?? null ), 'database diagnostic result preserves server details' );
maa_adapter_smoke_assert( array_key_exists( 'https', (array) ( $database_info['result'] ?? array() ) ), 'database diagnostic result preserves HTTPS details' );
maa_adapter_smoke_assert( array_key_exists( 'content_types', (array) ( $database_info['result'] ?? array() ) ), 'database diagnostic result preserves content type details' );
maa_adapter_smoke_assert( array_key_exists( 'roles', (array) ( $database_info['result'] ?? array() ) ), 'database diagnostic result preserves role details' );
maa_adapter_smoke_assert( array_key_exists( 'widgets', (array) ( $database_info['result'] ?? array() ) ), 'database diagnostic result preserves widget details' );
maa_adapter_smoke_assert( array_key_exists( 'block_theme', (array) ( $database_info['result'] ?? array() ) ), 'database diagnostic result preserves block theme details' );
maa_adapter_smoke_assert( array_key_exists( 'search', (array) ( $database_info['result'] ?? array() ) ), 'database diagnostic result preserves search details' );
maa_adapter_smoke_assert( array_key_exists( 'integrations', (array) ( $database_info['result'] ?? array() ) ), 'database diagnostic result preserves integration details' );
maa_adapter_smoke_assert( array_key_exists( 'seo_summary', (array) ( $database_info['result'] ?? array() ) ), 'database diagnostic result preserves SEO summary' );
maa_adapter_smoke_assert( array_key_exists( 'security_summary', (array) ( $database_info['result'] ?? array() ) ), 'database diagnostic result preserves security summary' );
maa_adapter_smoke_assert( array_key_exists( 'performance_summary', (array) ( $database_info['result'] ?? array() ) ), 'database diagnostic result preserves performance summary' );

$cron_events_detail = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/cron-events-detail' );
maa_adapter_smoke_assert( 'magick-ai-abilities/wp-ops-diagnostics-detail' === (string) ( $cron_events_detail['ability_id'] ?? '' ), 'adapter runs cron events diagnostic shortcut' );
maa_adapter_smoke_assert( is_array( $cron_events_detail['result']['cron_events'] ?? null ), 'cron events diagnostic returns cron object' );
maa_adapter_smoke_assert( array_key_exists( 'events', (array) ( $cron_events_detail['result']['cron_events'] ?? array() ) ), 'cron events diagnostic returns event details' );

$workflow_recipes = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/workflow-recipes' );
maa_adapter_smoke_assert( isset( $workflow_recipes['result']['cases']['article_publish_preflight'] ), 'adapter returns workflow recipe list result' );

$workflow_recipe = maa_adapter_smoke_rest(
	'GET',
	'/magick-ai-adapter/v1/workflow-recipe',
	array(
		'recipe_id' => 'workflow/wordpress_comment_compliance_handoff',
	)
);
maa_adapter_smoke_assert( 'magick-ai-abilities/get-workflow-recipe' === (string) ( $workflow_recipe['ability_id'] ?? '' ), 'adapter runs workflow recipe detail ability' );
maa_adapter_smoke_assert( 'magick-ai/get-comment-compliance-handoff' === (string) ( $workflow_recipe['result']['entrypoint_ability_id'] ?? '' ), 'adapter returns workflow recipe detail result' );

$site_info = maa_adapter_smoke_rest(
	'GET',
	'/magick-ai-adapter/v1/site-info',
	array(
		'proposal_id'    => 'proposal-log-context-smoke',
		'correlation_id' => 'correlation-log-context-smoke',
	)
);
maa_adapter_smoke_assert( 'magick-ai/site-info' === (string) ( $site_info['ability_id'] ?? '' ), 'adapter runs site-info shortcut' );
maa_adapter_smoke_assert( is_array( $site_info['result'] ?? null ), 'site-info shortcut returns a result object' );
maa_adapter_smoke_assert( is_array( $site_info['log_context'] ?? null ), 'adapter read response exposes AI request log context' );
maa_adapter_smoke_assert( 'proposal-log-context-smoke' === (string) ( $site_info['log_context']['proposal_id'] ?? '' ), 'adapter read log context carries proposal id' );
maa_adapter_smoke_assert( 'correlation-log-context-smoke' === (string) ( $site_info['log_context']['correlation_id'] ?? '' ), 'adapter read log context carries correlation id' );

$media = maa_adapter_smoke_rest(
	'GET',
	'/magick-ai-adapter/v1/media',
	array(
		'per_page' => 1,
	)
);
maa_adapter_smoke_assert( 'magick-ai/list-media' === (string) ( $media['ability_id'] ?? '' ), 'adapter runs media shortcut with query input' );
maa_adapter_smoke_assert( is_array( $media['result'] ?? null ), 'media shortcut returns a result object' );

$pages = maa_adapter_smoke_rest(
	'GET',
	'/magick-ai-adapter/v1/pages',
	array(
		'per_page' => 1,
	)
);
maa_adapter_smoke_assert( 'magick-ai/list-pages' === (string) ( $pages['ability_id'] ?? '' ), 'adapter runs pages shortcut with query input' );
maa_adapter_smoke_assert( is_array( $pages['result'] ?? null ), 'pages shortcut returns a result object' );

$write_run = maa_adapter_smoke_rest_result(
	'POST',
	'/magick-ai-adapter/v1/run-read-ability',
	array(
		'ability_id' => 'magick-ai/create-draft',
		'input'      => array(
			'title'   => 'Adapter smoke should not execute writes',
			'dry_run' => true,
			'commit'  => false,
		),
	)
);
maa_adapter_smoke_assert( 403 === (int) $write_run['status'], 'adapter refuses direct execution for proposal-required ability' );

$proposal = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/create-draft',
		'title'      => 'Adapter proposal status smoke',
		'summary'    => 'Proposal status relay smoke, no final write.',
		'input'      => array(
			'title'   => 'Adapter proposal status smoke',
			'dry_run' => true,
			'commit'  => false,
		),
		'preview'    => array(
			'mode' => 'adapter_status_smoke',
		),
		'caller'     => array(
			'external_thread_id' => 'adapter-status-smoke',
		),
	)
);
$proposal_id = (string) ( $proposal['proposal_id'] ?? '' );
maa_adapter_smoke_assert( '' !== $proposal_id, 'adapter creates Core proposal for status smoke' );
maa_adapter_smoke_assert( 'pending' === (string) ( $proposal['status'] ?? '' ), 'adapter created proposal starts pending' );
maa_adapter_smoke_assert( 'openclaw_adapter' === (string) ( $proposal['caller']['caller_type'] ?? '' ), 'adapter proposal caller marks OpenClaw adapter' );
maa_adapter_smoke_assert( 'magick-ai-adapter' === (string) ( $proposal['caller']['via'] ?? '' ), 'adapter proposal caller preserves adapter source' );

$proposal_list = maa_adapter_smoke_rest(
	'GET',
	'/magick-ai-adapter/v1/proposals',
	array(
		'limit' => 50,
	)
);
$found_proposal = false;
foreach ( (array) ( $proposal_list['items'] ?? array() ) as $item ) {
	if ( is_array( $item ) && $proposal_id === (string) ( $item['proposal_id'] ?? '' ) ) {
		$found_proposal = true;
		maa_adapter_smoke_assert( 'magick-ai/create-draft' === (string) ( $item['ability_id'] ?? '' ), 'adapter proposal list preserves ability id' );
		maa_adapter_smoke_assert( 'pending' === (string) ( $item['status'] ?? '' ), 'adapter proposal list preserves status' );
		break;
	}
}
maa_adapter_smoke_assert( $found_proposal, 'adapter returns created proposal in Core proposal list' );

$proposal_detail = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $proposal_id ) );
maa_adapter_smoke_assert( $proposal_id === (string) ( $proposal_detail['proposal_id'] ?? '' ), 'adapter returns proposal detail through Core' );
maa_adapter_smoke_assert( 'pending' === (string) ( $proposal_detail['status'] ?? '' ), 'adapter proposal detail preserves status' );
maa_adapter_smoke_assert( 'Adapter proposal status smoke' === (string) ( $proposal_detail['title'] ?? '' ), 'adapter proposal detail preserves title' );
maa_adapter_smoke_assert( is_array( $proposal_detail['input'] ?? null ), 'adapter proposal detail preserves input' );
maa_adapter_smoke_assert( is_array( $proposal_detail['preview'] ?? null ), 'adapter proposal detail preserves preview' );
maa_adapter_smoke_assert( is_array( $proposal_detail['caller'] ?? null ), 'adapter proposal detail preserves caller' );
maa_adapter_smoke_assert( is_array( $proposal_detail['audit_timeline'] ?? null ), 'adapter proposal detail preserves audit timeline' );

$approval_stub = maa_adapter_smoke_rest_result( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $proposal_id ) . '/approve' );
maa_adapter_smoke_assert( 403 === (int) $approval_stub['status'], 'adapter approval stub returns HTTP 403' );
maa_adapter_smoke_assert( is_array( $approval_stub['data'] ?? null ), 'adapter approval stub returns response object' );
maa_adapter_smoke_assert( 'magick_ai_adapter_approval_proxy_disabled' === (string) ( $approval_stub['data']['code'] ?? '' ), 'adapter approval stub returns disabled response' );
maa_adapter_smoke_assert( false === (bool) ( $approval_stub['data']['approval_proxy_enabled'] ?? true ), 'adapter approval stub reports disabled proxy' );
maa_adapter_smoke_assert( 'magick_ai_core_admin' === (string) ( $approval_stub['data']['approval_surface'] ?? '' ), 'adapter approval stub reports Core admin approval surface' );

$after_approval_stub = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $proposal_id ) );
maa_adapter_smoke_assert( 'pending' === (string) ( $after_approval_stub['status'] ?? '' ), 'adapter approval stub does not change Core proposal status' );

$rejection_stub = maa_adapter_smoke_rest_result( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $proposal_id ) . '/reject' );
maa_adapter_smoke_assert( 403 === (int) $rejection_stub['status'], 'adapter rejection stub returns HTTP 403' );
maa_adapter_smoke_assert( is_array( $rejection_stub['data'] ?? null ), 'adapter rejection stub returns response object' );
maa_adapter_smoke_assert( 'magick_ai_adapter_approval_proxy_disabled' === (string) ( $rejection_stub['data']['code'] ?? '' ), 'adapter rejection stub returns disabled response' );
maa_adapter_smoke_assert( false === (bool) ( $rejection_stub['data']['approval_proxy_enabled'] ?? true ), 'adapter rejection stub reports disabled proxy' );
maa_adapter_smoke_assert( 'magick_ai_core_admin' === (string) ( $rejection_stub['data']['approval_surface'] ?? '' ), 'adapter rejection stub reports Core admin approval surface' );

$after_rejection_stub = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $proposal_id ) );
maa_adapter_smoke_assert( 'pending' === (string) ( $after_rejection_stub['status'] ?? '' ), 'adapter rejection stub does not change Core proposal status' );

$approved = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-core/v1/proposals/' . rawurlencode( $proposal_id ) . '/approve',
	array(
		'note' => 'Adapter provider log correlation smoke approval.',
	)
);
maa_adapter_smoke_assert( 'approved' === (string) ( $approved['status'] ?? '' ), 'Core admin REST approval succeeds for provider log correlation smoke' );

$preflight = maa_adapter_smoke_rest( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $proposal_id ) . '/commit-preflight' );
$correlation_id = (string) ( $preflight['correlation_id'] ?? '' );
maa_adapter_smoke_assert( '' !== $correlation_id, 'adapter commit preflight returns correlation id for provider log smoke' );
maa_adapter_smoke_assert( false === (bool) ( $preflight['commit_execution'] ?? true ), 'adapter commit preflight keeps final execution disabled for provider log smoke' );
maa_adapter_smoke_assert( $correlation_id === (string) ( $preflight['approval_context']['correlation_id'] ?? '' ), 'adapter commit preflight approval context carries matching correlation id' );

$provider_smoke = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/ai-provider-log-correlation-smoke',
	array(
		'proposal_id'    => $proposal_id,
		'correlation_id' => $correlation_id,
		'ability_id'     => 'magick-ai/create-draft',
		'ai_provider'    => 'ollama',
		'ai_model'       => 'qwen3.5:0.8b',
		'prompt'         => 'Reply with exactly: OK',
	)
);
maa_adapter_smoke_assert( 'success' === (string) ( $provider_smoke['status'] ?? '' ), 'adapter local Ollama provider smoke succeeds' );
maa_adapter_smoke_assert( 'ollama' === (string) ( $provider_smoke['log_context']['ai_provider'] ?? '' ), 'adapter provider smoke context carries ollama provider' );
maa_adapter_smoke_assert( 'qwen3.5:0.8b' === (string) ( $provider_smoke['log_context']['ai_model'] ?? '' ), 'adapter provider smoke context carries qwen model' );
maa_adapter_smoke_assert( $proposal_id === (string) ( $provider_smoke['log_context']['magick_ai_core']['proposal_id'] ?? '' ), 'adapter provider smoke context carries nested Core proposal id' );
maa_adapter_smoke_assert( $correlation_id === (string) ( $provider_smoke['log_context']['magick_ai_core']['correlation_id'] ?? '' ), 'adapter provider smoke context carries nested Core correlation id' );

$ai_log = maa_adapter_smoke_find_ai_request_log( $proposal_id, $correlation_id );
maa_adapter_smoke_assert( is_array( $ai_log ), 'AI Request Logs contains provider request with proposal and correlation context' );
$ai_log_context = is_array( $ai_log['context'] ?? null ) ? $ai_log['context'] : array();
maa_adapter_smoke_assert( 'success' === (string) ( $ai_log['status'] ?? '' ), 'AI Request Logs provider request status is success' );
maa_adapter_smoke_assert( $proposal_id === (string) ( $ai_log_context['proposal_id'] ?? '' ), 'AI Request Logs context contains proposal id' );
maa_adapter_smoke_assert( $correlation_id === (string) ( $ai_log_context['correlation_id'] ?? '' ), 'AI Request Logs context contains correlation id' );
maa_adapter_smoke_assert( 'magick-ai/create-draft' === (string) ( $ai_log_context['ability_id'] ?? '' ), 'AI Request Logs context contains ability id' );
maa_adapter_smoke_assert( '' !== (string) ( $ai_log_context['adapter_request_id'] ?? '' ), 'AI Request Logs context contains adapter request id' );
maa_adapter_smoke_assert( '/magick-ai-adapter/v1/ai-provider-log-correlation-smoke' === (string) ( $ai_log_context['adapter_route'] ?? '' ), 'AI Request Logs context contains adapter route' );
maa_adapter_smoke_assert( 'ollama' === (string) ( $ai_log_context['ai_provider'] ?? '' ), 'AI Request Logs context contains explicit provider even if provider column is blank' );
maa_adapter_smoke_assert( 'qwen3.5:0.8b' === (string) ( $ai_log_context['ai_model'] ?? '' ), 'AI Request Logs context contains explicit model even if provider column is blank' );
maa_adapter_smoke_assert( 'magick-ai-core' === (string) ( $ai_log_context['governance_source'] ?? '' ), 'AI Request Logs context contains Core governance source' );
maa_adapter_smoke_assert( $proposal_id === (string) ( $ai_log_context['magick_ai_core']['proposal_id'] ?? '' ), 'AI Request Logs context contains nested Core proposal id' );
maa_adapter_smoke_assert( $correlation_id === (string) ( $ai_log_context['magick_ai_core']['correlation_id'] ?? '' ), 'AI Request Logs context contains nested Core correlation id' );

$core_correlation_audit = maa_adapter_smoke_rest(
	'GET',
	'/magick-ai-core/v1/audit',
	array(
		'correlation_id' => $correlation_id,
		'limit'          => 10,
	)
);
maa_adapter_smoke_assert( count( (array) ( $core_correlation_audit['items'] ?? array() ) ) >= 1, 'Core Governance Audit filters by the same provider smoke correlation id' );

global $wpdb;
$wpdb->delete( $wpdb->prefix . 'magick_ai_core_audit_log', array( 'proposal_id' => $proposal_id ), array( '%s' ) );
$wpdb->delete( $wpdb->prefix . 'magick_ai_core_proposals', array( 'proposal_id' => $proposal_id ), array( '%s' ) );
maa_adapter_smoke_assert( true, 'adapter status smoke cleaned created proposal records' );

echo "magick-ai-adapter WordPress smoke: ok\n";
