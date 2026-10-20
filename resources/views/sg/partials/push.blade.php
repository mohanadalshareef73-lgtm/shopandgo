{{-- زر تفعيل إشعارات الجوال (موظف / مندوب) — مجاني --}}
@if (\Illuminate\Support\Facades\Schema::hasColumn('push_subscriptions', 'user_id'))
<button type="button" class="btn ghost sm" id="pushStaffBtn" style="display:none">📲 فعّل إشعارات الجوال</button>
<script>
(function () {
  const b = document.getElementById('pushStaffBtn'); if (!b || !('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window) || Notification.permission === 'denied') return;
  const key = @json(\App\Services\PushService::publicKey());
  const u8 = s => { const p = '='.repeat((4 - s.length % 4) % 4), r = atob((s + p).replace(/-/g, '+').replace(/_/g, '/')); return Uint8Array.from([...r].map(c => c.charCodeAt(0))); };
  async function sub() {
    const reg = await navigator.serviceWorker.register(@json(route('portal.sw', [], false)), { scope: '/' }); await navigator.serviceWorker.ready;
    let s = await reg.pushManager.getSubscription(); if (!s) s = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: u8(key) });
    await fetch(@json($subUrl), { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) }, body: JSON.stringify(s) });
  }
  if (Notification.permission === 'granted') { sub().catch(() => {}); return; }
  b.style.display = '';
  b.onclick = async () => { b.disabled = true; try { if (await Notification.requestPermission() === 'granted') { await sub(); b.textContent = '✓ تفعلت'; } } catch (e) { b.textContent = 'ما تفعلتش على الجهاز هذا'; } };
})();
</script>
@endif
