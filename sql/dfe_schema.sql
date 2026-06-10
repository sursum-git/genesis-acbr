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

DROP TABLE IF EXISTS public.t99018 CASCADE;
DROP TABLE IF EXISTS public.t99017 CASCADE;
DROP TABLE IF EXISTS public.t99016 CASCADE;
DROP TABLE IF EXISTS public.t99015 CASCADE;
DROP TABLE IF EXISTS public.t99014 CASCADE;
DROP TABLE IF EXISTS public.t99013 CASCADE;
DROP TABLE IF EXISTS public.t99012 CASCADE;
DROP TABLE IF EXISTS public.t99011 CASCADE;
DROP TABLE IF EXISTS public.t99010 CASCADE;
DROP TABLE IF EXISTS public.t99009 CASCADE;
DROP TABLE IF EXISTS public.t99008 CASCADE;
DROP TABLE IF EXISTS public.t99007 CASCADE;

CREATE TABLE IF NOT EXISTS public.t99007 (
    id_t99007 bigserial PRIMARY KEY,
    t99001_id bigint NOT NULL REFERENCES public.t99001 (id_t99001) ON DELETE CASCADE,
    u_c_request_id varchar(36) NOT NULL,
    caminho_origem varchar(255),
    tipo_consulta varchar(40),
    documento_consulta varchar(20),
    nsu_entrada char(15),
    tp_amb smallint,
    ver_aplic varchar(120),
    c_stat integer,
    x_motivo varchar(255),
    dh_resp timestamptz,
    ult_nsu char(15),
    max_nsu char(15),
    q_doc_zip integer NOT NULL DEFAULT 0,
    xml_envelope text,
    dt_hr_criacao timestamptz NOT NULL DEFAULT now(),
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS t99007_t99001_id_idx
    ON public.t99007 (t99001_id, id_t99007 DESC);

CREATE TABLE IF NOT EXISTS public.t99008 (
    id_t99008 bigserial PRIMARY KEY,
    t99007_id bigint NOT NULL REFERENCES public.t99007 (id_t99007) ON DELETE CASCADE,
    u_c_request_id varchar(36) NOT NULL,
    caminho_origem varchar(255),
    nsu char(15),
    schema_name varchar(100) NOT NULL,
    schema_family varchar(30),
    ch_nfe char(44),
    tp_evento varchar(20),
    n_seq_evento integer,
    n_prot varchar(20),
    xml_gzip_base64 text,
    xml_descompactado text,
    hash_xml varchar(64),
    tp_amb smallint,
    emit_cnpj_cpf varchar(14),
    dest_cnpj_cpf varchar(14),
    dt_hr_processado_em timestamptz NOT NULL DEFAULT now(),
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS t99008_schema_nsu_hash_uidx
    ON public.t99008 (schema_name, nsu, hash_xml);

CREATE INDEX IF NOT EXISTS t99008_t99007_id_idx
    ON public.t99008 (t99007_id, id_t99008 DESC);

CREATE INDEX IF NOT EXISTS t99008_chave_idx
    ON public.t99008 (ch_nfe);

CREATE TABLE IF NOT EXISTS public.t99009 (
    t99008_id bigint PRIMARY KEY REFERENCES public.t99008 (id_t99008) ON DELETE CASCADE,
    cnpj varchar(14),
    cpf varchar(11),
    x_nome varchar(255),
    ie varchar(20),
    dh_emi timestamptz,
    tp_nf smallint,
    v_nf numeric(18,2),
    dig_val varchar(128),
    dh_recbto timestamptz,
    c_sit_nfe varchar(20),
    versao varchar(20),
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.t99010 (
    t99008_id bigint PRIMARY KEY REFERENCES public.t99008 (id_t99008) ON DELETE CASCADE,
    versao varchar(20),
    id_nfe varchar(80),
    c_uf varchar(4),
    n_nf varchar(20),
    serie varchar(10),
    mod varchar(10),
    dh_emi timestamptz,
    dh_saida_entrada timestamptz,
    tp_nf smallint,
    id_dest varchar(4),
    c_mun_fg varchar(10),
    tp_emis varchar(10),
    tp_amb smallint,
    fin_nfe varchar(10),
    ind_final varchar(10),
    ind_pres varchar(10),
    proc_emi varchar(10),
    ver_proc varchar(60),
    n_prot varchar(20),
    ch_nfe char(44),
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.t99011 (
    t99008_id bigint PRIMARY KEY REFERENCES public.t99008 (id_t99008) ON DELETE CASCADE,
    cnpj varchar(14),
    cpf varchar(11),
    x_nome varchar(255),
    x_fant varchar(255),
    ie varchar(20),
    iest varchar(20),
    im varchar(20),
    cnae varchar(20),
    crt varchar(10),
    x_lgr varchar(255),
    nro varchar(20),
    x_bairro varchar(255),
    c_mun varchar(10),
    x_mun varchar(255),
    uf varchar(4),
    cep varchar(12),
    c_pais varchar(10),
    x_pais varchar(120),
    fone varchar(20),
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.t99012 (
    t99008_id bigint PRIMARY KEY REFERENCES public.t99008 (id_t99008) ON DELETE CASCADE,
    cnpj varchar(14),
    cpf varchar(11),
    id_estrangeiro varchar(40),
    x_nome varchar(255),
    ind_ie_dest varchar(10),
    ie varchar(20),
    isuf varchar(20),
    im varchar(20),
    email varchar(255),
    x_lgr varchar(255),
    nro varchar(20),
    x_bairro varchar(255),
    c_mun varchar(10),
    x_mun varchar(255),
    uf varchar(4),
    cep varchar(12),
    c_pais varchar(10),
    x_pais varchar(120),
    fone varchar(20),
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.t99013 (
    id_t99013 bigserial PRIMARY KEY,
    t99008_id bigint NOT NULL REFERENCES public.t99008 (id_t99008) ON DELETE CASCADE,
    n_item integer,
    c_prod varchar(80),
    c_ean varchar(30),
    x_prod varchar(255),
    c_ncm varchar(20),
    c_cest varchar(20),
    c_cfop varchar(10),
    u_com varchar(20),
    q_com numeric(18,4),
    v_un_com numeric(18,6),
    v_prod numeric(18,2),
    c_ean_trib varchar(30),
    u_trib varchar(20),
    q_trib numeric(18,4),
    v_un_trib numeric(18,6),
    v_frete numeric(18,2),
    v_seg numeric(18,2),
    v_desc numeric(18,2),
    ind_tot smallint,
    inf_ad_prod text,
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS t99013_t99008_id_idx
    ON public.t99013 (t99008_id, n_item);

CREATE TABLE IF NOT EXISTS public.t99014 (
    t99008_id bigint PRIMARY KEY REFERENCES public.t99008 (id_t99008) ON DELETE CASCADE,
    v_bc numeric(18,2),
    v_icms numeric(18,2),
    v_icms_deson numeric(18,2),
    v_fcp numeric(18,2),
    v_bcst numeric(18,2),
    v_st numeric(18,2),
    v_fcpst numeric(18,2),
    v_prod numeric(18,2),
    v_frete numeric(18,2),
    v_seg numeric(18,2),
    v_desc numeric(18,2),
    v_ii numeric(18,2),
    v_ipi numeric(18,2),
    v_ipi_devol numeric(18,2),
    v_pis numeric(18,2),
    v_cofins numeric(18,2),
    v_outro numeric(18,2),
    v_nf numeric(18,2),
    v_tot_trib numeric(18,2),
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.t99015 (
    t99008_id bigint PRIMARY KEY REFERENCES public.t99008 (id_t99008) ON DELETE CASCADE,
    c_orgao varchar(10),
    cnpj varchar(14),
    cpf varchar(11),
    ch_nfe char(44),
    dh_evento timestamptz,
    tp_evento varchar(20),
    n_seq_evento integer,
    x_evento varchar(255),
    dh_recbto timestamptz,
    n_prot varchar(20),
    versao varchar(20),
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.t99016 (
    t99008_id bigint PRIMARY KEY REFERENCES public.t99008 (id_t99008) ON DELETE CASCADE,
    versao varchar(20),
    id_evento varchar(80),
    c_orgao varchar(10),
    tp_amb smallint,
    cnpj varchar(14),
    cpf varchar(11),
    ch_nfe char(44),
    dh_evento timestamptz,
    tp_evento varchar(20),
    n_seq_evento integer,
    ver_evento varchar(20),
    desc_evento varchar(255),
    c_stat integer,
    x_motivo varchar(255),
    n_prot varchar(20),
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.t99017 (
    t99008_id bigint PRIMARY KEY REFERENCES public.t99008 (id_t99008) ON DELETE CASCADE,
    xml_det_evento text,
    json_det_evento text,
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.t99018 (
    t99008_id bigint PRIMARY KEY REFERENCES public.t99008 (id_t99008) ON DELETE CASCADE,
    versao varchar(20),
    tp_amb smallint,
    x_serv varchar(60),
    c_uf varchar(4),
    ano varchar(4),
    cnpj varchar(14),
    mod varchar(10),
    serie varchar(10),
    n_nf_ini varchar(20),
    n_nf_fin varchar(20),
    x_just text,
    ver_aplic varchar(120),
    c_stat integer,
    x_motivo varchar(255),
    dh_recbto timestamptz,
    n_prot varchar(20),
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.t99019 (
    id_t99019 bigserial PRIMARY KEY,
    t99008_id bigint UNIQUE REFERENCES public.t99008 (id_t99008) ON DELETE CASCADE,
    ch_nfe char(44),
    n_nf varchar(20),
    versao varchar(20),
    mod varchar(10),
    serie varchar(10),
    dh_emi timestamptz,
    dh_sai_ent timestamptz,
    v_nf numeric(18,2),
    tp_imp varchar(10),
    inf_cpl text,
    proc_emi varchar(20),
    ver_proc varchar(60),
    tp_emis varchar(10),
    fin_nfe varchar(10),
    nat_op varchar(255),
    ind_intermed varchar(10),
    tp_nf smallint,
    dig_val varchar(128),
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS t99019_t99008_id_idx
    ON public.t99019 (t99008_id);

CREATE INDEX IF NOT EXISTS t99019_ch_nfe_idx
    ON public.t99019 (ch_nfe);

CREATE TABLE IF NOT EXISTS public.t99020 (
    id_t99020 bigserial PRIMARY KEY,
    nome_razao_social varchar(255),
    nome_fantasia varchar(255),
    cnpj varchar(14) NOT NULL,
    endereco varchar(255),
    bairro_distrito varchar(255),
    cep varchar(12),
    municipio varchar(255),
    telefone varchar(20),
    uf varchar(4),
    pais varchar(120),
    inscricao_estadual varchar(20),
    inscricao_estadual_st varchar(20),
    inscricao_municipal varchar(20),
    municipio_ocorrencia_fato_gerador_icms varchar(255),
    cnae_fiscal varchar(20),
    codigo_regime_tributario varchar(10),
    versao integer NOT NULL,
    data_inicio timestamptz NOT NULL DEFAULT now(),
    data_fim timestamptz,
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS t99020_cnpj_idx
    ON public.t99020 (cnpj, versao DESC);

CREATE UNIQUE INDEX IF NOT EXISTS t99020_cnpj_ativo_uidx
    ON public.t99020 (cnpj)
    WHERE data_fim IS NULL;

CREATE TABLE IF NOT EXISTS public.t99021 (
    id_t99021 bigserial PRIMARY KEY,
    nome_razao_social varchar(255),
    cnpj varchar(14) NOT NULL,
    endereco varchar(255),
    bairro_distrito varchar(255),
    cep varchar(12),
    municipio varchar(255),
    telefone varchar(20),
    uf varchar(4),
    pais varchar(120),
    indicador_ie varchar(10),
    inscricao_estadual varchar(20),
    inscricao_suframa varchar(20),
    im varchar(20),
    email varchar(255),
    versao integer NOT NULL,
    data_inicio timestamptz NOT NULL DEFAULT now(),
    data_fim timestamptz,
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS t99021_cnpj_idx
    ON public.t99021 (cnpj, versao DESC);

CREATE UNIQUE INDEX IF NOT EXISTS t99021_cnpj_ativo_uidx
    ON public.t99021 (cnpj)
    WHERE data_fim IS NULL;

CREATE TABLE IF NOT EXISTS public.t99022 (
    id_t99022 bigserial PRIMARY KEY,
    modalidade_frete varchar(20),
    cnpj varchar(14) NOT NULL,
    nome_razao_social varchar(255),
    inscricao_estadual varchar(20),
    endereco_completo varchar(255),
    municipio varchar(255),
    uf varchar(4),
    volumes varchar(40),
    quantidade numeric(18,4),
    especie varchar(120),
    marca_volumes varchar(255),
    numeracao varchar(120),
    peso_liquido numeric(18,4),
    peso_bruto numeric(18,4),
    versao integer NOT NULL,
    data_inicio timestamptz NOT NULL DEFAULT now(),
    data_fim timestamptz,
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS t99022_cnpj_idx
    ON public.t99022 (cnpj, versao DESC);

CREATE UNIQUE INDEX IF NOT EXISTS t99022_cnpj_ativo_uidx
    ON public.t99022 (cnpj)
    WHERE data_fim IS NULL;

CREATE TABLE IF NOT EXISTS public.t99023 (
    id_t99023 bigserial PRIMARY KEY,
    t99019_id bigint NOT NULL REFERENCES public.t99019 (id_t99019) ON DELETE CASCADE,
    t99020_id bigint NOT NULL REFERENCES public.t99020 (id_t99020) ON DELETE CASCADE,
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS t99023_t99019_uidx
    ON public.t99023 (t99019_id);

CREATE TABLE IF NOT EXISTS public.t99024 (
    id_t99024 bigserial PRIMARY KEY,
    t99019_id bigint NOT NULL REFERENCES public.t99019 (id_t99019) ON DELETE CASCADE,
    t99021_id bigint NOT NULL REFERENCES public.t99021 (id_t99021) ON DELETE CASCADE,
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS t99024_t99019_uidx
    ON public.t99024 (t99019_id);

CREATE TABLE IF NOT EXISTS public.t99025 (
    id_t99025 bigserial PRIMARY KEY,
    t99019_id bigint NOT NULL REFERENCES public.t99019 (id_t99019) ON DELETE CASCADE,
    t99022_id bigint NOT NULL REFERENCES public.t99022 (id_t99022) ON DELETE CASCADE,
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS t99025_t99019_uidx
    ON public.t99025 (t99019_id);

CREATE TABLE IF NOT EXISTS public.t99026 (
    id_t99026 bigserial PRIMARY KEY,
    t99019_id bigint NOT NULL REFERENCES public.t99019 (id_t99019) ON DELETE CASCADE,
    num integer,
    descricao text,
    qtd numeric(18,4),
    unidade_comercial varchar(20),
    valor numeric(18,6),
    codigo_produto varchar(80),
    codigo_ncm varchar(20),
    codigo_cest varchar(20),
    indicador_escala_relevante varchar(10),
    cnpj_fabricante_mercadoria varchar(14),
    codigo_beneficio_fiscal_uf varchar(40),
    codigo_ex_tipi varchar(20),
    cfop varchar(10),
    outras_despesas_acessorias numeric(18,2),
    valor_desconto numeric(18,2),
    valor_total_frete numeric(18,2),
    valor_seguro numeric(18,2),
    indicador_composicao_valor_total_nfe varchar(10),
    codigo_ean_comercial varchar(30),
    quantidade_comercial numeric(18,4),
    codigo_ean_tributavel varchar(30),
    unidade_tributavel varchar(20),
    quantidade_tributavel numeric(18,4),
    valor_unitario_comercializacao numeric(18,6),
    valor_unitario_tributacao numeric(18,6),
    numero_pedido_compra varchar(40),
    item_pedido_compra varchar(40),
    valor_aproximado_tributos numeric(18,2),
    numero_fci varchar(40),
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS t99026_t99019_id_idx
    ON public.t99026 (t99019_id, num);

CREATE TABLE IF NOT EXISTS public.t99027 (
    id_t99027 bigserial PRIMARY KEY,
    t99026_id bigint NOT NULL REFERENCES public.t99026 (id_t99026) ON DELETE CASCADE,
    nome_imposto varchar(120),
    cst varchar(20),
    base_calculo numeric(18,2),
    aliquota numeric(10,4),
    valor numeric(18,2),
    dt_hr_atu timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS t99027_t99026_id_idx
    ON public.t99027 (t99026_id, nome_imposto);

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
