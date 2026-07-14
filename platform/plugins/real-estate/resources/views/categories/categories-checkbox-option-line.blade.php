@php
    /**
     * @var string $value
     */
    $value = isset($value) ? (array) $value : [];
@endphp
@if ($categories)
    <ul class="list-unstyled ms-3">
        @foreach ($categories as $category)
            @if ($category->id != $currentId)
                <li
                    value="{{ $category->id ?? '' }}"
                    {{ $category->id == $value ? 'selected' : '' }}
                >
                    <label class="form-check">
                        <input type="radio" class="form-check-input" name="{{ $name }}" value="{{ $category->id }}" {{ in_array($category->id, $value) ? 'checked' : '' }}>
                        <span class="form-check-label">{{ $category->name }}</span>
                    </label>
                    @include('plugins/real-estate::categories.categories-checkbox-option-line', [
                        'categories' => $category->child_cats,
                        'value' => $value,
                        'currentId' => $currentId,
                        'name' => $name,
                    ])
                </li>
            @endif
        @endforeach
    </ul>
@endif
