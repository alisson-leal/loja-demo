<?php

namespace App\Livewire\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Throwable;

/** Período dos relatórios: mês atual por padrão, com atalhos e limite de 1 ano. */
trait HasReportPeriod
{
    #[Url(as: 'de')]
    public string $from = '';

    #[Url(as: 'ate')]
    public string $to = '';

    protected function initPeriod(): void
    {
        if ($this->from === '' || $this->to === '') {
            $this->preset('mes');
        }
    }

    public function preset(string $name): void
    {
        [$from, $to] = match ($name) {
            'hoje' => [today(), today()],
            '7' => [today()->subDays(6), today()],
            'mes_anterior' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            'proximos_30' => [today(), today()->addDays(29)],
            default => [today()->startOfMonth(), today()->endOfMonth()],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
    }

    /** @return array{CarbonInterface, CarbonInterface, ?string} */
    protected function period(): array
    {
        try {
            $from = Carbon::createFromFormat('!Y-m-d', $this->from);
            $to = Carbon::createFromFormat('!Y-m-d', $this->to);
        } catch (Throwable) {
            return [today()->startOfMonth(), today()->endOfMonth(), 'Período inválido. Exibindo o mês atual.'];
        }

        if ($from->gt($to) || $from->diffInDays($to) > 366) {
            return [today()->startOfMonth(), today()->endOfMonth(), 'O período deve ter início antes do fim e até 1 ano. Exibindo o mês atual.'];
        }

        return [$from, $to, null];
    }
}
