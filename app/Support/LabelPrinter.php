<?php

namespace App\Support;

use App\Models\ProductVariant;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Throwable;

/**
 * Etiquetas de preço com código de barras. Usa o código de barras do
 * produto (EAN-13) quando houver; senão, o SKU em Code 128. Os dois são
 * lidos pelo leitor no PDV, que busca por código de barras ou SKU.
 */
final class LabelPrinter
{
    public const MAX_LABELS = 1500;

    /** Formatos de papel: medidas em milímetros. */
    public const FORMATS = [
        'a4' => ['label' => 'Folha A4: 3 colunas x 10 linhas (63,5 x 25,4 mm)', 'width' => 63.5, 'height' => 25.4, 'columns' => 3, 'per_page' => 30],
        'a4-grande' => ['label' => 'Folha A4: 2 colunas x 7 linhas (99 x 38 mm)', 'width' => 99, 'height' => 38.1, 'columns' => 2, 'per_page' => 14],
        'rolo' => ['label' => 'Rolo de impressora térmica (40 x 25 mm)', 'width' => 40, 'height' => 25, 'columns' => 1, 'per_page' => 1],
    ];

    public static function code(ProductVariant $variant): string
    {
        return filled($variant->barcode) ? (string) $variant->barcode : (string) $variant->sku;
    }

    public static function svg(ProductVariant $variant): string
    {
        $code = self::code($variant);
        $generator = new BarcodeGeneratorSVG;

        $svg = null;

        if (preg_match('/^\d{13}$/', $code)) {
            try {
                $svg = $generator->getBarcode($code, $generator::TYPE_EAN_13, 1, 30);
            } catch (Throwable) {
                // EAN com dígito verificador errado: imprime em Code 128, que aceita qualquer texto.
            }
        }

        return self::scalable($svg ?? $generator->getBarcode($code, $generator::TYPE_CODE_128, 1, 30));
    }

    /** Tira o cabeçalho XML e o tamanho fixo: as barras ocupam a largura definida na etiqueta. */
    private static function scalable(string $svg): string
    {
        $svg = substr($svg, (int) strpos($svg, '<svg'));

        return preg_replace('/<svg\s+width="[\d.]+"\s+height="[\d.]+"/', '<svg preserveAspectRatio="none"', $svg, 1) ?? $svg;
    }
}
