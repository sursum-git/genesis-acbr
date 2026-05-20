# Integracao Symfony/API Platform

Arquivos principais:

- `App\Service\AcbrCep\AcbrCepMtService`
- `App\ApiResource\AcbrCep\AcbrCepConfiguracaoResource`
- `App\ApiResource\AcbrCep\AcbrCepConsultaCepResource`
- `App\ApiResource\AcbrCep\AcbrCepConsultaLogradouroResource`
- `App\State\AcbrCep\AcbrCepConfiguracaoProvider`
- `App\State\AcbrCep\AcbrCepConfiguracaoProcessor`
- `App\State\AcbrCep\AcbrCepConsultaCepProcessor`
- `App\State\AcbrCep\AcbrCepConsultaLogradouroProcessor`
- `App\EventSubscriber\AcbrCepExceptionSubscriber`
- `App\Http\Exception\AcbrCepException`

Uso esperado no Symfony:

1. Use a pasta compartilhada `/dados_containers/www/src` como base do projeto Symfony/API Platform.
2. Garanta que `ConsultaCEP/MT/ACBrCEPApiMT.php` e as dependencias ACBr estejam disponiveis no servidor.
3. Ajuste o `require_once` de `App\Service\AcbrCep\AcbrCepMtService` apenas se a pasta `ConsultaCEP` for movida.
4. Deixe o autowiring padrao do Symfony resolver os providers e processors.
5. Importe `config/services/consulta_cep.yaml` no `services.yaml` do projeto.

Rotas esperadas:

- `GET /acbr-cep/configuracoes`
- `POST /acbr-cep/configuracoes`
- `POST /acbr-cep/consulta-cep`
- `POST /acbr-cep/consulta-logradouro`

Import do services:

```yaml
# config/services.yaml
imports:
  - { resource: services/consulta_cep.yaml }
```
