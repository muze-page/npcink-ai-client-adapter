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

/**
 * Returns whether the governed execution scenario should run.
 *
 * @return bool
 */
function maa_adapter_btoa_commit_enabled(): bool {
	$value = (string) getenv( 'MAA_ADAPTER_BLOCK_THEME_OPENCLAW_ACCEPTANCE_COMMIT' );
	return '1' === $value || 'true' === strtolower( $value );
}

/**
 * Collects recursive block names from a block tree.
 *
 * @param array<int,array<string,mixed>> $blocks Blocks.
 * @return string[]
 */
function maa_adapter_btoa_collect_block_names( array $blocks ): array {
	$names = array();
	$walk  = static function ( array $items ) use ( &$walk, &$names ): void {
		foreach ( $items as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$name = (string) ( $block['blockName'] ?? '' );
			if ( '' !== $name ) {
				$names[] = $name;
			}
			$walk( is_array( $block['innerBlocks'] ?? null ) ? array_values( $block['innerBlocks'] ) : array() );
		}
	};
	$walk( $blocks );
	return array_values( array_unique( array_filter( $names ) ) );
}

/**
 * Returns whether a block tree includes a class name.
 *
 * @param array<int,array<string,mixed>> $blocks Blocks.
 * @param string                         $class_name Class name.
 * @return bool
 */
function maa_adapter_btoa_blocks_have_class( array $blocks, string $class_name ): bool {
	$found = false;
	$walk  = static function ( array $items ) use ( &$walk, &$found, $class_name ): void {
		foreach ( $items as $block ) {
			if ( ! is_array( $block ) || $found ) {
				continue;
			}
			$classes = preg_split( '/\s+/', (string) ( $block['attrs']['className'] ?? '' ) );
			if ( in_array( $class_name, is_array( $classes ) ? $classes : array(), true ) ) {
				$found = true;
				return;
			}
			$walk( is_array( $block['innerBlocks'] ?? null ) ? array_values( $block['innerBlocks'] ) : array() );
		}
	};
	$walk( $blocks );
	return $found;
}

/**
 * Returns whether a block tree includes a block with an attr value.
 *
 * @param array<int,array<string,mixed>> $blocks Blocks.
 * @param string                         $block_name Block name.
 * @param string                         $attr Attr name.
 * @param string                         $value Expected value.
 * @return bool
 */
function maa_adapter_btoa_blocks_have_attr( array $blocks, string $block_name, string $attr, string $value ): bool {
	$found = false;
	$walk  = static function ( array $items ) use ( &$walk, &$found, $block_name, $attr, $value ): void {
		foreach ( $items as $block ) {
			if ( ! is_array( $block ) || $found ) {
				continue;
			}
			if ( $block_name === (string) ( $block['blockName'] ?? '' ) && $value === (string) ( $block['attrs'][ $attr ] ?? '' ) ) {
				$found = true;
				return;
			}
			$walk( is_array( $block['innerBlocks'] ?? null ) ? array_values( $block['innerBlocks'] ) : array() );
		}
	};
	$walk( $blocks );
	return $found;
}

/**
 * Returns a local wp_template backup by post id.
 *
 * This is local acceptance cleanup only; the tested flow itself still uses
 * Adapter proposal creation and approve-and-execute.
 *
 * @param int $post_id Template post id.
 * @return array<string,mixed>
 */
function maa_adapter_btoa_template_backup( int $post_id ): array {
	$post = get_post( $post_id );
	return array(
		'post_id'      => $post_id,
		'post_content' => $post instanceof WP_Post ? (string) $post->post_content : '',
	);
}

/**
 * Restores a local wp_template backup.
 *
 * @param array<string,mixed> $backup Backup.
 * @return void
 */
function maa_adapter_btoa_restore_template_backup( array $backup ): void {
	$post_id = absint( $backup['post_id'] ?? 0 );
	if ( $post_id <= 0 ) {
		return;
	}
	wp_update_post(
		array(
			'ID'           => $post_id,
			'post_content' => (string) ( $backup['post_content'] ?? '' ),
		)
	);
}

/**
 * Applies a minimal stale article template fixture for governed repair testing.
 *
 * @param int $post_id Template post id.
 * @return void
 */
function maa_adapter_btoa_apply_stale_article_template_fixture( int $post_id ): void {
	if ( $post_id <= 0 || ! function_exists( 'serialize_blocks' ) ) {
		return;
	}

	$blocks = array(
		array(
			'blockName'    => 'core/template-part',
			'attrs'        => array( 'slug' => 'header', 'theme' => 'twentytwentyfive' ),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		),
		array(
			'blockName'    => 'core/group',
			'attrs'        => array( 'tagName' => 'main' ),
			'innerBlocks'  => array(
				array(
					'blockName'    => 'core/post-title',
					'attrs'        => array( 'level' => 1 ),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				),
				array(
					'blockName'    => 'core/post-content',
					'attrs'        => array(),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				),
			),
			'innerHTML'    => '',
			'innerContent' => array(),
		),
		array(
			'blockName'    => 'core/template-part',
			'attrs'        => array( 'slug' => 'footer', 'theme' => 'twentytwentyfive' ),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		),
	);

	wp_update_post(
		array(
			'ID'           => $post_id,
			'post_content' => serialize_blocks( $blocks ),
		)
	);
}

/**
 * Runs a governed template layout execution scenario with local restore.
 *
 * @param array<string,mixed>          $config Scenario config.
 * @param array<int,array<string,mixed>> $assertions Assertions.
 * @return array<string,mixed>
 */
function maa_adapter_btoa_run_template_layout_execution_scenario( array $config, array &$assertions ): array {
	$key              = sanitize_key( (string) ( $config['key'] ?? 'template_layout' ) );
	$prompt           = (string) ( $config['prompt'] ?? '' );
	$target_slug      = sanitize_key( (string) ( $config['target_slug'] ?? '' ) );
	$expected_profile = sanitize_key( (string) ( $config['layout_profile'] ?? '' ) );
	$profile_version  = (string) ( $config['profile_version'] ?? '' );
	$required_blocks  = is_array( $config['required_blocks'] ?? null ) ? $config['required_blocks'] : array();
	$required_classes = is_array( $config['required_classes'] ?? null ) ? $config['required_classes'] : array();
	$required_attrs   = is_array( $config['required_attrs'] ?? null ) ? $config['required_attrs'] : array();

	$report = array(
		'enabled'          => true,
		'prompt'           => $prompt,
		'target_slug'      => $target_slug,
		'layout_profile'   => $expected_profile,
		'created_proposal' => false,
		'executed_write'   => false,
	);

	$route_response = maa_adapter_btoa_run_read_ability(
		'npcink-abilities-toolkit/route-content-intent',
		array( 'prompt' => $prompt ),
		$assertions
	);
	$route = maa_adapter_btoa_route_data( $route_response );
	$plan_input = is_array( $route['recommended_plan_input'] ?? null ) ? $route['recommended_plan_input'] : array();
	if ( is_array( $config['plan_input_overrides'] ?? null ) ) {
		$plan_input = array_merge( $plan_input, $config['plan_input_overrides'] );
	}
	$report['route'] = $route;

	maa_adapter_btoa_assert( $assertions, 'block_theme_site_plan' === (string) ( $route['route'] ?? '' ), $key . ' routes to block_theme_site_plan' );
	maa_adapter_btoa_assert( $assertions, 'site_template_layout' === (string) ( $route['route_key'] ?? '' ), $key . ' uses site_template_layout route key' );
	maa_adapter_btoa_assert( $assertions, 'customize_template_layout' === (string) ( $plan_input['intent'] ?? '' ), $key . ' recommends layout customization' );
	maa_adapter_btoa_assert( $assertions, $expected_profile === sanitize_key( (string) ( $plan_input['layout_profile'] ?? '' ) ), $key . ' recommends expected profile' );
	maa_adapter_btoa_assert( $assertions, in_array( $target_slug, array_map( 'sanitize_key', (array) ( $plan_input['target_templates'] ?? array() ) ), true ), $key . ' recommends expected template target' );

	$context_response = maa_adapter_btoa_run_read_ability( 'npcink-abilities-toolkit/get-block-theme-context', array(), $assertions );
	$context = maa_adapter_btoa_ability_data( $context_response );
	$target_override = is_array( $context['existing_overrides'][ $target_slug ] ?? null ) ? $context['existing_overrides'][ $target_slug ] : array();
	$target_post_id  = absint( $target_override['post_id'] ?? 0 );
	maa_adapter_btoa_assert( $assertions, $target_post_id > 0, $key . ' finds an existing template override' );

	$template_backup = array();
	if ( $target_post_id > 0 ) {
		$template_backup = maa_adapter_btoa_template_backup( $target_post_id );
		maa_adapter_btoa_apply_stale_article_template_fixture( $target_post_id );
	}

	$plan_response = maa_adapter_btoa_run_read_ability(
		'npcink-abilities-toolkit/build-block-theme-site-plan',
		$plan_input,
		$assertions
	);
	$plan = maa_adapter_btoa_ability_data( $plan_response );
	$profile_row = is_array( $plan['template_layout_contract']['profiles'][0] ?? null ) ? $plan['template_layout_contract']['profiles'][0] : array();
	$quality_codes = (array) ( $plan['preview'][0]['block_editor_quality_gate']['finding_codes'] ?? array() );
	$report['profile_version'] = (string) ( $profile_row['profile_version'] ?? '' );
	$report['quality_finding_codes'] = $quality_codes;

	maa_adapter_btoa_assert( $assertions, $profile_version === (string) ( $profile_row['profile_version'] ?? '' ), $key . ' plan uses expected profile version' );
	maa_adapter_btoa_assert( $assertions, 'replace_template_layout_with_preserved_template_parts' === (string) ( $profile_row['operation'] ?? '' ), $key . ' plan declares the Core intake operation' );
	maa_adapter_btoa_assert( $assertions, ! empty( $plan['write_actions'] ?? array() ), $key . ' plan produces write actions for stale fixture' );

	$proposal_response = maa_adapter_btoa_rest(
		'POST',
		'/npcink-openclaw-adapter/v1/proposals/from-plan',
		array(
			'plan_ability_id' => 'npcink-abilities-toolkit/build-block-theme-site-plan',
			'plan'            => $plan,
			'plan_input'      => $plan_input,
		),
		$assertions
	);
	$proposal_id = (string) ( $proposal_response['proposal_id'] ?? ( $proposal_response['proposals'][0]['proposal_id'] ?? '' ) );
	$report['proposal_id'] = $proposal_id;
	$report['created_proposal'] = '' !== $proposal_id;
	maa_adapter_btoa_assert( $assertions, '' !== $proposal_id, $key . ' proposal is created' );
	maa_adapter_btoa_assert( $assertions, 'pending' === (string) ( $proposal_response['status'] ?? ( $proposal_response['proposals'][0]['status'] ?? '' ) ), $key . ' proposal starts pending' );

	$execute_response = array();
	if ( '' !== $proposal_id ) {
		$execute_response = maa_adapter_btoa_rest(
			'POST',
			'/npcink-openclaw-adapter/v1/proposals/' . rawurlencode( $proposal_id ) . '/approve-and-execute',
			array( 'intent' => 'commit' ),
			$assertions
		);
		$report['executed_write'] = true;
		$report['execution_record_status'] = (string) ( $execute_response['execution_record']['status'] ?? ( $execute_response['execution']['status'] ?? '' ) );
		maa_adapter_btoa_assert( $assertions, is_array( $execute_response['execution_record'] ?? null ) || is_array( $execute_response['execution'] ?? null ), $key . ' proposal returns execution evidence' );
		maa_adapter_btoa_assert( $assertions, 'succeeded' === $report['execution_record_status'], $key . ' execution succeeds' );
	}

	$readback = maa_adapter_btoa_read_template( $target_slug, $assertions );
	$blocks = is_array( $readback['data']['blocks'] ?? null ) ? $readback['data']['blocks'] : array();
	$block_names = maa_adapter_btoa_collect_block_names( $blocks );
	foreach ( $required_blocks as $required_block ) {
		maa_adapter_btoa_assert( $assertions, in_array( (string) $required_block, $block_names, true ), $key . ' readback includes ' . (string) $required_block );
	}
	foreach ( array( 'core/html', 'core/freeform' ) as $forbidden_block ) {
		maa_adapter_btoa_assert( $assertions, ! in_array( $forbidden_block, $block_names, true ), $key . ' readback excludes ' . $forbidden_block );
	}
	foreach ( $required_classes as $required_class ) {
		maa_adapter_btoa_assert( $assertions, maa_adapter_btoa_blocks_have_class( $blocks, (string) $required_class ), $key . ' readback includes ' . (string) $required_class );
	}
	foreach ( $required_attrs as $attr_check ) {
		$block_name = (string) ( $attr_check['block'] ?? '' );
		$attr       = (string) ( $attr_check['attr'] ?? '' );
		$value      = (string) ( $attr_check['value'] ?? '' );
		$label      = (string) ( $attr_check['label'] ?? ( $block_name . ' ' . $attr . '=' . $value ) );
		maa_adapter_btoa_assert( $assertions, maa_adapter_btoa_blocks_have_attr( $blocks, $block_name, $attr, $value ), $key . ' readback includes ' . $label );
	}

	if ( ! empty( $template_backup ) ) {
		maa_adapter_btoa_restore_template_backup( $template_backup );
	}

	$report['readback_summary']  = maa_adapter_btoa_template_summary( is_array( $readback['data'] ?? null ) ? $readback['data'] : array() );
	$report['restored_template'] = ! empty( $template_backup );
	return $report;
}

$assertions = array();
$product_gaps = array();
$report     = array(
	'artifact_type' => 'block_theme_openclaw_acceptance_report',
	'version'       => 1,
	'scenarios'     => array(),
	'assertions'    => array(),
);
$created_proposal = false;
$executed_write   = false;
$template_backup  = array();

$health       = maa_adapter_btoa_rest( 'GET', '/npcink-openclaw-adapter/v1/health', array(), $assertions );
$help         = maa_adapter_btoa_rest( 'GET', '/npcink-openclaw-adapter/v1/help', array(), $assertions );
$capabilities = maa_adapter_btoa_rest( 'GET', '/npcink-openclaw-adapter/v1/capabilities', array(), $assertions );

maa_adapter_btoa_assert( $assertions, true === (bool) ( $health['dependencies_ready'] ?? false ), 'Adapter dependencies are ready' );
maa_adapter_btoa_assert( $assertions, ! isset( $help['openclaw_recipes'] ), 'Help does not expose Adapter-owned recipe catalog' );
maa_adapter_btoa_assert( $assertions, in_array( 'npcink-abilities-toolkit/build-block-theme-site-plan', (array) ( $help['supported_plan_ability_ids'] ?? array() ), true ), 'Help exposes block theme site plan as supported plan ability' );
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

// Scenario 3: bounded layout customization routes before proposal.
$layout_prompts = array(
	'article_layout'  => '帮我把文章页改成更专业的布局：顶部有面包屑，标题下面显示作者和日期，下面是特色图和正文，底部放相关文章。',
	'homepage_layout' => '帮我自定义首页：顶部放一个大标题和介绍，下面展示最新文章、分类入口和一个行动按钮。',
);
$expected_layout_profiles = array(
	'article_layout'  => 'article_standard',
	'homepage_layout' => 'homepage_landing',
);
$expected_layout_targets = array(
	'article_layout'  => 'single',
	'homepage_layout' => 'front-page',
);
$layout_results = array();
foreach ( $layout_prompts as $key => $layout_prompt ) {
	$layout_input = array( 'prompt' => $layout_prompt );
	$layout_response = maa_adapter_btoa_run_read_ability( 'npcink-abilities-toolkit/route-content-intent', $layout_input, $assertions );
	$layout_route = maa_adapter_btoa_route_data( $layout_response );
	$layout_plan_input = is_array( $layout_route['recommended_plan_input'] ?? null ) ? $layout_route['recommended_plan_input'] : array();
	$layout_results[ $key ] = array(
		'input'                  => $layout_input,
		'route'                  => $layout_route,
		'recommended_plan_input' => $layout_plan_input,
		'fail_closed'            => maa_adapter_btoa_route_is_fail_closed( $layout_route ),
	);
	maa_adapter_btoa_assert( $assertions, array( 'prompt' ) === array_keys( $layout_input ), $key . ' route input contains only prompt' );
	maa_adapter_btoa_assert( $assertions, ! maa_adapter_btoa_route_is_fail_closed( $layout_route ), $key . ' template layout request routes to a supported bounded plan' );
	maa_adapter_btoa_assert( $assertions, 'block_theme_site_plan' === (string) ( $layout_route['route'] ?? '' ), $key . ' template layout routes to block_theme_site_plan' );
	maa_adapter_btoa_assert( $assertions, 'site_template_layout' === (string) ( $layout_route['route_key'] ?? '' ), $key . ' template layout uses the site_template_layout route key' );
	maa_adapter_btoa_assert( $assertions, 'npcink-abilities-toolkit/build-block-theme-site-plan' === (string) ( $layout_route['plan_ability_id'] ?? '' ), $key . ' template layout selects the block theme plan ability' );
	maa_adapter_btoa_assert( $assertions, 'customize_template_layout' === (string) ( $layout_plan_input['intent'] ?? '' ), $key . ' template layout recommends customize_template_layout' );
	maa_adapter_btoa_assert( $assertions, ( $expected_layout_profiles[ $key ] ?? '' ) === (string) ( $layout_plan_input['layout_profile'] ?? '' ), $key . ' template layout recommends the expected layout profile' );
	maa_adapter_btoa_assert( $assertions, in_array( $expected_layout_targets[ $key ] ?? '', (array) ( $layout_plan_input['target_templates'] ?? array() ), true ), $key . ' template layout recommends the expected template target' );
}
$report['scenarios']['template_layout_route'] = array(
	'prompts'          => $layout_prompts,
	'results'          => $layout_results,
	'created_proposal' => false,
);

// Scenario 4: opt-in governed article template proposal, execution, readback, and local restore.
$article_execution_enabled = maa_adapter_btoa_commit_enabled();
$article_execution_report  = array(
	'enabled'          => $article_execution_enabled,
	'created_proposal' => false,
	'executed_write'   => false,
);
if ( $article_execution_enabled ) {
	$article_prompt = '帮我把文章页模板整理成标准文章页布局：面包屑在标题区域上方，标题下面显示作者、日期和分类；保留特色图、正文、标签、上一篇下一篇、评论和相关文章。';
	$article_route_response = maa_adapter_btoa_run_read_ability(
		'npcink-abilities-toolkit/route-content-intent',
		array( 'prompt' => $article_prompt ),
		$assertions
	);
	$article_route = maa_adapter_btoa_route_data( $article_route_response );
	$article_plan_input = is_array( $article_route['recommended_plan_input'] ?? null ) ? $article_route['recommended_plan_input'] : array();
	maa_adapter_btoa_assert( $assertions, 'block_theme_site_plan' === (string) ( $article_route['route'] ?? '' ), 'article standard execution routes to block_theme_site_plan' );
	maa_adapter_btoa_assert( $assertions, 'site_template_layout' === (string) ( $article_route['route_key'] ?? '' ), 'article standard execution uses site_template_layout route key' );
	maa_adapter_btoa_assert( $assertions, 'customize_template_layout' === (string) ( $article_plan_input['intent'] ?? '' ), 'article standard execution recommends layout customization' );
	maa_adapter_btoa_assert( $assertions, 'article_standard' === (string) ( $article_plan_input['layout_profile'] ?? '' ), 'article standard execution recommends article_standard profile' );

	$article_context_response = maa_adapter_btoa_run_read_ability( 'npcink-abilities-toolkit/get-block-theme-context', array(), $assertions );
	$article_context = maa_adapter_btoa_ability_data( $article_context_response );
	$single_override = is_array( $article_context['existing_overrides']['single'] ?? null ) ? $article_context['existing_overrides']['single'] : array();
	$single_post_id  = absint( $single_override['post_id'] ?? 0 );
	maa_adapter_btoa_assert( $assertions, $single_post_id > 0, 'article standard execution finds an existing single template override' );
	if ( $single_post_id > 0 ) {
		$template_backup = maa_adapter_btoa_template_backup( $single_post_id );
		maa_adapter_btoa_apply_stale_article_template_fixture( $single_post_id );
	}

	$article_plan_response = maa_adapter_btoa_run_read_ability(
		'npcink-abilities-toolkit/build-block-theme-site-plan',
		$article_plan_input,
		$assertions
	);
	$article_plan_envelope = is_array( $article_plan_response['result'] ?? null ) ? $article_plan_response['result'] : array();
	$article_plan          = maa_adapter_btoa_ability_data( $article_plan_response );
	$article_profile_row   = is_array( $article_plan['template_layout_contract']['profiles'][0] ?? null ) ? $article_plan['template_layout_contract']['profiles'][0] : array();
	maa_adapter_btoa_assert( $assertions, 'article_standard@0.4' === (string) ( $article_profile_row['profile_version'] ?? '' ), 'article standard plan uses article_standard@0.4' );
	maa_adapter_btoa_assert( $assertions, 'replace_template_layout_with_preserved_template_parts' === (string) ( $article_profile_row['operation'] ?? '' ), 'article standard plan declares the Core intake operation' );
	maa_adapter_btoa_assert( $assertions, in_array( 'post_navigation', (array) ( $article_profile_row['modules'] ?? array() ), true ), 'article standard plan declares post_navigation module' );
	maa_adapter_btoa_assert( $assertions, in_array( 'comments', (array) ( $article_profile_row['modules'] ?? array() ), true ), 'article standard plan declares comments module' );
	$article_quality_codes = (array) ( $article_plan['preview'][0]['block_editor_quality_gate']['finding_codes'] ?? array() );
	foreach ( array( 'hero_media_missing', 'bento_grid_missing', 'faq_missing', 'final_cta_missing' ) as $landing_only_code ) {
		maa_adapter_btoa_assert( $assertions, ! in_array( $landing_only_code, $article_quality_codes, true ), 'article standard quality gate omits ' . $landing_only_code );
	}

	$proposal_response = maa_adapter_btoa_rest(
		'POST',
		'/npcink-openclaw-adapter/v1/proposals/from-plan',
		array(
			'plan_ability_id' => 'npcink-abilities-toolkit/build-block-theme-site-plan',
			'plan'            => $article_plan,
			'plan_input'      => $article_plan_input,
		),
		$assertions
	);
	$proposal_id = (string) ( $proposal_response['proposal_id'] ?? ( $proposal_response['proposals'][0]['proposal_id'] ?? '' ) );
	$created_proposal = '' !== $proposal_id;
	$execute_response = array();
	maa_adapter_btoa_assert( $assertions, $created_proposal, 'article standard proposal is created' );
	maa_adapter_btoa_assert( $assertions, 'pending' === (string) ( $proposal_response['status'] ?? ( $proposal_response['proposals'][0]['status'] ?? '' ) ), 'article standard proposal starts pending' );

	if ( '' !== $proposal_id ) {
		$execute_response = maa_adapter_btoa_rest(
			'POST',
			'/npcink-openclaw-adapter/v1/proposals/' . rawurlencode( $proposal_id ) . '/approve-and-execute',
			array( 'intent' => 'commit' ),
			$assertions
		);
		$executed_write = true;
		maa_adapter_btoa_assert( $assertions, is_array( $execute_response['execution_record'] ?? null ) || is_array( $execute_response['execution'] ?? null ), 'article standard proposal returns execution evidence' );
		maa_adapter_btoa_assert( $assertions, 'succeeded' === (string) ( $execute_response['execution_record']['status'] ?? ( $execute_response['execution']['status'] ?? '' ) ), 'article standard execution succeeds' );
	}

	$single_after = maa_adapter_btoa_read_template( 'single', $assertions );
	$after_blocks = is_array( $single_after['data']['blocks'] ?? null ) ? $single_after['data']['blocks'] : array();
	$after_block_names = maa_adapter_btoa_collect_block_names( $after_blocks );
	foreach ( array( 'core/template-part', 'core/group', 'core/post-title', 'core/post-author-name', 'core/post-date', 'core/post-terms', 'core/post-featured-image', 'core/post-content', 'core/post-navigation-link', 'core/comments', 'core/latest-posts' ) as $required_block ) {
		maa_adapter_btoa_assert( $assertions, in_array( $required_block, $after_block_names, true ), 'article standard readback includes ' . $required_block );
	}
	foreach ( array( 'core/html', 'core/freeform' ) as $forbidden_block ) {
		maa_adapter_btoa_assert( $assertions, ! in_array( $forbidden_block, $after_block_names, true ), 'article standard readback excludes ' . $forbidden_block );
	}
	maa_adapter_btoa_assert( $assertions, maa_adapter_btoa_blocks_have_class( $after_blocks, 'openclaw-template-title-stack' ), 'article standard readback includes title stack' );
	maa_adapter_btoa_assert( $assertions, maa_adapter_btoa_blocks_have_class( $after_blocks, 'openclaw-template-content-stack' ), 'article standard readback includes content stack' );
	maa_adapter_btoa_assert( $assertions, maa_adapter_btoa_blocks_have_class( $after_blocks, 'openclaw-template-post-navigation' ), 'article standard readback includes post navigation band' );
	maa_adapter_btoa_assert( $assertions, maa_adapter_btoa_blocks_have_class( $after_blocks, 'openclaw-template-related' ), 'article standard readback includes related posts band' );
	maa_adapter_btoa_assert( $assertions, maa_adapter_btoa_blocks_have_attr( $after_blocks, 'core/post-terms', 'term', 'category' ), 'article standard readback includes category terms' );
	maa_adapter_btoa_assert( $assertions, maa_adapter_btoa_blocks_have_attr( $after_blocks, 'core/post-terms', 'term', 'post_tag' ), 'article standard readback includes tag terms' );
	maa_adapter_btoa_assert( $assertions, maa_adapter_btoa_blocks_have_attr( $after_blocks, 'core/post-navigation-link', 'type', 'previous' ), 'article standard readback includes previous post link' );
	maa_adapter_btoa_assert( $assertions, maa_adapter_btoa_blocks_have_attr( $after_blocks, 'core/post-navigation-link', 'type', 'next' ), 'article standard readback includes next post link' );

	if ( ! empty( $template_backup ) ) {
		maa_adapter_btoa_restore_template_backup( $template_backup );
	}

	$article_execution_report = array(
		'enabled'              => true,
		'prompt'               => $article_prompt,
		'route'                => $article_route,
		'profile_version'      => (string) ( $article_profile_row['profile_version'] ?? '' ),
		'quality_finding_codes' => $article_quality_codes,
		'proposal_id'          => $proposal_id,
		'proposal_status_after_execute' => (string) ( $execute_response['status'] ?? ( $execute_response['proposal']['status'] ?? '' ) ),
		'execution_record_status' => (string) ( $execute_response['execution_record']['status'] ?? ( $execute_response['execution']['status'] ?? '' ) ),
		'created_proposal'     => $created_proposal,
		'executed_write'       => $executed_write,
		'readback_summary'     => maa_adapter_btoa_template_summary( is_array( $single_after['data'] ?? null ) ? $single_after['data'] : array() ),
		'restored_template'    => ! empty( $template_backup ),
	);
} else {
	$product_gaps[] = array(
		'code'    => 'article_standard_commit_acceptance_skipped',
		'message' => 'Set MAA_ADAPTER_BLOCK_THEME_OPENCLAW_ACCEPTANCE_COMMIT=1 to run proposal creation, approve-and-execute, readback, and local restore for article_standard@0.4.',
	);
}
$report['scenarios']['article_standard_proposal_execute_readback'] = $article_execution_report;

// Scenario 5: opt-in governed page and homepage template proposal, execution, readback, and local restore.
$additional_layout_execution_reports = array(
	'page_standard'     => array(
		'enabled'          => $article_execution_enabled,
		'created_proposal' => false,
		'executed_write'   => false,
	),
	'homepage_landing' => array(
		'enabled'          => $article_execution_enabled,
		'created_proposal' => false,
		'executed_write'   => false,
	),
);
if ( $article_execution_enabled ) {
	$additional_layout_execution_reports['page_standard'] = maa_adapter_btoa_run_template_layout_execution_scenario(
		array(
			'key'             => 'page standard execution',
			'prompt'          => '帮我把普通页面模板整理成标准页面布局：面包屑在页面标题上方，保留页面标题、特色图和正文。',
			'target_slug'     => 'page',
			'layout_profile'  => 'page_standard',
			'profile_version' => 'page_standard@0.2',
			'required_blocks' => array(
				'core/template-part',
				'core/group',
				'core/post-title',
				'core/post-featured-image',
				'core/post-content',
			),
			'required_classes' => array(
				'openclaw-breadcrumbs',
				'openclaw-template-layout-page_standard',
			),
		),
		$assertions
	);
	$additional_layout_execution_reports['homepage_landing'] = maa_adapter_btoa_run_template_layout_execution_scenario(
		array(
			'key'             => 'homepage landing execution',
			'prompt'          => '帮我把首页模板整理成落地页：顶部有大标题和介绍，下面有行动按钮，再展示最新文章和分类入口。',
			'target_slug'     => 'front-page',
			'layout_profile'  => 'homepage_landing',
			'profile_version' => 'homepage_landing@0.3',
			'required_blocks' => array(
				'core/template-part',
				'core/group',
				'core/heading',
				'core/paragraph',
				'core/buttons',
				'core/button',
				'core/latest-posts',
				'core/categories',
			),
			'required_classes' => array(
				'openclaw-template-layout-homepage_landing',
			),
		),
		$assertions
	);
	foreach ( $additional_layout_execution_reports as $execution_report ) {
		$created_proposal = $created_proposal || ! empty( $execution_report['created_proposal'] );
		$executed_write   = $executed_write || ! empty( $execution_report['executed_write'] );
	}
}
$report['scenarios']['additional_template_layout_proposal_execute_readback'] = $additional_layout_execution_reports;

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
	'created_proposal' => $created_proposal,
	'executed_write' => $executed_write,
);
$report['product_gaps'] = $product_gaps;

$report_path = maa_adapter_btoa_report_path();
wp_mkdir_p( dirname( $report_path ) );
file_put_contents( $report_path, wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
echo 'Wrote block theme OpenClaw acceptance report: ' . $report_path . "\n";

if ( ! empty( $failed ) || ! empty( $strict_gap_failures ) ) {
	exit( 1 );
}
