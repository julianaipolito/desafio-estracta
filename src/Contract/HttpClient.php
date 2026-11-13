<?php

declare(strict_types=1);

namespace ProvaSpider\Contract;

use ProvaSpider\Exception\FalhaHttp;
use ProvaSpider\Exception\ServicoIndisponivel;
use ProvaSpider\Http\HttpResponse;

/**
 * Transporte usado pelo spider.
 *
 * Mantém os cookies entre as chamadas (sessão) e devolve respostas de qualquer
 * status: decidir o que é erro de negócio fica com quem chama.
 */
interface HttpClient
{
    /**
     * @throws ServicoIndisponivel Falha de conexão ou tempo limite esgotado.
     * @throws FalhaHttp Limite de redirecionamentos excedido.
     */
    public function get(string $url): HttpResponse;

    /**
     * Envia os campos como formulário (application/x-www-form-urlencoded).
     *
     * @param array<string, string> $campos
     *
     * @throws ServicoIndisponivel Falha de conexão ou tempo limite esgotado.
     * @throws FalhaHttp Limite de redirecionamentos excedido.
     */
    public function postForm(string $url, array $campos): HttpResponse;
}
