<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\AuditLogger;
use App\Support\CustomerFilter;
use App\Support\Money;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta em CSV, que abre no Excel. O CPF fica de fora de propósito,
 * para reduzir a circulação de dados pessoais.
 */
class CustomerExportController extends Controller
{
    public function download(Request $request, AuditLogger $audit): StreamedResponse
    {
        $query = CustomerFilter::apply(Customer::query()->withStats(), $request->query())->orderBy('name');

        $audit->record('customer.exported', 'Lista de clientes exportada: '.(clone $query)->count().' registro(s).', properties: [
            'filtros' => array_filter(CustomerFilter::normalize($request->query()), fn ($value) => $value !== ''),
        ]);

        return response()->streamDownload(function () use ($query) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Nome', 'WhatsApp', 'Telefone', 'E-mail', 'Bairro', 'Cidade', 'Compras', 'Total comprado', 'Última compra', 'Situação'], ';');

            foreach ($query->cursor() as $customer) {
                fputcsv($output, array_map($this->safeCell(...), [
                    $customer->name,
                    $customer->whatsapp,
                    $customer->phone,
                    $customer->email,
                    $customer->neighborhood,
                    $customer->city,
                    $customer->purchases_count,
                    Money::format($customer->total_spent),
                    $customer->last_purchase_at?->format('d/m/Y'),
                    $customer->statusLabel(),
                ]), ';');
            }

            fclose($output);
        }, 'clientes-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Impede que um texto cadastrado seja interpretado como fórmula pelo Excel. */
    private function safeCell(mixed $value): string
    {
        $text = (string) $value;

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@'], true) ? "'{$text}" : $text;
    }
}
