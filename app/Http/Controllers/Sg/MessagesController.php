<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\WhatsappOutbox;
use App\Models\whatsappMessage;
use App\Services\MessageService;
use Illuminate\Http\Request;

/** قوالب رسائل الواتساب: تشغيل/إيقاف، تعديل النص، تجربة، وسجل الإرسال */
class MessagesController extends Controller
{
    public const TITLES = [
        'new' => ['إيصال جديد', 'لما تسجيل إيصال'], 'trip_added' => ['دخل رحلة', 'لما الإيصال يدخل رحلة شراء'], 'trip_status' => ['تغيرت حالة الرحلة', ''],
        'arrived' => ['وصلت بضاعتك', 'وقت استلام البضاعة مع الباقي والشحن'], 'shelf_reminder' => ['تذكير الرف', 'للي نسيوا بضاعتهم'], 'delivered' => ['تم الاستلام', 'بعد التسليم'],
        'debt_reminder' => ['تذكير دين', ''], 'payment_received' => ['استلمنا دفعتك', ''], 'courier_assigned' => ['الطلب مع المندوب', ''], 'courier_delivered' => ['وصلك الطلب (مندوب)', ''],
        'delivery_request' => ['استلمنا طلب التوصيل', ''], 'customer_note' => ['ملاحظة للعميل', ''], 'points_earned' => ['نقاط جديدة', ''],
        'shift_closed' => ['تقفيل وردية (للمدير)', ''], 'monthly_report' => ['التقرير الشهري (للشركاء)', ''],
        'return_done' => ['تسجيل مرتجع', 'لما تسجيل مرتجع أو استبدال'],
    ];

    public function __construct()
    {
        $this->middleware('permission:whatsapp-templates');
    }

    public function index(Request $request)
    {
        $tpls = whatsappMessage::orderBy('id')->get();
        $sel = $tpls->firstWhere('id', (int) $request->id) ?? $tpls->first();
        $since = now()->subDays(30);
        $stats = WhatsappOutbox::where('created_at', '>=', $since)->selectRaw('type, status, COUNT(*) n')->groupBy('type', 'status')->get();

        return view('sg.settings.messages', [
            'nav' => 'msg', 'tpls' => $tpls, 'sel' => $sel, 'stats' => $stats,
            'sent' => (int) $stats->where('status', 'sent')->sum('n'), 'failed' => (int) $stats->where('status', 'failed')->sum('n'),
            'recent' => WhatsappOutbox::with('customer')->latest()->take(15)->get(),
            'vars' => MessageService::VARIABLES, 'titles' => self::TITLES,
            'sample' => Order::with('customer')->whereNotNull('customer_id')->latest()->first(),
        ]);
    }

    public function update(Request $request, whatsappMessage $tpl)
    {
        $data = $request->validate(['title' => 'nullable|string|max:120', 'message' => 'required|string|max:4000']);
        $tpl->title = $data['title'] ?? $tpl->title;
        $tpl->message = $data['message'];
        $tpl->enabled = $request->boolean('enabled');
        $tpl->save();

        return back()->with('success', 'تم حفظ الرسالة');
    }

    public function toggle(whatsappMessage $tpl)
    {
        $tpl->enabled = !($tpl->enabled ?? true);
        $tpl->save();

        return back()->with('success', $tpl->enabled ? 'الرسالة شغالة' : 'الرسالة موقوفة');
    }

    public function test(Request $request, whatsappMessage $tpl)
    {
        $data = $request->validate(['phone' => 'required|string|max:20']);
        $o = Order::with('customer')->whereNotNull('customer_id')->latest()->first();
        $vars = $o ? MessageService::orderVars($o) : MessageService::customerVars(new Customer(['name' => 'عميل تجربة', 'code' => '0000']));
        $ok = MessageService::sendText('test', "🧪 تجربة\n" . MessageService::render($tpl->message, $vars), [$data['phone']]);

        return back()->with($ok ? 'success' : 'warning', $ok ? 'أُرسلت رسالة التجربة ✓' : 'لم تُرسل — تأكد من خدمة الواتساب في الإعدادات القديمة');
    }
}
