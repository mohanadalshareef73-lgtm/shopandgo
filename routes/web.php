<?php

use App\Exports\CustomerExport;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerAuthController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes([
    'register' => false, // Registration Routes...
    'reset' => false, // Password Reset Routes...
    'verify' => false, // Email Verification Routes...
  ]);

  Route::fallback(function () {
    return redirect()->route('home');
});

// (أُزيل /test2: كان يمنح صلاحيات المدير بدون حماية)

Route::get('order-invoice/{orderCode}', [App\Http\Controllers\OrderInvoiceController::class, 'print'])->middleware('throttle:invoice')->name('order.invoice');


// Customer login (/login)
Route::get('/login', [CustomerAuthController::class, 'showLoginForm'])->name('customer.login');
Route::post('/login', [CustomerAuthController::class, 'login'])->middleware('throttle:login')->name('customer.login.submit');
// تسجيل عميل جديد + رمز التحقق عبر واتساب
Route::get('/register', [App\Http\Controllers\Portal\SignupController::class, 'form'])->name('customer.register');
Route::post('/register', [App\Http\Controllers\Portal\SignupController::class, 'store'])->middleware('throttle:signup')->name('customer.register.store');
Route::get('/verify', [App\Http\Controllers\Portal\SignupController::class, 'verifyForm'])->name('customer.verify');
Route::get('/forgot', [App\Http\Controllers\Portal\SignupController::class, 'forgotForm'])->name('customer.forgot');
Route::post('/forgot', [App\Http\Controllers\Portal\SignupController::class, 'forgot'])->middleware('throttle:otp')->name('customer.forgot.send');
Route::post('/verify', [App\Http\Controllers\Portal\SignupController::class, 'verify'])->middleware('throttle:otp')->name('customer.verify.submit');
Route::post('/verify/resend', [App\Http\Controllers\Portal\SignupController::class, 'resend'])->middleware('throttle:otp')->name('customer.verify.resend');
Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('customer.logout');
Route::get('/sw.js', [App\Http\Controllers\Portal\PushController::class, 'sw'])->name('portal.sw');
Route::get('/push/latest', [App\Http\Controllers\Portal\PushController::class, 'latestAny'])->name('push.latest');

// تحديث النظام من المتصفح (للمدير فقط) — يعمل حتى قبل تشغيل الترحيلات
Route::get('system-update', [App\Http\Controllers\SystemUpdateController::class, 'index'])->name('system.update');
Route::post('system-update', [App\Http\Controllers\SystemUpdateController::class, 'run'])->name('system.update.run');
Route::get('system-update/backup/{file}', [App\Http\Controllers\SystemUpdateController::class, 'download'])->name('system.update.download')->where('file', '[A-Za-z0-9_.-]+');

// Admin login (/admin/login)
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [App\Http\Controllers\Auth\AdminLoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [App\Http\Controllers\Auth\AdminLoginController::class, 'login'])->middleware('throttle:login')->name('login');
    Route::post('/logout', [App\Http\Controllers\Auth\AdminLoginController::class, 'logout'])->name('logout');
});


// تصدير العملاء ومسح الكاش: للإدارة فقط (كانا متاحين لأي شخص)
Route::get('/export-customers', function () {
    return Excel::download(new CustomerExport, 'customers.xlsx');
})->middleware(['auth', 'isAdmin', 'permission:customer-list'])->name('export.customers');


Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
// 🩺 آخر خطأ في المنظومة (للتشخيص — المدير بس)
Route::get('last-error', function () {
    $f = storage_path('logs/laravel.log');
    if (!is_file($f)) {
        foreach (glob(storage_path('logs/laravel-*.log')) ?: [] as $g) {
            $f = $g;
        }
    }
    $txt = is_file($f) ? (string) file_get_contents($f, false, null, max(0, filesize($f) - 400000)) : '';
    preg_match_all('/^\[(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d)\] \w+\.ERROR: (.+)$/m', $txt, $m, PREG_SET_ORDER);
    $last = array_slice(array_reverse($m), 0, 5);
    $out = '<body style="font-family:monospace;direction:ltr;padding:16px;white-space:pre-wrap;font-size:14px">';
    foreach ($last as $x) {
        $out .= '<div style="border-bottom:1px solid #ccc;padding:10px 0"><b>' . e($x[1]) . '</b>' . "\n" . e(mb_substr($x[2], 0, 900)) . '</div>';
    }

    return response($out ?: 'no errors');
})->middleware(['auth', 'isAdmin', 'permission:system-edit'])->name('last.error');
Route::get('clearCacheAndCookies/', [App\Http\Controllers\SettingController::class, 'clearCacheAndCookies'])->middleware(['auth', 'isAdmin', 'permission:system-edit'])->name('clearCacheAndCookies');


Route::group(['middleware' => ['auth', 'isAdmin']], function () {

    // الرئيسية الجديدة (التقرير). الرئيسية القديمة على /admin/old
    Route::get('/admin', [App\Http\Controllers\Sg\DashboardController::class, 'index'])->middleware(\App\Http\Middleware\RememberPeriod::class)->name('admin.home');
    Route::get('/admin/old', [App\Http\Controllers\HomeController::class, 'admin'])->name('admin.home.old');

    Route::get('/settings', [App\Http\Controllers\SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [App\Http\Controllers\SettingController::class, 'update'])->name('settings.update');
    Route::get('/settings/reply-images/{key}', [App\Http\Controllers\SettingController::class, 'replyImage'])->name('settings.reply-image');
    Route::get('/settings/whatsapp', [App\Http\Controllers\WhatsAppSettingController::class, 'edit'])->name('settings.whatsapp.edit');
    Route::put('/settings/whatsapp', [App\Http\Controllers\WhatsAppSettingController::class, 'update'])->name('settings.whatsapp.update');

    Route::get('/carts/wallet', [App\Http\Controllers\CartController::class, 'wallet'])->name('carts.wallet');
    Route::get('/carts/{cart}/quotation.pdf', [App\Http\Controllers\CartController::class, 'quotation'])->name('carts.quotation');
    Route::get('/carts/{cart}/products/{index}/image', [App\Http\Controllers\CartController::class, 'productImage'])->whereNumber('index')->name('carts.products.image');
    Route::put('/carts/{cart}/pdf-settings', [App\Http\Controllers\CartController::class, 'updatePdf'])->name('carts.pdf.update');
    Route::delete('/carts/{cart}/pdf-settings', [App\Http\Controllers\CartController::class, 'resetPdf'])->name('carts.pdf.reset');
    Route::resource('carts', App\Http\Controllers\CartController::class)->only(['index', 'create', 'store', 'show', 'update']);

    Route::resource('ai-roles', App\Http\Controllers\AiRolesController::class);
    Route::get('ai-roles-search/', [App\Http\Controllers\AiRolesController::class, 'search'])->name('ai-roles.search');


    Route::resource('customer', App\Http\Controllers\CustomerController::class);
    Route::put('customer-pay/{id}', [App\Http\Controllers\CustomerController::class, 'pay'])->name('customer.pay');
    Route::get('customer-paid-history/{id}', [App\Http\Controllers\CustomerController::class, 'paidhistory'])->name('customer.paidhistory');
    Route::get('customer-search/', [App\Http\Controllers\CustomerController::class, 'search'])->name('customer.search');
    Route::DELETE('customer_delete/{id}', [App\Http\Controllers\CustomerController::class, 'destroy2'])->name('customer_delete.destroy');
    Route::get('customer-print', [App\Http\Controllers\CustomerController::class, 'print'])->name('customer.print');
    Route::get('customer-depts-print', [App\Http\Controllers\CustomerController::class, 'print_Depts'])->name('customer.print.depts');
    Route::get('customer-noDepts-print', [App\Http\Controllers\CustomerController::class, 'print_No_Depts'])->name('customer.print.noDepts');

    Route::get('paid-print/{id}', [App\Http\Controllers\CustomerController::class, 'paidPrint'])->name('customer.paid.print');

    Route::resource('trip', App\Http\Controllers\TripController::class);
    Route::get('trip-onway/', [App\Http\Controllers\TripController::class, 'onway'])->name('trip.onway.index');
    Route::get('trip-delay/', [App\Http\Controllers\TripController::class, 'delay'])->name('trip.delay.index');
    Route::get('trip-arrived/', [App\Http\Controllers\TripController::class, 'arrived'])->name('trip.arrived.index');
    Route::get('trip-arrived-now/{id}', [App\Http\Controllers\TripController::class, 'tripArrived'])->name('trip.arrived');
    Route::get('trip-download-file/{id}', [App\Http\Controllers\TripController::class, 'download2'])->name('trip.download');
    Route::get('trip-search/', [App\Http\Controllers\TripController::class, 'search'])->name('trip.search');

    Route::post('trip-update/', [App\Http\Controllers\TripController::class, 'updateTrip'])->name('trip.update');

    Route::resource('order', App\Http\Controllers\OrderController::class);
    Route::get('order-ready/', [App\Http\Controllers\OrderController::class, 'ready'])->name('order.ready.index');
    Route::get('order-unpaid/', [App\Http\Controllers\OrderController::class, 'unpaid'])->name('order.unpaid.index');
    Route::post('order-store-recipt/', [App\Http\Controllers\OrderController::class, 'store2'])->name('order.store2');

    Route::get('order-paid-history/{id}', [App\Http\Controllers\OrderController::class, 'orderPaidsHistory'])->name('order.paids.history');


    Route::get('order-delivered-index/', [App\Http\Controllers\OrderController::class, 'getDelivered'])->name('order.delivered.index');
    Route::get('order-notDelivered-index/', [App\Http\Controllers\OrderController::class, 'notDelivered'])->name('order.notDelivered.index');

    Route::get('order-no_trip/', [App\Http\Controllers\OrderController::class, 'no_trip'])->name('order.no_trip.index');
    Route::get('order-search/', [App\Http\Controllers\OrderController::class, 'search'])->name('order.search');
    Route::post('order-pay-rest/{id}', [App\Http\Controllers\OrderController::class, 'payRest'])->name('order.payRest');
    Route::post('order-delivered/{id}', [App\Http\Controllers\OrderController::class, 'delivered'])->name('order.delivered');
    Route::post('order-arrived/{id}', [App\Http\Controllers\OrderController::class, 'arrived'])->name('order.arrived');
    Route::get('order-print/{id}', [App\Http\Controllers\OrderController::class, 'print'])->name('order.print');
    Route::DELETE('order_delete/{id}', [App\Http\Controllers\OrderController::class, 'destroy2'])->name('order_delete.destroy');
    Route::get('order-ready-print', [App\Http\Controllers\OrderController::class, 'ready_print'])->name('order.ready.print');
    Route::get('order-print-date/', [App\Http\Controllers\OrderController::class, 'printDate'])->name('order.print.date');
    Route::post('order-change-note/{id}', [App\Http\Controllers\OrderController::class, 'changeNote'])->name('order.changeNote');

    Route::DELETE('order-delete-note/{id}', [App\Http\Controllers\OrderController::class, 'deleteNote'])->name('order.notes.destroy');

    Route::post('order-trip-details/{id}', [App\Http\Controllers\OrderController::class, 'trip_details'])->name('order.trip_details');

    Route::resource('purchase', App\Http\Controllers\PurchaseController::class);
    Route::get('purchase-search/', [App\Http\Controllers\PurchaseController::class, 'search'])->name('purchase.search');
    Route::resource('purchaseDesc', App\Http\Controllers\PurchaseDescriptionController::class);
    Route::get('purchaseDesc-search/', [App\Http\Controllers\PurchaseDescriptionController::class, 'search'])->name('purchaseDesc.search');
    Route::get('purchase-print/', [App\Http\Controllers\PurchaseController::class, 'print'])->name('purchase.print');

    Route::get('purchase-report/', [App\Http\Controllers\PurchaseController::class, 'report'])->name('purchase.report');

    Route::resource('shippingCharges', App\Http\Controllers\ShippingChargesController::class);
    Route::get('shippingCharges-search/', [App\Http\Controllers\ShippingChargesController::class, 'search'])->name('shippingCharges.search');
    Route::resource('shippingType', App\Http\Controllers\ShippingTypeController::class);
    Route::get('shippingType-search/', [App\Http\Controllers\ShippingTypeController::class, 'search'])->name('shippingType.search');
    Route::get('shippingCharges-print/', [App\Http\Controllers\ShippingChargesController::class, 'print'])->name('shippingCharges.print');

    Route::resource('paymentExpenses', App\Http\Controllers\paymentExpensesController::class);
    Route::get('paymentExpenses-search/', [App\Http\Controllers\paymentExpensesController::class, 'search'])->name('paymentExpenses.search');
    Route::post('paymentExpenses-pay/{id}', [App\Http\Controllers\paymentExpensesController::class, 'pay'])->name('paymentExpenses.pay');
    Route::get('paymentExpenses-print/', [App\Http\Controllers\paymentExpensesController::class, 'print'])->name('paymentExpenses.print');

    Route::resource('platform', App\Http\Controllers\PlatformController::class);
    Route::get('platform-search/', [App\Http\Controllers\PlatformController::class, 'search'])->name('platform.search');

    Route::resource('status', App\Http\Controllers\StatusController::class);
    Route::get('status-search/', [App\Http\Controllers\StatusController::class, 'search'])->name('status.search');

    Route::resource('whatsapp-message', App\Http\Controllers\WhatsappMessageController::class);


    Route::resource('safe', App\Http\Controllers\TreasuryController::class);
    Route::get('safe-history/{currency}', [App\Http\Controllers\TreasuryController::class, 'showSafe'])->name('safe.history');
    Route::get('safe-search/', [App\Http\Controllers\TreasuryController::class, 'search'])->name('safe.search');

    Route::resource('dept', App\Http\Controllers\DeptController::class);
    Route::get('dept-paid/', [App\Http\Controllers\DeptController::class, 'paidDepts'])->name('dept.paidDept');
    Route::get('dept-unpaid/', [App\Http\Controllers\DeptController::class, 'unpaidDepts'])->name('dept.unpaidDepts');
    Route::get('dept-paid-history/{id}', [App\Http\Controllers\DeptController::class, 'paidhistory'])->name('dept.paidhistory');
    Route::get('dept-search/', [App\Http\Controllers\DeptController::class, 'search'])->name('dept.search');
    Route::DELETE('dept-delete/{id}', [App\Http\Controllers\DeptController::class, 'destroy2'])->name('dept_delete.destroy');
    Route::get('dept-paid-print/{id}', [App\Http\Controllers\DeptController::class, 'paidPrint'])->name('dept.paid.print');
    Route::post('dept-pay-rest/{id}', [App\Http\Controllers\DeptController::class, 'payRest'])->name('dept.payRest');
    Route::get('dept-print/', [App\Http\Controllers\DeptController::class, 'print'])->name('dept.print');

    Route::resource('invoice', App\Http\Controllers\InvoiceController::class);

    Route::resource('roles', App\Http\Controllers\RoleController::class);
    Route::post('active-system', [App\Http\Controllers\HomeController::class, 'create'])->name('system.active');
    Route::resource('users', App\Http\Controllers\UserController::class);
    Route::resource('notification', App\Http\Controllers\NotificationController::class);
    Route::resource('log', App\Http\Controllers\LogController::class);


    Route::Resource('profits', App\Http\Controllers\ProfitController::class);
    Route::get('profits-search', [App\Http\Controllers\ProfitController::class, 'search'])->name('profits.search');

    Route::Resource('payment-methods', App\Http\Controllers\PaymentMethodController::class);
    Route::get('payment-methods-search', [App\Http\Controllers\PaymentMethodController::class, 'search'])->name('payment-methods.search');


    Route::Resource('reports', App\Http\Controllers\ReportController::class);

    // ===================== المالية =====================
    Route::get('finance', [App\Http\Controllers\FinanceController::class, 'index'])->name('finance.index');

    Route::get('finance/exchanges', [App\Http\Controllers\CurrencyExchangeController::class, 'index'])->name('finance.exchanges.index');
    Route::post('finance/exchanges', [App\Http\Controllers\CurrencyExchangeController::class, 'store'])->name('finance.exchanges.store');
    Route::delete('finance/exchanges/{exchange}', [App\Http\Controllers\CurrencyExchangeController::class, 'destroy'])->name('finance.exchanges.destroy');

    Route::get('finance/partners', [App\Http\Controllers\PartnerController::class, 'index'])->name('finance.partners.index');
    Route::post('finance/partners', [App\Http\Controllers\PartnerController::class, 'store'])->name('finance.partners.store');
    Route::put('finance/partners/{partner}', [App\Http\Controllers\PartnerController::class, 'update'])->name('finance.partners.update');
    Route::post('finance/partners/transactions', [App\Http\Controllers\PartnerController::class, 'storeTransaction'])->name('finance.partners.transactions.store');
    Route::post('finance/partners/distribute', [App\Http\Controllers\PartnerController::class, 'distribute'])->name('finance.partners.distribute');
    Route::post('finance/partners/surplus-settings', [App\Http\Controllers\PartnerController::class, 'surplusSettings'])->name('finance.partners.surplus');
    Route::delete('finance/partners/transactions/{transaction}', [App\Http\Controllers\PartnerController::class, 'destroyTransaction'])->name('finance.partners.transactions.destroy');

    Route::get('finance/trips', [App\Http\Controllers\TripPurchaseController::class, 'index'])->name('finance.trips.index');
    Route::get('finance/trips/create', [App\Http\Controllers\TripPurchaseController::class, 'create'])->name('finance.trips.create');
    Route::post('finance/trips', [App\Http\Controllers\TripPurchaseController::class, 'store'])->name('finance.trips.store');
    Route::get('finance/trips/{trip}', [App\Http\Controllers\TripPurchaseController::class, 'show'])->name('finance.trips.show');
    Route::put('finance/trips/{trip}', [App\Http\Controllers\TripPurchaseController::class, 'update'])->name('finance.trips.update');
    Route::delete('finance/trips/{trip}', [App\Http\Controllers\TripPurchaseController::class, 'destroy'])->name('finance.trips.destroy');
    Route::post('finance/trips-attach', [App\Http\Controllers\TripPurchaseController::class, 'attachTo'])->name('finance.trips.attachTo');
    Route::post('finance/trips-no-trip', [App\Http\Controllers\TripPurchaseController::class, 'noTrip'])->name('finance.trips.noTrip');
    Route::post('finance/trips/{trip}/orders', [App\Http\Controllers\TripPurchaseController::class, 'attach'])->name('finance.trips.attach');
    Route::delete('finance/trips/{trip}/orders/{order}', [App\Http\Controllers\TripPurchaseController::class, 'detach'])->name('finance.trips.detach');


    // ===================== الواجهة الجديدة (Shop & Go) =====================
    Route::prefix('app')->name('sg.')->middleware(\App\Http\Middleware\RememberPeriod::class)->group(function () {
        $C = 'App\Http\Controllers\Sg\\';

        Route::get('orders', [$C . 'OrdersController', 'index'])->name('orders.index');
        Route::get('orders/suggest', [$C . 'OrdersController', 'suggest'])->name('orders.suggest');
        Route::post('orders/bulk', [$C . 'OrdersController', 'bulk'])->name('orders.bulk');
        Route::get('orders/create', [$C . 'OrdersController', 'create'])->name('orders.create');
        Route::get('site-pay', [$C . 'SitePayController', 'index'])->name('sitepay');
        Route::post('site-pay', [$C . 'SitePayController', 'store'])->name('sitepay.store');
        Route::post('site-pay/sites', [$C . 'SitePayController', 'sites'])->name('sitepay.sites');
        Route::post('site-pay/{order}/cost', [$C . 'SitePayController', 'cost'])->name('sitepay.cost');
        Route::post('site-pay/{order}/profit', [$C . 'SitePayController', 'profit'])->name('sitepay.profit');
        Route::get('orders/{order}/edit', [$C . 'OrdersController', 'edit'])->name('orders.edit');
        Route::delete('orders/{order}', [$C . 'OrdersController', 'destroy'])->name('orders.destroy');
        Route::get('orders/lookup', [$C . 'OrdersController', 'lookup'])->name('orders.lookup');
        Route::post('orders', [$C . 'OrdersController', 'store'])->name('orders.store');
        Route::get('orders/{order}', [$C . 'OrdersController', 'show'])->name('orders.show');
        Route::post('orders/{order}/pay', [$C . 'OrdersController', 'pay'])->name('orders.pay');
        Route::post('orders/{order}/pays/{paid}', [$C . 'OrdersController', 'payEdit'])->name('orders.pay.edit');
        Route::post('orders/{order}/items-receive', [$C . 'ItemReceiveController', 'store'])->name('orders.items.receive');
        Route::post('orders/{order}/send-receipt', [$C . 'ItemReceiveController', 'sendReceipt'])->name('orders.send.receipt');
        Route::post('orders/{order}/deposit-courier', [$C . 'OrdersController', 'depositCourier'])->name('orders.depcourier');
        Route::post('orders/{order}/missing', [$C . 'OrdersController', 'missing'])->name('orders.missing');
        Route::post('orders/{order}/note', [$C . 'OrdersController', 'note'])->name('orders.note');
        Route::post('orders/{order}/cancel', [$C . 'OrdersController', 'cancel'])->name('orders.cancel');
        Route::post('orders/{order}/no-trip', [$C . 'OrdersController', 'noTrip'])->name('orders.notrip');

        Route::get('trips', [App\Http\Controllers\TripPurchaseController::class, 'index'])->name('trips.index');
        Route::get('trips/{trip}/receive', [App\Http\Controllers\TripPurchaseController::class, 'receiveForm'])->name('trips.receive');
        Route::post('trips/{trip}/receive', [App\Http\Controllers\TripPurchaseController::class, 'receiveStore'])->name('trips.receive.store');
        Route::get('trips/{trip}/notify', [App\Http\Controllers\TripPurchaseController::class, 'notifyForm'])->name('trips.notify');
        Route::post('trips/{trip}/notify', [App\Http\Controllers\TripPurchaseController::class, 'notifySend'])->name('trips.notify.send');
        Route::get('trips-bulk/preview', [App\Http\Controllers\TripPurchaseController::class, 'bulkPreview'])->name('trips.bulk.preview');
        Route::post('trips-bulk/status', [App\Http\Controllers\TripPurchaseController::class, 'bulkStatus'])->name('trips.bulk.status');
        Route::post('trips/{trip}/status', [App\Http\Controllers\TripPurchaseController::class, 'status'])->name('trips.status');
        Route::post('trips/import-legacy', [App\Http\Controllers\TripPurchaseController::class, 'importLegacy'])->name('trips.import-legacy');
        Route::post('trips/{trip}/to-legacy', [App\Http\Controllers\TripPurchaseController::class, 'toLegacy'])->name('trips.to-legacy');

        Route::get('delivery', [$C . 'DeliveryController', 'index'])->name('delivery');
        Route::post('delivery', [$C . 'DeliveryController', 'store'])->name('delivery.store');
        Route::get('delivery/slip', [$C . 'DeliveryController', 'slip'])->name('delivery.slip');
        Route::get('delivery/done', [$C . 'DeliveryController', 'done'])->name('delivery.done');
        Route::get('delivery/suggest', [$C . 'DeliveryController', 'suggest'])->name('delivery.suggest');
        Route::match(['get', 'post'], 'delivery/audit', [$C . 'DeliveryController', 'audit'])->name('delivery.audit');
        Route::post('delivery/{order}/undo', [$C . 'DeliveryController', 'undo'])->name('delivery.undo');
        Route::get('delivery/old', [$C . 'OldDeliveriesController', 'index'])->name('delivery.old');
        Route::post('delivery/old', [$C . 'OldDeliveriesController', 'store'])->name('delivery.old.store');
        Route::get('delivery/old-no-trip', [$C . 'OldDeliveriesController', 'noTripIndex'])->name('delivery.old.notrip');
        Route::post('delivery/old-no-trip', [$C . 'OldDeliveriesController', 'noTripStore'])->name('delivery.old.notrip.store');
        Route::post('delivery/remind', [$C . 'DeliveryController', 'remindShelf'])->name('delivery.remind');

        Route::get('dispatch', [$C . 'DispatchController', 'index'])->name('dispatch');
        Route::post('dispatch', [$C . 'DispatchController', 'assign'])->name('dispatch.assign');
        Route::get('dispatch/pdf', [$C . 'DispatchController', 'pdf'])->name('dispatch.pdf');
        Route::post('dispatch/bulk', [$C . 'DispatchController', 'bulk'])->name('dispatch.bulk');
        Route::get('dispatch/{delivery}/receipt', [$C . 'DispatchController', 'receipt'])->name('dispatch.receipt');
        Route::post('dispatch/{delivery}', [$C . 'DispatchController', 'act'])->name('dispatch.act');
        Route::get('couriers', [$C . 'CouriersController', 'index'])->name('couriers');
        Route::post('couriers', [$C . 'CouriersController', 'store'])->name('couriers.store');
        Route::put('couriers/{courier}', [$C . 'CouriersController', 'update'])->name('couriers.update');
        Route::post('couriers/{courier}/settle', [$C . 'CouriersController', 'settle'])->name('couriers.settle');
        Route::put('deliveries/{delivery}', [$C . 'CouriersController', 'updateDelivery'])->name('couriers.delivery.update');
        Route::post('deliveries/{delivery}/action', [$C . 'CouriersController', 'action'])->name('couriers.action');
        Route::get('deliveries/{delivery}/label', [$C . 'CouriersController', 'label'])->name('couriers.label');
        Route::get('alerts', [$C . 'CouriersController', 'alerts'])->name('alerts');
        Route::get('alerts-center', [$C . 'AlertsController', 'index'])->name('alerts.center');
        Route::post('alerts-center/read', [$C . 'AlertsController', 'read'])->name('alerts.center.read');
        Route::post('alerts/read', [$C . 'CouriersController', 'alertsRead'])->name('alerts.read');
        Route::delete('alerts-center/{alert}', [$C . 'AlertsController', 'destroy'])->name('alerts.destroy');
        Route::post('alerts-center/bulk', [$C . 'AlertsController', 'bulk'])->name('alerts.bulk');
        Route::post('alerts-center/clear', [$C . 'AlertsController', 'clear'])->name('alerts.clear');
        Route::get('alerts-center/{alert}/open', [$C . 'AlertsController', 'open'])->name('alerts.open');
        Route::post('alerts-center/whatsapp', [$C . 'AlertsController', 'waSave'])->name('alerts.wa');
        Route::post('alerts-center/mobile', [$C . 'AlertsController', 'mobile'])->name('alerts.mobile');
        Route::get('locked-accounts', [$C . 'AlertsController', 'locks'])->name('locks');
        Route::post('locked-accounts/unlock', [$C . 'AlertsController', 'unlock'])->name('locks.unlock');
        Route::post('locked-accounts/bulk', [$C . 'AlertsController', 'unlockBulk'])->name('locks.bulk');

        Route::get('clients', [$C . 'ClientsController', 'index'])->name('clients');
        Route::get('clients/profit', [$C . 'ClientsController', 'profit'])->name('clients.profit');
        Route::get('clients/code-check', [$C . 'ClientsController', 'codeCheck'])->name('clients.code');
        Route::post('clients', [$C . 'ClientsController', 'store'])->name('clients.store');
        Route::put('clients/{customer}', [$C . 'ClientsController', 'update'])->name('clients.update');
        Route::post('clients/{customer}/note', [$C . 'ClientsController', 'note'])->name('clients.note');
        Route::post('clients/{customer}/message', [$C . 'ClientsController', 'message'])->name('clients.message');
        Route::put('clients/{customer}/account', [$C . 'ClientsController', 'account'])->name('clients.account');
        Route::post('clients/{customer}/points', [$C . 'ClientsController', 'points'])->name('clients.points');
        Route::post('clients/{customer}/vip', [$C . 'ClientsController', 'vip'])->name('clients.vip');
        Route::post('clients/{customer}/trusted', [$C . 'ClientsController', 'trusted'])->name('clients.trusted');
        Route::get('clients-trusted', [$C . 'ClientsController', 'trustedPage'])->name('clients.trusted.page');
        Route::post('clients-trusted/remove', [$C . 'ClientsController', 'trustedRemove'])->name('clients.trusted.remove');
        Route::post('clients-trusted', [$C . 'ClientsController', 'trustedBulk'])->name('clients.trusted.bulk');
        Route::post('clients-trusted/chat', [$C . 'ClientsController', 'trustedPreview'])->name('clients.trusted.chat');
        Route::post('clients/{customer}/credit', [$C . 'ClientsController', 'credit'])->name('clients.credit');
        Route::post('clients-bulk-message', [$C . 'ClientsController', 'bulkMessage'])->name('clients.bulk');

        Route::get('carts', [App\Http\Controllers\CartController::class, 'index'])->name('carts');
        Route::get('shipping', [$C . 'ShippingController', 'index'])->name('shipping');
        Route::get('shipping/new', [$C . 'ShippingController', 'create'])->name('shipping.create');
        Route::post('shipping', [$C . 'ShippingController', 'store'])->name('shipping.store');
        Route::get('shipping/prices', [$C . 'ShippingController', 'prices'])->name('shipping.prices');
        Route::post('shipping/prices', [$C . 'ShippingController', 'savePrices'])->name('shipping.prices.save');
        Route::post('shipping/stage', [$C . 'ShippingController', 'stage'])->name('shipping.stage');
        Route::get('shipping/notify', [$C . 'ShippingController', 'notifyForm'])->name('shipping.notify');
        Route::post('shipping/notify', [$C . 'ShippingController', 'notifySend'])->name('shipping.notify.send');
        Route::get('shipping/addresses', [$C . 'ShippingController', 'addresses'])->name('shipping.addresses');
        Route::post('shipping/addresses', [$C . 'ShippingController', 'sendAddresses'])->name('shipping.addresses.send');
        Route::post('shipping/card', [$C . 'ShippingController', 'saveCard'])->name('shipping.card');
        Route::get('shipping/card/preview', [$C . 'ShippingController', 'cardPreview'])->name('shipping.card.preview');
        Route::get('shipping/{order}/edit', [$C . 'ShippingController', 'edit'])->name('shipping.edit');
        Route::put('shipping/{order}', [$C . 'ShippingController', 'update'])->name('shipping.update');
        Route::post('shipping/{order}/ready', [$C . 'ShippingController', 'ready'])->name('shipping.ready');
        Route::get('orders/{order}/missing-list', [$C . 'MissingController', 'index'])->name('missing.list');
        Route::post('orders/{order}/missing-add', [$C . 'MissingController', 'store'])->name('missing.add');
        Route::post('orders/{order}/missing-remove', [$C . 'MissingController', 'remove'])->name('missing.remove');
        Route::post('orders/{order}/missing-settle', [$C . 'MissingController', 'settle'])->name('missing.settle');
        Route::get('shipping/{order}/ready-preview', [$C . 'ShippingController', 'readyPreview'])->name('shipping.ready.preview');
        Route::get('signups', [$C . 'SignupsController', 'index'])->name('signups');
        Route::post('signups/{id}/approve', [$C . 'SignupsController', 'approve'])->name('signups.approve');
        Route::post('signups/{id}/reject', [$C . 'SignupsController', 'reject'])->name('signups.reject');
        Route::delete('signups/{id}', [$C . 'SignupsController', 'destroy'])->name('signups.destroy');
        Route::get('tickets', [$C . 'TicketsController', 'index'])->name('tickets');
        Route::delete('tickets/{ticket}', [$C . 'TicketsController', 'destroy'])->name('tickets.destroy');
        Route::post('tickets/{ticket}/reply', [$C . 'TicketsController', 'reply'])->name('tickets.reply');
        Route::post('couriers/{courier}/close-request/clear', [$C . 'CouriersController', 'clearCloseRequest'])->name('couriers.close.clear');
        Route::get('returns', [$C . 'ReturnsController', 'index'])->name('returns');
        Route::post('flag', [$C . 'FlagController', 'store'])->name('flag');
        Route::post('push/subscribe', [App\Http\Controllers\Portal\PushController::class, 'subscribeUser'])->name('push.subscribe');
    Route::get('ping', [App\Http\Controllers\Courier\PanelController::class, 'ping'])->name('ping');
        Route::post('rate', [$C . 'RateController', 'update'])->name('rate');
        Route::post('rate/ok', [$C . 'RateController', 'touch'])->name('rate.ok');
        Route::get('portal-carts', [$C . 'PortalCartsController', 'index'])->name('pcarts');
        Route::get('booking-queue', [$C . 'PortalCartsController', 'queue'])->name('bqueue');
        Route::post('booking-queue/{cart}', [$C . 'PortalCartsController', 'queueDone'])->name('bqueue.done');
        Route::post('portal-carts/{cart}/decide', [$C . 'PortalCartsController', 'decide'])->name('pcarts.decide');
        Route::post('portal-carts/{cart}/to-delivery', [$C . 'PortalCartsController', 'toDelivery'])->name('pcarts.delivery');
        Route::post('portal-carts/{cart}/note', [$C . 'PortalCartsController', 'note'])->name('pcarts.note');
        Route::get('portal-carts/{cart}/receipt', [$C . 'PortalCartsController', 'receipt'])->name('pcarts.receipt');
        Route::get('portal-carts/{cart}/booking', [$C . 'PortalCartsController', 'booking'])->name('pcarts.booking');
        Route::get('portal-carts/{cart}/download', [$C . 'PortalCartsController', 'download'])->name('pcarts.download');
        Route::post('portal-carts/{cart}/book-now', [$C . 'PortalCartsController', 'bookNow'])->name('pcarts.booknow');
        Route::post('portal-carts/{cart}/office', [$C . 'PortalCartsController', 'office'])->name('pcarts.office');
        Route::post('portal-carts/{cart}/resend', [$C . 'PortalCartsController', 'resend'])->name('pcarts.resend');
        Route::get('returns/orders', [$C . 'ReturnsController', 'customerOrders'])->name('returns.orders');
        Route::post('orders/{order}/return', [$C . 'ReturnsController', 'store'])->name('returns.store');
        Route::get('offers', [$C . 'OffersController', 'index'])->name('offers');
        Route::post('offers', [$C . 'OffersController', 'store'])->name('offers.store');
        Route::post('offers/{offer}/toggle', [$C . 'OffersController', 'toggle'])->name('offers.toggle');
        Route::post('coupons', [$C . 'OffersController', 'coupon'])->name('coupons.store');
        Route::post('orders/{order}/coupon', [$C . 'OffersController', 'applyCoupon'])->name('orders.coupon');
        Route::get('issues', [$C . 'IssuesController', 'index'])->name('issues');
        Route::post('orders/{order}/issues', [$C . 'IssuesController', 'store'])->name('issues.store');
        Route::post('issues/{issue}/resolve', [$C . 'IssuesController', 'resolve'])->name('issues.resolve');
        Route::get('soldouts', [$C . 'SoldoutsController', 'index'])->name('soldouts');
        Route::get('soldouts/find', [$C . 'SoldoutsController', 'find'])->name('soldouts.find');
        Route::post('orders/{order}/soldouts', [$C . 'SoldoutsController', 'store'])->name('soldouts.store');
        Route::post('orders/{order}/soldouts/notify', [$C . 'SoldoutsController', 'notify'])->name('soldouts.notify');
        Route::post('soldouts/{soldout}/decide', [$C . 'SoldoutsController', 'decide'])->name('soldouts.decide');
        Route::get('debts/reminders', [$C . 'DebtsController', 'reminders'])->name('debts.reminders');
        Route::get('debts', [$C . 'DebtsController', 'index'])->name('debts');
        Route::get('debts/statement', [$C . 'DebtsController', 'statement'])->name('debts.statement');
        Route::post('debts/remind', [$C . 'DebtsController', 'remind'])->name('debts.remind');
        Route::post('debts/collect/{customer}', [$C . 'DebtsController', 'collect'])->name('debts.collect');
        Route::post('debts/payables', [$C . 'DebtsController', 'storePayable'])->name('debts.payable.store');
        Route::post('debts/payables/{dept}/pay', [$C . 'DebtsController', 'payPayable'])->name('debts.payable.pay');

        Route::get('expenses', [$C . 'ExpensesController', 'index'])->name('expenses');
        Route::post('expenses', [$C . 'ExpensesController', 'store'])->name('expenses.store');
        Route::post('expenses/salary', [$C . 'ExpensesController', 'salary'])->name('expenses.salary');
        Route::delete('expenses/{purchase}', [$C . 'ExpensesController', 'destroy'])->name('expenses.destroy');
        Route::post('expenses/categories', [$C . 'ExpensesController', 'storeCategory'])->name('expenses.cats.store');
        Route::put('expenses/categories/{cat}', [$C . 'ExpensesController', 'updateCategory'])->name('expenses.cats.update');

        Route::get('finance', [$C . 'FinanceController', 'index'])->name('finance');
        Route::post('finance/ship', [$C . 'FinanceController', 'shipStore'])->name('finance.ship.store');
        Route::delete('finance/ship/{charge}', [$C . 'FinanceController', 'shipDestroy'])->name('finance.ship.destroy');
        Route::post('finance/companies', [$C . 'FinanceController', 'companyStore'])->name('finance.companies.store');
        Route::post('finance/transfer', [$C . 'FinanceController', 'transfer'])->name('finance.transfer');
        Route::post('finance/close', [$C . 'FinanceController', 'closeCash'])->name('finance.close');
        Route::post('finance/ship-toggle', [$C . 'FinanceController', 'shipToggle'])->name('finance.shiptoggle');
        Route::get('daily-close', [$C . 'DailyCloseController', 'index'])->name('daily');
        Route::get('trade', [$C . 'TradeController', 'index'])->name('trade');
        Route::post('trade/partner', [$C . 'TradeController', 'partner'])->name('trade.partner');
        Route::post('trade/partner/{partner}', [$C . 'TradeController', 'partnerUpdate'])->name('trade.partner.update');
        Route::post('trade/invoices/{invoice}/edit', [$C . 'TradeController', 'invoiceUpdate'])->name('trade.invoice.update');
        Route::post('trade/invoices/{invoice}/delete', [$C . 'TradeController', 'invoiceDelete'])->name('trade.invoice.delete');
        Route::post('trade/money', [$C . 'TradeController', 'money'])->name('trade.money');
        Route::post('trade/customers', [$C . 'TradeController', 'tcustomer'])->name('trade.tcustomer');
        Route::post('trade/wallet/{wallet}', [$C . 'TradeController', 'walletUpdate'])->name('trade.wallet.update');
        Route::post('trade/invoices', [$C . 'TradeController', 'invoiceStore'])->name('trade.invoice.store');
        Route::get('trade/invoices/{invoice}', [$C . 'TradeController', 'invoice'])->name('trade.invoice');
        Route::post('trade/invoices/{invoice}', [$C . 'TradeController', 'invoiceAct'])->name('trade.invoice.act');
        Route::get('finance/adjust', [$C . 'FinanceController', 'adjustForm'])->name('finance.adjust.form');
        Route::post('finance/adjust', [$C . 'FinanceController', 'adjust'])->name('finance.adjust');

        Route::get('staff', [$C . 'StaffController', 'index'])->name('staff');
        Route::post('staff', [$C . 'StaffController', 'store'])->name('staff.store');
        Route::put('staff/{user}', [$C . 'StaffController', 'update'])->name('staff.update');
        Route::post('staff/{user}/perms', [$C . 'StaffController', 'perms'])->name('staff.perms');
        Route::post('staff/{user}/password', [$C . 'StaffController', 'password'])->name('staff.password');
        Route::post('staff/{user}/toggle', [$C . 'StaffController', 'toggle'])->name('staff.toggle');
        Route::delete('staff/{user}', [$C . 'StaffController', 'destroy'])->name('staff.destroy');

        Route::get('reports', [$C . 'ReportsController', 'index'])->name('reports');
        Route::post('reports/whatsapp', [$C . 'ReportsController', 'whatsapp'])->name('reports.whatsapp');

        Route::get('messages', [$C . 'MessagesController', 'index'])->name('messages');
        Route::put('messages/{tpl}', [$C . 'MessagesController', 'update'])->name('messages.update');
        Route::post('messages/{tpl}/toggle', [$C . 'MessagesController', 'toggle'])->name('messages.toggle');
        Route::post('messages/{tpl}/test', [$C . 'MessagesController', 'test'])->name('messages.test');

        // بطاقات SHEIN
        Route::get('shein-cards/sell', [$C . 'CardSalesController', 'create'])->name('cards.sell');
        Route::post('shein-cards/sell', [$C . 'CardSalesController', 'store'])->name('cards.sell.store');
        Route::post('orders/{order}/cards/resend', [$C . 'CardSalesController', 'resend'])->name('cards.resend');
        Route::get('orders/{order}/cards/reveal', [$C . 'CardSalesController', 'reveal'])->name('cards.reveal');
        Route::get('shein-cards', [$C . 'SheinCardsController', 'index'])->name('shein');
        Route::get('shein-cards/check', [$C . 'SheinCardsController', 'check'])->name('shein.check');
        Route::post('shein-cards', [$C . 'SheinCardsController', 'store'])->name('shein.store');
        Route::post('shein-cards/use', [$C . 'SheinCardsController', 'use'])->name('shein.use');
        Route::delete('shein-cards/uses/{use}', [$C . 'SheinCardsController', 'reverse'])->name('shein.reverse');
        Route::put('shein-cards/{card}', [$C . 'SheinCardsController', 'update'])->name('shein.update');
        Route::post('shein-cards/{card}/status', [$C . 'SheinCardsController', 'status'])->name('shein.status');
        Route::delete('shein-cards/{card}', [$C . 'SheinCardsController', 'destroy'])->name('shein.destroy');
        Route::get('shein-cards/{card}/pin', [$C . 'SheinCardsController', 'pin'])->name('shein.pin');

        Route::get('audit', [$C . 'AuditController', 'index'])->name('audit');
        Route::get('visits', [$C . 'VisitsController', 'index'])->name('visits');
        Route::get('campaigns', [$C . 'CampaignsController', 'index'])->name('campaigns');
        Route::post('campaigns', [$C . 'CampaignsController', 'store'])->name('campaigns.store');
        Route::post('campaigns/{id}/toggle', [$C . 'CampaignsController', 'toggle'])->name('campaigns.toggle');
        Route::delete('campaigns/{id}', [$C . 'CampaignsController', 'destroy'])->name('campaigns.destroy');
        Route::get('backups', [$C . 'BackupController', 'index'])->name('backups');
        Route::post('cleanup', function () {
            abort_unless(auth()->user()->hasRole('Admin'), 403);
            \Illuminate\Support\Facades\Artisan::call('sg:cleanup');

            return back()->with('success', '🧹 ' . trim(\Illuminate\Support\Facades\Artisan::output()));
        })->name('cleanup');
        Route::post('backups', [$C . 'BackupController', 'store'])->name('backups.store');
        Route::get('backups/{file}', [$C . 'BackupController', 'download'])->name('backups.download')->where('file', '[A-Za-z0-9_.-]+');
        Route::get('settings', [$C . 'SettingsController', 'index'])->name('settings');
        Route::post('settings', [$C . 'SettingsController', 'save'])->name('settings.save');
        Route::post('settings/group-remove', [$C . 'SettingsController', 'groupRemove'])->name('settings.group.remove');
        Route::post('settings/groups-fetch', [$C . 'SettingsController', 'groupsFetch'])->name('settings.groups.fetch');
        Route::post('settings/group-test', [$C . 'SettingsController', 'groupTest'])->name('settings.group.test');
        Route::post('settings/payments', [$C . 'SettingsController', 'payment'])->name('settings.payment');
        Route::post('settings/payments/{payment}/toggle', [$C . 'SettingsController', 'paymentToggle'])->name('settings.payment.toggle');
        Route::post('settings/statuses', [$C . 'SettingsController', 'statuses'])->name('settings.statuses');
        Route::post('settings/platforms', [$C . 'SettingsController', 'platforms'])->name('settings.platforms');
    });

    Route::get('order/{order}/receive', [App\Http\Controllers\OrderFinanceController::class, 'receiveForm'])->name('order.receive.form');
    Route::post('order/{order}/receive', [App\Http\Controllers\OrderFinanceController::class, 'receiveStore'])->name('order.receive.store');


});


  // ===================== واجهة المندوب =====================
  Route::middleware(['auth', 'isCourier'])->prefix('courier')->name('courier.')->group(function () {
    Route::get('/', [App\Http\Controllers\Courier\PanelController::class, 'index'])->name('home');
    Route::post('push/subscribe', [App\Http\Controllers\Portal\PushController::class, 'subscribeUser'])->name('push.subscribe');
    Route::get('ping', [App\Http\Controllers\Courier\PanelController::class, 'ping'])->name('ping');
    Route::post('{id}/out', [App\Http\Controllers\Courier\PanelController::class, 'out'])->name('out');
    Route::post('{id}/done', [App\Http\Controllers\Courier\PanelController::class, 'done'])->name('done');
    Route::post('{id}/problem', [App\Http\Controllers\Courier\PanelController::class, 'problem'])->name('problem');
    Route::post('{id}/cancel', [App\Http\Controllers\Courier\PanelController::class, 'cancel'])->name('cancel');
    Route::post('{id}/recipient', [App\Http\Controllers\Courier\PanelController::class, 'recipient'])->name('recipient');
    Route::post('close', [App\Http\Controllers\Courier\PanelController::class, 'close'])->name('close');
    Route::post('available', [App\Http\Controllers\Courier\PanelController::class, 'available'])->name('available');
    Route::post('password', [App\Http\Controllers\Courier\PanelController::class, 'password'])->name('password');
  });

  Route::middleware('auth')->group(function () {
    Route::resource('dashboard', App\Http\Controllers\HomeController::class);
    Route::post('changePassword', [App\Http\Controllers\HomeController::class, 'changePassword'])->name('changePassword');
  });


// 🛵 العميل يطلب مندوب من رابط رسالة «جاهز للاستلام» (من غير دخول — الرابط فيه توقيع)
Route::get('dl/{order}/{t}', [App\Http\Controllers\Portal\DeliveryRequestController::class, 'show'])->whereNumber('order')->name('deliv.req');
Route::post('dl/{order}/{t}', [App\Http\Controllers\Portal\DeliveryRequestController::class, 'store'])->whereNumber('order')->middleware('throttle:10,1')->name('deliv.req.store');

Route::prefix('customers')->group(function () {
    // Route::get('/login', [CustomerAuthController::class, 'showLoginForm'])->name('customer.login');
    // Route::post('/login', [CustomerAuthController::class, 'login'])->middleware('throttle:login')->name('customer.login.submit');
// تسجيل عميل جديد + رمز التحقق عبر واتساب
Route::get('/register', [App\Http\Controllers\Portal\SignupController::class, 'form'])->name('customer.register');
Route::post('/register', [App\Http\Controllers\Portal\SignupController::class, 'store'])->middleware('throttle:signup')->name('customer.register.store');
Route::get('/verify', [App\Http\Controllers\Portal\SignupController::class, 'verifyForm'])->name('customer.verify');
Route::post('/verify', [App\Http\Controllers\Portal\SignupController::class, 'verify'])->middleware('throttle:otp')->name('customer.verify.submit');
Route::post('/verify/resend', [App\Http\Controllers\Portal\SignupController::class, 'resend'])->middleware('throttle:otp')->name('customer.verify.resend');
    // Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('customer.logout');

    // حساب السلة متاح حتى بدون تسجيل دخول (التأكيد يطلب دخول/حساب)
    Route::middleware('throttle:120,1')->group(function () {
        Route::get('/carts', [App\Http\Controllers\Portal\CartsController::class, 'index'])->name('portal.carts');
        Route::get('/c/{id}/{t}', [App\Http\Controllers\Portal\CartsController::class, 'publicView'])->whereNumber('id')->name('portal.cart.public');
        Route::get('/c/{id}/{t}/courier', [App\Http\Controllers\Portal\CartsController::class, 'courierForm'])->whereNumber('id')->name('portal.cart.courier');
        Route::post('/c/{id}/{t}/courier', [App\Http\Controllers\Portal\CartsController::class, 'courierSend'])->whereNumber('id')->middleware('throttle:10,1')->name('portal.cart.courier.send');
        Route::post('/carts', [App\Http\Controllers\Portal\CartsController::class, 'store'])->middleware('throttle:portal')->name('portal.carts.store');
        Route::get('/carts/{id}', [App\Http\Controllers\Portal\CartsController::class, 'show'])->whereNumber('id')->name('portal.cart');
        Route::get('/carts/{id}/poll', [App\Http\Controllers\Portal\CartsController::class, 'poll'])->whereNumber('id')->name('portal.cart.poll');
        Route::get('/carts/{id}/image/{i}', [App\Http\Controllers\Portal\CartsController::class, 'image'])->whereNumber('id')->whereNumber('i')->name('portal.cart.image');
        Route::post('/carts/{id}/order', [App\Http\Controllers\Portal\CartsController::class, 'order'])->whereNumber('id')->name('portal.cart.order');
        // الـ PDF والإيصال: حتى للزائر اللي حجز من الرابط (السلة لازم تكون في جلسته أو حسابه)
        Route::get('/carts/{id}/pdf', [App\Http\Controllers\Portal\CartsController::class, 'pdf'])->whereNumber('id')->name('portal.cart.pdf');
        Route::post('/carts/{id}/receipt', [App\Http\Controllers\Portal\CartsController::class, 'receipt'])->whereNumber('id')->middleware('throttle:10,1')->name('portal.cart.receipt');
    });

    Route::middleware('auth:customer')->group(function () {

                    Route::post('/change-password-now', [CustomerAuthController::class, 'changePassword'])->name('customer.change.password');
        Route::get('/change-password', [CustomerAuthController::class, 'changePasswordPage'])->name('customer.change.password.page');

        Route::post('/change-password-new', [CustomerAuthController::class, 'changeNewPassword'])->name('customer.change.password.new');

        Route::get('order-search/', [App\Http\Controllers\CustomerAuthController::class, 'search'])->name('customer.order.search');

        // حساب الزبون الجديد
        Route::get('/dashboard', [App\Http\Controllers\Portal\PortalController::class, 'index'])->name('customer.dashboard');
        Route::post('/delivery', [App\Http\Controllers\Portal\PortalController::class, 'requestDelivery'])->name('portal.delivery');
        Route::post('/delivery/{id}/cancel', [App\Http\Controllers\Portal\PortalController::class, 'cancelRequest'])->name('portal.delivery.cancel');
        Route::post('/password', [App\Http\Controllers\Portal\PortalController::class, 'password'])->name('portal.password');
        Route::post('/my-name', [App\Http\Controllers\Portal\PortalController::class, 'saveName'])->name('portal.name');
        Route::post('/ticket', [App\Http\Controllers\Portal\PortalController::class, 'ticket'])->middleware('throttle:portal')->name('portal.ticket');
        Route::post('/points-coupon', [App\Http\Controllers\Portal\PortalController::class, 'pointsCoupon'])->middleware('throttle:portal')->name('portal.points.coupon');
        Route::post('/tracking', [App\Http\Controllers\Portal\PortalController::class, 'tracking'])->name('portal.tracking');
        Route::post('/profile', [App\Http\Controllers\Portal\PortalController::class, 'profile'])->name('portal.profile');
        Route::get('/statement', [App\Http\Controllers\Portal\PortalController::class, 'statement'])->name('portal.statement');
        Route::post('/rate-delivery/{id}', [App\Http\Controllers\Portal\PortalController::class, 'rateDelivery'])->whereNumber('id')->middleware('throttle:portal')->name('portal.rate.delivery');
        Route::post('/rate/{id}', [App\Http\Controllers\Portal\PortalController::class, 'rate'])->whereNumber('id')->middleware('throttle:portal')->name('portal.rate');
        Route::post('/push/subscribe', [App\Http\Controllers\Portal\PushController::class, 'subscribe'])->middleware('throttle:portal')->name('portal.push.subscribe');
        Route::get('/push/latest', [App\Http\Controllers\Portal\PushController::class, 'latest'])->name('portal.push.latest');
        Route::post('/carts/{id}/claim', [App\Http\Controllers\Portal\CartsController::class, 'claim'])->whereNumber('id')->name('portal.cart.claim');
        Route::get('/old', [CustomerAuthController::class, 'index'])->name('customer.dashboard.old');
    });
});
