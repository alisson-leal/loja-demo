<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleReceiptController extends Controller
{
    public function show(Request $request, Sale $sale): View
    {
        abort_unless($sale->isVisibleTo($request->user()), 403);

        return view('sales.receipt', [
            'sale' => $sale->load(['items', 'payments.method', 'customer', 'user:id,name']),
        ]);
    }
}
