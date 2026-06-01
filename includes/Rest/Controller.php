<?php
/**
 * Adapter REST controller.
 *
 * @package MagickAIAdapter
 */

namespace MagickAI\Adapter\Rest;

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
	const NAMESPACE = 'magick-ai-adapter/v1';
	const MAX_EXECUTION_ACTIONS = 50;
	const DEVICE_PAIRING_OPTION = 'magick_ai_adapter_device_pairings';
	const CLIENT_KEYS_OPTION    = 'magick_ai_adapter_client_keys';
	const DEVICE_PAIRING_TTL    = 600;
	const SIGNATURE_NONCE_TTL   = 300;

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
		'magick-ai/build-content-inventory-fix-plan' => true,
		'magick-ai/build-test-content-cleanup-plan'  => true,
		'magick-ai/build-media-inventory-fix-plan'   => true,
	);

	/**
	 * Abilities this adapter may execute after Core approval and commit preflight.
	 *
	 * @var array<string,bool>
	 */
	private static $allowed_execute_ability_ids = array(
		'magick-ai/trash-post'     => true,
		'magick-ai/create-draft'   => true,
		'magick-ai/update-post'    => true,
		'magick-ai/set-post-terms' => true,
		'magick-ai/reply-comment'  => true,
	);

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
				)
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
		$body = $this->request_json_body( $request );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			return new WP_Error(
				'magick_ai_adapter_sodium_unavailable',
				__( 'Ed25519 device pairing requires the PHP sodium extension.', 'magick-ai-adapter' ),
				array( 'status' => 501 )
			);
		}

		$client = is_array( $body['client'] ?? null ) ? $body['client'] : array();
		$key    = is_array( $body['key'] ?? null ) ? $body['key'] : array();
		$name   = sanitize_text_field( (string) ( $client['name'] ?? '' ) );
		$public_key = sanitize_text_field( (string) ( $key['public_key'] ?? '' ) );
		$scopes = $this->connection_requested_scopes( is_array( $body['requested_scopes'] ?? null ) ? $body['requested_scopes'] : array() );

		if ( '' === $name || 'Ed25519' !== (string) ( $key['alg'] ?? '' ) || 32 !== strlen( $this->base64url_decode( $public_key ) ) ) {
			return new WP_Error(
				'magick_ai_adapter_device_pairing_invalid',
				__( 'Device pairing requires client metadata and a base64url Ed25519 public key.', 'magick-ai-adapter' ),
				array( 'status' => 400 )
			);
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
		$body = $this->request_json_body( $request );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$device_code = sanitize_text_field( (string) ( $body['device_code'] ?? '' ) );
		$pairing     = $this->device_pairing_by_device_code( $device_code );

		if ( empty( $pairing ) || time() > (int) ( $pairing['expires_at'] ?? 0 ) ) {
			return new WP_Error(
				'magick_ai_adapter_device_pairing_expired',
				__( 'Device pairing is expired or invalid.', 'magick-ai-adapter' ),
				array( 'status' => 401 )
			);
		}

		if ( 'rejected' === (string) ( $pairing['status'] ?? '' ) ) {
			return new WP_Error(
				'magick_ai_adapter_device_pairing_rejected',
				__( 'Device pairing was rejected.', 'magick-ai-adapter' ),
				array( 'status' => 403 )
			);
		}

		if ( 'approved' !== (string) ( $pairing['status'] ?? '' ) ) {
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
		$user_code = strtoupper( sanitize_text_field( $user_code ) );
		$pairings  = $this->device_pairings();
		$pairing   = is_array( $pairings[ $user_code ] ?? null ) ? $pairings[ $user_code ] : array();
		if ( empty( $pairing ) || time() > (int) ( $pairing['expires_at'] ?? 0 ) ) {
			return new WP_Error( 'magick_ai_adapter_pairing_not_found', __( 'Device pairing was not found or expired.', 'magick-ai-adapter' ) );
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

		return $this->public_client_key_record( $record );
	}

	/**
	 * Rejects a device pairing.
	 *
	 * @param string $user_code User code.
	 * @return bool
	 */
	public function reject_device_pairing( string $user_code ): bool {
		$user_code = strtoupper( sanitize_text_field( $user_code ) );
		$pairings  = $this->device_pairings();
		if ( ! is_array( $pairings[ $user_code ] ?? null ) ) {
			return false;
		}

		$pairings[ $user_code ]['status']      = 'rejected';
		$pairings[ $user_code ]['rejected_at'] = gmdate( 'c' );
		update_option( self::DEVICE_PAIRING_OPTION, $this->prune_device_pairings( $pairings ), false );

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
				'allowed_execute_ability_ids' => array_keys( self::$allowed_execute_ability_ids ),
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
						'governance_mode'   => 'direct_read',
						'execution_surface' => 'wp_abilities_rest',
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
						'allowed_ability_ids'  => array_keys( self::$allowed_execute_ability_ids ),
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
						'allowed_ability_ids'  => array_keys( self::$allowed_execute_ability_ids ),
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
				'allowed_execute_ability_ids' => array_keys( self::$allowed_execute_ability_ids ),
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
			'POST /ai-provider-log-correlation-smoke' => 'Run a provider log correlation smoke request.',
			'GET /proposals' => 'List Core proposal statuses for polling.',
			'GET /proposals/{proposal_id}' => 'Read one Core proposal status by proposal_id.',
			'POST /proposals' => 'Create a Core proposal for governed work.',
			'POST /proposals/from-plan' => 'Forward a read-only plan output to Core plan-to-proposal intake.',
			'POST /proposals/{proposal_id}/approve' => 'Disabled stub; approvals happen in Magick AI Core admin.',
			'POST /proposals/{proposal_id}/reject' => 'Disabled stub; rejections happen in Magick AI Core admin.',
			'POST /proposals/{proposal_id}/commit-preflight' => 'Run Core commit preflight without executing final writes.',
			'POST /execute-approved-proposal' => 'Execute one approved proposal after Core commit preflight; supports allowlisted single inputs or write_actions.',
			'POST /proposals/{proposal_id}/execute' => 'Execute one approved proposal by id after Core commit preflight; supports allowlisted single inputs or write_actions.',
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
	 * Runs a workflow recipe detail helper.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function workflow_recipe( WP_REST_Request $request ) {
		return $this->run_read_ability(
			'magick-ai-abilities/get-workflow-recipe',
			array(
				'recipe_id' => (string) $request->get_param( 'recipe_id' ),
			),
			$this->request_log_context( $request, 'magick-ai-abilities/get-workflow-recipe' )
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
		$ability_id = (string) $request->get_param( 'ability_id' );
		$params = array(
			'ability_id' => $ability_id,
			'title'      => (string) $request->get_param( 'title' ),
			'summary'    => (string) $request->get_param( 'summary' ),
			'input'      => $this->object_param( $request, 'input' ),
			'preview'    => $this->object_param( $request, 'preview' ),
			'caller'     => $this->proposal_caller_context( $request, $ability_id ),
		);

		return $this->dispatch_upstream( 'POST', '/magick-ai-core/v1/proposals', $params );
	}

	/**
	 * Creates Core proposals from a read-only plan output.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_proposals_from_plan( WP_REST_Request $request ) {
		$plan_ability_id = sanitize_text_field( (string) $request->get_param( 'plan_ability_id' ) );
		if ( ! isset( self::$allowed_plan_ability_ids[ $plan_ability_id ] ) ) {
			return new WP_Error(
				'magick_ai_adapter_plan_ability_not_allowed',
				__( 'This planning ability is not accepted by the adapter plan-to-proposal bridge.', 'magick-ai-adapter' ),
				array(
					'status'                   => 400,
					'allowed_plan_ability_ids' => array_keys( self::$allowed_plan_ability_ids ),
				)
			);
		}

		$params = array(
			'plan_ability_id' => $plan_ability_id,
			'plan'            => $this->object_param( $request, 'plan' ),
			'plan_input'      => $this->object_param( $request, 'plan_input' ),
			'caller'          => $this->proposal_caller_context( $request, $plan_ability_id ),
		);

		return $this->dispatch_upstream( 'POST', '/magick-ai-core/v1/proposals/from-plan', $params );
	}

	/**
	 * Runs Core commit preflight.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function commit_preflight( WP_REST_Request $request ) {
		$proposal_id = (string) $request->get_param( 'proposal_id' );
		return $this->dispatch_upstream( 'POST', '/magick-ai-core/v1/proposals/' . rawurlencode( $proposal_id ) . '/commit-preflight' );
	}

	/**
	 * Executes one approved Core proposal after commit preflight.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function execute_approved_proposal_route( WP_REST_Request $request ) {
		$proposal_id = sanitize_text_field( (string) $request->get_param( 'proposal_id' ) );
		if ( '' === $proposal_id ) {
			return new WP_Error(
				'magick_ai_adapter_proposal_id_required',
				__( 'proposal_id is required.', 'magick-ai-adapter' ),
				array( 'status' => 400 )
			);
		}

		$proposal = $this->get_core_proposal_data( $proposal_id );
		if ( is_wp_error( $proposal ) ) {
			return $proposal;
		}

		$execution = $this->execute_core_approved_proposal( $request, $proposal_id, $proposal );
		if ( is_wp_error( $execution ) ) {
			return $execution;
		}

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
				'commit_execution'   => false,
				'execution_surface'  => 'wp_abilities_rest',
				'executed_count'     => $execution['executed_count'],
				'failed_count'       => $execution['failed_count'],
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
		$proposal_id = sanitize_text_field( (string) $request->get_param( 'proposal_id' ) );
		if ( '' === $proposal_id ) {
			return new WP_Error(
				'magick_ai_adapter_proposal_id_required',
				__( 'proposal_id is required.', 'magick-ai-adapter' ),
				array( 'status' => 400 )
			);
		}

		$proposal = $this->get_core_proposal_data( $proposal_id );
		if ( is_wp_error( $proposal ) ) {
			return $proposal;
		}

		$ability_id = sanitize_text_field( (string) ( $proposal['ability_id'] ?? '' ) );
		$execution_actions = $this->normalize_execution_actions( $proposal_id, $proposal );
		if ( is_wp_error( $execution_actions ) ) {
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
				return $approved_response;
			}

			$approved = $approved_response->get_data();
			if ( ! is_array( $approved ) || 'approved' !== (string) ( $approved['status'] ?? '' ) ) {
				return new WP_Error(
					'magick_ai_adapter_core_approve_failed',
					__( 'Core did not return an approved proposal state.', 'magick-ai-adapter' ),
					array(
						'status'      => 409,
						'proposal_id' => $proposal_id,
						'core_result' => $approved,
					)
				);
			}

			$approved_by_adapter = true;
		} elseif ( 'approved' !== $status_before ) {
			$code = 'rejected' === $status_before ? 'magick_ai_adapter_proposal_rejected' : 'magick_ai_adapter_proposal_not_executable';
			return new WP_Error(
				$code,
				__( 'This proposal cannot be approved and executed from its current status.', 'magick-ai-adapter' ),
				array(
					'status'        => 409,
					'proposal_id'   => $proposal_id,
					'ability_id'    => $ability_id,
					'status_before' => $status_before,
				)
			);
		}

		$execution = $this->execute_core_approved_proposal( $request, $proposal_id, $proposal );
		if ( is_wp_error( $execution ) ) {
			return $execution;
		}

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
				'executed_count'        => $execution['executed_count'],
				'failed_count'          => $execution['failed_count'],
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
		if ( isset( self::$allowed_execute_ability_ids[ $ability_id ] ) ) {
			return true;
		}

		return new WP_Error(
			'magick_ai_adapter_execute_ability_not_allowed',
			__( 'This proposal ability is not allowed for adapter execution.', 'magick-ai-adapter' ),
			array(
				'status'                      => 403,
				'proposal_id'                 => $proposal_id,
				'ability_id'                  => $ability_id,
				'allowed_execute_ability_ids' => array_keys( self::$allowed_execute_ability_ids ),
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
	 * @return true|WP_Error
	 */
	private function validate_execute_action_input( string $proposal_id, string $ability_id, array $input, int $post_id, ?int $action_index = null ) {
		$error_data = array(
			'status'      => 400,
			'proposal_id' => $proposal_id,
			'ability_id'  => $ability_id,
		);
		if ( null !== $action_index ) {
			$error_data['action_index']      = $action_index;
			$error_data['target_ability_id'] = $ability_id;
		}

		if ( 'magick-ai/trash-post' === $ability_id && 0 === $post_id ) {
			return new WP_Error(
				'magick_ai_adapter_post_id_required',
				__( 'trash-post execution input must include post_id.', 'magick-ai-adapter' ),
				$error_data
			);
		}

		if ( 'magick-ai/create-draft' === $ability_id && '' === trim( sanitize_text_field( (string) ( $input['title'] ?? '' ) ) ) ) {
			return new WP_Error(
				'magick_ai_adapter_title_required',
				__( 'create-draft execution input must include title.', 'magick-ai-adapter' ),
				$error_data
			);
		}

		if ( 'magick-ai/update-post' === $ability_id ) {
			if ( 0 === $post_id ) {
				return new WP_Error(
					'magick_ai_adapter_post_id_required',
					__( 'update-post execution input must include post_id.', 'magick-ai-adapter' ),
					$error_data
				);
			}

			$has_update_field = false;
			foreach ( array( 'title', 'content', 'excerpt' ) as $field ) {
				if ( array_key_exists( $field, $input ) ) {
					$has_update_field = true;
					break;
				}
			}
			if ( ! $has_update_field ) {
				return new WP_Error(
					'magick_ai_adapter_update_fields_required',
					__( 'update-post execution input must include title, content, or excerpt.', 'magick-ai-adapter' ),
					$error_data
				);
			}
		}

		if ( 'magick-ai/set-post-terms' === $ability_id ) {
			if ( 0 === $post_id ) {
				return new WP_Error(
					'magick_ai_adapter_post_id_required',
					__( 'set-post-terms execution input must include post_id.', 'magick-ai-adapter' ),
					$error_data
				);
			}

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

		if ( 'magick-ai/reply-comment' === $ability_id ) {
			$comment_id = absint( $input['comment_id'] ?? 0 );
			if ( 0 === $comment_id ) {
				return new WP_Error(
					'magick_ai_adapter_comment_id_required',
					__( 'reply-comment execution input must include comment_id.', 'magick-ai-adapter' ),
					$error_data
				);
			}

			$content = (string) ( $input['content'] ?? '' );
			if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
				return new WP_Error(
					'magick_ai_adapter_comment_content_required',
					__( 'reply-comment execution input must include content.', 'magick-ai-adapter' ),
					$error_data
				);
			}

			$content_format = sanitize_key( (string) ( $input['content_format'] ?? 'html' ) );
			if ( ! in_array( $content_format, array( 'html', 'markdown', 'plain' ), true ) ) {
				return new WP_Error(
					'magick_ai_adapter_content_format_invalid',
					__( 'reply-comment content_format must be html, markdown, or plain.', 'magick-ai-adapter' ),
					$error_data
				);
			}
		}

		return true;
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

			$actions = array();
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

				$action_input = is_array( $raw_action['input'] ?? null ) ? $raw_action['input'] : array();
				$post_id      = absint( $action_input['post_id'] ?? 0 );
				$valid_input  = $this->validate_execute_action_input( $proposal_id, $target_ability_id, $action_input, $post_id, $index );
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

				$action_id = sanitize_key( (string) ( $raw_action['action_id'] ?? '' ) );
				if ( '' === $action_id ) {
					$action_id = 'action-' . ( $index + 1 );
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

		$post_status_before = get_post_status( $post_id );
		$post_status_before = false === $post_status_before ? '' : (string) $post_status_before;

		$ability_input = is_array( $action['input'] ?? null ) ? $action['input'] : array();
		if ( 'magick-ai/trash-post' === $ability_id ) {
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
		if ( in_array( $ability_id, array( 'magick-ai/create-draft', 'magick-ai/reply-comment' ), true ) && is_array( $result_data ) ) {
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
		$actions             = $this->normalize_execution_actions( $proposal_id, $proposal );
		if ( is_wp_error( $actions ) ) {
			return $actions;
		}

		$preflight_response = $this->dispatch_upstream( 'POST', '/magick-ai-core/v1/proposals/' . rawurlencode( $proposal_id ) . '/commit-preflight' );
		if ( is_wp_error( $preflight_response ) ) {
			return $preflight_response;
		}

		$preflight = $preflight_response->get_data();
		if ( ! is_array( $preflight ) ) {
			return new WP_Error(
				'magick_ai_adapter_invalid_core_preflight',
				__( 'Core commit preflight response is invalid.', 'magick-ai-adapter' ),
				array( 'status' => 502 )
			);
		}

		$approval_context = is_array( $preflight['approval_context'] ?? null ) ? $preflight['approval_context'] : array();
		if ( true !== (bool) ( $approval_context['approval_commit_authorized'] ?? false ) ) {
			return new WP_Error(
				'magick_ai_adapter_preflight_not_authorized',
				__( 'Core commit preflight did not authorize approval commit.', 'magick-ai-adapter' ),
				array(
					'status'      => 409,
					'proposal_id' => $proposal_id,
					'preflight'   => $preflight,
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
					'status'      => 409,
					'proposal_id' => $proposal_id,
					'preflight'   => $preflight,
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
		foreach ( $actions as $action ) {
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

		return array(
			'ability_id'          => $response_ability_id,
			'post_id'             => absint( $first_result['post_id'] ?? 0 ),
			'post_ids'            => $post_ids,
			'correlation_id'      => $correlation_id,
			'adapter_request_id'  => (string) ( $base_request_context['adapter_request_id'] ?? '' ),
			'approval_context'    => $approval_context,
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
		$ability_id = sanitize_text_field( $ability_id );
		$capability = $this->find_core_capability( $ability_id );

		if ( is_wp_error( $capability ) ) {
			return $capability;
		}

		if ( 'direct_read' !== (string) ( $capability['governance_mode'] ?? '' ) || 'wp_abilities_rest' !== (string) ( $capability['execution_surface'] ?? '' ) ) {
			return new WP_Error(
				'magick_ai_adapter_proposal_required',
				__( 'This ability is not direct-read. Create a Core proposal instead.', 'magick-ai-adapter' ),
				array(
					'status'     => 403,
					'capability' => $this->public_capability_guidance( $capability ),
				)
			);
		}

		if ( true === (bool) ( $capability['core_proxy_execute'] ?? false ) || true === (bool) ( $capability['commit_execution'] ?? false ) ) {
			return new WP_Error(
				'magick_ai_adapter_invalid_core_guidance',
				__( 'Core guidance unexpectedly allows proxy or commit execution.', 'magick-ai-adapter' ),
				array( 'status' => 500 )
			);
		}

		$route    = '/wp-abilities/v1/abilities/' . $ability_id . '/run';
		$response = $this->dispatch_upstream_with_request_log_context( $log_context, 'GET', $route, array( 'input' => $input ), true );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = $response->get_data();

		return new WP_REST_Response(
			array(
				'ability_id'        => $ability_id,
				'governance_mode'   => 'direct_read',
				'execution_surface' => 'wp_abilities_rest',
				'core_proxy_execute' => false,
				'commit_execution'  => false,
				'log_context'       => $log_context,
				'result'            => $data,
			),
			200
		);
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
		$summary_ability = 'magick-ai-abilities/wp-diagnostics-summary';
		$ops_ability     = 'magick-ai-abilities/wp-ops-diagnostics-detail';

		return array(
			'site-info'              => array( 'ability_id' => 'magick-ai/site-info' ),
			'site-summary'           => array( 'ability_id' => 'magick-ai-abilities/site-summary' ),
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
			'workflow-recipes'       => array( 'ability_id' => 'magick-ai-abilities/list-workflow-recipes' ),
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
			'site-operations-dashboard' => array( 'ability_id' => 'magick-ai/get-site-operations-dashboard' ),
			'publishing-calendar-context' => array( 'ability_id' => 'magick-ai/get-publishing-calendar-context' ),
			'media-inventory-health' => array( 'ability_id' => 'magick-ai/get-media-inventory-health' ),
			'media-inventory-fix-plan' => array( 'ability_id' => 'magick-ai/build-media-inventory-fix-plan' ),
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
			'detail_ability_id'      => 'magick-ai-abilities/wp-ops-diagnostics-detail',
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
		if ( 'media-inventory-fix-plan' === $route && array_key_exists( 'include_delete_candidates', $input ) ) {
			$input['include_delete_candidates'] = $this->boolean_input_value( $input['include_delete_candidates'] );
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
}
