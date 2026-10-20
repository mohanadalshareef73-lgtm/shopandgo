<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الدورة المالية:
 * - شراء دولار/USDT وتعبئة الفيزا (currency_exchanges)
 * - رحلات الشراء: عدة إيصالات تنشرى مع بعض، سعر صرف ومبلغ بالدولار، وربح الرحلة (trip_purchases)
 * - استلام البضاعة (أعمدة على orders)
 * - الشركاء: سحوبات يومية وتوزيع فائض (partners / partner_transactions)
 * - دفع شركة الشحن حسب الوزن وعدد الطرود (أعمدة على shipping_charges)
 */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('currency_exchanges')) {
            Schema::create('currency_exchanges', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->string('from_currency', 20)->default('dinar');
                $table->foreignId('from_payment_id')->constrained('payment_methods');
                $table->double('from_amount');
                $table->string('to_currency', 20)->default('dollar');
                $table->foreignId('to_payment_id')->constrained('payment_methods');
                $table->double('to_amount');
                $table->double('rate')->comment('كم دينار مقابل 1 دولار');
                $table->string('kind', 30)->default('usdt')->comment('usdt / cash / visa_load');
                $table->text('note')->nullable();
                $table->foreignId('created_id')->constrained('users');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('partners')) {
            Schema::create('partners', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('phone')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('status')->default(true);
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('partner_transactions')) {
            Schema::create('partner_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
                $table->string('type', 20)->comment('withdrawal = سحب يومي, distribution = توزيع فائض, deposit = إيداع من الشريك');
                $table->double('amount');
                $table->string('currency', 20)->default('dinar');
                $table->foreignId('payment_id')->constrained('payment_methods');
                $table->date('date');
                $table->text('note')->nullable();
                $table->foreignId('created_id')->constrained('users');
                $table->timestamps();
            });
        }

        // رحلة شراء: مجموعة إيصالات تنشرى مع بعض تحت رقم رحلة واحد (orders.trip_details)
        if (!Schema::hasTable('trip_purchases')) {
            Schema::create('trip_purchases', function (Blueprint $table) {
                $table->id();
                $table->string('trip_code')->unique()->comment('نفس رقم الرحلة في orders.trip_details');
                $table->date('purchased_at');
                $table->double('cost')->comment('المدفوع للموقع');
                $table->string('cost_currency', 20)->default('dollar');
                $table->foreignId('payment_id')->constrained('payment_methods')->comment('دفعنا منين (الفيزا)');
                $table->double('rate')->comment('سعر الصرف للرحلة: كم دينار مقابل 1 دولار');
                $table->boolean('coupon_used')->default(false);
                $table->text('note')->nullable();
                $table->foreignId('created_id')->constrained('users');
                $table->timestamps();
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            // استلام البضاعة
            if (!Schema::hasColumn('orders', 'received_qty')) {
                $table->integer('received_qty')->nullable()->comment('عدد القطع اللي وصلت');
            }
            if (!Schema::hasColumn('orders', 'package_type')) {
                $table->string('package_type')->nullable()->comment('نوع الطرد');
            }
            if (!Schema::hasColumn('orders', 'received_at')) {
                $table->dateTime('received_at')->nullable();
            }
            if (!Schema::hasColumn('orders', 'received_by')) {
                $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('shipping_charges', function (Blueprint $table) {
            if (!Schema::hasColumn('shipping_charges', 'parcels_count')) {
                $table->integer('parcels_count')->nullable()->comment('عدد الطرود');
            }
            if (!Schema::hasColumn('shipping_charges', 'trip_id')) {
                $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            }
            if (!Schema::hasColumn('shipping_charges', 'company')) {
                $table->string('company')->nullable()->comment('شركة الشحن');
            }
        });

        // نوع الحركة في سجل الخزينة كان enum محدود، نخليه نص حر باش نقدرو نزيدو أنواع جديدة
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE treasury_histories MODIFY `type` VARCHAR(40) NOT NULL COMMENT 'نوع الاجراء بالتفصيل'");
            DB::statement("ALTER TABLE treasury_histories MODIFY `status` VARCHAR(20) NOT NULL COMMENT 'حالة الاجراء'");
        }
        if (!Schema::hasColumn('treasury_histories', 'note')) {
            Schema::table('treasury_histories', function (Blueprint $table) {
                $table->string('note')->nullable();
            });
        }

        // قالب رسالة وصول البضاعة
        if (Schema::hasTable('whatsapp_messages') && !DB::table('whatsapp_messages')->where('type', 'arrived')->exists()) {
            DB::table('whatsapp_messages')->insert([
                'title' => 'رسالة وصول البضاعة (الاستلام)',
                'type' => 'arrived',
                'message' => "*وصلت بضاعتك* ✅\n\nاهلا عميلنا العزيز ({{\$name}})\nكود الزبون: ({{\$customer_code}})\nرقم الطلب: ({{\$order_code}})\n\nعدد القطع اللي وصلت: {{\$pieces}}\nعدد الطرود: {{\$parcels}}\nالوزن: {{\$weight}} كيلو\n\nقيمة الفاتورة: {{\$total}} {{\$currency}}\nقيمة الشحن: {{\$ship_price}} {{\$currency}}\nالإجمالي مع الشحن: {{\$grand_total}} {{\$currency}}\nالمدفوع: {{\$paid}} {{\$currency}}\n*المتبقي عليك: {{\$remaining}} {{\$currency}}*\n\nتقدر تستلم من المكتب، الدفع كاش أو تحويل مصرفي.\n\n{{\$app_url}}\n\nشكرآ لإختياركم شركتنا",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // صلاحيات الشاشات الجديدة
        if (Schema::hasTable('permissions')) {
            $perms = [
                ['finance-dashboard', 'عرض لوحة المالية'],
                ['exchange-list', 'عرض شراء الدولار'],
                ['exchange-create', 'تسجيل شراء دولار'],
                ['exchange-delete', 'حذف شراء دولار'],
                ['trip-purchase-list', 'عرض رحلات الشراء وأرباحها'],
            ['trip-purchase-create', 'تسجيل وتعديل رحلة شراء'],
            ['trip-purchase-delete', 'حذف رحلة شراء'],
                ['order-receive', 'استلام البضاعة'],
                ['partner-list', 'عرض الشركاء'],
                ['partner-create', 'اضافة شريك وسحوبات'],
                ['partner-delete', 'حذف حركات الشركاء'],
            ];
            $hasAr = Schema::hasColumn('permissions', 'ar_name');
            foreach ($perms as [$name, $ar]) {
                if (!DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists()) {
                    $row = ['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()];
                    if ($hasAr) {
                        $row['ar_name'] = $ar;
                    }
                    $id = DB::table('permissions')->insertGetId($row);
                    // نعطيها لأي دور عنده صلاحية الخزينة (المدير)
                    $roleIds = DB::table('role_has_permissions')
                        ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                        ->where('permissions.name', 'treasury-list')
                        ->pluck('role_has_permissions.role_id');
                    foreach ($roleIds as $roleId) {
                        DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
                    }
                }
            }
            app()['cache']->forget('spatie.permission.cache');
        }
    }

    public function down()
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('trip_purchases');
        Schema::dropIfExists('partner_transactions');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('currency_exchanges');
        Schema::table('orders', function (Blueprint $table) {
            foreach (['received_by'] as $fk) {
                if (Schema::hasColumn('orders', $fk)) {
                    $table->dropConstrainedForeignId($fk);
                }
            }
            foreach (['received_qty', 'package_type', 'received_at'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('shipping_charges', function (Blueprint $table) {
            if (Schema::hasColumn('shipping_charges', 'trip_id')) {
                $table->dropConstrainedForeignId('trip_id');
            }
            foreach (['parcels_count', 'company'] as $col) {
                if (Schema::hasColumn('shipping_charges', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        DB::table('whatsapp_messages')->where('type', 'arrived')->delete();
        Schema::enableForeignKeyConstraints();
    }
};
