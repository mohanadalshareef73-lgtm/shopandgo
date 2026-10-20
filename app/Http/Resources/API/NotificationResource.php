<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
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
            'type' => $this->type,
            'message' => $this->message,
            'message_en' => $this->message_en,
            'title' => $this->title,
            'title_en' => $this->title_en,
            'price' => $this->price,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at,
        ];
    }
}
