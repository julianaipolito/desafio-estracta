<?php

declare(strict_types=1);

namespace ProvaSpider\Exception;

use Throwable;

/**
 * O portal respondeu com status de erro (4xx ou 500) ou excedeu o limite de
 * redirecionamentos.
 */
class FalhaHttp extends ConsultaException
{
    public function __construct(string $message, public readonly int $status, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
