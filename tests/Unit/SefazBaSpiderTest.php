<?php

declare(strict_types=1);

use ProvaSpider\Exception\CadastroNaoEncontrado;
use ProvaSpider\Exception\EntradaInvalida;
use ProvaSpider\Exception\FalhaHttp;
use ProvaSpider\Exception\RespostaInesperada;
use ProvaSpider\Exception\ServicoIndisponivel;
use ProvaSpider\Http\HttpResponse;
use ProvaSpider\Spider\SefazBaSpider;
use ProvaSpider\Tests\Fakes\FakeHttpClient;
use ProvaSpider\ValueObject\Consulta;

function paginaDoFormulario(): HttpResponse
{
    return new HttpResponse(200, '<form action="result.asp" method="post"></form>', ['Content-Type' => ['text/html']]);
}

function paginaDaFixture(string $nome): HttpResponse
{
    return new HttpResponse(200, fixture("sefaz-ba/{$nome}"), ['Content-Type' => ['text/html']]);
}

it('abre o formulário e envia o CNPJ sem máscara pelo botão de CNPJ', function () {
    $http = new FakeHttpClient([paginaDoFormulario(), paginaDaFixture('resultado-cadastro.html')]);

    (new SefazBaSpider($http))->consultar(new Consulta('01.234.567/0001-95'));

    expect($http->requisicoes)->toBe([
        [
            'metodo' => 'GET',
            'url' => 'https://portal.sefaz.ba.gov.br/scripts/cadastro/cadastroBa/consultaBa.asp',
            'campos' => [],
        ],
        [
            'metodo' => 'POST',
            'url' => 'https://portal.sefaz.ba.gov.br/scripts/cadastro/cadastroBa/result.asp',
            'campos' => ['sefp' => '1', 'estado' => 'BA', 'CGC' => '01234567000195', 'B1' => 'CNPJ  ->'],
        ],
    ]);
});

it('devolve o cadastro extraído com acentos e zeros preservados', function () {
    $http = new FakeHttpClient([paginaDoFormulario(), paginaDaFixture('resultado-cadastro.html')]);

    $cadastro = (new SefazBaSpider($http))->consultar(new Consulta('01234567000195'));

    expect($cadastro->razaoSocial)->toBe('AÇAÍ DO JOÃO COMÉRCIO DE ALIMENTOS LTDA')
        ->and($cadastro->cnpj)->toBe('01234567000195')
        ->and($cadastro->inscricaoEstadual)->toBe('001234567');
});

it('não usa o CNPJ da entrada quando a página não o exibe', function () {
    $http = new FakeHttpClient([paginaDoFormulario(), paginaDaFixture('resultado-campos-ausentes.html')]);

    $cadastro = (new SefazBaSpider($http))->consultar(new Consulta('01234567000195'));

    expect($cadastro->cnpj)->toBeNull();
});

it('recusa o cadastro de um CNPJ diferente do consultado', function () {
    $http = new FakeHttpClient([paginaDoFormulario(), paginaDaFixture('resultado-cadastro.html')]);

    expect(fn () => (new SefazBaSpider($http))->consultar(new Consulta('11.222.333/0001-81')))
        ->toThrow(RespostaInesperada::class, 'diferente do consultado');
});

it('classifica as páginas sem cadastro', function (string $fixture, string $excecao) {
    $http = new FakeHttpClient([paginaDoFormulario(), paginaDaFixture($fixture)]);

    expect(fn () => (new SefazBaSpider($http))->consultar(new Consulta('11222333000181')))
        ->toThrow($excecao);
})->with([
    'CNPJ sem cadastro' => ['consulta-vazia.html', CadastroNaoEncontrado::class],
    'CNPJ recusado pelo portal' => ['cnpj-incorreto.html', EntradaInvalida::class],
    'HTML inesperado' => ['pagina-manutencao.html', RespostaInesperada::class],
]);

it('lança FalhaHttp com o status de erro recebido', function (int $status) {
    $http = new FakeHttpClient([paginaDoFormulario(), new HttpResponse($status, 'erro')]);

    try {
        (new SefazBaSpider($http))->consultar(new Consulta('01234567000195'));
        $this->fail('Deveria ter lançado FalhaHttp.');
    } catch (FalhaHttp $e) {
        expect($e->status)->toBe($status);
    }
})->with([403, 404, 500]);

it('lança ServicoIndisponivel para 502, 503 e 504', function (int $status) {
    $http = new FakeHttpClient([paginaDoFormulario(), new HttpResponse($status, '')]);

    expect(fn () => (new SefazBaSpider($http))->consultar(new Consulta('01234567000195')))
        ->toThrow(ServicoIndisponivel::class, "HTTP {$status}");
})->with([502, 503, 504]);

it('propaga a falha de transporte e não envia o CNPJ', function () {
    $http = new FakeHttpClient([new ServicoIndisponivel('Falha de comunicação')]);

    expect(fn () => (new SefazBaSpider($http))->consultar(new Consulta('01234567000195')))
        ->toThrow(ServicoIndisponivel::class)
        ->and($http->requisicoes)->toHaveCount(1);
});

it('não envia o CNPJ quando o formulário responde com erro', function () {
    $http = new FakeHttpClient([new HttpResponse(500, '')]);

    expect(fn () => (new SefazBaSpider($http))->consultar(new Consulta('01234567000195')))
        ->toThrow(FalhaHttp::class, 'ao abrir o formulário')
        ->and($http->requisicoes)->toHaveCount(1);
});
