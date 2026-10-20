<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Log;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\MessageService;
use App\Services\OrderPaymentService;
use App\Services\TreasuryService as TS;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 📦 استلام منتجات الإيصال على دفعات: كل مرة يتسجل كم وصل من كل صنف،
 * وتقدر تقبض دفعة معاها، والعميل تجيه رسالة (اختيارية) بكم استلم وكم دفع.
 */
class ItemReceiveController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:order-edit');
    }

    /** 📄 ملف PDF الاستلام (يتحفظ في storage/app/receive-pdfs) */
    protected function pdf(Order $order, $c, $items, array $nowMap, int $got, int $all, float $paid, bool $done, string $note): string
    {
        $dir = storage_path('app/receive-pdfs');
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $temp = storage_path('app/mpdf-temp');
        if (!is_dir($temp)) {
            mkdir($temp, 0700, true);
        }
        $fontDirs = (new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'];
        $fontData = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];
        $pdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'orientation' => 'P',
            'fontDir' => array_merge($fontDirs, [resource_path('fonts/somar')]),
            'fontdata' => array_merge($fontData, ['somar' => ['R' => 'Somar-Regular.ttf', 'B' => 'Somar-Bold.ttf', 'useOTL' => 0xFF, 'useKashida' => 75]]),
            'tempDir' => $temp, 'default_font' => 'somar', 'default_font_size' => 12,
            'margin_top' => 10, 'margin_bottom' => 12, 'margin_left' => 10, 'margin_right' => 10, 'autoScriptToLang' => true, 'autoLangToFont' => false]);
        $pdf->SetDirectionality('rtl');
        $pdf->SetTitle('استلام ' . $order->code);
        $pdf->WriteHTML(view('pdf.items-receive', ['order' => $order, 'c' => $c, 'items' => $items, 'nowMap' => $nowMap, 'nowTotal' => array_sum($nowMap),
            'got' => $got, 'all' => $all, 'paid' => $paid, 'done' => $done, 'note' => $note, 'fmt' => fn ($n) => TS::fmt($n)])->render());
        $path = $dir . '/' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $order->code) . '-' . time() . '.pdf';
        file_put_contents($path, $pdf->Output('', \Mpdf\Output\Destination::STRING_RETURN));
        // نخليو آخر 300 ملف بس (خفيف على الاستضافة)
        $old = glob($dir . '/*.pdf') ?: [];
        if (count($old) > 300) {
            usort($old, fn ($a, $b) => filemtime($a) <=> filemtime($b));
            foreach (array_slice($old, 0, count($old) - 300) as $f) {
                @unlink($f);
            }
        }

        return $path;
    }

    /** 📤 إرسال الإيصال للعميل على الواتساب (ملف PDF + رسالة تسجيل الطلب) */
    public function sendReceipt(Order $order)
    {
        if (!$order->customer) {
            return back()->with('error', 'الإيصال هذا ما عندهش عميل');
        }
        $ok = \App\Support\OrderPdf::sendInvoice($order->fresh(['customer', 'items', 'platform', 'paids']));
        Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' إرسال إيصال (' . $order->code . ')', 'log' => $ok ? ' انبعت للعميل على الواتساب' : ' ما وصلش']);

        return back()->with($ok ? 'success' : 'error', $ok ? '📤 انبعت الإيصال ' . $order->code . ' للعميل على الواتساب ✅' : '✕ ما وصلش الإيصال — تأكد من رقم العميل وجلسة واتسي');
    }

    public function store(Request $request, Order $order)
    {
        if (!Schema::hasColumn('order_items', 'received_qty') || !Schema::hasTable('order_item_receives')) {
            return back()->with('error', 'افتح system-update قبل (تحديث قاعدة البيانات)');
        }
        $data = $request->validate([
            'got' => 'nullable|array', 'got.*' => 'nullable|integer|min:0|max:100000',
            'paid' => 'nullable|numeric|min:0', 'payment_id' => 'nullable|exists:payment_methods,id',
            'note' => 'nullable|string|max:600', 'send_whatsapp' => 'nullable|boolean',
        ]);
        $paid = round((float) ($data['paid'] ?? 0), 2);
        $lines = [];
        $done = false;
        try {
            DB::transaction(function () use ($order, $data, $paid, &$lines, &$done) {
                $items = OrderItem::where('order_id', $order->id)->lockForUpdate()->get();
                foreach ($items as $it) {
                    $want = (int) ($data['got'][$it->id] ?? 0);
                    $left = max(0, (int) $it->quantity - (int) $it->received_qty);
                    $add = min($want, $left);
                    if ($add > 0) {
                        $it->received_qty = (int) $it->received_qty + $add;
                        $it->save();
                        $lines[] = ['id' => $it->id, 'name' => $it->name, 'n' => $add, 'got' => (int) $it->received_qty, 'qty' => (int) $it->quantity];
                    }
                }
                if (!$lines && $paid <= 0) {
                    throw new \RuntimeException('اكتب كم وصل من صنف واحد على الأقل');
                }
                if ($paid > 0) {
                    if (empty($data['payment_id'])) {
                        throw new \RuntimeException('اختار الخزينة اللي دخلت فيها الدفعة');
                    }
                    OrderPaymentService::pay($order, $paid, (int) $data['payment_id'], 'دفعة وقت استلام منتجات');
                }
                $done = $items->every(fn ($i) => (int) $i->received_qty >= (int) $i->quantity);
                DB::table('order_item_receives')->insert([
                    'order_id' => $order->id, 'user_id' => auth()->id(), 'lines' => json_encode($lines, JSON_UNESCAPED_UNICODE),
                    'paid' => $paid, 'payment_id' => $data['payment_id'] ?? null, 'note' => $data['note'] ?? null,
                    'sent' => false, 'created_at' => now(), 'updated_at' => now(),
                ]);
                if ($done && !$order->delivered) {
                    $order->refresh();
                    $order->delivered = true;
                    $order->delivered_at = now()->toDateString();
                    if (Schema::hasColumn('orders', 'delivered_time')) {
                        $order->delivered_time = now();
                    }
                    if (Schema::hasColumn('orders', 'delivered_by')) {
                        $order->delivered_by = auth()->id();
                    }
                    $order->save();
                }
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $order->refresh();
        $items = OrderItem::where('order_id', $order->id)->get();
        $got = (int) $items->sum('received_qty');
        $all = (int) $items->sum('quantity');
        Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' استلام منتجات (' . $order->code . ')',
            'log' => ' ' . collect($lines)->map(fn ($l) => $l['name'] . ' × ' . $l['n'])->implode('، ') . ' · استلم ' . $got . ' من ' . $all . ($paid > 0 ? ' · دفع ' . TS::fmt($paid) : '')]);

        $sent = false;
        $c = $order->customer;
        if ($c && $request->boolean('send_whatsapp')) {
            // 📄 بدل الرسالة: ملف PDF فيه كم وصل وكم باقي وكم دفع وكم باقي عليه
            try {
                $nowMap = collect($lines)->pluck('n', 'id')->all();
                $file = $this->pdf($order, $c, $items, $nowMap, $got, $all, $paid, $done, trim((string) ($data['note'] ?? '')));
                $caption = "📦 *استلام منتجات — طلب {$order->code}*\nاستلمت {$got} من {$all} قطعة" . ($all - $got > 0 ? ' · باقي ' . ($all - $got) : ' ✅ بالكامل')
                    . "\n⏳ الباقي عليك: " . TS::fmt(max(0, $order->remaining())) . ' د.ل' . (!empty($data['note']) ? "\n📝 " . trim($data['note']) : '');
                $sent = app(\App\Services\WhatsAppService::class)->sendDocument(MessageService::normalize((string) $c->phone_1), $file, 'استلام-' . $order->code . '.pdf', $caption);
                \App\Models\WhatsappOutbox::create(['type' => 'items_receive', 'phone' => $c->phone_1, 'body' => $caption . ' [PDF]', 'status' => $sent ? 'sent' : 'failed',
                    'error' => $sent ? null : 'PDF ما وصلش', 'customer_id' => $c->id, 'order_id' => $order->id, 'user_id' => auth()->id()]);
                if ($sent) {
                    DB::table('order_item_receives')->where('order_id', $order->id)->latest('id')->limit(1)->update(['sent' => true]);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', '📦 تسجل الاستلام · ' . $got . ' من ' . $all . ' قطعة' . ($done ? ' · ✅ تسلم بالكامل' : ' · باقي ' . ($all - $got))
            . ($paid > 0 ? ' · قبضنا ' . TS::fmt($paid) . ' د.ل' : '') . ($sent ? ' · وصلت الرسالة للعميل' : ''));
    }
}
