<?php

namespace MediciVN\EloquentNestedSet;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\QueueableCollection;
use Illuminate\Contracts\Queue\QueueableEntity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MediciVN\EloquentNestedSet\ModelIdentifier;

class NestedSetModelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The model using NestedSetModel trait
     * 
     * @var Model
     */
    protected Model $model;

    /**
     * @var string
     */
    protected string $event;

    /**
     * @var array
     */
    protected array $arguments;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($model, $event, ...$arguments)
    {
        $this->model = $model;
        $this->event = $event;
        $this->arguments = $arguments;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        match ($this->event) {
            'created'  => $this->model->handleTreeOnCreated(...$this->arguments),
            'updated'  => $this->model->handleTreeOnUpdated(...$this->arguments),
            'deleting' => $this->model->handleTreeOnDeleting(...$this->arguments),
        };
    }

    /**
     * @override Illuminate/Queue/SerializesAndRestoresModelIdentifiers->getSerializedPropertyValue
     * 
     * Get the property value prepared for serialization.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function getSerializedPropertyValue($value)
    {
        if ($value instanceof QueueableCollection) {
            return new ModelIdentifier(
                $value->getQueueableClass(),
                $value->getQueueableIds(),
                $value->getQueueableRelations(),
                $value->getQueueableConnection(),
            );
        }

        if ($value instanceof QueueableEntity) {
            return new ModelIdentifier(
                get_class($value),
                $value->getQueueableId(),
                $value->getQueueableRelations(),
                $value->getQueueableConnection(),
                $value->getTable(),
            );
        }

        return $value;
    }

    /**
     * @override Illuminate/Queue/SerializesAndRestoresModelIdentifiers->restoreModel
     * 
     * Restore the model from the model identifier instance.
     *
     * @param  \Illuminate\Contracts\Database\ModelIdentifier  $identifier
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function restoreModel($identifier)
    {
        $model = (new $identifier->class);
        $model->setConnection($identifier->connection)->setTable($identifier->table);

        return $this
            ->getQueryForModelRestoration($model, $identifier->id)
            ->useWritePdo()
            ->firstOrFail()
            ->load($identifier->relations ?? []);
    }
}
