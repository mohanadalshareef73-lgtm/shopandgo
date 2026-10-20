<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Resources\Json\JsonResource;

class RegionResource extends JsonResource
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
            'name' => $this->name,
            'name_en' => $this->name,
            'product_id' => $this->product_id,
            'delete_at' => $this->delete_at,
            'classifications' => ClassificationCollection::make($this->classifications->where('show', true)),
            // 'product' => ProductResource::make($this->product),
        ];
    }
}
