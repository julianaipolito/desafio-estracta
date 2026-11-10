<?php

declare(strict_types=1);

namespace ProvaSpider\Spider;

use LogicException;
use ProvaSpider\Contract\Spider;
use ProvaSpider\ValueObject\CadastroContribuinte;
use ProvaSpider\ValueObject\Consulta;

final class SefazBaSpider implements Spider
{
    public const URL = 'https://portal.sefaz.ba.gov.br/scripts/cadastro/cadastroBa/consultaBa.asp';

    public function consultar(Consulta $consulta): CadastroContribuinte
    {
        // TODO candidato: analisar formulário, consultar, interpretar e mapear o resultado.
        throw new LogicException('Spider ainda não implementado: esta é a tarefa da prova técnica.');
    }
}
