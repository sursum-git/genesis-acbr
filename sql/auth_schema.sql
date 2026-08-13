CREATE TABLE IF NOT EXISTS public.t00005 (
    id_t00005 bigserial PRIMARY KEY,
    c_usuario varchar(80) NOT NULL,
    c_usuario_normalizado varchar(80) NOT NULL,
    c_senha_hash varchar(255) NOT NULL,
    c_nome varchar(255),
    c_tipo varchar(30) NOT NULL DEFAULT 'common',
    i_token_version integer NOT NULL DEFAULT 1,
    log_trocar_senha boolean NOT NULL DEFAULT false,
    log_ativo boolean NOT NULL DEFAULT true,
    dt_hr_criacao timestamptz NOT NULL DEFAULT now(),
    dt_hr_ult_login timestamptz,
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS t00005_usuario_uidx ON public.t00005 (c_usuario_normalizado);

CREATE TABLE IF NOT EXISTS public.t00006 (
    id_t00006 bigserial PRIMARY KEY,
    c_nome varchar(255) NOT NULL,
    c_cnpj char(14) NOT NULL,
    log_ativo boolean NOT NULL DEFAULT true,
    dt_hr_criacao timestamptz NOT NULL DEFAULT now(),
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS t00006_cnpj_uidx ON public.t00006 (c_cnpj);

CREATE TABLE IF NOT EXISTS public.t00007 (
    id_t00007 bigserial PRIMARY KEY,
    t00005_id bigint NOT NULL REFERENCES public.t00005 (id_t00005) ON DELETE CASCADE,
    t00006_id bigint NOT NULL REFERENCES public.t00006 (id_t00006) ON DELETE CASCADE,
    c_role varchar(30) NOT NULL DEFAULT 'common',
    log_ativo boolean NOT NULL DEFAULT true,
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS t00007_user_company_uidx ON public.t00007 (t00005_id, t00006_id);

CREATE TABLE IF NOT EXISTS public.t00008 (
    id_t00008 bigserial PRIMARY KEY,
    t00006_id bigint NOT NULL REFERENCES public.t00006 (id_t00006) ON DELETE CASCADE,
    t00002_id bigint NOT NULL REFERENCES public.t00002 (id_t00002) ON DELETE CASCADE,
    log_ativo boolean NOT NULL DEFAULT true,
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS t00008_company_subscriber_uidx ON public.t00008 (t00006_id, t00002_id);

CREATE TABLE IF NOT EXISTS public.t00009 (
    id_t00009 bigserial PRIMARY KEY,
    t00005_id bigint NOT NULL REFERENCES public.t00005 (id_t00005) ON DELETE CASCADE,
    c_token_hash char(64) NOT NULL,
    c_family varchar(64) NOT NULL,
    dt_hr_expira timestamptz NOT NULL,
    dt_hr_revogado timestamptz,
    dt_hr_criacao timestamptz NOT NULL DEFAULT now(),
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS t00009_token_hash_uidx ON public.t00009 (c_token_hash);

ALTER TABLE public.t99001 ADD COLUMN IF NOT EXISTS t00005_id bigint;
ALTER TABLE public.t99001 ADD COLUMN IF NOT EXISTS t00006_id bigint;
ALTER TABLE public.t99001 ADD COLUMN IF NOT EXISTS t00002_id bigint;
ALTER TABLE public.t99001 ADD COLUMN IF NOT EXISTS c_auth_type varchar(30);
