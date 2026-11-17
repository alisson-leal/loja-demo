@props(['catalog', 'grantable' => null])
<div class="row g-3">
    @foreach ($catalog as $module => $definition)
        <div class="col-12 col-md-6 col-xl-4" wire:key="module-{{ $module }}">
            <fieldset class="permission-group">
                <legend>{{ $definition['label'] }}</legend>
                @foreach ($definition['operations'] as $operation => $label)
                    @php($permission = "{$module}.{$operation}")
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="perm-{{ $module }}-{{ $operation }}"
                               value="{{ $permission }}" wire:model="permissions"
                               @disabled($grantable !== null && ! in_array($permission, $grantable, true))>
                        <label class="form-check-label" for="perm-{{ $module }}-{{ $operation }}">{{ $label }}</label>
                    </div>
                @endforeach
            </fieldset>
        </div>
    @endforeach
</div>
