<?php

declare(strict_types=1);

namespace ProvaSpider\Http;

use Closure;
use InvalidArgumentException;
use ProvaSpider\Contract\HttpClient;
use ProvaSpider\Exception\ServicoIndisponivel;

/**
 * Repete a requisição apenas em falhas recuperáveis: erro de conexão ou tempo
 * limite (ServicoIndisponivel) e status 502, 503 e 504, com espera exponencial.
 *
 * Repetir o POST é seguro porque a consulta pública é somente leitura.
 * 4xx, 500 e HTML inesperado não mudam ao tentar de novo e não são repetidos.
 */
final class RetryingHttpClient implements HttpClient
{
    private const STATUS_RECUPERAVEIS = [502, 503, 504];

    private readonly Closure $dormir;

    /**
     * @param int $maxTentativas Total de tentativas, incluindo a primeira.
     * @param callable(int): void|null $dormir Recebe a espera em milissegundos; substituível nos testes.
     */
    public function __construct(
        private readonly HttpClient $interno,
        private readonly int $maxTentativas = 3,
        private readonly int $esperaInicialMs = 500,
        ?callable $dormir = null,
    ) {
        if ($maxTentativas < 1) {
            throw new InvalidArgumentException('O número de tentativas deve ser pelo menos 1.');
        }

        $this->dormir = $dormir !== null
            ? Closure::fromCallable($dormir)
            : static fn (int $ms) => usleep($ms * 1000);
    }

    public function get(string $url): HttpResponse
    {
        return $this->tentar(fn (): HttpResponse => $this->interno->get($url));
    }

    public function postForm(string $url, array $campos): HttpResponse
    {
        return $this->tentar(fn (): HttpResponse => $this->interno->postForm($url, $campos));
    }

    /**
     * @param Closure(): HttpResponse $requisicao
     */
    private function tentar(Closure $requisicao): HttpResponse
    {
        for ($tentativa = 1; ; $tentativa++) {
            $ultima = $tentativa >= $this->maxTentativas;

            try {
                $resposta = $requisicao();
            } catch (ServicoIndisponivel $e) {
                if ($ultima) {
                    throw $e;
                }
                $this->esperar($tentativa);
                continue;
            }

            if ($ultima || !in_array($resposta->status, self::STATUS_RECUPERAVEIS, true)) {
                return $resposta;
            }
            $this->esperar($tentativa);
        }
    }

    private function esperar(int $tentativa): void
    {
        ($this->dormir)($this->esperaInicialMs * 2 ** ($tentativa - 1));
    }
}
