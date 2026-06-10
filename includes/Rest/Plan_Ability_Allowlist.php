<?php
/**
 * Adapter plan-to-proposal ability allowlist.
 *
 * @package NpcinkOpenClawAdapter
 */

namespace Npcink\OpenClawAdapter\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists planning abilities accepted by the adapter plan-to-proposal bridge.
 */
final class Plan_Ability_Allowlist {
	/**
	 * Returns all allowed planning ability ids.
	 *
	 * @return list<string>
	 */
	public static function ids(): array {
		return array_keys( self::map() );
	}

	/**
	 * Checks whether a planning ability may be submitted to Core.
	 *
	 * Core remains the governance truth; this adapter-side allowlist prevents
	 * arbitrary plan payload forwarding before Core intake.
	 *
	 * @param string $ability_id Ability id.
	 * @return bool
	 */
	public static function contains( string $ability_id ): bool {
		return isset( self::map()[ $ability_id ] );
	}

	/**
	 * Returns the allowlist as an id map.
	 *
	 * @return array<string,bool>
	 */
	private static function map(): array {
		return array(
			'npcink-abilities-toolkit/build-content-inventory-fix-plan'        => true,
			'npcink-abilities-toolkit/build-nonproduction-content-cleanup-plan' => true,
			'npcink-abilities-toolkit/build-media-inventory-fix-plan'          => true,
			'npcink-abilities-toolkit/build-media-reference-repair-plan'       => true,
			'npcink-abilities-toolkit/build-media-settings-reference-repair-plan' => true,
			'npcink-abilities-toolkit/build-media-optimization-plan'           => true,
			'npcink-abilities-toolkit/build-media-adoption-enhancement-plan'   => true,
			'npcink-abilities-toolkit/build-media-rename-plan'                 => true,
			'npcink-abilities-toolkit/build-article-block-plan'                => true,
			'npcink-abilities-toolkit/build-block-theme-site-plan'             => true,
			'npcink-abilities-toolkit/build-pattern-page-plan'                 => true,
			'npcink-toolbox/build-article-write-plan'                         => true,
			'npcink-toolbox/build-article-batch-write-plan'                   => true,
			'npcink-toolbox/build-article-media-batch-write-plan'             => true,
			'npcink-toolbox/build-image-candidate-adoption-plan'              => true,
			'npcink-toolbox/build-site-knowledge-review-plan'                 => true,
		);
	}
}
