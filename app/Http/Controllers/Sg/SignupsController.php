<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Log;
use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** طلبات تسجيل العملاء: موافقة (مع تأكيد الكود) أو رفض */
class SignupsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:customer-create');
    }

    protected function q()
    {
        return Customer::withoutGlobalScope('approved');
    }

    public function index(Request $request)
    {
        $status = $request->status === 'rejected' ? 'rejected' : 'pending';
        $rows = $this->q()->where('signup_status', $status)->latest('signup_at')->paginate(30)->withQueryString();
        // تشابه مع عملاء موجودين (نفس الاسم أو رقم قريب) لمساعدة الإدارة في كشف التكرار
        $similar = [];
        foreach ($rows as $r) {
            $similar[$r->id] = Customer::where(fn ($q) => $q->where('name', $r->name)->orWhere('phone_1', $r->phone_2 ?: '__')->orWhere('phone_2', $r->phone_1))->take(3)->get(['id', 'name', 'code', 'phone_1']);
        }

        return view('sg.signups.index', [
            'nav' => 'signup', 'rows' => $rows, 'status' => $status, 'similar' => $similar,
            'counts' => ['pending' => $this->q()->where('signup_status', 'pending')->count(), 'rejected' => $this->q()->where('signup_status', 'rejected')->count()],
        ]);
    }

    public function approve(Request $request, int $id)
    {
        $c = $this->q()->whereIn('signup_status', ['pending', 'rejected'])->findOrFail($id);
        $data = $request->validate(['code' => 'required|string|max:20|regex:/^[A-Za-z0-9\-]+$/', 'send_whatsapp' => 'nullable|boolean'],
            ['code.required' => 'اكتب كود العميل', 'code.regex' => 'الكود حروف إنجليزية وأرقام فقط']);
        if ($this->q()->where('code', $data['code'])->where('id', '!=', $c->id)->exists()) {
            return back()->with('error', 'الكود ' . $data['code'] . ' مستخدم، اختر كوداً آخر — مقترح: ' . implode('، ', ClientsController::codeSuggestions($data['code'], (string) $c->phone_1, fn ($x) => $this->q()->where('code', $x)->exists(), 3)));
        }
        $c->code = $data['code'];
        $c->signup_status = null;
        $c->approved_by = auth()->id();
        $c->approved_at = now();
        $c->save();
        Cache::forget('sg.badge.signups');
        Log::create(['user_id' => auth()->id(), 'type' => 'customer', 'title' => ' موافقة على تسجيل عميل (' . $c->name . ')', 'log' => ' الكود ' . $c->code . ' · ' . $c->phone_1]);
        $sent = $request->boolean('send_whatsapp') && MessageService::sendTemplate('account_approved', $c, MessageService::customerVars($c));

        return back()->with('success', 'تمت الموافقة على حساب ' . $c->name . ' بالكود ' . $c->code . ($request->boolean('send_whatsapp') ? ($sent ? ' · أُرسل إشعار واتساب ✓' : ' · تعذّر إرسال واتساب') : ''));
    }

    public function reject(Request $request, int $id)
    {
        $c = $this->q()->where('signup_status', 'pending')->findOrFail($id);
        $data = $request->validate(['reason' => 'nullable|string|max:300', 'send_whatsapp' => 'nullable|boolean']);
        $c->signup_status = 'rejected';
        $c->signup_note = $data['reason'] ?? null;
        $c->save();
        Cache::forget('sg.badge.signups');
        Log::create(['user_id' => auth()->id(), 'type' => 'customer', 'title' => ' رفض طلب تسجيل (' . $c->name . ')', 'log' => ' ' . $c->phone_1 . ($c->signup_note ? ' · ' . $c->signup_note : '')]);
        if ($request->boolean('send_whatsapp')) {
            // نرسل مباشرة لأن العميل المرفوض لا يمر عبر النطاق العام
            $tpl = MessageService::template('account_rejected');
            if ($tpl) {
                MessageService::sendText('account_rejected', MessageService::render($tpl->message, ['{الاسم}' => $c->name, '{السبب}' => $c->signup_note ?? '']), [$c->phone_1]);
            }
        }

        return back()->with('success', 'تم رفض طلب ' . $c->name);
    }

    /** حذف طلب مرفوض نهائياً (يسمح لصاحب الرقم بالتسجيل من جديد) */
    public function destroy(int $id)
    {
        $c = $this->q()->where('signup_status', 'rejected')->findOrFail($id);
        $c->delete();

        return back()->with('success', 'تم حذف الطلب');
    }
}
