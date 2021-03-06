<?php

declare(strict_types=1);

namespace Umbrellio\LTree\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\JoinClause;
use Umbrellio\LTree\Interfaces\LTreeModelInterface;

class BelongsToTree extends Relation
{
    protected $throughRelationName;
    private $foreignKey;
    private $ownerKey;

    public function __construct(
        Builder $query,
        Model $child,
        string $throughRelationName,
        string $foreignKey,
        string $ownerKey
    ) {
        $this->throughRelationName = $throughRelationName;
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;

        parent::__construct($query, $child);
    }

    public function addConstraints(): void
    {
        if (static::$constraints) {
            $relation = $this->parent->{$this->throughRelationName};

            if ($relation) {
                $this->query = $relation->newQuery()->ancestorsOf($relation)->orderBy('path');
            }
        }
    }

    public function addEagerConstraints(array $models): void
    {
        $key = $this->related->getTable() . '.' . $this->ownerKey;

        $whereIn = $this->whereInMethod($this->related, $this->ownerKey);

        $this->query->{$whereIn}($key, $this->getEagerModelKeys($models));

        $table = $this->getModel()->getTable();
        $alias = sprintf('%s_depends', $table);

        $this->query->join(
            sprintf('%s as %s', $table, $alias),
            function (JoinClause $query) use ($alias, $table) {
                /** @var LTreeModelInterface $related */
                $related = $this->parent->{$this->throughRelationName}()->related;

                $query->whereRaw(sprintf(
                    '%1$s.%2$s @> %3$s.%2$s',
                    $alias,
                    $related->getLtreePathColumn(),
                    $table
                ));
            }
        );

        $this->query->orderBy('path');

        $this->query->selectRaw(sprintf('%s.*, %s.%s as relation_id', $alias, $table, $this->ownerKey));
    }

    public function match(array $models, Collection $results, $relation)
    {
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->relation_id][] = $result;
        }

        foreach ($models as $model) {
            foreach ($dictionary as $related => $value) {
                if ($model->getAttribute($this->foreignKey) === $related) {
                    $model->setRelation($relation, $this->related->newCollection($value));
                }
            }
        }

        return $models;
    }

    public function getResults()
    {
        return $this->getParentKey() !== null
            ? $this->query->get()
            : $this->related->newCollection();
    }


    /**
     * Initialize the relation on a set of models.
     *
     * @param array $models
     * @param string $relation
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    protected function getEagerModelKeys(array $models)
    {
        $keys = [];

        foreach ($models as $model) {
            if (($value = $model->{$this->foreignKey}) !== null) {
                $keys[] = $value;
            }
        }

        sort($keys);

        return array_values(array_unique($keys));
    }

    private function getParentKey()
    {
        return $this->parent->{$this->foreignKey};
    }
}
