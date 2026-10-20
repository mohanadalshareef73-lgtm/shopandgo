{{-- ✅ تسليم من واجهة الطلبات: نفس واجهة التسليم بالضبط في نافذة فوق الصفحة (تقعد في مكانك) --}}
@can('delivery-create')
<div id="qdBg" style="display:none;position:fixed;inset:0;z-index:300;background:rgba(15,20,40,.55);padding:18px">
    <div style="background:#f2f3f7;border-radius:20px;height:100%;max-width:1400px;margin:0 auto;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3)">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 16px;background:#fff;border-bottom:1px solid var(--line)">
            <b id="qdTitle" style="font-size:16px">تسليم</b>
            <button type="button" class="btn ghost sm" onclick="closeDeliver()">✕ سكّر</button>
        </div>
        <iframe id="qdFrame" style="flex:1;border:0;width:100%;background:#f2f3f7"></iframe>
    </div>
</div>
<script>
window.openDeliver = function (o, courier) {
  const u = new URL(@json(route('sg.delivery')), location.href);
  u.searchParams.set('q', o.ccode || o.code); u.searchParams.set('all', '1'); u.searchParams.set('pick', o.id); u.searchParams.set('embed', '1');
  if (courier) u.searchParams.set('courier', '1');
  document.getElementById('qdTitle').textContent = (courier ? '🛵 مندوب · ' : '✅ تسليم · ') + o.code + (o.name ? ' — ' + o.name : '');
  document.getElementById('qdFrame').src = u.toString();
  document.getElementById('qdBg').style.display = 'block'; document.body.style.overflow = 'hidden';
};
window.closeDeliver = function () { document.getElementById('qdBg').style.display = 'none'; document.getElementById('qdFrame').src = 'about:blank'; document.body.style.overflow = ''; };
document.addEventListener('keydown', e => { if (e.key === 'Escape' && document.getElementById('qdBg').style.display === 'block') closeDeliver(); });
</script>
@endcan
@if (session('print_url'))<script>window.open(@json(session('print_url')), '_blank');</script>@endif
