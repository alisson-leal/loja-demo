<?php

namespace App\Livewire\Marketing;

use App\Models\MarketingCampaign;
use App\Models\MarketingMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Campanhas')]
class CampaignIndex extends Component
{
    use WithPagination;

    #[Url(as: 'situacao')]
    public string $status = '';

    public function mount(): void
    {
        Gate::authorize('marketing.ver');
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.marketing.campaign-index', [
            'campaigns' => MarketingCampaign::query()
                ->when(array_key_exists($this->status, MarketingCampaign::STATUS_LABELS), fn ($q) => $q->where('status', $this->status))
                ->withCount([
                    'messages',
                    'messages as sent_count' => fn ($q) => $q->where('status', MarketingMessage::SENT),
                    'messages as pending_count' => fn ($q) => $q->where('status', MarketingMessage::PENDING),
                ])
                ->latest('id')
                ->paginate(20),
            'statuses' => MarketingCampaign::STATUS_LABELS,
        ]);
    }
}
