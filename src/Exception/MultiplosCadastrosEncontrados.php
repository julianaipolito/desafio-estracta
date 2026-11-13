<?php

declare(strict_types=1);

namespace ProvaSpider\Exception;

/**
 * A página trouxe mais de uma inscrição estadual. O contrato devolve um cadastro
 * por consulta, então nenhuma delas é escolhida arbitrariamente.
 */
class MultiplosCadastrosEncontrados extends ConsultaException
{
    /**
     * @param list<string> $inscricoes
     */
    public function __construct(public readonly array $inscricoes)
    {
        parent::__construct(sprintf(
            'A SEFAZ-BA retornou %d inscrições estaduais (%s) para o CNPJ; o contrato prevê um cadastro por consulta.',
            count($inscricoes),
            implode(', ', $inscricoes),
        ));
    }
}
