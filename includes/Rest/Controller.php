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

	/**
	 * Current request log context while an ability is running.
	 *
	 * @var array<string,mixed>
	 */
	private $current_request_log_context = array();

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

		foreach ( self::read_shortcuts() as $route => $ability_id ) {
			register_rest_route(
				self::NAMESPACE,
				'/' . $route,
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => function ( WP_REST_Request $request ) use ( $ability_id ) {
							return $this->run_read_ability( $ability_id, $this->shortcut_input( $request ), $this->request_log_context( $request, $ability_id ) );
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
	 * @return bool
	 */
	public function can_use_adapter(): bool {
		return current_user_can( 'manage_options' );
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
				),
				'core_app_token_required_scopes' => array(
					'capabilities:read',
					'proposals:read',
					'proposals:create',
					'commit:preflight',
				),
				'proposal_status_routes' => array(
					'GET /proposals',
					'GET /proposals/{proposal_id}',
				),
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
				'routes'        => array(
					'connection'      => array(
						'GET /health',
						'GET /help',
						'GET /capabilities',
					),
					'read_shortcuts'  => $this->help_read_shortcuts(),
					'generic_read'    => array(
						'POST /run-read-ability',
					),
					'proposal_status' => array(
						'GET /proposals',
						'GET /proposals/{proposal_id}',
					),
					'governance'      => array(
						'POST /proposals',
						'POST /proposals/{proposal_id}/approve',
						'POST /proposals/{proposal_id}/reject',
						'POST /proposals/{proposal_id}/commit-preflight',
					),
				),
				'core_required_scopes' => array(
					'proposal_status'  => 'proposals:read',
					'proposal_create'  => 'proposals:create',
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
				),
				'core_app_token_required_scopes' => array(
					'capabilities:read',
					'proposals:read',
					'proposals:create',
					'commit:preflight',
				),
				'proposal_status_routes' => array(
					'GET /proposals',
					'GET /proposals/{proposal_id}',
				),
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
				'message'                => __( 'Approval is handled in Magick AI Core admin.', 'magick-ai-adapter' ),
				'approval_proxy_enabled' => false,
				'approval_surface'       => 'magick_ai_core_admin',
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
		$params = array(
			'ability_id' => (string) $request->get_param( 'ability_id' ),
			'title'      => (string) $request->get_param( 'title' ),
			'summary'    => (string) $request->get_param( 'summary' ),
			'input'      => $this->object_param( $request, 'input' ),
			'preview'    => $this->object_param( $request, 'preview' ),
			'caller'     => array_merge(
				array(
					'caller_type' => 'openclaw_adapter',
					'via'         => 'magick-ai-adapter',
				),
				$this->object_param( $request, 'caller' )
			),
		);

		return $this->dispatch_upstream( 'POST', '/magick-ai-core/v1/proposals', $params );
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
	 * @return WP_REST_Response|WP_Error
	 */
	private function dispatch_upstream_with_request_log_context( array $log_context, string $method, string $route, array $params = array(), bool $query_params = false ) {
		if ( empty( $log_context ) ) {
			return $this->dispatch_upstream( $method, $route, $params, $query_params );
		}

		$previous                          = $this->current_request_log_context;
		$this->current_request_log_context = $log_context;
		add_filter( 'wpai_request_log_context', array( $this, 'append_ai_request_log_context' ), 10, 3 );

		try {
			return $this->dispatch_upstream( $method, $route, $params, $query_params );
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

		foreach ( array( 'proposal_id', 'correlation_id' ) as $key ) {
			if ( isset( $this->current_request_log_context[ $key ] ) ) {
				$context[ $key ] = $this->current_request_log_context[ $key ];
			}
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
	 * @return WP_REST_Response|WP_Error
	 */
	private function dispatch_upstream( string $method, string $route, array $params = array(), bool $query_params = false ) {
		$request = new WP_REST_Request( $method, $route );
		$token   = $this->core_app_token();
		$user_id = get_current_user_id();

		if ( '' !== $token && 0 === strpos( $route, '/magick-ai-core/v1/' ) ) {
			$request->set_header( 'x-magick-ai-core-app-token', $token );
			wp_set_current_user( 0 );
		}

		if ( $query_params ) {
			$request->set_query_params( $params );
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
		return array(
			'site-info'              => 'magick-ai/site-info',
			'site-summary'           => 'magick-ai-abilities/site-summary',
			'wp-diagnostics-summary' => 'magick-ai-abilities/wp-diagnostics-summary',
			'workflow-recipes'       => 'magick-ai-abilities/list-workflow-recipes',
			'media'                  => 'magick-ai/list-media',
			'terms'                  => 'magick-ai/list-terms',
			'taxonomy-terms'         => 'magick-ai/list-taxonomy-terms',
			'categories'             => 'magick-ai/list-categories',
			'tags'                   => 'magick-ai/list-tags',
			'term'                   => 'magick-ai/get-term',
			'comments'               => 'magick-ai/list-comments',
			'internal-link-targets'   => 'magick-ai/resolve-internal-link-targets',
			'post-stats'             => 'magick-ai/get-post-stats',
			'post-revisions'         => 'magick-ai/list-revisions',
			'post-meta'              => 'magick-ai/get-post-meta',
			'pages'                  => 'magick-ai/list-pages',
			'page'                   => 'magick-ai/get-page',
			'page-structure'         => 'magick-ai/inspect-page-structure',
			'pages-tree'             => 'magick-ai/list-pages-tree',
			'content-inventory-health' => 'magick-ai/get-content-inventory-health',
			'site-operations-dashboard' => 'magick-ai/get-site-operations-dashboard',
			'publishing-calendar-context' => 'magick-ai/get-publishing-calendar-context',
			'media-inventory-health' => 'magick-ai/get-media-inventory-health',
			'taxonomy-inventory-health' => 'magick-ai/get-taxonomy-inventory-health',
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

		foreach ( array( 'proposal_id', 'correlation_id', 'external_thread_id', 'openclaw_thread_id' ) as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value && '' !== (string) $value ) {
				$context[ $key ] = $value;
			}
		}

		$context['ability_id'] = sanitize_text_field( $ability_id );
		$context['via']        = 'magick-ai-adapter';

		return $this->sanitize_log_context( $context );
	}

	/**
	 * Returns input for a GET shortcut.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>
	 */
	private function shortcut_input( WP_REST_Request $request ): array {
		$input = $this->object_param( $request, 'input' );

		foreach ( $request->get_query_params() as $key => $value ) {
			if ( in_array( $key, array( 'input', 'rest_route', '_wpnonce' ), true ) ) {
				continue;
			}

			if ( in_array( $key, array( 'log_context', 'proposal_id', 'correlation_id', 'external_thread_id', 'openclaw_thread_id' ), true ) ) {
				continue;
			}

			$input[ sanitize_key( $key ) ] = $this->sanitize_input_value( $value );
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
