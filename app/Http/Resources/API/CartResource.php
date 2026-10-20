<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
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
            'customer_id' => $this->customer_id,
            'name' => $this->classification->name,
            'name_en' => $this->classification->name_en,
            'price' => $this->classification->price,
            'logo' => $this->classification->region->product->logo,
            'background_color' => $this->classification->region->product->background_color,
            'count' => $this->count,
        ];
    }
}
