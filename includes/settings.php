<?php
/**
 * Admin settings for Proxmox VE Dashboard.
 *
 * @package Proxmox_VE_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pvd_register_menu() {
	add_menu_page(
		__( 'Proxmox VE Dashboard', 'proxmox-ve-dashboard' ),
		__( 'Proxmox VE', 'proxmox-ve-dashboard' ),
		'manage_options',
		'proxmox-ve-dashboard',
		'pvd_settings_page',
		'dashicons-screenoptions',
		58
	);
}
add_action( 'admin_menu', 'pvd_register_menu' );

function pvd_register_settings() {
	register_setting(
		'pvd_settings_group',
		PVD_OPTION_KEY,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'pvd_sanitize_settings',
			'default'           => pvd_defaults(),
		)
	);
}
add_action( 'admin_init', 'pvd_register_settings' );

function pvd_sanitize_settings( $input ) {
	$current  = pvd_get_options();
	$defaults = pvd_defaults();
	if ( ! is_array( $input ) ) {
		return $current;
	}

	$out = $current;

	// Connection
	$out['host'] = isset( $input['host'] ) ? esc_url_raw( trim( (string) $input['host'] ) ) : $current['host'];
	$token_raw   = isset( $input['token_id'] ) ? sanitize_text_field( trim( (string) $input['token_id'] ) ) : $current['token_id'];
	$out['token_id'] = $token_raw;
	if ( false !== strpos( $token_raw, '!' ) ) {
		$parts = explode( '!', $token_raw, 2 );
		$out['user'] = sanitize_text_field( trim( $parts[0] ) );
	} elseif ( isset( $input['user'] ) ) {
		$out['user'] = sanitize_text_field( trim( (string) $input['user'] ) );
	}
	if ( isset( $input['token_secret'] ) ) {
		$secret = sanitize_text_field( trim( (string) $input['token_secret'] ) );
		if ( '' !== $secret && '******' !== $secret ) {
			$out['token_secret'] = $secret;
		}
	}
	$out['refresh_interval'] = isset( $input['refresh_interval'] ) ? max( 0, absint( $input['refresh_interval'] ) ) : $current['refresh_interval'];
	$out['sslverify']        = ! empty( $input['sslverify'] );

	// Branding / text
	$text_fields = array( 'brand_name', 'brand_title', 'brand_subtitle', 'footer_text', 'server_ip', 'server_so', 'server_fn', 'server_mac', 'server_url', 'router_ip', 'router_label', 'switch_label', 'lan_network' );
	foreach ( $text_fields as $f ) {
		if ( array_key_exists( $f, $input ) ) {
			$out[ $f ] = sanitize_text_field( (string) $input[ $f ] );
		}
	}
	if ( array_key_exists( 'server_status', $input ) ) {
		$out['server_status'] = sanitize_textarea_field( (string) $input['server_status'] );
	}
	if ( array_key_exists( 'server_icon', $input ) ) {
		$out['server_icon'] = esc_url_raw( trim( (string) $input['server_icon'] ) );
	}

	// Containers
	if ( isset( $input['containers'] ) && is_array( $input['containers'] ) ) {
		$out['containers'] = array();
		foreach ( $input['containers'] as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$name = sanitize_text_field( $c['name'] ?? '' );
			$id   = sanitize_text_field( $c['id'] ?? '' );
			if ( '' === $name && '' === $id ) {
				continue;
			}
			$out['containers'][] = array(
				'id'   => $id,
				'name' => $name,
				'icon' => esc_url_raw( trim( (string) ( $c['icon'] ?? '' ) ) ),
				'ip'   => sanitize_text_field( $c['ip'] ?? '' ),
				'mac'  => sanitize_text_field( $c['mac'] ?? '' ),
				'url'  => sanitize_text_field( $c['url'] ?? '' ),
				'fn'   => sanitize_text_field( $c['fn'] ?? '' ),
			);
		}
	}

	// Docker services
	if ( isset( $input['docker_services'] ) && is_array( $input['docker_services'] ) ) {
		$out['docker_services'] = array();
		foreach ( $input['docker_services'] as $d ) {
			if ( ! is_array( $d ) ) {
				continue;
			}
			$name = sanitize_text_field( $d['name'] ?? '' );
			if ( '' === $name && empty( $d['domain'] ) && empty( $d['port'] ) ) {
				continue;
			}
			$out['docker_services'][] = array(
				'name'   => $name,
				'icon'   => esc_url_raw( trim( (string) ( $d['icon'] ?? '' ) ) ),
				'port'   => sanitize_text_field( $d['port'] ?? '' ),
				'domain' => sanitize_text_field( $d['domain'] ?? '' ),
			);
		}
	}

	// Storages custom
	if ( isset( $input['storages_custom'] ) && is_array( $input['storages_custom'] ) ) {
		$out['storages_custom'] = array();
		foreach ( $input['storages_custom'] as $s ) {
			if ( ! is_array( $s ) ) {
				continue;
			}
			$title = sanitize_text_field( $s['title'] ?? '' );
			$items = sanitize_textarea_field( $s['items'] ?? '' );
			if ( '' === $title && '' === $items ) {
				continue;
			}
			$out['storages_custom'][] = array(
				'drive' => sanitize_text_field( $s['drive'] ?? 'SSD' ),
				'title' => $title,
				'items' => $items,
			);
		}
	}

	// Legend
	if ( isset( $input['legend_items'] ) && is_array( $input['legend_items'] ) ) {
		$out['legend_items'] = array();
		foreach ( $input['legend_items'] as $l ) {
			if ( ! is_array( $l ) ) {
				continue;
			}
			$tag = sanitize_text_field( $l['tag'] ?? '' );
			$label = sanitize_text_field( $l['label'] ?? '' );
			if ( '' === $tag && '' === $label ) {
				continue;
			}
			$out['legend_items'][] = array(
				'tag'   => $tag,
				'color' => sanitize_key( $l['color'] ?? 'lxc' ),
				'label' => $label,
			);
		}
	}

	return $out;
}

function pvd_handle_export() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sem permissão.', 'proxmox-ve-dashboard' ) );
	}
	check_admin_referer( 'pvd_export', 'pvd_export_nonce' );
	$options = pvd_get_options();
	if ( empty( $_POST['include_secret'] ) ) {
		$options['token_secret'] = '';
	}
	$payload = array(
		'generator' => 'Proxmox VE Dashboard',
		'version'   => PVD_VERSION,
		'exported'  => current_time( 'mysql' ),
		'settings'  => $options,
	);
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=pvd-settings-' . gmdate( 'Y-m-d-His' ) . '.json' );
	echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	exit;
}
add_action( 'admin_post_pvd_export', 'pvd_handle_export' );

function pvd_handle_import() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sem permissão.', 'proxmox-ve-dashboard' ) );
	}
	check_admin_referer( 'pvd_import', 'pvd_import_nonce' );
	$redirect = admin_url( 'admin.php?page=proxmox-ve-dashboard&tab=backup' );

	$raw = '';
	if ( ! empty( $_FILES['pvd_import_file']['tmp_name'] ) && is_uploaded_file( $_FILES['pvd_import_file']['tmp_name'] ) ) {
		$raw = file_get_contents( $_FILES['pvd_import_file']['tmp_name'] ); // phpcs:ignore
	} elseif ( ! empty( $_POST['pvd_import_json'] ) ) {
		$raw = wp_unslash( (string) $_POST['pvd_import_json'] );
	}
	if ( '' === trim( $raw ) ) {
		wp_safe_redirect( add_query_arg( 'pvd_msg', 'import_empty', $redirect ) );
		exit;
	}
	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		wp_safe_redirect( add_query_arg( 'pvd_msg', 'import_invalid', $redirect ) );
		exit;
	}
	$settings = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : $data;
	$current  = pvd_get_options();

	if ( ! empty( $_POST['preserve_credentials'] ) ) {
		$settings['host']         = $current['host'];
		$settings['user']         = $current['user'];
		$settings['token_id']     = $current['token_id'];
		$settings['token_secret'] = $current['token_secret'];
	} elseif ( empty( $settings['token_secret'] ) && ! empty( $current['token_secret'] ) ) {
		$settings['token_secret'] = $current['token_secret'];
	}

	$clean = pvd_sanitize_settings( $settings );
	update_option( PVD_OPTION_KEY, $clean );
	wp_safe_redirect( add_query_arg( 'pvd_msg', 'import_ok', $redirect ) );
	exit;
}
add_action( 'admin_post_pvd_import', 'pvd_handle_import' );

function pvd_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$options = pvd_get_options();
	$tab     = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'connection'; // phpcs:ignore
	$msg     = isset( $_GET['pvd_msg'] ) ? sanitize_key( $_GET['pvd_msg'] ) : ''; // phpcs:ignore

	$test_result = null;
	if ( isset( $_POST['pvd_test_connection'] ) && check_admin_referer( 'pvd_test_connection' ) ) {
		$api = PVD_Proxmox_API::instance();
		$test_result = $api->test_connection();
	}

	$token_display = $options['token_id'];
	if ( ! empty( $options['user'] ) && false === strpos( $token_display, '!' ) ) {
		$token_display = $options['user'] . '!' . $token_display;
	}
	?>
	<div class="wrap pvd-admin">
		<h1><?php esc_html_e( 'Proxmox VE Dashboard', 'proxmox-ve-dashboard' ); ?></h1>
		<p class="description"><?php esc_html_e( 'Configure a API do Proxmox, personalize o dashboard e gerencie importação/exportação.', 'proxmox-ve-dashboard' ); ?></p>

		<?php if ( 'import_ok' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Configurações importadas com sucesso.', 'proxmox-ve-dashboard' ); ?></p></div>
		<?php elseif ( 'import_invalid' === $msg ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'JSON inválido.', 'proxmox-ve-dashboard' ); ?></p></div>
		<?php elseif ( 'import_empty' === $msg ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Nenhum arquivo ou JSON informado.', 'proxmox-ve-dashboard' ); ?></p></div>
		<?php endif; ?>

		<?php if ( null !== $test_result ) : ?>
			<?php if ( true === $test_result ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Conexão com Proxmox bem-sucedida.', 'proxmox-ve-dashboard' ); ?></p></div>
			<?php else : ?>
				<div class="notice notice-error is-dismissible"><p><strong><?php esc_html_e( 'Falha na conexão:', 'proxmox-ve-dashboard' ); ?></strong> <?php echo esc_html( is_wp_error( $test_result ) ? $test_result->get_error_message() : __( 'Erro desconhecido', 'proxmox-ve-dashboard' ) ); ?></p></div>
			<?php endif; ?>
		<?php endif; ?>

		<h2 class="nav-tab-wrapper">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=proxmox-ve-dashboard&tab=connection' ) ); ?>" class="nav-tab <?php echo 'connection' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Conexão API', 'proxmox-ve-dashboard' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=proxmox-ve-dashboard&tab=visual' ) ); ?>" class="nav-tab <?php echo 'visual' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Personalização', 'proxmox-ve-dashboard' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=proxmox-ve-dashboard&tab=content' ) ); ?>" class="nav-tab <?php echo 'content' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Conteúdo', 'proxmox-ve-dashboard' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=proxmox-ve-dashboard&tab=backup' ) ); ?>" class="nav-tab <?php echo 'backup' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Importar/Exportar', 'proxmox-ve-dashboard' ); ?></a>
		</h2>

		<?php if ( 'backup' !== $tab ) : ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'pvd_settings_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( PVD_OPTION_KEY ); ?>[_active_tab]" value="<?php echo esc_attr( $tab ); ?>" />

			<div class="pvd-tab-panel" <?php echo 'connection' !== $tab ? 'style="display:none"' : ''; ?>>
				<table class="form-table" role="presentation">
					<tr><th><label for="pvd_host"><?php esc_html_e( 'URL da API Proxmox', 'proxmox-ve-dashboard' ); ?></label></th>
						<td><input type="url" id="pvd_host" name="<?php echo esc_attr( PVD_OPTION_KEY ); ?>[host]" value="<?php echo esc_attr( $options['host'] ); ?>" class="regular-text" placeholder="https://192.168.2.50:8006">
						<p class="description"><?php esc_html_e( 'Ex: https://proxmox.seudominio.com ou https://192.168.2.50:8006', 'proxmox-ve-dashboard' ); ?></p></td></tr>
					<tr><th><label for="pvd_token_id"><?php esc_html_e( 'Token ID', 'proxmox-ve-dashboard' ); ?></label></th>
						<td><input type="text" id="pvd_token_id" name="<?php echo esc_attr( PVD_OPTION_KEY ); ?>[token_id]" value="<?php echo esc_attr( $token_display ); ?>" class="regular-text" placeholder="user@pam!tokenid">
						<p class="description"><?php esc_html_e( 'Formato: usuario@realm!tokenid', 'proxmox-ve-dashboard' ); ?></p></td></tr>
					<tr><th><label for="pvd_token_secret"><?php esc_html_e( 'Token Secret', 'proxmox-ve-dashboard' ); ?></label></th>
						<td><input type="password" id="pvd_token_secret" name="<?php echo esc_attr( PVD_OPTION_KEY ); ?>[token_secret]" value="<?php echo esc_attr( $options['token_secret'] ? '******' : '' ); ?>" class="regular-text" autocomplete="new-password">
						<p class="description"><?php esc_html_e( 'Deixe "******" para manter o secret atual. O secret não é exposto no frontend.', 'proxmox-ve-dashboard' ); ?></p></td></tr>
					<tr><th><label for="pvd_refresh"><?php esc_html_e( 'Intervalo de atualização (segundos)', 'proxmox-ve-dashboard' ); ?></label></th>
						<td><input type="number" id="pvd_refresh" min="0" max="3600" name="<?php echo esc_attr( PVD_OPTION_KEY ); ?>[refresh_interval]" value="<?php echo esc_attr( $options['refresh_interval'] ); ?>" class="small-text">
						<p class="description"><?php esc_html_e( '0 desativa auto-refresh.', 'proxmox-ve-dashboard' ); ?></p></td></tr>
					<tr><th><?php esc_html_e( 'Verificar SSL', 'proxmox-ve-dashboard' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( PVD_OPTION_KEY ); ?>[sslverify]" value="1" <?php checked( ! empty( $options['sslverify'] ) ); ?>> <?php esc_html_e( 'Validar certificado SSL (desative para certificados self-signed locais)', 'proxmox-ve-dashboard' ); ?></label></td></tr>
				</table>
			</div>

			<div class="pvd-tab-panel" <?php echo 'visual' !== $tab ? 'style="display:none"' : ''; ?>>
				<h3><?php esc_html_e( 'Branding e Cabeçalho', 'proxmox-ve-dashboard' ); ?></h3>
				<table class="form-table" role="presentation">
					<tr><th><label>Nome da marca</label></th><td><input type="text" name="<?php echo esc_attr( PVD_OPTION_KEY ); ?>[brand_name]" value="<?php echo esc_attr( $options['brand_name'] ); ?>" class="regular-text"></td></tr>
					<tr><th><label>Título</label></th><td><input type="text" name="<?php echo esc_attr( PVD_OPTION_KEY ); ?>[brand_title]" value="<?php echo esc_attr( $options['brand_title'] ); ?>" class="regular-text"></td></tr>
					<tr><th><label>Subtítulo</label></th><td><input type="text" name="<?php echo esc_attr( PVD_OPTION_KEY ); ?>[brand_subtitle]" value="<?php echo esc_attr( $options['brand_subtitle'] ); ?>" class="regular-text"></td></tr>
					<tr><th><label>Texto do rodapé</label></th><td><input type="text" name="<?php echo esc_attr( PVD_OPTION_KEY ); ?>[footer_text]" value="<?php echo esc_attr( $options['footer_text'] ); ?>" class="regular-text"></td></tr>
				</table>
				<h3><?php esc_html_e( 'Card do Servidor', 'proxmox-ve-dashboard' ); ?></h3>
				<table class="form-table" role="presentation">
					<tr><th>Ícone/Logo</th><td>
						<div class="pvd-media-field">
							<input type="text" id="pvd_server_icon" name="<?php echo esc_attr( PVD_OPTION_KEY ); ?>[server_icon]" value="<?php echo esc_attr( $options['server_icon'] ); ?>" class="regular-text">
							<button type="button" class="button pvd-media-btn" data-target="pvd_server_icon"><?php esc_html_e( 'Selecionar', 'proxmox-ve-dashboard' ); ?></button>
						</div>
					</td></tr>
					<tr><th>IP</th><td><input type="text" name="<?php echo esc_attr( PVD_OPTION_KEY ); ?>[server_ip]" value="<?php echo esc_attr( $options['server_ip'] ); ?>" class="regular-text"></td></tr>
					<tr><th>Sistema Operacional</th><td><input type="text" name="<?php echo esc_attr( PVD_OPTION_KEY ); ?>[server_so]" value="<?php echo esc_attr( $options['server_so'] ); ?>" class="regular-text"></td></tr>
					<tr><th>Função</th><td><input type="text" name="<?php echo esc_attr( PVD_OPTION_KEY ); ?>[server_fn]" value="<?php echo esc_attr( $options['server_fn'] ); ?>" class="regular-text"></td></tr>
					<tr><th>MAC</th><td><input type="text" name="<?php echo esc_attr( PVD_OPTION_KEY ); ?>[server_mac]" value="<?php echo esc_attr( $options['server_mac'] ); ?>" class="regular-text"></td></tr>
					<tr><th>URL</th><td><input type="text" name="<?php echo esc_attr( PVD_OPTION_KEY ); ?>[server_url]" value="<?php echo esc_attr( $options['server_url'] ); ?>" class="regular-text"></td></tr>
					<tr><th>Status/Mensagem</th><td><textarea name="<?php echo esc_attr( PVD_OPTION_KEY ); ?>[server_status]" rows="4" class="large-text"><?php echo esc_textarea( $options['server_status'] ); ?></textarea></td></tr>
				</table>
				<h3><?php esc_html_e( 'Topologia de Rede', 'proxmox-ve-dashboard' ); ?></h3>
				<table class="form-table" role="presentation">
					<tr><th>Rótulo roteador</th><td><input type="text" name="<?php echo esc_attr( PVD_OPTION_KEY ); ?>[router_label]" value="<?php echo esc_attr( $options['router_label'] ); ?>"></td></tr>
					<tr><th>IP roteador</th><td><input type="text" name="<?php echo esc_attr( PVD_OPTION_KEY ); ?>[router_ip]" value="<?php echo esc_attr( $options['router_ip'] ); ?>"></td></tr>
					<tr><th>Rótulo switch</th><td><input type="text" name="<?php echo esc_attr( PVD_OPTION_KEY ); ?>[switch_label]" value="<?php echo esc_attr( $options['switch_label'] ); ?>"></td></tr>
					<tr><th>Rede LAN</th><td><input type="text" name="<?php echo esc_attr( PVD_OPTION_KEY ); ?>[lan_network]" value="<?php echo esc_attr( $options['lan_network'] ); ?>"></td></tr>
				</table>
			</div>

			<div class="pvd-tab-panel" <?php echo 'content' !== $tab ? 'style="display:none"' : ''; ?>>
				<h3><?php esc_html_e( 'Containers LXC', 'proxmox-ve-dashboard' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Edite os cards LXC exibidos no dashboard. O campo Ícone aceita URL de imagem/SVG.', 'proxmox-ve-dashboard' ); ?></p>
				<div id="pvd-containers" class="pvd-repeater">
					<?php foreach ( $options['containers'] as $i => $c ) : ?>
						<?php pvd_render_container_row( $i, $c ); ?>
					<?php endforeach; ?>
				</div>
				<p><button type="button" class="button" id="pvd-add-container"><?php esc_html_e( 'Adicionar LXC', 'proxmox-ve-dashboard' ); ?></button></p>

				<hr>
				<h3><?php esc_html_e( 'Serviços Docker', 'proxmox-ve-dashboard' ); ?></h3>
				<div id="pvd-docker" class="pvd-repeater">
					<?php foreach ( $options['docker_services'] as $i => $d ) : ?>
						<?php pvd_render_docker_row( $i, $d ); ?>
					<?php endforeach; ?>
				</div>
				<p><button type="button" class="button" id="pvd-add-docker"><?php esc_html_e( 'Adicionar Docker', 'proxmox-ve-dashboard' ); ?></button></p>

				<hr>
				<h3><?php esc_html_e( 'Armazenamento personalizado (opcional)', 'proxmox-ve-dashboard' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Se preenchido, substitui os cards de armazenamento automáticos da API. Um item por linha em "Itens".', 'proxmox-ve-dashboard' ); ?></p>
				<div id="pvd-storage" class="pvd-repeater">
					<?php foreach ( $options['storages_custom'] as $i => $s ) : ?>
						<?php pvd_render_storage_row( $i, $s ); ?>
					<?php endforeach; ?>
				</div>
				<p><button type="button" class="button" id="pvd-add-storage"><?php esc_html_e( 'Adicionar armazenamento', 'proxmox-ve-dashboard' ); ?></button></p>
			</div>

			<?php submit_button( __( 'Salvar configurações', 'proxmox-ve-dashboard' ) ); ?>
		</form>

		<?php if ( 'connection' === $tab ) : ?>
			<hr>
			<h2><?php esc_html_e( 'Teste de conectividade', 'proxmox-ve-dashboard' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'pvd_test_connection' ); ?>
				<p><button type="submit" name="pvd_test_connection" class="button button-secondary"><?php esc_html_e( 'Testar conexão com API', 'proxmox-ve-dashboard' ); ?></button></p>
			</form>
		<?php endif; ?>

		<?php else : ?>
			<div class="pvd-backup-grid">
				<div class="pvd-card">
					<h2><?php esc_html_e( 'Exportar', 'proxmox-ve-dashboard' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="pvd_export">
						<?php wp_nonce_field( 'pvd_export', 'pvd_export_nonce' ); ?>
						<p><label><input type="checkbox" name="include_secret" value="1"> <?php esc_html_e( 'Incluir Token Secret no arquivo (cuidado ao compartilhar)', 'proxmox-ve-dashboard' ); ?></label></p>
						<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Exportar JSON', 'proxmox-ve-dashboard' ); ?></button></p>
					</form>
				</div>
				<div class="pvd-card">
					<h2><?php esc_html_e( 'Importar', 'proxmox-ve-dashboard' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
						<input type="hidden" name="action" value="pvd_import">
						<?php wp_nonce_field( 'pvd_import', 'pvd_import_nonce' ); ?>
						<p><input type="file" name="pvd_import_file" accept=".json,application/json"></p>
						<p><textarea name="pvd_import_json" rows="6" class="large-text" placeholder="<?php esc_attr_e( 'Ou cole o JSON aqui', 'proxmox-ve-dashboard' ); ?>"></textarea></p>
						<p><label><input type="checkbox" name="preserve_credentials" value="1" checked> <?php esc_html_e( 'Preservar credenciais da API neste servidor', 'proxmox-ve-dashboard' ); ?></label></p>
						<p><button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Importar substituirá as configurações atuais. Continuar?', 'proxmox-ve-dashboard' ) ); ?>');"><?php esc_html_e( 'Importar JSON', 'proxmox-ve-dashboard' ); ?></button></p>
					</form>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<script type="text/template" id="pvd-tpl-container"><?php pvd_render_container_row( '__INDEX__', array( 'id' => '', 'name' => '', 'icon' => '', 'ip' => '', 'mac' => '', 'url' => '', 'fn' => '' ) ); ?></script>
	<script type="text/template" id="pvd-tpl-docker"><?php pvd_render_docker_row( '__INDEX__', array( 'name' => '', 'icon' => '', 'port' => '', 'domain' => '' ) ); ?></script>
	<script type="text/template" id="pvd-tpl-storage"><?php pvd_render_storage_row( '__INDEX__', array( 'drive' => 'SSD', 'title' => '', 'items' => '' ) ); ?></script>
	<?php
}

function pvd_render_container_row( $i, $c ) {
	$k = PVD_OPTION_KEY;
	?>
	<div class="pvd-repeater-row">
		<div class="pvd-repeater-head"><strong>LXC</strong> <button type="button" class="button-link-delete pvd-remove-row"><?php esc_html_e( 'Remover', 'proxmox-ve-dashboard' ); ?></button></div>
		<div class="pvd-grid">
			<label>ID<input type="text" name="<?php echo esc_attr( $k ); ?>[containers][<?php echo esc_attr( $i ); ?>][id]" value="<?php echo esc_attr( $c['id'] ?? '' ); ?>"></label>
			<label>Nome<input type="text" name="<?php echo esc_attr( $k ); ?>[containers][<?php echo esc_attr( $i ); ?>][name]" value="<?php echo esc_attr( $c['name'] ?? '' ); ?>"></label>
			<label>IP<input type="text" name="<?php echo esc_attr( $k ); ?>[containers][<?php echo esc_attr( $i ); ?>][ip]" value="<?php echo esc_attr( $c['ip'] ?? '' ); ?>"></label>
			<label>MAC<input type="text" name="<?php echo esc_attr( $k ); ?>[containers][<?php echo esc_attr( $i ); ?>][mac]" value="<?php echo esc_attr( $c['mac'] ?? '' ); ?>"></label>
			<label>URL<input type="text" name="<?php echo esc_attr( $k ); ?>[containers][<?php echo esc_attr( $i ); ?>][url]" value="<?php echo esc_attr( $c['url'] ?? '' ); ?>"></label>
			<label>Ícone URL<input type="text" name="<?php echo esc_attr( $k ); ?>[containers][<?php echo esc_attr( $i ); ?>][icon]" value="<?php echo esc_attr( $c['icon'] ?? '' ); ?>"></label>
			<label class="pvd-full">Função<input type="text" name="<?php echo esc_attr( $k ); ?>[containers][<?php echo esc_attr( $i ); ?>][fn]" value="<?php echo esc_attr( $c['fn'] ?? '' ); ?>"></label>
		</div>
	</div>
	<?php
}

function pvd_render_docker_row( $i, $d ) {
	$k = PVD_OPTION_KEY;
	?>
	<div class="pvd-repeater-row">
		<div class="pvd-repeater-head"><strong>Docker</strong> <button type="button" class="button-link-delete pvd-remove-row"><?php esc_html_e( 'Remover', 'proxmox-ve-dashboard' ); ?></button></div>
		<div class="pvd-grid">
			<label>Nome<input type="text" name="<?php echo esc_attr( $k ); ?>[docker_services][<?php echo esc_attr( $i ); ?>][name]" value="<?php echo esc_attr( $d['name'] ?? '' ); ?>"></label>
			<label>Porta<input type="text" name="<?php echo esc_attr( $k ); ?>[docker_services][<?php echo esc_attr( $i ); ?>][port]" value="<?php echo esc_attr( $d['port'] ?? '' ); ?>"></label>
			<label>Domínio<input type="text" name="<?php echo esc_attr( $k ); ?>[docker_services][<?php echo esc_attr( $i ); ?>][domain]" value="<?php echo esc_attr( $d['domain'] ?? '' ); ?>"></label>
			<label>Ícone URL<input type="text" name="<?php echo esc_attr( $k ); ?>[docker_services][<?php echo esc_attr( $i ); ?>][icon]" value="<?php echo esc_attr( $d['icon'] ?? '' ); ?>"></label>
		</div>
	</div>
	<?php
}

function pvd_render_storage_row( $i, $s ) {
	$k = PVD_OPTION_KEY;
	?>
	<div class="pvd-repeater-row">
		<div class="pvd-repeater-head"><strong>Storage</strong> <button type="button" class="button-link-delete pvd-remove-row"><?php esc_html_e( 'Remover', 'proxmox-ve-dashboard' ); ?></button></div>
		<div class="pvd-grid">
			<label>Drive<input type="text" name="<?php echo esc_attr( $k ); ?>[storages_custom][<?php echo esc_attr( $i ); ?>][drive]" value="<?php echo esc_attr( $s['drive'] ?? 'SSD' ); ?>"></label>
			<label class="pvd-full">Título<input type="text" name="<?php echo esc_attr( $k ); ?>[storages_custom][<?php echo esc_attr( $i ); ?>][title]" value="<?php echo esc_attr( $s['title'] ?? '' ); ?>"></label>
			<label class="pvd-full">Itens (uma linha por item)<textarea rows="3" name="<?php echo esc_attr( $k ); ?>[storages_custom][<?php echo esc_attr( $i ); ?>][items]"><?php echo esc_textarea( $s['items'] ?? '' ); ?></textarea></label>
		</div>
	</div>
	<?php
}