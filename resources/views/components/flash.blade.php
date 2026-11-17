@if (session('success'))
    <div class="alert alert-success" role="status"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ session('error') }}</div>
@endif
