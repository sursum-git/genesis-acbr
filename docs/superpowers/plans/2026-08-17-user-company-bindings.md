# User Company Bindings Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow an admin geral to associate companies with users from the `/usuarios` screen.

**Architecture:** Extend the existing `UserAdminController`, `AuthUserRepository`, and `templates/admin/users.html.twig`. Use existing tables `t00006` for companies and `t00007` for user-company bindings; do not introduce new schema.

**Tech Stack:** Symfony 7.4 controllers/routes, Twig, Doctrine DBAL, existing plain PHP controller tests.

## Global Constraints

- Keep `/usuarios` restricted to `super_admin` through the existing auth gate.
- Use `t00007` as the only user-company association table.
- Existing unrelated workspace changes must be preserved.
- Super admin users do not need company bindings for global access.

---

### Task 1: Render company association controls

**Files:**
- Modify: `src/Repository/Auth/AuthUserRepository.php`
- Modify: `src/Controller/UserAdminController.php`
- Modify: `templates/admin/users.html.twig`
- Test: `tests/Controller/UserAdminPageTest.php`

**Interfaces:**
- Produces: `AuthUserRepository::listCompanies(): list<array<string,mixed>>`
- Produces: `AuthUserRepository::companiesForUsers(array $userIds): array<int,list<array<string,mixed>>>`

- [ ] **Step 1: Write the failing test**

Add assertions to `tests/Controller/UserAdminPageTest.php` requiring the rendered page to contain `Empresas`, `empresa_ids[]`, and `/index.php/usuarios/empresas/salvar`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/Controller/UserAdminPageTest.php`
Expected: fail because company association controls are missing.

- [ ] **Step 3: Implement repository/controller/template rendering**

Add company list and user-company maps to the repository, pass them from the controller, and render a multi-select in the new-user form plus a per-user company form in the table.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/Controller/UserAdminPageTest.php`
Expected: pass.

### Task 2: Persist company bindings

**Files:**
- Modify: `src/Repository/Auth/AuthUserRepository.php`
- Modify: `src/Controller/UserAdminController.php`
- Test: `tests/Repository/AuthUserCompanyBindingTest.php`

**Interfaces:**
- Produces: `AuthUserRepository::replaceUserCompanies(int $userId, array $companyIds, string $role): void`
- Consumes: `AuthUserRepository::assignUserCompany(int $userId, int $companyId, string $role): void`

- [ ] **Step 1: Write the failing test**

Create an in-memory SQLite repository test that creates two companies, creates one user, calls `replaceUserCompanies()`, verifies active bindings, then replaces with one company and verifies the removed binding is inactive.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/Repository/AuthUserCompanyBindingTest.php`
Expected: fail because `replaceUserCompanies()` does not exist.

- [ ] **Step 3: Implement persistence**

Implement `replaceUserCompanies()` by deactivating existing bindings for the user, then upserting selected bindings with the selected role.

- [ ] **Step 4: Wire forms**

In user creation, read `empresa_ids[]` and create bindings after creating the user. Add POST `/usuarios/empresas/salvar` to update bindings for existing users.

- [ ] **Step 5: Run verification**

Run:

```bash
php tests/Repository/AuthUserCompanyBindingTest.php
php tests/Controller/UserAdminPageTest.php
php bin/console lint:container
```

Expected: all pass.
