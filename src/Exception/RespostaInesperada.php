<?php

declare(strict_types=1);

namespace ProvaSpider\Exception;

/**
 * A página não tem um cadastro nem uma mensagem conhecida do portal
 * (layout alterado, página de manutenção, corpo vazio).
 */
class RespostaInesperada extends ConsultaException
{
}
