# Desafio estracta

Spider em PHP que consulta, **exclusivamente por CNPJ**, o cadastro público de contribuintes do ICMS da Bahia e devolve um objeto de valor imutável (`CadastroContribuinte`).

- Portal: <https://portal.sefaz.ba.gov.br/scripts/cadastro/cadastroBa/consultaBa.asp>
- Enunciado original: [docs/enunciado.md](docs/enunciado.md)
- Como desenvolvi, decisões e dificuldades: [docs/desenvolvimento.md](docs/desenvolvimento.md)

## Como executar

Requisitos: PHP 8.1 ou superior com as extensões `dom`, `mbstring`, `curl` e `openssl`, e Composer 2.

```bash
composer install
composer test
php bin/consultar.php 13.504.675/0001-10
```

O CNPJ pode ser informado com ou sem máscara. Em caso de sucesso, o comando imprime o JSON:

```json
{
    "inscricaoEstadual": "000665571",
    "cnpj": "13504675000110",
    "razaoSocial": "EMPRESA BAIANA DE ÁGUAS E SANEAMENTO S/A EMBASA",
    "nomeFantasia": "EMBASA",
    "situacaoCadastral": "ATIVO",
    "endereco": "AVENIDA LUIS VIANA, 420, EDIF SEDUR, CENTRO ADMINISTRATIVO DA BAHIA, SALVADOR, BA, 41745-010",
    "atividadeEconomica": "3600601 - Captação, tratamento e distribuição de água",
    "regimeApuracao": "C/CORRENTE FISCAL"
}
```

Em caso de erro, escreve a mensagem em `stderr` e encerra com código 1.

`composer test` roda a suíte sem acessar a internet. Os testes que acessam o portal real ficam separados:

```bash
composer test:integration
SEFAZ_BA_CNPJ=15139629000194 composer test:integration   # outro CNPJ
```

> No Windows, se o PHP não tiver um pacote de certificados configurado, o cURL falha com `unable to get local issuer certificate`. Basta apontar `curl.cainfo` no `php.ini` para um `cacert.pem`.

## Como o portal responde

Mapeado inspecionando o formulário e fazendo as requisições manualmente:

| Etapa | O que acontece |
| --- | --- |
| `GET consultaBa.asp` | Formulário em ISO-8859-1. Cria a sessão ASP (`ASPSESSIONID...`). |
| `POST result.asp` | Campos `sefp=1`, `estado=BA`, `CGC=<14 dígitos>` e `B1=CNPJ  ->`. O servidor escolhe o tipo de consulta pelo nome do botão (`B1`). |
| Cadastro encontrado | `200`, página em UTF-8 (declarado só na `<meta>`; o header não informa charset). |
| CNPJ sem cadastro | `302` para `consulta_vazia.htm`: "Nenhum registro encontrado !". |
| Dígito verificador errado | `302` para `cnpj_incorreto.htm`: "Cnpj incorreto - dígito verificador !". |
| Sem `B1`, CNPJ com máscara ou alfanumérico | `302` para `param_errado.htm`, que hoje responde `404`. |

O portal não exige captcha. Hoje ele também aceita o POST sem o cookie de sessão, mas o spider reproduz o fluxo do navegador (abre o formulário antes) para não depender disso.

## Arquitetura

```
bin/consultar.php           entrada (CNPJ) e saída (JSON ou stderr + código 1)
└── SefazBaSpider           fluxo da consulta e interpretação do status HTTP
    ├── HttpClient          contrato do transporte (tests/Fakes/FakeHttpClient nos testes)
    │   └── RetryingHttpClient → GuzzleHttpClient   novas tentativas | timeout, redirects, cookies
    ├── DecodificadorHtml   bytes da resposta → UTF-8
    └── ExtratorCadastroBa  HTML → CadastroContribuinte ou exceção (não conhece HTTP)
```

- **Transporte** (`src/Http`): Guzzle com `timeout` de 15 s, `connect_timeout` de 5 s, no máximo 3 redirecionamentos e `CookieJar` para a sessão. O redirecionamento não é estrito: o `302` depois do POST vira GET, como no navegador (as páginas `.htm` respondem `405` a POST). Respostas 4xx/5xx não viram exceção aqui; quem decide é o spider.
- **Novas tentativas**: no máximo 3 tentativas, com espera de 0,5 s e 1 s, só para falha de conexão, tempo limite e status 502, 503 e 504. A consulta é somente leitura, então repetir o POST é seguro. 4xx, 500 e HTML inesperado não são repetidos.
- **Extração** (`src/Extrator`): `DOMDocument` + `DOMXPath`, localizando cada valor pelo rótulo (`<b>Rótulo:</b>&nbsp;valor`), não pela posição das células.

## Mapeamento dos campos

| Propriedade | Rótulo na página | Transformação |
| --- | --- | --- |
| `inscricaoEstadual` | Inscrição Estadual | Primeiro número, só os dígitos: `000.478.696 NO (Inscrição Única)` → `000478696` |
| `cnpj` | CNPJ | Só os dígitos. `null` se a página não exibir (nunca vem da entrada). |
| `razaoSocial` | Razão Social | Espaços e `&nbsp;` normalizados, como em todos os campos |
| `nomeFantasia` | Nome Fantasia | |
| `situacaoCadastral` | Situação Cadastral Vigente | |
| `endereco` | Logradouro, Número, Complemento, Bairro/Distrito, Município, UF e CEP | Partes não vazias unidas por vírgula, lidas só da tabela do estabelecimento |
| `atividadeEconomica` | Atividade Econômica Principal | Código e descrição como publicados (o valor fica na linha seguinte ao rótulo) |
| `regimeApuracao` | Forma de pagamento | Como publicado, ex.: `C/CORRENTE FISCAL` (regime de conta corrente fiscal) |

Campo vazio ou ausente vira `null`. Identificadores são strings do início ao fim, para preservar zeros à esquerda.

## Erros

Todas as exceções da consulta estendem `ConsultaException`.

| Situação | Exceção |
| --- | --- |
| CNPJ com formato ou dígito verificador inválido (antes de consultar) | `InvalidArgumentException` (da `Consulta`) |
| Portal informa que não há cadastro | `CadastroNaoEncontrado` |
| Portal recusa o CNPJ | `EntradaInvalida` |
| Status 4xx, 500 ou redirecionamentos acima do limite | `FalhaHttp` (com o `status`) |
| Falha de conexão, tempo limite, 502, 503 ou 504 | `ServicoIndisponivel` |
| Página sem cadastro nem aviso conhecido, ou com o cadastro de outro CNPJ | `RespostaInesperada` |
| Mais de uma inscrição estadual na mesma página | `MultiplosCadastrosEncontrados` |

## Testes

```
tests/
├── Unit/
│   ├── ConsultaTest, CadastroContribuinteTest   entrada e objeto de valor (inclui os testes da base)
│   ├── DecodificadorHtmlTest                    charset do header, da <meta> e dos bytes
│   ├── ExtratorCadastroBaTest                   mapeamento, acentos, zeros, campos ausentes, avisos
│   ├── GuzzleHttpClientTest                     formulário, cookie de sessão, 302 após POST, limites (MockHandler)
│   ├── RetryingHttpClientTest                   quando repete e quando não repete
│   └── SefazBaSpiderTest                        requisições montadas e classificação das respostas
├── Integration/                                 portal real, grupo "integration"
└── Fixtures/sefaz-ba/                           HTML identificado no topo de cada arquivo
```

As fixtures `resultado-*.html` têm a estrutura copiada da página real, com todos os dados trocados por valores fictícios. `consulta-vazia.html` e `cnpj-incorreto.html` são cópias das páginas de aviso, que não têm dados de contribuinte. `pagina-manutencao.html` é sintética e está em Windows-1252.
