<?php

declare(strict_types=1);

use ProvaSpider\Exception\CadastroNaoEncontrado;
use ProvaSpider\Spider\SefazBaSpider;
use ProvaSpider\ValueObject\Consulta;

// Estes testes acessam o portal real e ficam fora de `composer test`.
// Rode com `composer test:integration`; para outro CNPJ, defina SEFAZ_BA_CNPJ.

it('consulta um CNPJ com cadastro no portal', function () {
    // Padrão: Embasa, empresa estadual com cadastro público na SEFAZ-BA.
    $consulta = new Consulta(getenv('SEFAZ_BA_CNPJ') ?: '13.504.675/0001-10');

    $cadastro = (new SefazBaSpider())->consultar($consulta);

    expect($cadastro->cnpj)->toBe($consulta->cnpj)
        ->and($cadastro->inscricaoEstadual)->toMatch('/^\d+$/')
        ->and($cadastro->razaoSocial)->not->toBeNull()
        ->and($cadastro->situacaoCadastral)->not->toBeNull();
})->group('integration');

it('reconhece um CNPJ sem cadastro na Bahia', function () {
    expect(fn () => (new SefazBaSpider())->consultar(new Consulta('11.222.333/0001-81')))
        ->toThrow(CadastroNaoEncontrado::class);
})->group('integration');
