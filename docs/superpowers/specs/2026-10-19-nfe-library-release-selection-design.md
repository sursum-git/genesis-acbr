# Seleção Versionada da ACBrLibNFe

Data: 2026-10-19

## Objetivo

Permitir que a aplicação escolha de forma determinística, auditável e reversível
a biblioteca nativa `libacbrnfe64.so` usada pelas operações de NFe.

A escolha deve considerar:

- módulo ACBr, inicialmente `nfe`;
- ambiente fiscal, `homologacao` ou `producao`;
- período de vigência;
- perfil INI associado;
- integridade do artefato por SHA-256.

O sistema deve permitir homologar uma release nova sem alterar a release vigente
em produção e permitir rollback por nova vigência da release anterior.

## Contexto atual

O carregador legado procura `libacbrnfe64.so` inicialmente em
`/opt/acbr_libs/x64`. O caminho não é versionado e a aplicação não registra a
versão real usada em cada requisição.

Existem perfis INI em `NFe/MT/configs/<perfil>/ACBrNFe.INI`, mas eles controlam
apenas a configuração ACBr. O perfil pode ser escolhido por `ACBrIniProfile`,
mas não seleciona a biblioteca nativa.

O PostgreSQL de auditoria já é a fonte de configuração operacional, incluindo
`t99003` para modo de execução. Essa tabela não deve ser reutilizada porque não
representa um artefato, vigência ou integridade de biblioteca.

## Modelo de dados

Criar `t99037_acbr_library_release` no banco PostgreSQL de auditoria.

| Coluna | Finalidade |
| --- | --- |
| `id_t99037` | Identificador da release configurada. |
| `c_modulo` | Módulo ACBr, inicialmente `nfe`. |
| `c_ambiente` | Ambiente fiscal: `homologacao` ou `producao`. |
| `c_release` | Versão declarada da ACBrLib, por exemplo `1.5.0.488`. |
| `c_caminho_so` | Caminho absoluto do binário aprovado. |
| `c_sha256` | SHA-256 hexadecimal do binário aprovado. |
| `c_perfil_ini` | Perfil INI que deve acompanhar a release. |
| `dt_hr_inicio_vigencia` | Início inclusivo da seleção. |
| `dt_hr_fim_vigencia` | Fim exclusivo; nulo enquanto vigente. |
| `log_ativo` | Habilita ou desabilita a entrada sem apagá-la. |
| `t_observacao` | Motivo operacional, mudanças relevantes ou rollback. |
| `dt_hr_criacao` | Data de criação. |
| `dt_hr_atu` | Data da última alteração. |

Restrições:

- `c_modulo`, `c_ambiente`, `c_release`, `c_caminho_so`, `c_sha256`,
  `c_perfil_ini` e `dt_hr_inicio_vigencia` são obrigatórios;
- `c_ambiente` aceita apenas `homologacao` e `producao`;
- `c_sha256` deve ter 64 caracteres hexadecimais;
- quando definido, `dt_hr_fim_vigencia` deve ser posterior ao início;
- não podem existir duas entradas ativas com períodos sobrepostos para o mesmo
  módulo e ambiente;
- registros históricos não são apagados.

No PostgreSQL, a proibição de sobreposição deve ser garantida por uma restrição
de exclusão sobre o intervalo de vigência. A validação da aplicação deve
antecipar uma mensagem clara, mas a restrição do banco é a garantia final.

## Artefatos de biblioteca

Cada release é instalada de modo imutável em um diretório próprio, fora do Git:

```text
/opt/acbr_libs/releases/nfe-<release>/x64/libacbrnfe64.so
```

As dependências nativas compatíveis devem acompanhar a release quando não forem
fornecidas pelo sistema operacional. O deploy deve criar o diretório, gravar o
manifesto de origem e o SHA-256, e somente então criar a linha de vigência.

O caminho configurado precisa ser canônico, existir como arquivo regular e ficar
dentro de `/opt/acbr_libs/releases/`. A aplicação calcula novamente o SHA-256
antes do carregamento. Uma divergência impede a execução.

## Seleção em tempo de execução

1. A requisição Symfony resolve o perfil INI solicitado ou ativo.
2. Um resolver lê o `Ambiente` do INI e o normaliza para `homologacao` ou
   `producao`.
3. O resolver consulta a única release ativa para `nfe`, ambiente e instante
   atual.
4. O resolvedor valida caminho, raiz permitida e checksum.
5. `AcbrLegacyScriptExecutor` encaminha ao script legado o identificador da
   release e o perfil INI, ambos emitidos internamente.
6. O script legado aceita somente uma seleção interna autenticada; ele obtém o
   caminho a partir do identificador e nunca de parâmetro público.
7. `CarregaDll` recebe o caminho aprovado e carrega a `.so` correspondente.

O transporte da seleção interna terá assinatura HMAC com segredo configurado no
ambiente. O conteúdo assinado inclui id da release, módulo, perfil INI, instante
de expiração curto e nonce. O legado rejeita uma seleção ausente, expirada,
inválida ou incompatível com o módulo.

Chamadas diretas aos scripts legados não podem informar versão nem caminho. Elas
continuam usando somente a seleção padrão explicitamente configurada para o
ambiente local, com mensagem de erro se não houver seleção segura.

## Auditoria e observabilidade

Adicionar ao snapshot da requisição em `t99001`:

- identificador da release (`t99037_id`);
- módulo;
- ambiente;
- versão declarada;
- caminho aprovado;
- SHA-256;
- perfil INI;
- versão retornada por `NFE_Versao`, quando disponível.

A versão e o hash são copiados para a requisição no início da execução; mudanças
posteriores na tabela não alteram o histórico. Falhas de resolução, integridade
ou incompatibilidade devem registrar evento operacional com motivo sanitizado.

## Administração

Uma tela restrita a `super_admin` permitirá listar, cadastrar, agendar,
desativar e consultar o histórico das releases NFe. Ela não fará upload de
bibliotecas nem aceitará conteúdo binário. A instalação do artefato continua
sendo responsabilidade controlada do deploy.

Antes de salvar, a tela e o endpoint administrativo validam intervalo, perfil
INI existente, caminho permitido, presença do arquivo e checksum. Alterações
em uma release já iniciada não substituem o artefato: devem criar uma nova
vigência ou registrar rollback.

## Compatibilidade e rollout

O primeiro registro preservará a release atualmente declarada (`1.5.0.444`) e
apontará para o binário existente após inventário operacional. Nenhuma nova
versão será promovida até a leitura de `NFE_Versao` confirmar a versão real.

Rollout de uma release nova:

1. instalar o artefato imutável e registrar seu checksum;
2. criar perfil INI de homologação, se necessário;
3. cadastrar vigência em homologação;
4. executar a matriz de testes NFe;
5. revisar versão, logs e auditoria;
6. cadastrar vigência em produção;
7. manter a release anterior para rollback.

Não é suportado alternar duas releases concorrentes no mesmo processo PHP-FPM.
Homologação e produção devem rodar em instâncias ou containers independentes;
isso evita conflitos entre dependências nativas e o carregador dinâmico.

## Testes

- resolver seleciona release por módulo, ambiente e instante;
- ausência, múltipla correspondência e período vencido falham de forma segura;
- intervalos sobrepostos são rejeitados pela aplicação e pelo banco;
- caminho fora da raiz permitida, arquivo ausente e SHA-256 divergente falham;
- perfil INI com ambiente incompatível não seleciona a release;
- executor transmite seleção interna assinada;
- script legado rejeita seleção externa, inválida ou expirada;
- auditoria persiste o snapshot de release;
- `NFE_Versao` é exposta em rota somente leitura autenticada;
- fluxos NFe de homologação cobrem consulta, distribuição, envio, evento,
  inutilização, DANFE/PDF e e-mail.
