<?php
/**
 * Proxmox Dashboard
 *
 * @package Proxmox_Dashboard
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PROXMOX_DASHBOARD_VERSION', '2.4.0' );
define( 'PROXMOX_DASHBOARD_DIR', get_template_directory() );
define( 'PROXMOX_DASHBOARD_URL', get_template_directory_uri() );

require_once PROXMOX_DASHBOARD_DIR . '/includes/helpers.php';
require_once PROXMOX_DASHBOARD_DIR . '/includes/proxmox-api.php';
require_once PROXMOX_DASHBOARD_DIR . '/includes/rest-api.php';
require_once PROXMOX_DASHBOARD_DIR . '/includes/settings.php';

/**
 * Configura o tema.
 */
function proxmox_dashboard_setup() {
	load_theme_textdomain( 'proxmox-dashboard', PROXMOX_DASHBOARD_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );

	// Sem barra lateral / widgets. Tema full-screen.
	register_nav_menus( array() );
}
add_action( 'after_setup_theme', 'proxmox_dashboard_setup' );

/**
 * Enfileira estilos e scripts do frontend.
 */
function proxmox_dashboard_enqueue_assets() {
	// Dashicons são usados nos botões da barra de ações no frontend.
	wp_enqueue_style( 'dashicons' );

	wp_enqueue_style(
		'proxmox-dashboard-font-inter',
		'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'proxmox-dashboard',
		PROXMOX_DASHBOARD_URL . '/style.css',
		array( 'proxmox-dashboard-font-inter' ),
		PROXMOX_DASHBOARD_VERSION
	);

	wp_enqueue_style(
		'proxmox-dashboard-dashboard',
		PROXMOX_DASHBOARD_URL . '/assets/css/dashboard.css',
		array( 'proxmox-dashboard-font-inter' ),
		PROXMOX_DASHBOARD_VERSION
	);

	wp_enqueue_style(
		'proxmox-dashboard-fa',
		'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
		array(),
		'6.5.2'
	);

	wp_enqueue_script(
		'proxmox-dashboard-dashboard',
		PROXMOX_DASHBOARD_URL . '/assets/js/dashboard.js',
		array(),
		PROXMOX_DASHBOARD_VERSION,
		true
	);

	$pxd_opts = function_exists( 'proxmox_dashboard_get_options' ) ? proxmox_dashboard_get_options() : array( 'refresh_interval' => 5 );
	$pxd_interval = isset( $pxd_opts['refresh_interval'] ) ? absint( $pxd_opts['refresh_interval'] ) : 5;
	wp_localize_script(
		'proxmox-dashboard-dashboard',
		'proxmoxDashboard',
		array(
			'restUrl'  => esc_url_raw( rest_url( 'proxmox/v1/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'interval' => $pxd_interval * 1000,
			'i18n'     => array(
				'connected'     => __( 'Proxmox conectado', 'proxmox-dashboard' ),
				'notConfigured' => __( 'Dashboard não configurado. Acesse Proxmox Dashboard → Configurações.', 'proxmox-dashboard' ),
				'error'         => __( 'Erro ao carregar dados', 'proxmox-dashboard' ),
				'copied'        => __( 'Copiado para a área de transferência!', 'proxmox-dashboard' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'proxmox_dashboard_enqueue_assets' );

/**
 * Enfileira scripts e mídia no admin (upload de imagens).
 */
function proxmox_dashboard_admin_assets( $hook ) {
	if ( false === strpos( $hook, 'proxmox-dashboard' ) ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style(
		'proxmox-dashboard-fa',
		'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
		array(),
		'6.5.2'
	);
}
add_action( 'admin_enqueue_scripts', 'proxmox_dashboard_admin_assets' );

/**
 * Favicon fallback caso o usuário não tenha definido um Site Icon no Customizer.
 * Usa os arquivos de /img/ (Proxmox-Logo.svg/.png) quando has_site_icon() for falso.
 * Também definido diretamente em header.php para compatibilidade com cache.
 */
function proxmox_dashboard_favicon_fallback() {
	if ( has_site_icon() ) {
		return;
	}
	$base = PROXMOX_DASHBOARD_URL . '/img/Proxmox-Logo';
	echo '<link rel="icon" type="image/svg+xml" href="' . esc_url( $base . '.svg' ) . '">' . "\n";
	echo '<link rel="icon" type="image/png" sizes="32x32" href="' . esc_url( $base . '.png' ) . '">' . "\n";
}
// Fallback via wp_head caso header.php seja sobrescrito por child theme.
add_action( 'wp_head', 'proxmox_dashboard_favicon_fallback', 5 );

/**
 * Remove a barra de admin quando logado, para um visual full-screen limpo.
 * A barra de admin só fica visível para usuários logados.
 */
function proxmox_dashboard_remove_admin_bar() {
	if ( current_user_can( 'administrator' ) && is_user_logged_in() ) {
		add_filter( 'show_admin_bar', '__return_false' );
	}
}
add_action( 'init', 'proxmox_dashboard_remove_admin_bar' );
add_filter( 'proxmox_dashboard_sslverify', '__return_false' );

/**
 * Permite o upload de arquivos SVG (image/svg+xml) na Biblioteca de Mídia.
 *
 * ATENÇÃO: Arquivos SVG podem conter scripts embutidos (XSS). Este filtro
 * habilita o upload apenas para usuários com a capacidade de 'upload_files'
 * (editores/administradores). Para maior segurança, considere sanitizar o
 * conteúdo SVG após o upload.
 *
 * @param array $mimes Lista de tipos MIME permitidos.
 * @return array
 */
function proxmox_dashboard_enable_svg_upload( $mimes ) {
	$mimes['svg']  = 'image/svg+xml';
	$mimes['svgz'] = 'image/svg+xml';
	return $mimes;
}
add_filter( 'upload_mimes', 'proxmox_dashboard_enable_svg_upload' );

/**
 * Corrige a verificação de tipo de arquivo para imagens SVG.
 *
 * O WordPress pode falhar ao identificar SVGs como imagens válidas durante o
 * upload. Este filtro garante que arquivos .svg sejam reconhecidos corretamente.
 *
 * @param array  $data Dados do arquivo verificado.
 * @param string $file Caminho absoluto do arquivo.
 * @param string $filename Nome do arquivo.
 * @param array  $mimes Tipos MIME permitidos.
 * @return array
 */
function proxmox_dashboard_fix_svg_mime_check( $data, $file, $filename, $mimes ) {
	if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) {
		return $data;
	}

	$filetype = wp_check_filetype( $filename, $mimes );

	if ( in_array( $filetype['ext'], array( 'svg', 'svgz' ), true ) ) {
		$data['ext']  = $filetype['ext'];
		$data['type'] = 'image/svg+xml';
	}

	return $data;
}
add_filter( 'wp_check_filetype_and_ext', 'proxmox_dashboard_fix_svg_mime_check', 10, 4 );
