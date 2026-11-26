(function () {
  'use strict';
  function nextIndex(container) {
    let max = -1;
    container.querySelectorAll('[name*="[').forEach(el => {
      const m = el.name.match(/\[(\d+)\]/g);
      if (!m) return;
      m.forEach(g => { const n = parseInt(g.replace(/\[|\]/g, ''), 10); if (!isNaN(n)) max = Math.max(max, n); });
    });
    return max + 1;
  }
  function addFromTemplate(tplId, containerId) {
    const tpl = document.getElementById(tplId);
    const container = document.getElementById(containerId);
    if (!tpl || !container) return;
    const idx = nextIndex(container);
    const html = tpl.innerHTML.replace(/__INDEX__/g, String(idx));
    container.insertAdjacentHTML('beforeend', html);
  }

  document.addEventListener('click', function (e) {
    if (e.target.matches('#pvd-add-container')) addFromTemplate('pvd-tpl-container', 'pvd-containers');
    if (e.target.matches('#pvd-add-docker')) addFromTemplate('pvd-tpl-docker', 'pvd-docker');
    if (e.target.matches('#pvd-add-storage')) addFromTemplate('pvd-tpl-storage', 'pvd-storage');
    if (e.target.classList.contains('pvd-remove-row')) {
      const row = e.target.closest('.pvd-repeater-row');
      if (row) row.remove();
    }
    if (e.target.classList.contains('pvd-media-btn')) {
      const targetId = e.target.getAttribute('data-target');
      const input = document.getElementById(targetId);
      if (!input || !window.wp || !wp.media) return;
      const frame = wp.media({ title: 'Selecionar imagem', button: { text: 'Usar' }, multiple: false });
      frame.on('select', function () {
        const att = frame.state().get('selection').first().toJSON();
        input.value = att.url || '';
      });
      frame.open();
    }
  });
})();