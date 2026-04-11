<?php

namespace App\Dto\Nfe;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class NfeConsultaCadastroInput
{
    public function __construct(
        #[ApiProperty(
            description: 'UF para consulta cadastral.',
            example: 'MT'
        )]
        #[Assert\NotBlank(message: 'AcUF e obrigatorio.')]
        #[Assert\Length(
            min: 2,
            max: 2,
            exactMessage: 'AcUF deve ter exatamente {{ limit }} caracteres.'
        )]
        #[Assert\Regex(
            pattern: '/^[A-Z]{2}$/',
            message: 'AcUF deve conter exatamente duas letras maiusculas.'
        )]
        public ?string $AcUF = null,
        #[ApiProperty(
            description: 'Documento informado para a consulta. Pode ser CPF/CNPJ ou inscricao estadual, conforme o tipo informado.',
            example: '12345678000123'
        )]
        #[Assert\NotBlank(message: 'AnDocumento e obrigatorio.')]
        public ?string $AnDocumento = null,
        #[ApiProperty(
            description: 'Tipo do documento informado em AnDocumento.',
            example: 'cpf_cnpj'
        )]
        #[Assert\NotBlank(message: 'TipoDocumento e obrigatorio.')]
        #[Assert\Choice(
            choices: ['cpf_cnpj', 'inscricao_estadual'],
            message: 'TipoDocumento deve ser cpf_cnpj ou inscricao_estadual.'
        )]
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
