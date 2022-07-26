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
    public static function queueConnection(): string | null
    {
        return defined(static::class . '::QUEUE_CONNECTION') ? static::QUEUE_CONNECTION : null;
    }

    /**
     * Get queue
     *
     * @return string|null
     */
    public static function queue(): string | null
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
     * @param Collection | array $nodes
     * @return Collection
     */
    public static function buildNestedTree(Collection | array $nodes): Collection
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
                $node->children = $groupNodes[$node->{static::primaryColumn()}] ?? [];
                $getChildrenFunc($node->children);
            }
        };

        $getChildrenFunc($tree);

        return collect($tree);
    }

    /**
     * Build raw query string to update node's position
     * 
     * The left, right and depth must always be integer
     * but can't be sure what their datatypes in the database are used
     * so forcing them to be integer is the way to avoid SQL Injection
     * 
     * @return string
     */
    public function getUpdatePositionSQL(): string
    {
        $table    = $this->getTable();
        $idColumn = $this->getKeyName();
        $id       = (int) $this->{$idColumn};
        $columns  = [
            static::leftColumn() . " = " . ((int) $this->{static::leftColumn()}),
            static::rightColumn() . " = " . ((int) $this->{static::rightColumn()}),
            static::depthColumn() . " = " . ((int) $this->{static::depthColumn()}),
        ];
        $setString = implode(", ", $columns);

        return "UPDATE $table SET $setString WHERE $idColumn = $id";
    }

    /**
     * @param Collection | array $nodes
     * @param int $patch
     * @return void
     */
    public static function bulkUpdatePosition(Collection | array $nodes, int $patch = 1000): void
    {
        $queries = $nodes->map(fn ($node) => $node->getUpdatePositionSQL());
        $chunked = $queries->chunk($patch);

        DB::beginTransaction();
        try {
            foreach ($chunked as $chunkedQueries) {
                DB::unprepared(implode(";", $chunkedQueries->toArray()));
            }
            DB::commit();
        } catch (Throwable $th) {
            DB::rollback();
            throw $th;
        }
    }

    /**
     * Fix tree base on parent's id
     *
     * @param int $patch
     * @return void
     */
    public static function fixTree(int $patch = 1000): void
    {
        $nodes = static::withoutGlobalScope('ignore_root')->get();
        $group = $nodes->groupBy(static::parentIdColumn());
        $fixed = collect();

        $fixPositionFunc = function ($node) use (&$fixPositionFunc, $group, &$fixed) {
            $children = $group->get($node->{static::primaryColumn()}, []);

            if (!($childrenCount = count($children))) {
                $node->{static::rightColumn()} = $node->{static::leftColumn()} + 1;
                $fixed->push($node);
                return;
            }

            $children[0]->{static::depthColumn()} = $node->{static::depthColumn()} + 1;
            $children[0]->{static::leftColumn()}  = $node->{static::leftColumn()} + 1;
            $fixPositionFunc($children[0]);

            for ($i = 1; $i < $childrenCount; $i++) {
                $children[$i]->{static::depthColumn()} = $node->{static::depthColumn()} + 1;
                $children[$i]->{static::leftColumn()}  = $children[$i - 1]->{static::rightColumn()} + 1;
                $fixPositionFunc($children[$i]);
            }

            $node->{static::rightColumn()} = $children[$childrenCount - 1]->{static::rightColumn()} + 1;
            $fixed->push($node);
        };

        $root = $nodes->where(static::primaryColumn(), static::rootId())->first();
        $root->{static::leftColumn()}  = 1;
        $root->{static::depthColumn()} = 0;
        $fixPositionFunc($root);
        static::bulkUpdatePosition($fixed, $patch);
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
     * Just refresh position fields of an instance even it has other changes
     */
    public function refreshPosition(): void
    {
        $fresh = static::withoutGlobalScope('ignore_root')
            ->where(static::primaryColumn(), $this->{static::primaryColumn()})
            ->first();

        $this->{static::parentIdColumn()} = $fresh->{static::parentIdColumn()};
        $this->{static::leftColumn()}     = $fresh->{static::leftColumn()};
        $this->{static::rightColumn()}    = $fresh->{static::rightColumn()};
        $this->{static::depthColumn()}    = $fresh->{static::depthColumn()};
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function handleTreeOnCreated(): void
    {
        try {
            DB::beginTransaction();
            $this->refreshPosition();

            $parent    = static::withoutGlobalScope('ignore_root')->findOrFail($this->{static::parentIdColumn()});
            $parentRgt = $parent->{static::rightColumn()};

            // Tạo khoảng trống cho node hiện tại ở node cha mới, cập nhật các node bên phải của node cha mới
            static::withoutGlobalScope('ignore_root')
                ->where(static::rightColumn(), '>=', $parentRgt)
                ->update([static::rightColumn() => DB::raw(static::rightColumn() . " + 2")]);

            static::where(static::leftColumn(), '>', $parentRgt)
                ->update([static::leftColumn() => DB::raw(static::leftColumn() . " + 2")]);

            // Node mới sẽ được thêm vào sau (bên phải) các nodes cùng cha
            $this->{static::depthColumn()} = $parent->{static::depthColumn()} + 1;
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
            $this->refreshPosition();

            if ($newParentId == $this->{static::primaryColumn()} || $this->hasDescendant($newParentId)) {
                throw new NestedSetModelException("The given parent's " . static::primaryColumn() . " is invalid");
            }

            $newParent   = static::withoutGlobalScope('ignore_root')->findOrFail($newParentId);
            $depthChange = $newParent->{static::depthColumn()} + 1 - $this->{static::depthColumn()};
            $distance    = $newParent->{static::rightColumn()} - 1 - $this->{static::rightColumn()};
            $width       = $this->getWidth();
            $from        = $this->{static::rightColumn()} + 1;
            $to          = $newParent->{static::rightColumn()};

            if ($newParent->{static::rightColumn()} < $this->{static::rightColumn()}) {
                $distance = $distance + $this->getWidth();
                $width    = -$width;
                $from     = $newParent->{static::rightColumn()};
                $to       = $this->{static::leftColumn()};
            }

            // Tạm thời để left và right của node hiện tại và các node con của nó ở giá trị âm
            static::query()
                ->where(static::leftColumn(), '>=', $this->{static::leftColumn()})
                ->where(static::rightColumn(), '<=', $this->{static::rightColumn()})
                ->update([
                    static::leftColumn()  => DB::raw(static::leftColumn() . " * (-1)"),
                    static::rightColumn() => DB::raw(static::rightColumn() . " * (-1)"),
                ]);

            // Cập nhật các nodes trong khoảng thay đổi
            static::withoutGlobalScope('ignore_root')
                ->where(static::rightColumn(), '>=', $from)
                ->where(static::rightColumn(), '<', $to)
                ->update([static::rightColumn() => DB::raw(static::rightColumn() . " - ($width)")]);

            static::withoutGlobalScope('ignore_root')
                ->where(static::leftColumn(), '>=', $from)
                ->where(static::leftColumn(), '<', $to)
                ->update([static::leftColumn() => DB::raw(static::leftColumn() . " - ($width)")]);

            // Cập nhật lại node đang có lft và rgt âm
            static::query()
                ->where(static::leftColumn(), '<=', -$this->{static::leftColumn()})
                ->where(static::rightColumn(), '>=', -$this->{static::rightColumn()})
                ->update([
                    static::leftColumn()  => DB::raw("ABS(" . static::leftColumn() . ") + $distance"),
                    static::rightColumn() => DB::raw("ABS(" . static::rightColumn() . ") + $distance"),
                    static::depthColumn() => DB::raw(static::depthColumn() . " + $depthChange"),
                ]);

            $this->refreshPosition();
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
            $this->refreshPosition();

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
