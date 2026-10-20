<?php

namespace Database\Seeders;

use App\Models\Invoice;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Invoice::create([
            'welcome' => 'شكرا على طلبك, انه لمن دواعي سرورنا ان نعمل على توفير الطلب الخاص بكم.',
            'note' => 'ملاحظة هامة: تم دفع كامل القيمة بعد استشارة المذكور اعلاه',
            'note2' => 'تم إصدار هذه الفاتورة استناداً إلى طلبكم. نحن سعداء لخدمتكم.',
            'address' => 'العنوان / الرحبة شارع قصر عبد إسماعيل 0920703777 - 0920403444',
        ]);
        Invoice::create([
            'welcome' => 'شكرا على طلبك, انه لمن دواعي سرورنا ان نعمل على توفير الطلب الخاص بكم.',
            'note' => 'تم تسديد القيمة او البعض منها المتبقية على العميل وذلك وفق البنود المتفق عليها مسبقاً ',
            'note2' => 'تم إصدار هذه الفاتورة استناداً إلى طلبكم. نحن سعداء لخدمتكم.',
            'address' => 'العنوان / الرحبة شارع قصر عبد إسماعيل 0920703777 - 0920403444',
        ]);
    }
}
