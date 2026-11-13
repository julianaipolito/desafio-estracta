<?php

declare(strict_types=1);

namespace ProvaSpider\Tests\Fakes;

use LogicException;
use ProvaSpider\Contract\HttpClient;
use ProvaSpider\Http\HttpResponse;
use Throwable;

/**
 * Transporte em memória: devolve (ou lança) as respostas na ordem e registra
 * cada requisição feita.
 */
final class FakeHttpClient implements HttpClient
{
    /** @var list<array{metodo: string, url: string, campos: array<string, string>}> */
    public array $requisicoes = [];

    /**
     * @param list<HttpResponse|Throwable> $respostas
     */
    public function __construct(private array $respostas)
    {
    }

    public function get(string $url): HttpResponse
    {
        return $this->responder('GET', $url, []);
    }

    public function postForm(string $url, array $campos): HttpResponse
    {
        return $this->responder('POST', $url, $campos);
    }

    /**
     * @param array<string, string> $campos
     */
    private function responder(string $metodo, string $url, array $campos): HttpResponse
    {
        $this->requisicoes[] = ['metodo' => $metodo, 'url' => $url, 'campos' => $campos];

        $proxima = array_shift($this->respostas)
            ?? throw new LogicException("Requisição não prevista no teste: {$metodo} {$url}");

        if ($proxima instanceof Throwable) {
            throw $proxima;
        }

        return $proxima;
    }
}
