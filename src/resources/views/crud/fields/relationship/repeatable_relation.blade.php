@php
    $field['type'] = 'repeatable';
    $field['fields'] = Arr::prepend($field['pivotFields'], [
        'name' => $field['name'],
        'label' => $field['label'],
        'multiple' => false,
        'ajax' => $field['ajax'] ?? false,
        'minimum_input_length' => $field['minimum_input_length'] ?? ($field['ajax'] ? 2 : 0),
    ]);
@endphp

@include('crud::fields.repeatable')
