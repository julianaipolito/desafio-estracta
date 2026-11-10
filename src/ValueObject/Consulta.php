<?php

declare(strict_types=1);

namespace ProvaSpider\ValueObject;

use InvalidArgumentException;

final class Consulta
{
    public readonly string $cnpj;

    public function __construct(string $cnpj)
    {
        $normalizado = preg_replace('/[.\/\-\s]+/', '', $cnpj) ?? '';

        if ($normalizado === '' || preg_match('/^[0-9]+$/D', $normalizado) !== 1) {
            throw new InvalidArgumentException('O CNPJ deve conter apenas números e pontuação de máscara.');
        }

        if (strlen($normalizado) !== 14) {
            throw new InvalidArgumentException('O CNPJ deve ter 14 dígitos.');
        }

        $this->cnpj = $normalizado;
    }
}
