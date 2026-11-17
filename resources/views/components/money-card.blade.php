@props(['label', 'cents', 'icon', 'tone' => 'pink'])
<div @class(['card', 'stat-card', 'h-100', 'stat-card-blue' => $tone === 'blue'])>
    <div class="card-body">
        <span @class(['stat-icon', 'stat-icon-blue' => $tone === 'blue'])><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
        <div>
            <div class="stat-value">{{ \App\Support\Money::format($cents / 100) }}</div>
            <div class="stat-label">{{ $label }}</div>
        </div>
    </div>
</div>
