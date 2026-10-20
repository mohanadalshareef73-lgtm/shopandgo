<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Resources\Json\JsonResource;

class VoucherResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'classification_id' => $this->classification->id,
            'name' => $this->classification->name,
            'external_id' => $this->classification->external_id,
            'price' => $this->classification->price,
            // 'classification' => ClassificationResource::make($this->classification),
            'external_order_id' => $this->external_order_id,
            'serial_number' => $this->serial_number,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
