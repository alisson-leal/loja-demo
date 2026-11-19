@if ($item->warranty_until)
    @if ($item->underWarranty())
        <div class="small text-success"><i class="bi bi-shield-check" aria-hidden="true"></i> Em garantia até {{ $item->warranty_until->format('d/m/Y') }}</div>
    @else
        <div class="small text-danger"><i class="bi bi-shield-x" aria-hidden="true"></i> Garantia vencida em {{ $item->warranty_until->format('d/m/Y') }}</div>
    @endif
@else
    <div class="small text-body-secondary">Sem garantia da loja</div>
@endif
