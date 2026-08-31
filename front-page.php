<?php
/**
 * Front page do tema Proxmox Dashboard - Premium v2
 *
 * @package Proxmox_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$is_configured = proxmox_dashboard_is_configured();
$admin_url     = admin_url( 'admin.php?page=proxmox-dashboard' );
?>
<div class="pxd-shell">

	<?php if ( ! $is_configured ) : ?>
		<div class="pxd-not-configured">
			<div class="pxd-not-configured__card">
				<div class="pxd-not-configured__icon">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.3 3 10.6c-.7.7-.7 1.9 0 2.6l7.3 7.3c.7.7 1.9.7 2.6 0l7.3-7.3c.7-.7.7-1.9 0-2.6L12.9 3.3c-.7-.7-1.9-.7-2.6 0Z"/></svg>
				</div>
				<h2><?php esc_html_e( 'Proxmox Dashboard não configurado', 'proxmox-dashboard' ); ?></h2>
				<p>
					<?php esc_html_e( 'Conecte seu Proxmox VE informando o host, usuário e API Token para ativar o monitoramento em tempo real.', 'proxmox-dashboard' ); ?>
				</p>
				<a class="pxd-btn" href="<?php echo esc_url( $admin_url ); ?>">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 9 15a1.65 1.65 0 0 0-1-1.51V13a1.65 1.65 0 0 0 1-1.51 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 13.5 7.5a1.65 1.65 0 0 0 1-1.51V6a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 13v.5a1.65 1.65 0 0 0-1 1.51Z"/></svg>
					<?php esc_html_e( 'Configurar agora', 'proxmox-dashboard' ); ?>
				</a>
			</div>
		</div>
	<?php else : ?>

		<header class="pxd-header">
			<div class="pxd-header__brand">
				<div class="pxd-header__brand__logo" aria-hidden="true">
					<svg viewBox="0 0 24 24"><path d="M4 4h8v8H4zM12 12h8v8h-8zM12 4h8v4h-8zM4 12h4v8H4z" opacity=".9"/><path d="M14 6h4v2h-4zM6 14h2v4H6z" fill="rgba(255,255,255,.7)"/></svg>
				</div>
				<div>
					<h1>Proxmox <span>Dashboard</span></h1>
					<p>Virtual Environment • PVE</p>
				</div>
			</div>

			<div class="pxd-header__center">
				<div class="pxd-header__status">
					<span class="pxd-dot" id="pxd-status-dot" aria-hidden="true"></span>
					<span id="pxd-status-text"><?php esc_html_e( 'Carregando…', 'proxmox-dashboard' ); ?></span>
					<span class="pxd-version" id="pxd-version"></span>
				</div>
				<span class="pxd-last-update" id="pxd-last-update"></span>
			</div>

			<div class="pxd-header__actions">
				<button type="button" id="pxd-kiosk" class="pxd-btn pxd-btn--ghost pxd-btn--icon pxd-btn--kiosk" title="<?php esc_attr_e( 'Modo TV — encaixa tudo na tela sem rolagem (ideal para TV/Tablet)', 'proxmox-dashboard' ); ?>" aria-label="<?php esc_attr_e( 'Modo TV', 'proxmox-dashboard' ); ?>" aria-pressed="false">
					<svg class="pxd-icon--kiosk-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6v6H9z"/><path d="M9 3v3M15 3v3M9 18v3M15 18v3"/></svg>
					<svg class="pxd-icon--kiosk-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="display:none"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 8l8 8"/><path d="M16 8l-8 8"/></svg>
				</button>
				<button type="button" id="pxd-fullscreen" class="pxd-btn pxd-btn--ghost pxd-btn--icon pxd-btn--fullscreen" title="<?php esc_attr_e( 'Alternar tela cheia (F11 / ESC para sair)', 'proxmox-dashboard' ); ?>" aria-label="<?php esc_attr_e( 'Tela cheia', 'proxmox-dashboard' ); ?>">
					<svg class="pxd-icon--expand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M16 3h3a2 2 0 0 1 2 2v3"/><path d="M8 21H5a2 2 0 0 1-2-2v-3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
					<svg class="pxd-icon--compress" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="display:none"><path d="M4 14h6v6"/><path d="M20 10H14V4"/><path d="M14 14h6v6"/><path d="M4 10h6V4"/></svg>
				</button>
				<button type="button" id="pxd-refresh" class="pxd-btn pxd-btn--ghost pxd-btn--icon" title="<?php esc_attr_e( 'Atualizar dados agora', 'proxmox-dashboard' ); ?>" aria-label="<?php esc_attr_e( 'Atualizar dados agora', 'proxmox-dashboard' ); ?>">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
				</button>
			</div>
		</header>

		<main class="pxd-main" id="pxd-main">

			<!-- Cards de resumo -->
			<section class="pxd-summary" aria-label="<?php esc_attr_e( 'Resumo', 'proxmox-dashboard' ); ?>">
				<div class="pxd-card pxd-card--summary">
					<div class="pxd-card__top">
						<div class="pxd-card__icon pxd-card__icon--nodes">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
						</div>
						<span class="pxd-card__trend">cluster</span>
					</div>
					<div class="pxd-card__label"><?php esc_html_e( 'Nodes', 'proxmox-dashboard' ); ?></div>
					<div class="pxd-card__value" id="pxd-sum-nodes">–</div>
				</div>
				<div class="pxd-card pxd-card--summary">
					<div class="pxd-card__top">
						<div class="pxd-card__icon pxd-card__icon--vms">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="4" width="20" height="14" rx="2.5"/><path d="M8 20h8"/><path d="M12 14v6"/></svg>
						</div>
						<span class="pxd-card__trend pxd-card__trend--up" id="pxd-trend-vms">● ativas</span>
					</div>
					<div class="pxd-card__label"><?php esc_html_e( 'VMs ativas', 'proxmox-dashboard' ); ?></div>
					<div class="pxd-card__value" id="pxd-sum-vms">–</div>
				</div>
				<div class="pxd-card pxd-card--summary">
					<div class="pxd-card__top">
						<div class="pxd-card__icon pxd-card__icon--lxc">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h6"/></svg>
						</div>
						<span class="pxd-card__trend pxd-card__trend--up" id="pxd-trend-lxc">● ativos</span>
					</div>
					<div class="pxd-card__label"><?php esc_html_e( 'LXC ativos', 'proxmox-dashboard' ); ?></div>
					<div class="pxd-card__value" id="pxd-sum-lxc">–</div>
				</div>
				<div class="pxd-card pxd-card--summary">
					<div class="pxd-card__top">
						<div class="pxd-card__icon pxd-card__icon--storage">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/><path d="M4 11v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"/></svg>
						</div>
						<span class="pxd-card__trend">storages</span>
					</div>
					<div class="pxd-card__label"><?php esc_html_e( 'Storages', 'proxmox-dashboard' ); ?></div>
					<div class="pxd-card__value" id="pxd-sum-storage">–</div>
				</div>
			</section>

			<!-- Utilização de recursos -->
			<section class="pxd-usage" aria-label="<?php esc_attr_e( 'Utilização', 'proxmox-dashboard' ); ?>">
				<div class="pxd-card pxd-card--usage">
					<h3 class="pxd-card__title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6v6H9z"/></svg> <?php esc_html_e( 'CPU', 'proxmox-dashboard' ); ?></h3>
					<div class="pxd-gauge" id="pxd-gauge-cpu"><span>–</span></div>
					<div class="pxd-bar"><div class="pxd-bar__fill" id="pxd-bar-cpu" style="width:0%"></div></div>
					<div class="pxd-card__detail" id="pxd-cpu-detail">pico do cluster</div>
				</div>
				<div class="pxd-card pxd-card--usage">
					<h3 class="pxd-card__title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12Z"/><path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/><path d="M11 11h2"/><path d="M11 15h2"/></svg> <?php esc_html_e( 'Memória', 'proxmox-dashboard' ); ?></h3>
					<div class="pxd-gauge pxd-gauge--ram" id="pxd-gauge-ram"><span>–</span></div>
					<div class="pxd-bar"><div class="pxd-bar__fill pxd-bar__fill--ram" id="pxd-bar-ram" style="width:0%"></div></div>
					<div class="pxd-card__detail" id="pxd-ram-detail">–</div>
				</div>
				<div class="pxd-card pxd-card--usage">
					<h3 class="pxd-card__title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/><path d="M12 14a8 3 0 0 0 8-3"/></svg> <?php esc_html_e( 'Disco', 'proxmox-dashboard' ); ?></h3>
					<div class="pxd-gauge pxd-gauge--disk" id="pxd-gauge-disk"><span>–</span></div>
					<div class="pxd-bar"><div class="pxd-bar__fill pxd-bar__fill--disk" id="pxd-bar-disk" style="width:0%"></div></div>
					<div class="pxd-card__detail" id="pxd-disk-detail">–</div>
				</div>
			</section>

			<!-- Serviços monitorados (cadastro manual gradativo) -->
			<?php
			$pxd_services = function_exists( 'proxmox_dashboard_get_services' ) ? proxmox_dashboard_get_services() : array();
			$pxd_has_services = ! empty( $pxd_services );
			?>
			<section class="pxd-section pxd-section--services" id="pxd-services-section" aria-label="<?php esc_attr_e( 'Serviços Monitorados', 'proxmox-dashboard' ); ?>">
				<div class="pxd-section__head">
					<h2 class="pxd-section__title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M12 1v2"/><path d="M12 21v2"/><path d="M4.22 4.22l1.42 1.42"/><path d="M18.36 18.36l1.42 1.42"/><path d="M1 12h2"/><path d="M21 12h2"/><path d="M4.22 19.78l1.42-1.42"/><path d="M18.36 5.64l1.42-1.42"/></svg> <?php esc_html_e( 'Serviços Monitorados', 'proxmox-dashboard' ); ?></h2>
					<span class="pxd-section__badge" id="pxd-count-services"><?php echo $pxd_has_services ? esc_html( count( $pxd_services ) . ' itens' ) : '—'; ?></span>
				</div>
				<div id="pxd-services-grid" class="pxd-services-grid" <?php echo $pxd_has_services ? '' : 'style="display:none"'; ?>>
					<?php if ( $pxd_has_services ) : ?>
						<?php foreach ( $pxd_services as $svc ) : ?>
							<?php
							$s_name = isset( $svc['name'] ) ? $svc['name'] : '';
							$s_url  = isset( $svc['url'] ) ? $svc['url'] : '';
							$s_icon = isset( $svc['icon'] ) ? $svc['icon'] : '';
							$s_icon_html = '';
							if ( $s_icon ) {
								if ( preg_match( '#^https?://#i', $s_icon ) ) {
									$s_icon_html = '<img src="' . esc_url( $s_icon ) . '" alt="" style="width:18px;height:18px;object-fit:contain;border-radius:4px" loading="lazy">';
								} elseif ( strpos( $s_icon, 'fa-' ) !== false ) {
									$s_icon_html = '<i class="' . esc_attr( $s_icon ) . '" aria-hidden="true"></i>';
								} else {
									$s_icon_html = '<span style="font-size:15px;line-height:1">' . esc_html( $s_icon ) . '</span>';
								}
							} else {
								$s_icon_html = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 1v2"/><path d="M12 21v2"/><path d="M4.22 4.22l1.42 1.42"/><path d="M18.36 18.36l1.42 1.42"/><path d="M1 12h2"/><path d="M21 12h2"/><path d="M4.22 19.78l1.42-1.42"/><path d="M18.36 5.64l1.42-1.42"/></svg>';
							}
							?>
							<a href="<?php echo esc_url( $s_url ); ?>" target="_blank" rel="noopener" class="pxd-service-card pxd-service-card--link" data-pxd-service-url="<?php echo esc_attr( $s_url ); ?>">
								<div class="pxd-service-card__top">
									<div class="pxd-service-card__icon"><?php echo $s_icon_html; // phpcs:ignore ?></div>
									<span class="pxd-service-card__status pxd-service-card__status--checking"><?php esc_html_e( 'Verificando…', 'proxmox-dashboard' ); ?></span>
								</div>
								<div class="pxd-service-card__name" title="<?php echo esc_attr( $s_name ); ?>"><?php echo esc_html( $s_name ); ?></div>
								<div class="pxd-service-card__url" title="<?php echo esc_attr( $s_url ); ?>"><?php echo esc_html( $s_url ); ?></div>
								<div class="pxd-service-card__meta"><code>…</code></div>
							</a>
						<?php endforeach; ?>
					<?php else : ?>
						<div class="pxd-services-skeleton" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
					<?php endif; ?>
				</div>
				<p class="pxd-services-empty description" id="pxd-services-empty" <?php echo $pxd_has_services ? 'style="display:none"' : ''; ?>><?php esc_html_e( 'Nenhum serviço cadastrado. Adicione em Proxmox Dashboard → Configurações → Serviços Monitorados.', 'proxmox-dashboard' ); ?></p>
			</section>

			<!-- Grid sem rolagem: em Modo TV as 4 tabelas viram grade 2×2 -->
			<div class="pxd-tables-grid" id="pxd-tables-grid">
			<!-- Nodes -->
			<section class="pxd-section">
				<div class="pxd-section__head">
					<h2 class="pxd-section__title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><path d="M14 14h7v7h-7z"/></svg> <?php esc_html_e( 'Nodes', 'proxmox-dashboard' ); ?></h2>
					<span class="pxd-section__badge" id="pxd-count-nodes">—</span>
				</div>
				<div class="pxd-table-wrap pxd-table-wrap--scroll">
					<table class="pxd-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Nome', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Status', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'CPU', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Memória', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Disco', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Uptime', 'proxmox-dashboard' ); ?></th>
							</tr>
						</thead>
						<tbody id="pxd-nodes-body">
							<tr class="pxd-skeleton-row"><td><span></span></td><td><span></span></td><td><span></span></td><td><span></span></td><td><span></span></td><td><span></span></td></tr>
							<tr class="pxd-skeleton-row"><td><span></span></td><td><span></span></td><td><span></span></td><td><span></span></td><td><span></span></td><td><span></span></td></tr>
						</tbody>
					</table>
				</div>
			</section>

			<!-- VMs -->
			<section class="pxd-section">
				<div class="pxd-section__head">
					<h2 class="pxd-section__title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="4" width="20" height="14" rx="2.5"/><path d="M8 20h8"/><path d="M12 14v6"/></svg> <?php esc_html_e( 'Máquinas Virtuais', 'proxmox-dashboard' ); ?></h2>
					<span class="pxd-section__badge" id="pxd-count-vms">—</span>
				</div>
				<div class="pxd-table-wrap pxd-table-wrap--scroll">
					<table class="pxd-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'VMID', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Nome', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Node', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Status', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'CPU', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Memória', 'proxmox-dashboard' ); ?></th>
							</tr>
						</thead>
						<tbody id="pxd-vms-body">
							<tr class="pxd-skeleton-row"><td><span></span></td><td><span></span></td><td><span></span></td><td><span></span></td><td><span></span></td><td><span></span></td></tr>
							<tr class="pxd-skeleton-row"><td><span></span></td><td><span></span></td><td><span></span></td><td><span></span></td><td><span></span></td><td><span></span></td></tr>
						</tbody>
					</table>
				</div>
			</section>

			<!-- Containers LXC -->
			<section class="pxd-section">
				<div class="pxd-section__head">
					<h2 class="pxd-section__title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/></svg> <?php esc_html_e( 'Containers LXC', 'proxmox-dashboard' ); ?></h2>
					<span class="pxd-section__badge" id="pxd-count-lxc">—</span>
				</div>
				<div class="pxd-table-wrap pxd-table-wrap--scroll">
					<table class="pxd-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'CTID', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Nome', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Node', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Status', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'CPU', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Memória', 'proxmox-dashboard' ); ?></th>
							</tr>
						</thead>
						<tbody id="pxd-lxc-body">
							<tr class="pxd-skeleton-row"><td><span></span></td><td><span></span></td><td><span></span></td><td><span></span></td><td><span></span></td><td><span></span></td></tr>
						</tbody>
					</table>
				</div>
			</section>

			<!-- Storage -->
			<section class="pxd-section">
				<div class="pxd-section__head">
					<h2 class="pxd-section__title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/><path d="M4 11v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"/></svg> <?php esc_html_e( 'Armazenamento', 'proxmox-dashboard' ); ?></h2>
					<span class="pxd-section__badge" id="pxd-count-storage">—</span>
				</div>
				<div class="pxd-table-wrap pxd-table-wrap--scroll">
					<table class="pxd-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Nome', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Tipo', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Status', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Total', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Usado', 'proxmox-dashboard' ); ?></th>
								<th><?php esc_html_e( 'Livre', 'proxmox-dashboard' ); ?></th>
							</tr>
						</thead>
						<tbody id="pxd-storage-body">
							<tr class="pxd-skeleton-row"><td><span></span></td><td><span></span></td><td><span></span></td><td><span></span></td><td><span></span></td><td><span></span></td></tr>
						</tbody>
					</table>
				</div>
			</section>
			</div><!-- /.pxd-tables-grid -->
		</main>

		<footer class="pxd-footer">
			<div class="pxd-footer__left">
				<span class="pxd-footer__brand">Proxmox Dashboard</span>
				<span class="pxd-footer__sep">•</span>
				<span>WordPress • PVE API</span>
			</div>
			<span id="pxd-last-update-footer" style="font-family:'JetBrains Mono',monospace; font-size:11px; opacity:.7"></span>
		</footer>

	<?php endif; ?>
</div><!-- /.pxd-shell -->
<?php
get_footer();
