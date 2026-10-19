# Manual operacional do projeto ACBr API/Portal

Atualizado em: 2026-08-20

## 1. Visão geral

Este projeto é uma aplicação PHP/Symfony que expõe APIs e telas administrativas para operar módulos ACBr, principalmente:

- CEP;
- NFe;
- NFSe;
- catálogos operacionais;
- auditoria de requisições;
- filas/workers;
- webhooks;
- monitores de NFe;
- controle de login, usuários, empresas e assinantes.

A arquitetura atual é híbrida:

- a camada moderna usa Symfony 7, API Platform, Twig e Doctrine DBAL;
- os módulos fiscais continuam usando scripts legados ACBr em pastas como `NFe/`, `NFSe/` e `ConsultaCEP/`;
- a aplicação moderna organiza autenticação, documentação, auditoria, filas, monitores e telas administrativas.

## 2. Ambiente operacional

Raiz do projeto:

```text
/dados_containers/www
```

Servidor HTTP padrão do ambiente:

```text
http://157.173.110.195:8089
```

Entrada principal:

```text
/index.php
```

URLs principais:

- Portal: `/index.php/`
- Documentação OpenAPI: `/index.php/docs`
- APIs por módulo:
  - `/index.php/docs/todos`
  - `/index.php/docs/cep`
  - `/index.php/docs/nfe`
  - `/index.php/docs/nfse`
- Login: `/index.php/login`
- Usuários: `/index.php/usuarios`
- Assinantes: `/index.php/assinantes`
- Consulta de requisições: `/index.php/consulta-requisicoes`
- Auditoria analítica: `/index.php/auditoria-requisicoes/visao-analitica`
- Catálogo de programas: `/index.php/catalogo-programas`
- Catálogo de testes: `/index.php/catalogo-testes`
- Monitor NFe saída: `/index.php/monitor-saida-nfe`
- Monitor NFe entrada: `/index.php/monitor-entrada-nfe`
- Webhooks: `/index.php/webhooks`
- Monitor de workers: `/index.php/monitor-workers`

## 3. Estrutura principal de pastas

Camada Symfony/API:

- `src/ApiResource/`: recursos expostos pelo API Platform.
- `src/Dto/`: DTOs de entrada/saída.
- `src/State/`: providers/processors do API Platform.
- `src/Service/`: serviços de integração, autenticação, auditoria, extração e legado.
- `src/Repository/`: acesso a bancos via Doctrine DBAL.
- `src/Controller/`: telas administrativas e endpoints HTML/JSON fora do API Platform.
- `src/EventSubscriber/`: autenticação, auditoria, tratamento de exceções e políticas de sessão.
- `src/Command/`: workers e comandos operacionais.
- `templates/`: telas Twig.
- `catalog-assets/`: CSS/JS do portal, catálogos e monitores.
- `config/api_platform/resources/`: metadados XML das APIs.
- `config/validator/`: validações Symfony.
- `config/serializer/`: grupos de serialização.

Legado ACBr:

- `ConsultaCEP/`
- `NFe/`
- `NFSe/`
- `Boleto/`
- `CTe/`
- `MDFe/`
- `GTIN/`
- `ConsultaCNPJ/`
- demais pastas ACBr mantidas no deploy.

Dados auxiliares:

- `var/db/program_catalog.sqlite`: catálogo local de programas.
- `var/db/audit.sqlite`: banco auxiliar local de auditoria quando aplicável.
- PostgreSQL externo `dfe`: base operacional principal de auditoria, autenticação, assinantes, extrações e filas.

## 4. Modelo de autenticação e permissões

O login usa:

```text
usuario + senha
```

Papéis:

- `super_admin`: administrador geral, usado pelos desenvolvedores/operadores internos.
- `company_admin`: administrador do assinante.
- `common`: usuário comum.

Regras:

- `super_admin` acessa tudo.
- `company_admin` acessa usuários e monitores, mas não acessa telas internas de operação nem assinantes.
- `common` acessa os monitores permitidos pelo vínculo com empresas.
- usuários não autenticados são redirecionados para `/index.php/login`.
- a tela de login é independente da área administrativa, não abre dentro do iframe/conteúdo central.
- a sessão expira após 1 hora de inatividade.
- existe botão `Sair` no topo da aplicação.
- o topo mostra o usuário logado e o tipo do usuário.

## 5. Tabelas de autenticação, empresas e assinantes

Tabelas principais:

- `t00002`: assinantes/clientes da API.
- `t00005`: usuários/logins.
- `t00006`: empresas.
- `t00007`: vínculo usuário × empresa.
- `t00008`: vínculo empresa × assinante.
- `t00009`: tokens/sessões.

Relação operacional:

```text
t00005 usuário
  -> t00007 usuário_empresa
  -> t00006 empresa
  -> t00008 empresa_assinante
  -> t00002 assinante
```

Consequências:

- um usuário pode ter várias empresas;
- uma empresa pode estar ligada a vários assinantes;
- um assinante pode estar ligado a várias empresas;
- um usuário pode, indiretamente, pertencer a vários assinantes.

## 6. Administração de usuários

Tela:

```text
/index.php/usuarios
```

Permissões:

- `super_admin` cria e edita `super_admin`, `company_admin` e `common`.
- `company_admin` cria e edita apenas `company_admin` e `common`.
- `company_admin` não vê a opção `Admin geral`.
- `company_admin` não consegue editar um usuário `super_admin`.

Criação de usuário:

- usuário;
- nome;
- senha inicial;
- tipo;
- empresas vinculadas.

Edição de usuário:

- nome;
- tipo;
- ativo/inativo;
- nova senha opcional;
- empresas vinculadas.

Observações:

- se a nova senha ficar vazia, a senha atual permanece;
- ao trocar senha, a versão do token do usuário é incrementada;
- empresas são buscadas por nome ou CNPJ, para suportar listas grandes.

Comando para criar o primeiro admin geral:

```bash
php bin/console app:auth:bootstrap-admin <usuario> <senha>
```

Executar o comando a partir da raiz correta do projeto.

## 7. Tokens da API

O token fixo do `.env` não deve mais ser usado como referência única para todos os usuários.

Regra atual:

- o token usado deve ser o token do assinante permitido para o usuário atual;
- `super_admin` pode obter token de qualquer assinante ativo;
- usuários não `super_admin` só obtêm token de assinantes ligados às suas empresas.

Endpoints de autenticação API:

- `POST /index.php/api/auth/token`
- `POST /index.php/api/auth/refresh`

## 8. Menus e áreas administrativas

Áreas restritas ao `super_admin`:

- demos legadas administrativas;
- catálogo de programas;
- catálogo de testes;
- assinantes;
- configuração de execução;
- capacidade de workers;
- webhooks;
- monitor de workers;
- consulta de requisições;
- auditoria analítica.

Área permitida para `super_admin` e `company_admin`:

- usuários.

Áreas permitidas para usuários autenticados conforme vínculo:

- monitor NFe saída;
- monitor NFe entrada.

## 9. APIs expostas

O API Platform centraliza a documentação e os contratos públicos.

Módulos principais:

### CEP

Base:

```text
/acbr-cep
```

Operações principais:

- `GET /acbr-cep/configuracoes`
- `POST /acbr-cep/configuracoes`
- `POST /acbr-cep/consulta-cep`
- `POST /acbr-cep/consulta-logradouro`

Características:

- usa DTOs próprios;
- usa providers/processors específicos;
- integra diretamente com `ConsultaCEP/MT/ACBrCEPApiMT.php`.

### NFe

Base:

```text
/nfe
```

Grupos:

- consultas;
- distribuição DFe;
- envio;
- eventos;
- ferramentas;
- inutilização.

Exemplos:

- `GET /nfe/consultas/status-servico`
- `GET /nfe/consultas/consultar-com-chave`
- `POST /nfe/consultas/consultar-com-chave-xml`
- `GET /nfe/consultas/consulta-cadastro`
- `GET /nfe/distribuicao-dfe/por-chave`
- `GET /nfe/distribuicao-dfe/por-nsu`
- `GET /nfe/distribuicao-dfe/por-ult-nsu`
- `POST /nfe/envio/enviar-sincrono-xml`
- `POST /nfe/envio/enviar-assincrono-xml`
- `POST /nfe/eventos/cancelar`
- `GET /nfe/inutilizacao/inutilizar`

Características:

- a maioria das operações delega para `NFe/MT/ACBrNFeServicosMT.php`;
- operações modernas usam DTOs e metadados XML;
- `consulta-cadastro` já tem contrato mais forte com DTO, provider próprio, API Platform XML e validação XML.

### NFSe

Base:

```text
/nfse
```

Grupos:

- padrão nacional;
- demais provedores;
- consultas;
- envio;
- cancelamento;
- serviços prestados;
- serviços tomados;
- ferramentas.

Exemplos:

- `POST /nfse/padrao-nacional/enviar-evento`
- `GET /nfse/padrao-nacional/consultar-nfse-por-chave`
- `GET /nfse/demais-provedores/consultas/consultar-situacao`
- `POST /nfse/demais-provedores/envio/emitir-nota`
- `POST /nfse/demais-provedores/cancelamento/cancelar`
- `GET /nfse/ferramentas/openssl-info`

Características:

- delega para `NFSe/MT/ACBrNFSeServicosMT.php`;
- usa adaptadores para corpo bruto XML/INI quando necessário.

## 10. Integração com legado ACBr

Fluxo típico NFe/NFSe:

```text
API Platform
  -> ApiResource/DTO
  -> Provider/Processor
  -> AcbrLegacyScriptExecutor
  -> script PHP legado ACBr
  -> resposta normalizada
```

As operações declaram metadados como:

- `acbr_script`;
- `acbr_method`;
- `acbr_payload`;
- `acbr_query_params`;
- parâmetro de arquivo bruto quando aplicável.

Diretriz:

- novas operações públicas devem preferir DTOs dedicados;
- não expor diretamente detalhes genéricos do legado no contrato público;
- manter metadados de API em XML quando a operação seguir o padrão já adotado.

## 11. Auditoria de requisições

A auditoria registra requisições, respostas, status, payloads, tempos, assinante, usuário e status de extração.

Tabelas principais:

- `t99001`: requisição principal.
- `t99002`: eventos/logs da requisição.
- `t99004`: tentativas/processamentos.
- `t99006`: entregas de webhook.
- `t99007`: envelope de distribuição DFe.
- `t99008`: documentos extraídos de distribuição/envio.
- `t99019`: dados gerais da NFe extraída.

Telas:

- `/index.php/consulta-requisicoes`
- `/index.php/auditoria-requisicoes/visao-analitica`

Para usuários não `super_admin`, os monitores aplicam escopo por assinante/empresa; as telas de auditoria administrativa são restritas ao `super_admin`.

## 12. Workers e processamento assíncrono

Comandos principais:

```bash
php bin/console app:api-request-worker
php bin/console app:api-extraction-worker
php bin/console app:webhook-delivery-worker
```

Funções:

- `app:api-request-worker`: processa requisições assíncronas pendentes.
- `app:api-extraction-worker`: extrai dados de NFe, NSU e distribuição DFe.
- `app:webhook-delivery-worker`: processa entregas pendentes de webhooks.

Telas relacionadas:

- `/index.php/configuracao-execucao`
- `/index.php/capacidade-workers`
- `/index.php/monitor-workers`

## 13. Webhooks

Tela:

```text
/index.php/webhooks
```

Funções:

- cadastrar endpoints de webhook;
- vincular endpoints a assinantes/eventos;
- gerar/regenerar segredo;
- reprocessar entregas.

Workers relacionados:

```bash
php bin/console app:webhook-delivery-worker
```

## 14. Monitores NFe

Monitores:

- saída: `/index.php/monitor-saida-nfe`
- entrada: `/index.php/monitor-entrada-nfe`

Monitor de saída:

- lista notas emitidas/enviadas;
- mostra detalhe de nota;
- mostra XML;
- mostra DANFE quando disponível;
- registra eventos fiscais;
- executa ações como cancelar, carta de correção e inutilizar quando aplicável.

Monitor de entrada:

- lista documentos recebidos pela distribuição DFe;
- mostra detalhe técnico e visão de usuário;
- permite manifestação do destinatário quando aplicável;
- expõe XML do documento.

Filtro por usuário:

- `super_admin`: vê tudo;
- demais usuários: veem apenas dados ligados aos assinantes e CNPJs das empresas vinculadas.

Se um usuário comum não tiver empresas vinculadas, os monitores não retornam dados.

## 15. Empresas e sincronização de emissores

As empresas (`t00006`) são derivadas principalmente dos emissores presentes nas extrações dos monitores.

Rotina usada pelo cadastro de usuários:

- sincroniza emissores encontrados;
- permite buscar empresas por nome ou CNPJ;
- vincula empresas ao usuário.

Esse vínculo impacta:

- dados visíveis nos monitores;
- assinantes permitidos;
- token disponível para uso em chamadas autenticadas.

## 16. Catálogo de programas

Tela:

```text
/index.php/catalogo-programas
```

Banco:

```text
var/db/program_catalog.sqlite
```

Regras:

- todo programa deve ter descrição resumida;
- todo programa deve ter explicação detalhada;
- todo programa deve apontar caminho físico de referência;
- alterações relevantes devem gerar histórico;
- programas encerrados não devem ser apagados, devem ser marcados como encerrados.

Documento complementar:

```text
docs/CATALOGO_PROGRAMAS_SQLITE.md
```

## 17. Catálogo de testes

Tela:

```text
/index.php/catalogo-testes
```

Funções:

- listar grupos e cenários de teste;
- executar teste individual;
- executar grupo;
- executar todos;
- registrar histórico de execução.

Diretriz:

- alterações relevantes em APIs ou telas devem ser acompanhadas por testes compatíveis.

## 18. Limpeza da base de exemplos NFe

Foi feita limpeza da base operacional para manter apenas exemplos válidos de NFe enviada.

Critério mantido:

- programa `nfe`;
- caminhos:
  - `/nfe/envio/enviar-sincrono-xml`;
  - `/nfe/envio/enviar-assincrono-xml`;
- processamento concluído;
- HTTP `200` ou `201`;
- nota extraída em `t99019`.

Resultado validado:

- `t99001`: 30 requisições restantes;
- `t99008`: 30 documentos restantes;
- `t99019`: 30 notas restantes;
- inválidos restantes pelo critério: 0.

Backups criados:

```text
cleanup_backup_20260819_keep30_*
```

## 19. Comandos úteis de validação

Lint Symfony/Twig:

```bash
php bin/console lint:container
php bin/console lint:twig templates/admin/base.html.twig templates/home/hub.html.twig templates/home/section.html.twig templates/catalog/base.html.twig
```

Testes PHP pontuais:

```bash
php tests/Repository/AuthUserRepositoryTest.php
php tests/Repository/AuthUserCompanyBindingTest.php
php tests/Service/AuthTokenServiceTest.php
php tests/AuthSessionPolicyTest.php
php tests/Controller/UserAdminPageTest.php
php tests/Controller/CompanyAdminMenuTest.php
php tests/Controller/CommonUserMenuTest.php
php tests/Controller/UserAdminTemplateEditTest.php
```

Validação JS:

```bash
node --check catalog-assets/admin/user-company-search.js
node --check catalog-assets/monitor/nfe-output-monitor.js
```

Testes shell existentes:

```bash
bash testes_api_platform/auditoria_requisicoes.sh
bash testes_api_platform/nfe_diagnostico.sh
bash testes_api_platform/nfe_rede_externa.sh
```

## 20. Boas práticas de alteração

Antes de editar:

```bash
git status --short
```

Regras práticas:

- preservar alterações locais não relacionadas;
- preferir `rg` para localizar arquivos e referências;
- usar Doctrine DBAL em código novo de banco;
- evitar `PDO` direto em controllers;
- para novas operações API, preferir DTOs específicos;
- manter metadados API Platform em XML quando o módulo já seguir esse padrão;
- executar testes proporcionais ao risco da alteração;
- não versionar senhas, tokens reais ou certificados;
- não apagar dados de banco sem backup e confirmação explícita.

## 21. Arquivos de referência recomendados

Leitura de contexto:

- `docs/ARQUITETURA_ATUAL.md`
- `docs/MAPA_MODULOS_API.md`
- `docs/FLUXO_INTEGRACAO_LEGADO.md`
- `docs/CONTEXTO_ACUMULADO_PROJETO.md`
- `docs/CONTEXTO_SESSAO_ATUAL.md`
- `docs/GUIA_RETORNO_RAPIDO.md`
- `docs/HISTORICO_IMPLEMENTACOES_ACBR.md`
- `docs/CHANGELOG_OPERACIONAL.md`

Arquivos sensíveis:

- `src/EventSubscriber/AuthGateSubscriber.php`
- `src/Service/Auth/CurrentUserContext.php`
- `src/Repository/Auth/AuthUserRepository.php`
- `src/Controller/UserAdminController.php`
- `src/Repository/ApiAuditRepository.php`
- `src/Service/Api/ApiAuditManager.php`
- `src/Service/Legacy/AcbrLegacyScriptExecutor.php`
- `src/Repository/NfeOutputMonitorRepository.php`
- `src/Repository/NfeInputMonitorRepository.php`
- `templates/admin/base.html.twig`
- `templates/admin/users.html.twig`
- `catalog-assets/admin/user-company-search.js`
- `catalog-assets/monitor/nfe-output-monitor.js`

## 22. Pontos de atenção

- O projeto ainda depende fisicamente dos scripts legados ACBr.
- NFe e NFSe ainda não são totalmente reimplementados no Symfony.
- O ambiente usa bind mount em `/dados_containers/www`.
- A validação web deve considerar a porta `8089`.
- Usuários comuns dependem de vínculo com empresas para enxergar dados nos monitores.
- `company_admin` pode criar outro `company_admin`, mas nunca `super_admin`.
- Backups de limpeza de base existem no banco com prefixo `cleanup_backup_20260819_keep30_`.
- O manual não contém credenciais reais, tokens ou senhas.
