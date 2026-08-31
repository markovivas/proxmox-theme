<?php
/**
 * Endpoints REST do tema Proxmox Dashboard.
 *
 * @package Proxmox_Dashboard
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra as rotas REST do tema.
 */
function proxmox_dashboard_register_routes() {
	register_rest_route(
		'proxmox/v1',
		'/status',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'proxmox_dashboard_rest_status',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'proxmox/v1',
		'/nodes',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'proxmox_dashboard_rest_nodes',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'proxmox/v1',
		'/resources',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'proxmox_dashboard_rest_resources',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'proxmox/v1',
		'/vms',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'proxmox_dashboard_rest_vms',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'proxmox/v1',
		'/lxc',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'proxmox_dashboard_rest_lxc',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'proxmox/v1',
		'/storage',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'proxmox_dashboard_rest_storage',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'proxmox/v1',
		'/services',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'proxmox_dashboard_rest_services',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'proxmox_dashboard_register_routes' );

/**
 * Callback: status geral.
 *
 * @return WP_REST_Response|WP_Error
 */
function proxmox_dashboard_rest_status() {
	$api    = Proxmox_API::instance();
	$status = $api->get_status();

	if ( is_wp_error( $status ) ) {
		return proxmox_dashboard_rest_error( $status );
	}

	return rest_ensure_response( $status );
}

/**
 * Callback: nodes.
 *
 * @return WP_REST_Response|WP_Error
 */
function proxmox_dashboard_rest_nodes() {
	$api   = Proxmox_API::instance();
	$nodes = $api->get_nodes();

	if ( is_wp_error( $nodes ) ) {
		return proxmox_dashboard_rest_error( $nodes );
	}

	return rest_ensure_response( $nodes );
}

/**
 * Callback: recursos (VMs + LXC).
 *
 * @return WP_REST_Response|WP_Error
 */
function proxmox_dashboard_rest_resources() {
	$api       = Proxmox_API::instance();
	$resources = $api->get_resources();

	if ( is_wp_error( $resources ) ) {
		return proxmox_dashboard_rest_error( $resources );
	}

	return rest_ensure_response( $resources );
}

/**
 * Callback: VMs.
 *
 * @return WP_REST_Response|WP_Error
 */
function proxmox_dashboard_rest_vms() {
	$api = Proxmox_API::instance();
	$vms = $api->get_vms();

	if ( is_wp_error( $vms ) ) {
		return proxmox_dashboard_rest_error( $vms );
	}

	return rest_ensure_response( $vms );
}

/**
 * Callback: containers LXC.
 *
 * @return WP_REST_Response|WP_Error
 */
function proxmox_dashboard_rest_lxc() {
	$api = Proxmox_API::instance();
	$lxc = $api->get_lxc();

	if ( is_wp_error( $lxc ) ) {
		return proxmox_dashboard_rest_error( $lxc );
	}

	return rest_ensure_response( $lxc );
}

/**
 * Callback: storages.
 *
 * @return WP_REST_Response|WP_Error
 */
function proxmox_dashboard_rest_storage() {
	$api     = Proxmox_API::instance();
	$storage = $api->get_storage();

	if ( is_wp_error( $storage ) ) {
		return proxmox_dashboard_rest_error( $storage );
	}

	return rest_ensure_response( $storage );
}

/**
 * Callback: serviços monitorados (health-check HTTP server-side).
 *
 * @return WP_REST_Response
 */
function proxmox_dashboard_rest_services() {
	$services = function_exists( 'proxmox_dashboard_get_services' ) ? proxmox_dashboard_get_services() : array();

	if ( empty( $services ) ) {
		return rest_ensure_response( array() );
	}

	$results = array();

	foreach ( $services as $idx => $svc ) {
		$url  = isset( $svc['url'] ) ? $svc['url'] : '';
		$name = isset( $svc['name'] ) ? $svc['name'] : '';
		$icon = isset( $svc['icon'] ) ? $svc['icon'] : '';

		if ( empty( $url ) ) {
			continue;
		}

		$start = microtime( true );

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 5,
				'redirection' => 2,
				'headers'     => array( 'Accept' => 'text/html,application/json,*/*' ),
				'sslverify'   => false,
				'user-agent'  => 'Proxmox-Dashboard/2.1 (+health-check)',
			)
		);

		$elapsed_ms = round( ( microtime( true ) - $start ) * 1000 );

		if ( is_wp_error( $response ) ) {
			$results[] = array(
				'id'         => $idx,
				'name'       => $name,
				'url'        => $url,
				'icon'       => $icon,
				'status'     => 'offline',
				'code'       => 0,
				'latency_ms' => $elapsed_ms,
				'error'      => $response->get_error_message(),
			);
			continue;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		// Considera online 2xx e 3xx; 401/403 pode ser online mas com auth.
		$is_online = ( $code >= 200 && $code < 400 );

		$results[] = array(
			'id'         => $idx,
			'name'       => $name,
			'url'        => $url,
			'icon'       => $icon,
			'status'     => $is_online ? 'online' : 'offline',
			'code'       => $code,
			'latency_ms' => $elapsed_ms,
			'error'      => $is_online ? '' : sprintf( 'HTTP %d', $code ),
		);
	}

	return rest_ensure_response( $results );
}

/**
 * Converte um WP_Error em resposta REST padronizada.
 *
 * @param WP_Error $error Erro a ser convertido.
 * @return WP_Error
 */
function proxmox_dashboard_rest_error( WP_Error $error ) {
	$code = (string) $error->get_error_code();

	// Trata códigos HTTP conhecidos.
	$http_codes = array(
		400 => 400,
		401 => 401,
		403 => 403,
		404 => 404,
		500 => 500,
		502 => 502,
		503 => 503,
	);

	$status = isset( $http_codes[ $code ] ) ? $http_codes[ $code ] : 500;

	return new WP_Error(
		$error->get_error_code(),
		$error->get_error_message(),
		array( 'status' => $status )
	);
}
