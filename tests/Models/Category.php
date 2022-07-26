<?php

namespace MediciVN\EloquentNestedSet\Tests\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MediciVN\EloquentNestedSet\NestedSetModel;
use MediciVN\EloquentNestedSet\Tests\Database\Factories\CategoryFactory;

class Category extends Model
{
    use NestedSetModel, HasFactory;

    const ROOT_ID = 1;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'description',
        'cover_image',
        'parent_id',
        'depth',
        'order',
        'status',
    ];

    /**
     * Create a new factory instance for the model.
     *
     * @return Factory
     */
    protected static function newFactory(): Factory
    {
        return CategoryFactory::new();
    }
}
