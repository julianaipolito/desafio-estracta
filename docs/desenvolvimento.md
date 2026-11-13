# Como desenvolvi

## Abordagem

Comecei pelo projeto-base: o contrato `Spider::consultar()`, a `Consulta`, o `CadastroContribuinte` e os testes iniciais. O enunciado deixava claro que o spider em si é pequeno e que o peso estava em separar responsabilidades, ser honesta com os dados e classificar bem as falhas. Então dividi o trabalho em três partes que não se conhecem: o transporte HTTP, a extração do HTML e o spider, que só orquestra as duas. O `bin/consultar.php` continua sendo a única camada que sabe de JSON.

Antes de escrever o extrator definitivo, fui ao portal. Li o HTML do formulário e repeti as requisições manualmente, variando um campo por vez, para entender o que o servidor realmente exige. Foi essa etapa que definiu quase todas as decisões abaixo.

## O que o portal mostrou

- O formulário faz POST para `result.asp` com dois campos ocultos (`sefp=1` e `estado=BA`), o campo `CGC` e um botão por tipo de consulta. O servidor decide o tipo pelo **nome** do botão: sem `B1`, ele redireciona para `param_errado.htm`. O mesmo acontece com o CNPJ mascarado ou alfanumérico, então o spider envia só os 14 dígitos.
- A abertura do formulário cria a sessão ASP (`ASPSESSIONID`). Testei o POST sem o cookie e hoje funciona igual, mas mantive o fluxo do navegador. Custa uma requisição leve e evita quebrar se o portal passar a exigir a sessão.
- A ausência de cadastro não volta como uma página 200 com mensagem: é um `302` para `consulta_vazia.htm` ("Nenhum registro encontrado !"). O CNPJ com dígito verificador errado vai para `cnpj_incorreto.htm`.
- As codificações são misturadas. O formulário e as páginas de aviso declaram ISO-8859-1, a página de resultado declara e usa UTF-8, e o header `Content-Type` não informa charset em nenhuma delas.
- Na página de resultado, cada dado está em `<td><b>Rótulo:</b>&nbsp;valor</td>`, dentro de tabelas aninhadas e com marcação irregular (`<tr>` sem fechar, ids repetidos). Rótulos como Número, Bairro, CEP e Município aparecem três vezes: no endereço do estabelecimento, no de correspondência e no do contador. A atividade principal fica na linha **seguinte** ao rótulo.
- Não há captcha.

## Decisões técnicas

**Transporte.** Criei uma interface pequena (`HttpClient`, com `get` e `postForm`) em vez de expor as opções do Guzzle para o spider. Ela diz exatamente o que a consulta precisa e permite um fake simples nos testes. Também considerei usar o PSR-18 direto, mas o `sendRequest` do Guzzle desliga os redirecionamentos, e eu precisava deles. A implementação com Guzzle define tempo limite (15 s; 5 s para conectar), no máximo 3 redirecionamentos e um `CookieJar` para a sessão.

Um detalhe importante: o redirecionamento **não** é estrito. Com `strict => true`, o Guzzle repetiria o POST no destino do `302`, e as páginas `.htm` respondem `405` a POST. Ou seja, um CNPJ sem cadastro viraria "falha HTTP". O navegador transforma o `302` em GET, e o cliente faz o mesmo.

**Novas tentativas.** Ficaram num decorador (`RetryingHttpClient`) para não misturar com o Guzzle: são no máximo 3 tentativas, com espera exponencial, e só em falha de conexão, tempo limite e 502/503/504. Repetir o POST é seguro porque a consulta é somente leitura. 4xx, 500 e HTML inesperado não mudam numa nova tentativa, então não são repetidos.

**Classificação das respostas.** O transporte só lança exceção para falhas de transporte. O spider interpreta o status (502/503/504 viram `ServicoIndisponivel`, os demais erros viram `FalhaHttp` com o código) e o extrator interpreta o conteúdo. Se reconhece o cadastro, extrai. Se reconhece um aviso do portal, lança a exceção correspondente. Se não reconhece nada, lança `RespostaInesperada`. Nunca devolvo um cadastro vazio.

**Codificação.** O `DecodificadorHtml` usa o charset do header, depois o da `<meta>` e, sem nenhum dos dois, os próprios bytes (UTF-8 válido ou Windows-1252). Trato ISO-8859-1 como Windows-1252, como os navegadores. No extrator, converto tudo acima de ASCII em entidades numéricas antes do `loadHTML`, para o libxml não depender da `<meta>` para ler os acentos.

**Extração por rótulo.** Localizo cada valor pelo texto do rótulo, comparado sem acento e sem diferenciar maiúsculas (a própria página escreve "Referência" e "Referencia"), e não pela posição das células. Duas regras evitam misturar seções:

- uso sempre a **primeira** ocorrência do rótulo, e se ela estiver vazia o campo é `null`, em vez de procurar o próximo "Complemento:" e acabar pegando o da correspondência;
- o endereço é lido só da tabela que contém "Logradouro", que existe apenas na seção do estabelecimento.

**Mapeamento.** O esquema sugerido tinha `regimeApuracao`, mas a página não tem esse rótulo. Ela publica "Condição" (ex.: NORMAL) e "Forma de pagamento" (ex.: C/CORRENTE FISCAL). Mapeei `regimeApuracao` para a forma de pagamento, porque "conta corrente fiscal" é justamente um regime de apuração do ICMS na legislação baiana. Já "Condição" descreve a classificação do contribuinte. Mantive o texto como o portal publica. A inscrição estadual vem como `000.478.696 NO (Inscrição Única)`, então extraio só o primeiro número e removo a máscara, mantendo string para não perder zeros à esquerda.

**Honestidade com os dados.** O CNPJ do objeto vem da página. Se ela não o exibir, é `null`, nunca a entrada. Além disso, se a página trouxer um CNPJ diferente do consultado, o spider recusa a resposta, para não devolver o cadastro errado como se fosse o certo.

**Um cadastro por consulta.** Não encontrei CNPJ com mais de uma inscrição no portal, mas o contrato devolve um único cadastro. Se a página trouxer duas inscrições distintas, lanço `MultiplosCadastrosEncontrados` em vez de escolher uma. A fixture desse caso está identificada como hipotética.

**Dígitos verificadores.** A base só validava a estrutura do CNPJ. Acrescentei a validação do DV na `Consulta`, porque o portal recusa o DV errado de qualquer forma e assim evitamos uma requisição inútil. Mantive a `InvalidArgumentException` da base e os testes originais sem alteração. Se mesmo assim o portal recusar o CNPJ, o extrator reconhece a página e lança `EntradaInvalida`.

**Dependências.** Fixei `config.platform.php` em 8.1 no `composer.json` para que o `composer.lock` continue instalável no PHP mínimo do enunciado. O Pest 2 trava o PHPUnit em 10.5.36, que tem um aviso de segurança no tratamento de cobertura de arquivos PHPT. Não afeta este projeto, e atualizar exigiria o Pest 3, que pede PHP 8.2.

## Testes

A suíte padrão roda sem internet. O spider é testado com um transporte em memória que registra as requisições, o que permite conferir URL, método e cada campo enviado. O cliente Guzzle é testado com o `MockHandler` usando a configuração real, para provar o que importa nele: o cookie de sessão reenviado, o `302` após o POST seguido com GET, o limite de redirecionamentos e o tempo limite.

As fixtures de resultado foram geradas a partir da página real, com todos os dados (contribuinte, endereços, contador, telefones e até comentários com nomes) trocados por valores fictícios. Deixei de propósito os endereços de correspondência e do contador diferentes do endereço do estabelecimento, para o teste provar que o extrator não mistura as seções. Os dois testes de integração, um com cadastro e outro sem, ficam no grupo `integration` e rodam com `composer test:integration`.

## Dificuldades

A maior foi não confiar em suposições sobre o HTML. A primeira versão do extrator foi escrita antes de eu inspecionar as respostas reais. Os testes passavam, mas contra fixtures que eu mesma tinha imaginado. Quando rodei o comando no portal, o endereço vinha da seção de correspondência e incompleto, a atividade e o regime vinham `null`, e um CNPJ sem cadastro terminava em HTTP 405 por causa do redirecionamento estrito. Reescrevi o extrator a partir da página real e refiz as fixtures com base nela.

Também levei um tempo para perceber que o portal usa codificações diferentes em cada página, e que o charset só aparece na `<meta>`.

## Aprendizados

- Fixture boa é fixture derivada da resposta real. Teste verde sobre HTML inventado dá uma falsa segurança.
- Em portais legados, a resposta "normal" muitas vezes é um redirecionamento. Vale olhar o status e o `Location` antes de olhar o corpo.
- Valores padrão de biblioteca (como o redirecionamento estrito) podem mudar o comportamento do site e precisam ser escolhidos conscientemente.

## O que ficou de fora

- **CNPJ alfanumérico**: a Receita começou a emitir em julho de 2026, mas o formulário do portal o recusa (`param_errado.htm`). A validação continua numérica. Como os identificadores já são strings, o modelo está pronto para quando o portal aceitar.
- Atividades secundárias, "Condição", natureza jurídica e datas não estão no objeto, porque o contrato tem um campo só para atividade e nenhum para os demais. Eles podem entrar se o modelo for ampliado.
- Se a requisição sair com parâmetros que o portal não entende, ele redireciona para uma página que hoje dá 404. O erro aparece como `FalhaHttp` 404, sem uma mensagem mais específica.
