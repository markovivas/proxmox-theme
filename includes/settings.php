<?php
/**
 * Página de configurações do tema Proxmox Dashboard.
 *
 * @package Proxmox_Dashboard
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra o menu de configurações no admin.
 */
function proxmox_dashboard_register_menu() {
	add_menu_page(
		__( 'Proxmox Dashboard', 'proxmox-dashboard' ),
		__( 'Proxmox Dashboard', 'proxmox-dashboard' ),
		'manage_options',
		'proxmox-dashboard',
		'proxmox_dashboard_settings_page',
		'dashicons-dashboard',
		3
	);

	add_submenu_page(
		'proxmox-dashboard',
		__( 'Configurações', 'proxmox-dashboard' ),
		__( 'Configurações', 'proxmox-dashboard' ),
		'manage_options',
		'proxmox-dashboard',
		'proxmox_dashboard_settings_page'
	);

	add_submenu_page(
		'proxmox-dashboard',
		__( 'Abrir Dashboard', 'proxmox-dashboard' ),
		__( 'Abrir Dashboard', 'proxmox-dashboard' ),
		'manage_options',
		'proxmox-dashboard-front',
		'proxmox_dashboard_front_redirect'
	);
}
add_action( 'admin_menu', 'proxmox_dashboard_register_menu' );

/**
 * Redireciona para a página inicial do dashboard.
 */
function proxmox_dashboard_front_redirect() {
	wp_safe_redirect( home_url( '/' ), 302 );
	exit;
}

/**
 * Registra as opções.
 */
function proxmox_dashboard_register_settings() {
	register_setting(
		'proxmox_dashboard_settings_group',
		'proxmox_dashboard_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'proxmox_dashboard_sanitize_settings',
			'default'           => array(),
		)
	);
}
add_action( 'admin_init', 'proxmox_dashboard_register_settings' );

/**
 * Sanitização das configurações.
 *
 * @param array $input Dados enviados.
 * @return array
 */
function proxmox_dashboard_sanitize_settings( $input ) {
	$current = proxmox_dashboard_get_options();

	// Suporte a modo personalizado: se refresh_interval = custom, usa refresh_interval_custom
	$interval_raw = $input['refresh_interval'] ?? '';
	$custom_raw   = $input['refresh_interval_custom'] ?? '';

	if ( 'custom' === $interval_raw ) {
		$interval = absint( $custom_raw );
	} else {
		$interval = absint( $interval_raw );
	}

	// Permite 0 = manual (sem auto), senão clamp 1..86400 (1s a 24h)
	if ( 0 === $interval ) {
		$interval = 0;
	} elseif ( $interval < 1 ) {
		$interval = 5;
	} elseif ( $interval > 86400 ) {
		$interval = 86400;
	}

	$clean = array(
		'host'             => isset( $input['host'] ) ? esc_url_raw( trim( $input['host'] ) ) : '',
		'user'             => isset( $input['user'] ) ? sanitize_text_field( $input['user'] ) : '',
		'token_id'         => isset( $input['token_id'] ) ? sanitize_text_field( $input['token_id'] ) : '',
		'refresh_interval' => $interval,
		'services'         => array(),
	);

	// Serviços monitorados (cadastro manual gradativo).
	if ( isset( $input['services'] ) && is_array( $input['services'] ) ) {
		foreach ( $input['services'] as $svc ) {
			if ( ! is_array( $svc ) ) {
				continue;
			}
			$name = isset( $svc['name'] ) ? sanitize_text_field( trim( $svc['name'] ) ) : '';
			$url_raw = isset( $svc['url'] ) ? trim( $svc['url'] ) : '';
			// Auto-corrige URL sem esquema (ex.: 192.168.2.100:3001 → http://...)
			if ( '' !== $url_raw && ! preg_match( '#^https?://#i', $url_raw ) ) {
				$url_raw = 'http://' . ltrim( $url_raw, '/' );
			}
			$url  = $url_raw !== '' ? esc_url_raw( $url_raw ) : '';
			$icon = isset( $svc['icon'] ) ? sanitize_text_field( trim( $svc['icon'] ) ) : '';

			// Linha vazia (removida no JS) — ignora se nome e url vazios.
			if ( '' === $name && '' === $url ) {
				continue;
			}
			// URL obrigatória; se inválida, descarta linha mas mantém nome para feedback — aqui descartamos só se vazia.
			if ( '' === $url ) {
				continue;
			}

			// Limita ícone a 200 chars e permite apenas classes FA / URLs / emoji (sanitizado acima).
			if ( strlen( $icon ) > 200 ) {
				$icon = substr( $icon, 0, 200 );
			}

			$clean['services'][] = array(
				'name' => $name !== '' ? $name : __( 'Serviço', 'proxmox-dashboard' ),
				'url'  => $url,
				'icon' => $icon,
			);
		}
		// Limita a 50 serviços para evitar abuso.
		if ( count( $clean['services'] ) > 50 ) {
			$clean['services'] = array_slice( $clean['services'], 0, 50 );
		}
	} elseif ( isset( $current['services'] ) && is_array( $current['services'] ) ) {
		// Se services não veio no POST (ex.: submit antigo), preserva.
		$clean['services'] = $current['services'];
	}

	// Se o secret não foi enviado (campo oculto), mantém o valor atual.
	if ( isset( $input['token_secret'] ) && '' !== $input['token_secret'] && '******' !== $input['token_secret'] ) {
		$clean['token_secret'] = sanitize_text_field( $input['token_secret'] );
	} elseif ( isset( $current['token_secret'] ) ) {
		$clean['token_secret'] = $current['token_secret'];
	} else {
		$clean['token_secret'] = '';
	}

	return $clean;
}

/**
 * Renderiza a página de configurações.
 */
function proxmox_dashboard_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$options = proxmox_dashboard_get_options();
	$tested  = false;
	$test    = null;

	// Ação de teste de conexão.
	if ( isset( $_POST['proxmox_dashboard_test'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'proxmox_dashboard_test' ) ) {
		$tested = true;
		$api = Proxmox_API::instance();
		$test = $api->test_connection();
		if ( true === $test ) {
			$test = true;
		}
	}

	$is_configured = proxmox_dashboard_is_configured();
	?>
	<style>
		/* ===== Proxmox Dashboard — Admin Premium (wp-admin) ===== */
		.pxd-admin{max-width:1100px;margin:14px 20px 40px 2px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif}
		.pxd-admin a{color:#2271b1}
		/* Header */
		.pxd-admin__header{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;background:#fff;border:1px solid #c3c4c7;border-radius:12px;padding:18px 20px;box-shadow:0 1px 3px rgba(0,0,0,.06);margin:0 0 18px;position:relative;overflow:hidden}
		.pxd-admin__header::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:linear-gradient(180deg,#2271b1 0%,#06b6d4 100%)}
		.pxd-admin__header__left{display:flex;align-items:center;gap:14px;min-width:0}
		.pxd-admin__logo{width:44px;height:44px;border-radius:10px;display:grid;place-items:center;background:linear-gradient(135deg,#ff7a18 0%,#e34a1d 55%,#b91c1c 100%);box-shadow:0 4px 16px rgba(227,74,29,.28);flex-shrink:0;color:#fff}
		.pxd-admin__logo .dashicons{font-size:24px;width:24px;height:24px}
		.pxd-admin__title{margin:0;font-size:18px;font-weight:800;letter-spacing:-.02em;line-height:1.1;color:#1d2327}
		.pxd-admin__title span{font-weight:400;color:#646970;font-size:13px;letter-spacing:0}
		.pxd-admin__subtitle{margin:3px 0 0;font-size:12.5px;color:#646970}
		.pxd-admin__subtitle code{background:#f6f7f7;border:1px solid #dcdcde;padding:1px 6px;border-radius:4px;font-size:11.5px}
		.pxd-admin__header__right{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
		.pxd-admin__badge{display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:100px;font-size:11px;font-weight:700;letter-spacing:.02em;border:1px solid #dcdcde;background:#f6f7f7;color:#50575e;white-space:nowrap}
		.pxd-admin__badge--ok{background:#f0f7ee;border-color:#b8d9b5;color:#1a5c1a}
		.pxd-admin__badge--warn{background:#fcf9e8;border-color:#e5d47a;color:#5b4a00}
		.pxd-admin__badge .dashicons{font-size:14px;width:14px;height:14px}
		.pxd-admin .button{border-radius:7px}
		.pxd-admin .button-primary{box-shadow:0 1px 0 rgba(0,0,0,.08)}
		/* Notices mais elegantes dentro do wrapper */
		.pxd-admin .notice{border-radius:8px}
		/* Cards */
		.pxd-admin-card{background:#fff;border:1px solid #c3c4c7;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.06);overflow:hidden;margin:0 0 18px}
		.pxd-admin-card__head{padding:16px 20px 14px;display:flex;align-items:center;gap:12px;border-bottom:1px solid #f0f0f1;background:linear-gradient(180deg,#fbfcfd 0%,#fff 100%)}
		.pxd-admin-card__head__icon{width:38px;height:38px;border-radius:9px;display:grid;place-items:center;flex-shrink:0;border:1px solid #dcdcde;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.06)}
		.pxd-admin-card__head__icon .dashicons{font-size:18px;width:18px;height:18px;color:#2271b1}
		.pxd-admin-card__head__icon--orange{background:linear-gradient(135deg,#fff7ed,#ffedd5);border-color:#fed7aa;color:#c2410c}
		.pxd-admin-card__head__icon--orange .dashicons{color:#ea580c}
		.pxd-admin-card__head__icon--blue{background:linear-gradient(135deg,#eff6ff,#dbeafe);border-color:#bfdbfe}
		.pxd-admin-card__head__icon--violet{background:linear-gradient(135deg,#f5f3ff,#ede9fe);border-color:#ddd6fe}
		.pxd-admin-card__head__icon--violet .dashicons{color:#7c3aed}
		.pxd-admin-card__head__icon--green{background:linear-gradient(135deg,#f0fdf4,#dcfce7);border-color:#bbf7d0}
		.pxd-admin-card__head__icon--green .dashicons{color:#16a34a}
		.pxd-admin-card__step{width:30px;height:30px;border-radius:100px;display:grid;place-items:center;font-size:11px;font-weight:800;letter-spacing:.04em;background:#1d2327;color:#fff;flex-shrink:0;box-shadow:0 1px 0 rgba(0,0,0,.12)}
		.pxd-admin-card__step--blue{background:linear-gradient(135deg,#2271b1,#06b6d4)}
		.pxd-admin-card__step--violet{background:linear-gradient(135deg,#7c3aed,#06b6d4)}
		.pxd-admin-card__head__text{flex:1;min-width:0}
		.pxd-admin-card__head__text h2{margin:0;font-size:14px;font-weight:750;letter-spacing:-.01em;color:#1d2327;line-height:1.2}
		.pxd-admin-card__head__text p{margin:2px 0 0;font-size:12.5px;color:#646970;line-height:1.4}
		.pxd-admin-card__body{padding:18px 20px 20px}
		/* Form table restyle para dentro do card */
		.pxd-admin-card .form-table{margin:0}
		.pxd-admin-card .form-table th{width:200px;padding:14px 14px 14px 0;font-size:13px;font-weight:600;color:#1d2327;vertical-align:top}
		.pxd-admin-card .form-table td{padding:10px 0}
		.pxd-admin-card .form-table tr{border-bottom:1px solid #f6f7f7}
		.pxd-admin-card .form-table tr:last-child{border-bottom:none}
		.pxd-admin-card input[type="url"],
		.pxd-admin-card input[type="text"],
		.pxd-admin-card input[type="password"],
		.pxd-admin-card input[type="number"],
		.pxd-admin-card select{border-radius:7px;border-color:#8c8f94;min-height:38px;box-shadow:0 1px 0 rgba(0,0,0,.02)}
		.pxd-admin-card input[type="url"]:focus,
		.pxd-admin-card input[type="text"]:focus,
		.pxd-admin-card input[type="password"]:focus,
		.pxd-admin-card input[type="number"]:focus,
		.pxd-admin-card select:focus{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}
		.pxd-admin-card .regular-text{width:100%;max-width:520px}
		.pxd-admin-card .description{margin-top:6px;color:#646970;font-size:12.5px;line-height:1.5}
		.pxd-admin-card code{background:#f6f7f7;border:1px solid #dcdcde;padding:1px 5px;border-radius:4px;font-size:11.5px}
		/* Divisor interno */
		.pxd-admin-card__divider{height:1px;background:#f0f0f1;margin:18px 0}
		.pxd-admin-card__section-label{display:flex;align-items:center;gap:8px;margin:0 0 12px;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#50575e}
		.pxd-admin-card__section-label .dashicons{font-size:15px;width:15px;height:15px;color:#8c8f94}
		/* Help steps */
		.pxd-steps{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:0;counter-reset:pxdstep}
		.pxd-steps li{position:relative;display:flex;gap:12px;padding:12px 0;border-bottom:1px solid #f6f7f7;line-height:1.6;font-size:13px;color:#3c434a}
		.pxd-steps li:last-child{border-bottom:none}
		.pxd-steps li::before{counter-increment:pxdstep;content:counter(pxdstep);width:28px;height:28px;flex-shrink:0;display:grid;place-items:center;background:#f6f7f7;border:1px solid #dcdcde;border-radius:100px;font-size:11px;font-weight:800;color:#50575e;margin-top:1px}
		.pxd-steps li strong{color:#1d2327}
		.pxd-steps li code{vertical-align:middle}
		/* Alert */
		.pxd-alert{display:flex;gap:12px;align-items:flex-start;background:#f0f7ee;border:1px solid #b8d9b5;border-left:4px solid #00a32a;border-radius:8px;padding:14px 14px;margin:16px 0 0}
		.pxd-alert .dashicons{color:#00a32a;flex-shrink:0;margin-top:1px}
		.pxd-alert strong{color:#1a5c1a}
		.pxd-alert code{background:#fff}
		.pxd-alert__title{font-size:13px;font-weight:750;margin:0 0 4px}
		.pxd-alert__body{font-size:12.5px;color:#3c434a;line-height:1.6}
		.pxd-help-foot{margin:16px 0 0;padding-top:14px;border-top:1px solid #f0f0f1;display:flex;flex-wrap:wrap;gap:10px;align-items:center}
		/* Serviços */
		.pxd-services-intro{font-size:12.5px;color:#646970;line-height:1.6;margin:0 0 14px}
		.pxd-services-intro a{font-weight:600}
		#pxd-services-list{display:flex;flex-direction:column;gap:10px}
		.pxd-service-row{display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap;background:#fbfcfd;border:1px solid #dcdcde;border-radius:10px;padding:14px 14px;transition:border-color .15s,box-shadow .15s,background .15s}
		.pxd-service-row:hover{background:#fff;border-color:#a7aaad;box-shadow:0 1px 3px rgba(0,0,0,.08)}
		.pxd-service-row:focus-within{background:#fff;border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}
		.pxd-service-row label{flex:1;min-width:160px;display:flex;flex-direction:column;gap:4px;margin:0}
		.pxd-service-row label span{font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#50575e}
		.pxd-service-row input{width:100%;margin:0}
		.pxd-service-row .description{font-size:11px;margin-top:4px}
		.pxd-service-row .button.pxd-service-remove{margin-top:18px;height:38px;min-width:38px;padding:0 10px;border-radius:7px;color:#d63638;border-color:#d63638;background:#fff}
		.pxd-service-row .button.pxd-service-remove:hover{background:#fef1f1;border-color:#d63638;color:#b32d2e}
		/* Refresh custom */
		#proxmox_refresh_custom_wrap{display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap;background:#f6f7f7;border:1px solid #dcdcde;border-radius:8px;padding:8px 10px;margin-left:10px}
		/* Submit bar */
		.pxd-submit-bar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;background:#fff;border:1px solid #c3c4c7;border-radius:10px;padding:14px 16px;box-shadow:0 1px 2px rgba(0,0,0,.06);margin:0 0 18px}
		.pxd-submit-bar p.submit{margin:0;padding:0}
		.pxd-submit-bar .description{margin:0}
		/* Test card */
		.pxd-test-card .pxd-admin-card__body{padding-bottom:16px}
		.pxd-test-card p.submit{margin:12px 0 0;padding:0}
		@media (max-width:782px){
			.pxd-admin{margin:10px 10px 30px 0}
			.pxd-admin__header{padding:14px}
			.pxd-admin-card__head{padding:14px}
			.pxd-admin-card__body{padding:14px}
			.pxd-admin-card .form-table th{width:auto;display:block;padding-bottom:0;border:none}
			.pxd-admin-card .form-table td{display:block;padding-top:8px}
			.pxd-service-row{padding:12px}
			.pxd-service-row label{min-width:100%}
		}
	</style>
	<div class="wrap pxd-admin">
		<div class="pxd-admin__header">
			<div class="pxd-admin__header__left">
				<div class="pxd-admin__logo" aria-hidden="true"><span class="dashicons dashicons-dashboard"></span></div>
				<div>
					<h1 class="pxd-admin__title"><?php esc_html_e( 'Proxmox Dashboard', 'proxmox-dashboard' ); ?> <span>v<?php echo esc_html( defined('PROXMOX_DASHBOARD_VERSION') ? PROXMOX_DASHBOARD_VERSION : '2.2.0' ); ?></span></h1>
					<p class="pxd-admin__subtitle"><?php esc_html_e( 'Gerencie a conexão com o Proxmox VE e os serviços monitorados.', 'proxmox-dashboard' ); ?> <code>wp-admin/admin.php?page=proxmox-dashboard</code></p>
				</div>
			</div>
			<div class="pxd-admin__header__right">
				<?php if ( $is_configured ) : ?>
					<span class="pxd-admin__badge pxd-admin__badge--ok"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Configurado', 'proxmox-dashboard' ); ?></span>
				<?php else : ?>
					<span class="pxd-admin__badge pxd-admin__badge--warn"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Ação necessária', 'proxmox-dashboard' ); ?></span>
				<?php endif; ?>
				<a class="button button-secondary" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-visibility" style="margin-top:3px"></span> <?php esc_html_e( 'Abrir Dashboard', 'proxmox-dashboard' ); ?></a>
			</div>
		</div>

		<?php if ( ! empty( $_GET['settings-updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><span class="dashicons dashicons-yes" style="color:#00a32a"></span> <?php esc_html_e( 'Configurações salvas com sucesso.', 'proxmox-dashboard' ); ?></p></div>
		<?php endif; ?>

		<?php if ( $tested ) : ?>
			<?php if ( true === $test ) : ?>
				<div class="notice notice-success is-dismissible"><p><strong>✓ <?php esc_html_e( 'Proxmox conectado', 'proxmox-dashboard' ); ?></strong> — <?php esc_html_e( 'Autenticação e API responderam corretamente.', 'proxmox-dashboard' ); ?></p></div>
			<?php else : ?>
				<div class="notice notice-error is-dismissible"><p><strong>✗ <?php esc_html_e( 'Falha na conexão', 'proxmox-dashboard' ); ?>:</strong> <code><?php echo esc_html( $test->get_error_message() ); ?></code></p></div>
			<?php endif; ?>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'proxmox_dashboard_settings_group' ); ?>

			<!-- 01 — Conexão -->
			<div class="pxd-admin-card">
				<div class="pxd-admin-card__head">
					<span class="pxd-admin-card__step pxd-admin-card__step--blue">01</span>
					<div class="pxd-admin-card__head__icon"><span class="dashicons dashicons-admin-network"></span></div>
					<div class="pxd-admin-card__head__text">
						<h2><?php esc_html_e( 'Conexão', 'proxmox-dashboard' ); ?></h2>
						<p><?php esc_html_e( 'Credenciais de acesso à API do Proxmox VE. Preencha os 4 campos abaixo.', 'proxmox-dashboard' ); ?></p>
					</div>
				</div>
				<div class="pxd-admin-card__body">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="proxmox_host"><?php esc_html_e( 'Proxmox Host', 'proxmox-dashboard' ); ?></label></th>
							<td>
								<input type="url" id="proxmox_host" name="proxmox_dashboard_settings[host]" value="<?php echo esc_attr( $options['host'] ); ?>" placeholder="https://192.168.2.50:8006" class="regular-text code" />
								<p class="description"><?php esc_html_e( 'URL completa do Proxmox, incluindo a porta (geralmente 8006). Ex.: https://192.168.2.50:8006', 'proxmox-dashboard' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="proxmox_user"><?php esc_html_e( 'API User', 'proxmox-dashboard' ); ?></label></th>
							<td>
								<input type="text" id="proxmox_user" name="proxmox_dashboard_settings[user]" value="<?php echo esc_attr( $options['user'] ); ?>" placeholder="dashboard@pve" class="regular-text code" />
								<p class="description"><?php esc_html_e( 'Exemplo: dashboard@pve ou root@pam. Recomendado criar usuário dedicado.', 'proxmox-dashboard' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="proxmox_token_id"><?php esc_html_e( 'API Token ID', 'proxmox-dashboard' ); ?></label></th>
							<td>
								<input type="text" id="proxmox_token_id" name="proxmox_dashboard_settings[token_id]" value="<?php echo esc_attr( $options['token_id'] ); ?>" placeholder="dashboard" class="regular-text code" />
								<p class="description"><?php esc_html_e( 'Apenas o Token ID (sem o prefixo usuario@realm!).', 'proxmox-dashboard' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="proxmox_token_secret"><?php esc_html_e( 'API Token Secret', 'proxmox-dashboard' ); ?></label></th>
							<td>
								<input type="password" id="proxmox_token_secret" name="proxmox_dashboard_settings[token_secret]" value="<?php echo esc_attr( $options['token_secret'] ? '******' : '' ); ?>" class="regular-text code" autocomplete="off" placeholder="••••••••••••••••" />
								<p class="description"><?php esc_html_e( 'O secret fica armazenado apenas no servidor e nunca é exibido. Deixe como ****** para manter o atual.', 'proxmox-dashboard' ); ?></p>
							</td>
						</tr>
					</table>

					<div class="pxd-admin-card__divider" aria-hidden="true"></div>
					<p class="pxd-admin-card__section-label"><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Atualização automática do dashboard', 'proxmox-dashboard' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="proxmox_refresh_interval"><?php esc_html_e( 'Intervalo de atualização', 'proxmox-dashboard' ); ?></label></th>
							<td>
								<?php
								$intervals = array(
									0    => __( 'Manual (sem auto-atualização)', 'proxmox-dashboard' ),
									1    => __( '1 segundo', 'proxmox-dashboard' ),
									5    => __( '5 segundos', 'proxmox-dashboard' ),
									10   => __( '10 segundos', 'proxmox-dashboard' ),
									15   => __( '15 segundos', 'proxmox-dashboard' ),
									30   => __( '30 segundos', 'proxmox-dashboard' ),
									60   => __( '1 minuto', 'proxmox-dashboard' ),
									300  => __( '5 minutos', 'proxmox-dashboard' ),
									900  => __( '15 minutos', 'proxmox-dashboard' ),
									1800 => __( '30 minutos', 'proxmox-dashboard' ),
									3600 => __( '1 hora', 'proxmox-dashboard' ),
								);
								$current_interval = intval( $options['refresh_interval'] );
								$is_custom        = ! array_key_exists( $current_interval, $intervals );
								?>
								<select id="proxmox_refresh_interval" name="proxmox_dashboard_settings[refresh_interval]">
									<?php foreach ( $intervals as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $is_custom ? 'custom' : $current_interval, $value ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
									<option value="custom" <?php selected( $is_custom, true ); ?>><?php esc_html_e( 'Personalizado…', 'proxmox-dashboard' ); ?></option>
								</select>
								<span id="proxmox_refresh_custom_wrap" style="<?php echo $is_custom ? '' : 'display:none;'; ?>">
									<input type="number" id="proxmox_refresh_interval_custom" name="proxmox_dashboard_settings[refresh_interval_custom]" value="<?php echo esc_attr( $is_custom ? $current_interval : '' ); ?>" min="1" max="86400" step="1" placeholder="ex: 120" class="small-text" style="width:92px;" /> <?php esc_html_e( 'segundos', 'proxmox-dashboard' ); ?>
									<span class="description" style="margin-left:4px;"><?php esc_html_e( '1 a 86400 (24h). Ex: 120 = 2 min.', 'proxmox-dashboard' ); ?></span>
								</span>
								<p class="description" id="proxmox_refresh_help"><?php esc_html_e( 'Use 5 min / 15 min / 30 min / 1h para economizar requisições, ou Manual para atualizar só no botão.', 'proxmox-dashboard' ); ?></p>
								<script>
								(function(){
									var sel = document.getElementById('proxmox_refresh_interval');
									var wrap = document.getElementById('proxmox_refresh_custom_wrap');
									if(sel && wrap){
										sel.addEventListener('change', function(){
											wrap.style.display = (this.value === 'custom') ? '' : 'none';
											if(this.value === 'custom'){
												var inp = document.getElementById('proxmox_refresh_interval_custom');
												if(inp) inp.focus();
											}
										});
									}
								})();
								</script>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<!-- 02 — Como preencher -->
			<div class="pxd-admin-card">
				<div class="pxd-admin-card__head">
					<span class="pxd-admin-card__step">02</span>
					<div class="pxd-admin-card__head__icon pxd-admin-card__head__icon--orange"><span class="dashicons dashicons-info-outline"></span></div>
					<div class="pxd-admin-card__head__text">
						<h2><?php esc_html_e( 'Como preencher — Instruções de conexão com o Proxmox VE', 'proxmox-dashboard' ); ?></h2>
						<p><?php esc_html_e( 'Siga os 5 passos abaixo para criar o API Token no Proxmox e voltar com as credenciais.', 'proxmox-dashboard' ); ?></p>
					</div>
				</div>
				<div class="pxd-admin-card__body">
					<ol class="pxd-steps">
						<li><?php esc_html_e( 'No Proxmox VE, acesse', 'proxmox-dashboard' ); ?> <code>Datacenter → Access → API Tokens</code> <?php esc_html_e( 'e clique em', 'proxmox-dashboard' ); ?> <strong><?php esc_html_e( 'Add', 'proxmox-dashboard' ); ?></strong>.</li>
						<li><?php esc_html_e( 'Selecione o', 'proxmox-dashboard' ); ?> <strong><?php esc_html_e( 'User', 'proxmox-dashboard' ); ?></strong> <?php esc_html_e( '(ex.:', 'proxmox-dashboard' ); ?> <code>dashboard@pve</code> <?php esc_html_e( 'ou', 'proxmox-dashboard' ); ?> <code>root@pam</code><?php esc_html_e( ') e defina um', 'proxmox-dashboard' ); ?> <strong><?php esc_html_e( 'Token ID', 'proxmox-dashboard' ); ?></strong> <?php esc_html_e( '(ex.:', 'proxmox-dashboard' ); ?> <code>dashboard</code><?php esc_html_e( ').', 'proxmox-dashboard' ); ?></li>
						<li><?php esc_html_e( 'Copie o', 'proxmox-dashboard' ); ?> <strong><?php esc_html_e( 'Token Secret', 'proxmox-dashboard' ); ?></strong> <?php esc_html_e( 'exibido apenas uma vez e guarde em local seguro.', 'proxmox-dashboard' ); ?></li>
						<li><?php esc_html_e( 'Volte nesta tela e preencha', 'proxmox-dashboard' ); ?> <strong><?php esc_html_e( 'Proxmox Host', 'proxmox-dashboard' ); ?></strong> <?php esc_html_e( '(ex.:', 'proxmox-dashboard' ); ?> <code>https://192.168.2.50:8006</code><?php esc_html_e( '),', 'proxmox-dashboard' ); ?> <strong><?php esc_html_e( 'API User', 'proxmox-dashboard' ); ?></strong> <?php esc_html_e( '(ex.:', 'proxmox-dashboard' ); ?> <code>dashboard@pve</code><?php esc_html_e( '),', 'proxmox-dashboard' ); ?> <strong><?php esc_html_e( 'API Token ID', 'proxmox-dashboard' ); ?></strong> <?php esc_html_e( 'e', 'proxmox-dashboard' ); ?> <strong><?php esc_html_e( 'API Token Secret', 'proxmox-dashboard' ); ?></strong>.</li>
						<li><?php esc_html_e( 'Clique em', 'proxmox-dashboard' ); ?> <strong><?php esc_html_e( 'Salvar configurações', 'proxmox-dashboard' ); ?></strong> <?php esc_html_e( 'e depois em', 'proxmox-dashboard' ); ?> <strong><?php esc_html_e( 'Testar conexão', 'proxmox-dashboard' ); ?></strong>.</li>
					</ol>

					<div class="pxd-alert">
						<span class="dashicons dashicons-yes-alt"></span>
						<div>
							<p class="pxd-alert__title">✅ <?php esc_html_e( 'IMPORTANTE — Desative “Privilege Separation”', 'proxmox-dashboard' ); ?></p>
							<div class="pxd-alert__body">
								<?php esc_html_e( 'Ao criar o API Token no Proxmox, deixe a opção', 'proxmox-dashboard' ); ?> <strong><code><?php esc_html_e( 'Privilege Separation', 'proxmox-dashboard' ); ?></code> <?php esc_html_e( 'DESMARCADA / DESATIVADA', 'proxmox-dashboard' ); ?></strong> <?php esc_html_e( '(toggle desligado).', 'proxmox-dashboard' ); ?><br>
								<?php esc_html_e( 'Se essa opção ficar ativada, o token herda apenas as permissões vazias do usuário e o dashboard não consegue listar Nodes, VMs, LXC e Storage (erro 403/“permission denied”). Com a separação desativada, o token usa as permissões do próprio usuário/grupo.', 'proxmox-dashboard' ); ?><br>
								<span style="font-size:12px;opacity:.85"><?php esc_html_e( 'Caminho:', 'proxmox-dashboard' ); ?> <code>Datacenter → Access → API Tokens → Add → Privilege Separation → OFF</code> — <?php esc_html_e( 'depois atribua ao usuário/grupo a permission', 'proxmox-dashboard' ); ?> <code>PVEAuditor</code> <?php esc_html_e( 'ou', 'proxmox-dashboard' ); ?> <code>PVEAdmin</code> <?php esc_html_e( 'no caminho', 'proxmox-dashboard' ); ?> <code>Datacenter → Permissions</code>.</span>
							</div>
						</div>
					</div>

					<div class="pxd-help-foot">
						<a class="button button-secondary" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-visibility" style="margin-top:3px"></span> <?php esc_html_e( 'Abrir Dashboard', 'proxmox-dashboard' ); ?></a>
						<span class="description"><?php esc_html_e( 'Dica: use um usuário dedicado (ex.: dashboard@pve) em vez de root@pam.', 'proxmox-dashboard' ); ?></span>
					</div>
				</div>
			</div>

			<!-- 03 — Serviços Monitorados -->
			<div class="pxd-admin-card">
				<div class="pxd-admin-card__head">
					<span class="pxd-admin-card__step pxd-admin-card__step--violet">03</span>
					<div class="pxd-admin-card__head__icon pxd-admin-card__head__icon--violet"><span class="dashicons dashicons-screenoptions"></span></div>
					<div class="pxd-admin-card__head__text">
						<h2><?php esc_html_e( 'Serviços Monitorados (VMs)', 'proxmox-dashboard' ); ?> <span style="font-weight:500;font-size:11px;color:#646970;background:#f6f7f7;border:1px solid #dcdcde;padding:2px 7px;border-radius:100px;vertical-align:middle;margin-left:6px"><?php esc_html_e( 'cadastro manual e gradativo', 'proxmox-dashboard' ); ?></span></h2>
						<p><?php esc_html_e( 'Sistemas que rodam nas VMs — health-check HTTP server-side com status Online/Offline + latência.', 'proxmox-dashboard' ); ?></p>
					</div>
				</div>
				<div class="pxd-admin-card__body">
					<p class="pxd-services-intro">
						<?php esc_html_e( 'Cadastre aqui os sistemas que rodam nas VMs, ex.: Gitea em http://192.168.2.100:3001/. O dashboard fará um health-check HTTP server-side (sem CORS) e exibirá Online/Offline + latência.', 'proxmox-dashboard' ); ?><br>
						<?php esc_html_e( 'Ícone: use classe do Font Awesome (ex.: fa-brands fa-git-alt, fa-solid fa-server) ou emoji/URL. Deixe vazio para ícone padrão.', 'proxmox-dashboard' ); ?>
						<a href="https://fontawesome.com/search?o=r&m=free" target="_blank" rel="noopener"><?php esc_html_e( 'Buscar ícones Font Awesome', 'proxmox-dashboard' ); ?> ↗</a>
					</p>

					<div id="pxd-services-list">
						<?php
						$services = isset( $options['services'] ) && is_array( $options['services'] ) ? $options['services'] : array();
						if ( empty( $services ) ) {
							$services[] = array( 'name' => '', 'url' => '', 'icon' => '' );
						}
						foreach ( $services as $idx => $svc ) :
							$s_name = isset( $svc['name'] ) ? $svc['name'] : '';
							$s_url  = isset( $svc['url'] ) ? $svc['url'] : '';
							$s_icon = isset( $svc['icon'] ) ? $svc['icon'] : '';
						?>
						<div class="pxd-service-row">
							<label>
								<span><?php esc_html_e( 'Nome', 'proxmox-dashboard' ); ?></span>
								<input type="text" name="proxmox_dashboard_settings[services][<?php echo esc_attr( $idx ); ?>][name]" value="<?php echo esc_attr( $s_name ); ?>" placeholder="Gitea" class="regular-text" />
							</label>
							<label style="flex:1.8;min-width:220px">
								<span><?php esc_html_e( 'URL', 'proxmox-dashboard' ); ?></span>
								<input type="url" name="proxmox_dashboard_settings[services][<?php echo esc_attr( $idx ); ?>][url]" value="<?php echo esc_attr( $s_url ); ?>" placeholder="http://192.168.2.100:3001/" class="regular-text code" />
							</label>
							<label>
								<span><?php esc_html_e( 'Ícone (Font Awesome)', 'proxmox-dashboard' ); ?></span>
								<input type="text" name="proxmox_dashboard_settings[services][<?php echo esc_attr( $idx ); ?>][icon]" value="<?php echo esc_attr( $s_icon ); ?>" placeholder="fa-brands fa-git-alt" class="regular-text code" />
								<span class="description">
									<?php if ( $s_icon && strpos( $s_icon, 'fa-' ) !== false ) : ?>
										<i class="<?php echo esc_attr( $s_icon ); ?>" aria-hidden="true"></i> <?php echo esc_html( $s_icon ); ?>
									<?php else : ?>
										<?php esc_html_e( 'Ex.: fa-solid fa-server', 'proxmox-dashboard' ); ?>
									<?php endif; ?>
								</span>
							</label>
							<button type="button" class="button pxd-service-remove" aria-label="<?php esc_attr_e( 'Remover serviço', 'proxmox-dashboard' ); ?>" title="<?php esc_attr_e( 'Remover', 'proxmox-dashboard' ); ?>">✕</button>
						</div>
						<?php endforeach; ?>
					</div>
					<p style="margin:14px 0 0">
						<button type="button" class="button button-secondary" id="pxd-service-add"><span class="dashicons dashicons-plus-alt" style="margin-top:3px"></span> <?php esc_html_e( 'Adicionar serviço', 'proxmox-dashboard' ); ?></button>
					</p>
					<p class="description" style="margin-top:10px">
						<?php esc_html_e( 'Exemplo Gitea: Nome = Gitea | URL = http://192.168.2.100:3001/ | Ícone = fa-brands fa-git-alt', 'proxmox-dashboard' ); ?><br>
						<?php esc_html_e( 'O check é feito pelo servidor WordPress (5s timeout). URLs internas 192.168.x são permitidas.', 'proxmox-dashboard' ); ?>
					</p>
					<script>
					(function(){
						var list = document.getElementById('pxd-services-list');
						var addBtn = document.getElementById('pxd-service-add');
						if(!list || !addBtn) return;
						function reindex(){
							var rows = list.querySelectorAll('.pxd-service-row');
							rows.forEach(function(row, i){
								row.querySelectorAll('input').forEach(function(inp){
									inp.name = inp.name.replace(/\[services\]\[\d+\]/, '[services]['+i+']');
								});
							});
						}
						function createRow(idx){
							var div = document.createElement('div');
							div.className = 'pxd-service-row';
							div.innerHTML = '<label><span><?php echo esc_js( __( 'Nome', 'proxmox-dashboard' ) ); ?></span><input type="text" name="proxmox_dashboard_settings[services]['+idx+'][name]" placeholder="Novo serviço" class="regular-text" /></label>'
								+ '<label style="flex:1.8;min-width:220px"><span><?php echo esc_js( __( 'URL', 'proxmox-dashboard' ) ); ?></span><input type="url" name="proxmox_dashboard_settings[services]['+idx+'][url]" placeholder="http://192.168.2.100:3000/" class="regular-text code" /></label>'
								+ '<label><span><?php echo esc_js( __( 'Ícone (Font Awesome)', 'proxmox-dashboard' ) ); ?></span><input type="text" name="proxmox_dashboard_settings[services]['+idx+'][icon]" placeholder="fa-solid fa-cube" class="regular-text code" /><span class="description"><?php echo esc_js( __( 'Ex.: fa-solid fa-server', 'proxmox-dashboard' ) ); ?></span></label>'
								+ '<button type="button" class="button pxd-service-remove" style="margin-top:18px">✕</button>';
							return div;
						}
						addBtn.addEventListener('click', function(){
							var idx = list.querySelectorAll('.pxd-service-row').length;
							list.appendChild(createRow(idx));
						});
						list.addEventListener('click', function(e){
							if(e.target.classList.contains('pxd-service-remove')){
								var row = e.target.closest('.pxd-service-row');
								if(row){
									row.remove();
									reindex();
									if(list.querySelectorAll('.pxd-service-row').length === 0){
										list.appendChild(createRow(0));
									}
								}
							}
						});
					})();
					</script>
				</div>
			</div>

			<div class="pxd-submit-bar">
				<?php submit_button( __( 'Salvar configurações', 'proxmox-dashboard' ), 'primary', 'submit', false ); ?>
				<span class="description"><?php esc_html_e( 'Salve primeiro e depois teste a conexão abaixo. As alterações só entram em vigor após salvar.', 'proxmox-dashboard' ); ?></span>
			</div>
		</form>

		<!-- Testar conexão (fora do form principal) -->
		<div class="pxd-admin-card pxd-test-card">
			<div class="pxd-admin-card__head">
				<div class="pxd-admin-card__head__icon pxd-admin-card__head__icon--green"><span class="dashicons dashicons-cloud"></span></div>
				<div class="pxd-admin-card__head__text">
					<h2><?php esc_html_e( 'Testar conexão', 'proxmox-dashboard' ); ?></h2>
					<p><?php esc_html_e( 'Verifica se o host está acessível, se a API responde e se a autenticação é válida.', 'proxmox-dashboard' ); ?></p>
				</div>
			</div>
			<div class="pxd-admin-card__body">
				<form method="post">
					<?php wp_nonce_field( 'proxmox_dashboard_test' ); ?>
					<?php submit_button( __( 'Testar conexão', 'proxmox-dashboard' ), 'secondary', 'proxmox_dashboard_test', false ); ?>
					<span class="description" style="margin-left:10px"><?php esc_html_e( 'Faça este teste após salvar as configurações de Conexão.', 'proxmox-dashboard' ); ?></span>
				</form>
			</div>
		</div>
	</div>
	<?php
}
