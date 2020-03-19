{{-- enumerate the values in an array  --}}
@php
    $value = data_get($entry, $column['name']);

    // the value should be an array wether or not attribute casting is used
    if (!is_array($value)) {
        $value = json_decode($value, true);
    }
@endphp

<span>

    @if($value && count($value))
        @php($lastKey = array_key_last($value))

        @foreach($value as $key => $text)
            @includeWhen(!empty($column['wrapper']), 'crud::columns.inc.wrapper_start')
                @if($column['escaped'])
                    {{ $text }}
                @else
                    {!! $text !!}
                @endif
            @includeWhen(!empty($column['wrapper']), 'crud::columns.inc.wrapper_end')

            @if($key != $lastKey)
                , 
            @endif
        @endforeach
    @else
        -
    @endif

</span>
