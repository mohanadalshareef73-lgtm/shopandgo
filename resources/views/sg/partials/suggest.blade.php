{{-- 🔎 اقتراحات وانت تكتب: أي خانة فيها data-suggest="order|trip|all" تطلع تحتها أقرب الإيصالات/الرحلات.
     الضغط على اقتراح يفتحه، و Enter من غير ما تختار = البحث العادي. --}}
<style>
.sg-sug { position: absolute; z-index: 60; background: #fff; border: 1px solid var(--line); border-radius: 12px; box-shadow: 0 12px 30px rgba(22,26,46,.14); padding: 6px; min-width: 300px; max-height: 380px; overflow-y: auto; }
.sg-sug a { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 9px; color: var(--ink); }
.sg-sug a.on, .sg-sug a:hover { background: #f1f3f8; }
.sg-sug a b { font-family: InterNum, Somar, sans-serif; font-weight: 800; font-size: 15.5px; direction: ltr; }
.sg-sug a small { color: var(--ink-3); font-size: 12.5px; margin-inline-start: auto; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px; }
.sg-sug a i { font-style: normal; font-size: 11px; font-weight: 700; padding: 1px 7px; border-radius: 6px; background: #eef0f4; color: var(--ink-2); }
.sg-sug a i.t { background: #e8f0fb; color: #2a78d6; }
.sg-sug .none { padding: 10px 12px; color: var(--ink-3); font-size: 13.5px; }
</style>
<script>
(function () {
  const url = @json(route('sg.orders.suggest'));
  document.querySelectorAll('input[data-suggest]').forEach(inp => {
    const box = document.createElement('div'); box.className = 'sg-sug'; box.hidden = true; document.body.appendChild(box);
    let t, ctl, idx = -1, items = [];
    const place = () => { const r = inp.getBoundingClientRect(); box.style.top = (r.bottom + window.scrollY + 6) + 'px'; box.style.right = (document.documentElement.clientWidth - r.right - window.scrollX) + 'px'; box.style.minWidth = Math.max(300, r.width) + 'px'; };
    const hide = () => { box.hidden = true; idx = -1; };
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const draw = () => {
      box.innerHTML = items.length ? items.map((x, i) => `<a href="${esc(x.url)}" class="${i === idx ? 'on' : ''}"><i class="${x.kind === 'trip' ? 't' : ''}">${x.kind === 'trip' ? 'رحلة' : 'إيصال'}</i><b>${esc(x.code)}</b><small>${esc(x.sub)}</small></a>`).join('') : '<div class="none">ما فيش نتائج قريبة</div>';
      place(); box.hidden = false;
    };
    inp.addEventListener('input', () => {
      clearTimeout(t); const v = inp.value.trim();
      if (!v) { hide(); return; }
      t = setTimeout(async () => {
        try {
          if (ctl) ctl.abort(); ctl = new AbortController();
          const r = await (await fetch(url + '?type=' + encodeURIComponent(inp.dataset.suggest) + '&t=' + encodeURIComponent(v), { signal: ctl.signal, headers: { Accept: 'application/json' } })).json();
          items = r.items || []; idx = -1; if (inp.value.trim() === v) draw();
        } catch (e) {}
      }, 200);
    });
    inp.addEventListener('keydown', e => {
      if (box.hidden || !items.length) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); idx = (idx + 1) % items.length; draw(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); idx = (idx - 1 + items.length) % items.length; draw(); }
      else if (e.key === 'Enter' && idx >= 0) { e.preventDefault(); location.href = items[idx].url; }
      else if (e.key === 'Escape') { hide(); }
    });
    document.addEventListener('click', e => { if (e.target !== inp && !box.contains(e.target)) hide(); });
    window.addEventListener('resize', () => { if (!box.hidden) place(); });
    inp.form && inp.form.addEventListener('submit', hide);
  });
})();
</script>
