@php
    $field['type'] = 'repeatable';
    $field['fields'] = $field['pivotFields'];
    $inline_create = !isset($inlineCreate) && isset($field['inline_create']) ? $field['inline_create'] : false;
    $pivotSelectorField = [
            'name' => $field['name'],
            'label' => $field['label'],
            'multiple' => false,
            'ajax' => $field['ajax'] ?? false,
            'wrapper' => $field['pivot_wrapper'] ?? [],
            'minimum_input_length' => $field['minimum_input_length'] ?? 2,
    ];
    if($inline_create) {
        $field['inline_create'] = $inline_create;
    }

    if ($field['relation_type'] == 'MorphToMany' || $field['relation_type'] == 'BelongsToMany') {
        $field['fields'] = Arr::prepend($field['fields'], $pivotSelectorField);
    }

    if ($field['relation_type'] == 'MorphMany' || $field['relation_type'] == 'HasMany') {
        if(isset($entry)) {

        $field['fields'] = Arr::prepend($field['fields'], [
            'name' => $entry->{$field['name']}()->getLocalKeyName(),
            'type' => 'hidden',
        ]);
        }
    }

@endphp

@include('crud::fields.repeatable')
