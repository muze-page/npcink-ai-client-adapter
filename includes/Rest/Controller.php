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
					),
				),
			)
		);

		foreach ( $this->read_shortcuts() as $route => $ability_id ) {
			register_rest_route(
				self::NAMESPACE,
				'/' . $route,
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => function ( WP_REST_Request $request ) use ( $ability_id ): WP_REST_Response {
							return $this->run_read_ability( $ability_id, $this->request_input( $request ) );
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
				'supported_guidance'     => array(
					'read'  => array(
						'governance_mode'   => 'direct_read',
						'execution_surface' => 'wp_abilities_rest',
					),
					'write' => array(
						'governance_mode'   => 'proposal_required',
						'execution_surface' => 'adapter_after_core_preflight',
					),
				),
				'permission_capability'  => 'manage_options',
				'current_user_authorized' => $this->can_use_adapter(),
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
			$this->request_input( $request )
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
			)
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
	 * @return WP_REST_Response|WP_Error
	 */
	private function run_read_ability( string $ability_id, array $input ) {
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
		$response = $this->dispatch_upstream( 'GET', $route, array( 'input' => $input ), true );

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
				'result'            => $data,
			),
			200
		);
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

		if ( $query_params ) {
			$request->set_query_params( $params );
		} else {
			foreach ( $params as $key => $value ) {
				$request->set_param( $key, $value );
			}
		}

		$response = rest_do_request( $request );
		$status   = (int) $response->get_status();

		if ( $status < 200 || $status >= 300 ) {
			return new WP_Error(
				'magick_ai_adapter_upstream_failed',
				__( 'The upstream WordPress REST request failed.', 'magick-ai-adapter' ),
				array(
					'status'        => $status,
					'upstream_route' => $route,
					'upstream_data'  => $response->get_data(),
				)
			);
		}

		return new WP_REST_Response( $response->get_data(), $status );
	}

	/**
	 * Returns supported read shortcuts.
	 *
	 * @return array<string,string>
	 */
	private function read_shortcuts(): array {
		return array(
			'site-summary'           => 'magick-ai-abilities/site-summary',
			'wp-diagnostics-summary' => 'magick-ai-abilities/wp-diagnostics-summary',
			'workflow-recipes'       => 'magick-ai-abilities/list-workflow-recipes',
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
