<?php

namespace App\Dto\Nfe;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class NfeConsultaCadastroInput
{
    public function __construct(
        public ?string $AcUF = null,
        public ?string $AnDocumento = null,
        public ?string $TipoDocumento = null,
    ) {
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->AnDocumento === null || $this->AnDocumento === '' || $this->TipoDocumento === null) {
            return;
        }

        if ($this->TipoDocumento === 'cpf_cnpj' && !preg_match('/^\d{11}$|^\d{14}$/', $this->AnDocumento)) {
            $context->buildViolation('AnDocumento deve conter 11 ou 14 digitos numericos quando TipoDocumento for cpf_cnpj.')
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
            'AnDocumento' => (string) $this->AnDocumento,
            'AnIE' => $this->TipoDocumento === 'inscricao_estadual' ? '1' : '',
        ];
    }
}
