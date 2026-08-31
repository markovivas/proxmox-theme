<?php
/**
 * Cliente para a API REST do Proxmox VE.
 *
 * @package Proxmox_Dashboard
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe Proxmox_API - responsável por toda a comunicação com o Proxmox VE.
 *
 * Utiliza exclusivamente a API REST oficial do Proxmox, autenticando por meio
 * de um API Token. O secret do token nunca é exposto ao navegador.
 */
class Proxmox_API {

	/**
	 * Instância única (singleton).
	 *
	 * @var Proxmox_API|null
	 */
	private static $instance = null;

	/**
	 * Opções de configuração do tema.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Retorna a instância única do cliente.
	 *
	 * @return Proxmox_API
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Construtor.
	 */
	private function __construct() {
		$this->options = proxmox_dashboard_get_options();
	}

	/**
	 * Realiza uma requisição GET à API do Proxmox.
	 *
	 * @param string $path Caminho do endpoint (ex.: /api2/json/version).
	 * @param array  $query Parâmetros de consulta.
	 * @return WP_Error|array Resposta decodificada (array) ou WP_Error.
	 */
	public function get( $path, $query = array() ) {
		$options = $this->options;

		if ( empty( $options['host'] ) || empty( $options['user'] ) || empty( $options['token_id'] ) || empty( $options['token_secret'] ) ) {
			return new WP_Error( 'proxmox_not_configured', __( 'Tema do Proxmox não configurado.', 'proxmox-dashboard' ) );
		}

		$host = untrailingslashit( $options['host'] );

		if ( empty( $path ) ) {
			return new WP_Error( 'proxmox_empty_path', __( 'Caminho da API vazio.', 'proxmox-dashboard' ) );
		}

		$path = '/' . ltrim( $path, '/' );
		$url  = $host . $path;

		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$token_identifier = $options['user'] . '!' . $options['token_id'];

		$args = array(
			'timeout'     => 15,
			'redirection' => 5,
			'headers'     => array(
				'Authorization' => 'PVEAPIToken=' . $token_identifier . '=' . $options['token_secret'],
				'Accept'        => 'application/json',
			),
			'sslverify'   => apply_filters( 'proxmox_dashboard_sslverify', true ),
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			$message = ( is_array( $data ) && isset( $data['errors'] ) )
				? wp_json_encode( $data['errors'] )
				: sprintf( __( 'Proxmox retornou o código HTTP %d.', 'proxmox-dashboard' ), $status_code );

			return new WP_Error( 'proxmox_http_' . $status_code, $message );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( null === $data ) {
			return new WP_Error( 'proxmox_invalid_json', __( 'Resposta inválida do Proxmox.', 'proxmox-dashboard' ) );
		}

		return $data;
	}

	/**
	 * Testa a conexão e autenticação com o Proxmox.
	 *
	 * O endpoint /version é público, então não é suficiente para validar o
	 * token. Aqui consultamos /cluster/resources, que exige autenticação e é o
	 * mesmo endpoint usado pelo dashboard — assim o teste reflete a realidade
	 * da consulta e evita um falso "Proxmox conectado".
	 *
	 * @return true|WP_Error
	 */
	public function test_connection() {
		// Valida autenticação com um endpoint que exige token válido.
		$response = $this->get( '/api2/json/cluster/resources' );

		if ( is_wp_error( $response ) ) {
			$code = $response->get_error_code();

			if ( 'proxmox_http_401' === $code ) {
				return new WP_Error(
					'proxmox_auth_invalid',
					__( 'Token da API inválido ou usuário/senha incorretos (401).', 'proxmox-dashboard' )
				);
			}

			if ( 'proxmox_http_403' === $code ) {
				return new WP_Error(
					'proxmox_permission_denied',
					__( 'Permissão insuficiente para consultar os dados (403). Verifique a role do usuário/token.', 'proxmox-dashboard' )
				);
			}

			if ( 'proxmox_not_configured' === $code ) {
				return $response;
			}

			// Demais erros: rede, SSL, etc.
			return $response;
		}

		// Confirma que o Proxmox respondeu com dados de recurso.
		if ( ! isset( $response['data'] ) ) {
			return new WP_Error( 'proxmox_invalid_response', __( 'Resposta inesperada do Proxmox.', 'proxmox-dashboard' ) );
		}

		return true;
	}

	/**
	 * Retorna a versão do Proxmox.
	 *
	 * @return mixed|null
	 */
	public function get_version() {
		$response = $this->get( '/api2/json/version' );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		return isset( $response['data'] ) ? $response['data'] : null;
	}

	/**
	 * Retorna a estrutura do cluster / datacenter (nodes + storage).
	 *
	 * @return array
	 */
	public function get_cluster() {
		$result = array(
			'nodes'   => array(),
			'storage' => array(),
		);

		$response = $this->get( '/api2/json/cluster/resources' );

		if ( is_wp_error( $response ) ) {
			return $result;
		}

		$resources = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();

		foreach ( $resources as $resource ) {
			if ( ! isset( $resource['type'] ) ) {
				continue;
			}

			switch ( $resource['type'] ) {
				case 'node':
					$result['nodes'][] = $resource;
					break;
				case 'storage':
					$result['storage'][] = $resource;
					break;
			}
		}

		return $result;
	}

	/**
	 * Retorna o resumo de status do dashboard (agrega informações principais).
	 *
	 * @return WP_Error|array
	 */
	public function get_status() {
		$version = $this->get_version();
		$cluster = $this->get_cluster();

		if ( is_wp_error( $cluster ) ) {
			return $cluster;
		}

		$nodes   = isset( $cluster['nodes'] ) ? $cluster['nodes'] : array();
		$storage = isset( $cluster['storage'] ) ? $cluster['storage'] : array();

		$total_cpu = 0.0;
		$total_ram = 0;
		$total_disk = 0;
		$used_ram   = 0;
		$used_disk  = 0;

		foreach ( $nodes as $node ) {
			$total_cpu += isset( $node['cpu'] ) ? (float) $node['cpu'] : 0.0;

			if ( isset( $node['maxmem'] ) ) {
				$total_ram += (int) $node['maxmem'];
			}
			if ( isset( $node['mem'] ) ) {
				$used_ram += (int) $node['mem'];
			}
			if ( isset( $node['maxdisk'] ) ) {
				$total_disk += (int) $node['maxdisk'];
			}
			if ( isset( $node['disk'] ) ) {
				$used_disk += (int) $node['disk'];
			}
		}

		return array(
			'version'    => isset( $version['version'] ) ? $version['version'] : '--',
			'release'    => isset( $version['release'] ) ? $version['release'] : '',
			'nodeCount'  => count( $nodes ),
			'storageCount' => count( $storage ),
			'cpuTotal'   => $total_cpu,
			'ramTotal'   => $total_ram,
			'ramUsed'    => $used_ram,
			'diskTotal'  => $total_disk,
			'diskUsed'   => $used_disk,
		);
	}

	/**
	 * Retorna a lista de nodes.
	 *
	 * @return WP_Error|array
	 */
	public function get_nodes() {
		$response = $this->get( '/api2/json/nodes' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$nodes = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();
		$result = array();

		foreach ( $nodes as $node ) {
			$entry = array(
				'node'    => isset( $node['node'] ) ? $node['node'] : '--',
				'status'  => isset( $node['status'] ) ? $node['status'] : 'unknown',
				'cpu'     => isset( $node['cpu'] ) ? round( (float) $node['cpu'] * 100, 1 ) : 0,
				'mem'     => isset( $node['mem'] ) ? (int) $node['mem'] : 0,
				'maxmem'  => isset( $node['maxmem'] ) ? (int) $node['maxmem'] : 0,
				'disk'    => isset( $node['disk'] ) ? (int) $node['disk'] : 0,
				'maxdisk' => isset( $node['maxdisk'] ) ? (int) $node['maxdisk'] : 0,
				'uptime'  => isset( $node['uptime'] ) ? (int) $node['uptime'] : 0,
				'version' => isset( $node['version'] ) ? $node['version'] : '',
			);

			$result[] = $entry;
		}

		// Enriquecer com rótulos/uptime e informações por node quando disponíveis.
		$result = $this->enrich_nodes( $result );

		return $result;
	}

	/**
	 * Enriquece os nodes com informações adicionais (uptime, nível de load, etc).
	 *
	 * @param array $nodes Lista de nodes.
	 * @return array
	 */
	private function enrich_nodes( array $nodes ) {
		return $nodes;
	}

	/**
	 * Retorna a lista de VMs e containers (recursos).
	 *
	 * @return WP_Error|array
	 */
	public function get_resources() {
		$response = $this->get( '/api2/json/cluster/resources' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$resources = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();

		$vms = array();
		$lxc = array();

		foreach ( $resources as $resource ) {
			if ( ! isset( $resource['type'] ) ) {
				continue;
			}

			if ( 'qemu' === $resource['type'] ) {
				$vms[] = $this->normalize_vm( $resource );
			} elseif ( 'lxc' === $resource['type'] ) {
				$lxc[] = $this->normalize_lxc( $resource );
			}
		}

		return array(
			'vms' => $vms,
			'lxc' => $lxc,
		);
	}

	/**
	 * Retorna apenas as VMs.
	 *
	 * @return WP_Error|array
	 */
	public function get_vms() {
		$resources = $this->get_resources();

		if ( is_wp_error( $resources ) ) {
			return $resources;
		}

		return $resources['vms'];
	}

	/**
	 * Retorna apenas os containers LXC.
	 *
	 * @return WP_Error|array
	 */
	public function get_lxc() {
		$resources = $this->get_resources();

		if ( is_wp_error( $resources ) ) {
			return $resources;
		}

		return $resources['lxc'];
	}

	/**
	 * Normaliza uma VM (qemu) para o formato do dashboard.
	 *
	 * @param array $resource Recurso bruto do Proxmox.
	 * @return array
	 */
	private function normalize_vm( array $resource ) {
		return array(
			'vmid'    => isset( $resource['vmid'] ) ? (int) $resource['vmid'] : 0,
			'name'    => isset( $resource['name'] ) ? $resource['name'] : '--',
			'node'    => isset( $resource['node'] ) ? $resource['node'] : '--',
			'status'  => isset( $resource['status'] ) ? $resource['status'] : 'unknown',
			'cpu'     => isset( $resource['cpu'] ) ? round( (float) $resource['cpu'] * 100, 1 ) : 0,
			'mem'     => isset( $resource['mem'] ) ? (int) $resource['mem'] : 0,
			'maxmem'  => isset( $resource['maxmem'] ) ? (int) $resource['maxmem'] : 0,
			'disk'    => isset( $resource['disk'] ) ? (int) $resource['disk'] : 0,
			'maxdisk' => isset( $resource['maxdisk'] ) ? (int) $resource['maxdisk'] : 0,
			'uptime'  => isset( $resource['uptime'] ) ? (int) $resource['uptime'] : 0,
			'type'    => 'qemu',
		);
	}

	/**
	 * Normaliza um container LXC para o formato do dashboard.
	 *
	 * @param array $resource Recurso bruto do Proxmox.
	 * @return array
	 */
	private function normalize_lxc( array $resource ) {
		return array(
			'vmid'    => isset( $resource['vmid'] ) ? (int) $resource['vmid'] : 0,
			'name'    => isset( $resource['name'] ) ? $resource['name'] : '--',
			'node'    => isset( $resource['node'] ) ? $resource['node'] : '--',
			'status'  => isset( $resource['status'] ) ? $resource['status'] : 'unknown',
			'cpu'     => isset( $resource['cpu'] ) ? round( (float) $resource['cpu'] * 100, 1 ) : 0,
			'mem'     => isset( $resource['mem'] ) ? (int) $resource['mem'] : 0,
			'maxmem'  => isset( $resource['maxmem'] ) ? (int) $resource['maxmem'] : 0,
			'disk'    => isset( $resource['disk'] ) ? (int) $resource['disk'] : 0,
			'maxdisk' => isset( $resource['maxdisk'] ) ? (int) $resource['maxdisk'] : 0,
			'uptime'  => isset( $resource['uptime'] ) ? (int) $resource['uptime'] : 0,
			'type'    => 'lxc',
		);
	}

	/**
	 * Retorna a lista de storages com uso (total/usado/livre).
	 *
	 * O endpoint /api2/json/storage retorna apenas a *configuração* (sem uso),
	 * por isso o total sempre vinha zerado. A implementação correta tenta em ordem:
	 *  1) /api2/json/cluster/resources (type=storage) — já traz disk/maxdisk
	 *  2) /api2/json/nodes/{node}/storage por node — traz total/used/avail reais
	 *  3) fallback para /api2/json/storage apenas para não retornar vazio
	 *
	 * @return WP_Error|array
	 */
	public function get_storage() {
		// 1) Tenta via cluster/resources (mais rápido, já tem uso)
		$cr = $this->get( '/api2/json/cluster/resources' );
		if ( ! is_wp_error( $cr ) && isset( $cr['data'] ) && is_array( $cr['data'] ) ) {
			$found = array();
			foreach ( $cr['data'] as $r ) {
				if ( ( $r['type'] ?? '' ) !== 'storage' ) {
					continue;
				}
				$name = $r['storage'] ?? $r['id'] ?? '';
				if ( false !== strpos( $name, '/' ) ) {
					$parts = explode( '/', $name );
					$name  = end( $parts );
				}
				if ( '' === $name ) {
					continue;
				}
				$total = (int) ( $r['maxdisk'] ?? $r['total'] ?? 0 );
				$used  = (int) ( $r['disk'] ?? $r['used'] ?? 0 );
				if ( $total <= 0 ) {
					continue; // sem métrica, tenta fallback por node
				}
				$found[] = array(
					'storage' => $name,
					'type'    => $r['plugintype'] ?? $r['type'] ?? '--',
					'status'  => $r['status'] ?? 'available',
					'active'  => true,
					'total'   => $total,
					'used'    => $used,
					'free'    => max( 0, $total - $used ),
					'percent' => $total > 0 ? round( ( $used / $total ) * 100, 1 ) : 0,
					'node'    => $r['node'] ?? '',
				);
			}
			if ( ! empty( $found ) ) {
				return $found;
			}
		}

		// 2) Fallback por node: /nodes/{node}/storage tem dados reais de uso
		$nodes_res = $this->get( '/api2/json/nodes' );
		if ( ! is_wp_error( $nodes_res ) && isset( $nodes_res['data'] ) && is_array( $nodes_res['data'] ) ) {
			$by_storage = array();
			foreach ( $nodes_res['data'] as $n ) {
				$node_name = $n['node'] ?? '';
				if ( '' === $node_name ) {
					continue;
				}
				$sres = $this->get( '/api2/json/nodes/' . rawurlencode( $node_name ) . '/storage' );
				if ( is_wp_error( $sres ) || ! isset( $sres['data'] ) || ! is_array( $sres['data'] ) ) {
					continue;
				}
				foreach ( $sres['data'] as $s ) {
					$nm = $s['storage'] ?? '';
					if ( '' === $nm ) {
						continue;
					}
					// Deduplica: storages compartilhados aparecem em todo node — mantém 1 entrada
					if ( isset( $by_storage[ $nm ] ) ) {
						continue;
					}
					$total = (int) ( $s['total'] ?? $s['maxdisk'] ?? 0 );
					$used  = (int) ( $s['used'] ?? $s['disk'] ?? 0 );
					$free  = isset( $s['avail'] ) ? (int) $s['avail'] : max( 0, $total - $used );
					if ( isset( $s['avail'] ) && $total <= 0 && $free > 0 ) {
						$total = $used + $free;
					}
					$status = $s['status'] ?? ( ! empty( $s['active'] ) ? 'available' : ( ! empty( $s['enabled'] ) ? 'available' : 'disabled' ) );
					$by_storage[ $nm ] = array(
						'storage' => $nm,
						'type'    => $s['type'] ?? '--',
						'status'  => $status,
						'active'  => ! empty( $s['active'] ),
						'total'   => max( 0, $total ),
						'used'    => max( 0, $used ),
						'free'    => max( 0, $free ),
						'percent' => $total > 0 ? round( ( $used / $total ) * 100, 1 ) : 0,
						'node'    => $node_name,
					);
				}
			}
			if ( ! empty( $by_storage ) ) {
				return array_values( $by_storage );
			}
		}

		// 3) Último fallback: endpoint de configuração (sem uso) — evita tabela vazia
		$response = $this->get( '/api2/json/storage' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$storages = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();
		$result   = array();
		foreach ( $storages as $storage ) {
			$total = isset( $storage['maxdisk'] ) ? (int) $storage['maxdisk'] : ( isset( $storage['total'] ) ? (int) $storage['total'] : 0 );
			$used  = isset( $storage['disk'] ) ? (int) $storage['disk'] : ( isset( $storage['used'] ) ? (int) $storage['used'] : 0 );
			$free  = isset( $storage['avail'] ) ? (int) $storage['avail'] : ( $total - $used );
			$result[] = array(
				'storage' => isset( $storage['storage'] ) ? $storage['storage'] : '--',
				'type'    => isset( $storage['type'] ) ? $storage['type'] : '--',
				'status'  => isset( $storage['status'] ) ? $storage['status'] : 'unknown',
				'active'  => isset( $storage['active'] ) ? (bool) $storage['active'] : false,
				'total'   => max( 0, $total ),
				'used'    => max( 0, $used ),
				'free'    => max( 0, $free ),
				'percent' => $total > 0 ? round( ( $used / $total ) * 100, 1 ) : 0,
			);
		}
		return $result;
	}
}
