<?php
/**
 * Adapter execution profile registry.
 *
 * @package NpcinkOpenClawAdapter
 */

namespace Npcink\OpenClawAdapter\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists Adapter-owned execution profiles for post-Core approved writes.
 */
final class Execution_Profile_Registry {
	/**
	 * Returns Adapter-owned execution profiles for abilities that may run after
	 * Core approval and commit preflight.
	 *
	 * Discovery tells Adapter which abilities exist; this registry is the
	 * explicit opt-in policy for final WordPress writes.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function profiles(): array {
		return array(
			'npcink-abilities-toolkit/trash-post'      => array(
				'allowed_input_fields'  => array( 'post_id', 'dry_run', 'commit', 'idempotency_key' ),
				'require_post_id'       => array(
					'code'    => 'npcink_openclaw_adapter_post_id_required',
					'message' => __( 'trash-post execution input must include post_id.', 'npcink-openclaw-adapter' ),
				),
				'force_post_input'      => true,
				'post_id_from_result'   => false,
			),
			'npcink-abilities-toolkit/create-draft'    => array(
				'allowed_input_fields'  => array( 'post_type', 'status', 'title', 'content', 'content_format', 'excerpt', 'soft_block_reason', 'soft_block_summary', 'meta', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'           => array(
					'status'         => array(
						'allowed' => array( 'draft' ),
						'code'    => 'npcink_openclaw_adapter_input_enum_invalid',
						'message' => __( 'create-draft status must be draft.', 'npcink-openclaw-adapter' ),
					),
					'content_format' => array(
						'allowed' => array( 'html', 'markdown', 'plain' ),
						'code'    => 'npcink_openclaw_adapter_content_format_invalid',
						'message' => __( 'create-draft content_format must be html, markdown, or plain.', 'npcink-openclaw-adapter' ),
					),
				),
				'required_text_fields'  => array(
					'title' => array(
						'code'    => 'npcink_openclaw_adapter_title_required',
						'message' => __( 'create-draft execution input must include title.', 'npcink-openclaw-adapter' ),
					),
				),
				'post_id_from_result'   => true,
			),
			'npcink-abilities-toolkit/update-post'     => array(
				'allowed_input_fields'  => array( 'post_id', 'title', 'content', 'content_format', 'excerpt', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'           => array(
					'content_format' => array(
						'allowed' => array( 'html', 'markdown', 'plain' ),
						'code'    => 'npcink_openclaw_adapter_content_format_invalid',
						'message' => __( 'update-post content_format must be html, markdown, or plain.', 'npcink-openclaw-adapter' ),
					),
				),
				'require_post_id'       => array(
					'code'    => 'npcink_openclaw_adapter_post_id_required',
					'message' => __( 'update-post execution input must include post_id.', 'npcink-openclaw-adapter' ),
				),
				'require_any_fields'    => array(
					'fields'  => array( 'title', 'content', 'excerpt' ),
					'code'    => 'npcink_openclaw_adapter_update_fields_required',
					'message' => __( 'update-post execution input must include title, content, or excerpt.', 'npcink-openclaw-adapter' ),
				),
				'post_id_from_result'   => false,
			),
			'npcink-abilities-toolkit/patch-post-content' => array(
				'allowed_input_fields'  => array( 'post_id', 'operations', 'dry_run', 'commit', 'idempotency_key' ),
				'require_post_id'       => array(
					'code'    => 'npcink_openclaw_adapter_post_id_required',
					'message' => __( 'patch-post-content execution input must include post_id.', 'npcink-openclaw-adapter' ),
				),
				'require_any_fields'    => array(
					'fields'  => array( 'operations' ),
					'code'    => 'npcink_openclaw_adapter_patch_operations_required',
					'message' => __( 'patch-post-content execution input must include operations.', 'npcink-openclaw-adapter' ),
				),
				'post_id_from_result'   => false,
			),
			'npcink-abilities-toolkit/update-post-blocks' => array(
				'allowed_input_fields'  => array( 'post_id', 'mode', 'validate_roundtrip', 'blocks', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'           => array(
					'mode' => array(
						'allowed' => array( 'replace', 'append' ),
						'code'    => 'npcink_openclaw_adapter_block_mode_invalid',
						'message' => __( 'update-post-blocks mode must be replace or append.', 'npcink-openclaw-adapter' ),
					),
				),
				'require_post_id'       => array(
					'code'    => 'npcink_openclaw_adapter_post_id_required',
					'message' => __( 'update-post-blocks execution input must include post_id.', 'npcink-openclaw-adapter' ),
				),
				'require_array_fields'  => array(
					'blocks' => array(
						'code'    => 'npcink_openclaw_adapter_blocks_required',
						'message' => __( 'update-post-blocks execution input must include at least one block.', 'npcink-openclaw-adapter' ),
					),
				),
				'post_id_from_result'   => false,
			),
			'npcink-abilities-toolkit/update-template-blocks' => array(
				'allowed_input_fields'  => array( 'post_id', 'mode', 'validate_roundtrip', 'blocks', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'           => array(
					'mode' => array(
						'allowed' => array( 'replace' ),
						'code'    => 'npcink_openclaw_adapter_template_block_mode_invalid',
						'message' => __( 'update-template-blocks mode must be replace.', 'npcink-openclaw-adapter' ),
					),
				),
				'require_post_id'       => array(
					'code'    => 'npcink_openclaw_adapter_post_id_required',
					'message' => __( 'update-template-blocks execution input must include post_id.', 'npcink-openclaw-adapter' ),
				),
				'require_array_fields'  => array(
					'blocks' => array(
						'code'    => 'npcink_openclaw_adapter_blocks_required',
						'message' => __( 'update-template-blocks execution input must include at least one block.', 'npcink-openclaw-adapter' ),
					),
				),
				'post_id_from_result'   => false,
			),
			'npcink-abilities-toolkit/upsert-template-blocks' => array(
				'allowed_input_fields'  => array( 'post_id', 'slug', 'theme', 'title', 'source_template_id', 'mode', 'validate_roundtrip', 'blocks', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'           => array(
					'mode' => array(
						'allowed' => array( 'replace' ),
						'code'    => 'npcink_openclaw_adapter_template_upsert_mode_invalid',
						'message' => __( 'upsert-template-blocks mode must be replace.', 'npcink-openclaw-adapter' ),
					),
				),
				'required_slug_fields'  => array(
					'slug' => array(
						'code'    => 'npcink_openclaw_adapter_template_slug_required',
						'message' => __( 'upsert-template-blocks execution input must include a valid slug.', 'npcink-openclaw-adapter' ),
					),
				),
				'require_array_fields'  => array(
					'blocks' => array(
						'code'    => 'npcink_openclaw_adapter_blocks_required',
						'message' => __( 'upsert-template-blocks execution input must include at least one block.', 'npcink-openclaw-adapter' ),
					),
				),
				'post_id_from_result'   => true,
			),
			'npcink-abilities-toolkit/update-template-part-blocks' => array(
				'allowed_input_fields'  => array( 'post_id', 'mode', 'validate_roundtrip', 'blocks', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'           => array(
					'mode' => array(
						'allowed' => array( 'replace' ),
						'code'    => 'npcink_openclaw_adapter_template_part_block_mode_invalid',
						'message' => __( 'update-template-part-blocks mode must be replace.', 'npcink-openclaw-adapter' ),
					),
				),
				'require_post_id'       => array(
					'code'    => 'npcink_openclaw_adapter_post_id_required',
					'message' => __( 'update-template-part-blocks execution input must include post_id.', 'npcink-openclaw-adapter' ),
				),
				'require_array_fields'  => array(
					'blocks' => array(
						'code'    => 'npcink_openclaw_adapter_blocks_required',
						'message' => __( 'update-template-part-blocks execution input must include at least one block.', 'npcink-openclaw-adapter' ),
					),
				),
				'post_id_from_result'   => false,
			),
			'npcink-abilities-toolkit/patch-setting-value' => array(
				'allowed_input_fields'  => array( 'target_type', 'target_name', 'operations', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'           => array(
					'target_type' => array(
						'allowed' => array( 'option', 'theme_mod' ),
						'code'    => 'npcink_openclaw_adapter_setting_target_type_invalid',
						'message' => __( 'patch-setting-value target_type must be option or theme_mod.', 'npcink-openclaw-adapter' ),
					),
				),
				'require_any_fields'    => array(
					'fields'  => array( 'operations' ),
					'code'    => 'npcink_openclaw_adapter_patch_operations_required',
					'message' => __( 'patch-setting-value execution input must include operations.', 'npcink-openclaw-adapter' ),
				),
				'required_text_fields'  => array(
					'target_name' => array(
						'code'    => 'npcink_openclaw_adapter_setting_target_required',
						'message' => __( 'patch-setting-value execution input must include target_name.', 'npcink-openclaw-adapter' ),
					),
				),
				'post_id_from_result'   => false,
			),
			'npcink-abilities-toolkit/set-post-seo-meta' => array(
				'allowed_input_fields'  => array( 'post_id', 'seo_title', 'seo_description', 'dry_run', 'commit', 'idempotency_key' ),
				'require_post_id'       => array(
					'code'    => 'npcink_openclaw_adapter_post_id_required',
					'message' => __( 'set-post-seo-meta execution input must include post_id.', 'npcink-openclaw-adapter' ),
				),
				'require_any_fields'    => array(
					'fields'  => array( 'seo_title', 'seo_description' ),
					'code'    => 'npcink_openclaw_adapter_seo_fields_required',
					'message' => __( 'set-post-seo-meta execution input must include seo_title or seo_description.', 'npcink-openclaw-adapter' ),
				),
				'post_id_from_result'   => false,
			),
			'npcink-abilities-toolkit/set-post-slug'   => array(
				'allowed_input_fields'  => array( 'post_id', 'slug', 'dry_run', 'commit', 'idempotency_key' ),
				'require_post_id'       => array(
					'code'    => 'npcink_openclaw_adapter_post_id_required',
					'message' => __( 'set-post-slug execution input must include post_id.', 'npcink-openclaw-adapter' ),
				),
				'required_slug_fields'  => array(
					'slug' => array(
						'code'    => 'npcink_openclaw_adapter_slug_required',
						'message' => __( 'set-post-slug execution input must include a valid slug.', 'npcink-openclaw-adapter' ),
					),
				),
				'post_id_from_result'   => false,
			),
			'npcink-abilities-toolkit/set-post-terms'  => array(
				'allowed_input_fields'  => array( 'post_id', 'taxonomy', 'mode', 'term_ids', 'terms', 'create_missing', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'           => array(
					'mode' => array(
						'allowed' => array( 'replace', 'append', 'remove' ),
						'code'    => 'npcink_openclaw_adapter_term_mode_invalid',
						'message' => __( 'set-post-terms execution mode must be replace, append, or remove.', 'npcink-openclaw-adapter' ),
					),
				),
				'require_post_id'       => array(
					'code'    => 'npcink_openclaw_adapter_post_id_required',
					'message' => __( 'set-post-terms execution input must include post_id.', 'npcink-openclaw-adapter' ),
				),
				'validate_terms_input'  => true,
				'post_id_from_result'   => false,
			),
			'npcink-abilities-toolkit/delete-term'     => array(
				'allowed_input_fields'      => array( 'taxonomy', 'term_id', 'dry_run', 'commit', 'idempotency_key' ),
				'required_int_fields'       => array(
					'term_id' => array(
						'code'    => 'npcink_openclaw_adapter_term_id_required',
						'message' => __( 'delete-term execution input must include term_id.', 'npcink-openclaw-adapter' ),
					),
				),
				'validate_delete_term_input' => true,
				'post_id_from_result'       => false,
			),
			'npcink-abilities-toolkit/update-media-details' => array(
				'allowed_input_fields'  => array( 'attachment_id', 'title', 'alt', 'caption', 'description', 'source_type', 'source_page_url', 'photographer_name', 'attribution_text', 'copyright_notice', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'           => array(
					'source_type' => array(
						'allowed' => array( 'owned', 'ai_generated', 'stock', 'external', 'test' ),
						'code'    => 'npcink_openclaw_adapter_media_source_type_invalid',
						'message' => __( 'update-media-details source_type must be owned, ai_generated, stock, external, or test.', 'npcink-openclaw-adapter' ),
					),
				),
				'required_int_fields'   => array(
					'attachment_id' => array(
						'code'    => 'npcink_openclaw_adapter_attachment_id_required',
						'message' => __( 'update-media-details execution input must include attachment_id.', 'npcink-openclaw-adapter' ),
					),
				),
				'require_any_fields'    => array(
					'fields'  => array( 'title', 'alt', 'caption', 'description', 'source_type', 'source_page_url', 'photographer_name', 'attribution_text', 'copyright_notice' ),
					'code'    => 'npcink_openclaw_adapter_media_fields_required',
					'message' => __( 'update-media-details execution input must include at least one media detail field.', 'npcink-openclaw-adapter' ),
				),
				'post_id_from_result'   => false,
			),
			'npcink-abilities-toolkit/upload-media-from-url' => array(
				'allowed_input_fields'  => array( 'url', 'title', 'file_name', 'alt', 'caption', 'description', 'source_type', 'source_page_url', 'photographer_name', 'attribution_text', 'copyright_notice', 'attach_to_post_id', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'           => array(
					'source_type' => array(
						'allowed' => array( 'owned', 'ai_generated', 'stock', 'external', 'test' ),
						'code'    => 'npcink_openclaw_adapter_media_source_type_invalid',
						'message' => __( 'upload-media-from-url source_type must be owned, ai_generated, stock, external, or test.', 'npcink-openclaw-adapter' ),
					),
				),
				'required_text_fields'  => array(
					'url' => array(
						'code'    => 'npcink_openclaw_adapter_media_url_required',
						'message' => __( 'upload-media-from-url execution input must include url.', 'npcink-openclaw-adapter' ),
					),
				),
				'post_id_from_result'   => false,
			),
			'npcink-abilities-toolkit/set-post-featured-image' => array(
				'allowed_input_fields'  => array( 'post_id', 'attachment_id', 'media_url', 'media_title', 'dry_run', 'commit', 'idempotency_key' ),
				'require_post_id'       => array(
					'code'    => 'npcink_openclaw_adapter_post_id_required',
					'message' => __( 'set-post-featured-image execution input must include post_id.', 'npcink-openclaw-adapter' ),
				),
				'require_any_fields'    => array(
					'fields'  => array( 'attachment_id', 'media_url' ),
					'code'    => 'npcink_openclaw_adapter_featured_image_required',
					'message' => __( 'set-post-featured-image execution input must include attachment_id or media_url.', 'npcink-openclaw-adapter' ),
				),
				'post_id_from_result'   => true,
			),
			'npcink-abilities-toolkit/optimize-media-asset' => array(
				'allowed_input_fields'  => array( 'attachment_id', 'target_max_width', 'preferred_format', 'quality', 'derivative_suffix', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'           => array(
					'preferred_format' => array(
						'allowed' => array( 'webp', 'jpeg', 'png' ),
						'code'    => 'npcink_openclaw_adapter_media_preferred_format_invalid',
						'message' => __( 'optimize-media-asset preferred_format must be webp, jpeg, or png.', 'npcink-openclaw-adapter' ),
					),
				),
				'required_int_fields'   => array(
					'attachment_id' => array(
						'code'    => 'npcink_openclaw_adapter_attachment_id_required',
						'message' => __( 'optimize-media-asset execution input must include attachment_id.', 'npcink-openclaw-adapter' ),
					),
				),
				'post_id_from_result'   => false,
			),
			'npcink-abilities-toolkit/replace-media-file' => array(
				'allowed_input_fields'  => array( 'attachment_id', 'derivative_relative_file', 'expected_current_relative_file', 'expected_current_mime_type', 'expected_derivative_mime_type', 'backup_suffix', 'dry_run', 'commit', 'idempotency_key' ),
				'required_int_fields'   => array(
					'attachment_id' => array(
						'code'    => 'npcink_openclaw_adapter_attachment_id_required',
						'message' => __( 'replace-media-file execution input must include attachment_id.', 'npcink-openclaw-adapter' ),
					),
				),
				'post_id_from_result'   => false,
			),
			'npcink-abilities-toolkit/restore-media-backup' => array(
				'allowed_input_fields'  => array( 'attachment_id', 'backup_id', 'expected_current_relative_file', 'expected_current_mime_type', 'target_conflict_mode', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'           => array(
					'target_conflict_mode' => array(
						'allowed' => array( 'fail', 'overwrite' ),
						'code'    => 'npcink_openclaw_adapter_media_restore_conflict_mode_invalid',
						'message' => __( 'restore-media-backup target_conflict_mode must be fail or overwrite.', 'npcink-openclaw-adapter' ),
					),
				),
				'required_int_fields'   => array(
					'attachment_id' => array(
						'code'    => 'npcink_openclaw_adapter_attachment_id_required',
						'message' => __( 'restore-media-backup execution input must include attachment_id.', 'npcink-openclaw-adapter' ),
					),
				),
				'required_text_fields'  => array(
					'backup_id' => array(
						'code'    => 'npcink_openclaw_adapter_backup_id_required',
						'message' => __( 'restore-media-backup execution input must include backup_id.', 'npcink-openclaw-adapter' ),
					),
				),
				'post_id_from_result'   => false,
			),
			'npcink-abilities-toolkit/adopt-cloud-media-derivative' => array(
				'allowed_input_fields'  => array( 'attachment_id', 'derivative_artifact', 'expected_current_relative_file', 'expected_current_mime_type', 'expected_derivative_mime_type', 'file_name', 'expected_content_reference_post_ids', 'expected_content_reference_post_count', 'expected_content_reference_replacement_count', 'content_reference_repairs', 'backup_suffix', 'dry_run', 'commit', 'idempotency_key' ),
				'required_int_fields'   => array(
					'attachment_id' => array(
						'code'    => 'npcink_openclaw_adapter_attachment_id_required',
						'message' => __( 'adopt-cloud-media-derivative execution input must include attachment_id.', 'npcink-openclaw-adapter' ),
					),
				),
				'require_any_fields'    => array(
					'fields'  => array( 'derivative_artifact' ),
					'code'    => 'npcink_openclaw_adapter_derivative_artifact_required',
					'message' => __( 'adopt-cloud-media-derivative execution input must include derivative_artifact evidence.', 'npcink-openclaw-adapter' ),
				),
				'post_id_from_result'   => false,
			),
			'npcink-abilities-toolkit/rename-media-file' => array(
				'allowed_input_fields'     => array( 'attachment_id', 'target_file_name', 'expected_current_relative_file', 'expected_current_mime_type', 'expected_current_md5', 'expected_current_sha256', 'conflict_mode', 'backup_suffix', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'              => array(
					'conflict_mode' => array(
						'allowed' => array( 'fail', 'unique' ),
						'code'    => 'npcink_openclaw_adapter_media_rename_conflict_mode_invalid',
						'message' => __( 'rename-media-file conflict_mode must be fail or unique.', 'npcink-openclaw-adapter' ),
					),
				),
				'required_int_fields'      => array(
					'attachment_id' => array(
						'code'    => 'npcink_openclaw_adapter_attachment_id_required',
						'message' => __( 'rename-media-file execution input must include attachment_id.', 'npcink-openclaw-adapter' ),
					),
				),
				'required_text_fields'     => array(
					'target_file_name' => array(
						'code'    => 'npcink_openclaw_adapter_target_file_name_required',
						'message' => __( 'rename-media-file execution input must include target_file_name.', 'npcink-openclaw-adapter' ),
					),
				),
				'validate_attachment_input' => array(
					'message' => __( 'rename-media-file execution input must target an existing attachment.', 'npcink-openclaw-adapter' ),
				),
				'post_id_from_result'      => false,
			),
			'npcink-abilities-toolkit/delete-media-permanently' => array(
				'allowed_input_fields'     => array( 'attachment_id', 'dry_run', 'commit', 'idempotency_key' ),
				'required_int_fields'      => array(
					'attachment_id' => array(
						'code'    => 'npcink_openclaw_adapter_attachment_id_required',
						'message' => __( 'delete-media-permanently execution input must include attachment_id.', 'npcink-openclaw-adapter' ),
					),
				),
				'validate_attachment_input' => array(
					'message' => __( 'delete-media-permanently execution input must target an existing attachment.', 'npcink-openclaw-adapter' ),
				),
				'post_id_from_result'      => false,
			),
			'npcink-abilities-toolkit/reply-comment'   => array(
				'allowed_input_fields'  => array( 'comment_id', 'content', 'content_format', 'dry_run', 'commit', 'idempotency_key' ),
				'enum_fields'           => array(
					'content_format' => array(
						'allowed' => array( 'html', 'markdown', 'plain' ),
						'code'    => 'npcink_openclaw_adapter_content_format_invalid',
						'message' => __( 'reply-comment content_format must be html, markdown, or plain.', 'npcink-openclaw-adapter' ),
					),
				),
				'required_int_fields'   => array(
					'comment_id' => array(
						'code'    => 'npcink_openclaw_adapter_comment_id_required',
						'message' => __( 'reply-comment execution input must include comment_id.', 'npcink-openclaw-adapter' ),
					),
				),
				'require_comment_body'  => array(
					'code'    => 'npcink_openclaw_adapter_comment_content_required',
					'message' => __( 'reply-comment execution input must include content.', 'npcink-openclaw-adapter' ),
				),
				'post_id_from_result'   => true,
			),
			'npcink-abilities-toolkit/trash-comment'   => array(
				'allowed_input_fields'  => array( 'comment_id', 'dry_run', 'commit', 'idempotency_key' ),
				'required_int_fields'   => array(
					'comment_id' => array(
						'code'    => 'npcink_openclaw_adapter_comment_id_required',
						'message' => __( 'trash-comment execution input must include comment_id.', 'npcink-openclaw-adapter' ),
					),
				),
				'post_id_from_result'   => true,
			),
			'npcink-abilities-toolkit/approve-comment' => array(
				'allowed_input_fields'  => array( 'comment_id', 'dry_run', 'commit', 'idempotency_key' ),
				'required_int_fields'   => array(
					'comment_id' => array(
						'code'    => 'npcink_openclaw_adapter_comment_id_required',
						'message' => __( 'approve-comment execution input must include comment_id.', 'npcink-openclaw-adapter' ),
					),
				),
				'post_id_from_result'   => true,
			),
		);
	}
}
