{{-- كود العميل: إذا مستخدم يقترح بدائل. الحقل يجب أن يكون عليه data-code-check (و data-phone="selector" و data-except="id" اختياري) --}}
@once
@push('style')
<style>
.cc-box { margin-top: 6px; font-size: 12.5px; }
.cc-box .bad { color: var(--down); font-weight: 700; }
.cc-box .ok { color: var(--up); font-weight: 700; }
.cc-box .sug { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.cc-box .sug button { font-family: InterNum, Somar; font-size: 13px; font-weight: 700; border: 1.5px solid var(--line); background: #fff; color: var(--ink); padding: 4px 11px; border-radius: 99px; cursor: pointer; }
.cc-box .sug button:hover { border-color: var(--brand); background: #f6f7fb; }
input[data-code-check].taken { border-color: var(--down) !important; box-shadow: 0 0 0 3px rgba(196,53,47,.1); }
</style>
@endpush
@push('script')
<script>
document.querySelectorAll('input[data-code-check]').forEach(function (inp) {
  const url = @json(route('sg.clients.code'));
  const box = document.createElement('div'); box.className = 'cc-box'; inp.insertAdjacentElement('afterend', box);
  const phone = () => { const s = inp.dataset.phone && document.querySelector(inp.dataset.phone); return s ? s.value : ''; };
  let t = null, last = '';
  const esc = s => String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const check = () => {
    const code = inp.value.trim(), key = code + '|' + phone();
    if (key === last) return; last = key;
    if (!code) { inp.classList.remove('taken'); box.innerHTML = phone().replace(/\D/g, '').length >= 4 ? '<span class="note">إذا خليته فارغ يأخذ آخر أرقام الهاتف</span>' : ''; return; }
    fetch(url + '?' + new URLSearchParams({ code, phone: phone(), except: inp.dataset.except || '' }), { headers: { Accept: 'application/json' } }).then(r => r.json()).then(d => {
      if (inp.value.trim() !== code) return;
      inp.classList.toggle('taken', d.exists);
      if (!d.exists) { box.innerHTML = '<span class="ok">✓ الكود فارغ</span>'; return; }
      box.innerHTML = '<span class="bad">✕ الكود ' + esc(code) + ' مستخدم' + (d.owner ? ' لـ ' + esc(d.owner.name) : '') + '</span>' +
        (d.suggestions.length ? '<div class="note" style="margin-top:4px">اختر كود فارغ:</div><div class="sug">' + d.suggestions.map(s => '<button type="button" data-v="' + esc(s) + '">' + esc(s) + '</button>').join('') + '</div>' : '');
      box.querySelectorAll('button[data-v]').forEach(b => b.onclick = () => { inp.value = b.dataset.v; check(); inp.focus(); });
    }).catch(() => {});
  };
  inp.addEventListener('input', () => { clearTimeout(t); t = setTimeout(check, 300); });
  const ph = inp.dataset.phone && document.querySelector(inp.dataset.phone); if (ph) ph.addEventListener('change', () => { last = ''; check(); });
  inp.form && inp.form.addEventListener('submit', e => { if (inp.classList.contains('taken')) { e.preventDefault(); inp.focus(); box.scrollIntoView({ block: 'center' }); } });
  if (inp.value) check();
});
</script>
@endpush
@endonce
