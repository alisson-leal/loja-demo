<?php

namespace App\Livewire\Marketing;

use App\Livewire\Concerns\HandlesBusinessRules;
use App\Models\MarketingCampaign;
use App\Models\MarketingMessage;
use App\Services\WhatsAppQueueService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('WhatsApp a enviar')]
class WhatsappQueue extends Component
{
    use HandlesBusinessRules;
    use WithPagination;

    /** Vazio: tudo; "aniversarios": automações; número: id da campanha. */
    #[Url(as: 'campanha')]
    public string $origin = '';

    public function mount(): void
    {
        Gate::authorize('marketing.whatsapp');
    }

    public function updatedOrigin(): void
    {
        $this->resetPage();
    }

    public function markSent(int $id, WhatsAppQueueService $queue): void
    {
        Gate::authorize('marketing.whatsapp');
        $this->attempt(fn () => $queue->markSent(MarketingMessage::findOrFail($id), Auth::user()), 'Mensagem marcada como enviada.');
    }

    public function skip(int $id, WhatsAppQueueService $queue): void
    {
        Gate::authorize('marketing.whatsapp');
        $this->attempt(fn () => $queue->skip(MarketingMessage::findOrFail($id), Auth::user()), 'Mensagem pulada.');
    }

    public function optOut(int $id, WhatsAppQueueService $queue): void
    {
        Gate::authorize('marketing.whatsapp');
        $this->attempt(fn () => $queue->optOut(MarketingMessage::findOrFail($id), Auth::user()), 'Pronto: este cliente não receberá mais mensagens pelo WhatsApp.');
    }

    public function render(): View
    {
        $messages = MarketingMessage::whatsappQueue()
            ->with('customer:id,name', 'campaign:id,name')
            ->when($this->origin === 'aniversarios', fn ($q) => $q->whereNotNull('automation'))
            ->when(ctype_digit($this->origin), fn ($q) => $q->where('campaign_id', (int) $this->origin))
            ->orderByRaw('automation IS NULL')
            ->orderBy('id')
            ->paginate(20);

        return view('livewire.marketing.whatsapp-queue', [
            'messages' => $messages,
            'campaigns' => MarketingCampaign::where('channel', 'whatsapp')->where('status', MarketingCampaign::SENDING)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
