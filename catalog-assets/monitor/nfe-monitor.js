(function () {
    const root = document.getElementById('nfe-monitor-app');
    if (!root || typeof window.kendo === 'undefined' || typeof window.jQuery === 'undefined') {
        return;
    }

    const $ = window.jQuery;
    const dataUrl = root.dataset.dataUrl || '';
    const detailUrlTemplate = root.dataset.detailUrlTemplate || '';
    const outputUrlTemplate = root.dataset.outputUrlTemplate || '';
    const $grid = $('#nfe-monitor-grid');
    const $window = $('#nfe-monitor-window');

    function buildUrl(template, requestId) {
        return template.replace('__REQUEST_ID__', encodeURIComponent(requestId));
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function occurrenceBadge(occurrence) {
        if (occurrence === 'erro') {
            return 'danger';
        }
        if (occurrence === 'processando') {
            return 'warning';
        }

        return 'success';
    }

    const detailWindow = $window.kendoWindow({
        width: 760,
        modal: true,
        visible: false,
        resizable: true,
        title: 'Detalhe do envio',
    }).data('kendoWindow');

    function renderDetail(detail) {
        const outputUrl = buildUrl(outputUrlTemplate, detail.request_id || '');
        const errorMessage = detail.erro_execucao || detail.erro_extracao || 'nenhum';
        const responseBody = detail.t_corpo_resposta || 'sem resposta registrada';

        return '' +
            '<div class="monitor-detail">' +
                '<div class="monitor-detail-header">' +
                    '<div>' +
                        '<h3 class="h5 mb-1">Nota ' + escapeHtml(detail.numero_nota || 'sem numero estruturado') + '</h3>' +
                        '<p class="text-muted mb-0">' + escapeHtml(detail.cliente || 'Cliente nao identificado') + '</p>' +
                    '</div>' +
                    '<div class="d-flex flex-wrap gap-2">' +
                        '<span class="badge text-bg-primary">' + escapeHtml(detail.situacao || '-') + '</span>' +
                        '<span class="badge text-bg-' + occurrenceBadge(detail.ocorrencia) + '">' + escapeHtml(detail.ocorrencia || '-') + '</span>' +
                    '</div>' +
                '</div>' +
                '<dl class="row mb-3">' +
                    '<dt class="col-sm-4">Request ID</dt><dd class="col-sm-8"><code>' + escapeHtml(detail.request_id || '') + '</code></dd>' +
                    '<dt class="col-sm-4">Valor</dt><dd class="col-sm-8">' + escapeHtml(detail.valor_total || '-') + '</dd>' +
                    '<dt class="col-sm-4">Chave</dt><dd class="col-sm-8 monitor-break">' + escapeHtml(detail.chave_nfe || '-') + '</dd>' +
                    '<dt class="col-sm-4">Erro</dt><dd class="col-sm-8">' + escapeHtml(errorMessage) + '</dd>' +
                '</dl>' +
                '<pre class="monitor-pre">' + escapeHtml(responseBody) + '</pre>' +
                '<div class="monitor-detail-actions">' +
                    '<a class="btn btn-primary" href="' + outputUrl + '">Abrir tela de saida</a>' +
                '</div>' +
            '</div>';
    }

    function openDetail(requestId) {
        if (!requestId) {
            return;
        }

        $.getJSON(buildUrl(detailUrlTemplate, requestId))
            .done(function (detail) {
                detailWindow.content(renderDetail(detail));
                detailWindow.center().open();
            })
            .fail(function () {
                detailWindow.content('<div class="alert alert-danger mb-0">Nao foi possivel carregar o detalhe deste envio.</div>');
                detailWindow.center().open();
            });
    }

    $grid.kendoGrid({
        dataSource: {
            transport: {
                read: {
                    url: dataUrl,
                    dataType: 'json'
                }
            },
            schema: {
                data: 'data'
            },
            pageSize: 100
        },
        sortable: true,
        pageable: false,
        scrollable: true,
        resizable: true,
        height: 640,
        columns: [
            {
                field: 'numero_nota',
                title: 'Numero da nota',
                width: 150,
                template: '#= numero_nota || "—" #'
            },
            {
                field: 'cliente',
                title: 'Cliente',
                width: 240,
                template: '#= cliente || "Nao identificado" #'
            },
            {
                field: 'dt_hr_recebimento',
                title: 'Data',
                width: 180
            },
            {
                field: 'valor_total',
                title: 'Valor',
                width: 120,
                template: '#= valor_total || "—" #'
            },
            {
                field: 'situacao',
                title: 'Situacao',
                width: 190
            },
            {
                field: 'ocorrencia',
                title: 'Ocorrencia',
                width: 130,
                template: function (row) {
                    return '<span class="badge text-bg-' + occurrenceBadge(row.ocorrencia) + '">' + escapeHtml(row.ocorrencia || '-') + '</span>';
                }
            },
            {
                title: 'Acoes',
                width: 240,
                sortable: false,
                template: function (row) {
                    return '' +
                        '<div class="d-flex gap-2">' +
                            '<button type="button" class="btn btn-sm btn-outline-primary js-open-monitor-detail" data-request-id="' + escapeHtml(row.request_id || '') + '">Detalhe rapido</button>' +
                            '<a class="btn btn-sm btn-primary" href="' + buildUrl(outputUrlTemplate, row.request_id || '') + '">Tela de saida</a>' +
                        '</div>';
                }
            }
        ],
        dataBound: function () {
            $grid.find('.js-open-monitor-detail').off('click').on('click', function () {
                openDetail(this.getAttribute('data-request-id') || '');
            });
        }
    });
}());
