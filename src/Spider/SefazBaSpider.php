<?php

declare(strict_types=1);

namespace ProvaSpider\Spider;

use ProvaSpider\Contract\HttpClient;
use ProvaSpider\Contract\Spider;
use ProvaSpider\Exception\FalhaHttp;
use ProvaSpider\Exception\ServicoIndisponivel;
use ProvaSpider\Extrator\ExtratorCadastroBa;
use ProvaSpider\Html\DecodificadorHtml;
use ProvaSpider\Http\GuzzleHttpClient;
use ProvaSpider\Http\HttpResponse;
use ProvaSpider\ValueObject\CadastroContribuinte;
use ProvaSpider\ValueObject\Consulta;

/**
 * Consulta por CNPJ no cadastro de contribuintes da SEFAZ-BA.
 *
 * Reproduz o navegador: abre o formulário (o portal cria a sessão ASP nesse
 * passo) e envia o CNPJ para result.asp. Quando não há cadastro ou o CNPJ é
 * recusado, o portal redireciona para páginas de aviso; o transporte segue o
 * redirecionamento e o extrator reconhece o aviso.
 */
final class SefazBaSpider implements Spider
{
    public const URL = 'https://portal.sefaz.ba.gov.br/scripts/cadastro/cadastroBa/consultaBa.asp';
    public const URL_RESULTADO = 'https://portal.sefaz.ba.gov.br/scripts/cadastro/cadastroBa/result.asp';

    private const STATUS_INDISPONIVEL = [502, 503, 504];

    public function __construct(
        private readonly HttpClient $http = new GuzzleHttpClient(),
        private readonly ExtratorCadastroBa $extrator = new ExtratorCadastroBa(),
    ) {
    }

    public function consultar(Consulta $consulta): CadastroContribuinte
    {
        $this->exigirSucesso($this->http->get(self::URL), 'abrir o formulário');

        $resposta = $this->http->postForm(self::URL_RESULTADO, $this->camposDoFormulario($consulta));
        $this->exigirSucesso($resposta, 'consultar o CNPJ');

        return $this->extrator->extrair(
            DecodificadorHtml::paraUtf8($resposta->body, $resposta->header('Content-Type'))
        );
    }

    /**
     * Campos que o navegador envia ao clicar em "CNPJ ->". O servidor escolhe o
     * tipo de consulta pelo nome do botão (B1); sem ele, ou com o CNPJ mascarado,
     * redireciona para param_errado.htm. Os campos de CPF e IE não são enviados.
     *
     * @return array<string, string>
     */
    private function camposDoFormulario(Consulta $consulta): array
    {
        return [
            'sefp' => '1',
            'estado' => 'BA',
            'CGC' => $consulta->cnpj,
            'B1' => 'CNPJ  ->',
        ];
    }

    private function exigirSucesso(HttpResponse $resposta, string $etapa): void
    {
        $status = $resposta->status;
        if ($status >= 200 && $status < 300) {
            return;
        }

        if (in_array($status, self::STATUS_INDISPONIVEL, true)) {
            throw new ServicoIndisponivel("A SEFAZ-BA está indisponível (HTTP {$status}) ao {$etapa}.");
        }

        throw new FalhaHttp("A SEFAZ-BA respondeu HTTP {$status} ao {$etapa}.", $status);
    }
}
