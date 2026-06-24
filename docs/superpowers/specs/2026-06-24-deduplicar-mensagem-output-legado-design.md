# Design: deduplicar `mensagem` no output legado

Data: `2026-06-24`

## Objetivo

Evitar que a resposta pública exponha o campo `mensagem` duas vezes quando o conteúdo já estiver presente dentro de `resultado`.

## Escopo

Ajustar somente a montagem de saída das operações legadas para omitir `mensagem` no nível superior quando ela for duplicada do conteúdo principal já carregado em `resultado`.

## Abordagem

Aplicar a regra no ponto central de montagem da resposta das operações legadas, não apenas no endpoint de `NFe envio`.

Regra:

- manter `mensagem` quando ela acrescentar informação própria
- omitir `mensagem` quando ela repetir exatamente a mensagem principal já exposta dentro de `resultado`

## Impacto esperado

- remove redundância no payload JSON-LD
- preserva compatibilidade nos casos em que `mensagem` continua sendo útil
- evita correções isoladas por endpoint

## Teste

Adicionar teste focado no caso regressivo:

- quando `resultado` já carregar a mesma mensagem, o campo superior `mensagem` não deve ser serializado
- quando a mensagem for distinta ou não existir em `resultado`, o campo superior deve continuar presente
