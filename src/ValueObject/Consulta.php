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

        if (!self::digitosVerificadoresValidos($normalizado)) {
            throw new InvalidArgumentException('O CNPJ informado tem dígitos verificadores inválidos.');
        }

        $this->cnpj = $normalizado;
    }

    /**
     * Módulo 11 com pesos de 2 a 9, da direita para a esquerda. O portal recusa
     * CNPJ com DV errado; validar aqui evita uma requisição que já se sabe inútil.
     */
    private static function digitosVerificadoresValidos(string $cnpj): bool
    {
        foreach ([12, 13] as $posicaoDv) {
            $soma = 0;
            for ($i = 0; $i < $posicaoDv; $i++) {
                $soma += (int) $cnpj[$i] * (2 + ($posicaoDv - 1 - $i) % 8);
            }

            $resto = $soma % 11;
            if ((int) $cnpj[$posicaoDv] !== ($resto < 2 ? 0 : 11 - $resto)) {
                return false;
            }
        }

        return true;
    }
}
