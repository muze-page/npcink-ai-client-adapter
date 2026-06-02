<?php
/**
 * Local observability event bridge.
 *
 * @package MagickAIAdapter
 */

namespace MagickAI\Adapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emits metadata-only events for optional Cloud Addon collection.
 */
final class Observability {
	/**
	 * Emits a local-only observability event.
	 *
	 * @param string              $event_kind Event kind.
	 * @param array<string,mixed> $payload Event details.
	 * @return void
	 */
	public static function emit( string $event_kind, array $payload = array() ): void {
		if ( ! function_exists( 'do_action' ) ) {
			return;
		}

		do_action(
			'magick_ai_observability_event',
			array_merge(
				array(
					'schema_version' => '2026-06-01',
					'plugin_slug'    => 'magick-ai-adapter',
					'plugin_version' => defined( 'MAGICK_AI_ADAPTER_VERSION' ) ? MAGICK_AI_ADAPTER_VERSION : '',
					'source'         => 'local',
					'event_kind'     => $event_kind,
					'emitted_at'     => gmdate( 'c' ),
				),
				$payload
			)
		);
	}
}
