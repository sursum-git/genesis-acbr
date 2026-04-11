<?php

namespace App\Dto\Nfe;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

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
            description: 'CPF ou CNPJ do contribuinte.',
            example: '12345678000123'
        )]
        #[Assert\NotBlank(message: 'AnDocumento e obrigatorio.')]
        #[Assert\Regex(
            pattern: '/^\d{11}(\d{3})?$|^\d{14}$/',
            message: 'AnDocumento deve conter 11 ou 14 digitos numericos.'
        )]
        public ?string $AnDocumento = null,
        #[ApiProperty(
            description: 'Inscricao estadual do contribuinte.',
            example: '123456789'
        )]
        #[Assert\NotBlank(message: 'AnIE e obrigatorio.')]
        #[Assert\Length(
            max: 20,
            maxMessage: 'AnIE deve ter no maximo {{ limit }} caracteres.'
        )]
        public ?string $AnIE = null,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toLegacyPayload(): array
    {
        return [
            'AcUF' => (string) $this->AcUF,
            'AnDocumento' => (string) $this->AnDocumento,
            'AnIE' => (string) $this->AnIE,
        ];
    }
}
