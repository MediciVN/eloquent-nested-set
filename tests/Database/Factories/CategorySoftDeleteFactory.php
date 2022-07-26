<?php

namespace MediciVN\EloquentNestedSet\Tests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use MediciVN\EloquentNestedSet\Tests\Models\CategorySoftDelete;

class CategorySoftDeleteFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CategorySoftDelete::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'slug' => $this->faker->slug,
            'description' => $this->faker->text,
            'parent_id' => null,
            'lft' => null,
            'rgt' => null,
            'depth' => null
        ];
    }
}
