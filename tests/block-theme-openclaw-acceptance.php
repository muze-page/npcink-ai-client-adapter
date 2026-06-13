<?php
/**
 * Focused block theme OpenClaw acceptance harness.
 *
 * This script simulates the Adapter-only calls an OpenClaw client should make
 * for natural-language block-theme checks. It is intentionally narrower than
 * the full WordPress smoke suite and writes only a local report artifact.
 *
 * @package NpcinkOpenClawAdapter
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress is not loaded.\n" );
	exit( 1 );
}

/**
 * Adds one acceptance assertion.
 *
 * @param array<int,array<string,mixed>> $assertions Assertions.
 * @param bool                           $condition Condition.
 * @param string                         $message Message.
 * @return void
 */
function maa_adapter_btoa_assert( array &$assertions, bool $condition, string $message ): void {
	$assertions[] = array(
		'status'  => $condition ? 'pass' : 'fail',
		'message' => $message,
	);

	echo '[' . ( $condition ? 'ok' : 'fail' ) . '] ' . $message . "\n";
}

/**
 * Dispatches an Adapter REST request and returns status/data.
 *
 * @param string              $method HTTP method.
 * @param string              $route REST route.
 * @param array<string,mixed> $params Params.
 * @return array{status:int,data:mixed}
 */
function maa_adapter_btoa_rest_result( string $method, string $route, array $params = array() ): array {
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
 * Dispatches an Adapter REST request and expects 2xx.
 *
 * @param string              $method HTTP method.
 * @param string              $route REST route.
 * @param array<string,mixed> $params Params.
 * @param array<int,array<string,mixed>> $assertions Assertions.
 * @return array<string,mixed>
 */
function maa_adapter_btoa_rest( string $method, string $route, array $params, array &$assertions ): array {
	$result = maa_adapter_btoa_rest_result( $method, $route, $params );
	maa_adapter_btoa_assert( $assertions, $result['status'] >= 200 && $result['status'] < 300, $method . ' ' . $route . ' returned 2xx' );

	return is_array( $result['data'] ) ? $result['data'] : array();
}

/**
 * Runs a direct read ability through Adapter.
 *
 * @param string              $ability_id Ability id.
 * @param array<string,mixed> $input Input.
 * @param array<int,array<string,mixed>> $assertions Assertions.
 * @return array<string,mixed>
 */
function maa_adapter_btoa_run_read_ability( string $ability_id, array $input, array &$assertions ): array {
	return maa_adapter_btoa_rest(
		'POST',
		'/npcink-openclaw-adapter/v1/run-read-ability',
		array(
			'ability_id' => $ability_id,
			'input'      => $input,
		),
		$assertions
	);
}

/**
 * Extracts a read ability data payload from an Adapter response.
 *
 * @param array<string,mixed> $response Adapter response.
 * @return array<string,mixed>
 */
function maa_adapter_btoa_ability_data( array $response ): array {
	if ( is_array( $response['result']['data'] ?? null ) ) {
		return $response['result']['data'];
	}
	if ( is_array( $response['result'] ?? null ) ) {
		return $response['result'];
	}
	if ( is_array( $response['data']['result'] ?? null ) ) {
		return $response['data']['result'];
	}
	if ( is_array( $response['data'] ?? null ) ) {
		return $response['data'];
	}
	return $response;
}

/**
 * Extracts route data regardless of Toolkit envelope vintage.
 *
 * @param array<string,mixed> $response Adapter response.
 * @return array<string,mixed>
 */
function maa_adapter_btoa_route_data( array $response ): array {
	$data = maa_adapter_btoa_ability_data( $response );
	if ( is_array( $data['route'] ?? null ) ) {
		return $data['route'];
	}
	return $data;
}

/**
 * Returns a compact summary of a template block read.
 *
 * @param array<string,mixed> $data Ability data.
 * @return array<string,mixed>
 */
function maa_adapter_btoa_template_summary( array $data ): array {
	return array(
		'slug'        => (string) ( $data['slug'] ?? '' ),
		'source'      => (string) ( $data['source'] ?? '' ),
		'post_id'     => absint( $data['post_id'] ?? 0 ),
		'block_count' => absint( $data['block_count'] ?? count( (array) ( $data['blocks'] ?? array() ) ) ),
	);
}

/**
 * Reads one template by slug.
 *
 * @param string $slug Template slug.
 * @param array<int,array<string,mixed>> $assertions Assertions.
 * @return array<string,mixed>
 */
function maa_adapter_btoa_read_template( string $slug, array &$assertions ): array {
	$result = maa_adapter_btoa_rest_result(
		'POST',
		'/npcink-openclaw-adapter/v1/run-read-ability',
		array(
			'ability_id' => 'npcink-abilities-toolkit/get-template-blocks',
			'input'      => array( 'slug' => $slug ),
		)
	);

	if ( $result['status'] < 200 || $result['status'] >= 300 ) {
		$data = is_array( $result['data'] ) ? $result['data'] : array();
		return array(
			'requested_target' => $slug,
			'success'          => false,
			'status'           => (int) $result['status'],
			'code'             => (string) ( $data['code'] ?? '' ),
			'message'          => (string) ( $data['message'] ?? '' ),
		);
	}

	$data = maa_adapter_btoa_ability_data( is_array( $result['data'] ) ? $result['data'] : array() );
	return array(
		'requested_target' => $slug,
		'success'          => true,
		'status'           => (int) $result['status'],
		'data'             => $data,
		'summary'          => maa_adapter_btoa_template_summary( $data ),
	);
}

/**
 * Runs composition contract inspection on blocks.
 *
 * @param string                   $slug Template slug.
 * @param array<int,array<mixed>>  $blocks Blocks.
 * @param array<int,array<string,mixed>> $assertions Assertions.
 * @return array<string,mixed>
 */
function maa_adapter_btoa_inspect_template_blocks( string $slug, array $blocks, array &$assertions ): array {
	$response = maa_adapter_btoa_run_read_ability(
		'npcink-abilities-toolkit/inspect-gutenberg-composition-contract',
		array(
			'surface_kind'      => 'site_editor_template',
			'post_type'         => 'wp_template',
			'slug'              => $slug,
			'placement_check'   => 'breadcrumbs',
			'show_on_home_page' => false,
			'blocks'            => $blocks,
		),
		$assertions
	);

	return maa_adapter_btoa_ability_data( $response );
}

/**
 * Returns whether a route result is fail-closed.
 *
 * @param array<string,mixed> $route Route data.
 * @return bool
 */
function maa_adapter_btoa_route_is_fail_closed( array $route ): bool {
	return 'unsupported' === (string) ( $route['route'] ?? '' )
		&& false === (bool) ( $route['supported'] ?? true )
		&& true === (bool) ( $route['needs_clarification'] ?? false )
		&& '' === (string) ( $route['plan_ability_id'] ?? '' );
}

/**
 * Builds the local report path.
 *
 * @return string
 */
function maa_adapter_btoa_report_path(): string {
	$path = trim( (string) getenv( 'MAA_ADAPTER_BLOCK_THEME_OPENCLAW_ACCEPTANCE_OUT' ) );
	if ( '' !== $path ) {
		return $path;
	}

	return dirname( __DIR__ ) . '/build/block-theme-openclaw-acceptance/report.json';
}

/**
 * Returns whether future-facing product gaps should fail this run.
 *
 * @return bool
 */
function maa_adapter_btoa_strict_future_gaps(): bool {
	return '1' === (string) getenv( 'MAA_ADAPTER_BLOCK_THEME_OPENCLAW_ACCEPTANCE_STRICT_FUTURE' );
}

$assertions = array();
$product_gaps = array();
$report     = array(
	'artifact_type' => 'block_theme_openclaw_acceptance_report',
	'version'       => 1,
	'scenarios'     => array(),
	'assertions'    => array(),
);

$health       = maa_adapter_btoa_rest( 'GET', '/npcink-openclaw-adapter/v1/health', array(), $assertions );
$help         = maa_adapter_btoa_rest( 'GET', '/npcink-openclaw-adapter/v1/help', array(), $assertions );
$capabilities = maa_adapter_btoa_rest( 'GET', '/npcink-openclaw-adapter/v1/capabilities', array(), $assertions );

maa_adapter_btoa_assert( $assertions, true === (bool) ( $health['dependencies_ready'] ?? false ), 'Adapter dependencies are ready' );
maa_adapter_btoa_assert( $assertions, isset( $help['openclaw_recipes']['block_theme_site_plan'] ), 'Help exposes block_theme_site_plan recipe' );
maa_adapter_btoa_assert( $assertions, isset( $help['openclaw_recipes']['block_theme_site_plan']['contract_inspection_ability_id'] ), 'Help exposes contract inspection ability' );
maa_adapter_btoa_assert( $assertions, ! empty( $capabilities['items'] ?? array() ), 'Capabilities are available' );

$report['startup'] = array(
	'health'       => array(
		'dependencies_ready' => (bool) ( $health['dependencies_ready'] ?? false ),
		'core_capabilities'  => (bool) ( $health['core_capabilities'] ?? false ),
		'abilities_catalog'  => (bool) ( $health['abilities_catalog'] ?? false ),
		'abilities_toolkit'  => (bool) ( $health['abilities_toolkit'] ?? false ),
	),
	'capabilities' => array(
		'count' => absint( $capabilities['count'] ?? count( (array) ( $capabilities['items'] ?? array() ) ) ),
	),
);

// Scenario 1: natural-language check without hints, including home fallback.
$prompt = '帮我看看文章页、页面和首页的面包屑是不是正常，不正常就处理一下。';
$route_input = array( 'prompt' => $prompt );
$route_response = maa_adapter_btoa_run_read_ability( 'npcink-abilities-toolkit/route-content-intent', $route_input, $assertions );
$route = maa_adapter_btoa_route_data( $route_response );
maa_adapter_btoa_assert( $assertions, array( 'prompt' ) === array_keys( $route_input ), 'Natural-language route input contains only prompt' );
maa_adapter_btoa_assert( $assertions, 'block_theme_site_plan' === (string) ( $route['route'] ?? '' ), 'Natural-language breadcrumb check routes to block_theme_site_plan' );
maa_adapter_btoa_assert( $assertions, true === (bool) ( $route['supported'] ?? false ), 'Natural-language breadcrumb check is supported' );

$context_response = maa_adapter_btoa_run_read_ability( 'npcink-abilities-toolkit/get-block-theme-context', array(), $assertions );
$context = maa_adapter_btoa_ability_data( $context_response );
maa_adapter_btoa_assert( $assertions, true === (bool) ( $context['is_block_theme'] ?? false ), 'Active theme is a block theme' );

$readbacks   = array();
$inspections = array();
foreach ( array( 'single', 'page' ) as $slug ) {
	$readback = maa_adapter_btoa_read_template( $slug, $assertions );
	$readbacks[ $slug ] = $readback;
	maa_adapter_btoa_assert( $assertions, true === (bool) ( $readback['success'] ?? false ), $slug . ' template readback succeeds' );
	if ( ! empty( $readback['success'] ) ) {
		$blocks = is_array( $readback['data']['blocks'] ?? null ) ? $readback['data']['blocks'] : array();
		$inspection = maa_adapter_btoa_inspect_template_blocks( $slug, $blocks, $assertions );
		$inspections[ $slug ] = $inspection;
		maa_adapter_btoa_assert( $assertions, 'pass' === (string) ( $inspection['contract_status'] ?? '' ), $slug . ' composition contract passes' );
	}
}

$home_readbacks = array();
$resolved_home  = null;
foreach ( array( 'front-page', 'home', 'index' ) as $slug ) {
	$readback = maa_adapter_btoa_read_template( $slug, $assertions );
	$home_readbacks[ $slug ] = $readback;
	if ( null === $resolved_home && ! empty( $readback['success'] ) ) {
		$resolved_home = $slug;
	}
}
maa_adapter_btoa_assert( $assertions, null !== $resolved_home, 'Homepage fallback resolves one of front-page, home, or index' );
if ( null !== $resolved_home ) {
	$blocks = is_array( $home_readbacks[ $resolved_home ]['data']['blocks'] ?? null ) ? $home_readbacks[ $resolved_home ]['data']['blocks'] : array();
	$inspection = maa_adapter_btoa_inspect_template_blocks( $resolved_home, $blocks, $assertions );
	$inspections[ $resolved_home ] = $inspection;
	maa_adapter_btoa_assert( $assertions, 'pass' === (string) ( $inspection['contract_status'] ?? '' ), 'Homepage fallback template composition contract passes' );
}

$report['scenarios']['no_hint_breadcrumb_check'] = array(
	'prompt'                 => $prompt,
	'route_input'            => $route_input,
	'route'                  => $route,
	'theme_context_summary'  => array(
		'active_theme'  => is_array( $context['active_theme'] ?? null ) ? $context['active_theme'] : array(),
		'is_block_theme' => (bool) ( $context['is_block_theme'] ?? false ),
	),
	'template_readbacks'     => $readbacks,
	'home_fallback_readbacks' => $home_readbacks,
	'resolved_home_template' => $resolved_home,
	'contract_inspections'   => $inspections,
	'created_proposal'       => false,
);

// Scenario 2: controlled broken breadcrumb placement fixture, no site mutation.
$broken_blocks = array(
	array(
		'blockName'   => 'core/group',
		'attrs'       => array( 'className' => 'openclaw-breadcrumbs' ),
		'innerBlocks' => array(),
	),
	array(
		'blockName'   => 'core/template-part',
		'attrs'       => array( 'slug' => 'header' ),
		'innerBlocks' => array(),
	),
	array(
		'blockName'   => 'core/group',
		'attrs'       => array( 'tagName' => 'main' ),
		'innerBlocks' => array(
			array(
				'blockName'   => 'core/post-title',
				'attrs'       => array(),
				'innerBlocks' => array(),
			),
			array(
				'blockName'   => 'core/post-content',
				'attrs'       => array(),
				'innerBlocks' => array(),
			),
		),
	),
);
$broken_inspection = maa_adapter_btoa_inspect_template_blocks( 'single', $broken_blocks, $assertions );
maa_adapter_btoa_assert( $assertions, 'needs_revision' === (string) ( $broken_inspection['contract_status'] ?? '' ), 'Broken breadcrumb fixture is detected as needs_revision' );
maa_adapter_btoa_assert( $assertions, in_array( 'template_placement_contract_failed', (array) ( $broken_inspection['violation_codes'] ?? array() ), true ), 'Broken breadcrumb fixture reports template placement violation' );
maa_adapter_btoa_assert( $assertions, in_array( 'build_block_theme_site_plan', (array) ( $broken_inspection['recommended_next_actions'] ?? array() ), true ), 'Broken breadcrumb fixture recommends block theme plan' );
$report['scenarios']['broken_breadcrumb_detection'] = array(
	'fixture_source' => 'blocks_input_no_site_mutation',
	'inspection'     => $broken_inspection,
	'created_proposal' => false,
);

// Scenario 3: future layout customization gaps are reported before proposal.
$layout_prompts = array(
	'article_layout'  => '帮我把文章页改成更专业的布局：顶部有面包屑，标题下面显示作者和日期，下面是特色图和正文，底部放相关文章。',
	'homepage_layout' => '帮我自定义首页：顶部放一个大标题和介绍，下面展示最新文章、分类入口和一个行动按钮。',
);
$layout_results = array();
foreach ( $layout_prompts as $key => $layout_prompt ) {
	$layout_input = array( 'prompt' => $layout_prompt );
	$layout_response = maa_adapter_btoa_run_read_ability( 'npcink-abilities-toolkit/route-content-intent', $layout_input, $assertions );
	$layout_route = maa_adapter_btoa_route_data( $layout_response );
	$layout_results[ $key ] = array(
		'input' => $layout_input,
		'route' => $layout_route,
		'fail_closed' => maa_adapter_btoa_route_is_fail_closed( $layout_route ),
	);
	maa_adapter_btoa_assert( $assertions, array( 'prompt' ) === array_keys( $layout_input ), $key . ' route input contains only prompt' );
	if ( ! maa_adapter_btoa_route_is_fail_closed( $layout_route ) ) {
		$product_gaps[] = array(
			'scenario'            => 'unsupported_layout_boundary',
			'case'                => $key,
			'expected'            => 'fail_closed_until_template_layout_plan_is_supported',
			'actual_route'        => (string) ( $layout_route['route'] ?? '' ),
			'actual_plan_ability' => (string) ( $layout_route['plan_ability_id'] ?? '' ),
			'next_owner'          => 'npcink-abilities-toolkit',
			'reason'              => 'natural_language_template_layout_request_was_routed_to_a_narrower_supported_intent',
		);
		echo '[gap] ' . $key . " unsupported layout customization did not fail closed\n";
	}
}
$report['scenarios']['unsupported_layout_boundary'] = array(
	'prompts'          => $layout_prompts,
	'results'          => $layout_results,
	'created_proposal' => false,
);

$report['assertions'] = $assertions;
$failed = array_values(
	array_filter(
		$assertions,
		static function ( array $assertion ): bool {
			return 'fail' === (string) ( $assertion['status'] ?? '' );
		}
	)
);
$strict_future_gaps = maa_adapter_btoa_strict_future_gaps();
$strict_gap_failures = $strict_future_gaps ? $product_gaps : array();
$report['final_decision'] = array(
	'overall_result' => empty( $failed ) && empty( $strict_gap_failures ) ? 'pass' : 'fail',
	'failed_count'   => count( $failed ),
	'product_gap_count' => count( $product_gaps ),
	'strict_future_gaps' => $strict_future_gaps,
	'created_proposal' => false,
	'executed_write' => false,
);
$report['product_gaps'] = $product_gaps;

$report_path = maa_adapter_btoa_report_path();
wp_mkdir_p( dirname( $report_path ) );
file_put_contents( $report_path, wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
echo 'Wrote block theme OpenClaw acceptance report: ' . $report_path . "\n";

if ( ! empty( $failed ) || ! empty( $strict_gap_failures ) ) {
	exit( 1 );
}
