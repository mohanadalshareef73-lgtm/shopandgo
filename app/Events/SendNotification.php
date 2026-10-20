<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class SendNotification
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user,$userIds;
    public $notification,$notification_en,$title,$title_en;
    public $type;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(User $user=null,$notification,$notification_en,$type,$title,$title_en,$userIds=null)
    {
        $this->userIds = $userIds;
        $this->user = $user;
        $this->type = $type;
        $this->title = $title;
        $this->title_en = $title_en;
        $this->notification = $notification;
        $this->notification_en = $notification_en;

    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
