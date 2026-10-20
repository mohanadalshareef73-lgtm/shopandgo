<?php

namespace App\Support;

use App\Models\Order;
use App\Services\MessageService;
use App\Services\TreasuryService as TS;

/** 🧾 إيصال الطلب كملف PDF + إرساله على الواتساب مع رسالة قصيرة */
class OrderPdf
{
    public static function make(Order $order, string $view = 'pdf.order-receipt'): string
    {
        $dir = storage_path('app/receipt-pdfs');
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $temp = storage_path('app/mpdf-temp');
        if (!is_dir($temp)) {
            mkdir($temp, 0700, true);
        }
        $order->loadMissing(['customer', 'items', 'platform', 'paids']);
        $fontDirs = (new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'];
        $fontData = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];
        $pdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'orientation' => 'P',
            'fontDir' => array_merge($fontDirs, [resource_path('fonts/somar')]),
            'fontdata' => array_merge($fontData, ['somar' => ['R' => 'Somar-Regular.ttf', 'B' => 'Somar-Bold.ttf', 'useOTL' => 0xFF, 'useKashida' => 75]]),
            'tempDir' => $temp, 'default_font' => 'somar', 'default_font_size' => 12,
            'margin_top' => 10, 'margin_bottom' => 12, 'margin_left' => 10, 'margin_right' => 10, 'autoScriptToLang' => true, 'autoLangToFont' => false]);
        $pdf->SetDirectionality('rtl');
        $pdf->SetTitle('إيصال ' . $order->code);
        $pdf->WriteHTML(view($view, ['order' => $order, 'c' => $order->customer, 'fmt' => fn ($n) => TS::fmt($n), 'invoice' => \App\Models\Invoice::first()])->render());
        $path = $dir . '/' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $order->code) . '-' . time() . '.pdf';
        file_put_contents($path, $pdf->Output('', \Mpdf\Output\Destination::STRING_RETURN));
        $old = glob($dir . '/*.pdf') ?: [];
        if (count($old) > 300) {
            usort($old, fn ($a, $b) => filemtime($a) <=> filemtime($b));
            foreach (array_slice($old, 0, count($old) - 300) as $f) {
                @unlink($f);
            }
        }

        return $path;
    }

    /** 📤 زر «إرسال الإيصال للعميل»: نفس فاتورتنا الأصلية PDF + رسالة «تم إرسال إيصال طلبك للتأكيد» ورابط المنظومة واسم المستخدم */
    public static function sendInvoice(Order $order): bool
    {
        $c = $order->customer;
        if (!$c) {
            return false;
        }
        $tpl = MessageService::template('receipt_sent');
        $text = $tpl && trim((string) $tpl->message) !== ''
            ? MessageService::render($tpl->message, MessageService::orderVars($order))
            : "✅ تم إرسال إيصال الطلب الخاص بك للتأكيد\n\n"
                . '🔢 رقم الطلب: *' . $order->code . "*\n"
                . '👤 ' . $c->name . ' · كود *' . $c->code . "*\n\n"
                . "تابع طلبك من حسابك في المنظومة 👇\n"
                . MessageService::loginText($c) . "\n\n"
                . 'شكراً لاختياركم Shop & Go 💙';
        $phone = MessageService::normalize((string) $c->phone_1);
        try {
            $file = static::make($order, 'pdf.order-invoice');
            $ok = app(\App\Services\WhatsAppService::class)->sendDocument($phone, $file, 'فاتورة-' . $order->code . '.pdf', $text);
        } catch (\Throwable $e) {
            report($e);
            $ok = false;
        }
        if (!$ok) {
            $ok = MessageService::sendText('receipt_sent', $text . "\n🧾 " . route('order.invoice', $order->code), array_values(array_filter([$c->phone_1, $c->phone_2])), $c, $order);

            return (bool) $ok;
        }
        try {
            \App\Services\CustomerNotify::inbox($c, $text, $order, 'receipt_sent');
            \App\Models\WhatsappOutbox::create(['type' => 'receipt_sent', 'phone' => $c->phone_1, 'body' => $text . ' [PDF]', 'status' => 'sent',
                'customer_id' => $c->id, 'order_id' => $order->id, 'user_id' => auth('web')->id()]);
        } catch (\Throwable $e) {
        }

        return true;
    }

    /** يبعت الإيصال PDF والرسالة (قالب "new") تحته — لو الملف ما مشاش يبعت الرسالة بروحها */
    public static function sendNew(Order $order): bool
    {
        $c = $order->customer;
        $tpl = MessageService::template('new');
        if (!$c || !$tpl || !($tpl->enabled ?? true)) {
            return false;
        }
        $text = MessageService::render($tpl->message, MessageService::orderVars($order));
        try {
            $file = static::make($order);
            $ok = app(\App\Services\WhatsAppService::class)->sendDocument(MessageService::normalize((string) $c->phone_1), $file, 'إيصال-' . $order->code . '.pdf', $text);
            if ($ok) {
                try {
                    \App\Services\CustomerNotify::inbox($c, $text, $order, 'new');
                    \App\Models\WhatsappOutbox::create(['type' => 'new', 'phone' => $c->phone_1, 'body' => $text . ' [PDF]', 'status' => 'sent',
                        'customer_id' => $c->id, 'order_id' => $order->id, 'user_id' => auth('web')->id()]);
                } catch (\Throwable $e) {
                }

                return true;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return MessageService::sendTemplate('new', $c, MessageService::orderVars($order), $order);
    }
}
