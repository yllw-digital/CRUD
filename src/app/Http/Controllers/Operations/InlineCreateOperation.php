<?php

namespace Backpack\CRUD\app\Http\Controllers\Operations;

use Illuminate\Support\Facades\Route;
use Prologue\Alerts\Facades\Alert;

trait InlineCreateOperation
{
    /**
     * Define which routes are needed for this operation.
     *
     * @param string $segment    Name of the current entity (singular). Used as first URL segment.
     * @param string $routeName  Prefix of the route name.
     * @param string $controller Name of the current CrudController.
     */
    protected function setupInlineCreateRoutes($segment, $routeName, $controller)
    {
        Route::post($segment.'/inline/create/modal', [
            'as'        => $segment.'-inline-create',
            'uses'      => $controller.'@getInlineCreateModal',
            'operation' => 'InlineCreate',
        ]);
        Route::post($segment.'/inline/create', [
            'as'        => $segment.'-inline-create-save',
            'uses'      => $controller.'@storeInlineCreate',
            'operation' => 'InlineCreate',
        ]);
    }

    /**
     *  This operation have some known quirks that can only be addressed using this setup instead of `setupInlineCreateDefaults`
     *  1 - InlineCreateOperation must be added AFTER CreateOperation trait.
     *  2 - setup() in controllers that have the InlineCreateOperations need to be called twice (technically we are re-creating a new crud).
     *
     *  Both problems are solved using this setup because it will only be called when InlineCreate route is requested, so even that we need to call
     *  setup again (we are creating a "new" crud panel to show in modal), it will not affect other operation setups.
     *
     *  The downside of it, and why we need this explanation note, is that using `setupInlineCreateOperation`
     *  in your CrudController to override/configure this operation will not have the same behaviour as
     *  other operations.
     *
     *  Usually you would just `setupSomeOperation()` and add your own setup of the operation. In this specific case is a little bit different,
     *  this code is the minimum needed to run the operation, and should be present if you override this method in your CrudController.
     */
    public function setupInlineCreateOperation()
    {
        if (method_exists($this, 'setup')) {
            $this->setup();
        }
        if (method_exists($this, 'setupCreateOperation')) {
            $this->setupCreateOperation();
        }

        $this->crud->applyConfigurationFromSettings('create');
    }

    /**
     * Returns the HTML of the create form. It's used by the CreateInline operation, to show that form
     * inside a popup (aka modal).
     */
    public function getInlineCreateModal()
    {
        if (! request()->has('entity')) {
            abort(400, 'No "entity" inside the request.');
        }

        return view(
            'crud::fields.relationship.inline_create_modal',
            [
                'fields' => $this->crud->getCreateFields(),
                'action' => 'create',
                'crud' => $this->crud,
                'entity' => request()->get('entity'),
                'modalClass' => request()->get('modal_class'),
                'parentLoadedFields' => request()->get('parent_loaded_fields'),
            ]
        );
    }

    /**
     * Runs the store() function in controller like a regular crud create form.
     * Developer might overwrite this if he wants some custom save behaviour when added on the fly.
     *
     * @return void
     */
    public function storeInlineCreate()
    {
        $result = $this->store();

        // do not carry over the flash messages from the Create operation
        Alert::flush();

        return $result;
    }
}
