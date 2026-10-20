<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Resources\Json\JsonResource;

class AdResource extends JsonResource
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
            'id' => $this->id,
            'name' => $this->name,
            'img' => $this->img,
            'product_id' => $this->product_id,
            'link' => $this->link,
            'status' => $this->status,
            'delete_at' => $this->delete_at,
            'products' => ProductResource::make($this->product, $this->customerId),
            // 'product' => $this->product,
        ];
    }
}
