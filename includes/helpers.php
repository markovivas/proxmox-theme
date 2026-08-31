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
		'host'       => '',
		'user'       => '',
		'token_id'   => '',
		'token_secret' => '',
		'refresh_interval' => 5,
		'services'   => array(),
	);

	$options = get_option( 'proxmox_dashboard_settings', array() );

	$parsed = wp_parse_args( $options, $defaults );

	// Garante que services seja sempre array de arrays válidos.
	if ( ! isset( $parsed['services'] ) || ! is_array( $parsed['services'] ) ) {
		$parsed['services'] = array();
	}

	return $parsed;
}

/**
 * Retorna serviços cadastrados já sanitizados para exibição.
 *
 * @return array
 */
function proxmox_dashboard_get_services() {
	$options  = proxmox_dashboard_get_options();
	$services = isset( $options['services'] ) && is_array( $options['services'] ) ? $options['services'] : array();
	$out      = array();
	foreach ( $services as $svc ) {
		if ( empty( $svc['name'] ) || empty( $svc['url'] ) ) {
			continue;
		}
		$out[] = array(
			'name' => sanitize_text_field( $svc['name'] ),
			'url'  => esc_url_raw( $svc['url'] ),
			'icon' => isset( $svc['icon'] ) ? sanitize_text_field( $svc['icon'] ) : '',
		);
	}
	return $out;
}

/**
 * Verifica se o tema já foi configurado (host e token preenchidos).
 *
 * @return bool
 */
function proxmox_dashboard_is_configured() {
	$options = proxmox_dashboard_get_options();

	return (
		! empty( $options['host'] ) &&
		! empty( $options['user'] ) &&
		! empty( $options['token_id'] ) &&
		! empty( $options['token_secret'] )
	);
}
