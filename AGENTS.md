# Diretrizes do projeto

- Leia `docs/README_CONTEXTO.md` e `docs/CONTEXTO_SESSAO_ATUAL.md` antes de alterar o projeto.
- Para ACBrLib em Linux, utilize sempre a variante `CONSOLE-MT` da arquitetura adequada. Neste servidor, use x64. A variante `Linux/MT` com GTK/X11 nao atende a esta preferencia.
- Confira o artefato dentro do pacote, as dependencias, o SHA-256 e a versao real retornada pela biblioteca. Cadastre cada variante em caminho proprio e preserve o historico anterior.
- Homologue a release e seus schemas separadamente antes de promover para producao.
- Nao registrar chaves privadas, frases secretas, senhas ou tokens no repositorio.
- NFe: no INI nativo Ambiente=0 significa producao e Ambiente=1 homologacao; no XML tpAmb=1 significa producao e tpAmb=2 homologacao. Verifique o valor efetivo da biblioteca antes de qualquer operacao fiscal; nao contorne erros de INI com configuracoes padrao.
