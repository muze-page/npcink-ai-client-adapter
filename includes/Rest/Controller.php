<?php
/**
 * Adapter REST controller.
 *
 * @package MagickAIAdapter
 */

namespace MagickAI\Adapter\Rest;

use MagickAI\Adapter\Observability;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes a thin OpenClaw adapter surface.
 */
final class Controller {
	const NAMESPACE                = 'magick-ai-adapter/v1';
	const MAX_EXECUTION_ACTIONS    = 200;
	const DEVICE_PAIRING_OPTION    = 'magick_ai_adapter_device_pairings';
	const CLIENT_KEYS_OPTION       = 'magick_ai_adapter_client_keys';
	const EXECUTION_RECORDS_OPTION  = 'magick_ai_adapter_execution_records';
	const PREFLIGHT_HANDOFFS_OPTION = 'magick_ai_adapter_preflight_handoffs';
	const DEVICE_PAIRING_TTL       = 600;
	const SIGNATURE_NONCE_TTL      = 300;
	const MAX_EXECUTION_RECORDS    = 500;
	const MAX_PREFLIGHT_HANDOFFS   = 500;

	/**
	 * Current request log context while an ability is running.
	 *
	 * @var array<string,mixed>
	 */
	private $current_request_log_context = array();

	/**
	 * Planning abilities accepted by the adapter plan-to-proposal bridge.
	 *
	 * Core enforces the same policy as governance truth; this adapter-side
	 * allowlist prevents arbitrary plan payload forwarding before Core intake.
	 *
	 * @var array<string,bool>
	 */
	private static $allowed_plan_ability_ids = array(
		'magick-ai/build-content-inventory-fix-plan'           => true,
		'magick-ai/build-test-content-cleanup-plan'            => true,
		'magick-ai/build-media-inventory-fix-plan'             => true,
		'magick-ai/build-media-reference-repair-plan'          => true,
		'magick-ai/build-media-settings-reference-repair-plan' => true,
		'magick-ai/build-media-optimization-plan'              => true,
		'magick-ai-toolbox/build-article-write-plan'           => true,
		'magick-ai-toolbox/build-article-batch-write-plan'     => true,
		'magick-ai-toolbox/build-article-media-batch-write-plan' => true,
	);

	/**
	 * Returns Adapter-owned execution profiles for abilities that may run after
	 * Core approval and commit preflight.
	 *
	 * Discovery tells Adapter which abilities exist; this registry is the
	 * explicit opt-in policy for final WordPress writes.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function execution_profiles(): array {
		return array(
			'magick-ai/trash-post'      => array(
				'allowed_input_fields'  => array( 'post_id', 'dry_run', 'commit', 'idempotency_key' ),
				'require_post_id'       => array(
					'code'    => 'magick_ai_adapter_post_id_required',
					'message' => __( 'trash-post execution input must include post_id.', 'magick-ai-adapter' ),
				),
				'force_post_input'      => true,
				'post_id_from_result'   => false,
			),
			'magick-ai/create-draft'    => array(
				'allowed_input_fields'  => array( 'post_type', 'status', 'title', 'content', 'content_format', 'excerpt', 'soft_block_reason', 'soft_block_summary', 'meta', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'           => array(
					'status'         => array(
						'allowed' => array( 'draft' ),
						'code'    => 'magick_ai_adapter_input_enum_invalid',
						'message' => __( 'create-draft status must be draft.', 'magick-ai-adapter' ),
					),
					'content_format' => array(
						'allowed' => array( 'html', 'markdown', 'plain' ),
						'code'    => 'magick_ai_adapter_content_format_invalid',
						'message' => __( 'create-draft content_format must be html, markdown, or plain.', 'magick-ai-adapter' ),
					),
				),
				'required_text_fields'  => array(
					'title' => array(
						'code'    => 'magick_ai_adapter_title_required',
						'message' => __( 'create-draft execution input must include title.', 'magick-ai-adapter' ),
					),
				),
				'post_id_from_result'   => true,
			),
			'magick-ai/update-post'     => array(
				'allowed_input_fields'  => array( 'post_id', 'title', 'content', 'content_format', 'excerpt', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'           => array(
					'content_format' => array(
						'allowed' => array( 'html', 'markdown', 'plain' ),
						'code'    => 'magick_ai_adapter_content_format_invalid',
						'message' => __( 'update-post content_format must be html, markdown, or plain.', 'magick-ai-adapter' ),
					),
				),
				'require_post_id'       => array(
					'code'    => 'magick_ai_adapter_post_id_required',
					'message' => __( 'update-post execution input must include post_id.', 'magick-ai-adapter' ),
				),
				'require_any_fields'    => array(
					'fields'  => array( 'title', 'content', 'excerpt' ),
					'code'    => 'magick_ai_adapter_update_fields_required',
					'message' => __( 'update-post execution input must include title, content, or excerpt.', 'magick-ai-adapter' ),
				),
				'post_id_from_result'   => false,
			),
			'magick-ai/patch-post-content' => array(
				'allowed_input_fields'  => array( 'post_id', 'operations', 'dry_run', 'commit', 'idempotency_key' ),
				'require_post_id'       => array(
					'code'    => 'magick_ai_adapter_post_id_required',
					'message' => __( 'patch-post-content execution input must include post_id.', 'magick-ai-adapter' ),
				),
				'require_any_fields'    => array(
					'fields'  => array( 'operations' ),
					'code'    => 'magick_ai_adapter_patch_operations_required',
					'message' => __( 'patch-post-content execution input must include operations.', 'magick-ai-adapter' ),
				),
				'post_id_from_result'   => false,
			),
			'magick-ai/patch-setting-value' => array(
				'allowed_input_fields'  => array( 'target_type', 'target_name', 'operations', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'           => array(
					'target_type' => array(
						'allowed' => array( 'option', 'theme_mod' ),
						'code'    => 'magick_ai_adapter_setting_target_type_invalid',
						'message' => __( 'patch-setting-value target_type must be option or theme_mod.', 'magick-ai-adapter' ),
					),
				),
				'require_any_fields'    => array(
					'fields'  => array( 'operations' ),
					'code'    => 'magick_ai_adapter_patch_operations_required',
					'message' => __( 'patch-setting-value execution input must include operations.', 'magick-ai-adapter' ),
				),
				'required_text_fields'  => array(
					'target_name' => array(
						'code'    => 'magick_ai_adapter_setting_target_required',
						'message' => __( 'patch-setting-value execution input must include target_name.', 'magick-ai-adapter' ),
					),
				),
				'post_id_from_result'   => false,
			),
			'magick-ai/set-post-seo-meta' => array(
				'allowed_input_fields'  => array( 'post_id', 'seo_title', 'seo_description', 'dry_run', 'commit', 'idempotency_key' ),
				'require_post_id'       => array(
					'code'    => 'magick_ai_adapter_post_id_required',
					'message' => __( 'set-post-seo-meta execution input must include post_id.', 'magick-ai-adapter' ),
				),
				'require_any_fields'    => array(
					'fields'  => array( 'seo_title', 'seo_description' ),
					'code'    => 'magick_ai_adapter_seo_fields_required',
					'message' => __( 'set-post-seo-meta execution input must include seo_title or seo_description.', 'magick-ai-adapter' ),
				),
				'post_id_from_result'   => false,
			),
			'magick-ai/set-post-slug'   => array(
				'allowed_input_fields'  => array( 'post_id', 'slug', 'dry_run', 'commit', 'idempotency_key' ),
				'require_post_id'       => array(
					'code'    => 'magick_ai_adapter_post_id_required',
					'message' => __( 'set-post-slug execution input must include post_id.', 'magick-ai-adapter' ),
				),
				'required_slug_fields'  => array(
					'slug' => array(
						'code'    => 'magick_ai_adapter_slug_required',
						'message' => __( 'set-post-slug execution input must include a valid slug.', 'magick-ai-adapter' ),
					),
				),
				'post_id_from_result'   => false,
			),
			'magick-ai/set-post-terms'  => array(
				'allowed_input_fields'  => array( 'post_id', 'taxonomy', 'mode', 'term_ids', 'terms', 'create_missing', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'           => array(
					'mode' => array(
						'allowed' => array( 'replace', 'append', 'remove' ),
						'code'    => 'magick_ai_adapter_term_mode_invalid',
						'message' => __( 'set-post-terms execution mode must be replace, append, or remove.', 'magick-ai-adapter' ),
					),
				),
				'require_post_id'       => array(
					'code'    => 'magick_ai_adapter_post_id_required',
					'message' => __( 'set-post-terms execution input must include post_id.', 'magick-ai-adapter' ),
				),
				'validate_terms_input'  => true,
				'post_id_from_result'   => false,
			),
			'magick-ai/delete-term'     => array(
				'allowed_input_fields'      => array( 'taxonomy', 'term_id', 'dry_run', 'commit', 'idempotency_key' ),
				'required_int_fields'       => array(
					'term_id' => array(
						'code'    => 'magick_ai_adapter_term_id_required',
						'message' => __( 'delete-term execution input must include term_id.', 'magick-ai-adapter' ),
					),
				),
				'validate_delete_term_input' => true,
				'post_id_from_result'       => false,
			),
			'magick-ai/update-media-details' => array(
				'allowed_input_fields'  => array( 'attachment_id', 'title', 'alt', 'caption', 'description', 'source_type', 'source_page_url', 'photographer_name', 'attribution_text', 'copyright_notice', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'           => array(
					'source_type' => array(
						'allowed' => array( 'owned', 'ai_generated', 'stock', 'external', 'test' ),
						'code'    => 'magick_ai_adapter_media_source_type_invalid',
						'message' => __( 'update-media-details source_type must be owned, ai_generated, stock, external, or test.', 'magick-ai-adapter' ),
					),
				),
				'required_int_fields'   => array(
					'attachment_id' => array(
						'code'    => 'magick_ai_adapter_attachment_id_required',
						'message' => __( 'update-media-details execution input must include attachment_id.', 'magick-ai-adapter' ),
					),
				),
				'require_any_fields'    => array(
					'fields'  => array( 'title', 'alt', 'caption', 'description', 'source_type', 'source_page_url', 'photographer_name', 'attribution_text', 'copyright_notice' ),
					'code'    => 'magick_ai_adapter_media_fields_required',
					'message' => __( 'update-media-details execution input must include at least one media detail field.', 'magick-ai-adapter' ),
				),
				'post_id_from_result'   => false,
			),
			'magick-ai/upload-media-from-url' => array(
				'allowed_input_fields'  => array( 'url', 'title', 'alt', 'caption', 'description', 'source_type', 'source_page_url', 'photographer_name', 'attribution_text', 'copyright_notice', 'attach_to_post_id', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'           => array(
					'source_type' => array(
						'allowed' => array( 'owned', 'ai_generated', 'stock', 'external', 'test' ),
						'code'    => 'magick_ai_adapter_media_source_type_invalid',
						'message' => __( 'upload-media-from-url source_type must be owned, ai_generated, stock, external, or test.', 'magick-ai-adapter' ),
					),
				),
				'required_text_fields'  => array(
					'url' => array(
						'code'    => 'magick_ai_adapter_media_url_required',
						'message' => __( 'upload-media-from-url execution input must include url.', 'magick-ai-adapter' ),
					),
				),
				'post_id_from_result'   => false,
			),
			'magick-ai/set-post-featured-image' => array(
				'allowed_input_fields'  => array( 'post_id', 'attachment_id', 'media_url', 'media_title', 'dry_run', 'commit', 'idempotency_key' ),
				'require_post_id'       => array(
					'code'    => 'magick_ai_adapter_post_id_required',
					'message' => __( 'set-post-featured-image execution input must include post_id.', 'magick-ai-adapter' ),
				),
				'require_any_fields'    => array(
					'fields'  => array( 'attachment_id', 'media_url' ),
					'code'    => 'magick_ai_adapter_featured_image_required',
					'message' => __( 'set-post-featured-image execution input must include attachment_id or media_url.', 'magick-ai-adapter' ),
				),
				'post_id_from_result'   => true,
			),
			'magick-ai/optimize-media-asset' => array(
				'allowed_input_fields'  => array( 'attachment_id', 'target_max_width', 'preferred_format', 'quality', 'derivative_suffix', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'           => array(
					'preferred_format' => array(
						'allowed' => array( 'webp', 'jpeg', 'png' ),
						'code'    => 'magick_ai_adapter_media_preferred_format_invalid',
						'message' => __( 'optimize-media-asset preferred_format must be webp, jpeg, or png.', 'magick-ai-adapter' ),
					),
				),
				'required_int_fields'   => array(
					'attachment_id' => array(
						'code'    => 'magick_ai_adapter_attachment_id_required',
						'message' => __( 'optimize-media-asset execution input must include attachment_id.', 'magick-ai-adapter' ),
					),
				),
				'post_id_from_result'   => false,
			),
			'magick-ai/replace-media-file' => array(
				'allowed_input_fields'  => array( 'attachment_id', 'mode', 'derivative_relative_file', 'replacement_id', 'expected_current_relative_file', 'expected_current_mime_type', 'expected_derivative_mime_type', 'backup_suffix', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'           => array(
					'mode' => array(
						'allowed' => array( 'replace', 'rollback' ),
						'code'    => 'magick_ai_adapter_media_replace_mode_invalid',
						'message' => __( 'replace-media-file mode must be replace or rollback.', 'magick-ai-adapter' ),
					),
				),
				'required_int_fields'   => array(
					'attachment_id' => array(
						'code'    => 'magick_ai_adapter_attachment_id_required',
						'message' => __( 'replace-media-file execution input must include attachment_id.', 'magick-ai-adapter' ),
					),
				),
				'post_id_from_result'   => false,
			),
			'magick-ai/adopt-cloud-media-derivative' => array(
				'allowed_input_fields'  => array( 'attachment_id', 'derivative_artifact', 'expected_current_relative_file', 'expected_current_mime_type', 'expected_derivative_mime_type', 'backup_suffix', 'dry_run', 'commit', 'idempotency_key' ),
				'required_int_fields'   => array(
					'attachment_id' => array(
						'code'    => 'magick_ai_adapter_attachment_id_required',
						'message' => __( 'adopt-cloud-media-derivative execution input must include attachment_id.', 'magick-ai-adapter' ),
					),
				),
				'require_any_fields'    => array(
					'fields'  => array( 'derivative_artifact' ),
					'code'    => 'magick_ai_adapter_derivative_artifact_required',
					'message' => __( 'adopt-cloud-media-derivative execution input must include derivative_artifact evidence.', 'magick-ai-adapter' ),
				),
				'post_id_from_result'   => false,
			),
			'magick-ai/delete-media-permanently' => array(
				'allowed_input_fields'     => array( 'attachment_id', 'dry_run', 'commit', 'idempotency_key' ),
				'required_int_fields'      => array(
					'attachment_id' => array(
						'code'    => 'magick_ai_adapter_attachment_id_required',
						'message' => __( 'delete-media-permanently execution input must include attachment_id.', 'magick-ai-adapter' ),
					),
				),
				'validate_attachment_input' => true,
				'post_id_from_result'      => false,
			),
			'magick-ai/reply-comment'   => array(
				'allowed_input_fields'  => array( 'comment_id', 'content', 'content_format', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'           => array(
					'content_format' => array(
						'allowed' => array( 'html', 'markdown', 'plain' ),
						'code'    => 'magick_ai_adapter_content_format_invalid',
						'message' => __( 'reply-comment content_format must be html, markdown, or plain.', 'magick-ai-adapter' ),
					),
				),
				'required_int_fields'   => array(
					'comment_id' => array(
						'code'    => 'magick_ai_adapter_comment_id_required',
						'message' => __( 'reply-comment execution input must include comment_id.', 'magick-ai-adapter' ),
					),
				),
				'require_comment_body'  => array(
					'code'    => 'magick_ai_adapter_comment_content_required',
					'message' => __( 'reply-comment execution input must include content.', 'magick-ai-adapter' ),
				),
				'post_id_from_result'   => true,
			),
			'magick-ai/trash-comment'   => array(
				'allowed_input_fields'  => array( 'comment_id', 'dry_run', 'commit', 'idempotency_key' ),
				'required_int_fields'   => array(
					'comment_id' => array(
						'code'    => 'magick_ai_adapter_comment_id_required',
						'message' => __( 'trash-comment execution input must include comment_id.', 'magick-ai-adapter' ),
					),
				),
				'post_id_from_result'   => true,
			),
			'magick-ai/approve-comment' => array(
				'allowed_input_fields'  => array( 'comment_id', 'dry_run', 'commit', 'idempotency_key' ),
				'required_int_fields'   => array(
					'comment_id' => array(
						'code'    => 'magick_ai_adapter_comment_id_required',
						'message' => __( 'approve-comment execution input must include comment_id.', 'magick-ai-adapter' ),
					),
				),
				'post_id_from_result'   => true,
			),
		);
	}

	/**
	 * Returns ability ids this adapter may execute after Core approval.
	 *
	 * @return array<int,string>
	 */
	private static function allowed_execute_ability_ids(): array {
		return array_keys( self::execution_profiles() );
	}

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/health',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'health' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/help',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'help' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/capabilities',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'capabilities' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/connection/manifest',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'connection_manifest' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/connect/device/start',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'start_device_pairing' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/connect/device/poll',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'poll_device_pairing' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/connection/key-pairs',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_client_keys' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/connection/key-pairs/(?P<key_id>mk_[A-Za-z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'revoke_client_key' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/run-read-ability',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'run_read_ability_route' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
					'args'                => array(
						'ability_id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'input'      => array(
							'type'    => 'object',
							'default' => array(),
						),
						'log_context' => array(
							'type'    => 'object',
							'default' => array(),
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/ai-provider-log-correlation-smoke',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'ai_provider_log_correlation_smoke' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
					'args'                => array(
						'proposal_id'    => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'correlation_id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'ability_id'     => array(
							'type'              => 'string',
							'default'           => 'magick-ai-adapter/provider-log-correlation-smoke',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'ai_provider'    => array(
							'type'              => 'string',
							'default'           => 'ollama',
							'sanitize_callback' => 'sanitize_key',
						),
						'ai_model'       => array(
							'type'              => 'string',
							'default'           => 'qwen3.5:0.8b',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'prompt'         => array(
							'type'              => 'string',
							'default'           => 'Reply with exactly: OK',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/media-metadata-optimization',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'media_metadata_optimization_route' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
					'args'                => array(
						'input'                => array(
							'type'    => 'object',
							'default' => array(),
						),
						'media_assets'         => array(
							'type'    => 'array',
							'default' => array(),
						),
						'article_title'        => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'article_excerpt'      => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'article_content'      => array(
							'type'              => 'string',
							'sanitize_callback' => 'wp_kses_post',
						),
						'focus_keyword'        => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'vision_fallback_mode' => array(
							'type'              => 'string',
							'default'           => 'auto',
							'sanitize_callback' => 'sanitize_key',
						),
						'log_context'          => array(
							'type'    => 'object',
							'default' => array(),
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/media-derivative-runs',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_media_derivative_run' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
					'args'                => array(
						'input'              => array(
							'type'    => 'object',
							'default' => array(),
						),
						'source_artifact'    => array(
							'type'    => 'object',
							'default' => array(),
						),
						'watermark_artifact' => array(
							'type'    => 'object',
							'default' => array(),
						),
						'trace_id'           => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'idempotency_key'    => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'log_context'        => array(
							'type'    => 'object',
							'default' => array(),
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/media-derivative-runs/(?P<run_id>[A-Za-z0-9._:-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_media_derivative_run' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
					'args'                => array(
						'run_id'   => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'trace_id' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/media-derivative-runs/(?P<run_id>[A-Za-z0-9._:-]+)/result',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_media_derivative_run_result' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
					'args'                => array(
						'run_id'   => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'trace_id' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/media-derivative-proposal-payload',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'build_media_derivative_proposal_payload' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
					'args'                => array(
						'ability_response'    => array(
							'type'    => 'object',
							'default' => array(),
						),
						'cloud_result'        => array(
							'type'    => 'object',
							'default' => array(),
						),
						'derivative_artifact' => array(
							'type'    => 'object',
							'default' => array(),
						),
						'media_details_input' => array(
							'type'    => 'object',
							'default' => array(),
						),
					),
				),
			)
		);

		foreach ( self::read_shortcut_definitions() as $route => $definition ) {
			$ability_id    = (string) ( $definition['ability_id'] ?? '' );
			$default_input = is_array( $definition['default_input'] ?? null ) ? $definition['default_input'] : array();
			register_rest_route(
				self::NAMESPACE,
				'/' . $route,
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => function ( WP_REST_Request $request ) use ( $route, $ability_id, $default_input ) {
							return $this->run_read_ability( $ability_id, $this->shortcut_input( $request, $route, $default_input ), $this->request_log_context( $request, $ability_id ) );
						},
						'permission_callback' => array( $this, 'can_use_adapter' ),
					),
				),
			);
		}

		register_rest_route(
			self::NAMESPACE,
			'/workflow-recipe',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'workflow_recipe' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
					'args'                => array(
						'recipe_id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/media-derivative-artifacts/(?P<artifact_id>[A-Za-z0-9._:-]+)/preview',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'download_media_derivative_artifact_preview' ),
					'permission_callback' => array( $this, 'can_use_media_derivative_artifact_preview' ),
					'args'                => array(
						'artifact_id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'expires_at'  => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'expires_ts'  => array(
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'mime_type'   => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'checksum'    => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'sha256'      => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'run_id'      => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'trace_id'    => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'preview_sig' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/proposals',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_proposals' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
					'args'                => array(
						'limit' => array(
							'type'              => 'integer',
							'default'           => 50,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_proposal' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
					'args'                => array(
						'ability_id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'title'      => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'summary'    => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'input'      => array(
							'type'    => 'object',
							'default' => array(),
						),
						'preview'    => array(
							'type'    => 'object',
							'default' => array(),
						),
						'caller'     => array(
							'type'    => 'object',
							'default' => array(),
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/proposals/from-plan',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_proposals_from_plan' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
					'args'                => array(
						'plan_ability_id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'plan'            => array(
							'type'     => 'object',
							'required' => true,
						),
						'plan_input'      => array(
							'type'    => 'object',
							'default' => array(),
						),
						'caller'          => array(
							'type'    => 'object',
							'default' => array(),
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/execute-approved-proposal',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'execute_approved_proposal_route' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
					'args'                => array(
						'proposal_id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/proposals/(?P<proposal_id>[A-Za-z0-9_-]+)/execute',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'execute_approved_proposal_route' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
					'args'                => array(
						'proposal_id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/proposals/(?P<proposal_id>[A-Za-z0-9_-]+)/approve-and-execute',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'approve_and_execute_proposal_route' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
					'args'                => array(
						'proposal_id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'note'        => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/proposals/(?P<proposal_id>[A-Za-z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_proposal' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
					'args'                => array(
						'proposal_id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/proposals/(?P<proposal_id>[A-Za-z0-9_-]+)/approve',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'approval_proxy_disabled' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
					'args'                => array(
						'proposal_id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/proposals/(?P<proposal_id>[A-Za-z0-9_-]+)/reject',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'approval_proxy_disabled' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
					'args'                => array(
						'proposal_id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/proposals/(?P<proposal_id>[A-Za-z0-9_-]+)/commit-preflight',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'commit_preflight' ),
					'permission_callback' => array( $this, 'can_use_adapter' ),
					'args'                => array(
						'proposal_id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

	}

	/**
	 * Authorizes adapter use.
	 *
	 * @param WP_REST_Request|null $request Request.
	 * @return bool
	 */
	public function can_use_adapter( ?WP_REST_Request $request = null ): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return $request instanceof WP_REST_Request && $this->authenticate_signed_request( $request );
	}

	/**
	 * Authorizes one local media derivative preview URL.
	 *
	 * Browser image requests cannot send X-WP-Nonce headers. The projection
	 * URL therefore carries a short-lived local HMAC over the artifact
	 * descriptor, while normal Adapter auth still works for authenticated REST
	 * callers.
	 *
	 * @param WP_REST_Request|null $request Request.
	 * @return bool
	 */
	public function can_use_media_derivative_artifact_preview( ?WP_REST_Request $request = null ): bool {
		if ( $this->can_use_adapter( $request ) ) {
			return true;
		}

		return $request instanceof WP_REST_Request && $this->valid_media_derivative_artifact_preview_signature( $request );
	}

	/**
	 * Returns the non-secret local broker connection manifest.
	 *
	 * @return WP_REST_Response
	 */
	public function connection_manifest(): WP_REST_Response {
		return new WP_REST_Response( $this->connection_manifest_payload( get_current_user_id() ), 200 );
	}

	/**
	 * Starts a public-key device pairing session.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function start_device_pairing( WP_REST_Request $request ) {
		$started = microtime( true );
		$body = $this->request_json_body( $request );
		if ( is_wp_error( $body ) ) {
			$this->emit_operation_event( 'adapter.device_pairing.start', $started, $body );
			return $body;
		}

		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			$error = new WP_Error(
				'magick_ai_adapter_sodium_unavailable',
				__( 'Ed25519 device pairing requires the PHP sodium extension.', 'magick-ai-adapter' ),
				array( 'status' => 501 )
			);
			$this->emit_operation_event( 'adapter.device_pairing.start', $started, $error );
			return $error;
		}

		$client = is_array( $body['client'] ?? null ) ? $body['client'] : array();
		$key    = is_array( $body['key'] ?? null ) ? $body['key'] : array();
		$name   = sanitize_text_field( (string) ( $client['name'] ?? '' ) );
		$public_key = sanitize_text_field( (string) ( $key['public_key'] ?? '' ) );
		$scopes = $this->connection_requested_scopes( is_array( $body['requested_scopes'] ?? null ) ? $body['requested_scopes'] : array() );

		if ( '' === $name || 'Ed25519' !== (string) ( $key['alg'] ?? '' ) || 32 !== strlen( $this->base64url_decode( $public_key ) ) ) {
			$error = new WP_Error(
				'magick_ai_adapter_device_pairing_invalid',
				__( 'Device pairing requires client metadata and a base64url Ed25519 public key.', 'magick-ai-adapter' ),
				array( 'status' => 400 )
			);
			$this->emit_operation_event( 'adapter.device_pairing.start', $started, $error );
			return $error;
		}

		$device_code = 'dev_' . $this->base64url_encode( random_bytes( 32 ) );
		$user_code   = strtoupper( substr( $this->base64url_encode( random_bytes( 5 ) ), 0, 4 ) . '-' . substr( $this->base64url_encode( random_bytes( 5 ) ), 0, 4 ) );
		$expires_at  = time() + self::DEVICE_PAIRING_TTL;
		$pairings    = $this->device_pairings();
		$fingerprint = 'sha256:' . hash( 'sha256', $this->canonical_json( array( 'alg' => 'Ed25519', 'public_key' => $public_key ) ) );
		$pairings[ $user_code ] = array(
			'user_code'        => $user_code,
			'device_code_hash' => hash( 'sha256', $device_code ),
			'status'           => 'pending',
			'client'           => array(
				'name'           => $name,
				'device_name'    => sanitize_text_field( (string) ( $client['device_name'] ?? '' ) ),
				'broker'         => sanitize_text_field( (string) ( $client['broker'] ?? '' ) ),
				'broker_version' => sanitize_text_field( (string) ( $client['broker_version'] ?? '' ) ),
			),
			'key'              => array(
				'alg'         => 'Ed25519',
				'public_key'  => $public_key,
				'fingerprint' => $fingerprint,
			),
			'scopes'           => $scopes,
			'created_at'       => gmdate( 'c' ),
			'expires_at'       => $expires_at,
		);
		update_option( self::DEVICE_PAIRING_OPTION, $this->prune_device_pairings( $pairings ), false );

		$verification_uri = admin_url( 'admin.php?page=magick-ai-adapter-pair' );

		$this->emit_operation_event( 'adapter.device_pairing.start', $started, null, array( 'status_detail' => 'pending' ) );

		return new WP_REST_Response(
			array(
				'device_code'               => $device_code,
				'user_code'                 => $user_code,
				'verification_uri'          => $verification_uri,
				'verification_uri_complete' => add_query_arg( 'user_code', rawurlencode( $user_code ), $verification_uri ),
				'expires_in'                => self::DEVICE_PAIRING_TTL,
				'interval'                  => 3,
			),
			201
		);
	}

	/**
	 * Polls a device pairing session.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function poll_device_pairing( WP_REST_Request $request ) {
		$started = microtime( true );
		$body = $this->request_json_body( $request );
		if ( is_wp_error( $body ) ) {
			$this->emit_operation_event( 'adapter.device_pairing.poll', $started, $body );
			return $body;
		}

		$device_code = sanitize_text_field( (string) ( $body['device_code'] ?? '' ) );
		$pairing     = $this->device_pairing_by_device_code( $device_code );

		if ( empty( $pairing ) || time() > (int) ( $pairing['expires_at'] ?? 0 ) ) {
			$error = new WP_Error(
				'magick_ai_adapter_device_pairing_expired',
				__( 'Device pairing is expired or invalid.', 'magick-ai-adapter' ),
				array( 'status' => 401 )
			);
			$this->emit_operation_event( 'adapter.device_pairing.poll', $started, $error );
			return $error;
		}

		if ( 'rejected' === (string) ( $pairing['status'] ?? '' ) ) {
			$error = new WP_Error(
				'magick_ai_adapter_device_pairing_rejected',
				__( 'Device pairing was rejected.', 'magick-ai-adapter' ),
				array( 'status' => 403 )
			);
			$this->emit_operation_event( 'adapter.device_pairing.poll', $started, $error, array( 'status_detail' => 'rejected' ) );
			return $error;
		}

		if ( 'approved' !== (string) ( $pairing['status'] ?? '' ) ) {
			$this->emit_operation_event( 'adapter.device_pairing.poll', $started, null, array( 'status_detail' => 'pending' ) );
			return new WP_REST_Response(
				array(
					'ok'      => false,
					'status'  => 'pending',
					'message' => __( 'Device pairing is still pending approval.', 'magick-ai-adapter' ),
				),
				202
			);
		}

		$scopes = is_array( $pairing['scopes_effective'] ?? null ) ? $pairing['scopes_effective'] : array();

		$this->emit_operation_event( 'adapter.device_pairing.poll', $started, null, array( 'status_detail' => 'approved' ) );

		return new WP_REST_Response(
			array(
				'ok'               => true,
				'connection_id'    => (string) ( $pairing['connection_id'] ?? '' ),
				'key_id'           => (string) ( $pairing['key_id'] ?? '' ),
				'site_url'         => home_url(),
				'adapter_base_url' => rest_url( self::NAMESPACE ),
				'scopes_effective' => array_values( $scopes ),
			),
			200
		);
	}

	/**
	 * Lists registered client keys for the current administrator.
	 *
	 * @return WP_REST_Response
	 */
	public function list_client_keys(): WP_REST_Response {
		$user_id = get_current_user_id();
		$records = array();
		foreach ( $this->client_key_records() as $record ) {
			if ( $user_id === (int) ( $record['user_id'] ?? 0 ) ) {
				$records[] = $this->public_client_key_record( $record );
			}
		}

		return new WP_REST_Response( array( 'key_pairs' => $records ), 200 );
	}

	/**
	 * Revokes a client key.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function revoke_client_key( WP_REST_Request $request ) {
		$key_id = sanitize_text_field( (string) $request['key_id'] );
		$result = $this->revoke_client_key_by_id( $key_id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Revokes a client key by id for a user.
	 *
	 * @param string $key_id Key id.
	 * @param int    $user_id User id.
	 * @return array<string,mixed>|WP_Error
	 */
	public function revoke_client_key_by_id( string $key_id, int $user_id ) {
		$key_id = sanitize_text_field( $key_id );
		$keys   = $this->client_key_records();
		$record = is_array( $keys[ $key_id ] ?? null ) ? $keys[ $key_id ] : array();
		if ( empty( $record ) || $user_id !== (int) ( $record['user_id'] ?? 0 ) ) {
			return new WP_Error(
				'magick_ai_adapter_client_key_not_found',
				__( 'Client key was not found for the current user.', 'magick-ai-adapter' ),
				array( 'status' => 404 )
			);
		}

		$record['revoked_at'] = gmdate( 'c' );
		$keys[ $key_id ]     = $record;
		update_option( self::CLIENT_KEYS_OPTION, $keys, false );

		return $this->public_client_key_record( $record );
	}

	/**
	 * Returns a device pairing record by user code for admin display.
	 *
	 * @param string $user_code User code.
	 * @return array<string,mixed>
	 */
	public function admin_device_pairing( string $user_code ): array {
		$user_code = strtoupper( sanitize_text_field( $user_code ) );
		$pairings  = $this->device_pairings();
		$pairing   = is_array( $pairings[ $user_code ] ?? null ) ? $pairings[ $user_code ] : array();
		if ( ! empty( $pairing ) && time() <= (int) ( $pairing['expires_at'] ?? 0 ) ) {
			return $pairing;
		}

		return array();
	}

	/**
	 * Approves a device pairing for the current administrator.
	 *
	 * @param string $user_code User code.
	 * @return array<string,mixed>|WP_Error
	 */
	public function approve_device_pairing( string $user_code ) {
		$started = microtime( true );
		$user_code = strtoupper( sanitize_text_field( $user_code ) );
		$pairings  = $this->device_pairings();
		$pairing   = is_array( $pairings[ $user_code ] ?? null ) ? $pairings[ $user_code ] : array();
		if ( empty( $pairing ) || time() > (int) ( $pairing['expires_at'] ?? 0 ) ) {
			$error = new WP_Error( 'magick_ai_adapter_pairing_not_found', __( 'Device pairing was not found or expired.', 'magick-ai-adapter' ) );
			$this->emit_operation_event( 'adapter.device_pairing.approve', $started, $error );
			return $error;
		}

		$user_id       = get_current_user_id();
		$key           = is_array( $pairing['key'] ?? null ) ? $pairing['key'] : array();
		$client        = is_array( $pairing['client'] ?? null ) ? $pairing['client'] : array();
		$public_key    = (string) ( $key['public_key'] ?? '' );
		$fingerprint   = (string) ( $key['fingerprint'] ?? '' );
		$key_id        = 'mk_' . substr( hash( 'sha256', rest_url( self::NAMESPACE ) . '|' . $user_id . '|' . $fingerprint ), 0, 24 );
		$connection_id = 'mag_conn_' . substr( hash( 'sha256', home_url() . '|' . $key_id ), 0, 24 );
		$record        = array(
			'key_id'        => $key_id,
			'connection_id' => $connection_id,
			'user_id'       => $user_id,
			'client_name'   => (string) ( $client['name'] ?? '' ),
			'device_name'   => (string) ( $client['device_name'] ?? '' ),
			'broker'        => (string) ( $client['broker'] ?? '' ),
			'broker_version' => (string) ( $client['broker_version'] ?? '' ),
			'public_key'    => $public_key,
			'fingerprint'   => $fingerprint,
			'scopes'        => is_array( $pairing['scopes'] ?? null ) ? array_values( $pairing['scopes'] ) : array(),
			'created_at'    => gmdate( 'c' ),
			'last_used_at'  => '',
			'revoked_at'    => '',
		);

		$keys            = $this->client_key_records();
		$keys[ $key_id ] = $record;
		update_option( self::CLIENT_KEYS_OPTION, $keys, false );

		$pairing['status']           = 'approved';
		$pairing['approved_at']      = gmdate( 'c' );
		$pairing['approved_user_id'] = $user_id;
		$pairing['key_id']           = $key_id;
		$pairing['connection_id']    = $connection_id;
		$pairing['scopes_effective'] = $record['scopes'];
		$pairings[ $user_code ]      = $pairing;
		update_option( self::DEVICE_PAIRING_OPTION, $this->prune_device_pairings( $pairings ), false );

		$this->emit_operation_event( 'adapter.device_pairing.approve', $started, null );

		return $this->public_client_key_record( $record );
	}

	/**
	 * Rejects a device pairing.
	 *
	 * @param string $user_code User code.
	 * @return bool
	 */
	public function reject_device_pairing( string $user_code ): bool {
		$started = microtime( true );
		$user_code = strtoupper( sanitize_text_field( $user_code ) );
		$pairings  = $this->device_pairings();
		if ( ! is_array( $pairings[ $user_code ] ?? null ) ) {
			$this->emit_operation_event(
				'adapter.device_pairing.reject',
				$started,
				new WP_Error( 'magick_ai_adapter_pairing_not_found', __( 'Device pairing was not found or expired.', 'magick-ai-adapter' ) )
			);
			return false;
		}

		$pairings[ $user_code ]['status']      = 'rejected';
		$pairings[ $user_code ]['rejected_at'] = gmdate( 'c' );
		update_option( self::DEVICE_PAIRING_OPTION, $this->prune_device_pairings( $pairings ), false );

		$this->emit_operation_event( 'adapter.device_pairing.reject', $started, null );

		return true;
	}

	/**
	 * Returns public client key records for an admin user.
	 *
	 * @param int $user_id User id.
	 * @return array<int,array<string,mixed>>
	 */
	public function admin_client_keys( int $user_id ): array {
		$records = array();
		foreach ( $this->client_key_records() as $record ) {
			if ( $user_id === (int) ( $record['user_id'] ?? 0 ) ) {
				$records[] = $this->public_client_key_record( $record );
			}
		}

		return $records;
	}

	/**
	 * Returns decoded JSON request body.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>|WP_Error
	 */
	private function request_json_body( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( is_array( $params ) ) {
			return $params;
		}

		return new WP_Error(
			'magick_ai_adapter_json_body_required',
			__( 'A JSON request body is required.', 'magick-ai-adapter' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Builds the non-secret connection manifest and digest.
	 *
	 * @param int $user_id User id.
	 * @return array<string,mixed>
	 */
	private function connection_manifest_payload( int $user_id ): array {
		$user     = $user_id > 0 ? get_userdata( $user_id ) : wp_get_current_user();
		$username = $user && $user->exists() ? (string) $user->user_login : '';
		$base     = array(
			'schema_version' => 'magick_ai_adapter_connection.v1',
			'kind'           => 'magick.ai/wordpress-adapter-connection',
			'manifest_id'    => 'mag_manifest_' . substr( hash( 'sha256', rest_url( self::NAMESPACE ) . '|' . $username ), 0, 24 ),
			'connection_id'  => 'local-wordpress',
			'site'           => array(
				'site_url'         => home_url(),
				'rest_url'         => rest_url(),
				'adapter_base_url' => rest_url( self::NAMESPACE ),
				'admin_origin'     => $this->url_origin( admin_url() ),
				'plugin'           => array(
					'slug'    => 'magick-ai-adapter',
					'version' => MAGICK_AI_ADAPTER_VERSION,
				),
			),
			'user'           => array(
				'username' => $username,
			),
			'auth'           => array(
				'preferred_method'  => 'key_pair_device_pairing',
				'supported_methods' => array(
					array(
						'type'            => 'key_pair_device_pairing',
						'protocol'        => 'magick-key-pair-auth.v1',
						'key_type'        => 'ed25519',
						'secret_delivery' => 'none',
						'requires_admin_approval' => true,
					),
					array(
						'type'            => 'wp_application_password_basic',
						'fallback_only'   => true,
						'secret_slot'     => 'wordpress_application_password',
						'secret_delivery' => 'dedicated_secret_field_or_vault_only',
					),
				),
			),
			'urls'           => array(
				'health'       => rest_url( self::NAMESPACE . '/health' ),
				'help'         => rest_url( self::NAMESPACE . '/help' ),
				'capabilities' => rest_url( self::NAMESPACE . '/capabilities' ),
				'device_start' => rest_url( self::NAMESPACE . '/connect/device/start' ),
				'device_poll'  => rest_url( self::NAMESPACE . '/connect/device/poll' ),
				'key_pairs'    => rest_url( self::NAMESPACE . '/connection/key-pairs' ),
			),
			'capabilities'   => array(
				'read'  => array(
					'requires_adapter_auth' => true,
				),
				'write' => array(
					'mode'                         => 'proposal_only',
					'direct_wordpress_write_allowed' => false,
					'requires_magick_ai_core'      => true,
				),
			),
		);

		$base['integrity'] = array(
			'canonicalization' => 'recursive_ksort_json',
			'manifest_sha256'  => 'sha256:' . hash( 'sha256', $this->canonical_json( $base ) ),
		);

		return $base;
	}

	/**
	 * Returns scheme/host/port origin for a URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function url_origin( string $url ): string {
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = wp_parse_url( $url, PHP_URL_HOST );
		$port   = wp_parse_url( $url, PHP_URL_PORT );
		if ( ! is_string( $scheme ) || ! is_string( $host ) ) {
			return '';
		}

		return strtolower( $scheme . '://' . $host . ( is_int( $port ) ? ':' . $port : '' ) );
	}

	/**
	 * Returns canonical JSON for digesting simple associative arrays.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function canonical_json( $value ): string {
		$value = $this->sort_array_keys_recursive( $value );
		$json  = wp_json_encode( $value, JSON_UNESCAPED_SLASHES );
		return is_string( $json ) ? $json : '';
	}

	/**
	 * Sorts associative array keys recursively.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private function sort_array_keys_recursive( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		foreach ( $value as $key => $child ) {
			$value[ $key ] = $this->sort_array_keys_recursive( $child );
		}

		if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
			ksort( $value );
		}

		return $value;
	}

	/**
	 * Filters requested client scopes to the current adapter contract.
	 *
	 * @param array<int,mixed> $requested Requested scopes.
	 * @return array<int,string>
	 */
	private function connection_requested_scopes( array $requested ): array {
		$allowed = array(
			'magick.read'    => true,
			'magick.propose' => true,
			'magick.status'  => true,
		);
		$scopes  = array();

		foreach ( $requested as $scope ) {
			$scope = sanitize_text_field( (string) $scope );
			if ( isset( $allowed[ $scope ] ) ) {
				$scopes[] = $scope;
			}
		}

		return ! empty( $scopes ) ? array_values( array_unique( $scopes ) ) : array_keys( $allowed );
	}

	/**
	 * Returns pending device pairing records.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function device_pairings(): array {
		$pairings = get_option( self::DEVICE_PAIRING_OPTION, array() );
		return is_array( $pairings ) ? $pairings : array();
	}

	/**
	 * Removes expired pending device pairing records.
	 *
	 * @param array<string,array<string,mixed>> $pairings Pairings.
	 * @return array<string,array<string,mixed>>
	 */
	private function prune_device_pairings( array $pairings ): array {
		$now = time();
		foreach ( $pairings as $user_code => $pairing ) {
			if ( $now > (int) ( $pairing['expires_at'] ?? 0 ) && 'approved' !== (string) ( $pairing['status'] ?? '' ) ) {
				unset( $pairings[ $user_code ] );
			}
		}

		return $pairings;
	}

	/**
	 * Returns a pending device pairing by device code.
	 *
	 * @param string $device_code Device code.
	 * @return array<string,mixed>
	 */
	private function device_pairing_by_device_code( string $device_code ): array {
		if ( '' === $device_code ) {
			return array();
		}

		$hash = hash( 'sha256', $device_code );
		foreach ( $this->device_pairings() as $pairing ) {
			if ( hash_equals( (string) ( $pairing['device_code_hash'] ?? '' ), $hash ) ) {
				return $pairing;
			}
		}

		return array();
	}

	/**
	 * Returns stored client keys.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function client_key_records(): array {
		$records = get_option( self::CLIENT_KEYS_OPTION, array() );
		return is_array( $records ) ? $records : array();
	}

	/**
	 * Returns a public-safe client key record.
	 *
	 * @param array<string,mixed> $record Record.
	 * @return array<string,mixed>
	 */
	private function public_client_key_record( array $record ): array {
		return array(
			'key_id'        => (string) ( $record['key_id'] ?? '' ),
			'connection_id' => (string) ( $record['connection_id'] ?? '' ),
			'client_name'   => (string) ( $record['client_name'] ?? '' ),
			'device_name'   => (string) ( $record['device_name'] ?? '' ),
			'fingerprint'   => (string) ( $record['fingerprint'] ?? '' ),
			'scopes'        => is_array( $record['scopes'] ?? null ) ? array_values( $record['scopes'] ) : array(),
			'created_at'    => (string) ( $record['created_at'] ?? '' ),
			'last_used_at'  => (string) ( $record['last_used_at'] ?? '' ),
			'revoked_at'    => (string) ( $record['revoked_at'] ?? '' ),
		);
	}

	/**
	 * Authenticates an Adapter request signed by a registered Ed25519 key.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	private function authenticate_signed_request( WP_REST_Request $request ): bool {
		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			return false;
		}

		$credentials = $this->signed_request_credentials( $request );

		$key_id         = $credentials['key_id'];
		$timestamp      = $credentials['timestamp'];
		$nonce          = $credentials['nonce'];
		$content_sha256 = $credentials['content_sha256'];
		$signature_alg  = $credentials['signature_alg'];
		$signature      = $credentials['signature'];

		if ( '' === $key_id || '' === $timestamp || '' === $nonce || '' === $content_sha256 || 'Ed25519' !== $signature_alg || '' === $signature ) {
			return false;
		}

		$keys   = $this->client_key_records();
		$record = is_array( $keys[ $key_id ] ?? null ) ? $keys[ $key_id ] : array();
		if ( empty( $record ) || '' !== (string) ( $record['revoked_at'] ?? '' ) ) {
			return false;
		}

		$user_id = (int) ( $record['user_id'] ?? 0 );
		$user    = get_userdata( $user_id );
		if ( ! $user || ! user_can( $user, 'manage_options' ) || ! $this->client_key_scope_allows_request( $record, $request ) ) {
			return false;
		}

		$timestamp_epoch = strtotime( $timestamp );
		if ( false === $timestamp_epoch || abs( time() - $timestamp_epoch ) > self::SIGNATURE_NONCE_TTL ) {
			return false;
		}

		$expected_hash = 'sha256:' . hash( 'sha256', (string) $request->get_body() );
		if ( ! hash_equals( $expected_hash, $content_sha256 ) ) {
			return false;
		}

		$nonce_key = 'maa_sig_nonce_' . md5( $key_id . '|' . $nonce );
		if ( get_transient( $nonce_key ) ) {
			return false;
		}

		$public_key = $this->base64url_decode( (string) ( $record['public_key'] ?? '' ) );
		$signature_bytes = $this->base64url_decode( $signature );
		$canonical = $this->signed_request_canonical_string( $request, $timestamp, $nonce, $content_sha256 );
		if ( 32 !== strlen( $public_key ) || 64 !== strlen( $signature_bytes ) || ! sodium_crypto_sign_verify_detached( $signature_bytes, $canonical, $public_key ) ) {
			return false;
		}

		set_transient( $nonce_key, 1, self::SIGNATURE_NONCE_TTL );
		$record['last_used_at'] = gmdate( 'c' );
		$keys[ $key_id ]        = $record;
		update_option( self::CLIENT_KEYS_OPTION, $keys, false );
		wp_set_current_user( $user_id );

		return true;
	}

	/**
	 * Returns request signature credentials from X-Magick headers or Authorization.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,string>
	 */
	private function signed_request_credentials( WP_REST_Request $request ): array {
		$credentials = array(
			'key_id'         => sanitize_text_field( (string) $request->get_header( 'x_magick_key_id' ) ),
			'timestamp'      => sanitize_text_field( (string) $request->get_header( 'x_magick_timestamp' ) ),
			'nonce'          => sanitize_text_field( (string) $request->get_header( 'x_magick_nonce' ) ),
			'content_sha256' => sanitize_text_field( (string) $request->get_header( 'x_magick_content_sha256' ) ),
			'signature_alg'  => sanitize_text_field( (string) $request->get_header( 'x_magick_signature_alg' ) ),
			'signature'      => sanitize_text_field( (string) $request->get_header( 'x_magick_signature' ) ),
		);

		if ( '' !== $credentials['key_id'] && '' !== $credentials['signature'] ) {
			return $credentials;
		}

		$authorization = (string) $request->get_header( 'authorization' );
		if ( ! preg_match( '/^Magick-Signature\s+(.+)$/i', $authorization, $matches ) ) {
			return $credentials;
		}

		$parts = array();
		foreach ( explode( ',', $matches[1] ) as $piece ) {
			$pair = explode( '=', trim( $piece ), 2 );
			if ( 2 !== count( $pair ) ) {
				continue;
			}
			$parts[ strtolower( trim( $pair[0] ) ) ] = trim( trim( $pair[1] ), '"' );
		}

		return array(
			'key_id'         => sanitize_text_field( (string) ( $parts['key_id'] ?? '' ) ),
			'timestamp'      => sanitize_text_field( (string) ( $parts['timestamp'] ?? '' ) ),
			'nonce'          => sanitize_text_field( (string) ( $parts['nonce'] ?? '' ) ),
			'content_sha256' => sanitize_text_field( (string) ( $parts['content_sha256'] ?? '' ) ),
			'signature_alg'  => sanitize_text_field( (string) ( $parts['alg'] ?? '' ) ),
			'signature'      => sanitize_text_field( (string) ( $parts['signature'] ?? '' ) ),
		);
	}

	/**
	 * Returns the canonical string signed by local clients.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $timestamp Timestamp.
	 * @param string          $nonce Nonce.
	 * @param string          $content_sha256 Body hash.
	 * @return string
	 */
	private function signed_request_canonical_string( WP_REST_Request $request, string $timestamp, string $nonce, string $content_sha256 ): string {
		return implode(
			"\n",
			array(
				'MAGICK-AI-ADAPTER-V1',
				strtoupper( $request->get_method() ),
				$request->get_route(),
				$this->canonical_json( $request->get_query_params() ),
				$timestamp,
				$nonce,
				$content_sha256,
			)
		);
	}

	/**
	 * Returns whether client key scopes allow a request.
	 *
	 * @param array<string,mixed> $record Record.
	 * @param WP_REST_Request     $request Request.
	 * @return bool
	 */
	private function client_key_scope_allows_request( array $record, WP_REST_Request $request ): bool {
		$scopes = array_fill_keys( is_array( $record['scopes'] ?? null ) ? $record['scopes'] : array(), true );
		$route  = $request->get_route();
		$method = strtoupper( $request->get_method() );

		if ( false !== strpos( $route, '/proposals' ) || false !== strpos( $route, '/execute-approved-proposal' ) ) {
			return ! empty( $scopes['magick.propose'] );
		}

		if ( 'GET' === $method && ( false !== strpos( $route, '/health' ) || false !== strpos( $route, '/help' ) || false !== strpos( $route, '/capabilities' ) || false !== strpos( $route, '/connection/' ) ) ) {
			return ! empty( $scopes['magick.status'] );
		}

		return ! empty( $scopes['magick.read'] );
	}

	/**
	 * Encodes base64url.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function base64url_encode( string $value ): string {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	/**
	 * Decodes base64url.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function base64url_decode( string $value ): string {
		$decoded = base64_decode( strtr( $value, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $value ) % 4 ) % 4 ), true );
		return is_string( $decoded ) ? $decoded : '';
	}

	/**
	 * Returns adapter health.
	 *
	 * @return WP_REST_Response
	 */
	public function health(): WP_REST_Response {
		$routes = rest_get_server()->get_routes();

		return new WP_REST_Response(
			array(
				'adapter'                => 'magick-ai-adapter',
				'version'                => MAGICK_AI_ADAPTER_VERSION,
				'core_capabilities'      => isset( $routes['/magick-ai-core/v1/capabilities'] ),
				'abilities_catalog'      => isset( $routes['/wp-abilities/v1/abilities'] ),
				'core_proxy_execute'     => false,
				'commit_execution'       => false,
				'approval_proxy_enabled' => false,
				'approval_surface'       => 'magick_ai_core_admin',
				'core_app_token_configured' => '' !== $this->core_app_token(),
				'ai_request_log_context_fields' => array(
					'proposal_id',
					'correlation_id',
					'external_thread_id',
					'openclaw_thread_id',
					'ability_id',
					'adapter_request_id',
					'adapter_route',
					'ai_provider',
					'ai_model',
					'governance_source',
					'magick_ai_core.proposal_id',
					'magick_ai_core.correlation_id',
				),
				'core_app_token_required_scopes' => array(
					'capabilities:read',
					'proposals:read',
					'proposals:create',
					'commit:preflight',
				),
				'approved_proposal_execution_routes' => array(
					'POST /execute-approved-proposal',
					'POST /proposals/{proposal_id}/execute',
					'POST /proposals/{proposal_id}/approve-and-execute',
				),
				'allowed_execute_ability_ids' => self::allowed_execute_ability_ids(),
				'execution_input_contract' => array(
					'single' => 'proposal.input, with ability-specific required fields',
					'batch'  => 'proposal.input.write_actions[].target_ability_id + proposal.input.write_actions[].input',
					'max_actions' => self::MAX_EXECUTION_ACTIONS,
					'partial_success' => false,
				),
				'plan_proposal_routes' => array(
					'POST /proposals/from-plan',
				),
				'allowed_plan_ability_ids' => array_keys( self::$allowed_plan_ability_ids ),
				'proposal_status_routes' => array(
					'GET /proposals',
					'GET /proposals/{proposal_id}',
				),
				'diagnostics'             => $this->diagnostics_contract(),
				'supported_guidance'     => array(
					'read'  => array(
						'governance_mode'           => 'direct_read',
						'execution_surface'         => 'wp_abilities_rest',
						'read_policy_values'        => array(
							'direct_read_public',
							'direct_read_internal',
							'direct_read_sensitive',
						),
						'sensitivity_values'        => array( 'public', 'internal', 'sensitive' ),
						'redaction_required_field' => 'redaction_required',
						'read_audit_mode'           => 'adapter_read_envelope',
					),
					'proposal_status' => array(
						'governance_mode'     => 'core_proposal_read_proxy',
						'execution_surface'   => 'magick_ai_core_rest',
						'core_required_scope' => 'proposals:read',
						'approval_proxy_enabled' => false,
						'approval_surface'    => 'magick_ai_core_admin',
						'proposal_status_routes' => array(
							'GET /proposals',
							'GET /proposals/{proposal_id}',
						),
					),
					'write' => array(
						'governance_mode'   => 'proposal_required',
						'execution_surface' => 'adapter_after_core_preflight',
					),
					'approved_proposal_execution' => array(
						'governance_mode'      => 'core_approved_commit_preflight_required',
						'execution_surface'    => 'wp_abilities_rest_after_core_preflight',
						'core_required_scope'  => 'commit:preflight',
						'core_commit_execution' => false,
						'allowed_ability_ids'  => self::allowed_execute_ability_ids(),
						'execution_input_contract' => array(
							'single' => 'proposal.input',
							'batch'  => 'proposal.input.write_actions[]',
							'max_actions' => self::MAX_EXECUTION_ACTIONS,
							'partial_success' => false,
						),
					),
					'unified_approve_and_execute' => array(
						'governance_mode'      => 'core_approval_then_adapter_execution',
						'execution_surface'    => 'wp_abilities_rest_after_core_preflight',
						'approval_surface'     => 'magick_ai_adapter_unified_action',
						'core_commit_execution' => false,
						'allowed_ability_ids'  => self::allowed_execute_ability_ids(),
						'execution_input_contract' => array(
							'single' => 'proposal.input',
							'batch'  => 'proposal.input.write_actions[]',
							'max_actions' => self::MAX_EXECUTION_ACTIONS,
							'partial_success' => false,
						),
					),
					'plan_to_proposal' => array(
						'governance_mode'   => 'direct_read_plan_to_core_proposals',
						'execution_surface' => 'magick_ai_core_rest',
						'core_required_scope' => 'proposals:create',
						'core_route'        => 'POST /magick-ai-core/v1/proposals/from-plan',
						'plan_fields_preserved' => array(
							'batch_id',
							'issue_types',
							'post_ids',
							'attachment_ids',
							'write_actions',
							'preview',
							'risk',
							'requires_approval',
							'commit_execution',
							'dry_run',
							'manual_review',
							'skipped_destructive_candidates',
							'issue_counts',
							'action_count',
						),
					),
				),
				'permission_capability'  => 'manage_options',
				'current_user_authorized' => $this->can_use_adapter(),
				'adapter_base_url'        => rest_url( self::NAMESPACE ),
				'health_url'              => rest_url( self::NAMESPACE . '/health' ),
				'help_url'                => rest_url( self::NAMESPACE . '/help' ),
				'capabilities_url'        => rest_url( self::NAMESPACE . '/capabilities' ),
				'proposal_list_url'       => rest_url( self::NAMESPACE . '/proposals' ),
				'proposal_detail_url'     => rest_url( self::NAMESPACE . '/proposals/{proposal_id}' ),
				'proposal_approve_url'    => rest_url( self::NAMESPACE . '/proposals/{proposal_id}/approve' ),
				'proposal_reject_url'     => rest_url( self::NAMESPACE . '/proposals/{proposal_id}/reject' ),
				'auth'                    => array(
					'type'        => 'wordpress_rest_application_password',
					'header'      => 'Authorization: Basic base64(username:application_password)',
					'recommended' => 'dedicated_administrator_application_password_for_initial_openclaw_poc',
				),
				'read_shortcuts'          => self::read_shortcuts(),
			),
			200
		);
	}

	/**
	 * Returns adapter route help.
	 *
	 * @return WP_REST_Response
	 */
	public function help(): WP_REST_Response {
		$route_groups = $this->help_route_groups();

		return new WP_REST_Response(
			array(
				'adapter'       => 'magick-ai-adapter',
				'namespace'     => self::NAMESPACE,
				'base_url'      => rest_url( self::NAMESPACE ),
				'auth'          => array(
					'type'        => 'wordpress_rest_application_password',
					'capability'  => 'manage_options',
					'header'      => 'Authorization: Basic base64(username:application_password)',
				),
				'routes'        => $this->help_routes_flat( $route_groups ),
				'route_groups'  => $route_groups,
				'openclaw_recipes' => $this->openclaw_recipes(),
				'core_required_scopes' => array(
					'proposal_status'  => 'proposals:read',
					'proposal_create'  => 'proposals:create',
					'proposal_from_plan' => 'proposals:create',
					'commit_preflight' => 'commit:preflight',
				),
				'approval_proxy_enabled' => false,
				'approval_surface' => 'magick_ai_core_admin',
				'core_app_token_configured' => '' !== $this->core_app_token(),
				'ai_request_log_context' => array(
					'accepted_param' => 'log_context',
					'query_fields'   => array(
						'proposal_id',
						'correlation_id',
						'external_thread_id',
						'openclaw_thread_id',
					),
					'target'         => 'wpai_request_log_context',
					'required_fields' => array(
						'proposal_id',
						'correlation_id',
						'ability_id',
						'adapter_request_id',
						'adapter_route',
						'ai_provider',
						'ai_model',
						'governance_source',
					),
				),
				'core_app_token_required_scopes' => array(
					'capabilities:read',
					'proposals:read',
					'proposals:create',
					'commit:preflight',
				),
				'approved_proposal_execution_routes' => array(
					'POST /execute-approved-proposal',
					'POST /proposals/{proposal_id}/execute',
					'POST /proposals/{proposal_id}/approve-and-execute',
				),
				'allowed_execute_ability_ids' => self::allowed_execute_ability_ids(),
				'execution_input_contract' => array(
					'single' => 'proposal.input, with ability-specific required fields',
					'batch'  => 'proposal.input.write_actions[].target_ability_id + proposal.input.write_actions[].input',
					'max_actions' => self::MAX_EXECUTION_ACTIONS,
					'partial_success' => false,
				),
				'plan_proposal_routes' => array(
					'POST /proposals/from-plan',
				),
				'allowed_plan_ability_ids' => array_keys( self::$allowed_plan_ability_ids ),
				'proposal_status_routes' => array(
					'GET /proposals',
					'GET /proposals/{proposal_id}',
				),
				'diagnostics'    => $this->diagnostics_contract(),
				'non_goals'     => array(
					'approval_proxy_enabled' => false,
					'reject_proxy_enabled'   => false,
					'workflow_runtime'       => false,
					'mcp_runtime'            => false,
					'final_commit_execution' => false,
				),
			),
			200
		);
	}

	/**
	 * Returns human-readable route groups for help output.
	 *
	 * @return array<string,array<int,string>>
	 */
	private function help_route_groups(): array {
		return array(
			'connection'      => array(
				'GET /health',
				'GET /help',
				'GET /capabilities',
				'GET /connection/manifest',
				'POST /connect/device/start',
				'POST /connect/device/poll',
				'GET /connection/key-pairs',
				'DELETE /connection/key-pairs/{key_id}',
			),
			'read_shortcuts'  => $this->help_read_shortcuts(),
			'generic_read'    => array(
				'POST /run-read-ability',
				'POST /media-metadata-optimization',
			),
			'media_derivative_cloud' => array(
				'POST /media-derivative-runs',
				'GET /media-derivative-runs/{run_id}',
				'GET /media-derivative-runs/{run_id}/result',
				'GET /media-derivative-artifacts/{artifact_id}/preview',
				'POST /media-derivative-proposal-payload',
			),
			'provider_log_correlation' => array(
				'POST /ai-provider-log-correlation-smoke',
			),
			'proposal_status' => array(
				'GET /proposals',
				'GET /proposals/{proposal_id}',
			),
			'governance'      => array(
				'POST /proposals',
				'POST /proposals/from-plan',
				'POST /proposals/{proposal_id}/approve',
				'POST /proposals/{proposal_id}/reject',
				'POST /proposals/{proposal_id}/commit-preflight',
				'POST /execute-approved-proposal',
				'POST /proposals/{proposal_id}/execute',
				'POST /proposals/{proposal_id}/approve-and-execute',
			),
		);
	}

	/**
	 * Returns machine-readable OpenClaw playbooks for fixed handoff flows.
	 *
	 * These are channel instructions only. Ability definitions stay in
	 * Abilities/Toolbox and governance truth stays in Core.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function openclaw_recipes(): array {
		return array(
			'article_draft_plan' => array(
				'title'       => 'Article draft plan',
				'description' => 'Build a reviewed Toolbox article_write_plan, forward it to Core, then execute only the Core-approved draft creation.',
				'entrypoint_ability_id' => 'magick-ai-toolbox/build-article-write-plan',
				'plan_ability_id' => 'magick-ai-toolbox/build-article-write-plan',
				'final_write_ability_id' => 'magick-ai/create-draft',
				'steps'       => array(
					array(
						'order'      => 1,
						'route'      => 'POST /run-read-ability',
						'ability_id' => 'magick-ai-toolbox/build-article-write-plan',
						'purpose'    => 'Build the reviewed article_write_plan without writing WordPress content.',
					),
					array(
						'order'   => 2,
						'route'   => 'POST /proposals/from-plan',
						'purpose' => 'Forward the Toolbox plan to Core plan intake with plan_ability_id and caller metadata.',
					),
					array(
						'order'   => 3,
						'route'   => 'GET /proposals/{proposal_id}',
						'purpose' => 'Poll Core proposal status through Adapter.',
					),
					array(
						'order'   => 4,
						'route'   => 'POST /proposals/{proposal_id}/approve-and-execute',
						'purpose' => 'Approve through Core when pending, run commit-preflight, then execute the allowlisted draft write.',
					),
				),
				'guardrails'   => array(
					'artifact_type'            => 'article_write_plan',
					'core_preflight_required'  => true,
					'draft_only'               => true,
					'publish_allowed'          => false,
					'core_proxy_execute'       => false,
					'commit_execution'         => false,
					'cloud_control_plane'      => false,
					'generic_write_executor'   => false,
				),
				'docs'         => 'docs/openclaw-article-draft-plan-recipe.md',
			),
			'article_batch_draft_plan' => array(
				'title'       => 'Article batch draft plan',
				'description' => 'Build a reviewed Toolbox article_batch_write_plan, forward it to Core as one batch proposal, then execute only Core-approved draft creation actions.',
				'entrypoint_ability_id' => 'magick-ai-toolbox/build-article-batch-write-plan',
				'plan_ability_id' => 'magick-ai-toolbox/build-article-batch-write-plan',
				'final_write_ability_id' => 'magick-ai/create-draft',
				'steps'       => array(
					array(
						'order'      => 1,
						'route'      => 'POST /run-read-ability',
						'ability_id' => 'magick-ai-toolbox/build-article-batch-write-plan',
						'purpose'    => 'Build a reviewed 2-5 draft article_batch_write_plan without writing WordPress content.',
					),
					array(
						'order'   => 2,
						'route'   => 'POST /proposals/from-plan',
						'purpose' => 'Forward the batch plan to Core plan intake with plan_ability_id and caller metadata.',
					),
					array(
						'order'   => 3,
						'route'   => 'GET /proposals/{proposal_id}',
						'purpose' => 'Poll the Core-owned batch proposal status through Adapter.',
					),
					array(
						'order'   => 4,
						'route'   => 'POST /proposals/{proposal_id}/approve-and-execute',
						'purpose' => 'Approve through Core when pending, run commit-preflight, then execute the allowlisted draft write_actions.',
					),
				),
				'guardrails'   => array(
					'artifact_type'            => 'article_batch_write_plan',
					'proposal_mode'            => 'batch',
					'batch_approval'           => true,
					'core_preflight_required'  => true,
					'draft_only'               => true,
					'publish_allowed'          => false,
					'core_proxy_execute'       => false,
					'commit_execution'         => false,
					'cloud_control_plane'      => false,
					'generic_write_executor'   => false,
				),
				'docs'         => 'docs/openclaw-article-batch-draft-plan-recipe.md',
			),
			'article_media_batch_plan' => array(
				'title'       => 'Article media batch plan',
				'description' => 'Build a reviewed Toolbox article_media_batch_write_plan with image-source candidates, forward it to Core as one batch proposal, then execute only Core-approved draft and media write actions.',
				'entrypoint_ability_id' => 'magick-ai-toolbox/build-article-media-batch-write-plan',
				'plan_ability_id' => 'magick-ai-toolbox/build-article-media-batch-write-plan',
				'final_write_ability_ids' => array(
					'magick-ai/create-draft',
					'magick-ai/upload-media-from-url',
					'magick-ai/update-media-details',
					'magick-ai/set-post-featured-image',
				),
				'steps'       => array(
					array(
						'order'      => 1,
						'route'      => 'POST /run-read-ability',
						'ability_id' => 'magick-ai-toolbox/search-image-source',
						'purpose'    => 'Collect image-source candidates and preserve attribution for operator review.',
					),
					array(
						'order'      => 2,
						'route'      => 'POST /run-read-ability',
						'ability_id' => 'magick-ai-toolbox/build-article-media-batch-write-plan',
						'purpose'    => 'Build a reviewed article_media_batch_write_plan without writing WordPress content or importing media.',
					),
					array(
						'order'   => 3,
						'route'   => 'POST /proposals/from-plan',
						'purpose' => 'Forward the media batch plan to Core plan intake with plan_ability_id and caller metadata.',
					),
					array(
						'order'   => 4,
						'route'   => 'GET /proposals/{proposal_id}',
						'purpose' => 'Poll the Core-owned batch proposal status through Adapter.',
					),
					array(
						'order'   => 5,
						'route'   => 'POST /proposals/{proposal_id}/approve-and-execute',
						'purpose' => 'Approve through Core when pending, run commit-preflight, then execute only allowlisted draft and media write_actions.',
					),
				),
				'guardrails'   => array(
					'artifact_type'            => 'article_media_batch_write_plan',
					'proposal_mode'            => 'batch',
					'batch_approval'           => true,
					'core_preflight_required'  => true,
					'publish_allowed'          => false,
					'image_source_attribution_required' => true,
					'core_proxy_execute'       => false,
					'commit_execution'         => false,
					'cloud_control_plane'      => false,
					'generic_write_executor'   => false,
				),
				'docs'         => 'docs/openclaw-article-media-batch-plan-recipe.md',
			),
			'content_discoverability_suggestions' => array(
				'title'       => 'Content discoverability suggestions',
				'description' => 'Validate Toolbox SEO/AEO/GEO context, build one suggestion-only brief, and return proposal-ready suggestions without writing WordPress data.',
				'entrypoint_ability_id' => 'magick-ai-toolbox/build-content-discoverability-brief',
				'steps'       => array(
					array(
						'order'      => 1,
						'route'      => 'GET /content-discoverability-validation or POST /run-read-ability',
						'ability_id' => 'magick-ai-toolbox/validate-content-discoverability-context',
						'purpose'    => 'Confirm the Toolbox content context is ready before using it.',
					),
					array(
						'order'      => 2,
						'route'      => 'GET /content-discoverability-context or POST /run-read-ability',
						'ability_id' => 'magick-ai-toolbox/get-content-discoverability-context',
						'purpose'    => 'Read operator-maintained SEO, AEO, GEO, brand voice, and forbidden-claims guidance.',
					),
					array(
						'order'      => 3,
						'route'      => 'GET /content-discoverability-brief?post_id={post_id} or POST /run-read-ability',
						'ability_id' => 'magick-ai-toolbox/build-content-discoverability-brief',
						'purpose'    => 'Build one suggestion-only brief for a post or supplied topic/title/content.',
					),
					array(
						'order'   => 4,
						'route'   => 'POST /proposals or POST /proposals/from-plan when a reviewed final write is needed',
						'purpose' => 'Use Core governance for any final WordPress write-like outcome.',
					),
				),
				'guardrails'   => array(
					'artifact_type'           => 'content_discoverability_brief',
					'write_posture'           => 'suggestion_only',
					'direct_wordpress_write'  => false,
					'core_preflight_required_for_writes' => true,
					'mutate_seo_meta'         => false,
					'mutate_slug'             => false,
					'mutate_excerpt'          => false,
					'mutate_schema'           => false,
					'mutate_media'            => false,
					'generic_write_executor'  => false,
				),
				'docs'         => 'docs/openclaw-content-discoverability-recipe.md',
			),
			'ai_article_draft_with_discoverability' => array(
				'title'       => 'AI article draft with discoverability',
				'description' => 'For natural-language article requests, build one Toolbox AI article writing pack with SEO/AEO/GEO context and drafting guardrails before OpenClaw writes the candidate article.',
				'entrypoint_ability_id' => 'magick-ai-toolbox/build-ai-article-writing-pack',
				'steps'       => array(
					array(
						'order'      => 1,
						'route'      => 'GET /article-writing-pack?topic={topic} or POST /run-read-ability',
						'ability_id' => 'magick-ai-toolbox/build-ai-article-writing-pack',
						'purpose'    => 'Build one suggestion-only writing pack from Toolbox content context, validation, discoverability brief, style rules, and forbidden claims.',
					),
					array(
						'order'   => 2,
						'route'   => 'OpenClaw local drafting step',
						'purpose' => 'Draft article content, SEO title/description, AEO answer summary, FAQ, GEO summary, and proposal candidates from the pack without writing WordPress data.',
					),
					array(
						'order'      => 3,
						'route'      => 'POST /run-read-ability',
						'ability_id' => 'magick-ai-toolbox/build-article-write-plan',
						'purpose'    => 'After operator review, convert the reviewed draft into a Core-ready article_write_plan.',
					),
					array(
						'order'   => 4,
						'route'   => 'POST /proposals/from-plan when a reviewed final write is needed',
						'purpose' => 'Use Core governance for any final WordPress write-like outcome.',
					),
				),
				'guardrails'   => array(
					'artifact_type'           => 'ai_article_writing_pack',
					'write_posture'           => 'suggestion_only',
					'direct_wordpress_write'  => false,
					'provider_execution'      => 'none',
					'core_preflight_required_for_writes' => true,
					'mutate_post_content'     => false,
					'mutate_seo_meta'         => false,
					'mutate_slug'             => false,
					'mutate_excerpt'          => false,
					'mutate_schema'           => false,
					'publish_allowed'         => false,
					'generic_write_executor'  => false,
				),
				'docs'         => 'docs/openclaw-ai-article-writing-pack-recipe.md',
			),
			'media_derivative_cloud' => array(
				'title'       => 'Media derivative Cloud artifact',
				'description' => 'Build a local single-image or bounded batch media derivative plan, dispatch selected candidates through Cloud Addon, then hand resulting artifacts back to Core governance before any WordPress adoption.',
				'entrypoint_ability_id' => 'magick-ai/build-media-derivative-cloud-request',
				'batch_plan_ability_id' => 'magick-ai/build-media-derivative-batch-plan',
				'steps'       => array(
					array(
						'order'      => 1,
						'route'      => 'POST /run-read-ability',
						'ability_id' => 'magick-ai/build-media-derivative-batch-plan',
						'purpose'    => 'For natural-language bulk requests, build a bounded read-only candidate plan first; review skipped reasons and never treat it as a write decision.',
					),
					array(
						'order'      => 2,
						'route'      => 'POST /media-derivative-runs',
						'ability_id' => 'magick-ai/build-media-derivative-cloud-request',
						'purpose'    => 'For each reviewed candidate, build the read-only local request contract and dispatch source or watermark artifacts through Cloud Addon.',
					),
					array(
						'order'   => 3,
						'route'   => 'GET /media-derivative-runs/{run_id}',
						'purpose' => 'Poll Cloud run status through Cloud Addon without Adapter run truth.',
					),
					array(
						'order'   => 4,
						'route'   => 'GET /media-derivative-runs/{run_id}/result',
						'purpose' => 'Read the derivative artifact projection and processing evidence.',
					),
					array(
						'order'   => 5,
						'route'   => 'GET /media-derivative-artifacts/{artifact_id}/preview',
						'purpose' => 'Serve one non-expired derivative artifact through the local signed preview proxy without storing artifact truth.',
					),
					array(
						'order'   => 6,
						'route'   => 'POST /media-derivative-proposal-payload',
						'purpose' => 'Build a Core-ready proposal payload without creating, approving, or executing the proposal.',
					),
					array(
						'order'   => 7,
						'route'   => 'POST /proposals',
						'purpose' => 'Use Core proposal intake for any local recording, attachment metadata, or media replacement decision.',
					),
				),
				'guardrails'   => array(
					'artifact_type'              => 'media_derivative_cloud_artifact',
					'cloud_transport_owner'      => 'magick-ai-cloud-addon',
					'final_write_owner'          => 'local_wordpress_host',
					'wordpress_write_included'   => false,
					'attachment_metadata_write_included' => false,
					'core_preflight_required_for_writes' => true,
					'adapter_cloud_control_plane' => false,
					'adapter_artifact_registry'  => false,
				),
				'docs'         => 'docs/openclaw-media-derivative-cloud-recipe.md',
			),
		);
	}

	/**
	 * Returns machine-readable route help rows.
	 *
	 * @param array<string,array<int,string>> $route_groups Route groups.
	 * @return array<int,array<string,string>>
	 */
	private function help_routes_flat( array $route_groups ): array {
		$routes = array();

		foreach ( $route_groups as $group => $labels ) {
			foreach ( $labels as $label ) {
				$routes[] = $this->help_route_row( (string) $label, (string) $group );
			}
		}

		return $routes;
	}

	/**
	 * Builds one machine-readable route help row from a label.
	 *
	 * @param string $label Route label, such as GET /health.
	 * @param string $group Route group.
	 * @return array<string,string>
	 */
	private function help_route_row( string $label, string $group ): array {
		$parts  = preg_split( '/\s+/', trim( $label ), 2 );
		$method = isset( $parts[0] ) ? strtoupper( (string) $parts[0] ) : '';
		$path   = isset( $parts[1] ) ? (string) $parts[1] : '';

		return array(
			'method'  => $method,
			'path'    => $path,
			'purpose' => $this->help_route_purpose( $method, $path, $group ),
			'group'   => $group,
		);
	}

	/**
	 * Returns a concise route purpose for agent route discovery.
	 *
	 * @param string $method Route method.
	 * @param string $path Route path.
	 * @param string $group Route group.
	 * @return string
	 */
	private function help_route_purpose( string $method, string $path, string $group ): string {
		$key      = $method . ' ' . $path;
		$purposes = array(
			'GET /health' => 'Check adapter health and connection state.',
			'GET /help' => 'Discover adapter routes and handoff guidance.',
			'GET /capabilities' => 'List Core capabilities and governance guidance.',
			'GET /connection/manifest' => 'Return the non-secret local broker connection manifest.',
			'POST /connect/device/start' => 'Start a public-key device pairing session.',
			'POST /connect/device/poll' => 'Poll a public-key device pairing session.',
			'GET /connection/key-pairs' => 'List registered key-pair clients for the current user.',
			'DELETE /connection/key-pairs/{key_id}' => 'Revoke a registered key-pair client.',
			'POST /run-read-ability' => 'Run a direct-read ability by ability_id.',
			'POST /media-metadata-optimization' => 'Build read-only media title, alt, caption, description, source, and attribution suggestions.',
			'POST /media-derivative-runs' => 'Build the local media derivative Cloud request ability, upload or reference source artifacts through Cloud Addon, and return a Cloud run projection without writing WordPress media.',
			'GET /media-derivative-runs/{run_id}' => 'Poll a Cloud media derivative run through Cloud Addon without storing Adapter run truth.',
			'GET /media-derivative-runs/{run_id}/result' => 'Read a Cloud media derivative result projection through Cloud Addon.',
			'GET /media-derivative-artifacts/{artifact_id}/preview' => 'Proxy one non-expired derivative artifact through Cloud Addon for same-origin local preview; does not store artifact truth.',
			'POST /media-derivative-proposal-payload' => 'Build a Core-ready proposal payload from a derivative artifact; does not create, approve, or execute a proposal.',
			'POST /ai-provider-log-correlation-smoke' => 'Run a provider log correlation smoke request.',
			'GET /proposals' => 'List Core proposal statuses for polling.',
			'GET /proposals/{proposal_id}' => 'Read one Core proposal status by proposal_id.',
			'POST /proposals' => 'Create a Core proposal for governed work.',
			'POST /proposals/from-plan' => 'Forward a read-only plan output to Core plan-to-proposal intake.',
			'POST /proposals/{proposal_id}/approve' => 'Disabled stub; approvals happen in Magick AI Core admin.',
			'POST /proposals/{proposal_id}/reject' => 'Disabled stub; rejections happen in Magick AI Core admin.',
			'POST /proposals/{proposal_id}/commit-preflight' => 'Advanced diagnostic route: run Core commit preflight without final writes and cache the one-time handoff for the next Adapter execute call.',
			'POST /execute-approved-proposal' => 'Execute one approved proposal after Core commit preflight or a cached Adapter preflight handoff; supports allowlisted single inputs or write_actions.',
			'POST /proposals/{proposal_id}/execute' => 'Execute one approved proposal by id after Core commit preflight or a cached Adapter preflight handoff; supports allowlisted single inputs or write_actions.',
			'POST /proposals/{proposal_id}/approve-and-execute' => 'Approve a pending proposal through Core, then preflight and execute one allowlisted single input or write_actions.',
			'GET /terms' => 'List terms; use returned id with GET /term?id={id}; pass taxonomy when known.',
			'GET /term' => 'Read one term by list row id. Adapter infers taxonomy from id when possible; term_id is accepted as an alias for id.',
		);

		if ( isset( $purposes[ $key ] ) ) {
			return $purposes[ $key ];
		}

		if ( 'read_shortcuts' === $group && '' !== $path ) {
			return 'Run the direct-read shortcut for ' . ltrim( $path, '/' ) . '.';
		}

		return 'Call adapter route ' . trim( $key ) . '.';
	}

	/**
	 * Returns Core capabilities.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function capabilities() {
		return $this->dispatch_upstream( 'GET', '/magick-ai-core/v1/capabilities' );
	}

	/**
	 * Runs a direct-read ability from request input.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function run_read_ability_route( WP_REST_Request $request ) {
		return $this->run_read_ability(
			(string) $request->get_param( 'ability_id' ),
			$this->request_input( $request ),
			$this->request_log_context( $request, (string) $request->get_param( 'ability_id' ) )
		);
	}

	/**
	 * Runs the media metadata optimization read helper.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function media_metadata_optimization_route( WP_REST_Request $request ) {
		$ability_id = 'magick-ai/optimize-media-metadata';

		return $this->run_read_ability(
			$ability_id,
			$this->media_metadata_optimization_input( $request ),
			$this->request_log_context( $request, $ability_id )
		);
	}

	/**
	 * Creates one Cloud media derivative run through the Cloud Addon seam.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_media_derivative_run( WP_REST_Request $request ) {
		if ( ! function_exists( 'magick_ai_cloud_addon_dispatch_media_derivative_cloud_request' ) ) {
			return $this->cloud_addon_unavailable_error();
		}

		$ability_id     = 'magick-ai/build-media-derivative-cloud-request';
		$ability_input  = $this->media_derivative_ability_input( $request );
		$log_context    = $this->request_log_context( $request, $ability_id );
		$ability_result = $this->run_read_ability( $ability_id, $ability_input, $log_context );
		if ( is_wp_error( $ability_result ) ) {
			return $ability_result;
		}

		$ability_envelope = $ability_result->get_data();
		$ability_response = is_array( $ability_envelope['result'] ?? null ) ? $ability_envelope['result'] : array();
		if ( empty( $ability_response ) ) {
			return new WP_Error(
				'magick_ai_adapter_media_derivative_ability_response_invalid',
				__( 'Media derivative ability response is invalid.', 'magick-ai-adapter' ),
				array( 'status' => 502 )
			);
		}

		$source_artifact = $this->media_derivative_source_artifact( $request, $ability_response, $ability_input );
		if ( is_wp_error( $source_artifact ) ) {
			return $source_artifact;
		}

		$watermark_artifact = $this->media_derivative_watermark_artifact( $request, $ability_response, $ability_input );
		if ( is_wp_error( $watermark_artifact ) ) {
			return $watermark_artifact;
		}

		$trace_id        = sanitize_text_field( (string) ( $request->get_param( 'trace_id' ) ?: ( $log_context['correlation_id'] ?? '' ) ) );
		$idempotency_key = sanitize_text_field( (string) $request->get_param( 'idempotency_key' ) );
		$dispatch        = magick_ai_cloud_addon_dispatch_media_derivative_cloud_request(
			$ability_response,
			$source_artifact,
			$trace_id,
			$idempotency_key,
			$watermark_artifact
		);
		if ( is_wp_error( $dispatch ) ) {
			return $dispatch;
		}

		$run_id = $this->media_derivative_run_id( $dispatch );

		return new WP_REST_Response(
			array(
				'contract_version'       => 'media_derivative_adapter_run.v1',
				'status'                 => 'submitted',
				'ability_id'             => $ability_id,
				'expected_request_contract_version' => 'media_derivative_cloud_request.v1',
				'request_contract_version' => (string) ( $this->media_derivative_contract_data( $ability_response )['request_contract_version'] ?? '' ),
				'run_id'                 => $run_id,
				'cloud_run'              => $this->public_media_derivative_cloud_projection( $dispatch ),
				'ability_response'       => $ability_response,
				'local_adoption'         => array(
					'final_write_owner'             => 'local_wordpress_host',
					'wordpress_write_included'      => false,
					'attachment_metadata_write_included' => false,
					'approval_required'             => true,
				),
				'next_steps'             => array(
					'poll_run'            => '' !== $run_id ? rest_url( self::NAMESPACE . '/media-derivative-runs/' . rawurlencode( $run_id ) ) : '',
					'poll_result'         => '' !== $run_id ? rest_url( self::NAMESPACE . '/media-derivative-runs/' . rawurlencode( $run_id ) . '/result' ) : '',
					'build_proposal_payload' => rest_url( self::NAMESPACE . '/media-derivative-proposal-payload' ),
					'core_proposal_required' => true,
				),
			),
			202
		);
	}

	/**
	 * Returns one media derivative Cloud run projection through Cloud Addon.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_media_derivative_run( WP_REST_Request $request ) {
		$client = $this->media_derivative_runtime_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		$result = $client->get_run(
			sanitize_text_field( (string) $request->get_param( 'run_id' ) ),
			sanitize_text_field( (string) $request->get_param( 'trace_id' ) )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'contract_version' => 'media_derivative_adapter_run_status.v1',
				'run_id'           => $this->media_derivative_run_id( $result ),
				'cloud_run'        => $this->public_media_derivative_cloud_projection( $result ),
				'commit_execution' => false,
			),
			200
		);
	}

	/**
	 * Returns one media derivative Cloud result projection through Cloud Addon.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_media_derivative_run_result( WP_REST_Request $request ) {
		$client = $this->media_derivative_runtime_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		$result = $client->get_run_result(
			sanitize_text_field( (string) $request->get_param( 'run_id' ) ),
			sanitize_text_field( (string) $request->get_param( 'trace_id' ) )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'contract_version' => 'media_derivative_adapter_run_result.v1',
				'run_id'           => $this->media_derivative_run_id( $result ),
				'cloud_result'     => $this->public_media_derivative_cloud_projection( $result ),
				'commit_execution' => false,
				'next_step'        => 'POST /media-derivative-proposal-payload with ability_response, cloud_result, and derivative_artifact before Core proposal intake.',
			),
			200
		);
	}

	/**
	 * Builds a Core-ready proposal payload from a Cloud derivative artifact.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function build_media_derivative_proposal_payload( WP_REST_Request $request ) {
		if ( ! function_exists( 'magick_ai_cloud_addon_build_media_derivative_proposal_payload' ) ) {
			return $this->cloud_addon_unavailable_error();
		}

		$ability_response    = $this->object_param( $request, 'ability_response' );
		$cloud_result        = $this->object_param( $request, 'cloud_result' );
		$artifact            = $this->object_param( $request, 'derivative_artifact' );
		$media_details_input = $this->object_param( $request, 'media_details_input' );
		if ( empty( $artifact ) ) {
			$artifact = $this->media_derivative_artifact_from_cloud_result( $cloud_result );
		}

		$payload = magick_ai_cloud_addon_build_media_derivative_proposal_payload(
			$ability_response,
			$cloud_result,
			$artifact
		);
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}
		$optimization_plan = $this->media_optimization_plan_from_derivative_payload( $payload, $media_details_input );
		$response_payload  = array(
			'contract_version'       => 'media_derivative_adapter_proposal_payload.v1',
			'proposal_payload'       => $payload,
			'media_optimization_plan' => $optimization_plan,
			'core_proposal_required' => true,
			'commit_execution'       => false,
		);
		if ( is_array( $optimization_plan['write_actions'] ?? null ) && count( (array) $optimization_plan['write_actions'] ) >= 2 ) {
			$response_payload['from_plan_request'] = array(
				'plan_ability_id' => 'magick-ai/build-media-optimization-plan',
				'plan'            => $optimization_plan,
			);
			$response_payload['next_step'] = 'POST /proposals/from-plan with from_plan_request for one Core batch proposal.';
		} else {
			$response_payload['next_step'] = 'Provide reviewed media_details_input, then POST /proposals/from-plan with the returned from_plan_request; legacy single derivative proposal_payload remains available.';
		}

		return new WP_REST_Response(
			$response_payload,
			200
		);
	}

	/**
	 * Builds the Core from-plan media optimization payload shape.
	 *
	 * @param array<string,mixed> $proposal_payload Legacy derivative proposal payload.
	 * @param array<string,mixed> $media_details_input Reviewed metadata action input.
	 * @return array<string,mixed>
	 */
	private function media_optimization_plan_from_derivative_payload( array $proposal_payload, array $media_details_input ): array {
		$attachment_id  = absint( $proposal_payload['attachment_id'] ?? 0 );
		$artifact       = is_array( $proposal_payload['artifact'] ?? null ) ? $proposal_payload['artifact'] : array();
		$original       = is_array( $proposal_payload['original'] ?? null ) ? $proposal_payload['original'] : array();
		$derivative     = is_array( $proposal_payload['derivative'] ?? null ) ? $proposal_payload['derivative'] : array();
		$metadata_input = $this->sanitize_media_details_plan_input( $attachment_id, $media_details_input );

		$metadata_preview = array(
			'before' => array(),
			'after'  => array_diff_key( $metadata_input, array( 'attachment_id' => true ) ),
		);
		$derivative_preview = array(
			'before' => array(
				'mime_type'      => sanitize_text_field( (string) ( $original['mime_type'] ?? '' ) ),
				'width'          => absint( $original['width'] ?? 0 ),
				'height'         => absint( $original['height'] ?? 0 ),
				'filesize_bytes' => absint( $original['filesize_bytes'] ?? 0 ),
			),
			'after'  => array(
				'artifact_id'    => sanitize_text_field( (string) ( $artifact['artifact_id'] ?? '' ) ),
				'mime_type'      => sanitize_text_field( (string) ( $derivative['mime_type'] ?? ( $artifact['mime_type'] ?? '' ) ) ),
				'width'          => absint( $derivative['width'] ?? ( $artifact['width'] ?? 0 ) ),
				'height'         => absint( $derivative['height'] ?? ( $artifact['height'] ?? 0 ) ),
				'filesize_bytes' => absint( $derivative['filesize_bytes'] ?? ( $artifact['filesize_bytes'] ?? 0 ) ),
			),
		);

		$plan = array(
			'artifact_type'      => 'media_optimization_plan',
			'version'            => 1,
			'batch_id'           => 'media_optimization_' . $attachment_id . '_' . gmdate( 'Ymd_His' ),
			'attachment_id'      => $attachment_id,
			'optimization_goal'  => 'image_seo_and_derivative_adoption',
			'requires_approval'  => true,
			'dry_run'            => true,
			'commit_execution'   => false,
			'proposal_mode'      => 'batch',
			'batch_approval'     => true,
			'action_count'       => 0,
			'metadata_preview'   => $metadata_preview,
			'derivative_preview' => $derivative_preview,
			'preview'            => array(),
			'write_actions'      => array(),
			'requires_input'     => array(),
			'proposal_ready'     => false,
			'risk'               => array(
				'level'  => 'medium',
				'reason' => 'One attachment metadata update and one reviewed Cloud derivative adoption share one Core approval.',
			),
		);

		if ( $attachment_id <= 0 || empty( $artifact['artifact_id'] ) ) {
			$plan['requires_input'][] = 'valid_derivative_proposal_payload';
			return $plan;
		}

		if ( count( $metadata_input ) <= 1 ) {
			$plan['requires_input'][] = 'media_details_input';
			return $plan;
		}

		$derivative_input = array(
			'attachment_id'       => $attachment_id,
			'derivative_artifact' => $artifact,
		);
		$current_mime     = sanitize_text_field( (string) ( $original['mime_type'] ?? '' ) );
		$derivative_mime  = sanitize_text_field( (string) ( $derivative['mime_type'] ?? ( $artifact['mime_type'] ?? '' ) ) );
		if ( '' !== $current_mime ) {
			$derivative_input['expected_current_mime_type'] = $current_mime;
		}
		if ( '' !== $derivative_mime ) {
			$derivative_input['expected_derivative_mime_type'] = $derivative_mime;
		}

		$plan['write_actions']  = array(
			$this->adapter_plan_action( 'update_media_details_' . $attachment_id, 'magick-ai/update-media-details', $metadata_input, 'medium', 'Apply reviewed media SEO and source metadata as part of one media optimization approval.' ),
			$this->adapter_plan_action( 'adopt_cloud_media_derivative_' . $attachment_id, 'magick-ai/adopt-cloud-media-derivative', $derivative_input, 'medium', 'Adopt the reviewed Cloud derivative artifact as the attachment main file after Core approval.' ),
		);
		$plan['action_count']   = count( $plan['write_actions'] );
		$plan['proposal_ready'] = true;
		$plan['preview'][]      = array(
			'attachment_id'    => $attachment_id,
			'before'           => array(
				'metadata'   => array(),
				'derivative' => $derivative_preview['before'],
			),
			'after_suggestion' => array(
				'metadata'   => $metadata_preview['after'],
				'derivative' => $derivative_preview['after'],
			),
		);

		return $plan;
	}

	/**
	 * Sanitizes update-media-details input for a generated plan.
	 *
	 * @param int                 $attachment_id Attachment id.
	 * @param array<string,mixed> $input Raw metadata input.
	 * @return array<string,mixed>
	 */
	private function sanitize_media_details_plan_input( int $attachment_id, array $input ): array {
		$output = array( 'attachment_id' => $attachment_id );
		foreach ( array( 'title', 'alt', 'caption', 'description', 'source_page_url', 'photographer_name', 'attribution_text', 'copyright_notice' ) as $field ) {
			if ( array_key_exists( $field, $input ) && '' !== (string) $input[ $field ] ) {
				$output[ $field ] = 'source_page_url' === $field ? esc_url_raw( (string) $input[ $field ] ) : sanitize_textarea_field( (string) $input[ $field ] );
			}
		}
		if ( array_key_exists( 'source_type', $input ) ) {
			$source_type = sanitize_key( (string) $input['source_type'] );
			if ( in_array( $source_type, array( 'owned', 'ai_generated', 'stock', 'external', 'test' ), true ) ) {
				$output['source_type'] = $source_type;
			}
		}
		return $output;
	}

	/**
	 * Builds one Adapter-authored plan action.
	 *
	 * @param string              $action_id Action id.
	 * @param string              $ability_id Target ability id.
	 * @param array<string,mixed> $input Target ability input.
	 * @param string              $risk Risk.
	 * @param string              $reason Reason.
	 * @return array<string,mixed>
	 */
	private function adapter_plan_action( string $action_id, string $ability_id, array $input, string $risk, string $reason ): array {
		$input['dry_run'] = true;
		$input['commit']  = false;
		return array(
			'action_id'         => sanitize_key( $action_id ),
			'target_ability_id' => sanitize_text_field( $ability_id ),
			'input'             => $input,
			'requires_approval' => true,
			'commit_execution'  => false,
			'required_scopes'   => array( 'media.write' ),
			'risk'              => sanitize_key( $risk ),
			'reason'            => sanitize_text_field( $reason ),
			'requires_input'    => array(),
			'proposal_ready'    => true,
		);
	}

	/**
	 * Proxies a short-TTL Cloud derivative artifact as a local same-origin preview.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_Error|null
	 */
	public function download_media_derivative_artifact_preview( WP_REST_Request $request ) {
		if ( ! function_exists( 'magick_ai_cloud_addon_download_media_derivative_artifact' ) ) {
			return $this->cloud_addon_unavailable_error();
		}

		$artifact = $this->sanitize_media_derivative_artifact_descriptor(
			array(
				'artifact_id' => sanitize_text_field( (string) $request->get_param( 'artifact_id' ) ),
				'expires_at'  => $this->media_derivative_preview_expires_at( $request ),
				'mime_type'   => sanitize_text_field( (string) $request->get_param( 'mime_type' ) ),
				'checksum'    => sanitize_text_field( (string) $request->get_param( 'checksum' ) ),
				'sha256'      => sanitize_text_field( (string) $request->get_param( 'sha256' ) ),
				'run_id'      => sanitize_text_field( (string) $request->get_param( 'run_id' ) ),
			)
		);

		$download = magick_ai_cloud_addon_download_media_derivative_artifact(
			$artifact,
			sanitize_text_field( (string) $request->get_param( 'trace_id' ) )
		);
		if ( is_wp_error( $download ) ) {
			return $download;
		}

		$contents    = is_string( $download['contents'] ?? null ) ? $download['contents'] : '';
		$mime_type   = sanitize_text_field( (string) ( $download['mime_type'] ?? 'application/octet-stream' ) );
		$artifact_id = sanitize_file_name( (string) ( $download['artifact_id'] ?? $artifact['artifact_id'] ?? 'derivative-artifact' ) );
		$filename    = $artifact_id . $this->media_derivative_extension_for_mime( $mime_type );

		if ( function_exists( 'status_header' ) ) {
			status_header( 200 );
		}
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}

		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Length: ' . strlen( $contents ) );
		header( 'Content-Disposition: inline; filename="' . $filename . '"' );
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Magick-AI-Artifact-ID: ' . $artifact_id );
		echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Runs a workflow recipe detail helper.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function workflow_recipe( WP_REST_Request $request ) {
		return $this->run_read_ability(
			'npcink-abilities-toolkit/get-workflow-recipe',
			array(
				'recipe_id' => (string) $request->get_param( 'recipe_id' ),
			),
			$this->request_log_context( $request, 'npcink-abilities-toolkit/get-workflow-recipe' )
		);
	}

	/**
	 * Runs a bounded local provider request to prove AI Request Logs correlation.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function ai_provider_log_correlation_smoke( WP_REST_Request $request ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error(
				'magick_ai_adapter_ai_client_unavailable',
				__( 'WordPress AI Client is not available.', 'magick-ai-adapter' ),
				array( 'status' => 501 )
			);
		}

		$ability_id  = (string) $request->get_param( 'ability_id' );
		$ai_provider = sanitize_key( (string) $request->get_param( 'ai_provider' ) );
		$ai_model    = sanitize_text_field( (string) $request->get_param( 'ai_model' ) );
		$prompt      = sanitize_textarea_field( (string) $request->get_param( 'prompt' ) );

		if ( '' === $ai_provider || '' === $ai_model ) {
			return new WP_Error(
				'magick_ai_adapter_ai_provider_model_required',
				__( 'AI provider and model are required for provider log correlation smoke.', 'magick-ai-adapter' ),
				array( 'status' => 400 )
			);
		}

		$log_context                = $this->request_log_context( $request, $ability_id );
		$log_context['ai_provider'] = $ai_provider;
		$log_context['ai_model']    = $ai_model;
		$log_context                = $this->sanitize_log_context( $log_context );

		$result = $this->with_ai_request_log_context(
			$log_context,
			static function () use ( $prompt, $ai_provider, $ai_model ) {
				$prompt_builder = 'wp_ai_client_prompt';
				$builder        = $prompt_builder( $prompt );

				if ( is_callable( array( $builder, 'using_provider' ) ) ) {
					$builder = $builder->using_provider( $ai_provider );
				}

				if ( is_callable( array( $builder, 'using_model_preference' ) ) ) {
					$builder = $builder->using_model_preference( array( $ai_provider, $ai_model ) );
				}

				if ( is_callable( array( $builder, 'using_max_tokens' ) ) ) {
					$builder = $builder->using_max_tokens( 16 );
				}

				if ( is_callable( array( $builder, 'using_temperature' ) ) ) {
					$builder = $builder->using_temperature( 0.0 );
				}

				return $builder->generate_text();
			}
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'status'             => 'success',
				'provider_call'      => 'wp_ai_client_prompt',
				'ai_provider'        => $ai_provider,
				'ai_model'           => $ai_model,
				'governance_source'  => 'magick-ai-core',
				'log_context'        => $log_context,
				'response_preview'   => $this->text_preview( (string) $result ),
				'core_proxy_execute' => false,
				'commit_execution'   => false,
			),
			200
		);
	}

	/**
	 * Lists Core proposals through the adapter read-only status proxy.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_proposals( WP_REST_Request $request ) {
		$limit = max( 1, absint( $request->get_param( 'limit' ) ) );

		return $this->dispatch_upstream(
			'GET',
			'/magick-ai-core/v1/proposals',
			array(
				'limit' => $limit,
			),
			true
		);
	}

	/**
	 * Gets one Core proposal through the adapter read-only status proxy.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_proposal( WP_REST_Request $request ) {
		$proposal_id = (string) $request->get_param( 'proposal_id' );

		return $this->dispatch_upstream( 'GET', '/magick-ai-core/v1/proposals/' . rawurlencode( $proposal_id ) );
	}

	/**
	 * Returns a disabled approval proxy response.
	 *
	 * @return WP_REST_Response
	 */
	public function approval_proxy_disabled(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'code'                   => 'magick_ai_adapter_approval_proxy_disabled',
				'message'                => __( 'Direct approve/reject proxy routes are disabled. Use POST /proposals/{proposal_id}/approve-and-execute for the Adapter unified user action, or use Magick AI Core admin for split approval decisions.', 'magick-ai-adapter' ),
				'approval_proxy_enabled' => false,
				'approval_surface'       => 'magick_ai_core_admin',
				'unified_action_route'   => 'POST /proposals/{proposal_id}/approve-and-execute',
			),
			403
		);
	}

	/**
	 * Creates a Core proposal.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_proposal( WP_REST_Request $request ) {
		$started    = microtime( true );
		$ability_id  = (string) $request->get_param( 'ability_id' );
		$event_context = $this->observability_request_context( $request, array( 'ability_id' => $ability_id ) );
		$input       = $this->object_param( $request, 'input' );
		$valid_input = $this->validate_proposal_create_input( $ability_id, $input );
		if ( is_wp_error( $valid_input ) ) {
			$this->emit_operation_event( 'adapter.proposal.create', $started, $valid_input, $event_context );
			return $valid_input;
		}

		$params = array(
			'ability_id' => $ability_id,
			'title'      => (string) $request->get_param( 'title' ),
			'summary'    => (string) $request->get_param( 'summary' ),
			'input'      => $input,
			'preview'    => $this->object_param( $request, 'preview' ),
			'caller'     => $this->proposal_caller_context( $request, $ability_id ),
		);

		$response = $this->dispatch_upstream( 'POST', '/magick-ai-core/v1/proposals', $params );
		$this->emit_operation_event( 'adapter.proposal.create', $started, is_wp_error( $response ) ? $response : null, $event_context );

		return $response;
	}

	/**
	 * Validates Adapter-owned proposal input before forwarding to Core.
	 *
	 * Only abilities with local execution profiles are validated here. Other
	 * proposal-required abilities remain Core-owned at proposal creation time.
	 *
	 * @param string              $ability_id Ability id.
	 * @param array<string,mixed> $input Proposal input.
	 * @return true|WP_Error
	 */
	private function validate_proposal_create_input( string $ability_id, array $input, bool $allow_output_refs = false, ?int $action_index = null ) {
		$ability_id = sanitize_text_field( $ability_id );
		$profiles   = self::execution_profiles();
		if ( ! isset( $profiles[ $ability_id ] ) ) {
			return true;
		}

		return $this->validate_execute_action_input( 'proposal_create', $ability_id, $input, absint( $input['post_id'] ?? 0 ), $action_index, $allow_output_refs );
	}

	/**
	 * Creates Core proposals from a read-only plan output.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_proposals_from_plan( WP_REST_Request $request ) {
		$started = microtime( true );
		$plan_ability_id = sanitize_text_field( (string) $request->get_param( 'plan_ability_id' ) );
		$event_context = $this->observability_request_context( $request, array( 'ability_id' => $plan_ability_id ) );
		if ( ! isset( self::$allowed_plan_ability_ids[ $plan_ability_id ] ) ) {
			$error = new WP_Error(
				'magick_ai_adapter_plan_ability_not_allowed',
				__( 'This planning ability is not accepted by the adapter plan-to-proposal bridge.', 'magick-ai-adapter' ),
				array(
					'status'                   => 400,
					'allowed_plan_ability_ids' => array_keys( self::$allowed_plan_ability_ids ),
				)
			);
			$error = $this->error_with_operator_feedback( $error, $this->plan_handoff_operator_feedback( $error, $plan_ability_id ) );
			$this->emit_operation_event( 'adapter.proposal.plan_ingest', $started, $error, $event_context );
			return $error;
		}

		$plan            = $this->object_param( $request, 'plan' );
		$valid_plan_input = $this->validate_plan_write_action_inputs( $plan );
		if ( is_wp_error( $valid_plan_input ) ) {
			$valid_plan_input = $this->error_with_operator_feedback( $valid_plan_input, $this->plan_handoff_operator_feedback( $valid_plan_input, $plan_ability_id ) );
			$this->emit_operation_event( 'adapter.proposal.plan_ingest', $started, $valid_plan_input, $event_context );
			return $valid_plan_input;
		}

		$params = array(
			'plan_ability_id' => $plan_ability_id,
			'plan'            => $plan,
			'plan_input'      => $this->object_param( $request, 'plan_input' ),
			'caller'          => $this->proposal_caller_context( $request, $plan_ability_id ),
		);

		$response = $this->dispatch_upstream( 'POST', '/magick-ai-core/v1/proposals/from-plan', $params );
		if ( is_wp_error( $response ) ) {
			$response = $this->error_with_operator_feedback( $response, $this->plan_handoff_operator_feedback( $response, $plan_ability_id ) );
		}
		$this->emit_operation_event( 'adapter.proposal.plan_ingest', $started, is_wp_error( $response ) ? $response : null, $event_context );

		return $response;
	}

	/**
	 * Validates profiled write action input before Core creates plan proposals.
	 *
	 * Core remains the proposal creation and blocked-item truth. Adapter only
	 * rejects inputs it already owns through the execution profile registry, so
	 * single proposal and plan-to-proposal intake fail on the same schema rules.
	 *
	 * @param array<string,mixed> $plan_payload Plan output or success envelope.
	 * @return true|WP_Error
	 */
	private function validate_plan_write_action_inputs( array $plan_payload ) {
		$plan = is_array( $plan_payload['data'] ?? null ) ? $plan_payload['data'] : $plan_payload;
		if ( ! is_array( $plan ) ) {
			return true;
		}

		$write_actions     = is_array( $plan['write_actions'] ?? null ) ? array_values( $plan['write_actions'] ) : array();
		$blocked_items     = array();
		$available_outputs = array();
		foreach ( $write_actions as $index => $raw_action ) {
			if ( ! is_array( $raw_action ) ) {
				continue;
			}

			$target_ability_id = sanitize_text_field( (string) ( $raw_action['target_ability_id'] ?? '' ) );
			if ( '' === $target_ability_id ) {
				continue;
			}

			$action_id = sanitize_key( (string) ( $raw_action['action_id'] ?? '' ) );
			if ( '' === $action_id ) {
				$action_id = 'action-' . ( $index + 1 );
			}
			if ( isset( $available_outputs[ $action_id ] ) ) {
				$blocked_items[] = array(
					'index'             => $index,
					'action_id'         => $action_id,
					'target_ability_id' => $target_ability_id,
					'block_code'        => 'magick_ai_adapter_write_action_duplicate_id',
					'reason'            => __( 'Each write_actions item must have a unique action_id.', 'magick-ai-adapter' ),
				);
				continue;
			}

			$input       = is_array( $raw_action['input'] ?? null ) ? $raw_action['input'] : array();
			$valid_refs  = $this->validate_output_references( 'proposal_create', $input, $available_outputs, $index );
			$valid_input = is_wp_error( $valid_refs ) ? $valid_refs : $this->validate_proposal_create_input( $target_ability_id, $input, true, $index );
			if ( is_wp_error( $valid_input ) ) {
				$error_data = $valid_input->get_error_data();
				$error_data = is_array( $error_data ) ? $error_data : array();
				$blocked    = array(
					'index'             => $index,
					'action_id'         => $action_id,
					'target_ability_id' => $target_ability_id,
					'block_code'        => $valid_input->get_error_code(),
					'reason'            => $valid_input->get_error_message(),
				);

				foreach ( array( 'field', 'allowed_input_fields', 'allowed_values', 'reference' ) as $key ) {
					if ( array_key_exists( $key, $error_data ) ) {
						$blocked[ $key ] = $error_data[ $key ];
					}
				}

				$blocked_items[] = $blocked;
				continue;
			}

			$available_outputs[ $action_id ] = true;
		}

		if ( empty( $blocked_items ) ) {
			return true;
		}

		return new WP_Error(
			'magick_ai_adapter_plan_action_input_invalid',
			__( 'Plan write action input failed Adapter proposal validation.', 'magick-ai-adapter' ),
			array(
				'status'         => 400,
				'proposal_count' => 0,
				'blocked_count'  => count( $blocked_items ),
				'blocked_items'  => $blocked_items,
			)
		);
	}

	/**
	 * Runs Core commit preflight.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function commit_preflight( WP_REST_Request $request ) {
		$started = microtime( true );
		$proposal_id = (string) $request->get_param( 'proposal_id' );
		$response = $this->dispatch_upstream( 'POST', '/magick-ai-core/v1/proposals/' . rawurlencode( $proposal_id ) . '/commit-preflight' );
		if ( ! is_wp_error( $response ) && $response instanceof WP_REST_Response ) {
			$data = $response->get_data();
			if ( is_array( $data ) ) {
				$proposal = is_array( $data['proposal'] ?? null ) ? $data['proposal'] : array();
				if ( empty( $proposal ) ) {
					$proposal_detail = $this->get_core_proposal_data( $proposal_id );
					if ( ! is_wp_error( $proposal_detail ) ) {
						$proposal = $proposal_detail;
					}
				}

				$handoff = $this->store_preflight_handoff( $proposal_id, $proposal, $data );
				$data['adapter_preflight_handoff_cached'] = is_array( $handoff );
				$data['adapter_execution_route']          = '/wp-json/' . self::NAMESPACE . '/proposals/' . rawurlencode( $proposal_id ) . '/execute';
				$response->set_data( $data );
			}
		}
		$this->emit_operation_event(
			'adapter.commit.preflight',
			$started,
			is_wp_error( $response ) ? $response : null,
			$this->observability_request_context( $request, array( 'proposal_id' => $proposal_id ) )
		);

		return $response;
	}

	/**
	 * Executes one approved Core proposal after commit preflight.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function execute_approved_proposal_route( WP_REST_Request $request ) {
		$started = microtime( true );
		$proposal_id = sanitize_text_field( (string) $request->get_param( 'proposal_id' ) );
		$event_context = $this->observability_request_context( $request, array( 'proposal_id' => $proposal_id ) );
		if ( '' === $proposal_id ) {
			$error = new WP_Error(
				'magick_ai_adapter_proposal_id_required',
				__( 'proposal_id is required.', 'magick-ai-adapter' ),
				array( 'status' => 400 )
			);
			$this->emit_operation_event( 'adapter.proposal.execute', $started, $error, $event_context );
			return $error;
		}

		$proposal = $this->get_core_proposal_data( $proposal_id );
		if ( is_wp_error( $proposal ) ) {
			$this->emit_operation_event( 'adapter.proposal.execute', $started, $proposal, $event_context );
			return $proposal;
		}

		$execution = $this->execute_core_approved_proposal( $request, $proposal_id, $proposal );
		if ( is_wp_error( $execution ) ) {
			$this->emit_operation_event( 'adapter.proposal.execute', $started, $execution, $event_context );
			return $execution;
		}

		$this->emit_operation_event(
			'adapter.proposal.execute',
			$started,
			null,
				array_merge(
					$event_context,
					array(
						'proposal_id'        => $proposal_id,
						'ability_id'         => (string) ( $execution['ability_id'] ?? '' ),
						'correlation_id'     => (string) ( $execution['correlation_id'] ?? '' ),
						'adapter_request_id' => (string) ( $execution['adapter_request_id'] ?? '' ),
						'executed_count'     => (int) ( $execution['executed_count'] ?? 0 ),
						'failed_count'       => (int) ( $execution['failed_count'] ?? 0 ),
					),
				)
			);

		return new WP_REST_Response(
			array(
				'status'             => 'executed',
				'proposal_id'        => $proposal_id,
				'correlation_id'     => $execution['correlation_id'],
				'ability_id'         => $execution['ability_id'],
				'post_id'            => $execution['post_id'],
				'post_ids'           => $execution['post_ids'],
				'execution_mode'     => $execution['execution_mode'],
				'adapter_request_id' => $execution['adapter_request_id'],
				'approval_context'   => $execution['approval_context'],
				'preflight_source'   => $execution['preflight_source'],
				'commit_execution'   => false,
				'execution_surface'  => 'wp_abilities_rest',
				'executed_count'     => $execution['executed_count'],
				'failed_count'       => $execution['failed_count'],
				'execution_record'   => $execution['execution_record'],
				'results'            => $execution['results'],
				'result'             => $execution['result'],
			),
			200
		);
	}

	/**
	 * Approves a pending proposal through Core and executes allowlisted input.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function approve_and_execute_proposal_route( WP_REST_Request $request ) {
		$started = microtime( true );
		$proposal_id = sanitize_text_field( (string) $request->get_param( 'proposal_id' ) );
		$event_context = $this->observability_request_context( $request, array( 'proposal_id' => $proposal_id ) );
		if ( '' === $proposal_id ) {
			$error = new WP_Error(
				'magick_ai_adapter_proposal_id_required',
				__( 'proposal_id is required.', 'magick-ai-adapter' ),
				array( 'status' => 400 )
			);
			$this->emit_operation_event( 'adapter.proposal.execute', $started, $error, $event_context );
			return $error;
		}

		$proposal = $this->get_core_proposal_data( $proposal_id );
		if ( is_wp_error( $proposal ) ) {
			$this->emit_operation_event( 'adapter.proposal.execute', $started, $proposal, $event_context );
			return $proposal;
		}

		$ability_id = sanitize_text_field( (string) ( $proposal['ability_id'] ?? '' ) );
		$event_context['ability_id'] = $ability_id;
		$execution_actions = $this->normalize_execution_actions( $proposal_id, $proposal );
		if ( is_wp_error( $execution_actions ) ) {
			$this->emit_operation_event( 'adapter.proposal.execute', $started, $execution_actions, $event_context );
			return $execution_actions;
		}

		$status_before       = sanitize_key( (string) ( $proposal['status'] ?? '' ) );
		$approved_by_adapter = false;

		if ( 'pending' === $status_before ) {
			$note = sanitize_text_field( (string) $request->get_param( 'note' ) );
			if ( '' === $note ) {
				$note = __( 'Approved by Magick AI Adapter approve-and-execute.', 'magick-ai-adapter' );
			}

			$approved_response = $this->dispatch_upstream(
				'POST',
				'/magick-ai-core/v1/proposals/' . rawurlencode( $proposal_id ) . '/approve',
				array( 'note' => $note ),
				false,
				false,
				false
			);
			if ( is_wp_error( $approved_response ) ) {
				$this->emit_operation_event( 'adapter.proposal.execute', $started, $approved_response, $event_context );
				return $approved_response;
			}

			$approved = $approved_response->get_data();
			if ( ! is_array( $approved ) || 'approved' !== (string) ( $approved['status'] ?? '' ) ) {
				$error = new WP_Error(
					'magick_ai_adapter_core_approve_failed',
					__( 'Core did not return an approved proposal state.', 'magick-ai-adapter' ),
					array(
						'status'      => 409,
						'proposal_id' => $proposal_id,
						'core_result' => $approved,
					),
				);
				$this->emit_operation_event( 'adapter.proposal.execute', $started, $error, $event_context );
				return $error;
			}

			$approved_by_adapter = true;
		} elseif ( 'approved' !== $status_before ) {
			$code = 'rejected' === $status_before ? 'magick_ai_adapter_proposal_rejected' : 'magick_ai_adapter_proposal_not_executable';
			$error = new WP_Error(
				$code,
				__( 'This proposal cannot be approved and executed from its current status.', 'magick-ai-adapter' ),
				array(
					'status'            => 409,
					'proposal_id'       => $proposal_id,
					'ability_id'        => $ability_id,
					'status_before'     => $status_before,
					'operator_feedback' => $this->proposal_status_operator_feedback( $proposal, $status_before ),
				)
			);
			$this->emit_operation_event( 'adapter.proposal.execute', $started, $error, $event_context );
			return $error;
		}

		$execution = $this->execute_core_approved_proposal( $request, $proposal_id, $proposal );
		if ( is_wp_error( $execution ) ) {
			$this->emit_operation_event( 'adapter.proposal.execute', $started, $execution, $event_context );
			return $execution;
		}

		$this->emit_operation_event(
			'adapter.proposal.execute',
			$started,
			null,
			array_merge(
				$event_context,
				array(
					'correlation_id'     => (string) ( $execution['correlation_id'] ?? '' ),
					'adapter_request_id' => (string) ( $execution['adapter_request_id'] ?? '' ),
					'executed_count'     => (int) ( $execution['executed_count'] ?? 0 ),
					'failed_count'       => (int) ( $execution['failed_count'] ?? 0 ),
				)
			)
		);

		return new WP_REST_Response(
			array(
				'success'               => true,
				'proposal_id'           => $proposal_id,
				'ability_id'            => $execution['ability_id'],
				'post_id'               => $execution['post_id'],
				'post_ids'              => $execution['post_ids'],
				'execution_mode'        => $execution['execution_mode'],
				'status_before'         => $status_before,
				'approved_by_adapter'   => $approved_by_adapter,
				'correlation_id'        => $execution['correlation_id'],
				'adapter_request_id'    => $execution['adapter_request_id'],
				'core_commit_execution' => false,
				'approval_context'      => $execution['approval_context'],
				'preflight_source'      => $execution['preflight_source'],
				'executed_count'        => $execution['executed_count'],
				'failed_count'          => $execution['failed_count'],
				'execution_record'      => $execution['execution_record'],
				'results'               => $execution['results'],
				'execution'             => array(
					'success'            => true,
					'post_status_before' => $execution['post_status_before'],
					'post_status_after'  => $execution['post_status_after'],
					'result'             => $execution['result'],
					'results'            => $execution['results'],
				),
			),
			200
		);
	}

	/**
	 * Fetches a Core proposal and validates the response shape.
	 *
	 * @param string $proposal_id Proposal id.
	 * @return array<string,mixed>|WP_Error
	 */
	private function get_core_proposal_data( string $proposal_id ) {
		$proposal_response = $this->dispatch_upstream( 'GET', '/magick-ai-core/v1/proposals/' . rawurlencode( $proposal_id ) );
		if ( is_wp_error( $proposal_response ) ) {
			return $proposal_response;
		}

		$proposal = $proposal_response->get_data();
		if ( ! is_array( $proposal ) ) {
			return new WP_Error(
				'magick_ai_adapter_invalid_core_proposal',
				__( 'Core proposal response is invalid.', 'magick-ai-adapter' ),
				array( 'status' => 502 )
			);
		}

		return $proposal;
	}

	/**
	 * @param string $proposal_id Proposal id.
	 * @param string $ability_id Ability id.
	 * @return true|WP_Error
	 */
	private function validate_execute_ability( string $proposal_id, string $ability_id ) {
		$profiles = self::execution_profiles();
		if ( isset( $profiles[ $ability_id ] ) ) {
			return true;
		}

		return new WP_Error(
			'magick_ai_adapter_execute_ability_not_allowed',
			__( 'This proposal ability is not allowed for adapter execution.', 'magick-ai-adapter' ),
			array(
				'status'                      => 403,
				'proposal_id'                 => $proposal_id,
				'ability_id'                  => $ability_id,
				'allowed_execute_ability_ids' => self::allowed_execute_ability_ids(),
			)
		);
	}

	/**
	 * Validates the Adapter-owned execution input shape for one allowlisted ability.
	 *
	 * @param string              $proposal_id Proposal id.
	 * @param string              $ability_id Ability id.
	 * @param array<string,mixed> $input Ability input.
	 * @param int                 $post_id Post id when the ability targets an existing post.
	 * @param int|null            $action_index Batch action index.
	 * @param bool                $allow_output_refs Whether batch output references may satisfy id fields.
	 * @return true|WP_Error
	 */
	private function validate_execute_action_input( string $proposal_id, string $ability_id, array $input, int $post_id, ?int $action_index = null, bool $allow_output_refs = false ) {
		$error_data = array(
			'status'      => 400,
			'proposal_id' => $proposal_id,
			'ability_id'  => $ability_id,
		);
		if ( null !== $action_index ) {
			$error_data['action_index']      = $action_index;
			$error_data['target_ability_id'] = $ability_id;
		}

		$profiles = self::execution_profiles();
		$profile  = is_array( $profiles[ $ability_id ] ?? null ) ? $profiles[ $ability_id ] : array();

		$allowed_input_fields = (array) ( $profile['allowed_input_fields'] ?? array() );
		if ( ! empty( $allowed_input_fields ) ) {
			foreach ( array_keys( $input ) as $field ) {
				$field = (string) $field;
				if ( in_array( $field, $allowed_input_fields, true ) ) {
					continue;
				}

				return new WP_Error(
					'magick_ai_adapter_ability_input_field_not_allowed',
					__( 'Proposal input includes a field that is not allowed for this ability.', 'magick-ai-adapter' ),
					array_merge(
						$error_data,
						array(
							'field'                => $field,
							'allowed_input_fields' => $allowed_input_fields,
						)
					)
				);
			}
		}

		$post_id_rule = is_array( $profile['require_post_id'] ?? null ) ? $profile['require_post_id'] : array();
		if ( ! empty( $post_id_rule ) && 0 === $post_id ) {
			if ( $allow_output_refs && $this->is_output_reference( $input['post_id'] ?? null ) ) {
				$post_id_rule = array();
			}
		}
		if ( ! empty( $post_id_rule ) && 0 === $post_id ) {
			return new WP_Error(
				(string) ( $post_id_rule['code'] ?? 'magick_ai_adapter_post_id_required' ),
				(string) ( $post_id_rule['message'] ?? __( 'Execution input must include post_id.', 'magick-ai-adapter' ) ),
				$error_data
			);
		}

		foreach ( (array) ( $profile['required_text_fields'] ?? array() ) as $field => $rule ) {
			$rule = is_array( $rule ) ? $rule : array();
			if ( '' !== trim( sanitize_text_field( (string) ( $input[ $field ] ?? '' ) ) ) ) {
				continue;
			}

			return new WP_Error(
				(string) ( $rule['code'] ?? 'magick_ai_adapter_required_text_missing' ),
				(string) ( $rule['message'] ?? __( 'Execution input is missing a required text field.', 'magick-ai-adapter' ) ),
				$error_data
			);
		}

		foreach ( (array) ( $profile['required_slug_fields'] ?? array() ) as $field => $rule ) {
			$rule = is_array( $rule ) ? $rule : array();
			if ( '' !== sanitize_title( (string) ( $input[ $field ] ?? '' ) ) ) {
				continue;
			}

			return new WP_Error(
				(string) ( $rule['code'] ?? 'magick_ai_adapter_required_slug_missing' ),
				(string) ( $rule['message'] ?? __( 'Execution input is missing a required slug field.', 'magick-ai-adapter' ) ),
				$error_data
			);
		}

		foreach ( (array) ( $profile['enum_fields'] ?? array() ) as $field => $rule ) {
			if ( ! array_key_exists( $field, $input ) ) {
				continue;
			}

			$rule    = is_array( $rule ) ? $rule : array();
			$value   = sanitize_key( (string) $input[ $field ] );
			$allowed = (array) ( $rule['allowed'] ?? array() );
			if ( in_array( $value, $allowed, true ) ) {
				continue;
			}

			return new WP_Error(
				(string) ( $rule['code'] ?? 'magick_ai_adapter_input_enum_invalid' ),
				(string) ( $rule['message'] ?? __( 'Proposal input includes an invalid enum value.', 'magick-ai-adapter' ) ),
				array_merge(
					$error_data,
					array(
						'field'          => (string) $field,
						'value'          => $value,
						'allowed_values' => $allowed,
					),
				)
			);
		}

		$any_fields_rule = is_array( $profile['require_any_fields'] ?? null ) ? $profile['require_any_fields'] : array();
		if ( ! empty( $any_fields_rule ) ) {
			$has_any_field = false;
			foreach ( (array) ( $any_fields_rule['fields'] ?? array() ) as $field ) {
				if ( array_key_exists( $field, $input ) ) {
					$has_any_field = true;
					break;
				}
			}
			if ( ! $has_any_field ) {
				return new WP_Error(
					(string) ( $any_fields_rule['code'] ?? 'magick_ai_adapter_required_fields_missing' ),
					(string) ( $any_fields_rule['message'] ?? __( 'Execution input is missing required fields.', 'magick-ai-adapter' ) ),
					$error_data
				);
			}
		}

		foreach ( (array) ( $profile['required_int_fields'] ?? array() ) as $field => $rule ) {
			$rule = is_array( $rule ) ? $rule : array();
			if ( 0 < absint( $input[ $field ] ?? 0 ) ) {
				continue;
			}
			if ( $allow_output_refs && $this->is_output_reference( $input[ $field ] ?? null ) ) {
				continue;
			}

			return new WP_Error(
				(string) ( $rule['code'] ?? 'magick_ai_adapter_required_int_missing' ),
				(string) ( $rule['message'] ?? __( 'Execution input is missing a required id.', 'magick-ai-adapter' ) ),
				$error_data
			);
		}

		if ( ! empty( $profile['validate_attachment_input'] ) ) {
			$attachment_id = absint( $input['attachment_id'] ?? 0 );
			$defer_attachment_check = $allow_output_refs && $this->is_output_reference( $input['attachment_id'] ?? null );
			if ( ! $defer_attachment_check && function_exists( 'get_post_type' ) && 'attachment' !== get_post_type( $attachment_id ) ) {
				return new WP_Error(
					'magick_ai_adapter_attachment_required',
					__( 'delete-media-permanently execution input must target an existing attachment.', 'magick-ai-adapter' ),
					array_merge(
						$error_data,
						array(
							'attachment_id' => $attachment_id,
						)
					)
				);
			}
		}

		if ( ! empty( $profile['validate_terms_input'] ) ) {
			$taxonomy = sanitize_key( (string) ( $input['taxonomy'] ?? 'post_tag' ) );
			if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
				return new WP_Error(
					'magick_ai_adapter_taxonomy_required',
					__( 'set-post-terms execution input must include a valid taxonomy.', 'magick-ai-adapter' ),
					$error_data
				);
			}

			$mode = sanitize_key( (string) ( $input['mode'] ?? 'replace' ) );
			if ( ! in_array( $mode, array( 'replace', 'append', 'remove' ), true ) ) {
				return new WP_Error(
					'magick_ai_adapter_term_mode_invalid',
					__( 'set-post-terms execution mode must be replace, append, or remove.', 'magick-ai-adapter' ),
					$error_data
				);
			}

			$term_ids = is_array( $input['term_ids'] ?? null ) ? array_filter( array_map( 'absint', $input['term_ids'] ) ) : array();
			$terms    = is_array( $input['terms'] ?? null ) ? array_filter(
				array_map(
					static function ( $term ) {
						return trim( sanitize_text_field( (string) $term ) );
					},
					$input['terms']
				)
			) : array();
			if ( empty( $term_ids ) && empty( $terms ) ) {
				return new WP_Error(
					'magick_ai_adapter_terms_required',
					__( 'set-post-terms execution input must include term_ids or terms.', 'magick-ai-adapter' ),
					$error_data
				);
			}

			if ( ! empty( $input['create_missing'] ) ) {
				return new WP_Error(
					'magick_ai_adapter_create_missing_terms_not_allowed',
					__( 'set-post-terms execution cannot create missing terms in this adapter policy.', 'magick-ai-adapter' ),
					$error_data
				);
			}
		}

		if ( ! empty( $profile['validate_delete_term_input'] ) ) {
			$taxonomy = array_key_exists( 'taxonomy', $input ) ? sanitize_key( (string) $input['taxonomy'] ) : '';
			if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
				return new WP_Error(
					'magick_ai_adapter_taxonomy_required',
					__( 'delete-term execution input must include a valid taxonomy.', 'magick-ai-adapter' ),
					$error_data
				);
			}
		}

		$comment_body_rule = is_array( $profile['require_comment_body'] ?? null ) ? $profile['require_comment_body'] : array();
		if ( ! empty( $comment_body_rule ) ) {
			$content = (string) ( $input['content'] ?? '' );
			if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
				return new WP_Error(
					(string) ( $comment_body_rule['code'] ?? 'magick_ai_adapter_comment_content_required' ),
					(string) ( $comment_body_rule['message'] ?? __( 'Comment execution input must include content.', 'magick-ai-adapter' ) ),
					$error_data
				);
			}
		}

		$content_format_rule = is_array( $profile['content_formats'] ?? null ) ? $profile['content_formats'] : array();
		if ( ! empty( $content_format_rule ) ) {
			$content_format = sanitize_key( (string) ( $input['content_format'] ?? 'html' ) );
			if ( ! in_array( $content_format, (array) ( $content_format_rule['allowed'] ?? array() ), true ) ) {
				return new WP_Error(
					(string) ( $content_format_rule['code'] ?? 'magick_ai_adapter_content_format_invalid' ),
					(string) ( $content_format_rule['message'] ?? __( 'Comment content_format is invalid.', 'magick-ai-adapter' ) ),
					$error_data
				);
			}
		}

		return true;
	}

	/**
	 * Checks whether a value is an exact batch output reference.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private function is_output_reference( $value ): bool {
		return is_string( $value ) && 1 === preg_match( '/^\$outputs\.[A-Za-z0-9_-]+\.[A-Za-z0-9_]+$/', $value );
	}

	/**
	 * Collects exact batch output references from a value tree.
	 *
	 * @param mixed $value Value.
	 * @return array<int,string>
	 */
	private function collect_output_references( $value ): array {
		if ( $this->is_output_reference( $value ) ) {
			return array( (string) $value );
		}
		if ( ! is_array( $value ) ) {
			return array();
		}

		$references = array();
		foreach ( $value as $child ) {
			$references = array_merge( $references, $this->collect_output_references( $child ) );
		}
		return array_values( array_unique( $references ) );
	}

	/**
	 * Finds a malformed output reference token in a value tree.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function invalid_output_reference_token( $value ): string {
		if ( is_string( $value ) ) {
			if ( false !== strpos( $value, '$outputs.' ) && ! $this->is_output_reference( $value ) ) {
				return $value;
			}
			return '';
		}
		if ( ! is_array( $value ) ) {
			return '';
		}

		foreach ( $value as $child ) {
			$invalid = $this->invalid_output_reference_token( $child );
			if ( '' !== $invalid ) {
				return $invalid;
			}
		}
		return '';
	}

	/**
	 * Parses one exact batch output reference.
	 *
	 * @param string $reference Reference.
	 * @return array{action_id:string,field:string}|null
	 */
	private function parse_output_reference( string $reference ): ?array {
		if ( 1 !== preg_match( '/^\$outputs\.([A-Za-z0-9_-]+)\.([A-Za-z0-9_]+)$/', $reference, $matches ) ) {
			return null;
		}

		return array(
			'action_id' => sanitize_key( $matches[1] ),
			'field'     => sanitize_key( $matches[2] ),
		);
	}

	/**
	 * Validates that output references only point to prior actions.
	 *
	 * @param string              $proposal_id Proposal id.
	 * @param array<string,mixed> $input Action input.
	 * @param array<string,bool>  $available_outputs Prior action ids.
	 * @param int                 $action_index Action index.
	 * @return true|WP_Error
	 */
	private function validate_output_references( string $proposal_id, array $input, array $available_outputs, int $action_index ) {
		$invalid_reference = $this->invalid_output_reference_token( $input );
		if ( '' !== $invalid_reference ) {
			return new WP_Error(
				'magick_ai_adapter_output_reference_invalid',
				__( 'Batch output references must use $outputs.action_id.field as the whole value.', 'magick-ai-adapter' ),
				array(
					'status'       => 400,
					'proposal_id'  => $proposal_id,
					'action_index' => $action_index,
					'reference'    => $invalid_reference,
				)
			);
		}

		foreach ( $this->collect_output_references( $input ) as $reference ) {
			$parsed = $this->parse_output_reference( $reference );
			if ( null === $parsed || empty( $available_outputs[ $parsed['action_id'] ] ) ) {
				return new WP_Error(
					'magick_ai_adapter_output_reference_unavailable',
					__( 'Batch output references must point to an earlier action in the same proposal.', 'magick-ai-adapter' ),
					array(
						'status'       => 400,
						'proposal_id'  => $proposal_id,
						'action_index' => $action_index,
						'reference'    => $reference,
					)
				);
			}
		}

		return true;
	}

	/**
	 * Resolves exact batch output references in an input value tree.
	 *
	 * @param mixed               $value Value.
	 * @param array<string,array<string,mixed>> $outputs Prior outputs keyed by action id.
	 * @param string              $proposal_id Proposal id.
	 * @param int                 $action_index Action index.
	 * @return mixed|WP_Error
	 */
	private function resolve_output_references( $value, array $outputs, string $proposal_id, int $action_index ) {
		if ( $this->is_output_reference( $value ) ) {
			$parsed = $this->parse_output_reference( (string) $value );
			if (
				null === $parsed
				|| ! array_key_exists( $parsed['action_id'], $outputs )
				|| ! array_key_exists( $parsed['field'], $outputs[ $parsed['action_id'] ] )
			) {
				return new WP_Error(
					'magick_ai_adapter_output_reference_unresolved',
					__( 'Batch output reference could not be resolved.', 'magick-ai-adapter' ),
					array(
						'status'       => 409,
						'proposal_id'  => $proposal_id,
						'action_index' => $action_index,
						'reference'    => (string) $value,
					)
				);
			}

			return $outputs[ $parsed['action_id'] ][ $parsed['field'] ];
		}

		$invalid_reference = $this->invalid_output_reference_token( $value );
		if ( '' !== $invalid_reference ) {
			return new WP_Error(
				'magick_ai_adapter_output_reference_invalid',
				__( 'Batch output references must use $outputs.action_id.field as the whole value.', 'magick-ai-adapter' ),
				array(
					'status'       => 400,
					'proposal_id'  => $proposal_id,
					'action_index' => $action_index,
					'reference'    => $invalid_reference,
				)
			);
		}

		if ( ! is_array( $value ) ) {
			return $value;
		}

		$resolved = array();
		foreach ( $value as $key => $child ) {
			$resolved_child = $this->resolve_output_references( $child, $outputs, $proposal_id, $action_index );
			if ( is_wp_error( $resolved_child ) ) {
				return $resolved_child;
			}
			$resolved[ $key ] = $resolved_child;
		}
		return $resolved;
	}

	/**
	 * Builds a flat output map for later batch actions.
	 *
	 * @param array<string,mixed> $result Executed action result.
	 * @return array<string,mixed>
	 */
	private function output_map_from_action_result( array $result ): array {
		$output = is_array( $result['result'] ?? null ) ? $result['result'] : array();
		foreach ( array( 'post_id', 'ability_id', 'target_ability_id', 'post_status_before', 'post_status_after' ) as $field ) {
			if ( array_key_exists( $field, $result ) ) {
				$output[ $field ] = $result[ $field ];
			}
		}
		return $output;
	}

	/**
	 * Normalizes one proposal into concrete Adapter execution actions.
	 *
	 * @param string              $proposal_id Proposal id.
	 * @param array<string,mixed> $proposal Core proposal.
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	private function normalize_execution_actions( string $proposal_id, array $proposal ) {
		$proposal_ability_id = sanitize_text_field( (string) ( $proposal['ability_id'] ?? '' ) );
		$input               = is_array( $proposal['input'] ?? null ) ? $proposal['input'] : array();
		$write_actions       = is_array( $input['write_actions'] ?? null ) ? array_values( $input['write_actions'] ) : array();
		$has_write_actions   = ! empty( $write_actions );
		$top_level_post_id   = absint( $input['post_id'] ?? 0 );

		if ( $has_write_actions && $top_level_post_id > 0 ) {
			return new WP_Error(
				'magick_ai_adapter_execution_input_ambiguous',
				__( 'Proposal input must use either post_id or write_actions, not both.', 'magick-ai-adapter' ),
				array(
					'status'      => 400,
					'proposal_id' => $proposal_id,
				)
			);
		}

		if ( $has_write_actions ) {
			if ( count( $write_actions ) > self::MAX_EXECUTION_ACTIONS ) {
				return new WP_Error(
					'magick_ai_adapter_write_actions_limit_exceeded',
					__( 'Proposal write_actions exceeds the adapter execution limit.', 'magick-ai-adapter' ),
					array(
						'status'      => 400,
						'proposal_id' => $proposal_id,
						'max_actions' => self::MAX_EXECUTION_ACTIONS,
					)
				);
			}

			$actions           = array();
			$available_outputs = array();
			foreach ( $write_actions as $index => $raw_action ) {
				if ( ! is_array( $raw_action ) ) {
					return new WP_Error(
						'magick_ai_adapter_write_action_invalid',
						__( 'Each write_actions item must be an object.', 'magick-ai-adapter' ),
						array(
							'status'       => 400,
							'proposal_id'  => $proposal_id,
							'action_index' => $index,
						)
					);
				}

				$target_ability_id = sanitize_text_field( (string) ( $raw_action['target_ability_id'] ?? '' ) );
				if ( '' === $target_ability_id ) {
					return new WP_Error(
						'magick_ai_adapter_write_action_target_required',
						__( 'Each write_actions item must include target_ability_id.', 'magick-ai-adapter' ),
						array(
							'status'       => 400,
							'proposal_id'  => $proposal_id,
							'action_index' => $index,
						)
					);
				}

				$allowed = $this->validate_execute_ability( $proposal_id, $target_ability_id );
				if ( is_wp_error( $allowed ) ) {
					$allowed->add_data(
						array_merge(
							(array) $allowed->get_error_data(),
							array(
								'action_index'      => $index,
								'action_id'         => sanitize_key( (string) ( $raw_action['action_id'] ?? '' ) ),
								'target_ability_id' => $target_ability_id,
							)
						)
					);
					return $allowed;
				}

				$action_id = sanitize_key( (string) ( $raw_action['action_id'] ?? '' ) );
				if ( '' === $action_id ) {
					$action_id = 'action-' . ( $index + 1 );
				}
				if ( isset( $available_outputs[ $action_id ] ) ) {
					return new WP_Error(
						'magick_ai_adapter_write_action_duplicate_id',
						__( 'Each write_actions item must have a unique action_id.', 'magick-ai-adapter' ),
						array(
							'status'       => 400,
							'proposal_id'  => $proposal_id,
							'action_index' => $index,
							'action_id'    => $action_id,
						)
					);
				}

				$action_input = is_array( $raw_action['input'] ?? null ) ? $raw_action['input'] : array();
				$valid_refs   = $this->validate_output_references( $proposal_id, $action_input, $available_outputs, $index );
				if ( is_wp_error( $valid_refs ) ) {
					return $valid_refs;
				}

				$post_id      = absint( $action_input['post_id'] ?? 0 );
				$valid_input  = $this->validate_execute_action_input( $proposal_id, $target_ability_id, $action_input, $post_id, $index, true );
				if ( is_wp_error( $valid_input ) ) {
					return $valid_input;
				}

				if ( array_key_exists( 'requires_approval', $raw_action ) && true !== (bool) $raw_action['requires_approval'] ) {
					return new WP_Error(
						'magick_ai_adapter_write_action_approval_required',
						__( 'Each executable write action must require Core approval.', 'magick-ai-adapter' ),
						array(
							'status'       => 409,
							'proposal_id'  => $proposal_id,
							'action_index' => $index,
						)
					);
				}

				if ( array_key_exists( 'commit_execution', $raw_action ) && false !== (bool) $raw_action['commit_execution'] ) {
					return new WP_Error(
						'magick_ai_adapter_write_action_commit_execution_not_allowed',
						__( 'Write actions must keep commit_execution=false before Adapter execution.', 'magick-ai-adapter' ),
						array(
							'status'       => 409,
							'proposal_id'  => $proposal_id,
							'action_index' => $index,
						)
					);
				}

				$requires_input = is_array( $raw_action['requires_input'] ?? null ) ? array_values( $raw_action['requires_input'] ) : array();
				if ( ! empty( $requires_input ) ) {
					return new WP_Error(
						'magick_ai_adapter_write_action_needs_input',
						__( 'Write action still requires reviewed input before execution.', 'magick-ai-adapter' ),
						array(
							'status'         => 409,
							'proposal_id'    => $proposal_id,
							'action_index'   => $index,
							'requires_input' => $requires_input,
						)
					);
				}

				$preflight_blockers = is_array( $raw_action['preflight_blockers'] ?? null ) ? array_values( $raw_action['preflight_blockers'] ) : array();
				if ( ( array_key_exists( 'proposal_ready', $raw_action ) && false === (bool) $raw_action['proposal_ready'] ) || ! empty( $preflight_blockers ) ) {
					return new WP_Error(
						'magick_ai_adapter_write_action_not_ready',
						__( 'Write action is not marked ready for execution.', 'magick-ai-adapter' ),
						array(
							'status'              => 409,
							'proposal_id'         => $proposal_id,
							'action_index'        => $index,
							'preflight_blockers'  => $preflight_blockers,
						)
					);
				}

				$actions[] = array(
					'action_id'         => $action_id,
					'action_index'      => $index,
					'ability_id'        => $target_ability_id,
					'target_ability_id' => $target_ability_id,
					'post_id'           => $post_id,
					'input'             => $action_input,
					'execution_mode'    => 'batch_write_actions',
				);
				$available_outputs[ $action_id ] = true;
			}

			return $actions;
		}

		$allowed = $this->validate_execute_ability( $proposal_id, $proposal_ability_id );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		$valid_input = $this->validate_execute_action_input( $proposal_id, $proposal_ability_id, $input, $top_level_post_id );
		if ( is_wp_error( $valid_input ) ) {
			return $valid_input;
		}

		return array(
			array(
				'action_id'         => 'single-post',
				'action_index'      => 0,
				'ability_id'        => $proposal_ability_id,
				'target_ability_id' => $proposal_ability_id,
				'post_id'           => $top_level_post_id,
				'input'             => $input,
				'execution_mode'    => 'single_post',
			),
		);
	}

	/**
	 * Executes one normalized action through WordPress Abilities API.
	 *
	 * @param WP_REST_Request     $request Request.
	 * @param string              $proposal_id Proposal id.
	 * @param array<string,mixed> $action Normalized action.
	 * @param array<string,mixed> $approval_context Core approval context.
	 * @param string              $correlation_id Correlation id.
	 * @param array<string,mixed> $base_request_context Base request context.
	 * @return array<string,mixed>|WP_Error
	 */
	private function execute_normalized_action( WP_REST_Request $request, string $proposal_id, array $action, array $approval_context, string $correlation_id, array $base_request_context ) {
		$ability_id = sanitize_text_field( (string) ( $action['ability_id'] ?? '' ) );
		$post_id    = absint( $action['post_id'] ?? 0 );
		$profiles   = self::execution_profiles();
		$profile    = is_array( $profiles[ $ability_id ] ?? null ) ? $profiles[ $ability_id ] : array();

		$post_status_before = get_post_status( $post_id );
		$post_status_before = false === $post_status_before ? '' : (string) $post_status_before;

		$ability_input = is_array( $action['input'] ?? null ) ? $action['input'] : array();
		if ( ! empty( $profile['force_post_input'] ) ) {
			$ability_input = array(
				'post_id' => $post_id,
				'dry_run' => false,
				'commit'  => true,
			);
		} else {
			$ability_input['dry_run'] = false;
			$ability_input['commit']  = true;
		}

		$route           = '/wp-abilities/v1/abilities/' . $ability_id . '/run';
		$request_context = $base_request_context;
		$request_context['ability_id'] = $ability_id;
		$context         = array_merge(
			$approval_context,
			$request_context,
			array(
				'ability_id'        => $ability_id,
				'target_ability_id' => sanitize_text_field( (string) ( $action['target_ability_id'] ?? $ability_id ) ),
				'action_id'         => sanitize_key( (string) ( $action['action_id'] ?? '' ) ),
				'action_index'      => absint( $action['action_index'] ?? 0 ),
				'proposal_id'       => $proposal_id,
				'post_id'           => $post_id,
				'correlation_id'    => $correlation_id,
				'via'               => 'magick-ai-adapter',
			)
		);

		$response = $this->dispatch_upstream_with_runtime_context( $context, 'POST', $route, array( 'input' => $ability_input ), false, true );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$result_data = $response->get_data();
		if ( ! empty( $profile['post_id_from_result'] ) && is_array( $result_data ) ) {
			$post_id = absint( $result_data['post_id'] ?? $post_id );
		}

		$post_status_after = get_post_status( $post_id );

		return array(
			'action_id'          => sanitize_key( (string) ( $action['action_id'] ?? '' ) ),
			'action_index'       => absint( $action['action_index'] ?? 0 ),
			'target_ability_id'  => sanitize_text_field( (string) ( $action['target_ability_id'] ?? $ability_id ) ),
			'ability_id'         => $ability_id,
			'post_id'            => $post_id,
			'status'             => 'executed',
			'post_status_before' => $post_status_before,
			'post_status_after'  => false === $post_status_after ? '' : (string) $post_status_after,
			'adapter_request_id' => (string) ( $context['adapter_request_id'] ?? '' ),
			'result'             => $result_data,
		);
	}

	/**
	 * Executes an approved proposal after Core commit preflight.
	 *
	 * @param WP_REST_Request     $request Request.
	 * @param string              $proposal_id Proposal id.
	 * @param array<string,mixed> $proposal Core proposal.
	 * @return array<string,mixed>|WP_Error
	 */
	private function execute_core_approved_proposal( WP_REST_Request $request, string $proposal_id, array $proposal ) {
		$proposal_ability_id = sanitize_text_field( (string) ( $proposal['ability_id'] ?? '' ) );
		$existing_record     = $this->completed_execution_record( $proposal_id );
		if ( is_array( $existing_record ) ) {
			return $this->execution_already_completed_error( $proposal_id, $existing_record );
		}

		$actions             = $this->normalize_execution_actions( $proposal_id, $proposal );
		if ( is_wp_error( $actions ) ) {
			return $actions;
		}

		$preflight        = $this->consume_cached_preflight_handoff( $proposal_id, $proposal );
		$preflight_source = is_array( $preflight ) ? 'adapter_cached_handoff' : 'core_commit_preflight';
		if ( ! is_array( $preflight ) ) {
			$preflight_response = $this->dispatch_upstream( 'POST', '/magick-ai-core/v1/proposals/' . rawurlencode( $proposal_id ) . '/commit-preflight' );
			if ( is_wp_error( $preflight_response ) ) {
				return $this->error_with_operator_feedback( $preflight_response, $this->preflight_operator_feedback( $preflight_response, $proposal ) );
			}

			$preflight = $preflight_response->get_data();
			if ( ! is_array( $preflight ) ) {
				return new WP_Error(
					'magick_ai_adapter_invalid_core_preflight',
					__( 'Core commit preflight response is invalid.', 'magick-ai-adapter' ),
					array( 'status' => 502 )
				);
			}
		}
		$preflight['adapter_preflight_source'] = $preflight_source;

		$approval_context = is_array( $preflight['approval_context'] ?? null ) ? $preflight['approval_context'] : array();
		if ( true !== (bool) ( $approval_context['approval_commit_authorized'] ?? false ) ) {
			return new WP_Error(
				'magick_ai_adapter_preflight_not_authorized',
				__( 'Core commit preflight did not authorize approval commit.', 'magick-ai-adapter' ),
				array(
					'status'            => 409,
					'proposal_id'       => $proposal_id,
					'preflight'         => $preflight,
					'operator_feedback' => $this->preflight_operator_feedback( null, $proposal, $preflight ),
				)
			);
		}

		if ( false !== (bool) ( $preflight['commit_execution'] ?? true ) ) {
			return new WP_Error(
				'magick_ai_adapter_core_execution_not_allowed',
				__( 'Core commit preflight must not execute final writes.', 'magick-ai-adapter' ),
				array(
					'status'      => 409,
					'proposal_id' => $proposal_id,
					'preflight'   => $preflight,
				)
			);
		}

		if ( false === (bool) ( $preflight['proposal_item_preflight']['executable'] ?? true ) ) {
			return new WP_Error(
				'magick_ai_adapter_preflight_item_blocked',
				__( 'Core commit preflight did not mark the proposal item executable.', 'magick-ai-adapter' ),
				array(
					'status'            => 409,
					'proposal_id'       => $proposal_id,
					'preflight'         => $preflight,
					'operator_feedback' => $this->preflight_operator_feedback( null, $proposal, $preflight ),
				)
			);
		}

		$correlation_id = sanitize_text_field( (string) ( $preflight['correlation_id'] ?? ( $approval_context['correlation_id'] ?? '' ) ) );
		if ( '' === $correlation_id ) {
			return new WP_Error(
				'magick_ai_adapter_preflight_correlation_required',
				__( 'Core commit preflight did not return a correlation id.', 'magick-ai-adapter' ),
				array(
					'status'      => 409,
					'proposal_id' => $proposal_id,
					'preflight'   => $preflight,
				)
			);
		}

		$base_request_context = $this->request_log_context( $request, '' !== $proposal_ability_id ? $proposal_ability_id : (string) ( $actions[0]['ability_id'] ?? '' ) );
		$base_request_context['proposal_id']    = $proposal_id;
		$base_request_context['correlation_id'] = $correlation_id;
		$magick_ai_core = is_array( $base_request_context['magick_ai_core'] ?? null ) ? $base_request_context['magick_ai_core'] : array();
		$magick_ai_core['proposal_id']    = $proposal_id;
		$magick_ai_core['correlation_id'] = $correlation_id;
		$base_request_context['magick_ai_core'] = $magick_ai_core;

		$results = array();
		$outputs = array();
		foreach ( $actions as $action ) {
			$action_index = absint( $action['action_index'] ?? 0 );
			$resolved_input = $this->resolve_output_references(
				is_array( $action['input'] ?? null ) ? $action['input'] : array(),
				$outputs,
				$proposal_id,
				$action_index
			);
			if ( is_wp_error( $resolved_input ) ) {
				$resolved_input->add_data(
					array_merge(
						(array) $resolved_input->get_error_data(),
						array(
							'correlation_id'   => $correlation_id,
							'action_id'        => sanitize_key( (string) ( $action['action_id'] ?? '' ) ),
							'executed_results' => $results,
						)
					)
				);
				return $resolved_input;
			}

			$action['input']   = is_array( $resolved_input ) ? $resolved_input : array();
			$action['post_id'] = absint( $action['input']['post_id'] ?? 0 );
			$valid_input       = $this->validate_execute_action_input(
				$proposal_id,
				sanitize_text_field( (string) ( $action['ability_id'] ?? '' ) ),
				$action['input'],
				absint( $action['post_id'] ?? 0 ),
				$action_index
			);
			if ( is_wp_error( $valid_input ) ) {
				$valid_input->add_data(
					array_merge(
						(array) $valid_input->get_error_data(),
						array(
							'correlation_id'   => $correlation_id,
							'action_id'        => sanitize_key( (string) ( $action['action_id'] ?? '' ) ),
							'executed_results' => $results,
						)
					)
				);
				return $valid_input;
			}

			$result = $this->execute_normalized_action( $request, $proposal_id, $action, $approval_context, $correlation_id, $base_request_context );
			if ( is_wp_error( $result ) ) {
				$error_data = $result->get_error_data();
				$error_data = is_array( $error_data ) ? $error_data : array();
				$status     = absint( $error_data['status'] ?? 0 );
				if ( 0 === $status ) {
					$status = 409;
				}

				$result->add_data(
					array_merge(
						$error_data,
						array(
							'status'           => $status,
							'proposal_id'      => $proposal_id,
							'correlation_id'   => $correlation_id,
							'action_id'        => sanitize_key( (string) ( $action['action_id'] ?? '' ) ),
							'action_index'     => absint( $action['action_index'] ?? 0 ),
							'executed_results' => $results,
						)
					)
				);
				return $result;
			}

			$results[] = $result;
			$outputs[ sanitize_key( (string) ( $result['action_id'] ?? '' ) ) ] = $this->output_map_from_action_result( $result );
		}

		$first_result      = is_array( $results[0] ?? null ) ? $results[0] : array();
		$post_ids          = array_values(
			array_map(
				'absint',
				array_column( $results, 'post_id' )
			)
		);
		$target_ability_ids = array_values(
			array_unique(
				array_map(
					static function ( $result ) {
						return is_array( $result ) ? sanitize_text_field( (string) ( $result['target_ability_id'] ?? '' ) ) : '';
					},
					$results
				)
			)
		);
		$target_ability_ids = array_values( array_filter( $target_ability_ids ) );
		$execution_mode     = count( $actions ) > 1 || 'batch_write_actions' === (string) ( $actions[0]['execution_mode'] ?? '' ) ? 'batch_write_actions' : 'single_post';
		$response_ability_id = 1 === count( $target_ability_ids ) ? $target_ability_ids[0] : $proposal_ability_id;

		$execution = array(
			'ability_id'          => $response_ability_id,
			'post_id'             => absint( $first_result['post_id'] ?? 0 ),
			'post_ids'            => $post_ids,
			'correlation_id'      => $correlation_id,
			'adapter_request_id'  => (string) ( $base_request_context['adapter_request_id'] ?? '' ),
			'approval_context'    => $approval_context,
			'preflight_source'    => $preflight_source,
			'preflight'           => $preflight,
			'execution_mode'      => $execution_mode,
			'executed_count'      => count( $results ),
			'failed_count'        => 0,
			'results'             => $results,
			'post_status_before'  => (string) ( $first_result['post_status_before'] ?? '' ),
			'post_status_after'   => (string) ( $first_result['post_status_after'] ?? '' ),
			'result'              => 1 === count( $results ) ? ( $first_result['result'] ?? array() ) : array(
				'success'        => true,
				'execution_mode' => $execution_mode,
				'executed_count' => count( $results ),
				'failed_count'   => 0,
				'results'        => $results,
			),
		);
		$execution['execution_record'] = $this->store_completed_execution_record( $proposal_id, $proposal, $execution );

		return $execution;
	}

	/**
	 * Adds operator-facing feedback to an error without changing its code.
	 *
	 * @param WP_Error            $error Error.
	 * @param array<string,mixed> $feedback Feedback payload.
	 * @return WP_Error
	 */
	private function error_with_operator_feedback( WP_Error $error, array $feedback ): WP_Error {
		$data = $error->get_error_data();
		$data = is_array( $data ) ? $data : array();
		if ( ! isset( $data['operator_feedback'] ) ) {
			$data['operator_feedback'] = $feedback;
		}
		$error->add_data( $data );

		return $error;
	}

	/**
	 * Builds operator feedback for plan handoff failures.
	 *
	 * @param WP_Error $error Error.
	 * @param string   $plan_ability_id Planning ability id.
	 * @return array<string,mixed>
	 */
	private function plan_handoff_operator_feedback( WP_Error $error, string $plan_ability_id ): array {
		$error_data = $this->error_data_array( $error );
		$core_data  = $this->upstream_error_detail( $error );
		$blocked    = is_array( $error_data['blocked_items'] ?? null ) ? $error_data['blocked_items'] : array();
		if ( empty( $blocked ) && is_array( $core_data['blocked_items'] ?? null ) ) {
			$blocked = $core_data['blocked_items'];
		}

		$reasons = $this->operator_reasons_from_blocked_items( $blocked );
		if ( empty( $reasons ) ) {
			$reasons[] = $error->get_error_message();
		}

		return array(
			'status'                   => 'plan_revision_required',
			'severity'                 => 'error',
			'message'                  => __( 'The plan was not accepted for Core proposal intake.', 'magick-ai-adapter' ),
			'reasons'                  => $reasons,
			'revision_fields'          => $this->operator_revision_fields( $blocked, $core_data ),
			'next_steps'               => array(
				__( 'Show these reasons to the operator.', 'magick-ai-adapter' ),
				__( 'Revise the Toolbox plan or reviewed draft, then submit a new from-plan request.', 'magick-ai-adapter' ),
				__( 'Do not call approve-and-execute until Core creates a proposal.', 'magick-ai-adapter' ),
			),
			'can_retry_after_revision' => true,
			'core_evidence'            => array(
				'plan_ability_id'    => sanitize_text_field( $plan_ability_id ),
				'adapter_error_code' => $error->get_error_code(),
				'core_error_code'    => (string) ( $core_data['code'] ?? '' ),
				'blocked_count'      => count( $blocked ),
			),
		);
	}

	/**
	 * Builds operator feedback for proposal status failures.
	 *
	 * @param array<string,mixed> $proposal Core proposal.
	 * @param string              $status_before Proposal status before execution.
	 * @return array<string,mixed>
	 */
	private function proposal_status_operator_feedback( array $proposal, string $status_before ): array {
		$proposal_id = sanitize_text_field( (string) ( $proposal['proposal_id'] ?? '' ) );
		$ability_id  = sanitize_text_field( (string) ( $proposal['ability_id'] ?? '' ) );
		$reasons     = 'rejected' === $status_before ? $this->core_rejection_reasons( $proposal ) : array();
		if ( empty( $reasons ) ) {
			$reasons[] = sprintf(
				/* translators: %s: proposal status. */
				__( 'Core proposal status is %s.', 'magick-ai-adapter' ),
				$status_before
			);
		}

		return array(
			'status'                   => 'proposal_' . sanitize_key( $status_before ),
			'severity'                 => 'error',
			'message'                  => 'rejected' === $status_before
				? __( 'Core rejected this proposal. Adapter will not execute it.', 'magick-ai-adapter' )
				: __( 'This proposal is not in an executable Core status.', 'magick-ai-adapter' ),
			'reasons'                  => $reasons,
			'revision_fields'          => array(),
			'next_steps'               => array(
				__( 'Show the Core decision to the operator.', 'magick-ai-adapter' ),
				__( 'Revise the source plan or draft, then create a new Core proposal.', 'magick-ai-adapter' ),
				__( 'Do not retry approve-and-execute against this proposal id.', 'magick-ai-adapter' ),
			),
			'can_retry_after_revision' => true,
			'core_evidence'            => array(
				'proposal_id'  => $proposal_id,
				'ability_id'   => $ability_id,
				'status'       => sanitize_key( $status_before ),
				'detail_route' => '/wp-json/magick-ai-core/v1/proposals/' . rawurlencode( $proposal_id ),
			),
		);
	}

	/**
	 * Builds operator feedback for commit-preflight failures.
	 *
	 * @param WP_Error|null       $error Error, when Core returned one.
	 * @param array<string,mixed> $proposal Core proposal.
	 * @param array<string,mixed> $preflight Preflight payload, when available.
	 * @return array<string,mixed>
	 */
	private function preflight_operator_feedback( ?WP_Error $error, array $proposal, array $preflight = array() ): array {
		if ( empty( $preflight ) && null !== $error ) {
			$preflight = $this->upstream_error_detail( $error );
		}

		$item_preflight = is_array( $preflight['proposal_item_preflight'] ?? null ) ? $preflight['proposal_item_preflight'] : array();
		$blocked        = is_array( $item_preflight['blocked_items'] ?? null ) ? $item_preflight['blocked_items'] : array();
		$needs_input    = array_values( array_map( 'sanitize_key', (array) ( $item_preflight['needs_input'] ?? array() ) ) );
		$reasons        = $this->operator_reasons_from_blocked_items( $blocked );

		foreach ( $needs_input as $field ) {
			$reasons[] = sprintf(
				/* translators: %s: missing field name. */
				__( 'Missing required input: %s.', 'magick-ai-adapter' ),
				$field
			);
		}

		if ( false === (bool) ( $item_preflight['proposal_ready'] ?? true ) && empty( $reasons ) ) {
			$reasons[] = __( 'Core marks the proposal item as not ready for execution.', 'magick-ai-adapter' );
		}
		if ( empty( $reasons ) ) {
			$reasons[] = null !== $error ? $error->get_error_message() : __( 'Core commit preflight did not authorize execution.', 'magick-ai-adapter' );
		}

		$core_error_code = null !== $error ? $error->get_error_code() : '';
		if ( 'magick_ai_core_commit_preflight_already_issued' === $core_error_code ) {
			$reasons[] = __( 'Core has already issued the one-time execution handoff. If commit-preflight was called directly against Core, Adapter cannot recover that handoff.', 'magick-ai-adapter' );
		}

		$next_steps = array(
			__( 'Show Core preflight blockers to the operator.', 'magick-ai-adapter' ),
			__( 'Revise the proposal input or source plan, then create a new proposal.', 'magick-ai-adapter' ),
			__( 'Do not retry approve-and-execute until Core preflight can pass.', 'magick-ai-adapter' ),
		);
		if ( 'magick_ai_core_commit_preflight_already_issued' === $core_error_code ) {
			$next_steps = array(
				__( 'Create a new proposal for the same intended write.', 'magick-ai-adapter' ),
				__( 'After approval, call Adapter execute or approve-and-execute; do not call Core commit-preflight directly.', 'magick-ai-adapter' ),
				__( 'Use Adapter commit-preflight only as an advanced diagnostic step and follow it immediately with Adapter execute.', 'magick-ai-adapter' ),
			);
		}

		return array(
			'status'                   => 'preflight_blocked',
			'severity'                 => 'error',
			'message'                  => __( 'Core commit preflight blocked execution. Adapter did not run the write ability.', 'magick-ai-adapter' ),
			'reasons'                  => array_values( array_unique( $reasons ) ),
			'revision_fields'          => array_values( array_unique( $needs_input ) ),
			'next_steps'               => $next_steps,
			'can_retry_after_revision' => true,
			'core_evidence'            => array(
				'proposal_id'             => sanitize_text_field( (string) ( $proposal['proposal_id'] ?? '' ) ),
				'ability_id'              => sanitize_text_field( (string) ( $proposal['ability_id'] ?? '' ) ),
				'status'                  => sanitize_key( (string) ( $proposal['status'] ?? '' ) ),
				'core_error_code'         => $core_error_code,
				'proposal_item_preflight' => $item_preflight,
				'commit_execution'        => false,
			),
		);
	}

	/**
	 * Returns rejection reasons from Core audit timeline.
	 *
	 * @param array<string,mixed> $proposal Core proposal.
	 * @return array<int,string>
	 */
	private function core_rejection_reasons( array $proposal ): array {
		$reasons = array();
		foreach ( (array) ( $proposal['audit_timeline'] ?? array() ) as $event ) {
			if ( ! is_array( $event ) || 'proposal.rejected' !== (string) ( $event['event_name'] ?? '' ) ) {
				continue;
			}

			$metadata = is_array( $event['metadata'] ?? null ) ? $event['metadata'] : array();
			$note     = sanitize_textarea_field( (string) ( $metadata['note'] ?? '' ) );
			if ( '' !== $note ) {
				$reasons[] = $note;
			}
		}

		return array_values( array_unique( $reasons ) );
	}

	/**
	 * Extracts readable reasons from blocked item rows.
	 *
	 * @param array<int,mixed> $blocked_items Blocked items.
	 * @return array<int,string>
	 */
	private function operator_reasons_from_blocked_items( array $blocked_items ): array {
		$reasons = array();
		foreach ( $blocked_items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$reason = sanitize_textarea_field( (string) ( $item['reason'] ?? '' ) );
			$code   = sanitize_key( (string) ( $item['block_code'] ?? ( $item['code'] ?? '' ) ) );
			if ( '' !== $reason && '' !== $code ) {
				$reasons[] = $code . ': ' . $reason;
			} elseif ( '' !== $reason ) {
				$reasons[] = $reason;
			} elseif ( '' !== $code ) {
				$reasons[] = $code;
			}
		}

		return array_values( array_unique( $reasons ) );
	}

	/**
	 * Extracts likely fields the operator needs to revise.
	 *
	 * @param array<int,mixed>    $blocked_items Blocked items.
	 * @param array<string,mixed> $core_data Core or Adapter error data.
	 * @return array<int,string>
	 */
	private function operator_revision_fields( array $blocked_items, array $core_data ): array {
		$fields = array();
		foreach ( $blocked_items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			if ( isset( $item['field'] ) ) {
				$fields[] = sanitize_key( (string) $item['field'] );
			}
			foreach ( (array) ( $item['fields'] ?? array() ) as $field ) {
				$fields[] = sanitize_key( (string) $field );
			}
		}

		foreach ( (array) ( $core_data['needs_input'] ?? array() ) as $field ) {
			$fields[] = sanitize_key( (string) $field );
		}

		return array_values( array_unique( array_filter( $fields ) ) );
	}

	/**
	 * Returns a WP_Error data array.
	 *
	 * @param WP_Error $error Error.
	 * @return array<string,mixed>
	 */
	private function error_data_array( WP_Error $error ): array {
		$data = $error->get_error_data();
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Extracts the upstream error detail payload from Adapter wrapped errors.
	 *
	 * @param WP_Error $error Error.
	 * @return array<string,mixed>
	 */
	private function upstream_error_detail( WP_Error $error ): array {
		$data     = $this->error_data_array( $error );
		$upstream = is_array( $data['upstream_data'] ?? null ) ? $data['upstream_data'] : array();
		if ( empty( $upstream ) ) {
			return $data;
		}

		$detail = is_array( $upstream['data'] ?? null ) ? $upstream['data'] : $upstream;
		if ( is_array( $detail['data'] ?? null ) ) {
			$detail = $detail['data'];
		}

		if ( ! isset( $detail['code'] ) && isset( $upstream['code'] ) ) {
			$detail['code'] = $upstream['code'];
		}
		if ( ! isset( $detail['message'] ) && isset( $upstream['message'] ) ) {
			$detail['message'] = $upstream['message'];
		}

		return is_array( $detail ) ? $detail : array();
	}

	/**
	 * Returns stored preflight handoffs issued through Adapter.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function preflight_handoffs(): array {
		$records = get_option( self::PREFLIGHT_HANDOFFS_OPTION, array() );
		return is_array( $records ) ? $records : array();
	}

	/**
	 * Stores a Core preflight handoff for the next Adapter execute call.
	 *
	 * @param string              $proposal_id Proposal id.
	 * @param array<string,mixed> $proposal Core proposal.
	 * @param array<string,mixed> $preflight Core preflight payload.
	 * @return array<string,mixed>|null
	 */
	private function store_preflight_handoff( string $proposal_id, array $proposal, array $preflight ): ?array {
		if ( '' === $proposal_id || empty( $proposal ) ) {
			return null;
		}

		$approval_context = is_array( $preflight['approval_context'] ?? null ) ? $preflight['approval_context'] : array();
		$approved_hash    = sanitize_text_field( (string) ( $approval_context['approved_input_hash'] ?? ( $preflight['approved_input_hash'] ?? '' ) ) );
		$current_hash     = $this->proposal_input_hash( $proposal );
		if (
			true !== (bool) ( $approval_context['approval_commit_authorized'] ?? false )
			|| false !== (bool) ( $preflight['commit_execution'] ?? true )
			|| $proposal_id !== (string) ( $approval_context['proposal_id'] ?? $proposal_id )
			|| '' === $approved_hash
			|| $approved_hash !== $current_hash
		) {
			return null;
		}

		$handoff = array(
			'status'              => 'issued',
			'proposal_id'         => $proposal_id,
			'ability_id'          => sanitize_text_field( (string) ( $proposal['ability_id'] ?? ( $approval_context['ability_id'] ?? '' ) ) ),
			'approved_input_hash' => $approved_hash,
			'correlation_id'      => sanitize_text_field( (string) ( $preflight['correlation_id'] ?? ( $approval_context['correlation_id'] ?? '' ) ) ),
			'commit_execution'    => false,
			'issued_at'           => gmdate( 'c' ),
			'preflight'           => array(
				'proposal_id'             => $proposal_id,
				'correlation_id'          => sanitize_text_field( (string) ( $preflight['correlation_id'] ?? ( $approval_context['correlation_id'] ?? '' ) ) ),
				'approval_context'        => $approval_context,
				'proposal_item_preflight' => is_array( $preflight['proposal_item_preflight'] ?? null ) ? $preflight['proposal_item_preflight'] : array(),
				'execution_handoff'       => is_array( $preflight['execution_handoff'] ?? null ) ? $preflight['execution_handoff'] : array(),
				'idempotency_required'    => (bool) ( $preflight['idempotency_required'] ?? true ),
				'commit_execution'        => false,
			),
		);

		$records = $this->preflight_handoffs();
		$records[ $this->execution_record_key( $proposal_id ) ] = $handoff;
		$records = $this->prune_preflight_handoffs( $records );
		update_option( self::PREFLIGHT_HANDOFFS_OPTION, $records, false );

		return $handoff;
	}

	/**
	 * Consumes a cached preflight handoff when it still matches the proposal.
	 *
	 * @param string              $proposal_id Proposal id.
	 * @param array<string,mixed> $proposal Core proposal.
	 * @return array<string,mixed>|null
	 */
	private function consume_cached_preflight_handoff( string $proposal_id, array $proposal ): ?array {
		$records = $this->preflight_handoffs();
		$key     = $this->execution_record_key( $proposal_id );
		$record  = is_array( $records[ $key ] ?? null ) ? $records[ $key ] : array();
		if ( empty( $record ) ) {
			return null;
		}

		unset( $records[ $key ] );
		update_option( self::PREFLIGHT_HANDOFFS_OPTION, $records, false );

		$preflight        = is_array( $record['preflight'] ?? null ) ? $record['preflight'] : array();
		$approval_context = is_array( $preflight['approval_context'] ?? null ) ? $preflight['approval_context'] : array();
		$approved_hash    = sanitize_text_field( (string) ( $record['approved_input_hash'] ?? ( $approval_context['approved_input_hash'] ?? '' ) ) );
		if (
			'issued' !== (string) ( $record['status'] ?? '' )
			|| $proposal_id !== (string) ( $record['proposal_id'] ?? '' )
			|| $proposal_id !== (string) ( $approval_context['proposal_id'] ?? $proposal_id )
			|| true !== (bool) ( $approval_context['approval_commit_authorized'] ?? false )
			|| false !== (bool) ( $preflight['commit_execution'] ?? true )
			|| '' === $approved_hash
			|| $approved_hash !== $this->proposal_input_hash( $proposal )
		) {
			return null;
		}

		return $preflight;
	}

	/**
	 * Removes old preflight handoffs after the bounded retention limit.
	 *
	 * @param array<string,array<string,mixed>> $records Records.
	 * @return array<string,array<string,mixed>>
	 */
	private function prune_preflight_handoffs( array $records ): array {
		if ( count( $records ) <= self::MAX_PREFLIGHT_HANDOFFS ) {
			return $records;
		}

		uasort(
			$records,
			static function ( $left, $right ): int {
				$left_time  = is_array( $left ) ? (string) ( $left['issued_at'] ?? '' ) : '';
				$right_time = is_array( $right ) ? (string) ( $right['issued_at'] ?? '' ) : '';

				return strcmp( $left_time, $right_time );
			}
		);

		return array_slice( $records, - self::MAX_PREFLIGHT_HANDOFFS, null, true );
	}

	/**
	 * Builds the same input hash Core commit-preflight uses for approved inputs.
	 *
	 * @param array<string,mixed> $proposal Core proposal.
	 * @return string
	 */
	private function proposal_input_hash( array $proposal ): string {
		$json = wp_json_encode( $proposal['input'] ?? array() );
		return hash( 'sha256', is_string( $json ) ? $json : '' );
	}

	/**
	 * Returns the completed execution record for a proposal.
	 *
	 * @param string $proposal_id Proposal id.
	 * @return array<string,mixed>|null
	 */
	private function completed_execution_record( string $proposal_id ): ?array {
		$records = $this->execution_records();
		$key     = $this->execution_record_key( $proposal_id );
		$record  = is_array( $records[ $key ] ?? null ) ? $records[ $key ] : array();

		if ( empty( $record ) || 'succeeded' !== (string) ( $record['status'] ?? '' ) ) {
			return null;
		}

		return $record;
	}

	/**
	 * Builds an error for a duplicate execution attempt.
	 *
	 * @param string              $proposal_id Proposal id.
	 * @param array<string,mixed> $record Completed execution record.
	 * @return WP_Error
	 */
	private function execution_already_completed_error( string $proposal_id, array $record ): WP_Error {
		return new WP_Error(
			'magick_ai_adapter_execution_already_completed',
			__( 'Adapter has already completed execution for this proposal.', 'magick-ai-adapter' ),
			array(
				'status'              => 409,
				'proposal_id'         => $proposal_id,
				'ability_id'          => (string) ( $record['ability_id'] ?? '' ),
				'approved_input_hash' => (string) ( $record['approved_input_hash'] ?? '' ),
				'correlation_id'      => (string) ( $record['correlation_id'] ?? '' ),
				'adapter_request_id'  => (string) ( $record['adapter_request_id'] ?? '' ),
				'commit_execution'    => false,
				'execution_record'    => $this->public_execution_record( $record ),
			)
		);
	}

	/**
	 * Returns stored execution records.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function execution_records(): array {
		$records = get_option( self::EXECUTION_RECORDS_OPTION, array() );
		return is_array( $records ) ? $records : array();
	}

	/**
	 * Stores one successful execution record.
	 *
	 * @param string              $proposal_id Proposal id.
	 * @param array<string,mixed> $proposal Core proposal.
	 * @param array<string,mixed> $execution Execution result.
	 * @return array<string,mixed>
	 */
	private function store_completed_execution_record( string $proposal_id, array $proposal, array $execution ): array {
		$approval_context = is_array( $execution['approval_context'] ?? null ) ? $execution['approval_context'] : array();
		$preflight        = is_array( $execution['preflight'] ?? null ) ? $execution['preflight'] : array();
		$record           = array(
			'status'              => 'succeeded',
			'proposal_id'         => $proposal_id,
			'ability_id'          => sanitize_text_field( (string) ( $execution['ability_id'] ?? '' ) ),
			'proposal_ability_id' => sanitize_text_field( (string) ( $proposal['ability_id'] ?? '' ) ),
			'approved_input_hash' => sanitize_text_field( (string) ( $approval_context['approved_input_hash'] ?? ( $preflight['approved_input_hash'] ?? '' ) ) ),
			'correlation_id'      => sanitize_text_field( (string) ( $execution['correlation_id'] ?? '' ) ),
			'adapter_request_id'  => sanitize_text_field( (string) ( $execution['adapter_request_id'] ?? '' ) ),
			'execution_mode'      => sanitize_key( (string) ( $execution['execution_mode'] ?? '' ) ),
			'execution_surface'   => 'wp_abilities_rest',
			'commit_execution'    => false,
			'post_id'             => absint( $execution['post_id'] ?? 0 ),
			'post_ids'            => array_values( array_map( 'absint', is_array( $execution['post_ids'] ?? null ) ? $execution['post_ids'] : array() ) ),
			'executed_count'      => absint( $execution['executed_count'] ?? 0 ),
			'failed_count'        => absint( $execution['failed_count'] ?? 0 ),
			'executed_at'         => gmdate( 'c' ),
		);

		$records = $this->execution_records();
		$records[ $this->execution_record_key( $proposal_id ) ] = $record;
		$records = $this->prune_execution_records( $records );
		update_option( self::EXECUTION_RECORDS_OPTION, $records, false );

		return $this->public_execution_record( $record );
	}

	/**
	 * Removes old execution records after the bounded retention limit.
	 *
	 * @param array<string,array<string,mixed>> $records Records.
	 * @return array<string,array<string,mixed>>
	 */
	private function prune_execution_records( array $records ): array {
		if ( count( $records ) <= self::MAX_EXECUTION_RECORDS ) {
			return $records;
		}

		uasort(
			$records,
			static function ( $left, $right ): int {
				$left_time  = is_array( $left ) ? (string) ( $left['executed_at'] ?? '' ) : '';
				$right_time = is_array( $right ) ? (string) ( $right['executed_at'] ?? '' ) : '';

				return strcmp( $left_time, $right_time );
			}
		);

		return array_slice( $records, - self::MAX_EXECUTION_RECORDS, null, true );
	}

	/**
	 * Returns a public-safe execution record.
	 *
	 * @param array<string,mixed> $record Record.
	 * @return array<string,mixed>
	 */
	private function public_execution_record( array $record ): array {
		return array(
			'status'              => (string) ( $record['status'] ?? '' ),
			'proposal_id'         => (string) ( $record['proposal_id'] ?? '' ),
			'ability_id'          => (string) ( $record['ability_id'] ?? '' ),
			'proposal_ability_id' => (string) ( $record['proposal_ability_id'] ?? '' ),
			'approved_input_hash' => (string) ( $record['approved_input_hash'] ?? '' ),
			'correlation_id'      => (string) ( $record['correlation_id'] ?? '' ),
			'adapter_request_id'  => (string) ( $record['adapter_request_id'] ?? '' ),
			'execution_mode'      => (string) ( $record['execution_mode'] ?? '' ),
			'execution_surface'   => (string) ( $record['execution_surface'] ?? '' ),
			'commit_execution'    => (bool) ( $record['commit_execution'] ?? false ),
			'post_id'             => absint( $record['post_id'] ?? 0 ),
			'post_ids'            => array_values( array_map( 'absint', is_array( $record['post_ids'] ?? null ) ? $record['post_ids'] : array() ) ),
			'executed_count'      => absint( $record['executed_count'] ?? 0 ),
			'failed_count'        => absint( $record['failed_count'] ?? 0 ),
			'executed_at'         => (string) ( $record['executed_at'] ?? '' ),
		);
	}

	/**
	 * Builds the storage key for an execution record.
	 *
	 * @param string $proposal_id Proposal id.
	 * @return string
	 */
	private function execution_record_key( string $proposal_id ): string {
		return md5( $proposal_id );
	}

	/**
	 * Runs a read-only ability through WordPress Abilities API.
	 *
	 * @param string              $ability_id Ability id.
	 * @param array<string,mixed> $input Ability input.
	 * @param array<string,mixed> $log_context AI request log context.
	 * @return WP_REST_Response|WP_Error
	 */
	private function run_read_ability( string $ability_id, array $input, array $log_context = array() ) {
		$started = microtime( true );
		$ability_id = sanitize_text_field( $ability_id );
		$capability = $this->find_core_capability( $ability_id );

		if ( is_wp_error( $capability ) ) {
			$this->emit_operation_event( 'adapter.ability.run_read', $started, $capability, array( 'ability_id' => $ability_id ) );
			return $capability;
		}

		if ( 'direct_read' !== (string) ( $capability['governance_mode'] ?? '' ) || 'wp_abilities_rest' !== (string) ( $capability['execution_surface'] ?? '' ) ) {
			$error = new WP_Error(
				'magick_ai_adapter_proposal_required',
				__( 'This ability is not direct-read. Create a Core proposal instead.', 'magick-ai-adapter' ),
				array(
					'status'     => 403,
					'capability' => $this->public_capability_guidance( $capability ),
				)
			);
			$this->emit_operation_event( 'adapter.ability.run_read', $started, $error, array( 'ability_id' => $ability_id ) );
			return $error;
		}

		if ( true === (bool) ( $capability['core_proxy_execute'] ?? false ) || true === (bool) ( $capability['commit_execution'] ?? false ) ) {
			$error = new WP_Error(
				'magick_ai_adapter_invalid_core_guidance',
				__( 'Core guidance unexpectedly allows proxy or commit execution.', 'magick-ai-adapter' ),
				array( 'status' => 500 )
			);
			$this->emit_operation_event( 'adapter.ability.run_read', $started, $error, array( 'ability_id' => $ability_id ) );
			return $error;
		}

		$read_context = $this->read_governance_context( $capability, $log_context );
		$route        = '/wp-abilities/v1/abilities/' . $ability_id . '/run';
		$response     = $this->dispatch_upstream_with_request_log_context( $read_context, 'GET', $route, array( 'input' => $input ), true );

		if ( is_wp_error( $response ) ) {
			$this->emit_operation_event( 'adapter.ability.run_read', $started, $response, array( 'ability_id' => $ability_id ) );
			return $response;
		}

		$redacted = $this->apply_read_redaction( $response->get_data(), $read_context );
		$data     = $redacted['result'];

		$this->emit_operation_event(
			'adapter.ability.run_read',
			$started,
			null,
			array(
				'ability_id'          => $ability_id,
				'read_policy'         => (string) ( $read_context['read_policy'] ?? '' ),
				'sensitivity'         => (string) ( $read_context['sensitivity'] ?? '' ),
				'redaction_applied'   => (bool) ( $redacted['redaction_applied'] ?? false ),
				'correlation_id'      => (string) ( $read_context['correlation_id'] ?? '' ),
				'adapter_request_id'  => (string) ( $read_context['adapter_request_id'] ?? '' ),
			)
		);

		return new WP_REST_Response(
			array(
				'ability_id'        => $ability_id,
				'governance_mode'   => 'direct_read',
				'execution_surface' => 'wp_abilities_rest',
				'core_proxy_execute' => false,
				'commit_execution'  => false,
				'read_policy'       => (string) ( $read_context['read_policy'] ?? '' ),
				'sensitivity'       => (string) ( $read_context['sensitivity'] ?? '' ),
				'redaction_required' => (bool) ( $read_context['redaction_required'] ?? false ),
				'redaction_applied' => (bool) ( $redacted['redaction_applied'] ?? false ),
				'redaction_summary' => is_array( $redacted['redaction_summary'] ?? null ) ? $redacted['redaction_summary'] : array(),
				'read_audit_mode'   => (string) ( $read_context['read_audit_mode'] ?? '' ),
				'correlation_id'    => (string) ( $read_context['correlation_id'] ?? '' ),
				'log_context'       => $read_context,
				'read_context'      => $read_context,
				'result'            => $data,
			),
			200
		);
	}

	/**
	 * Builds read governance context from Core capability guidance.
	 *
	 * @param array<string,mixed> $capability Capability row.
	 * @param array<string,mixed> $log_context Existing log context.
	 * @return array<string,mixed>
	 */
	private function read_governance_context( array $capability, array $log_context ): array {
		$sensitivity = sanitize_key( (string) ( $capability['sensitivity'] ?? '' ) );
		if ( ! in_array( $sensitivity, array( 'public', 'internal', 'sensitive' ), true ) ) {
			$sensitivity = $this->infer_read_sensitivity( sanitize_text_field( (string) ( $capability['ability_id'] ?? '' ) ) );
		}

		$read_policy = sanitize_key( (string) ( $capability['read_policy'] ?? '' ) );
		if ( '' === $read_policy ) {
			$read_policy = 'direct_read_' . $sensitivity;
		}

		$log_context['read_policy']        = $read_policy;
		$log_context['sensitivity']        = $sensitivity;
		$log_context['redaction_required'] = (bool) ( $capability['redaction_required'] ?? ( 'sensitive' === $sensitivity ) );
		$log_context['read_audit_mode']    = sanitize_key( (string) ( $capability['read_audit_mode'] ?? 'adapter_read_envelope' ) );
		$log_context['correlation_id']     = isset( $log_context['correlation_id'] ) && '' !== (string) $log_context['correlation_id']
			? sanitize_text_field( (string) $log_context['correlation_id'] )
			: wp_generate_uuid4();

		$magick_ai_core = is_array( $log_context['magick_ai_core'] ?? null ) ? $log_context['magick_ai_core'] : array();
		$magick_ai_core['correlation_id'] = $log_context['correlation_id'];
		$log_context['magick_ai_core']    = $magick_ai_core;

		return $this->sanitize_log_context( $log_context );
	}

	/**
	 * Applies read redaction according to Core read policy.
	 *
	 * @param mixed               $result Read result.
	 * @param array<string,mixed> $read_context Read context.
	 * @return array{result:mixed,redaction_applied:bool,redaction_summary:array<string,mixed>}
	 */
	private function apply_read_redaction( $result, array $read_context ): array {
		$required = (bool) ( $read_context['redaction_required'] ?? false );
		$count    = 0;

		if ( $required ) {
			$result = $this->redact_read_value( $result, $count );
		}

		return array(
			'result'             => $result,
			'redaction_applied'  => $required,
			'redaction_summary'  => array(
				'policy_applied'        => $required,
				'redacted_field_count'  => $count,
			),
		);
	}

	/**
	 * Redacts sensitive values in a read result.
	 *
	 * @param mixed $value Value.
	 * @param int   $count Redacted field count.
	 * @return mixed
	 */
	private function redact_read_value( $value, int &$count ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$clean = array();
		foreach ( $value as $key => $item ) {
			$key_string = is_string( $key ) ? $key : (string) $key;
			if ( $this->is_sensitive_read_key( $key_string ) ) {
				$clean[ $key ] = '[REDACTED]';
				++$count;
				continue;
			}

			$clean[ $key ] = $this->redact_read_value( $item, $count );
		}

		return $clean;
	}

	/**
	 * Returns whether a read result key must be redacted.
	 *
	 * @param string $key Result key.
	 * @return bool
	 */
	private function is_sensitive_read_key( string $key ): bool {
		$key = strtolower( $key );
		foreach ( array( 'password', 'pass', 'secret', 'token', 'authorization', 'cookie', 'nonce', 'user_email', 'email', 'api_key', 'private_key' ) as $needle ) {
			if ( false !== strpos( $key, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Infers read sensitivity when Core capability guidance predates read policy.
	 *
	 * @param string $ability_id Ability id.
	 * @return string
	 */
	private function infer_read_sensitivity( string $ability_id ): string {
		$ability_id = strtolower( $ability_id );

		foreach ( array( 'diagnostic', 'permissions', 'database', 'error-log', 'plugin-conflict', 'ops' ) as $needle ) {
			if ( false !== strpos( $ability_id, $needle ) ) {
				return 'sensitive';
			}
		}

		foreach ( array( 'inventory', 'plan', 'media', 'pages', 'posts', 'users', 'menu', 'term', 'workflow' ) as $needle ) {
			if ( false !== strpos( $ability_id, $needle ) ) {
				return 'internal';
			}
		}

		return 'public';
	}

	/**
	 * Dispatches an upstream request while adding governance context to AI logs.
	 *
	 * @param array<string,mixed> $log_context Log context.
	 * @param string              $method HTTP method.
	 * @param string              $route REST route.
	 * @param array<string,mixed> $params Params.
	 * @param bool                $query_params Whether params should be query params.
	 * @param bool                $json_body Whether params should be encoded as JSON body.
	 * @return WP_REST_Response|WP_Error
	 */
	private function dispatch_upstream_with_request_log_context( array $log_context, string $method, string $route, array $params = array(), bool $query_params = false ) {
		if ( empty( $log_context ) ) {
			return $this->dispatch_upstream( $method, $route, $params, $query_params );
		}

		return $this->with_ai_request_log_context(
			$log_context,
			function () use ( $method, $route, $params, $query_params ) {
				return $this->dispatch_upstream( $method, $route, $params, $query_params );
			}
		);
	}

	/**
	 * Dispatches an upstream request with host approval runtime context.
	 *
	 * @param array<string,mixed> $runtime_context Runtime context.
	 * @param string              $method HTTP method.
	 * @param string              $route REST route.
	 * @param array<string,mixed> $params Params.
	 * @param bool                $query_params Whether params should be query params.
	 * @param bool                $json_body Whether params should be encoded as JSON body.
	 * @return WP_REST_Response|WP_Error
	 */
	private function dispatch_upstream_with_runtime_context( array $runtime_context, string $method, string $route, array $params = array(), bool $query_params = false, bool $json_body = false ) {
		$previous = isset( $GLOBALS['magick_ai_runtime_wp_ability_context'] ) ? $GLOBALS['magick_ai_runtime_wp_ability_context'] : null;
		$GLOBALS['magick_ai_runtime_wp_ability_context'] = array(
			'context' => $this->sanitize_runtime_context( $runtime_context ),
		);

		try {
			return $this->dispatch_upstream( $method, $route, $params, $query_params, $json_body );
		} finally {
			if ( null === $previous ) {
				unset( $GLOBALS['magick_ai_runtime_wp_ability_context'] );
			} else {
				$GLOBALS['magick_ai_runtime_wp_ability_context'] = $previous;
			}
		}
	}

	/**
	 * Runs a callback while adding adapter governance context to AI logs.
	 *
	 * @param array<string,mixed> $log_context Log context.
	 * @param callable            $callback Callback.
	 * @return mixed
	 */
	private function with_ai_request_log_context( array $log_context, callable $callback ) {
		$previous                          = $this->current_request_log_context;
		$this->current_request_log_context = $log_context;
		add_filter( 'wpai_request_log_context', array( $this, 'append_ai_request_log_context' ), 10, 3 );

		try {
			return $callback();
		} finally {
			remove_filter( 'wpai_request_log_context', array( $this, 'append_ai_request_log_context' ), 10 );
			$this->current_request_log_context = $previous;
		}
	}

	/**
	 * Adds adapter governance context to AI request logs.
	 *
	 * @param array<string,mixed> $context Existing log context.
	 * @param array<string,mixed> $decoded Decoded response.
	 * @param array<string,mixed> $log_data Full log data.
	 * @return array<string,mixed>
	 */
	public function append_ai_request_log_context( array $context, array $decoded = array(), array $log_data = array() ): array {
		if ( empty( $this->current_request_log_context ) ) {
			return $context;
		}

		$context['magick_ai_adapter'] = $this->current_request_log_context;

		foreach ( array( 'proposal_id', 'correlation_id', 'ability_id', 'post_id', 'adapter_request_id', 'adapter_route', 'ai_provider', 'ai_model', 'governance_source' ) as $key ) {
			if ( isset( $this->current_request_log_context[ $key ] ) ) {
				$context[ $key ] = $this->current_request_log_context[ $key ];
			}
		}

		if ( isset( $this->current_request_log_context['magick_ai_core'] ) && is_array( $this->current_request_log_context['magick_ai_core'] ) ) {
			$context['magick_ai_core'] = $this->current_request_log_context['magick_ai_core'];
		}

		return $context;
	}

	/**
	 * Finds one capability row from Core.
	 *
	 * @param string $ability_id Ability id.
	 * @return array<string,mixed>|WP_Error
	 */
	private function find_core_capability( string $ability_id ) {
		$response = $this->dispatch_upstream( 'GET', '/magick-ai-core/v1/capabilities' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = $response->get_data();
		foreach ( (array) ( is_array( $data ) ? ( $data['items'] ?? array() ) : array() ) as $item ) {
			if ( is_array( $item ) && $ability_id === (string) ( $item['ability_id'] ?? '' ) ) {
				return $item;
			}
		}

		return new WP_Error(
			'magick_ai_adapter_ability_not_found',
			__( 'The requested ability is not discoverable through Core.', 'magick-ai-adapter' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Dispatches an internal REST request.
	 *
	 * @param string              $method HTTP method.
	 * @param string              $route REST route.
	 * @param array<string,mixed> $params Params.
	 * @param bool                $query_params Whether params should be query params.
	 * @param bool                $json_body Whether params should be encoded as JSON body.
	 * @param bool                $use_core_app_token Whether configured Core app token should be used.
	 * @return WP_REST_Response|WP_Error
	 */
	private function dispatch_upstream( string $method, string $route, array $params = array(), bool $query_params = false, bool $json_body = false, bool $use_core_app_token = true ) {
		$started = microtime( true );
		$request = new WP_REST_Request( $method, $route );
		$token   = $use_core_app_token ? $this->core_app_token() : '';
		$user_id = get_current_user_id();

		if ( '' !== $token && 0 === strpos( $route, '/magick-ai-core/v1/' ) ) {
			$request->set_header( 'x-magick-ai-core-app-token', $token );
			wp_set_current_user( 0 );
		}

		if ( $query_params ) {
			$request->set_query_params( $params );
		} elseif ( $json_body ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $params ) );
		} else {
			foreach ( $params as $key => $value ) {
				$request->set_param( $key, $value );
			}
		}

		$response = rest_do_request( $request );
		if ( '' !== $token && 0 === strpos( $route, '/magick-ai-core/v1/' ) ) {
			wp_set_current_user( $user_id );
		}
		$status   = (int) $response->get_status();

		if ( $status < 200 || $status >= 300 ) {
			$data    = $response->get_data();
			$code    = is_array( $data ) ? (string) ( $data['code'] ?? 'magick_ai_adapter_upstream_failed' ) : 'magick_ai_adapter_upstream_failed';
			$message = is_array( $data ) ? (string) ( $data['message'] ?? __( 'The upstream WordPress REST request failed.', 'magick-ai-adapter' ) ) : __( 'The upstream WordPress REST request failed.', 'magick-ai-adapter' );

			$this->emit_operation_event(
				'adapter.core.request',
				$started,
				new WP_Error( $code, $message, array( 'status' => $status ) ),
				array(
					'method'      => strtoupper( $method ),
					'route'       => $route,
					'status_code' => $status,
				)
			);

			return new WP_Error(
				$code,
				$message,
				array(
					'status'        => $status,
					'upstream_route' => $route,
					'upstream_data'  => $data,
				)
			);
		}

		$this->emit_operation_event(
			'adapter.core.request',
			$started,
			null,
			array(
				'method' => strtoupper( $method ),
				'route'  => $route,
				'status_code' => $status,
			)
		);

		return new WP_REST_Response( $response->get_data(), $status );
	}

	/**
	 * Returns the configured Core app token without exposing it in responses.
	 *
	 * @return string
	 */
	private function core_app_token(): string {
		if ( defined( 'MAGICK_AI_ADAPTER_CORE_APP_TOKEN' ) ) {
			return trim( (string) constant( 'MAGICK_AI_ADAPTER_CORE_APP_TOKEN' ) );
		}

		$env_token = getenv( 'MAGICK_AI_ADAPTER_CORE_APP_TOKEN' );
		if ( is_string( $env_token ) && '' !== trim( $env_token ) ) {
			return trim( $env_token );
		}

		$option = get_option( 'magick_ai_adapter_core_app_token', '' );
		return is_string( $option ) ? trim( $option ) : '';
	}

	/**
	 * Returns supported read shortcuts.
	 *
	 * @return array<string,string>
	 */
	public static function read_shortcuts(): array {
		$shortcuts = array();
		foreach ( self::read_shortcut_definitions() as $route => $definition ) {
			$shortcuts[ $route ] = (string) ( $definition['ability_id'] ?? '' );
		}

		return $shortcuts;
	}

	/**
	 * Returns supported read shortcut definitions.
	 *
	 * @return array<string,array{ability_id:string,default_input?:array<string,mixed>}>
	 */
	private static function read_shortcut_definitions(): array {
		$summary_ability = 'npcink-abilities-toolkit/wp-diagnostics-summary';
		$ops_ability     = 'npcink-abilities-toolkit/wp-ops-diagnostics-detail';

		return array(
			'site-info'              => array( 'ability_id' => 'magick-ai/site-info' ),
			'site-summary'           => array( 'ability_id' => 'npcink-abilities-toolkit/site-summary' ),
			'wp-diagnostics-summary' => array( 'ability_id' => $summary_ability ),
			'active-plugins-detail'  => array(
				'ability_id'     => $ops_ability,
				'default_input'  => self::ops_diagnostics_input(),
			),
			'plugin-conflict-diagnostics' => array(
				'ability_id'     => $ops_ability,
				'default_input'  => self::ops_diagnostics_plugin_conflict_input(),
			),
			'current-user-permissions' => array(
				'ability_id'     => $ops_ability,
				'default_input'  => self::ops_diagnostics_input(),
			),
			'php-extensions'        => array(
				'ability_id'     => $ops_ability,
				'default_input'  => self::ops_diagnostics_input(),
			),
			'object-cache-status'   => array(
				'ability_id'     => $ops_ability,
				'default_input'  => self::ops_diagnostics_input(),
			),
			'rewrite-rules-status'  => array(
				'ability_id'     => $ops_ability,
				'default_input'  => self::ops_diagnostics_input(),
			),
			'ssl-https-status'      => array(
				'ability_id'     => $ops_ability,
				'default_input'  => self::ops_diagnostics_input(),
			),
			'wp-ops-diagnostics-detail' => array(
				'ability_id'     => $ops_ability,
				'default_input'  => self::ops_diagnostics_input(),
			),
			'recent-error-log'      => array(
				'ability_id'     => $ops_ability,
				'default_input'  => self::ops_diagnostics_input(),
			),
			'recent-error-log-tail' => array(
				'ability_id'     => $ops_ability,
				'default_input'  => self::ops_diagnostics_log_input(),
			),
			'database-info'         => array(
				'ability_id'     => $ops_ability,
				'default_input'  => self::ops_diagnostics_input(),
			),
			'cron-events-detail'    => array(
				'ability_id'     => $ops_ability,
				'default_input'  => self::ops_diagnostics_input(),
			),
			'custom-post-types'     => array(
				'ability_id'     => $ops_ability,
				'default_input'  => self::ops_diagnostics_input(),
			),
			'roles-capabilities'    => array(
				'ability_id'     => $ops_ability,
				'default_input'  => self::ops_diagnostics_input(),
			),
			'widgets-sidebars'      => array(
				'ability_id'     => $ops_ability,
				'default_input'  => self::ops_diagnostics_input(),
			),
			'block-theme-assets'    => array(
				'ability_id'     => $ops_ability,
				'default_input'  => self::ops_diagnostics_input(),
			),
			'search-index-status'   => array(
				'ability_id'     => $ops_ability,
				'default_input'  => self::ops_diagnostics_input(),
			),
			'server-info'           => array(
				'ability_id'     => $ops_ability,
				'default_input'  => self::ops_diagnostics_input(),
			),
			'integrations-status'   => array(
				'ability_id'     => $ops_ability,
				'default_input'  => self::ops_diagnostics_input(),
			),
			'seo-summary'           => array(
				'ability_id'     => $ops_ability,
				'default_input'  => self::ops_diagnostics_input(),
			),
			'security-summary'      => array(
				'ability_id'     => $ops_ability,
				'default_input'  => self::ops_diagnostics_input(),
			),
			'performance-summary'   => array(
				'ability_id'     => $ops_ability,
				'default_input'  => self::ops_diagnostics_input(),
			),
			'workflow-recipes'       => array( 'ability_id' => 'npcink-abilities-toolkit/list-workflow-recipes' ),
			'posts'                  => array( 'ability_id' => 'magick-ai/list-posts' ),
			'post-context'           => array( 'ability_id' => 'magick-ai/get-post-context' ),
			'media'                  => array( 'ability_id' => 'magick-ai/list-media' ),
			'terms'                  => array( 'ability_id' => 'magick-ai/list-terms' ),
			'taxonomy-terms'         => array( 'ability_id' => 'magick-ai/list-taxonomy-terms' ),
			'categories'             => array( 'ability_id' => 'magick-ai/list-categories' ),
			'tags'                   => array( 'ability_id' => 'magick-ai/list-tags' ),
			'term'                   => array( 'ability_id' => 'magick-ai/get-term' ),
			'comments'               => array( 'ability_id' => 'magick-ai/list-comments' ),
			'users'                  => array( 'ability_id' => 'magick-ai/list-users' ),
			'menu'                   => array( 'ability_id' => 'magick-ai/get-menu' ),
			'internal-link-targets'   => array( 'ability_id' => 'magick-ai/resolve-internal-link-targets' ),
			'post-stats'             => array( 'ability_id' => 'magick-ai/get-post-stats' ),
			'post-revisions'         => array( 'ability_id' => 'magick-ai/list-revisions' ),
			'post-meta'              => array( 'ability_id' => 'magick-ai/get-post-meta' ),
			'pages'                  => array( 'ability_id' => 'magick-ai/list-pages' ),
			'page'                   => array( 'ability_id' => 'magick-ai/get-page' ),
			'page-structure'         => array( 'ability_id' => 'magick-ai/inspect-page-structure' ),
			'pages-tree'             => array( 'ability_id' => 'magick-ai/list-pages-tree' ),
			'content-inventory-health' => array( 'ability_id' => 'magick-ai/get-content-inventory-health' ),
			'content-inventory-fix-plan' => array( 'ability_id' => 'magick-ai/build-content-inventory-fix-plan' ),
			'test-content-cleanup-plan' => array( 'ability_id' => 'magick-ai/build-test-content-cleanup-plan' ),
			'content-discoverability-context' => array( 'ability_id' => 'magick-ai-toolbox/get-content-discoverability-context' ),
			'content-discoverability-validation' => array( 'ability_id' => 'magick-ai-toolbox/validate-content-discoverability-context' ),
			'content-discoverability-brief' => array( 'ability_id' => 'magick-ai-toolbox/build-content-discoverability-brief' ),
			'article-writing-pack' => array( 'ability_id' => 'magick-ai-toolbox/build-ai-article-writing-pack' ),
			'site-operations-dashboard' => array( 'ability_id' => 'magick-ai/get-site-operations-dashboard' ),
			'publishing-calendar-context' => array( 'ability_id' => 'magick-ai/get-publishing-calendar-context' ),
			'media-inventory-health' => array( 'ability_id' => 'magick-ai/get-media-inventory-health' ),
			'media-inventory-fix-plan' => array( 'ability_id' => 'magick-ai/build-media-inventory-fix-plan' ),
			'media-attachment-by-url' => array( 'ability_id' => 'magick-ai/resolve-media-attachment-by-url' ),
			'media-asset-inspection' => array( 'ability_id' => 'magick-ai/inspect-media-asset' ),
			'taxonomy-inventory-health' => array( 'ability_id' => 'magick-ai/get-taxonomy-inventory-health' ),
		);
	}

	/**
	 * Builds default operations diagnostics input.
	 *
	 * @param array<string,mixed> $overrides Input overrides.
	 * @return array<string,mixed>
	 */
	private static function ops_diagnostics_input( array $overrides = array() ): array {
		return array_merge(
			array(
				'include_log_contents'     => false,
				'include_active_plugins'   => true,
				'include_inactive_plugins' => false,
				'include_plugin_updates'   => true,
				'include_must_use_plugins' => true,
				'include_dropins'          => true,
				'max_plugins_per_group'    => 100,
			),
			$overrides
		);
	}

	/**
	 * Builds operations diagnostics input for plugin conflict troubleshooting.
	 *
	 * @return array<string,mixed>
	 */
	private static function ops_diagnostics_plugin_conflict_input(): array {
		return self::ops_diagnostics_input(
			array(
				'include_inactive_plugins' => true,
				'max_plugins_per_group'    => 200,
			)
		);
	}

	/**
	 * Builds explicit operations diagnostics input for bounded log inspection.
	 *
	 * @return array<string,mixed>
	 */
	private static function ops_diagnostics_log_input(): array {
		return array(
			'include_log_contents' => true,
			'tail_lines'           => 50,
			'severity'             => array( 'fatal', 'error', 'warning' ),
			'since_minutes'        => 1440,
		);
	}

	/**
	 * Returns help route labels for read shortcuts.
	 *
	 * @return array<int,string>
	 */
	private function help_read_shortcuts(): array {
		$routes = array();
		foreach ( array_keys( self::read_shortcuts() ) as $route ) {
			$routes[] = 'GET /' . $route;
		}

		return $routes;
	}

	/**
	 * Returns the OpenClaw diagnostics shortcut contract.
	 *
	 * @return array<string,mixed>
	 */
	private function diagnostics_contract(): array {
		return array(
			'summary_route'          => 'GET /wp-diagnostics-summary',
			'detail_route'           => 'GET /wp-ops-diagnostics-detail',
			'detail_ability_id'      => 'npcink-abilities-toolkit/wp-ops-diagnostics-detail',
			'default_input'          => self::ops_diagnostics_input(),
			'plugin_conflict_input'  => self::ops_diagnostics_plugin_conflict_input(),
			'explicit_log_input'     => self::ops_diagnostics_log_input(),
			'inactive_plugins_absent_reason' => 'inactive plugin rows are not requested by default',
			'log_contents_absent_reason' => 'not explicitly requested',
			'plugin_group_fields'    => array(
				'plugins.groups_included',
				'plugins.max_plugins_per_group',
				'plugins.available_count',
				'plugins.active_count',
				'plugins.inactive_count',
				'plugins.update_available_count',
				'plugins.mu_count',
				'plugins.dropin_count',
				'plugins.active',
				'plugins.inactive',
				'plugins.update_available',
				'plugins.must_use',
				'plugins.dropins',
			),
			'plugin_row_fields'      => array(
				'slug',
				'plugin_file',
				'name',
				'version',
				'author',
				'status',
				'network_active',
				'must_use',
				'requires_wp',
				'requires_php',
				'dependencies',
				'dependency_count',
				'is_magick_ai',
				'update_available',
				'latest_version',
			),
			'error_log_fields'       => array(
				'error_log.contents_included',
				'error_log.log_exists',
				'error_log.log_readable',
				'error_log.log_size_bytes',
				'error_log.log_modified_gmt',
				'error_log.summary',
				'error_log.summary.by_severity',
			),
			'error_log_summary_fields' => array(
				'returned_lines',
				'fatal_count',
				'error_count',
				'warning_count',
				'deprecated_count',
				'notice_count',
				'info_count',
				'unknown_count',
				'latest_fatal_at',
				'latest_error_at',
				'latest_warning_at',
				'latest_deprecated_at',
				'latest_notice_at',
				'summary_source',
				'by_severity',
			),
		);
	}

	/**
	 * Returns request input.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>
	 */
	private function request_input( WP_REST_Request $request ): array {
		return $this->object_param( $request, 'input' );
	}

	/**
	 * Returns input for the local media derivative request ability.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>
	 */
	private function media_derivative_ability_input( WP_REST_Request $request ): array {
		$overrides = $this->request_input( $request );
		foreach ( array( 'attachment_id', 'preferred_format', 'target_format', 'target_max_width', 'max_width', 'quality', 'watermark' ) as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value && ! array_key_exists( $key, $overrides ) ) {
				$overrides[ $key ] = $this->sanitize_input_value( $value );
			}
		}
		if ( isset( $overrides['target_format'] ) && ! isset( $overrides['preferred_format'] ) ) {
			$overrides['preferred_format'] = $overrides['target_format'];
		}
		if ( isset( $overrides['max_width'] ) && ! isset( $overrides['target_max_width'] ) ) {
			$overrides['target_max_width'] = $overrides['max_width'];
		}

		if ( function_exists( 'magick_ai_core_build_media_derivative_ability_input' ) ) {
			return magick_ai_core_build_media_derivative_ability_input( $overrides );
		}

		return $overrides;
	}

	/**
	 * Returns the source artifact or local upload descriptor for a derivative run.
	 *
	 * @param WP_REST_Request     $request Request.
	 * @param array<string,mixed> $ability_response Ability response.
	 * @param array<string,mixed> $ability_input Ability input.
	 * @return array<string,mixed>|WP_Error
	 */
	private function media_derivative_source_artifact( WP_REST_Request $request, array $ability_response, array $ability_input ) {
		$source_artifact = $this->object_param( $request, 'source_artifact' );
		if ( ! empty( $source_artifact ) ) {
			return $this->sanitize_media_derivative_artifact_descriptor( $source_artifact );
		}

		$contract      = $this->media_derivative_contract_data( $ability_response );
		$attachment_id = absint( $contract['attachment_id'] ?? $ability_input['attachment_id'] ?? 0 );
		if ( $attachment_id <= 0 ) {
			return new WP_Error(
				'magick_ai_adapter_media_derivative_attachment_required',
				__( 'attachment_id is required when no source_artifact is supplied.', 'magick-ai-adapter' ),
				array( 'status' => 400 )
			);
		}

		return $this->attachment_upload_descriptor( $attachment_id, 'source_file' );
	}

	/**
	 * Returns an optional watermark artifact or local upload descriptor.
	 *
	 * @param WP_REST_Request     $request Request.
	 * @param array<string,mixed> $ability_response Ability response.
	 * @param array<string,mixed> $ability_input Ability input.
	 * @return array<string,mixed>|WP_Error
	 */
	private function media_derivative_watermark_artifact( WP_REST_Request $request, array $ability_response, array $ability_input ) {
		$watermark_artifact = $this->object_param( $request, 'watermark_artifact' );
		if ( ! empty( $watermark_artifact ) ) {
			return $this->sanitize_media_derivative_artifact_descriptor( $watermark_artifact );
		}

		$contract    = $this->media_derivative_contract_data( $ability_response );
		$job_payload = is_array( $contract['cloud_job_payload'] ?? null ) ? $contract['cloud_job_payload'] : array();
		$watermark   = is_array( $job_payload['watermark'] ?? null ) ? $job_payload['watermark'] : array();
		if ( empty( $watermark ) || ! empty( $watermark['artifact_id'] ) ) {
			return array();
		}

		$attachment_id = absint( $ability_input['watermark_attachment_id'] ?? 0 );
		if ( $attachment_id <= 0 && function_exists( 'magick_ai_core_get_media_derivative_settings' ) ) {
			$settings      = magick_ai_core_get_media_derivative_settings();
			$attachment_id = absint( is_array( $settings ) ? ( $settings['watermark_attachment_id'] ?? 0 ) : 0 );
		}
		if ( $attachment_id <= 0 ) {
			return array();
		}

		return $this->attachment_upload_descriptor( $attachment_id, 'watermark_file' );
	}

	/**
	 * Builds a multipart upload descriptor for a local attachment.
	 *
	 * @param int    $attachment_id Attachment id.
	 * @param string $field_name Multipart field name.
	 * @return array<string,mixed>|WP_Error
	 */
	private function attachment_upload_descriptor( int $attachment_id, string $field_name ) {
		$path = function_exists( 'get_attached_file' ) ? get_attached_file( $attachment_id ) : '';
		if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) {
			return new WP_Error(
				'magick_ai_adapter_media_derivative_file_unreadable',
				__( 'The local attachment file is not readable for media derivative upload.', 'magick-ai-adapter' ),
				array(
					'status'        => 400,
					'attachment_id' => $attachment_id,
				)
			);
		}

		return array(
			'path'      => $path,
			'filename'  => sanitize_file_name( basename( $path ) ),
			'mime_type' => sanitize_text_field( (string) get_post_mime_type( $attachment_id ) ),
			'field_name' => sanitize_key( $field_name ),
		);
	}

	/**
	 * Returns a verified Cloud runtime client through Cloud Addon.
	 *
	 * @return object|WP_Error
	 */
	private function media_derivative_runtime_client() {
		if ( ! function_exists( 'magick_ai_cloud_addon_verified_runtime_client' ) ) {
			return $this->cloud_addon_unavailable_error();
		}

		$client = magick_ai_cloud_addon_verified_runtime_client();
		if ( ! is_object( $client ) ) {
			return new WP_Error(
				'magick_ai_adapter_cloud_addon_unverified',
				__( 'Magick AI Cloud Addon must be configured and verified before media derivative run reads.', 'magick-ai-adapter' ),
				array( 'status' => 403 )
			);
		}

		return $client;
	}

	/**
	 * Returns a clear Cloud Addon dependency error.
	 *
	 * @return WP_Error
	 */
	private function cloud_addon_unavailable_error(): WP_Error {
		return new WP_Error(
			'magick_ai_adapter_cloud_addon_unavailable',
			__( 'Magick AI Cloud Addon is required for media derivative Cloud transport.', 'magick-ai-adapter' ),
			array(
				'status'        => 501,
				'required_plugin' => 'magick-ai-cloud-addon',
			)
		);
	}

	/**
	 * Extracts ability contract data from a response envelope.
	 *
	 * @param array<string,mixed> $ability_response Ability response.
	 * @return array<string,mixed>
	 */
	private function media_derivative_contract_data( array $ability_response ): array {
		return is_array( $ability_response['data'] ?? null ) ? $ability_response['data'] : $ability_response;
	}

	/**
	 * Extracts a run id from Cloud response shapes.
	 *
	 * @param array<string,mixed> $cloud_response Cloud response.
	 * @return string
	 */
	private function media_derivative_run_id( array $cloud_response ): string {
		$data = is_array( $cloud_response['data'] ?? null ) ? $cloud_response['data'] : $cloud_response;
		return sanitize_text_field( (string) ( $data['run_id'] ?? $data['id'] ?? $cloud_response['run_id'] ?? '' ) );
	}

	/**
	 * Returns a bounded Cloud run/result projection.
	 *
	 * @param array<string,mixed> $cloud_response Cloud response.
	 * @return array<string,mixed>
	 */
	private function public_media_derivative_cloud_projection( array $cloud_response ): array {
		$data       = is_array( $cloud_response['data'] ?? null ) ? $cloud_response['data'] : $cloud_response;
		$derivative = $this->media_derivative_artifact_from_cloud_result( $data );
		$preview_url = $this->media_derivative_artifact_preview_url( $derivative );
		if ( '' !== $preview_url ) {
			$derivative['preview_url'] = $preview_url;
		}
		$error      = is_array( $data['error'] ?? null ) ? $data['error'] : array();
		$warnings   = is_array( $data['warnings'] ?? null ) ? $data['warnings'] : array();
		if ( empty( $warnings ) && is_array( $derivative['processing_warnings'] ?? null ) ) {
			$warnings = $derivative['processing_warnings'];
		}

		return array(
			'run_id'     => sanitize_text_field( (string) ( $data['run_id'] ?? $data['id'] ?? '' ) ),
			'status'     => sanitize_key( (string) ( $data['status'] ?? $cloud_response['status'] ?? '' ) ),
			'job_type'   => sanitize_key( (string) ( $data['job_type'] ?? $data['cloud_job_payload']['job_type'] ?? '' ) ),
			'created_at' => sanitize_text_field( (string) ( $data['created_at'] ?? '' ) ),
			'updated_at' => sanitize_text_field( (string) ( $data['updated_at'] ?? '' ) ),
			'derivative' => $derivative,
			'warnings'   => array_values( array_map( 'sanitize_text_field', $warnings ) ),
			'error'      => $this->sanitize_input_value( $error ),
		);
	}

	/**
	 * Infers a derivative artifact descriptor from a Cloud result.
	 *
	 * @param array<string,mixed> $cloud_result Cloud result.
	 * @return array<string,mixed>
	 */
	private function media_derivative_artifact_from_cloud_result( array $cloud_result ): array {
		$data       = is_array( $cloud_result['data'] ?? null ) ? $cloud_result['data'] : $cloud_result;
		$derivative = is_array( $data['derivative'] ?? null ) ? $data['derivative'] : array();
		if ( empty( $derivative ) && is_array( $data['result']['artifact'] ?? null ) ) {
			$derivative = $data['result']['artifact'];
		}

		return $this->sanitize_media_derivative_artifact_descriptor( $derivative );
	}

	/**
	 * Sanitizes a Cloud artifact or local upload descriptor.
	 *
	 * @param array<string,mixed> $descriptor Descriptor.
	 * @return array<string,mixed>
	 */
	private function sanitize_media_derivative_artifact_descriptor( array $descriptor ): array {
		$clean = array();
		foreach ( array( 'artifact_id', 'id', 'download_url', 'url', 'expires_at', 'run_id', 'mime_type', 'format', 'path', 'file_path', 'tmp_name', 'filename', 'name', 'field_name', 'sha256', 'checksum' ) as $key ) {
			if ( isset( $descriptor[ $key ] ) && is_scalar( $descriptor[ $key ] ) ) {
				$clean[ $key ] = sanitize_text_field( (string) $descriptor[ $key ] );
			}
		}
		if ( ! isset( $clean['sha256'] ) && isset( $clean['checksum'] ) && 0 === strpos( strtolower( (string) $clean['checksum'] ), 'sha256:' ) ) {
			$clean['sha256'] = substr( strtolower( (string) $clean['checksum'] ), 7 );
		}
		foreach ( array( 'width', 'height', 'filesize_bytes', 'size_bytes' ) as $key ) {
			if ( isset( $descriptor[ $key ] ) ) {
				$clean[ $key ] = absint( $descriptor[ $key ] );
			}
		}
		if ( is_array( $descriptor['processing_warnings'] ?? null ) ) {
			$clean['processing_warnings'] = array_values( array_map( 'sanitize_text_field', $descriptor['processing_warnings'] ) );
		}
		foreach ( array( 'bytes', 'content' ) as $key ) {
			if ( isset( $descriptor[ $key ] ) && is_string( $descriptor[ $key ] ) ) {
				$clean[ $key ] = $descriptor[ $key ];
			}
		}

		return $clean;
	}

	/**
	 * Builds a local same-origin preview proxy URL from a Cloud artifact descriptor.
	 *
	 * @param array<string,mixed> $artifact Artifact descriptor.
	 * @return string
	 */
	private function media_derivative_artifact_preview_url( array $artifact ): string {
		$artifact_id = sanitize_text_field( (string) ( $artifact['artifact_id'] ?? $artifact['id'] ?? '' ) );
		$expires_at  = sanitize_text_field( (string) ( $artifact['expires_at'] ?? '' ) );
		$expires_ts  = strtotime( $expires_at );
		if ( '' === $artifact_id || '' === $expires_at || false === $expires_ts ) {
			return '';
		}

		$query = array(
			'expires_ts' => (string) $expires_ts,
		);
		foreach ( array( 'mime_type', 'checksum', 'sha256', 'run_id' ) as $key ) {
			if ( '' !== (string) ( $artifact[ $key ] ?? '' ) ) {
				$query[ $key ] = (string) $artifact[ $key ];
			}
		}
		$query['preview_sig'] = $this->media_derivative_artifact_preview_signature( $artifact_id, $query );

		return add_query_arg(
			$query,
			rest_url( self::NAMESPACE . '/media-derivative-artifacts/' . rawurlencode( $artifact_id ) . '/preview' )
		);
	}

	/**
	 * Returns a local HMAC for one derivative preview descriptor.
	 *
	 * @param string              $artifact_id Artifact id.
	 * @param array<string,mixed> $query Preview query descriptor.
	 * @return string
	 */
	private function media_derivative_artifact_preview_signature( string $artifact_id, array $query ): string {
		return hash_hmac(
			'sha256',
			$this->media_derivative_artifact_preview_signature_payload( $artifact_id, $query ),
			wp_salt( 'auth' )
		);
	}

	/**
	 * Verifies a local HMAC preview signature from a REST image request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	private function valid_media_derivative_artifact_preview_signature( WP_REST_Request $request ): bool {
		$artifact_id = sanitize_text_field( (string) $request->get_param( 'artifact_id' ) );
		$expires_at  = $this->media_derivative_preview_expires_at( $request );
		$signature   = strtolower( sanitize_text_field( (string) $request->get_param( 'preview_sig' ) ) );
		if ( '' === $artifact_id || '' === $expires_at || '' === $signature ) {
			return false;
		}

		$expires = strtotime( $expires_at );
		if ( false === $expires || $expires <= time() ) {
			return false;
		}

		$query = array(
			'expires_ts' => (string) $expires,
		);
		foreach ( array( 'mime_type', 'checksum', 'sha256', 'run_id' ) as $key ) {
			$value = sanitize_text_field( (string) $request->get_param( $key ) );
			if ( '' !== $value ) {
				$query[ $key ] = $value;
			}
		}

		$expected = $this->media_derivative_artifact_preview_signature( $artifact_id, $query );

		return hash_equals( $expected, $signature );
	}

	/**
	 * Builds the signed preview descriptor payload.
	 *
	 * @param string              $artifact_id Artifact id.
	 * @param array<string,mixed> $query Preview query descriptor.
	 * @return string
	 */
	private function media_derivative_artifact_preview_signature_payload( string $artifact_id, array $query ): string {
		$payload = array(
			'artifact_id' => sanitize_text_field( $artifact_id ),
			'expires_ts'  => absint( $query['expires_ts'] ?? 0 ),
			'mime_type'   => sanitize_text_field( (string) ( $query['mime_type'] ?? '' ) ),
			'checksum'    => sanitize_text_field( (string) ( $query['checksum'] ?? '' ) ),
			'sha256'      => sanitize_text_field( (string) ( $query['sha256'] ?? '' ) ),
			'run_id'      => sanitize_text_field( (string) ( $query['run_id'] ?? '' ) ),
		);

		return wp_json_encode( $payload );
	}

	/**
	 * Returns the preview artifact expiry from either timestamp or legacy ISO query.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string
	 */
	private function media_derivative_preview_expires_at( WP_REST_Request $request ): string {
		$expires_ts = absint( $request->get_param( 'expires_ts' ) );
		if ( $expires_ts > 0 ) {
			return gmdate( 'c', $expires_ts );
		}

		return sanitize_text_field( (string) $request->get_param( 'expires_at' ) );
	}

	/**
	 * Returns a stable file extension for supported derivative preview mimes.
	 *
	 * @param string $mime_type Mime type.
	 * @return string
	 */
	private function media_derivative_extension_for_mime( string $mime_type ): string {
		$map = array(
			'image/avif' => '.avif',
			'image/gif'  => '.gif',
			'image/jpeg' => '.jpg',
			'image/png'  => '.png',
			'image/webp' => '.webp',
		);

		return (string) ( $map[ strtolower( trim( $mime_type ) ) ] ?? '.bin' );
	}

	/**
	 * Returns media metadata optimization input.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>
	 */
	private function media_metadata_optimization_input( WP_REST_Request $request ): array {
		$input = $this->request_input( $request );

		foreach ( array( 'media_assets', 'article_title', 'article_excerpt', 'article_content', 'focus_keyword', 'vision_fallback_mode' ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				continue;
			}

			$value = $request->get_param( $key );
			if ( null !== $value ) {
				$input[ $key ] = $this->sanitize_input_value( $value );
			}
		}

		return $input;
	}

	/**
	 * Returns governance context that should be copied into AI request logs.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $ability_id Ability id.
	 * @return array<string,mixed>
	 */
	private function request_log_context( WP_REST_Request $request, string $ability_id ): array {
		$context = $this->object_param( $request, 'log_context' );

		foreach ( array( 'proposal_id', 'correlation_id', 'external_thread_id', 'openclaw_thread_id', 'adapter_request_id', 'adapter_route', 'ai_provider', 'ai_model' ) as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value && '' !== (string) $value ) {
				$context[ $key ] = $value;
			}
		}

		$context['ability_id']        = sanitize_text_field( $ability_id );
		$context['adapter_request_id'] = isset( $context['adapter_request_id'] ) && '' !== (string) $context['adapter_request_id']
			? sanitize_text_field( (string) $context['adapter_request_id'] )
			: wp_generate_uuid4();
		$context['adapter_route']      = isset( $context['adapter_route'] ) && '' !== (string) $context['adapter_route']
			? sanitize_text_field( (string) $context['adapter_route'] )
			: $request->get_route();
		$context['governance_source']  = 'magick-ai-core';
		$context['via']                = 'magick-ai-adapter';

		$magick_ai_core = is_array( $context['magick_ai_core'] ?? null ) ? $context['magick_ai_core'] : array();
		if ( isset( $context['proposal_id'] ) && '' !== (string) $context['proposal_id'] ) {
			$magick_ai_core['proposal_id'] = $context['proposal_id'];
		}
		if ( isset( $context['correlation_id'] ) && '' !== (string) $context['correlation_id'] ) {
			$magick_ai_core['correlation_id'] = $context['correlation_id'];
		}
		if ( ! empty( $magick_ai_core ) ) {
			$context['magick_ai_core'] = $magick_ai_core;
		}

		return $this->sanitize_log_context( $context );
	}

	/**
	 * Returns caller metadata for Core proposal requests.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $ability_id Ability id.
	 * @return array<string,mixed>
	 */
	private function proposal_caller_context( WP_REST_Request $request, string $ability_id ): array {
		return array_merge(
			array(
				'caller_type' => 'openclaw_adapter',
				'via'         => 'magick-ai-adapter',
			),
			$this->request_log_context( $request, $ability_id ),
			$this->object_param( $request, 'caller' )
		);
	}

	/**
	 * Returns metadata-only context for observability events.
	 *
	 * @param WP_REST_Request     $request Request.
	 * @param array<string,mixed> $context Existing safe context.
	 * @return array<string,mixed>
	 */
	private function observability_request_context( WP_REST_Request $request, array $context = array() ): array {
		$context['method'] = $request->get_method();
		$context['route']  = $request->get_route();

		foreach ( array( 'proposal_id', 'correlation_id', 'adapter_request_id' ) as $key ) {
			$value = $request->get_param( $key );
			if ( is_scalar( $value ) && '' !== (string) $value ) {
				$context[ $key ] = $value;
			}
		}

		foreach ( array( 'log_context', 'caller' ) as $param ) {
			$value = $this->object_param( $request, $param );
			foreach ( array( 'proposal_id', 'correlation_id', 'adapter_request_id' ) as $key ) {
				if ( isset( $context[ $key ] ) && '' !== (string) $context[ $key ] ) {
					continue;
				}

				if ( isset( $value[ $key ] ) && is_scalar( $value[ $key ] ) && '' !== (string) $value[ $key ] ) {
					$context[ $key ] = $value[ $key ];
				}
			}
		}

		return $context;
	}

	/**
	 * Returns input for a GET shortcut.
	 *
	 * @param WP_REST_Request   $request Request.
	 * @param string            $route Shortcut route.
	 * @param array<string,mixed> $default_input Route default input.
	 * @return array<string,mixed>
	 */
	private function shortcut_input( WP_REST_Request $request, string $route, array $default_input = array() ): array {
		$input = array_merge( $default_input, $this->object_param( $request, 'input' ) );

		foreach ( $request->get_query_params() as $key => $value ) {
			if ( in_array( $key, array( 'input', 'rest_route', '_wpnonce' ), true ) ) {
				continue;
			}

			if ( in_array( $key, array( 'log_context', 'proposal_id', 'correlation_id', 'external_thread_id', 'openclaw_thread_id' ), true ) ) {
				continue;
			}

			$input[ sanitize_key( $key ) ] = $this->sanitize_input_value( $value );
		}

		return $this->normalize_shortcut_input( $route, $input );
	}

	/**
	 * Normalizes shortcut inputs where adapter route contracts differ from upstream ability inputs.
	 *
	 * @param string              $route Shortcut route.
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>
	 */
	private function normalize_shortcut_input( string $route, array $input ): array {
		if ( 'media-inventory-fix-plan' === $route ) {
			foreach ( array( 'include_delete_candidates', 'include_trash_parent_media', 'include_unattached_test_media' ) as $boolean_key ) {
				if ( array_key_exists( $boolean_key, $input ) ) {
					$input[ $boolean_key ] = $this->boolean_input_value( $input[ $boolean_key ] );
				}
			}
		}

		if ( 'term' !== $route ) {
			return $input;
		}

		if ( array_key_exists( 'term_id', $input ) && ! array_key_exists( 'id', $input ) ) {
			$input['id'] = absint( $input['term_id'] );
		}

		unset( $input['term_id'] );

		if ( ! array_key_exists( 'taxonomy', $input ) || '' === trim( (string) $input['taxonomy'] ) ) {
			$term = array_key_exists( 'id', $input ) ? get_term( absint( $input['id'] ) ) : null;
			if ( is_object( $term ) && ! is_wp_error( $term ) && '' !== (string) ( $term->taxonomy ?? '' ) ) {
				$input['taxonomy'] = sanitize_key( (string) $term->taxonomy );
			} else {
				$input['taxonomy'] = 'category';
			}
		}

		return $input;
	}

	/**
	 * Returns one object param.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $key Param key.
	 * @return array<string,mixed>
	 */
	private function object_param( WP_REST_Request $request, string $key ): array {
		$value = $request->get_param( $key );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Sanitizes shortcut query input.
	 *
	 * @param mixed $value Input value.
	 * @return mixed
	 */
	private function sanitize_input_value( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( $this, 'sanitize_input_value' ), $value );
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		return sanitize_text_field( wp_unslash( (string) $value ) );
	}

	/**
	 * Normalizes common REST query boolean values.
	 *
	 * @param mixed $value Input value.
	 * @return bool
	 */
	private function boolean_input_value( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_int( $value ) ) {
			return 0 !== $value;
		}

		$normalized = strtolower( trim( (string) $value ) );
		return in_array( $normalized, array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * Returns a short text preview for smoke responses.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private function text_preview( string $text ): string {
		$text = trim( wp_strip_all_tags( $text ) );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, 200 );
		}

		return substr( $text, 0, 200 );
	}

	/**
	 * Sanitizes AI request log context.
	 *
	 * @param mixed $value Context value.
	 * @return mixed
	 */
	private function sanitize_log_context( $value ) {
		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $key => $item ) {
				$clean[ sanitize_key( (string) $key ) ] = $this->sanitize_log_context( $item );
			}
			return $clean;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		return sanitize_text_field( wp_unslash( (string) $value ) );
	}

	/**
	 * Sanitizes host runtime context while preserving field names.
	 *
	 * @param mixed $value Runtime context value.
	 * @return mixed
	 */
	private function sanitize_runtime_context( $value ) {
		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $key => $item ) {
				$clean[ sanitize_key( (string) $key ) ] = $this->sanitize_runtime_context( $item );
			}
			return $clean;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		return sanitize_text_field( wp_unslash( (string) $value ) );
	}

	/**
	 * Returns public capability guidance fields.
	 *
	 * @param array<string,mixed> $capability Capability row.
	 * @return array<string,mixed>
	 */
	private function public_capability_guidance( array $capability ): array {
		return array(
			'ability_id'        => (string) ( $capability['ability_id'] ?? '' ),
			'risk_level'        => (string) ( $capability['risk_level'] ?? '' ),
			'requires_approval' => (bool) ( $capability['requires_approval'] ?? false ),
			'governance_mode'   => (string) ( $capability['governance_mode'] ?? '' ),
			'execution_surface' => (string) ( $capability['execution_surface'] ?? '' ),
			'core_proxy_execute' => (bool) ( $capability['core_proxy_execute'] ?? false ),
			'commit_execution'  => (bool) ( $capability['commit_execution'] ?? false ),
		);
	}

	/**
	 * Emits a metadata-only operation event.
	 *
	 * @param string              $event_kind Event kind.
	 * @param float               $started Start time.
	 * @param WP_Error|null       $error Error result.
	 * @param array<string,mixed> $context Safe context fields.
	 * @return void
	 */
	private function emit_operation_event( string $event_kind, float $started, $error, array $context = array() ): void {
		if ( is_wp_error( $error ) ) {
			if ( ! isset( $context['status_code'] ) ) {
				$error_data = $this->error_data_array( $error );
				if ( isset( $error_data['status'] ) ) {
					$context['status_code'] = absint( $error_data['status'] );
				}
			}

			if ( ! isset( $context['status_detail'] ) ) {
				$context['status_detail'] = $error->get_error_code();
			}
		}

		$status     = is_wp_error( $error ) ? 'error' : 'ok';
		$error_code = is_wp_error( $error ) ? (string) $error->get_error_code() : '';

		Observability::emit(
			$event_kind,
			array_merge(
				array(
					'status'     => $status,
					'event_id'   => $this->operation_event_id( $event_kind, $status, $error_code, $context ),
					'error_code' => $error_code,
					'latency_ms' => max( 0, (int) round( ( microtime( true ) - $started ) * 1000 ) ),
				),
				$this->safe_observability_context( $context )
			)
		);
	}

	/**
	 * Keeps operation observability payloads metadata-only and bounded.
	 *
	 * @param array<string,mixed> $context Candidate context.
	 * @return array<string,mixed>
	 */
	private function safe_observability_context( array $context ): array {
		$safe = array();

		foreach ( array( 'method', 'route', 'ability_id', 'proposal_id', 'correlation_id', 'adapter_request_id', 'status_detail', 'read_policy', 'sensitivity' ) as $key ) {
			if ( ! isset( $context[ $key ] ) || ! is_scalar( $context[ $key ] ) ) {
				continue;
			}

			$value = sanitize_text_field( (string) $context[ $key ] );
			if ( '' === $value ) {
				continue;
			}

			if ( 'method' === $key ) {
				$value = strtoupper( sanitize_key( $value ) );
			} elseif ( 'status_detail' === $key ) {
				$value = sanitize_key( $value );
			}

			$safe[ $key ] = substr( $value, 0, 200 );
		}

		foreach ( array( 'status_code', 'proposal_count', 'blocked_count', 'executed_count', 'failed_count' ) as $key ) {
			if ( isset( $context[ $key ] ) && is_scalar( $context[ $key ] ) ) {
				$safe[ $key ] = max( 0, absint( $context[ $key ] ) );
			}
		}

		foreach ( array( 'redaction_applied' ) as $key ) {
			if ( isset( $context[ $key ] ) ) {
				$safe[ $key ] = (bool) $context[ $key ];
			}
		}

			return $safe;
		}

		/**
		 * Builds a stable metadata-only event id for operation dedupe.
		 *
		 * @param string              $event_kind Event kind.
		 * @param string              $status Event status.
		 * @param string              $error_code Error code, when present.
		 * @param array<string,mixed> $context Metadata-only event context.
		 * @return string
		 */
		private function operation_event_id( string $event_kind, string $status, string $error_code, array $context ): string {
			$identity = array(
				'event_kind'         => $event_kind,
				'status'             => $status,
				'error_code'         => $error_code,
				'method'             => (string) ( $context['method'] ?? '' ),
				'route'              => (string) ( $context['route'] ?? '' ),
				'status_code'        => (int) ( $context['status_code'] ?? 0 ),
				'ability_id'         => (string) ( $context['ability_id'] ?? '' ),
				'proposal_id'        => (string) ( $context['proposal_id'] ?? '' ),
				'correlation_id'     => (string) ( $context['correlation_id'] ?? '' ),
				'adapter_request_id' => (string) ( $context['adapter_request_id'] ?? '' ),
				'proposal_count'     => (int) ( $context['proposal_count'] ?? 0 ),
				'blocked_count'      => (int) ( $context['blocked_count'] ?? 0 ),
				'executed_count'     => (int) ( $context['executed_count'] ?? 0 ),
				'failed_count'       => (int) ( $context['failed_count'] ?? 0 ),
			);
			$json     = function_exists( 'wp_json_encode' ) ? wp_json_encode( $identity ) : json_encode( $identity );
			$hash     = hash( 'sha256', is_string( $json ) ? $json : '' );
			$prefix   = sanitize_key( str_replace( '.', '_', $event_kind ) );

			return $prefix . '_' . substr( $hash, 0, 32 );
		}
	}
