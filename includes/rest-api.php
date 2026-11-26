<?php
/**
 * REST API endpoints.
 *
 * @package Proxmox_VE_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pvd_register_rest_routes() {
	$routes = array(
		'status'    => 'pvd_rest_status',
		'nodes'     => 'pvd_rest_nodes',
		'resources' => 'pvd_rest_resources',
		'vms'       => 'pvd_rest_vms',
		'lxc'       => 'pvd_rest_lxc',
		'storage'   => 'pvd_rest_storage',
	);
	foreach ( $routes as $route => $cb ) {
		register_rest_route(
			'pvd/v1',
			'/' . $route,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => $cb,
				'permission_callback' => '__return_true',
			)
		);
	}
}
add_action( 'rest_api_init', 'pvd_register_rest_routes' );

function pvd_rest_error( $error ) {
	$status = 500;
	$data   = $error->get_error_data();
	if ( is_array( $data ) && isset( $data['status'] ) ) {
		$status = (int) $data['status'];
	} elseif ( preg_match( '/_(\d{3})$/', (string) $error->get_error_code(), $m ) ) {
		$status = (int) $m[1];
	}
	return new WP_REST_Response(
		array(
			'code'    => $error->get_error_code(),
			'message' => $error->get_error_message(),
		),
		$status
	);
}

function pvd_rest_status( WP_REST_Request $request ) {
	$api = PVD_Proxmox_API::instance();
	$res = $api->get_status();
	return is_wp_error( $res ) ? pvd_rest_error( $res ) : rest_ensure_response( $res );
}

function pvd_rest_nodes( WP_REST_Request $request ) {
	$api = PVD_Proxmox_API::instance();
	$res = $api->get_nodes();
	return is_wp_error( $res ) ? pvd_rest_error( $res ) : rest_ensure_response( $res );
}

function pvd_rest_resources( WP_REST_Request $request ) {
	$api = PVD_Proxmox_API::instance();
	$res = $api->get_resources();
	return is_wp_error( $res ) ? pvd_rest_error( $res ) : rest_ensure_response( $res );
}

function pvd_rest_vms( WP_REST_Request $request ) {
	$api = PVD_Proxmox_API::instance();
	$res = $api->get_vms();
	return is_wp_error( $res ) ? pvd_rest_error( $res ) : rest_ensure_response( $res );
}

function pvd_rest_lxc( WP_REST_Request $request ) {
	$api = PVD_Proxmox_API::instance();
	$res = $api->get_lxc();
	return is_wp_error( $res ) ? pvd_rest_error( $res ) : rest_ensure_response( $res );
}

function pvd_rest_storage( WP_REST_Request $request ) {
	$api = PVD_Proxmox_API::instance();
	$res = $api->get_storage();
	return is_wp_error( $res ) ? pvd_rest_error( $res ) : rest_ensure_response( $res );
}