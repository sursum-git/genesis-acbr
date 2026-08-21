package main

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"os"
	"path"
	"path/filepath"
	"strconv"
	"strings"
	"time"
)

const (
	ExitOK       = 0
	ExitUsage    = 1
	ExitAPI      = 2
	ExitNetwork  = 3
	ExitFile     = 4
	defaultTOSec = 60
)

type Config struct {
	API       APIConfig
	Request   RequestConfig
	Params    map[string]string
	Files     FileConfig
	SourceDir string
}

type APIConfig struct {
	BaseURL        string
	Token          string
	TimeoutSeconds int
}

type RequestConfig struct {
	Module    string
	Operation string
}

type FileConfig struct {
	Input  string
	Output string
}

type operationSpec struct {
	Method      string
	Path        string
	ContentType string
	RawInput    bool
}

type cliResponse struct {
	OK         bool   `json:"ok"`
	StatusCode int    `json:"status_code,omitempty"`
	RequestID  string `json:"request_id,omitempty"`
	Data       any    `json:"data,omitempty"`
	Error      string `json:"error,omitempty"`
}

type SuiteConfig struct {
	API       APIConfig
	OutputDir string
	Scenarios []ScenarioConfig
	SourceDir string
}

type ScenarioConfig struct {
	Name string
	Path string
}

type scenarioResult struct {
	Name     string `json:"name"`
	Path     string `json:"path"`
	Output   string `json:"output,omitempty"`
	ExitCode int    `json:"exit_code"`
	OK       bool   `json:"ok"`
}

type suiteResult struct {
	OK        bool             `json:"ok"`
	Scenarios []scenarioResult `json:"scenarios"`
}

func main() {
	os.Exit(Run(os.Args[1:], os.Stdout, os.Stderr))
}

var httpDo = func(req *http.Request) (*http.Response, error) {
	return http.DefaultClient.Do(req)
}

func Run(args []string, stdout io.Writer, stderr io.Writer) int {
	fs := flag.NewFlagSet("acbr-api-cli", flag.ContinueOnError)
	fs.SetOutput(stderr)

	configPath := fs.String("arq-config", "", "arquivo INI da chamada")
	suitePath := fs.String("suite-config", "", "arquivo INI com lista de cenarios")
	baseURL := fs.String("base-url", "", "sobrescreve BaseURL do arquivo")
	token := fs.String("token", "", "sobrescreve Token do arquivo")
	timeout := fs.Int("timeout", 0, "sobrescreve TimeoutSeconds do arquivo")

	if err := fs.Parse(args); err != nil {
		return ExitUsage
	}
	if strings.TrimSpace(*suitePath) != "" {
		return runSuite(*suitePath, *baseURL, *token, *timeout, stdout, stderr)
	}
	if strings.TrimSpace(*configPath) == "" {
		fmt.Fprintln(stderr, "informe --arq-config ou --suite-config")
		return ExitUsage
	}

	cfg, err := LoadConfigFile(*configPath)
	if err != nil {
		fmt.Fprintln(stderr, err)
		if errors.Is(err, errReadFile) {
			return ExitFile
		}
		return ExitUsage
	}
	if *baseURL != "" {
		cfg.API.BaseURL = *baseURL
	}
	if *token != "" {
		cfg.API.Token = *token
	}
	if *timeout > 0 {
		cfg.API.TimeoutSeconds = *timeout
	}

	if err := cfg.Validate(); err != nil {
		fmt.Fprintln(stderr, err)
		return ExitUsage
	}

	return runSingleConfig(cfg, stdout, stderr)
}

func runSingleConfig(cfg Config, stdout io.Writer, stderr io.Writer) int {
	resp, exitCode := Execute(cfg)
	raw, marshalErr := json.MarshalIndent(resp, "", "  ")
	if marshalErr != nil {
		fmt.Fprintln(stderr, marshalErr)
		return ExitUsage
	}
	raw = append(raw, '\n')

	if cfg.Files.Output != "" {
		outputPath := resolveRelativePath(cfg.SourceDir, cfg.Files.Output)
		if err := os.MkdirAll(filepath.Dir(outputPath), 0o755); err != nil {
			fmt.Fprintln(stderr, err)
			return ExitFile
		}
		if err := os.WriteFile(outputPath, raw, 0o600); err != nil {
			fmt.Fprintln(stderr, err)
			return ExitFile
		}
		return exitCode
	}

	if _, err := stdout.Write(raw); err != nil {
		fmt.Fprintln(stderr, err)
		return ExitFile
	}
	return exitCode
}

var errReadFile = errors.New("erro de leitura de arquivo")

func LoadConfigFile(filename string) (Config, error) {
	raw, err := os.ReadFile(filename)
	if err != nil {
		return Config{}, fmt.Errorf("%w: %s: %v", errReadFile, filename, err)
	}

	cfg := Config{
		API: APIConfig{
			TimeoutSeconds: defaultTOSec,
		},
		Params:    map[string]string{},
		SourceDir: filepath.Dir(filename),
	}

	section := ""
	lines := strings.Split(string(raw), "\n")
	for lineNumber, original := range lines {
		line := strings.TrimSpace(original)
		if line == "" || strings.HasPrefix(line, "#") || strings.HasPrefix(line, ";") {
			continue
		}
		if strings.HasPrefix(line, "[") && strings.HasSuffix(line, "]") {
			section = strings.ToLower(strings.TrimSpace(strings.TrimSuffix(strings.TrimPrefix(line, "["), "]")))
			continue
		}
		key, value, ok := strings.Cut(line, "=")
		if !ok {
			return Config{}, fmt.Errorf("linha %d invalida: esperado chave=valor", lineNumber+1)
		}
		key = strings.TrimSpace(key)
		value = strings.TrimSpace(value)

		switch section {
		case "api":
			switch strings.ToLower(key) {
			case "baseurl":
				cfg.API.BaseURL = value
			case "token":
				cfg.API.Token = value
			case "timeoutseconds":
				n, err := strconv.Atoi(value)
				if err != nil || n <= 0 {
					return Config{}, fmt.Errorf("TimeoutSeconds invalido: %q", value)
				}
				cfg.API.TimeoutSeconds = n
			}
		case "requisicao":
			switch strings.ToLower(key) {
			case "modulo":
				cfg.Request.Module = strings.ToLower(value)
			case "operacao":
				cfg.Request.Operation = strings.ToLower(value)
			}
		case "parametros":
			cfg.Params[key] = value
		case "arquivos":
			switch strings.ToLower(key) {
			case "entrada":
				cfg.Files.Input = value
			case "saida":
				cfg.Files.Output = value
			}
		default:
			return Config{}, fmt.Errorf("linha %d fora de uma secao conhecida", lineNumber+1)
		}
	}

	return cfg, nil
}

func LoadParamFile(filename string) (map[string]string, error) {
	raw, err := os.ReadFile(filename)
	if err != nil {
		return nil, fmt.Errorf("%w: %s: %v", errReadFile, filename, err)
	}

	params := map[string]string{}
	section := "parametros"
	lines := strings.Split(string(raw), "\n")
	for lineNumber, original := range lines {
		line := strings.TrimSpace(original)
		if line == "" || strings.HasPrefix(line, "#") || strings.HasPrefix(line, ";") {
			continue
		}
		if strings.HasPrefix(line, "[") && strings.HasSuffix(line, "]") {
			section = strings.ToLower(strings.TrimSpace(strings.TrimSuffix(strings.TrimPrefix(line, "["), "]")))
			continue
		}
		key, value, ok := strings.Cut(line, "=")
		if !ok {
			return nil, fmt.Errorf("linha %d invalida: esperado chave=valor", lineNumber+1)
		}
		if section != "parametros" {
			continue
		}
		params[strings.TrimSpace(key)] = strings.TrimSpace(value)
	}
	return params, nil
}

func LoadSuiteConfig(filename string) (SuiteConfig, error) {
	raw, err := os.ReadFile(filename)
	if err != nil {
		return SuiteConfig{}, fmt.Errorf("%w: %s: %v", errReadFile, filename, err)
	}

	suite := SuiteConfig{
		API: APIConfig{
			TimeoutSeconds: defaultTOSec,
		},
		SourceDir: filepath.Dir(filename),
	}

	section := ""
	lines := strings.Split(string(raw), "\n")
	for lineNumber, original := range lines {
		line := strings.TrimSpace(original)
		if line == "" || strings.HasPrefix(line, "#") || strings.HasPrefix(line, ";") {
			continue
		}
		if strings.HasPrefix(line, "[") && strings.HasSuffix(line, "]") {
			section = strings.ToLower(strings.TrimSpace(strings.TrimSuffix(strings.TrimPrefix(line, "["), "]")))
			continue
		}
		key, value, ok := strings.Cut(line, "=")
		if !ok {
			return SuiteConfig{}, fmt.Errorf("linha %d invalida: esperado chave=valor", lineNumber+1)
		}
		key = strings.TrimSpace(key)
		value = strings.TrimSpace(value)

		switch section {
		case "api":
			switch strings.ToLower(key) {
			case "baseurl":
				suite.API.BaseURL = value
			case "token":
				suite.API.Token = value
			case "timeoutseconds":
				n, err := strconv.Atoi(value)
				if err != nil || n <= 0 {
					return SuiteConfig{}, fmt.Errorf("TimeoutSeconds invalido: %q", value)
				}
				suite.API.TimeoutSeconds = n
			}
		case "suite":
			if strings.EqualFold(key, "OutputDir") {
				suite.OutputDir = value
			}
		case "cenarios":
			suite.Scenarios = append(suite.Scenarios, ScenarioConfig{Name: key, Path: value})
		default:
			return SuiteConfig{}, fmt.Errorf("linha %d fora de uma secao conhecida", lineNumber+1)
		}
	}
	if len(suite.Scenarios) == 0 {
		return SuiteConfig{}, errors.New("Suite sem cenarios")
	}
	return suite, nil
}

func (cfg Config) Validate() error {
	if strings.TrimSpace(cfg.API.BaseURL) == "" {
		return errors.New("API.BaseURL e obrigatorio")
	}
	if strings.TrimSpace(cfg.API.Token) == "" {
		return errors.New("API.Token e obrigatorio")
	}
	if strings.TrimSpace(cfg.Request.Module) == "" {
		return errors.New("Requisicao.Modulo e obrigatorio")
	}
	if strings.TrimSpace(cfg.Request.Operation) == "" {
		return errors.New("Requisicao.Operacao e obrigatorio")
	}
	_, err := resolveOperation(cfg.Request.Module, cfg.Request.Operation)
	return err
}

func Execute(cfg Config) (cliResponse, int) {
	spec, err := resolveOperation(cfg.Request.Module, cfg.Request.Operation)
	if err != nil {
		return cliResponse{OK: false, Error: err.Error()}, ExitUsage
	}
	if err := loadInputParamsIfNeeded(&cfg, spec); err != nil {
		code := ExitUsage
		if errors.Is(err, errReadFile) {
			code = ExitFile
		}
		return cliResponse{OK: false, Error: err.Error()}, code
	}

	req, err := buildHTTPRequest(cfg, spec)
	if err != nil {
		code := ExitUsage
		if errors.Is(err, errReadFile) {
			code = ExitFile
		}
		return cliResponse{OK: false, Error: err.Error()}, code
	}

	ctx, cancel := context.WithTimeout(req.Context(), time.Duration(cfg.API.TimeoutSeconds)*time.Second)
	defer cancel()
	req = req.WithContext(ctx)

	httpResp, err := httpDo(req)
	if err != nil {
		return cliResponse{OK: false, Error: err.Error()}, ExitNetwork
	}
	defer httpResp.Body.Close()

	body, err := io.ReadAll(httpResp.Body)
	if err != nil {
		return cliResponse{OK: false, StatusCode: httpResp.StatusCode, Error: err.Error()}, ExitNetwork
	}

	data := decodeResponseBody(body)
	out := cliResponse{
		OK:         httpResp.StatusCode >= 200 && httpResp.StatusCode < 300,
		StatusCode: httpResp.StatusCode,
		Data:       data,
	}
	if requestID := extractRequestID(data); requestID != "" {
		out.RequestID = requestID
	}
	if !out.OK {
		out.Error = extractError(data, string(body))
		return out, ExitAPI
	}
	return out, ExitOK
}

func loadInputParamsIfNeeded(cfg *Config, spec operationSpec) error {
	if spec.RawInput || cfg.Files.Input == "" {
		return nil
	}
	paramsPath := resolveRelativePath(cfg.SourceDir, cfg.Files.Input)
	fileParams, err := LoadParamFile(paramsPath)
	if err != nil {
		return err
	}
	for key, value := range fileParams {
		if _, exists := cfg.Params[key]; !exists {
			cfg.Params[key] = value
		}
	}
	return nil
}

func buildHTTPRequest(cfg Config, spec operationSpec) (*http.Request, error) {
	base, err := url.Parse(strings.TrimRight(cfg.API.BaseURL, "/"))
	if err != nil {
		return nil, err
	}
	resolvedPath, consumedParams, err := resolvePathParams(spec.Path, cfg.Params)
	if err != nil {
		return nil, err
	}
	base.Path = path.Join(base.Path, resolvedPath)

	query := base.Query()
	for key, value := range cfg.Params {
		if consumedParams[key] {
			continue
		}
		if value != "" {
			query.Set(key, value)
		}
	}
	base.RawQuery = query.Encode()

	var body io.Reader
	if spec.RawInput {
		if cfg.Files.Input == "" {
			return nil, errors.New("Arquivos.Entrada e obrigatorio para esta operacao")
		}
		inputPath := resolveRelativePath(cfg.SourceDir, cfg.Files.Input)
		raw, err := os.ReadFile(inputPath)
		if err != nil {
			return nil, fmt.Errorf("%w: %s: %v", errReadFile, inputPath, err)
		}
		body = bytes.NewReader(raw)
	}

	req, err := http.NewRequest(spec.Method, base.String(), body)
	if err != nil {
		return nil, err
	}
	req.Header.Set("X-Api-Token", cfg.API.Token)
	req.Header.Set("Accept", "application/ld+json")
	if spec.ContentType != "" {
		req.Header.Set("Content-Type", spec.ContentType)
	}
	return req, nil
}

func runSuite(filename string, baseURLOverride string, tokenOverride string, timeoutOverride int, stdout io.Writer, stderr io.Writer) int {
	suite, err := LoadSuiteConfig(filename)
	if err != nil {
		fmt.Fprintln(stderr, err)
		if errors.Is(err, errReadFile) {
			return ExitFile
		}
		return ExitUsage
	}
	if baseURLOverride != "" {
		suite.API.BaseURL = baseURLOverride
	}
	if tokenOverride != "" {
		suite.API.Token = tokenOverride
	}
	if timeoutOverride > 0 {
		suite.API.TimeoutSeconds = timeoutOverride
	}

	outputDir := resolveRelativePath(suite.SourceDir, suite.OutputDir)
	if outputDir == "" {
		outputDir = suite.SourceDir
	}
	if err := os.MkdirAll(outputDir, 0o755); err != nil {
		fmt.Fprintln(stderr, err)
		return ExitFile
	}

	results := suiteResult{OK: true}
	exitCode := ExitOK
	for _, scenario := range suite.Scenarios {
		scenarioPath := resolveRelativePath(suite.SourceDir, scenario.Path)
		cfg, err := LoadConfigFile(scenarioPath)
		if err != nil {
			results.OK = false
			results.Scenarios = append(results.Scenarios, scenarioResult{Name: scenario.Name, Path: scenario.Path, ExitCode: ExitFile, OK: false})
			exitCode = ExitFile
			continue
		}
		inheritAPI(&cfg, suite.API)
		if cfg.Files.Output == "" {
			cfg.Files.Output = filepath.Join(outputDir, scenario.Name+".json")
		}

		resp, scenarioExit := Execute(cfg)
		raw, marshalErr := json.MarshalIndent(resp, "", "  ")
		if marshalErr != nil {
			results.OK = false
			results.Scenarios = append(results.Scenarios, scenarioResult{Name: scenario.Name, Path: scenario.Path, ExitCode: ExitUsage, OK: false})
			exitCode = ExitUsage
			continue
		}
		raw = append(raw, '\n')
		outputPath := resolveRelativePath(cfg.SourceDir, cfg.Files.Output)
		if err := os.MkdirAll(filepath.Dir(outputPath), 0o755); err != nil {
			fmt.Fprintln(stderr, err)
			return ExitFile
		}
		if err := os.WriteFile(outputPath, raw, 0o600); err != nil {
			fmt.Fprintln(stderr, err)
			return ExitFile
		}

		ok := scenarioExit == ExitOK
		if !ok {
			results.OK = false
			if exitCode == ExitOK {
				exitCode = scenarioExit
			}
		}
		results.Scenarios = append(results.Scenarios, scenarioResult{Name: scenario.Name, Path: scenario.Path, Output: outputPath, ExitCode: scenarioExit, OK: ok})
	}

	summary, err := json.MarshalIndent(results, "", "  ")
	if err != nil {
		fmt.Fprintln(stderr, err)
		return ExitUsage
	}
	summary = append(summary, '\n')
	if err := os.WriteFile(filepath.Join(outputDir, "resumo.json"), summary, 0o600); err != nil {
		fmt.Fprintln(stderr, err)
		return ExitFile
	}
	if _, err := stdout.Write(summary); err != nil {
		fmt.Fprintln(stderr, err)
		return ExitFile
	}
	return exitCode
}

func inheritAPI(cfg *Config, api APIConfig) {
	if cfg.API.BaseURL == "" {
		cfg.API.BaseURL = api.BaseURL
	}
	if cfg.API.Token == "" {
		cfg.API.Token = api.Token
	}
	if cfg.API.TimeoutSeconds == defaultTOSec && api.TimeoutSeconds != defaultTOSec {
		cfg.API.TimeoutSeconds = api.TimeoutSeconds
	}
}

func resolveRelativePath(baseDir string, filename string) string {
	if filename == "" || filepath.IsAbs(filename) || baseDir == "" {
		return filename
	}
	return filepath.Join(baseDir, filename)
}

func resolvePathParams(specPath string, params map[string]string) (string, map[string]bool, error) {
	consumed := map[string]bool{}
	if !strings.Contains(specPath, "{RequestId}") {
		return specPath, consumed, nil
	}
	requestID := strings.TrimSpace(params["RequestId"])
	if requestID == "" {
		return "", nil, errors.New("Parametros.RequestId e obrigatorio para esta operacao")
	}
	consumed["RequestId"] = true
	return strings.ReplaceAll(specPath, "{RequestId}", url.PathEscape(requestID)), consumed, nil
}

func resolveOperation(module string, operation string) (operationSpec, error) {
	key := strings.ToLower(strings.TrimSpace(module)) + "." + strings.ToLower(strings.TrimSpace(operation))
	ops := map[string]operationSpec{
		"nfe.status-servico":        {Method: http.MethodGet, Path: "/nfe/consultas/status-servico"},
		"nfe.consulta-cadastro":     {Method: http.MethodGet, Path: "/nfe/consultas/consulta-cadastro"},
		"nfe.consultar-chave":       {Method: http.MethodGet, Path: "/nfe/consultas/consultar-com-chave"},
		"nfe.distribuicao-chave":    {Method: http.MethodGet, Path: "/nfe/distribuicao-dfe/por-chave"},
		"nfe.distribuicao-nsu":      {Method: http.MethodGet, Path: "/nfe/distribuicao-dfe/por-nsu"},
		"nfe.distribuicao-ult-nsu":  {Method: http.MethodGet, Path: "/nfe/distribuicao-dfe/por-ult-nsu"},
		"nfe.enviar-sincrono-xml":   {Method: http.MethodPost, Path: "/nfe/envio/enviar-sincrono-xml", ContentType: "application/xml", RawInput: true},
		"nfe.enviar-assincrono-xml": {Method: http.MethodPost, Path: "/nfe/envio/enviar-assincrono-xml", ContentType: "application/xml", RawInput: true},
		"nfe.enviar-sincrono-ini":   {Method: http.MethodPost, Path: "/nfe/envio/enviar-sincrono-ini", ContentType: "text/plain", RawInput: true},
		"nfe.enviar-assincrono-ini": {Method: http.MethodPost, Path: "/nfe/envio/enviar-assincrono-ini", ContentType: "text/plain", RawInput: true},
		"nfe.validar-regras":        {Method: http.MethodPost, Path: "/nfe/envio/validar-regras-negocio", ContentType: "application/xml", RawInput: true},
		"nfe.imprimir-pdf":          {Method: http.MethodPost, Path: "/nfe/envio/imprimir-pdf", ContentType: "application/xml", RawInput: true},
		"nfe.inutilizar":            {Method: http.MethodGet, Path: "/nfe/inutilizacao/inutilizar"},
		"request.get":               {Method: http.MethodGet, Path: requestPath(operation)},
		"request.request-get":       {Method: http.MethodGet, Path: requestPath(operation)},
		"nfe.request-get":           {Method: http.MethodGet, Path: requestPath(operation)},
	}
	spec, ok := ops[key]
	if !ok {
		return operationSpec{}, fmt.Errorf("operacao nao suportada: %s.%s", module, operation)
	}
	return spec, nil
}

func requestPath(operation string) string {
	_ = operation
	return "/requests/{RequestId}"
}

func decodeResponseBody(body []byte) any {
	var data any
	if len(strings.TrimSpace(string(body))) == 0 {
		return nil
	}
	if err := json.Unmarshal(body, &data); err == nil {
		return data
	}
	return string(body)
}

func extractRequestID(data any) string {
	obj, ok := data.(map[string]any)
	if !ok {
		return ""
	}
	if value, ok := obj["request_id"].(string); ok {
		return value
	}
	return ""
}

func extractError(data any, fallback string) string {
	obj, ok := data.(map[string]any)
	if ok {
		for _, key := range []string{"mensagem", "message", "error"} {
			if value, ok := obj[key].(string); ok && value != "" {
				return value
			}
		}
	}
	if strings.TrimSpace(fallback) != "" {
		return strings.TrimSpace(fallback)
	}
	return "erro retornado pela API"
}
