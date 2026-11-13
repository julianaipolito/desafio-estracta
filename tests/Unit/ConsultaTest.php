<?php

declare(strict_types=1);

use ProvaSpider\ValueObject\Consulta;

it('remove a máscara do documento e preserva zeros à esquerda', function () {
    $consulta = new Consulta('00.000.000/0000-00');

    expect($consulta->cnpj)->toBe('00000000000000');
});

it('rejeita CNPJ com formato inválido', function (string $cnpj) {
    expect(fn () => new Consulta($cnpj))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'vazio' => [''],
    'letras' => ['abc'],
    'CNPJ curto' => ['123'],
    'CNPJ longo' => ['123456789012345'],
]);

it('aceita CNPJ com dígitos verificadores válidos', function (string $cnpj, string $esperado) {
    expect((new Consulta($cnpj))->cnpj)->toBe($esperado);
})->with([
    'com máscara' => ['01.234.567/0001-95', '01234567000195'],
    'sem máscara' => ['11222333000181', '11222333000181'],
    'com espaços' => [' 13 504 675 0001 10 ', '13504675000110'],
]);

it('rejeita CNPJ com dígito verificador errado antes de consultar', function (string $cnpj) {
    expect(fn () => new Consulta($cnpj))
        ->toThrow(InvalidArgumentException::class, 'dígitos verificadores');
})->with([
    'primeiro dígito' => ['01.234.567/0001-85'],
    'segundo dígito' => ['01.234.567/0001-96'],
]);
