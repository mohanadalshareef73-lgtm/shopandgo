<?php

use App\Http\Controllers\API\CartQuoteWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/cart-quotes', CartQuoteWebhookController::class)->name('webhooks.cart-quotes');
Route::post('/hooks/cart-quotes', CartQuoteWebhookController::class)->name('hooks.cart-quotes');
Route::post('/hooks/cartquotes', CartQuoteWebhookController::class)->name('hooks.cartquotes');
