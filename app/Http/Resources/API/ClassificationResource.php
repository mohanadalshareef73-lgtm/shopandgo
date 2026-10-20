<?php

namespace App\Http\Resources\API;

use App\Models\ClassificationPayment;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_en' => $this->name_en,
            'external_id' => $this->external_id,
            'price' => $this->price,
            'delete_at' => $this->delete_at,
            // 'region' => RegionResource::make($this->region),
            'currency' => CurrencyResource::make($this->currency),
            'payments' => ClassificationPaymentCollection::make($this->payments),
        ];
    }
}
