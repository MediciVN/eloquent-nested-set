<?php

namespace MediciVN\EloquentNestedSet;

use Illuminate\Contracts\Database\ModelIdentifier as BaseModelIdentifier;

/**
 * Customize for dynamic table name case, set table name when serializing
 */
class ModelIdentifier extends BaseModelIdentifier {
    /**
     * The table name of the model.
     *
     * @var string|null
     */
    public $table;

    /**
     * Create a new model identifier.
     *
     * @param  string   $class
     * @param  mixed    $id
     * @param  array    $relations
     * @param  mixed    $connection
     * @param  string   $table
     * @return void
     */
    public function __construct($class, $id, array $relations, $connection, $table = null)
    {
        $this->id           = $id;
        $this->class        = $class;
        $this->relations    = $relations;
        $this->connection   = $connection;
        $this->table        = $table;
    }
}
