<?php

declare(strict_types=1);

/** Lê uma fixture como bytes crus, sem conversão de codificação. */
function fixture(string $nome): string
{
    $conteudo = file_get_contents(__DIR__ . '/Fixtures/' . $nome);
    if ($conteudo === false) {
        throw new RuntimeException("Fixture não encontrada: {$nome}");
    }

    return $conteudo;
}
