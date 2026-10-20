<?php

namespace App\Support;

class AmazonOfferDetails
{
    public const DELIVERY_EXCLUSION = 'رسوم توصيل البائع غير مشمولة في السعر المعروض.';

    public static function fromItem(array $item): array
    {
        $notes = is_array($item['notes'] ?? null) ? $item['notes'] : [];
        $first = $notes[0] ?? null;
        $notice = is_string($first) && str_starts_with($first, 'Notice:') ? $first : null;
        $seller = null;
        $delivery = null;
        $other = [];
        foreach ($notes as $index => $note) {
            if (! is_string($note) || trim($note) === '' || ($index === 0 && $notice !== null)) {
                continue;
            }
            if (str_starts_with($note, 'Sold by ')) {
                $seller = $seller ?? $note;
            } elseif (str_starts_with($note, 'Delivery ')) {
                $delivery = $delivery ?? $note;
            } else {
                $other[] = $note;
            }
        }

        return [
            'notice' => $notice,
            'seller' => $seller,
            'delivery' => $delivery,
            'other_notes' => array_slice($other, 0, 3),
        ];
    }

    public static function captionLines(array $item): array
    {
        $details = self::fromItem($item);
        if ($details['notice'] === null && $details['delivery'] === null) {
            return [];
        }

        $lines = array_filter([$details['notice'], $details['seller'], $details['delivery']], fn ($line) => $line !== null);
        if ($details['delivery'] !== null) {
            $lines[] = self::DELIVERY_EXCLUSION;
        }

        return array_values($lines);
    }
}
