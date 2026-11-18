<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Support\LabelPrinter;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Página de impressão das etiquetas montadas na tela Estoque > Etiquetas. */
class LabelPrintController extends Controller
{
    public function show(): View|RedirectResponse
    {
        $request = session('labels');

        if (! $request || empty($request['items'])) {
            return redirect()->route('stock.labels');
        }

        $variants = ProductVariant::with(['product', 'values.attribute'])->whereIn('id', array_keys($request['items']))->get()->keyBy('id');
        $labels = [];

        foreach ($request['items'] as $id => $quantity) {
            if ($variant = $variants[$id] ?? null) {
                $label = [
                    'name' => $variant->product->name,
                    'variant' => $variant->label(),
                    'price' => $variant->sale_price,
                    'code' => LabelPrinter::code($variant),
                    'svg' => LabelPrinter::svg($variant),
                ];
                array_push($labels, ...array_fill(0, (int) $quantity, $label));
            }
        }

        return view('stock.labels-print', [
            'labels' => array_slice($labels, 0, LabelPrinter::MAX_LABELS),
            'format' => LabelPrinter::FORMATS[$request['format']] ?? LabelPrinter::FORMATS['a4'],
            'showPrice' => (bool) $request['price'],
            'store' => config('loja.store.name'),
        ]);
    }
}
