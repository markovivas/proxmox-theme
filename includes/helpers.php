<?php
/**
 * Helper functions and option defaults.
 *
 * @package Proxmox_VE_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pvd_defaults() {
	return array(
		'host'             => '',
		'user'             => '',
		'token_id'         => '',
		'token_secret'     => '',
		'refresh_interval' => 10,
		'sslverify'        => false,

		'brand_name'       => 'PROXMOX',
		'brand_title'      => 'MAPA DA INFRAESTRUTURA',
		'brand_subtitle'   => 'AMBIENTE HOMELAB',
		'footer_text'      => 'Mapa da infraestrutura • Homelab',

		'server_ip'        => '192.168.2.50',
		'server_so'        => 'Proxmox VE 9.2.20',
		'server_fn'        => 'Virtualização',
		'server_mac'       => 'f0:4d:a2:e6:64:5e',
		'server_url'       => 'proxmox.marcovivas.online',
		'server_status'    => "AMBIENTE ESTÁVEL\ne CENTRALIZADO\nPARA TODOS\nOS SERVIÇOS",
		'server_icon'      => '',

		'router_ip'        => '192.168.2.1',
		'router_label'     => 'ROTEADOR',
		'switch_label'     => 'SWITCH',
		'lan_network'      => '192.168.2.0/24',

		'containers'       => array(),
		'docker_services'  => array(),

		'storages_custom'  => array(),
		'legend_items'     => array(
			array( 'tag' => 'LXC', 'color' => 'lxc', 'label' => 'Linux Container' ),
			array( 'tag' => 'IP', 'color' => 'ip', 'label' => 'Endereço IP na rede local' ),
			array( 'tag' => 'URL', 'color' => 'url', 'label' => 'Acesso via domínio (externo)' ),
			array( 'tag' => 'VM', 'color' => 'vm', 'label' => 'Máquina Virtual' ),
			array( 'tag' => 'MAC', 'color' => 'mac', 'label' => 'Endereço MAC do Container' ),
			array( 'tag' => 'Porta', 'color' => 'port', 'label' => 'Porta do serviço no host' ),
		),
	);
}

function pvd_get_options() {
	$defaults = pvd_defaults();
	$saved    = get_option( PVD_OPTION_KEY, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	$options = wp_parse_args( $saved, $defaults );

	foreach ( array( 'containers', 'docker_services', 'storages_custom', 'legend_items' ) as $key ) {
		if ( ! isset( $options[ $key ] ) || ! is_array( $options[ $key ] ) ) {
			$options[ $key ] = $defaults[ $key ];
		}
	}
	// Keep saved empty arrays as intentional "no items configured".
	if ( ! array_key_exists( 'legend_items', $saved ) ) {
		$options['legend_items'] = $defaults['legend_items'];
	}

	$options['refresh_interval'] = max( 0, absint( $options['refresh_interval'] ) );
	$options['sslverify']        = ! empty( $options['sslverify'] );

	return $options;
}

function pvd_get_option( $key, $default = null ) {
	$options = pvd_get_options();
	if ( array_key_exists( $key, $options ) ) {
		return $options[ $key ];
	}
	return $default;
}

function pvd_is_configured() {
	$o = pvd_get_options();
	$token_id = trim( (string) $o['token_id'] );
	$user     = trim( (string) $o['user'] );
	$has_token = ( false !== strpos( $token_id, '!' ) ) || ( '' !== $token_id && '' !== $user );
	return ! empty( $o['host'] ) && $has_token && ! empty( $o['token_secret'] );
}

function pvd_get_containers() {
	$items = pvd_get_option( 'containers', array() );
	$out   = array();
	foreach ( $items as $c ) {
		if ( ! is_array( $c ) ) {
			continue;
		}
		$out[] = array(
			'id'   => sanitize_text_field( $c['id'] ?? '' ),
			'name' => sanitize_text_field( $c['name'] ?? '' ),
			'icon' => esc_url_raw( $c['icon'] ?? '' ),
			'ip'   => sanitize_text_field( $c['ip'] ?? '' ),
			'mac'  => sanitize_text_field( $c['mac'] ?? '' ),
			'url'  => sanitize_text_field( $c['url'] ?? '' ),
			'fn'   => sanitize_text_field( $c['fn'] ?? '' ),
		);
	}
	return $out;
}

function pvd_get_docker_services() {
	$items = pvd_get_option( 'docker_services', array() );
	$out   = array();
	foreach ( $items as $d ) {
		if ( ! is_array( $d ) ) {
			continue;
		}
		$out[] = array(
			'name'   => sanitize_text_field( $d['name'] ?? '' ),
			'icon'   => esc_url_raw( $d['icon'] ?? '' ),
			'port'   => sanitize_text_field( $d['port'] ?? '' ),
			'domain' => sanitize_text_field( $d['domain'] ?? '' ),
		);
	}
	return $out;
}

function pvd_get_storages_custom() {
	$items = pvd_get_option( 'storages_custom', array() );
	$out   = array();
	foreach ( $items as $s ) {
		if ( ! is_array( $s ) ) {
			continue;
		}
		$out[] = array(
			'drive' => sanitize_text_field( $s['drive'] ?? 'SSD' ),
			'title' => sanitize_text_field( $s['title'] ?? '' ),
			'items' => sanitize_textarea_field( $s['items'] ?? '' ),
		);
	}
	return $out;
}

function pvd_get_legend_items() {
	$items = pvd_get_option( 'legend_items', array() );
	$out   = array();
	foreach ( $items as $l ) {
		if ( ! is_array( $l ) ) {
			continue;
		}
		$out[] = array(
			'tag'   => sanitize_text_field( $l['tag'] ?? '' ),
			'color' => sanitize_key( $l['color'] ?? 'lxc' ),
			'label' => sanitize_text_field( $l['label'] ?? '' ),
		);
	}
	return $out;
}

function pvd_format_bytes( $bytes, $precision = 1 ) {
	$bytes = max( 0, (float) $bytes );
	$units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB' );
	$pow   = $bytes > 0 ? floor( log( $bytes, 1024 ) ) : 0;
	$pow   = min( $pow, count( $units ) - 1 );
	$value = $bytes / pow( 1024, $pow );
	return round( $value, $pow > 0 ? $precision : 0 ) . ' ' . $units[ $pow ];
}

function pvd_status_label( $status ) {
	$map = array(
		'running' => 'Ativo',
		'stopped' => 'Parado',
		'paused'  => 'Pausado',
		'online'  => 'Online',
		'offline' => 'Offline',
	);
	$status = strtolower( (string) $status );
	return $map[ $status ] ?? ucfirst( $status );
}