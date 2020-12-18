<?php

namespace Backpack\CRUD\app\Library\CrudPanel\Traits;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Support\Arr;

trait Update
{
    /*
    |--------------------------------------------------------------------------
    |                                   UPDATE
    |--------------------------------------------------------------------------
    */

    /**
     * Update a row in the database.
     *
     * @param int   $id   The entity's id
     * @param array $data All inputs to be updated.
     *
     * @return object
     */
    public function update($id, $data)
    {
        $data = $this->decodeJsonCastedAttributes($data);
        $data = $this->compactFakeFields($data);
        $item = $this->model->findOrFail($id);

        // omit the n-n relationships when updating the eloquent item
        $nn_relationships = Arr::pluck($this->getRelationFieldsWithPivot(), 'name');

        // handle BelongsTo 1:1 relations
        $item = $this->associateOrDissociateBelongsToRelations($item, $data);

        $item->fill(Arr::except($data, $nn_relationships));
        $item->save();

        $this->createRelations($item, $data);

        return $item;
    }

    /**
     * Get all fields needed for the EDIT ENTRY form.
     *
     * @param int $id The id of the entry that is being edited.
     *
     * @return array The fields with attributes, fake attributes and values.
     */
    public function getUpdateFields($id = false)
    {
        $fields = $this->fields();
        $entry = ($id != false) ? $this->getEntry($id) : $this->getCurrentEntry();

        foreach ($fields as &$field) {
            // set the value
            if (! isset($field['value'])) {
                if (isset($field['subfields'])) {
                    $field['value'] = [];
                    foreach ($field['subfields'] as $subfield) {
                        $field['value'][] = $entry->{$subfield['name']};
                    }
                } else {
                    $field['value'] = $this->getModelAttributeValue($entry, $field);
                }
            }
        }

        // always have a hidden input for the entry id
        if (! array_key_exists('id', $fields)) {
            $fields['id'] = [
                'name'  => $entry->getKeyName(),
                'value' => $entry->getKey(),
                'type'  => 'hidden',
            ];
        }

        return $fields;
    }

    /**
     * Get the value of the 'name' attribute from the declared relation model in the given field.
     *
     * @param \Illuminate\Database\Eloquent\Model $model The current CRUD model.
     * @param array                               $field The CRUD field array.
     *
     * @return mixed The value of the 'name' attribute from the relation model.
     */
    private function getModelAttributeValue($model, $field)
    {

        if (isset($field['entity'])) {

            $relational_entity = $this->parseRelationFieldNamesFromHtml([$field])[0]['name'];

            $relation_array = explode('.', $relational_entity);

            $relatedModel = $relatedModel = array_reduce(array_splice($relation_array, 0, -1), function ($obj, $method) {
                return $obj->{$method} ? $obj->{$method} : $obj;
            }, $model);

            $relationMethod = Arr::last($relation_array);
            if (method_exists($relatedModel, $relationMethod)) {

                $relation = $relatedModel->{$relationMethod}();
                if ($relation instanceof HasOne || $relation instanceof MorphOne) {
                    return $relatedModel->{$relationMethod}->{Arr::last(explode('.', $relational_entity))};
                }


                if ($relation instanceof HasMany || $relation instanceof MorphMany) {
                    if(isset($field['pivotFields']) && is_array($field['pivotFields'])) {
                        $pivot_fields = Arr::where($field['pivotFields'], function ($item) use ($field) {
                            return $field['name'] != $item['name'];
                        });
                        $related_models = $relatedModel->{$relationMethod};
                        $return = [];

                        // for any given model, we grab the attributes that belong to our pivot table.
                        foreach ($related_models as $related_model) {
                            //for any given related model, we attach the pivot fields.
                            foreach ($pivot_fields as $pivot_field) {
                                $item[$pivot_field['name']] = $related_model->{$pivot_field['name']};
                                $item[$related_model->getKeyName()] = $related_model->getKey();
                            }
                            $return[] = $item;
                        }
                        // we return the json encoded result as expected by repeatable field.
                        return json_encode($return);
                    }

                }

                if ($relation instanceof BelongsToMany || $relation instanceof MorphToMany) {

                    // if pivot is true and there are `pivotFields` we need to get those pivot values to show on the edit page
                    if (isset($field['pivot']) && $field['pivot'] && isset($field['pivotFields']) && is_array($field['pivotFields'])) {

                        // we remove our current relation from the pivotFields.
                        $pivot_fields = Arr::where($field['pivotFields'], function ($item) use ($field) {
                            return $field['name'] != $item['name'];
                        });

                        $related_models = $relatedModel->{$relationMethod};
                        $return = [];

                        // for any given model, we grab the attributes that belong to our pivot table.
                        foreach ($related_models as $related_model) {
                            $item[$field['name']] = $related_model->getKey();
                            //for any given related model, we attach the pivot fields.
                            foreach ($pivot_fields as $pivot_field) {
                                $item[$pivot_field['name']] = $related_model->pivot->{$pivot_field['name']};
                            }
                            $return[] = $item;
                        }

                        // we return the json encoded result as expected by repeatable field.
                        return json_encode($return);
                    }
                }
            }

            return $relatedModel->{$relationMethod};
        }

        if (is_string($field['name'])) {
            return $model->{$field['name']};
        }

        if (is_array($field['name'])) {
            $result = [];
            foreach ($field['name'] as $key => $value) {
                $result = $model->{$value};
            }

            return $result;
        }
    }
}
