<?php

namespace Backpack\CRUD\app\Http\Controllers\Operations;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Illuminate\Support\Facades\Route;

trait InstantFieldsOperation
{
    /**
     * Define which routes are needed for this operation.
     *
     * @param string $segment    Name of the current entity (singular). Used as first URL segment.
     * @param string $routeName  Prefix of the route name.
     * @param string $controller Name of the current CrudController.
     */
    protected function setupInstantFieldsRoutes($segment, $routeName, $controller)
    {
        Route::get($segment.'/instant-fields/create', [
            'as'        => $segment.'-on-the-fly-create',
            'uses'      => $controller.'@getInstantCreateModal',
            'operation' => 'InstantFieldsOperation',
        ]);
        Route::post($segment.'/instant-fields/create', [
            'as'        => $segment.'-on-the-fly-create',
            'uses'      => $controller.'@storeOnTheFly',
            'operation' => 'InstantFieldsOperation',
        ]);
        Route::get($segment.'/instant-fields/refresh', [
            'as'        => $segment.'-on-the-fly-refresh-options',
            'uses'      => $controller.'@refreshOptions',
            'operation' => 'InstantFieldsOperation',
        ]);

        Route::get($segment.'/instant-fields/update', [
            'as'        => $segment.'-on-the-fly-update',
            'uses'      => $controller.'@getInstantUpdateModal',
            'operation' => 'InstantFieldsOperation',
        ]);
    }

    public function setupInstantFieldsDefaults()
    {
        $this->crud->setOperationSetting('on_the_fly', true);
    }

    public function getInstantCreateModal()
    {
        if (request()->has('entity')) {
            $this->setupCreateOperation();

            return $this->getInstantModal(request()->get('entity'), 'create', $this->crud->getCreateFields());
        }
    }

    public function getInstantUpdateModal()
    {
        if (request()->has('entity')) {
            $this->setupUpdateOperation();

            return $this->getInstantModal(request()->get('entity'), 'update', $this->crud->getUpdateFields());
        }
    }

    public function getInstantModal($entity, $action, $fields)
    {
        return view(
                'crud::inc.on-the-fly',
                [
                    'fields' => $fields,
                    'action' => $action,
                    'crud' => $this->crud,
                    'entity' => $entity,
                ]
                );
    }

    public function refreshOptions()
    {
        $this->setupCreateOperation();

        if (request()->has('field')) {
            $field = $this->crud->fields()[request()->get('field')];
            $relatedModelInstance = new $field['model']();
            if ($field) {
                if (! isset($field['options'])) {
                    $options = $field['model']::all()->pluck($field['attribute'], $relatedModelInstance->getKeyName());
                } else {
                    $options = call_user_func($field['options'], $field['model']::query()->pluck($field['attribute'], $relatedModelInstance->getKeyName()));
                }
            }

            return response()->json($options);
        }
    }

    public function storeOnTheFly()
    {
        $this->setupCreateOperation();

        return $this->store();
    }
}
