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
    si_status_extracao smallint NOT NULL DEFAULT 0,
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
    dt_hr_ini_extracao timestamp,
    dt_hr_fim_extracao timestamp,
    t_erro_extracao text,
    dt_hr_atu timestamp NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS t99001_u_c_request_id_uidx
    ON public.t99001 (u_c_request_id);

CREATE INDEX IF NOT EXISTS t99001_si_status_processamento_idx
    ON public.t99001 (si_status_processamento, dt_hr_recebimento);

CREATE INDEX IF NOT EXISTS t99001_c_token_hash_idx
    ON public.t99001 (c_token_hash);

CREATE INDEX IF NOT EXISTS t99001_si_status_extracao_idx
    ON public.t99001 (si_status_extracao, dt_hr_recebimento);

CREATE TABLE IF NOT EXISTS public.t99002 (
    id_t99002 bigserial PRIMARY KEY,
    t99001_id bigint NOT NULL REFERENCES public.t99001 (id_t99001) ON DELETE CASCADE,
    si_num_tentativa smallint NOT NULL,
    si_status_processamento smallint NOT NULL DEFAULT 2,
    c_worker_id varchar(160),
    i_worker_pid integer,
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

CREATE TABLE IF NOT EXISTS public.t99005 (
    id_t99005 bigserial PRIMARY KEY,
    qtd_workers integer NOT NULL,
    dt_inicio_vigencia timestamp NOT NULL,
    dt_fim_vigencia timestamp,
    log_ativo boolean NOT NULL DEFAULT true,
    t_observacao text,
    dt_hr_atu timestamp NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS t99005_vigencia_idx
    ON public.t99005 (log_ativo, dt_inicio_vigencia, dt_fim_vigencia);

CREATE TABLE IF NOT EXISTS public.t00003 (
    id_t00003 bigserial PRIMARY KEY,
    c_nome varchar(255) NOT NULL,
    c_url varchar(1000) NOT NULL,
    c_metodo_http varchar(10) NOT NULL DEFAULT 'POST',
    t_headers_json text,
    t_secret varchar(255),
    si_timeout_segundos smallint NOT NULL DEFAULT 10,
    t_variaveis_json text,
    c_success_mode varchar(30) NOT NULL DEFAULT 'status_only',
    c_success_status_codes varchar(255) NOT NULL DEFAULT '200,201,202,204',
    t_success_payload_rules_json text,
    si_max_tentativas smallint NOT NULL DEFAULT 3,
    si_intervalo_tentativas_segundos integer NOT NULL DEFAULT 300,
    log_ativo boolean NOT NULL DEFAULT true,
    dt_hr_atu timestamp NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.t00004 (
    id_t00004 bigserial PRIMARY KEY,
    c_assinante_identificador varchar(120) NOT NULL,
    t00003_id bigint NOT NULL REFERENCES public.t00003 (id_t00003) ON DELETE CASCADE,
    c_programa varchar(80) NOT NULL DEFAULT '*',
    c_evento varchar(80) NOT NULL DEFAULT 'request.completed',
    c_caminho varchar(255),
    c_modo_execucao varchar(10) NOT NULL DEFAULT 'sync',
    log_ativo boolean NOT NULL DEFAULT true,
    dt_hr_atu timestamp NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS t00004_assinante_idx
    ON public.t00004 (c_assinante_identificador, log_ativo, c_programa, c_evento);

CREATE TABLE IF NOT EXISTS public.t99006 (
    id_t99006 bigserial PRIMARY KEY,
    t00004_id bigint NOT NULL REFERENCES public.t00004 (id_t00004) ON DELETE CASCADE,
    t99001_id bigint NOT NULL REFERENCES public.t99001 (id_t99001) ON DELETE CASCADE,
    c_status_entrega varchar(20) NOT NULL DEFAULT 'pending',
    si_num_tentativa smallint NOT NULL DEFAULT 0,
    si_status_http smallint,
    t_payload_json text,
    t_headers_resposta text,
    t_corpo_resposta text,
    t_erro text,
    dt_hr_proxima_tentativa timestamp,
    dt_hr_ini_processamento timestamp,
    dt_hr_fim_processamento timestamp,
    dt_hr_atu timestamp NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS t99006_binding_request_uidx
    ON public.t99006 (t00004_id, t99001_id);

CREATE INDEX IF NOT EXISTS t99006_status_idx
    ON public.t99006 (c_status_entrega, dt_hr_proxima_tentativa, dt_hr_atu);

CREATE TABLE IF NOT EXISTS public.t99007 (
    id_t99007 bigserial PRIMARY KEY,
    t99001_id bigint NOT NULL REFERENCES public.t99001 (id_t99001) ON DELETE CASCADE,
    u_c_request_id varchar(36) NOT NULL,
    c_caminho_origem varchar(255),
    c_tipo_documento varchar(60),
    c_chave_acesso varchar(44),
    c_nsu_relacionado varchar(20),
    c_numero varchar(20),
    c_serie varchar(10),
    c_modelo varchar(10),
    c_emitente_documento varchar(20),
    c_destinatario_documento varchar(20),
    c_interessado_documento varchar(20),
    c_stat varchar(10),
    x_motivo varchar(500),
    c_situacao varchar(120),
    dt_emissao timestamp,
    dt_autorizacao timestamp,
    t_payload_bruto text,
    dt_hr_extracao timestamp NOT NULL DEFAULT now(),
    dt_hr_atu timestamp NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS t99007_t99001_id_idx
    ON public.t99007 (t99001_id, dt_hr_extracao DESC);

CREATE INDEX IF NOT EXISTS t99007_chave_idx
    ON public.t99007 (c_chave_acesso);

CREATE TABLE IF NOT EXISTS public.t99008 (
    id_t99008 bigserial PRIMARY KEY,
    t99001_id bigint NOT NULL REFERENCES public.t99001 (id_t99001) ON DELETE CASCADE,
    u_c_request_id varchar(36) NOT NULL,
    c_caminho_origem varchar(255),
    c_tipo_item varchar(60),
    c_nsu_consultado varchar(20),
    c_nsu varchar(20),
    c_ult_nsu varchar(20),
    c_max_nsu varchar(20),
    c_schema varchar(120),
    c_chave_acesso varchar(44),
    c_stat varchar(10),
    x_motivo varchar(500),
    c_situacao varchar(120),
    t_payload_bruto text,
    dt_hr_extracao timestamp NOT NULL DEFAULT now(),
    dt_hr_atu timestamp NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS t99008_t99001_id_idx
    ON public.t99008 (t99001_id, dt_hr_extracao DESC);

CREATE INDEX IF NOT EXISTS t99008_nsu_idx
    ON public.t99008 (c_nsu, c_ult_nsu, c_max_nsu);

ALTER TABLE IF EXISTS public.t99001 ADD COLUMN IF NOT EXISTS c_cod_programa varchar(80);
ALTER TABLE IF EXISTS public.t99001 ADD COLUMN IF NOT EXISTS c_nome_programa varchar(255);
ALTER TABLE IF EXISTS public.t99001 ADD COLUMN IF NOT EXISTS c_versao_programa varchar(80);
ALTER TABLE IF EXISTS public.t99001 ADD COLUMN IF NOT EXISTS dt_hr_ult_atu_programa timestamp;
ALTER TABLE IF EXISTS public.t99001 ADD COLUMN IF NOT EXISTS c_revisao_programa varchar(64);
ALTER TABLE IF EXISTS public.t99001 ADD COLUMN IF NOT EXISTS c_fonte_versao varchar(80);
ALTER TABLE IF EXISTS public.t99001 ADD COLUMN IF NOT EXISTS c_caminho_fisico_programa varchar(500);
ALTER TABLE IF EXISTS public.t99001 ADD COLUMN IF NOT EXISTS si_status_extracao smallint NOT NULL DEFAULT 0;
ALTER TABLE IF EXISTS public.t99001 ADD COLUMN IF NOT EXISTS dt_hr_ini_extracao timestamp;
ALTER TABLE IF EXISTS public.t99001 ADD COLUMN IF NOT EXISTS dt_hr_fim_extracao timestamp;
ALTER TABLE IF EXISTS public.t99001 ADD COLUMN IF NOT EXISTS t_erro_extracao text;

ALTER TABLE IF EXISTS public.t99002 ADD COLUMN IF NOT EXISTS c_cod_programa varchar(80);
ALTER TABLE IF EXISTS public.t99002 ADD COLUMN IF NOT EXISTS c_worker_id varchar(160);
ALTER TABLE IF EXISTS public.t99002 ADD COLUMN IF NOT EXISTS i_worker_pid integer;
ALTER TABLE IF EXISTS public.t99002 ADD COLUMN IF NOT EXISTS c_nome_programa varchar(255);
ALTER TABLE IF EXISTS public.t99002 ADD COLUMN IF NOT EXISTS c_versao_programa varchar(80);
ALTER TABLE IF EXISTS public.t99002 ADD COLUMN IF NOT EXISTS dt_hr_ult_atu_programa timestamp;
ALTER TABLE IF EXISTS public.t99002 ADD COLUMN IF NOT EXISTS c_revisao_programa varchar(64);
ALTER TABLE IF EXISTS public.t99002 ADD COLUMN IF NOT EXISTS c_fonte_versao varchar(80);
ALTER TABLE IF EXISTS public.t99002 ADD COLUMN IF NOT EXISTS c_caminho_fisico_programa varchar(500);

ALTER TABLE IF EXISTS public.t00003 ADD COLUMN IF NOT EXISTS t_variaveis_json text;
ALTER TABLE IF EXISTS public.t00003 ADD COLUMN IF NOT EXISTS c_success_mode varchar(30) NOT NULL DEFAULT 'status_only';
ALTER TABLE IF EXISTS public.t00003 ADD COLUMN IF NOT EXISTS c_success_status_codes varchar(255) NOT NULL DEFAULT '200,201,202,204';
ALTER TABLE IF EXISTS public.t00003 ADD COLUMN IF NOT EXISTS t_success_payload_rules_json text;
ALTER TABLE IF EXISTS public.t00003 ADD COLUMN IF NOT EXISTS si_max_tentativas smallint NOT NULL DEFAULT 3;
ALTER TABLE IF EXISTS public.t00003 ADD COLUMN IF NOT EXISTS si_intervalo_tentativas_segundos integer NOT NULL DEFAULT 300;
