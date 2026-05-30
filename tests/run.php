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
		'/capabilities',
		'/run-read-ability',
		'/site-summary',
		'/wp-diagnostics-summary',
		'workflow-recipes',
		'/workflow-recipe',
		'/proposals',
		'/commit-preflight',
		"current_user_can( 'manage_options' )",
		'/magick-ai-core/v1/capabilities',
		'/magick-ai-core/v1/proposals',
		'/wp-abilities/v1/abilities/',
		'governance_mode',
		'direct_read',
		'proposal_required',
		'wp_abilities_rest',
		'adapter_after_core_preflight',
		'core_proxy_execute',
		'commit_execution',
		'magick_ai_adapter_proposal_required',
		'magick-ai-abilities/site-summary',
		'magick-ai-abilities/wp-diagnostics-summary',
		'magick-ai-abilities/list-workflow-recipes',
		'magick-ai-abilities/get-workflow-recipe',
	) as $required
) {
	maa_adapter_assert( false !== strpos( $controller, $required ), 'Controller contains required text: ' . $required );
}
maa_adapter_assert( false === strpos( $controller, '/approve' ), 'Adapter does not expose proposal approval route.' );

$readme = maa_adapter_read( $root . '/README.md' );
foreach (
	array(
		'thin OpenClaw channel plugin',
		'read Magick AI Core capability guidance',
		'run approved direct-read abilities through WordPress Abilities API',
		'create Core proposals',
		'does not define abilities',
		'execute final write mutations',
		'GET /wp-json/magick-ai-adapter/v1/site-summary',
		'POST /wp-json/magick-ai-adapter/v1/run-read-ability',
		'governance_mode=direct_read',
		'governance_mode=proposal_required',
	) as $required
) {
	maa_adapter_assert( false !== strpos( $readme, $required ), 'README contains required text: ' . $required );
}

$contract = maa_adapter_read( $root . '/docs/openclaw-adapter-contract.md' );
foreach (
	array(
		'initial productization contract',
		'magick-ai-abilities',
		'magick-ai-core',
		'core_proxy_execute',
		'commit_execution',
		'It does not execute abilities marked `proposal_required`.',
		'The adapter does not approve proposals',
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
		'/magick-ai-adapter/v1/capabilities',
		'/magick-ai-adapter/v1/site-summary',
		'/magick-ai-adapter/v1/wp-diagnostics-summary',
		'/magick-ai-adapter/v1/workflow-recipes',
		'/magick-ai-adapter/v1/workflow-recipe',
		'magick-ai-abilities/site-summary',
		'magick-ai-abilities/wp-diagnostics-summary',
		'magick-ai-abilities/list-workflow-recipes',
		'magick-ai-abilities/get-workflow-recipe',
		'adapter refuses direct execution for proposal-required ability',
	) as $required
) {
	maa_adapter_assert( false !== strpos( $smoke_wp, $required ), 'WordPress smoke contains required text: ' . $required );
}

echo "Static contracts: ok\n";
