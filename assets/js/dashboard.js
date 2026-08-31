/**
 * Proxmox Dashboard - Lógica do frontend (Premium v2)
 */
(function () {
	'use strict';

	var cfg = window.proxmoxDashboard || {};
	var apiBase = cfg.restUrl || '';
	var intervalMs = parseInt(cfg.interval, 10) || 5000;
	var timer = null;

	var VMS_OK = ['running'];
	var LXC_OK = ['running'];

	function el(id) { return document.getElementById(id); }

	function showToast(msg) {
		var t = document.getElementById('pxd-toast');
		if (!t) {
			t = document.createElement('div');
			t.id = 'pxd-toast';
			t.className = 'pxd-toast';
			t.setAttribute('role', 'status');
			t.setAttribute('aria-live', 'polite');
			document.body.appendChild(t);
		}
		t.textContent = msg;
		t.classList.add('is-visible');
		clearTimeout(t._hideTimer);
		t._hideTimer = setTimeout(function(){ t.classList.remove('is-visible'); }, 2200);
	}

	function setText(id, value) {
		var node = el(id);
		if (!node) return;
		var str = value === undefined || value === null || value === '' ? '–' : String(value);
		// animação sutil de contador para números
		if (/^\d+$/.test(str) && node.textContent !== str) {
			animateCount(node, parseInt(node.textContent, 10) || 0, parseInt(str, 10) || 0);
		} else {
			node.textContent = str;
		}
	}

	function animateCount(node, from, to) {
		if (from === to || isNaN(from) || isNaN(to)) { node.textContent = to; return; }
		var start = null;
		var duration = 420;
		function step(ts) {
			if (!start) start = ts;
			var p = Math.min((ts - start) / duration, 1);
			var eased = 1 - Math.pow(1 - p, 3);
			node.textContent = Math.round(from + (to - from) * eased);
			if (p < 1) requestAnimationFrame(step);
			else node.textContent = to;
		}
		requestAnimationFrame(step);
	}

	function setBadge(id, count) {
		var node = el(id);
		if (!node) return;
		if (count === undefined || count === null) { node.textContent = '—'; return; }
		node.textContent = count + (count === 1 ? ' item' : ' itens');
	}

	function showStatus(state, text, version) {
		var dot = el('pxd-status-dot');
		var txt = el('pxd-status-text');
		if (dot) {
			dot.className = 'pxd-dot';
			if (state === 'ok') dot.classList.add('pxd-dot--ok');
			else if (state === 'error') dot.classList.add('pxd-dot--error');
		}
		if (txt) txt.textContent = text || '';
		var ver = el('pxd-version');
		if (ver) ver.textContent = version ? 'PVE ' + version : '';
	}

	function fetchJson(path) {
		return fetch(apiBase + path, {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce || '', Accept: 'application/json' },
		}).then(function (res) {
			return res.json().catch(function () { return null; }).then(function (data) {
				if (!res.ok) {
					var msg = (data && data.message) || 'HTTP ' + res.status + ' em "' + path + '"';
					var err = new Error(msg); err.status = res.status; err.data = data; throw err;
				}
				return data;
			});
		});
	}

	function fmtBytes(bytes, prec) {
		prec = prec || 1;
		if (typeof bytes !== 'number' || isNaN(bytes) || bytes < 0) return '0 B';
		var units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
		var power = 0, value = bytes;
		while (value >= 1024 && power < units.length - 1) { value /= 1024; power++; }
		return value.toFixed(power > 0 ? prec : 0) + ' ' + units[power];
	}
	function fmtPct(used, max) { if (!max) return '0%'; return Math.round((used / max) * 100) + '%'; }
	function fmtUptime(seconds) {
		seconds = parseInt(seconds, 10) || 0;
		if (seconds < 0) return '–';
		var d = Math.floor(seconds / 86400), h = Math.floor((seconds % 86400) / 3600), m = Math.floor((seconds % 3600) / 60);
		if (d > 0) return d + 'd ' + h + 'h ' + m + 'm';
		if (h > 0) return h + 'h ' + m + 'm';
		return m + 'min';
	}
	function statusBadge(status) {
		var cls = 'pxd-status pxd-status--' + (status || 'unknown');
		var labels = { running: 'Ativo', stopped: 'Parado', paused: 'Pausado', suspended: 'Suspenso', error: 'Erro', failed: 'Falhou', online: 'Online', offline: 'Offline', available: 'Disponível', disabled: 'Desabilitado', unknown: '—' };
		return '<span class="' + cls + '">' + (labels[status] || status || '—') + '</span>';
	}
	function cellBar(used, max) {
		if (!max) return '<div class="pxd-cell-bar"><span style="color:var(--pxd-text-faint)">–</span></div>';
		var pct = Math.min(100, Math.round((used / max) * 100));
		var extra = pct >= 90 ? ' pxd-cell-bar__fill--err' : pct >= 70 ? ' pxd-cell-bar__fill--warn' : '';
		return '<div class="pxd-cell-bar"><div class="pxd-cell-bar__track"><div class="pxd-cell-bar__fill' + extra + '" style="width:' + pct + '%"></div></div><span>' + pct + '%</span></div>';
	}
	function esc(str) {
		return String(str === undefined || str === null ? '' : str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
	}
	function renderError(tbody, message) {
		if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="pxd-empty">' + esc(message || 'Erro ao carregar') + '</td></tr>';
	}
	function renderEmpty(tbody, cols, message) {
		if (tbody) tbody.innerHTML = '<tr><td colspan="' + (cols || 8) + '" class="pxd-empty">' + esc(message || 'Nenhum dado encontrado') + '</td></tr>';
	}

	function renderSummary(status, vmCounts, lxcCounts, storageCount) {
		setText('pxd-sum-nodes', status.nodeCount);
		setText('pxd-sum-vms', vmCounts);
		setText('pxd-sum-lxc', lxcCounts);
		setText('pxd-sum-storage', storageCount);
	}

	function renderGauge(id, percent, variant) {
		var node = el(id);
		if (!node) return;
		percent = Math.max(0, Math.min(100, percent || 0));
		node.style.setProperty('--pxd-percent', percent);
		if (variant) { node.classList.add('pxd-gauge--' + variant); }
		var span = node.querySelector('span');
		if (span) span.textContent = percent.toFixed(1) + '%';
	}
	function setBar(id, pct) {
		var node = el(id);
		if (node) node.style.width = Math.max(0, Math.min(100, pct || 0)) + '%';
	}
	function renderUsage(u) {
		renderGauge('pxd-gauge-cpu', u.cpuPct, 'cpu');
		setBar('pxd-bar-cpu', u.cpuPct);
		var cpuDetail = el('pxd-cpu-detail');
		if (cpuDetail) cpuDetail.textContent = u.cpuPct ? 'pico do cluster • ' + u.cpuPct.toFixed(1) + '%' : 'pico do cluster';

		var ramPct = u.ramTotal ? (u.ramUsed / u.ramTotal) * 100 : 0;
		renderGauge('pxd-gauge-ram', ramPct, 'ram');
		setBar('pxd-bar-ram', ramPct);
		setText('pxd-ram-detail', 'Usado ' + fmtBytes(u.ramUsed) + ' de ' + fmtBytes(u.ramTotal));

		var diskPct = u.diskTotal ? (u.diskUsed / u.diskTotal) * 100 : 0;
		renderGauge('pxd-gauge-disk', diskPct, 'disk');
		setBar('pxd-bar-disk', diskPct);
		setText('pxd-disk-detail', 'Usado ' + fmtBytes(u.diskUsed) + ' de ' + fmtBytes(u.diskTotal));
	}

	function renderNodes(nodes) {
		var tbody = el('pxd-nodes-body');
		if (!tbody) return;
		if (!nodes || !nodes.length) { renderEmpty(tbody, 6, 'Nenhum node encontrado'); setBadge('pxd-count-nodes', 0); return; }
		setBadge('pxd-count-nodes', nodes.length);
		tbody.innerHTML = nodes.map(function (n) {
			var cpuPct = (Number(n.cpu) || 0) * 100;
			var memPct = fmtPct(n.mem, n.maxmem);
			return '<tr>' +
				'<td><strong>' + esc(n.node) + '</strong></td>' +
				'<td>' + statusBadge(n.status) + '</td>' +
				'<td>' + cellBar(cpuPct, 100) + '</td>' +
				'<td>' + fmtBytes(n.mem) + ' / ' + fmtBytes(n.maxmem) + ' <span style="color:var(--pxd-text-faint)">(' + memPct + ')</span></td>' +
				'<td>' + cellBar(n.disk, n.maxdisk) + '</td>' +
				'<td style="font-family:JetBrains Mono,monospace; font-size:12px">' + fmtUptime(n.uptime) + '</td>' +
				'</tr>';
		}).join('');
	}
	function renderVMs(vms) {
		var tbody = el('pxd-vms-body');
		if (!tbody) return;
		if (!vms || !vms.length) { renderEmpty(tbody, 6, 'Nenhuma VM encontrada'); setBadge('pxd-count-vms', 0); return; }
		setBadge('pxd-count-vms', vms.length);
		tbody.innerHTML = vms.map(function (v) {
			return '<tr>' +
				'<td style="font-family:JetBrains Mono,monospace; font-weight:600">#' + esc(v.vmid) + '</td>' +
				'<td><strong>' + esc(v.name) + '</strong></td>' +
				'<td>' + esc(v.node) + '</td>' +
				'<td>' + statusBadge(v.status) + '</td>' +
				'<td>' + cellBar((Number(v.cpu) || 0) * 100, 100) + '</td>' +
				'<td style="font-family:JetBrains Mono,monospace; font-size:12px">' + fmtBytes(v.mem) + ' / ' + fmtBytes(v.maxmem) + '</td>' +
				'</tr>';
		}).join('');
	}
	function renderLXC(lxc) {
		var tbody = el('pxd-lxc-body');
		if (!tbody) return;
		if (!lxc || !lxc.length) { renderEmpty(tbody, 6, 'Nenhum container encontrado'); setBadge('pxd-count-lxc', 0); return; }
		setBadge('pxd-count-lxc', lxc.length);
		tbody.innerHTML = lxc.map(function (c) {
			return '<tr>' +
				'<td style="font-family:JetBrains Mono,monospace; font-weight:600">#' + esc(c.vmid) + '</td>' +
				'<td><strong>' + esc(c.name) + '</strong></td>' +
				'<td>' + esc(c.node) + '</td>' +
				'<td>' + statusBadge(c.status) + '</td>' +
				'<td>' + cellBar((Number(c.cpu) || 0) * 100, 100) + '</td>' +
				'<td style="font-family:JetBrains Mono,monospace; font-size:12px">' + fmtBytes(c.mem) + ' / ' + fmtBytes(c.maxmem) + '</td>' +
				'</tr>';
		}).join('');
	}
	function renderStorage(storage) {
		var tbody = el('pxd-storage-body');
		if (!tbody) return;
		if (!storage || !storage.length) { renderEmpty(tbody, 6, 'Nenhum storage encontrado'); setBadge('pxd-count-storage', 0); return; }
		setBadge('pxd-count-storage', storage.length);
		tbody.innerHTML = storage.map(function (s) {
			return '<tr>' +
				'<td><strong>' + esc(s.storage) + '</strong></td>' +
				'<td><span class="pxd-type">' + esc(s.type) + '</span></td>' +
				'<td>' + statusBadge(s.status) + '</td>' +
				'<td style="font-family:JetBrains Mono,monospace; font-size:12px">' + fmtBytes(s.total) + '</td>' +
				'<td>' + cellBar(s.used, s.total) + '</td>' +
				'<td style="font-family:JetBrains Mono,monospace; font-size:12px">' + fmtBytes(s.free) + '</td>' +
				'</tr>';
		}).join('');
	}

	function renderServices(services) {
		var grid = el('pxd-services-grid');
		var empty = el('pxd-services-empty');
		var badge = el('pxd-count-services');
		if (!grid) return;

		if (!services || !services.length) {
			// Mantém fallback PHP se já houver cards; senão mostra empty
			if (grid.querySelector('.pxd-service-card')) {
				grid.style.display = '';
				if (empty) empty.style.display = 'none';
				if (badge) badge.textContent = grid.querySelectorAll('.pxd-service-card').length + ' itens';
				return;
			}
			grid.innerHTML = '';
			grid.style.display = 'none';
			if (empty) empty.style.display = 'block';
			if (badge) badge.textContent = '0 itens';
			return;
		}

		if (empty) empty.style.display = 'none';
		grid.style.display = '';
		grid.removeAttribute('style');
		if (badge) badge.textContent = services.length + (services.length === 1 ? ' item' : ' itens');

		grid.innerHTML = services.map(function (s) {
			var isOnline = s.status === 'online';
			var cls = 'pxd-service-card' + (isOnline ? '' : ' pxd-service-card--offline');
			var href = s.url ? ' href="' + esc(s.url) + '" target="_blank" rel="noopener"' : '';
			var tag = s.url ? 'a' : 'div';
			var iconHtml = '';
			if (s.icon) {
				var ic = String(s.icon).trim();
				// Se for URL de imagem, usa <img>; se for emoji (1-2 chars sem fa-), usa texto; senão Font Awesome <i class="">
				if (/^https?:\/\//i.test(ic)) {
					iconHtml = '<img src="' + esc(ic) + '" alt="" style="width:18px;height:18px;object-fit:contain;border-radius:4px" loading="lazy">';
				} else if (ic.indexOf('fa-') !== -1) {
					// Suporta "fa-solid fa-server" ou "fa-brands fa-git-alt"
					iconHtml = '<i class="' + esc(ic) + '" aria-hidden="true"></i>';
				} else {
					iconHtml = '<span style="font-size:15px;line-height:1">' + esc(ic) + '</span>';
				}
			} else {
				iconHtml = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 1v2"/><path d="M12 21v2"/><path d="M4.22 4.22l1.42 1.42"/><path d="M18.36 18.36l1.42 1.42"/><path d="M1 12h2"/><path d="M21 12h2"/><path d="M4.22 19.78l1.42-1.42"/><path d="M18.36 5.64l1.42-1.42"/></svg>';
			}
			var statusCls = isOnline ? 'pxd-service-card__status--online' : (s.status === 'checking' ? 'pxd-service-card__status--checking' : 'pxd-service-card__status--offline');
			var statusLabel = isOnline ? 'Online' : (s.status === 'checking' ? 'Verificando…' : 'Offline');
			var latency = (typeof s.latency_ms === 'number') ? s.latency_ms + ' ms' : '';
			var code = s.code ? 'HTTP ' + esc(String(s.code)) : '';
			var linkCls = s.url ? ' pxd-service-card--link' : '';

			return '<' + tag + href + ' class="' + cls + linkCls + '">' +
				'<div class="pxd-service-card__top">' +
					'<div class="pxd-service-card__icon">' + iconHtml + '</div>' +
					'<span class="pxd-service-card__status ' + statusCls + '">' + esc(statusLabel) + '</span>' +
				'</div>' +
				'<div class="pxd-service-card__name" title="' + esc(s.name) + '">' + esc(s.name) + '</div>' +
				'<div class="pxd-service-card__url" title="' + esc(s.url) + '">' + esc(s.url) + '</div>' +
				'<div class="pxd-service-card__meta">' +
					(latency ? '<code>' + esc(latency) + '</code>' : '') +
					(code ? '<span>' + code + '</span>' : '') +
					(s.error ? '<span style="color:var(--pxd-error);max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + esc(s.error) + '">' + esc(s.error) + '</span>' : '') +
				'</div>' +
			'</' + tag + '>';
		}).join('');
	}

	function updateLastUpdate() {
		var txt = 'Atualizado em ' + new Date().toLocaleTimeString();
		var n = el('pxd-last-update');
		if (n) n.textContent = txt;
		var n2 = el('pxd-last-update-footer');
		if (n2) n2.textContent = txt;
	}

	function aggregateUsage(resources) {
		var vmsOk = 0, lxcOk = 0, cpuPct = 0, ramUsed = 0, ramTotal = 0, diskUsed = 0, diskTotal = 0;
		var vms = (resources && resources.vms) || [];
		var lxc = (resources && resources.lxc) || [];
		vms.forEach(function (v) {
			if (VMS_OK.indexOf(v.status) !== -1) vmsOk++;
			ramUsed += v.mem || 0; ramTotal += v.maxmem || 0;
			diskUsed += v.disk || 0; diskTotal += v.maxdisk || 0;
			if (v.cpu) cpuPct = Math.max(cpuPct, (Number(v.cpu) || 0) * 100);
		});
		lxc.forEach(function (c) {
			if (LXC_OK.indexOf(c.status) !== -1) lxcOk++;
			ramUsed += c.mem || 0; ramTotal += c.maxmem || 0;
			diskUsed += c.disk || 0; diskTotal += c.maxdisk || 0;
			if (c.cpu) cpuPct = Math.max(cpuPct, (Number(c.cpu) || 0) * 100);
		});
		return { vmsActive: vmsOk, lxcActive: lxcOk, cpuPct: cpuPct, ramUsed: ramUsed, ramTotal: ramTotal, diskUsed: diskUsed, diskTotal: diskTotal };
	}

	function loadAll() {
		showStatus('', 'Carregando…');
		var refreshBtn = el('pxd-refresh');
		if (refreshBtn) { refreshBtn.disabled = true; refreshBtn.style.opacity = '.6'; }
		// Serviços: skeleton já visível no HTML
		var svcGrid = el('pxd-services-grid');
		if (svcGrid && !svcGrid.dataset.loading) {
			svcGrid.dataset.loading = '1';
		}
		var tasks = [
			{ key: 'status', name: 'status' },
			{ key: 'resources', name: 'resources' },
			{ key: 'nodes', name: 'nodes' },
			{ key: 'storage', name: 'storage' },
			{ key: 'services', name: 'services' },
		].map(function (t) {
			return fetchJson(t.name).then(function (data) { return { key: t.key, ok: true, data: data }; }, function (err) { return { key: t.key, ok: false, message: err && err.message }; });
		});
		Promise.all(tasks).then(function (results) {
			var byKey = {}; results.forEach(function (r) { byKey[r.key] = r; });
			var statusRes = byKey.status || {};
			var status = (statusRes.ok && statusRes.data) || {};
			var usage = { vmsActive: 0, lxcActive: 0, cpuPct: 0, ramUsed: 0, ramTotal: 0, diskUsed: 0, diskTotal: 0 };
			if (byKey.resources && byKey.resources.ok) usage = aggregateUsage(byKey.resources.data || {});
			var storageData = (byKey.storage && byKey.storage.ok && byKey.storage.data) || [];
			renderSummary(status, usage.vmsActive, usage.lxcActive, storageData.length);
			renderUsage(usage);
			if (byKey.nodes && byKey.nodes.ok) renderNodes(byKey.nodes.data || []);
			else if (byKey.nodes && !byKey.nodes.ok) renderError(el('pxd-nodes-body'), byKey.nodes.message);
			if (byKey.resources && byKey.resources.ok) {
				var resources = byKey.resources.data || {};
				renderVMs(resources.vms || []);
				renderLXC(resources.lxc || []);
			} else if (byKey.resources && !byKey.resources.ok) {
				renderError(el('pxd-vms-body'), byKey.resources.message);
				renderError(el('pxd-lxc-body'), byKey.resources.message);
			}
			if (byKey.storage && byKey.storage.ok) renderStorage(storageData);
			else if (byKey.storage && !byKey.storage.ok) renderError(el('pxd-storage-body'), byKey.storage.message);
			if (byKey.services) {
				if (byKey.services.ok) renderServices(byKey.services.data || []);
				else {
					var svcGrid2 = el('pxd-services-grid');
					if (svcGrid2) svcGrid2.innerHTML = '<div class="pxd-empty" style="grid-column:1/-1">' + esc(byKey.services.message || 'Erro ao verificar serviços') + '</div>';
				}
			}
			if (statusRes && statusRes.ok) showStatus('ok', cfg.i18n.connected || 'Proxmox conectado', status.version);
			else showStatus('error', (statusRes && statusRes.message) || cfg.i18n.error || 'Erro ao carregar dados');
			updateLastUpdate();
			if (refreshBtn) { refreshBtn.disabled = false; refreshBtn.style.opacity = '1'; }
		});
	}

	function initFullscreen() {
		var btn = el('pxd-fullscreen');
		var target = document.getElementById('proxmox-dashboard-page') || document.documentElement;
		if (!btn || !target) return;

		function isFullscreen() {
			return !!(document.fullscreenElement || document.webkitFullscreenElement);
		}
		function updateBtn() {
			var active = isFullscreen() || document.body.classList.contains('pxd-is-fullscreen-fallback');
			var iconExpand = btn.querySelector('.pxd-icon--expand');
			var iconCompress = btn.querySelector('.pxd-icon--compress');
			var page = document.getElementById('proxmox-dashboard-page');
			if (page) {
				if (active) page.classList.add('is-fullscreen');
				else page.classList.remove('is-fullscreen');
			}
			btn.classList.toggle('is-active', active);
			btn.setAttribute('aria-label', active ? 'Sair da tela cheia' : 'Tela cheia');
			btn.setAttribute('title', active ? 'Sair da tela cheia (ESC)' : 'Tela cheia (F11 / clique para tela cheia)');
			if (iconExpand) iconExpand.style.display = active ? 'none' : 'block';
			if (iconCompress) iconCompress.style.display = active ? 'block' : 'none';
		}
		function enterFullscreen() {
			var p = null;
			if (target.requestFullscreen) p = target.requestFullscreen();
			else if (target.webkitRequestFullscreen) p = target.webkitRequestFullscreen();
			if (p && p.catch) {
				p.catch(function () {
					// Fallback para browsers que bloqueiam fullscreen (ex: iframe sem allow)
					document.body.classList.add('pxd-is-fullscreen-fallback');
					target.classList.add('is-fullscreen');
					updateBtn();
				});
			}
			// Fallback imediato se API não existir
			if (!document.fullscreenEnabled && !document.webkitFullscreenEnabled) {
				document.body.classList.add('pxd-is-fullscreen-fallback');
				target.classList.add('is-fullscreen');
				updateBtn();
			}
		}
		function exitFullscreen() {
			if (document.body.classList.contains('pxd-is-fullscreen-fallback')) {
				document.body.classList.remove('pxd-is-fullscreen-fallback');
				var page = document.getElementById('proxmox-dashboard-page');
				if (page) page.classList.remove('is-fullscreen');
				updateBtn();
				return;
			}
			if (document.exitFullscreen) document.exitFullscreen();
			else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
		}
		btn.addEventListener('click', function () {
			if (isFullscreen() || document.body.classList.contains('pxd-is-fullscreen-fallback')) { exitFullscreen(); showToast('Saiu da tela cheia'); }
			else { enterFullscreen(); showToast('Tela cheia ativada — ESC para sair'); }
		});
		// ESC também sai do fallback
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && document.body.classList.contains('pxd-is-fullscreen-fallback')) {
				document.body.classList.remove('pxd-is-fullscreen-fallback');
				var page = document.getElementById('proxmox-dashboard-page');
				if (page) page.classList.remove('is-fullscreen');
				updateBtn();
			}
		});
		document.addEventListener('fullscreenchange', updateBtn);
		document.addEventListener('webkitfullscreenchange', updateBtn);
		updateBtn();
	}

	function initKiosk() {
		var btn = el('pxd-kiosk');
		var shell = document.querySelector('.pxd-shell');
		if (!shell) return;

		var STORAGE_KEY = 'pxd-kiosk';
		var isKiosk = false;
		var scaleTimer = null;

		function hasKioskParam() {
			try {
				var p = new URLSearchParams(window.location.search);
				return p.has('kiosk') || p.get('kiosk') === '1';
			} catch (e) { return window.location.search.indexOf('kiosk') !== -1; }
		}

		function updateBtn() {
			if (!btn) return;
			var on = btn.querySelector('.pxd-icon--kiosk-on');
			var off = btn.querySelector('.pxd-icon--kiosk-off');
			btn.classList.toggle('is-active', isKiosk);
			btn.setAttribute('aria-pressed', isKiosk ? 'true' : 'false');
			btn.setAttribute('aria-label', isKiosk ? 'Sair do Modo TV' : 'Modo TV');
			btn.setAttribute('title', isKiosk ? 'Sair do Modo TV (sem rolagem)' : 'Modo TV — encaixa tudo na tela sem rolagem');
			if (on) on.style.display = isKiosk ? 'none' : 'block';
			if (off) off.style.display = isKiosk ? 'block' : 'none';
		}

		function applyScale() {
			if (!isKiosk) {
				document.body.classList.remove('pxd-kiosk--scaled');
				shell.style.removeProperty('--pxd-kiosk-scale');
				return;
			}
			// reseta para medir altura natural
			document.body.classList.remove('pxd-kiosk--scaled');
			shell.style.setProperty('--pxd-kiosk-scale', '1');
			// mede overflow real
			var vh = window.innerHeight;
			var sh = shell.scrollHeight;
			// se couber, sem escala
			if (sh <= vh + 2) {
				shell.style.setProperty('--pxd-kiosk-scale', '1');
				return;
			}
			// escala proporcional para caber 100% da altura (com margem 2%)
			var scale = Math.max(0.55, Math.min(1, (vh / sh) * 0.985));
			// arredonda para evitar blur
			scale = Math.floor(scale * 100) / 100;
			shell.style.setProperty('--pxd-kiosk-scale', String(scale));
			if (scale < 0.985) document.body.classList.add('pxd-kiosk--scaled');
		}

		function scheduleScale() {
			if (scaleTimer) clearTimeout(scaleTimer);
			scaleTimer = setTimeout(applyScale, 80);
		}

		function setKiosk(active, persist) {
			isKiosk = !!active;
			if (isKiosk) {
				document.body.classList.add('pxd-kiosk');
				document.documentElement.classList.add('pxd-kiosk');
			} else {
				document.body.classList.remove('pxd-kiosk');
				document.documentElement.classList.remove('pxd-kiosk');
				document.body.classList.remove('pxd-kiosk--scaled');
				shell.style.removeProperty('--pxd-kiosk-scale');
			}
			updateBtn();
			if (persist) {
				try { localStorage.setItem(STORAGE_KEY, isKiosk ? '1' : '0'); } catch (e) {}
			}
			// notifica CSS que precisa recalcular
			scheduleScale();
			// força reflow para tabelas recém-renderizadas
			setTimeout(applyScale, 300);
		}

		// toggle
		if (btn) {
			btn.addEventListener('click', function () {
				var next = !isKiosk;
				setKiosk(next, true);
				showToast(next ? 'Modo TV ativado — sem rolagem' : 'Modo TV desativado');
			});
		}
		// ESC sai do modo TV
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && isKiosk) setKiosk(false, true);
		});
		window.addEventListener('resize', scheduleScale);
		// re-escala após carregar dados (tabelas mudam altura)
		var origLoadAll = loadAll;
		// hook: após render, agenda escala
		var _scheduleHook = function () { scheduleScale(); setTimeout(applyScale, 400); };
		// monkey-patch leve: observa mudanças no main
		var main = document.getElementById('pxd-main') || shell;
		if (window.MutationObserver && main) {
			var obs = new MutationObserver(function () { if (isKiosk) scheduleScale(); });
			obs.observe(main, { childList: true, subtree: true });
		}

		// estado inicial: ?kiosk=1 tem prioridade, senão localStorage
		var initial = hasKioskParam() ? true : false;
		if (!hasKioskParam()) {
			try { initial = localStorage.getItem(STORAGE_KEY) === '1'; } catch (e) {}
		}
		if (initial) setKiosk(true, false);
		else updateBtn();

		// expõe para debug / automação TV: window.pxdKiosk(true/false)
		window.pxdKiosk = function (v) {
			if (typeof v === 'boolean') setKiosk(v, true);
			return isKiosk;
		};
		window.pxdKioskScale = applyScale;
	}

	function init() {
		// Botões header sempre ativos (mesmo se dashboard ainda não renderizou)
		initFullscreen();
		initKiosk();
		if (!document.getElementById('pxd-nodes-body')) return;
		var refreshBtn = el('pxd-refresh');
		if (refreshBtn) refreshBtn.addEventListener('click', loadAll);
		loadAll();
		if (intervalMs >= 1000) timer = setInterval(loadAll, intervalMs);
		document.addEventListener('visibilitychange', function () {
			if (document.hidden) { if (timer) clearInterval(timer); }
			else { loadAll(); if (intervalMs >= 1000) timer = setInterval(loadAll, intervalMs); }
		});
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();
