<?php

namespace MediciVN\EloquentNestedSet\Tests\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\SoftDeletes;
use MediciVN\EloquentNestedSet\Tests\Database\Factories\CategorySoftDeleteFactory;

class CategorySoftDelete extends Category
{
    use SoftDeletes;

    protected $table = 'soft_delete_categories';

    /**
     * Create a new factory instance for the model.
     *
     * @return Factory
     */
    protected static function newFactory(): Factory
    {
        return CategorySoftDeleteFactory::new();
    }
}
