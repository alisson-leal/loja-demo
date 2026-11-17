<?php

namespace App\Livewire\Audit;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Auditoria')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'evento')]
    public string $event = '';

    #[Url(as: 'usuario')]
    public string $userId = '';

    #[Url(as: 'de')]
    public string $from = '';

    #[Url(as: 'ate')]
    public string $to = '';

    public function mount(): void
    {
        Gate::authorize('auditoria.ver');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('event', 'userId', 'from', 'to');
        $this->resetPage();
    }

    public function render(): View
    {
        $isDate = fn (string $value) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;

        $logs = ActivityLog::with('user:id,name')
            ->when($this->event !== '', fn (Builder $query) => $query->where('event', $this->event))
            ->when(ctype_digit($this->userId), fn (Builder $query) => $query->where('user_id', (int) $this->userId))
            ->when($isDate($this->from), fn (Builder $query) => $query->where('created_at', '>=', $this->from.' 00:00:00'))
            ->when($isDate($this->to), fn (Builder $query) => $query->where('created_at', '<=', $this->to.' 23:59:59'))
            ->latest('id')
            ->paginate(25);

        return view('livewire.audit.index', [
            'logs' => $logs,
            'events' => ActivityLog::EVENT_LABELS,
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
