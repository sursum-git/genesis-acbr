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
    const detailUrlTemplate = root.dataset.detailUrlTemplate || '';
    const appBaseUrl = basePath + '/index.php';
    const $grid = $('#nfe-output-monitor-grid');
    const $filterWindow = $('#nfe-output-filter-window');
    const $openFilters = $('#open-nfe-output-filters');
    const $clearFilters = $('#clear-nfe-output-filters');

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

    function currentFilters() {
        return {
            date_from: $('#filter-date-from').data('kendoDatePicker').value() ? window.kendo.toString($('#filter-date-from').data('kendoDatePicker').value(), 'yyyy-MM-dd') : '',
            date_to: $('#filter-date-to').data('kendoDatePicker').value() ? window.kendo.toString($('#filter-date-to').data('kendoDatePicker').value(), 'yyyy-MM-dd') : '',
            numero_nota: $('#filter-numero-nota').val() || '',
            cliente: $('#filter-cliente').val() || '',
            assinante: $('#filter-assinante').val() || '',
            emissor: $('#filter-emissor').val() || '',
            chave: $('#filter-chave').val() || '',
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

    function initializeFilterWindow() {
        $('#filter-date-from').kendoDatePicker({ format: 'dd/MM/yyyy' });
        $('#filter-date-to').kendoDatePicker({ format: 'dd/MM/yyyy' });
        $('#filter-numero-nota').kendoTextBox();
        $('#filter-cliente').kendoTextBox();
        $('#filter-assinante').kendoTextBox();
        $('#filter-emissor').kendoTextBox();
        $('#filter-chave').kendoTextBox();

        $(form.querySelectorAll('input[type="checkbox"]')).each(function () {
            $(this).kendoCheckBox();
        });

        const filterWindow = $filterWindow.kendoWindow({
            title: 'Filtros do monitor',
            modal: true,
            visible: false,
            draggable: false,
            resizable: false,
            width: '90%',
            open: function () {
                this.maximize();
            }
        }).data('kendoWindow');

        $openFilters.on('click', function () {
            filterWindow.center().open();
            filterWindow.maximize();
        });

        filterWindow.center().open();
        filterWindow.maximize();

        return filterWindow;
    }

    const filterWindow = initializeFilterWindow();

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
        pageSize: 20
    });

    $grid.kendoGrid({
        dataSource: dataSource,
        height: 720,
        sortable: true,
        groupable: true,
        resizable: true,
        reorderable: true,
        scrollable: true,
        columnMenu: true,
        filterable: {
            mode: 'row'
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
        columns: [
            { field: 'data_envio', title: 'Data', width: 165, template: function (row) { return formatDate(row.data_envio); } },
            { field: 'numero_nota', title: 'Nota', width: 110, template: function (row) { return escapeHtml(row.numero_nota || ''); } },
            { field: 'cliente', title: 'Cliente', width: 220 },
            { field: 'emitente_nome', title: 'Emissor', width: 220 },
            { field: 'valor_total_num', title: 'Valor', width: 120, template: function (row) { return formatDecimal(row.valor_total_num); } },
            { field: 'status_envio', title: 'Status', width: 130 },
            { field: 'chave_nfe', title: 'Chave', width: 250, template: '<span class="small">#= chave_nfe || "" #</span>' },
            { field: 'icms_valor', title: 'ICMS', width: 95, template: function (row) { return formatDecimal(row.icms_valor); } },
            { field: 'cofins_valor', title: 'COFINS', width: 95, template: function (row) { return formatDecimal(row.cofins_valor); } },
            { field: 'pis_valor', title: 'PIS', width: 95, template: function (row) { return formatDecimal(row.pis_valor); } },
            { field: 'ipi_valor', title: 'IPI', width: 95, template: function (row) { return formatDecimal(row.ipi_valor); } },
            { field: 'ibs_valor', title: 'IBS', width: 95, template: function (row) { return formatDecimal(row.ibs_valor); } },
            { field: 'cbs_valor', title: 'CBS', width: 95, template: function (row) { return formatDecimal(row.cbs_valor); } },
            { field: 'is_valor', title: 'IS', width: 95, template: function (row) { return formatDecimal(row.is_valor); } },
            {
                title: 'Arquivos',
                width: 220,
                sortable: false,
                filterable: false,
                template: function (row) {
                    const buttons = [
                        '<a class="btn btn-sm btn-outline-secondary" href="' + buildDetailUrl(row.request_id || '') + '">Detalhe</a>'
                    ];

                    if (row.danfe_url) {
                        buttons.push('<a class="btn btn-sm btn-outline-primary" target="_blank" rel="noreferrer" href="' + appBaseUrl + escapeHtml(row.danfe_url) + '">DANFE</a>');
                    }

                    if (row.xml_url) {
                        buttons.push('<a class="btn btn-sm btn-primary" href="' + appBaseUrl + escapeHtml(row.xml_url) + '">XML</a>');
                    }

                    return '<div class="d-flex flex-wrap gap-2">' + buttons.join('') + '</div>';
                }
            }
        ]
    });

    const grid = $grid.data('kendoGrid');

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
            grid.dataSource.page(1);
            grid.dataSource.read();
        }, 0);
    });

    $clearFilters.on('click', function () {
        form.reset();
    });
}());
