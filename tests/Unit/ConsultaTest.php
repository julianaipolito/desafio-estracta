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
