<?php

namespace App\Support;

/**
 * Segmento da loja (config loja.segment, vindo de LOJA_SEGMENTO). Define as
 * variações que já vêm cadastradas, a variação principal da planilha de
 * importação e se os recursos de filhos dos clientes aparecem (só moda
 * infantil e mista).
 */
final class StoreSegment
{
    public const LABELS = [
        'infantil' => 'Moda infantil',
        'adulto' => 'Moda adulta',
        'misto' => 'Moda adulta e infantil',
        'eletronicos' => 'Eletrônicos e informática',
        'armarinho' => 'Armarinho e aviamentos',
        'variedades' => 'Variedades e utilidades',
    ];

    public static function current(): string
    {
        $segment = (string) config('loja.segment');

        return array_key_exists($segment, self::LABELS) ? $segment : 'infantil';
    }

    public static function label(): string
    {
        return self::LABELS[self::current()];
    }

    public static function isFashion(): bool
    {
        return in_array(self::current(), ['infantil', 'adulto', 'misto'], true);
    }

    /** Cadastro de filhos, aniversário do filho e filtros por idade/tamanho. */
    public static function childrenEnabled(): bool
    {
        return in_array(self::current(), ['infantil', 'misto'], true);
    }

    /**
     * Variações criadas na instalação (atributo => valores iniciais).
     *
     * @return array<string, list<string>>
     */
    public static function variationPreset(): array
    {
        return match (self::current()) {
            'eletronicos' => [
                'Voltagem' => ['110 V', '220 V', 'Bivolt'],
                'Capacidade' => ['32 GB', '64 GB', '128 GB', '256 GB', '512 GB', '1 TB'],
                'Cor' => ['Preto', 'Branco', 'Prata', 'Azul', 'Vermelho'],
                'Modelo' => [],
            ],
            'armarinho' => [
                'Cor' => ['Branco', 'Preto', 'Cru', 'Vermelho', 'Azul', 'Rosa', 'Amarelo', 'Verde'],
                'Largura' => ['0,5 cm', '1 cm', '2 cm', '3 cm', '5 cm'],
                'Numeração' => [],
                'Espessura' => [],
            ],
            'variedades' => [
                'Cor' => ['Preto', 'Branco', 'Azul', 'Vermelho', 'Verde', 'Rosa'],
                'Tamanho' => ['P', 'M', 'G'],
                'Modelo' => [],
                'Voltagem' => ['110 V', '220 V', 'Bivolt'],
            ],
            default => [
                'Tamanho' => ['RN', 'P', 'M', 'G', 'GG', '1', '2', '3', '4', '6', '8', '10', '12', '14'],
                'Cor' => ['Rosa', 'Azul', 'Branco', 'Amarelo', 'Vermelho', 'Verde'],
                'Estampa' => [],
                'Modelo' => [],
                'Numeração' => [],
            ],
        };
    }

    /** Nome da variação da coluna "variacao" da planilha de importação. */
    public static function primaryVariation(): string
    {
        return match (self::current()) {
            'eletronicos' => 'Voltagem',
            'armarinho' => 'Largura',
            default => 'Tamanho',
        };
    }

    /**
     * Linhas de exemplo da planilha modelo (sem o cabeçalho), no formato das colunas da importação.
     *
     * @return list<list<string>>
     */
    public static function importExamples(): array
    {
        return match (self::current()) {
            'eletronicos' => [
                ['Liquidificador Turbo', 'Eletrodomésticos', 'Cozinha', 'Marca Exemplo', 'Fornecedor Exemplo', '110 V', 'Preto', '189,90', '110,00', '', '', '5', '1', '1000 W'],
                ['Liquidificador Turbo', 'Eletrodomésticos', 'Cozinha', 'Marca Exemplo', 'Fornecedor Exemplo', '220 V', 'Preto', '189,90', '110,00', '', '', '3', '1', ''],
                ['Fone Bluetooth', 'Áudio', '', 'Marca Exemplo', '', '', 'Branco', '129,90', '60,00', '', '', '10', '2', ''],
                ['Cabo USB-C 1 m', 'Acessórios', 'Cabos', '', '', '', '', '29,90', '9,00', '', '', '30', '5', ''],
            ],
            'armarinho' => [
                ['Fita de Cetim', 'Fitas', 'Cetim', 'Marca Exemplo', 'Fornecedor Exemplo', '1 cm', 'Vermelho', '8,90', '3,50', '', '', '20', '5', 'Rolo com 10 m'],
                ['Fita de Cetim', 'Fitas', 'Cetim', 'Marca Exemplo', 'Fornecedor Exemplo', '2 cm', 'Vermelho', '11,90', '4,80', '', '', '15', '5', ''],
                ['Linha de Costura 100 m', 'Linhas', '', 'Marca Exemplo', '', '', 'Branco', '4,50', '1,80', '', '', '50', '10', ''],
                ['Kit de Agulhas', 'Aviamentos', 'Agulhas', '', '', '', '', '12,90', '5,00', '', '', '12', '3', ''],
            ],
            'variedades' => [
                ['Garrafa Térmica 500 ml', 'Utilidades', 'Cozinha', 'Marca Exemplo', 'Fornecedor Exemplo', '', 'Preto', '49,90', '22,00', '', '', '10', '2', ''],
                ['Garrafa Térmica 500 ml', 'Utilidades', 'Cozinha', 'Marca Exemplo', 'Fornecedor Exemplo', '', 'Branco', '49,90', '22,00', '', '', '8', '2', ''],
                ['Caderno Universitário', 'Papelaria', '', '', '', '', 'Azul', '24,90', '11,00', '', '', '20', '5', ''],
                ['Ventilador de Mesa', 'Eletroportáteis', '', 'Marca Exemplo', '', '220 V', 'Branco', '139,90', '75,00', '', '', '4', '1', ''],
            ],
            default => [
                ['Camiseta Básica', 'Masculino', 'Camisetas', 'Marca Exemplo', 'Fornecedor Exemplo', 'P', 'Preto', '59,90', '25,00', '', '', '10', '2', 'Algodão'],
                ['Camiseta Básica', 'Masculino', 'Camisetas', 'Marca Exemplo', 'Fornecedor Exemplo', 'M', 'Preto', '59,90', '25,00', '', '', '12', '2', ''],
                ['Tênis Casual', 'Calçados', '', 'Marca Exemplo', '', '38', 'Branco', '219,90', '110,00', '', '', '4', '1', ''],
                ['Bolsa Transversal', 'Acessórios', 'Bolsas', '', '', '', 'Caramelo', '149,90', '70,00', '', '', '3', '1', ''],
            ],
        };
    }
}
