<!-- select2 -->
@php

    $current_value = old($field['name']) ?? $field['value'] ?? $field['default'] ?? '';
    $entity_model = $crud->getRelationModel($field['entity'],  - 1);

    $entity_model_instance = new $field['model']();

    if (!isset($field['options'])) {
        $options = $field['model']::all()->pluck($field['attribute'],$entity_model_instance->getKeyName());
    } else {
        $options = call_user_func($field['options'], $field['model']::query()->pluck($field['attribute'],$entity_model_instance->getKeyName()));
    }
    //dd($options);
    $fieldOnTheFlyConfiguration = $field['on_the_fly'] ?? [];

    //if user don't specify 'entity_route' we assume it's the same from $field['entity']
    $onTheFlyEntity = isset($field['on_the_fly']['entity_route']) ? $field['on_the_fly']['entity_route'] : $field['entity'];

    //we make sure on_the_fly operation is setup and that user wants to allow field creation
    $activeOnTheFlyCreate = $crud->has($crud->getOperation().'.on_the_fly') ?
    isset($field['on_the_fly']['create']) ? $field['on_the_fly']['create'] : false : false;

    $activeOnTheFlyUpdate = $crud->has($crud->getOperation().'.on_the_fly') ?
    isset($field['on_the_fly']['update']) ? $field['on_the_fly']['update'] : false : false;

    if($activeOnTheFlyCreate || $activeOnTheFlyUpdate) {

    if(!isset($onTheFly)) {
        $createRoute = route($onTheFlyEntity."-on-the-fly-create");

        $updateRoute = route($onTheFlyEntity."-on-the-fly-update");
        $createRouteEntity = explode('/',$crud->route)[1];

        $refreshRoute = route($createRouteEntity."-on-the-fly-refresh-options");

    }else{
        $activeOnTheFlyCreate = false;
        $activeOnTheFlyUpdate = false;
    }
}

    if ($entity_model::isColumnNullable($field['name'])) {
        $allows_null = isset($field['allows_null']) ? $field['allows_null'] : true;
    }else {
        $allows_null = isset($field['allows_null']) ? $field['allows_null'] : false;
    }

@endphp

<div @include('crud::inc.field_wrapper_attributes') >

    <label>{!! $field['label'] !!}</label>
    @include('crud::inc.field_translatable_icon')
    @if($activeOnTheFlyCreate)
        @include('crud::buttons.on_the_fly.create', ['name' => $field['name'], 'onTheFlyEntity' => $onTheFlyEntity])
       @endif
<select
        name="{{ $field['name'] }}"
        data-original-name="{{ $field['name'] }}"
        style="width: 100%"
        data-init-function="bpFieldInitSelect2Element"
        data-is-on-the-fly="{{ $onTheFly ?? 'false' }}"
        data-field-related-name="{{$onTheFlyEntity}}"
        data-on-the-fly-create-route="{{$createRoute ?? false}}"
        data-on-the-fly-update-route="{{$updateRoute ?? false}}"
        data-on-the-fly-refresh-route="{{$refreshRoute ?? false}}"
        data-field-multiple="false"
        data-on-the-fly-related-key="{{$entity_model_instance->getKeyName()}}"
        data-on-the-fly-related-attribute="{{$field['attribute']}}"
        data-options-for-select="{{json_encode($options)}}"
        data-on-the-fly-create-button="{{ $onTheFlyEntity }}-on-the-fly-create-{{$field['name']}}"
        data-on-the-fly-allow-create="{{var_export($activeOnTheFlyCreate)}}"
        data-on-the-fly-allow-update="{{var_export($activeOnTheFlyUpdate)}}"
        data-allows-null="{{var_export($allows_null)}}"
        data-current-value="{{$current_value}}"
        @include('crud::inc.field_attributes', ['default_class' =>  'form-control select2_field'])
        >
    </select>

    {{-- HINT --}}
    @if (isset($field['hint']))
        <p class="help-block">{!! $field['hint'] !!}</p>
    @endif

</div>

{{-- ########################################## --}}
{{-- Extra CSS and JS for this particular field --}}
{{-- If a field type is shown multiple times on a form, the CSS and JS will only be loaded once --}}

@if (!$crud->isAnyTypeLoaded([
    'select2','select2_multiple'
]))
@push('before_scripts')
<script src="{{ asset('packages/backpack/crud/js/selects.js') }}" ></script>
@endpush
@endif
@if ($crud->fieldTypeNotLoaded($field))
    @php
        $crud->markFieldTypeAsLoaded($field);
    @endphp

    {{-- FIELD CSS - will be loaded in the after_styles section --}}
    @push('crud_fields_styles')
    @stack('on_the_fly_styles')
        <!-- include select2 css-->
        <link href="{{ asset('packages/select2/dist/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
        <link href="{{ asset('packages/select2-bootstrap-theme/dist/select2-bootstrap.min.css') }}" rel="stylesheet" type="text/css" />
    @endpush

    {{-- FIELD JS - will be loaded in the after_scripts section --}}
    @push('crud_fields_scripts')
    @stack('on_the_fly_scripts')
        <!-- include select2 js-->
        <script src="{{ asset('packages/select2/dist/js/select2.full.min.js') }}"></script>
        @if (app()->getLocale() !== 'en')
        <script src="{{ asset('packages/select2/dist/js/i18n/' . app()->getLocale() . '.js') }}"></script>

        @endif



<script type="text/javascript">



            function bpFieldInitSelect2Element(element) {

                var $onTheFlyField = element.attr('data-is-on-the-fly');


                var $onTheFlyCreateRoute = element.attr('data-on-the-fly-create-route');
                var $onTheFlyRefreshRoute = element.attr('data-on-the-fly-refresh-route');

                var $modalUrls = {
                        'createUrl' : $onTheFlyCreateRoute,
                        'refreshUrl': $onTheFlyRefreshRoute
                    }

                var $selectOptions = element.attr('data-options-for-select');

                triggerSelectOptions(element,$modalUrls['refreshUrl']);

                //Checks if field is not beeing inserted in one on-the-fly modal and setup buttons
                if($onTheFlyField == "false") {

                    setupOnTheFlyButtons(element, $modalUrls);

                }
                // element will be a jQuery wrapped DOM node
                if (!element.hasClass("select2-hidden-accessible")) {
                    element.select2({
                        theme: "bootstrap",

                    });
                }
            }
        </script>
    @endpush

@endif
{{-- End of Extra CSS and JS --}}
{{-- ########################################## --}}
