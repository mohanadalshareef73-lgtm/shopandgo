{{-- نافذة «مشكلة» لطلب: تُفتح بزر عليه data-issue="رابط الحفظ" و data-issue-code="رقم الإيصال" --}}
@once
<div class="modal-bg" id="mIssue"><div class="modal" style="max-width:540px">
    <h3>⚠ مشكلة في الطلب <span class="code" id="isCode"></span></h3>
    <form method="post" id="isF">
        @csrf
        <label class="l">نوع المشكلة</label>
        <div class="opts mb" style="flex-wrap:wrap">
            @foreach (\App\Models\OrderIssue::TYPES as $k => $t)<label><input type="radio" name="type" value="{{ $k }}" @checked($loop->first)><span>{{ $t[1] }} {{ $t[0] }}</span></label>@endforeach
        </div>
        <div class="grid g2 f" id="isQtyBox"><div><label class="l">عدد القطع (اختياري)</label><input name="qty" class="inp numr" inputmode="numeric"></div><div><label class="l">القيمة د.ل (اختياري)</label><input name="value" id="isVal" class="inp numr" inputmode="decimal"></div></div>
        <div id="isActBox" hidden>
            <label class="l">ماذا نفعل بالقيمة؟</label>
            <div class="opts mb" style="flex-wrap:wrap">@foreach (\App\Models\OrderIssue::ACTIONS as $k => $v)<label><input type="radio" name="action" value="{{ $k }}" @checked($k === 'later')><span>{{ $v }}</span></label>@endforeach</div>
            <div class="f" id="isPay" hidden><label class="l">تُصرف من خزينة</label><select name="payment_id" class="inp">@foreach (\App\Models\PaymentMethod::where('status', true)->get() as $pm)<option value="{{ $pm->id }}" @selected($pm->id == \App\Services\AppSettings::cashPaymentId())>{{ $pm->name }}</option>@endforeach</select></div>
        </div>
        <div class="f"><label class="l">التفاصيل</label><textarea name="note" class="inp" rows="2" style="height:auto" placeholder="مثلاً: وصل 3 من 5، أو الطرد لم يصل مع الرحلة…"></textarea></div>
        <label class="ck mb"><input type="checkbox" name="send_whatsapp" value="1" checked> <span id="isWaL">إبلاغ العميل عبر واتساب (ويظهر في حسابه دائماً)</span></label>
        <div style="display:flex;gap:8px"><button class="btn">حفظ</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
<script>
(function () {
  const m = document.getElementById('mIssue'), f = document.getElementById('isF');
  document.addEventListener('click', e => { const b = e.target.closest('[data-issue]'); if (!b) return; e.preventDefault(); e.stopPropagation();
    f.action = b.dataset.issue; document.getElementById('isCode').textContent = b.dataset.issueCode || ''; f.reset();
    // من واجهة الاستلام: المشكلة تمشي للعميل مع رسالة «جاهز للاستلام» (مش رسالة لروحها)
    const wa = f.querySelector('input[name=send_whatsapp]'), wl = document.getElementById('isWaL');
    if (b.dataset.issueNowa) { wa.checked = false; if (wl) wl.textContent = 'ابعتها للعميل توا في رسالة لروحها (من غيرها تمشي معاه في رسالة «جاهز للاستلام»)'; }
    else if (wl) wl.textContent = 'إبلاغ العميل عبر واتساب (ويظهر في حسابه دائماً)';
    upd(); m.classList.add('open'); });
  const upd = () => { const v = parseFloat(document.getElementById('isVal').value) || 0; document.getElementById('isActBox').hidden = !(v > 0);
    const a = f.querySelector('input[name=action]:checked'); document.getElementById('isPay').hidden = !(v > 0 && a && a.value === 'refund'); };
  f.addEventListener('input', upd); f.addEventListener('change', upd);
  // 💾 الحفظ من غير ما الصفحة تتحدث: اللي كنت تكتب فيه في الصفحة يقعد زي ما هو
  let btn = null;
  document.addEventListener('click', e => { const b = e.target.closest('[data-issue]'); if (b) btn = b; }, true);
  const toast = (t, bad) => { const d = document.createElement('div'); d.textContent = t;
    d.style.cssText = 'position:fixed;top:18px;left:50%;transform:translateX(-50%);z-index:999;padding:12px 18px;border-radius:12px;font-weight:700;box-shadow:0 8px 24px rgba(0,0,0,.18);background:' + (bad ? '#fdecea;color:#b3261e' : '#e6f6ee;color:#0f6b4c');
    document.body.appendChild(d); setTimeout(() => d.remove(), 4000); };
  f.addEventListener('submit', e => {
    e.preventDefault();
    const sb = f.querySelector('button:not([type=button])'); sb.disabled = true; sb.textContent = '…';
    const tok = document.querySelector('meta[name=csrf-token]'); const fd = new FormData(f); if (tok) fd.set('_token', tok.content);
    fetch(f.action, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': tok ? tok.content : '' } })
      .then(r => r.text().then(t => { let j = {}; try { j = JSON.parse(t); } catch (x) { j = { msg: '' }; } return { ok: r.ok && j.ok, j, st: r.status }; }))
      .then(({ ok, j, st }) => {
        if (!ok && j.errors) { toast(Object.values(j.errors)[0][0], true); return; }
        if (!ok && j.msg) { toast(j.msg, true); return; }
        // ما نجحش الحفظ السريع (الجلسة/الصلاحية): نحفظوها بالطريقة العادية — بيانات الصفحة محفوظة في المسودة وترجع وحدها
        if (!ok) { toast('نحفظو بالطريقة العادية… (' + st + ')'); try { window.dispatchEvent(new Event('sg-draft-save')); } catch (x) {} f.submit(); return; }
        m.classList.remove('open'); toast(j.msg || '✓ تحفظت');
        if (btn) { btn.textContent = '⚠ فيه مشكلة ✓'; btn.style.color = '#b3261e'; btn.style.borderColor = '#f3b8b2'; }
      })
      .catch(() => toast('ما تحفظتش — تأكد من النت وعاود', true))
      .finally(() => { sb.disabled = false; sb.textContent = 'حفظ'; });
  });
})();
</script>
@endonce
