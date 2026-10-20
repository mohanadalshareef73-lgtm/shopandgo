<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Resources\Json\JsonResource;

class FavoriteResource extends JsonResource
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
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'customer_id' => $this->customer_id,
            'product' => ProductResource::make($this->product, $this->customerId),
        ];
    }
}
