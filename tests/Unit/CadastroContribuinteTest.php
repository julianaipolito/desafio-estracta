<?php

declare(strict_types=1);

use ProvaSpider\ValueObject\CadastroContribuinte;

it('serializa o objeto de valor preservando identificadores e campos ausentes', function () {
    $cadastro = new CadastroContribuinte(
        inscricaoEstadual: '000000000',
        cnpj: '00000000000000',
        razaoSocial: 'EMPRESA FICTÍCIA DE TESTE',
        nomeFantasia: null,
        situacaoCadastral: 'VALOR FICTÍCIO',
        endereco: null,
        atividadeEconomica: null,
        regimeApuracao: null,
    );

    $resultado = json_decode(json_encode($cadastro, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

    expect($resultado)
        ->toHaveKey('nomeFantasia', null)
        ->and($resultado['cnpj'])->toBe('00000000000000')
        ->and($resultado['inscricaoEstadual'])->toBe('000000000');
});
