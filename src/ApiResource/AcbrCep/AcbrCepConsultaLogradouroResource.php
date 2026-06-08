<?php

namespace App\ApiResource\AcbrCep;

final class AcbrCepConsultaLogradouroResource
{
    public function __construct(
        public ?string $cidade = null,
        public ?string $tipo = '',
        public ?string $logradouro = '',
        public ?string $uf = '',
        public ?string $bairro = '',
        public ?string $webservice = '0',
        public ?int $retorno = null,
        public ?string $mensagem = null,
        public ?AcbrCepEnderecoResource $dados = null,
        public ?array $resultado = null,
        public ?string $request_id = null,
        public ?string $status = null,
        public ?string $endpoint = null,
        public ?string $received_at = null,
    ) {
        $this->request_id ??= $this->extractString($resultado, 'request_id');
        $this->status ??= $this->extractString($resultado, 'status');
        $this->endpoint ??= $this->extractString($resultado, 'endpoint');
        $this->received_at ??= $this->extractString($resultado, 'received_at');
    }

    private function extractString(?array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
