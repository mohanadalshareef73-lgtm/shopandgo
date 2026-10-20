<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Classification;
use App\Models\User;
use App\Models\Voucher;

class VoucherFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Voucher::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'classification_id' => Classification::factory(),
            'user_id' => User::factory(),
            'serial_number' => $this->faker->word,
            'delete_at' => $this->faker->dateTime(),
        ];
    }
}
