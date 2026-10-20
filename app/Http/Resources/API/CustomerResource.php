<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            'mobile_number' => $this->mobile_number,
            'name' => $this->name,
            'email' => $this->email,
            'city' => CityResource::make($this->city),
            'birthday' => $this->birthday->format('Y-m-d'),
            'password' => $this->password,
            'paymentType_id' => $this->paymentType_id,
            'api_token' => $this->api_token,
            'delete_at' => $this->delete_at,
            'phoneNumbers' => PhoneNumberCollection::make($this->phoneNumbers),
        ];
    }
}
