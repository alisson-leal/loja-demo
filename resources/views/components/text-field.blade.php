@props(['name', 'label', 'type' => 'text', 'required' => false, 'maxlength' => null, 'hint' => null])
<label for="{{ $name }}" class="form-label">{{ $label }}</label>
<input id="{{ $name }}" type="{{ $type }}" wire:model="{{ $name }}" @required($required) @if ($maxlength) maxlength="{{ $maxlength }}" @endif
       {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>
@if ($hint) <div class="form-text">{{ $hint }}</div> @endif
@error($name) <div class="invalid-feedback">{{ $message }}</div> @enderror
