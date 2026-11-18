@if (session('success'))
    <div class="alert alert-success" role="status">
        <i class="bi bi-check-circle me-1" aria-hidden="true"></i>{{ session('success') }}
        @if (session('labels_entry') && auth()->user()?->can('produtos.ver'))
            <a href="{{ route('stock.labels', ['entrada' => session('labels_entry')]) }}" class="alert-link ms-1">Imprimir as etiquetas desta entrada</a>
        @endif
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ session('error') }}</div>
@endif
