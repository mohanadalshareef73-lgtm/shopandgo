@php
    $messageValue = old($errorKey ?? $field, $value);
    $messageValue = is_string($messageValue) ? $messageValue : '';
@endphp
<div class="settings-field">
    <label class="form-label" for="{{ $field }}">{{ $label }}</label>
    <p id="{{ $field }}-help" class="settings-help">{{ $help }}</p>
    <textarea name="{{ $name ?? $field }}" id="{{ $field }}" rows="{{ $rows }}" maxlength="{{ $max }}" required dir="{{ $direction ?? 'auto' }}"
        class="form-control @error($errorKey ?? $field) is-invalid @enderror"
        aria-describedby="{{ $field }}-help {{ $field }}-count @error($errorKey ?? $field) {{ $field }}-error @enderror"
        @error($errorKey ?? $field) aria-invalid="true" @enderror
        @cannot('system-edit') readonly @endcannot>{{ $messageValue }}</textarea>
    <small id="{{ $field }}-count" class="settings-character-count"><span data-character-count="{{ $field }}">{{ mb_strlen($messageValue) }}</span> / {{ $max }} حرف</small>
    @error($errorKey ?? $field)<div id="{{ $field }}-error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @php($replyImageKey = str_replace('-message-', '_', $field))
    @if (in_array($replyImageKey, \App\Models\Setting::replyImageKeys(), true))
        @include('admin.settings.partials.reply-image', ['imageKey' => $replyImageKey, 'imageLabel' => $label])
    @endif
</div>
