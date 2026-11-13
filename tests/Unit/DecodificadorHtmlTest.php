<?php

declare(strict_types=1);

use ProvaSpider\Html\DecodificadorHtml;

it('mantém a página de resultado, que declara e usa UTF-8', function () {
    $bruto = fixture('sefaz-ba/resultado-cadastro.html');

    expect(DecodificadorHtml::paraUtf8($bruto, 'text/html'))->toBe($bruto)
        ->and($bruto)->toContain('AÇAÍ DO JOÃO');
});

it('converte Windows-1252 declarado como ISO-8859-1 na meta', function () {
    $bruto = fixture('sefaz-ba/pagina-manutencao.html');

    $html = DecodificadorHtml::paraUtf8($bruto, 'text/html');

    expect(mb_check_encoding($bruto, 'UTF-8'))->toBeFalse()
        ->and($html)->toContain('Sistema em manutenção.');
});

it('prioriza o charset do header Content-Type sobre a meta', function () {
    $bruto = mb_convert_encoding('<meta charset="utf-8"><p>Razão</p>', 'Windows-1252', 'UTF-8');

    expect(DecodificadorHtml::paraUtf8($bruto, 'text/html; charset=windows-1252'))
        ->toBe('<meta charset="utf-8"><p>Razão</p>');
});

it('decide pelos bytes quando nada é declarado', function (string $bruto) {
    expect(DecodificadorHtml::paraUtf8($bruto))->toBe('<p>Município</p>');
})->with([
    'UTF-8' => ['<p>Município</p>'],
    'Windows-1252' => [mb_convert_encoding('<p>Município</p>', 'Windows-1252', 'UTF-8')],
]);

it('não falha com charset desconhecido', function () {
    expect(DecodificadorHtml::paraUtf8('<p>Situação</p>', 'text/html; charset=x-inventado'))
        ->toBe('<p>Situação</p>');
});
