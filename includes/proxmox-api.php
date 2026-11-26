<?php
/**
 * Proxmox VE API client.
 *
 * @package Proxmox_VE_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PVD_Proxmox_API {

	private static $instance = null;
	private $options;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->options = pvd_get_options();
	}

	public function refresh_options() {
		$this->options = pvd_get_options();
	}

	private function token_identifier() {
		$token_id = trim( (string) ( $this->options['token_id'] ?? '' ) );
		$user     = trim( (string) ( $this->options['user'] ?? '' ) );
		if ( false !== strpos( $token_id, '!' ) ) {
			return $token_id;
		}
		if ( '' !== $user && '' !== $token_id ) {
			return $user . '!' . $token_id;
		}
		return '';
	}

	public function get( $path, $query = array() ) {
		$this->refresh_options();
		$host   = untrailingslashit( trim( (string) ( $this->options['host'] ?? '' ) ) );
		$token  = $this->token_identifier();
		$secret = (string) ( $this->options['token_secret'] ?? '' );

		if ( '' === $host || '' === $token || '' === $secret ) {
			return new WP_Error( 'pvd_not_configured', __( 'API do Proxmox não configurada.', 'proxmox-ve-dashboard' ) );
		}
		if ( '' === $path ) {
			return new WP_Error( 'pvd_empty_path', __( 'Caminho da API vazio.', 'proxmox-ve-dashboard' ) );
		}

		$url = $host . '/' . ltrim( $path, '/' );
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 15,
				'headers'   => array(
					'Authorization' => 'PVEAPIToken=' . $token . '=' . $secret,
					'Accept'        => 'application/json',
				),
				'sslverify' => (bool) apply_filters( 'pvd_sslverify', ! empty( $this->options['sslverify'] ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = is_array( $data ) && isset( $data['errors'] ) ? wp_json_encode( $data['errors'] ) : sprintf( __( 'Proxmox retornou HTTP %d.', 'proxmox-ve-dashboard' ), $code );
			return new WP_Error( 'pvd_http_' . $code, $msg, array( 'status' => $code ) );
		}

		if ( null === $data ) {
			return new WP_Error( 'pvd_invalid_json', __( 'Resposta inválida do Proxmox.', 'proxmox-ve-dashboard' ) );
		}
		return $data;
	}

	public function test_connection() {
		$res = $this->get( '/api2/json/version' );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$nodes = $this->get( '/api2/json/nodes' );
		if ( is_wp_error( $nodes ) ) {
			return $nodes;
		}
		return true;
	}

	public function get_status() {
		$version = $this->get( '/api2/json/version' );
		$nodes   = $this->get_nodes();
		$storage = $this->get_storage();

		if ( is_wp_error( $nodes ) ) {
			return $nodes;
		}
		if ( is_wp_error( $storage ) ) {
			$storage = array();
		}

		$cpu_total = 0.0;
		$ram_used  = 0;
		$ram_total = 0;
		$disk_used = 0;
		$disk_total = 0;
		$uptime_max = 0;

		foreach ( $nodes as $n ) {
			$cpu_total  += (float) ( $n['cpu'] ?? 0 );
			$ram_used   += (int) ( $n['mem'] ?? 0 );
			$ram_total  += (int) ( $n['maxmem'] ?? 0 );
			$disk_used  += (int) ( $n['disk'] ?? 0 );
			$disk_total += (int) ( $n['maxdisk'] ?? 0 );
			$uptime_max  = max( $uptime_max, (int) ( $n['uptime'] ?? 0 ) );
		}

		$node_count = count( $nodes );
		$cpu_avg    = $node_count > 0 ? $cpu_total / $node_count : 0;

		return array(
			'version'      => is_array( $version ) ? ( $version['data']['version'] ?? '--' ) : '--',
			'release'      => is_array( $version ) ? ( $version['data']['release'] ?? '' ) : '',
			'nodeCount'    => $node_count,
			'storageCount' => is_array( $storage ) ? count( $storage ) : 0,
			'cpuTotal'     => $cpu_avg, // 0-1
			'ramUsed'      => $ram_used,
			'ramTotal'     => $ram_total,
			'diskUsed'     => $disk_used,
			'diskTotal'    => $disk_total,
			'uptime'       => $uptime_max,
		);
	}

	public function get_nodes() {
		$res = $this->get( '/api2/json/nodes' );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$nodes = is_array( $res['data'] ?? null ) ? $res['data'] : array();
		$out   = array();
		foreach ( $nodes as $n ) {
			$out[] = array(
				'node'    => $n['node'] ?? '--',
				'status'  => $n['status'] ?? 'unknown',
				'cpu'     => round( (float) ( $n['cpu'] ?? 0 ) * 100, 1 ),
				'mem'     => (int) ( $n['mem'] ?? 0 ),
				'maxmem'  => (int) ( $n['maxmem'] ?? 0 ),
				'disk'    => (int) ( $n['disk'] ?? 0 ),
				'maxdisk' => (int) ( $n['maxdisk'] ?? 0 ),
				'uptime'  => (int) ( $n['uptime'] ?? 0 ),
			);
		}
		return $out;
	}

	public function get_resources() {
		$res = $this->get( '/api2/json/cluster/resources' );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$rows = is_array( $res['data'] ?? null ) ? $res['data'] : array();
		$vms  = array();
		$lxc  = array();
		foreach ( $rows as $r ) {
			$type = $r['type'] ?? '';
			if ( 'qemu' === $type ) {
				$vms[] = $this->normalize_vm( $r );
			} elseif ( 'lxc' === $type ) {
				$lxc[] = $this->normalize_lxc( $r );
			}
		}
		return array( 'vms' => $vms, 'lxc' => $lxc );
	}

	private function normalize_vm( $r ) {
		return array(
			'vmid'    => (int) ( $r['vmid'] ?? 0 ),
			'name'    => $r['name'] ?? '--',
			'node'    => $r['node'] ?? '--',
			'status'  => $r['status'] ?? 'unknown',
			'cpu'     => round( (float) ( $r['cpu'] ?? 0 ) * 100, 1 ),
			'mem'     => (int) ( $r['mem'] ?? 0 ),
			'maxmem'  => (int) ( $r['maxmem'] ?? 0 ),
			'disk'    => (int) ( $r['disk'] ?? 0 ),
			'maxdisk' => (int) ( $r['maxdisk'] ?? 0 ),
			'uptime'  => (int) ( $r['uptime'] ?? 0 ),
			'type'    => 'qemu',
		);
	}

	private function normalize_lxc( $r ) {
		return array(
			'vmid'    => (int) ( $r['vmid'] ?? 0 ),
			'name'    => $r['name'] ?? '--',
			'node'    => $r['node'] ?? '--',
			'status'  => $r['status'] ?? 'unknown',
			'cpu'     => round( (float) ( $r['cpu'] ?? 0 ) * 100, 1 ),
			'mem'     => (int) ( $r['mem'] ?? 0 ),
			'maxmem'  => (int) ( $r['maxmem'] ?? 0 ),
			'disk'    => (int) ( $r['disk'] ?? 0 ),
			'maxdisk' => (int) ( $r['maxdisk'] ?? 0 ),
			'uptime'  => (int) ( $r['uptime'] ?? 0 ),
			'type'    => 'lxc',
		);
	}

	public function get_vms() {
		$r = $this->get_resources();
		return is_wp_error( $r ) ? $r : $r['vms'];
	}

	public function get_lxc() {
		$r = $this->get_resources();
		return is_wp_error( $r ) ? $r : $r['lxc'];
	}

	public function get_storage() {
		// Prefer cluster/resources for usage data.
		$cr = $this->get( '/api2/json/cluster/resources' );
		if ( ! is_wp_error( $cr ) && is_array( $cr['data'] ?? null ) ) {
			$out = array();
			foreach ( $cr['data'] as $r ) {
				if ( ( $r['type'] ?? '' ) !== 'storage' ) {
					continue;
				}
				$name = $r['storage'] ?? $r['id'] ?? '';
				if ( false !== strpos( $name, '/' ) ) {
					$parts = explode( '/', $name );
					$name  = end( $parts );
				}
				$total = (int) ( $r['maxdisk'] ?? 0 );
				$used  = (int) ( $r['disk'] ?? 0 );
				if ( $total <= 0 ) {
					continue;
				}
				$out[] = array(
					'storage' => $name,
					'type'    => $r['plugintype'] ?? 'storage',
					'status'  => $r['status'] ?? 'available',
					'active'  => true,
					'total'   => $total,
					'used'    => $used,
					'free'    => max( 0, $total - $used ),
					'percent' => round( ( $used / $total ) * 100, 1 ),
					'node'    => $r['node'] ?? '',
				);
			}
			if ( ! empty( $out ) ) {
				return $out;
			}
		}

		// Fallback: /nodes/{node}/storage
		$nodes = $this->get( '/api2/json/nodes' );
		if ( ! is_wp_error( $nodes ) && is_array( $nodes['data'] ?? null ) ) {
			$by = array();
			foreach ( $nodes['data'] as $n ) {
				$node = $n['node'] ?? '';
				if ( '' === $node ) {
					continue;
				}
				$sr = $this->get( '/api2/json/nodes/' . rawurlencode( $node ) . '/storage' );
				if ( is_wp_error( $sr ) || ! is_array( $sr['data'] ?? null ) ) {
					continue;
				}
				foreach ( $sr['data'] as $s ) {
					$name = $s['storage'] ?? '';
					if ( '' === $name || isset( $by[ $name ] ) ) {
						continue;
					}
					$total = (int) ( $s['total'] ?? $s['maxdisk'] ?? 0 );
					$used  = (int) ( $s['used'] ?? $s['disk'] ?? 0 );
					$avail = (int) ( $s['avail'] ?? max( 0, $total - $used ) );
					if ( $total <= 0 && $avail > 0 ) {
						$total = $used + $avail;
					}
					$by[ $name ] = array(
						'storage' => $name,
						'type'    => $s['type'] ?? 'storage',
						'status'  => ! empty( $s['active'] ) ? 'available' : ( $s['status'] ?? 'unknown' ),
						'active'  => ! empty( $s['active'] ),
						'total'   => max( 0, $total ),
						'used'    => max( 0, $used ),
						'free'    => max( 0, $avail ),
						'percent' => $total > 0 ? round( ( $used / $total ) * 100, 1 ) : 0,
						'node'    => $node,
					);
				}
			}
			if ( ! empty( $by ) ) {
				return array_values( $by );
			}
		}

		return array();
	}
}