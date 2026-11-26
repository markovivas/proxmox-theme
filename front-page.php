<?php
/**
 * Front page dashboard template.
 *
 * @package Proxmox_VE_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$options = pvd_get_options();
get_header();

$brand_name     = (string) $options['brand_name'];
$brand_parts    = preg_split( '/(X)/u', $brand_name, -1, PREG_SPLIT_DELIM_CAPTURE );
$server_icon    = ! empty( $options['server_icon'] ) ? $options['server_icon'] : PVD_URI . '/img/proxmox.svg';
$status_lines   = preg_split( "/\r\n|\r|\n/", (string) $options['server_status'] );
$status_lines   = array_values( array_filter( array_map( 'trim', $status_lines ), 'strlen' ) );
?>
	<header class="topbar">
		<div class="brand">
			<div class="proxmox-mark" aria-hidden="true">
				<span class="mark-orange"></span><span class="mark-black"></span>
			</div>
			<div>
				<div class="brand-name">
					<?php
					foreach ( $brand_parts as $part ) {
						if ( 'X' === $part ) {
							echo '<span>X</span>';
						} else {
							echo esc_html( $part );
						}
					}
					?>
				</div>
				<div class="brand-title"><?php echo esc_html( $options['brand_title'] ); ?></div>
				<div class="brand-subtitle"><?php echo esc_html( $options['brand_subtitle'] ); ?></div>
			</div>
		</div>

		<section class="server-card">
			<div class="server-icon"><img src="<?php echo esc_url( $server_icon ); ?>" alt="<?php esc_attr_e( 'Servidor', 'proxmox-ve-dashboard' ); ?>" class="server-svg" /></div>
			<div class="server-info">
				<h2><?php esc_html_e( 'Servidor Proxmox', 'proxmox-ve-dashboard' ); ?></h2>
				<p><b><?php esc_html_e( 'IP:', 'proxmox-ve-dashboard' ); ?></b> <span id="srv-ip"><?php echo esc_html( $options['server_ip'] ); ?></span></p>
				<p><b><?php esc_html_e( 'SO:', 'proxmox-ve-dashboard' ); ?></b> <span id="srv-so"><?php echo esc_html( $options['server_so'] ); ?></span></p>
				<p><b><?php esc_html_e( 'Função:', 'proxmox-ve-dashboard' ); ?></b> <span id="srv-fn"><?php echo esc_html( $options['server_fn'] ); ?></span></p>
				<p><b><?php esc_html_e( 'MAC:', 'proxmox-ve-dashboard' ); ?></b> <span id="srv-mac"><?php echo esc_html( $options['server_mac'] ); ?></span></p>
				<p><b><?php esc_html_e( 'URL:', 'proxmox-ve-dashboard' ); ?></b> <span class="link" id="srv-url"><?php echo esc_html( $options['server_url'] ); ?></span></p>
			</div>
			<div class="server-status" id="srv-status">
				<?php echo esc_html( implode( ' ', $status_lines ) ? '' : '' ); ?>
				<?php foreach ( $status_lines as $line ) : ?>
					<span><?php echo esc_html( $line ); ?></span><br>
				<?php endforeach; ?>
			</div>
		</section>
	</header>

	<section class="network-flow" aria-label="<?php esc_attr_e( 'Topologia de rede', 'proxmox-ve-dashboard' ); ?>">
		<div class="network-node">
			<div class="big-icon">◎</div><strong>INTERNET</strong>
		</div>
		<div class="flow-line"></div>
		<div class="network-node">
			<div class="big-icon">⌁</div><strong id="router-label"><?php echo esc_html( $options['router_label'] ); ?></strong><small id="router-ip"><?php echo esc_html( $options['router_ip'] ); ?></small>
		</div>
		<div class="flow-line"></div>
		<div class="network-node">
			<div class="big-icon">▦</div><strong id="switch-label"><?php echo esc_html( $options['switch_label'] ); ?></strong>
		</div>
		<div class="flow-line"></div>
		<div class="lan-badge"><b>REDE LAN</b><span id="lan-network"><?php echo esc_html( $options['lan_network'] ); ?></span></div>
	</section>

	<section class="section lxc-section" aria-labelledby="lxc-title">
		<div class="section-title blue-title"><span id="lxc-title"><?php esc_html_e( 'CONTAINERS (LXC)', 'proxmox-ve-dashboard' ); ?></span></div>
		<div id="lxcGrid" class="lxc-grid"></div>
	</section>

	<section class="section docker-section" aria-labelledby="docker-title">
		<div class="section-title orange-title">
			<span id="docker-title"><?php esc_html_e( 'SERVIÇOS DOCKER — LXC 100 (Ubuntu Server)', 'proxmox-ve-dashboard' ); ?></span>
			<small><?php esc_html_e( 'ACESSO VIA DOMÍNIO (CLOUDFLARE)', 'proxmox-ve-dashboard' ); ?></small>
		</div>
		<div id="dockerGrid" class="docker-grid"></div>
	</section>

	<?php if ( ! pvd_is_configured() ) : ?>
		<section class="section config-notice" aria-live="polite">
			<div class="section-title blue-title"><span><?php esc_html_e( 'Integração Proxmox', 'proxmox-ve-dashboard' ); ?></span></div>
			<div class="config-notice__body">
				<p><?php esc_html_e( 'A API do Proxmox ainda não está configurada. As seções dinâmicas exibirão dados quando a conexão for configurada.', 'proxmox-ve-dashboard' ); ?></p>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<a class="config-notice__btn" href="<?php echo esc_url( admin_url( 'admin.php?page=proxmox-ve-dashboard' ) ); ?>"><?php esc_html_e( 'Configurar API', 'proxmox-ve-dashboard' ); ?></a>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<section class="section metrics-section" aria-labelledby="metrics-title">
		<div class="section-title blue-title">
			<span id="metrics-title"><?php esc_html_e( 'MÉTRICAS GLOBAIS', 'proxmox-ve-dashboard' ); ?></span>
			<small id="metrics-meta"><?php esc_html_e( 'ATUALIZANDO...', 'proxmox-ve-dashboard' ); ?></small>
		</div>
		<div id="metricsGrid" class="metrics-grid">
			<div class="metric-card metric-skeleton"></div>
			<div class="metric-card metric-skeleton"></div>
			<div class="metric-card metric-skeleton"></div>
			<div class="metric-card metric-skeleton"></div>
		</div>
	</section>

	<section class="section storage-section" aria-labelledby="storage-title">
		<div class="section-title green-title">
			<span id="storage-title"><?php esc_html_e( 'ARMAZENAMENTO', 'proxmox-ve-dashboard' ); ?></span>
			<small id="storage-meta"><?php esc_html_e( 'PROXMOX VE', 'proxmox-ve-dashboard' ); ?></small>
		</div>
		<div id="storageGrid" class="storage-grid">
			<div class="storage-card storage-skeleton"></div>
			<div class="storage-card storage-skeleton"></div>
		</div>
	</section>

	<section class="section tables-section" aria-labelledby="tables-title">
		<div class="section-title purple-title">
			<span id="tables-title"><?php esc_html_e( 'TABELAS DE MONITORAMENTO', 'proxmox-ve-dashboard' ); ?></span>
			<small id="tables-meta"><?php esc_html_e( 'NODES / VMS / LXC / STORAGE', 'proxmox-ve-dashboard' ); ?></small>
		</div>
		<div class="tables-wrap">
			<div class="table-block">
				<h3 class="table-block__title"><?php esc_html_e( 'Nodes', 'proxmox-ve-dashboard' ); ?></h3>
				<div class="table-responsive"><table class="pvd-table" id="table-nodes"><thead><tr><th>Node</th><th>Status</th><th>CPU</th><th>RAM</th><th>Disco</th><th>Uptime</th></tr></thead><tbody><tr><td colspan="6" class="pvd-empty"><?php esc_html_e( 'Carregando...', 'proxmox-ve-dashboard' ); ?></td></tr></tbody></table></div>
			</div>
			<div class="table-block">
				<h3 class="table-block__title"><?php esc_html_e( 'Máquinas Virtuais (QEMU)', 'proxmox-ve-dashboard' ); ?></h3>
				<div class="table-responsive"><table class="pvd-table" id="table-vms"><thead><tr><th>VMID</th><th>Nome</th><th>Node</th><th>Status</th><th>CPU</th><th>RAM</th><th>Disco</th><th>Uptime</th></tr></thead><tbody><tr><td colspan="8" class="pvd-empty"><?php esc_html_e( 'Carregando...', 'proxmox-ve-dashboard' ); ?></td></tr></tbody></table></div>
			</div>
			<div class="table-block">
				<h3 class="table-block__title"><?php esc_html_e( 'Containers (LXC)', 'proxmox-ve-dashboard' ); ?></h3>
				<div class="table-responsive"><table class="pvd-table" id="table-lxc"><thead><tr><th>VMID</th><th>Nome</th><th>Node</th><th>Status</th><th>CPU</th><th>RAM</th><th>Disco</th><th>Uptime</th></tr></thead><tbody><tr><td colspan="8" class="pvd-empty"><?php esc_html_e( 'Carregando...', 'proxmox-ve-dashboard' ); ?></td></tr></tbody></table></div>
			</div>
			<div class="table-block">
				<h3 class="table-block__title"><?php esc_html_e( 'Storages', 'proxmox-ve-dashboard' ); ?></h3>
				<div class="table-responsive"><table class="pvd-table" id="table-storage"><thead><tr><th>Storage</th><th>Tipo</th><th>Status</th><th>Total</th><th>Usado</th><th>Livre</th><th>%</th><th>Node</th></tr></thead><tbody><tr><td colspan="8" class="pvd-empty"><?php esc_html_e( 'Carregando...', 'proxmox-ve-dashboard' ); ?></td></tr></tbody></table></div>
			</div>
		</div>
	</section>

<?php
get_footer();