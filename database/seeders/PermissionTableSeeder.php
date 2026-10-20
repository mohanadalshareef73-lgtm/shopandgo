<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class PermissionTableSeeder extends Seeder
{
    public function run()
    {
        DB::table('permissions')->insert([

            // Admins Permissions
            ['name' => 'role-list', 'guard_name' => 'web', 'ar_name' => 'عرض الصلاحيات', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'role-create', 'guard_name' => 'web', 'ar_name' => 'أنشاء صلاحية', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'role-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل صلاحية', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'role-delete', 'guard_name' => 'web', 'ar_name' => 'حذف صلاحية', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            ['name' => 'user-list', 'guard_name' => 'web', 'ar_name' => 'عرض المستخدمين', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'user-create', 'guard_name' => 'web', 'ar_name' => 'أنشاء المستخدمين', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'user-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل المستخدمين', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'user-delete', 'guard_name' => 'web', 'ar_name' => 'حذف المستخدمين', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            ['name' => 'system-list', 'guard_name' => 'web', 'ar_name' => 'عرض أعدادات النظام', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'system-create', 'guard_name' => 'web', 'ar_name' => 'أنشاء أعدادات النظام', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'system-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل أعدادات النظام', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'system-delete', 'guard_name' => 'web', 'ar_name' => 'حذف أعدادات النظام', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            ['name' => 'trip-list', 'guard_name' => 'web', 'ar_name' => 'عرض الرحلات', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'trip-create', 'guard_name' => 'web', 'ar_name' => 'اضافة دحلة', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'trip-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل رحلة', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'trip-delete', 'guard_name' => 'web', 'ar_name' => 'الغاء رحلة', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            ['name' => 'order-list', 'guard_name' => 'web', 'ar_name' => 'عرض الطلبات', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'order-create', 'guard_name' => 'web', 'ar_name' => 'اضافة طلب', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'order-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل طلب', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'order-delete', 'guard_name' => 'web', 'ar_name' => 'الغاء طلب', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            ['name' => 'notification-list', 'guard_name' => 'web', 'ar_name' => 'عرض التعميمات', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'notification-create', 'guard_name' => 'web', 'ar_name' => 'أنشاء تعميم', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'notification-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل تعميم', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'notification-delete', 'guard_name' => 'web', 'ar_name' => 'حذف تعميم', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            ['name' => 'platform-list', 'guard_name' => 'web', 'ar_name' => 'عرض المنصات', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'platform-create', 'guard_name' => 'web', 'ar_name' => 'أنشاء منصة', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'platform-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل منصة', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'platform-delete', 'guard_name' => 'web', 'ar_name' => 'حذف منصة', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            ['name' => 'customer-list', 'guard_name' => 'web', 'ar_name' => 'عرض الزبائن', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'customer-create', 'guard_name' => 'web', 'ar_name' => 'اضافة زبون', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'customer-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل وحذف الزبون', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'customer-delete', 'guard_name' => 'web', 'ar_name' => 'تسديد مستحقات الزبون', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            ['name' => 'invoice-list', 'guard_name' => 'web', 'ar_name' => 'عرض الفاتورة', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'invoice-create', 'guard_name' => 'web', 'ar_name' => 'انشاء فاتورة', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'invoice-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل فاتورة', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'invoice-delete', 'guard_name' => 'web', 'ar_name' => 'حذف فاتورة', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            ['name' => 'purchase-list', 'guard_name' => 'web', 'ar_name' => 'عرض المشتريات', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'purchase-create', 'guard_name' => 'web', 'ar_name' => 'انشاء المشتريات', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'purchase-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل المشتريات', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'purchase-delete', 'guard_name' => 'web', 'ar_name' => 'حذف المشتريات', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            ['name' => 'purchase_description-list', 'guard_name' => 'web', 'ar_name' => 'عرض وصف المشتريات', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'purchase_description-create', 'guard_name' => 'web', 'ar_name' => 'انشاء وصف للمشتريات', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'purchase_description-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل وصف المشتريات', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'purchase_description-delete', 'guard_name' => 'web', 'ar_name' => 'حذف وصف المشتريات', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            ['name' => 'shipping_type-list', 'guard_name' => 'web', 'ar_name' => 'عرض نوع الشحن', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'shipping_type-create', 'guard_name' => 'web', 'ar_name' => 'انشاء نوع الشحن', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'shipping_type-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل نوع الشحن', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'shipping_type-delete', 'guard_name' => 'web', 'ar_name' => 'حذف نوع الشحن', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            ['name' => 'shippingCharge-list', 'guard_name' => 'web', 'ar_name' => 'عرض مصروفات الشحن', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'shippingCharge-create', 'guard_name' => 'web', 'ar_name' => 'انشاء مصروفات الشحن', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'shippingCharge-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل مصروفات الشحن', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'shippingCharge-delete', 'guard_name' => 'web', 'ar_name' => 'حذف مصروفات الشحن', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            ['name' => 'paymentExpenses-list', 'guard_name' => 'web', 'ar_name' => 'عرض مصروفات الدفع', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'paymentExpenses-create', 'guard_name' => 'web', 'ar_name' => 'انشاء مصروفات الدفع', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'paymentExpenses-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل مصروفات الدفع', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'paymentExpenses-delete', 'guard_name' => 'web', 'ar_name' => 'حذف مصروفات الدفع', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            ['name' => 'treasury-list', 'guard_name' => 'web', 'ar_name' => 'عرض سجلات الخزينة', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'treasury-create', 'guard_name' => 'web', 'ar_name' => 'انشاء الخزينة', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'treasury-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل الخزينة', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'treasury-delete', 'guard_name' => 'web', 'ar_name' => 'حذف سجلات الخزينة', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            ['name' => 'dept-list', 'guard_name' => 'web', 'ar_name' => 'عرض الديون', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'dept-create', 'guard_name' => 'web', 'ar_name' => 'انشاء الديون', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'dept-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل الديون', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'dept-delete', 'guard_name' => 'web', 'ar_name' => 'حذف الديون', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            ['name' => 'status-list', 'guard_name' => 'web', 'ar_name' => 'عرض حالات الرحلة', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'status-create', 'guard_name' => 'web', 'ar_name' => 'انشاء حالة رحلة', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'status-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل حالة الرحلة', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'status-delete', 'guard_name' => 'web', 'ar_name' => 'حذف حالة الرحلة', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            ['name' => 'payment-methods-list', 'guard_name' => 'web', 'ar_name' => 'عرض طرق الدفع', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'payment-methods-create', 'guard_name' => 'web', 'ar_name' => 'انشاء طريقة دفع', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'payment-methods-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل طريقة دفع', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'payment-methods-delete', 'guard_name' => 'web', 'ar_name' => 'حذف طريقة دفع', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            ['name' => 'profits-list', 'guard_name' => 'web', 'ar_name' => 'عرض الأرباح', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'profits-create', 'guard_name' => 'web', 'ar_name' => 'انشاء أرباح', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'profits-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل أرباح', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'profits-delete', 'guard_name' => 'web', 'ar_name' => 'حذف أرباح', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],


            ['name' => 'aiRoles-list', 'guard_name' => 'web', 'ar_name' => 'عرض قواعد الذكاء الاصطناعي' ,'guard_name' => 'web', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'aiRoles-create', 'guard_name' => 'web', 'ar_name' => 'انشاء قواعد الذكاء الاصطناعي', 'guard_name' => 'web', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'aiRoles-edit', 'guard_name' => 'web', 'ar_name' => 'تعديل قواعد الذكاء الاصطناعي', 'guard_name' => 'web', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'aiRoles-delete', 'guard_name' => 'web', 'ar_name' => 'حذف قواعد الذكاء الاصطناعي', 'guard_name' => 'web', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            ['name' => 'reports-list', 'guard_name' => 'web',   'ar_name' => 'عرض التقارير', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            ['name' => 'log-list', 'guard_name' => 'web', 'ar_name' => 'عرض حركات النظام', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            // Users Permissions

        ]
    );

    }
}
