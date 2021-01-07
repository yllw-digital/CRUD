<?php

namespace Backpack\CRUD\app\Library\CrudPanel\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait Relationships
{
    /**
     * From the field entity we get the relation instance.
     *
     * @param array $entity
     * @return object
     */
    public function getRelationInstance($field)
    {
        $entity = $this->getOnlyRelationEntity($field);
        $entity_array = explode('.', $entity);
        $relation_model = $this->getRelationModel($entity);

        $related_method = Arr::last($entity_array);
        if (count(explode('.', $entity)) == count(explode('.', $field['entity']))) {
            $relation_model = $this->getRelationModel($entity, -1);
        }
        $relation_model = new $relation_model();

        //if counts are diferent means that last element of entity is the field in relation.
        if (count(explode('.', $entity)) != count(explode('.', $field['entity']))) {
            if (in_array($related_method, $relation_model->getFillable())) {
                if (count($entity_array) > 1) {
                    $related_method = $entity_array[(count($entity_array) - 2)];
                    $relation_model = $this->getRelationModel($entity, -2);
                } else {
                    $relation_model = $this->model;
                }
            }
        }
        if (count($entity_array) == 1) {
            if (method_exists($this->model, $related_method)) {
                return $this->model->{$related_method}();
            }
        }

        return $relation_model->{$related_method}();
    }

    /**
     * Get the fields for relationships, according to the relation type. It looks only for direct
     * relations - it will NOT look through relationships of relationships.
     *
     * @param string|array $relation_types Eloquent relation class or array of Eloquent relation classes. Eg: BelongsTo
     *
     * @return array The fields with corresponding relation types.
     */
    public function getFieldsWithRelationType($relation_types): array
    {
        $relation_types = (array) $relation_types;

        return collect($this->fields())
            ->where('model')
            ->whereIn('relation_type', $relation_types)
            ->filter(function ($item) {
                $related_model = get_class($this->model->{Arr::first(explode('.', $item['entity']))}()->getRelated());

                return Str::contains($item['entity'], '.') && $item['model'] !== $related_model ? false : true;
            })
            ->toArray();
    }

    /**
     * Grabs an relation instance and returns the class name of the related model.
     *
     * @param array $field
     * @return string
     */
    public function inferFieldModelFromRelationship($field)
    {
        $relation = $this->getRelationInstance($field);

        return get_class($relation->getRelated());
    }

    /**
     * Return the relation type from a given field: BelongsTo, HasOne ... etc.
     *
     * @param array $field
     * @return string
     */
    public function inferRelationTypeFromRelationship($field)
    {
        $relation = $this->getRelationInstance($field);

        return Arr::last(explode('\\', get_class($relation)));
    }

    /**
     * Parse the field name back to the related entity after the form is submited.
     * Its called in getAllFieldNames().
     *
     * @param array $fields
     * @return array
     */
    public function parseRelationFieldNamesFromHtml($fields)
    {
        foreach ($fields as &$field) {
            //we only want to parse fields that has a relation type and their name contains [ ] used in html.
            if (isset($field['relation_type']) && preg_match('/[\[\]]/', $field['name']) !== 0) {
                $chunks = explode('[', $field['name']);

                foreach ($chunks as &$chunk) {
                    if (strpos($chunk, ']')) {
                        $chunk = str_replace(']', '', $chunk);
                    }
                }
                $field['name'] = implode('.', $chunks);
            }
        }

        return $fields;
    }

    /**
     * Based on relation type returns the default field type.
     *
     * @param string $relation_type
     * @return string
     */
    public function inferFieldTypeFromFieldRelation($field)
    {
        switch ($field['relation_type']) {
            case 'BelongsToMany':
            case 'HasMany':
            case 'HasManyThrough':
            case 'MorphMany':
            case 'MorphToMany':
            case 'BelongsTo':
                return 'relationship';
            default:
                return 'text';
        }
    }

    /**
     * Based on relation type returns if relation allows multiple entities.
     *
     * @param string $relation_type
     * @return bool
     */
    public function guessIfFieldHasMultipleFromRelationType($relation_type)
    {
        switch ($relation_type) {
            case 'BelongsToMany':
            case 'HasMany':
            case 'HasManyThrough':
            case 'HasOneOrMany':
            case 'MorphMany':
            case 'MorphOneOrMany':
            case 'MorphToMany':
                return true;
            default:
                return false;
        }
    }

    /**
     * Based on relation type returns if relation has a pivot table.
     *
     * @param string $relation_type
     * @return bool
     */
    public function guessIfFieldHasPivotFromRelationType($relation_type)
    {
        switch ($relation_type) {
            case 'BelongsToMany':
            case 'MorphToMany':
                return true;
            break;
            default:
                return false;
            break;
        }
    }

    /**
     * Check if field name contains a dot, if so, meaning it's a nested relation.
     *
     * @param array $field
     * @return bool
     */
    protected function isNestedRelation($field): bool
    {
        if (strpos($field['entity'], '.') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Return the relation without any model attributes there.
     * Eg. user.entity_id would return user, as entity_id is not a relation in user.
     *
     * @param array $relation_field
     * @return string
     */
    public function getOnlyRelationEntity($relation_field)
    {
        $entity_array = explode('.', $relation_field['entity']);

        $relation_model = $this->getRelationModel($relation_field['entity'], -1);

        $related_method = Arr::last($entity_array);

        if (! method_exists($relation_model, $related_method)) {
            if (count($entity_array) <= 1) {
                return $relation_field['entity'];
            } else {
                array_pop($entity_array);
            }

            return implode('.', $entity_array);
        }

        return $relation_field['entity'];
    }
}
