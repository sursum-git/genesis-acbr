<?php
header('Content-Type: text/html; charset=UTF-8');

$title = 'ACBrCEP';
$titleModo = isset($_GET['modo']) ? $_GET['modo'] : 'MT';

if ($titleModo == 'MT') {
    $title .= ' - MultiThread';
} else {
    $title .= ' - SingleThread';
}

$modo = isset($_GET['modo']) ? $_GET['modo'] : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $title; ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .page-header img {
            width: 64px;
            height: 64px;
            object-fit: contain;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
        }

        textarea.form-control {
            font-family: Consolas, Monaco, monospace;
            font-size: 0.92rem;
        }

        .form-label {
            font-weight: 500;
        }

        .nav-tabs .nav-link {
            font-weight: 500;
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <div class="container py-4">
        <div class="d-flex align-items-center gap-3 mb-4 page-header">
            <img src="https://svn.code.sf.net/p/acbr/code/trunk2/Exemplos/ACBrTEFD/Android/ACBr_96_96.png" alt="ACBr Logo">
            <div>
                <h1 class="h3 mb-1" id="pageTitle"><?php echo $title; ?></h1>
                <p class="text-muted mb-0">Consulta de CEP e logradouro com configuração de WebService</p>
            </div>
        </div>

        <form id="formConsulta">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <ul class="nav nav-tabs mb-4" id="consultaTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="cep-tab" data-bs-toggle="tab" data-bs-target="#cep-pane" type="button" role="tab" aria-controls="cep-pane" aria-selected="true">
                                Consulta por CEP
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="logradouro-tab" data-bs-toggle="tab" data-bs-target="#logradouro-pane" type="button" role="tab" aria-controls="logradouro-pane" aria-selected="false">
                                Consulta por Logradouro
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="config-tab" data-bs-toggle="tab" data-bs-target="#config-pane" type="button" role="tab" aria-controls="config-pane" aria-selected="false">
                                Configurações
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="consultaTabsContent">
                        <div class="tab-pane fade show active" id="cep-pane" role="tabpanel" aria-labelledby="cep-tab" tabindex="0">
                            <div class="row">
                                <div class="col-lg-4">
                                    <div class="mb-3">
                                        <label for="cepcons" class="form-label">Digite o CEP</label>
                                        <input type="text" id="cepcons" name="cepcons" class="form-control" placeholder="Ex: 60000-000">
                                    </div>

                                    <div class="d-grid">
                                        <input type="button" id="consultaCEP" value="Consultar" class="btn btn-primary">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="logradouro-pane" role="tabpanel" aria-labelledby="logradouro-tab" tabindex="0">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="tipocons" class="form-label">Tipo</label>
                                    <input type="text" id="tipocons" name="tipocons" class="form-control">
                                </div>

                                <div class="col-md-4">
                                    <label for="cidadecons" class="form-label">Cidade</label>
                                    <input type="text" id="cidadecons" name="cidadecons" class="form-control">
                                </div>

                                <div class="col-md-4">
                                    <label for="ufcons" class="form-label">UF</label>
                                    <input type="text" id="ufcons" name="ufcons" class="form-control">
                                </div>

                                <div class="col-md-6">
                                    <label for="logradourocons" class="form-label">Logradouro</label>
                                    <input type="text" id="logradourocons" name="logradourocons" class="form-control">
                                </div>

                                <div class="col-md-6">
                                    <label for="bairrocons" class="form-label">Bairro</label>
                                    <input type="text" id="bairrocons" name="bairrocons" class="form-control">
                                </div>

                                <div class="col-12">
                                    <div class="d-grid d-md-flex justify-content-md-start">
                                        <input type="button" id="consultalogradouro" value="Consultar" class="btn btn-success px-4">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="config-pane" role="tabpanel" aria-labelledby="config-tab" tabindex="0">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="webservice" class="form-label">Selecione o WebService</label>
                                    <select id="webservice" name="webservice" class="form-select">
                                        <option value="0">wsNenhum</option>
                                        <option value="1">wsBuscarCep</option>
                                        <option value="2">wsCepLivre</option>
                                        <option value="3">wsRepublicaVirtual</option>
                                        <option value="4">wsBases4you</option>
                                        <option value="5">wsRNSolucoes</option>
                                        <option value="6">wsKingHost</option>
                                        <option value="7">wsByJG</option>
                                        <option value="8">wsCorreios</option>
                                        <option value="9">wsDevMedia</option>
                                        <option value="10">wsViaCep</option>
                                        <option value="11">wsCorreiosSIGEP</option>
                                        <option value="12">wsCepAberto</option>
                                        <option value="13">wsWSCep</option>
                                        <option value="14">wsOpenCep</option>
                                        <option value="15">wsBrasilAPI</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label for="usuario" class="form-label">Usuário</label>
                                    <input type="text" id="usuario" name="usuario" class="form-control">
                                </div>

                                <div class="col-md-6">
                                    <label for="chaveacesso" class="form-label">Chave de Acesso</label>
                                    <input type="password" id="chaveacesso" name="chaveacesso" class="form-control">
                                </div>

                                <div class="col-md-6">
                                    <label for="senha" class="form-label">Senha</label>
                                    <input type="password" id="senha" name="senha" class="form-control">
                                </div>

                                <div class="col-12">
                                    <div class="d-flex flex-column flex-md-row gap-2">
                                        <input type="button" id="salvarConfiguracoes" value="Salvar Configurações" class="btn btn-warning">
                                        <input type="button" id="carregarConfiguracoes" value="Carregar Configurações" class="btn btn-outline-secondary">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="card shadow-sm mt-4">
            <div class="card-body">
                <ul class="nav nav-tabs mb-4" id="resultadoTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="detalhes-tab" data-bs-toggle="tab" data-bs-target="#detalhes-pane" type="button" role="tab" aria-controls="detalhes-pane" aria-selected="true">
                            Detalhes do Endereço
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="retorno-tab" data-bs-toggle="tab" data-bs-target="#retorno-pane" type="button" role="tab" aria-controls="retorno-pane" aria-selected="false">
                            Retorno
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="resultadoTabsContent">
                    <div class="tab-pane fade show active" id="detalhes-pane" role="tabpanel" aria-labelledby="detalhes-tab" tabindex="0">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="tipologradouro" class="form-label">Tipo de Logradouro</label>
                                <input type="text" id="tipologradouro" name="tipologradouro" class="form-control">
                            </div>

                            <div class="col-md-5">
                                <label for="logradouro" class="form-label">Logradouro</label>
                                <input type="text" id="logradouro" name="logradouro" class="form-control">
                            </div>

                            <div class="col-md-4">
                                <label for="complemento" class="form-label">Complemento</label>
                                <input type="text" id="complemento" name="complemento" class="form-control">
                            </div>

                            <div class="col-md-3">
                                <label for="bairro" class="form-label">Bairro</label>
                                <input type="text" id="bairro" name="bairro" class="form-control">
                            </div>

                            <div class="col-md-3">
                                <label for="cep" class="form-label">CEP</label>
                                <input type="text" id="cep" name="cep" class="form-control">
                            </div>

                            <div class="col-md-3">
                                <label for="ibgemunicipio" class="form-label">Município IBGE</label>
                                <input type="text" id="ibgemunicipio" name="ibgemunicipio" class="form-control">
                            </div>

                            <div class="col-md-3">
                                <label for="ibgeuf" class="form-label">UF IBGE</label>
                                <input type="text" id="ibgeuf" name="ibgeuf" class="form-control">
                            </div>

                            <div class="col-md-6">
                                <label for="municipio" class="form-label">Município</label>
                                <input type="text" id="municipio" name="municipio" class="form-control">
                            </div>

                            <div class="col-md-2">
                                <label for="uf" class="form-label">UF</label>
                                <input type="text" id="uf" name="uf" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="retorno-pane" role="tabpanel" aria-labelledby="retorno-tab" tabindex="0">
                        <div class="mb-3">
                            <label for="result" class="form-label">Resposta da operação</label>
                            <textarea id="result" rows="12" class="form-control" readonly></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('pageTitle').textContent = document.title;

        chamaAjaxEnviar({
            metodo: "carregarConfiguracoes"
        });

        $('#carregarConfiguracoes').on('click', function() {
            chamaAjaxEnviar({
                metodo: "carregarConfiguracoes"
            });
        });

        $('#salvarConfiguracoes').on('click', function() {
            const infoData = {
                metodo: "salvarConfiguracoes",
                usuario: $('#usuario').val(),
                senha: $('#senha').val(),
                chaveacesso: $('#chaveacesso').val(),
                webservice: $('#webservice').val()
            };

            chamaAjaxEnviar(infoData);
        });

        $('#consultaCEP').on('click', function() {
            chamaAjaxEnviar({
                metodo: "BuscarPorCEP",
                cepcons: $('#cepcons').val(),
                tipocons: "",
                logradourocons: "",
                bairrocons: "",
                cidadecons: "",
                ufcons: "",
                webservice: $('#webservice').val()
            });
        });

        $('#consultalogradouro').on('click', function() {
            chamaAjaxEnviar({
                metodo: "BuscarPorLogradouro",
                cepcons: "",
                tipocons: $('#tipocons').val(),
                logradourocons: $('#logradourocons').val(),
                bairrocons: $('#bairrocons').val(),
                cidadecons: $('#cidadecons').val(),
                ufcons: $('#ufcons').val(),
                webservice: $('#webservice').val()
            });
        });

        function chamaAjaxEnviar(infoData) {
            var modo = "<?php echo $modo; ?>";
            if (modo == "") {
                modo = "MT";
            }
            var basePath = window.location.pathname.replace(/\/[^\/]*$/, '');

            $.ajax({
                url: basePath + '/' + modo + '/ACBrCEPServicos' + modo + '.php',
                type: 'POST',
                data: infoData,
                success: function(response) {
                    if ((infoData.metodo === "carregarConfiguracoes") ||
                        (infoData.metodo === "Inicializar")) {
                        processaRetornoConfiguracoes(response);
                    } else if ((infoData.metodo === "BuscarPorCEP") ||
                               (infoData.metodo === "BuscarPorLogradouro")) {
                        processaRetornoConsulta(response);
                    } else {
                        processaResponseGeral(response);
                    }
                },
                error: function(error) {
                    processaResponseGeral(error);
                }
            });
        }

        function processaResponseGeral(retorno) {
            if (retorno.mensagem) {
                $('#result').val(retorno.mensagem);
            } else {
                $('#result').val('Erro: ' + JSON.stringify(retorno, null, 4));
            }
        }

        function processaRetornoConfiguracoes(response) {
            if (response.dados) {
                $('#result').val(JSON.stringify(response, null, 4));
                $('#usuario').val(response.dados.usuario || '');
                $('#senha').val(response.dados.senha || '');
                $('#chaveacesso').val(response.dados.chaveacesso || '');
                $('#webservice').val(response.dados.webservice || '0');
            } else {
                processaResponseGeral(response);
            }
        }

        function processaRetornoConsulta(response) {
            if (response.dados) {
                $('#result').val(JSON.stringify(response, null, 4));
                $('#tipologradouro').val(response.dados.tipologradouro || '');
                $('#logradouro').val(response.dados.logradouro || '');
                $('#complemento').val(response.dados.complemento || '');
                $('#bairro').val(response.dados.bairro || '');
                $('#cep').val(response.dados.cep || '');
                $('#ibgemunicipio').val(response.dados.ibgemunicipio || '');
                $('#ibgeuf').val(response.dados.ibgeuf || '');
                $('#municipio').val(response.dados.municipio || '');
                $('#uf').val(response.dados.UF || '');
            } else {
                processaResponseGeral(response);
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
