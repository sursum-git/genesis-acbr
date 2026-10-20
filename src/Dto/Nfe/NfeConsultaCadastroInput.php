<?php

namespace App\Dto\Nfe;

use App\Service\Nfe\NfeFiscalIdentifier;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class NfeConsultaCadastroInput
{
    public function __construct(
        public ?string $AcUF = null,
        public ?string $AnDocumento = null,
        public ?string $TipoDocumento = null,
    ) {
    }

    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->AnDocumento === null || $this->AnDocumento === '' || $this->TipoDocumento === null) {
            return;
        }

        if ($this->TipoDocumento === 'cpf_cnpj' && NfeFiscalIdentifier::normalizeDocument($this->AnDocumento) === '') {
            $context->buildViolation('AnDocumento deve conter CPF com 11 digitos ou CNPJ com 12 caracteres alfanumericos e 2 digitos verificadores.')
                ->atPath('AnDocumento')
                ->addViolation();
        }

        if ($this->TipoDocumento === 'inscricao_estadual' && mb_strlen($this->AnDocumento) > 20) {
            $context->buildViolation('AnDocumento deve ter no maximo 20 caracteres quando TipoDocumento for inscricao_estadual.')
                ->atPath('AnDocumento')
                ->addViolation();
        }
    }

    /**
     * @return array<string, string>
     */
    public function toLegacyPayload(): array
    {
        return [
            'AcUF' => (string) $this->AcUF,
            'AnDocumento' => $this->TipoDocumento === 'cpf_cnpj' ? NfeFiscalIdentifier::normalizeDocument((string) $this->AnDocumento) : (string) $this->AnDocumento,
            'AnIE' => $this->TipoDocumento === 'inscricao_estadual' ? '1' : '',
        ];
    }
}
