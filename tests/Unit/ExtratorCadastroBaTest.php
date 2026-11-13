<?php

declare(strict_types=1);

use ProvaSpider\Exception\CadastroNaoEncontrado;
use ProvaSpider\Exception\EntradaInvalida;
use ProvaSpider\Exception\MultiplosCadastrosEncontrados;
use ProvaSpider\Exception\RespostaInesperada;
use ProvaSpider\Extrator\ExtratorCadastroBa;
use ProvaSpider\Html\DecodificadorHtml;
use ProvaSpider\ValueObject\CadastroContribuinte;

function extrairFixture(string $nome): CadastroContribuinte
{
    return (new ExtratorCadastroBa())->extrair(DecodificadorHtml::paraUtf8(fixture("sefaz-ba/{$nome}")));
}

it('mapeia a página de resultado para o cadastro', function () {
    expect(extrairFixture('resultado-cadastro.html'))->toEqual(new CadastroContribuinte(
        inscricaoEstadual: '001234567',
        cnpj: '01234567000195',
        razaoSocial: 'AÇAÍ DO JOÃO COMÉRCIO DE ALIMENTOS LTDA',
        nomeFantasia: 'AÇAÍ DO JOÃO',
        situacaoCadastral: 'ATIVO',
        endereco: 'RUA DA CONCEIÇÃO, 0123, SALA 2, SÃO CAETANO, SALVADOR, BA, 40301-110',
        atividadeEconomica: '4721104 - Comércio varejista de doces, balas, bombons e semelhantes',
        regimeApuracao: 'C/CORRENTE FISCAL',
    ));
});

it('tira a máscara e o tipo da inscrição e preserva zeros à esquerda', function () {
    // Na página: "01.234.567/0001-95" e "001.234.567 NO (Inscrição Única)".
    $cadastro = extrairFixture('resultado-cadastro.html');

    expect($cadastro->cnpj)->toBe('01234567000195')
        ->and($cadastro->inscricaoEstadual)->toBe('001234567');
});

it('monta o endereço só com a seção do estabelecimento', function () {
    // Correspondência e contador repetem Número, Bairro, CEP e Município com outros valores.
    $endereco = extrairFixture('resultado-cadastro.html')->endereco;

    expect($endereco)->not->toContain('SETE DE SETEMBRO')
        ->and($endereco)->not->toContain('FEIRA DE SANTANA')
        ->and($endereco)->not->toContain('LAURO DE FREITAS');
});

it('representa como null os campos vazios ou ausentes', function () {
    $cadastro = extrairFixture('resultado-campos-ausentes.html');

    expect($cadastro->cnpj)->toBeNull()
        ->and($cadastro->nomeFantasia)->toBeNull()
        ->and($cadastro->atividadeEconomica)->toBeNull()
        ->and($cadastro->regimeApuracao)->toBeNull()
        ->and($cadastro->endereco)->toBe('RUA DA CONCEIÇÃO, 0123, SÃO CAETANO, SALVADOR, BA, 40301-110')
        ->and($cadastro->inscricaoEstadual)->toBe('001234567')
        ->and($cadastro->razaoSocial)->toBe('JOSÉ DA SILVA FICTÍCIO')
        ->and($cadastro->situacaoCadastral)->toBe('INAPTO');
});

it('aceita a atividade na mesma célula do rótulo', function () {
    $cadastro = (new ExtratorCadastroBa())->extrair(
        '<table><tr><td><b>Razão Social:</b>&nbsp;EMPRESA FICTÍCIA</td></tr>'
        . '<tr><td><b>Atividade Econômica Principal:</b> 4721104 - Comércio varejista</td></tr></table>'
    );

    expect($cadastro->atividadeEconomica)->toBe('4721104 - Comércio varejista');
});

it('recusa a página com mais de uma inscrição estadual', function () {
    try {
        extrairFixture('resultado-multiplas-ie.html');
        $this->fail('Deveria ter lançado MultiplosCadastrosEncontrados.');
    } catch (MultiplosCadastrosEncontrados $e) {
        expect($e->inscricoes)->toBe(['001234567', '009876543']);
    }
});

it('reconhece as páginas de aviso do portal', function (string $fixture, string $excecao) {
    expect(fn () => extrairFixture($fixture))->toThrow($excecao);
})->with([
    'CNPJ sem cadastro' => ['consulta-vazia.html', CadastroNaoEncontrado::class],
    'CNPJ recusado pelo portal' => ['cnpj-incorreto.html', EntradaInvalida::class],
]);

it('lança RespostaInesperada sem cadastro nem aviso conhecido', function (string $html) {
    (new ExtratorCadastroBa())->extrair($html);
})->throws(RespostaInesperada::class)->with([
    'página de manutenção' => fn () => DecodificadorHtml::paraUtf8(fixture('sefaz-ba/pagina-manutencao.html')),
    'corpo vazio' => [''],
    'só espaços' => ["  \r\n "],
    'rótulos sem valor' => ['<table><tr><td><b>Razão Social:</b>&nbsp;</td></tr></table>'],
]);
