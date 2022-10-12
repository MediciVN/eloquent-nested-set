<?php

namespace MediciVN\EloquentNestedSet;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use MediciVN\EloquentNestedSet\NestedSetModelException;
use MediciVN\EloquentNestedSet\NestedSetModelJob;
use Throwable;

/**
 * Nested Set Model - hierarchies tree
 */
trait NestedSetModel
{
    /**
     * Get custom parent_id column name
     *
     * @return string
     */
    public static function parentIdColumn(): string
    {
        return defined(static::class . '::PARENT_ID') ? static::PARENT_ID : 'parent_id';
    }

    /**
     * Get custom right column name
     *
     * @return string
     */
    public static function rightColumn(): string
    {
        return defined(static::class . '::RIGHT') ? static::RIGHT : 'rgt';
    }

    /**
     * Get custom left column name
     *
     * @return string
     */
    public static function leftColumn(): string
    {
        return defined(static::class . '::LEFT') ? static::LEFT : 'lft';
    }

    /**
     * Get custom depth column name
     *
     * @return string
     */
    public static function depthColumn(): string
    {
        return defined(static::class . '::DEPTH') ? static::DEPTH : 'depth';
    }

    /**
     * Get custom root's id value
     *
     * @return int
     */
    public static function rootId(): int
    {
        return defined(static::class . '::ROOT_ID') ? static::ROOT_ID : 1;
    }

    /**
     * Get queue connection
     *
     * @return string|null
     */
    public static function queueConnection(): string|null
    {
        return defined(static::class . '::QUEUE_CONNECTION') ? static::QUEUE_CONNECTION : null;
    }

    /**
     * Get queue
     *
     * @return string|null
     */
    public static function queue(): string|null
    {
        return defined(static::class . '::QUEUE') ? static::QUEUE : null;
    }

    /**
     * Get queue's afterCommit option
     *
     * @return bool
     */
    public static function queueAfterCommit(): bool
    {
        return defined(static::class . '::QUEUE_AFTER_COMMIT') ? static::QUEUE_AFTER_COMMIT : true;
    }

    /**
     * @return bool
     */
    public static function queueEnabled(): bool
    {
        return !empty(static::queueConnection()) || !empty(static::queue());
    }

    /**
     * Get table name
     *
     * @return string
     */
    public static function tableName(): string
    {
        return (new static)->getTable();
    }

    /**
     * get primary column name
     *
     * @return string
     */
    public static function primaryColumn(): string
    {
        return (new static)->getKeyName();
    }

    /**
     * @return mixed
     */
    public static function rootNode(): mixed
    {
        return static::withoutGlobalScope('ignore_root')->find(static::rootId());
    }

    /**
     * check if 'this' model uses the SoftDeletes trait
     *
     * @return bool
     */
    public static function IsSoftDelete(): bool
    {
        return in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(new static));
    }

    /**
     * @param string $event
     * @param $arguments
     * @return void
     */
    public function handleEvent($event, ...$arguments): void
    {
        $job = new NestedSetModelJob($this, $event, $arguments);

        if (!static::queueEnabled()) {
            $job->handle(); // run immediately
            return;
        }

        if (static::queueConnection()) {
            $job->onConnection(static::queueConnection());
        }
        if (static::queue()) {
            $job->onQueue(static::queue());
        }
        if (static::queueAfterCommit()) {
            $job->afterCommit();
        }

        dispatch($job);
    }

    /**
     * Update tree when CRUD
     *
     * @return void
     * @throws Throwable
     */
    public static function bootNestedSetModel(): void
    {
        // If queue is declared, SoftDelete is required
        if (static::queueEnabled() && !static::IsSoftDelete()) {
            throw new Exception('SoftDelete trait is required if queue is enabled');
        }

        // Ignore root node in global scope
        static::addGlobalScope('ignore_root', function (Builder $builder) {
            $builder->where(static::tableName() . '.' . static::primaryColumn(), '<>', static::rootId());
        });

        // set default parent_id is root's id
        static::saving(function (Model $model) {
            if (empty($model->{static::parentIdColumn()})) {
                $model->{static::parentIdColumn()} = static::rootId();
            }
        });

        static::created(function (Model $model) {
            $model->handleEvent('created');
        });

        static::updated(function (Model $model) {
            $oldParentId = $model->getOriginal(static::parentIdColumn());
            $newParentId = $model->{static::parentIdColumn()};

            if ($oldParentId != $newParentId) {
                // When run with queue, the new lft, rgt and parent_id will be assigned after calculation
                // so keep the old parent_id
                $model->{static::parentIdColumn()} = $oldParentId;
                $model->handleEvent('updated', $newParentId);
            }
        });

        static::deleting(function (Model $model) {
            $model->handleEvent('deleting');
        });
    }

    /**
     * Scope a query to find ancestors.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeAncestors($query)
    {
        return $query
            ->where(static::leftColumn(), '<', $this->{static::leftColumn()})
            ->where(static::rightColumn(), '>', $this->{static::rightColumn()});
    }

    /**
     * Scope a query to find descendants.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeDescendants($query)
    {
        return $query
            ->where(static::leftColumn(), '>', $this->{static::leftColumn()})
            ->where(static::rightColumn(), '<', $this->{static::rightColumn()});
    }

    /**
     * Scope a query to get flatten array
     * Flatten tree: nodes are sorted in order child nodes are sorted after parent node
     *
     * @param $query
     * @return void
     */
    public function scopeFlattenTree($query)
    {
        return $query->orderBy(static::leftColumn(), 'ASC');
    }

    /**
     * Scope a query to find leaf node
     * Leaf nodes: nodes without children, with left = right - 1
     *
     * @param $query
     * @return void
     */
    public function scopeLeafNodes($query)
    {
        return $query->where(static::leftColumn(), '=', DB::raw(static::rightColumn() . " - 1"));
    }

    /**
     * Lấy tất cả các entity cha, sắp xếp theo thứ tự entity cha gần nhất đầu tiên.
     *
     * Các entity cha trong 1 cây sẽ có
     * - left nhỏ hơn left của entity hiện tại
     * - right lớn hơn right của entity hiện tại
     *
     * @param array $columns
     * @return Collection
     */
    public function getAncestors(array $columns = ['*']): Collection
    {
        return $this->ancestors()->orderBy(static::leftColumn(), 'DESC')->get($columns);
    }

    /**
     * Lấy tất cả các entity con
     *
     * Các entity con trong 1 cây sẽ có
     * - left lớn hơn left của entity hiện tại
     * - right nhỏ hơn right của entity hiện tại
     *
     * @param array $columns
     * @return Collection
     */
    public function getDescendants(array $columns = ['*']): Collection
    {
        return $this->descendants()->get($columns);
    }

    /**
     * The parent entity to which the current entity belongs
     *
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, static::parentIdColumn());
    }

    /**
     * The children entity belongs to the current entity
     *
     * @return HasMany
     */
    public function children(): HasMany
    {
        return $this->hasMany(static::class, static::parentIdColumn());
    }

    /**
     * Build a nested tree based on parent's id
     *
     * @param Collection $nodes
     * @return Collection
     */
    public static function buildNestedTree(Collection|array $nodes): Collection
    {
        $tree = $ids = $parentIds = $groupNodes = [];

        foreach ($nodes as $node) {
            $ids[]                   = $node->{static::primaryColumn()};
            $parentId                = $node->{static::parentIdColumn()};
            $parentIds[]             = $parentId;
            $groupNodes[$parentId][] = $node;
        }

        $topParentIds = array_unique(array_diff($parentIds, $ids), SORT_REGULAR);

        foreach ($topParentIds as $topParentId) {
            array_push($tree, ...($groupNodes[$topParentId] ?? []));
        }

        $getChildrenFunc = function ($nodes) use (&$getChildrenFunc, $groupNodes) {
            foreach ($nodes as $node) {
                $node->setRelation('children', collect($groupNodes[$node->{static::primaryColumn()}] ?? []));
                $getChildrenFunc($node->children);
            }
        };

        $getChildrenFunc($tree);

        return collect($tree);
    }

    /**
     * Fix tree base on parent's id
     * 
     * @return void
     */
    public static function fixTree(): void
    {
        $nodes = static::withoutGlobalScope('ignore_root')->get();
        $tree  = static::buildNestedTree($nodes);

        $fixPositionFunc = function ($node) use (&$fixPositionFunc) {
            $children      = $node->children->all();
            $childrenCount = count($children);

            if (!$childrenCount) {
                $node->{static::rightColumn()} = $node->{static::leftColumn()} + 1;
                $node->savePositionQuietly();
                return;
            }

            $firstChild = $children[0];
            $firstChild->{static::depthColumn()} = $node->{static::depthColumn()} + 1;
            $firstChild->{static::leftColumn()}  = $node->{static::leftColumn()} + 1;
            $firstChild->{static::rightColumn()} = $firstChild->{static::leftColumn()} + 1;
            $fixPositionFunc($firstChild);

            for ($i = 1; $i < $childrenCount; $i++) {
                $child = $children[$i];
                $child->{static::depthColumn()} = $node->{static::depthColumn()} + 1;
                $child->{static::leftColumn()}  = $children[$i - 1]->{static::rightColumn()} + 1;
                $child->{static::rightColumn()} = $child->{static::leftColumn()} + 1;
                $fixPositionFunc($child);
            }

            $lastChild = $children[$childrenCount - 1];
            $node->{static::rightColumn()} = $lastChild->{static::rightColumn()} + 1;
            $node->savePositionQuietly();
        };

        $root = $tree[0];
        $root->{static::leftColumn()}  = 1;
        $root->{static::rightColumn()} = 2;
        $root->{static::depthColumn()} = 0;
        $fixPositionFunc($root);
    }

    /**
     * Get all nodes in nested array
     *
     * @param array $columns
     * @return Collection
     */
    public static function getTree(array $columns = ['*']): Collection
    {
        return static::buildNestedTree(static::flattenTree()->get($columns));
    }

    /**
     * Get all nodes order by parent-children relationship in flat array
     *
     * @param array $columns
     * @return Collection
     */
    public static function getFlatTree(array $columns = ['*']): Collection
    {
        return static::flattenTree()->get($columns);
    }

    /**
     * Get all leaf nodes
     *
     * @param array $columns
     * @return mixed
     */
    public static function getLeafNodes(array $columns = ['*'])
    {
        return static::leafNodes()->get($columns);
    }

    /**
     * Get all parent in nested array
     *
     * @param array $columns
     * @return Collection
     */
    public function getAncestorsTree(array $columns = ['*']): Collection
    {
        return static::buildNestedTree($this->ancestors()->get($columns));
    }

    /**
     * Get all descendants in nested array
     *
     * @param array $columns
     * @return Collection
     */
    public function getDescendantsTree(array $columns = ['*']): Collection
    {
        return static::buildNestedTree($this->descendants()->get($columns));
    }

    /**
     * Check given id is a ancestor of current instance
     *
     * @param $id
     * @return bool
     */
    public function hasAncestor($id): bool
    {
        return $this->ancestors()->where(static::primaryColumn(), '=', $id)->exists();
    }

    /**
     * Check given id is a descendant of current instance
     *
     * @param $id
     * @return bool
     */
    public function hasDescendant($id): bool
    {
        return $this->descendants()->where(static::primaryColumn(), '=', $id)->exists();
    }

    /**
     * @return int
     */
    public function getWidth(): int
    {
        return $this->{static::rightColumn()} - $this->{static::leftColumn()} + 1;
    }

    /**
     * Just save position fields of an instance even it has other changes
     * Position fields: lft, rgt, parent_id, depth
     *
     * @return Model
     */
    public function savePositionQuietly(): Model
    {
        static::withoutGlobalScope('ignore_root')
            ->where(static::primaryColumn(), '=', $this->{static::primaryColumn()})
            ->update([
                static::leftColumn()     => $this->{static::leftColumn()},
                static::rightColumn()    => $this->{static::rightColumn()},
                static::parentIdColumn() => $this->{static::parentIdColumn()},
                static::depthColumn()    => $this->{static::depthColumn()},
            ]);

        return $this;
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function handleTreeOnCreated(): void
    {
        try {
            DB::beginTransaction();
            $this->refresh();

            $parent    = static::withoutGlobalScope('ignore_root')->findOrFail($this->{static::parentIdColumn()});
            $parentRgt = $parent->{static::rightColumn()};

            // Tạo khoảng trống cho node hiện tại ở node cha mới, cập nhật các node bên phải của node cha mới
            static::withoutGlobalScope('ignore_root')
                ->where(static::rightColumn(), '>=', $parentRgt)
                ->update([static::rightColumn() => DB::raw(static::rightColumn() . " + 2")]);

            static::where(static::leftColumn(), '>', $parentRgt)
                ->update([static::leftColumn() => DB::raw(static::leftColumn() . " + 2")]);

            // Node mới sẽ được thêm vào sau (bên phải) các nodes cùng cha
            $this->{static::depthColumn()} = $parent->{static::depthColumn()}+1;
            $this->{static::leftColumn()}  = $parentRgt;
            $this->{static::rightColumn()} = $parentRgt + 1;
            $this->savePositionQuietly();
            DB::commit();
        } catch (Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * @param $newParentId
     * @return void
     * @throws Throwable
     */
    public function handleTreeOnUpdated($newParentId): void
    {
        try {
            DB::beginTransaction();
            $this->refresh();

            if ($newParentId == $this->{static::primaryColumn()} || $this->hasDescendant($newParentId)) {
                throw new NestedSetModelException("The given parent's " . static::primaryColumn() . " is invalid");
            }

            $newParent    = static::withoutGlobalScope('ignore_root')->findOrFail($newParentId);
            $currentLft   = $this->{static::leftColumn()};
            $currentRgt   = $this->{static::rightColumn()};
            $currentDepth = $this->{static::depthColumn()};
            $width        = $this->getWidth();
            $query        = static::withoutGlobalScope('ignore_root')->whereNot(static::primaryColumn(), $this->{static::primaryColumn()});

            // Tạm thời để left và right các node con của node hiện tại ở giá trị âm
            $this->descendants()->update([
                static::leftColumn()  => DB::raw(static::leftColumn() . " * (-1)"),
                static::rightColumn() => DB::raw(static::rightColumn() . " * (-1)"),
            ]);

            // Giả định node hiện tại bị xóa khỏi cây, cập nhật các node bên phải của node hiện tại
            (clone $query)
                ->where(static::rightColumn(), '>', $this->{static::rightColumn()})
                ->update([static::rightColumn() => DB::raw(static::rightColumn() . " - $width")]);

            (clone $query)
                ->where(static::leftColumn(), '>', $this->{static::rightColumn()})
                ->update([static::leftColumn() => DB::raw(static::leftColumn() . " - $width")]);

            // Tạo khoảng trống cho node hiện tại ở node cha mới, cập nhật các node bên phải của node cha mới
            $newParent->refresh();
            $newParentRgt = $newParent->{static::rightColumn()};

            (clone $query)
                ->where(static::rightColumn(), '>=', $newParentRgt)
                ->update([static::rightColumn() => DB::raw(static::rightColumn() . " + $width")]);

            (clone $query)
                ->where(static::leftColumn(), '>', $newParentRgt)
                ->update([static::leftColumn() => DB::raw(static::leftColumn() . " + $width")]);

            // Cập nhật lại node hiện tại theo node cha mới
            $this->{static::depthColumn()}    = $newParent->{static::depthColumn()}+1;
            $this->{static::parentIdColumn()} = $newParentId;
            $this->{static::leftColumn()}     = $newParentRgt;
            $this->{static::rightColumn()}    = $newParentRgt + $width - 1;
            $this->savePositionQuietly();

            // Cập nhật lại các node con có left và right âm
            $distance    = $this->{static::rightColumn()} - $currentRgt;
            $depthChange = $this->{static::depthColumn()} - $currentDepth;

            static::where(static::leftColumn(), '<', 0 - $currentLft)
                ->where(static::rightColumn(), '>', 0 - $currentRgt)
                ->update([
                    static::leftColumn()  => DB::raw("ABS(" . static::leftColumn() . ") + $distance"),
                    static::rightColumn() => DB::raw("ABS(" . static::rightColumn() . ") + $distance"),
                    static::depthColumn() => DB::raw(static::depthColumn() . " + $depthChange"),
                ]);

            DB::commit();
        } catch (Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function handleTreeOnDeleting(): void
    {
        try {
            DB::beginTransaction();
            // make sure that no unsaved changes affect the calculation
            $this->refresh();

            // move the child nodes to the parent node of the deleted node
            $this->children()->update([
                static::parentIdColumn() => $this->{static::parentIdColumn()},
            ]);

            $this->descendants()->update([
                static::leftColumn()  => DB::raw(static::leftColumn() . " - 1"),
                static::rightColumn() => DB::raw(static::rightColumn() . " - 1"),
                static::depthColumn() => DB::raw(static::depthColumn() . " - 1"),
            ]);

            // Update the nodes to the right of the deleted node
            static::withoutGlobalScope('ignore_root')
                ->where(static::rightColumn(), '>', $this->{static::rightColumn()})
                ->update([static::rightColumn() => DB::raw(static::rightColumn() . " - 2")]);

            static::withoutGlobalScope('ignore_root')
                ->where(static::leftColumn(), '>', $this->{static::rightColumn()})
                ->update([static::leftColumn() => DB::raw(static::leftColumn() . " - 2")]);

            DB::commit();
        } catch (Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
