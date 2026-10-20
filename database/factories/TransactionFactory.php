<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Provider;
use App\Models\Transaction;
use App\Models\User;

class TransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'order_id' => Order::factory(),
            'check_out_id' => $this->faker->word,
            'price' => $this->faker->randomFloat(0, 0, 9999999999.),
            'provider_id' => Provider::factory(),
            'currency_id' => Currency::factory(),
            'exchange_rate' => $this->faker->randomFloat(0, 0, 9999999999.),
            'user_id' => User::factory(),
            'delete_at' => $this->faker->dateTime(),
        ];
    }
}
