<?php

declare(strict_types=1);

namespace ProvaSpider\Contract;

use ProvaSpider\Exception\ConsultaException;
use ProvaSpider\ValueObject\CadastroContribuinte;
use ProvaSpider\ValueObject\Consulta;

interface Spider
{
    /**
     * @throws ConsultaException Quando não houver resultado ou a consulta falhar.
     */
    public function consultar(Consulta $consulta): CadastroContribuinte;
}
