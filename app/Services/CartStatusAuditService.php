<?php

namespace App\Services;

use App\Models\CartQuote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CartStatusAuditService
{
    public function saveForOperator(CartQuote $cart, User $operator): void
    {
        $this->save($cart, ['actor_type' => 'operator', 'actor_name' => $operator->name,
            'actor_user_id' => $operator->id]);
    }

    public function saveForCustomer(CartQuote $cart, string $phone, string $sessionUuid, string $eventId): void
    {
        $this->save($cart, ['actor_type' => 'customer',
            'actor_name' => $cart->incomingMessage?->name ?? 'عميل واتساب',
            'actor_phone' => $phone, 'session_uuid' => $sessionUuid, 'event_id' => $eventId]);
    }

    /** Callers hold the cart row lock. Save the change and its audit atomically. */
    private function save(CartQuote $cart, array $actor): void
    {
        DB::transaction(function () use ($cart, $actor) {
            $changed = $cart->isDirty(['operator_status', 'customer_status', 'operator_notes']);
            $audit = [
                'previous_status' => $cart->getRawOriginal('operator_status') ?? 'new',
                'new_status' => $cart->operator_status ?? 'new',
                'previous_customer_status' => $cart->getRawOriginal('customer_status') ?? 'open',
                'new_customer_status' => $cart->customer_status ?? 'open',
                'previous_notes' => $cart->getRawOriginal('operator_notes'),
                'new_notes' => $cart->operator_notes,
                'changed_at' => now(),
            ];
            $cart->save();
            if ($changed) {
                $cart->statusAudits()->create($audit + $actor);
            }
        });
    }
}
