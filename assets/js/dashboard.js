(function () {
  'use strict';

  const cfg = window.pvdDashboard || {};
  const restBase = (cfg.restUrl || '').replace(/\/$/, '');
  const refreshMs = Math.max(0, parseInt(cfg.refreshInterval || 0, 10));
  let refreshing = false;

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const fmtBytes = (n) => {
    const b = Math.max(0, Number(n) || 0);
    const u = ['B','KB','MB','GB','TB','PB'];
    let i = 0, v = b;
    while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
    return (i === 0 ? Math.round(v) : v.toFixed(1)) + ' ' + u[i];
  };
  const fmtPct = (used, total) => total > 0 ? ((used / total) * 100) : 0;
  const fmtUptime = (sec) => {
    sec = Math.max(0, parseInt(sec || 0, 10));
    const d = Math.floor(sec / 86400), h = Math.floor((sec % 86400) / 3600), m = Math.floor((sec % 3600) / 60);
    if (d > 0) return `${d}d ${h}h ${m}m`;
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
  };
  const statusClass = (s) => {
    s = String(s || '').toLowerCase();
    if (['running','online','available','active'].includes(s)) return 'pvd-badge pvd-badge--ok';
    if (['stopped','offline','inactive','disabled','error','failed'].includes(s)) return 'pvd-badge pvd-badge--err';
    if (['paused','suspended','unknown'].includes(s)) return 'pvd-badge pvd-badge--warn';
    return 'pvd-badge pvd-badge--muted';
  };
  const statusLabel = (s) => {
    const map = {running:'Ativo',stopped:'Parado',paused:'Pausado',online:'Online',offline:'Offline',available:'Disponível',unknown:'Desconhecido'};
    const k = String(s||'').toLowerCase();
    return map[k] || (s || '--');
  };

  function iconHtml(icon, alt) {
    if (icon && (icon.includes('/') || /\.(svg|png|jpe?g|webp|gif)$/i.test(icon))) {
      return `<img src="${esc(icon)}" alt="${esc(alt||'')}" class="card-icon" loading="lazy">`;
    }
    return esc(icon || '▣');
  }
  function defaultIconForContainer(name) {
    const n = String(name||'').toLowerCase();
    if (n.includes('docker')) return cfg.themeUri ? cfg.themeUri + '/img/docker.svg' : 'img/docker.svg';
    if (n.includes('cloudflared')) return 'img/cloudflare.svg';
    if (n.includes('pihole') || n.includes('pi-hole')) return 'img/pi-hole.svg';
    if (n.includes('vaultwarden')) return 'img/vaultwarden.svg';
    if (n.includes('wordpress')) return 'img/wordpress.svg';
    if (n.includes('gitea')) return 'img/gitea.svg';
    return 'img/box.svg';
  }
  function defaultIconForDocker(name) {
    const n = String(name||'').toLowerCase();
    if (n.includes('portainer')) return 'img/portainer.svg';
    if (n.includes('gitea')) return 'img/gitea.svg';
    if (n.includes('jellyfin')) return 'img/jellyfin.svg';
    if (n.includes('qbittorrent')) return 'img/qbittorrent.svg';
    if (n.includes('metube')) return 'img/metube.svg';
    if (n.includes('navidrome')) return 'img/navidrome.svg';
    if (n.includes('note')) return 'img/notes.svg';
    if (n.includes('arquivo') || n.includes('filebrowser')) return 'img/filebrowser.svg';
    return 'img/files.svg';
  }
  function resolveIcon(icon, fallbackName, type) {
    if (icon && String(icon).trim()) return icon;
    const base = (cfg.themeUri || '').replace(/\/$/, '');
    const file = type === 'docker' ? defaultIconForDocker(fallbackName) : defaultIconForContainer(fallbackName);
    return base ? base + '/' + file : file;
  }

  function renderLxc() {
    const grid = document.getElementById('lxcGrid');
    if (!grid) return;
    const items = Array.isArray(cfg.lxc) ? cfg.lxc : [];
    if (!items.length) {
      grid.innerHTML = `<div class="pvd-empty-block">Nenhum container LXC configurado. Adicione no painel administrativo.</div>`;
      return;
    }
    grid.innerHTML = items.map(x => `
      <article class="lxc-card" title="Clique para copiar as informações">
        <div class="lxc-head"><span class="id-badge">${esc(x.id)}</span><h3>${esc(x.name)}</h3></div>
        <div class="icon-box">${iconHtml(resolveIcon(x.icon, x.name, 'lxc'), x.name)}</div>
        <div class="meta">
          <b>IP:</b> ${esc(x.ip || '—')}<br>
          <b>MAC:</b> ${esc(x.mac || '—')}<br>
          <b>URL:</b> <span class="link">${esc(x.url || '—')}</span>
        </div>
        <div class="function"><b>Função</b><br>${esc(x.fn || '—')}</div>
      </article>`).join('');
  }

  function renderDocker() {
    const grid = document.getElementById('dockerGrid');
    if (!grid) return;
    const items = Array.isArray(cfg.docker) ? cfg.docker : [];
    if (!items.length) {
      grid.innerHTML = `<div class="pvd-empty-block">Nenhum serviço Docker configurado. Adicione no painel administrativo.</div>`;
      return;
    }
    grid.innerHTML = items.map(x => `
      <article class="docker-card" title="Clique para copiar">
        <div class="service-icon">${iconHtml(resolveIcon(x.icon, x.name, 'docker'), x.name)}</div>
        <h4>${esc(x.name)}</h4>
        <p><b>Porta:</b> ${esc(x.port || '—')}<br><span class="domain">${esc(x.domain || '—')}</span></p>
      </article>`).join('');
  }

  function bindCopy() {
    document.querySelectorAll('.lxc-card, .docker-card').forEach(card => {
      if (card.dataset.copyBound) return;
      card.dataset.copyBound = '1';
      card.style.cursor = 'pointer';
      card.addEventListener('click', async () => {
        const text = card.innerText.trim();
        try {
          await navigator.clipboard.writeText(text);
          const old = card.style.outline;
          card.style.outline = '3px solid #21a9e8';
          setTimeout(() => { card.style.outline = old; }, 500);
        } catch (_) {}
      });
    });
  }

  function setMeta(id, text) { const el = document.getElementById(id); if (el) el.textContent = text; }

  function renderMetrics(status) {
    const grid = document.getElementById('metricsGrid');
    if (!grid) return;
    const cpu = Math.max(0, Math.min(100, (Number(status.cpuTotal) || 0) * 100));
    const ramPct = fmtPct(status.ramUsed, status.ramTotal);
    const diskPct = fmtPct(status.diskUsed, status.diskTotal);
    const nodes = Number(status.nodeCount) || 0;
    const onlineTxt = nodes > 0 ? `${nodes} node(s)` : '—';
    const cards = [
      { label:'CPU', value: cpu.toFixed(1)+'%', sub: `Média do cluster • ${onlineTxt}`, pct: cpu, cls:'cpu' },
      { label:'Memória RAM', value: ramPct.toFixed(1)+'%', sub: `${fmtBytes(status.ramUsed)} / ${fmtBytes(status.ramTotal)}`, pct: ramPct, cls:'ram' },
      { label:'Armazenamento', value: diskPct.toFixed(1)+'%', sub: `${fmtBytes(status.diskUsed)} / ${fmtBytes(status.diskTotal)}`, pct: diskPct, cls:'disk' },
      { label:'Nodes / Storages', value: String(nodes), sub: `${Number(status.storageCount)||0} storage(s) • Uptime ${fmtUptime(status.uptime)}`, pct: nodes > 0 ? 100 : 0, cls:'nodes' },
    ];
    grid.innerHTML = cards.map(c => `
      <article class="metric-card">
        <div class="metric-card__head"><span class="metric-card__label">${esc(c.label)}</span><span class="metric-card__value">${esc(c.value)}</span></div>
        <div class="metric-bar"><div class="metric-bar__fill metric-bar__fill--${c.cls}" style="width:${Math.max(0,Math.min(100,c.pct)).toFixed(1)}%"></div></div>
        <div class="metric-card__sub">${esc(c.sub)}</div>
      </article>`).join('');
    setMeta('metrics-meta', `ATUALIZADO ${new Date().toLocaleTimeString('pt-BR')}`);
  }

  function renderStorage(storages) {
    const grid = document.getElementById('storageGrid');
    if (!grid) return;
    const custom = Array.isArray(cfg.storageCustom) ? cfg.storageCustom : [];
    if (custom.length > 0) {
      grid.innerHTML = custom.map(s => `
        <article class="storage-card">
          <div class="storage-card__head">
            <div class="drive">${esc(s.drive || 'DISK')}</div>
            <div>
              <h3 class="storage-card__title">${esc(s.title || 'Armazenamento')}</h3>
              <div class="storage-card__meta">Configurado no painel administrativo</div>
            </div>
          </div>
          <ul class="storage-card__items">${String(s.items||'').split(/\r?\n/).filter(Boolean).map(i=>`<li>${esc(i)}</li>`).join('') || '<li>—</li>'}</ul>
        </article>`).join('');
      setMeta('storage-meta', 'PERSONALIZADO');
      return;
    }

    const items = Array.isArray(storages) ? storages : [];
    if (!items.length) {
      grid.innerHTML = `<article class="storage-card"><div class="storage-card__head"><div class="drive">—</div><div><h3 class="storage-card__title">Sem dados de armazenamento</h3><div class="storage-card__meta">Aguardando resposta da API do Proxmox.</div></div></div></article>`;
      setMeta('storage-meta', 'SEM DADOS');
      return;
    }
    grid.innerHTML = items.map(s => {
      const pct = Math.max(0, Math.min(100, Number(s.percent) || 0));
      const label = String(s.type || '').toLowerCase().includes('dir') ? 'DIR' : String(s.type || 'storage').slice(0, 4).toUpperCase();
      return `
      <article class="storage-card">
        <div class="storage-card__head">
          <div class="drive">${esc(label)}</div>
          <div style="min-width:0;flex:1">
            <h3 class="storage-card__title">${esc(s.storage || 'Storage')}</h3>
            <div class="storage-card__meta">Tipo: ${esc(s.type || '—')} • Node: ${esc(s.node || '—')} • Status: ${esc(statusLabel(s.status))}</div>
          </div>
        </div>
        <div class="storage-bar"><div class="storage-bar__fill" style="width:${pct.toFixed(1)}%"></div></div>
        <div class="storage-card__stats">
          <div class="storage-card__stat"><b>${esc(fmtBytes(s.used))}</b>Usado</div>
          <div class="storage-card__stat"><b>${esc(fmtBytes(s.free))}</b>Livre</div>
          <div class="storage-card__stat"><b>${esc(fmtBytes(s.total))}</b>Total</div>
        </div>
      </article>`;
    }).join('');
    setMeta('storage-meta', `${items.length} STORAGE(S)`);
  }

  function usageCell(used, total) {
    const pct = total > 0 ? (used / total) * 100 : 0;
    const cls = pct >= 90 ? 'pvd-usage__fill pvd-usage__fill--crit' : pct >= 75 ? 'pvd-usage__fill pvd-usage__fill--high' : 'pvd-usage__fill';
    return `<div class="pvd-usage"><div class="pvd-usage__bar"><div class="${cls}" style="width:${Math.max(0,Math.min(100,pct)).toFixed(1)}%"></div></div><span class="pvd-mono">${pct.toFixed(0)}%</span></div>`;
  }

  function renderTables(nodes, vms, lxc, storages) {
    const tn = document.querySelector('#table-nodes tbody');
    if (tn) tn.innerHTML = (nodes||[]).length ? nodes.map(n => `<tr>
      <td>${esc(n.node)}</td><td><span class="${statusClass(n.status)}">${esc(statusLabel(n.status))}</span></td>
      <td>${usageCell((Number(n.cpu)||0),100)}<div class="pvd-mono">${Number(n.cpu||0).toFixed(1)}%</div></td>
      <td>${usageCell(n.mem,n.maxmem)}<div class="pvd-mono">${esc(fmtBytes(n.mem))} / ${esc(fmtBytes(n.maxmem))}</div></td>
      <td>${usageCell(n.disk,n.maxdisk)}<div class="pvd-mono">${esc(fmtBytes(n.disk))} / ${esc(fmtBytes(n.maxdisk))}</div></td>
      <td class="pvd-mono">${esc(fmtUptime(n.uptime))}</td></tr>`).join('') : `<tr><td colspan="6" class="pvd-empty">Nenhum node encontrado.</td></tr>`;

    const renderVMRows = (rows, tbody, emptyCols) => {
      if (!tbody) return;
      tbody.innerHTML = (rows||[]).length ? rows.map(r => `<tr>
        <td class="pvd-mono">${esc(r.vmid)}</td><td>${esc(r.name)}</td><td>${esc(r.node)}</td>
        <td><span class="${statusClass(r.status)}">${esc(statusLabel(r.status))}</span></td>
        <td>${usageCell(Number(r.cpu||0),100)}<div class="pvd-mono">${Number(r.cpu||0).toFixed(1)}%</div></td>
        <td>${usageCell(r.mem,r.maxmem)}<div class="pvd-mono">${esc(fmtBytes(r.mem))} / ${esc(fmtBytes(r.maxmem))}</div></td>
        <td>${usageCell(r.disk,r.maxdisk)}<div class="pvd-mono">${esc(fmtBytes(r.disk))} / ${esc(fmtBytes(r.maxdisk))}</div></td>
        <td class="pvd-mono">${esc(fmtUptime(r.uptime))}</td></tr>`).join('') : `<tr><td colspan="${emptyCols}" class="pvd-empty">Sem dados.</td></tr>`;
    };
    renderVMRows(vms, document.querySelector('#table-vms tbody'), 8);
    renderVMRows(lxc, document.querySelector('#table-lxc tbody'), 8);

    const ts = document.querySelector('#table-storage tbody');
    if (ts) ts.innerHTML = (storages||[]).length ? storages.map(s => `<tr>
      <td>${esc(s.storage)}</td><td>${esc(s.type)}</td>
      <td><span class="${statusClass(s.status)}">${esc(statusLabel(s.status))}</span></td>
      <td class="pvd-mono">${esc(fmtBytes(s.total))}</td><td class="pvd-mono">${esc(fmtBytes(s.used))}</td>
      <td class="pvd-mono">${esc(fmtBytes(s.free))}</td>
      <td>${usageCell(s.used,s.total)}<div class="pvd-mono">${Number(s.percent||0).toFixed(1)}%</div></td>
      <td>${esc(s.node || '—')}</td></tr>`).join('') : `<tr><td colspan="8" class="pvd-empty">Sem dados de storage.</td></tr>`;
  }

  async function fetchJson(endpoint) {
    const res = await fetch(`${restBase}/${endpoint}`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || `HTTP ${res.status}`);
    return data;
  }

  async function refresh() {
    if (refreshing) return;
    refreshing = true;
    setMeta('metrics-meta', 'ATUALIZANDO...');
    try {
      const [status, nodes, resources, storages] = await Promise.all([
        fetchJson('status'),
        fetchJson('nodes').catch(() => []),
        fetchJson('resources').catch(() => ({vms:[],lxc:[]})),
        fetchJson('storage').catch(() => []),
      ]);
      renderMetrics(status || {});
      renderStorage(storages || []);
      renderTables(nodes || [], (resources||{}).vms || [], (resources||{}).lxc || [], storages || []);
    } catch (e) {
      setMeta('metrics-meta', 'ERRO NA API');
      const msg = esc(e.message || 'Erro');
      ['table-nodes','table-vms','table-lxc','table-storage'].forEach(id => {
        const tb = document.querySelector(`#${id} tbody`);
        if (tb) tb.innerHTML = `<tr><td colspan="8" class="pvd-empty">Erro ao carregar: ${msg}</td></tr>`;
      });
      const mg = document.getElementById('metricsGrid');
      if (mg) mg.innerHTML = `<div class="metric-card"><div class="metric-card__label">API</div><div class="metric-card__value" style="font-size:16px;color:#bf3d3d">Erro de conexão</div><div class="metric-card__sub">${msg}</div></div>`;
    } finally {
      refreshing = false;
    }
  }

  function updateClock() {
    const el = document.getElementById('clock');
    if (el) el.textContent = new Date().toLocaleString('pt-BR', { dateStyle:'short', timeStyle:'medium' });
  }

  document.addEventListener('DOMContentLoaded', () => {
    // expose themeUri from localized script if present
    if (!cfg.themeUri) {
      const css = document.querySelector('link[href*="assets/css/dashboard.css"]');
      if (css) cfg.themeUri = css.href.replace(/\/assets\/css\/dashboard\.css.*$/, '');
    }
    renderLxc();
    renderDocker();
    bindCopy();
    updateClock();
    setInterval(updateClock, 1000);

    if (cfg.isConfigured) {
      refresh();
      if (refreshMs > 0) setInterval(refresh, refreshMs);
    } else {
      setMeta('metrics-meta', 'AGUARDANDO CONFIGURAÇÃO');
      setMeta('storage-meta', 'AGUARDANDO CONFIGURAÇÃO');
      ['table-nodes','table-vms','table-lxc','table-storage'].forEach(id => {
        const tb = document.querySelector(`#${id} tbody`);
        const cols = tb?.parentElement?.querySelectorAll('thead th').length || 8;
        if (tb) tb.innerHTML = `<tr><td colspan="${cols}" class="pvd-empty">Configure a API do Proxmox no painel administrativo para ver dados dinâmicos.</td></tr>`;
      });
      const mg = document.getElementById('metricsGrid');
      if (mg) mg.innerHTML = `
        <div class="metric-card"><div class="metric-card__head"><span class="metric-card__label">CPU</span><span class="metric-card__value">--</span></div><div class="metric-bar"><div class="metric-bar__fill metric-bar__fill--cpu" style="width:0%"></div></div><div class="metric-card__sub">Aguardando API</div></div>
        <div class="metric-card"><div class="metric-card__head"><span class="metric-card__label">Memória RAM</span><span class="metric-card__value">--</span></div><div class="metric-bar"><div class="metric-bar__fill metric-bar__fill--ram" style="width:0%"></div></div><div class="metric-card__sub">Aguardando API</div></div>
        <div class="metric-card"><div class="metric-card__head"><span class="metric-card__label">Armazenamento</span><span class="metric-card__value">--</span></div><div class="metric-bar"><div class="metric-bar__fill metric-bar__fill--disk" style="width:0%"></div></div><div class="metric-card__sub">Aguardando API</div></div>
        <div class="metric-card"><div class="metric-card__head"><span class="metric-card__label">Nodes</span><span class="metric-card__value">--</span></div><div class="metric-bar"><div class="metric-bar__fill metric-bar__fill--nodes" style="width:0%"></div></div><div class="metric-card__sub">Aguardando API</div></div>`;
      renderStorage([]); // will use custom or empty
    }
  });
})();