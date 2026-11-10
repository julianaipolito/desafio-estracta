<?php

declare(strict_types=1);

namespace ProvaSpider\ValueObject;

use JsonSerializable;

/**
 * Esquema inicial da prova, a validar com os campos efetivamente publicados.
 * null representa campo ausente; identificadores são strings para preservar zeros.
 */
final class CadastroContribuinte implements JsonSerializable
{
    public function __construct(
        public readonly ?string $inscricaoEstadual,
        public readonly ?string $cnpj,
        public readonly ?string $razaoSocial,
        public readonly ?string $nomeFantasia,
        public readonly ?string $situacaoCadastral,
        public readonly ?string $endereco,
        public readonly ?string $atividadeEconomica,
        public readonly ?string $regimeApuracao,
    ) {
    }

    /** @return array<string, string|null> */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
