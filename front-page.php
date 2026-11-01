<?php
/**
 * Front page do tema Proxmox Dashboard - Homelab & Mapa da Infraestrutura Reformulado.
 *
 * @package Proxmox_Dashboard
 * @since   2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$options          = proxmox_dashboard_get_options();
$is_configured    = proxmox_dashboard_is_configured();
$admin_url        = admin_url( 'admin.php?page=proxmox-dashboard' );
$containers       = proxmox_dashboard_get_containers();
$docker_services  = proxmox_dashboard_get_docker_services();
$storages_custom  = proxmox_dashboard_get_storages_custom();
$legend_items     = proxmox_dashboard_get_legend_items();
?>
<main class="page">

	<!-- Topbar & Identidade Visual Proxmox -->
	<header class="topbar">
		<div class="brand">
			<div class="proxmox-mark" aria-hidden="true">
				<span class="mark-orange"></span><span class="mark-black"></span>
			</div>
			<div>
				<div class="brand-name">PRO<span>X</span>MOX</div>
				<div class="brand-title"><?php esc_html_e( 'MAPA DA INFRAESTRUTURA', 'proxmox-dashboard' ); ?></div>
				<div class="brand-subtitle"><?php esc_html_e( 'AMBIENTE HOMELAB', 'proxmox-dashboard' ); ?></div>
			</div>
		</div>

		<section class="server-card">
			<div class="server-icon">
				<?php if ( ! empty( $options['server_icon'] ) ) : ?>
					<img src="<?php echo esc_url( $options['server_icon'] ); ?>" alt="Proxmox VE" class="server-svg" />
				<?php else : ?>
					<img src="<?php echo esc_url( get_template_directory_uri() . '/img/Proxmox-Logo.svg' ); ?>" alt="Proxmox VE" class="server-svg" />
				<?php endif; ?>
			</div>
			<div class="server-info">
				<h2><?php esc_html_e( 'Servidor Proxmox', 'proxmox-dashboard' ); ?></h2>
				<p><b><?php esc_html_e( 'IP:', 'proxmox-dashboard' ); ?></b> <span id="pxd-server-ip"><?php echo esc_html( $options['server_ip'] ?: '192.168.2.50' ); ?></span></p>
				<p><b><?php esc_html_e( 'SO:', 'proxmox-dashboard' ); ?></b> <span id="pxd-server-so"><?php echo esc_html( $options['server_so'] ?: 'Proxmox VE' ); ?></span></p>
				<p><b><?php esc_html_e( 'Função:', 'proxmox-dashboard' ); ?></b> <?php echo esc_html( $options['server_fn'] ?: 'Virtualização' ); ?></p>
				<p><b><?php esc_html_e( 'MAC:', 'proxmox-dashboard' ); ?></b> <?php echo esc_html( $options['server_mac'] ?: '—' ); ?></p>
				<p><b><?php esc_html_e( 'URL:', 'proxmox-dashboard' ); ?></b> 
					<?php if ( ! empty( $options['server_url'] ) ) : 
						$clean_server_url = preg_replace( '#^https?://#i', '', trim( $options['server_url'] ) );
					?>
						<a href="<?php echo esc_url( 'https://' . $clean_server_url ); ?>" target="_blank" rel="noopener" class="link"><?php echo esc_html( $clean_server_url ); ?></a>
					<?php else : ?>
						<span class="link">—</span>
					<?php endif; ?>
				</p>
			</div>
			<div class="server-status">
				<div class="server-status-text">
					<?php echo nl2br( esc_html( $options['server_status'] ?: "AMBIENTE ESTÁVEL\ne CENTRALIZADO\nPARA TODOS\nOS SERVIÇOS" ) ); ?>
				</div>
				<div class="server-live-dot" id="pxd-live-status-wrap">
					<span class="pxd-dot" id="pxd-status-dot"></span>
					<span id="pxd-status-text"><?php echo $is_configured ? esc_html__( 'Conectando…', 'proxmox-dashboard' ) : esc_html__( 'Não configurado', 'proxmox-dashboard' ); ?></span>
					<span class="pxd-version" id="pxd-version"></span>
				</div>
			</div>
		</section>
	</header>

	<!-- Barra de Controles Rápidos -->
	<div class="pxd-action-bar">
		<div class="pxd-action-bar__left">
			<button type="button" id="pxd-view-toggle" class="pxd-pill-btn is-active" data-view="mapa">
				<span class="dashicons dashicons-networking"></span> <?php esc_html_e( 'Mapa da Infraestrutura', 'proxmox-dashboard' ); ?>
			</button>
			<button type="button" id="pxd-tables-toggle" class="pxd-pill-btn" data-view="tabelas">
				<span class="dashicons dashicons-chart-area"></span> <?php esc_html_e( 'Métricas & Tabelas', 'proxmox-dashboard' ); ?>
			</button>
		</div>
		<div class="pxd-action-bar__right">
			<span class="pxd-last-update" id="pxd-last-update"></span>
			<button type="button" id="pxd-kiosk" class="pxd-pill-btn pxd-pill-btn--icon" title="<?php esc_attr_e( 'Modo TV (sem rolagem)', 'proxmox-dashboard' ); ?>">
				<svg class="pxd-icon--kiosk-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6v6H9z"/><path d="M9 3v3M15 3v3M9 18v3M15 18v3"/></svg>
				<svg class="pxd-icon--kiosk-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 8l8 8"/><path d="M16 8l-8 8"/></svg>
			</button>
			<button type="button" id="pxd-fullscreen" class="pxd-pill-btn pxd-pill-btn--icon" title="<?php esc_attr_e( 'Tela Cheia (F11)', 'proxmox-dashboard' ); ?>">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M16 3h3a2 2 0 0 1 2 2v3"/><path d="M8 21H5a2 2 0 0 1-2-2v-3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
			</button>
			<button type="button" id="pxd-refresh" class="pxd-pill-btn pxd-pill-btn--icon" title="<?php esc_attr_e( 'Atualizar Agora', 'proxmox-dashboard' ); ?>">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
			</button>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<a href="<?php echo esc_url( $admin_url ); ?>" class="pxd-pill-btn pxd-pill-btn--admin" title="<?php esc_attr_e( 'Configurações no Admin', 'proxmox-dashboard' ); ?>">
					<span class="dashicons dashicons-admin-generic" style="font-size:15px;width:15px;height:15px"></span> <?php esc_html_e( 'Editar', 'proxmox-dashboard' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<!-- Topologia da Rede (Diagrama de Fluxo) -->
	<section class="network-flow" aria-label="<?php esc_attr_e( 'Topologia da Rede', 'proxmox-dashboard' ); ?>">
		<div class="network-node">
			<div class="big-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:28px;height:28px"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
			</div><strong><?php esc_html_e( 'Internet', 'proxmox-dashboard' ); ?></strong>
		</div>
		<div class="flow-line"></div>
		<div class="network-node">
			<div class="big-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:28px;height:28px"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M6 8h.01M10 8h.01M6 12h.01M10 12h.01M6 16h.01M10 16h.01"/><path d="M14 8h4M14 12h4M14 16h4"/></svg>
			</div><strong><?php echo esc_html( $options['router_label'] ?: 'Roteador' ); ?></strong><small><?php echo esc_html( $options['router_ip'] ?: '192.168.2.1' ); ?></small>
		</div>
		<div class="flow-line"></div>
		<div class="network-node">
			<div class="big-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:28px;height:28px"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18M15 3v18M3 9h18M3 15h18"/></svg>
			</div><strong><?php echo esc_html( $options['switch_label'] ?: 'Switch' ); ?></strong>
		</div>
		<div class="flow-line"></div>
		<div class="lan-badge"><b><?php esc_html_e( 'Rede LAN', 'proxmox-dashboard' ); ?></b><span><?php echo esc_html( $options['lan_network'] ?: '192.168.2.0/24' ); ?></span></div>
	</section>

	<!-- Métricas Globais em Tempo Real (Gauges Proxmox API) -->
	<section class="pxd-global-metrics">
		<div class="pxd-metrics-summary">
			<div class="pxd-metric-box">
				<span class="pxd-metric-label"><?php esc_html_e( 'Nodes', 'proxmox-dashboard' ); ?></span>
				<strong class="pxd-metric-val" id="pxd-sum-nodes">–</strong>
			</div>
			<div class="pxd-metric-box">
				<span class="pxd-metric-label"><?php esc_html_e( 'VMs Ativas', 'proxmox-dashboard' ); ?></span>
				<strong class="pxd-metric-val" id="pxd-sum-vms">–</strong>
			</div>
			<div class="pxd-metric-box">
				<span class="pxd-metric-label"><?php esc_html_e( 'LXC Ativos', 'proxmox-dashboard' ); ?></span>
				<strong class="pxd-metric-val" id="pxd-sum-lxc">–</strong>
			</div>
			<div class="pxd-metric-box">
				<span class="pxd-metric-label"><?php esc_html_e( 'Storages', 'proxmox-dashboard' ); ?></span>
				<strong class="pxd-metric-val" id="pxd-sum-storage">–</strong>
			</div>
		</div>

		<div class="pxd-gauges-grid">
			<div class="pxd-gauge-card">
				<div class="pxd-gauge-head">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6v6H9z"/></svg>
					<span><?php esc_html_e( 'CPU Cluster', 'proxmox-dashboard' ); ?></span>
				</div>
				<div class="pxd-gauge" id="pxd-gauge-cpu"><span>–</span></div>
				<div class="pxd-bar"><div class="pxd-bar__fill" id="pxd-bar-cpu" style="width:0%"></div></div>
				<div class="pxd-gauge-detail" id="pxd-cpu-detail"><?php esc_html_e( 'pico do cluster', 'proxmox-dashboard' ); ?></div>
			</div>

			<div class="pxd-gauge-card">
				<div class="pxd-gauge-head">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12Z"/><path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/><path d="M11 11h2"/><path d="M11 15h2"/></svg>
					<span><?php esc_html_e( 'Memória RAM', 'proxmox-dashboard' ); ?></span>
				</div>
				<div class="pxd-gauge pxd-gauge--ram" id="pxd-gauge-ram"><span>–</span></div>
				<div class="pxd-bar"><div class="pxd-bar__fill pxd-bar__fill--ram" id="pxd-bar-ram" style="width:0%"></div></div>
				<div class="pxd-gauge-detail" id="pxd-ram-detail"><?php esc_html_e( 'carregando…', 'proxmox-dashboard' ); ?></div>
			</div>

			<div class="pxd-gauge-card">
				<div class="pxd-gauge-head">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/><path d="M12 14a8 3 0 0 0 8-3"/></svg>
					<span><?php esc_html_e( 'Disco Total', 'proxmox-dashboard' ); ?></span>
				</div>
				<div class="pxd-gauge pxd-gauge--disk" id="pxd-gauge-disk"><span>–</span></div>
				<div class="pxd-bar"><div class="pxd-bar__fill pxd-bar__fill--disk" id="pxd-bar-disk" style="width:0%"></div></div>
				<div class="pxd-gauge-detail" id="pxd-disk-detail"><?php esc_html_e( 'carregando…', 'proxmox-dashboard' ); ?></div>
			</div>
		</div>
	</section>

	<!-- Seção: Containers (LXC) & VMs -->
	<section class="section lxc-section" id="section-containers">
		<div class="section-title blue-title">
			<span>
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;vertical-align:-3px;margin-right:6px"><rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 20h8M12 14v6"/></svg>
				<?php esc_html_e( 'Containers (LXC) & Máquinas Virtuais', 'proxmox-dashboard' ); ?>
			</span>
			<small id="pxd-count-containers"><?php echo esc_html( count( $containers ) ); ?> <?php esc_html_e( 'itens', 'proxmox-dashboard' ); ?></small>
		</div>

		<?php if ( ! empty( $containers ) ) : ?>
			<div id="lxcGrid" class="lxc-grid">
				<?php foreach ( $containers as $c ) :
					$cid   = ! empty( $c['id'] ) ? $c['id'] : '';
					$cname = ! empty( $c['name'] ) ? $c['name'] : 'Container';
					$cicon = ! empty( $c['icon'] ) ? $c['icon'] : '';
					$cip   = ! empty( $c['ip'] ) ? $c['ip'] : '—';
					$cmac  = ! empty( $c['mac'] ) ? $c['mac'] : '—';
					$curl  = ! empty( $c['url'] ) ? $c['url'] : '—';
					$cfn   = ! empty( $c['fn'] ) ? $c['fn'] : '—';
					$ctype = ! empty( $c['type'] ) ? $c['type'] : 'lxc';
				?>
				<article class="lxc-card" data-vmid="<?php echo esc_attr( $cid ); ?>" title="<?php esc_attr_e( 'Clique para copiar as informações', 'proxmox-dashboard' ); ?>">
					<div class="lxc-head">
						<span class="id-badge <?php echo 'vm' === $ctype ? 'id-badge--vm' : ''; ?>"><?php echo esc_html( $cid ? $cid : '#' ); ?></span>
						<h3><?php echo esc_html( $cname ); ?></h3>
						<span class="pxd-live-pulse" data-live-status="<?php echo esc_attr( $cid ); ?>" title="<?php esc_attr_e( 'Status ao vivo', 'proxmox-dashboard' ); ?>"></span>
					</div>
					<div class="icon-box">
						<?php if ( $cicon ) : ?>
							<?php if ( preg_match( '#^https?://#i', $cicon ) || strpos( $cicon, '/' ) !== false ) : ?>
								<img src="<?php echo esc_url( $cicon ); ?>" alt="<?php echo esc_attr( $cname ); ?>" class="card-icon" loading="lazy" />
							<?php elseif ( strpos( $cicon, 'fa-' ) !== false ) : ?>
								<i class="<?php echo esc_attr( $cicon ); ?>" aria-hidden="true" style="font-size:32px;color:#087ff5"></i>
							<?php else : ?>
								<span style="font-size:28px"><?php echo esc_html( $cicon ); ?></span>
							<?php endif; ?>
						<?php else : ?>
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:36px;height:36px;color:#087ff5"><rect x="2" y="4" width="20" height="14" rx="2.5"/><path d="M8 20h8M12 14v6"/></svg>
						<?php endif; ?>
					</div>
					<div class="meta">
						<b><?php esc_html_e( 'IP:', 'proxmox-dashboard' ); ?></b> <span class="pxd-copyable"><?php echo esc_html( $cip ); ?></span><br>
						<b><?php esc_html_e( 'MAC:', 'proxmox-dashboard' ); ?></b> <span class="pxd-copyable"><?php echo esc_html( $cmac ); ?></span><br>
						<b><?php esc_html_e( 'URL:', 'proxmox-dashboard' ); ?></b> 
						<?php if ( '—' !== $curl && ! empty( $curl ) ) : 
							$clean_curl = preg_replace( '#^https?://#i', '', trim( $curl ) );
						?>
							<a href="<?php echo esc_url( 'https://' . $clean_curl ); ?>" target="_blank" rel="noopener" class="link pxd-copyable"><?php echo esc_html( $clean_curl ); ?></a>
						<?php else : ?>
							<span class="link">—</span>
						<?php endif; ?>
					</div>
					<!-- Mini medidor Proxmox ao vivo se houver match com VMID -->
					<div class="pxd-card-live-meter" data-live-meter="<?php echo esc_attr( $cid ); ?>" style="display:none">
						<div class="pxd-card-meter-bar"><div class="pxd-card-meter-fill" style="width:0%"></div></div>
						<span class="pxd-card-meter-txt">–</span>
					</div>
					<div class="function">
						<b><?php esc_html_e( 'Função', 'proxmox-dashboard' ); ?></b><br>
						<?php echo esc_html( $cfn ); ?>
					</div>
				</article>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="pxd-empty-box">
				<p><strong><?php esc_html_e( 'Nenhum container ou VM cadastrado ainda.', 'proxmox-dashboard' ); ?></strong></p>
				<p><?php esc_html_e( 'Você pode adicionar seus containers LXC e VMs com ícone, IP, MAC e domínio no painel de controle.', 'proxmox-dashboard' ); ?></p>
				<a href="<?php echo esc_url( $admin_url ); ?>" class="pxd-pill-btn pxd-pill-btn--primary" style="display:inline-flex;margin-top:8px">
					<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Adicionar Containers no Painel', 'proxmox-dashboard' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</section>

	<!-- Seção: Serviços Docker -->
	<section class="section docker-section" id="section-docker">
		<div class="section-title orange-title">
			<span>
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;vertical-align:-3px;margin-right:6px"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
				<?php esc_html_e( 'Serviços Docker — LXC 100 (Ubuntu Server)', 'proxmox-dashboard' ); ?>
			</span>
			<small><?php esc_html_e( 'Acesso via domínio (Cloudflare)', 'proxmox-dashboard' ); ?></small>
		</div>

		<?php if ( ! empty( $docker_services ) ) : ?>
			<div id="dockerGrid" class="docker-grid">
				<?php foreach ( $docker_services as $ds ) :
					$dname   = ! empty( $ds['name'] ) ? $ds['name'] : 'Serviço';
					$dicon   = ! empty( $ds['icon'] ) ? $ds['icon'] : '';
					$dport   = ! empty( $ds['port'] ) ? $ds['port'] : '—';
					$ddomain = ! empty( $ds['domain'] ) ? $ds['domain'] : '—';
					$dcheck  = ! empty( $ds['check_url'] ) ? $ds['check_url'] : '';
				?>
				<article class="docker-card" data-service-name="<?php echo esc_attr( $dname ); ?>" data-check-url="<?php echo esc_attr( $dcheck ); ?>" title="<?php esc_attr_e( 'Clique para copiar', 'proxmox-dashboard' ); ?>">
					<div class="service-icon">
						<?php if ( $dicon ) : ?>
							<?php if ( preg_match( '#^https?://#i', $dicon ) || strpos( $dicon, '/' ) !== false ) : ?>
								<img src="<?php echo esc_url( $dicon ); ?>" alt="<?php echo esc_attr( $dname ); ?>" class="card-icon" loading="lazy" />
							<?php elseif ( strpos( $dicon, 'fa-' ) !== false ) : ?>
								<i class="<?php echo esc_attr( $dicon ); ?>" aria-hidden="true" style="font-size:28px;color:#ff7900"></i>
							<?php else : ?>
								<span style="font-size:26px"><?php echo esc_html( $dicon ); ?></span>
							<?php endif; ?>
						<?php else : ?>
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:32px;height:32px;color:#ff7900"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
						<?php endif; ?>
					</div>
					<h4><?php echo esc_html( $dname ); ?></h4>
					<p>
						<b><?php esc_html_e( 'Porta:', 'proxmox-dashboard' ); ?></b> <span class="pxd-copyable"><?php echo esc_html( $dport ); ?></span><br>
						<?php if ( '—' !== $ddomain && ! empty( $ddomain ) ) : 
							$clean_domain = preg_replace( '#^https?://#i', '', trim( $ddomain ) );
						?>
							<a href="<?php echo esc_url( 'https://' . $clean_domain ); ?>" target="_blank" rel="noopener" class="domain pxd-copyable"><?php echo esc_html( $clean_domain ); ?></a>
						<?php else : ?>
							<span class="domain">—</span>
						<?php endif; ?>
					</p>
					<!-- Live Health-Check Status Badge -->
					<div class="pxd-health-badge" data-health-badge="<?php echo esc_attr( $dname ); ?>">
						<span class="pxd-health-dot"></span>
						<span class="pxd-health-label"><?php esc_html_e( 'Verificando…', 'proxmox-dashboard' ); ?></span>
					</div>
				</article>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="pxd-empty-box">
				<p><strong><?php esc_html_e( 'Nenhum serviço Docker cadastrado ainda.', 'proxmox-dashboard' ); ?></strong></p>
				<p><?php esc_html_e( 'Adicione serviços como Portainer, Gitea, Jellyfin com portas e domínios no painel.', 'proxmox-dashboard' ); ?></p>
				<a href="<?php echo esc_url( $admin_url ); ?>" class="pxd-pill-btn pxd-pill-btn--primary" style="display:inline-flex;margin-top:8px">
					<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Adicionar Serviços Docker', 'proxmox-dashboard' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</section>

	<!-- Seção Inferior: Armazenamento & Legenda -->
	<div class="bottom-grid">
		<section class="section storage-section">
			<div class="section-title green-title">
				<span>
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;vertical-align:-3px;margin-right:6px"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/><path d="M4 11v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"/></svg>
					<?php esc_html_e( 'Armazenamento', 'proxmox-dashboard' ); ?>
				</span>
			</div>
			<div class="storage-grid">
				<?php if ( ! empty( $storages_custom ) ) : ?>
					<?php foreach ( $storages_custom as $sc ) :
						$sdrive = ! empty( $sc['drive'] ) ? $sc['drive'] : 'SSD';
						$stitle = ! empty( $sc['title'] ) ? $sc['title'] : 'Storage';
						$sitems = ! empty( $sc['items'] ) ? explode( "\n", str_replace( "\r", '', $sc['items'] ) ) : array();
					?>
					<article class="storage-card">
						<div class="drive"><?php echo esc_html( $sdrive ); ?></div>
						<div>
							<h3><?php echo esc_html( $stitle ); ?></h3>
							<ul>
								<?php
								$clean_sitems = array_filter( array_map( 'trim', $sitems ) );
								foreach ( $clean_sitems as $sit_clean ) :
								?>
									<li><?php echo esc_html( $sit_clean ); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
					</article>
					<?php endforeach; ?>
				<?php else : ?>
					<article class="storage-card">
						<div class="drive">SSD</div>
						<div>
							<h3>SSD /dev/sda (256 GB)</h3>
							<ul>
								<li>Proxmox VE</li>
								<li>Storage: local-lvm</li>
								<li>Containers (LXC)</li>
							</ul>
						</div>
					</article>
					<article class="storage-card">
						<div class="drive">HDD</div>
						<div>
							<h3>HDD /dev/sdb (500 GB)</h3>
							<ul>
								<li>Storage: local-lvm</li>
								<li>Discos das Máquinas Virtuais (VM)</li>
							</ul>
						</div>
					</article>
				<?php endif; ?>
			</div>
		</section>

		<section class="section legend-section">
			<div class="section-title purple-title">
				<span>
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;vertical-align:-3px;margin-right:6px"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
					<?php esc_html_e( 'Legenda', 'proxmox-dashboard' ); ?>
				</span>
			</div>
			<div class="legend-grid">
				<?php if ( ! empty( $legend_items ) ) : ?>
					<?php foreach ( $legend_items as $lg ) :
						$ltag   = ! empty( $lg['tag'] ) ? $lg['tag'] : 'TAG';
						$lcolor = ! empty( $lg['color'] ) ? $lg['color'] : 'lxc';
						$llabel = ! empty( $lg['label'] ) ? $lg['label'] : '';
					?>
					<div><span class="tag <?php echo esc_attr( $lcolor ); ?>"><?php echo esc_html( $ltag ); ?></span> <?php echo esc_html( $llabel ); ?></div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</section>
	</div>

	<!-- Visão Técnica Alternativa: Tabelas Detalhadas Proxmox (Opcional via Toggle) -->
	<section id="pxd-technical-tables" class="pxd-technical-tables" style="display:none;">
		<div class="pxd-tables-grid">
			<!-- Tabela: Nodes -->
			<div class="pxd-table-card">
				<div class="pxd-table-head">
					<h3><?php esc_html_e( 'Nodes do Proxmox VE', 'proxmox-dashboard' ); ?></h3>
					<span class="pxd-section-badge" id="pxd-count-nodes">–</span>
				</div>
				<div class="pxd-table-wrap">
					<table>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Node', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Status', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'CPU', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Memória', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Disco', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Uptime', 'proxmox-dashboard' ); ?></th>
							</tr>
						</thead>
						<tbody id="pxd-nodes-body">
							<tr><td colspan="6" class="pxd-empty"><?php esc_html_e( 'Carregando dados dos nodes…', 'proxmox-dashboard' ); ?></td></tr>
						</tbody>
					</table>
				</div>
			</div>

			<!-- Tabela: Storages Proxmox -->
			<div class="pxd-table-card">
				<div class="pxd-table-head">
					<h3><?php esc_html_e( 'Storages do Proxmox (LVM / ZFS)', 'proxmox-dashboard' ); ?></h3>
					<span class="pxd-section-badge" id="pxd-count-storage-tab">–</span>
				</div>
				<div class="pxd-table-wrap">
					<table>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Storage', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Tipo', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Status', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Total', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Uso', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Livre', 'proxmox-dashboard' ); ?></th>
							</tr>
						</thead>
						<tbody id="pxd-storage-body">
							<tr><td colspan="6" class="pxd-empty"><?php esc_html_e( 'Carregando storages…', 'proxmox-dashboard' ); ?></td></tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</section>

	<footer>
		<span><?php esc_html_e( 'Mapa da infraestrutura • Homelab', 'proxmox-dashboard' ); ?></span>
		<span id="clock"></span>
	</footer>

</main>

<!-- Toast de feedback para cópia -->
<div id="pxd-toast" class="pxd-toast" role="status" aria-live="polite"></div>

<?php
get_footer();
