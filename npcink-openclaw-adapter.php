<?php
/**
 * Legacy bootstrap for installs that still reference the old main file path.
 *
 * This file intentionally has no WordPress plugin header. It prevents stale
 * active_plugins entries from breaking the Adapter during the package rename
 * to npcink-ai-client-adapter.
 *
 * @package NpcinkOpenClawAdapter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$npcink_ai_client_adapter_main = __DIR__ . '/npcink-ai-client-adapter.php';
if ( is_readable( $npcink_ai_client_adapter_main ) ) {
	require_once $npcink_ai_client_adapter_main;
}
