{{-- إضافة عملاء لرحلة موجودة — نفس شكل صفحة إضافة رحلة --}}
<style>
.pk-lists { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px; }
.pk-lists .panel { display: flex; flex-direction: column; min-height: 420px; padding: 0; overflow: hidden; }
.pk-lists .panel-h { padding: 16px 18px 12px; border-bottom: 1px solid var(--line-soft); }
.pk-lists .panel-h .t { display: flex; justify-content: space-between; align-items: center; }
.pk-lists .panel-h h3 { margin: 0; font-size: 17px; }
.pk-lists .sr { position: relative; margin-top: 12px; }
.pk-lists .sr input { padding-inline-start: 44px; font-size: 18px; height: 54px; border-width: 2px; }
.pk-lists .sr svg { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; color: var(--ink-3); }
.pk-lists .sr kbd { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-family: inherit; font-size: 12px; color: var(--ink-3); background: var(--line-soft); border-radius: 6px; padding: 3px 8px; }
.pk-lists .rows { flex: 1; overflow: auto; padding: 6px 10px 10px; max-height: 520px; }
.pk-lists .row { display: grid; grid-template-columns: 58px 1fr auto auto; align-items: center; gap: 12px; padding: 10px; border-radius: 12px; border: 1px solid transparent; }
.pk-lists .row:hover { background: #f7f8fb; }
.pk-lists .row.hit { border-color: #cfe0f7; background: #f3f8fe; }
.pk-lists .row .cd { font-family: InterNum; font-weight: 700; font-size: 16px; background: var(--line-soft); border-radius: 9px; padding: 6px 0; text-align: center; }
.pk-lists .row .cd mark { background: #ffe58a; color: inherit; border-radius: 3px; }
.pk-lists .row .nm { font-size: 15px; font-weight: 700; }
.pk-lists .row .meta { font-size: 12.5px; color: var(--ink-3); display: flex; gap: 8px; align-items: center; margin-top: 2px; }
.pk-lists .row .amt { text-align: left; font-size: 14px; }
.pk-lists .row .amt small { display: block; font-size: 11.5px; color: var(--ink-3); font-family: Somar; }
.pk-lists .add { width: 38px; height: 38px; border-radius: 10px; border: 0; background: var(--brand); color: #fff; font-size: 20px; cursor: pointer; }
.pk-lists .rmv { width: 34px; height: 34px; border-radius: 10px; border: 1px solid var(--line); background: #fff; color: var(--down); font-size: 18px; cursor: pointer; }
.pk-lists .trip-head { background: var(--brand); color: #fff; border-bottom: 0; }
.pk-lists .trip-head .note { color: #b9bfd8; }
.pk-lists .trip-head .badge { font-family: InterNum; font-weight: 800; font-size: 15px; background: rgba(255,255,255,.12); padding: 4px 10px; border-radius: 8px; }
.pk-lists .totals { display: grid; grid-template-columns: repeat(3, 1fr); border-top: 1px solid var(--line); }
.pk-lists .totals div { padding: 12px 16px; border-left: 1px solid var(--line-soft); } .pk-lists .totals div:last-child { border-left: 0; }
.pk-lists .totals small { display: block; font-size: 12.5px; color: var(--ink-3); } .pk-lists .totals b { font-size: 20px; }
.pk-lists .sub { grid-column: 1/-1; margin: 2px 0 6px; border-top: 1px dashed var(--line, #ddd); }
.pk-lists .sr2 { display: grid; grid-template-columns: 1fr auto 1fr auto 30px; gap: 8px; align-items: center; padding: 4px 6px; font-size: 13px; }
.pk-lists .sr2 .st2 { color: var(--muted, #888); font-size: 12px; }
.pk-lists .sr2 button { width: 28px; height: 28px; border-radius: 8px; border: 1px solid var(--line, #ddd); background: transparent; cursor: pointer; font-weight: 700; }
.pk-lists .sr2 .rmv1 { color: var(--down, #c33); }
@media (max-width: 1100px) { .pk-lists { grid-template-columns: 1fr; } }
</style>
<form method="post" action="{{ route('finance.trips.attach', $trip->id) }}" id="pkF">@csrf
<div class="pk-lists">
    <div class="card panel">
        <div class="panel-h">
            <div class="t"><h3>عملاء ليس في رحلة</h3><span class="note" id="pkFreeCnt"></span></div>
            <div class="sr"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg><input class="inp" id="pkQ" placeholder="كود العميل أو اسمه أو كود الإيصال…" autocomplete="off"><kbd>Enter = إضافة</kbd></div>
        </div>
        <div class="rows" id="pkFree"></div>
    </div>
    <div class="card panel">
        <div class="panel-h trip-head"><div class="t"><h3>نزيدوهم للرحلة <span class="badge">{{ $trip->trip_code }}</span></h3><span class="note" id="pkTripCnt"></span></div></div>
        <div class="rows" id="pkTrip"></div>
        <div class="totals"><div><small>عملاء</small><b class="num" id="pkC">0</b></div><div><small>إيصالات</small><b class="num" id="pkR">0</b></div><div><small>قيمة الإيصالات</small><b><span class="num" id="pkV">0</span> <small style="display:inline">د.ل</small></b></div></div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:12px 16px;border-top:1px solid var(--line-soft)">
            <label class="ck" style="margin:0"><input type="checkbox" name="send_whatsapp" value="1" checked> ابعتلهم «طلبك دخل رحلة»</label>
            <button class="btn green" id="pkSave" disabled style="margin-inline-start:auto">✈ زيدهم للرحلة</button>
        </div>
    </div>
</div>
<div id="pkHidden"></div>
</form>
<script>
(function () {
  const CUST = @json($customers);
  const $ = id => document.getElementById(id);
  const fmt = n => (window.SG && SG.fmt) ? SG.fmt(n) : Math.round(n).toLocaleString('en-US');
  const sel = new Set();
  const custIn = c => c.orders.some(o => sel.has(o.id));
  const custFree = c => c.orders.some(o => !sel.has(o.id));
  const hl = (s, q) => { s = String(s ?? ''); const i = q ? s.indexOf(q) : -1; return i < 0 ? s : s.slice(0, i) + '<mark>' + s.slice(i, i + q.length) + '</mark>' + s.slice(i + q.length); };
  const daysTxt = d => ['من اليوم', 'من أمس', 'من يومين'][d] || ('من ' + d + ' أيام');
  function row(c, mode, q) {
    const os = mode === 'free' ? c.orders.filter(o => !sel.has(o.id)) : c.orders.filter(o => sel.has(o.id));
    const tot = os.reduce((a, o) => a + o.total, 0);
    const pfs = [...new Set(os.map(o => o.pf).filter(Boolean))];
    return `<div class="row ${mode === 'free' && q && (c.code || '').includes(q) ? 'hit' : ''}">
      <div class="cd num">${hl(c.code, q)}</div>
      <div><div class="nm">${c.name || ''}</div><div class="meta">${pfs.map(p => `<span class="pf ${p.toLowerCase()}">${p}</span>`).join('')}<span>${os.length} ${os.length > 1 ? 'إيصالات' : 'إيصال'}</span><span>· ${daysTxt(c.days)}</span></div></div>
      <div class="amt"><b class="num">${fmt(tot)}</b><small>د.ل</small></div>
      ${mode === 'free' ? `<button type="button" class="add" data-id="${c.id}" title="كل إيصالاته">+</button>` : `<button type="button" class="rmv" data-id="${c.id}" title="طلّع كل إيصالاته">×</button>`}
      <div class="sub">${os.map(o => `<div class="sr2"><span class="num">${hl(o.code, q)}</span><span class="num">${o.qty} قطعة</span><span class="st2">${o.st || ''}</span><span class="num">${fmt(o.total)}</span>${mode === 'free' ? `<button type="button" class="add1" data-oid="${o.id}">+</button>` : `<button type="button" class="rmv1" data-oid="${o.id}">×</button>`}</div>`).join('')}</div></div>`;
  }
  function add(id) { const c = CUST.find(x => x.id == id); c.orders.forEach(o => sel.add(o.id)); $('pkQ').value = ''; render(); $('pkQ').focus(); }
  function rm(id) { const c = CUST.find(x => x.id == id); c.orders.forEach(o => sel.delete(o.id)); render(); }
  function render() {
    const q = $('pkQ').value.trim();
    const free = CUST.filter(c => custFree(c) && (!q || (c.code || '').includes(q) || (c.name || '').includes(q) || c.orders.some(o => String(o.code ?? '').includes(q))));
    $('pkFree').innerHTML = free.length ? free.map(c => row(c, 'free', q)).join('') : '<div class="empty"><span class="ic">🔍</span>لا يوجد عميل هنا</div>';
    $('pkFreeCnt').textContent = free.length + ' عميل';
    const inn = CUST.filter(custIn);
    $('pkTrip').innerHTML = inn.length ? inn.map(c => row(c, 'trip')).join('') : '<div class="empty"><span class="ic">✈️</span>اختار العملاء<br>اكتب كود العميل على اليمين واضغط +</div>';
    const ords = CUST.flatMap(c => c.orders).filter(o => sel.has(o.id));
    $('pkC').textContent = inn.length; $('pkR').textContent = ords.length; $('pkV').textContent = fmt(ords.reduce((a, o) => a + o.total, 0));
    $('pkTripCnt').textContent = ords.length + ' إيصال';
    $('pkSave').disabled = !ords.length; $('pkSave').textContent = ords.length ? '✈ زيدهم للرحلة · ' + ords.length + ' إيصال' : '✈ زيدهم للرحلة';
    $('pkHidden').innerHTML = ords.map(o => `<input type="hidden" name="order_ids[]" value="${o.id}">`).join('');
    document.querySelectorAll('#pkF .add').forEach(b => b.onclick = () => add(b.dataset.id));
    document.querySelectorAll('#pkF .rmv').forEach(b => b.onclick = () => rm(b.dataset.id));
    document.querySelectorAll('#pkF .add1').forEach(b => b.onclick = () => { sel.add(+b.dataset.oid); render(); $('pkQ').focus(); });
    document.querySelectorAll('#pkF .rmv1').forEach(b => b.onclick = () => { sel.delete(+b.dataset.oid); render(); });
  }
  $('pkQ').addEventListener('input', render);
  $('pkQ').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); const b = document.querySelector('#pkFree .add'); if (b) add(b.dataset.id); } });
  render();
})();
</script>
