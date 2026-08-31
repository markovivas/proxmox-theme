<?php
/**
 * Template padrão do tema Proxmox Dashboard.
 *
 * Como o tema é full-screen, o template padrão apenas chama o dashboard
 * (que vive em front-page.php). Este arquivo serve de fallback para
 * páginas sem template específico.
 *
 * @package Proxmox_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
endif;

get_footer();
