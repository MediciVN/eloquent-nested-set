# EloquentNestedSet

Automatically update the tree when creating, updating and deleting a node.

How to use:

- First, a root node must be initialized in your model's table
- Add `use NestedSetModel;` to your eloquent model, example:

```injectablephp

use MediciVN\EloquentNestedSet\NestedSetModel;

class Category extends Model
{
    use NestedSetModel;

    // All settings below are optional

    /**
     * The root node id 
     * 
     * Default: 1 
     */
    const ROOT_ID = 99999; 

    /**
     * The left position column name (can be a negative number)
     * 
     * Default: 'lft' 
     */
    const LEFT = 'lft';

    /**
     * The right position column name (can be a negative number)
     * 
     * Default: 'rgt'
     */
    const RIGHT = 'rgt';

    /**
     * The parent's id column name
     * 
     * Default: 'parent_id'
     */
    const PARENT_ID = 'parent_id';

    /**
     * The depth column name
     * The depth of a node - nth descendant, it doesn't affect left and right calculation
     * Starting from the root node will have a depth of 0
     * 
     * Default: 'depth'
     */
    const DEPTH = 'depth';

    /**
     * The queue connection is declared in your project `config/queue.php`.
     * if QUEUE_CONNECTION and QUEUE are not provided, lft and rgt calculation are synchronized.
     * 
     * Default: null
     */
    const QUEUE_CONNECTION = 'sqs';

    /**
     * Default: null
     */
    const QUEUE = 'your_queue';

    /**
     * Default: true
     */
    const QUEUE_AFTER_COMMIT = true;
```

## Functions

- `getTree`: get all nodes and return as `nested array`
- `getFlatTree`: get all nodes and return as `flatten array`, the child nodes will be sorted after the parent node
- `getAncestors`: get all `ancestor` nodes of current instance
- `getAncestorsTree`: get all `ancestor` nodes of current instance and return as `nested array`
- `getDescendants`: get all `descendant` nodes of current instance
- `getDescendantsTree`: get all `descendant` nodes of current instance and return as `nested array`
- `parent`: get the parent node to which the current instance belongs
- `children`: get the child nodes of the current instance
- `getLeafNodes`: get all leaf nodes - nodes with no children

### Other

- `buildNestedTree`: build a nested tree base on `parent_id`

## Query scopes

[Laravel Eloquent Query Scopes](https://laravel.com/docs/9.x/eloquent#query-scopes)

The `root` node is automatically ignored by a global scope of `ignore_root`.
To get the `root` node, use `withoutGlobalScope('ignore_root')`.

- `ancestors`
- `descendants`
- `flattenTree`
- `leafNodes`

## Notes

- `SoftDelete` is required if you use `queue`.
  Because the `queue` will not run in `deleting` and `deleted` events if a record is permanently deleted.

- If you are using `SoftDelete` and intend to stop using it, you must deal with soft deleted records.
  The tree will be shuffled, and the calculation of lft and rgt may go wrong.
