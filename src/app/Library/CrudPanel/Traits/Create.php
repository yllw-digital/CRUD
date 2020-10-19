<?php

namespace Backpack\CRUD\app\Library\CrudPanel\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;

trait Create
{
    /*
    |--------------------------------------------------------------------------
    |                                   CREATE
    |--------------------------------------------------------------------------
    */

    /**
     * Insert a row in the database.
     *
     * @param array $data All input values to be inserted.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function create($data)
    {
        $data = $this->decodeJsonCastedAttributes($data);
        $data = $this->compactFakeFields($data);

        // omit the n-n relationships when updating the eloquent item
        $nn_relationships = Arr::pluck($this->getRelationFieldsWithPivot(), 'name');
        $item = $this->model->create(Arr::except($data, $nn_relationships));

        // if there are any relationships available, also sync those
        $this->createRelations($item, $data);

        return $item;
    }

    /**
     * Get all fields needed for the ADD NEW ENTRY form.
     *
     * @return array The fields with attributes and fake attributes.
     */
    public function getCreateFields()
    {
        return $this->fields();
    }

    /**
     * Get all fields with relation set (model key set on field).
     *
     * @return array The fields with model key set.
     */
    public function getRelationFields()
    {
        $fields = $this->fields();
        $relationFields = [];

        foreach ($fields as $field) {
            if (isset($field['model']) && $field['model'] !== false) {
                array_push($relationFields, $field);
            }

            if (isset($field['subfields']) &&
                is_array($field['subfields']) &&
                count($field['subfields'])) {
                foreach ($field['subfields'] as $subfield) {
                    array_push($relationFields, $subfield);
                }
            }
        }

        return $relationFields;
    }

    /**
     * Get all fields with n-n relation set (pivot table is true).
     *
     * @return array The fields with n-n relationships.
     */
    public function getRelationFieldsWithPivot()
    {
        $all_relation_fields = $this->getRelationFields();

        return Arr::where($all_relation_fields, function ($value, $key) {
            return isset($value['pivot']) && $value['pivot'];
        });
    }

    /**
     * Create the relations for the current model.
     *
     * @param \Illuminate\Database\Eloquent\Model $item The current CRUD model.
     * @param array                               $data The form data.
     */
    public function createRelations($item, $data)
    {
        $this->syncPivot($item, $data);
        $this->createOneToOneRelations($item, $data);
    }

    /**
     * Sync the declared many-to-many associations through the pivot field.
     *
     * @param \Illuminate\Database\Eloquent\Model $model The current CRUD model.
     * @param array                               $data  The form data.
     */
    public function syncPivot($model, $data)
    {
        $fields_with_relationships = $this->getRelationFields();
        foreach ($fields_with_relationships as $key => $field) {
            if (isset($field['pivot']) && $field['pivot']) {
                $values = isset($data[$field['name']]) ? $data[$field['name']] : [];

                // if a JSON was passed instead of an array, turn it into an array
                if (is_string($values)) {
                    $values = json_decode($values);
                }

                $relation_data = [];
                foreach ($values as $pivot_id) {
                    $pivot_data = [];

                    if (isset($field['pivotFields'])) {
                        foreach ($field['pivotFields'] as $pivot_field_name) {
                            $pivot_data[$pivot_field_name] = $data[$pivot_field_name][$pivot_id];
                        }
                    }
                    $relation_data[$pivot_id] = $pivot_data;
                }

                $model->{$field['name']}()->sync($relation_data);
            }

            if (isset($field['morph']) && $field['morph'] && isset($data[$field['name']])) {
                $values = $data[$field['name']];
                $model->{$field['name']}()->sync($values);
            }
        }
    }

    /**
     * Create any existing one to one relations for the current model from the form data.
     *
     * @param \Illuminate\Database\Eloquent\Model $item The current CRUD model.
     * @param array                               $data The form data.
     */
    private function createOneToOneRelations($item, $data)
    {
        $relationData = $this->getRelationDataFromFormData($data);
        $this->createRelationsForItem($item, $relationData);
    }

    /**
     * Create any existing one to one relations for the current model from the relation data.
     *
     * @param \Illuminate\Database\Eloquent\Model $item          The current CRUD model.
     * @param array                               $formattedData The form data.
     *
     * @return bool|null
     */
    private function createRelationsForItem($item, $formattedData)
    {
        if (! isset($formattedData['relations'])) {
            return false;
        }
        foreach ($formattedData['relations'] as $relationMethod => $relationData) {
            if (! isset($relationData['model'])) {
                continue;
            }
            $model = $relationData['model'];
            $relation = $item->{$relationMethod}();

            if ($relation instanceof BelongsTo) {
                $modelInstance = $model::find($relationData['values'])->first();
                if ($modelInstance != null) {
                    $relation->associate($modelInstance)->save();
                } else {
                    $relation->dissociate()->save();
                }
            } elseif ($relation instanceof HasOne) {
                if ($item->{$relationMethod} != null) {
                    $item->{$relationMethod}->update($relationData['values']);
                    $modelInstance = $item->{$relationMethod};
                } else {
                    $modelInstance = new $model($relationData['values']);
                    $relation->save($modelInstance);
                }
            } elseif ($relation instanceof HasMany) {

                //if it's an HasMany relation and we have fields setup in the main field it means it's a repeatable
                //and developer wants to add the items to the relation.
                if (isset($relationData['fields'])) {
                    $this->createHasManyEntries($item, $relation, $relationMethod, $relationData);
                } else {
                    $this->attachHasManyRelation($item, $relation, $relationMethod, $relationData);
                }
            }

            if (isset($relationData['relations'])) {
                $this->createRelationsForItem($modelInstance, ['relations' => $relationData['relations']]);
            }
        }
    }

    /*
        Used to sync the relations using already stored in database entries.
     */
    public function attachHasManyRelation($item, $relation, $relationMethod, $relationData)
    {
        $modelInstance = $relation->getRelated();
        $relation_column_is_nullable = $modelInstance->isColumnNullable($relation->getForeignKeyName());

        if ($relationData['values'][$relationMethod][0] !== null) {
            //we add the new values into the relation
            $modelInstance->whereIn($modelInstance->getKeyName(), $relationData['values'][$relationMethod])
           ->update([$relation->getForeignKeyName() => $item->{$relation->getLocalKeyName()}]);

            //we clear up any values that were removed from model relation.
            //if developer provided a fallback id, we use it
            //if column is nullable we set it to null
            //if none of the above we delete the model from database
            if ($relationData['fallback_id']) {
                $modelInstance->whereNotIn($modelInstance->getKeyName(), $relationData['values'][$relationMethod])
                            ->where($relation->getForeignKeyName(), $item->{$relation->getLocalKeyName()})
                            ->update([$relation->getForeignKeyName() => $relationData['fallback_id']]);
            } else {
                if (! $relation_column_is_nullable) {
                    $modelInstance->whereNotIn($modelInstance->getKeyName(), $relationData['values'][$relationMethod])
                            ->where($relation->getForeignKeyName(), $item->{$relation->getLocalKeyName()})
                            ->delete();
                } else {
                    $modelInstance->whereNotIn($modelInstance->getKeyName(), $relationData['values'][$relationMethod])
                            ->where($relation->getForeignKeyName(), $item->{$relation->getLocalKeyName()})
                            ->update([$relation->getForeignKeyName() => null]);
                }
            }
        } else {
            //the developer cleared the selection
            //we gonna clear all related values by setting up the value to the fallback id, to null or delete.
            if ($relationData['fallback_id']) {
                $modelInstance->where($relation->getForeignKeyName(), $item->{$relation->getLocalKeyName()})
                            ->update([$relation->getForeignKeyName() => $relationData['fallback_id']]);
            } else {
                if (! $relation_column_is_nullable) {
                    $modelInstance->where($relation->getForeignKeyName(), $item->{$relation->getLocalKeyName()})->delete();
                } else {
                    $modelInstance->where($relation->getForeignKeyName(), $item->{$relation->getLocalKeyName()})
                            ->update([$relation->getForeignKeyName() => null]);
                }
            }
        }
    }

    /*
        Create HasMany entries from an repeatable field type
     */
    public function createHasManyEntries($entry, $relation, $relationMethod, $relationData)
    {
        $items = collect(json_decode($relationData['values'][$relationMethod], true));
        $relatedId = $entry->getKey();
        $relatedModel = $relation->getRelated();
        $itemsInDatabase = $entry->{$relationMethod};
        //if the collection is empty we clear all previous values in database if any.
        if ($items->isEmpty()) {
            $relatedModel->where($relation->getForeignKeyName(), $entry->{$relation->getLocalKeyName()})->delete();
        } else {
            $items->each(function (&$item, $key) use ($relatedId, $relation, $relatedModel) {
                $item[$relation->getForeignKeyName()] = $relatedId;

                if (isset($item[$relatedModel->getKeyName()]) && $item[$relatedModel->getKeyName()] !== '') {
                    $relatedModel->where($relatedModel->getKeyName(), $item[$relation->getLocalKeyName()])->update($item);
                } else {
                    //we are creating, so we unset the model key from request
                    unset($item[$relatedModel->getKeyName()]);

                    $createdItem = $relatedModel->create($item);

                    $item[$relatedModel->getKeyName()] = $createdItem->{$relatedModel->getKeyName()};
                }
            });

            $relatedItemsSent = $items->pluck($relatedModel->getKeyName());

            if (! $relatedItemsSent->isEmpty()) {
                $itemsInDatabase = $entry->{$relationMethod};
                //we perform the cleanup of removed database items
                $itemsInDatabase->each(function ($item, $key) use ($relatedItemsSent) {
                    if (! $relatedItemsSent->contains($item->getKey())) {
                        $item->delete();
                    }
                });
            }
        }
    }

    /**
     * Get a relation data array from the form data.
     * For each relation defined in the fields through the entity attribute, set the model, the parent model and the
     * attribute values.
     *
     * We traverse this relation array later to create the relations, for example:
     *
     * Current model HasOne Address, this Address (line_1, country_id) BelongsTo Country through country_id in Address Model.
     *
     * So when editing current model crud user have two fields address.line_1 and address.country (we infer country_id from relation)
     *
     * Those will be nested accordingly in this relation array, so address relation will have a nested relation with country.
     *
     *
     * @param array $data The form data.
     *
     * @return array The formatted relation data.
     */
    private function getRelationDataFromFormData($data)
    {
        $relation_fields = $this->getRelationFields();
        $relationData = [];
        foreach ($relation_fields as $relation_field) {
            $attributeKey = $this->parseRelationFieldNamesFromHtml([$relation_field])[0]['name'];

            if (! is_null(Arr::get($data, $attributeKey)) && isset($relation_field['pivot']) && $relation_field['pivot'] !== true) {
                $key = implode('.relations.', explode('.', $this->getOnlyRelationEntity($relation_field)));
                $fieldData = Arr::get($relationData, 'relations.'.$key, []);

                $fieldData['model'] = $relation_field['model'];

                $fieldData['parent'] = $this->getRelationModel($attributeKey, -1);

                if (array_key_exists('fallback_id', $relation_field)) {
                    $fieldData['fallback_id'] = $relation_field['fallback_id'];
                }

                if (array_key_exists('fields', $relation_field)) {
                    $fieldData['fields'] = $relation_field['fields'];
                }

                $relatedAttribute = Arr::last(explode('.', $attributeKey));
                $fieldData['values'][$relatedAttribute] = Arr::get($data, $attributeKey);

                Arr::set($relationData, 'relations.'.$key, $fieldData);
            }
        }

        return $relationData;
    }

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
