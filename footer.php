<?php
/**
 * Footer template.
 *
 * @package Proxmox_VE_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
	<footer class="site-footer">
		<span><?php echo esc_html( pvd_get_option( 'footer_text', 'Mapa da infraestrutura • Homelab' ) ); ?></span>
		<span id="clock"></span>
	</footer>
</main>
<?php wp_footer(); ?>
</body>
</html>