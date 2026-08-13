# Login system implementation plan

## Scope

- Add username/password authentication backed by audit database tables.
- Model users, companies, company-user roles, and company-subscriber bindings.
- Keep legacy `X-Api-Token` support for machine integrations.
- Add user JWT access and refresh tokens.
- Resolve subscriber token from the current user context for monitor fiscal actions.
- Restrict operation/admin screens to super administrators.

## Tables

- `t00005`: users.
- `t00006`: companies.
- `t00007`: user-company memberships.
- `t00008`: company-subscriber memberships.
- `t00009`: refresh tokens.

## Roles

- `super_admin`: global developer/admin access.
- `company_admin`: administers the companies where the role is assigned.
- `common`: monitor/operator access only.

## Execution notes

- Direct fiscal API token auth remains supported.
- JWT auth requires `X-Subscriber-Id` whenever a fiscal API operation needs a subscriber context.
- Web session stores the current user context and optional active company/subscriber.
- Existing monitor rows are scoped by subscriber snapshot fields, with exact `t00002_id` support for new rows.

## Deployment notes

- Run `php bin/console app:auth:bootstrap-admin <usuario> <senha>` after applying `sql/auth_schema.sql`.
- Define `APP_AUTH_JWT_SECRET` in the environment with a long random value before enabling JWT auth.
- Existing legacy `X-Api-Token` values in `t00002.c_token` remain valid for external machine integrations.
