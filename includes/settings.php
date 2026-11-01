<?php
/**
 * Página de configurações do tema Proxmox Dashboard.
 * Gerenciador completo de Conexão, Topologia, Containers LXC/VMs, Docker e Storage.
 *
 * @package Proxmox_Dashboard
 * @since   2.3.0
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
 * Exporta as configurações completas do tema em formato JSON para download.
 */
function proxmox_dashboard_handle_export() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Você não tem permissão para realizar esta ação.', 'proxmox-dashboard' ) );
	}

	check_admin_referer( 'proxmox_dashboard_export_action', 'proxmox_dashboard_export_nonce' );

	$options = proxmox_dashboard_get_options();

	// Verifica se deve incluir o segredo da API Proxmox
	$include_secret = ! empty( $_POST['export_include_secret'] );
	if ( ! $include_secret ) {
		$options['token_secret'] = '';
	}

	$export_data = array(
		'generator'   => 'Proxmox Dashboard WordPress Theme',
		'version'     => defined( 'PROXMOX_DASHBOARD_VERSION' ) ? PROXMOX_DASHBOARD_VERSION : '2.4.0',
		'exported_at' => current_time( 'mysql' ),
		'site_url'    => home_url(),
		'settings'    => $options,
	);

	$filename = 'proxmox-dashboard-settings-' . gmdate( 'Y-m-d-His' ) . '.json';

	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	echo wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
}
add_action( 'admin_post_proxmox_dashboard_export', 'proxmox_dashboard_handle_export' );

/**
 * Importa configurações a partir de arquivo JSON ou texto colado diretamente.
 */
function proxmox_dashboard_handle_import() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Você não tem permissão para realizar esta ação.', 'proxmox-dashboard' ) );
	}

	check_admin_referer( 'proxmox_dashboard_import_action', 'proxmox_dashboard_import_nonce' );

	$redirect_url = admin_url( 'admin.php?page=proxmox-dashboard' );

	$raw_content = '';
	if ( ! empty( $_FILES['settings_file']['tmp_name'] ) && is_uploaded_file( $_FILES['settings_file']['tmp_name'] ) ) {
		$raw_content = file_get_contents( $_FILES['settings_file']['tmp_name'] );
	} elseif ( ! empty( $_POST['settings_json_text'] ) ) {
		$raw_content = wp_unslash( $_POST['settings_json_text'] );
	}

	if ( empty( trim( $raw_content ) ) ) {
		wp_safe_redirect( add_query_arg( array( 'pxd_status' => 'import_error_nofile', 'tab' => 'tab-backup' ), $redirect_url ) );
		exit;
	}

	$data = json_decode( $raw_content, true );
	if ( ! is_array( $data ) ) {
		wp_safe_redirect( add_query_arg( array( 'pxd_status' => 'import_error_invalid_json', 'tab' => 'tab-backup' ), $redirect_url ) );
		exit;
	}

	// Suporta formato envelopado { settings: {...} } ou formato direto { host: ... }
	$settings_to_import = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : $data;

	$current = proxmox_dashboard_get_options();

	// Preservar credenciais Proxmox se a opção estiver marcada
	if ( ! empty( $_POST['preserve_credentials'] ) ) {
		$settings_to_import['host']         = $current['host'];
		$settings_to_import['user']         = $current['user'];
		$settings_to_import['token_id']     = $current['token_id'];
		$settings_to_import['token_secret'] = $current['token_secret'];
	} else {
		// Mesmo se não marcada, caso o arquivo venha sem secret preenchido, preserva o atual para não quebrar a conexão
		if ( empty( $settings_to_import['token_secret'] ) && ! empty( $current['token_secret'] ) ) {
			$settings_to_import['token_secret'] = $current['token_secret'];
		}
	}

	$clean = proxmox_dashboard_sanitize_settings( $settings_to_import );

	update_option( 'proxmox_dashboard_settings', $clean );

	wp_safe_redirect( add_query_arg( array( 'pxd_status' => 'import_success', 'tab' => 'tab-backup' ), $redirect_url ) );
	exit;
}
add_action( 'admin_post_proxmox_dashboard_import', 'proxmox_dashboard_handle_import' );

/**
 * Sanitização das configurações completas.
 *
 * @param array $input Dados enviados via POST.
 * @return array
 */
function proxmox_dashboard_sanitize_settings( $input ) {
	if ( ! is_array( $input ) ) {
		return array();
	}

	$current = proxmox_dashboard_get_options();

	// Intervalo de atualização
	$interval_raw = isset( $input['refresh_interval'] ) ? $input['refresh_interval'] : '';
	$custom_raw   = isset( $input['refresh_interval_custom'] ) ? $input['refresh_interval_custom'] : '';

	if ( 'custom' === $interval_raw ) {
		$interval = absint( $custom_raw );
	} else {
		$interval = absint( $interval_raw );
	}

	if ( 0 === $interval ) {
		$interval = 0;
	} elseif ( $interval < 1 ) {
		$interval = 5;
	} elseif ( $interval > 86400 ) {
		$interval = 86400;
	}

	$token_id_raw = isset( $input['token_id'] ) ? sanitize_text_field( trim( $input['token_id'] ) ) : '';
	$user_raw     = '';

	if ( strpos( $token_id_raw, '!' ) !== false ) {
		$parts    = explode( '!', $token_id_raw, 2 );
		$user_raw = sanitize_text_field( trim( $parts[0] ) );
	} elseif ( ! empty( $input['user'] ) ) {
		$user_raw = sanitize_text_field( trim( $input['user'] ) );
	} elseif ( ! empty( $current['user'] ) ) {
		$user_raw = $current['user'];
	}

	$clean = array(
		'host'             => isset( $input['host'] ) ? esc_url_raw( trim( $input['host'] ) ) : '',
		'user'             => $user_raw,
		'token_id'         => $token_id_raw,
		'refresh_interval' => $interval,

		// Topologia & Servidor
		'server_ip'        => isset( $input['server_ip'] ) ? sanitize_text_field( trim( $input['server_ip'] ) ) : '',
		'server_so'        => isset( $input['server_so'] ) ? sanitize_text_field( trim( $input['server_so'] ) ) : '',
		'server_fn'        => isset( $input['server_fn'] ) ? sanitize_text_field( trim( $input['server_fn'] ) ) : '',
		'server_mac'       => isset( $input['server_mac'] ) ? sanitize_text_field( trim( $input['server_mac'] ) ) : '',
		'server_url'       => isset( $input['server_url'] ) ? sanitize_text_field( trim( $input['server_url'] ) ) : '',
		'server_status'    => isset( $input['server_status'] ) ? sanitize_textarea_field( $input['server_status'] ) : '',
		'server_icon'      => isset( $input['server_icon'] ) ? esc_url_raw( trim( $input['server_icon'] ) ) : '',
		'router_ip'        => isset( $input['router_ip'] ) ? sanitize_text_field( trim( $input['router_ip'] ) ) : '192.168.2.1',
		'router_label'     => isset( $input['router_label'] ) ? sanitize_text_field( trim( $input['router_label'] ) ) : 'ROTEADOR',
		'switch_label'     => isset( $input['switch_label'] ) ? sanitize_text_field( trim( $input['switch_label'] ) ) : 'SWITCH',
		'lan_network'      => isset( $input['lan_network'] ) ? sanitize_text_field( trim( $input['lan_network'] ) ) : '192.168.2.0/24',

		'containers'       => array(),
		'docker_services'  => array(),
		'storages_custom'  => array(),
		'legend_items'     => array(),
		'services'         => array(),
	);

	// Secret do token
	if ( isset( $input['token_secret'] ) && '' !== $input['token_secret'] && '******' !== $input['token_secret'] ) {
		$clean['token_secret'] = sanitize_text_field( $input['token_secret'] );
	} elseif ( isset( $current['token_secret'] ) ) {
		$clean['token_secret'] = $current['token_secret'];
	} else {
		$clean['token_secret'] = '';
	}

	// 1) Containers (LXC & VMs)
	if ( isset( $input['containers'] ) && is_array( $input['containers'] ) ) {
		foreach ( $input['containers'] as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$name = isset( $c['name'] ) ? sanitize_text_field( trim( $c['name'] ) ) : '';
			$id   = isset( $c['id'] ) ? sanitize_text_field( trim( $c['id'] ) ) : '';
			$ip   = isset( $c['ip'] ) ? sanitize_text_field( trim( $c['ip'] ) ) : '';

			if ( '' === $name && '' === $id && '' === $ip ) {
				continue;
			}

			$clean['containers'][] = array(
				'type' => ( isset( $c['type'] ) && 'vm' === $c['type'] ) ? 'vm' : 'lxc',
				'id'   => $id,
				'name' => $name !== '' ? $name : __( 'Container', 'proxmox-dashboard' ),
				'icon' => isset( $c['icon'] ) ? sanitize_text_field( trim( $c['icon'] ) ) : '',
				'ip'   => $ip,
				'mac'  => isset( $c['mac'] ) ? sanitize_text_field( trim( $c['mac'] ) ) : '',
				'url'  => isset( $c['url'] ) ? sanitize_text_field( trim( $c['url'] ) ) : '',
				'fn'   => isset( $c['fn'] ) ? sanitize_text_field( trim( $c['fn'] ) ) : '',
			);
		}
	}

	// 2) Serviços Docker
	if ( isset( $input['docker_services'] ) && is_array( $input['docker_services'] ) ) {
		foreach ( $input['docker_services'] as $ds ) {
			if ( ! is_array( $ds ) ) {
				continue;
			}
			$name   = isset( $ds['name'] ) ? sanitize_text_field( trim( $ds['name'] ) ) : '';
			$domain = isset( $ds['domain'] ) ? sanitize_text_field( trim( $ds['domain'] ) ) : '';
			$port   = isset( $ds['port'] ) ? sanitize_text_field( trim( $ds['port'] ) ) : '';

			if ( '' === $name && '' === $domain && '' === $port ) {
				continue;
			}

			$clean['docker_services'][] = array(
				'name'      => $name !== '' ? $name : __( 'Serviço Docker', 'proxmox-dashboard' ),
				'icon'      => isset( $ds['icon'] ) ? sanitize_text_field( trim( $ds['icon'] ) ) : '',
				'port'      => $port,
				'domain'    => $domain,
				'check_url' => isset( $ds['check_url'] ) ? esc_url_raw( trim( $ds['check_url'] ) ) : '',
			);
		}
	}

	// 3) Armazenamento Customizado (SSD/HDD)
	if ( isset( $input['storages_custom'] ) && is_array( $input['storages_custom'] ) ) {
		foreach ( $input['storages_custom'] as $sc ) {
			if ( ! is_array( $sc ) ) {
				continue;
			}
			$title = isset( $sc['title'] ) ? sanitize_text_field( trim( $sc['title'] ) ) : '';
			$drive = isset( $sc['drive'] ) ? sanitize_text_field( trim( $sc['drive'] ) ) : 'SSD';
			$items = isset( $sc['items'] ) ? sanitize_textarea_field( $sc['items'] ) : '';

			if ( '' === $title && '' === $items ) {
				continue;
			}

			$clean['storages_custom'][] = array(
				'drive' => $drive,
				'title' => $title !== '' ? $title : __( 'Armazenamento', 'proxmox-dashboard' ),
				'items' => $items,
			);
		}
	}

	// 4) Legenda
	if ( isset( $input['legend_items'] ) && is_array( $input['legend_items'] ) ) {
		foreach ( $input['legend_items'] as $lg ) {
			if ( ! is_array( $lg ) ) {
				continue;
			}
			$tag   = isset( $lg['tag'] ) ? sanitize_text_field( trim( $lg['tag'] ) ) : '';
			$label = isset( $lg['label'] ) ? sanitize_text_field( trim( $lg['label'] ) ) : '';
			$color = isset( $lg['color'] ) ? sanitize_text_field( trim( $lg['color'] ) ) : 'lxc';

			if ( '' === $tag && '' === $label ) {
				continue;
			}

			$clean['legend_items'][] = array(
				'tag'   => $tag,
				'color' => $color,
				'label' => $label,
			);
		}
	} elseif ( isset( $current['legend_items'] ) && is_array( $current['legend_items'] ) ) {
		$clean['legend_items'] = $current['legend_items'];
	}

	// Serviços legados (preserva caso existam)
	if ( isset( $input['services'] ) && is_array( $input['services'] ) ) {
		$clean['services'] = $input['services'];
	} elseif ( isset( $current['services'] ) && is_array( $current['services'] ) ) {
		$clean['services'] = $current['services'];
	}

	return $clean;
}

/**
 * Renderiza a página de configurações completa.
 */
function proxmox_dashboard_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$options          = proxmox_dashboard_get_options();
	$tested           = false;
	$test             = null;
	$is_configured    = proxmox_dashboard_is_configured();
	$token_id_display = $options['token_id'];

	if ( ! empty( $options['user'] ) && strpos( $token_id_display, '!' ) === false ) {
		$token_id_display = $options['user'] . '!' . $token_id_display;
	}

	// Teste de conexão
	if ( isset( $_POST['proxmox_dashboard_test'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'proxmox_dashboard_test' ) ) {
		$tested = true;
		$api    = Proxmox_API::instance();
		$test   = $api->test_connection();
	}
	?>
	<style>
		/* ===== Proxmox Dashboard — Painel de Gestão Completo ===== */
		.pxd-admin { max-width: 1200px; margin: 16px 20px 40px 2px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
		.pxd-admin__header { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; background: #fff; border: 1px solid #c3c4c7; border-radius: 12px; padding: 18px 22px; box-shadow: 0 1px 3px rgba(0,0,0,.06); margin-bottom: 18px; position: relative; overflow: hidden; }
		.pxd-admin__header::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; background: linear-gradient(180deg, #ff7900 0%, #087ff5 100%); }
		.pxd-admin__header__left { display: flex; align-items: center; gap: 14px; min-width: 0; }
		.pxd-admin__logo { width: 46px; height: 46px; border-radius: 10px; display: grid; place-items: center; background: linear-gradient(135deg, #ff7900 0%, #e34a1d 100%); color: #fff; box-shadow: 0 4px 14px rgba(245, 122, 0, 0.35); flex-shrink: 0; }
		.pxd-admin__title { margin: 0; font-size: 19px; font-weight: 800; color: #17213e; letter-spacing: -0.02em; }
		.pxd-admin__title span { font-weight: 400; font-size: 13px; color: #646970; }
		.pxd-admin__subtitle { margin: 4px 0 0; font-size: 12.5px; color: #646970; }
		.pxd-admin__badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 100px; font-size: 11.5px; font-weight: 700; border: 1px solid #dcdcde; background: #f6f7f7; color: #50575e; }
		.pxd-admin__badge--ok { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
		.pxd-admin__badge--warn { background: #fefce8; border-color: #fef08a; color: #854d0e; }

		/* Abas */
		.pxd-tabs-nav { display: flex; gap: 6px; border-bottom: 2px solid #e2e8f0; margin-bottom: 22px; flex-wrap: wrap; }
		.pxd-tab-btn { background: none; border: none; padding: 12px 18px; font-size: 13.5px; font-weight: 700; color: #64748b; cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; display: inline-flex; align-items: center; gap: 8px; transition: all .15s ease; border-radius: 6px 6px 0 0; }
		.pxd-tab-btn:hover { color: #1e293b; background: rgba(0,0,0,0.03); }
		.pxd-tab-btn.is-active { color: #087ff5; border-bottom-color: #087ff5; background: #fff; }
		.pxd-tab-pane { display: none; }
		.pxd-tab-pane.is-active { display: block; }

		/* Cards */
		.pxd-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.06); overflow: hidden; margin-bottom: 22px; }
		.pxd-card__head { padding: 16px 20px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f1; background: #fbfcfd; }
		.pxd-card__head h2 { margin: 0; font-size: 15px; font-weight: 750; color: #17213e; }
		.pxd-card__head p { margin: 3px 0 0; font-size: 12.5px; color: #64748b; }
		.pxd-card__body { padding: 20px; }
		.pxd-card .form-table th { width: 220px; font-weight: 600; color: #1e293b; vertical-align: top; padding: 14px 10px 14px 0; }
		.pxd-card input[type="text"], .pxd-card input[type="url"], .pxd-card input[type="password"], .pxd-card input[type="number"], .pxd-card select, .pxd-card textarea {
			border-radius: 7px; border: 1px solid #8c8f94; min-height: 38px; width: 100%; max-width: 540px; box-shadow: 0 1px 1px rgba(0,0,0,.04);
		}
		.pxd-card textarea { min-height: 70px; font-family: inherit; }
		.pxd-card input:focus, .pxd-card textarea:focus, .pxd-card select:focus { border-color: #087ff5; box-shadow: 0 0 0 1px #087ff5; outline: none; }
		.pxd-card .description { margin-top: 5px; color: #64748b; font-size: 12px; line-height: 1.45; }

		/* Repeater Items */
		.pxd-repeater-list { display: flex; flex-direction: column; gap: 14px; margin-bottom: 16px; }
		.pxd-repeater-row { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; padding: 16px; position: relative; transition: all .2s; }
		.pxd-repeater-row:hover { background: #fff; border-color: #94a3b8; box-shadow: 0 4px 12px rgba(0,0,0,.06); }
		.pxd-repeater-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: start; }
		.pxd-field-group { display: flex; flex-direction: column; gap: 4px; }
		.pxd-field-group label { font-size: 11.5px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.04em; }
		.pxd-field-group input, .pxd-field-group select, .pxd-field-group textarea { max-width: 100% !important; }
		.pxd-img-upload-box { display: flex; gap: 8px; align-items: center; }
		.pxd-img-preview { width: 36px; height: 36px; border-radius: 6px; border: 1px solid #cbd5e1; object-fit: contain; background: #fff; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; }
		.pxd-img-preview img { width: 100%; height: 100%; object-fit: contain; }
		.pxd-btn-remove { position: absolute; right: 12px; top: 12px; background: #fee2e2; border: 1px solid #fca5a5; color: #dc2626; border-radius: 6px; padding: 4px 9px; font-weight: 700; font-size: 12px; cursor: pointer; transition: background .15s; }
		.pxd-btn-remove:hover { background: #fecaca; }
		.pxd-btn-add { display: inline-flex; align-items: center; gap: 6px; border-radius: 8px; padding: 8px 16px; font-weight: 600; cursor: pointer; }

		/* Submit Bar */
		.pxd-submit-bar { display: flex; align-items: center; gap: 14px; background: #fff; border: 1px solid #c3c4c7; border-radius: 10px; padding: 14px 18px; box-shadow: 0 1px 3px rgba(0,0,0,.06); margin-top: 20px; }
		.pxd-submit-bar p.submit { margin: 0; padding: 0; }

		/* Box Informativo */
		.pxd-alert-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-left: 4px solid #16a34a; border-radius: 8px; padding: 14px 16px; margin-top: 14px; font-size: 12.5px; line-height: 1.6; color: #166534; }
	</style>

	<div class="wrap pxd-admin">
		<div class="pxd-admin__header">
			<div class="pxd-admin__header__left">
				<div class="pxd-admin__logo" aria-hidden="true"><span class="dashicons dashicons-dashboard" style="font-size:26px;width:26px;height:26px"></span></div>
				<div>
					<h1 class="pxd-admin__title"><?php esc_html_e( 'Proxmox Dashboard & Mapa da Infraestrutura', 'proxmox-dashboard' ); ?> <span>v<?php echo esc_html( defined( 'PROXMOX_DASHBOARD_VERSION' ) ? PROXMOX_DASHBOARD_VERSION : '2.3.0' ); ?></span></h1>
					<p class="pxd-admin__subtitle"><?php esc_html_e( 'Gerencie as credenciais da API, topologia de rede, containers LXC/VMs, serviços Docker e discos de armazenamento.', 'proxmox-dashboard' ); ?></p>
				</div>
			</div>
			<div>
				<?php if ( $is_configured ) : ?>
					<span class="pxd-admin__badge pxd-admin__badge--ok"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'API Conectada', 'proxmox-dashboard' ); ?></span>
				<?php else : ?>
					<span class="pxd-admin__badge pxd-admin__badge--warn"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Conexão Pendente', 'proxmox-dashboard' ); ?></span>
				<?php endif; ?>
				<a class="button button-secondary" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener" style="margin-left:8px"><span class="dashicons dashicons-visibility" style="margin-top:3px"></span> <?php esc_html_e( 'Abrir Mapa / Dashboard', 'proxmox-dashboard' ); ?></a>
			</div>
		</div>

		<?php if ( ! empty( $_GET['settings-updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><span class="dashicons dashicons-yes" style="color:#00a32a"></span> <?php esc_html_e( 'Configurações e itens atualizados com sucesso.', 'proxmox-dashboard' ); ?></p></div>
		<?php endif; ?>

		<?php if ( ! empty( $_GET['pxd_status'] ) ) : ?>
			<?php if ( 'import_success' === $_GET['pxd_status'] ) : ?>
				<div class="notice notice-success is-dismissible"><p><strong>✓ <?php esc_html_e( 'Importação concluída com sucesso!', 'proxmox-dashboard' ); ?></strong> <?php esc_html_e( 'Todas as configurações, containers, serviços e discos foram restaurados.', 'proxmox-dashboard' ); ?></p></div>
			<?php elseif ( 'import_error_nofile' === $_GET['pxd_status'] ) : ?>
				<div class="notice notice-error is-dismissible"><p><strong>✗ <?php esc_html_e( 'Erro na importação:', 'proxmox-dashboard' ); ?></strong> <?php esc_html_e( 'Nenhum arquivo JSON foi enviado ou o campo de texto estava vazio.', 'proxmox-dashboard' ); ?></p></div>
			<?php elseif ( 'import_error_empty' === $_GET['pxd_status'] ) : ?>
				<div class="notice notice-error is-dismissible"><p><strong>✗ <?php esc_html_e( 'Erro na importação:', 'proxmox-dashboard' ); ?></strong> <?php esc_html_e( 'O arquivo enviado está vazio.', 'proxmox-dashboard' ); ?></p></div>
			<?php elseif ( 'import_error_invalid_json' === $_GET['pxd_status'] ) : ?>
				<div class="notice notice-error is-dismissible"><p><strong>✗ <?php esc_html_e( 'Erro na importação:', 'proxmox-dashboard' ); ?></strong> <?php esc_html_e( 'O conteúdo enviado não é um JSON válido de configurações.', 'proxmox-dashboard' ); ?></p></div>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( $tested ) : ?>
			<?php if ( true === $test ) : ?>
				<div class="notice notice-success is-dismissible"><p><strong>✓ <?php esc_html_e( 'Proxmox conectado com sucesso!', 'proxmox-dashboard' ); ?></strong> <?php esc_html_e( 'A API e os endpoints responderam perfeitamente.', 'proxmox-dashboard' ); ?></p></div>
			<?php else : ?>
				<div class="notice notice-error is-dismissible"><p><strong>✗ <?php esc_html_e( 'Falha na conexão:', 'proxmox-dashboard' ); ?></strong> <code><?php echo esc_html( is_wp_error( $test ) ? $test->get_error_message() : 'Erro desconhecido' ); ?></code></p></div>
			<?php endif; ?>
		<?php endif; ?>

		<!-- Navegação por Abas -->
		<nav class="pxd-tabs-nav" id="pxd-tabs-nav">
			<button type="button" class="pxd-tab-btn is-active" data-tab="tab-conexao"><span class="dashicons dashicons-admin-network"></span> 1. Conexão Proxmox</button>
			<button type="button" class="pxd-tab-btn" data-tab="tab-topologia"><span class="dashicons dashicons-networking"></span> 2. Topologia & Servidor</button>
			<button type="button" class="pxd-tab-btn" data-tab="tab-containers"><span class="dashicons dashicons-screenoptions"></span> 3. Containers (LXC) & VMs</button>
			<button type="button" class="pxd-tab-btn" data-tab="tab-docker"><span class="dashicons dashicons-cloud"></span> 4. Serviços Docker</button>
			<button type="button" class="pxd-tab-btn" data-tab="tab-storage"><span class="dashicons dashicons-database"></span> 5. Armazenamento & Legenda</button>
			<button type="button" class="pxd-tab-btn" data-tab="tab-backup"><span class="dashicons dashicons-migrate"></span> 6. Importar / Exportar</button>
		</nav>

		<form method="post" action="options.php" id="pxd-settings-form">
			<?php settings_fields( 'proxmox_dashboard_settings_group' ); ?>

			<!-- ==========================================
			     ABA 1: CONEXÃO PROXMOX
			     ========================================== -->
			<div class="pxd-tab-pane is-active" id="tab-conexao">
				<div class="pxd-card">
					<div class="pxd-card__head">
						<div>
							<h2><?php esc_html_e( 'Credenciais de Acesso ao Proxmox VE (3 Opções)', 'proxmox-dashboard' ); ?></h2>
							<p><?php esc_html_e( 'Preencha os dados do API Token gerado no Proxmox para habilitar métricas de CPU, RAM, Disco e monitoramento dinâmico.', 'proxmox-dashboard' ); ?></p>
						</div>
					</div>
					<div class="pxd-card__body">
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="proxmox_host"><?php esc_html_e( 'Proxmox Host', 'proxmox-dashboard' ); ?></label></th>
								<td>
									<input type="url" id="proxmox_host" name="proxmox_dashboard_settings[host]" value="<?php echo esc_attr( $options['host'] ); ?>" placeholder="https://proxmox.marcovivas.online" class="regular-text code" />
									<p class="description"><?php esc_html_e( 'URL de acesso ao Proxmox VE. Exemplo: https://proxmox.marcovivas.online ou https://192.168.2.50:8006', 'proxmox-dashboard' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="proxmox_token_id"><?php esc_html_e( 'Token ID', 'proxmox-dashboard' ); ?></label></th>
								<td>
									<input type="text" id="proxmox_token_id" name="proxmox_dashboard_settings[token_id]" value="<?php echo esc_attr( $token_id_display ); ?>" placeholder="root@pam!api" class="regular-text code" />
									<p class="description"><?php esc_html_e( 'Token ID completo gerado no Proxmox no formato usuario@realm!nome. Exemplo: root@pam!api ou dashboard@pve!api', 'proxmox-dashboard' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="proxmox_token_secret"><?php esc_html_e( 'API Token Secret', 'proxmox-dashboard' ); ?></label></th>
								<td>
									<input type="password" id="proxmox_token_secret" name="proxmox_dashboard_settings[token_secret]" value="<?php echo esc_attr( $options['token_secret'] ? '******' : '' ); ?>" class="regular-text code" autocomplete="off" placeholder="••••••••••••••••" />
									<p class="description"><?php esc_html_e( 'O secret UUID gerado pelo Proxmox. Exemplo: e7911894-268d-4624-a6df-686d0a504be8. Fica armazenado com segurança no servidor e nunca é exposto ao cliente.', 'proxmox-dashboard' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="proxmox_refresh_interval"><?php esc_html_e( 'Intervalo de Atualização', 'proxmox-dashboard' ); ?></label></th>
								<td>
									<?php
									$intervals = array(
										0    => __( 'Manual (sem auto-atualização)', 'proxmox-dashboard' ),
										1    => __( '1 segundo', 'proxmox-dashboard' ),
										5    => __( '5 segundos (recomendado)', 'proxmox-dashboard' ),
										10   => __( '10 segundos', 'proxmox-dashboard' ),
										15   => __( '15 segundos', 'proxmox-dashboard' ),
										30   => __( '30 segundos', 'proxmox-dashboard' ),
										60   => __( '1 minuto', 'proxmox-dashboard' ),
										300  => __( '5 minutos', 'proxmox-dashboard' ),
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
										<input type="number" id="proxmox_refresh_interval_custom" name="proxmox_dashboard_settings[refresh_interval_custom]" value="<?php echo esc_attr( $is_custom ? $current_interval : '' ); ?>" min="1" max="86400" step="1" placeholder="ex: 120" style="width:90px;" /> <?php esc_html_e( 'segundos', 'proxmox-dashboard' ); ?>
									</span>
								</td>
							</tr>
						</table>

						<div class="pxd-alert-box">
							<strong>✅ Dica Proxmox (Privilege Separation):</strong>
							Ao criar o token em <code>Datacenter → Access → API Tokens → Add</code>, deixe a opção <strong>Privilege Separation DESATIVADA (OFF)</strong> para que o token consiga consultar nodes, recursos e storages sem restrições.
						</div>
					</div>
				</div>
			</div>

			<!-- ==========================================
			     ABA 2: TOPOLOGIA & SERVIDOR
			     ========================================== -->
			<div class="pxd-tab-pane" id="tab-topologia">
				<div class="pxd-card">
					<div class="pxd-card__head">
						<div>
							<h2><?php esc_html_e( 'Informações do Servidor Proxmox (Card do Cabeçalho)', 'proxmox-dashboard' ); ?></h2>
							<p><?php esc_html_e( 'Dados exibidos no cartão principal do servidor no topo da página.', 'proxmox-dashboard' ); ?></p>
						</div>
					</div>
					<div class="pxd-card__body">
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="pxd_server_ip"><?php esc_html_e( 'IP Local do Servidor', 'proxmox-dashboard' ); ?></label></th>
								<td>
									<input type="text" id="pxd_server_ip" name="proxmox_dashboard_settings[server_ip]" value="<?php echo esc_attr( $options['server_ip'] ); ?>" placeholder="192.168.2.50" />
									<p class="description"><?php esc_html_e( 'Exemplo: 192.168.2.50', 'proxmox-dashboard' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="pxd_server_so"><?php esc_html_e( 'Sistema Operacional / Versão', 'proxmox-dashboard' ); ?></label></th>
								<td>
									<input type="text" id="pxd_server_so" name="proxmox_dashboard_settings[server_so]" value="<?php echo esc_attr( $options['server_so'] ); ?>" placeholder="Proxmox VE 9.2.20" />
									<p class="description"><?php esc_html_e( 'Exemplo: Proxmox VE 9.2.20 (ou deixe vazio para puxar automaticamente da API).', 'proxmox-dashboard' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="pxd_server_fn"><?php esc_html_e( 'Função do Host', 'proxmox-dashboard' ); ?></label></th>
								<td>
									<input type="text" id="pxd_server_fn" name="proxmox_dashboard_settings[server_fn]" value="<?php echo esc_attr( $options['server_fn'] ); ?>" placeholder="Virtualização e Containers" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="pxd_server_mac"><?php esc_html_e( 'Endereço MAC', 'proxmox-dashboard' ); ?></label></th>
								<td>
									<input type="text" id="pxd_server_mac" name="proxmox_dashboard_settings[server_mac]" value="<?php echo esc_attr( $options['server_mac'] ); ?>" placeholder="f0:4d:a2:e6:64:5e" class="code" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="pxd_server_url"><?php esc_html_e( 'URL / Domínio Externo', 'proxmox-dashboard' ); ?></label></th>
								<td>
									<input type="text" id="pxd_server_url" name="proxmox_dashboard_settings[server_url]" value="<?php echo esc_attr( $options['server_url'] ); ?>" placeholder="proxmox.marcovivas.online" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="pxd_server_status"><?php esc_html_e( 'Mensagem / Status do Servidor', 'proxmox-dashboard' ); ?></label></th>
								<td>
									<textarea id="pxd_server_status" name="proxmox_dashboard_settings[server_status]" rows="3"><?php echo esc_textarea( $options['server_status'] ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Texto exibido no box lateral direito do cartão do servidor.', 'proxmox-dashboard' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="pxd_server_icon"><?php esc_html_e( 'Ícone / Logo do Servidor', 'proxmox-dashboard' ); ?></label></th>
								<td>
									<div class="pxd-img-upload-box">
										<div class="pxd-img-preview" id="preview_server_icon">
											<?php if ( ! empty( $options['server_icon'] ) ) : ?>
												<img src="<?php echo esc_url( $options['server_icon'] ); ?>" alt="" />
											<?php else : ?>
												<span class="dashicons dashicons-admin-site" style="color:#94a3b8"></span>
											<?php endif; ?>
										</div>
										<input type="text" id="pxd_server_icon" name="proxmox_dashboard_settings[server_icon]" value="<?php echo esc_attr( $options['server_icon'] ); ?>" placeholder="URL da imagem ou SVG" style="flex:1" />
										<button type="button" class="button pxd-media-upload-btn" data-target="pxd_server_icon" data-preview="preview_server_icon"><span class="dashicons dashicons-upload" style="margin-top:4px"></span> <?php esc_html_e( 'Selecionar Imagem', 'proxmox-dashboard' ); ?></button>
									</div>
									<p class="description"><?php esc_html_e( 'Faça upload de um SVG ou PNG pela biblioteca de mídia ou informe a URL. Deixe vazio para usar o ícone padrão.', 'proxmox-dashboard' ); ?></p>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<div class="pxd-card">
					<div class="pxd-card__head">
						<div>
							<h2><?php esc_html_e( 'Topologia e Fluxo de Rede (Diagrama)', 'proxmox-dashboard' ); ?></h2>
							<p><?php esc_html_e( 'Configure os nós exibidos no fluxo de rede: INTERNET → ROTEADOR → SWITCH → REDE LAN.', 'proxmox-dashboard' ); ?></p>
						</div>
					</div>
					<div class="pxd-card__body">
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="pxd_router_label"><?php esc_html_e( 'Rótulo do Roteador', 'proxmox-dashboard' ); ?></label></th>
								<td><input type="text" id="pxd_router_label" name="proxmox_dashboard_settings[router_label]" value="<?php echo esc_attr( $options['router_label'] ); ?>" placeholder="ROTEADOR" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="pxd_router_ip"><?php esc_html_e( 'IP do Roteador / Gateway', 'proxmox-dashboard' ); ?></label></th>
								<td><input type="text" id="pxd_router_ip" name="proxmox_dashboard_settings[router_ip]" value="<?php echo esc_attr( $options['router_ip'] ); ?>" placeholder="192.168.2.1" class="code" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="pxd_switch_label"><?php esc_html_e( 'Rótulo do Switch', 'proxmox-dashboard' ); ?></label></th>
								<td><input type="text" id="pxd_switch_label" name="proxmox_dashboard_settings[switch_label]" value="<?php echo esc_attr( $options['switch_label'] ); ?>" placeholder="SWITCH" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="pxd_lan_network"><?php esc_html_e( 'Sub-rede LAN', 'proxmox-dashboard' ); ?></label></th>
								<td><input type="text" id="pxd_lan_network" name="proxmox_dashboard_settings[lan_network]" value="<?php echo esc_attr( $options['lan_network'] ); ?>" placeholder="192.168.2.0/24" class="code" /></td>
							</tr>
						</table>
					</div>
				</div>
			</div>

			<!-- ==========================================
			     ABA 3: CONTAINERS (LXC) & VMs
			     ========================================== -->
			<div class="pxd-tab-pane" id="tab-containers">
				<div class="pxd-card">
					<div class="pxd-card__head">
						<div>
							<h2><?php esc_html_e( 'Gerenciador de Containers (LXC) & Máquinas Virtuais (VMs)', 'proxmox-dashboard' ); ?></h2>
							<p><?php esc_html_e( 'Adicione, edite ou exclua os cards dos seus containers. Se o ID coincidir com um VMID do Proxmox, o card mostrará o status ao vivo e consumo de recursos!', 'proxmox-dashboard' ); ?></p>
						</div>
					</div>
					<div class="pxd-card__body">
						<div class="pxd-repeater-list" id="pxd-containers-list">
							<?php
							$containers = isset( $options['containers'] ) && is_array( $options['containers'] ) ? $options['containers'] : array();
							if ( empty( $containers ) ) {
								$containers = array();
							}
							foreach ( $containers as $i => $item ) :
								$type = isset( $item['type'] ) ? $item['type'] : 'lxc';
								$id   = isset( $item['id'] ) ? $item['id'] : '';
								$name = isset( $item['name'] ) ? $item['name'] : '';
								$icon = isset( $item['icon'] ) ? $item['icon'] : '';
								$ip   = isset( $item['ip'] ) ? $item['ip'] : '';
								$mac  = isset( $item['mac'] ) ? $item['mac'] : '';
								$url  = isset( $item['url'] ) ? $item['url'] : '';
								$fn   = isset( $item['fn'] ) ? $item['fn'] : '';
							?>
							<div class="pxd-repeater-row" data-index="<?php echo esc_attr( $i ); ?>">
								<button type="button" class="pxd-btn-remove" title="<?php esc_attr_e( 'Remover', 'proxmox-dashboard' ); ?>">✕ Excluir</button>
								<div class="pxd-repeater-fields">
									<div class="pxd-field-group" style="max-width:130px">
										<label><?php esc_html_e( 'Tipo', 'proxmox-dashboard' ); ?></label>
										<select name="proxmox_dashboard_settings[containers][<?php echo esc_attr( $i ); ?>][type]">
											<option value="lxc" <?php selected( $type, 'lxc' ); ?>>LXC</option>
											<option value="vm" <?php selected( $type, 'vm' ); ?>>VM (QEMU)</option>
										</select>
									</div>
									<div class="pxd-field-group" style="max-width:110px">
										<label><?php esc_html_e( 'ID (VMID)', 'proxmox-dashboard' ); ?></label>
										<input type="text" name="proxmox_dashboard_settings[containers][<?php echo esc_attr( $i ); ?>][id]" value="<?php echo esc_attr( $id ); ?>" placeholder="100" class="code" />
									</div>
									<div class="pxd-field-group" style="flex:1.5">
										<label><?php esc_html_e( 'Nome do Container / VM', 'proxmox-dashboard' ); ?></label>
										<input type="text" name="proxmox_dashboard_settings[containers][<?php echo esc_attr( $i ); ?>][name]" value="<?php echo esc_attr( $name ); ?>" placeholder="Ubuntu Server + Docker" />
									</div>
									<div class="pxd-field-group" style="flex:1.5">
										<label><?php esc_html_e( 'Ícone / Imagem', 'proxmox-dashboard' ); ?></label>
										<div class="pxd-img-upload-box">
											<div class="pxd-img-preview" id="preview_c_<?php echo esc_attr( $i ); ?>">
												<?php if ( $icon ) : ?>
													<img src="<?php echo esc_url( $icon ); ?>" alt="" />
												<?php else : ?>
													<span class="dashicons dashicons-format-image" style="color:#94a3b8"></span>
												<?php endif; ?>
											</div>
											<input type="text" id="input_c_<?php echo esc_attr( $i ); ?>" name="proxmox_dashboard_settings[containers][<?php echo esc_attr( $i ); ?>][icon]" value="<?php echo esc_attr( $icon ); ?>" placeholder="URL ou upload" style="flex:1" />
											<button type="button" class="button pxd-media-upload-btn" data-target="input_c_<?php echo esc_attr( $i ); ?>" data-preview="preview_c_<?php echo esc_attr( $i ); ?>">📁</button>
										</div>
									</div>
									<div class="pxd-field-group">
										<label><?php esc_html_e( 'IP Local', 'proxmox-dashboard' ); ?></label>
										<input type="text" name="proxmox_dashboard_settings[containers][<?php echo esc_attr( $i ); ?>][ip]" value="<?php echo esc_attr( $ip ); ?>" placeholder="192.168.2.100" class="code" />
									</div>
									<div class="pxd-field-group">
										<label><?php esc_html_e( 'MAC Address', 'proxmox-dashboard' ); ?></label>
										<input type="text" name="proxmox_dashboard_settings[containers][<?php echo esc_attr( $i ); ?>][mac]" value="<?php echo esc_attr( $mac ); ?>" placeholder="02:AA:BB:CC:01:00" class="code" />
									</div>
									<div class="pxd-field-group">
										<label><?php esc_html_e( 'URL / Domínio Externo', 'proxmox-dashboard' ); ?></label>
										<input type="text" name="proxmox_dashboard_settings[containers][<?php echo esc_attr( $i ); ?>][url]" value="<?php echo esc_attr( $url ); ?>" placeholder="ubuntu.marcovivas.online" />
									</div>
									<div class="pxd-field-group" style="grid-column: 1 / -1">
										<label><?php esc_html_e( 'Função / Descrição', 'proxmox-dashboard' ); ?></label>
										<input type="text" name="proxmox_dashboard_settings[containers][<?php echo esc_attr( $i ); ?>][fn]" value="<?php echo esc_attr( $fn ); ?>" placeholder="Hospeda todos os serviços Docker" />
									</div>
								</div>
							</div>
							<?php endforeach; ?>
						</div>

						<p style="margin: 12px 0 0;">
							<button type="button" class="button button-primary pxd-btn-add" id="btn-add-container"><span class="dashicons dashicons-plus-alt2" style="margin-top:2px"></span> <?php esc_html_e( 'Adicionar Container / VM', 'proxmox-dashboard' ); ?></button>
						</p>
					</div>
				</div>
			</div>

			<!-- ==========================================
			     ABA 4: SERVIÇOS DOCKER
			     ========================================== -->
			<div class="pxd-tab-pane" id="tab-docker">
				<div class="pxd-card">
					<div class="pxd-card__head">
						<div>
							<h2><?php esc_html_e( 'Gerenciador de Serviços Docker (Expostos / Portas)', 'proxmox-dashboard' ); ?></h2>
							<p><?php esc_html_e( 'Cadastre os serviços que rodam em containers (ex.: Portainer, Gitea, Jellyfin). O tema realiza health-check HTTP em tempo real!', 'proxmox-dashboard' ); ?></p>
						</div>
					</div>
					<div class="pxd-card__body">
						<div class="pxd-repeater-list" id="pxd-docker-list">
							<?php
							$docker = isset( $options['docker_services'] ) && is_array( $options['docker_services'] ) ? $options['docker_services'] : array();
							if ( empty( $docker ) ) {
								$docker = array();
							}
							foreach ( $docker as $i => $item ) :
								$name   = isset( $item['name'] ) ? $item['name'] : '';
								$icon   = isset( $item['icon'] ) ? $item['icon'] : '';
								$port   = isset( $item['port'] ) ? $item['port'] : '';
								$domain = isset( $item['domain'] ) ? $item['domain'] : '';
								$check  = isset( $item['check_url'] ) ? $item['check_url'] : '';
							?>
							<div class="pxd-repeater-row" data-index="<?php echo esc_attr( $i ); ?>">
								<button type="button" class="pxd-btn-remove" title="<?php esc_attr_e( 'Remover', 'proxmox-dashboard' ); ?>">✕ Excluir</button>
								<div class="pxd-repeater-fields">
									<div class="pxd-field-group" style="flex:1.5">
										<label><?php esc_html_e( 'Nome do Serviço', 'proxmox-dashboard' ); ?></label>
										<input type="text" name="proxmox_dashboard_settings[docker_services][<?php echo esc_attr( $i ); ?>][name]" value="<?php echo esc_attr( $name ); ?>" placeholder="Portainer" />
									</div>
									<div class="pxd-field-group" style="flex:1.5">
										<label><?php esc_html_e( 'Ícone / Logo', 'proxmox-dashboard' ); ?></label>
										<div class="pxd-img-upload-box">
											<div class="pxd-img-preview" id="preview_d_<?php echo esc_attr( $i ); ?>">
												<?php if ( $icon ) : ?>
													<img src="<?php echo esc_url( $icon ); ?>" alt="" />
												<?php else : ?>
													<span class="dashicons dashicons-cloud" style="color:#94a3b8"></span>
												<?php endif; ?>
											</div>
											<input type="text" id="input_d_<?php echo esc_attr( $i ); ?>" name="proxmox_dashboard_settings[docker_services][<?php echo esc_attr( $i ); ?>][icon]" value="<?php echo esc_attr( $icon ); ?>" placeholder="URL ou upload" style="flex:1" />
											<button type="button" class="button pxd-media-upload-btn" data-target="input_d_<?php echo esc_attr( $i ); ?>" data-preview="preview_d_<?php echo esc_attr( $i ); ?>">📁</button>
										</div>
									</div>
									<div class="pxd-field-group" style="max-width:140px">
										<label><?php esc_html_e( 'Porta no Host', 'proxmox-dashboard' ); ?></label>
										<input type="text" name="proxmox_dashboard_settings[docker_services][<?php echo esc_attr( $i ); ?>][port]" value="<?php echo esc_attr( $port ); ?>" placeholder="9443" class="code" />
									</div>
									<div class="pxd-field-group" style="flex:1.8">
										<label><?php esc_html_e( 'Domínio Externo (Cloudflare / URL)', 'proxmox-dashboard' ); ?></label>
										<input type="text" name="proxmox_dashboard_settings[docker_services][<?php echo esc_attr( $i ); ?>][domain]" value="<?php echo esc_attr( $domain ); ?>" placeholder="portainer.marcovivas.online" />
									</div>
									<div class="pxd-field-group" style="flex:1.8">
										<label><?php esc_html_e( 'URL de Checagem HTTP (Health-Check)', 'proxmox-dashboard' ); ?></label>
										<input type="url" name="proxmox_dashboard_settings[docker_services][<?php echo esc_attr( $i ); ?>][check_url]" value="<?php echo esc_attr( $check ); ?>" placeholder="https://portainer.marcovivas.online (opcional)" />
									</div>
								</div>
							</div>
							<?php endforeach; ?>
						</div>

						<p style="margin: 12px 0 0;">
							<button type="button" class="button button-primary pxd-btn-add" id="btn-add-docker"><span class="dashicons dashicons-plus-alt2" style="margin-top:2px"></span> <?php esc_html_e( 'Adicionar Serviço Docker', 'proxmox-dashboard' ); ?></button>
						</p>
					</div>
				</div>
			</div>

			<!-- ==========================================
			     ABA 5: ARMAZENAMENTO & LEGENDA
			     ========================================== -->
			<div class="pxd-tab-pane" id="tab-storage">
				<div class="pxd-card">
					<div class="pxd-card__head">
						<div>
							<h2><?php esc_html_e( 'Discos de Armazenamento Físico', 'proxmox-dashboard' ); ?></h2>
							<p><?php esc_html_e( 'Cadastre as unidades de armazenamento (SSD, HDD, NVMe) e as partições/containers alocados.', 'proxmox-dashboard' ); ?></p>
						</div>
					</div>
					<div class="pxd-card__body">
						<div class="pxd-repeater-list" id="pxd-storage-list">
							<?php
							$storages = isset( $options['storages_custom'] ) && is_array( $options['storages_custom'] ) ? $options['storages_custom'] : array();
							if ( empty( $storages ) ) {
								$storages = array();
							}
							foreach ( $storages as $i => $item ) :
								$drive = isset( $item['drive'] ) ? $item['drive'] : 'SSD';
								$title = isset( $item['title'] ) ? $item['title'] : '';
								$items = isset( $item['items'] ) ? $item['items'] : '';
							?>
							<div class="pxd-repeater-row" data-index="<?php echo esc_attr( $i ); ?>">
								<button type="button" class="pxd-btn-remove" title="<?php esc_attr_e( 'Remover', 'proxmox-dashboard' ); ?>">✕ Excluir</button>
								<div class="pxd-repeater-fields">
									<div class="pxd-field-group" style="max-width:140px">
										<label><?php esc_html_e( 'Tipo de Drive', 'proxmox-dashboard' ); ?></label>
										<input type="text" name="proxmox_dashboard_settings[storages_custom][<?php echo esc_attr( $i ); ?>][drive]" value="<?php echo esc_attr( $drive ); ?>" placeholder="SSD ou HDD" />
									</div>
									<div class="pxd-field-group" style="flex:2">
										<label><?php esc_html_e( 'Título do Disco / Ponto de Montagem', 'proxmox-dashboard' ); ?></label>
										<input type="text" name="proxmox_dashboard_settings[storages_custom][<?php echo esc_attr( $i ); ?>][title]" value="<?php echo esc_attr( $title ); ?>" placeholder="SSD /dev/sda (256 GB)" />
									</div>
									<div class="pxd-field-group" style="grid-column: 1 / -1">
										<label><?php esc_html_e( 'Itens / Alocações (um por linha)', 'proxmox-dashboard' ); ?></label>
										<textarea name="proxmox_dashboard_settings[storages_custom][<?php echo esc_attr( $i ); ?>][items]" rows="3" placeholder="Proxmox&#10;Storage: local-lvm&#10;Containers (LXC): 100, 101, 102..."><?php echo esc_textarea( $items ); ?></textarea>
									</div>
								</div>
							</div>
							<?php endforeach; ?>
						</div>

						<p style="margin: 12px 0 0;">
							<button type="button" class="button button-primary pxd-btn-add" id="btn-add-storage"><span class="dashicons dashicons-plus-alt2" style="margin-top:2px"></span> <?php esc_html_e( 'Adicionar Disco de Armazenamento', 'proxmox-dashboard' ); ?></button>
						</p>
					</div>
				</div>

				<div class="pxd-card">
					<div class="pxd-card__head">
						<div>
							<h2><?php esc_html_e( 'Itens da Legenda', 'proxmox-dashboard' ); ?></h2>
							<p><?php esc_html_e( 'Personalize as siglas e descrições exibidas na caixa de legenda do rodapé.', 'proxmox-dashboard' ); ?></p>
						</div>
					</div>
					<div class="pxd-card__body">
						<div class="pxd-repeater-list" id="pxd-legend-list">
							<?php
							$legend = isset( $options['legend_items'] ) && is_array( $options['legend_items'] ) ? $options['legend_items'] : array();
							foreach ( $legend as $i => $item ) :
								$tag   = isset( $item['tag'] ) ? $item['tag'] : '';
								$color = isset( $item['color'] ) ? $item['color'] : 'lxc';
								$label = isset( $item['label'] ) ? $item['label'] : '';
							?>
							<div class="pxd-repeater-row" data-index="<?php echo esc_attr( $i ); ?>">
								<button type="button" class="pxd-btn-remove" title="<?php esc_attr_e( 'Remover', 'proxmox-dashboard' ); ?>">✕ Excluir</button>
								<div class="pxd-repeater-fields">
									<div class="pxd-field-group" style="max-width:140px">
										<label><?php esc_html_e( 'Sigla / Tag', 'proxmox-dashboard' ); ?></label>
										<input type="text" name="proxmox_dashboard_settings[legend_items][<?php echo esc_attr( $i ); ?>][tag]" value="<?php echo esc_attr( $tag ); ?>" placeholder="LXC" />
									</div>
									<div class="pxd-field-group" style="max-width:140px">
										<label><?php esc_html_e( 'Estilo / Cor', 'proxmox-dashboard' ); ?></label>
										<select name="proxmox_dashboard_settings[legend_items][<?php echo esc_attr( $i ); ?>][color]">
											<option value="lxc" <?php selected( $color, 'lxc' ); ?>>Azul (LXC)</option>
											<option value="ip" <?php selected( $color, 'ip' ); ?>>Verde (IP)</option>
											<option value="url" <?php selected( $color, 'url' ); ?>>Laranja (URL)</option>
											<option value="vm" <?php selected( $color, 'vm' ); ?>>Roxo (VM)</option>
											<option value="mac" <?php selected( $color, 'mac' ); ?>>Ciano (MAC)</option>
											<option value="port" <?php selected( $color, 'port' ); ?>>Âmbar (Porta)</option>
										</select>
									</div>
									<div class="pxd-field-group" style="flex:2">
										<label><?php esc_html_e( 'Descrição', 'proxmox-dashboard' ); ?></label>
										<input type="text" name="proxmox_dashboard_settings[legend_items][<?php echo esc_attr( $i ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" placeholder="Linux Container" />
									</div>
								</div>
							</div>
							<?php endforeach; ?>
						</div>

						<p style="margin: 12px 0 0;">
							<button type="button" class="button button-primary pxd-btn-add" id="btn-add-legend"><span class="dashicons dashicons-plus-alt2" style="margin-top:2px"></span> <?php esc_html_e( 'Adicionar Item à Legenda', 'proxmox-dashboard' ); ?></button>
						</p>
					</div>
				</div>
			</div>

			<div class="pxd-submit-bar" id="pxd-submit-bar">
				<?php submit_button( __( 'Salvar Todas as Configurações', 'proxmox-dashboard' ), 'primary', 'submit', false, array( 'id' => 'btn-save-all' ) ); ?>
				<span class="description"><?php esc_html_e( 'As alterações são aplicadas imediatamente no dashboard e no mapa de infraestrutura.', 'proxmox-dashboard' ); ?></span>
			</div>
		</form>

		<!-- ==========================================
		     ABA 6: IMPORTAR / EXPORTAR (BACKUP)
		     ========================================== -->
		<div class="pxd-tab-pane" id="tab-backup">
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 20px;">
				
				<!-- CARD EXPORTAR -->
				<div class="pxd-card" style="margin-bottom:0">
					<div class="pxd-card__head">
						<div>
							<h2><span class="dashicons dashicons-download" style="color:#087ff5"></span> <?php esc_html_e( 'Exportar Configurações (Backup)', 'proxmox-dashboard' ); ?></h2>
							<p><?php esc_html_e( 'Baixe um arquivo JSON com todas as configurações do dashboard para backup ou migração.', 'proxmox-dashboard' ); ?></p>
						</div>
					</div>
					<div class="pxd-card__body">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="proxmox_dashboard_export">
							<?php wp_nonce_field( 'proxmox_dashboard_export_action', 'proxmox_dashboard_export_nonce' ); ?>

							<p style="margin-top:0; font-size:13px; color:#475569; line-height:1.6;">
								<?php esc_html_e( 'O arquivo de exportação inclui todos os itens cadastrados no painel:', 'proxmox-dashboard' ); ?>
							</p>
							<ul style="list-style:disc; margin-left:20px; font-size:12.5px; color:#64748b; line-height:1.6;">
								<li><strong><?php esc_html_e( 'Conexão Proxmox:', 'proxmox-dashboard' ); ?></strong> <?php esc_html_e( 'Host, Token ID e intervalo de atualização.', 'proxmox-dashboard' ); ?></li>
								<li><strong><?php esc_html_e( 'Topologia & Servidor:', 'proxmox-dashboard' ); ?></strong> <?php esc_html_e( 'IPs, MAC, rótulos de rede, switch e roteador.', 'proxmox-dashboard' ); ?></li>
								<li><strong><?php esc_html_e( 'Containers & VMs:', 'proxmox-dashboard' ); ?></strong> <?php esc_html_e( 'Todos os containers LXC e VMs com nomes, ícones e IPs.', 'proxmox-dashboard' ); ?></li>
								<li><strong><?php esc_html_e( 'Serviços Docker:', 'proxmox-dashboard' ); ?></strong> <?php esc_html_e( 'Portas, domínios e URLs de health-check.', 'proxmox-dashboard' ); ?></li>
								<li><strong><?php esc_html_e( 'Armazenamento & Legenda:', 'proxmox-dashboard' ); ?></strong> <?php esc_html_e( 'Discos SSD/HDD cadastrados e cores da legenda.', 'proxmox-dashboard' ); ?></li>
							</ul>

							<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; margin:16px 0;">
								<label style="display:flex; align-items:flex-start; gap:8px; font-size:13px; font-weight:600; color:#1e293b; cursor:pointer;">
									<input type="checkbox" name="export_include_secret" value="1" checked style="margin-top:2px;">
									<span>
										<?php esc_html_e( 'Incluir API Token Secret (senha)', 'proxmox-dashboard' ); ?><br>
										<span style="font-weight:400; font-size:12px; color:#64748b;">
											<?php esc_html_e( 'Desmarque caso deseje compartilhar o arquivo publicamente ou enviar para terceiros.', 'proxmox-dashboard' ); ?>
										</span>
									</span>
								</label>
							</div>

							<button type="submit" class="button button-primary button-hero" style="display:inline-flex; align-items:center; gap:8px; font-size:13.5px; height:auto; padding:8px 18px;">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Baixar Arquivo de Backup (.json)', 'proxmox-dashboard' ); ?>
							</button>
						</form>
					</div>
				</div>

				<!-- CARD IMPORTAR -->
				<div class="pxd-card" style="margin-bottom:0">
					<div class="pxd-card__head">
						<div>
							<h2><span class="dashicons dashicons-upload" style="color:#16a34a"></span> <?php esc_html_e( 'Importar Configurações (Restaurar)', 'proxmox-dashboard' ); ?></h2>
							<p><?php esc_html_e( 'Restaure seu dashboard a partir de um arquivo .json ou cole o código JSON.', 'proxmox-dashboard' ); ?></p>
						</div>
					</div>
					<div class="pxd-card__body">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
							<input type="hidden" name="action" value="proxmox_dashboard_import">
							<?php wp_nonce_field( 'proxmox_dashboard_import_action', 'proxmox_dashboard_import_nonce' ); ?>

							<div style="margin-bottom:14px;">
								<label for="pxd_settings_file" style="display:block; font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:6px;">
									<?php esc_html_e( 'Opção 1: Selecionar Arquivo JSON', 'proxmox-dashboard' ); ?>
								</label>
								<input type="file" name="settings_file" id="pxd_settings_file" accept=".json,application/json" style="width:100%; border:1px solid #cbd5e1; border-radius:6px; padding:8px; background:#fff;">
							</div>

							<div style="margin-bottom:14px;">
								<label for="pxd_settings_json_text" style="display:block; font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:6px;">
									<?php esc_html_e( 'Opção 2: Ou Cole o Conteúdo JSON Diretamente', 'proxmox-dashboard' ); ?>
								</label>
								<textarea name="settings_json_text" id="pxd_settings_json_text" rows="4" placeholder="<?php esc_attr_e( 'Cole aqui o conteúdo do arquivo JSON (opcional caso selecione o arquivo acima)...', 'proxmox-dashboard' ); ?>" style="width:100%; font-family:monospace; font-size:12px;"></textarea>
							</div>

							<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; margin:16px 0;">
								<label style="display:flex; align-items:flex-start; gap:8px; font-size:13px; font-weight:600; color:#1e293b; cursor:pointer;">
									<input type="checkbox" name="preserve_credentials" value="1" checked style="margin-top:2px;">
									<span>
										<?php esc_html_e( 'Preservar credenciais Proxmox locais atuais', 'proxmox-dashboard' ); ?><br>
										<span style="font-weight:400; font-size:12px; color:#64748b;">
											<?php esc_html_e( 'Mantém Host, Token ID e Secret deste servidor intactos, aplicando apenas os containers, serviços e discos do arquivo.', 'proxmox-dashboard' ); ?>
										</span>
									</span>
								</label>
							</div>

							<div class="pxd-alert-box" style="background:#fffbeb; border-color:#fde68a; border-left-color:#d97706; color:#92400e; margin:0 0 16px 0; font-size:12px;">
								<strong>⚠️ <?php esc_html_e( 'Atenção:', 'proxmox-dashboard' ); ?></strong>
								<?php esc_html_e( 'A importação substituirá os dados atuais de containers, serviços Docker, armazenamento e topologia. Recomendamos fazer um backup antes.', 'proxmox-dashboard' ); ?>
							</div>

							<button type="submit" class="button button-secondary button-hero" style="display:inline-flex; align-items:center; gap:8px; font-size:13.5px; height:auto; padding:8px 18px; border-color:#16a34a; color:#16a34a;" onclick="return confirm('<?php echo esc_js( __( 'Tem certeza que deseja importar estas configurações? Os itens e cadastros atuais serão substituídos.', 'proxmox-dashboard' ) ); ?>');">
								<span class="dashicons dashicons-upload"></span>
								<?php esc_html_e( 'Importar e Restaurar Configurações', 'proxmox-dashboard' ); ?>
							</button>
						</form>
					</div>
				</div>
			</div>
		</div>

		<!-- Testar conexão -->
		<div class="pxd-card" style="margin-top:22px" id="pxd-test-card">
			<div class="pxd-card__head">
				<div>
					<h2><?php esc_html_e( 'Testar Conexão com o Proxmox VE', 'proxmox-dashboard' ); ?></h2>
					<p><?php esc_html_e( 'Executa um teste em tempo real consultando o cluster do Proxmox para validar se o Host, Token ID e Secret estão corretos.', 'proxmox-dashboard' ); ?></p>
				</div>
			</div>
			<div class="pxd-card__body">
				<form method="post">
					<?php wp_nonce_field( 'proxmox_dashboard_test' ); ?>
					<?php submit_button( __( 'Testar Conexão Agora', 'proxmox-dashboard' ), 'secondary', 'proxmox_dashboard_test', false ); ?>
					<span class="description" style="margin-left:12px"><?php esc_html_e( 'Certifique-se de ter salvo as configurações acima antes de testar.', 'proxmox-dashboard' ); ?></span>
				</form>
			</div>
		</div>
	</div>

	<!-- Script de Gestão Dinâmica (Abas, Upload de Mídia e Repeaters) -->
	<script>
	(function() {
		// 1. Abas e Visibilidade
		var tabBtns = document.querySelectorAll('.pxd-tab-btn');
		var tabPanes = document.querySelectorAll('.pxd-tab-pane');
		var submitBar = document.getElementById('pxd-submit-bar');
		var testCard = document.getElementById('pxd-test-card');

		function switchTab(target) {
			tabBtns.forEach(function(b) { b.classList.remove('is-active'); });
			tabPanes.forEach(function(p) { p.classList.remove('is-active'); });

			var activeBtn = document.querySelector('.pxd-tab-btn[data-tab="' + target + '"]');
			if (activeBtn) {
				activeBtn.classList.add('is-active');
			}
			var pane = document.getElementById(target);
			if (pane) {
				pane.classList.add('is-active');
			}

			// Esconde botão global de salvar na aba de backup/importação
			if (submitBar) {
				submitBar.style.display = (target === 'tab-backup') ? 'none' : 'flex';
			}
			// Mostra o card de teste de conexão apenas na aba 1 (Conexão)
			if (testCard) {
				testCard.style.display = (target === 'tab-conexao') ? 'block' : 'none';
			}
		}

		tabBtns.forEach(function(btn) {
			btn.addEventListener('click', function() {
				var target = this.getAttribute('data-tab');
				switchTab(target);
				if (window.history && window.history.replaceState) {
					var newUrl = new URL(window.location.href);
					newUrl.searchParams.set('tab', target);
					window.history.replaceState(null, '', newUrl.toString());
				}
			});
		});

		// Ativação da aba inicial por URL (?tab=...) ou padrão
		var urlParams = new URLSearchParams(window.location.search);
		var initialTab = urlParams.get('tab') || (window.location.hash ? window.location.hash.replace('#', '') : 'tab-conexao');
		switchTab(initialTab);

		// Notificação visual ao escolher arquivo JSON
		var fileInput = document.getElementById('pxd_settings_file');
		var jsonTextarea = document.getElementById('pxd_settings_json_text');
		if (fileInput && jsonTextarea) {
			fileInput.addEventListener('change', function() {
				if (this.files && this.files[0]) {
					jsonTextarea.placeholder = '<?php echo esc_js( __( 'Arquivo selecionado: ', 'proxmox-dashboard' ) ); ?>' + this.files[0].name + ' (o arquivo terá prioridade)';
				}
			});
		}

		// 2. Upload de Imagem com Media Library
		document.addEventListener('click', function(e) {
			var btn = e.target.closest('.pxd-media-upload-btn');
			if (!btn) return;
			e.preventDefault();
			var targetId = btn.getAttribute('data-target');
			var previewId = btn.getAttribute('data-preview');
			var inputEl = document.getElementById(targetId);
			var previewEl = document.getElementById(previewId);

			if (typeof wp !== 'undefined' && wp.media) {
				var frame = wp.media({
					title: '<?php echo esc_js( __( 'Selecionar Ícone / Imagem', 'proxmox-dashboard' ) ); ?>',
					button: { text: '<?php echo esc_js( __( 'Usar Imagem', 'proxmox-dashboard' ) ); ?>' },
					multiple: false,
					library: { type: 'image' }
				});
				frame.on('select', function() {
					var attachment = frame.state().get('selection').first().toJSON();
					if (inputEl) {
						inputEl.value = attachment.url;
						inputEl.dispatchEvent(new Event('input', { bubbles: true }));
					}
					if (previewEl) {
						previewEl.innerHTML = '<img src="' + attachment.url + '" alt="" />';
					}
				});
				frame.open();
			}
		});

		// 3. Excluir Linha de Repeater
		document.addEventListener('click', function(e) {
			var btn = e.target.closest('.pxd-btn-remove');
			if (!btn) return;
			var row = btn.closest('.pxd-repeater-row');
			if (row && confirm('<?php echo esc_js( __( 'Deseja realmente remover este item?', 'proxmox-dashboard' ) ); ?>')) {
				row.remove();
			}
		});

		// 4. Adicionar Container / VM
		var btnAddContainer = document.getElementById('btn-add-container');
		var containersList = document.getElementById('pxd-containers-list');
		if (btnAddContainer && containersList) {
			btnAddContainer.addEventListener('click', function() {
				var idx = Date.now();
				var html = '<div class="pxd-repeater-row" data-index="' + idx + '">' +
					'<button type="button" class="pxd-btn-remove" title="Remover">✕ Excluir</button>' +
					'<div class="pxd-repeater-fields">' +
						'<div class="pxd-field-group" style="max-width:130px"><label><?php echo esc_js( __( 'Tipo', 'proxmox-dashboard' ) ); ?></label><select name="proxmox_dashboard_settings[containers][' + idx + '][type]"><option value="lxc">LXC</option><option value="vm">VM (QEMU)</option></select></div>' +
						'<div class="pxd-field-group" style="max-width:110px"><label><?php echo esc_js( __( 'ID (VMID)', 'proxmox-dashboard' ) ); ?></label><input type="text" name="proxmox_dashboard_settings[containers][' + idx + '][id]" placeholder="100" class="code" /></div>' +
						'<div class="pxd-field-group" style="flex:1.5"><label><?php echo esc_js( __( 'Nome do Container / VM', 'proxmox-dashboard' ) ); ?></label><input type="text" name="proxmox_dashboard_settings[containers][' + idx + '][name]" placeholder="Novo Container" /></div>' +
						'<div class="pxd-field-group" style="flex:1.5"><label><?php echo esc_js( __( 'Ícone / Imagem', 'proxmox-dashboard' ) ); ?></label><div class="pxd-img-upload-box"><div class="pxd-img-preview" id="preview_c_' + idx + '"><span class="dashicons dashicons-format-image" style="color:#94a3b8"></span></div><input type="text" id="input_c_' + idx + '" name="proxmox_dashboard_settings[containers][' + idx + '][icon]" placeholder="URL ou upload" style="flex:1" /><button type="button" class="button pxd-media-upload-btn" data-target="input_c_' + idx + '" data-preview="preview_c_' + idx + '">📁</button></div></div>' +
						'<div class="pxd-field-group"><label><?php echo esc_js( __( 'IP Local', 'proxmox-dashboard' ) ); ?></label><input type="text" name="proxmox_dashboard_settings[containers][' + idx + '][ip]" placeholder="192.168.2.100" class="code" /></div>' +
						'<div class="pxd-field-group"><label><?php echo esc_js( __( 'MAC Address', 'proxmox-dashboard' ) ); ?></label><input type="text" name="proxmox_dashboard_settings[containers][' + idx + '][mac]" placeholder="02:AA:BB:CC:01:00" class="code" /></div>' +
						'<div class="pxd-field-group"><label><?php echo esc_js( __( 'URL / Domínio Externo', 'proxmox-dashboard' ) ); ?></label><input type="text" name="proxmox_dashboard_settings[containers][' + idx + '][url]" placeholder="servico.marcovivas.online" /></div>' +
						'<div class="pxd-field-group" style="grid-column: 1 / -1"><label><?php echo esc_js( __( 'Função / Descrição', 'proxmox-dashboard' ) ); ?></label><input type="text" name="proxmox_dashboard_settings[containers][' + idx + '][fn]" placeholder="Descrição do serviço" /></div>' +
					'</div>' +
				'</div>';
				containersList.insertAdjacentHTML('beforeend', html);
			});
		}

		// 5. Adicionar Serviço Docker
		var btnAddDocker = document.getElementById('btn-add-docker');
		var dockerList = document.getElementById('pxd-docker-list');
		if (btnAddDocker && dockerList) {
			btnAddDocker.addEventListener('click', function() {
				var idx = Date.now();
				var html = '<div class="pxd-repeater-row" data-index="' + idx + '">' +
					'<button type="button" class="pxd-btn-remove" title="Remover">✕ Excluir</button>' +
					'<div class="pxd-repeater-fields">' +
						'<div class="pxd-field-group" style="flex:1.5"><label><?php echo esc_js( __( 'Nome do Serviço', 'proxmox-dashboard' ) ); ?></label><input type="text" name="proxmox_dashboard_settings[docker_services][' + idx + '][name]" placeholder="Novo Serviço" /></div>' +
						'<div class="pxd-field-group" style="flex:1.5"><label><?php echo esc_js( __( 'Ícone / Logo', 'proxmox-dashboard' ) ); ?></label><div class="pxd-img-upload-box"><div class="pxd-img-preview" id="preview_d_' + idx + '"><span class="dashicons dashicons-cloud" style="color:#94a3b8"></span></div><input type="text" id="input_d_' + idx + '" name="proxmox_dashboard_settings[docker_services][' + idx + '][icon]" placeholder="URL ou upload" style="flex:1" /><button type="button" class="button pxd-media-upload-btn" data-target="input_d_' + idx + '" data-preview="preview_d_' + idx + '">📁</button></div></div>' +
						'<div class="pxd-field-group" style="max-width:140px"><label><?php echo esc_js( __( 'Porta no Host', 'proxmox-dashboard' ) ); ?></label><input type="text" name="proxmox_dashboard_settings[docker_services][' + idx + '][port]" placeholder="8080" class="code" /></div>' +
						'<div class="pxd-field-group" style="flex:1.8"><label><?php echo esc_js( __( 'Domínio Externo (Cloudflare / URL)', 'proxmox-dashboard' ) ); ?></label><input type="text" name="proxmox_dashboard_settings[docker_services][' + idx + '][domain]" placeholder="app.marcovivas.online" /></div>' +
						'<div class="pxd-field-group" style="flex:1.8"><label><?php echo esc_js( __( 'URL de Checagem HTTP (Health-Check)', 'proxmox-dashboard' ) ); ?></label><input type="url" name="proxmox_dashboard_settings[docker_services][' + idx + '][check_url]" placeholder="https://app.marcovivas.online (opcional)" /></div>' +
					'</div>' +
				'</div>';
				dockerList.insertAdjacentHTML('beforeend', html);
			});
		}

		// 6. Adicionar Armazenamento
		var btnAddStorage = document.getElementById('btn-add-storage');
		var storageList = document.getElementById('pxd-storage-list');
		if (btnAddStorage && storageList) {
			btnAddStorage.addEventListener('click', function() {
				var idx = Date.now();
				var html = '<div class="pxd-repeater-row" data-index="' + idx + '">' +
					'<button type="button" class="pxd-btn-remove" title="Remover">✕ Excluir</button>' +
					'<div class="pxd-repeater-fields">' +
						'<div class="pxd-field-group" style="max-width:140px"><label><?php echo esc_js( __( 'Tipo de Drive', 'proxmox-dashboard' ) ); ?></label><input type="text" name="proxmox_dashboard_settings[storages_custom][' + idx + '][drive]" placeholder="SSD ou HDD" value="SSD" /></div>' +
						'<div class="pxd-field-group" style="flex:2"><label><?php echo esc_js( __( 'Título do Disco / Ponto de Montagem', 'proxmox-dashboard' ) ); ?></label><input type="text" name="proxmox_dashboard_settings[storages_custom][' + idx + '][title]" placeholder="SSD /dev/sda (256 GB)" /></div>' +
						'<div class="pxd-field-group" style="grid-column: 1 / -1"><label><?php echo esc_js( __( 'Itens / Alocações (um por linha)', 'proxmox-dashboard' ) ); ?></label><textarea name="proxmox_dashboard_settings[storages_custom][' + idx + '][items]" rows="3" placeholder="Proxmox&#10;Storage: local-lvm..."></textarea></div>' +
					'</div>' +
				'</div>';
				storageList.insertAdjacentHTML('beforeend', html);
			});
		}

		// 7. Adicionar Legenda
		var btnAddLegend = document.getElementById('btn-add-legend');
		var legendList = document.getElementById('pxd-legend-list');
		if (btnAddLegend && legendList) {
			btnAddLegend.addEventListener('click', function() {
				var idx = Date.now();
				var html = '<div class="pxd-repeater-row" data-index="' + idx + '">' +
					'<button type="button" class="pxd-btn-remove" title="Remover">✕ Excluir</button>' +
					'<div class="pxd-repeater-fields">' +
						'<div class="pxd-field-group" style="max-width:140px"><label><?php echo esc_js( __( 'Sigla / Tag', 'proxmox-dashboard' ) ); ?></label><input type="text" name="proxmox_dashboard_settings[legend_items][' + idx + '][tag]" placeholder="TAG" /></div>' +
						'<div class="pxd-field-group" style="max-width:140px"><label><?php echo esc_js( __( 'Estilo / Cor', 'proxmox-dashboard' ) ); ?></label><select name="proxmox_dashboard_settings[legend_items][' + idx + '][color]"><option value="lxc">Azul (LXC)</option><option value="ip">Verde (IP)</option><option value="url">Laranja (URL)</option><option value="vm">Roxo (VM)</option><option value="mac">Ciano (MAC)</option><option value="port">Âmbar (Porta)</option></select></div>' +
						'<div class="pxd-field-group" style="flex:2"><label><?php echo esc_js( __( 'Descrição', 'proxmox-dashboard' ) ); ?></label><input type="text" name="proxmox_dashboard_settings[legend_items][' + idx + '][label]" placeholder="Descrição da tag" /></div>' +
					'</div>' +
				'</div>';
				legendList.insertAdjacentHTML('beforeend', html);
			});
		}

		// Refresh custom
		var sel = document.getElementById('proxmox_refresh_interval');
		var wrap = document.getElementById('proxmox_refresh_custom_wrap');
		if (sel && wrap) {
			sel.addEventListener('change', function() {
				wrap.style.display = (this.value === 'custom') ? '' : 'none';
			});
		}
	})();
	</script>
	<?php
}
