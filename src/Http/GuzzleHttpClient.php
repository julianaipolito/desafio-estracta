<?php

declare(strict_types=1);

namespace ProvaSpider\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\RequestOptions;
use ProvaSpider\Contract\HttpClient;
use ProvaSpider\Exception\FalhaHttp;
use ProvaSpider\Exception\ServicoIndisponivel;

/**
 * Transporte HTTP com Guzzle.
 *
 * Só traduz falhas de transporte. Respostas 4xx/5xx voltam normalmente para o
 * spider, que é quem sabe o que cada status significa na consulta.
 */
final class GuzzleHttpClient implements HttpClient
{
    public const TIMEOUT = 15;
    public const CONNECT_TIMEOUT = 5;
    public const MAX_REDIRECTS = 3;

    private readonly Client $client;

    /**
     * @param callable|null $handler Handler do Guzzle; nos testes, um MockHandler.
     */
    public function __construct(?callable $handler = null)
    {
        $config = [
            RequestOptions::TIMEOUT => self::TIMEOUT,
            RequestOptions::CONNECT_TIMEOUT => self::CONNECT_TIMEOUT,
            // strict = false faz o 302 depois do POST virar GET, como no navegador.
            // O portal redireciona para páginas .htm, que respondem 405 a um POST.
            RequestOptions::ALLOW_REDIRECTS => [
                'max' => self::MAX_REDIRECTS,
                'strict' => false,
                'referer' => true,
            ],
            // Guarda o ASPSESSIONID recebido no formulário e o reenvia no POST.
            RequestOptions::COOKIES => new CookieJar(),
            RequestOptions::HTTP_ERRORS => false,
        ];

        if ($handler !== null) {
            $config['handler'] = $handler;
        }

        $this->client = new Client($config);
    }

    public function get(string $url): HttpResponse
    {
        return $this->enviar('GET', $url, []);
    }

    public function postForm(string $url, array $campos): HttpResponse
    {
        return $this->enviar('POST', $url, [RequestOptions::FORM_PARAMS => $campos]);
    }

    /**
     * @param array<string, mixed> $opcoes
     */
    private function enviar(string $metodo, string $url, array $opcoes): HttpResponse
    {
        try {
            $resposta = $this->client->request($metodo, $url, $opcoes);
        } catch (TooManyRedirectsException $e) {
            throw new FalhaHttp(
                sprintf('Mais de %d redirecionamentos ao acessar %s.', self::MAX_REDIRECTS, $url),
                $e->getResponse()?->getStatusCode() ?? 0,
                $e,
            );
        } catch (TransferException $e) {
            // Recusa de conexão, DNS, TLS e tempo limite esgotado.
            throw new ServicoIndisponivel("Falha de comunicação com {$url}: {$e->getMessage()}", 0, $e);
        }

        return new HttpResponse($resposta->getStatusCode(), (string) $resposta->getBody(), $resposta->getHeaders());
    }
}
