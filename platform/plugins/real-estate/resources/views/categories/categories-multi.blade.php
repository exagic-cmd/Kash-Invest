<div class="@if ($errors->has($name)) has-error @endif">
    <div class="multi-choices-widget ms-n3">
        @if (isset($options['choices']) &&
                (is_array($options['choices']) || $options['choices'] instanceof \Illuminate\Support\Collection))
            @include('plugins/real-estate::categories.categories-checkbox-option-line', [
                'categories' => $options['choices'],
                'value' => $options['value'],
                'currentId' => null,
                'name' => $name,
            ])
        @endif
    </div>
</div>
