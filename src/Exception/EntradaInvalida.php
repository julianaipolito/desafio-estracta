<?php

declare(strict_types=1);

namespace ProvaSpider\Exception;

/**
 * O portal recusou o CNPJ ("Cnpj incorreto - dígito verificador").
 *
 * A Consulta já valida os dígitos verificadores antes da requisição; esta
 * exceção cobre o caso de o portal discordar dessa validação.
 */
class EntradaInvalida extends ConsultaException
{
}
