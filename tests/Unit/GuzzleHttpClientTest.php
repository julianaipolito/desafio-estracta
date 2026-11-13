<?php

declare(strict_types=1);

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use ProvaSpider\Exception\FalhaHttp;
use ProvaSpider\Exception\ServicoIndisponivel;
use ProvaSpider\Http\GuzzleHttpClient;

/**
 * Cliente com a configuração real, mas com um MockHandler no lugar da rede.
 * O histórico registra cada requisição que sairia de fato, já com cookies e
 * redirecionamentos aplicados.
 *
 * @param list<Response|Throwable> $respostas
 * @param list<array{request: Psr\Http\Message\RequestInterface, options: array<string, mixed>}> $historico
 */
function guzzleSemRede(array $respostas, array &$historico = []): GuzzleHttpClient
{
    $pilha = HandlerStack::create(new MockHandler($respostas));
    $pilha->push(Middleware::history($historico));

    return new GuzzleHttpClient($pilha);
}

it('faz GET e devolve status, corpo e headers', function () {
    $http = guzzleSemRede([new Response(200, ['Content-Type' => 'text/html'], '<html>ok</html>')]);

    $resposta = $http->get('https://portal.test/consultaBa.asp');

    expect($resposta->status)->toBe(200)
        ->and($resposta->body)->toBe('<html>ok</html>')
        ->and($resposta->header('content-type'))->toBe('text/html');
});

it('envia os campos como formulário url-encoded', function () {
    $historico = [];
    $http = guzzleSemRede([new Response(200)], $historico);

    $http->postForm('https://portal.test/result.asp', ['CGC' => '01234567000195', 'B1' => 'CNPJ  ->']);

    $requisicao = $historico[0]['request'];
    expect($requisicao->getMethod())->toBe('POST')
        ->and($requisicao->getHeaderLine('Content-Type'))->toBe('application/x-www-form-urlencoded')
        ->and((string) $requisicao->getBody())->toBe('CGC=01234567000195&B1=CNPJ++-%3E');
});

it('reenvia o cookie de sessão recebido no formulário', function () {
    $historico = [];
    $http = guzzleSemRede([
        new Response(200, ['Set-Cookie' => 'ASPSESSIONIDCGACSCTC=OHMNDKJDAOEC; secure; path=/']),
        new Response(200),
    ], $historico);

    $http->get('https://portal.test/consultaBa.asp');
    $http->postForm('https://portal.test/result.asp', ['CGC' => '01234567000195']);

    expect($historico[0]['request']->hasHeader('Cookie'))->toBeFalse()
        ->and($historico[1]['request']->getHeaderLine('Cookie'))->toBe('ASPSESSIONIDCGACSCTC=OHMNDKJDAOEC');
});

it('segue o 302 do POST com um GET, como o navegador', function () {
    $historico = [];
    $http = guzzleSemRede([
        new Response(302, ['Location' => 'https://portal.test/consulta_vazia.htm']),
        new Response(200, [], 'Nenhum registro encontrado !'),
    ], $historico);

    $resposta = $http->postForm('https://portal.test/result.asp', ['CGC' => '11222333000181']);

    $redirecionada = $historico[1]['request'];
    expect($resposta->body)->toBe('Nenhum registro encontrado !')
        ->and($redirecionada->getMethod())->toBe('GET')
        ->and((string) $redirecionada->getUri())->toBe('https://portal.test/consulta_vazia.htm');
});

it('lança FalhaHttp quando passa de 3 redirecionamentos', function () {
    $loop = array_map(
        fn () => new Response(302, ['Location' => 'https://portal.test/loop']),
        range(1, 4),
    );

    expect(fn () => guzzleSemRede($loop)->get('https://portal.test/loop'))
        ->toThrow(FalhaHttp::class, 'Mais de 3 redirecionamentos');
});

it('devolve respostas 4xx e 5xx sem lançar exceção', function (int $status) {
    $http = guzzleSemRede([new Response($status, [], 'erro')]);

    expect($http->get('https://portal.test')->status)->toBe($status);
})->with([404, 500, 503]);

it('converte falha de conexão e tempo limite em ServicoIndisponivel', function () {
    $http = guzzleSemRede([
        new ConnectException('cURL error 28: Operation timed out', new Request('GET', 'https://portal.test')),
    ]);

    expect(fn () => $http->get('https://portal.test'))
        ->toThrow(ServicoIndisponivel::class, 'Operation timed out');
});

it('aplica tempo limite e limite de redirecionamentos a toda requisição', function () {
    $historico = [];
    guzzleSemRede([new Response(200)], $historico)->get('https://portal.test');

    $opcoes = $historico[0]['options'];
    expect($opcoes['timeout'])->toBe(15)
        ->and($opcoes['connect_timeout'])->toBe(5)
        ->and($opcoes['allow_redirects']['max'])->toBe(3)
        ->and($opcoes['allow_redirects']['strict'])->toBeFalse();
});
