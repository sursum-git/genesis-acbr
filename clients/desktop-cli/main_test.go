package main

import (
	"bytes"
	"encoding/json"
	"io"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestLoadConfigFromIniReadsRequiredSections(t *testing.T) {
	dir := t.TempDir()
	configPath := filepath.Join(dir, "chamada.ini")
	err := os.WriteFile(configPath, []byte(`
[API]
BaseURL=http://api.local/index.php
Token=tok_123
TimeoutSeconds=45

[Requisicao]
Modulo=nfe
Operacao=consulta-cadastro

[Parametros]
AcUF=ES
AnDocumento=06013812000158
TipoDocumento=cpf_cnpj

[Arquivos]
Saida=C:\IntegracaoACBr\resposta.json
`), 0o600)
	if err != nil {
		t.Fatal(err)
	}

	cfg, err := LoadConfigFile(configPath)
	if err != nil {
		t.Fatalf("LoadConfigFile returned error: %v", err)
	}

	if cfg.API.BaseURL != "http://api.local/index.php" {
		t.Fatalf("BaseURL = %q", cfg.API.BaseURL)
	}
	if cfg.API.Token != "tok_123" {
		t.Fatalf("Token = %q", cfg.API.Token)
	}
	if cfg.API.TimeoutSeconds != 45 {
		t.Fatalf("TimeoutSeconds = %d", cfg.API.TimeoutSeconds)
	}
	if cfg.Request.Module != "nfe" || cfg.Request.Operation != "consulta-cadastro" {
		t.Fatalf("request = %#v", cfg.Request)
	}
	if cfg.Params["AcUF"] != "ES" || cfg.Params["TipoDocumento"] != "cpf_cnpj" {
		t.Fatalf("params = %#v", cfg.Params)
	}
	if cfg.Files.Output != `C:\IntegracaoACBr\resposta.json` {
		t.Fatalf("output = %q", cfg.Files.Output)
	}
}

func TestRunWithConfigCallsConsultaCadastroAndWritesResponseFile(t *testing.T) {
	var gotPath string
	var gotToken string
	var gotAccept string

	restore := replaceHTTPDoer(t, func(r *http.Request) (*http.Response, error) {
		gotPath = r.URL.RequestURI()
		gotToken = r.Header.Get("X-Api-Token")
		gotAccept = r.Header.Get("Accept")
		return httpResponse(200, `{"resultado":{"mensagem":"ok"}}`), nil
	})
	defer restore()

	dir := t.TempDir()
	outputPath := filepath.Join(dir, "resposta.json")
	configPath := filepath.Join(dir, "chamada.ini")
	writeConfig(t, configPath, `
[API]
BaseURL=http://api.test/index.php
Token=tok_abc
TimeoutSeconds=10

[Requisicao]
Modulo=nfe
Operacao=consulta-cadastro

[Parametros]
AcUF=ES
AnDocumento=06013812000158
TipoDocumento=cpf_cnpj

[Arquivos]
Saida=`+outputPath+`
`)

	stdout := &strings.Builder{}
	stderr := &strings.Builder{}
	exitCode := Run([]string{"--arq-config", configPath}, stdout, stderr)

	if exitCode != ExitOK {
		t.Fatalf("exitCode = %d stderr=%s", exitCode, stderr.String())
	}
	if stdout.Len() != 0 {
		t.Fatalf("stdout should be empty when Saida is configured, got %q", stdout.String())
	}
	if gotToken != "tok_abc" {
		t.Fatalf("X-Api-Token = %q", gotToken)
	}
	if gotAccept != "application/ld+json" {
		t.Fatalf("Accept = %q", gotAccept)
	}
	wantPath := "/index.php/nfe/consultas/consulta-cadastro?AcUF=ES&AnDocumento=06013812000158&TipoDocumento=cpf_cnpj"
	if gotPath != wantPath {
		t.Fatalf("request URI = %q, want %q", gotPath, wantPath)
	}

	var payload map[string]any
	raw, err := os.ReadFile(outputPath)
	if err != nil {
		t.Fatal(err)
	}
	if err := json.Unmarshal(raw, &payload); err != nil {
		t.Fatalf("response is not JSON: %v\n%s", err, string(raw))
	}
	if payload["ok"] != true {
		t.Fatalf("ok = %#v in %s", payload["ok"], string(raw))
	}
	if payload["status_code"].(float64) != 200 {
		t.Fatalf("status_code = %#v", payload["status_code"])
	}
}

func TestRunWithConfigLoadsQueryParamsFromEntradaFileForConsultaCadastro(t *testing.T) {
	var gotPath string

	restore := replaceHTTPDoer(t, func(r *http.Request) (*http.Response, error) {
		gotPath = r.URL.RequestURI()
		return httpResponse(200, `{"resultado":{"mensagem":"ok"}}`), nil
	})
	defer restore()

	dir := t.TempDir()
	paramsPath := filepath.Join(dir, "parametros-consulta.ini")
	writeConfig(t, paramsPath, `
[Parametros]
AcUF=ES
AnDocumento=06013812000158
TipoDocumento=cpf_cnpj
`)
	configPath := filepath.Join(dir, "chamada.ini")
	writeConfig(t, configPath, `
[API]
BaseURL=http://api.test/index.php
Token=tok_abc

[Requisicao]
Modulo=nfe
Operacao=consulta-cadastro

[Arquivos]
Entrada=parametros-consulta.ini
`)

	stdout := &strings.Builder{}
	stderr := &strings.Builder{}
	exitCode := Run([]string{"--arq-config", configPath}, stdout, stderr)

	if exitCode != ExitOK {
		t.Fatalf("exitCode = %d stderr=%s stdout=%s", exitCode, stderr.String(), stdout.String())
	}
	wantPath := "/index.php/nfe/consultas/consulta-cadastro?AcUF=ES&AnDocumento=06013812000158&TipoDocumento=cpf_cnpj"
	if gotPath != wantPath {
		t.Fatalf("request URI = %q, want %q", gotPath, wantPath)
	}
}

func TestRunWithConfigSendsXmlFileAndReturnsRequestIDForAcceptedAsyncCall(t *testing.T) {
	var gotContentType string
	var gotBody string

	restore := replaceHTTPDoer(t, func(r *http.Request) (*http.Response, error) {
		gotContentType = r.Header.Get("Content-Type")
		body, _ := ioReadAllString(r)
		gotBody = body
		return httpResponse(http.StatusAccepted, `{"request_id":"req-123","mensagem":"aceita"}`), nil
	})
	defer restore()

	dir := t.TempDir()
	xmlPath := filepath.Join(dir, "lote.xml")
	writeFile(t, xmlPath, `<NFe><infNFe Id="NFe123"/></NFe>`)
	configPath := filepath.Join(dir, "chamada.ini")
	writeConfig(t, configPath, `
[API]
BaseURL=http://api.test/index.php
Token=tok_xml

[Requisicao]
Modulo=nfe
Operacao=enviar-assincrono-xml

[Parametros]
ALote=1

[Arquivos]
Entrada=`+xmlPath+`
`)

	stdout := &strings.Builder{}
	stderr := &strings.Builder{}
	exitCode := Run([]string{"--arq-config", configPath}, stdout, stderr)

	if exitCode != ExitOK {
		t.Fatalf("exitCode = %d stderr=%s", exitCode, stderr.String())
	}
	if gotContentType != "application/xml" {
		t.Fatalf("Content-Type = %q", gotContentType)
	}
	if gotBody != `<NFe><infNFe Id="NFe123"/></NFe>` {
		t.Fatalf("body = %q", gotBody)
	}
	var payload map[string]any
	if err := json.Unmarshal([]byte(stdout.String()), &payload); err != nil {
		t.Fatalf("stdout is not JSON: %v\n%s", err, stdout.String())
	}
	if payload["request_id"] != "req-123" {
		t.Fatalf("request_id = %#v in %s", payload["request_id"], stdout.String())
	}
	if payload["status_code"].(float64) != 202 {
		t.Fatalf("status_code = %#v", payload["status_code"])
	}
}

func TestRunSuiteConfigExecutesMultipleScenarioFiles(t *testing.T) {
	var gotPaths []string

	restore := replaceHTTPDoer(t, func(r *http.Request) (*http.Response, error) {
		gotPaths = append(gotPaths, r.URL.RequestURI())
		return httpResponse(200, `{"resultado":{"mensagem":"ok"}}`), nil
	})
	defer restore()

	dir := t.TempDir()
	writeConfig(t, filepath.Join(dir, "consulta-parametros.ini"), `
[Parametros]
AcUF=ES
AnDocumento=06013812000158
TipoDocumento=cpf_cnpj
`)
	writeConfig(t, filepath.Join(dir, "consulta.ini"), `
[Requisicao]
Modulo=nfe
Operacao=consulta-cadastro

[Arquivos]
Entrada=consulta-parametros.ini
`)
	writeConfig(t, filepath.Join(dir, "status.ini"), `
[Requisicao]
Modulo=nfe
Operacao=status-servico
`)
	suitePath := filepath.Join(dir, "cenarios.ini")
	writeConfig(t, suitePath, `
[API]
BaseURL=http://api.test/index.php
Token=tok_suite

[Suite]
OutputDir=saida

[Cenarios]
consulta=consulta.ini
status=status.ini
`)

	stdout := &strings.Builder{}
	stderr := &strings.Builder{}
	exitCode := Run([]string{"--suite-config", suitePath}, stdout, stderr)

	if exitCode != ExitOK {
		t.Fatalf("exitCode = %d stderr=%s stdout=%s", exitCode, stderr.String(), stdout.String())
	}
	wantPaths := []string{
		"/index.php/nfe/consultas/consulta-cadastro?AcUF=ES&AnDocumento=06013812000158&TipoDocumento=cpf_cnpj",
		"/index.php/nfe/consultas/status-servico",
	}
	if strings.Join(gotPaths, "\n") != strings.Join(wantPaths, "\n") {
		t.Fatalf("paths = %#v", gotPaths)
	}
	for _, name := range []string{"consulta.json", "status.json", "resumo.json"} {
		if _, err := os.Stat(filepath.Join(dir, "saida", name)); err != nil {
			t.Fatalf("expected suite output %s: %v", name, err)
		}
	}
}

func TestRunReturnsConfigErrorWhenRequiredOperationIsMissing(t *testing.T) {
	dir := t.TempDir()
	configPath := filepath.Join(dir, "chamada.ini")
	writeConfig(t, configPath, `
[API]
BaseURL=http://api.local/index.php
Token=tok_123

[Requisicao]
Modulo=nfe
`)

	stdout := &strings.Builder{}
	stderr := &strings.Builder{}
	exitCode := Run([]string{"--arq-config", configPath}, stdout, stderr)

	if exitCode != ExitUsage {
		t.Fatalf("exitCode = %d stderr=%s stdout=%s", exitCode, stderr.String(), stdout.String())
	}
	if !strings.Contains(stderr.String(), "Operacao") {
		t.Fatalf("stderr should mention missing Operacao, got %q", stderr.String())
	}
}

func TestRunWithConfigCallsRequestGetUsingRequestID(t *testing.T) {
	var gotPath string

	restore := replaceHTTPDoer(t, func(r *http.Request) (*http.Response, error) {
		gotPath = r.URL.RequestURI()
		return httpResponse(200, `{"si_status_processamento":3}`), nil
	})
	defer restore()

	dir := t.TempDir()
	configPath := filepath.Join(dir, "request.ini")
	writeConfig(t, configPath, `
[API]
BaseURL=http://api.test/index.php
Token=tok_req

[Requisicao]
Modulo=request
Operacao=get

[Parametros]
RequestId=req-789
`)

	stdout := &strings.Builder{}
	stderr := &strings.Builder{}
	exitCode := Run([]string{"--arq-config", configPath}, stdout, stderr)

	if exitCode != ExitOK {
		t.Fatalf("exitCode = %d stderr=%s", exitCode, stderr.String())
	}
	if gotPath != "/index.php/requests/req-789" {
		t.Fatalf("request URI = %q", gotPath)
	}
}

func TestRunReturnsAPIErrorForUnauthorizedResponse(t *testing.T) {
	restore := replaceHTTPDoer(t, func(r *http.Request) (*http.Response, error) {
		return httpResponse(401, `{"mensagem":"token invalido"}`), nil
	})
	defer restore()

	dir := t.TempDir()
	configPath := filepath.Join(dir, "status.ini")
	writeConfig(t, configPath, `
[API]
BaseURL=http://api.test/index.php
Token=tok_bad

[Requisicao]
Modulo=nfe
Operacao=status-servico
`)

	stdout := &strings.Builder{}
	stderr := &strings.Builder{}
	exitCode := Run([]string{"--arq-config", configPath}, stdout, stderr)

	if exitCode != ExitAPI {
		t.Fatalf("exitCode = %d stderr=%s stdout=%s", exitCode, stderr.String(), stdout.String())
	}
	var payload map[string]any
	if err := json.Unmarshal([]byte(stdout.String()), &payload); err != nil {
		t.Fatalf("stdout is not JSON: %v\n%s", err, stdout.String())
	}
	if payload["ok"] != false {
		t.Fatalf("ok = %#v", payload["ok"])
	}
	if payload["error"] != "token invalido" {
		t.Fatalf("error = %#v", payload["error"])
	}
}

func TestSharedLibraryRunConfigReturnsExitCodeStdoutAndStderrAsJSON(t *testing.T) {
	restore := replaceHTTPDoer(t, func(r *http.Request) (*http.Response, error) {
		return httpResponse(200, `{"resultado":{"mensagem":"ok"}}`), nil
	})
	defer restore()

	dir := t.TempDir()
	configPath := filepath.Join(dir, "status.ini")
	writeConfig(t, configPath, `
[API]
BaseURL=http://api.test/index.php
Token=tok_shared

[Requisicao]
Modulo=nfe
Operacao=status-servico
`)

	raw := RunConfigForSharedLibrary(configPath)

	var payload map[string]any
	if err := json.Unmarshal([]byte(raw), &payload); err != nil {
		t.Fatalf("shared result is not JSON: %v\n%s", err, raw)
	}
	if payload["exit_code"].(float64) != 0 {
		t.Fatalf("exit_code = %#v in %s", payload["exit_code"], raw)
	}
	if !strings.Contains(payload["stdout"].(string), `"ok": true`) {
		t.Fatalf("stdout does not contain CLI JSON success: %s", payload["stdout"])
	}
	if payload["stderr"].(string) != "" {
		t.Fatalf("stderr = %q", payload["stderr"])
	}
}

func writeConfig(t *testing.T, path string, content string) {
	t.Helper()
	writeFile(t, path, strings.TrimSpace(content)+"\n")
}

func writeFile(t *testing.T, path string, content string) {
	t.Helper()
	if err := os.WriteFile(path, []byte(content), 0o600); err != nil {
		t.Fatal(err)
	}
}

func ioReadAllString(r *http.Request) (string, error) {
	body, err := io.ReadAll(r.Body)
	if err != nil {
		return "", err
	}
	return string(body), nil
}

func replaceHTTPDoer(t *testing.T, fake func(*http.Request) (*http.Response, error)) func() {
	t.Helper()
	original := httpDo
	httpDo = fake
	return func() {
		httpDo = original
	}
}

func httpResponse(statusCode int, body string) *http.Response {
	return &http.Response{
		StatusCode: statusCode,
		Body:       io.NopCloser(bytes.NewBufferString(body)),
		Header:     http.Header{"Content-Type": []string{"application/json"}},
	}
}

func TestEnvironmentHeaderFromConfig(t *testing.T) {
	for _, value := range []string{"1", "2"} {
		filename := filepath.Join(t.TempDir(), "call.ini")
		os.WriteFile(filename, []byte("[API]\nBaseURL=http://api.local/index.php\nToken=fixture\nAmbiente="+value+"\n[Requisicao]\nModulo=nfe\nOperacao=status-servico\n"), 0600)
		cfg, err := LoadConfigFile(filename)
		if err != nil {
			t.Fatal(err)
		}
		spec, err := resolveOperation(cfg.Request.Module, cfg.Request.Operation)
		if err != nil {
			t.Fatal(err)
		}
		req, err := buildHTTPRequest(cfg, spec)
		if err != nil {
			t.Fatal(err)
		}
		if req.Header.Get("X-ACBr-Ambiente") != value {
			t.Fatalf("missing environment header %s", value)
		}
		if req.URL.Query().Has("Ambiente") {
			t.Fatal("environment must be a header")
		}
	}
}

func TestEnvironmentValidationAndSuiteInheritance(t *testing.T) {
	cfg := Config{API: APIConfig{BaseURL: "http://api.local", Token: "fixture"}, Request: RequestConfig{Module: "nfe", Operation: "status-servico"}}
	for _, value := range []string{"0", "3", "homologacao"} {
		cfg.API.Ambiente = value
		if cfg.Validate() == nil {
			t.Fatalf("invalid environment %q accepted", value)
		}
	}
	cfg.API.Ambiente = ""
	inheritAPI(&cfg, APIConfig{Ambiente: "2"})
	if cfg.API.Ambiente != "2" {
		t.Fatal("suite environment not inherited")
	}
	inheritAPI(&cfg, APIConfig{Ambiente: "1"})
	if cfg.API.Ambiente != "2" {
		t.Fatal("explicit environment overwritten")
	}
	filename := filepath.Join(t.TempDir(), "suite.ini")
	os.WriteFile(filename, []byte("[API]\nAmbiente=2\n[Cenarios]\nexample=call.ini\n"), 0600)
	suite, err := LoadSuiteConfig(filename)
	if err != nil || suite.API.Ambiente != "2" {
		t.Fatalf("suite environment: %+v %v", suite, err)
	}
}

func TestDistributedExamplesSelectHomologation(t *testing.T) {
	cfg, err := LoadConfigFile("chamada.ini")
	if err != nil || cfg.API.Ambiente != "2" {
		t.Fatalf("default example must select homologation: %+v %v", cfg.API, err)
	}
	suite, err := LoadSuiteConfig("windows-app/suites/nfe-cenarios.ini")
	if err != nil || suite.API.Ambiente != "2" {
		t.Fatalf("suite example must select homologation: %+v %v", suite.API, err)
	}
	cfg, err = LoadConfigFile("linux-example/chamada.ini")
	if err != nil || cfg.API.Ambiente != "2" {
		t.Fatalf("shared library example must select homologation: %+v %v", cfg.API, err)
	}
}

func TestCLIAndSharedLibraryEnvironmentOverHTTP(t *testing.T) {
	for _, shared := range []bool{false, true} {
		for _, environment := range []string{"1", "2", "", "0", "3"} {
			t.Run(environment+map[bool]string{true: "-shared", false: "-cli"}[shared], func(t *testing.T) {
				count := 0
				xml := `<NFe><infNFe><ide><tpAmb>` + environment + `</tpAmb></ide></infNFe></NFe>`
				server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
					count++
					if got := r.Header.Get("X-ACBr-Ambiente"); got != environment {
						t.Errorf("header=%q want %q", got, environment)
					}
					if r.URL.Query().Has("Ambiente") {
						t.Error("environment leaked into query")
					}
					if r.Header.Get("X-Api-Token") != "fixture" || r.Header.Get("Content-Type") != "application/xml" {
						t.Error("missing auth or XML content type")
					}
					raw, _ := io.ReadAll(r.Body)
					if string(raw) != xml {
						t.Error("XML body changed")
					}
					w.Header().Set("Content-Type", "application/ld+json")
					w.WriteHeader(202)
					io.WriteString(w, `{"request_id":"req-environment"}`)
				}))
				defer server.Close()
				dir := t.TempDir()
				writeFile(t, filepath.Join(dir, "nfe.xml"), xml)
				file := filepath.Join(dir, "call.ini")
				writeConfig(t, file, "[API]\nBaseURL="+server.URL+"/index.php\nToken=fixture\nAmbiente="+environment+"\n[Requisicao]\nModulo=nfe\nOperacao=enviar-sincrono-xml\n[Arquivos]\nEntrada=nfe.xml")
				stdout, stderr := &bytes.Buffer{}, &bytes.Buffer{}
				code := 0
				if shared {
					var result sharedRunResult
					if err := json.Unmarshal([]byte(RunConfigForSharedLibrary(file)), &result); err != nil {
						t.Fatal(err)
					}
					code = result.ExitCode
					stdout.WriteString(result.Stdout)
					stderr.WriteString(result.Stderr)
				} else {
					code = Run([]string{"--arq-config", file}, stdout, stderr)
				}
				if environment == "0" || environment == "3" {
					if code != ExitUsage || count != 0 {
						t.Fatalf("invalid environment: code %d HTTP requests %d", code, count)
					}
				} else if code != ExitOK || count != 1 || !strings.Contains(stdout.String(), "req-environment") {
					t.Fatalf("code %d HTTP requests %d stdout=%s stderr=%s", code, count, stdout, stderr)
				}
			})
		}
	}
}

func TestSuiteEnvironmentOverHTTP(t *testing.T) {
	var got []string
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		got = append(got, r.Header.Get("X-ACBr-Ambiente"))
		io.WriteString(w, `{"mensagem":"ok"}`)
	}))
	defer server.Close()
	dir := t.TempDir()
	writeConfig(t, filepath.Join(dir, "inherited.ini"), "[Requisicao]\nModulo=nfe\nOperacao=status-servico")
	writeConfig(t, filepath.Join(dir, "explicit.ini"), "[API]\nAmbiente=1\n[Requisicao]\nModulo=nfe\nOperacao=status-servico")
	file := filepath.Join(dir, "suite.ini")
	writeConfig(t, file, "[API]\nBaseURL="+server.URL+"\nToken=fixture\nAmbiente=2\n[Cenarios]\nhom=inherited.ini\nprod=explicit.ini")
	stdout, stderr := &bytes.Buffer{}, &bytes.Buffer{}
	code := Run([]string{"--suite-config", file}, stdout, stderr)
	if code != ExitOK || strings.Join(got, ",") != "2,1" {
		t.Fatalf("code %d environments %v stderr=%s", code, got, stderr)
	}
}
