@can('delivery-undo')
<button type="button" id="undoOpener" data-modal="undoModal" hidden></button>
<div class="modal-bg" id="undoModal"><div class="modal" style="max-width:430px">
    <h3>↩ إلغاء تسليم <span class="num" id="undoCode"></span></h3>
    <div class="note mb" id="undoWho"></div>
    <form method="post" id="undoF" data-base="{{ route('sg.delivery.undo', 0) }}">@csrf
        <div class="hint mb">الإيصال يرجع <b>جاهز للاستلام</b> في نفس الرف متاعه، والعملية تتسجل باسمك.</div>
        <label class="ck"><input type="checkbox" name="refund" value="1" id="undoRefund"> رجّع الدفعة اللي انقبضت وقت التسليم (تنحذف وتنقص من الخزينة)</label>
        <div class="note" id="undoPaid" style="margin:4px 0 10px"></div>
        <div style="display:flex;gap:8px"><button class="btn" style="background:var(--down);color:#fff">↩ ألغِ التسليم</button><button type="button" class="btn ghost" data-close>رجوع</button></div>
    </form>
</div></div>
<script>
window.openUndo = function (o) {
  const f = document.getElementById('undoF'); f.action = f.dataset.base.replace(/\/0\/undo$/, '/' + o.id + '/undo');
  document.getElementById('undoCode').textContent = o.code; document.getElementById('undoWho').textContent = (o.name || '') + (o.by ? ' · سلّمه ' + o.by : '');
  document.getElementById('undoRefund').checked = false;
  document.getElementById('undoPaid').textContent = o.paid > 0 ? 'المدفوع على الإيصال توا: ' + o.paid + ' د.ل' : '';
  document.getElementById('undoOpener').click();
};
</script>
@endcan
