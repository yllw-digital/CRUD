<?php

namespace Backpack\CRUD\app\Library\CrudPanel\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        $relationships = Arr::pluck($this->getRelationFields(), 'name');

        // init and fill model
        $item = $this->model->make(Arr::except($data, $relationships));

        // handle BelongsTo 1:1 relations
        $item = $this->associateOrDissociateBelongsToRelations($item, $data);
        $item->save();

        // if there are any other relations create them.
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
        $fields_with_relationships = $this->getRelationFieldsWithPivot();
        foreach ($fields_with_relationships as $key => $field) {
            $values = isset($data[$field['name']]) ? $data[$field['name']] : [];

            // if a JSON was passed instead of an array, turn it into an array
            if (is_string($values)) {
                $decoded_values = json_decode($values, true);
                $values = [];
                //array is not multidimensional
                if (count($decoded_values) != count($decoded_values, COUNT_RECURSIVE))  {
                    foreach ($decoded_values as $value) {
                        $values[] = $value[$field['name']];
                    }
                }else{
                    $values = $decoded_values;
                }
            }

            $relation_data = [];

            foreach ($values as $pivot_id) {
                if($pivot_id != '') {
                    $pivot_data = [];

                    if (isset($field['pivotFields'])) {
                        //array is not multidimensional
                        if (count($field['pivotFields']) == count($field['pivotFields'], COUNT_RECURSIVE))  {
                            foreach ($field['pivotFields'] as $pivot_field_name) {
                                $pivot_data[$pivot_field_name] = $data[$pivot_field_name][$pivot_id];
                            }
                        }else{
                            $field_data = json_decode($data[$field['name']], true);

                            //we grab from the parsed data the specific values for this pivot
                            $pivot_data = Arr::first(Arr::where($field_data, function ($item) use ($pivot_id, $field) {
                                return $item[$field['name']] === $pivot_id;
                            }));

                            //we remove the relation field from extra pivot data as we already have the relation.
                            unset($pivot_data[$field['name']]);
                        }
                    }

                    $relation_data[$pivot_id] = $pivot_data;
                }


                $model->{$field['name']}()->sync($relation_data);

                if (isset($field['morph']) && $field['morph'] && isset($data[$field['name']])) {
                    $values = $data[$field['name']];
                    $model->{$field['name']}()->sync($values);
                }
            }
        }
    }

    /**
     * Create any existing one to one relations and subsquent relations for the item.
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
     * Create any existing one to one relations and subsquent relations from form data.
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

            $relation = $item->{$relationMethod}();

            if ($relation instanceof HasOne || $relation instanceof MorphOne) {
                if (isset($relationData['relations'])) {
                    $belongsToRelations = Arr::where($relationData['relations'], function ($relation_data) {
                        return $relation_data['relation_type'] == 'BelongsTo';
                    });
                    // adds the values of the BelongsTo relations of this entity to the array of values that will
                    // be saved at the same time like we do in parent entity belongs to relations
                    $valuesWithRelations = $this->associateHasOneBelongsTo($belongsToRelations, $relationData['values'], $relation->getModel());

                    $relationData['relations'] = Arr::where($relationData['relations'], function ($item) {
                        return $item['relation_type'] != 'BelongsTo';
                    });

                    $modelInstance = $relation->updateOrCreate([], $valuesWithRelations);
                } else {

                    $modelInstance = $relation->updateOrCreate([], $relationData['values']);

                }
            }elseif($relation instanceof HasMany || $relation instanceof MorphMany) {

                $relation_values = $relationData['values'][$relationMethod];

                if(is_string($relation_values)) {
                    $relation_values = json_decode($relationData['values'][$relationMethod], true);
                }

                if (is_null($relation_values) || count($relation_values) == count($relation_values, COUNT_RECURSIVE))  {
                        $this->attachManyRelation($item, $relation, $relationMethod, $relationData, $relation_values);
                }else{
                        $this->createManyEntries($item, $relation, $relationMethod, $relationData);
                }
            }

            if (isset($relationData['relations'])) {
                $this->createRelationsForItem($modelInstance, ['relations' => $relationData['relations']]);
            }
        }
    }

    private function associateHasOneBelongsTo($belongsToRelations, $modelValues, $modelInstance)
    {
        foreach ($belongsToRelations as $methodName => $values) {
            $relation = $modelInstance->{$methodName}();
            $modelValues[$relation->getForeignKeyName()] = $values['values'][$methodName];
        }

        return $modelValues;
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
        // exclude the already attached belongs to relations but include nested belongs to.
        $relation_fields = Arr::where($this->getRelationFields(), function ($field, $key) {
            return $field['relation_type'] !== 'BelongsTo' || $this->isNestedRelation($field);
        });

        $relationData = [];

        foreach ($relation_fields as $relation_field) {
            $attributeKey = $this->parseRelationFieldNamesFromHtml([$relation_field])[0]['name'];
            if (isset($relation_field['pivot']) && $relation_field['pivot'] !== true) {
                $key = implode('.relations.', explode('.', $this->getOnlyRelationEntity($relation_field)));
                $fieldData = Arr::get($relationData, 'relations.'.$key, []);
                if (! array_key_exists('model', $fieldData)) {
                    $fieldData['model'] = $relation_field['model'];
                }
                if (! array_key_exists('parent', $fieldData)) {
                    $fieldData['parent'] = $this->getRelationModel($attributeKey, -1);
                }

                if (array_key_exists('fallback_id', $relation_field)) {
                    $fieldData['fallback_id'] = $relation_field['fallback_id'];
                }

                $fieldData['force_delete'] = $relation_field['force_delete'] ?? false;

                if (! array_key_exists('relation_type', $fieldData)) {
                    $fieldData['relation_type'] = $relation_field['relation_type'];
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

    /**
     * When using the HasMany/MorphMany relations as selectable elements we use this function to sync those relations.
     * Here we allow for different functionality than when creating. Developer could use this relation as a
     * selectable list of items that can belong to one/none entity at any given time.
     *
     * @return void
     */
    public function attachManyRelation($item, $relation, $relationMethod, $relationData, $relation_values)
    {
        $model_instance = $relation->getRelated();
        $force_delete = $relationData['force_delete'];

        $relation_column_is_nullable = $model_instance->isColumnNullable($relation->getForeignKeyName());

        if (!is_null($relation_values) && $relationData['values'][$relationMethod][0] !== null) {
            //we add the new values into the relation
            $model_instance->whereIn($model_instance->getKeyName(), $relation_values)
           ->update([$relation->getForeignKeyName() => $item->{$relation->getLocalKeyName()}]);

            //we clear up any values that were removed from model relation.
            //if developer provided a fallback id, we use it
            //if column is nullable we set it to null
            //if none of the above we delete the model from database
            if (isset($relationData['fallback_id'])) {
                $model_instance->whereNotIn($model_instance->getKeyName(), $relation_values)
                            ->where($relation->getForeignKeyName(), $item->{$relation->getLocalKeyName()})
                            ->update([$relation->getForeignKeyName() => $relationData['fallback_id']]);
            } else {
                if (! $relation_column_is_nullable || $force_delete) {
                    $model_instance->whereNotIn($model_instance->getKeyName(), $relation_values)
                            ->where($relation->getForeignKeyName(), $item->{$relation->getLocalKeyName()})
                            ->delete();
                } else {
                    $model_instance->whereNotIn($model_instance->getKeyName(), $relation_values)
                            ->where($relation->getForeignKeyName(), $item->{$relation->getLocalKeyName()})
                            ->update([$relation->getForeignKeyName() => null]);
                }
            }
        } else {
            //the developer cleared the selection
            //we gonna clear all related values by setting up the value to the fallback id, to null or delete.
            if (isset($relationData['fallback_id'])) {
                $model_instance->where($relation->getForeignKeyName(), $item->{$relation->getLocalKeyName()})
                            ->update([$relation->getForeignKeyName() => $relationData['fallback_id']]);
            } else {
                if (! $relation_column_is_nullable || $force_delete) {
                    $model_instance->where($relation->getForeignKeyName(), $item->{$relation->getLocalKeyName()})->delete();
                } else {
                    $model_instance->where($relation->getForeignKeyName(), $item->{$relation->getLocalKeyName()})
                            ->update([$relation->getForeignKeyName() => null]);
                }
            }
        }
    }

    /**
     * Handler HasMany/MorphMany relations when used as creatable entries in the crud.
     * By using repeatable field, developer can allow the creation of such entries
     * in the crud forms.
     *
     * @return void
     */
    public function createManyEntries($entry, $relation, $relationMethod, $relationData)
    {
        $items = collect(json_decode($relationData['values'][$relationMethod], true));
        $relatedModel = $relation->getRelated();
        $itemsInDatabase = $entry->{$relationMethod};
        //if the collection is empty we clear all previous values in database if any.
        if ($items->isEmpty()) {
            $entry->{$relationMethod}()->sync([]);
        } else {
            $items->each(function (&$item, $key) use ($relatedModel, $entry, $relationMethod) {
                if(isset($item[$relatedModel->getKeyName()])) {
                    $entry->{$relationMethod}()->updateOrCreate([$relatedModel->getKeyName() => $item[$relatedModel->getKeyName()]], $item);
                }else{
                    $entry->{$relationMethod}()->updateOrCreate([], $item);
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

}
