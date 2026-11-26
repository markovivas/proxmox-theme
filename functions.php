<?php
/**
 * Proxmox VE Dashboard theme bootstrap.
 *
 * @package Proxmox_VE_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PVD_VERSION', '1.0.0' );
define( 'PVD_DIR', get_template_directory() );
define( 'PVD_URI', get_template_directory_uri() );
define( 'PVD_OPTION_KEY', 'pvd_settings' );

require_once PVD_DIR . '/includes/helpers.php';
require_once PVD_DIR . '/includes/proxmox-api.php';
require_once PVD_DIR . '/includes/rest-api.php';
require_once PVD_DIR . '/includes/settings.php';

function pvd_setup() {
	load_theme_textdomain( 'proxmox-ve-dashboard', PVD_DIR . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'html5', array( 'search-form', 'style', 'script' ) );
}
add_action( 'after_setup_theme', 'pvd_setup' );

function pvd_enqueue_assets() {
	wp_enqueue_style(
		'pvd-font-inter',
		'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'pvd-dashboard',
		PVD_URI . '/assets/css/dashboard.css',
		array( 'pvd-font-inter' ),
		PVD_VERSION
	);

	wp_enqueue_script(
		'pvd-dashboard',
		PVD_URI . '/assets/js/dashboard.js',
		array(),
		PVD_VERSION,
		true
	);

	$options = pvd_get_options();
	wp_localize_script(
		'pvd-dashboard',
		'pvdDashboard',
		array(
			'restUrl'         => esc_url_raw( rest_url( 'pvd/v1/' ) ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'refreshInterval' => max( 0, absint( $options['refresh_interval'] ) ) * 1000,
			'isConfigured'    => pvd_is_configured(),
			'themeUri'        => PVD_URI,
			'options'         => array(
				'server_ip'     => $options['server_ip'],
				'server_so'     => $options['server_so'],
				'server_fn'     => $options['server_fn'],
				'server_mac'    => $options['server_mac'],
				'server_url'    => $options['server_url'],
				'server_status' => $options['server_status'],
				'server_icon'   => $options['server_icon'],
				'router_ip'     => $options['router_ip'],
				'router_label'  => $options['router_label'],
				'switch_label'  => $options['switch_label'],
				'lan_network'   => $options['lan_network'],
				'brand_name'    => $options['brand_name'],
				'brand_title'   => $options['brand_title'],
				'brand_subtitle'=> $options['brand_subtitle'],
			),
			'lxc'             => pvd_get_containers(),
			'docker'          => pvd_get_docker_services(),
			'storageCustom'   => pvd_get_storages_custom(),
			'legend'          => pvd_get_legend_items(),
			'adminUrl'        => esc_url_raw( admin_url( 'admin.php?page=proxmox-ve-dashboard' ) ),
			'i18n'            => array(
				'copied'        => __( 'Copiado!', 'proxmox-ve-dashboard' ),
				'loading'       => __( 'Carregando...', 'proxmox-ve-dashboard' ),
				'notConfigured' => __( 'Configure a API do Proxmox no painel administrativo.', 'proxmox-ve-dashboard' ),
				'error'         => __( 'Erro ao carregar dados do Proxmox.', 'proxmox-ve-dashboard' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'pvd_enqueue_assets' );

function pvd_admin_assets( $hook ) {
	if ( false === strpos( (string) $hook, 'proxmox-ve-dashboard' ) ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style( 'pvd-admin', PVD_URI . '/assets/css/admin.css', array(), PVD_VERSION );
	wp_enqueue_script( 'pvd-admin', PVD_URI . '/assets/js/admin.js', array(), PVD_VERSION, true );
}
add_action( 'admin_enqueue_scripts', 'pvd_admin_assets' );

function pvd_enable_svg_upload( $mimes ) {
	$mimes['svg']  = 'image/svg+xml';
	$mimes['svgz'] = 'image/svg+xml';
	return $mimes;
}
add_filter( 'upload_mimes', 'pvd_enable_svg_upload' );

function pvd_fix_svg_mime_check( $data, $file, $filename, $mimes ) {
	$filetype = wp_check_filetype( $filename, $mimes );
	if ( in_array( $filetype['ext'], array( 'svg', 'svgz' ), true ) ) {
		$data['ext']  = $filetype['ext'];
		$data['type'] = 'image/svg+xml';
	}
	return $data;
}
add_filter( 'wp_check_filetype_and_ext', 'pvd_fix_svg_mime_check', 10, 4 );

// SSL verification is controlled by the admin setting; default false for self-signed local Proxmox.