(() => {
  const $ = (s, r = document) => r.querySelector(s), $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const csrf = $('meta[name=csrf]')?.content || '';
  const root = document.documentElement;

  // Mode terang / gelap (tersimpan di browser)
  $('[data-theme-toggle]')?.addEventListener('click', () => {
    const t = root.dataset.theme === 'dark' ? 'light' : 'dark';
    root.dataset.theme = t;
    try { localStorage.setItem('theme', t); } catch (e) {}
  });

  // Tekan "/" untuk fokus ke pencarian
  addEventListener('keydown', e => {
    if (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) { e.preventDefault(); $('.search input')?.focus(); }
  });

  // Sidebar di HP
  $$('[data-drawer]').forEach(b => b.addEventListener('click', () => $('#side')?.classList.toggle('open')));
  $$('[data-autosubmit]').forEach(el => el.addEventListener('change', () => el.form.submit()));

  // Simpan / hapus dari favorit
  document.addEventListener('click', async e => {
    const b = e.target.closest('[data-fav]');
    if (!b) return;
    e.preventDefault();
    const fd = new FormData(); fd.append('item', b.dataset.fav); fd.append('_csrf', csrf);
    const r = await fetch('/api.php?a=fav', { method: 'POST', body: fd });
    if (r.status === 401) { location.href = '/login.php?next=' + encodeURIComponent(location.pathname + location.search); return; }
    const j = await r.json();
    $$(`[data-fav="${b.dataset.fav}"]`).forEach(x => x.classList.toggle('on', j.on));
  });

  // Galeri foto di halaman barang
  $$('.thumbs button[data-src]').forEach(b => b.addEventListener('click', () => {
    $('#gal').src = b.dataset.src;
    $$('.thumbs button').forEach(x => x.setAttribute('aria-current', x === b));
  }));

  // Pratinjau foto sebelum diunggah
  const file = $('input[type=file][data-preview]');
  file?.addEventListener('change', () => {
    const max = +file.dataset.preview, box = $('#previews'); box.innerHTML = '';
    if (file.files.length > max) { alert(`Maksimal ${max} foto lagi untuk barang ini.`); file.value = ''; return; }
    [...file.files].forEach(f => { const i = new Image(); i.src = URL.createObjectURL(f); i.alt = ''; box.append(i); });
  });

  // Chat: kirim pesan dan polling tiap 3 detik
  const box = $('#msgs'), form = $('#chat-form');
  if (box && form) {
    let last = +box.dataset.last || 0; const cid = box.dataset.conv, ta = $('textarea', form);
    const down = () => box.scrollTop = box.scrollHeight;
    const add = m => {
      const d = document.createElement('div'); d.className = 'msg' + (m.me ? ' me' : ''); d.textContent = m.body;
      const t = document.createElement('time'); t.textContent = m.time; d.append(t); box.append(d); last = Math.max(last, m.id);
    };
    const poll = async () => {
      if (document.hidden) return;
      try {
        const r = await fetch(`/api.php?a=poll&c=${cid}&after=${last}`); if (!r.ok) return;
        const j = await r.json(), end = box.scrollHeight - box.scrollTop - box.clientHeight < 80;
        j.messages.forEach(add); if (j.messages.length && end) down();
      } catch (e) {}
    };
    form.addEventListener('submit', async e => {
      e.preventDefault(); const body = ta.value.trim(); if (!body) return;
      const fd = new FormData(); fd.append('c', cid); fd.append('body', body); fd.append('_csrf', csrf); ta.value = '';
      await fetch('/api.php?a=send', { method: 'POST', body: fd }); await poll(); down();
    });
    ta.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
    down(); setInterval(poll, 3000);
  }
})();
