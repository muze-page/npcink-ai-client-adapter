<?php
/**
 * Local-only article template visual development harness.
 *
 * This bypasses the production proposal flow only inside WP-CLI local
 * development: it builds the current Toolkit article profile, temporarily
 * applies the generated single template blocks, and writes a visual acceptance
 * manifest. The shell wrapper restores the original template content on exit.
 *
 * @package NpcinkOpenClawAdapter
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress is not loaded.\n" );
	exit( 1 );
}

/**
 * Emits an error and exits.
 *
 * @param string $message Error message.
 * @return never
 */
function maa_adapter_dev_article_visual_fail( string $message ) {
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
}

/**
 * Returns an env value with a fallback.
 *
 * @param string $name Env var name.
 * @param string $fallback Fallback value.
 * @return string
 */
function maa_adapter_dev_article_visual_env( string $name, string $fallback = '' ): string {
	$value = getenv( $name );
	return is_string( $value ) && '' !== $value ? $value : $fallback;
}

/**
 * Returns a writable path from env.
 *
 * @param string $env Env var name.
 * @param string $fallback Fallback path.
 * @return string
 */
function maa_adapter_dev_article_visual_path( string $env, string $fallback ): string {
	$path = maa_adapter_dev_article_visual_env( $env, $fallback );
	wp_mkdir_p( dirname( $path ) );
	return $path;
}

/**
 * Selects an administrator for local REST dispatch.
 *
 * @return int
 */
function maa_adapter_dev_article_visual_admin_id(): int {
	$users = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	$user_id = absint( $users[0] ?? 0 );
	if ( $user_id <= 0 ) {
		maa_adapter_dev_article_visual_fail( 'No administrator user is available for local REST dispatch.' );
	}
	return $user_id;
}

/**
 * Dispatches an Adapter REST request.
 *
 * @param string              $method HTTP method.
 * @param string              $route REST route.
 * @param array<string,mixed> $params Params.
 * @return array{status:int,data:mixed}
 */
function maa_adapter_dev_article_visual_rest( string $method, string $route, array $params = array() ): array {
	wp_set_current_user( maa_adapter_dev_article_visual_admin_id() );

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
 * Runs a direct-read ability through Adapter.
 *
 * @param string              $ability_id Ability id.
 * @param array<string,mixed> $input Input.
 * @return array<string,mixed>
 */
function maa_adapter_dev_article_visual_read_ability( string $ability_id, array $input ): array {
	$result = maa_adapter_dev_article_visual_rest(
		'POST',
		'/npcink-openclaw-adapter/v1/run-read-ability',
		array(
			'ability_id' => $ability_id,
			'input'      => $input,
		)
	);
	if ( $result['status'] < 200 || $result['status'] >= 300 ) {
		maa_adapter_dev_article_visual_fail( 'Adapter read ability failed: ' . $ability_id . ' status=' . $result['status'] );
	}
	return is_array( $result['data'] ) ? $result['data'] : array();
}

/**
 * Extracts an ability data payload from Adapter envelopes.
 *
 * @param array<string,mixed> $response Adapter response.
 * @return array<string,mixed>
 */
function maa_adapter_dev_article_visual_ability_data( array $response ): array {
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
 * Finds the post URL for browser visual acceptance.
 *
 * @return array{id:int,url:string,title:string}
 */
function maa_adapter_dev_article_visual_post_target(): array {
	$configured_post_id = absint( maa_adapter_dev_article_visual_env( 'MAA_ADAPTER_DEV_ARTICLE_VISUAL_POST_ID' ) );
	$post_id            = $configured_post_id;
	if ( $post_id <= 0 ) {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);
		$post_id = absint( $posts[0] ?? 0 );
	}
	if ( $post_id <= 0 || 'publish' !== get_post_status( $post_id ) ) {
		maa_adapter_dev_article_visual_fail( 'No published post is available for article visual acceptance.' );
	}
	return array(
		'id'    => $post_id,
		'url'   => (string) get_permalink( $post_id ),
		'title' => (string) get_the_title( $post_id ),
	);
}

/**
 * Restores the template content from backup.
 *
 * @param string $backup_path Backup path.
 * @return void
 */
function maa_adapter_dev_article_visual_restore( string $backup_path ): void {
	if ( ! is_readable( $backup_path ) ) {
		echo "No local article template visual backup found.\n";
		return;
	}
	$backup = json_decode( (string) file_get_contents( $backup_path ), true );
	if ( ! is_array( $backup ) ) {
		maa_adapter_dev_article_visual_fail( 'Invalid local article template visual backup.' );
	}
	$template_post_id = absint( $backup['template_post_id'] ?? 0 );
	if ( $template_post_id <= 0 ) {
		maa_adapter_dev_article_visual_fail( 'Backup does not include a template post id.' );
	}
	$result = wp_update_post(
		array(
			'ID'           => $template_post_id,
			'post_content' => (string) ( $backup['post_content'] ?? '' ),
		),
		true
	);
	if ( is_wp_error( $result ) ) {
		maa_adapter_dev_article_visual_fail( 'Failed to restore template content: ' . $result->get_error_message() );
	}
	echo 'Restored template post ' . $template_post_id . " from local visual backup.\n";
}

$root_dir     = dirname( __DIR__ );
$backup_path  = maa_adapter_dev_article_visual_path( 'MAA_ADAPTER_DEV_ARTICLE_VISUAL_BACKUP', $root_dir . '/build/dev-article-template-visual/template-backup.json' );
$manifest_path = maa_adapter_dev_article_visual_path( 'MAA_ADAPTER_VISUAL_ACCEPTANCE_OUT', $root_dir . '/build/dev-article-template-visual/manifest.json' );
$report_path  = maa_adapter_dev_article_visual_path( 'MAA_ADAPTER_DEV_ARTICLE_VISUAL_PLAN_REPORT', $root_dir . '/build/dev-article-template-visual/plan-report.json' );

if ( 'restore' === maa_adapter_dev_article_visual_env( 'MAA_ADAPTER_DEV_ARTICLE_VISUAL_MODE' ) ) {
	maa_adapter_dev_article_visual_restore( $backup_path );
	exit( 0 );
}

$template_slug = sanitize_key( maa_adapter_dev_article_visual_env( 'MAA_ADAPTER_DEV_ARTICLE_VISUAL_TEMPLATE', 'single' ) );
if ( 'single' !== $template_slug ) {
	maa_adapter_dev_article_visual_fail( 'Local article visual harness currently supports only the single template.' );
}

$plan_input = array(
	'intent'             => 'customize_template_layout',
	'target_templates'   => array( $template_slug ),
	'layout_profile'     => 'article_standard',
	'include_breadcrumbs' => true,
	'show_author_date'   => true,
	'show_featured_image' => true,
	'include_related_posts' => true,
	'separator'          => '/',
	'show_current_item'  => true,
	'show_home_item'     => true,
	'show_on_home_page'  => false,
);

$plan_response = maa_adapter_dev_article_visual_read_ability( 'npcink-abilities-toolkit/build-block-theme-site-plan', $plan_input );
$plan          = maa_adapter_dev_article_visual_ability_data( $plan_response );
$actions       = is_array( $plan['write_actions'] ?? null ) ? array_values( $plan['write_actions'] ) : array();
$action        = is_array( $actions[0] ?? null ) ? $actions[0] : array();
$action_input  = is_array( $action['input'] ?? null ) ? $action['input'] : array();
$blocks        = is_array( $action_input['blocks'] ?? null ) ? $action_input['blocks'] : array();
$template_post_id = absint( $action_input['post_id'] ?? 0 );

if ( empty( $blocks ) || $template_post_id <= 0 ) {
	maa_adapter_dev_article_visual_fail( 'The article template plan did not return updateable single template blocks.' );
}

$template_post = get_post( $template_post_id );
if ( ! $template_post instanceof WP_Post || 'wp_template' !== $template_post->post_type ) {
	maa_adapter_dev_article_visual_fail( 'The planned target is not an existing wp_template post.' );
}

$backup = array(
	'generated_at'      => gmdate( 'c' ),
	'template_post_id' => $template_post_id,
	'template_slug'    => $template_slug,
	'post_content'     => (string) $template_post->post_content,
	'local_dev_only'   => true,
);
file_put_contents( $backup_path, wp_json_encode( $backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

$serialized_blocks = serialize_blocks( $blocks );
$update_result     = wp_update_post(
	array(
		'ID'           => $template_post_id,
		'post_content' => $serialized_blocks,
	),
	true
);
if ( is_wp_error( $update_result ) ) {
	maa_adapter_dev_article_visual_fail( 'Failed to apply local article template candidate: ' . $update_result->get_error_message() );
}

$post_target = maa_adapter_dev_article_visual_post_target();
$minimum_padded_sections = max( 1, absint( maa_adapter_dev_article_visual_env( 'MAA_ADAPTER_DEV_ARTICLE_VISUAL_MIN_PADDED_SECTIONS', '2' ) ) );
$manifest = array(
	'generated_at' => gmdate( 'c' ),
	'local_dev_only' => true,
	'viewports'    => array(
		array( 'name' => 'desktop', 'width' => 1440, 'height' => 1000 ),
		array( 'name' => 'tablet', 'width' => 768, 'height' => 1024 ),
		array( 'name' => 'mobile', 'width' => 390, 'height' => 844 ),
	),
	'fixtures'     => array(
		array(
			'fixture_type'            => 'block_theme_template',
			'template_slug'           => $template_slug,
			'post_id'                 => $post_target['id'],
			'front_end_url'           => $post_target['url'],
			'required_blocks'         => array( 'post_content', 'latest_posts' ),
			'minimum_padded_sections' => $minimum_padded_sections,
			'require_images'          => false,
			'validate_images'         => false,
		),
	),
);
file_put_contents( $manifest_path, wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );

$plan_report = array(
	'artifact_type'     => 'local_article_template_visual_candidate',
	'generated_at'      => gmdate( 'c' ),
	'local_dev_only'    => true,
	'plan_input'        => $plan_input,
	'layout_profile'    => (string) ( $plan['layout_profile'] ?? '' ),
	'profile_version'   => (string) ( $plan['template_layout_contract']['profiles'][0]['profile_version'] ?? '' ),
	'compiler_version'  => (string) ( $plan['compiler_version'] ?? ( $plan['template_layout_contract']['compiler_version'] ?? '' ) ),
	'action_count'      => count( $actions ),
	'target_ability_id' => (string) ( $action['target_ability_id'] ?? '' ),
	'template_post_id'  => $template_post_id,
	'template_slug'     => $template_slug,
	'block_count'       => count( $blocks ),
	'visual_post'       => $post_target,
	'manifest_path'     => $manifest_path,
	'backup_path'       => $backup_path,
);
file_put_contents( $report_path, wp_json_encode( $plan_report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );

echo 'Applied local article template visual candidate: ' . $template_slug . ' template post ' . $template_post_id . "\n";
echo 'Visual manifest: ' . $manifest_path . "\n";
echo 'Plan report: ' . $report_path . "\n";
