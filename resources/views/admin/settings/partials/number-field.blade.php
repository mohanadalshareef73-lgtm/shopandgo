<div class="settings-field">
    <label class="form-label" for="{{ $field }}">{{ $label }}</label>
    <div class="settings-number-control">
        <input type="number" name="{{ $field }}" id="{{ $field }}"
            value="{{ old($field, $settings->getAttribute($field)) }}"
            class="form-control @error($field) is-invalid @enderror"
            dir="ltr" inputmode="decimal" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" required
            aria-describedby="{{ $field }}-unit {{ $field }}-help @error($field) {{ $field }}-error @enderror"
            @error($field) aria-invalid="true" @enderror @cannot('system-edit') readonly @endcannot>
        <span id="{{ $field }}-unit" dir="ltr">{{ $unit }}</span>
    </div>
    <small id="{{ $field }}-help" class="settings-help">{{ $help }}</small>
    @error($field)<div id="{{ $field }}-error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
