<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'name' => $this->name,
            'name_en' => $this->name_en,
            'logo' => $this->logo,
            'delete_at' => $this->delete_at,
            'products' => ProductCollection::make($this->whenLoaded('products'), $this->customerId),
        ];
    }
}
