<?php

namespace Database\Seeders;

use App\Models\whatsappMessage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class whatsappMessagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        whatsappMessage::insert([
            [
                'title' => 'رسالة اضافة طلب جديد',
                'type' => 'new',
                'message' =>
                '*رسالة إضافة الطلب*

اهلا عميلنا العزيز ({{$customer->name}})
رقم الكود الخاص بك: ({{$customer->code}})

تم إضافة الطلب الخاص بك في منظومة تتبع الطلبات
لأي استفسار يمكنك الدخول عبر الرابط

{{$app_url}}

*اسم المستخدم:* رقم هاتفك
*كلمة المرور:* رقم هاتفك

شكرآ لاختياركم شركتنا ونتمنى لكم تجربة ممتعة

الفاتورة
{{$app_url}}/order-invoice/{{$invoice_link}}'
            ],
            [
                'title' => 'رسالة تغيير حالة الطلب',
                'type' => 'change',
                'message' => '*رسالة تغيير حالة الطلب*

اهلا عميلنا العزيز ({{$order1->customer->name}})
رقم الكود الخاص بك: ({{$order1->customer->code}})
كود الطلب الخاص بك: ({{$order1->code}})
رقم الرحلة: ({{$order1->trip_details}})

تم وصول الطلب الخاص بك إلى
({{$order1->trip_status}})
منظومة تتبع الطلبات لأي إستفسار متوفر هنا

{{$app_url}}

شكرآ لإختياركم شركتنا ونتمنى لكم تجربة ممتعة '
            ],
            [
                'title' => 'رسالة الطلب جاهز للاستلام',
                'type' => 'ready',
                'message' => '*رسالة جاهز للاستلام*

اهلا عميلنا العزيز ({{$order1->customer->name}})
رقم الكود الخاص بك: ({{$order1->customer->code}})
كود الطلب الخاص بك: ({{$order1->code}})
رقم الرحلة: ({{$order1->trip_details}})

تم وصول الطلب الخاص بك إلى
({{$order1->trip_status}})
قيمة الشحن: ({{$shipPrice}})
الإجمالي على الطلب: ({{$total}}) ({{$currency}})
لمعرفة باقي تفاصيل طلبك متوفر هنا

{{$app_url}}

شكرآ لإختياركم شركتنا ونتمنى لكم تجربة ممتعة '
            ],
            [
                'title' => 'تسجيل ملاحظة جديدة للطلب',
                'type' => 'note',
                'message' => '*ملاحظة جديدة للطلب*

اهلا عميلنا العزيز ({{$order1->customer->name}})
رقم الكود الخاص بك: ({{$order1->customer->code}})
كود الطلب الخاص بك: ({{$order1->code}})
رقم الرحلة: ({{$order1->trip_details}})

تم تسجيل ملاحظة جديدة بخصوص طلبك
({{$order1->note}})
لمعرفة باقي تفاصيل طلبك متوفر هنا

{{$app_url}}

شكرآ لإختياركم شركتنا ونتمنى لكم تجربة ممتعة '
            ],

        ]);
    }
}
