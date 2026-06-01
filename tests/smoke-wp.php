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
 * Checks whether help route rows include a route.
 *
 * @param array<string,mixed> $help Help response.
 * @param string              $method HTTP method.
 * @param string              $path Route path.
 * @return bool
 */
function maa_adapter_smoke_help_has_route( array $help, string $method, string $path ): bool {
	foreach ( (array) ( $help['routes'] ?? array() ) as $route ) {
		if ( ! is_array( $route ) ) {
			continue;
		}

		if ( $method === (string) ( $route['method'] ?? '' ) && $path === (string) ( $route['path'] ?? '' ) ) {
			return true;
		}
	}

	return false;
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
	if ( 'GET' === strtoupper( $method ) ) {
		$request->set_query_params( $params );
	} else {
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
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

/**
 * Returns a configured AI text generation provider/model for smoke.
 *
 * @return array{provider:string,model:string}
 */
function maa_adapter_smoke_text_generation_model(): array {
	$providers = maa_adapter_smoke_rest(
		'GET',
		'/ai/v1/providers',
		array(
			'capability' => 'text_generation',
		)
	);

	$fallback = array(
		'provider' => '',
		'model'    => '',
	);

	foreach ( $providers as $provider ) {
		if ( ! is_array( $provider ) ) {
			continue;
		}

		$provider_id = (string) ( $provider['id'] ?? '' );
		foreach ( (array) ( $provider['models'] ?? array() ) as $model ) {
			if ( ! is_array( $model ) ) {
				continue;
			}

			$model_id = (string) ( $model['id'] ?? '' );
			if ( '' === $provider_id || '' === $model_id ) {
				continue;
			}

			if ( 'ollama' === $provider_id && 'qwen3.5:0.8b' === $model_id ) {
				return array(
					'provider' => $provider_id,
					'model'    => $model_id,
				);
			}

			if ( '' === $fallback['provider'] ) {
				$fallback = array(
					'provider' => $provider_id,
					'model'    => $model_id,
				);
			}
		}
	}

	maa_adapter_smoke_assert( '' !== $fallback['provider'] && '' !== $fallback['model'], 'AI provider smoke found a configured text generation model' );
	return $fallback;
}

/**
 * Creates an unattached image attachment with missing metadata for media plan smoke.
 *
 * @return int
 */
function maa_adapter_smoke_create_media_plan_attachment(): int {
	$uploads = wp_upload_dir();
	$url     = is_array( $uploads ) && ! empty( $uploads['baseurl'] ) ? trailingslashit( (string) $uploads['baseurl'] ) . 'adapter-media-plan-smoke.jpg' : '';
	$id      = wp_insert_attachment(
		array(
			'post_title'     => 'Adapter Media Plan Smoke',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image/jpeg',
			'post_parent'    => 0,
			'post_excerpt'   => '',
			'post_content'   => '',
			'guid'           => $url,
		),
		'',
		0,
		true
	);

	maa_adapter_smoke_assert( ! is_wp_error( $id ) && (int) $id > 0, 'adapter smoke created media fixture attachment' );
	update_post_meta( (int) $id, '_wp_attachment_image_alt', '' );
	return (int) $id;
}

/**
 * Creates a post fixture for approved proposal execution smoke.
 *
 * @return int
 */
function maa_adapter_smoke_create_trash_post_fixture(): int {
	$post_id = wp_insert_post(
		array(
			'post_title'   => 'Adapter Approved Trash Smoke',
			'post_content' => 'Adapter approved proposal execution smoke.',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		),
		true
	);

	maa_adapter_smoke_assert( ! is_wp_error( $post_id ) && (int) $post_id > 0, 'adapter smoke created trash-post fixture' );
	return (int) $post_id;
}

/**
 * Creates a comment fixture for comment execution smoke.
 *
 * @param int        $post_id Post id.
 * @param string     $content Comment content.
 * @param int|string $approved Comment approval state.
 * @return int
 */
function maa_adapter_smoke_create_comment_fixture(
	int $post_id,
	string $content = 'Adapter reply-comment parent smoke.',
	$approved = 1
): int {
	$comment_id = wp_insert_comment(
		array(
			'comment_post_ID'      => $post_id,
			'comment_content'      => $content,
			'comment_approved'     => $approved,
			'comment_author'       => 'Adapter Smoke',
			'comment_author_email' => 'adapter-smoke@example.test',
		)
	);

	maa_adapter_smoke_assert( (int) $comment_id > 0, 'adapter smoke created comment fixture' );
	return (int) $comment_id;
}

$maa_adapter_smoke_cleanup_proposal_ids = array();
$maa_adapter_smoke_cleanup_attachment_ids = array();
$maa_adapter_smoke_cleanup_post_ids = array();
$maa_adapter_smoke_cleanup_comment_ids = array();
$maa_adapter_smoke_cleanup_terms = array();

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
maa_adapter_smoke_assert( 'magick-ai-abilities/wp-ops-diagnostics-detail' === (string) ( $health['read_shortcuts']['plugin-conflict-diagnostics'] ?? '' ), 'adapter plugin conflict shortcut uses ops diagnostics detail' );
maa_adapter_smoke_assert( 'magick-ai-abilities/wp-ops-diagnostics-detail' === (string) ( $health['read_shortcuts']['recent-error-log-tail'] ?? '' ), 'adapter explicit log shortcut uses ops diagnostics detail' );
maa_adapter_smoke_assert( 'magick-ai/list-posts' === (string) ( $health['read_shortcuts']['posts'] ?? '' ), 'adapter health exposes posts shortcut' );
maa_adapter_smoke_assert( 'magick-ai/get-post-context' === (string) ( $health['read_shortcuts']['post-context'] ?? '' ), 'adapter health exposes post context shortcut' );
maa_adapter_smoke_assert( 'magick-ai/list-users' === (string) ( $health['read_shortcuts']['users'] ?? '' ), 'adapter health exposes users shortcut' );
maa_adapter_smoke_assert( 'magick-ai/get-menu' === (string) ( $health['read_shortcuts']['menu'] ?? '' ), 'adapter health exposes menu shortcut' );
maa_adapter_smoke_assert( false === (bool) ( $health['diagnostics']['default_input']['include_log_contents'] ?? true ), 'adapter health exposes diagnostics default without log contents' );
maa_adapter_smoke_assert( true === (bool) ( $health['diagnostics']['default_input']['include_active_plugins'] ?? false ), 'adapter health exposes active plugin rows by default' );
maa_adapter_smoke_assert( false === (bool) ( $health['diagnostics']['default_input']['include_inactive_plugins'] ?? true ), 'adapter health does not request inactive plugin rows by default' );
maa_adapter_smoke_assert( true === (bool) ( $health['diagnostics']['default_input']['include_plugin_updates'] ?? false ), 'adapter health requests plugin update rows by default' );
maa_adapter_smoke_assert( true === (bool) ( $health['diagnostics']['default_input']['include_must_use_plugins'] ?? false ), 'adapter health requests must-use plugin rows by default' );
maa_adapter_smoke_assert( true === (bool) ( $health['diagnostics']['default_input']['include_dropins'] ?? false ), 'adapter health requests dropin rows by default' );
maa_adapter_smoke_assert( 100 === (int) ( $health['diagnostics']['default_input']['max_plugins_per_group'] ?? 0 ), 'adapter health exposes default plugin group row limit' );
maa_adapter_smoke_assert( true === (bool) ( $health['diagnostics']['plugin_conflict_input']['include_inactive_plugins'] ?? false ), 'adapter health exposes deep plugin conflict inactive rows input' );
maa_adapter_smoke_assert( 200 === (int) ( $health['diagnostics']['plugin_conflict_input']['max_plugins_per_group'] ?? 0 ), 'adapter health exposes deep plugin conflict row limit' );
maa_adapter_smoke_assert( true === (bool) ( $health['diagnostics']['explicit_log_input']['include_log_contents'] ?? false ), 'adapter health exposes explicit diagnostics log input' );
maa_adapter_smoke_assert( in_array( 'adapter_request_id', (array) ( $health['ai_request_log_context_fields'] ?? array() ), true ), 'adapter health exposes provider log adapter request id context' );
maa_adapter_smoke_assert( in_array( 'ai_provider', (array) ( $health['ai_request_log_context_fields'] ?? array() ), true ), 'adapter health exposes provider context field' );
maa_adapter_smoke_assert( in_array( 'ai_model', (array) ( $health['ai_request_log_context_fields'] ?? array() ), true ), 'adapter health exposes model context field' );
maa_adapter_smoke_assert( in_array( 'magick_ai_core.correlation_id', (array) ( $health['ai_request_log_context_fields'] ?? array() ), true ), 'adapter health exposes nested Core correlation context field' );
maa_adapter_smoke_assert( 'wordpress_rest_application_password' === (string) ( $health['auth']['type'] ?? '' ), 'adapter health exposes Application Password auth handoff' );
maa_adapter_smoke_assert( 'proposals:read' === (string) ( $health['supported_guidance']['proposal_status']['core_required_scope'] ?? '' ), 'adapter health documents proposal status read scope' );
maa_adapter_smoke_assert( in_array( 'GET /proposals/{proposal_id}', (array) ( $health['proposal_status_routes'] ?? array() ), true ), 'adapter health exposes proposal status routes' );
maa_adapter_smoke_assert( in_array( 'POST /proposals/from-plan', (array) ( $health['plan_proposal_routes'] ?? array() ), true ), 'adapter health exposes plan-to-proposal route' );
maa_adapter_smoke_assert( in_array( 'POST /proposals/{proposal_id}/execute', (array) ( $health['approved_proposal_execution_routes'] ?? array() ), true ), 'adapter health exposes approved proposal execution route' );
maa_adapter_smoke_assert( in_array( 'POST /proposals/{proposal_id}/approve-and-execute', (array) ( $health['approved_proposal_execution_routes'] ?? array() ), true ), 'adapter health exposes approve-and-execute route' );
maa_adapter_smoke_assert( in_array( 'magick-ai/trash-post', (array) ( $health['allowed_execute_ability_ids'] ?? array() ), true ), 'adapter health exposes trash-post execute allowlist' );
maa_adapter_smoke_assert( in_array( 'magick-ai/create-draft', (array) ( $health['allowed_execute_ability_ids'] ?? array() ), true ), 'adapter health exposes create-draft execute allowlist' );
maa_adapter_smoke_assert( in_array( 'magick-ai/update-post', (array) ( $health['allowed_execute_ability_ids'] ?? array() ), true ), 'adapter health exposes update-post execute allowlist' );
maa_adapter_smoke_assert( in_array( 'magick-ai/set-post-seo-meta', (array) ( $health['allowed_execute_ability_ids'] ?? array() ), true ), 'adapter health exposes set-post-seo-meta execute allowlist' );
maa_adapter_smoke_assert( in_array( 'magick-ai/set-post-slug', (array) ( $health['allowed_execute_ability_ids'] ?? array() ), true ), 'adapter health exposes set-post-slug execute allowlist' );
maa_adapter_smoke_assert( in_array( 'magick-ai/set-post-terms', (array) ( $health['allowed_execute_ability_ids'] ?? array() ), true ), 'adapter health exposes set-post-terms execute allowlist' );
maa_adapter_smoke_assert( in_array( 'magick-ai/delete-term', (array) ( $health['allowed_execute_ability_ids'] ?? array() ), true ), 'adapter health exposes delete-term execute allowlist' );
maa_adapter_smoke_assert( in_array( 'magick-ai/update-media-details', (array) ( $health['allowed_execute_ability_ids'] ?? array() ), true ), 'adapter health exposes update-media-details execute allowlist' );
maa_adapter_smoke_assert( in_array( 'magick-ai/reply-comment', (array) ( $health['allowed_execute_ability_ids'] ?? array() ), true ), 'adapter health exposes reply-comment execute allowlist' );
maa_adapter_smoke_assert( in_array( 'magick-ai/trash-comment', (array) ( $health['allowed_execute_ability_ids'] ?? array() ), true ), 'adapter health exposes trash-comment execute allowlist' );
maa_adapter_smoke_assert( in_array( 'magick-ai/approve-comment', (array) ( $health['allowed_execute_ability_ids'] ?? array() ), true ), 'adapter health exposes approve-comment execute allowlist' );

$help = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/help' );
maa_adapter_smoke_assert( maa_adapter_smoke_help_has_route( $help, 'GET', '/proposals' ), 'adapter help exposes proposal list route' );
maa_adapter_smoke_assert( maa_adapter_smoke_help_has_route( $help, 'GET', '/proposals/{proposal_id}' ), 'adapter help exposes proposal detail route' );
maa_adapter_smoke_assert( maa_adapter_smoke_help_has_route( $help, 'POST', '/proposals/from-plan' ), 'adapter help exposes plan-to-proposal route' );
maa_adapter_smoke_assert( maa_adapter_smoke_help_has_route( $help, 'POST', '/execute-approved-proposal' ), 'adapter help exposes execute-approved-proposal route' );
maa_adapter_smoke_assert( maa_adapter_smoke_help_has_route( $help, 'POST', '/proposals/{proposal_id}/execute' ), 'adapter help exposes proposal execute route' );
maa_adapter_smoke_assert( maa_adapter_smoke_help_has_route( $help, 'POST', '/proposals/{proposal_id}/approve-and-execute' ), 'adapter help exposes proposal approve-and-execute route' );
maa_adapter_smoke_assert( maa_adapter_smoke_help_has_route( $help, 'POST', '/proposals/{proposal_id}/approve' ), 'adapter help exposes approval disabled stub route' );
maa_adapter_smoke_assert( maa_adapter_smoke_help_has_route( $help, 'POST', '/proposals/{proposal_id}/reject' ), 'adapter help exposes rejection disabled stub route' );
maa_adapter_smoke_assert( maa_adapter_smoke_help_has_route( $help, 'POST', '/ai-provider-log-correlation-smoke' ), 'adapter help exposes provider log correlation smoke route' );
maa_adapter_smoke_assert( maa_adapter_smoke_help_has_route( $help, 'GET', '/plugin-conflict-diagnostics' ), 'adapter help exposes plugin conflict diagnostic shortcut' );
maa_adapter_smoke_assert( maa_adapter_smoke_help_has_route( $help, 'GET', '/term' ), 'adapter help exposes term detail shortcut' );
maa_adapter_smoke_assert( in_array( 'GET /plugin-conflict-diagnostics', (array) ( $help['route_groups']['read_shortcuts'] ?? array() ), true ), 'adapter help keeps grouped read shortcut routes for humans' );
maa_adapter_smoke_assert( false === (bool) ( $help['approval_proxy_enabled'] ?? true ), 'adapter help keeps approval proxy disabled' );
maa_adapter_smoke_assert( 'magick_ai_core_admin' === (string) ( $help['approval_surface'] ?? '' ), 'adapter help exposes Core admin approval surface' );
maa_adapter_smoke_assert( array_key_exists( 'core_app_token_configured', $help ), 'adapter help exposes Core app token configured state without token value' );
maa_adapter_smoke_assert( false === (bool) ( $help['non_goals']['approval_proxy_enabled'] ?? true ), 'adapter help keeps approval proxy disabled' );
maa_adapter_smoke_assert( false === (bool) ( $help['non_goals']['reject_proxy_enabled'] ?? true ), 'adapter help keeps rejection proxy disabled' );

$smoke_terms = get_terms(
	array(
		'taxonomy'   => 'category',
		'hide_empty' => false,
		'number'     => 1,
	)
);
maa_adapter_smoke_assert( ! is_wp_error( $smoke_terms ) && ! empty( $smoke_terms ), 'WordPress has a category term for adapter term detail smoke' );
$smoke_term  = $smoke_terms[0];
$term_detail = maa_adapter_smoke_rest(
	'GET',
	'/magick-ai-adapter/v1/term',
	array(
		'id' => (int) $smoke_term->term_id,
	)
);
maa_adapter_smoke_assert( 'magick-ai/get-term' === (string) ( $term_detail['ability_id'] ?? '' ), 'adapter resolves term detail from list id' );

$capabilities = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/capabilities' );
$by_id        = maa_adapter_smoke_capabilities_by_id( $capabilities );
maa_adapter_smoke_assert( isset( $by_id['magick-ai-abilities/site-summary'] ), 'adapter exposes site-summary capability through Core' );
maa_adapter_smoke_assert( isset( $by_id['magick-ai-abilities/wp-diagnostics-summary'] ), 'adapter exposes diagnostics capability through Core' );
maa_adapter_smoke_assert( isset( $by_id['magick-ai-abilities/wp-ops-diagnostics-detail'] ), 'adapter exposes ops diagnostics capability through Core' );
maa_adapter_smoke_assert( isset( $by_id['magick-ai-abilities/list-workflow-recipes'] ), 'adapter exposes workflow recipe list capability through Core' );
maa_adapter_smoke_assert( isset( $by_id['magick-ai-abilities/get-workflow-recipe'] ), 'adapter exposes workflow recipe detail capability through Core' );
maa_adapter_smoke_assert( isset( $by_id['magick-ai/build-content-inventory-fix-plan'] ), 'adapter capabilities expose content inventory fix plan through Core' );
maa_adapter_smoke_assert( isset( $by_id['magick-ai/build-test-content-cleanup-plan'] ), 'adapter capabilities expose test content cleanup plan through Core' );
maa_adapter_smoke_assert( isset( $by_id['magick-ai/build-media-inventory-fix-plan'] ), 'adapter capabilities expose media inventory fix plan through Core' );
maa_adapter_smoke_assert( 'direct_read' === (string) ( $by_id['magick-ai-abilities/site-summary']['governance_mode'] ?? '' ), 'site-summary is direct read' );

$content_plan_response = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/run-read-ability',
	array(
		'ability_id' => 'magick-ai/build-content-inventory-fix-plan',
		'input'      => array(
			'per_page'    => 1,
			'max_actions' => 1,
		),
	)
);
$content_plan = is_array( $content_plan_response['result']['data'] ?? null ) ? $content_plan_response['result']['data'] : array();
maa_adapter_smoke_assert( array_key_exists( 'write_actions', $content_plan ), 'adapter plan read preserves write_actions' );
maa_adapter_smoke_assert( array_key_exists( 'preview', $content_plan ), 'adapter plan read preserves preview' );
maa_adapter_smoke_assert( array_key_exists( 'risk', $content_plan ), 'adapter plan read preserves risk' );
maa_adapter_smoke_assert( true === (bool) ( $content_plan['requires_approval'] ?? false ), 'adapter plan read preserves requires_approval=true' );
maa_adapter_smoke_assert( false === (bool) ( $content_plan['commit_execution'] ?? true ), 'adapter plan read preserves commit_execution=false' );
maa_adapter_smoke_assert( true === (bool) ( $content_plan['dry_run'] ?? false ), 'adapter plan read preserves dry_run=true' );
maa_adapter_smoke_assert( false === (bool) ( $content_plan_response['commit_execution'] ?? true ), 'adapter plan wrapper does not report execution' );

$media_plan_response = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/run-read-ability',
	array(
		'ability_id' => 'magick-ai/build-media-inventory-fix-plan',
		'input'      => array(
			'per_page'                  => 1,
			'max_actions'               => 1,
			'include_delete_candidates' => false,
		),
	)
);
$media_plan = is_array( $media_plan_response['result']['data'] ?? null ) ? $media_plan_response['result']['data'] : array();
maa_adapter_smoke_assert( array_key_exists( 'skipped_destructive_candidates', $media_plan ), 'adapter media plan preserves skipped destructive candidates field' );
foreach ( (array) ( $media_plan['write_actions'] ?? array() ) as $media_action ) {
	maa_adapter_smoke_assert( ! is_array( $media_action ) || 'magick-ai/delete-media-permanently' !== (string) ( $media_action['target_ability_id'] ?? '' ), 'adapter media plan does not promote skipped deletes into write actions by default' );
}

$media_plan_shortcut = maa_adapter_smoke_rest(
	'GET',
	'/magick-ai-adapter/v1/media-inventory-fix-plan',
	array(
		'per_page'                  => 1,
		'max_actions'               => 1,
		'include_delete_candidates' => 'false',
	)
);
$media_shortcut_plan = is_array( $media_plan_shortcut['result']['data'] ?? null ) ? $media_plan_shortcut['result']['data'] : array();
foreach ( (array) ( $media_shortcut_plan['write_actions'] ?? array() ) as $media_shortcut_action ) {
	maa_adapter_smoke_assert( ! is_array( $media_shortcut_action ) || 'magick-ai/delete-media-permanently' !== (string) ( $media_shortcut_action['target_ability_id'] ?? '' ), 'adapter media plan shortcut treats include_delete_candidates=false as false' );
}

$unallowed_plan_bridge = maa_adapter_smoke_rest_result(
	'POST',
	'/magick-ai-adapter/v1/proposals/from-plan',
	array(
		'plan_ability_id' => 'magick-ai/site-info',
		'plan'            => array(
			'requires_approval' => true,
			'commit_execution'  => false,
			'dry_run'           => true,
			'write_actions'     => array(),
		),
	)
);
maa_adapter_smoke_assert( 400 === (int) $unallowed_plan_bridge['status'], 'adapter rejects unallowed plan-to-proposal ability before Core forwarding' );
maa_adapter_smoke_assert( 'magick_ai_adapter_plan_ability_not_allowed' === (string) ( $unallowed_plan_bridge['data']['code'] ?? '' ), 'adapter unallowed plan rejection uses adapter error code' );

$invalid_plan_action_bridge = maa_adapter_smoke_rest_result(
	'POST',
	'/magick-ai-adapter/v1/proposals/from-plan',
	array(
		'plan_ability_id' => 'magick-ai/build-test-content-cleanup-plan',
		'plan'            => array(
			'requires_approval' => true,
			'commit_execution'  => false,
			'dry_run'           => true,
			'write_actions'     => array(
				array(
					'action_id'         => 'invalid-update-post-status',
					'target_ability_id' => 'magick-ai/update-post',
					'requires_approval' => true,
					'commit_execution'  => false,
					'proposal_ready'    => true,
					'input'             => array(
						'post_id' => 123,
						'title'   => 'Adapter invalid plan action status should not apply',
						'status'  => 'publish',
						'dry_run' => true,
						'commit'  => false,
					),
				),
			),
		),
	)
);
$invalid_plan_action_error = is_array( $invalid_plan_action_bridge['data']['data'] ?? null ) ? $invalid_plan_action_bridge['data']['data'] : array();
$invalid_plan_action_block = is_array( $invalid_plan_action_error['blocked_items'][0] ?? null ) ? $invalid_plan_action_error['blocked_items'][0] : array();
maa_adapter_smoke_assert( 400 === (int) $invalid_plan_action_bridge['status'], 'adapter plan-to-proposal rejects invalid profiled action input before Core forwarding' );
maa_adapter_smoke_assert( 'magick_ai_adapter_plan_action_input_invalid' === (string) ( $invalid_plan_action_bridge['data']['code'] ?? '' ), 'adapter plan action input rejection uses adapter error code' );
maa_adapter_smoke_assert( 0 === (int) ( $invalid_plan_action_error['proposal_count'] ?? -1 ), 'adapter plan action input rejection creates no proposals' );
maa_adapter_smoke_assert( 0 === (int) ( $invalid_plan_action_block['index'] ?? -1 ), 'adapter plan action input rejection carries action index' );
maa_adapter_smoke_assert( 'invalid-update-post-status' === (string) ( $invalid_plan_action_block['action_id'] ?? '' ), 'adapter plan action input rejection carries action id' );
maa_adapter_smoke_assert( 'magick-ai/update-post' === (string) ( $invalid_plan_action_block['target_ability_id'] ?? '' ), 'adapter plan action input rejection carries target ability id' );
maa_adapter_smoke_assert( 'status' === (string) ( $invalid_plan_action_block['field'] ?? '' ), 'adapter plan action input rejection carries field' );
maa_adapter_smoke_assert( 'magick_ai_adapter_ability_input_field_not_allowed' === (string) ( $invalid_plan_action_block['block_code'] ?? '' ), 'adapter plan action input rejection reuses proposal schema field error code' );

$duplicate_plan_action_bridge = maa_adapter_smoke_rest_result(
	'POST',
	'/magick-ai-adapter/v1/proposals/from-plan',
	array(
		'plan_ability_id' => 'magick-ai/build-content-inventory-fix-plan',
		'plan'            => array(
			'requires_approval' => true,
			'commit_execution'  => false,
			'dry_run'           => true,
			'write_actions'     => array(
				array(
					'action_id'         => 'duplicate-plan-action',
					'target_ability_id' => 'magick-ai/create-draft',
					'input'             => array(
						'title'   => 'Adapter duplicate plan action one',
						'dry_run' => true,
						'commit'  => false,
					),
					'requires_approval' => true,
					'commit_execution'  => false,
					'proposal_ready'    => true,
				),
				array(
					'action_id'         => 'duplicate-plan-action',
					'target_ability_id' => 'magick-ai/create-draft',
					'input'             => array(
						'title'   => 'Adapter duplicate plan action two',
						'dry_run' => true,
						'commit'  => false,
					),
					'requires_approval' => true,
					'commit_execution'  => false,
					'proposal_ready'    => true,
				),
			),
		),
	)
);
$duplicate_plan_action_error = is_array( $duplicate_plan_action_bridge['data']['data'] ?? null ) ? $duplicate_plan_action_bridge['data']['data'] : array();
$duplicate_plan_action_block = is_array( $duplicate_plan_action_error['blocked_items'][0] ?? null ) ? $duplicate_plan_action_error['blocked_items'][0] : array();
maa_adapter_smoke_assert( 400 === (int) $duplicate_plan_action_bridge['status'], 'adapter plan-to-proposal rejects duplicate action ids before Core forwarding' );
maa_adapter_smoke_assert( 'magick_ai_adapter_write_action_duplicate_id' === (string) ( $duplicate_plan_action_block['block_code'] ?? '' ), 'adapter duplicate plan action rejection uses duplicate id block code' );

$embedded_output_plan_bridge = maa_adapter_smoke_rest_result(
	'POST',
	'/magick-ai-adapter/v1/proposals/from-plan',
	array(
		'plan_ability_id' => 'magick-ai/build-content-inventory-fix-plan',
		'plan'            => array(
			'requires_approval' => true,
			'commit_execution'  => false,
			'dry_run'           => true,
			'write_actions'     => array(
				array(
					'action_id'         => 'embedded-output-token',
					'target_ability_id' => 'magick-ai/create-draft',
					'input'             => array(
						'title'   => 'Adapter embedded output token smoke',
						'content' => 'prefix-$outputs.embedded-output-token.post_id',
						'dry_run' => true,
						'commit'  => false,
					),
					'requires_approval' => true,
					'commit_execution'  => false,
					'proposal_ready'    => true,
				),
			),
		),
	)
);
$embedded_output_error = is_array( $embedded_output_plan_bridge['data']['data'] ?? null ) ? $embedded_output_plan_bridge['data']['data'] : array();
$embedded_output_block = is_array( $embedded_output_error['blocked_items'][0] ?? null ) ? $embedded_output_error['blocked_items'][0] : array();
maa_adapter_smoke_assert( 400 === (int) $embedded_output_plan_bridge['status'], 'adapter plan-to-proposal rejects embedded output reference tokens before Core forwarding' );
maa_adapter_smoke_assert( 'magick_ai_adapter_output_reference_invalid' === (string) ( $embedded_output_block['block_code'] ?? '' ), 'adapter embedded plan output token rejection uses output reference invalid code' );

$output_reference_plan_bridge = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals/from-plan',
	array(
		'plan_ability_id' => 'magick-ai/build-content-inventory-fix-plan',
		'plan'            => array(
			'success' => true,
			'data'    => array(
				'batch_id'         => 'adapter-plan-output-reference-smoke',
				'issue_types'      => array( 'acceptance' ),
				'write_actions'    => array(
					array(
						'action_id'         => 'create-draft-fixture',
						'target_ability_id' => 'magick-ai/create-draft',
						'input'             => array(
							'status'         => 'draft',
							'title'          => 'Adapter from-plan output reference draft',
							'content'        => 'Adapter from-plan output reference smoke.',
							'content_format' => 'plain',
							'dry_run'        => true,
							'commit'         => false,
						),
						'requires_approval' => true,
						'commit_execution'  => false,
						'proposal_ready'    => true,
					),
					array(
						'action_id'         => 'update-created-draft',
						'target_ability_id' => 'magick-ai/update-post',
						'depends_on'        => array( 'create-draft-fixture' ),
						'input'             => array(
							'post_id'        => '$outputs.create-draft-fixture.post_id',
							'title'          => 'Adapter from-plan output reference updated draft',
							'content'        => 'Adapter resolved a from-plan output reference before update.',
							'content_format' => 'plain',
							'dry_run'        => true,
							'commit'         => false,
						),
						'requires_approval' => true,
						'commit_execution'  => false,
						'proposal_ready'    => true,
					),
					array(
						'action_id'         => 'trash-created-draft',
						'target_ability_id' => 'magick-ai/trash-post',
						'depends_on'        => array( 'create-draft-fixture' ),
						'input'             => array(
							'post_id' => '$outputs.create-draft-fixture.post_id',
							'dry_run' => true,
							'commit'  => false,
						),
						'requires_approval' => true,
						'commit_execution'  => false,
						'proposal_ready'    => true,
					),
				),
				'preview'          => array(),
				'risk'             => array(
					'level'  => 'medium',
					'reason' => 'Adapter from-plan output reference smoke.',
				),
				'requires_approval' => true,
				'commit_execution' => false,
				'dry_run'          => true,
			),
		),
	)
);
maa_adapter_smoke_assert( 1 === (int) ( $output_reference_plan_bridge['proposal_count'] ?? 0 ), 'adapter from-plan output references create one batch proposal' );
$output_reference_plan_proposal = is_array( $output_reference_plan_bridge['proposals'][0] ?? null ) ? $output_reference_plan_bridge['proposals'][0] : array();
$output_reference_plan_proposal_id = (string) ( $output_reference_plan_proposal['proposal_id'] ?? '' );
$maa_adapter_smoke_cleanup_proposal_ids[] = $output_reference_plan_proposal_id;
$output_reference_plan_actions = is_array( $output_reference_plan_proposal['input']['write_actions'] ?? null ) ? array_values( $output_reference_plan_proposal['input']['write_actions'] ) : array();
maa_adapter_smoke_assert( 'plan_to_proposal_batch' === (string) ( $output_reference_plan_proposal['preview']['source']['type'] ?? '' ), 'adapter from-plan output reference proposal records batch source' );
maa_adapter_smoke_assert( 3 === count( $output_reference_plan_actions ), 'adapter from-plan output reference proposal preserves ordered actions' );
maa_adapter_smoke_assert( '$outputs.create-draft-fixture.post_id' === (string) ( $output_reference_plan_actions[1]['input']['post_id'] ?? '' ), 'adapter from-plan output reference proposal preserves unresolved post_id reference' );
$output_reference_plan_result = maa_adapter_smoke_rest( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $output_reference_plan_proposal_id ) . '/approve-and-execute' );
maa_adapter_smoke_assert( true === (bool) ( $output_reference_plan_result['success'] ?? false ), 'adapter from-plan output-reference batch approve-and-execute succeeds' );
maa_adapter_smoke_assert( 3 === (int) ( $output_reference_plan_result['executed_count'] ?? 0 ), 'adapter from-plan output-reference batch executes all actions' );
$output_reference_plan_post_id = (int) ( $output_reference_plan_result['results'][0]['post_id'] ?? 0 );
$maa_adapter_smoke_cleanup_post_ids[] = $output_reference_plan_post_id;
maa_adapter_smoke_assert( $output_reference_plan_post_id > 0, 'adapter from-plan output-reference batch creates a draft post' );
maa_adapter_smoke_assert( $output_reference_plan_post_id === (int) ( $output_reference_plan_result['results'][1]['post_id'] ?? 0 ), 'adapter from-plan output-reference batch updates the created draft' );
maa_adapter_smoke_assert( $output_reference_plan_post_id === (int) ( $output_reference_plan_result['results'][2]['post_id'] ?? 0 ), 'adapter from-plan output-reference batch trashes the created draft' );
maa_adapter_smoke_assert( 'trash' === (string) get_post_status( $output_reference_plan_post_id ), 'adapter from-plan output-reference batch leaves created draft trashed' );

$media_plan_attachment_id = maa_adapter_smoke_create_media_plan_attachment();
$maa_adapter_smoke_cleanup_attachment_ids[] = $media_plan_attachment_id;
$media_e2e_input = array(
	'attachment_ids'              => array( $media_plan_attachment_id ),
	'issue_types'                 => array( 'missing_alt', 'missing_source', 'possibly_unattached' ),
	'include_delete_candidates'   => false,
	'max_actions'                 => 5,
);
$media_e2e_plan_response = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/run-read-ability',
	array(
		'ability_id' => 'magick-ai/build-media-inventory-fix-plan',
		'input'      => $media_e2e_input,
	)
);
$media_e2e_plan = is_array( $media_e2e_plan_response['result']['data'] ?? null ) ? $media_e2e_plan_response['result']['data'] : array();
maa_adapter_smoke_assert( ! empty( $media_e2e_plan['write_actions'] ), 'adapter e2e media plan contains write_actions before proposal handoff' );
maa_adapter_smoke_assert( ! empty( $media_e2e_plan['manual_review'] ), 'adapter e2e media plan contains manual_review rows before proposal handoff' );
maa_adapter_smoke_assert( ! empty( $media_e2e_plan['skipped_destructive_candidates'] ), 'adapter e2e media plan contains skipped destructive candidates before proposal handoff' );
foreach ( (array) ( $media_e2e_plan['write_actions'] ?? array() ) as $media_e2e_action ) {
	maa_adapter_smoke_assert( ! is_array( $media_e2e_action ) || 'magick-ai/delete-media-permanently' !== (string) ( $media_e2e_action['target_ability_id'] ?? '' ), 'adapter e2e media plan keeps delete-media-permanently out of write_actions by default' );
}

$plan_bridge = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals/from-plan',
	array(
		'plan_ability_id'    => 'magick-ai/build-media-inventory-fix-plan',
		'plan'               => $media_e2e_plan_response['result'],
		'plan_input'         => $media_e2e_input,
		'adapter_request_id' => 'adapter-plan-e2e-request',
		'correlation_id'     => 'adapter-plan-e2e-correlation',
		'caller'             => array(
			'external_thread_id' => 'adapter-plan-bridge-smoke',
		),
	)
);
maa_adapter_smoke_assert( 'magick-ai/build-media-inventory-fix-plan' === (string) ( $plan_bridge['plan_ability_id'] ?? '' ), 'adapter forwards plan-to-proposal route to Core' );
maa_adapter_smoke_assert( false === (bool) ( $plan_bridge['commit_execution'] ?? true ), 'adapter plan-to-proposal response preserves commit_execution=false' );
maa_adapter_smoke_assert( is_array( $plan_bridge['proposals'] ?? null ), 'adapter plan-to-proposal response preserves proposal list state' );
maa_adapter_smoke_assert( (int) ( $plan_bridge['proposal_count'] ?? 0 ) >= 1, 'adapter e2e media plan creates Core proposal' );
foreach ( (array) ( $plan_bridge['proposals'] ?? array() ) as $created_plan_proposal ) {
	if ( is_array( $created_plan_proposal ) && '' !== (string) ( $created_plan_proposal['proposal_id'] ?? '' ) ) {
		$maa_adapter_smoke_cleanup_proposal_ids[] = (string) $created_plan_proposal['proposal_id'];
		maa_adapter_smoke_assert( 'magick-ai/delete-media-permanently' !== (string) ( $created_plan_proposal['ability_id'] ?? '' ), 'adapter e2e media plan keeps delete-media-permanently out of created proposals by default' );
	}
}
$plan_proposal = is_array( $plan_bridge['proposals'][0] ?? null ) ? $plan_bridge['proposals'][0] : array();
$plan_proposal_id = (string) ( $plan_proposal['proposal_id'] ?? '' );
maa_adapter_smoke_assert( '' !== $plan_proposal_id, 'adapter e2e media plan returned a proposal id' );
maa_adapter_smoke_assert( 'adapter-plan-e2e-request' === (string) ( $plan_proposal['caller']['adapter_request_id'] ?? '' ), 'adapter plan proposal caller carries adapter request id' );
$plan_proposal_detail = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $plan_proposal_id ) );
maa_adapter_smoke_assert( $plan_proposal_id === (string) ( $plan_proposal_detail['proposal_id'] ?? '' ), 'adapter e2e plan proposal detail is readable through adapter' );
maa_adapter_smoke_assert( is_array( $plan_proposal_detail['preview'] ?? null ), 'adapter e2e plan proposal detail preserves preview object' );
maa_adapter_smoke_assert( is_array( $plan_proposal_detail['input'] ?? null ), 'adapter e2e plan proposal detail preserves write action input' );
maa_adapter_smoke_assert( 'plan_to_proposal' === (string) ( $plan_proposal_detail['preview']['source']['type'] ?? '' ), 'adapter e2e plan proposal detail preserves source plan preview' );
maa_adapter_smoke_assert( is_array( $plan_proposal_detail['preview']['plan_preview_row'] ?? null ), 'adapter e2e plan proposal detail preserves source plan preview row' );
maa_adapter_smoke_assert( ! empty( $plan_proposal_detail['preview']['blocked_items']['manual_review'] ?? array() ), 'adapter e2e plan proposal detail preserves manual review rows' );
maa_adapter_smoke_assert( ! empty( $plan_proposal_detail['preview']['blocked_items']['skipped_destructive_candidates'] ?? array() ), 'adapter e2e plan proposal detail preserves skipped destructive candidates' );
maa_adapter_smoke_assert( 'adapter-plan-e2e-request' === (string) ( $plan_proposal_detail['caller']['adapter_request_id'] ?? '' ), 'adapter e2e proposal detail preserves adapter request id in caller' );
maa_adapter_smoke_assert( 'adapter-plan-e2e-correlation' === (string) ( $plan_proposal_detail['caller']['correlation_id'] ?? '' ), 'adapter e2e proposal detail preserves correlation id in caller' );

$site_summary = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/site-summary' );
maa_adapter_smoke_assert( 'magick-ai-abilities/site-summary' === (string) ( $site_summary['ability_id'] ?? '' ), 'adapter runs site-summary read ability' );
maa_adapter_smoke_assert( is_array( $site_summary['result'] ?? null ), 'site-summary returns a result object' );

$diagnostics = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/wp-diagnostics-summary' );
maa_adapter_smoke_assert( 'magick-ai-abilities/wp-diagnostics-summary' === (string) ( $diagnostics['ability_id'] ?? '' ), 'adapter runs diagnostics read ability' );
maa_adapter_smoke_assert( is_array( $diagnostics['result'] ?? null ), 'diagnostics returns a result object' );

$active_plugins = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/active-plugins-detail' );
maa_adapter_smoke_assert( 'magick-ai-abilities/wp-ops-diagnostics-detail' === (string) ( $active_plugins['ability_id'] ?? '' ), 'adapter runs active plugins diagnostic shortcut through ops detail' );
maa_adapter_smoke_assert( is_array( $active_plugins['result']['plugins'] ?? null ), 'active plugins diagnostic returns plugin details object' );
$plugin_result = (array) ( $active_plugins['result']['plugins'] ?? array() );
maa_adapter_smoke_assert( is_array( $plugin_result['groups_included'] ?? null ), 'active plugins diagnostic returns plugin group inclusion metadata' );
maa_adapter_smoke_assert( true === (bool) ( $plugin_result['groups_included']['active'] ?? false ), 'active plugins diagnostic includes active plugin rows by default' );
maa_adapter_smoke_assert( false === (bool) ( $plugin_result['groups_included']['inactive'] ?? true ), 'active plugins diagnostic does not request inactive plugin rows by default' );
maa_adapter_smoke_assert( 100 === (int) ( $plugin_result['max_plugins_per_group'] ?? 0 ), 'active plugins diagnostic uses default plugin group limit' );
foreach ( array( 'available_count', 'active_count', 'inactive_count', 'update_available_count', 'mu_count', 'dropin_count' ) as $plugin_count_field ) {
	maa_adapter_smoke_assert( array_key_exists( $plugin_count_field, $plugin_result ), 'active plugins diagnostic returns plugin count field: ' . $plugin_count_field );
}
maa_adapter_smoke_assert( is_array( $active_plugins['result']['plugins']['active'] ?? null ), 'active plugins diagnostic returns active plugin details' );
maa_adapter_smoke_assert( is_array( $active_plugins['result']['plugins']['inactive'] ?? null ), 'active plugins diagnostic preserves inactive plugin group as an array even when not requested' );
maa_adapter_smoke_assert( array_key_exists( 'update_available', (array) ( $active_plugins['result']['plugins'] ?? array() ) ), 'active plugins diagnostic returns plugin update details' );
maa_adapter_smoke_assert( is_array( $active_plugins['result']['plugins']['must_use'] ?? null ), 'active plugins diagnostic returns must-use plugin group' );
maa_adapter_smoke_assert( is_array( $active_plugins['result']['plugins']['dropins'] ?? null ), 'active plugins diagnostic returns dropin plugin group' );
if ( isset( $active_plugins['result']['plugins']['active'][0] ) && is_array( $active_plugins['result']['plugins']['active'][0] ) ) {
	foreach ( array( 'slug', 'plugin_file', 'name', 'version', 'author', 'status', 'network_active', 'must_use', 'requires_wp', 'requires_php', 'dependencies', 'dependency_count', 'is_magick_ai', 'update_available', 'latest_version' ) as $plugin_row_field ) {
		maa_adapter_smoke_assert( array_key_exists( $plugin_row_field, $active_plugins['result']['plugins']['active'][0] ), 'active plugin row returns field: ' . $plugin_row_field );
	}
}

$plugin_conflict = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/plugin-conflict-diagnostics' );
maa_adapter_smoke_assert( 'magick-ai-abilities/wp-ops-diagnostics-detail' === (string) ( $plugin_conflict['ability_id'] ?? '' ), 'adapter runs plugin conflict diagnostic shortcut through ops detail' );
maa_adapter_smoke_assert( true === (bool) ( $plugin_conflict['result']['plugins']['groups_included']['inactive'] ?? false ), 'plugin conflict diagnostic requests inactive plugin rows' );
maa_adapter_smoke_assert( 200 === (int) ( $plugin_conflict['result']['plugins']['max_plugins_per_group'] ?? 0 ), 'plugin conflict diagnostic uses deep plugin group limit' );
maa_adapter_smoke_assert( is_array( $plugin_conflict['result']['plugins']['inactive'] ?? null ), 'plugin conflict diagnostic returns inactive plugin rows array' );

$current_user_permissions = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/current-user-permissions' );
maa_adapter_smoke_assert( 'magick-ai-abilities/wp-ops-diagnostics-detail' === (string) ( $current_user_permissions['ability_id'] ?? '' ), 'adapter runs current user permissions diagnostic shortcut through ops detail' );
maa_adapter_smoke_assert( is_array( $current_user_permissions['result']['current_user'] ?? null ), 'current user permissions diagnostic returns user capability object' );
maa_adapter_smoke_assert( array_key_exists( 'capabilities', (array) ( $current_user_permissions['result']['current_user'] ?? array() ) ), 'current user permissions diagnostic returns capability details' );

$recent_error_log = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/recent-error-log' );
maa_adapter_smoke_assert( 'magick-ai-abilities/wp-ops-diagnostics-detail' === (string) ( $recent_error_log['ability_id'] ?? '' ), 'adapter runs default error log diagnostic through ops detail' );
maa_adapter_smoke_assert( false === (bool) ( $recent_error_log['result']['error_log']['contents_included'] ?? true ), 'default error log diagnostic does not include log contents' );
maa_adapter_smoke_assert( is_array( $recent_error_log['result']['error_log']['summary'] ?? null ), 'default error log diagnostic exposes severity summary without log contents' );
foreach ( array( 'returned_lines', 'fatal_count', 'error_count', 'warning_count', 'deprecated_count', 'notice_count', 'info_count', 'unknown_count', 'summary_source' ) as $summary_field ) {
	maa_adapter_smoke_assert( array_key_exists( $summary_field, $recent_error_log['result']['error_log']['summary'] ), 'default error log summary returns field: ' . $summary_field );
}
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

$destructive_run = maa_adapter_smoke_rest_result(
	'POST',
	'/magick-ai-adapter/v1/run-read-ability',
	array(
		'ability_id' => 'magick-ai/delete-media-permanently',
		'input'      => array(
			'attachment_id' => 0,
			'dry_run'       => true,
			'commit'        => false,
		),
	)
);
maa_adapter_smoke_assert( 403 === (int) $destructive_run['status'], 'adapter refuses direct execution for destructive ability' );

$trash_post_id = maa_adapter_smoke_create_trash_post_fixture();
$maa_adapter_smoke_cleanup_post_ids[] = $trash_post_id;
$trash_proposal = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/trash-post',
		'title'      => 'Adapter approved trash execution smoke',
		'summary'    => 'Adapter executes one approved trash-post proposal after Core preflight.',
		'input'      => array(
			'post_id' => $trash_post_id,
			'dry_run' => true,
			'commit'  => false,
		),
		'preview'    => array(
			'action'           => 'trash_post',
			'post_id'          => $trash_post_id,
			'dry_run'          => true,
			'commit_execution' => false,
		),
		'caller'     => array(
			'external_thread_id' => 'adapter-approved-execution-smoke',
		),
	)
);
$trash_proposal_id = (string) ( $trash_proposal['proposal_id'] ?? '' );
$maa_adapter_smoke_cleanup_proposal_ids[] = $trash_proposal_id;
maa_adapter_smoke_assert( '' !== $trash_proposal_id, 'adapter creates trash-post proposal for approved execution smoke' );
maa_adapter_smoke_assert( 'pending' === (string) ( $trash_proposal['status'] ?? '' ), 'adapter trash-post proposal starts pending' );

$pending_execute = maa_adapter_smoke_rest_result(
	'POST',
	'/magick-ai-adapter/v1/execute-approved-proposal',
	array(
		'proposal_id' => $trash_proposal_id,
	)
);
maa_adapter_smoke_assert( 409 === (int) $pending_execute['status'], 'adapter execute refuses pending proposal' );
maa_adapter_smoke_assert( 'publish' === (string) get_post_status( $trash_post_id ), 'adapter pending execute leaves post published' );

$approved_trash = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-core/v1/proposals/' . rawurlencode( $trash_proposal_id ) . '/approve',
	array(
		'note' => 'Approve adapter trash execution smoke.',
	)
);
maa_adapter_smoke_assert( 'approved' === (string) ( $approved_trash['status'] ?? '' ), 'Core admin REST approval succeeds for adapter trash execution smoke' );

$executed_trash = maa_adapter_smoke_rest( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $trash_proposal_id ) . '/execute' );
maa_adapter_smoke_assert( 'executed' === (string) ( $executed_trash['status'] ?? '' ), 'adapter executes approved trash-post proposal' );
maa_adapter_smoke_assert( $trash_proposal_id === (string) ( $executed_trash['proposal_id'] ?? '' ), 'adapter execute response carries proposal id' );
maa_adapter_smoke_assert( 'magick-ai/trash-post' === (string) ( $executed_trash['ability_id'] ?? '' ), 'adapter execute response carries ability id' );
maa_adapter_smoke_assert( '' !== (string) ( $executed_trash['correlation_id'] ?? '' ), 'adapter execute response carries correlation id' );
maa_adapter_smoke_assert( '' !== (string) ( $executed_trash['adapter_request_id'] ?? '' ), 'adapter execute response carries adapter request id' );
maa_adapter_smoke_assert( true === (bool) ( $executed_trash['approval_context']['approval_commit_authorized'] ?? false ), 'adapter execute response carries approval context' );
maa_adapter_smoke_assert( false === (bool) ( $executed_trash['commit_execution'] ?? true ), 'adapter execute response preserves commit_execution=false' );
maa_adapter_smoke_assert( true === (bool) ( $executed_trash['result']['trashed'] ?? false ), 'adapter execute trashes post' );
maa_adapter_smoke_assert( false === (bool) ( $executed_trash['result']['dry_run'] ?? true ), 'adapter execute returns non-dry-run ability result' );
maa_adapter_smoke_assert( 'trash' === (string) get_post_status( $trash_post_id ), 'adapter approved execution moves post to trash' );

$approve_execute_post_id = maa_adapter_smoke_create_trash_post_fixture();
$maa_adapter_smoke_cleanup_post_ids[] = $approve_execute_post_id;
$approve_execute_proposal = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/trash-post',
		'title'      => 'Adapter unified approve execute smoke',
		'summary'    => 'Adapter approves through Core and executes one trash-post proposal.',
		'input'      => array(
			'post_id' => $approve_execute_post_id,
			'dry_run' => true,
			'commit'  => false,
		),
		'preview'    => array(
			'action'           => 'trash_post',
			'post_id'          => $approve_execute_post_id,
			'dry_run'          => true,
			'commit_execution' => false,
		),
		'caller'     => array(
			'external_thread_id' => 'adapter-unified-approve-execute-smoke',
		),
	)
);
$approve_execute_proposal_id = (string) ( $approve_execute_proposal['proposal_id'] ?? '' );
$maa_adapter_smoke_cleanup_proposal_ids[] = $approve_execute_proposal_id;
maa_adapter_smoke_assert( '' !== $approve_execute_proposal_id, 'adapter creates trash-post proposal for approve-and-execute smoke' );
$approve_execute_result = maa_adapter_smoke_rest( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $approve_execute_proposal_id ) . '/approve-and-execute' );
maa_adapter_smoke_assert( true === (bool) ( $approve_execute_result['success'] ?? false ), 'adapter approve-and-execute succeeds for pending trash-post proposal' );
maa_adapter_smoke_assert( $approve_execute_proposal_id === (string) ( $approve_execute_result['proposal_id'] ?? '' ), 'adapter approve-and-execute response carries proposal id' );
maa_adapter_smoke_assert( 'magick-ai/trash-post' === (string) ( $approve_execute_result['ability_id'] ?? '' ), 'adapter approve-and-execute response carries ability id' );
maa_adapter_smoke_assert( $approve_execute_post_id === (int) ( $approve_execute_result['post_id'] ?? 0 ), 'adapter approve-and-execute response carries post id' );
maa_adapter_smoke_assert( 'pending' === (string) ( $approve_execute_result['status_before'] ?? '' ), 'adapter approve-and-execute records pending status before approval' );
maa_adapter_smoke_assert( true === (bool) ( $approve_execute_result['approved_by_adapter'] ?? false ), 'adapter approve-and-execute auto approves pending proposal through Core' );
maa_adapter_smoke_assert( '' !== (string) ( $approve_execute_result['correlation_id'] ?? '' ), 'adapter approve-and-execute response carries correlation id' );
maa_adapter_smoke_assert( false === (bool) ( $approve_execute_result['core_commit_execution'] ?? true ), 'adapter approve-and-execute preserves Core commit_execution=false' );
maa_adapter_smoke_assert( 'publish' === (string) ( $approve_execute_result['execution']['post_status_before'] ?? '' ), 'adapter approve-and-execute records post status before execution' );
maa_adapter_smoke_assert( 'trash' === (string) ( $approve_execute_result['execution']['post_status_after'] ?? '' ), 'adapter approve-and-execute records post status after execution' );
maa_adapter_smoke_assert( true === (bool) ( $approve_execute_result['execution']['success'] ?? false ), 'adapter approve-and-execute execution result succeeds' );
maa_adapter_smoke_assert( 'trash' === (string) get_post_status( $approve_execute_post_id ), 'adapter approve-and-execute moves pending proposal post to trash' );

$batch_post_id = maa_adapter_smoke_create_trash_post_fixture();
$batch_second_post_id = maa_adapter_smoke_create_trash_post_fixture();
$maa_adapter_smoke_cleanup_post_ids[] = $batch_post_id;
$maa_adapter_smoke_cleanup_post_ids[] = $batch_second_post_id;
$batch_proposal = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/build-test-content-cleanup-plan',
		'title'      => 'Adapter batch approve execute smoke',
		'summary'    => 'Adapter approves through Core and executes a bounded write_actions trash-post batch.',
		'input'      => array(
			'write_actions' => array(
				array(
					'action_id'         => 'trash-post-' . $batch_post_id,
					'target_ability_id' => 'magick-ai/trash-post',
					'input'             => array(
						'post_id' => $batch_post_id,
						'dry_run' => true,
						'commit'  => false,
					),
					'requires_approval' => true,
					'commit_execution'  => false,
					'proposal_ready'    => true,
				),
				array(
					'action_id'         => 'trash-post-' . $batch_second_post_id,
					'target_ability_id' => 'magick-ai/trash-post',
					'input'             => array(
						'post_id' => $batch_second_post_id,
						'dry_run' => true,
						'commit'  => false,
					),
					'requires_approval' => true,
					'commit_execution'  => false,
					'proposal_ready'    => true,
				),
			),
		),
		'preview'    => array(
			'action'           => 'batch_trash_posts',
			'action_count'     => 2,
			'proposal_ready'   => true,
			'commit_execution' => false,
		),
		'caller'     => array(
			'external_thread_id' => 'adapter-batch-approve-execute-smoke',
		),
	)
);
$batch_proposal_id = (string) ( $batch_proposal['proposal_id'] ?? '' );
$maa_adapter_smoke_cleanup_proposal_ids[] = $batch_proposal_id;
maa_adapter_smoke_assert( '' !== $batch_proposal_id, 'adapter creates write_actions batch proposal for approve-and-execute smoke' );
$batch_result = maa_adapter_smoke_rest( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $batch_proposal_id ) . '/approve-and-execute' );
maa_adapter_smoke_assert( true === (bool) ( $batch_result['success'] ?? false ), 'adapter batch approve-and-execute succeeds for allowlisted write_actions' );
maa_adapter_smoke_assert( 'batch_write_actions' === (string) ( $batch_result['execution_mode'] ?? '' ), 'adapter batch approve-and-execute reports batch execution mode' );
maa_adapter_smoke_assert( 2 === (int) ( $batch_result['executed_count'] ?? 0 ), 'adapter batch approve-and-execute reports executed count' );
maa_adapter_smoke_assert( 0 === (int) ( $batch_result['failed_count'] ?? 1 ), 'adapter batch approve-and-execute reports zero failures' );
maa_adapter_smoke_assert( is_array( $batch_result['results'] ?? null ) && 2 === count( $batch_result['results'] ), 'adapter batch approve-and-execute returns per-action results' );
maa_adapter_smoke_assert( 'magick-ai/trash-post' === (string) ( $batch_result['results'][0]['target_ability_id'] ?? '' ), 'adapter batch approve-and-execute result carries target ability id' );
maa_adapter_smoke_assert( 'trash' === (string) get_post_status( $batch_post_id ), 'adapter batch approve-and-execute trashes first post' );
maa_adapter_smoke_assert( 'trash' === (string) get_post_status( $batch_second_post_id ), 'adapter batch approve-and-execute trashes second post' );

$referenced_batch_proposal = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/build-test-content-cleanup-plan',
		'title'      => 'Adapter output reference batch smoke',
		'summary'    => 'Adapter resolves prior action outputs inside one approved write_actions batch.',
		'input'      => array(
			'write_actions' => array(
				array(
					'action_id'         => 'create-draft',
					'target_ability_id' => 'magick-ai/create-draft',
					'input'             => array(
						'title'          => 'Adapter referenced batch draft',
						'content'        => 'Adapter output reference batch smoke.',
						'content_format' => 'plain',
						'dry_run'        => true,
						'commit'         => false,
					),
					'requires_approval' => true,
					'commit_execution'  => false,
					'proposal_ready'    => true,
				),
				array(
					'action_id'         => 'update-created-draft',
					'target_ability_id' => 'magick-ai/update-post',
					'input'             => array(
						'post_id'        => '$outputs.create-draft.post_id',
						'title'          => 'Adapter referenced batch updated draft',
						'content'        => 'Adapter resolved create-draft output before update.',
						'content_format' => 'plain',
						'dry_run'        => true,
						'commit'         => false,
					),
					'requires_approval' => true,
					'commit_execution'  => false,
					'proposal_ready'    => true,
				),
				array(
					'action_id'         => 'trash-created-draft',
					'target_ability_id' => 'magick-ai/trash-post',
					'input'             => array(
						'post_id' => '$outputs.create-draft.post_id',
						'dry_run' => true,
						'commit'  => false,
					),
					'requires_approval' => true,
					'commit_execution'  => false,
					'proposal_ready'    => true,
				),
			),
		),
		'preview'    => array(
			'action'           => 'batch_referenced_draft_lifecycle',
			'action_count'     => 3,
			'proposal_ready'   => true,
			'commit_execution' => false,
		),
		'caller'     => array(
			'external_thread_id' => 'adapter-output-reference-batch-smoke',
		),
	)
);
$referenced_batch_proposal_id = (string) ( $referenced_batch_proposal['proposal_id'] ?? '' );
$maa_adapter_smoke_cleanup_proposal_ids[] = $referenced_batch_proposal_id;
maa_adapter_smoke_assert( '' !== $referenced_batch_proposal_id, 'adapter creates output-reference write_actions batch proposal' );
$referenced_batch_result = maa_adapter_smoke_rest( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $referenced_batch_proposal_id ) . '/approve-and-execute' );
maa_adapter_smoke_assert( true === (bool) ( $referenced_batch_result['success'] ?? false ), 'adapter batch approve-and-execute succeeds with output references' );
maa_adapter_smoke_assert( 3 === (int) ( $referenced_batch_result['executed_count'] ?? 0 ), 'adapter output-reference batch executes all actions' );
$referenced_batch_post_id = (int) ( $referenced_batch_result['results'][0]['post_id'] ?? 0 );
$maa_adapter_smoke_cleanup_post_ids[] = $referenced_batch_post_id;
maa_adapter_smoke_assert( $referenced_batch_post_id > 0, 'adapter output-reference batch creates a draft post' );
maa_adapter_smoke_assert( $referenced_batch_post_id === (int) ( $referenced_batch_result['results'][1]['post_id'] ?? 0 ), 'adapter output-reference batch updates the created draft' );
maa_adapter_smoke_assert( $referenced_batch_post_id === (int) ( $referenced_batch_result['results'][2]['post_id'] ?? 0 ), 'adapter output-reference batch trashes the created draft' );
maa_adapter_smoke_assert( 'trash' === (string) get_post_status( $referenced_batch_post_id ), 'adapter output-reference batch leaves created draft trashed' );

$embedded_reference_batch_proposal = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/build-test-content-cleanup-plan',
		'title'      => 'Adapter embedded output reference batch smoke',
		'summary'    => 'Adapter must reject embedded output reference tokens before batch execution.',
		'input'      => array(
			'write_actions' => array(
				array(
					'action_id'         => 'embedded-output-reference',
					'target_ability_id' => 'magick-ai/create-draft',
					'input'             => array(
						'title'   => 'Adapter embedded output reference batch',
						'content' => 'prefix-$outputs.embedded-output-reference.post_id',
						'dry_run' => true,
						'commit'  => false,
					),
					'requires_approval' => true,
					'commit_execution'  => false,
					'proposal_ready'    => true,
				),
			),
		),
		'preview'    => array(
			'action'           => 'batch_embedded_output_reference',
			'proposal_ready'   => true,
			'commit_execution' => false,
		),
	)
);
$embedded_reference_batch_proposal_id = (string) ( $embedded_reference_batch_proposal['proposal_id'] ?? '' );
$maa_adapter_smoke_cleanup_proposal_ids[] = $embedded_reference_batch_proposal_id;
$embedded_reference_batch_result = maa_adapter_smoke_rest_result( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $embedded_reference_batch_proposal_id ) . '/approve-and-execute' );
maa_adapter_smoke_assert( 400 === (int) $embedded_reference_batch_result['status'], 'adapter batch approve-and-execute rejects embedded output reference tokens before execution' );
maa_adapter_smoke_assert( 'magick_ai_adapter_output_reference_invalid' === (string) ( $embedded_reference_batch_result['data']['code'] ?? '' ), 'adapter embedded output reference execution rejection uses output reference invalid code' );

$bad_batch_post_id = maa_adapter_smoke_create_trash_post_fixture();
$bad_batch_second_post_id = maa_adapter_smoke_create_trash_post_fixture();
$maa_adapter_smoke_cleanup_post_ids[] = $bad_batch_post_id;
$maa_adapter_smoke_cleanup_post_ids[] = $bad_batch_second_post_id;
$bad_batch_proposal = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/build-test-content-cleanup-plan',
		'title'      => 'Adapter bad batch approve execute smoke',
		'summary'    => 'Adapter must fail closed when write_actions contains a non-allowlisted target.',
		'input'      => array(
			'write_actions' => array(
				array(
					'action_id'         => 'trash-post-' . $bad_batch_post_id,
					'target_ability_id' => 'magick-ai/trash-post',
					'input'             => array(
						'post_id' => $bad_batch_post_id,
						'dry_run' => true,
						'commit'  => false,
					),
					'requires_approval' => true,
					'commit_execution'  => false,
					'proposal_ready'    => true,
				),
					array(
						'action_id'         => 'set-author-' . $bad_batch_second_post_id,
						'target_ability_id' => 'magick-ai/set-post-author',
						'input'             => array(
							'post_id'   => $bad_batch_second_post_id,
							'author_id' => 1,
							'dry_run'   => true,
							'commit'    => false,
						),
					'requires_approval' => true,
					'commit_execution'  => false,
					'proposal_ready'    => true,
				),
			),
		),
		'preview'    => array(
			'action'           => 'bad_batch_trash_posts',
			'action_count'     => 2,
			'proposal_ready'   => true,
			'commit_execution' => false,
		),
	)
);
$bad_batch_proposal_id = (string) ( $bad_batch_proposal['proposal_id'] ?? '' );
$maa_adapter_smoke_cleanup_proposal_ids[] = $bad_batch_proposal_id;
$bad_batch_result = maa_adapter_smoke_rest_result( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $bad_batch_proposal_id ) . '/approve-and-execute' );
maa_adapter_smoke_assert( 403 === (int) $bad_batch_result['status'], 'adapter batch approve-and-execute rejects non-allowlisted write_action' );
maa_adapter_smoke_assert( 'publish' === (string) get_post_status( $bad_batch_post_id ), 'adapter bad batch does not execute allowed action before failing closed' );
maa_adapter_smoke_assert( 'publish' === (string) get_post_status( $bad_batch_second_post_id ), 'adapter bad batch does not execute non-allowlisted action' );

$approved_skip_post_id = maa_adapter_smoke_create_trash_post_fixture();
$maa_adapter_smoke_cleanup_post_ids[] = $approved_skip_post_id;
$approved_skip_proposal = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/trash-post',
		'title'      => 'Adapter approved skip smoke',
		'summary'    => 'Adapter skips Core approve for already approved trash-post proposal.',
		'input'      => array(
			'post_id' => $approved_skip_post_id,
			'dry_run' => true,
			'commit'  => false,
		),
		'preview'    => array(
			'action'           => 'trash_post',
			'post_id'          => $approved_skip_post_id,
			'dry_run'          => true,
			'commit_execution' => false,
		),
	)
);
$approved_skip_proposal_id = (string) ( $approved_skip_proposal['proposal_id'] ?? '' );
$maa_adapter_smoke_cleanup_proposal_ids[] = $approved_skip_proposal_id;
maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-core/v1/proposals/' . rawurlencode( $approved_skip_proposal_id ) . '/approve',
	array(
		'note' => 'Approve before Adapter approve-and-execute smoke.',
	)
);
$approved_skip_result = maa_adapter_smoke_rest( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $approved_skip_proposal_id ) . '/approve-and-execute' );
maa_adapter_smoke_assert( true === (bool) ( $approved_skip_result['success'] ?? false ), 'adapter approve-and-execute succeeds for already approved proposal' );
maa_adapter_smoke_assert( 'approved' === (string) ( $approved_skip_result['status_before'] ?? '' ), 'adapter approve-and-execute records approved status before execution' );
maa_adapter_smoke_assert( false === (bool) ( $approved_skip_result['approved_by_adapter'] ?? true ), 'adapter approve-and-execute skips approve for already approved proposal' );
maa_adapter_smoke_assert( 'trash' === (string) get_post_status( $approved_skip_post_id ), 'adapter approve-and-execute moves already approved post to trash' );

$rejected_post_id = maa_adapter_smoke_create_trash_post_fixture();
$maa_adapter_smoke_cleanup_post_ids[] = $rejected_post_id;
$rejected_proposal = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/trash-post',
		'title'      => 'Adapter rejected approve execute smoke',
		'summary'    => 'Adapter must not execute rejected trash-post proposal.',
		'input'      => array(
			'post_id' => $rejected_post_id,
			'dry_run' => true,
			'commit'  => false,
		),
		'preview'    => array(
			'action'  => 'trash_post',
			'post_id' => $rejected_post_id,
		),
	)
);
$rejected_proposal_id = (string) ( $rejected_proposal['proposal_id'] ?? '' );
$maa_adapter_smoke_cleanup_proposal_ids[] = $rejected_proposal_id;
maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-core/v1/proposals/' . rawurlencode( $rejected_proposal_id ) . '/reject',
	array(
		'note' => 'Reject Adapter approve-and-execute smoke.',
	)
);
$rejected_execute = maa_adapter_smoke_rest_result( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $rejected_proposal_id ) . '/approve-and-execute' );
maa_adapter_smoke_assert( 409 === (int) $rejected_execute['status'], 'adapter approve-and-execute rejects rejected proposal' );
maa_adapter_smoke_assert( 'publish' === (string) get_post_status( $rejected_post_id ), 'adapter approve-and-execute does not execute rejected proposal' );

$blocked_post_id = maa_adapter_smoke_create_trash_post_fixture();
$maa_adapter_smoke_cleanup_post_ids[] = $blocked_post_id;
$blocked_proposal = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/trash-post',
		'title'      => 'Adapter blocked preflight smoke',
		'summary'    => 'Adapter must not execute if Core commit-preflight blocks the item.',
		'input'      => array(
			'post_id' => $blocked_post_id,
			'dry_run' => true,
			'commit'  => false,
		),
		'preview'    => array(
			'action'             => 'trash_post',
			'post_id'            => $blocked_post_id,
			'proposal_ready'     => false,
			'preflight_blockers' => array(
				array(
					'code'   => 'adapter_smoke_blocker',
					'reason' => 'Adapter smoke requires Core preflight failure.',
				),
			),
		),
	)
);
$blocked_proposal_id = (string) ( $blocked_proposal['proposal_id'] ?? '' );
$maa_adapter_smoke_cleanup_proposal_ids[] = $blocked_proposal_id;
$blocked_execute = maa_adapter_smoke_rest_result( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $blocked_proposal_id ) . '/approve-and-execute' );
maa_adapter_smoke_assert( 409 === (int) $blocked_execute['status'], 'adapter approve-and-execute returns preflight failure' );
maa_adapter_smoke_assert( 'publish' === (string) get_post_status( $blocked_post_id ), 'adapter approve-and-execute does not execute preflight-blocked proposal' );

$draft_proposal = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/create-draft',
		'title'      => 'Adapter draft approve execute smoke',
		'summary'    => 'Adapter approves through Core and executes one create-draft proposal.',
		'input'      => array(
			'title'          => 'Adapter approved draft smoke',
			'content'        => 'Adapter approved draft execution smoke.',
			'content_format' => 'plain',
			'dry_run'        => true,
			'commit'         => false,
		),
		'preview'    => array(
			'action'           => 'create_draft',
			'dry_run'          => true,
			'commit_execution' => false,
		),
		'caller'     => array(
			'external_thread_id' => 'adapter-create-draft-approve-execute-smoke',
		),
	)
);
$draft_proposal_id = (string) ( $draft_proposal['proposal_id'] ?? '' );
$maa_adapter_smoke_cleanup_proposal_ids[] = $draft_proposal_id;
maa_adapter_smoke_assert( '' !== $draft_proposal_id, 'adapter creates create-draft proposal for approve-and-execute smoke' );
$draft_execute = maa_adapter_smoke_rest( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $draft_proposal_id ) . '/approve-and-execute' );
maa_adapter_smoke_assert( true === (bool) ( $draft_execute['success'] ?? false ), 'adapter approve-and-execute succeeds for pending create-draft proposal' );
maa_adapter_smoke_assert( 'magick-ai/create-draft' === (string) ( $draft_execute['ability_id'] ?? '' ), 'adapter approve-and-execute response carries create-draft ability id' );
maa_adapter_smoke_assert( (int) ( $draft_execute['post_id'] ?? 0 ) > 0, 'adapter approve-and-execute returns created draft post id' );
maa_adapter_smoke_assert( 'draft' === (string) ( $draft_execute['execution']['post_status_after'] ?? '' ), 'adapter approve-and-execute records draft status after creation' );
maa_adapter_smoke_assert( false === (bool) ( $draft_execute['execution']['result']['dry_run'] ?? true ), 'adapter create-draft execution returns non-dry-run ability result' );
$maa_adapter_smoke_cleanup_post_ids[] = (int) ( $draft_execute['post_id'] ?? 0 );
maa_adapter_smoke_assert( 'draft' === (string) get_post_status( (int) ( $draft_execute['post_id'] ?? 0 ) ), 'adapter approve-and-execute creates a WordPress draft' );

$update_post_id = maa_adapter_smoke_create_trash_post_fixture();
$maa_adapter_smoke_cleanup_post_ids[] = $update_post_id;
$update_proposal = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/update-post',
		'title'      => 'Adapter update-post approve execute smoke',
		'summary'    => 'Adapter approves through Core and executes one update-post proposal.',
		'input'      => array(
			'post_id'        => $update_post_id,
			'title'          => 'Adapter updated post smoke',
			'content'        => 'Adapter approved update-post execution smoke.',
			'content_format' => 'plain',
			'dry_run'        => true,
			'commit'         => false,
		),
		'preview'    => array(
			'action'           => 'update_post',
			'post_id'          => $update_post_id,
			'dry_run'          => true,
			'commit_execution' => false,
		),
		'caller'     => array(
			'external_thread_id' => 'adapter-update-post-approve-execute-smoke',
		),
	)
);
$update_proposal_id = (string) ( $update_proposal['proposal_id'] ?? '' );
$maa_adapter_smoke_cleanup_proposal_ids[] = $update_proposal_id;
maa_adapter_smoke_assert( '' !== $update_proposal_id, 'adapter creates update-post proposal for approve-and-execute smoke' );
$update_execute = maa_adapter_smoke_rest( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $update_proposal_id ) . '/approve-and-execute' );
maa_adapter_smoke_assert( true === (bool) ( $update_execute['success'] ?? false ), 'adapter approve-and-execute succeeds for pending update-post proposal' );
maa_adapter_smoke_assert( 'magick-ai/update-post' === (string) ( $update_execute['ability_id'] ?? '' ), 'adapter approve-and-execute response carries update-post ability id' );
maa_adapter_smoke_assert( $update_post_id === (int) ( $update_execute['post_id'] ?? 0 ), 'adapter approve-and-execute response carries updated post id' );
maa_adapter_smoke_assert( false === (bool) ( $update_execute['execution']['result']['dry_run'] ?? true ), 'adapter update-post execution returns non-dry-run ability result' );
$updated_post = get_post( $update_post_id );
maa_adapter_smoke_assert( is_object( $updated_post ) && 'Adapter updated post smoke' === (string) $updated_post->post_title, 'adapter approve-and-execute updates post title' );
maa_adapter_smoke_assert( is_object( $updated_post ) && false !== strpos( (string) $updated_post->post_content, 'Adapter approved update-post execution smoke.' ), 'adapter approve-and-execute updates post content' );

$empty_update_proposal = maa_adapter_smoke_rest_result(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/update-post',
		'title'      => 'Adapter empty update-post smoke',
		'summary'    => 'Adapter must not execute update-post without update fields.',
		'input'      => array(
			'post_id' => $update_post_id,
			'dry_run' => true,
			'commit'  => false,
		),
		'preview'    => array(
			'action'  => 'update_post',
			'post_id' => $update_post_id,
		),
	)
);
maa_adapter_smoke_assert( 400 === (int) $empty_update_proposal['status'], 'adapter proposal create rejects update-post without update fields' );
maa_adapter_smoke_assert( 'Adapter updated post smoke' === (string) get_the_title( $update_post_id ), 'adapter empty update-post rejection leaves post unchanged' );

$invalid_update_status_proposal = maa_adapter_smoke_rest_result(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/update-post',
		'title'      => 'Adapter invalid update-post status smoke',
		'summary'    => 'Adapter must reject update-post proposal input with undeclared status.',
		'input'      => array(
			'post_id' => $update_post_id,
			'title'   => 'Adapter invalid status should not apply',
			'status'  => 'publish',
			'dry_run' => true,
			'commit'  => false,
		),
		'preview'    => array(
			'action'  => 'update_post',
			'post_id' => $update_post_id,
			'status'  => 'publish',
		),
	)
);
maa_adapter_smoke_assert( 400 === (int) $invalid_update_status_proposal['status'], 'adapter proposal create rejects update-post status input' );
maa_adapter_smoke_assert( 'magick_ai_adapter_ability_input_field_not_allowed' === (string) ( $invalid_update_status_proposal['data']['code'] ?? '' ), 'adapter update-post status rejection uses schema field error code' );

$seo_post_id = maa_adapter_smoke_create_trash_post_fixture();
$maa_adapter_smoke_cleanup_post_ids[] = $seo_post_id;
$seo_title = 'Adapter SEO title ' . wp_generate_uuid4();
$seo_description = 'Adapter SEO description for approve-and-execute smoke.';
$seo_proposal = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/set-post-seo-meta',
		'title'      => 'Adapter set-post-seo-meta approve execute smoke',
		'summary'    => 'Adapter approves through Core and executes one set-post-seo-meta proposal.',
		'input'      => array(
			'post_id'         => $seo_post_id,
			'seo_title'       => $seo_title,
			'seo_description' => $seo_description,
			'dry_run'         => true,
			'commit'          => false,
		),
		'preview'    => array(
			'action'           => 'set_post_seo_meta',
			'post_id'          => $seo_post_id,
			'changed_fields'   => array( 'seo_title', 'seo_description' ),
			'dry_run'          => true,
			'commit_execution' => false,
		),
		'caller'     => array(
			'external_thread_id' => 'adapter-set-post-seo-meta-approve-execute-smoke',
		),
	)
);
$seo_proposal_id = (string) ( $seo_proposal['proposal_id'] ?? '' );
$maa_adapter_smoke_cleanup_proposal_ids[] = $seo_proposal_id;
maa_adapter_smoke_assert( '' !== $seo_proposal_id, 'adapter creates set-post-seo-meta proposal for approve-and-execute smoke' );
$seo_execute = maa_adapter_smoke_rest( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $seo_proposal_id ) . '/approve-and-execute' );
maa_adapter_smoke_assert( true === (bool) ( $seo_execute['success'] ?? false ), 'adapter approve-and-execute succeeds for pending set-post-seo-meta proposal' );
maa_adapter_smoke_assert( 'magick-ai/set-post-seo-meta' === (string) ( $seo_execute['ability_id'] ?? '' ), 'adapter approve-and-execute response carries set-post-seo-meta ability id' );
maa_adapter_smoke_assert( $seo_post_id === (int) ( $seo_execute['post_id'] ?? 0 ), 'adapter approve-and-execute response carries SEO post id' );
maa_adapter_smoke_assert( true === (bool) ( $seo_execute['execution']['result']['updated'] ?? false ), 'adapter set-post-seo-meta execution reports update' );
maa_adapter_smoke_assert( $seo_title === (string) get_post_meta( $seo_post_id, '_yoast_wpseo_title', true ), 'adapter approve-and-execute writes SEO title meta' );
maa_adapter_smoke_assert( $seo_description === (string) get_post_meta( $seo_post_id, '_yoast_wpseo_metadesc', true ), 'adapter approve-and-execute writes SEO description meta' );

$empty_seo_proposal = maa_adapter_smoke_rest_result(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/set-post-seo-meta',
		'title'      => 'Adapter empty set-post-seo-meta smoke',
		'summary'    => 'Adapter must not execute set-post-seo-meta without SEO fields.',
		'input'      => array(
			'post_id' => $seo_post_id,
			'dry_run' => true,
			'commit'  => false,
		),
		'preview'    => array(
			'action'  => 'set_post_seo_meta',
			'post_id' => $seo_post_id,
		),
	)
);
maa_adapter_smoke_assert( 400 === (int) $empty_seo_proposal['status'], 'adapter proposal create rejects set-post-seo-meta without SEO fields' );

$slug_post_id = maa_adapter_smoke_create_trash_post_fixture();
$maa_adapter_smoke_cleanup_post_ids[] = $slug_post_id;
$slug = 'adapter-slug-smoke-' . substr( md5( wp_generate_uuid4() ), 0, 12 );
$slug_proposal = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/set-post-slug',
		'title'      => 'Adapter set-post-slug approve execute smoke',
		'summary'    => 'Adapter approves through Core and executes one set-post-slug proposal.',
		'input'      => array(
			'post_id' => $slug_post_id,
			'slug'    => $slug,
			'dry_run' => true,
			'commit'  => false,
		),
		'preview'    => array(
			'action'           => 'set_post_slug',
			'post_id'          => $slug_post_id,
			'slug'             => $slug,
			'dry_run'          => true,
			'commit_execution' => false,
		),
		'caller'     => array(
			'external_thread_id' => 'adapter-set-post-slug-approve-execute-smoke',
		),
	)
);
$slug_proposal_id = (string) ( $slug_proposal['proposal_id'] ?? '' );
$maa_adapter_smoke_cleanup_proposal_ids[] = $slug_proposal_id;
maa_adapter_smoke_assert( '' !== $slug_proposal_id, 'adapter creates set-post-slug proposal for approve-and-execute smoke' );
$slug_execute = maa_adapter_smoke_rest( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $slug_proposal_id ) . '/approve-and-execute' );
maa_adapter_smoke_assert( true === (bool) ( $slug_execute['success'] ?? false ), 'adapter approve-and-execute succeeds for pending set-post-slug proposal' );
maa_adapter_smoke_assert( 'magick-ai/set-post-slug' === (string) ( $slug_execute['ability_id'] ?? '' ), 'adapter approve-and-execute response carries set-post-slug ability id' );
maa_adapter_smoke_assert( $slug_post_id === (int) ( $slug_execute['post_id'] ?? 0 ), 'adapter approve-and-execute response carries slug post id' );
maa_adapter_smoke_assert( $slug === (string) get_post_field( 'post_name', $slug_post_id ), 'adapter approve-and-execute writes post slug' );

$empty_slug_proposal = maa_adapter_smoke_rest_result(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/set-post-slug',
		'title'      => 'Adapter empty set-post-slug smoke',
		'summary'    => 'Adapter must not execute set-post-slug without a valid slug.',
		'input'      => array(
			'post_id' => $slug_post_id,
			'slug'    => '!!!',
			'dry_run' => true,
			'commit'  => false,
		),
		'preview'    => array(
			'action'  => 'set_post_slug',
			'post_id' => $slug_post_id,
		),
	)
);
maa_adapter_smoke_assert( 400 === (int) $empty_slug_proposal['status'], 'adapter proposal create rejects set-post-slug without valid slug' );

$set_terms_post_id = maa_adapter_smoke_create_trash_post_fixture();
$maa_adapter_smoke_cleanup_post_ids[] = $set_terms_post_id;
$set_terms_term = wp_insert_term( 'Adapter Terms Smoke ' . wp_generate_uuid4(), 'post_tag' );
maa_adapter_smoke_assert( ! is_wp_error( $set_terms_term ) && (int) ( $set_terms_term['term_id'] ?? 0 ) > 0, 'adapter smoke created post_tag term fixture' );
$set_terms_term_id = (int) $set_terms_term['term_id'];
$maa_adapter_smoke_cleanup_terms[] = array(
	'term_id'  => $set_terms_term_id,
	'taxonomy' => 'post_tag',
);
$set_terms_proposal = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/set-post-terms',
		'title'      => 'Adapter set-post-terms approve execute smoke',
		'summary'    => 'Adapter approves through Core and executes one set-post-terms proposal.',
		'input'      => array(
			'post_id'        => $set_terms_post_id,
			'taxonomy'       => 'post_tag',
			'mode'           => 'append',
			'term_ids'       => array( $set_terms_term_id ),
			'create_missing' => false,
			'dry_run'        => true,
			'commit'         => false,
		),
		'preview'    => array(
			'action'           => 'set_post_terms',
			'post_id'          => $set_terms_post_id,
			'taxonomy'         => 'post_tag',
			'mode'             => 'append',
			'term_ids'         => array( $set_terms_term_id ),
			'dry_run'          => true,
			'commit_execution' => false,
		),
		'caller'     => array(
			'external_thread_id' => 'adapter-set-post-terms-approve-execute-smoke',
		),
	)
);
$set_terms_proposal_id = (string) ( $set_terms_proposal['proposal_id'] ?? '' );
$maa_adapter_smoke_cleanup_proposal_ids[] = $set_terms_proposal_id;
maa_adapter_smoke_assert( '' !== $set_terms_proposal_id, 'adapter creates set-post-terms proposal for approve-and-execute smoke' );
$set_terms_execute = maa_adapter_smoke_rest( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $set_terms_proposal_id ) . '/approve-and-execute' );
maa_adapter_smoke_assert( true === (bool) ( $set_terms_execute['success'] ?? false ), 'adapter approve-and-execute succeeds for pending set-post-terms proposal' );
maa_adapter_smoke_assert( 'magick-ai/set-post-terms' === (string) ( $set_terms_execute['ability_id'] ?? '' ), 'adapter approve-and-execute response carries set-post-terms ability id' );
maa_adapter_smoke_assert( $set_terms_post_id === (int) ( $set_terms_execute['post_id'] ?? 0 ), 'adapter approve-and-execute response carries terms post id' );
maa_adapter_smoke_assert( false === (bool) ( $set_terms_execute['execution']['result']['dry_run'] ?? true ), 'adapter set-post-terms execution returns non-dry-run ability result' );
$assigned_term_ids = wp_get_post_terms( $set_terms_post_id, 'post_tag', array( 'fields' => 'ids' ) );
maa_adapter_smoke_assert( ! is_wp_error( $assigned_term_ids ) && in_array( $set_terms_term_id, array_map( 'intval', (array) $assigned_term_ids ), true ), 'adapter approve-and-execute assigns existing post term' );

$empty_terms_proposal = maa_adapter_smoke_rest_result(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/set-post-terms',
		'title'      => 'Adapter empty set-post-terms smoke',
		'summary'    => 'Adapter must not execute set-post-terms without terms.',
		'input'      => array(
			'post_id'  => $set_terms_post_id,
			'taxonomy' => 'post_tag',
			'mode'     => 'append',
			'dry_run'  => true,
			'commit'   => false,
		),
		'preview'    => array(
			'action'  => 'set_post_terms',
			'post_id' => $set_terms_post_id,
		),
	)
);
maa_adapter_smoke_assert( 400 === (int) $empty_terms_proposal['status'], 'adapter proposal create rejects set-post-terms without terms' );

$create_missing_terms_proposal = maa_adapter_smoke_rest_result(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/set-post-terms',
		'title'      => 'Adapter create-missing terms smoke',
		'summary'    => 'Adapter must not create missing terms during set-post-terms execution.',
		'input'      => array(
			'post_id'        => $set_terms_post_id,
			'taxonomy'       => 'post_tag',
			'mode'           => 'append',
			'terms'          => array( 'Adapter Missing Term Smoke' ),
			'create_missing' => true,
			'dry_run'        => true,
			'commit'         => false,
		),
		'preview'    => array(
			'action'         => 'set_post_terms',
			'post_id'        => $set_terms_post_id,
			'create_missing' => true,
		),
	)
);
maa_adapter_smoke_assert( 400 === (int) $create_missing_terms_proposal['status'], 'adapter proposal create rejects set-post-terms create_missing' );

$delete_term = wp_insert_term( 'Adapter Delete Term Smoke ' . wp_generate_uuid4(), 'post_tag' );
maa_adapter_smoke_assert( ! is_wp_error( $delete_term ) && (int) ( $delete_term['term_id'] ?? 0 ) > 0, 'adapter smoke created delete-term fixture' );
$delete_term_id = (int) $delete_term['term_id'];
$maa_adapter_smoke_cleanup_terms[] = array(
	'term_id'  => $delete_term_id,
	'taxonomy' => 'post_tag',
);
$delete_term_proposal = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/delete-term',
		'title'      => 'Adapter delete-term approve execute smoke',
		'summary'    => 'Adapter approves through Core and executes one delete-term proposal.',
		'input'      => array(
			'taxonomy' => 'post_tag',
			'term_id'  => $delete_term_id,
			'dry_run'  => true,
			'commit'   => false,
		),
		'preview'    => array(
			'action'           => 'delete_term',
			'taxonomy'         => 'post_tag',
			'term_id'          => $delete_term_id,
			'dry_run'          => true,
			'commit_execution' => false,
		),
		'caller'     => array(
			'external_thread_id' => 'adapter-delete-term-approve-execute-smoke',
		),
	)
);
$delete_term_proposal_id = (string) ( $delete_term_proposal['proposal_id'] ?? '' );
$maa_adapter_smoke_cleanup_proposal_ids[] = $delete_term_proposal_id;
maa_adapter_smoke_assert( '' !== $delete_term_proposal_id, 'adapter creates delete-term proposal for approve-and-execute smoke' );
$delete_term_execute = maa_adapter_smoke_rest( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $delete_term_proposal_id ) . '/approve-and-execute' );
maa_adapter_smoke_assert( true === (bool) ( $delete_term_execute['success'] ?? false ), 'adapter approve-and-execute succeeds for pending delete-term proposal' );
maa_adapter_smoke_assert( 'magick-ai/delete-term' === (string) ( $delete_term_execute['ability_id'] ?? '' ), 'adapter approve-and-execute response carries delete-term ability id' );
maa_adapter_smoke_assert( false === (bool) ( $delete_term_execute['execution']['result']['dry_run'] ?? true ), 'adapter delete-term execution returns non-dry-run ability result' );
maa_adapter_smoke_assert( true === (bool) ( $delete_term_execute['execution']['result']['deleted'] ?? false ), 'adapter delete-term execution reports deletion' );
maa_adapter_smoke_assert( ! term_exists( $delete_term_id, 'post_tag' ), 'adapter approve-and-execute deletes unused term' );

$empty_delete_term_proposal = maa_adapter_smoke_rest_result(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/delete-term',
		'title'      => 'Adapter empty delete-term smoke',
		'summary'    => 'Adapter must not execute delete-term without term_id.',
		'input'      => array(
			'taxonomy' => 'post_tag',
			'dry_run'  => true,
			'commit'   => false,
		),
		'preview'    => array(
			'action'   => 'delete_term',
			'taxonomy' => 'post_tag',
		),
	)
);
maa_adapter_smoke_assert( 400 === (int) $empty_delete_term_proposal['status'], 'adapter proposal create rejects delete-term without term_id' );

$invalid_taxonomy_delete_term_proposal = maa_adapter_smoke_rest_result(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/delete-term',
		'title'      => 'Adapter invalid taxonomy delete-term smoke',
		'summary'    => 'Adapter must not execute delete-term without a valid taxonomy.',
		'input'      => array(
			'taxonomy' => 'adapter_missing_taxonomy',
			'term_id'  => $delete_term_id,
			'dry_run'  => true,
			'commit'   => false,
		),
		'preview'    => array(
			'action'   => 'delete_term',
			'taxonomy' => 'adapter_missing_taxonomy',
			'term_id'  => $delete_term_id,
		),
	)
);
maa_adapter_smoke_assert( 400 === (int) $invalid_taxonomy_delete_term_proposal['status'], 'adapter proposal create rejects delete-term invalid taxonomy' );

$media_details_attachment_id = maa_adapter_smoke_create_media_plan_attachment();
$maa_adapter_smoke_cleanup_attachment_ids[] = $media_details_attachment_id;
$media_details_title = 'Adapter media details smoke ' . wp_generate_uuid4();
$media_details_alt = 'Adapter media details alt smoke.';
$media_details_proposal = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/update-media-details',
		'title'      => 'Adapter update-media-details approve execute smoke',
		'summary'    => 'Adapter approves through Core and executes one update-media-details proposal.',
		'input'      => array(
			'attachment_id' => $media_details_attachment_id,
			'title'         => $media_details_title,
			'alt'           => $media_details_alt,
			'dry_run'       => true,
			'commit'        => false,
		),
		'preview'    => array(
			'action'           => 'update_media_details',
			'attachment_id'    => $media_details_attachment_id,
			'changed_fields'   => array( 'title', 'alt' ),
			'dry_run'          => true,
			'commit_execution' => false,
		),
		'caller'     => array(
			'external_thread_id' => 'adapter-update-media-details-approve-execute-smoke',
		),
	)
);
$media_details_proposal_id = (string) ( $media_details_proposal['proposal_id'] ?? '' );
$maa_adapter_smoke_cleanup_proposal_ids[] = $media_details_proposal_id;
maa_adapter_smoke_assert( '' !== $media_details_proposal_id, 'adapter creates update-media-details proposal for approve-and-execute smoke' );
$media_details_execute = maa_adapter_smoke_rest( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $media_details_proposal_id ) . '/approve-and-execute' );
maa_adapter_smoke_assert( true === (bool) ( $media_details_execute['success'] ?? false ), 'adapter approve-and-execute succeeds for pending update-media-details proposal' );
maa_adapter_smoke_assert( 'magick-ai/update-media-details' === (string) ( $media_details_execute['ability_id'] ?? '' ), 'adapter approve-and-execute response carries update-media-details ability id' );
maa_adapter_smoke_assert( $media_details_attachment_id === (int) ( $media_details_execute['execution']['result']['attachment_id'] ?? 0 ), 'adapter update-media-details execution result carries attachment id' );
maa_adapter_smoke_assert( true === (bool) ( $media_details_execute['execution']['result']['updated'] ?? false ), 'adapter update-media-details execution reports update' );
maa_adapter_smoke_assert( $media_details_title === (string) get_the_title( $media_details_attachment_id ), 'adapter approve-and-execute updates media title' );
maa_adapter_smoke_assert( $media_details_alt === (string) get_post_meta( $media_details_attachment_id, '_wp_attachment_image_alt', true ), 'adapter approve-and-execute updates media alt text' );

$empty_media_details_proposal = maa_adapter_smoke_rest_result(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/update-media-details',
		'title'      => 'Adapter empty update-media-details smoke',
		'summary'    => 'Adapter must not execute update-media-details without detail fields.',
		'input'      => array(
			'attachment_id' => $media_details_attachment_id,
			'dry_run'       => true,
			'commit'        => false,
		),
		'preview'    => array(
			'action'        => 'update_media_details',
			'attachment_id' => $media_details_attachment_id,
		),
	)
);
maa_adapter_smoke_assert( 400 === (int) $empty_media_details_proposal['status'], 'adapter proposal create rejects update-media-details without detail fields' );

$reply_post_id = maa_adapter_smoke_create_trash_post_fixture();
$maa_adapter_smoke_cleanup_post_ids[] = $reply_post_id;
$reply_parent_comment_id = maa_adapter_smoke_create_comment_fixture( $reply_post_id );
$maa_adapter_smoke_cleanup_comment_ids[] = $reply_parent_comment_id;
$reply_proposal = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/reply-comment',
		'title'      => 'Adapter reply-comment approve execute smoke',
		'summary'    => 'Adapter approves through Core and executes one reply-comment proposal.',
		'input'      => array(
			'comment_id'     => $reply_parent_comment_id,
			'content'        => 'Adapter approved reply-comment execution smoke.',
			'content_format' => 'plain',
			'dry_run'        => true,
			'commit'         => false,
		),
		'preview'    => array(
			'action'           => 'reply_comment',
			'comment_id'       => $reply_parent_comment_id,
			'content_format'   => 'plain',
			'dry_run'          => true,
			'commit_execution' => false,
		),
		'caller'     => array(
			'external_thread_id' => 'adapter-reply-comment-approve-execute-smoke',
		),
	)
);
$reply_proposal_id = (string) ( $reply_proposal['proposal_id'] ?? '' );
$maa_adapter_smoke_cleanup_proposal_ids[] = $reply_proposal_id;
maa_adapter_smoke_assert( '' !== $reply_proposal_id, 'adapter creates reply-comment proposal for approve-and-execute smoke' );
$reply_execute = maa_adapter_smoke_rest( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $reply_proposal_id ) . '/approve-and-execute' );
maa_adapter_smoke_assert( true === (bool) ( $reply_execute['success'] ?? false ), 'adapter approve-and-execute succeeds for pending reply-comment proposal' );
maa_adapter_smoke_assert( 'magick-ai/reply-comment' === (string) ( $reply_execute['ability_id'] ?? '' ), 'adapter approve-and-execute response carries reply-comment ability id' );
maa_adapter_smoke_assert( $reply_post_id === (int) ( $reply_execute['post_id'] ?? 0 ), 'adapter approve-and-execute response carries reply post id' );
maa_adapter_smoke_assert( false === (bool) ( $reply_execute['execution']['result']['dry_run'] ?? true ), 'adapter reply-comment execution returns non-dry-run ability result' );
$reply_comment_id = (int) ( $reply_execute['execution']['result']['comment_id'] ?? 0 );
$maa_adapter_smoke_cleanup_comment_ids[] = $reply_comment_id;
$reply_comment = get_comment( $reply_comment_id );
maa_adapter_smoke_assert( $reply_comment instanceof WP_Comment, 'adapter approve-and-execute creates reply comment' );
maa_adapter_smoke_assert( $reply_parent_comment_id === (int) ( $reply_comment->comment_parent ?? 0 ), 'adapter reply-comment uses original comment as parent' );
maa_adapter_smoke_assert( $reply_post_id === (int) ( $reply_comment->comment_post_ID ?? 0 ), 'adapter reply-comment stays on original post' );

$empty_reply_proposal = maa_adapter_smoke_rest_result(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/reply-comment',
		'title'      => 'Adapter empty reply-comment smoke',
		'summary'    => 'Adapter must not execute reply-comment without content.',
		'input'      => array(
			'comment_id' => $reply_parent_comment_id,
			'content'    => '   ',
			'dry_run'    => true,
			'commit'     => false,
		),
		'preview'    => array(
			'action'     => 'reply_comment',
			'comment_id' => $reply_parent_comment_id,
		),
	)
);
maa_adapter_smoke_assert( 400 === (int) $empty_reply_proposal['status'], 'adapter proposal create rejects reply-comment without content' );

$approve_comment_post_id = maa_adapter_smoke_create_trash_post_fixture();
$maa_adapter_smoke_cleanup_post_ids[] = $approve_comment_post_id;
$approve_comment_id = maa_adapter_smoke_create_comment_fixture( $approve_comment_post_id, 'Adapter approve-comment pending smoke.', '0' );
$maa_adapter_smoke_cleanup_comment_ids[] = $approve_comment_id;
$approve_comment_proposal = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/approve-comment',
		'title'      => 'Adapter approve-comment approve execute smoke',
		'summary'    => 'Adapter approves through Core and executes one approve-comment proposal.',
		'input'      => array(
			'comment_id' => $approve_comment_id,
			'dry_run'    => true,
			'commit'     => false,
		),
		'preview'    => array(
			'action'           => 'approve_comment',
			'comment_id'       => $approve_comment_id,
			'dry_run'          => true,
			'commit_execution' => false,
		),
		'caller'     => array(
			'external_thread_id' => 'adapter-approve-comment-approve-execute-smoke',
		),
	)
);
$approve_comment_proposal_id = (string) ( $approve_comment_proposal['proposal_id'] ?? '' );
$maa_adapter_smoke_cleanup_proposal_ids[] = $approve_comment_proposal_id;
maa_adapter_smoke_assert( '' !== $approve_comment_proposal_id, 'adapter creates approve-comment proposal for approve-and-execute smoke' );
$approve_comment_execute = maa_adapter_smoke_rest( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $approve_comment_proposal_id ) . '/approve-and-execute' );
maa_adapter_smoke_assert( true === (bool) ( $approve_comment_execute['success'] ?? false ), 'adapter approve-and-execute succeeds for pending approve-comment proposal' );
maa_adapter_smoke_assert( 'magick-ai/approve-comment' === (string) ( $approve_comment_execute['ability_id'] ?? '' ), 'adapter approve-and-execute response carries approve-comment ability id' );
maa_adapter_smoke_assert( $approve_comment_post_id === (int) ( $approve_comment_execute['post_id'] ?? 0 ), 'adapter approve-and-execute response carries approve-comment post id' );
maa_adapter_smoke_assert( false === (bool) ( $approve_comment_execute['execution']['result']['dry_run'] ?? true ), 'adapter approve-comment execution returns non-dry-run ability result' );
maa_adapter_smoke_assert( true === (bool) ( $approve_comment_execute['execution']['result']['updated'] ?? false ), 'adapter approve-comment execution reports update' );
maa_adapter_smoke_assert( 'approved' === wp_get_comment_status( $approve_comment_id ), 'adapter approve-and-execute approves pending comment' );

$empty_approve_comment_proposal = maa_adapter_smoke_rest_result(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/approve-comment',
		'title'      => 'Adapter empty approve-comment smoke',
		'summary'    => 'Adapter must not execute approve-comment without comment_id.',
		'input'      => array(
			'comment_id' => 0,
			'dry_run'    => true,
			'commit'     => false,
		),
		'preview'    => array(
			'action'     => 'approve_comment',
			'comment_id' => 0,
		),
	)
);
maa_adapter_smoke_assert( 400 === (int) $empty_approve_comment_proposal['status'], 'adapter proposal create rejects approve-comment without comment_id' );

$trash_comment_post_id = maa_adapter_smoke_create_trash_post_fixture();
$maa_adapter_smoke_cleanup_post_ids[] = $trash_comment_post_id;
$trash_comment_id = maa_adapter_smoke_create_comment_fixture( $trash_comment_post_id, 'Adapter trash-comment smoke.' );
$maa_adapter_smoke_cleanup_comment_ids[] = $trash_comment_id;
$trash_comment_proposal = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/trash-comment',
		'title'      => 'Adapter trash-comment approve execute smoke',
		'summary'    => 'Adapter approves through Core and executes one trash-comment proposal.',
		'input'      => array(
			'comment_id' => $trash_comment_id,
			'dry_run'    => true,
			'commit'     => false,
		),
		'preview'    => array(
			'action'           => 'trash_comment',
			'comment_id'       => $trash_comment_id,
			'dry_run'          => true,
			'commit_execution' => false,
		),
		'caller'     => array(
			'external_thread_id' => 'adapter-trash-comment-approve-execute-smoke',
		),
	)
);
$trash_comment_proposal_id = (string) ( $trash_comment_proposal['proposal_id'] ?? '' );
$maa_adapter_smoke_cleanup_proposal_ids[] = $trash_comment_proposal_id;
maa_adapter_smoke_assert( '' !== $trash_comment_proposal_id, 'adapter creates trash-comment proposal for approve-and-execute smoke' );
$trash_comment_execute = maa_adapter_smoke_rest( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $trash_comment_proposal_id ) . '/approve-and-execute' );
maa_adapter_smoke_assert( true === (bool) ( $trash_comment_execute['success'] ?? false ), 'adapter approve-and-execute succeeds for pending trash-comment proposal' );
maa_adapter_smoke_assert( 'magick-ai/trash-comment' === (string) ( $trash_comment_execute['ability_id'] ?? '' ), 'adapter approve-and-execute response carries trash-comment ability id' );
maa_adapter_smoke_assert( $trash_comment_post_id === (int) ( $trash_comment_execute['post_id'] ?? 0 ), 'adapter approve-and-execute response carries trash-comment post id' );
maa_adapter_smoke_assert( 'trash' === wp_get_comment_status( $trash_comment_id ), 'adapter approve-and-execute trashes comment' );

$empty_trash_comment_proposal = maa_adapter_smoke_rest_result(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/trash-comment',
		'title'      => 'Adapter empty trash-comment smoke',
		'summary'    => 'Adapter must not execute trash-comment without comment_id.',
		'input'      => array(
			'comment_id' => 0,
			'dry_run'    => true,
			'commit'     => false,
		),
		'preview'    => array(
			'action'     => 'trash_comment',
			'comment_id' => 0,
		),
	)
);
maa_adapter_smoke_assert( 400 === (int) $empty_trash_comment_proposal['status'], 'adapter proposal create rejects trash-comment without comment_id' );

$unallowed_proposal = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/proposals',
	array(
		'ability_id' => 'magick-ai/set-post-author',
		'title'      => 'Adapter unallowed approve execute smoke',
		'summary'    => 'Adapter must not approve-and-execute non-allowlisted proposals.',
		'input'      => array(
			'post_id'   => $blocked_post_id,
			'author_id' => 1,
			'dry_run'   => true,
			'commit'    => false,
		),
		'preview'    => array(),
	)
);
$unallowed_proposal_id = (string) ( $unallowed_proposal['proposal_id'] ?? '' );
$maa_adapter_smoke_cleanup_proposal_ids[] = $unallowed_proposal_id;
$unallowed_approve_execute = maa_adapter_smoke_rest_result( 'POST', '/magick-ai-adapter/v1/proposals/' . rawurlencode( $unallowed_proposal_id ) . '/approve-and-execute' );
maa_adapter_smoke_assert( 403 === (int) $unallowed_approve_execute['status'], 'adapter approve-and-execute rejects non-allowlisted ability' );

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

$provider_model = maa_adapter_smoke_text_generation_model();
$provider_smoke = maa_adapter_smoke_rest(
	'POST',
	'/magick-ai-adapter/v1/ai-provider-log-correlation-smoke',
	array(
		'proposal_id'    => $proposal_id,
		'correlation_id' => $correlation_id,
		'ability_id'     => 'magick-ai/create-draft',
		'ai_provider'    => $provider_model['provider'],
		'ai_model'       => $provider_model['model'],
		'prompt'         => 'Reply with exactly: OK',
	)
);
maa_adapter_smoke_assert( 'success' === (string) ( $provider_smoke['status'] ?? '' ), 'adapter provider smoke succeeds with configured text model' );
maa_adapter_smoke_assert( $provider_model['provider'] === (string) ( $provider_smoke['log_context']['ai_provider'] ?? '' ), 'adapter provider smoke context carries selected provider' );
maa_adapter_smoke_assert( $provider_model['model'] === (string) ( $provider_smoke['log_context']['ai_model'] ?? '' ), 'adapter provider smoke context carries selected model' );
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
maa_adapter_smoke_assert( $provider_model['provider'] === (string) ( $ai_log_context['ai_provider'] ?? '' ), 'AI Request Logs context contains explicit provider even if provider column is blank' );
maa_adapter_smoke_assert( $provider_model['model'] === (string) ( $ai_log_context['ai_model'] ?? '' ), 'AI Request Logs context contains explicit model even if provider column is blank' );
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
$maa_adapter_smoke_cleanup_proposal_ids[] = $proposal_id;
foreach ( array_values( array_unique( array_filter( $maa_adapter_smoke_cleanup_proposal_ids ) ) ) as $cleanup_proposal_id ) {
	$wpdb->delete( $wpdb->prefix . 'magick_ai_core_audit_log', array( 'proposal_id' => $cleanup_proposal_id ), array( '%s' ) );
	$wpdb->delete( $wpdb->prefix . 'magick_ai_core_proposals', array( 'proposal_id' => $cleanup_proposal_id ), array( '%s' ) );
}
foreach ( array_values( array_unique( array_filter( $maa_adapter_smoke_cleanup_attachment_ids ) ) ) as $cleanup_attachment_id ) {
	wp_delete_attachment( (int) $cleanup_attachment_id, true );
}
foreach ( array_values( array_unique( array_filter( $maa_adapter_smoke_cleanup_comment_ids ) ) ) as $cleanup_comment_id ) {
	wp_delete_comment( (int) $cleanup_comment_id, true );
}
foreach ( array_values( array_unique( array_filter( $maa_adapter_smoke_cleanup_post_ids ) ) ) as $cleanup_post_id ) {
	wp_delete_post( (int) $cleanup_post_id, true );
}
foreach ( $maa_adapter_smoke_cleanup_terms as $cleanup_term ) {
	if ( is_array( $cleanup_term ) ) {
		wp_delete_term( (int) ( $cleanup_term['term_id'] ?? 0 ), (string) ( $cleanup_term['taxonomy'] ?? '' ) );
	}
}
maa_adapter_smoke_assert( true, 'adapter status smoke cleaned created proposal records' );

echo "magick-ai-adapter WordPress smoke: ok\n";
