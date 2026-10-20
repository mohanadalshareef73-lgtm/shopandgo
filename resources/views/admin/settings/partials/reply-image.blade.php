@php($savedImage = $settings->replyImagePath($imageKey))
<div class="settings-reply-image mt-3" data-reply-image>
    <label class="form-label" for="reply-image-{{ $imageKey }}">صورة مرفقة — {{ $imageLabel }} (اختياري)</label>
    <p id="reply-image-{{ $imageKey }}-help" class="settings-help">اختر صورة JPG أو PNG حتى 5 ميغابايت. يصل النص مع الصورة.@if ($imageKey === 'shein_completed_message' || str_ends_with($imageKey, '_succeeded')) يرسل ملف PDF أيضاً في رسالة منفصلة.@endif</p>
    <img @if ($savedImage) src="{{ route('settings.reply-image', $imageKey) }}" @endif alt="معاينة صورة {{ $imageLabel }}"
        data-reply-image-preview data-saved-src="{{ $savedImage ? route('settings.reply-image', $imageKey) : '' }}"
        @if (! $savedImage) hidden @endif class="settings-reply-image-preview mb-3">
    <div class="settings-image-picker">
        <input type="file" id="reply-image-{{ $imageKey }}" name="reply_images[{{ $imageKey }}]" accept="image/jpeg,image/png"
            class="visually-hidden @error('reply_images.'.$imageKey) is-invalid @enderror" data-reply-image-input
            aria-describedby="reply-image-{{ $imageKey }}-help" @error('reply_images.'.$imageKey) aria-invalid="true" @enderror
            @cannot('system-edit') disabled @endcannot>
        <label class="btn btn-outline-secondary mb-0 @cannot('system-edit') disabled @endcannot" for="reply-image-{{ $imageKey }}">{{ $savedImage ? 'استبدال الصورة' : 'اختيار صورة' }}</label>
        <span class="settings-help mb-0" data-reply-image-filename aria-live="polite" dir="auto">{{ $savedImage ? 'صورة محفوظة' : 'لم يتم اختيار صورة' }}</span>
    </div>
    @error('reply_images.'.$imageKey)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if ($savedImage)
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" id="remove-reply-image-{{ $imageKey }}" name="remove_reply_images[]"
                value="{{ $imageKey }}" data-reply-image-remove @checked(in_array($imageKey, old('remove_reply_images', []), true))
                @cannot('system-edit') disabled @endcannot>
            <label class="form-check-label" for="remove-reply-image-{{ $imageKey }}">إزالة الصورة عند الحفظ</label>
        </div>
    @endif
</div>
