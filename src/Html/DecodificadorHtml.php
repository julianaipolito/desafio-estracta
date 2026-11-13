<?php

declare(strict_types=1);

namespace ProvaSpider\Html;

use ValueError;

/**
 * Converte o corpo da resposta para UTF-8.
 *
 * O portal mistura codificações: o formulário e as páginas de aviso declaram
 * ISO-8859-1, a página de resultado declara e usa UTF-8, e o header
 * Content-Type não informa charset. Por isso a ordem é: header, depois <meta>,
 * depois os próprios bytes.
 */
final class DecodificadorHtml
{
    /** Navegadores tratam ISO-8859-1 como Windows-1252, que é um superconjunto dele. */
    private const LATIN1 = 'Windows-1252';

    public static function paraUtf8(string $corpo, ?string $contentType = null): string
    {
        $charset = self::capturar('/charset\s*=\s*["\']?([\w.:-]+)/i', $contentType ?? '')
            ?? self::capturar('/<meta\b[^>]*?charset\s*=\s*["\']?([\w.:-]+)/i', $corpo);

        if ($charset !== null && !in_array($charset, ['utf-8', 'utf8'], true)) {
            $origem = in_array($charset, ['iso-8859-1', 'latin1', 'us-ascii', 'ascii'], true) ? self::LATIN1 : $charset;
            try {
                return mb_convert_encoding($corpo, 'UTF-8', $origem);
            } catch (ValueError) {
                // Charset desconhecido pelo mbstring: decide pelos bytes, abaixo.
            }
        }

        return mb_check_encoding($corpo, 'UTF-8') ? $corpo : mb_convert_encoding($corpo, 'UTF-8', self::LATIN1);
    }

    private static function capturar(string $padrao, string $texto): ?string
    {
        return preg_match($padrao, $texto, $m) === 1 ? strtolower($m[1]) : null;
    }
}
