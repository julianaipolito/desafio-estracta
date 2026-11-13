<?php

declare(strict_types=1);

use ProvaSpider\Exception\FalhaHttp;
use ProvaSpider\Exception\ServicoIndisponivel;
use ProvaSpider\Http\HttpResponse;
use ProvaSpider\Http\RetryingHttpClient;
use ProvaSpider\Tests\Fakes\FakeHttpClient;

beforeEach(function () {
    $this->esperas = [];
    $this->comRetry = fn (FakeHttpClient $interno, int $tentativas = 3) => new RetryingHttpClient(
        $interno,
        $tentativas,
        100,
        function (int $ms): void {
            $this->esperas[] = $ms;
        },
    );
});

it('tenta de novo após falha de conexão e devolve a resposta seguinte', function () {
    $interno = new FakeHttpClient([new ServicoIndisponivel('timeout'), new HttpResponse(200, 'ok')]);

    $resposta = ($this->comRetry)($interno)->postForm('https://portal.test', ['CGC' => '01234567000195']);

    expect($resposta->body)->toBe('ok')
        ->and($interno->requisicoes)->toHaveCount(2)
        ->and($interno->requisicoes[1]['campos'])->toBe(['CGC' => '01234567000195'])
        ->and($this->esperas)->toBe([100]);
});

it('repete 502, 503 e 504 com espera exponencial até o limite', function () {
    $interno = new FakeHttpClient([new HttpResponse(502, ''), new HttpResponse(503, ''), new HttpResponse(504, '')]);

    $resposta = ($this->comRetry)($interno)->get('https://portal.test');

    expect($resposta->status)->toBe(504)
        ->and($interno->requisicoes)->toHaveCount(3)
        ->and($this->esperas)->toBe([100, 200]);
});

it('relança a falha de conexão depois da última tentativa', function () {
    $interno = new FakeHttpClient([new ServicoIndisponivel('primeira'), new ServicoIndisponivel('segunda')]);

    expect(fn () => ($this->comRetry)($interno, 2)->get('https://portal.test'))
        ->toThrow(ServicoIndisponivel::class, 'segunda');
});

it('não repete respostas que não mudam numa nova tentativa', function (int $status) {
    $interno = new FakeHttpClient([new HttpResponse($status, '')]);

    $resposta = ($this->comRetry)($interno)->get('https://portal.test');

    expect($resposta->status)->toBe($status)
        ->and($interno->requisicoes)->toHaveCount(1)
        ->and($this->esperas)->toBe([]);
})->with([200, 404, 500]);

it('não repete FalhaHttp', function () {
    $interno = new FakeHttpClient([new FalhaHttp('Mais de 3 redirecionamentos', 302)]);

    expect(fn () => ($this->comRetry)($interno)->get('https://portal.test'))->toThrow(FalhaHttp::class)
        ->and($interno->requisicoes)->toHaveCount(1);
});

it('exige pelo menos uma tentativa', function () {
    new RetryingHttpClient(new FakeHttpClient([]), 0);
})->throws(InvalidArgumentException::class);
