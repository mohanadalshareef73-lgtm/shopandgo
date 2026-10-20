{{-- 📉 نواقص الإيصال: نشوفها، نضيف، ونلغي — الزر: data-miss="{{ $order->id }}" data-miss-code="..." --}}
@once
<div class="modal-bg" id="mMiss"><div class="modal" style="max-width:560px">
    <h3>📉 نواقص الإيصال <span class="num" id="msCode"></span></h3>
    <div id="msList" class="mb"><div class="note">جاري التحميل…</div></div>
    @can('order-edit')
    <form id="msAdd" style="border-top:1px solid var(--line);padding-top:10px">
        <label class="l">➕ إضافة نقص</label>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
            <input name="item" class="inp" style="flex:2 1 200px" placeholder="شن الناقص؟ (اسم المنتج / وصف)" required>
            <input name="qty" class="inp numr" style="flex:1 1 80px" placeholder="كم قطعة" value="1" required inputmode="numeric">
            <input name="value" class="inp numr" style="flex:1 1 100px" placeholder="السعر د.ل" inputmode="decimal">
            <button class="btn">➕ إضافة</button>
        </div>
        <div class="note" style="margin-top:4px">تطلع وقت التسليم في تنبيه أحمر، وتقدر تخصمها من الإيصال</div>
    </form>
    @endcan
    <div style="display:flex;justify-content:flex-end;margin-top:10px"><button type="button" class="btn ghost" data-close>سكّر</button></div>
</div></div>
<button type="button" id="msOpener" data-modal="mMiss" hidden></button>
<script>
(function () {
  const U = { list: @json(route('sg.missing.list', 0)), add: @json(route('sg.missing.add', 0)), rm: @json(route('sg.missing.remove', 0)) }, u = (k, id) => U[k].replace('/orders/0/', '/orders/' + id + '/'), tok = () => (document.querySelector('meta[name=csrf-token]') || {}).content;
  let cur = null, btn = null;
  const esc = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const fmt = n => (Math.round((+n || 0) * 100) / 100).toLocaleString('en-US');
  const draw = (rows, canEdit) => {
    const L = document.getElementById('msList');
    if (!rows.length) { L.innerHTML = '<div class="note">ما فيش نواقص مسجلة على الإيصال هذا ✓</div>'; return; }
    L.innerHTML = rows.map(r => `<div style="display:flex;gap:8px;align-items:center;padding:8px 0;border-bottom:1px dashed var(--line)">
      <div style="flex:1"><b>${esc(r.title)}</b><div class="note">${r.qty} قطعة · <span class="num">${fmt(r.value)}</span> د.ل · ${esc(r.state)}</div></div>
      ${canEdit ? `<button type="button" class="btn ghost sm" style="color:var(--down)" data-rm="${r.kind}:${r.id}">❌ إلغاء</button>` : ''}</div>`).join('');
  };
  const setBtn = n => { if (!btn) return; btn.textContent = n ? '📉 نواقص (' + n + ')' : '📉 النواقص'; btn.style.color = n ? '#b3261e' : ''; btn.style.borderColor = n ? '#f3b8b2' : ''; };
  const toast = (t, bad) => { const d = document.createElement('div'); d.textContent = t; d.style.cssText = 'position:fixed;top:18px;left:50%;transform:translateX(-50%);z-index:999;padding:12px 18px;border-radius:12px;font-weight:700;box-shadow:0 8px 24px rgba(0,0,0,.18);background:' + (bad ? '#fdecea;color:#b3261e' : '#e6f6ee;color:#0f6b4c'); document.body.appendChild(d); setTimeout(() => d.remove(), 3500); };
  const post = (url, body) => fetch(url, { method: 'POST', body, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': tok(), 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.text().then(t => { let j = {}; try { j = JSON.parse(t); } catch (e) {} return { ok: r.ok && j.ok, j, st: r.status }; }));
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-miss]');
    if (b) { e.preventDefault(); btn = b; cur = b.dataset.miss; document.getElementById('msCode').textContent = b.dataset.missCode || '';
      document.getElementById('msList').innerHTML = '<div class="note">جاري التحميل…</div>'; document.getElementById('msOpener').click();
      fetch(u('list', cur), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(r => r.json()).then(j => draw(j.rows || [], j.canEdit)).catch(() => { document.getElementById('msList').innerHTML = '<div class="note">ما قدرناش نجيبوها — عاود</div>'; });
      return; }
    const rm = e.target.closest('[data-rm]');
    if (rm && cur) { if (!confirm('نلغيو النقص هذا؟ (لو كان مخصوم، الخصم يرجع)')) return;
      const [kind, id] = rm.dataset.rm.split(':'); const fd = new FormData(); fd.set('kind', kind); fd.set('id', id); fd.set('_token', tok());
      post(u('rm', cur), fd).then(({ ok, j, st }) => { if (!ok) { toast(j.msg || ('ما تلغاش (' + st + ')'), true); return; } draw(j.rows, true); setBtn(j.open); toast('✓ اتلغى'); }); }
  });
  const af = document.getElementById('msAdd');
  if (af) af.addEventListener('submit', e => { e.preventDefault(); if (!cur) return; const fd = new FormData(af); fd.set('_token', tok());
    post(u('add', cur), fd).then(({ ok, j, st }) => { if (!ok) { toast(j.msg || (j.errors ? Object.values(j.errors)[0][0] : 'ما تضافش (' + st + ')'), true); return; } af.reset(); draw(j.rows, true); setBtn(j.open); toast('✓ تضاف النقص'); }); });
})();
</script>
@endonce
