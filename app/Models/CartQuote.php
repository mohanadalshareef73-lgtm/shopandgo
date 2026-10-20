<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartQuote extends Model
{
    public const RETAILERS = ['shein' => 'شي إن', 'amazon' => 'أمازون', 'iherb' => 'آي هيرب'];

    public function getRetailerLabelAttribute(): string
    {
        $retailer = $this->retailer ?? 'shein';
        return self::RETAILERS[$retailer] ?? $retailer;
    }

    public function getRetailerNameAttribute(): string
    {
        $retailer = $this->retailer ?? 'shein';
        return ['shein' => 'SHEIN', 'amazon' => 'Amazon', 'iherb' => 'iHerb'][$retailer] ?? $retailer;
    }

    public const CUSTOMER_STATUSES = [
        'open' => 'بانتظار قرار العميل',
        'editing' => 'طلب تعديل الكميات',
        'confirmed' => 'أكد العميل الطلب',
        'cancelled' => 'ألغى العميل الطلب',
    ];

    public const PAYMENT_METHODS = ['cash' => 'الدفع نقدًا', 'bank' => 'تحويل بنكي'];

    public const OPERATOR_STATUSES = [
        'new' => 'جديدة',
        'following_up' => 'قيد المتابعة',
        'editing' => 'بانتظار تعديل الكميات',
        'confirmed' => 'مؤكدة',
        'buying' => 'قيد الشراء',
        'purchased' => 'تم الشراء',
        'cancelled' => 'ملغاة',
        'dismissed' => 'مستبعدة',
    ];

    public const QUOTE_STATUSES = [
        'pending' => 'بانتظار تجهيز العرض',
        'submitted' => 'جاري تجهيز العرض',
        'succeeded' => 'العرض جاهز',
        'failed' => 'تعذر تجهيز العرض',
        'cancelled' => 'تم إلغاء العرض',
        'expired' => 'انتهت مهلة العرض',
    ];

    public const PDF_STATUSES = [
        'new' => 'عرض سعر',
        'following_up' => 'قيد المتابعة',
        'editing' => 'بانتظار تعديل الكميات',
        'confirmed' => 'مؤكدة',
        'buying' => 'قيد الشراء',
        'purchased' => 'تم الشراء',
        'cancelled' => 'ملغاة',
        'dismissed' => 'مستبعدة',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'quotation' => 'array',
        'amazon_pricing' => 'array',
        'pdf_overrides' => 'array',
        'next_attempt_at' => 'datetime',
        'notified_at' => 'datetime',
        'operator_updated_at' => 'datetime',
        'whatsapp_queued_at' => 'datetime',
        'customer_updated_at' => 'datetime',
        'customer_payment_updated_at' => 'datetime',
        'deadline_checked_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function incomingMessage()
    {
        return $this->belongsTo(WhatsappMessages::class, 'whatsapp_messages_id');
    }

    public static function amazonSite(?string $storefront = null): string
    {
        return ['AMZ-AE' => 'amazon.ae', 'AMZ-SA' => 'amazon.sa', 'AMZ-US' => 'amazon.com'][$storefront ?? config('settings.storefronts.amazon')] ?? 'Amazon';
    }

    public function whatsappCartReference(): string
    {
        return $this->whatsapp_cart_reference ?? 'CART-'.$this->id;
    }

    public function whatsappReplyButtons(): array
    {
        // أسماء الأزرار تتعدل من الإعدادات (واتساب يقبل لين 20 حرف) · الأزرار تمشي على SgButtons (sg:...)
        $t = fn ($k, $d) => mb_substr(trim((string) \App\Services\AppSettings::get('btn_'.$k, '')) ?: $d, 0, 20);

        return [
            ['id' => 'sg:c_ok:'.$this->id, 'title' => $t('confirm', '✅ تأكيد الطلب')],
            ['id' => 'sg:c_edit:'.$this->id, 'title' => $t('edit', '✏️ تعديل الكميات')],
            ['id' => 'sg:c_cour:'.$this->id, 'title' => $t('cancel', '🛵 مندوب للعربون')],
        ];
    }

    public function whatsappPaymentButtons(): array
    {
        $buttons = [];
        foreach (self::PAYMENT_METHODS as $method => $title) {
            $buttons[] = ['id' => $method.':'.$this->whatsappCartReference(), 'title' => $title];
        }

        return $buttons;
    }

    public function handledBy()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function statusAudits()
    {
        return $this->hasMany(CartStatusAudit::class)->orderByDesc('id');
    }

    public function getPdfStampAttribute(): string
    {
        return self::PDF_STATUSES[$this->operator_status] ?? 'عرض سعر';
    }

    public function getWhatsappUrlAttribute(): ?string
    {
        return $this->contact_phone ? 'https://wa.me/'.$this->contact_phone : null;
    }

    public function getContactPhoneAttribute(): ?string
    {
        $sender = \App\Services\WhatsAppMessageContext::recipient($this->incomingMessage?->from);
        if ($sender !== null && ! str_ends_with($sender, '@lid')) {
            return $sender;
        }
        $phone = $this->incomingMessage?->contact_phone;
        return \App\Services\WhatsAppContactNumbers::validPhone($phone) ? $phone : null;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
