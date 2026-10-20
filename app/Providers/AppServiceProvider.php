<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
        $rawWebhook = fn ($request) => \App\Http\CartQuoteWebhookRequest::matches($request);
        \Illuminate\Foundation\Http\Middleware\TrimStrings::skipWhen($rawWebhook);
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::skipWhen($rawWebhook);
        // سجل التعديلات الأوتوماتيكي
        \App\Services\AuditService::register();
    }
}
