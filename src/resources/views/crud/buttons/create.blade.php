@if ($crud->hasAccess('create'))
	<a href="{{ url($crud->route.'/create') }}" class="btn btn-primary" data-style="zoom-in">
		<span class="ladda-label">
			<i class="la la-plus"></i>
			<span class="{{ config('backpack.crud.operations.list.defaultButtonTextClass.top', '') }}">{{ trans('backpack::crud.add') }} {{ $crud->entity_name }}</span>
		</span>
	</a>
@endif