<?php
/**
 * Header do tema Proxmox Dashboard.
 *
 * @package Proxmox_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#070a0f">
	<meta name="color-scheme" content="dark">
	<?php if ( ! has_site_icon() ) : ?>
	<link rel="icon" type="image/svg+xml" href="<?php echo esc_url( get_template_directory_uri() . '/img/Proxmox-Logo.svg' ); ?>">
	<link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url( get_template_directory_uri() . '/img/Proxmox-Logo.png' ); ?>">
	<link rel="apple-touch-icon" href="<?php echo esc_url( get_template_directory_uri() . '/img/Proxmox-Logo.png' ); ?>">
	<?php endif; ?>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div id="proxmox-dashboard-page" class="pxd-page">
