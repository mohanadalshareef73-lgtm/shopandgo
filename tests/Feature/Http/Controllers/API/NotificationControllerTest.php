<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\API\NotificationController
 */
class NotificationControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    /**
     * @test
     */
    public function index_behaves_as_expected()
    {
        $notifications = Notification::factory()->count(3)->create();

        $response = $this->get(route('notification.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    /**
     * @test
     */
    public function store_uses_form_request_validation()
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\API\NotificationController::class,
            'store',
            \App\Http\Requests\API\NotificationStoreRequest::class
        );
    }

    /**
     * @test
     */
    public function store_saves()
    {
        $type = $this->faker->randomElement(/** enum_attributes **/);
        $message = $this->faker->word;
        $user = User::factory()->create();

        $response = $this->post(route('notification.store'), [
            'type' => $type,
            'message' => $message,
            'user_id' => $user->id,
        ]);

        $notifications = Notification::query()
            ->where('type', $type)
            ->where('message', $message)
            ->where('user_id', $user->id)
            ->get();
        $this->assertCount(1, $notifications);
        $notification = $notifications->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }
}
