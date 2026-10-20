{{-- 👁 معاينة رسالة «جاهز للاستلام» قبل الإرسال — تتعدل وبعدين 📤 ابعت --}}
@once
<div class="modal-bg" id="mShipReady"><div class="modal" style="max-width:520px">
    <h3>✅ جاهز للاستلام <span class="num" id="srCode"></span></h3>
    <div class="note mb" id="srWho"></div>
    <form method="post" id="srF">@csrf
        <label class="l">الرسالة اللي بتوصل العميل (تقدر تعدل فيها) · ومعاها ملف PDF</label>
        <textarea name="text" id="srText" class="inp" rows="14" style="height:auto;line-height:1.7;font-size:14px">جاري التحميل…</textarea>
        <label class="ck" style="margin-top:8px"><input type="checkbox" name="no_msg" value="1" id="srNo"> خليها جاهزة للاستلام بس، من غير رسالة</label>
        <div style="display:flex;gap:8px;margin-top:10px"><button class="btn green" style="flex:1" id="srGo">📤 ابعت</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
<button type="button" id="srOpener" data-modal="mShipReady" hidden></button>
<script>
(function () {
  const f = document.getElementById('srF'), tx = document.getElementById('srText');
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-ship-ready]'); if (!b) return; e.preventDefault();
    f.action = b.dataset.shipReady; document.getElementById('srCode').textContent = b.dataset.code || '';
    tx.value = 'جاري التحميل…'; document.getElementById('srWho').textContent = ''; document.getElementById('srNo').checked = false;
    document.getElementById('srOpener').click();
    fetch(b.dataset.preview, { headers: { 'Accept': 'application/json' } }).then(r => r.json()).then(j => {
      tx.value = j.text || ''; document.getElementById('srWho').textContent = (j.name || '') + (j.phone ? ' · ' + j.phone : '') + (j.sent ? ' · الرسالة انبعتت قبل' : '');
    }).catch(() => { tx.value = ''; document.getElementById('srWho').textContent = 'ما قدرناش نجيبو الرسالة — اكتبها بيدك أو عاود'; });
  });
  document.getElementById('srNo').addEventListener('change', e => { document.getElementById('srGo').textContent = e.target.checked ? '✅ جاهزة بس' : '📤 ابعت'; });
})();
</script>
@endonce
