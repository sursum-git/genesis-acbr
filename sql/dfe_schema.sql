ALTER TABLE public.t00002
    ADD COLUMN IF NOT EXISTS c_token varchar(255);

CREATE TABLE IF NOT EXISTS public.t99001 (
    id_t99001 bigserial PRIMARY KEY,
    u_c_request_id varchar(36) NOT NULL,
    c_metodo varchar(10) NOT NULL,
    c_caminho varchar(255) NOT NULL,
    c_cod_programa varchar(80),
    c_nome_programa varchar(255),
    c_versao_programa varchar(80),
    dt_hr_ult_atu_programa timestamp,
    c_revisao_programa varchar(64),
    c_fonte_versao varchar(80),
    c_caminho_fisico_programa varchar(500),
    c_rota varchar(255),
    c_nome_operacao varchar(255),
    c_modo_execucao varchar(10),
    c_token_hash varchar(64),
    c_ip_origem varchar(64),
    si_status_processamento smallint NOT NULL DEFAULT 0,
    si_status_http smallint,
    i_tempo_processamento_ms integer,
    t_query_string text,
    t_corpo_requisicao text,
    t_headers_requisicao text,
    t_corpo_resposta text,
    t_headers_resposta text,
    t_assinante_json text,
    t_erro text,
    dt_hr_recebimento timestamp NOT NULL DEFAULT now(),
    dt_hr_ini_processamento timestamp,
    dt_hr_fim_processamento timestamp,
    dt_hr_atu timestamp NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS t99001_u_c_request_id_uidx
    ON public.t99001 (u_c_request_id);

CREATE INDEX IF NOT EXISTS t99001_si_status_processamento_idx
    ON public.t99001 (si_status_processamento, dt_hr_recebimento);

CREATE INDEX IF NOT EXISTS t99001_c_token_hash_idx
    ON public.t99001 (c_token_hash);

CREATE TABLE IF NOT EXISTS public.t99002 (
    id_t99002 bigserial PRIMARY KEY,
    t99001_id bigint NOT NULL REFERENCES public.t99001 (id_t99001) ON DELETE CASCADE,
    si_num_tentativa smallint NOT NULL,
    si_status_processamento smallint NOT NULL DEFAULT 2,
    c_cod_programa varchar(80),
    c_nome_programa varchar(255),
    c_versao_programa varchar(80),
    dt_hr_ult_atu_programa timestamp,
    c_revisao_programa varchar(64),
    c_fonte_versao varchar(80),
    c_caminho_fisico_programa varchar(500),
    si_status_http smallint,
    t_corpo_resposta text,
    t_erro text,
    dt_hr_ini_processamento timestamp NOT NULL DEFAULT now(),
    dt_hr_fim_processamento timestamp,
    dt_hr_atu timestamp NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS t99002_t99001_id_idx
    ON public.t99002 (t99001_id, si_num_tentativa);

CREATE TABLE IF NOT EXISTS public.t99003 (
    id_t99003 bigserial PRIMARY KEY,
    c_chave_configuracao varchar(80) NOT NULL,
    c_caminho varchar(255),
    c_nome_operacao varchar(255),
    c_modo_execucao varchar(10) NOT NULL,
    log_ativo boolean NOT NULL DEFAULT true,
    dt_hr_atu timestamp NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS t99003_log_ativo_idx
    ON public.t99003 (log_ativo, c_nome_operacao, c_caminho);

CREATE TABLE IF NOT EXISTS public.t99004 (
    id_t99004 bigserial PRIMARY KEY,
    t99001_id bigint NOT NULL REFERENCES public.t99001 (id_t99001) ON DELETE CASCADE,
    c_evento varchar(80) NOT NULL,
    t_detalhe text,
    dt_hr_evento timestamp NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS t99004_t99001_id_idx
    ON public.t99004 (t99001_id, dt_hr_evento);

ALTER TABLE IF EXISTS public.t99001 ADD COLUMN IF NOT EXISTS c_cod_programa varchar(80);
ALTER TABLE IF EXISTS public.t99001 ADD COLUMN IF NOT EXISTS c_nome_programa varchar(255);
ALTER TABLE IF EXISTS public.t99001 ADD COLUMN IF NOT EXISTS c_versao_programa varchar(80);
ALTER TABLE IF EXISTS public.t99001 ADD COLUMN IF NOT EXISTS dt_hr_ult_atu_programa timestamp;
ALTER TABLE IF EXISTS public.t99001 ADD COLUMN IF NOT EXISTS c_revisao_programa varchar(64);
ALTER TABLE IF EXISTS public.t99001 ADD COLUMN IF NOT EXISTS c_fonte_versao varchar(80);
ALTER TABLE IF EXISTS public.t99001 ADD COLUMN IF NOT EXISTS c_caminho_fisico_programa varchar(500);

ALTER TABLE IF EXISTS public.t99002 ADD COLUMN IF NOT EXISTS c_cod_programa varchar(80);
ALTER TABLE IF EXISTS public.t99002 ADD COLUMN IF NOT EXISTS c_nome_programa varchar(255);
ALTER TABLE IF EXISTS public.t99002 ADD COLUMN IF NOT EXISTS c_versao_programa varchar(80);
ALTER TABLE IF EXISTS public.t99002 ADD COLUMN IF NOT EXISTS dt_hr_ult_atu_programa timestamp;
ALTER TABLE IF EXISTS public.t99002 ADD COLUMN IF NOT EXISTS c_revisao_programa varchar(64);
ALTER TABLE IF EXISTS public.t99002 ADD COLUMN IF NOT EXISTS c_fonte_versao varchar(80);
ALTER TABLE IF EXISTS public.t99002 ADD COLUMN IF NOT EXISTS c_caminho_fisico_programa varchar(500);
