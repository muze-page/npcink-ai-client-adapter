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

$capabilities = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/capabilities' );
$by_id        = maa_adapter_smoke_capabilities_by_id( $capabilities );
maa_adapter_smoke_assert( isset( $by_id['magick-ai-abilities/site-summary'] ), 'adapter exposes site-summary capability through Core' );
maa_adapter_smoke_assert( isset( $by_id['magick-ai-abilities/wp-diagnostics-summary'] ), 'adapter exposes diagnostics capability through Core' );
maa_adapter_smoke_assert( isset( $by_id['magick-ai-abilities/list-workflow-recipes'] ), 'adapter exposes workflow recipe list capability through Core' );
maa_adapter_smoke_assert( isset( $by_id['magick-ai-abilities/get-workflow-recipe'] ), 'adapter exposes workflow recipe detail capability through Core' );
maa_adapter_smoke_assert( 'direct_read' === (string) ( $by_id['magick-ai-abilities/site-summary']['governance_mode'] ?? '' ), 'site-summary is direct read' );

$site_summary = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/site-summary' );
maa_adapter_smoke_assert( 'magick-ai-abilities/site-summary' === (string) ( $site_summary['ability_id'] ?? '' ), 'adapter runs site-summary read ability' );
maa_adapter_smoke_assert( is_array( $site_summary['result'] ?? null ), 'site-summary returns a result object' );

$diagnostics = maa_adapter_smoke_rest( 'GET', '/magick-ai-adapter/v1/wp-diagnostics-summary' );
maa_adapter_smoke_assert( 'magick-ai-abilities/wp-diagnostics-summary' === (string) ( $diagnostics['ability_id'] ?? '' ), 'adapter runs diagnostics read ability' );
maa_adapter_smoke_assert( is_array( $diagnostics['result'] ?? null ), 'diagnostics returns a result object' );

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

echo "magick-ai-adapter WordPress smoke: ok\n";
