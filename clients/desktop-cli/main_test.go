package main

import (
	"bytes"
	"encoding/json"
	"io"
	"net/http"
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
