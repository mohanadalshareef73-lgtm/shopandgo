@extends('sg.layout')
@section('title', 'الإعدادات')
@php
    $list = fn ($v) => implode('، ', (array) $v);
    $tabs = ['shop' => 'المحل', 'ship' => 'الشحن والرفوف', 'points' => '⭐ النقاط', 'delivery' => '🚚 التوصيل وحسابات العملاء', 'remind' => '⏰ التذكيرات والتقرير', 'numbers' => 'الترقيم وSHEIN', 'money' => 'الخزائن', 'statuses' => 'حالات الرحلة', 'platforms' => 'المنصات'];
    $tg = fn ($k, $label, $hint = '') => '<label class="tgl" style="display:flex;margin-bottom:10px"><input type="checkbox" name="' . $k . '" value="1" ' . (!empty($s[$k]) ? 'checked' : '') . '><span class="tg"></span><span>' . e($label) . ($hint ? '<br><small class="note">' . e($hint) . '</small>' : '') . '</span></label><input type="hidden" name="_bools[]" value="' . $k . '">';
@endphp
@section('content')
<div class="top">
    <div><h1>الإعدادات</h1><div class="sub">كل إعداد يأثر على الواجهة الجديدة</div></div>
    <div class="acts"><a class="btn ghost" href="{{ route('settings.edit') }}">🛍 إعدادات SHEIN وأمازون وعروض الأسعار</a><button class="sg-burger">☰</button></div>
</div>
<nav class="tabs">@foreach ($tabs as $k => $v)<a href="?tab={{ $k }}" class="{{ $tab === $k ? 'on' : '' }}">{{ $v }}</a>@endforeach</nav>

@if (in_array($tab, ['shop', 'ship', 'points', 'delivery', 'remind', 'numbers']))
<form class="card" method="post" action="{{ route('sg.settings.save') }}" style="max-width:820px">
    @csrf
    @if ($tab === 'shop')
        <div class="grid g2 f"><div><label class="l">اسم المحل</label><input name="shop_name" class="inp" value="{{ $s['shop_name'] }}"></div><div><label class="l">هاتف المحل</label><input name="shop_phone" class="inp numr" value="{{ $s['shop_phone'] }}"></div></div>
        <div class="grid g2 f"><div><label class="l">العنوان</label><input name="shop_address" class="inp" value="{{ $s['shop_address'] }}"></div><div><label class="l">أوقات الدوام</label><input name="shop_hours" class="inp" value="{{ $s['shop_hours'] }}"></div></div>
        <div class="f"><label class="l">أرقام الإدارة (التقارير، التنبيهات، تقفيل الورديات) — افصل بفاصلة</label><input name="report_phones" class="inp numr" value="{{ $list($s['report_phones']) }}" placeholder="0912345678، 0923456789"></div>
        {!! $tg('monthly_report_enabled', 'أرسل التقرير الشهري أوتوماتيك أول يوم في الشهر', 'يمشي على أرقام الإدارة') !!}
        @php $s['biweekly_report_enabled'] = $s['biweekly_report_enabled'] ?? true; @endphp
        {!! $tg('biweekly_report_enabled', 'أرسل تقرير كل 14 يوم', 'الأرقام + أهم العملاء + اللي يحتاج انتباه، على أرقام الإدارة الساعة 9:15 الصبح') !!}
        <div class="f"><label class="l">أرقام الحجز — تطلع في قائمة ⚡ احجز (افصل بفاصلة)</label><input id="bookingPhones" name="booking_phones" class="inp numr" value="{{ $list(array_values(array_filter(array_unique(array_merge((array) ($s['booking_phones'] ?? []), (array) ($s['booking_numbers'] ?? []))), fn ($x) => !str_contains((string) $x, '@')))) }}"></div>
        @php $s['wa_footer_enabled'] = $s['wa_footer_enabled'] ?? true; @endphp
        {!! $tg('wa_footer_enabled', 'زيد رابط الحساب تحت رسائل واتساب للعملاء', 'باش العميل يتعود يدخل المنظومة · {رابط} = رابط الدخول') !!}
        <div class="grid g2 f"><div><label class="l">تحت أي رسالة للعميل (وصول، تنبيه…)</label><textarea name="wa_footer" class="inp" rows="2">{{ $s['wa_footer'] }}</textarea></div>
            <div><label class="l">تحت عرض سعر السلة (لما يبعت رابط)</label><textarea name="wa_cart_footer" class="inp" rows="2">{{ $s['wa_cart_footer'] }}</textarea></div></div>
        @php $s['wa_buttons'] = $s['wa_buttons'] ?? true; @endphp
        {!! $tg('wa_buttons', 'أزرار رد تحت رسائل الواتساب', 'وصلت بضاعتك (وصّلهالي/نجي نستلم) · نواقص (إلغاء/بديل) · الدين (كاش/تحويل) · الحملات (وصلتني/توقف) · رابط السعر (نبي مندوب/كلّم موظف) · ' . count((array) ($s['wa_optout'] ?? [])) . ' عميل ضغط «توقف»') !!}
        @php $s['wa_quote_link_mode'] = $s['wa_quote_link_mode'] ?? false; @endphp
        {!! $tg('wa_quote_link_mode', 'سعر السلة على الواتساب = رابط بس (بدل السعر والـ PDF)', 'العميل يفتح الرابط ويشوف السعر كزائر، ولما يبي يأكد أو يطلب مندوب يسجّل · لو طفيتها يرجع يجيه السعر والـ PDF زي قبل') !!}
        <div class="f"><label class="l">رسالة رابط السعر <span class="note">{رابط} = رابط السلة</span></label><textarea name="wa_quote_link_text" class="inp" rows="4">{{ $s['wa_quote_link_text'] ?? '' }}</textarea></div>
        <div class="card" style="background:var(--line-soft);padding:12px;margin-bottom:12px">
            <b>🛒 رسايل السلة على الواتساب</b> <span class="note">فارغ = النص الافتراضي</span>
            <div class="f" style="margin-top:8px"><label class="l">أول رد لما العميل يبعت الرابط</label><textarea name="wa_ack_text" class="inp" rows="5">{{ $s['wa_ack_text'] ?? '' }}</textarea></div>
            <div class="f"><label class="l">⚡ تحت أول رد: رابط يحسب السلة في المنظومة <span class="note">{رابط} = رابط السلة · فارغ = النص الافتراضي</span></label><textarea name="wa_ack_link" class="inp" rows="3" placeholder="{{ \App\Services\AppSettings::DEFAULTS['wa_ack_link'] }}">{{ $s['wa_ack_link'] ?? '' }}</textarea></div>
            <div class="f"><label class="l">⚡ تحت كل رسايل السلة (السعر والأزرار) <span class="note">{رابط} = رابط سلته · فارغ = النص الافتراضي</span></label><textarea name="wa_cart_link_line" class="inp" rows="2" placeholder="{{ \App\Services\AppSettings::DEFAULTS['wa_cart_link_line'] }}">{{ $s['wa_cart_link_line'] ?? '' }}</textarea></div>
            <div class="note mb">تحت رسالة السعر (PDF) 3 أزرار — الاسم لين 20 حرف:</div>
            @foreach (['confirm' => ['✅ تأكيد الطلب', 'الرد (يجيه معاه 3 خيارات: ⚡ حجز فوري · 🛵 مندوب · 🏦 تحويل)', 'wa_rp_confirm', ''], 'edit' => ['✏️ تعديل الكميات', 'الرد (صور القطع المكررة) · {رابط} = رابط سلته (لو ما حطيتهش يتزاد تحت أوتوماتيك)', 'wa_rp_edit', ''], 'cancel' => ['🛵 مندوب للعربون', 'الرد · {العربون} = قيمة العربون · {رابط} = رابط تعبئة البيانات', 'wa_rp_courier', '']] as $k => [$def, $lb, $rk, $x])
            <div class="grid g2 f">
                <div><label class="l">زر — الاسم</label><input name="btn_{{ $k }}" maxlength="20" class="inp" value="{{ $s['btn_'.$k] ?? '' }}" placeholder="{{ $def }}"></div>
                <div><label class="l">{{ $lb }}</label><textarea name="{{ $rk }}" class="inp" rows="3" placeholder="فارغ = النص الافتراضي">{{ $s[$rk] ?? '' }}</textarea></div>
            </div>
            @endforeach
        </div>
        <div class="f"><label class="l">أول رد لما العميل يبعت رابط سلة على الواتساب <span class="note">{رابط} = صفحة السلات في حسابه</span></label><textarea name="wa_ack_promo" class="inp" rows="3">{{ $s['wa_ack_promo'] ?? '' }}</textarea></div>
        <div class="card" style="background:var(--line-soft);padding:12px;margin-bottom:12px">
            <b>🏦 حساب التحويل اللي يطلع للعميل في السلة</b>
            <div class="grid g2 f" style="margin-top:8px"><div><label class="l">رقم الحساب (فيه زر نسخ)</label><input name="transfer_account" class="inp numr" value="{{ $s['transfer_account'] ?? '' }}"></div><div><label class="l">اسم صاحب الحساب</label><input name="transfer_holder" class="inp" value="{{ $s['transfer_holder'] ?? '' }}"></div></div>
            <div class="grid g2 f"><div><label class="l">المصرف</label><input name="transfer_bank_name" class="inp" value="{{ $s['transfer_bank_name'] ?? '' }}"></div>
                <div><label class="l">التحويلات تدخل في خزينة</label><select name="transfer_payment_id" class="inp"><option value="">— أول حساب مصرف —</option>@foreach (\App\Models\PaymentMethod::where('status', true)->get() as $pm)<option value="{{ $pm->id }}" @selected((int) ($s['transfer_payment_id'] ?? 0) === $pm->id)>{{ $pm->name }}</option>@endforeach</select></div></div>
        </div>
        <div class="grid g2 f"><div><label class="l">عربون إجباري للسلات من (د.ل) <span class="note">0 = بدون</span></label><input name="deposit_min" class="inp numr" value="{{ $s['deposit_min'] ?? 1000 }}"></div><div><label class="l">نسبة العربون % <span class="note">أقل مبلغ يدفعه العميل للمندوب</span></label><input name="deposit_pct" class="inp numr" value="{{ $s['deposit_pct'] ?? 30 }}"></div></div>
        <div class="f"><label class="l">نقاط «ادعُ صديق» (تنزل للعميل لما صاحبه يستلم أول طلبية)</label><input name="referral_points" class="inp numr" value="{{ $s['referral_points'] ?? 50 }}"></div>
        <div class="f"><label class="l">الأسئلة والسياسات في حساب العميل <span class="note">سؤال في سطر يبدا بـ س: والجواب بعده في سطر يبدا بـ ج:</span></label><textarea name="portal_faq" class="inp" rows="10">{{ $s['portal_faq'] ?? \App\Services\AppSettings::defaultFaq() }}</textarea></div>
    @elseif ($tab === 'ship')
        <div class="grid g3 f"><div><label class="l">سعر الكيلو على العميل</label><input name="ship_price_per_kg" class="inp numr" value="{{ $s['ship_price_per_kg'] }}"></div><div><label class="l">أقل سعر شحن</label><input name="ship_min" class="inp numr" value="{{ $s['ship_min'] }}"></div><div><label class="l">تذكير الرف بعد (يوم)</label><input name="shelf_reminder_days" class="inp numr" value="{{ $s['shelf_reminder_days'] }}"></div></div>
        <div class="grid g2 f"><div><label class="l">صفوف الرفوف</label><input name="shelf_rows" class="inp" value="{{ $list($s['shelf_rows']) }}" placeholder="فارغ = أرقام بس (1، 2، 3 …)"></div><div><label class="l">عدد الرفوف (في كل صف)</label><input name="shelf_per_row" class="inp numr" value="{{ $s['shelf_per_row'] }}"></div></div>
        <div class="f"><label class="l">أنواع الطرود</label><input name="package_types" class="inp" value="{{ $list($s['package_types']) }}"></div>
        <div class="hint">الرفوف: {{ implode(' · ', array_slice(\App\Services\AppSettings::shelves(), 0, 12)) }}{{ count(\App\Services\AppSettings::shelves()) > 12 ? ' …' : '' }} ({{ count(\App\Services\AppSettings::shelves()) }} رف)</div>
    @elseif ($tab === 'points')
        <div class="hint mb" style="background:#fff8e6"><b>🏅 مستويات العملاء</b> — أوتوماتيك حسب مجموع صرف العميل، وكل مستوى ينقص من سعر كيلو الشحن (ما ينطبقش على اللي عندهم سعر خاص)</div>
        {!! $tg('loyalty_enabled', 'تشغيل المستويات (برونزي · فضي · ذهبي)', 'العميل يشوف مستواه وكم باقيله على الجاي في حسابه') !!}
        @foreach ([1 => '🥉 برونزي', 2 => '🥈 فضي', 3 => '🥇 ذهبي'] as $n => $lb)
            <div class="grid g2 f"><div><label class="l">{{ $lb }} — من صرف (د.ل)</label><input name="loyalty_t{{ $n }}" class="inp numr" value="{{ $s['loyalty_t' . $n] ?? [1 => 2000, 2 => 5000, 3 => 10000][$n] }}"></div><div><label class="l">خصم كيلو الشحن ($)</label><input name="loyalty_d{{ $n }}" class="inp numr" value="{{ $s['loyalty_d' . $n] ?? [1 => 0.25, 2 => 0.5, 3 => 1][$n] }}"></div></div>
        @endforeach
        <hr style="border:0;border-top:1px solid var(--line);margin:14px 0">
        {!! $tg('points_enabled', 'تشغيل النقاط', 'العميل يأخذ نقاط أوتوماتيك على كل إيصال جديد، ويمكنه استخدامها خصم') !!}
        <div class="grid g2 f"><div><label class="l">نقاط ثابتة لكل إيصال</label><input name="points_per_order" class="inp numr" value="{{ $s['points_per_order'] }}"></div><div><label class="l">نقاط لكل 100 د.ل من قيمة الإيصال</label><input name="points_per_100" class="inp numr" value="{{ $s['points_per_100'] }}"></div></div>
        <div class="grid g2 f"><div><label class="l">قيمة النقطة (د.ل) وقت الخصم</label><input name="point_value" class="inp numr" value="{{ $s['point_value'] }}"></div><div><label class="l">أقل عدد نقاط يستطيع أن يستخدمها</label><input name="points_min_redeem" class="inp numr" value="{{ $s['points_min_redeem'] }}"></div></div>
        <label class="l">طريقة استخدام النقاط</label>
        <div class="opts mb" style="flex-wrap:wrap">@foreach (['money' => '💵 خصم مبلغ من الطلبية', 'coupon' => '🎟 كوبون خصم بنسبة', 'both' => 'الاثنين (العميل يختار)'] as $k => $v)<label><input type="radio" name="points_mode" value="{{ $k }}" @checked(($s['points_mode'] ?? 'money') === $k)><span>{{ $v }}</span></label>@endforeach</div>
        <div class="grid g2 f"><div><label class="l">نقاط مقابل كوبون واحد</label><input name="coupon_points" class="inp numr" value="{{ $s['coupon_points'] ?? 100 }}"></div><div><label class="l">نسبة خصم الكوبون %</label><input name="coupon_percent" class="inp numr" value="{{ $s['coupon_percent'] ?? 10 }}"></div></div>
        <div class="grid g2 f"><div><label class="l">أقصى خصم للكوبون (د.ل، 0 = بلا حد)</label><input name="coupon_max" class="inp numr" value="{{ $s['coupon_max'] ?? 0 }}"></div><div><label class="l">صلاحية الكوبون (أيام)</label><input name="coupon_days" class="inp numr" value="{{ $s['coupon_days'] ?? 30 }}"></div></div>
        {!! $tg('points_notify', 'إرسال للعميل واتساب لما تزيد نقاطه') !!}
        <div class="hint">مثال: إيصال بـ 500 د.ل يعطي {{ (int) $s['points_per_order'] + floor(5 * (float) $s['points_per_100']) }} نقطة = {{ \App\Services\TreasuryService::fmt(((int) $s['points_per_order'] + floor(5 * (float) $s['points_per_100'])) * (float) $s['point_value']) }} د.ل خصم. يمكنك أن تزيد أو تنقص نقاط أي عميل من صفحته.</div>
    @elseif ($tab === 'remind')
        <h3>📦 تذكير الرف (أوتوماتيك)</h3>
        {!! $tg('shelf_auto_remind', 'أرسل تذكير أوتوماتيك كل يوم للي نسيوا بضاعتهم', 'رسالة "تذكير الرف" من رسائل الواتساب، ويصلك تنبيه بكم أُرسل') !!}
        <div class="grid g4 f">
            <div><label class="l">بعد كم يوم في الرف</label><input name="shelf_reminder_days" class="inp numr" value="{{ $s['shelf_reminder_days'] }}"></div>
            <div><label class="l">يتعاود كل (يوم)</label><input name="shelf_remind_every" class="inp numr" value="{{ $s['shelf_remind_every'] }}"></div>
            <div><label class="l">أقصى عدد تذكيرات</label><input name="shelf_remind_max" class="inp numr" value="{{ $s['shelf_remind_max'] }}"></div>
            <div><label class="l">الساعة</label><input type="time" name="shelf_remind_time" class="inp" value="{{ $s['shelf_remind_time'] }}"></div>
        </div>
        <div class="hint mb">بعد آخر تذكير، الطرد يظهر لك في التنبيهات "اتصلوا بهم" بدل ما نزعجو العميل أكثر.</div>
        <h3 style="margin-top:18px">💰 تذكير الديون (بتأكيدك)</h3>
        {!! $tg('debt_remind_enabled', 'جهزلي قائمة تذكير الديون كل يوم ونبهني', 'ما يُرسل أي شيء للعميل حتى تأكد من صفحة "تذكيرات تستنى تأكيدك"') !!}
        <div class="grid g3 f">
            <div><label class="l">الدين مضى عليه (يوم)</label><input name="debt_remind_days" class="inp numr" value="{{ $s['debt_remind_days'] }}"></div>
            <div><label class="l">لا يُعاد قبل (يوم)</label><input name="debt_remind_every" class="inp numr" value="{{ $s['debt_remind_every'] }}"></div>
            <div><label class="l">أقل مبلغ (د.ل)</label><input name="debt_remind_min" class="inp numr" value="{{ $s['debt_remind_min'] }}"></div>
        </div>
        <h3 style="margin-top:18px">🔔 تنبيهات الطلبات والرحلات</h3>
        <div class="grid g2 f"><div><label class="l">نبهني على الطلب الذي ليس في رحلة بعد (يوم)</label><input name="free_order_alert_days" class="inp numr" value="{{ $s['free_order_alert_days'] }}"></div><div><label class="l">نبهني على الرحلة التي لم تصل بعد (يوم من الشراء)</label><input name="trip_late_days" class="inp numr" value="{{ $s['trip_late_days'] }}"></div></div>
        <h3 style="margin-top:18px">📊 التقرير اليومي</h3>
        {!! $tg('daily_report_enabled', 'أرسل لي تقرير اليوم على الواتساب', 'يمشي لأرقام الإدارة (من تبويب المحل): الإيصالات، الذي تسلّم، الذي قبضناه، الخزائن، الرف، الديون') !!}
        <div class="grid g3 f"><div><label class="l">الساعة</label><input type="time" name="daily_report_time" class="inp" value="{{ $s['daily_report_time'] }}"></div></div>
        <details class="mb"><summary class="note" style="cursor:pointer">اعرض شكل تقرير اليوم</summary><pre class="hint" style="white-space:pre-wrap;font-family:inherit;margin-top:8px">{{ \App\Services\DailyReport::text() }}</pre></details>
    @elseif ($tab === 'numbers')
        <h3>ترقيم الإيصالات</h3>
        <div class="grid g2 f"><div><label class="l">البادئة</label><input name="order_prefix" class="inp numr" value="{{ $s['order_prefix'] }}"></div><div><label class="l">يبدا من رقم</label><input name="order_start" class="inp numr" value="{{ $s['order_start'] }}"></div></div>
        <div class="hint mb">الإيصال القادم يأخذ: <b class="num">{{ \App\Services\OrderNumber::peek() }}</b> — كل الإيصالات (الفورم القديم والجديد) تتبع نفس الترقيم بالتسلسل.</div>
        <h3 style="margin-top:18px">💳 بطاقات SHEIN</h3>
        <div class="grid g2 f"><div><label class="l">عدد أرقام البطاقة</label><input name="shein_card_digits" class="inp numr" value="{{ $s['shein_card_digits'] }}"></div><div><label class="l">عدد أرقام الرمز</label><input name="shein_pin_digits" class="inp numr" value="{{ $s['shein_pin_digits'] }}"></div></div>
        <div class="f"><label class="l">سعر الدولار عند بيع البطاقات للعملاء (د.ل)</label><input name="card_sale_rate" class="inp numr" value="{{ $s['card_sale_rate'] ?? '' }}" placeholder="فارغ = آخر سعر شراء للدولار"><div class="note">يُقترح سعر البطاقة = قيمتها بالدولار × هذا السعر، ويمكن تعديله في كل إيصال</div></div>
    @else
        <div class="grid g2 f"><div><label class="l">أجرة التوصيل الافتراضية (للمندوب)</label><input name="courier_default_fee" class="inp numr" value="{{ $s['courier_default_fee'] }}"></div><div><label class="l">أقصى فرق يُشطب على الشركة إذا قبض المندوب أقل (د.ل)</label><input name="courier_writeoff_max" class="inp numr" value="{{ $s['courier_writeoff_max'] }}"></div></div>
        {!! $tg('portal_delivery_enabled', 'العميل يستطيع أن يطلب توصيل من حسابه') !!}
        @php $s['courier_auto_assign'] = $s['courier_auto_assign'] ?? true; @endphp
        {!! $tg('courier_auto_assign', 'طلب التوصيل يمشي للمندوب أوتوماتيك', 'يختار مندوب متاح من نفس المنطقة والأقل شغل، ويوصله إشعار طول. لو مافيش متاح يقعد يستنى تعيين') !!}
        {!! $tg('signup_enabled', 'السماح للعملاء بإنشاء حساب بأنفسهم', 'يصلك تنبيه بكل طلب تسجيل، ولا يُفعَّل الحساب إلا بعد موافقتك') !!}
        {!! $tg('otp_enabled', 'رمز تحقق عبر واتساب عند التسجيل وأول دخول', 'يمنع التسجيل بأرقام وهمية ويحمي حسابات العملاء من الدخول بدون إذنهم') !!}
        {!! $tg('courier_phone_hidden', 'خبّي أرقام كل المندوبين على العملاء', 'يطلعلهم رقم المحل بداله · وتقدر تخبي مندوب واحد بس من صفحة المندوبين') !!}
        {!! $tg('courier_alert_whatsapp', 'أرسل تنبيه واتساب للإدارة لما المندوب يكتب مشكلة أو عميل يطلب توصيل') !!}
        {!! $tg('courier_wa_notify', '🛵 رسالة واتساب للمندوب أول ما تتعينله توصيلة', 'العنوان + رقم الزبون + كم يقبض + رابط التطبيق · طفيها لو ما تبيش رسايل للمندوبين') !!}
        <div class="f"><label class="l">بيانات الحساب المصرفي (تظهر للعميل لما يختار تحويل)</label><textarea name="bank_accounts_text" class="inp" rows="3" placeholder="مصرف الجمهورية · رقم الحساب …">{{ $s['bank_accounts_text'] }}</textarea></div>
    @endif
    <button class="btn">حفظ</button>
</form>
@if ($tab === 'shop')
@role('Admin')
@php
    $gRecent = array_filter((array) ($s['wa_groups_recent'] ?? []), fn ($g) => !empty($g['tag'])); // قروب الحجز بس (اللي انكتب فيه #حجز)
    $gSeen = (array) ($s['wa_groups_seen'] ?? []);
    $gAll = collect($gSeen)->merge($gRecent)->filter(fn ($g) => !empty($g['id']))->keyBy('id');
    $gTest = (array) ($s['wa_group_test'] ?? []);
@endphp
<div class="card" style="max-width:820px;margin-top:12px">
    <h3 style="margin:0 0 6px">🧪 تجربة قروب الحجز (منتظرة)</h3>
    <div class="note" style="margin-bottom:10px">1) خلّي <b>حد ثاني</b> (مش الرقم المربوط بواتسي) يكتب <b>#حجز</b> في قروب منتظرة · 2) حدّث الصفحة هذي · 3) لو طلع القروب تحت اضغط 🧪 جرب</div>
    @if (!$gRecent && !$gSeen)
        <div class="chip n">لين توا ما جاتنا حتى رسالة من أي قروب</div>
    @endif
    @foreach ($gRecent as $k => $g)
        <div class="ln" style="gap:8px;flex-wrap:wrap">
            <span>👥 <b>{{ $g['name'] ?: 'قروب منتظرة (الحجز)' }}</b> <small class="note">{{ $g['id'] ?: ('ما لقيناش ID · ' . ($g['raw'] ?? '')) }} · {{ $g['at'] ?? '' }}</small> @if (!empty($g['tag']))<span class="chip u">#حجز</span>@endif</span>
            @if (!empty($g['id']))
            <form method="post" action="{{ route('sg.settings.group.test') }}" style="margin:0">@csrf<input type="hidden" name="id" value="{{ $g['id'] }}"><button class="btn sm">🧪 جرب</button></form>
            @endif
            <form method="post" action="{{ route('sg.settings.group.remove') }}" style="margin:0" onsubmit="return confirm('نمسحو القروب هذا من القائمة؟')">@csrf<input type="hidden" name="id" value="{{ $g['id'] ?: $k }}"><button class="btn ghost sm" style="color:var(--down)">🗑</button></form>
        </div>
    @endforeach
    @foreach ($gSeen as $k => $g)
        @if (empty($gRecent[$k]))
        <div class="ln" style="gap:8px;flex-wrap:wrap">
            <span>👥 <b>{{ $g['name'] ?: 'قروب' }}</b> <small class="note">{{ $g['id'] ?? $k }} · {{ $g['at'] ?? '' }}</small></span>
            <form method="post" action="{{ route('sg.settings.group.test') }}" style="margin:0">@csrf<input type="hidden" name="id" value="{{ $g['id'] ?? $k }}"><button class="btn sm">🧪 جرب</button></form>
            <form method="post" action="{{ route('sg.settings.group.remove') }}" style="margin:0" onsubmit="return confirm('نمسحو القروب هذا من القائمة؟')">@csrf<input type="hidden" name="id" value="{{ $g['id'] ?? $k }}"><button class="btn ghost sm" style="color:var(--down)">🗑</button></form>
        </div>
        @endif
    @endforeach
    <form method="post" action="{{ route('sg.settings.group.test') }}" style="display:flex;gap:6px;margin-top:10px">@csrf<input name="id" class="inp numr" placeholder="أو حط ID القروب يدوي (ينتهي بـ ‎@g.us)"><button class="btn ghost sm">🧪 جرب</button></form>
    @if ($gTest)
        <div class="note" style="margin-top:8px">آخر تجربة: {{ $gTest['at'] ?? '' }} · {{ !empty($gTest['ok']) ? '✅ واتسي قبلها' : '✕ رفضها' }} · <small>{{ $gTest['msg'] ?? '' }}</small></div>
    @endif
</div>
@endrole
@endif

@elseif ($tab === 'money')
<div class="grid g2" style="max-width:900px">
    <div class="card">
        <h3>الخزائن (طرق الدفع)</h3>
        @foreach ($payments as $p)
            <div class="ln"><span>{{ $p->name }} @if ($p->id == $s['cash_payment_id'])<span class="chip u">الكاش (الدرج)</span>@endif @if (!$p->status)<span class="chip n">موقوفة</span>@endif</span>@if ($p->id != $s['cash_payment_id'])<form method="post" action="{{ route('sg.settings.payment.toggle', $p) }}">@csrf<button class="btn ghost sm">{{ $p->status ? 'إيقاف' : 'تشغيل' }}</button></form>@endif</div>
        @endforeach
        <form method="post" action="{{ route('sg.settings.payment') }}" style="display:flex;gap:6px;margin-top:12px">@csrf<input name="name" class="inp" placeholder="خزينة جديدة (مثلا: مصرف الجمهورية)" required><button class="btn">إضافة</button></form>
    </div>
    <form class="card" method="post" action="{{ route('sg.settings.save') }}">
        @csrf
        <h3>خزينة الكاش</h3>
        <div class="note mb">الخزينة التي يُحسب منها تقفيل الكاش للموظفين والمندوبين</div>
        <select name="cash_payment_id" class="inp mb">@foreach ($payments as $p)<option value="{{ $p->id }}" @selected($p->id == $s['cash_payment_id'])>{{ $p->name }}</option>@endforeach</select>
        <button class="btn">حفظ</button>
    </form>
</div>

@elseif ($tab === 'statuses')
<form class="card tw" method="post" action="{{ route('sg.settings.statuses') }}" style="max-width:900px">
    @csrf
    <div class="tw-h"><h3>حالات الرحلة</h3><span class="note">المرحلة تحدد أين يظهر الإيصال: قيد التجهيز / في الطريق / وصلت</span></div>
    <table class="t"><thead><tr><th>الترتيب</th><th>الاسم</th><th>المرحلة</th><th>اللون</th><th>حذف</th></tr></thead><tbody>
    @foreach ($statuses as $st)
        <tr><td style="width:80px"><input name="st[{{ $st->id }}][sort]" class="inp numr" style="height:36px" value="{{ $st->sort }}"></td><td><input name="st[{{ $st->id }}][name]" class="inp" style="height:36px" value="{{ $st->name }}"></td><td><select name="st[{{ $st->id }}][stage]" class="inp" style="height:36px">@foreach (['prep' => 'قيد التجهيز', 'way' => 'في الطريق', 'arrived' => 'وصلت'] as $k => $v)<option value="{{ $k }}" @selected($st->stage === $k)>{{ $v }}</option>@endforeach</select></td><td style="width:70px"><input type="color" name="st[{{ $st->id }}][color]" value="{{ $st->color ?: '#2a78d6' }}" style="width:44px;height:34px;border:0;background:none"></td><td><input type="checkbox" name="st[{{ $st->id }}][delete]" value="1"></td></tr>
    @endforeach
    </tbody></table>
    <div class="tw-f"><input name="new_name" class="inp" style="max-width:260px" placeholder="حالة جديدة"><select name="new_stage" class="inp" style="max-width:160px"><option value="prep">قيد التجهيز</option><option value="way" selected>في الطريق</option><option value="arrived">وصلت</option></select><button class="btn">حفظ</button></div>
</form>

@else
<form class="card tw" method="post" action="{{ route('sg.settings.platforms') }}" style="max-width:700px">
    @csrf
    <div class="tw-h"><h3>المنصات</h3><span class="note">سعر الصرف الافتراضي لكل منصة (اختياري)</span></div>
    <table class="t"><thead><tr><th>المنصة</th><th>السعر</th></tr></thead><tbody>
    @foreach ($platforms as $p)
        <tr><td><input name="pf[{{ $p->id }}][name]" class="inp" style="height:36px" value="{{ $p->name }}"></td><td style="width:140px"><input name="pf[{{ $p->id }}][rate]" class="inp numr" style="height:36px" value="{{ $p->rate }}"></td></tr>
    @endforeach
    </tbody></table>
    <div class="tw-f"><input name="new_name" class="inp" style="max-width:240px" placeholder="منصة جديدة"><input name="new_rate" class="inp numr" style="max-width:120px" placeholder="السعر"><button class="btn">حفظ</button></div>
</form>
@endif
@endsection
