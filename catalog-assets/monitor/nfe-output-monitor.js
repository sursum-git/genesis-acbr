(function () {
    const root = document.getElementById('nfe-output-monitor-app');
    const form = document.getElementById('nfe-output-monitor-filters');
    const filterWindowElement = document.getElementById('nfe-output-filter-window');
    if (!root || !form || !filterWindowElement || typeof window.kendo === 'undefined' || typeof window.jQuery === 'undefined') {
        return;
    }

    const $ = window.jQuery;
    const basePath = root.dataset.basePath || '';
    const dataUrl = root.dataset.dataUrl || '';
    const filterOptionsUrl = root.dataset.filterOptionsUrl || '';
    const filterLookupUrl = root.dataset.filterLookupUrl || '';
    const actionEventUrl = root.dataset.actionEventUrl || '';
    const correctionUrl = root.dataset.correctionUrl || '';
    const manifestationUrl = root.dataset.manifestationUrl || '';
    const detailUrlTemplate = root.dataset.detailUrlTemplate || '';
    const cancelUrl = root.dataset.cancelUrl || (basePath + '/index.php/nfe/eventos/cancelar');
    const inutilizeUrl = root.dataset.inutilizeUrl || (basePath + '/index.php/nfe/inutilizacao/inutilizar');
    const appBaseUrl = basePath + '/index.php';
    const $grid = $('#nfe-output-monitor-grid');
    const $filterWindow = $('#nfe-output-filter-window');
    const $openFilters = $('#open-nfe-output-filters');
    const $clearFilters = $('#clear-nfe-output-filters');
    let currentEnvironment = ['1', '2'].includes(root.dataset.defaultEnvironment || '') ? root.dataset.defaultEnvironment : '2';
    let activeColumnField = '';
    let activeColumnTitle = '';
    let activeColumnHeader = null;
    let actionWindow = null;
    let activeAction = null;
    let lookupWindow = null;
    let lookupGrid = null;
    let lookupActiveTarget = null;
    let lookupActiveType = '';
    let lookupWindowInitialized = false;
    let fiscalEventWindow = null;
    let fiscalEventGrid = null;

    function buildDetailUrl(requestId) {
        return detailUrlTemplate.replace('__REQUEST_ID__', encodeURIComponent(requestId));
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function parseDecimal(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }

        const normalized = String(value).replace(',', '.');
        const parsed = Number.parseFloat(normalized);

        return Number.isNaN(parsed) ? null : parsed;
    }

    function parseDate(value) {
        if (!value) {
            return null;
        }

        const normalized = String(value)
            .replace(' ', 'T')
            .replace(/(\.\d{3})\d+/, '$1');
        const parsed = window.kendo.parseDate(normalized) || new Date(normalized);
        return parsed && !Number.isNaN(parsed.getTime()) ? parsed : null;
    }

    function formatDate(value) {
        return value ? window.kendo.toString(value, 'g') : '';
    }

    function formatDecimal(value) {
        return value === null || value === undefined || value === '' ? '' : window.kendo.toString(value, 'n2');
    }

    function requiredActionFields(row, action) {
        const payload = row.acoes_nfe || {};
        if (action === 'cancelar') {
            return Boolean(payload.cancelar_url && payload.chave && payload.cnpj_emitente && !(row.cancelamento && row.cancelamento.cancelada));
        }

        if (action === 'carta_correcao') {
            return Boolean(correctionUrl && payload.chave && payload.cnpj_emitente && row.xml_url && !(row.cancelamento && row.cancelamento.cancelada));
        }

        if (action === 'manifestacao_destinatario') {
            return Boolean(manifestationUrl && row.chave_nfe && row.cliente_documento && row.xml_url);
        }

        return Boolean(payload.cnpj_emitente && payload.ano && payload.modelo && payload.serie && payload.numero_inicial && payload.numero_final);
    }

    function currentFilters() {
        const dateFromPicker = $('#filter-date-from').data('kendoDatePicker');
        const dateToPicker = $('#filter-date-to').data('kendoDatePicker');

        return {
            date_from: dateFromPicker && dateFromPicker.value() ? window.kendo.toString(dateFromPicker.value(), 'yyyy-MM-dd') : '',
            date_to: dateToPicker && dateToPicker.value() ? window.kendo.toString(dateToPicker.value(), 'yyyy-MM-dd') : '',
            numero_nota: $('#filter-numero-nota').val() || '',
            cliente: $('#filter-cliente').val() || '',
            assinante: $('#filter-assinante').val() || '',
            emissor: $('#filter-emissor').val() || '',
            chave: $('#filter-chave').val() || '',
            ambiente: currentEnvironment,
            status: Array.from(form.querySelectorAll('input[name="status"]:checked')).map(function (input) {
                return input.value;
            })
        };
    }

    function mapRow(row) {
        const impostos = row.impostos || {};
        const ibs = impostos.IBS || null;
        const cbs = impostos.CBS || null;
        const isTax = impostos.IS || null;

        return Object.assign({}, row, {
            data_envio: parseDate(row.data_envio),
            data_envio_texto: row.data_envio ? formatDate(parseDate(row.data_envio)) : '',
            data_emissao: parseDate(row.data_emissao),
            valor_total_num: parseDecimal(row.valor_total),
            icms_valor: parseDecimal(impostos.ICMS && impostos.ICMS.valor),
            cofins_valor: parseDecimal(impostos.COFINS && impostos.COFINS.valor),
            pis_valor: parseDecimal(impostos.PIS && impostos.PIS.valor),
            ipi_valor: parseDecimal(impostos.IPI && impostos.IPI.valor),
            ibs_valor: parseDecimal(ibs && ibs.valor),
            cbs_valor: parseDecimal(cbs && cbs.valor),
            is_valor: parseDecimal(isTax && isTax.valor)
        });
    }

    function openLookupWindow(type, targetSelector) {
        if (!lookupWindow) {
            return;
        }

        lookupActiveType = type;
        lookupActiveTarget = targetSelector;

        const targetValue = $(targetSelector).val() || '';
        $('#monitor-lookup-query').data('kendoTextBox').value(targetValue);
        lookupWindow.title('Buscar ' + (type === 'cliente' ? 'cliente' : type === 'emissor' ? 'emissor' : 'assinante'));
        lookupWindow.center().open();
        loadLookupResults(targetValue);
    }

    function loadLookupResults(query) {
        if (!lookupGrid || !filterLookupUrl || !lookupActiveType) {
            return;
        }

        $.getJSON(filterLookupUrl, {
            type: lookupActiveType,
            q: String(query || '').trim()
        }).done(function (response) {
            const items = Array.isArray(response.items) ? response.items.map(function (item) {
                return { value: item };
            }) : [];

            lookupGrid.dataSource.data(items);
            if (items.length > 0) {
                lookupGrid.select(lookupGrid.tbody.find('tr:first'));
            }
        }).fail(function () {
            lookupGrid.dataSource.data([]);
        });
    }

    function applyLookupSelection() {
        if (!lookupGrid || !lookupActiveTarget) {
            return;
        }

        const selected = lookupGrid.select();
        const dataItem = lookupGrid.dataItem(selected);
        if (!dataItem || !dataItem.value) {
            return;
        }

        $(lookupActiveTarget).val(dataItem.value).trigger('change');
        lookupWindow.close();
    }

    function initializeFiscalEventWindow() {
        if (fiscalEventWindow) {
            return;
        }

        const markup = '' +
            '<div id="nfe-fiscal-events-window" style="display: none;">' +
                '<div class="mb-3">' +
                    '<div class="small text-muted text-uppercase">Situação fiscal da NFe</div>' +
                    '<h3 id="nfe-fiscal-events-title" class="h5 mb-0"></h3>' +
                '</div>' +
                '<div id="nfe-fiscal-events-grid"></div>' +
            '</div>';
        $('body').append(markup);

        fiscalEventWindow = $('#nfe-fiscal-events-window').kendoWindow({
            title: 'Eventos da nota fiscal',
            modal: true,
            visible: false,
            width: 980,
            height: 620,
            actions: ['Close']
        }).data('kendoWindow');

        $('#nfe-fiscal-events-grid').kendoGrid({
            dataSource: [],
            height: 470,
            sortable: true,
            noRecords: {
                template: 'Nenhum evento fiscal registrado para esta nota.'
            },
            columns: [
                { field: 'data', title: 'Data', width: 155 },
                { field: 'tipo_acao', title: 'Evento', width: 190 },
                { field: 'situacao', title: 'Situação', width: 170 },
                { field: 'motivo', title: 'Retorno', width: 360 },
                { field: 'protocolo', title: 'Protocolo', width: 160 }
            ]
        });

        fiscalEventGrid = $('#nfe-fiscal-events-grid').data('kendoGrid');
    }

    function fiscalEventsFromRow(row) {
        if (!row) {
            return [];
        }

        const events = typeof row.get === 'function' ? row.get('eventos_nfe') : row.eventos_nfe;
        if (Array.isArray(events)) {
            return events;
        }

        if (events && typeof events.toJSON === 'function') {
            const normalized = events.toJSON();
            return Array.isArray(normalized) ? normalized : [];
        }

        return [];
    }

    function rowValue(row, field) {
        if (row && typeof row.get === 'function') {
            return row.get(field);
        }

        return row ? row[field] : '';
    }

    function openFiscalEventWindow(row) {
        initializeFiscalEventWindow();

        $('#nfe-fiscal-events-title').text((rowValue(row, 'situacao_nfe') || 'Sem situação') + ' - Nota ' + (rowValue(row, 'numero_nota') || rowValue(row, 'request_id') || ''));
        fiscalEventGrid.dataSource.data(fiscalEventsFromRow(row));
        fiscalEventWindow.center().open();
    }

    function initializeLookupWindow() {
        if (lookupWindowInitialized) {
            return;
        }

        $('#monitor-lookup-query').kendoTextBox();
        $('#monitor-lookup-search').kendoButton();
        $('#monitor-lookup-select').kendoButton({
            themeColor: 'primary'
        });
        $('#monitor-lookup-cancel').kendoButton();

        lookupWindow = $('#monitor-lookup-window').kendoWindow({
            title: 'Buscar',
            modal: true,
            visible: false,
            width: 960,
            height: 700,
            actions: ['Close'],
            open: function () {
                window.setTimeout(function () {
                    const textBox = $('#monitor-lookup-query').data('kendoTextBox');
                    if (textBox) {
                        textBox.focus();
                    }
                }, 0);
            }
        }).data('kendoWindow');

        $('#monitor-lookup-grid').kendoGrid({
            dataSource: [],
            height: 500,
            selectable: 'row',
            sortable: true,
            pageable: false,
            noRecords: {
                template: 'Nenhum resultado encontrado.'
            },
            columns: [
                { field: 'value', title: 'Resultado' }
            ],
            change: function () {
                const selected = this.select();
                const dataItem = this.dataItem(selected);
                if (dataItem && dataItem.value) {
                    $('#monitor-lookup-query').data('kendoTextBox').value(dataItem.value);
                }
            },
            dataBound: function () {
                this.tbody.find('tr').off('dblclick').on('dblclick', function () {
                    lookupGrid.select(this);
                    applyLookupSelection();
                });
            }
        });

        lookupGrid = $('#monitor-lookup-grid').data('kendoGrid');

        $('#monitor-lookup-search').on('click', function () {
            const textBox = $('#monitor-lookup-query').data('kendoTextBox');
            loadLookupResults(textBox ? textBox.value() : '');
        });

        $('#monitor-lookup-query').on('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                const textBox = $('#monitor-lookup-query').data('kendoTextBox');
                loadLookupResults(textBox ? textBox.value() : '');
            }
        });

        $('#monitor-lookup-select').on('click', applyLookupSelection);
        $('#monitor-lookup-cancel').on('click', function () {
            lookupWindow.close();
        });

        $(document).on('click', '.monitor-lookup-open', function () {
            openLookupWindow($(this).data('lookup-type'), $(this).data('lookup-target'));
        });

        lookupWindowInitialized = true;
    }

    function resetActionWindow() {
        $('#nfe-action-token').data('kendoTextBox').value('');
        $('#nfe-action-justification').data('kendoTextArea').value('');
        $('#nfe-action-justification-label').text('Justificativa');
        $('#nfe-manifestation-type-wrap').hide();
        const manifestationType = $('#nfe-manifestation-type').data('kendoDropDownList');
        if (manifestationType) {
            manifestationType.value('210210');
        }
        $('#nfe-action-result').removeClass('text-danger text-success').text('');
        $('#nfe-action-confirm').data('kendoButton').enable(true);
    }

    function initializeActionWindow() {
        $('#nfe-action-token').kendoTextBox();
        $('#nfe-action-justification').kendoTextArea({
            rows: 4,
            maxLength: 1000
        });
        $('#nfe-action-confirm').kendoButton({
            themeColor: 'primary'
        });
        $('#nfe-action-close').kendoButton();
        $('#nfe-manifestation-type').kendoDropDownList();

        actionWindow = $('#nfe-action-window').kendoWindow({
            title: 'Ação da NFe',
            modal: true,
            visible: false,
            width: 680,
            actions: ['Close'],
            close: function () {
                activeAction = null;
            }
        }).data('kendoWindow');

        $('#nfe-action-close').on('click', function () {
            actionWindow.close();
        });

        $('#nfe-action-form').on('submit', function (event) {
            event.preventDefault();
            submitNfeAction();
        });
    }

    function openActionWindow(action, row) {
        if (!actionWindow || !row) {
            return;
        }

        activeAction = {
            type: action,
            row: row
        };
        resetActionWindow();

        const title = action === 'cancelar'
            ? 'Cancelar NFe'
            : action === 'carta_correcao'
                ? 'Carta de Correção'
                : action === 'manifestacao_destinatario'
                    ? 'Manifestação do Destinatário'
                    : 'Inutilizar numeração';
        const payload = row.acoes_nfe || {};
        const summary = action === 'cancelar'
            ? 'Cancelar a NFe ' + escapeHtml(row.numero_nota || '') + '<br>Chave: <span class="monitor-break">' + escapeHtml(payload.chave || row.chave_nfe || '') + '</span>'
            : action === 'carta_correcao'
                ? 'Registrar Carta de Correção para a NFe ' + escapeHtml(row.numero_nota || '') + '<br>Chave: <span class="monitor-break">' + escapeHtml(payload.chave || row.chave_nfe || '') + '</span><br><span class="text-muted">Não use para corrigir valores, impostos, quantidade, remetente/destinatário ou datas da nota.</span>'
                : action === 'manifestacao_destinatario'
                    ? 'Registrar manifestação para a NFe de entrada ' + escapeHtml(row.numero_nota || '') + '<br>Chave: <span class="monitor-break">' + escapeHtml(row.chave_nfe || '') + '</span><br>Destinatário: ' + escapeHtml(row.cliente || row.cliente_documento || '')
                    : 'Inutilizar a numeração da NFe ' + escapeHtml(row.numero_nota || '') + '<br>Série: ' + escapeHtml(payload.serie || '') + ' | Modelo: ' + escapeHtml(payload.modelo || '55') + ' | Ano: ' + escapeHtml(payload.ano || '');

        actionWindow.title(title);
        $('#nfe-action-justification-label').text(action === 'carta_correcao' ? 'Correção' : 'Justificativa');
        $('#nfe-manifestation-type-wrap').toggle(action === 'manifestacao_destinatario');
        $('#nfe-action-help-text').text(action === 'manifestacao_destinatario' ? 'Justificativa obrigatória apenas para Operação não Realizada.' : 'Informe no mínimo 15 caracteres.');
        $('#nfe-action-summary').html(summary);
        actionWindow.center().open();

        window.setTimeout(function () {
            const tokenInput = $('#nfe-action-token').data('kendoTextBox');
            if (tokenInput) {
                tokenInput.focus();
            }
        }, 0);
    }

    function firstResponseValue(response, paths) {
        for (const path of paths) {
            const parts = path.split('.');
            let value = response;

            for (const part of parts) {
                if (!value || typeof value !== 'object' || !(part in value)) {
                    value = null;
                    break;
                }

                value = value[part];
            }

            if (value !== null && value !== undefined && value !== '') {
                return String(value);
            }
        }

        return '';
    }

    function responseTextCandidates(response) {
        return [
            response.message,
            response.mensagem,
            response.raw,
            response.resultado && response.resultado.message,
            response.resultado && response.resultado.mensagem,
            response.resultado && response.resultado.raw,
            response.resultado && response.resultado.xml,
            response.resultado && response.resultado.XML
        ].filter(function (value) {
            return typeof value === 'string' && value !== '';
        });
    }

    function extractLineValue(texts, keys) {
        for (const text of texts) {
            for (const key of keys) {
                const match = text.match(new RegExp('(?:^|\\n)\\s*' + key + '\\s*=\\s*([^\\r\\n<]+)', 'i'));
                if (match && match[1]) {
                    return match[1].trim();
                }
            }
        }

        return '';
    }

    function extractLastLineValue(texts, keys) {
        let lastValue = '';
        for (const text of texts) {
            for (const key of keys) {
                const matches = text.matchAll(new RegExp('(?:^|\\n)\\s*' + key + '\\s*=\\s*([^\\r\\n<]*)', 'gi'));
                for (const match of matches) {
                    const value = String(match[1] || '').trim();
                    if (value !== '') {
                        lastValue = value;
                    }
                }
            }
        }

        return lastValue;
    }

    function actionResultMessage(response) {
        if (!response) {
            return 'Ação enviada. Consulte a requisição gerada para confirmar o retorno da SEFAZ.';
        }

        if (typeof response === 'string') {
            return response;
        }

        const texts = responseTextCandidates(response);
        const event = response.event && typeof response.event === 'object' ? response.event : {};
        const message = firstResponseValue({ event: event }, [
            'event.motivo',
            'event.situacao'
        ]) || extractLastLineValue(texts, ['xMotivo', 'XMotivo', 'Motivo']) || firstResponseValue(response, [
            'resultado.xMotivo',
            'resultado.XMotivo',
            'resultado.motivo',
            'hydra:description',
            'message',
            'mensagem'
        ]);
        const cStat = firstResponseValue({ event: event }, [
            'event.c_stat'
        ]) || firstResponseValue(response, [
            'c_stat_receita',
            'cStat',
            'CStat',
            'resultado.c_stat_receita',
            'resultado.cStat',
            'resultado.CStat'
        ]) || extractLastLineValue(texts, ['cStat', 'CStat']);
        const nProt = firstResponseValue({ event: event }, [
            'event.protocolo'
        ]) || firstResponseValue(response, [
            'nProt',
            'NProt',
            'protocolo',
            'resultado.nProt',
            'resultado.NProt',
            'resultado.protocolo'
        ]) || extractLastLineValue(texts, ['nProt', 'NProt', 'Protocolo']);
        const status = firstResponseValue(response, ['status', 'resultado.status']);
        const requestId = firstResponseValue(response, ['request_id', 'resultado.request_id']);
        const parts = [];

        if (message) {
            parts.push(message);
        }

        if (cStat) {
            parts.push('cStat: ' + cStat);
        }

        if (nProt) {
            parts.push('nProt: ' + nProt);
        }

        if (status) {
            parts.push('Status: ' + status);
        }

        if (requestId) {
            parts.push('Request ID: ' + requestId + ' (consulte em Consulta de requisições)');
        }

        return parts.length > 0 ? parts.join(' | ') : 'Ação enviada. Consulte a requisição gerada para confirmar o retorno da SEFAZ.';
    }

    function actionResponseRequestId(response) {
        if (!response || typeof response !== 'object') {
            return '';
        }

        return firstResponseValue(response, ['request_id', 'resultado.request_id']);
    }

    function fiscalEventErrorMessage(xhr) {
        if (!xhr) {
            return 'Evento fiscal não foi gravado automaticamente.';
        }

        if (xhr.responseJSON && xhr.responseJSON.message) {
            return 'Evento fiscal não foi gravado automaticamente: ' + xhr.responseJSON.message;
        }

        if (xhr.responseText) {
            return 'Evento fiscal não foi gravado automaticamente: ' + xhr.responseText;
        }

        return 'Evento fiscal não foi gravado automaticamente.';
    }

    function recordFiscalEvent(action, row, actionPayload, response) {
        if (!actionEventUrl || !row || !row.request_id) {
            return $.Deferred().resolve().promise();
        }

        return $.ajax({
            url: actionEventUrl,
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                nota_request_id: row.request_id,
                action: action,
                request_id: actionResponseRequestId(response),
                payload: actionPayload,
                response: response && typeof response === 'object' ? response : { mensagem: String(response || '') }
            })
        });
    }

    function submitNfeAction() {
        if (!activeAction || !activeAction.row) {
            return;
        }

        const textArea = $('#nfe-action-justification').data('kendoTextArea');
        const tokenInput = $('#nfe-action-token').data('kendoTextBox');
        const token = String(tokenInput ? tokenInput.value() : '').trim();
        const justification = String(textArea ? textArea.value() : '').trim();
        const $result = $('#nfe-action-result');
        const confirmButton = $('#nfe-action-confirm').data('kendoButton');

        if (token === '') {
            $result.removeClass('text-success').addClass('text-danger').text('Informe o token da API.');
            return;
        }

        if (activeAction.type !== 'manifestacao_destinatario' && justification.length < 15) {
            $result.removeClass('text-success').addClass('text-danger').text('A justificativa deve ter no mínimo 15 caracteres.');
            return;
        }

        const row = activeAction.row;
        const payload = row.acoes_nfe || {};
        confirmButton.enable(false);
        $result.removeClass('text-danger text-success').text('Enviando...');

        if (activeAction.type === 'cancelar') {
            const actionPayload = {
                AeChave: payload.chave || row.chave_nfe || '',
                AeJustificativa: justification,
                AeCNPJCPF: payload.cnpj_emitente || '',
                ALote: payload.lote || '1'
            };

            $.ajax({
                url: cancelUrl,
                method: 'POST',
                contentType: 'application/ld+json',
                dataType: 'json',
                headers: {
                    'X-Api-Token': token
                },
                data: JSON.stringify({
                    payload: actionPayload
                })
            }).done(function (response) {
                recordFiscalEvent(activeAction.type, row, actionPayload, response)
                    .done(function () {
                        $result.removeClass('text-danger').addClass('text-success').text(actionResultMessage(response));
                    })
                    .fail(function (xhr) {
                        $result.removeClass('text-danger').addClass('text-success').text(actionResultMessage(response) + ' ' + fiscalEventErrorMessage(xhr));
                    })
                    .always(function () {
                        $grid.data('kendoGrid').dataSource.read();
                    });
            }).fail(function (xhr) {
                $result.removeClass('text-success').addClass('text-danger').text(xhr.responseJSON ? actionResultMessage(xhr.responseJSON) : (xhr.responseText || 'Falha ao cancelar a NFe.'));
                confirmButton.enable(true);
            });
            return;
        }

        if (activeAction.type === 'carta_correcao') {
            $.ajax({
                url: correctionUrl,
                method: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                headers: {
                    'X-Api-Token': token
                },
                data: JSON.stringify({
                    nota_request_id: row.request_id || '',
                    correcao: justification
                })
            }).done(function (response) {
                $result.removeClass('text-danger').addClass('text-success').text(actionResultMessage(response));
                $grid.data('kendoGrid').dataSource.read();
            }).fail(function (xhr) {
                const response = xhr.responseJSON || null;
                $result.removeClass('text-success').addClass('text-danger').text(response ? actionResultMessage(response) : (xhr.responseText || 'Falha ao enviar a Carta de Correção.'));
                confirmButton.enable(true);
                $grid.data('kendoGrid').dataSource.read();
            });
            return;
        }

        if (activeAction.type === 'manifestacao_destinatario') {
            const manifestationType = $('#nfe-manifestation-type').data('kendoDropDownList');
            const selectedManifestation = String(manifestationType ? manifestationType.value() : $('#nfe-manifestation-type').val() || '210210');
            if (selectedManifestation === '210240' && justification.length < 15) {
                $result.removeClass('text-success').addClass('text-danger').text('Operação não realizada exige justificativa com no mínimo 15 caracteres.');
                confirmButton.enable(true);
                return;
            }

            $.ajax({
                url: manifestationUrl,
                method: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                headers: {
                    'X-Api-Token': token
                },
                data: JSON.stringify({
                    nota_request_id: row.request_id || '',
                    tipo_manifestacao: selectedManifestation,
                    justificativa: justification
                })
            }).done(function (response) {
                $result.removeClass('text-danger').addClass('text-success').text(actionResultMessage(response));
                $grid.data('kendoGrid').dataSource.read();
            }).fail(function (xhr) {
                const response = xhr.responseJSON || null;
                $result.removeClass('text-success').addClass('text-danger').text(response ? actionResultMessage(response) : (xhr.responseText || 'Falha ao enviar a Manifestação do Destinatário.'));
                confirmButton.enable(true);
                $grid.data('kendoGrid').dataSource.read();
            });
            return;
        }

        const actionPayload = {
            ACNPJ: payload.cnpj_emitente || '',
            AJustificativa: justification,
            AAno: payload.ano || '',
            AModelo: payload.modelo || '55',
            ASerie: payload.serie || '',
            ANumeroInicial: payload.numero_inicial || row.numero_nota || '',
            ANumeroFinal: payload.numero_final || row.numero_nota || ''
        };

        $.ajax({
            url: inutilizeUrl,
            method: 'GET',
            dataType: 'json',
            headers: {
                'X-Api-Token': token
            },
            data: actionPayload
        }).done(function (response) {
            recordFiscalEvent(activeAction.type, row, actionPayload, response)
                .done(function () {
                    $result.removeClass('text-danger').addClass('text-success').text(actionResultMessage(response));
                })
                .fail(function (xhr) {
                    $result.removeClass('text-danger').addClass('text-success').text(actionResultMessage(response) + ' ' + fiscalEventErrorMessage(xhr));
                })
                .always(function () {
                    $grid.data('kendoGrid').dataSource.read();
                });
        }).fail(function (xhr) {
            $result.removeClass('text-success').addClass('text-danger').text(xhr.responseJSON ? actionResultMessage(xhr.responseJSON) : (xhr.responseText || 'Falha ao inutilizar a numeração.'));
            confirmButton.enable(true);
        });
    }

    function configureDatePicker(selector) {
        const widget = $(selector).kendoDatePicker({
            culture: 'pt-BR',
            format: 'dd/MM/yyyy',
            parseFormats: ['ddMMyyyy', 'dd/MM/yyyy', 'yyyy-MM-dd'],
            dateInput: true,
            popup: {
                appendTo: filterWindowElement
            }
        }).data('kendoDatePicker');

        if (widget && widget.dateInput) {
            widget.dateInput.setOptions({
                messages: {
                    year: 'aaaa',
                    month: 'mm',
                    day: 'dd',
                    weekday: ''
                },
                formatPlaceholder: {
                    day: 'dd',
                    month: 'mm',
                    year: 'aaaa'
                }
            });
            widget.element.attr('placeholder', 'dd/mm/aaaa');
            widget.dateInput.element.attr('placeholder', 'dd/mm/aaaa');
        }

        return widget;
    }

    function syncWindowSize(filterWindow) {
        const viewportHeight = Math.max(window.innerHeight || 0, 720);
        const viewportWidth = Math.max(window.innerWidth || 0, 1280);
        filterWindow.setOptions({
            width: Math.min(viewportWidth - 32, 1440),
            height: Math.min(viewportHeight - 32, 900)
        });
    }

    function initializeFilterWindow() {
        configureDatePicker('#filter-date-from');
        configureDatePicker('#filter-date-to');
        $('#filter-numero-nota').kendoTextBox();
        $('#filter-chave').kendoTextBox();
        $('#filter-cliente').kendoTextBox();
        $('#filter-assinante').kendoTextBox();
        $('#filter-emissor').kendoTextBox();

        $(form.querySelectorAll('input[type="checkbox"]')).each(function () {
            $(this).kendoCheckBox();
        });

        const filterWindow = $filterWindow.kendoWindow({
            title: 'Filtros do monitor',
            modal: true,
            visible: false,
            draggable: false,
            resizable: false,
            actions: ['Close'],
            width: 1200,
            height: 820,
            deactivate: function () {
                document.body.classList.remove('monitor-filter-open');
            }
        }).data('kendoWindow');

        initializeLookupWindow();

        $openFilters.on('click', function () {
            syncWindowSize(filterWindow);
            filterWindow.center().open();
            window.setTimeout(function () {
                filterWindow.maximize();
                document.body.classList.add('monitor-filter-open');
            }, 0);
        });

        $(window).on('resize', function () {
            if (filterWindow.wrapper.is(':visible')) {
                syncWindowSize(filterWindow);
            }
        });

        return filterWindow;
    }

    function gridHeight() {
        const topOffset = $grid.offset() ? $grid.offset().top : 220;
        const viewportHeight = window.innerHeight || 900;
        return Math.max(460, viewportHeight - topOffset - 36);
    }

    function currentGridColumns(grid) {
        return grid.columns.filter(function (column) {
            return !!column.field && column.columnMenu !== false;
        });
    }

    function findGridColumn(grid, field) {
        return currentGridColumns(grid).find(function (column) {
            return column.field === field;
        }) || null;
    }

    function renderColumnPopup(grid) {
        const groupedFields = (grid.dataSource.group() || []).map(function (group) {
            return group.field;
        });
        const columnsMarkup = currentGridColumns(grid).map(function (column) {
            const title = escapeHtml(column.title || column.field || '');
            const field = escapeHtml(column.field || '');
            const hidden = column.hidden ? '' : ' checked';
            return '<label class="monitor-column-menu-check"><input type="checkbox" data-column-field="' + field + '"' + hidden + '> ' + title + '</label>';
        }).join('');
        const isGrouped = groupedFields.indexOf(activeColumnField) >= 0;

        return '' +
            '<div class="monitor-column-menu">' +
                '<div class="monitor-column-menu-title">' + escapeHtml(activeColumnTitle) + '</div>' +
                '<div class="monitor-column-menu-actions">' +
                    '<button type="button" class="k-button k-button-flat k-button-flat-base monitor-column-action" data-action="sort-asc">Ordenar ascendente</button>' +
                    '<button type="button" class="k-button k-button-flat k-button-flat-base monitor-column-action" data-action="sort-desc">Ordenar descendente</button>' +
                    '<button type="button" class="k-button k-button-flat k-button-flat-base monitor-column-action" data-action="' + (isGrouped ? 'ungroup' : 'group') + '">' + (isGrouped ? 'Remover agrupamento' : 'Agrupar por coluna') + '</button>' +
                    '<button type="button" class="k-button k-button-flat k-button-flat-base monitor-column-action" data-action="move-prev">Mover para a esquerda</button>' +
                    '<button type="button" class="k-button k-button-flat k-button-flat-base monitor-column-action" data-action="move-next">Mover para a direita</button>' +
                '</div>' +
                '<div class="monitor-column-menu-section">' +
                    '<div class="monitor-column-menu-subtitle">Colunas visíveis</div>' +
                    '<div class="monitor-column-menu-columns">' + columnsMarkup + '</div>' +
                '</div>' +
            '</div>';
    }

    function ensureColumnPopup() {
        if (!document.getElementById('nfe-output-column-popup')) {
            $('body').append('<div id="nfe-output-column-popup" style="display:none;"></div>');
        }

        return $('#nfe-output-column-popup');
    }

    function updateGrouping(grid, field, shouldGroup) {
        const groups = (grid.dataSource.group() || []).slice();
        const existingIndex = groups.findIndex(function (group) {
            return group.field === field;
        });

        if (shouldGroup && existingIndex === -1) {
            groups.push({ field: field });
        }

        if (!shouldGroup && existingIndex >= 0) {
            groups.splice(existingIndex, 1);
        }

        grid.dataSource.group(groups);
    }

    function moveActiveColumn(grid, shouldMovePrev) {
        if (!activeColumnHeader || typeof grid._moveColumn !== 'function') {
            return;
        }

        grid._moveColumn(activeColumnHeader, shouldMovePrev);
    }

    function openColumnPopup(grid, trigger, field, title, headerElement) {
        activeColumnField = field;
        activeColumnTitle = title;
        activeColumnHeader = headerElement;

        const $popup = ensureColumnPopup();
        const triggerOffset = trigger.offset();
        const triggerHeight = trigger.outerHeight() || 0;
        const triggerWidth = trigger.outerWidth() || 0;

        $popup.html(renderColumnPopup(grid));
        $popup.css({
            display: 'block',
            position: 'absolute',
            top: (triggerOffset.top + triggerHeight + 6) + 'px',
            left: Math.max(12, triggerOffset.left + triggerWidth - 304) + 'px'
        });
    }

    function closeColumnPopup() {
        ensureColumnPopup().hide().empty();
    }

    function attachHeaderColumnMenus(grid) {
        const headerCells = grid.thead.find('th[data-field]');
        headerCells.each(function () {
            const $header = $(this);
            const field = $header.data('field');
            if (!field || $header.find('.monitor-column-trigger').length) {
                return;
            }

            const column = findGridColumn(grid, field);
            if (!column || column.columnMenu === false) {
                return;
            }

            let $inner = $header.find('.k-cell-inner');
            if (!$inner.length) {
                $header.wrapInner('<span class="k-cell-inner"></span>');
                $inner = $header.find('.k-cell-inner');
            }

            const title = column.title || field;
            const buttonHtml = '' +
                '<button type="button" class="k-button k-button-flat k-button-flat-base k-icon-button monitor-column-trigger" aria-label="Menu da coluna">' +
                    window.kendo.ui.icon('more-vertical') +
                '</button>';
            $inner.append(buttonHtml);

            $inner.find('.monitor-column-trigger').last().on('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                openColumnPopup(grid, $(this), field, title, $header);
            });
        });
    }

    function updateEnvironmentButtons($buttons) {
        $buttons.each(function () {
            const $button = $(this);
            const selected = String($button.data('environment')) === currentEnvironment;
            $button
                .toggleClass('k-selected k-button-solid-primary', selected)
                .toggleClass('k-button-solid-base', !selected)
                .attr('aria-pressed', selected ? 'true' : 'false');
        });
    }

    function initializeEnvironmentToggle(grid) {
        const $toolbar = $grid.find('.k-grid-toolbar');
        if (!$toolbar.length || $toolbar.find('.monitor-env-toggle').length) {
            return;
        }

        const markup = '' +
            '<div class="monitor-env-toggle" role="group" aria-label="Ambiente da nota">' +
                '<span class="monitor-env-label">Ambiente</span>' +
                '<button type="button" class="monitor-env-button" data-environment="1" aria-pressed="false">Produção</button>' +
                '<button type="button" class="monitor-env-button" data-environment="2" aria-pressed="false">Homologação</button>' +
            '</div>';
        const $searchItem = $toolbar.find('.k-grid-search').closest('.k-toolbar-item');
        const $toggle = $(markup);

        if ($searchItem.length) {
            $searchItem.before($toggle);
        } else {
            $toolbar.append($toggle);
        }

        const $buttons = $toggle.find('.monitor-env-button');
        $buttons.kendoButton();
        updateEnvironmentButtons($buttons);

        $buttons.on('click', function () {
            const nextEnvironment = String($(this).data('environment'));
            if (nextEnvironment === currentEnvironment) {
                return;
            }

            currentEnvironment = nextEnvironment;
            updateEnvironmentButtons($buttons);
            grid.dataSource.page(1);
            grid.dataSource.read();
        });
    }

    const filterWindow = initializeFilterWindow();
    initializeActionWindow();

    const dataSource = new window.kendo.data.DataSource({
        transport: {
            read: function (options) {
                $.ajax({
                    url: dataUrl,
                    dataType: 'json',
                    data: currentFilters(),
                    success: function (response) {
                        const rows = Array.isArray(response.data) ? response.data.map(mapRow) : [];
                        options.success(rows);
                    },
                    error: function (xhr) {
                        options.error(xhr);
                    }
                });
            }
        },
        schema: {
            model: {
                fields: {
                    data_envio: { type: 'date' },
                    data_envio_texto: { type: 'string' },
                    data_emissao: { type: 'date' },
                    valor_total_num: { type: 'number' },
                    icms_valor: { type: 'number' },
                    cofins_valor: { type: 'number' },
                    pis_valor: { type: 'number' },
                    ipi_valor: { type: 'number' },
                    ibs_valor: { type: 'number' },
                    cbs_valor: { type: 'number' },
                    is_valor: { type: 'number' }
                }
            }
        },
        pageSize: 20
    });

    $grid.kendoGrid({
        dataSource: dataSource,
        height: gridHeight(),
        toolbar: ['search'],
        sortable: true,
        groupable: true,
        resizable: true,
        reorderable: true,
        scrollable: true,
        columnMenu: false,
        filterable: {
            mode: 'menu, row',
            operators: {
                string: {
                    contains: 'Contém'
                },
                number: {
                    eq: 'Igual a'
                }
            }
        },
        pageable: {
            pageSizes: [20, 50, 100],
            refresh: true,
            buttonCount: 5,
            messages: {
                display: '{0} - {1} de {2} registros',
                empty: 'Nenhum registro encontrado',
                page: 'Página',
                of: 'de {0}',
                itemsPerPage: 'registros por página',
                first: 'Primeira página',
                previous: 'Página anterior',
                next: 'Próxima página',
                last: 'Última página',
                refresh: 'Atualizar'
            }
        },
        noRecords: {
            template: 'Nenhuma nota encontrada para os filtros informados.'
        },
        search: {
            fields: [
                'numero_nota',
                'cliente',
                'emitente_nome',
                'status_envio',
                'situacao_nfe',
                'chave_nfe'
            ]
        },
        dataBound: function () {
            attachHeaderColumnMenus(this);
        },
        columns: [
            {
                field: 'data_envio_texto',
                title: 'Data',
                width: 190,
                template: function (row) { return row.data_envio_texto || ''; },
                filterable: {
                    cell: {
                        operator: 'contains',
                        showOperators: false,
                        template: function (args) {
                            args.element.kendoMaskedTextBox({
                                mask: '00000000'
                            });
                        }
                    }
                }
            },
            {
                field: 'numero_nota',
                title: 'Nota',
                width: 135,
                template: function (row) { return escapeHtml(row.numero_nota || ''); },
                filterable: { cell: { operator: 'contains', showOperators: false } }
            },
            { field: 'cliente', title: 'Cliente', width: 280, filterable: { cell: { operator: 'contains', showOperators: false } } },
            { field: 'emitente_nome', title: 'Emissor', width: 280, filterable: { cell: { operator: 'contains', showOperators: false } } },
            { field: 'valor_total_num', title: 'Valor', width: 140, template: function (row) { return formatDecimal(row.valor_total_num); }, filterable: { cell: { operator: 'eq', showOperators: false } } },
            { field: 'status_envio', title: 'Status', width: 155, filterable: { cell: { operator: 'contains', showOperators: false } } },
            {
                field: 'situacao_nfe',
                title: 'Situação NFe',
                width: 190,
                template: function (row) {
                    return '<button type="button" class="btn btn-sm btn-link p-0 monitor-nfe-situation" data-request-id="' + escapeHtml(row.request_id || '') + '">' + escapeHtml(row.situacao_nfe || 'Sem situação') + '</button>';
                },
                filterable: { cell: { operator: 'contains', showOperators: false } }
            },
            { field: 'chave_nfe', title: 'Chave', width: 310, template: '<span class="small">#= chave_nfe || "" #</span>', filterable: { cell: { operator: 'contains', showOperators: false } } },
            { field: 'icms_valor', title: 'ICMS', width: 150, template: function (row) { return formatDecimal(row.icms_valor); }, filterable: { cell: { operator: 'eq', showOperators: false } } },
            { field: 'cofins_valor', title: 'COFINS', width: 175, template: function (row) { return formatDecimal(row.cofins_valor); }, filterable: { cell: { operator: 'eq', showOperators: false } } },
            { field: 'pis_valor', title: 'PIS', width: 140, template: function (row) { return formatDecimal(row.pis_valor); }, filterable: { cell: { operator: 'eq', showOperators: false } } },
            { field: 'ipi_valor', title: 'IPI', width: 140, template: function (row) { return formatDecimal(row.ipi_valor); }, filterable: { cell: { operator: 'eq', showOperators: false } } },
            { field: 'ibs_valor', title: 'IBS', width: 140, template: function (row) { return formatDecimal(row.ibs_valor); }, filterable: { cell: { operator: 'eq', showOperators: false } } },
            { field: 'cbs_valor', title: 'CBS', width: 140, template: function (row) { return formatDecimal(row.cbs_valor); }, filterable: { cell: { operator: 'eq', showOperators: false } } },
            { field: 'is_valor', title: 'IS', width: 130, template: function (row) { return formatDecimal(row.is_valor); }, filterable: { cell: { operator: 'eq', showOperators: false } } },
            {
                title: 'Arquivos',
                width: 360,
                sortable: false,
                filterable: false,
                columnMenu: false,
                template: function (row) {
                    const buttons = [
                        '<a class="btn btn-sm btn-outline-secondary" href="' + buildDetailUrl(row.request_id || '') + '">Detalhe</a>'
                    ];

                    if (row.xml_url) {
                        buttons.push('<a class="btn btn-sm btn-primary" href="' + appBaseUrl + escapeHtml(row.xml_url) + '">XML</a>');
                    }

                    buttons.push('<button type="button" class="btn btn-sm btn-outline-info monitor-nfe-events" data-request-id="' + escapeHtml(row.request_id || '') + '">Eventos</button>');

                    if (requiredActionFields(row, 'cancelar')) {
                        buttons.push('<button type="button" class="btn btn-sm btn-outline-danger monitor-nfe-action" data-action="cancelar" data-request-id="' + escapeHtml(row.request_id || '') + '">Cancelar</button>');
                    }

                    if (requiredActionFields(row, 'carta_correcao')) {
                        buttons.push('<button type="button" class="btn btn-sm btn-outline-dark monitor-nfe-action nfe-action-correction" data-action="carta_correcao" data-request-id="' + escapeHtml(row.request_id || '') + '">Carta de Correção</button>');
                    }

                    if (requiredActionFields(row, 'manifestacao_destinatario')) {
                        buttons.push('<button type="button" class="btn btn-sm btn-outline-dark monitor-nfe-action" data-action="manifestacao_destinatario" data-request-id="' + escapeHtml(row.request_id || '') + '">Manifestar</button>');
                    }

                    if (requiredActionFields(row, 'inutilizar')) {
                        buttons.push('<button type="button" class="btn btn-sm btn-outline-warning monitor-nfe-action" data-action="inutilizar" data-request-id="' + escapeHtml(row.request_id || '') + '">Inutilizar</button>');
                    }

                    return '<div class="d-flex flex-wrap gap-2">' + buttons.join('') + '</div>';
                }
            }
        ]
    });

    const grid = $grid.data('kendoGrid');
    initializeEnvironmentToggle(grid);

    $(document).on('click', '.monitor-nfe-action', function () {
        const action = $(this).data('action');
        const requestId = String($(this).data('request-id') || '');
        const data = grid.dataSource.data();
        let row = null;
        for (let index = 0; index < data.length; index += 1) {
            if (String(data[index].request_id || '') === requestId) {
                row = data[index];
                break;
            }
        }

        openActionWindow(action, row);
    });

    $(document).on('click', '.monitor-nfe-situation', function () {
        const requestId = String($(this).data('request-id') || '');
        const data = grid.dataSource.data();
        let row = null;
        for (let index = 0; index < data.length; index += 1) {
            if (String(data[index].request_id || '') === requestId) {
                row = data[index];
                break;
            }
        }

        if (row) {
            openFiscalEventWindow(row);
        }
    });

    $(document).on('click', '.monitor-nfe-events', function () {
        const requestId = String($(this).data('request-id') || '');
        const data = grid.dataSource.data();
        let row = null;
        for (let index = 0; index < data.length; index += 1) {
            if (String(data[index].request_id || '') === requestId) {
                row = data[index];
                break;
            }
        }

        if (row) {
            openFiscalEventWindow(row);
        }
    });

    $(document).on('click', '.monitor-column-action', function (event) {
        event.preventDefault();
        const action = $(this).data('action');

        if (!activeColumnField) {
            return;
        }

        if (action === 'sort-asc') {
            grid.dataSource.sort([{ field: activeColumnField, dir: 'asc' }]);
        } else if (action === 'sort-desc') {
            grid.dataSource.sort([{ field: activeColumnField, dir: 'desc' }]);
        } else if (action === 'group') {
            updateGrouping(grid, activeColumnField, true);
        } else if (action === 'ungroup') {
            updateGrouping(grid, activeColumnField, false);
        } else if (action === 'move-prev') {
            moveActiveColumn(grid, true);
        } else if (action === 'move-next') {
            moveActiveColumn(grid, false);
        }

        closeColumnPopup();
    });

    $(document).on('change', '[data-column-field]', function () {
        const field = $(this).data('column-field');
        const column = findGridColumn(grid, field);
        if (!column) {
            return;
        }

        if (this.checked) {
            grid.showColumn(column);
        } else {
            grid.hideColumn(column);
        }
    });

    $(document).on('click', function (event) {
        const $popup = $('#nfe-output-column-popup');
        if (!$popup.length || !$popup.is(':visible')) {
            return;
        }

        const $target = $(event.target);
        if ($target.closest('#nfe-output-column-popup').length || $target.closest('.monitor-column-trigger').length) {
            return;
        }

        closeColumnPopup();
    });

    $(window).on('resize', function () {
        grid.setOptions({
            height: gridHeight()
        });
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        grid.dataSource.page(1);
        grid.dataSource.read();
        filterWindow.close();
    });

    form.addEventListener('reset', function () {
        window.setTimeout(function () {
            $('#filter-date-from').data('kendoDatePicker').value(null);
            $('#filter-date-to').data('kendoDatePicker').value(null);

            ['#filter-cliente', '#filter-assinante', '#filter-emissor'].forEach(function (selector) {
                const textBox = $(selector).data('kendoTextBox');
                if (textBox) {
                    textBox.value('');
                }
            });

            grid.dataSource.page(1);
            grid.dataSource.read();
        }, 0);
    });

    $clearFilters.on('click', function () {
        form.reset();
    });
}());
