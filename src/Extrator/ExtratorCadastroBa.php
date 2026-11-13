<?php

declare(strict_types=1);

namespace ProvaSpider\Extrator;

use DOMDocument;
use DOMElement;
use DOMXPath;
use ProvaSpider\Exception\RespostaInesperada;
use ProvaSpider\ValueObject\CadastroContribuinte;

/**
 * Interpreta o HTML devolvido pela consulta (já em UTF-8) e monta o CadastroContribuinte.
 *
 * Não conhece HTTP. Na página de resultado cada dado aparece como
 * <td><b>Rótulo:</b>&nbsp;valor</td>, em tabelas aninhadas e com marcação
 * irregular, então os valores são localizados pelo rótulo, não pela posição.
 *
 * @phpstan-type Campo array{rotulo: string, valor: ?string, celula: DOMElement}
 */
final class ExtratorCadastroBa
{
    /** Ordem das partes do endereço do estabelecimento no texto final. */
    private const PARTES_ENDERECO = ['Logradouro', 'Número', 'Complemento', 'Bairro/Distrito', 'Município', 'UF', 'CEP'];

    private const SEM_ACENTO = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'ê' => 'e', 'í' => 'i',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ü' => 'u', 'ç' => 'c',
    ];

    /**
     * @throws RespostaInesperada
     */
    public function extrair(string $html): CadastroContribuinte
    {
        $xpath = $this->carregar($html);
        $campos = $this->camposRotulados($xpath);

        $inscricao = $this->identificador($this->campo($campos, 'Inscrição Estadual')['valor'] ?? null);
        $razaoSocial = $this->campo($campos, 'Razão Social')['valor'] ?? null;
        if ($inscricao === null && $razaoSocial === null) {
            throw new RespostaInesperada('A resposta da SEFAZ-BA não contém os dados de um cadastro.');
        }

        return new CadastroContribuinte(
            inscricaoEstadual: $inscricao,
            cnpj: $this->identificador($this->campo($campos, 'CNPJ')['valor'] ?? null),
            razaoSocial: $razaoSocial,
            nomeFantasia: $this->campo($campos, 'Nome Fantasia')['valor'] ?? null,
            situacaoCadastral: $this->campo($campos, 'Situação Cadastral Vigente')['valor'] ?? null,
            endereco: $this->endereco($campos),
            atividadeEconomica: $this->atividadePrincipal($campos),
            regimeApuracao: $this->campo($campos, 'Forma de pagamento')['valor'] ?? null,
        );
    }

    private function carregar(string $html): DOMXPath
    {
        if (trim($html) === '') {
            throw new RespostaInesperada('A SEFAZ-BA devolveu uma resposta vazia.');
        }

        // Tudo acima de ASCII vira entidade numérica: assim o libxml não depende
        // do charset declarado na página para ler os acentos.
        $html = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');

        $dom = new DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        return new DOMXPath($dom);
    }

    /**
     * Células sem tabela aninhada cujo texto tem a forma "Rótulo: valor", na ordem da página.
     *
     * @return list<Campo>
     */
    private function camposRotulados(DOMXPath $xpath): array
    {
        $campos = [];
        foreach ($xpath->query('//td[not(.//td)]') ?: [] as $celula) {
            /** @var DOMElement $celula */
            $partes = explode(':', $this->normalizar($celula->textContent) ?? '', 2);
            if (count($partes) === 2) {
                $campos[] = [
                    'rotulo' => $this->chave($partes[0]),
                    'valor' => $this->normalizar($partes[1]),
                    'celula' => $celula,
                ];
            }
        }

        return $campos;
    }

    /**
     * Primeira ocorrência do rótulo, opcionalmente restrita a uma tabela.
     * Não procura nas ocorrências seguintes: um valor vazio aqui é null, e não
     * o valor de outra seção da página.
     *
     * @param list<Campo> $campos
     * @return Campo|null
     */
    private function campo(array $campos, string $rotulo, ?DOMElement $tabela = null): ?array
    {
        $chave = $this->chave($rotulo);
        foreach ($campos as $campo) {
            if ($campo['rotulo'] !== $chave) {
                continue;
            }
            if ($tabela === null || $this->tabelaDe($campo['celula'])?->isSameNode($tabela)) {
                return $campo;
            }
        }

        return null;
    }

    /**
     * Número, CEP, Município e UF se repetem nas seções de correspondência e do
     * contador. Só a tabela do estabelecimento tem "Logradouro", então ela
     * delimita a busca das demais partes.
     *
     * @param list<Campo> $campos
     */
    private function endereco(array $campos): ?string
    {
        $logradouro = $this->campo($campos, 'Logradouro');
        $tabela = $logradouro === null ? null : $this->tabelaDe($logradouro['celula']);
        if ($tabela === null) {
            return null;
        }

        $partes = [];
        foreach (self::PARTES_ENDERECO as $rotulo) {
            $valor = $this->campo($campos, $rotulo, $tabela)['valor'] ?? null;
            if ($valor !== null) {
                $partes[] = $valor;
            }
        }

        return $partes === [] ? null : implode(', ', $partes);
    }

    /**
     * O rótulo ocupa uma linha e o código com a descrição vem na linha seguinte.
     * Se a linha seguinte tiver outro rótulo em negrito, a atividade não foi publicada.
     *
     * @param list<Campo> $campos
     */
    private function atividadePrincipal(array $campos): ?string
    {
        $campo = $this->campo($campos, 'Atividade Econômica Principal');
        if ($campo === null || $campo['valor'] !== null) {
            return $campo['valor'] ?? null;
        }

        $linha = $campo['celula']->parentNode;
        $seguinte = $linha instanceof DOMElement ? $linha->nextElementSibling : null;
        if ($seguinte === null || $seguinte->getElementsByTagName('b')->length > 0) {
            return null;
        }

        return $this->normalizar($seguinte->textContent);
    }

    private function tabelaDe(DOMElement $celula): ?DOMElement
    {
        for ($no = $celula->parentNode; $no instanceof DOMElement; $no = $no->parentNode) {
            if (strtolower($no->nodeName) === 'table') {
                return $no;
            }
        }

        return null;
    }

    /**
     * Primeiro número do valor, sem a máscara e como string para manter os zeros
     * à esquerda: "000.478.696 NO (Inscrição Única)" vira "000478696".
     */
    private function identificador(?string $valor): ?string
    {
        if ($valor === null || preg_match('/^[\d.\/-]+/', $valor, $m) !== 1) {
            return null;
        }

        $digitos = preg_replace('/\D/', '', $m[0]) ?? '';

        return $digitos === '' ? null : $digitos;
    }

    /** Colapsa espaços, inclusive &nbsp; (U+00A0); texto vazio vira null. */
    private function normalizar(string $texto): ?string
    {
        $texto = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $texto) ?? '');

        return $texto === '' ? null : $texto;
    }

    /** Forma comparável de um texto: minúsculas e sem acentos ("Município" vira "municipio"). */
    private function chave(string $texto): string
    {
        return strtr(mb_strtolower($this->normalizar($texto) ?? '', 'UTF-8'), self::SEM_ACENTO);
    }
}
