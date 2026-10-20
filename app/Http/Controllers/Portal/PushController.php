<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CustomerMessage;
use App\Services\AppSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** إشعارات الجوال: تسجيل الجهاز، وآخر رسالة يعرضها الإشعار */
class PushController extends Controller
{
    public function subscribe(Request $request)
    {
        $data = $request->validate(['endpoint' => 'required|url|max:2000', 'keys.p256dh' => 'nullable|string|max:200', 'keys.auth' => 'nullable|string|max:100']);
        $c = auth('customer')->user();
        DB::table('push_subscriptions')->updateOrInsert(['endpoint_hash' => hash('sha256', $data['endpoint'])], [
            'customer_id' => $c->id, 'user_id' => null, 'endpoint' => $data['endpoint'], 'p256dh' => data_get($data, 'keys.p256dh'), 'auth' => data_get($data, 'keys.auth'),
            'agent' => mb_substr((string) $request->userAgent(), 0, 200), 'updated_at' => now(), 'created_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /** تسجيل جهاز موظف أو مندوب */
    public function subscribeUser(Request $request)
    {
        $data = $request->validate(['endpoint' => 'required|url|max:2000', 'keys.p256dh' => 'nullable|string|max:200', 'keys.auth' => 'nullable|string|max:100']);
        DB::table('push_subscriptions')->updateOrInsert(['endpoint_hash' => hash('sha256', $data['endpoint'])], [
            'user_id' => auth('web')->id(), 'customer_id' => null, 'endpoint' => $data['endpoint'], 'p256dh' => data_get($data, 'keys.p256dh'), 'auth' => data_get($data, 'keys.auth'),
            'agent' => mb_substr((string) $request->userAgent(), 0, 200), 'updated_at' => now(), 'created_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /** آخر شي يعرضه الإشعار: للموظف آخر تنبيه، للمندوب آخر توصيل، وللعميل آخر رسالة */
    public function latestAny()
    {
        $u = auth('web')->user();
        if ($u && (int) $u->type_id === 4) {
            $d = \App\Models\Delivery::with('customer')->where('courier_id', $u->id)->whereIn('status', ['assigned', 'out'])->latest('assigned_at')->first();

            return response()->json(['title' => '🛵 توصيل جديد', 'body' => $d ? $d->code . ' · ' . optional($d->customer)->name . ' · ' . $d->address : 'عندك توصيل جديد',
                'url' => route('courier.home', $d ? ['d' => $d->id] : [])])->header('Cache-Control', 'no-store');
        }
        if ($u) {
            $a = \App\Models\SgAlert::latest()->first();

            return response()->json(['title' => $a->title ?? 'تنبيه جديد', 'body' => mb_substr((string) ($a->body ?? ''), 0, 180), 'url' => $a->url ?? route('sg.alerts.center')])->header('Cache-Control', 'no-store');
        }

        return $this->latest();
    }

    public function latest()
    {
        $c = auth('customer')->user();
        $m = $c ? CustomerMessage::where('customer_id', $c->id)->latest()->first() : null;

        return response()->json([
            'title' => AppSettings::get('shop_name', 'Shop and Go'),
            'body' => $m ? mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $m->body)), 0, 180) : 'عندك إشعار جديد',
            'url' => route('customer.dashboard', ['tab' => 'account']),
        ])->header('Cache-Control', 'no-store');
    }

    /** ملف الـ Service Worker من جذر الموقع */
    public function sw()
    {
        $latest = route('push.latest', [], false);
        $icon = asset('assets/sg/logo.png');
        $home = '/';
        $js = <<<JS
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));
self.addEventListener('push', e => {
  e.waitUntil(fetch('{$latest}', { credentials: 'include', cache: 'no-store' }).then(r => r.json()).catch(() => ({}))
    .then(d => self.registration.showNotification(d.title || 'Shop and Go', { body: d.body || 'عندك إشعار جديد', icon: '{$icon}', badge: '{$icon}', dir: 'rtl', lang: 'ar', data: { url: d.url || '{$home}' } })));
});
self.addEventListener('notificationclick', e => {
  e.notification.close();
  e.waitUntil(clients.matchAll({ type: 'window' }).then(ws => { for (const w of ws) { if ('focus' in w) { w.navigate(e.notification.data.url); return w.focus(); } } return clients.openWindow(e.notification.data.url); }));
});
JS;

        return response($js, 200, ['Content-Type' => 'application/javascript; charset=utf-8', 'Service-Worker-Allowed' => '/', 'Cache-Control' => 'no-cache']);
    }
}
