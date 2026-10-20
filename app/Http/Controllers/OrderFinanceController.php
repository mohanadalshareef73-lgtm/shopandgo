<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Log;
use App\Models\Notif_orgs;
use App\Models\Order;
use App\Models\whatsappMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/**
 * استلام البضاعة (القطع، الوزن، الشحن، الرف، نوع الطرد) → رسالة واتساب للزبون بالمتبقي.
 * (الشراء نفسه يتسجل على مستوى رحلة الشراء: TripPurchaseController)
 */
class OrderFinanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:order-receive', ['only' => ['receiveForm', 'receiveStore']]);
    }

    protected function notifications()
    {
        return Notif_orgs::where('user_id', auth()->id())->latest()->take(5)->get();
    }

    // ---------------------------------------------------------------- الاستلام

    public function receiveForm(Order $order)
    {
        $main_notifications = $this->notifications();
        $packageTypes = Order::PACKAGE_TYPES;
        $template = optional(whatsappMessage::where('type', 'arrived')->first())->message;

        return view('admin.finance.order-receive', compact('order', 'packageTypes', 'template', 'main_notifications'));
    }

    public function receiveStore(Request $request, Order $order)
    {
        $data = $request->validate([
            'received_qty' => 'required|integer|min:0',
            'bags_num' => 'required|integer|min:1',
            'kilo' => 'required|numeric|min:0',
            'ship_price' => 'required|numeric|min:0',
            'storage_num' => 'required|string|max:100',
            'package_type' => 'required|string|max:100',
            'send_whatsapp' => 'nullable|boolean',
        ], [
            'received_qty.required' => 'اكتب عدد القطع اللي وصلن',
            'bags_num.required' => 'اكتب عدد الطرود',
            'kilo.required' => 'اكتب الوزن',
            'ship_price.required' => 'اكتب قيمة الشحن على الزبون',
            'storage_num.required' => 'اكتب مكان التخزين (الرف)',
            'package_type.required' => 'اختار نوع الطرد',
        ]);

        DB::beginTransaction();
        try {
            $order->received_qty = $data['received_qty'];
            $order->bags_num = $data['bags_num'];
            $order->kilo = $data['kilo'];
            $order->ship_price = $data['ship_price'];
            $order->storage_num = $data['storage_num'];
            $order->package_type = $data['package_type'];
            $order->arrived = true;
            $order->arrived_at = $order->arrived_at ?: now()->toDateString();
            $order->received_at = now();
            $order->received_by = auth()->id();
            $order->refreshPaymentStatus();
            $order->save();

            Log::create([
                'user_id' => auth()->id(),
                'type' => 'order',
                'title' => ' استلام بضاعة (' . $order->code . ')',
                'log' => ' وصلت ' . $order->received_qty . ' قطعة من ' . $order->qty . ' - وزن ' . $order->kilo . ' - شحن ' . $order->ship_price . ' - رف ' . $order->storage_num,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->withInput()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }

        $msg = 'تم تسجيل استلام البضاعة. المتبقي على الزبون ' . $order->remaining() . ' ' . $order->currency_sign;

        if ($request->boolean('send_whatsapp')) {
            $text = $this->arrivalMessage($order);
            $sent = false;
            foreach (array_filter([$order->customer->phone_1, $order->customer->phone_2]) as $phone) {
                Bus::dispatchSync(new SendWhatsAppMessageJob([$phone], $text));
                if (session('whatsapp_status', false)) {
                    $sent = true;
                    break;
                }
            }
            $msg .= $sent ? ' — وتم إرسال رسالة واتساب للزبون ✅' : ' — لكن رسالة الواتساب ما وصلتش ⚠️';
        }

        return redirect()->route('order.index')->with($request->boolean('send_whatsapp') && !str_contains($msg, '✅') ? 'error' : 'success', $msg);
    }

    public static function arrivalMessage(Order $order): string
    {
        $template = optional(whatsappMessage::where('type', 'arrived')->first())->message
            ?? "وصلت بضاعتك. رقم الطلب {{\$order_code}}\nالشحن: {{\$ship_price}} {{\$currency}}\nالمتبقي: {{\$remaining}} {{\$currency}}";

        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');

        $vars = [
            '{{$name}}' => $order->customer->name,
            '{{$customer_code}}' => $order->customer->code,
            '{{$order_code}}' => $order->code,
            '{{$pieces}}' => $order->received_qty ?? $order->qty,
            '{{$ordered_pieces}}' => $order->qty,
            '{{$parcels}}' => $order->bags_num,
            '{{$package_type}}' => $order->package_type,
            '{{$shelf}}' => $order->storage_num,
            '{{$weight}}' => $fmt($order->kilo),
            '{{$total}}' => $fmt($order->total_price - $order->discount),
            '{{$ship_price}}' => $fmt($order->ship_price),
            '{{$grand_total}}' => $fmt($order->grandTotal()),
            '{{$paid}}' => $fmt($order->paid_price),
            '{{$remaining}}' => $fmt(max(0, $order->remaining())),
            '{{$currency}}' => $order->currency_sign ?: 'د.ل',
            '{{$app_url}}' => config('app.url'),
        ];

        return strtr($template, $vars);
    }
}
