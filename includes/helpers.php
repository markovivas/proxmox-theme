<?php
/**
 * Funções auxiliares do tema Proxmox Dashboard.
 *
 * @package Proxmox_Dashboard
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formata uma quantidade de bytes em um formato legível.
 *
 * @param int|float $bytes Valor em bytes.
 * @param int       $precision Casas decimais.
 * @return string
 */
function proxmox_dashboard_format_bytes( $bytes, $precision = 1 ) {
	if ( ! is_numeric( $bytes ) || $bytes < 0 ) {
		return '0 B';
	}

	$units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB' );
	$power = $bytes > 0 ? floor( log( $bytes, 1024 ) ) : 0;

	if ( $power >= count( $units ) ) {
		$power = count( $units ) - 1;
	}

	$value = $bytes / pow( 1024, $power );

	return number_format_i18n( $value, ( $power > 0 ? $precision : 0 ) ) . ' ' . $units[ $power ];
}

/**
 * Formata um uptime (em segundos) em uma string legível (dias, horas, minutos).
 *
 * @param int $seconds Segundos de uptime.
 * @return string
 */
function proxmox_dashboard_format_uptime( $seconds ) {
	$seconds = (int) $seconds;

	if ( $seconds < 0 ) {
		return '--';
	}

	$days    = floor( $seconds / 86400 );
	$hours   = floor( ( $seconds % 86400 ) / 3600 );
	$minutes = floor( ( $seconds % 3600 ) / 60 );

	if ( $days > 0 ) {
		return sprintf(
			/* translators: %1$d dias, %2$d horas, %3$d minutos. */
			__( '%1$dd %2$dh %3$dm', 'proxmox-dashboard' ),
			$days,
			$hours,
			$minutes
		);
	}

	if ( $hours > 0 ) {
		return sprintf(
			/* translators: %1$d horas, %2$d minutos. */
			__( '%1$dh %2$dm', 'proxmox-dashboard' ),
			$hours,
			$minutes
		);
	}

	return sprintf(
		/* translators: %d minutos. */
		__( '%dmin', 'proxmox-dashboard' ),
		$minutes
	);
}

/**
 * Retorna o texto de status traduzido para status conhecidos do Proxmox.
 *
 * @param string $status Status do Proxmox (running, stopped, etc).
 * @return string
 */
function proxmox_dashboard_status_label( $status ) {
	$map = array(
		'running' => __( 'Ativo', 'proxmox-dashboard' ),
		'stopped' => __( 'Parado', 'proxmox-dashboard' ),
		'paused'  => __( 'Pausado', 'proxmox-dashboard' ),
		'suspended' => __( 'Suspenso', 'proxmox-dashboard' ),
		'error'   => __( 'Erro', 'proxmox-dashboard' ),
	);

	return isset( $map[ $status ] ) ? $map[ $status ] : $status;
}

/**
 * Normaliza os dados das opções do tema.
 *
 * @return array
 */
function proxmox_dashboard_get_options() {
	$defaults = array(
		'host'             => '',
		'user'             => '',
		'token_id'         => '',
		'token_secret'     => '',
		'refresh_interval' => 5,

		// Topologia & Servidor
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

		// Containers (LXC) & VMs
		'containers'       => array(),

		// Serviços Docker
		'docker_services'  => array(),

		// Armazenamento
		'storages_custom'  => array(),

		// Legenda
		'legend_items'     => array(
			array( 'tag' => 'LXC',   'color' => 'lxc',    'label' => __( 'Linux Container', 'proxmox-dashboard' ) ),
			array( 'tag' => 'IP',    'color' => 'ip',     'label' => __( 'Endereço IP na rede local', 'proxmox-dashboard' ) ),
			array( 'tag' => 'URL',   'color' => 'url',    'label' => __( 'Acesso via domínio (externo)', 'proxmox-dashboard' ) ),
			array( 'tag' => 'VM',    'color' => 'vm',     'label' => __( 'Máquina Virtual', 'proxmox-dashboard' ) ),
			array( 'tag' => 'MAC',   'color' => 'mac',    'label' => __( 'Endereço MAC do Container', 'proxmox-dashboard' ) ),
			array( 'tag' => 'Porta', 'color' => 'port',   'label' => __( 'Porta do serviço no host', 'proxmox-dashboard' ) ),
		),

		// Serviços monitorados manuais (compatibilidade)
		'services'         => array(),
	);

	$options = get_option( 'proxmox_dashboard_settings', array() );

	$parsed = wp_parse_args( $options, $defaults );

	if ( ! isset( $parsed['containers'] ) || ! is_array( $parsed['containers'] ) ) {
		$parsed['containers'] = array();
	}
	if ( ! isset( $parsed['docker_services'] ) || ! is_array( $parsed['docker_services'] ) ) {
		$parsed['docker_services'] = array();
	}
	if ( ! isset( $parsed['storages_custom'] ) || ! is_array( $parsed['storages_custom'] ) ) {
		$parsed['storages_custom'] = array();
	}
	if ( ! isset( $parsed['legend_items'] ) || ! is_array( $parsed['legend_items'] ) ) {
		$parsed['legend_items'] = $defaults['legend_items'];
	}
	if ( ! isset( $parsed['services'] ) || ! is_array( $parsed['services'] ) ) {
		$parsed['services'] = array();
	}

	return $parsed;
}

/**
 * Retorna serviços cadastrados já sanitizados para monitoramento e health-check.
 * Agrega tanto serviços manuais quanto serviços Docker que possuem URL de checagem.
 *
 * @return array
 */
function proxmox_dashboard_get_services() {
	$options = proxmox_dashboard_get_options();
	$out     = array();

	// 1) Serviços manuais (legado)
	if ( ! empty( $options['services'] ) && is_array( $options['services'] ) ) {
		foreach ( $options['services'] as $svc ) {
			if ( empty( $svc['name'] ) || empty( $svc['url'] ) ) {
				continue;
			}
			$out[] = array(
				'name' => sanitize_text_field( $svc['name'] ),
				'url'  => esc_url_raw( $svc['url'] ),
				'icon' => isset( $svc['icon'] ) ? sanitize_text_field( $svc['icon'] ) : '',
			);
		}
	}

	// 2) Serviços Docker com checagem configurada
	if ( ! empty( $options['docker_services'] ) && is_array( $options['docker_services'] ) ) {
		foreach ( $options['docker_services'] as $ds ) {
			$check_url = ! empty( $ds['check_url'] ) ? trim( $ds['check_url'] ) : '';
			if ( empty( $check_url ) && ! empty( $ds['domain'] ) ) {
				$check_url = $ds['domain'];
			}
			if ( empty( $check_url ) ) {
				continue;
			}
			if ( ! preg_match( '#^https?://#i', $check_url ) ) {
				$check_url = 'https://' . ltrim( $check_url, '/' );
			}
			$out[] = array(
				'name' => ! empty( $ds['name'] ) ? sanitize_text_field( $ds['name'] ) : 'Docker',
				'url'  => esc_url_raw( $check_url ),
				'icon' => isset( $ds['icon'] ) ? sanitize_text_field( $ds['icon'] ) : '',
			);
		}
	}

	return $out;
}

/**
 * Retorna lista de containers (LXC / VMs) cadastrados.
 *
 * @return array
 */
function proxmox_dashboard_get_containers() {
	$options = proxmox_dashboard_get_options();
	return ! empty( $options['containers'] ) && is_array( $options['containers'] ) ? $options['containers'] : array();
}

/**
 * Retorna lista de serviços Docker cadastrados.
 *
 * @return array
 */
function proxmox_dashboard_get_docker_services() {
	$options = proxmox_dashboard_get_options();
	return ! empty( $options['docker_services'] ) && is_array( $options['docker_services'] ) ? $options['docker_services'] : array();
}

/**
 * Retorna lista de storages/discos personalizados cadastrados.
 *
 * @return array
 */
function proxmox_dashboard_get_storages_custom() {
	$options = proxmox_dashboard_get_options();
	return ! empty( $options['storages_custom'] ) && is_array( $options['storages_custom'] ) ? $options['storages_custom'] : array();
}

/**
 * Retorna lista de itens da legenda.
 *
 * @return array
 */
function proxmox_dashboard_get_legend_items() {
	$options = proxmox_dashboard_get_options();
	return ! empty( $options['legend_items'] ) && is_array( $options['legend_items'] ) ? $options['legend_items'] : array();
}

/**
 * Verifica se o tema já foi configurado (host e token preenchidos).
 *
 * @return bool
 */
function proxmox_dashboard_is_configured() {
	$options = proxmox_dashboard_get_options();

	$token_id = ! empty( $options['token_id'] ) ? trim( $options['token_id'] ) : '';
	$user     = ! empty( $options['user'] ) ? trim( $options['user'] ) : '';

	$has_token = ( strpos( $token_id, '!' ) !== false ) || ( ! empty( $token_id ) && ! empty( $user ) );

	return (
		! empty( $options['host'] ) &&
		$has_token &&
		! empty( $options['token_secret'] )
	);
}
