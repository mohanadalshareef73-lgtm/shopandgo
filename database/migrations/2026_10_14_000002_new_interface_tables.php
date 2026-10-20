<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الواجهة الجديدة: ملاحظات ورصيد الزبون، القطع الناقصة، مين سلّم، الورديات،
 * سجل رسائل الواتساب، شركات الشحن، إعدادات عامة، آخر ظهور للموظف، ترتيب حالات الرحلة.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'notes')) {
                $table->text('notes')->nullable();
            }
            if (!Schema::hasColumn('customers', 'credit')) {
                $table->double('credit')->default(0)->comment('رصيد للزبون عندنا (قطع ناقصة مثلا)');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'missing_qty')) {
                $table->integer('missing_qty')->nullable();
            }
            if (!Schema::hasColumn('orders', 'missing_value')) {
                $table->double('missing_value')->nullable();
            }
            if (!Schema::hasColumn('orders', 'missing_action')) {
                $table->string('missing_action', 20)->nullable()->comment('deduct / credit / refund');
            }
            if (!Schema::hasColumn('orders', 'delivered_by')) {
                $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'delivered_time')) {
                $table->dateTime('delivered_time')->nullable();
            }
        });

        Schema::table('purchases', function (Blueprint $table) {
            if (!Schema::hasColumn('purchases', 'attachment')) {
                $table->string('attachment')->nullable();
            }
        });

        Schema::table('purchase_descriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_descriptions', 'icon')) {
                $table->string('icon', 16)->nullable();
            }
            if (!Schema::hasColumn('purchase_descriptions', 'monthly_day')) {
                $table->unsignedTinyInteger('monthly_day')->nullable()->comment('مصروف ثابت كل شهر في اليوم هذا');
            }
        });

        if (!Schema::hasTable('shipping_companies')) {
            Schema::create('shipping_companies', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('route')->nullable();
                $table->double('price_per_kg')->nullable();
                $table->string('icon', 16)->nullable();
                $table->timestamps();
            });
        }
        Schema::table('shipping_charges', function (Blueprint $table) {
            if (!Schema::hasColumn('shipping_charges', 'company_id')) {
                $table->foreignId('company_id')->nullable()->constrained('shipping_companies')->nullOnDelete();
            }
            if (!Schema::hasColumn('shipping_charges', 'trip_purchase_id')) {
                $table->foreignId('trip_purchase_id')->nullable()->constrained('trip_purchases')->nullOnDelete();
            }
        });

        if (!Schema::hasTable('shifts')) {
            Schema::create('shifts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->dateTime('opened_at');
                $table->double('opening_cash')->default(0);
                $table->dateTime('closed_at')->nullable();
                $table->double('expected_cash')->nullable();
                $table->double('counted_cash')->nullable();
                $table->double('diff')->nullable();
                $table->text('note')->nullable();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('whatsapp_outbox')) {
            Schema::create('whatsapp_outbox', function (Blueprint $table) {
                $table->id();
                $table->string('type', 40);
                $table->string('phone', 30)->nullable();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 12)->default('sent');
                $table->text('body')->nullable();
                $table->string('error')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_messages', 'enabled')) {
                $table->boolean('enabled')->default(true);
            }
        });

        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'general')) {
                $table->text('general')->nullable()->comment('إعدادات الواجهة الجديدة JSON');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'last_seen_at')) {
                $table->dateTime('last_seen_at')->nullable();
            }
        });

        Schema::table('statuses', function (Blueprint $table) {
            if (!Schema::hasColumn('statuses', 'sort')) {
                $table->unsignedSmallInteger('sort')->default(0);
            }
            if (!Schema::hasColumn('statuses', 'stage')) {
                $table->string('stage', 10)->default('way')->comment('prep = قيد التجهيز, way = في الطريق, arrived = وصلت ليبيا');
            }
            if (!Schema::hasColumn('statuses', 'color')) {
                $table->string('color', 10)->nullable();
            }
        });
        foreach (DB::table('statuses')->orderBy('id')->get() as $i => $st) {
            DB::table('statuses')->where('id', $st->id)->update([
                'sort' => $i + 1,
                'stage' => (str_contains($st->name, 'تجهيز') || str_contains($st->name, 'شراء')) ? 'prep'
                    : ((str_contains($st->name, 'جاهز') || str_contains($st->name, 'ليبيا')) ? 'arrived' : 'way'),
            ]);
        }

        // قوالب رسائل جديدة (الخانات بالعربي: {الاسم} {الباقي} ...)
        $templates = [
            ['trip_added', 'دخل رحلة', "اهلا {الاسم} 👋\nطلبك رقم ({رقم_الإيصال}) دخل رحلة *{الرحلة}*\nالحالة: {الحالة}\n\nبنبلغك أول ما توصل بضاعتك."],
            ['trip_status', 'تغيرت حالة الرحلة', "اهلا {الاسم}\nطلبك رقم ({رقم_الإيصال}) في رحلة {الرحلة}\nالحالة توا: *{الحالة}*\n\n{رابط_الموقع}"],
            ['shelf_reminder', 'تذكير: بضاعتك في الرف', "اهلا {الاسم} 👋\nبضاعتك (طلب {رقم_الإيصال}) تستناك في المحل من {أيام_الرف} أيام.\nالباقي عليك: {الباقي} د.ل\n\nنستنوك من 10 الصبح لين 10 الليل 🙏"],
            ['delivered', 'تم الاستلام', "شكراً {الاسم} 🌷\nاستلمتي/استلمت طلبك رقم ({رقم_الإيصال}).\nالمدفوع اليوم: {المدفوع_الآن} د.ل\nالباقي: {الباقي} د.ل\n\nنتمنالك تجربة حلوة مع Shop & Go"],
            ['debt_reminder', 'تذكير دين', "اهلا {الاسم}\nحبينا نذكرك إن عليك {الباقي_الكلي} د.ل على طلباتك عندنا.\nتقدر تدفع كاش في المحل أو تحويل مصرفي.\nشكراً 🙏"],
            ['payment_received', 'استلمنا دفعتك', "اهلا {الاسم}\nاستلمنا منك {المدفوع_الآن} د.ل على طلب {رقم_الإيصال} ✅\nالباقي: {الباقي} د.ل"],
            ['shift_closed', 'تقفيل وردية (للمدير)', "تقفيل وردية {الموظف}\nالمفروض: {المفروض} د.ل\nسلّم: {المسلّم} د.ل\nالفرق: {الفرق} د.ل"],
            ['monthly_report', 'التقرير الشهري (للشركاء)', "التقرير الشهري — {الشهر}\nالطلبات: {الطلبات}\nصافي الربح: {صافي_الربح} د.ل\nالديون عند الزبائن: {الديون} د.ل\nالفائض اللي ما تقسمش: {الفائض} د.ل"],
        ];
        foreach ($templates as [$type, $title, $msg]) {
            if (!DB::table('whatsapp_messages')->where('type', $type)->exists()) {
                DB::table('whatsapp_messages')->insert(['type' => $type, 'title' => $title, 'message' => $msg, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        // رسالة "وصلت بضاعتك" بالخانات العربية الجديدة
        DB::table('whatsapp_messages')->where('type', 'arrived')->update([
            'message' => "*وصلت بضاعتك* ✅\n\nاهلا {الاسم}\nرقم الطلب: {رقم_الإيصال}\n\nوصلت {القطع_الواصلة} قطعة من {القطع_المطلوبة}\nعدد الطرود: {الطرود} · الوزن: {الوزن} كيلو\n\nقيمة الفاتورة: {الفاتورة} د.ل\nالشحن: {الشحن} د.ل\nالمدفوع: {المدفوع} د.ل\n*الباقي عليك: {الباقي} د.ل*\n\nتقدر تستلم من المحل، الدفع كاش أو تحويل مصرفي 🙏",
        ]);

        // صلاحيات جديدة
        if (Schema::hasTable('permissions')) {
            $perms = [
                ['delivery-list', 'عرض التسليم'], ['delivery-create', 'تسليم طلبية وقبض'],
                ['shift-close', 'تقفيل وردية موظف'], ['whatsapp-templates', 'تعديل رسائل الواتساب'],
            ];
            $hasAr = Schema::hasColumn('permissions', 'ar_name');
            foreach ($perms as [$name, $ar]) {
                if (!DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists()) {
                    $row = ['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()];
                    if ($hasAr) {
                        $row['ar_name'] = $ar;
                    }
                    $id = DB::table('permissions')->insertGetId($row);
                    $roleIds = DB::table('role_has_permissions')->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                        ->where('permissions.name', 'order-list')->pluck('role_has_permissions.role_id');
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
        Schema::dropIfExists('whatsapp_outbox');
        Schema::dropIfExists('shifts');
        foreach (['company_id', 'trip_purchase_id'] as $fk) {
            if (Schema::hasColumn('shipping_charges', $fk)) {
                Schema::table('shipping_charges', function (Blueprint $table) use ($fk) {
                    if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
                        $table->dropForeign([$fk]);
                    }
                    $table->dropColumn($fk);
                });
            }
        }
        Schema::dropIfExists('shipping_companies');
        Schema::enableForeignKeyConstraints();
    }
};
