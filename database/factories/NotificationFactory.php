<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Notification;
use App\Models\User;

class NotificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Notification::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'type' => $this->faker->randomElement(["1","2","3","4"]),
            'message' => $this->faker->word,
            'price' => $this->faker->randomFloat(0, 0, 9999999999.),
            'user_id' => User::factory(),
            'delete_at' => $this->faker->dateTime(),
        ];
    }
}
