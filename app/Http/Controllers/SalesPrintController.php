<?php

namespace App\Http\Controllers;

use App\Models\Consignment;
use App\Models\Receivable;
use App\Models\Sale;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Documentos para imprimir: comprovante da condicional e carnê do crediário. */
class SalesPrintController extends Controller
{
    public function consignment(Consignment $consignment): View
    {
        abort_unless(Auth::user()->canAny(['condicional.criar', 'condicional.acertar']), 403);

        return view('sales.consignment-print', ['consignment' => $consignment->load('items', 'customer')]);
    }

    public function carne(Sale $sale): View
    {
        abort_unless(Auth::user()->canAny(['crediario.vender', 'crediario.receber', 'crediario.gerir']), 403);
        $installments = Receivable::crediario()->with('method')->where('sale_id', $sale->id)->where('status', '!=', Receivable::CANCELLED)->orderBy('installment')->get();
        abort_if($installments->isEmpty(), 404);

        return view('sales.carne', ['sale' => $sale->load('customer'), 'installments' => $installments]);
    }
}
