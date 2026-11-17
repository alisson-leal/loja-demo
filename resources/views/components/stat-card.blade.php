@props(['label', 'value', 'icon', 'tone' => 'pink'])
<div @class(['card', 'stat-card', 'h-100', 'stat-card-blue' => $tone === 'blue'])>
    <div class="card-body">
        <span @class(['stat-icon', 'stat-icon-blue' => $tone === 'blue'])><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
        <div>
            <div class="stat-value">{{ number_format($value, 0, ',', '.') }}</div>
            <div class="stat-label">{{ $label }}</div>
        </div>
    </div>
</div>
