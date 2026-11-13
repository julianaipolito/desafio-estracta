<?php

declare(strict_types=1);

namespace ProvaSpider\Http;

/**
 * Resposta HTTP já lida por completo, independente da biblioteca de transporte.
 */
final class HttpResponse
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        private readonly array $headers = [],
    ) {
    }

    /** Primeiro valor do header, sem diferenciar maiúsculas de minúsculas no nome. */
    public function header(string $nome): ?string
    {
        foreach ($this->headers as $chave => $valores) {
            if (strcasecmp($chave, $nome) === 0) {
                return $valores[0] ?? null;
            }
        }

        return null;
    }
}
