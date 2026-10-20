<?php

namespace App\Http\Resources\API;

use App\Http\Resources\API\CityResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CityCollection extends ResourceCollection
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
     * @return array
     */
    public function toArray($request)
    {
        return [
            'data' => $this->collection,
        ];
    }
}
