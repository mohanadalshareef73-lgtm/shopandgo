<?php

namespace App\Listeners;

use App\Services\FCMService;
use App\Events\SendNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendNotificationListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(SendNotification $event)
    {
        try {
            Log::channel('sendNotification')->info("date (".date('F j, Y, g:i a').") - Title (".$event->notification .")");
            // if user = null pass userIds
            $ids= isset($event->user)? [''.$event->user->id] : $event->userIds ;
            FCMService::send($ids ,$event->notification,$event->notification_en,$event->type, $event->title, $event->title_en, $event->userIds); 
        } catch (\Throwable $th) {
            Log::channel('sendNotification')->error("Title (".$event->notification .") - Error =>".$th );
        }
    }
}
