<?php

namespace MediciVN\EloquentNestedSet\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Benchmark;
use MediciVN\EloquentNestedSet\NestedSetModelException;
use MediciVN\EloquentNestedSet\Tests\Models\Category;
use MediciVN\EloquentNestedSet\Tests\Models\CategorySoftDelete;
use MediciVN\EloquentNestedSet\Tests\TestCase;

class NestedSetModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_must_has_a_root_node()
    {
        $this->assertDatabaseHas('categories', [
            'id' => Category::ROOT_ID,
            'name' => 'root',
            'slug' => 'root',
            'parent_id' => 0,
            'lft' => 1,
            'rgt' => 2,
            'depth' => 0,
        ]);

        $root = Category::find(Category::ROOT_ID);
        $this->assertNull($root);

        $root = Category::withoutGlobalScope('ignore_root')->find(Category::ROOT_ID);
        $this->assertEquals(Category::rootId(), $root->id);
    }

    /** @test */
    public function it_must_detect_if_soft_detele_trait_in_use()
    {
        $this->assertTrue(CategorySoftDelete::IsSoftDelete());
        $this->assertNotTrue(Category::IsSoftDelete());
    }

    /** @test */
    public function it_must_to_check_descendant_or_not()
    {
        $c2 = Category::factory()->create(["name" => "Category 2"]);
        $c3 = Category::factory()->create(["name" => "Category 3", "parent_id" => $c2->id]);
        $c4 = Category::factory()->create(["name" => "Category 4", "parent_id" => $c3->id]);
        $c5 = Category::factory()->create(["name" => "Category 5", "parent_id" => $c4->id]);

        $c2->refresh();
        $c3->refresh();
        $c4->refresh();
        $c5->refresh();

        $this->assertTrue($c2->hasDescendant($c3->id));
        $this->assertTrue($c2->hasDescendant($c4->id));
        $this->assertTrue($c2->hasDescendant($c5->id));

        $this->assertTrue($c3->hasDescendant($c4->id));
        $this->assertTrue($c3->hasDescendant($c5->id));

        $this->assertFalse($c2->hasDescendant($c2->id));
        $this->assertFalse($c3->hasDescendant($c2->id));
        $this->assertFalse($c4->hasDescendant($c2->id));
        $this->assertFalse($c5->hasDescendant($c2->id));
    }

    /** @test */
    public function it_must_to_check_ancestor_or_not()
    {
        $c2 = Category::factory()->create(["name" => "Category 2"]);
        $c3 = Category::factory()->create(["name" => "Category 3", "parent_id" => $c2->id]);
        $c4 = Category::factory()->create(["name" => "Category 4", "parent_id" => $c3->id]);
        $c5 = Category::factory()->create(["name" => "Category 5", "parent_id" => $c4->id]);

        $c2->refresh();
        $c3->refresh();
        $c4->refresh();
        $c5->refresh();

        $this->assertFalse($c2->hasAncestor($c2->id));
        $this->assertFalse($c2->hasAncestor($c3->id));
        $this->assertFalse($c2->hasAncestor($c4->id));
        $this->assertFalse($c2->hasAncestor($c5->id));

        $this->assertTrue($c3->hasAncestor($c2->id));
        $this->assertTrue($c4->hasAncestor($c2->id));
        $this->assertTrue($c5->hasAncestor($c2->id));
    }

    /** @test */
    public function it_must_to_throw_exception_if_given_parent_is_same_as_current_node_id()
    {
        $this->expectException(NestedSetModelException::class);
        $c2 = Category::factory()->create(["name" => "Category 2"]);
        $c2->parent_id = $c2->id;
        $c2->save();
    }

    /** @test */
    public function it_must_to_throw_exception_if_given_parent_id_is_a_descendant_of_current_node()
    {
        $this->expectException(NestedSetModelException::class);
        $c2 = Category::factory()->create(["name" => "Category 2"]);
        $c3 = Category::factory()->create(["name" => "Category 3", "parent_id" => $c2->id]);
        $c4 = Category::factory()->create(["name" => "Category 4", "parent_id" => $c3->id]);

        $c2->parent_id = $c4->id;
        $c2->save();
    }

    /** @test */
    public function it_must_return_ancestors_of_current_node()
    {
        $c1 = Category::factory()->create(["name" => "Category 1"]);
        $c2 = Category::factory()->create(["name" => "Category 2"]);
        $c3 = Category::factory()->create(["name" => "Category 3", "parent_id" => $c2->id]);
        $c4 = Category::factory()->create(["name" => "Category 4", "parent_id" => $c3->id]);
        $c5 = Category::factory()->create(["name" => "Category 5", "parent_id" => $c4->id]);
        $c6 = Category::factory()->create(["name" => "Category 6"]);
        $c7 = Category::factory()->create(["name" => "Category 7"]);
        $c8 = Category::factory()->create(["name" => "Category 8"]);

        $c1->refresh();
        $c2->refresh();
        $c3->refresh();
        $c4->refresh();
        $c5->refresh();
        $c6->refresh();
        $c7->refresh();
        $c8->refresh();

        $c5_ancestors = $c5->getAncestors();

        $this->assertNotContains($c1->toArray(), $c5_ancestors->toArray());
        $this->assertContains($c2->toArray(), $c5_ancestors->toArray());
        $this->assertContains($c3->toArray(), $c5_ancestors->toArray());
        $this->assertContains($c4->toArray(), $c5_ancestors->toArray());
        $this->assertNotContains($c5->toArray(), $c5_ancestors->toArray());
        $this->assertNotContains($c6->toArray(), $c5_ancestors->toArray());
        $this->assertNotContains($c7->toArray(), $c5_ancestors->toArray());
        $this->assertNotContains($c8->toArray(), $c5_ancestors->toArray());

        $c4_ancestors = $c4->getAncestors();

        $this->assertNotContains($c1->toArray(), $c4_ancestors->toArray());
        $this->assertContains($c2->toArray(), $c4_ancestors->toArray());
        $this->assertContains($c3->toArray(), $c4_ancestors->toArray());
        $this->assertNotContains($c4->toArray(), $c4_ancestors->toArray());
        $this->assertNotContains($c5->toArray(), $c4_ancestors->toArray());
        $this->assertNotContains($c6->toArray(), $c4_ancestors->toArray());
        $this->assertNotContains($c7->toArray(), $c4_ancestors->toArray());
        $this->assertNotContains($c8->toArray(), $c4_ancestors->toArray());
    }

    /** @test */
    public function it_must_return_descendants_of_current_node()
    {
        $c1 = Category::factory()->create(["name" => "Category 1"]);
        $c2 = Category::factory()->create(["name" => "Category 2"]);
        $c3 = Category::factory()->create(["name" => "Category 3", "parent_id" => $c2->id]);
        $c4 = Category::factory()->create(["name" => "Category 4", "parent_id" => $c3->id]);
        $c5 = Category::factory()->create(["name" => "Category 5", "parent_id" => $c4->id]);
        $c6 = Category::factory()->create(["name" => "Category 6"]);
        $c7 = Category::factory()->create(["name" => "Category 7"]);
        $c8 = Category::factory()->create(["name" => "Category 8"]);

        $c1->refresh();
        $c2->refresh();
        $c3->refresh();
        $c4->refresh();
        $c5->refresh();
        $c6->refresh();
        $c7->refresh();
        $c8->refresh();

        $c2_descendants = $c2->getDescendants();

        $this->assertNotContains($c1->toArray(), $c2_descendants->toArray());
        $this->assertNotContains($c2->toArray(), $c2_descendants->toArray());
        $this->assertContains($c3->toArray(), $c2_descendants->toArray());
        $this->assertContains($c4->toArray(), $c2_descendants->toArray());
        $this->assertContains($c5->toArray(), $c2_descendants->toArray());
        $this->assertNotContains($c6->toArray(), $c2_descendants->toArray());
        $this->assertNotContains($c7->toArray(), $c2_descendants->toArray());
        $this->assertNotContains($c8->toArray(), $c2_descendants->toArray());
    }

    /** @test */
    public function it_can_return_flat_tree()
    {
        Category::factory()->createMany([
            ["name" => "Category 2"],
            ["name" => "Category 3"],
            ["name" => "Category 4"],
            ["name" => "Category 5"],
            ["name" => "Category 6"],
            ["name" => "Category 7", "parent_id" => 3],
            ["name" => "Category 8", "parent_id" => 3],
            ["name" => "Category 9", "parent_id" => 3],
            ["name" => "Category 10", "parent_id" => 3],
            ["name" => "Category 11", "parent_id" => 5],
            ["name" => "Category 12", "parent_id" => 5],
            ["name" => "Category 13", "parent_id" => 6],
            ["name" => "Category 14", "parent_id" => 2],
            ["name" => "Category 15", "parent_id" => 2],
            ["name" => "Category 16", "parent_id" => 10],
            ["name" => "Category 17", "parent_id" => 10],
            ["name" => "Category 18", "parent_id" => 10]
        ]);

        $flatTree = Category::getFlatTree()->toArray();
        $c2 = Category::where('name', 'Category 2')->first();
        $c3 = Category::where('name', 'Category 3')->first();
        $c4 = Category::where('name', 'Category 4')->first();
        $c5 = Category::where('name', 'Category 5')->first();
        $c6 = Category::where('name', 'Category 6')->first();
        $c7 = Category::where('name', 'Category 7')->first();
        $c8 = Category::where('name', 'Category 8')->first();
        $c9 = Category::where('name', 'Category 9')->first();
        $c10 = Category::where('name', 'Category 10')->first();
        $c11 = Category::where('name', 'Category 11')->first();
        $c12 = Category::where('name', 'Category 12')->first();
        $c13 = Category::where('name', 'Category 13')->first();
        $c14 = Category::where('name', 'Category 14')->first();
        $c15 = Category::where('name', 'Category 15')->first();
        $c16 = Category::where('name', 'Category 16')->first();
        $c17 = Category::where('name', 'Category 17')->first();
        $c18 = Category::where('name', 'Category 18')->first();

        $this->assertEquals($c2->toArray(), $flatTree[0]);
        $this->assertEquals($c14->toArray(), $flatTree[1]);
        $this->assertEquals($c15->toArray(), $flatTree[2]);
        $this->assertEquals($c2->id, $c14->parent_id);
        $this->assertEquals($c2->id, $c15->parent_id);
        //
        $this->assertEquals($c3->toArray(), $flatTree[3]);
        $this->assertEquals($c7->toArray(), $flatTree[4]);
        $this->assertEquals($c8->toArray(), $flatTree[5]);
        $this->assertEquals($c9->toArray(), $flatTree[6]);
        $this->assertEquals($c10->toArray(), $flatTree[7]);
        $this->assertEquals($c16->toArray(), $flatTree[8]);
        $this->assertEquals($c17->toArray(), $flatTree[9]);
        $this->assertEquals($c18->toArray(), $flatTree[10]);
        $this->assertEquals($c3->id, $c7->parent_id);
        $this->assertEquals($c3->id, $c8->parent_id);
        $this->assertEquals($c3->id, $c9->parent_id);
        $this->assertEquals($c3->id, $c10->parent_id);
        $this->assertEquals($c10->id, $c16->parent_id);
        $this->assertEquals($c10->id, $c17->parent_id);
        $this->assertEquals($c10->id, $c18->parent_id);
        //
        $this->assertEquals($c4->toArray(), $flatTree[11]);
        //
        $this->assertEquals($c5->toArray(), $flatTree[12]);
        $this->assertEquals($c11->toArray(), $flatTree[13]);
        $this->assertEquals($c12->toArray(), $flatTree[14]);
        $this->assertEquals($c5->id, $c11->parent_id);
        $this->assertEquals($c5->id, $c12->parent_id);
        //
        $this->assertEquals($c6->toArray(), $flatTree[15]);
        $this->assertEquals($c13->toArray(), $flatTree[16]);
        $this->assertEquals($c6->id, $c13->parent_id);
    }

    /** @test */
    public function it_can_return_nested_tree()
    {
        Category::factory()->createMany([
            ["name" => "Category 2"],
            ["name" => "Category 3"],
            ["name" => "Category 4"],
            ["name" => "Category 5"],
            ["name" => "Category 6"],
            ["name" => "Category 7", "parent_id" => 3],
            ["name" => "Category 8", "parent_id" => 3],
            ["name" => "Category 9", "parent_id" => 3],
            ["name" => "Category 10", "parent_id" => 3],
            ["name" => "Category 11", "parent_id" => 5],
            ["name" => "Category 12", "parent_id" => 5],
            ["name" => "Category 13", "parent_id" => 6],
            ["name" => "Category 14", "parent_id" => 2],
            ["name" => "Category 15", "parent_id" => 2],
            ["name" => "Category 16", "parent_id" => 10],
            ["name" => "Category 17", "parent_id" => 10],
            ["name" => "Category 18", "parent_id" => 10]
        ]);

        $tree = Category::getTree();
        $c2 = Category::where('name', 'Category 2')->first();
        $c3 = Category::where('name', 'Category 3')->first();
        $c4 = Category::where('name', 'Category 4')->first();
        $c5 = Category::where('name', 'Category 5')->first();
        $c6 = Category::where('name', 'Category 6')->first();
        $c7 = Category::where('name', 'Category 7')->first();
        $c8 = Category::where('name', 'Category 8')->first();
        $c9 = Category::where('name', 'Category 9')->first();
        $c10 = Category::where('name', 'Category 10')->first();
        $c11 = Category::where('name', 'Category 11')->first();
        $c12 = Category::where('name', 'Category 12')->first();
        $c13 = Category::where('name', 'Category 13')->first();
        $c14 = Category::where('name', 'Category 14')->first();
        $c15 = Category::where('name', 'Category 15')->first();
        $c16 = Category::where('name', 'Category 16')->first();
        $c17 = Category::where('name', 'Category 17')->first();
        $c18 = Category::where('name', 'Category 18')->first();

        $this->assertEquals($c2->id, $tree[0]->id);
        $this->assertEquals($c3->id, $tree[1]->id);
        $this->assertEquals($c4->id, $tree[2]->id);
        $this->assertEquals($c5->id, $tree[3]->id);
        $this->assertEquals($c6->id, $tree[4]->id);

        $this->assertEquals($c2->children[0]->toArray(), $c14->toArray());
        $this->assertEquals($c2->children[1]->toArray(), $c15->toArray());
        //
        $this->assertEquals($c3->children[0]->toArray(), $c7->toArray());
        $this->assertEquals($c3->children[1]->toArray(), $c8->toArray());
        $this->assertEquals($c3->children[2]->toArray(), $c9->toArray());
        $this->assertEquals($c3->children[3]->toArray(), $c10->toArray());
        //
        $this->assertEquals($c10->children[0]->toArray(), $c16->toArray());
        $this->assertEquals($c10->children[1]->toArray(), $c17->toArray());
        $this->assertEquals($c10->children[2]->toArray(), $c18->toArray());
        //
        $this->assertEquals($c5->children[0]->toArray(), $c11->toArray());
        $this->assertEquals($c5->children[1]->toArray(), $c12->toArray());
        //
        $this->assertEquals($c6->children[0]->toArray(), $c13->toArray());
    }

    /** @test */
    public function it_can_return_nested_tree_with_root_node()
    {
        Category::factory()->createMany([
            ["name" => "Category 2"],
            ["name" => "Category 3"],
            ["name" => "Category 4"],
            ["name" => "Category 5"],
            ["name" => "Category 6"],
            ["name" => "Category 7", "parent_id" => 3],
            ["name" => "Category 8", "parent_id" => 3],
            ["name" => "Category 9", "parent_id" => 3],
            ["name" => "Category 10", "parent_id" => 3],
            ["name" => "Category 11", "parent_id" => 5],
            ["name" => "Category 12", "parent_id" => 5],
            ["name" => "Category 13", "parent_id" => 6],
            ["name" => "Category 14", "parent_id" => 2],
            ["name" => "Category 15", "parent_id" => 2],
            ["name" => "Category 16", "parent_id" => 10],
            ["name" => "Category 17", "parent_id" => 10],
            ["name" => "Category 18", "parent_id" => 10]
        ]);

        $tree = Category::buildNestedTree(Category::withoutGlobalScope('ignore_root')->flattenTree()->get());
        $root = Category::withoutGlobalScope('ignore_root')->find(Category::ROOT_ID);
        $c2 = Category::where('name', 'Category 2')->first();
        $c3 = Category::where('name', 'Category 3')->first();
        $c4 = Category::where('name', 'Category 4')->first();
        $c5 = Category::where('name', 'Category 5')->first();
        $c6 = Category::where('name', 'Category 6')->first();
        $c7 = Category::where('name', 'Category 7')->first();
        $c8 = Category::where('name', 'Category 8')->first();
        $c9 = Category::where('name', 'Category 9')->first();
        $c10 = Category::where('name', 'Category 10')->first();
        $c11 = Category::where('name', 'Category 11')->first();
        $c12 = Category::where('name', 'Category 12')->first();
        $c13 = Category::where('name', 'Category 13')->first();
        $c14 = Category::where('name', 'Category 14')->first();
        $c15 = Category::where('name', 'Category 15')->first();
        $c16 = Category::where('name', 'Category 16')->first();
        $c17 = Category::where('name', 'Category 17')->first();
        $c18 = Category::where('name', 'Category 18')->first();

        $this->assertEquals($root->id, $tree[0]->id);
        $this->assertEquals($root->children[0]->id, $c2->id);
        $this->assertEquals($root->children[1]->id, $c3->id);
        $this->assertEquals($root->children[2]->id, $c4->id);
        $this->assertEquals($root->children[3]->id, $c5->id);
        $this->assertEquals($root->children[4]->id, $c6->id);

        $this->assertEquals($c2->children[0]->toArray(), $c14->toArray());
        $this->assertEquals($c2->children[1]->toArray(), $c15->toArray());
        //
        $this->assertEquals($c3->children[0]->toArray(), $c7->toArray());
        $this->assertEquals($c3->children[1]->toArray(), $c8->toArray());
        $this->assertEquals($c3->children[2]->toArray(), $c9->toArray());
        $this->assertEquals($c3->children[3]->toArray(), $c10->toArray());
        //
        $this->assertEquals($c10->children[0]->toArray(), $c16->toArray());
        $this->assertEquals($c10->children[1]->toArray(), $c17->toArray());
        $this->assertEquals($c10->children[2]->toArray(), $c18->toArray());
        //
        $this->assertEquals($c5->children[0]->toArray(), $c11->toArray());
        $this->assertEquals($c5->children[1]->toArray(), $c12->toArray());
        //
        $this->assertEquals($c6->children[0]->toArray(), $c13->toArray());
    }

    /** @test */
    public function it_can_return_ancestors_tree()
    {
        $c2 = Category::factory()->create(["name" => "Category 2"]);
        $c3 = Category::factory()->create(["name" => "Category 3", "parent_id" => $c2->id]);
        $c4 = Category::factory()->create(["name" => "Category 4", "parent_id" => $c2->id]);
        $c5 = Category::factory()->create(["name" => "Category 5", "parent_id" => $c3->id]);
        $c6 = Category::factory()->create(["name" => "Category 6", "parent_id" => $c3->id]);
        $c7 = Category::factory()->create(["name" => "Category 7", "parent_id" => $c5->id]);
        $c8 = Category::factory()->create(["name" => "Category 8", "parent_id" => $c5->id]);

        $c8->refresh();

        $ancestorsTree = $c8->getAncestorsTree(['id', 'name', 'parent_id', 'lft', 'rgt', 'depth']);

        $this->assertEquals(1, count($ancestorsTree));
        $this->assertEquals($ancestorsTree[0]->id, $c2->id);
        //
        $this->assertEquals(1, count($ancestorsTree[0]->children));
        $this->assertEquals($ancestorsTree[0]->children[0]->id, $c3->id);
        //
        $this->assertEquals(1, count($ancestorsTree[0]->children[0]->children));
        $this->assertEquals($ancestorsTree[0]->children[0]->children[0]->id, $c5->id);
        $this->assertEquals($ancestorsTree[0]->children[0]->children[0]->id, $c8->parent_id);
        //
        $this->assertEquals(0, count($ancestorsTree[0]->children[0]->children[0]->children));
    }

    /** @test */
    public function it_can_return_descendant_nested_tree()
    {
        $c2 = Category::factory()->create(["name" => "Category 2"]);
        $c3 = Category::factory()->create(["name" => "Category 3", "parent_id" => $c2->id]);
        $c4 = Category::factory()->create(["name" => "Category 4", "parent_id" => $c2->id]);
        $c5 = Category::factory()->create(["name" => "Category 5", "parent_id" => $c3->id]);
        $c6 = Category::factory()->create(["name" => "Category 6", "parent_id" => $c3->id]);
        $c7 = Category::factory()->create(["name" => "Category 7", "parent_id" => $c5->id]);
        $c8 = Category::factory()->create(["name" => "Category 8", "parent_id" => $c5->id]);

        $c3->refresh();
        $descendantsTree = $c3->getDescendantsTree(['id', 'name', 'parent_id', 'lft', 'rgt', 'depth']);

        $this->assertEquals(2, count($descendantsTree));
        $this->assertEquals($descendantsTree[0]->id, $c5->id);
        $this->assertEquals($descendantsTree[1]->id, $c6->id);
        //
        $this->assertEquals(2, count($descendantsTree[0]->children));
        $this->assertEquals($descendantsTree[0]->children[0]->id, $c7->id);
        $this->assertEquals($descendantsTree[0]->children[1]->id, $c8->id);

        $this->assertEquals(0, count($descendantsTree[0]->children[0]->children));
        $this->assertEquals(0, count($descendantsTree[0]->children[0]->children));
    }

    /** @test */
    public function it_can_return_leaf_nodes()
    {
        Category::factory()->createMany([
            ["name" => "Category 2"],
            ["name" => "Category 3"],
            ["name" => "Category 4"],
            ["name" => "Category 5", "parent_id" => 2],
            ["name" => "Category 6", "parent_id" => 2],
            ["name" => "Category 7", "parent_id" => 6],
        ]);

        $root = Category::withoutGlobalScope('ignore_root')->find(Category::ROOT_ID);
        $leafNodes = Category::getLeafNodes();
        [$c3, $c4, $c5, $c7] = $leafNodes;
        $this->assertEquals(4, $leafNodes->count());
        $this->assertEquals(['Category 3', 10, 11], [$c3->name, $c3->lft, $c3->rgt]);
        $this->assertEquals(['Category 4', 12, 13], [$c4->name, $c4->lft, $c4->rgt]);
        $this->assertEquals(['Category 5', 3, 4], [$c5->name, $c5->lft, $c5->rgt]);
        $this->assertEquals(['Category 7', 6, 7], [$c7->name, $c7->lft, $c7->rgt]);
        $this->assertEquals([1, 14], [$root->lft, $root->rgt]);
    }

    /** @test */
    public function it_can_calculate_rightly_lft_rgt_depth_for_new_nodes()
    {
        $root = Category::withoutGlobalScope('ignore_root')->find(Category::ROOT_ID);
        $this->assertEquals([1, 2], [$root->lft, $root->rgt]);

        $c2 = Category::factory()->create(["name" => "Category 2"]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 2', 2, 3], [$c2->parent_id, $c2->depth, $c2->name, $c2->lft, $c2->rgt]);
        $root->refresh();
        $this->assertEquals([1, 4], [$root->lft, $root->rgt]);

        Category::factory()->createMany([
            ["name" => "Category 3"],
            ["name" => "Category 4"],
            ["name" => "Category 5"],
            ["name" => "Category 6"],
        ]);

        $root->refresh();
        $categories = Category::all();
        [$c2, $c3, $c4, $c5, $c6] = $categories;

        $this->assertEquals([Category::ROOT_ID, 1, 'Category 2', 2, 3], [$c2->parent_id, $c2->depth, $c2->name, $c2->lft, $c2->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 3', 4, 5], [$c3->parent_id, $c3->depth, $c3->name, $c3->lft, $c3->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 4', 6, 7], [$c4->parent_id, $c4->depth, $c4->name, $c4->lft, $c4->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 5', 8, 9], [$c5->parent_id, $c5->depth, $c5->name, $c5->lft, $c5->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 6', 10, 11], [$c6->parent_id, $c6->depth, $c6->name, $c6->lft, $c6->rgt]);
        $this->assertEquals([Category::ROOT_ID, 12], [$root->lft, $root->rgt]);

        // Check when adding new child nodes to Category 3
        Category::factory()->createMany([
            ["name" => "Category 7", "parent_id" => 3],
            ["name" => "Category 8", "parent_id" => 3],
            ["name" => "Category 9", "parent_id" => 3],
            ["name" => "Category 10", "parent_id" => 3],
        ]);

        $root->refresh();
        $categories = Category::all();
        [$c2, $c3, $c4, $c5, $c6, $c7, $c8, $c9, $c10] = $categories;

        $this->assertEquals([Category::ROOT_ID, 1, 'Category 2', 2, 3, ], [$c2->parent_id, $c2->depth, $c2->name, $c2->lft, $c2->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 3', 4, 13], [$c3->parent_id, $c3->depth, $c3->name, $c3->lft, $c3->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 7', 5, 6], [$c7->parent_id, $c7->depth, $c7->name, $c7->lft, $c7->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 8', 7, 8], [$c8->parent_id, $c8->depth, $c8->name, $c8->lft, $c8->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 9', 9, 10], [$c9->parent_id, $c9->depth, $c9->name, $c9->lft, $c9->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 10', 11, 12], [$c10->parent_id, $c10->depth, $c10->name, $c10->lft, $c10->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 4', 14, 15], [$c4->parent_id, $c4->depth, $c4->name, $c4->lft, $c4->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 5', 16, 17], [$c5->parent_id, $c5->depth, $c5->name, $c5->lft, $c5->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 6', 18, 19], [$c6->parent_id, $c6->depth, $c6->name, $c6->lft, $c6->rgt]);
        $this->assertEquals([1, 20], [$root->lft, $root->rgt]);

        // Check when adding new child nodes to Category 5
        Category::factory()->createMany([
            ["name" => "Category 11", "parent_id" => 5],
            ["name" => "Category 12", "parent_id" => 5],
        ]);

        $root->refresh();
        $categories = Category::all();
        [$c2, $c3, $c4, $c5, $c6, $c7, $c8, $c9, $c10, $c11, $c12] = $categories;

        $this->assertEquals([Category::ROOT_ID, 1, 'Category 2', 2, 3], [$c2->parent_id, $c2->depth, $c2->name, $c2->lft, $c2->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 3', 4, 13], [$c3->parent_id, $c3->depth, $c3->name, $c3->lft, $c3->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 7', 5, 6], [$c7->parent_id, $c7->depth, $c7->name, $c7->lft, $c7->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 8', 7, 8], [$c8->parent_id, $c8->depth, $c8->name, $c8->lft, $c8->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 9', 9, 10], [$c9->parent_id, $c9->depth, $c9->name, $c9->lft, $c9->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 10', 11, 12], [$c10->parent_id, $c10->depth, $c10->name, $c10->lft, $c10->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 4', 14, 15], [$c4->parent_id, $c4->depth, $c4->name, $c4->lft, $c4->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 5', 16, 21], [$c5->parent_id, $c5->depth, $c5->name, $c5->lft, $c5->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 11', 17, 18], [$c11->parent_id, $c11->depth, $c11->name, $c11->lft, $c11->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 12', 19, 20], [$c12->parent_id, $c12->depth, $c12->name, $c12->lft, $c12->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 6', 22, 23], [$c6->parent_id, $c6->depth, $c6->name, $c6->lft, $c6->rgt]);
        $this->assertEquals([1, 24], [$root->lft, $root->rgt]);

        // Check when adding new child nodes to Category 6
        Category::factory()->createMany([
            ["name" => "Category 13", "parent_id" => 6]
        ]);

        $root->refresh();
        $categories = Category::all();
        [$c2, $c3, $c4, $c5, $c6, $c7, $c8, $c9, $c10, $c11, $c12, $c13] = $categories;

        $this->assertEquals([Category::ROOT_ID, 1, 'Category 2', 2, 3], [$c2->parent_id, $c2->depth, $c2->name, $c2->lft, $c2->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 3', 4, 13], [$c3->parent_id, $c3->depth, $c3->name, $c3->lft, $c3->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 7', 5, 6], [$c7->parent_id, $c7->depth, $c7->name, $c7->lft, $c7->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 8', 7, 8], [$c8->parent_id, $c8->depth, $c8->name, $c8->lft, $c8->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 9', 9, 10], [$c9->parent_id, $c9->depth, $c9->name, $c9->lft, $c9->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 10', 11, 12], [$c10->parent_id, $c10->depth, $c10->name, $c10->lft, $c10->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 4', 14, 15], [$c4->parent_id, $c4->depth, $c4->name, $c4->lft, $c4->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 5', 16, 21], [$c5->parent_id, $c5->depth, $c5->name, $c5->lft, $c5->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 11', 17, 18], [$c11->parent_id, $c11->depth, $c11->name, $c11->lft, $c11->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 12', 19, 20], [$c12->parent_id, $c12->depth, $c12->name, $c12->lft, $c12->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 6', 22, 25], [$c6->parent_id, $c6->depth, $c6->name, $c6->lft, $c6->rgt]);
        $this->assertEquals([$c6->id, 2, 'Category 13', 23, 24], [$c13->parent_id, $c13->depth, $c13->name, $c13->lft, $c13->rgt]);
        $this->assertEquals([1, 26], [$root->lft, $root->rgt]);

        // Check when adding new child nodes to Category 2
        Category::factory()->createMany([
            ["name" => "Category 14", "parent_id" => 2],
            ["name" => "Category 15", "parent_id" => 2],
        ]);

        $root->refresh();
        $categories = Category::all();
        [$c2, $c3, $c4, $c5, $c6, $c7, $c8, $c9, $c10, $c11, $c12, $c13, $c14, $c15] = $categories;

        $this->assertEquals([Category::ROOT_ID, 1, 'Category 2', 2, 7], [$c2->parent_id, $c2->depth, $c2->name, $c2->lft, $c2->rgt]);
        $this->assertEquals([$c2->id, 2, 'Category 14', 3, 4], [$c14->parent_id, $c14->depth, $c14->name, $c14->lft, $c14->rgt]);
        $this->assertEquals([$c2->id, 2, 'Category 15', 5, 6], [$c15->parent_id, $c15->depth, $c15->name, $c15->lft, $c15->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 3', 8, 17], [$c3->parent_id, $c3->depth, $c3->name, $c3->lft, $c3->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 7', 9, 10], [$c7->parent_id, $c7->depth, $c7->name, $c7->lft, $c7->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 8', 11, 12], [$c8->parent_id, $c8->depth, $c8->name, $c8->lft, $c8->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 9', 13, 14], [$c9->parent_id, $c9->depth, $c9->name, $c9->lft, $c9->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 10', 15, 16], [$c10->parent_id, $c10->depth, $c10->name, $c10->lft, $c10->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 4', 18, 19], [$c4->parent_id, $c4->depth, $c4->name, $c4->lft, $c4->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 5', 20, 25], [$c5->parent_id, $c5->depth, $c5->name, $c5->lft, $c5->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 11', 21, 22], [$c11->parent_id, $c11->depth, $c11->name, $c11->lft, $c11->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 12', 23, 24], [$c12->parent_id, $c12->depth, $c12->name, $c12->lft, $c12->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 6', 26, 29], [$c6->parent_id, $c6->depth, $c6->name, $c6->lft, $c6->rgt]);
        $this->assertEquals([$c6->id, 2, 'Category 13', 27, 28], [$c13->parent_id, $c13->depth, $c13->name, $c13->lft, $c13->rgt]);
        $this->assertEquals([1, 30], [$root->lft, $root->rgt]);

        // Check when adding new child nodes to Category 10
        Category::factory()->createMany([
            ["name" => "Category 16", "parent_id" => 10],
            ["name" => "Category 17", "parent_id" => 10],
            ["name" => "Category 18", "parent_id" => 10]
        ]);

        $root->refresh();
        $categories = Category::all();
        [$c2, $c3, $c4, $c5, $c6, $c7, $c8, $c9, $c10, $c11, $c12, $c13, $c14, $c15, $c16, $c17, $c18] = $categories;

        $this->assertEquals([Category::ROOT_ID, 1, 'Category 2', 2, 7], [$c2->parent_id, $c2->depth, $c2->name, $c2->lft, $c2->rgt]);
        $this->assertEquals([$c2->id, 2, 'Category 14', 3, 4], [$c14->parent_id, $c14->depth, $c14->name, $c14->lft, $c14->rgt]);
        $this->assertEquals([$c2->id, 2, 'Category 15', 5, 6], [$c15->parent_id, $c15->depth, $c15->name, $c15->lft, $c15->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 3', 8, 23], [$c3->parent_id, $c3->depth, $c3->name, $c3->lft, $c3->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 7', 9, 10], [$c7->parent_id, $c7->depth, $c7->name, $c7->lft, $c7->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 8', 11, 12], [$c8->parent_id, $c8->depth, $c8->name, $c8->lft, $c8->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 9', 13, 14], [$c9->parent_id, $c9->depth, $c9->name, $c9->lft, $c9->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 10', 15, 22], [$c10->parent_id, $c10->depth, $c10->name, $c10->lft, $c10->rgt]);
        $this->assertEquals([$c10->id, 3, 'Category 16', 16, 17], [$c16->parent_id, $c16->depth, $c16->name, $c16->lft, $c16->rgt]);
        $this->assertEquals([$c10->id, 3, 'Category 17', 18, 19], [$c17->parent_id, $c17->depth, $c17->name, $c17->lft, $c17->rgt]);
        $this->assertEquals([$c10->id, 3, 'Category 18', 20, 21], [$c18->parent_id, $c18->depth, $c18->name, $c18->lft, $c18->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 4', 24, 25], [$c4->parent_id, $c4->depth, $c4->name, $c4->lft, $c4->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 5', 26, 31], [$c5->parent_id, $c5->depth, $c5->name, $c5->lft, $c5->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 11', 27, 28], [$c11->parent_id, $c11->depth, $c11->name, $c11->lft, $c11->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 12', 29, 30], [$c12->parent_id, $c12->depth, $c12->name, $c12->lft, $c12->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 6', 32, 35], [$c6->parent_id, $c6->depth, $c6->name, $c6->lft, $c6->rgt]);
        $this->assertEquals([$c6->id, 2, 'Category 13', 33, 34], [$c13->parent_id, $c13->depth, $c13->name, $c13->lft, $c13->rgt]);
        $this->assertEquals([1, 36], [$root->lft, $root->rgt]);
    }

    /** @test */
    public function it_can_calculate_rightly_lft_rgt_depth_when_update()
    {
        $root = Category::withoutGlobalScope('ignore_root')->find(Category::ROOT_ID);
        $this->assertEquals([1, 2], [$root->lft, $root->rgt]);

        Category::factory()->createMany([
            ["name" => "Category 2"],
            ["name" => "Category 3"],
            ["name" => "Category 4"],
            ["name" => "Category 5"],
            ["name" => "Category 6"],
            ["name" => "Category 7", "parent_id" => 3],
            ["name" => "Category 8", "parent_id" => 3],
            ["name" => "Category 9", "parent_id" => 3],
            ["name" => "Category 10", "parent_id" => 3],
            ["name" => "Category 11", "parent_id" => 5],
            ["name" => "Category 12", "parent_id" => 5],
            ["name" => "Category 13", "parent_id" => 6],
            ["name" => "Category 14", "parent_id" => 2],
            ["name" => "Category 15", "parent_id" => 2],
            ["name" => "Category 16", "parent_id" => 10],
            ["name" => "Category 17", "parent_id" => 10],
            ["name" => "Category 18", "parent_id" => 10]
        ]);

        $root->refresh();
        $categories = Category::all();
        [$c2, $c3, $c4, $c5, $c6, $c7, $c8, $c9, $c10, $c11, $c12, $c13, $c14, $c15, $c16, $c17, $c18] = $categories;

        $this->assertEquals([Category::ROOT_ID, 1, 'Category 2', 2, 7], [$c2->parent_id, $c2->depth, $c2->name, $c2->lft, $c2->rgt]);
        $this->assertEquals([$c2->id, 2, 'Category 14', 3, 4], [$c14->parent_id, $c14->depth, $c14->name, $c14->lft, $c14->rgt]);
        $this->assertEquals([$c2->id, 2, 'Category 15', 5, 6], [$c15->parent_id, $c15->depth, $c15->name, $c15->lft, $c15->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 3', 8, 23], [$c3->parent_id, $c3->depth, $c3->name, $c3->lft, $c3->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 7', 9, 10], [$c7->parent_id, $c7->depth, $c7->name, $c7->lft, $c7->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 8', 11, 12], [$c8->parent_id, $c8->depth, $c8->name, $c8->lft, $c8->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 9', 13, 14], [$c9->parent_id, $c9->depth, $c9->name, $c9->lft, $c9->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 10', 15, 22], [$c10->parent_id, $c10->depth, $c10->name, $c10->lft, $c10->rgt]);
        $this->assertEquals([$c10->id, 3, 'Category 16', 16, 17], [$c16->parent_id, $c16->depth, $c16->name, $c16->lft, $c16->rgt]);
        $this->assertEquals([$c10->id, 3, 'Category 17', 18, 19], [$c17->parent_id, $c17->depth, $c17->name, $c17->lft, $c17->rgt]);
        $this->assertEquals([$c10->id, 3, 'Category 18', 20, 21], [$c18->parent_id, $c18->depth, $c18->name, $c18->lft, $c18->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 4', 24, 25], [$c4->parent_id, $c4->depth, $c4->name, $c4->lft, $c4->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 5', 26, 31], [$c5->parent_id, $c5->depth, $c5->name, $c5->lft, $c5->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 11', 27, 28], [$c11->parent_id, $c11->depth, $c11->name, $c11->lft, $c11->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 12', 29, 30], [$c12->parent_id, $c12->depth, $c12->name, $c12->lft, $c12->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 6', 32, 35], [$c6->parent_id, $c6->depth, $c6->name, $c6->lft, $c6->rgt]);
        $this->assertEquals([$c6->id, 2, 'Category 13', 33, 34], [$c13->parent_id, $c13->depth, $c13->name, $c13->lft, $c13->rgt]);
        $this->assertEquals([1, 36], [$root->lft, $root->rgt]);

        // Move Category 10 from Category 3 into Category 2
        $c10->parent_id = $c2->id;
        $c10->save();

        $root->refresh();
        $categories = Category::all();
        [$c2, $c3, $c4, $c5, $c6, $c7, $c8, $c9, $c10, $c11, $c12, $c13, $c14, $c15, $c16, $c17, $c18] = $categories;

        $this->assertEquals([Category::ROOT_ID, 1, 'Category 2', 2, 15], [$c2->parent_id, $c2->depth, $c2->name, $c2->lft, $c2->rgt]);
        $this->assertEquals([$c2->id, 2, 'Category 14', 3, 4], [$c14->parent_id, $c14->depth, $c14->name, $c14->lft, $c14->rgt]);
        $this->assertEquals([$c2->id, 2, 'Category 15', 5, 6], [$c15->parent_id, $c15->depth, $c15->name, $c15->lft, $c15->rgt]);
        $this->assertEquals([$c2->id, 2, 'Category 10', 7, 14], [$c10->parent_id, $c10->depth, $c10->name, $c10->lft, $c10->rgt]);
        $this->assertEquals([$c10->id, 3, 'Category 16', 8, 9], [$c16->parent_id, $c16->depth, $c16->name, $c16->lft, $c16->rgt]);
        $this->assertEquals([$c10->id, 3, 'Category 17', 10, 11], [$c17->parent_id, $c17->depth, $c17->name, $c17->lft, $c17->rgt]);
        $this->assertEquals([$c10->id, 3, 'Category 18', 12, 13], [$c18->parent_id, $c18->depth, $c18->name, $c18->lft, $c18->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 3', 16, 23], [$c3->parent_id, $c3->depth, $c3->name, $c3->lft, $c3->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 7', 17, 18], [$c7->parent_id, $c7->depth, $c7->name, $c7->lft, $c7->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 8', 19, 20], [$c8->parent_id, $c8->depth, $c8->name, $c8->lft, $c8->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 9', 21, 22], [$c9->parent_id, $c9->depth, $c9->name, $c9->lft, $c9->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 4', 24, 25], [$c4->parent_id, $c4->depth, $c4->name, $c4->lft, $c4->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 5', 26, 31], [$c5->parent_id, $c5->depth, $c5->name, $c5->lft, $c5->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 11', 27, 28], [$c11->parent_id, $c11->depth, $c11->name, $c11->lft, $c11->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 12', 29, 30], [$c12->parent_id, $c12->depth, $c12->name, $c12->lft, $c12->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 6', 32, 35], [$c6->parent_id, $c6->depth, $c6->name, $c6->lft, $c6->rgt]);
        $this->assertEquals([$c6->id, 2, 'Category 13', 33, 34], [$c13->parent_id, $c13->depth, $c13->name, $c13->lft, $c13->rgt]);
        $this->assertEquals([1, 36], [$root->lft, $root->rgt]);

        // Move Category 2 into Category 4
        $c2->parent_id = $c4->id;
        $c2->save();

        $root->refresh();
        $categories = Category::all();
        [$c2, $c3, $c4, $c5, $c6, $c7, $c8, $c9, $c10, $c11, $c12, $c13, $c14, $c15, $c16, $c17, $c18] = $categories;

        $this->assertEquals([Category::ROOT_ID, 1, 'Category 3', 2, 9], [$c3->parent_id, $c3->depth ,$c3->name, $c3->lft, $c3->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 7', 3, 4], [$c7->parent_id, $c7->depth ,$c7->name, $c7->lft, $c7->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 8', 5, 6], [$c8->parent_id, $c8->depth ,$c8->name, $c8->lft, $c8->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 9', 7, 8], [$c9->parent_id, $c9->depth ,$c9->name, $c9->lft, $c9->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 4', 10, 25], [$c4->parent_id, $c4->depth ,$c4->name, $c4->lft, $c4->rgt]);
        $this->assertEquals([$c4->id, 2, 'Category 2', 11, 24], [$c2->parent_id, $c2->depth ,$c2->name, $c2->lft, $c2->rgt]);
        $this->assertEquals([$c2->id, 3, 'Category 14', 12, 13], [$c14->parent_id, $c14->depth ,$c14->name, $c14->lft, $c14->rgt]);
        $this->assertEquals([$c2->id, 3, 'Category 15', 14, 15], [$c15->parent_id, $c15->depth ,$c15->name, $c15->lft, $c15->rgt]);
        $this->assertEquals([$c2->id, 3, 'Category 10', 16, 23], [$c10->parent_id, $c10->depth ,$c10->name, $c10->lft, $c10->rgt]);
        $this->assertEquals([$c10->id, 4, 'Category 16', 17, 18], [$c16->parent_id, $c16->depth ,$c16->name, $c16->lft, $c16->rgt]);
        $this->assertEquals([$c10->id, 4, 'Category 17', 19, 20], [$c17->parent_id, $c17->depth ,$c17->name, $c17->lft, $c17->rgt]);
        $this->assertEquals([$c10->id, 4, 'Category 18', 21, 22], [$c18->parent_id, $c18->depth ,$c18->name, $c18->lft, $c18->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 5', 26, 31], [$c5->parent_id, $c5->depth ,$c5->name, $c5->lft, $c5->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 11', 27, 28], [$c11->parent_id, $c11->depth ,$c11->name, $c11->lft, $c11->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 12', 29, 30], [$c12->parent_id, $c12->depth ,$c12->name, $c12->lft, $c12->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 6', 32, 35], [$c6->parent_id, $c6->depth ,$c6->name, $c6->lft, $c6->rgt]);
        $this->assertEquals([$c6->id, 2, 'Category 13', 33, 34], [$c13->parent_id, $c13->depth ,$c13->name, $c13->lft, $c13->rgt]);
        $this->assertEquals([1, 36], [$root->lft, $root->rgt]);

        // Move Category 4 into Category 6
        $c4->parent_id = $c6->id;
        $c4->save();

        $root->refresh();
        $categories = Category::all();
        [$c2, $c3, $c4, $c5, $c6, $c7, $c8, $c9, $c10, $c11, $c12, $c13, $c14, $c15, $c16, $c17, $c18] = $categories;

        $this->assertEquals([Category::ROOT_ID, 1, 'Category 3', 2, 9], [$c3->parent_id, $c3->depth, $c3->name, $c3->lft, $c3->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 7', 3, 4], [$c7->parent_id, $c7->depth, $c7->name, $c7->lft, $c7->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 8', 5, 6], [$c8->parent_id, $c8->depth, $c8->name, $c8->lft, $c8->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 9', 7, 8], [$c9->parent_id, $c9->depth, $c9->name, $c9->lft, $c9->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 5', 10, 15], [$c5->parent_id, $c5->depth, $c5->name, $c5->lft, $c5->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 11', 11, 12], [$c11->parent_id, $c11->depth, $c11->name, $c11->lft, $c11->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 12', 13, 14], [$c12->parent_id, $c12->depth, $c12->name, $c12->lft, $c12->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 6', 16, 35], [$c6->parent_id, $c6->depth, $c6->name, $c6->lft, $c6->rgt]);
        $this->assertEquals([$c6->id, 2, 'Category 13', 17, 18], [$c13->parent_id, $c13->depth, $c13->name, $c13->lft, $c13->rgt]);
        $this->assertEquals([$c6->id, 2, 'Category 4', 19, 34], [$c4->parent_id, $c4->depth, $c4->name, $c4->lft, $c4->rgt]);
        $this->assertEquals([$c4->id, 3, 'Category 2', 20, 33], [$c2->parent_id, $c2->depth, $c2->name, $c2->lft, $c2->rgt]);
        $this->assertEquals([$c2->id, 4, 'Category 14', 21, 22], [$c14->parent_id, $c14->depth, $c14->name, $c14->lft, $c14->rgt]);
        $this->assertEquals([$c2->id, 4, 'Category 15', 23, 24], [$c15->parent_id, $c15->depth, $c15->name, $c15->lft, $c15->rgt]);
        $this->assertEquals([$c2->id, 4, 'Category 10', 25, 32], [$c10->parent_id, $c10->depth, $c10->name, $c10->lft, $c10->rgt]);
        $this->assertEquals([$c10->id, 5, 'Category 16', 26, 27], [$c16->parent_id, $c16->depth, $c16->name, $c16->lft, $c16->rgt]);
        $this->assertEquals([$c10->id, 5, 'Category 17', 28, 29], [$c17->parent_id, $c17->depth, $c17->name, $c17->lft, $c17->rgt]);
        $this->assertEquals([$c10->id, 5, 'Category 18', 30, 31], [$c18->parent_id, $c18->depth, $c18->name, $c18->lft, $c18->rgt]);
        $this->assertEquals([1, 36], [$root->lft, $root->rgt]);

        // Move Category 6 into Category 5
        $c6->parent_id = $c5->id;
        $c6->save();

        $root->refresh();
        $categories = Category::all();
        [$c2, $c3, $c4, $c5, $c6, $c7, $c8, $c9, $c10, $c11, $c12, $c13, $c14, $c15, $c16, $c17, $c18] = $categories;

        $this->assertEquals([Category::ROOT_ID, 1, 'Category 3', 2, 9], [$c3->parent_id, $c3->depth, $c3->name, $c3->lft, $c3->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 7', 3, 4], [$c7->parent_id, $c7->depth, $c7->name, $c7->lft, $c7->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 8', 5, 6], [$c8->parent_id, $c8->depth, $c8->name, $c8->lft, $c8->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 9', 7, 8], [$c9->parent_id, $c9->depth, $c9->name, $c9->lft, $c9->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 5', 10, 35], [$c5->parent_id, $c5->depth, $c5->name, $c5->lft, $c5->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 11', 11, 12], [$c11->parent_id, $c11->depth, $c11->name, $c11->lft, $c11->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 12', 13, 14], [$c12->parent_id, $c12->depth, $c12->name, $c12->lft, $c12->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 6', 15, 34], [$c6->parent_id, $c6->depth, $c6->name, $c6->lft, $c6->rgt]);
        $this->assertEquals([$c6->id, 3, 'Category 13', 16, 17], [$c13->parent_id, $c13->depth, $c13->name, $c13->lft, $c13->rgt]);
        $this->assertEquals([$c6->id, 3, 'Category 4', 18, 33], [$c4->parent_id, $c4->depth, $c4->name, $c4->lft, $c4->rgt]);
        $this->assertEquals([$c4->id, 4, 'Category 2', 19, 32], [$c2->parent_id, $c2->depth, $c2->name, $c2->lft, $c2->rgt]);
        $this->assertEquals([$c2->id, 5, 'Category 14', 20, 21], [$c14->parent_id, $c14->depth, $c14->name, $c14->lft, $c14->rgt]);
        $this->assertEquals([$c2->id, 5, 'Category 15', 22, 23], [$c15->parent_id, $c15->depth, $c15->name, $c15->lft, $c15->rgt]);
        $this->assertEquals([$c2->id, 5, 'Category 10', 24, 31], [$c10->parent_id, $c10->depth, $c10->name, $c10->lft, $c10->rgt]);
        $this->assertEquals([$c10->id, 6, 'Category 16', 25, 26], [$c16->parent_id, $c16->depth, $c16->name, $c16->lft, $c16->rgt]);
        $this->assertEquals([$c10->id, 6, 'Category 17', 27, 28], [$c17->parent_id, $c17->depth, $c17->name, $c17->lft, $c17->rgt]);
        $this->assertEquals([$c10->id, 6, 'Category 18', 29, 30], [$c18->parent_id, $c18->depth, $c18->name, $c18->lft, $c18->rgt]);
        $this->assertEquals([1, 36], [$root->lft, $root->rgt]);

        // remove parent of Category 10, expect root node to be automatically assigned instead
        $c10->parent_id = null;
        $c10->save();

        $root->refresh();
        $categories = Category::all();
        [$c2, $c3, $c4, $c5, $c6, $c7, $c8, $c9, $c10, $c11, $c12, $c13, $c14, $c15, $c16, $c17, $c18] = $categories;

        $this->assertEquals([Category::ROOT_ID, 1, 'Category 3', 2, 9], [$c3->parent_id, $c3->depth, $c3->name, $c3->lft, $c3->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 7', 3, 4], [$c7->parent_id, $c7->depth, $c7->name, $c7->lft, $c7->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 8', 5, 6], [$c8->parent_id, $c8->depth, $c8->name, $c8->lft, $c8->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 9', 7, 8], [$c9->parent_id, $c9->depth, $c9->name, $c9->lft, $c9->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 5', 10, 27], [$c5->parent_id, $c5->depth, $c5->name, $c5->lft, $c5->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 11', 11, 12], [$c11->parent_id, $c11->depth, $c11->name, $c11->lft, $c11->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 12', 13, 14], [$c12->parent_id, $c12->depth, $c12->name, $c12->lft, $c12->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 6', 15, 26], [$c6->parent_id, $c6->depth, $c6->name, $c6->lft, $c6->rgt]);
        $this->assertEquals([$c6->id, 3, 'Category 13', 16, 17], [$c13->parent_id, $c13->depth, $c13->name, $c13->lft, $c13->rgt]);
        $this->assertEquals([$c6->id, 3, 'Category 4', 18, 25], [$c4->parent_id, $c4->depth, $c4->name, $c4->lft, $c4->rgt]);
        $this->assertEquals([$c4->id, 4, 'Category 2', 19, 24], [$c2->parent_id, $c2->depth, $c2->name, $c2->lft, $c2->rgt]);
        $this->assertEquals([$c2->id, 5, 'Category 14', 20, 21], [$c14->parent_id, $c14->depth, $c14->name, $c14->lft, $c14->rgt]);
        $this->assertEquals([$c2->id, 5, 'Category 15', 22, 23], [$c15->parent_id, $c15->depth, $c15->name, $c15->lft, $c15->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 10', 28, 35], [$c10->parent_id, $c10->depth, $c10->name, $c10->lft, $c10->rgt]);
        $this->assertEquals([$c10->id, 2, 'Category 16', 29, 30], [$c16->parent_id, $c16->depth, $c16->name, $c16->lft, $c16->rgt]);
        $this->assertEquals([$c10->id, 2, 'Category 17', 31, 32], [$c17->parent_id, $c17->depth, $c17->name, $c17->lft, $c17->rgt]);
        $this->assertEquals([$c10->id, 2, 'Category 18', 33, 34], [$c18->parent_id, $c18->depth, $c18->name, $c18->lft, $c18->rgt]);
        $this->assertEquals([1, 36], [$root->lft, $root->rgt]);
    }

    /** @test */
    public function it_can_assign_correct_parent_id_for_children_nodes_when_delete()
    {
        $root = Category::withoutGlobalScope('ignore_root')->find(Category::ROOT_ID);
        $this->assertEquals([1, 2], [$root->lft, $root->rgt]);

        $c2 = Category::factory()->createOne(["name" => "Category 2"]);
        $c3 = Category::factory()->createOne(["name" => "Category 3", "parent_id" => $c2->id]);
        $c4 = Category::factory()->createOne(["name" => "Category 4", "parent_id" => $c3->id]);
        $c5 = Category::factory()->createOne(["name" => "Category 5", "parent_id" => $c4->id]);

        $root->refresh();
        $c2->refresh();
        $c3->refresh();
        $c4->refresh();
        $c5->refresh();

        $this->assertEquals([1, 10], [$root->lft, $root->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 2', 2, 9], [$c2->parent_id, $c2->depth, $c2->name, $c2->lft, $c2->rgt]);
        $this->assertEquals([$c2->id, 2, 'Category 3', 3, 8], [$c3->parent_id, $c3->depth, $c3->name, $c3->lft, $c3->rgt]);
        $this->assertEquals([$c3->id, 3, 'Category 4', 4, 7], [$c4->parent_id, $c4->depth, $c4->name, $c4->lft, $c4->rgt]);
        $this->assertEquals([$c4->id, 4, 'Category 5', 5, 6], [$c5->parent_id, $c5->depth, $c5->name, $c5->lft, $c5->rgt]);

        /**
         * Xóa node c2, kỳ vọng node c3 sẽ được kế thừa parent_id của c2
         * và các node con cháu của c2 được tính toán left, right đúng
         */
        $c2->delete();

        $root->refresh();
        $c3->refresh();
        $c4->refresh();
        $c5->refresh();

        $this->assertEquals([1, 8], [$root->lft, $root->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 3', 2, 7], [$c3->parent_id, $c3->depth, $c3->name, $c3->lft, $c3->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 4', 3, 6], [$c4->parent_id, $c4->depth, $c4->name, $c4->lft, $c4->rgt]);
        $this->assertEquals([$c4->id, 3, 'Category 5', 4, 5], [$c5->parent_id, $c5->depth, $c5->name, $c5->lft, $c5->rgt]);
    }

    /** @test */
    public function it_can_calculate_rightly_lft_rgt_depth_when_delete()
    {
        $root = Category::withoutGlobalScope('ignore_root')->find(Category::ROOT_ID);
        $this->assertEquals([1, 2], [$root->lft, $root->rgt]);

        Category::factory()->createMany([
            ["name" => "Category 2"],
            ["name" => "Category 3"],
            ["name" => "Category 4"],
            ["name" => "Category 5"],
            ["name" => "Category 6"],
            ["name" => "Category 7", "parent_id" => 3],
            ["name" => "Category 8", "parent_id" => 3],
            ["name" => "Category 9", "parent_id" => 3],
            ["name" => "Category 10", "parent_id" => 3],
            ["name" => "Category 11", "parent_id" => 5],
            ["name" => "Category 12", "parent_id" => 5],
            ["name" => "Category 13", "parent_id" => 6],
            ["name" => "Category 14", "parent_id" => 2],
            ["name" => "Category 15", "parent_id" => 2],
            ["name" => "Category 16", "parent_id" => 10],
            ["name" => "Category 17", "parent_id" => 10],
            ["name" => "Category 18", "parent_id" => 10]
        ]);

        $root->refresh();
        $categories = Category::all();
        [$c2, $c3, $c4, $c5, $c6, $c7, $c8, $c9, $c10, $c11, $c12, $c13, $c14, $c15, $c16, $c17, $c18] = $categories;

        $this->assertEquals([Category::ROOT_ID, 1, 'Category 2', 2, 7], [$c2->parent_id, $c2->depth, $c2->name, $c2->lft, $c2->rgt]);
        $this->assertEquals([$c2->id, 2, 'Category 14', 3, 4], [$c14->parent_id, $c14->depth, $c14->name, $c14->lft, $c14->rgt]);
        $this->assertEquals([$c2->id, 2, 'Category 15', 5, 6], [$c15->parent_id, $c15->depth, $c15->name, $c15->lft, $c15->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 3', 8, 23], [$c3->parent_id, $c3->depth, $c3->name, $c3->lft, $c3->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 7', 9, 10], [$c7->parent_id, $c7->depth, $c7->name, $c7->lft, $c7->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 8', 11, 12], [$c8->parent_id, $c8->depth, $c8->name, $c8->lft, $c8->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 9', 13, 14], [$c9->parent_id, $c9->depth, $c9->name, $c9->lft, $c9->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 10', 15, 22], [$c10->parent_id, $c10->depth, $c10->name, $c10->lft, $c10->rgt]);
        $this->assertEquals([$c10->id, 3, 'Category 16', 16, 17], [$c16->parent_id, $c16->depth, $c16->name, $c16->lft, $c16->rgt]);
        $this->assertEquals([$c10->id, 3, 'Category 17', 18, 19], [$c17->parent_id, $c17->depth, $c17->name, $c17->lft, $c17->rgt]);
        $this->assertEquals([$c10->id, 3, 'Category 18', 20, 21], [$c18->parent_id, $c18->depth, $c18->name, $c18->lft, $c18->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 4', 24, 25], [$c4->parent_id, $c4->depth, $c4->name, $c4->lft, $c4->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 5', 26, 31], [$c5->parent_id, $c5->depth, $c5->name, $c5->lft, $c5->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 11', 27, 28], [$c11->parent_id, $c11->depth, $c11->name, $c11->lft, $c11->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 12', 29, 30], [$c12->parent_id, $c12->depth, $c12->name, $c12->lft, $c12->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 6', 32, 35], [$c6->parent_id, $c6->depth, $c6->name, $c6->lft, $c6->rgt]);
        $this->assertEquals([$c6->id, 2, 'Category 13', 33, 34], [$c13->parent_id, $c13->depth, $c13->name, $c13->lft, $c13->rgt]);
        $this->assertEquals([1, 36], [$root->lft, $root->rgt]);

        // Delete Category 10, expect Category 16, 17, 18 to be moved to Category 3
        $c10->delete();
        $root->refresh();
        $categories->each(function ($category) { $category->refresh(); });

        $this->assertEquals([Category::ROOT_ID, 1, 'Category 2', 2, 7], [$c2->parent_id, $c2->depth, $c2->name, $c2->lft, $c2->rgt]);
        $this->assertEquals([$c2->id, 2, 'Category 14', 3, 4], [$c14->parent_id, $c14->depth, $c14->name, $c14->lft, $c14->rgt]);
        $this->assertEquals([$c2->id, 2, 'Category 15', 5, 6], [$c15->parent_id, $c15->depth, $c15->name, $c15->lft, $c15->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 3', 8, 21], [$c3->parent_id, $c3->depth, $c3->name, $c3->lft, $c3->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 7', 9, 10], [$c7->parent_id, $c7->depth, $c7->name, $c7->lft, $c7->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 8', 11, 12], [$c8->parent_id, $c8->depth, $c8->name, $c8->lft, $c8->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 9', 13, 14], [$c9->parent_id, $c9->depth, $c9->name, $c9->lft, $c9->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 16', 15, 16], [$c16->parent_id, $c16->depth, $c16->name, $c16->lft, $c16->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 17', 17, 18], [$c17->parent_id, $c17->depth, $c17->name, $c17->lft, $c17->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 18', 19, 20], [$c18->parent_id, $c18->depth, $c18->name, $c18->lft, $c18->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 4', 22, 23], [$c4->parent_id, $c4->depth, $c4->name, $c4->lft, $c4->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 5', 24, 29], [$c5->parent_id, $c5->depth, $c5->name, $c5->lft, $c5->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 11', 25, 26], [$c11->parent_id, $c11->depth, $c11->name, $c11->lft, $c11->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 12', 27, 28], [$c12->parent_id, $c12->depth, $c12->name, $c12->lft, $c12->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 6', 30, 33], [$c6->parent_id, $c6->depth, $c6->name, $c6->lft, $c6->rgt]);
        $this->assertEquals([$c6->id, 2, 'Category 13', 31, 32], [$c13->parent_id, $c13->depth, $c13->name, $c13->lft, $c13->rgt]);
        $this->assertEquals([1, 34], [$root->lft, $root->rgt]);

        // Delete Category 2, 3, 4, 5, 6
        $c2->delete();
        $c3->delete();
        $c4->delete();
        $c5->delete();
        $c6->delete();
        $root->refresh();
        $categories->each(function ($category) { $category->refresh(); });

        $this->assertEquals([Category::ROOT_ID, 1, 'Category 14', 2, 3], [$c14->parent_id, $c14->depth, $c14->name, $c14->lft, $c14->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 15', 4, 5], [$c15->parent_id, $c15->depth, $c15->name, $c15->lft, $c15->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 7', 6, 7], [$c7->parent_id, $c7->depth, $c7->name, $c7->lft, $c7->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 8', 8, 9], [$c8->parent_id, $c8->depth, $c8->name, $c8->lft, $c8->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 9', 10, 11], [$c9->parent_id, $c9->depth, $c9->name, $c9->lft, $c9->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 16', 12, 13], [$c16->parent_id, $c16->depth, $c16->name, $c16->lft, $c16->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 17', 14, 15], [$c17->parent_id, $c17->depth, $c17->name, $c17->lft, $c17->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 18', 16, 17], [$c18->parent_id, $c18->depth, $c18->name, $c18->lft, $c18->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 11', 18, 19], [$c11->parent_id, $c11->depth, $c11->name, $c11->lft, $c11->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 12', 20, 21], [$c12->parent_id, $c12->depth, $c12->name, $c12->lft, $c12->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 13', 22, 23], [$c13->parent_id, $c13->depth, $c13->name, $c13->lft, $c13->rgt]);
        $this->assertEquals([1, 24], [$root->lft, $root->rgt]);
    }

    /** @test */
    public function it_can_calculate_rightly_lft_rgt_depth_when_soft_delete()
    {
        $root = CategorySoftDelete::withoutGlobalScope('ignore_root')->find(CategorySoftDelete::ROOT_ID);
        $this->assertEquals([1, 2], [$root->lft, $root->rgt]);

        CategorySoftDelete::factory()->createMany([
            ["name" => "Category 2"],
            ["name" => "Category 3"],
            ["name" => "Category 4"],
            ["name" => "Category 5"],
            ["name" => "Category 6"],
            ["name" => "Category 7", "parent_id" => 3],
            ["name" => "Category 8", "parent_id" => 3],
            ["name" => "Category 9", "parent_id" => 3],
            ["name" => "Category 10", "parent_id" => 3],
            ["name" => "Category 11", "parent_id" => 5],
            ["name" => "Category 12", "parent_id" => 5],
            ["name" => "Category 13", "parent_id" => 6],
            ["name" => "Category 14", "parent_id" => 2],
            ["name" => "Category 15", "parent_id" => 2],
            ["name" => "Category 16", "parent_id" => 10],
            ["name" => "Category 17", "parent_id" => 10],
            ["name" => "Category 18", "parent_id" => 10]
        ]);

        $root->refresh();
        $categories = CategorySoftDelete::all();
        [$c2, $c3, $c4, $c5, $c6, $c7, $c8, $c9, $c10, $c11, $c12, $c13, $c14, $c15, $c16, $c17, $c18] = $categories;

        $this->assertEquals([CategorySoftDelete::ROOT_ID, 1, 'Category 2', 2, 7], [$c2->parent_id, $c2->depth, $c2->name, $c2->lft, $c2->rgt]);
        $this->assertEquals([$c2->id, 2, 'Category 14', 3, 4], [$c14->parent_id, $c14->depth, $c14->name, $c14->lft, $c14->rgt]);
        $this->assertEquals([$c2->id, 2, 'Category 15', 5, 6], [$c15->parent_id, $c15->depth, $c15->name, $c15->lft, $c15->rgt]);
        $this->assertEquals([CategorySoftDelete::ROOT_ID, 1, 'Category 3', 8, 23], [$c3->parent_id, $c3->depth, $c3->name, $c3->lft, $c3->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 7', 9, 10], [$c7->parent_id, $c7->depth, $c7->name, $c7->lft, $c7->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 8', 11, 12], [$c8->parent_id, $c8->depth, $c8->name, $c8->lft, $c8->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 9', 13, 14], [$c9->parent_id, $c9->depth, $c9->name, $c9->lft, $c9->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 10', 15, 22], [$c10->parent_id, $c10->depth, $c10->name, $c10->lft, $c10->rgt]);
        $this->assertEquals([$c10->id, 3, 'Category 16', 16, 17], [$c16->parent_id, $c16->depth, $c16->name, $c16->lft, $c16->rgt]);
        $this->assertEquals([$c10->id, 3, 'Category 17', 18, 19], [$c17->parent_id, $c17->depth, $c17->name, $c17->lft, $c17->rgt]);
        $this->assertEquals([$c10->id, 3, 'Category 18', 20, 21], [$c18->parent_id, $c18->depth, $c18->name, $c18->lft, $c18->rgt]);
        $this->assertEquals([CategorySoftDelete::ROOT_ID, 1, 'Category 4', 24, 25], [$c4->parent_id, $c4->depth, $c4->name, $c4->lft, $c4->rgt]);
        $this->assertEquals([CategorySoftDelete::ROOT_ID, 1, 'Category 5', 26, 31], [$c5->parent_id, $c5->depth, $c5->name, $c5->lft, $c5->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 11', 27, 28], [$c11->parent_id, $c11->depth, $c11->name, $c11->lft, $c11->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 12', 29, 30], [$c12->parent_id, $c12->depth, $c12->name, $c12->lft, $c12->rgt]);
        $this->assertEquals([CategorySoftDelete::ROOT_ID, 1, 'Category 6', 32, 35], [$c6->parent_id, $c6->depth, $c6->name, $c6->lft, $c6->rgt]);
        $this->assertEquals([$c6->id, 2, 'Category 13', 33, 34], [$c13->parent_id, $c13->depth, $c13->name, $c13->lft, $c13->rgt]);
        $this->assertEquals([1, 36], [$root->lft, $root->rgt]);

        // Delete Category 10, expect Category 16, 17, 18 to be moved to Category 3
        $c10->delete();
        $root->refresh();
        $categories = CategorySoftDelete::withTrashed()->get();
        [$c2, $c3, $c4, $c5, $c6, $c7, $c8, $c9, $c10, $c11, $c12, $c13, $c14, $c15, $c16, $c17, $c18] = $categories;

        $this->assertSoftDeleted($c10);
        $this->assertEquals([CategorySoftDelete::ROOT_ID, 1, 'Category 2', 2, 7], [$c2->parent_id, $c2->depth, $c2->name, $c2->lft, $c2->rgt]);
        $this->assertEquals([$c2->id, 2, 'Category 14', 3, 4], [$c14->parent_id, $c14->depth, $c14->name, $c14->lft, $c14->rgt]);
        $this->assertEquals([$c2->id, 2, 'Category 15', 5, 6], [$c15->parent_id, $c15->depth, $c15->name, $c15->lft, $c15->rgt]);
        $this->assertEquals([CategorySoftDelete::ROOT_ID, 1, 'Category 3', 8, 21], [$c3->parent_id, $c3->depth, $c3->name, $c3->lft, $c3->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 7', 9, 10], [$c7->parent_id, $c7->depth, $c7->name, $c7->lft, $c7->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 8', 11, 12], [$c8->parent_id, $c8->depth, $c8->name, $c8->lft, $c8->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 9', 13, 14], [$c9->parent_id, $c9->depth, $c9->name, $c9->lft, $c9->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 16', 15, 16], [$c16->parent_id, $c16->depth, $c16->name, $c16->lft, $c16->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 17', 17, 18], [$c17->parent_id, $c17->depth, $c17->name, $c17->lft, $c17->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 18', 19, 20], [$c18->parent_id, $c18->depth, $c18->name, $c18->lft, $c18->rgt]);
        $this->assertEquals([CategorySoftDelete::ROOT_ID, 1, 'Category 4', 22, 23], [$c4->parent_id, $c4->depth, $c4->name, $c4->lft, $c4->rgt]);
        $this->assertEquals([CategorySoftDelete::ROOT_ID, 1, 'Category 5', 24, 29], [$c5->parent_id, $c5->depth, $c5->name, $c5->lft, $c5->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 11', 25, 26], [$c11->parent_id, $c11->depth, $c11->name, $c11->lft, $c11->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 12', 27, 28], [$c12->parent_id, $c12->depth, $c12->name, $c12->lft, $c12->rgt]);
        $this->assertEquals([CategorySoftDelete::ROOT_ID, 1, 'Category 6', 30, 33], [$c6->parent_id, $c6->depth, $c6->name, $c6->lft, $c6->rgt]);
        $this->assertEquals([$c6->id, 2, 'Category 13', 31, 32], [$c13->parent_id, $c13->depth, $c13->name, $c13->lft, $c13->rgt]);
        $this->assertEquals([1, 34], [$root->lft, $root->rgt]);

        // Delete Category 2, 3, 4, 5, 6
        $c2->delete();
        $c3->delete();
        $c4->delete();
        $c5->delete();
        $c6->delete();

        $root->refresh();
        $categories = CategorySoftDelete::withTrashed()->get();
        [$c2, $c3, $c4, $c5, $c6, $c7, $c8, $c9, $c10, $c11, $c12, $c13, $c14, $c15, $c16, $c17, $c18] = $categories;

        $this->assertSoftDeleted($c10);
        $this->assertSoftDeleted($c2);
        $this->assertEquals([CategorySoftDelete::ROOT_ID, 1, 'Category 14', 2, 3], [$c14->parent_id, $c14->depth, $c14->name, $c14->lft, $c14->rgt]);
        $this->assertEquals([CategorySoftDelete::ROOT_ID, 1, 'Category 15', 4, 5], [$c15->parent_id, $c15->depth, $c15->name, $c15->lft, $c15->rgt]);

        $this->assertSoftDeleted($c3);
        $this->assertEquals([CategorySoftDelete::ROOT_ID, 1, 'Category 7', 6, 7], [$c7->parent_id, $c7->depth, $c7->name, $c7->lft, $c7->rgt]);
        $this->assertEquals([CategorySoftDelete::ROOT_ID, 1, 'Category 8', 8, 9], [$c8->parent_id, $c8->depth, $c8->name, $c8->lft, $c8->rgt]);
        $this->assertEquals([CategorySoftDelete::ROOT_ID, 1, 'Category 9', 10, 11], [$c9->parent_id, $c9->depth, $c9->name, $c9->lft, $c9->rgt]);
        $this->assertEquals([CategorySoftDelete::ROOT_ID, 1, 'Category 16', 12, 13], [$c16->parent_id, $c16->depth, $c16->name, $c16->lft, $c16->rgt]);
        $this->assertEquals([CategorySoftDelete::ROOT_ID, 1, 'Category 17', 14, 15], [$c17->parent_id, $c17->depth, $c17->name, $c17->lft, $c17->rgt]);
        $this->assertEquals([CategorySoftDelete::ROOT_ID, 1, 'Category 18', 16, 17], [$c18->parent_id, $c18->depth, $c18->name, $c18->lft, $c18->rgt]);

        $this->assertSoftDeleted($c4);
        $this->assertSoftDeleted($c5);
        $this->assertEquals([CategorySoftDelete::ROOT_ID, 1, 'Category 11', 18, 19], [$c11->parent_id, $c11->depth, $c11->name, $c11->lft, $c11->rgt]);
        $this->assertEquals([CategorySoftDelete::ROOT_ID, 1, 'Category 12', 20, 21], [$c12->parent_id, $c12->depth, $c12->name, $c12->lft, $c12->rgt]);

        $this->assertSoftDeleted($c6);
        $this->assertEquals([CategorySoftDelete::ROOT_ID, 1, 'Category 13', 22, 23], [$c13->parent_id, $c13->depth, $c13->name, $c13->lft, $c13->rgt]);
        $this->assertEquals([1, 24], [$root->lft, $root->rgt]);
    }

    /** @test */
    public function it_can_return_correct_data_when_update()
    {
        $c2 = Category::factory()->create(["name" => "Category 2"]);
        $c3 = Category::factory()->create(["name" => "Category 3"]);
        $c3->name = "Category 333";
        $c3->parent_id = $c2->id;
        $c3->save();
        $this->assertEquals("Category 333", $c3->name);
        $this->assertEquals(3, $c3->lft);
        $this->assertEquals(4, $c3->rgt);
        $this->assertEquals(2, $c3->depth);
    }

    /** @test */
    public function it_can_fix_tree()
    {
        $data = [
            ["name" => "Category 2", "slug" => "category-2", "parent_id" => Category::rootId()],
            ["name" => "Category 3", "slug" => "category-3", "parent_id" => Category::rootId()],
            ["name" => "Category 4", "slug" => "category-4", "parent_id" => Category::rootId()],
            ["name" => "Category 5", "slug" => "category-5", "parent_id" => Category::rootId()],
            ["name" => "Category 6", "slug" => "category-6", "parent_id" => Category::rootId()],
            ["name" => "Category 7", "slug" => "category-7", "parent_id" => 3],
            ["name" => "Category 8", "slug" => "category-8", "parent_id" => 3],
            ["name" => "Category 9", "slug" => "category-9", "parent_id" => 3],
            ["name" => "Category 10", "slug" => "category-10", "parent_id" => 3],
            ["name" => "Category 11", "slug" => "category-11", "parent_id" => 5],
            ["name" => "Category 12", "slug" => "category-12", "parent_id" => 5],
            ["name" => "Category 13", "slug" => "category-13", "parent_id" => 6],
            ["name" => "Category 14", "slug" => "category-14", "parent_id" => 2],
            ["name" => "Category 15", "slug" => "category-15", "parent_id" => 2],
            ["name" => "Category 16", "slug" => "category-16", "parent_id" => 10],
            ["name" => "Category 17", "slug" => "category-17", "parent_id" => 10],
            ["name" => "Category 18", "slug" => "category-18", "parent_id" => 10]
        ];

        // foreach(range(1, 10000) as $i) {
        //     $data[] = ["name" => "Category 18 $i", "slug" => "category-18-$i", "parent_id" => 10];
        // }

        Category::insert($data);
        Category::fixTree();
        // Benchmark::dd(fn () => Category::fixTree(), 10);

        $root = Category::withoutGlobalScope('ignore_root')->find(Category::ROOT_ID);
        $categories = Category::all();
        [$c2, $c3, $c4, $c5, $c6, $c7, $c8, $c9, $c10, $c11, $c12, $c13, $c14, $c15, $c16, $c17, $c18] = $categories;

        $this->assertEquals([Category::ROOT_ID, 1, 'Category 2', 2, 7], [$c2->parent_id, $c2->depth, $c2->name, $c2->lft, $c2->rgt]);
        $this->assertEquals([$c2->id, 2, 'Category 14', 3, 4], [$c14->parent_id, $c14->depth, $c14->name, $c14->lft, $c14->rgt]);
        $this->assertEquals([$c2->id, 2, 'Category 15', 5, 6], [$c15->parent_id, $c15->depth, $c15->name, $c15->lft, $c15->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 3', 8, 23], [$c3->parent_id, $c3->depth, $c3->name, $c3->lft, $c3->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 7', 9, 10], [$c7->parent_id, $c7->depth, $c7->name, $c7->lft, $c7->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 8', 11, 12], [$c8->parent_id, $c8->depth, $c8->name, $c8->lft, $c8->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 9', 13, 14], [$c9->parent_id, $c9->depth, $c9->name, $c9->lft, $c9->rgt]);
        $this->assertEquals([$c3->id, 2, 'Category 10', 15, 22], [$c10->parent_id, $c10->depth, $c10->name, $c10->lft, $c10->rgt]);
        $this->assertEquals([$c10->id, 3, 'Category 16', 16, 17], [$c16->parent_id, $c16->depth, $c16->name, $c16->lft, $c16->rgt]);
        $this->assertEquals([$c10->id, 3, 'Category 17', 18, 19], [$c17->parent_id, $c17->depth, $c17->name, $c17->lft, $c17->rgt]);
        $this->assertEquals([$c10->id, 3, 'Category 18', 20, 21], [$c18->parent_id, $c18->depth, $c18->name, $c18->lft, $c18->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 4', 24, 25], [$c4->parent_id, $c4->depth, $c4->name, $c4->lft, $c4->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 5', 26, 31], [$c5->parent_id, $c5->depth, $c5->name, $c5->lft, $c5->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 11', 27, 28], [$c11->parent_id, $c11->depth, $c11->name, $c11->lft, $c11->rgt]);
        $this->assertEquals([$c5->id, 2, 'Category 12', 29, 30], [$c12->parent_id, $c12->depth, $c12->name, $c12->lft, $c12->rgt]);
        $this->assertEquals([Category::ROOT_ID, 1, 'Category 6', 32, 35], [$c6->parent_id, $c6->depth, $c6->name, $c6->lft, $c6->rgt]);
        $this->assertEquals([$c6->id, 2, 'Category 13', 33, 34], [$c13->parent_id, $c13->depth, $c13->name, $c13->lft, $c13->rgt]);
        $this->assertEquals([1, 36], [$root->lft, $root->rgt]);
    }
}