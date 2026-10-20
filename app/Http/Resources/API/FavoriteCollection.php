<?php

namespace App\Http\Resources\API;

use App\Models\Favorite;
use Illuminate\Http\Resources\Json\ResourceCollection;

class FavoriteCollection extends ResourceCollection
{

    protected $customerId;
    public function __construct($resource, $customerId)
    {
        parent::__construct($resource);
        $this->customerId = $customerId;
    }

    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function ($item) {
                return new FavoriteResource($item, $this->customerId);
            }),
        ];
    }
}
