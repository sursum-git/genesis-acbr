# Deduplicar Mensagem Output Legado Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remover o campo superior `mensagem` apenas quando ele duplicar a mesma informação já presente em `resultado` nas respostas legadas.

**Architecture:** Concentrar a regra no DTO de saída legado, que já recebe `resultado` e `mensagem` e é compartilhado pelos providers/processors da integração ACBr. Cobrir a regressão com teste unitário do DTO para evitar ajuste endpoint a endpoint.

**Tech Stack:** PHP 8.2, Symfony 7, API Platform 4.2, PHPUnit via `phpunit`

---

### Task 1: Cobrir a regressão no DTO legado

**Files:**
- Create: `tests/Dto/Legacy/AbstractLegacyOperationOutputTest.php`
- Test: `tests/Dto/Legacy/AbstractLegacyOperationOutputTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Dto\Legacy;

use App\Dto\Legacy\AbstractLegacyOperationOutput;
use PHPUnit\Framework\TestCase;

final class AbstractLegacyOperationOutputTest extends TestCase
{
    public function testOmitMensagemWhenResultadoAlreadyContainsSameMensagem(): void
    {
        $mensagem = "Linha 1\nLinha 2";

        $output = new AbstractLegacyOperationOutput(
            resultado: ['mensagem' => $mensagem],
            mensagem: $mensagem,
        );

        self::assertNull($output->mensagem);
    }

    public function testKeepMensagemWhenResultadoDoesNotContainSameMensagem(): void
    {
        $output = new AbstractLegacyOperationOutput(
            resultado: ['mensagem' => 'Mensagem interna'],
            mensagem: 'Mensagem publica',
        );

        self::assertSame('Mensagem publica', $output->mensagem);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Dto/Legacy/AbstractLegacyOperationOutputTest.php`
Expected: FAIL because `mensagem` ainda permanece preenchida mesmo quando duplicada.

- [ ] **Step 3: Write minimal implementation**

```php
private function normalizeMensagem(?array $resultado, ?string $mensagem): ?string
{
    if ($mensagem === null) {
        return null;
    }

    $mensagemResultado = $this->extractString($resultado, 'mensagem');

    if ($mensagemResultado !== null && $mensagemResultado === $mensagem) {
        return null;
    }

    return $mensagem;
}
```

Usar esse helper no construtor de `src/Dto/Legacy/AbstractLegacyOperationOutput.php`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Dto/Legacy/AbstractLegacyOperationOutputTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add tests/Dto/Legacy/AbstractLegacyOperationOutputTest.php src/Dto/Legacy/AbstractLegacyOperationOutput.php
git commit -m "fix: deduplicate legacy output message"
```

### Task 2: Verificação rápida do projeto

**Files:**
- Modify: `src/Dto/Legacy/AbstractLegacyOperationOutput.php`
- Test: `tests/Dto/Legacy/AbstractLegacyOperationOutputTest.php`

- [ ] **Step 1: Run a focused syntax check**

Run: `php -l src/Dto/Legacy/AbstractLegacyOperationOutput.php`
Expected: `No syntax errors detected`

- [ ] **Step 2: Re-run the focused test**

Run: `php vendor/bin/phpunit tests/Dto/Legacy/AbstractLegacyOperationOutputTest.php`
Expected: PASS

- [ ] **Step 3: Confirm working tree status**

Run: `git status --short`
Expected: only the intended files for this fix, plus any pre-existing unrelated changes already in the tree.
