<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{

    protected $customerId;
    public function __construct($resource, $customerId)
    {
        parent::__construct($resource);
        $this->customerId = $customerId;
    }

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
            'external_id' => $this->external_id,
            'background_color' => $this->background_color,
            'name' => $this->name,
            'name_en' => $this->name,
            'description' => $this->description,
            'description_en' => $this->description_en,
            'provider_id' => $this->provider_id,
            'logo' => $this->logo,
            'category_id' => $this->category_id,
            'customer_id' => $this->customerId,
            'favorite' => $this->favorite->contains('customer_id', $this->customerId),
            'delete_at' => $this->delete_at,
            'regions' => RegionCollection::make($this->regions->where('show', true)),
        ];
    }
}
