# Desafio estracta

## Objetivo

Implementar um spider em PHP que realize a consulta pública ao cadastro de contribuintes da Bahia **exclusivamente por CNPJ**, extraia os dados da resposta e os retorne em um **objeto de valor imutável** (`CadastroContribuinte`).

Página inicial: <https://portal.sefaz.ba.gov.br/scripts/cadastro/cadastroBa/consultaBa.asp>

Este repositório é o projeto-base da avaliação. A consulta HTTP e a extração estão pendentes em [SefazBaSpider.php](src/Spider/SefazBaSpider.php); implementá-las é a tarefa do candidato.

## Preparação e execução

Requisitos: PHP 8.1 ou superior, Composer 2 e extensões exigidas pelas dependências, incluindo DOM, XML e mbstring. O projeto utiliza Pest 2, compatível com PHP 8.1. Instale também as extensões requeridas pela biblioteca HTTP escolhida.

Para instalar as dependências e executar os testes iniciais:

```bash
composer install
composer test
```

Após implementar o spider, execute a consulta:

```bash
php bin/consultar.php CNPJ
```

Substitua `CNPJ` pelo número a consultar, com ou sem máscara. Se houver espaços na entrada, use aspas. O comando recebe somente o CNPJ, sem argumento para selecionar o tipo de consulta.

Em caso de sucesso, o comando imprime o resultado em JSON. Em caso de erro, escreve uma mensagem na saída de erro (`stderr`) e encerra com código 1. Antes da implementação, uma entrada com formato válido resulta no aviso de que o spider ainda não foi implementado.

## Escopo da implementação

1. Inspecionar o formulário e identificar método, parâmetros e eventuais etapas intermediárias da consulta por CNPJ.
2. Realizar a consulta exclusivamente por CNPJ.
3. Extrair os dados do cadastro retornado, tratar a codificação da página e normalizar espaços e máscaras sem perder zeros à esquerda.
4. Construir e retornar `CadastroContribuinte` pelo contrato `Spider::consultar()`. O retorno público do spider deve ser o objeto, e a conversão para JSON deve acontecer na camada de saída.
5. Distinguir ausência de cadastro, entrada inválida, falha HTTP, indisponibilidade e resposta inesperada. Usar exceções claras; não converter falhas em um cadastro vazio ou em dados simulados.
6. Definir tempo limite para as requisições e limite de redirecionamentos. Caso implemente novas tentativas, limitar sua quantidade e aplicá-las apenas a falhas recuperáveis.
7. Escrever os testes em **Pest**, com respostas locais e transporte substituível para que a suíte padrão rode sem acessar a SEFAZ.

O candidato pode adicionar dependências, classes e exceções, separar a comunicação HTTP da extração e ajustar o modelo conforme os dados reais. Deve preservar a finalidade do contrato e tornar as alterações compreensíveis pelo código e pelos testes. Não é necessário usar um framework, banco de dados, interface web ou execução em lote.

## Contrato e dados esperados

```php
public function consultar(Consulta $consulta): CadastroContribuinte;
```

`Consulta` contém o CNPJ na propriedade `cnpj`. A validação fornecida é apenas estrutural: remove pontuação usual, verifica caracteres e o tamanho de 14 dígitos. Não há validação de dígitos verificadores no projeto-base.

O objeto de valor fornecido é `final`, tem propriedades `readonly` e implementa `JsonSerializable`. O esquema inicial é:

| Propriedade | Conteúdo proposto |
| --- | --- |
| `inscricaoEstadual` | Inscrição estadual |
| `cnpj` | CNPJ do contribuinte |
| `razaoSocial` | Razão social ou nome do contribuinte |
| `nomeFantasia` | Nome fantasia |
| `situacaoCadastral` | Situação do cadastro |
| `endereco` | Endereço publicado |
| `atividadeEconomica` | Atividade econômica publicada |
| `regimeApuracao` | Regime de apuração, quando disponível |

O esquema é uma referência inicial e deve ser validado com o cadastro retornado pelo portal. O mapeamento dos campos e suas transformações deve estar implementado no código e coberto pelos testes. Campos ausentes devem ser representados por `null`. Identificadores devem permanecer como strings para preservar zeros à esquerda. O CNPJ informado na entrada não deve ser apresentado como dado extraído se não aparecer no resultado.

A inscrição estadual é um dado a extrair do resultado, quando disponível; a entrada da consulta é sempre o CNPJ.

O contrato inicial representa um cadastro por consulta.

## Testes e aceite

Todos os testes devem ser escritos em **Pest** e executados com `composer test`. Os testes iniciais verificam somente a validação estrutural da entrada e a serialização do objeto de valor. Cabe ao candidato acrescentar os testes da consulta e da extração.

Os testes devem cobrir:

- Montagem da requisição por CNPJ e manutenção de sessão quando aplicável.
- Extração de cadastro a partir de HTML local representativo, incluindo acentos e campos ausentes.
- Mapeamento e retorno do objeto de valor, com preservação de zeros nos identificadores.
- Resposta sem cadastro, falha de transporte, status HTTP de erro e HTML inesperado.

As respostas HTML utilizadas nos testes (fixtures) devem ser sintéticas ou anonimizadas e identificadas como tal. A suíte padrão deve funcionar sem acesso à internet. Testes de integração com o portal, se incluídos, devem ter execução opcional e separada. As fixtures servem para testar a extração; o spider deve realizar a consulta real quando executado pelo comando.

## Entrega

O projeto deve se chamar **Desafio estracta** e ser salvo em um **repositório Git público do próprio candidato**, contendo o código, o arquivo `composer.lock` atualizado, os testes em Pest e as fixtures. O link do repositório do projeto deve ser enviado por e-mail para **[mario.aquino@e-stracta.com](mailto:mario.aquino@e-stracta.com)** para avaliação. Deve ser possível clonar o repositório, instalar as dependências com `composer install`, executar os testes com `composer test` e consultar por CNPJ com o comando indicado neste documento.

O histórico de commits e os pull requests (PRs) também serão avaliados. Organize o desenvolvimento em commits que permitam acompanhar a evolução da solução e utilize PRs para apresentar e revisar as alterações.

A aplicação e os testes entregues serão avaliados. Não é necessário preencher formulário ou relatório de entrega.

Todos os testes entregues serão analisados e receberão uma devolutiva no resultado da entrevista.

O candidato deve escrever um texto livre explicando como desenvolveu a aplicação, incluindo sua abordagem, decisões técnicas, dificuldades e aprendizados. Serão consideradas a clareza da explicação, a fundamentação das decisões e a coerência com a implementação entregue.

