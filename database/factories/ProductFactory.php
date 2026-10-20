<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Product;
use App\Models\Provider;

class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'external_id' => $this->faker->word,
            'background_color' => $this->faker->regexify('[A-Za-z0-9]{10}'),
            'name' => $this->faker->name,
            'provider_id' => Provider::factory(),
            'logo' => $this->faker->text,
            'category_id' => Category::factory(),
            'delete_at' => $this->faker->dateTime(),
        ];
    }
}
