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
				'POST /proposals/{proposal_id}/approve',
				'POST /proposals/{proposal_id}/reject',
				'POST /proposals/{proposal_id}/commit-preflight',
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
			'POST /run-read-ability' => 'Run a direct-read ability by ability_id.',
			'POST /ai-provider-log-correlation-smoke' => 'Run a provider log correlation smoke request.',
			'GET /proposals' => 'List Core proposal statuses for polling.',
			'GET /proposals/{proposal_id}' => 'Read one Core proposal status by proposal_id.',
			'POST /proposals' => 'Create a Core proposal for governed work.',
			'POST /proposals/{proposal_id}/approve' => 'Disabled stub; approvals happen in Magick AI Core admin.',
			'POST /proposals/{proposal_id}/reject' => 'Disabled stub; rejections happen in Magick AI Core admin.',
			'POST /proposals/{proposal_id}/commit-preflight' => 'Run Core commit preflight without executing final writes.',
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
				$builder = wp_ai_client_prompt( $prompt );

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

		return $this->with_ai_request_log_context(
			$log_context,
			function () use ( $method, $route, $params, $query_params ) {
				return $this->dispatch_upstream( $method, $route, $params, $query_params );
			}
		);
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

		foreach ( array( 'proposal_id', 'correlation_id', 'ability_id', 'adapter_request_id', 'adapter_route', 'ai_provider', 'ai_model', 'governance_source' ) as $key ) {
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
			'site-operations-dashboard' => array( 'ability_id' => 'magick-ai/get-site-operations-dashboard' ),
			'publishing-calendar-context' => array( 'ability_id' => 'magick-ai/get-publishing-calendar-context' ),
			'media-inventory-health' => array( 'ability_id' => 'magick-ai/get-media-inventory-health' ),
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
