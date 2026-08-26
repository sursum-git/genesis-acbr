package main

import (
	"bytes"
	"encoding/json"
)

type sharedRunResult struct {
	ExitCode int    `json:"exit_code"`
	Stdout   string `json:"stdout"`
	Stderr   string `json:"stderr"`
}

func RunConfigForSharedLibrary(configPath string) string {
	stdout := &bytes.Buffer{}
	stderr := &bytes.Buffer{}
	exitCode := Run([]string{"--arq-config", configPath}, stdout, stderr)

	raw, err := json.Marshal(sharedRunResult{
		ExitCode: exitCode,
		Stdout:   stdout.String(),
		Stderr:   stderr.String(),
	})
	if err != nil {
		return `{"exit_code":1,"stdout":"","stderr":"erro ao serializar retorno da biblioteca"}`
	}
	return string(raw)
}
