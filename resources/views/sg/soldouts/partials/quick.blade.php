{{-- نافذة سريعة: نبحث على إيصال ونسجّل النواقص (المنتجات اللي نفدت) --}}
@can('soldout-manage')
<button type="button" data-modal="mQSold" id="qsOpener" style="display:none"></button>
<div class="modal-bg" id="mQSold"><div class="modal" style="max-width:620px">
    <h3>🚫 نواقص — منتجات نفدت</h3>
    <div id="qsPick">
        <input class="inp" id="qsQ" placeholder="🔍 رقم الإيصال أو كود العميل أو اسمه أو رقمه…" autocomplete="off" style="height:50px;font-size:16px">
        <div id="qsRes" style="margin-top:8px;max-height:300px;overflow:auto"></div>
    </div>
    <form method="post" id="qsF" data-base="{{ route('sg.soldouts.store', 0) }}" style="display:none">@csrf
        <div style="display:flex;align-items:center;gap:8px;background:var(--line-soft);border-radius:12px;padding:10px 12px;margin-bottom:10px">
            <div style="flex:1"><b class="num" id="qsCode"></b> · <span id="qsName"></span> <span class="note num" id="qsCc"></span></div>
            <button type="button" class="btn ghost sm" id="qsBack">تغيير</button>
        </div>
        <div class="note" style="margin-bottom:6px">علّم على القطع اللي نفدت، وعدّل العدد لو نفد جزء منها بس:</div>
        <div style="display:grid;grid-template-columns:28px 1fr 70px 100px 0 30px;gap:6px;font-size:12.5px;color:var(--ink-3);padding:0 2px"><span></span><span>المنتج</span><span>العدد</span><span>سعر القطعة</span><span></span><span></span></div>
        <div id="qsRows"></div>
        <datalist id="qsItems"></datalist>
        <button type="button" class="btn ghost sm" id="qsAdd" style="margin-top:4px">+ منتج مش في القائمة</button>
        <div class="hint mt">المجموع: <b class="num" id="qsSum">0</b> د.ل</div>
        <label class="ck mt"><input type="checkbox" name="send_whatsapp" value="1" checked> بلّغ العميل توا على الواتساب (يرد: إلغاء أو استبدال)</label>
        <div style="display:flex;gap:8px;margin-top:10px"><button class="btn">💾 حفظ</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
<script>
(function () {
  const $ = id => document.getElementById(id), find = @json(route('sg.soldouts.find'));
  let items = [], t;
  let idx = 0;
  function row(it) {
    const i = idx++, d = document.createElement('div');
    d.style.cssText = 'display:grid;grid-template-columns:28px 1fr 70px 100px 0 30px;gap:6px;margin-bottom:6px;align-items:center';
    const esc = v => String(v ?? '').replace(/"/g, '&quot;');
    d.innerHTML = `<input type="checkbox" class="qsC" ${it ? '' : 'checked'} style="width:20px;height:20px"><input class="inp" name="rows[${i}][item]" list="qsItems" placeholder="اسم المنتج" value="${esc(it && it.name)}" ${it ? 'readonly' : ''}><input class="inp numr" name="rows[${i}][qty]" value="1" ${it && it.max ? 'max="' + it.max + '" title="من ' + it.max + '"' : ''} type="number" min="1"><input class="inp numr" placeholder="0" value="${it ? it.price : ''}" inputmode="decimal"><input type="hidden" name="rows[${i}][price]"><button type="button" class="btn ghost sm" style="color:var(--down);padding:0;${it ? 'visibility:hidden' : ''}">✕</button>`;
    const [ck, nm, q, p, hp, x] = d.children;
    const sync = () => { [nm, q, p, hp].forEach(e => { e.disabled = !ck.checked; }); d.style.opacity = ck.checked ? 1 : .5; sum(); };
    ck.addEventListener('change', sync);
    nm.addEventListener('change', () => { const f = items.find(z => z.name === nm.value); if (f && !p.value) p.value = f.price; sum(); });
    [q, p].forEach(e => e.addEventListener('input', sum));
    x.onclick = () => { d.remove(); sum(); };
    $('qsRows').appendChild(d); sync(); if (!it) nm.focus();
  }
  function fillItems() { $('qsRows').innerHTML = ''; idx = 0; if (items.length) items.forEach(z => row(z)); else row(); sum(); }
  // السعر اللي يتحفظ = العدد × سعر القطعة
  function sum() { let s = 0; [...$('qsRows').children].forEach(r => { const q = parseFloat(r.children[2].value) || 0, p = parseFloat(r.children[3].value) || 0, v = Math.round(q * p * 100) / 100; r.children[4].value = v || ''; if (r.children[0].checked) s += v; }); $('qsSum').textContent = s.toLocaleString('en-US'); }
  window.openSold = function (o) {
    items = o.items || [];
    $('qsF').action = $('qsF').dataset.base.replace(/\/0\//, '/' + o.id + '/');
    $('qsCode').textContent = o.code; $('qsName').textContent = o.name || ''; $('qsCc').textContent = o.ccode ? '· ' + o.ccode : '';
    $('qsItems').innerHTML = items.map(z => `<option value="${String(z.name).replace(/"/g, '&quot;')}">`).join('');
    fillItems();
    if (!items.length) fetch(find + '?q=' + encodeURIComponent(o.code), { headers: { Accept: 'application/json' } }).then(r => r.json()).then(rs => { const m = rs.find(z => z.id == o.id); if (m && m.items.length) { items = m.items; $('qsItems').innerHTML = items.map(z => `<option value="${String(z.name).replace(/"/g, '&quot;')}">`).join(''); fillItems(); } }).catch(() => {});
    $('qsPick').style.display = 'none'; $('qsF').style.display = '';
    $('qsOpener').click();
  };
  window.openSoldSearch = function () { $('qsPick').style.display = ''; $('qsF').style.display = 'none'; $('qsQ').value = ''; $('qsRes').innerHTML = ''; $('qsOpener').click(); setTimeout(() => $('qsQ').focus(), 50); };
  $('qsBack').onclick = window.openSoldSearch;
  $('qsAdd').onclick = () => row();
  $('qsF').addEventListener('submit', e => { if (!document.querySelectorAll('#qsRows .qsC:checked').length) { e.preventDefault(); alert('علّم على قطعة وحدة على الأقل'); } });
  $('qsQ').addEventListener('input', () => { clearTimeout(t); const q = $('qsQ').value.trim(); if (q.length < 2) { $('qsRes').innerHTML = ''; return; }
    t = setTimeout(() => fetch(find + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } }).then(r => r.json()).then(rs => {
      $('qsRes').innerHTML = rs.length ? '' : '<div class="note" style="padding:10px">ما لقيناش إيصال مفتوح بهذا</div>';
      const byC = {}; rs.forEach(o => { (byC[o.ccode || o.name] = byC[o.ccode || o.name] || []).push(o); });
      Object.values(byC).forEach(list => {
        const h = document.createElement('div'); h.style.cssText = 'font-weight:700;margin:8px 2px 4px'; h.innerHTML = `👤 ${list[0].name || ''} <span class="note num">${list[0].ccode || ''}</span> <span class="note">· ${list.length} إيصال مفتوح</span>`; $('qsRes').appendChild(h);
        list.forEach(o => { const b = document.createElement('button'); b.type = 'button'; b.className = 'btn ghost'; b.style.cssText = 'display:flex;width:100%;justify-content:space-between;margin-bottom:6px;height:auto;padding:10px 12px;text-align:right';
          b.innerHTML = `<span>🧾 <b class="num">${o.code}</b> <span class="note">· ${o.items.length} قطعة</span></span><span class="note num">${o.total} د.ل · ${o.when || ''}</span>`;
          b.onclick = () => window.openSold(o); $('qsRes').appendChild(b); });
      });
    }), 300); });
})();
</script>
@endcan
