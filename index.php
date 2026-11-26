<?php
/**
 * Fallback template.
 *
 * @package Proxmox_VE_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_safe_redirect( home_url( '/' ), 302 );
exit;