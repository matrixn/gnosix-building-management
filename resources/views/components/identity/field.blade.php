@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'id' => null])
@php($fieldId = $id ?? 'identity-'.$name)
@php($fieldValue = old($name, $value))
<div class="identity-field">
    <label for="{{ $fieldId }}">{{ $label }}</label>
    <div class="identity-input-wrap">
        <input id="{{ $fieldId }}" name="{{ $name }}" type="{{ $type }}" @if($type !== 'password') value="{{ is_scalar($fieldValue) ? $fieldValue : '' }}" @endif {{ $attributes }} @if($hint || $errors->has($name)) aria-describedby="{{ $fieldId }}-help" @endif @error($name) aria-invalid="true" @enderror>
        @if($type === 'password')<button type="button" class="password-toggle" data-password-toggle="{{ $fieldId }}" aria-controls="{{ $fieldId }}" aria-pressed="false" data-show="{{ __('identity.show_password') }}" data-hide="{{ __('identity.hide_password') }}" hidden>{{ __('identity.show_password') }}</button>@endif
    </div>
    @if($hint || $errors->has($name))<span id="{{ $fieldId }}-help" class="field-help @error($name) field-error @enderror">{{ $errors->first($name) ?: $hint }}</span>@endif
</div>
