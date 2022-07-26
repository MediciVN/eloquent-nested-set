<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MediciVN\EloquentNestedSet\Tests\Models\Category;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('slug')->unique();
            $table->json('cover_image')->nullable();
            $table->unsignedBigInteger('parent_id')->default(Category::ROOT_ID)->nullable();
            $table->integer('lft')->nullable();
            $table->integer('rgt')->nullable();
            $table->unsignedBigInteger('depth')->nullable();
            $table->unsignedBigInteger('order')->nullable();
            $table->unsignedInteger('status')->default(2);
            $table->timestamps();
        });

        $this->seed();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('categories');
    }

    /**
     * Initial root node for Nested-set
     * @return void
     */
    private function seed()
    {
        DB::table('categories')->insert([
            [
                'id' => Category::ROOT_ID,
                'name' => 'root',
                'slug' => 'root',
                'parent_id' => 0,
                'lft' => 1,
                'rgt' => 2,
                'depth' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
};
