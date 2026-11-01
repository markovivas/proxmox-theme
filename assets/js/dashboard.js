/**
 * Proxmox Dashboard & Mapa da Infraestrutura — Script do Frontend
 * Lógica reativa unificada: relógio, polling da API, status ao vivo nos cards e clique para copiar
 *
 * @package Proxmox_Dashboard
 * @since   2.3.0
 */
(function () {
	'use strict';

	var cfg = window.proxmoxDashboard || {};
	var apiBase = cfg.restUrl || '';
	var intervalMs = parseInt(cfg.interval, 10) || 5000;
	var pollTimer = null;

	function el(id) {
		return document.getElementById(id);
	}

	function esc(str) {
		return String(str === undefined || str === null ? '' : str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function showToast(msg) {
		var t = el('pxd-toast');
		if (!t) return;
		t.textContent = msg || (cfg.i18n && cfg.i18n.copied) || 'Copiado para a área de transferência!';
		t.classList.add('is-visible');
		clearTimeout(t._hideTimer);
		t._hideTimer = setTimeout(function () {
			t.classList.remove('is-visible');
		}, 2200);
	}

	function setText(id, val) {
		var node = el(id);
		if (!node) return;
		node.textContent = (val === undefined || val === null || val === '') ? '–' : String(val);
	}

	function fmtBytes(bytes, prec) {
		prec = prec || 1;
		if (typeof bytes !== 'number' || isNaN(bytes) || bytes < 0) return '0 B';
		var units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
		var power = 0, value = bytes;
		while (value >= 1024 && power < units.length - 1) {
			value /= 1024;
			power++;
		}
		return value.toFixed(power > 0 ? prec : 0) + ' ' + units[power];
	}

	function fetchJson(path) {
		return fetch(apiBase + path, {
			credentials: 'same-origin',
			headers: { Accept: 'application/json' },
		}).then(function (res) {
			return res.json().catch(function () { return null; }).then(function (data) {
				if (!res.ok) {
					var err = new Error((data && data.message) || 'HTTP ' + res.status);
					err.status = res.status;
					throw err;
				}
				return data;
			});
		});
	}

	// 1. Relógio dinâmico no rodapé
	function updateClock() {
		var clockEl = el('clock');
		if (clockEl) {
			clockEl.textContent = new Date().toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'medium' });
		}
	}
	updateClock();
	setInterval(updateClock, 1000);

	// 2. Clique para copiar nos cards de containers e serviços
	function initClipboard() {
		var cards = document.querySelectorAll('.lxc-card, .docker-card');
		cards.forEach(function (card) {
			card.addEventListener('click', function (e) {
				// Se clicou em um link direto (<a>), permite abrir normalmente
				if (e.target.tagName === 'A' && !e.target.classList.contains('pxd-copyable')) {
					return;
				}

				var textToCopy = '';
				// Se clicou em um elemento com classe pxd-copyable, copia só ele
				if (e.target.classList.contains('pxd-copyable')) {
					textToCopy = e.target.textContent.trim();
				} else {
					textToCopy = card.innerText.trim();
				}

				if (textToCopy && navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(textToCopy).then(function () {
						showToast((cfg.i18n && cfg.i18n.copied) || 'Copiado para a área de transferência!');
						var oldOutline = card.style.outline;
						card.style.outline = '3px solid #087ff5';
						setTimeout(function () {
							card.style.outline = oldOutline;
						}, 450);
					}).catch(function () {});
				}
			});
		});
	}

	// 3. Alternador de Visualização (Mapa vs Tabelas)
	function initViewToggle() {
		var btnMapa = el('pxd-view-toggle');
		var btnTabelas = el('pxd-tables-toggle');
		var secContainers = el('section-containers');
		var secDocker = el('section-docker');
		var secTables = el('pxd-technical-tables');

		if (!btnMapa || !btnTabelas) return;

		btnMapa.addEventListener('click', function () {
			btnMapa.classList.add('is-active');
			btnTabelas.classList.remove('is-active');
			if (secContainers) secContainers.style.display = '';
			if (secDocker) secDocker.style.display = '';
			if (secTables) secTables.style.display = 'none';
		});

		btnTabelas.addEventListener('click', function () {
			btnTabelas.classList.add('is-active');
			btnMapa.classList.remove('is-active');
			if (secTables) secTables.style.display = '';
		});
	}

	// 4. Modo TV / Kiosk e Tela Cheia
	function initKioskAndFullscreen() {
		var kioskBtn = el('pxd-kiosk');
		if (kioskBtn) {
			kioskBtn.addEventListener('click', function () {
				document.body.classList.toggle('is-kiosk');
				var isKiosk = document.body.classList.contains('is-kiosk');
				var on = kioskBtn.querySelector('.pxd-icon--kiosk-on');
				var off = kioskBtn.querySelector('.pxd-icon--kiosk-off');
				if (on && off) {
					on.style.display = isKiosk ? 'none' : '';
					off.style.display = isKiosk ? '' : 'none';
				}
				showToast(isKiosk ? 'Modo TV ativado' : 'Modo TV desativado');
			});
		}

		var fsBtn = el('pxd-fullscreen');
		if (fsBtn) {
			fsBtn.addEventListener('click', function () {
				if (!document.fullscreenElement) {
					document.documentElement.requestFullscreen().catch(function () {});
				} else {
					document.exitFullscreen().catch(function () {});
				}
			});
		}

		var refreshBtn = el('pxd-refresh');
		if (refreshBtn) {
			refreshBtn.addEventListener('click', function () {
				refreshBtn.style.transform = 'rotate(180deg)';
				refreshBtn.style.transition = 'transform .4s ease';
				setTimeout(function () { refreshBtn.style.transform = ''; }, 400);
				pollAll();
			});
		}
	}

	// 5. Atualização de Gauges e Contadores
	function renderGauges(status) {
		if (!status) return;

		// Resumo de contadores
		setText('pxd-sum-nodes', status.nodeCount);
		setText('pxd-sum-vms', (status.vmsRunning !== undefined) ? status.vmsRunning : '–');
		setText('pxd-sum-lxc', (status.lxcRunning !== undefined) ? status.lxcRunning : '–');
		setText('pxd-sum-storage', (status.storageCount !== undefined) ? status.storageCount : '–');

		// CPU
		var cpuPct = Number(status.cpuPct) || 0;
		var cpuGauge = el('pxd-gauge-cpu');
		var cpuBar = el('pxd-bar-cpu');
		var cpuDetail = el('pxd-cpu-detail');
		if (cpuGauge) cpuGauge.innerHTML = '<span>' + cpuPct.toFixed(1) + '%</span>';
		if (cpuBar) cpuBar.style.width = Math.min(100, Math.max(0, cpuPct)) + '%';
		if (cpuDetail) cpuDetail.textContent = 'pico do cluster • ' + cpuPct.toFixed(1) + '%';

		// RAM
		var ramTotal = Number(status.ramTotal) || 0;
		var ramUsed = Number(status.ramUsed) || 0;
		var ramPct = ramTotal > 0 ? (ramUsed / ramTotal) * 100 : 0;
		var ramGauge = el('pxd-gauge-ram');
		var ramBar = el('pxd-bar-ram');
		var ramDetail = el('pxd-ram-detail');
		if (ramGauge) ramGauge.innerHTML = '<span>' + ramPct.toFixed(1) + '%</span>';
		if (ramBar) ramBar.style.width = Math.min(100, Math.max(0, ramPct)) + '%';
		if (ramDetail) ramDetail.textContent = 'Usado ' + fmtBytes(ramUsed) + ' de ' + fmtBytes(ramTotal);

		// Disco
		var diskTotal = Number(status.diskTotal) || 0;
		var diskUsed = Number(status.diskUsed) || 0;
		var diskPct = diskTotal > 0 ? (diskUsed / diskTotal) * 100 : 0;
		var diskGauge = el('pxd-gauge-disk');
		var diskBar = el('pxd-bar-disk');
		var diskDetail = el('pxd-disk-detail');
		if (diskGauge) diskGauge.innerHTML = '<span>' + diskPct.toFixed(1) + '%</span>';
		if (diskBar) diskBar.style.width = Math.min(100, Math.max(0, diskPct)) + '%';
		if (diskDetail) diskDetail.textContent = 'Usado ' + fmtBytes(diskUsed) + ' de ' + fmtBytes(diskTotal);
	}

	// 6. Atualização dos Cards de Containers com Status ao Vivo da API
	function updateContainerCards(vms, lxc) {
		var allResources = {};
		if (Array.isArray(vms)) {
			vms.forEach(function (v) { allResources[String(v.vmid)] = v; });
		}
		if (Array.isArray(lxc)) {
			lxc.forEach(function (c) { allResources[String(c.vmid)] = c; });
		}

		var pulses = document.querySelectorAll('.pxd-live-pulse[data-live-status]');
		pulses.forEach(function (p) {
			var vmid = String(p.getAttribute('data-live-status')).trim();
			var item = allResources[vmid];
			if (item) {
				var isRunning = (item.status === 'running' || item.status === 'online');
				p.className = 'pxd-live-pulse ' + (isRunning ? 'pxd-live-pulse--running' : 'pxd-live-pulse--stopped');
				p.setAttribute('title', isRunning ? 'Ativo (online)' : 'Parado (' + item.status + ')');

				// Mini medidor
				var card = p.closest('.lxc-card');
				if (card) {
					var meter = card.querySelector('.pxd-card-live-meter');
					if (meter && item.maxmem) {
						var memPct = Math.min(100, Math.round((item.mem / item.maxmem) * 100));
						var fill = meter.querySelector('.pxd-card-meter-fill');
						var txt = meter.querySelector('.pxd-card-meter-txt');
						if (fill) fill.style.width = memPct + '%';
						if (txt) txt.textContent = 'RAM: ' + memPct + '% (' + fmtBytes(item.mem) + ')';
						meter.style.display = 'flex';
					}
				}
			}
		});
	}

	// 7. Atualização do Health-Check dos Serviços Docker
	function updateDockerHealth(services) {
		if (!Array.isArray(services)) return;

		var serviceMap = {};
		services.forEach(function (s) {
			if (s.name) serviceMap[s.name.toLowerCase().trim()] = s;
			if (s.url) serviceMap[s.url.toLowerCase().trim()] = s;
		});

		var badges = document.querySelectorAll('.pxd-health-badge[data-health-badge]');
		badges.forEach(function (b) {
			var name = String(b.getAttribute('data-health-badge')).toLowerCase().trim();
			var match = serviceMap[name];
			if (match) {
				var isOnline = (match.status === 'online');
				b.className = 'pxd-health-badge ' + (isOnline ? 'pxd-health-badge--online' : 'pxd-health-badge--offline');
				var lbl = b.querySelector('.pxd-health-label');
				if (lbl) {
					lbl.textContent = isOnline ? ('Online' + (match.latency_ms ? ' (' + match.latency_ms + ' ms)' : '')) : 'Offline';
				}
			}
		});
	}

	// 8. Preenchimento das Tabelas Técnicas
	function renderTechnicalTables(nodes, storages) {
		var nodesBody = el('pxd-nodes-body');
		if (nodesBody && Array.isArray(nodes) && nodes.length) {
			setText('pxd-count-nodes', nodes.length + ' nodes');
			nodesBody.innerHTML = nodes.map(function (n) {
				var cpu = (Number(n.cpu) || 0) * 100;
				return '<tr>' +
					'<td><strong>' + esc(n.node) + '</strong></td>' +
					'<td><span class="tag ' + (n.status === 'online' ? 'ip' : 'url') + '">' + esc(n.status) + '</span></td>' +
					'<td>' + cpu.toFixed(1) + '%</td>' +
					'<td>' + fmtBytes(n.mem) + ' / ' + fmtBytes(n.maxmem) + '</td>' +
					'<td>' + fmtBytes(n.disk) + ' / ' + fmtBytes(n.maxdisk) + '</td>' +
					'<td>' + esc(n.uptime ? Math.round(n.uptime / 3600) + 'h' : '–') + '</td>' +
				'</tr>';
			}).join('');
		}

		var storageBody = el('pxd-storage-body');
		if (storageBody && Array.isArray(storages) && storages.length) {
			setText('pxd-count-storage-tab', storages.length + ' storages');
			storageBody.innerHTML = storages.map(function (s) {
				return '<tr>' +
					'<td><strong>' + esc(s.storage) + '</strong></td>' +
					'<td><code>' + esc(s.type) + '</code></td>' +
					'<td><span class="tag ' + (s.status === 'available' || s.status === 'active' ? 'ip' : 'lxc') + '">' + esc(s.status) + '</span></td>' +
					'<td>' + fmtBytes(s.total) + '</td>' +
					'<td>' + fmtBytes(s.used) + ' (' + (s.percent || 0) + '%)</td>' +
					'<td>' + fmtBytes(s.free) + '</td>' +
				'</tr>';
			}).join('');
		}
	}

	// 9. Ciclo Principal de Polling
	function pollAll() {
		var statusDot = el('pxd-status-dot');
		var statusText = el('pxd-status-text');
		var versionBadge = el('pxd-version');
		var lastUpdate = el('pxd-last-update');

		// 1) Status geral
		fetchJson('status').then(function (status) {
			if (statusDot) {
				statusDot.className = 'pxd-dot pxd-dot--ok';
			}
			if (statusText) statusText.textContent = (cfg.i18n && cfg.i18n.connected) || 'Proxmox Conectado';
			if (versionBadge && status.version) {
				versionBadge.textContent = 'PVE ' + status.version;
			}
			if (lastUpdate) {
				var now = new Date();
				lastUpdate.textContent = 'Atualizado às ' + now.toLocaleTimeString('pt-BR');
			}
			renderGauges(status);
		}).catch(function (err) {
			if (statusDot) statusDot.className = 'pxd-dot pxd-dot--error';
			if (statusText) statusText.textContent = (err && err.status === 401) ? 'Erro de Autenticação (401)' : 'Erro de Conexão';
		});

		// 2) VMs & LXC
		Promise.all([
			fetchJson('vms').catch(function () { return []; }),
			fetchJson('lxc').catch(function () { return []; }),
		]).then(function (results) {
			updateContainerCards(results[0], results[1]);
		});

		// 3) Health-Check dos serviços Docker
		fetchJson('services').then(function (services) {
			updateDockerHealth(services);
		}).catch(function () {});

		// 4) Nodes & Storage (para tabelas técnicas)
		Promise.all([
			fetchJson('nodes').catch(function () { return []; }),
			fetchJson('storage').catch(function () { return []; }),
		]).then(function (results) {
			renderTechnicalTables(results[0], results[1]);
		});
	}

	// Inicialização
	document.addEventListener('DOMContentLoaded', function () {
		initClipboard();
		initViewToggle();
		initKioskAndFullscreen();

		pollAll();
		if (intervalMs > 0) {
			pollTimer = setInterval(pollAll, intervalMs);
		}
	});

})();
