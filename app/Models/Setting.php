<?php

namespace App\Models;

use App\Support\AmazonMessageTemplates;
use App\Support\IherbMessageTemplates;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public const RECEIVED_MESSAGE = 'استلمنا رابط سلة شي إن. سنجهز عرض السعر ونرسله لك قريباً.';
    public const COMPLETED_MESSAGE = 'تم تجهيز عرض سعر سلة شي إن. تجد التفاصيل في ملف PDF.';
    public const PRODUCT_MESSAGE = 'يرجى إضافة جميع المنتجات التي ترغب في شرائها إلى سلة شي إن، ثم مشاركة رابط السلة وإرساله لنا بدلاً من روابط المنتجات، لنتمكن من تجهيز عرض السعر لك.';
    public const CALCULATION_NOTE = 'تعرض الأسعار بمنزلتين عشريتين. يقرب الإجمالي النهائي بالدينار فقط إلى أقرب دينار صحيح. الكميات التقديرية تحتاج إلى تأكيد، والأسعار والتوفر قابلان للتغيير قبل الشراء.';
    public const CART_ACTION_REPLIES = [
        'confirm' => 'تم تأكيد طلبك {cart_ref}. سيتواصل معك فريقنا لإتمام إجراءات الطلب.',
        'edit' => 'لتعديل كميات الطلب {cart_ref}، أرسل رقم كل قطعة من ملف PDF والكمية المطلوبة، مثلاً: القطعة 1: الكمية 2، القطعة 3: الكمية 0 للحذف. سيراجع فريقنا التعديلات ويرسل عرض سعر محدثاً قبل تأكيد الطلب.',
        'cancel' => 'تم إلغاء طلبك {cart_ref}. يمكنك إرسال رابط سلة جديد في أي وقت.',
    ];

    public const CART_PAYMENT_REPLIES = [
        'cash' => 'تم اختيار الدفع نقدًا للطلب {cart_ref}. سيتواصل معك فريقنا لتحديد مكان وموعد الدفع وإتمام إجراءات الطلب.',
        'bank' => 'تم اختيار التحويل البنكي للطلب {cart_ref}. سيتواصل معك فريقنا لتزويدك ببيانات الحساب والمبلغ المطلوب. يرجى إرسال إيصال التحويل هنا بعد الدفع لمراجعة العملية.',
    ];

    protected $fillable = ['usd_to_lyd_rate', 'shein_company_percentage', 'shein_received_message',
        'shein_completed_message', 'shein_calculation_note', 'whatsapp_numbers', 'whatsapp_main_number',
        'whatsapp_main_session_uuid', 'shein_pause_on_empty_wallet', 'shein_product_message',
        'shein_confirm_reply', 'shein_edit_reply', 'shein_cancel_reply',
        'cart_cash_reply', 'cart_bank_reply', 'reply_images',
        'shein_enabled', 'amazon_enabled', 'iherb_enabled', 'iherb_messages',
        'amazon_fixed_fee_usd', 'amazon_flat_surcharge_usd', 'amazon_threshold_usd', 'amazon_percentage', 'amazon_messages'];

    protected $casts = ['usd_to_lyd_rate' => 'decimal:6', 'shein_company_percentage' => 'decimal:4',
        'whatsapp_numbers' => 'array', 'reply_images' => 'array', 'shein_pause_on_empty_wallet' => 'boolean',
        'shein_enabled' => 'boolean', 'amazon_enabled' => 'boolean', 'iherb_enabled' => 'boolean', 'iherb_messages' => 'array',
        'amazon_fixed_fee_usd' => 'decimal:4', 'amazon_flat_surcharge_usd' => 'decimal:4',
        'amazon_threshold_usd' => 'decimal:4', 'amazon_percentage' => 'decimal:4', 'amazon_messages' => 'array'];

    public function isCartStoreEnabled(string $retailer): bool
    {
        return array_key_exists($retailer, CartQuote::RETAILERS)
            && ($this->getAttribute($retailer.'_enabled') ?? true);
    }

    public function amazonMessageTemplate(string $status): string
    {
        if (! isset(AmazonMessageTemplates::DEFINITIONS[$status])) {
            throw new \InvalidArgumentException('Unknown Amazon message status.');
        }

        $message = $this->amazon_messages[$status] ?? null;
        // Preserve existing text from the former bilingual settings.
        if (is_array($message)) {
            $message = $message['ar'] ?? $message['en'] ?? null;
        }

        return $message ?? AmazonMessageTemplates::DEFINITIONS[$status]['message'];
    }

    public function whatsappMainSessionUuid(): ?string
    {
        return $this->whatsapp_main_session_uuid
            ?? data_get($this->whatsapp_numbers ?? [], ($this->whatsapp_main_number ?? '').'.session_uuid')
            ?? config('services.whatsapp.main_session_uuid');
    }

    public function iherbMessageTemplate(string $status): string
    {
        if (! isset(IherbMessageTemplates::DEFINITIONS[$status])) {
            throw new \InvalidArgumentException('Unknown iHerb message status.');
        }
        return $this->iherb_messages[$status] ?? IherbMessageTemplates::DEFINITIONS[$status]['message'];
    }

    public function receivedMessage(): string
    {
        return $this->shein_received_message ?: self::RECEIVED_MESSAGE;
    }

    public function completedMessage(): string
    {
        return $this->shein_completed_message ?: self::COMPLETED_MESSAGE;
    }

    public function productMessage(): string
    {
        return $this->shein_product_message ?: self::PRODUCT_MESSAGE;
    }

    public function calculationNote(): string
    {
        return $this->shein_calculation_note ?: self::CALCULATION_NOTE;
    }

    public function cartActionReplyTemplate(string $action, string $retailer = 'shein'): string
    {
        if (! array_key_exists($action, self::CART_ACTION_REPLIES)) {
            throw new \InvalidArgumentException('Unknown cart reply action.');
        }

        return $retailer === 'iherb' ? $this->iherbMessageTemplate($action)
            : ($this->getAttribute('shein_'.$action.'_reply') ?: self::CART_ACTION_REPLIES[$action]);
    }

    public function cartActionReply(string $action, string $reference, string $retailer = 'shein'): string
    {
        return str_replace('{cart_ref}', $reference, $this->cartActionReplyTemplate($action, $retailer));
    }

    public function cartPaymentReplyTemplate(string $method): string
    {
        if (! array_key_exists($method, self::CART_PAYMENT_REPLIES)) {
            throw new \InvalidArgumentException('Unknown cart payment method.');
        }

        return $this->getAttribute('cart_'.$method.'_reply') ?: self::CART_PAYMENT_REPLIES[$method];
    }

    public function cartPaymentReply(string $method, string $reference): string
    {
        return str_replace('{cart_ref}', $reference, $this->cartPaymentReplyTemplate($method));
    }

    public static function replyImageKeys(): array
    {
        $keys = ['shein_received_message', 'shein_product_message', 'shein_completed_message',
            'shein_confirm_reply', 'shein_edit_reply', 'shein_cancel_reply', 'cart_cash_reply', 'cart_bank_reply'];
        foreach (array_keys(AmazonMessageTemplates::DEFINITIONS) as $status) {
            // These are lines inside a caption, not separate replies.
            if (! in_array($status, ['quantity', 'discount'], true)) {
                $keys[] = 'amazon_'.$status;
            }
        }
        foreach (array_keys(IherbMessageTemplates::DEFINITIONS) as $status) {
            $keys[] = 'iherb_'.$status;
        }

        return $keys;
    }

    public function replyImagePath(string $key): ?string
    {
        if (! in_array($key, self::replyImageKeys(), true)) {
            throw new \InvalidArgumentException('Unknown reply image key.');
        }

        return $this->reply_images[$key] ?? null;
    }

    public function cartReplyImageKey(string $action, string $retailer = 'shein'): string
    {
        if (array_key_exists($action, self::CART_PAYMENT_REPLIES)) {
            return 'cart_'.$action.'_reply';
        }
        if (! array_key_exists($action, self::CART_ACTION_REPLIES)) {
            throw new \InvalidArgumentException('Unknown cart reply action.');
        }

        return $retailer === 'iherb' ? 'iherb_'.$action : 'shein_'.$action.'_reply';
    }

    /** LYD for one USD, or null until an administrator sets the rate. */
    public static function usdToLydRate(): ?string
    {
        return static::find(1)?->usd_to_lyd_rate;
    }
}
