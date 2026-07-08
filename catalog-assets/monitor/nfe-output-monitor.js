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
    const detailUrlTemplate = root.dataset.detailUrlTemplate || '';
    const appBaseUrl = basePath + '/index.php';
    const $grid = $('#nfe-output-monitor-grid');
    const $filterWindow = $('#nfe-output-filter-window');
    const $openFilters = $('#open-nfe-output-filters');
    const $clearFilters = $('#clear-nfe-output-filters');
    let cachedFilterOptions = null;
    let activeColumnField = '';
    let activeColumnTitle = '';
    let activeColumnHeader = null;

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
        const dateFromPicker = $('#filter-date-from').data('kendoDatePicker');
        const dateToPicker = $('#filter-date-to').data('kendoDatePicker');

        return {
            date_from: dateFromPicker && dateFromPicker.value() ? window.kendo.toString(dateFromPicker.value(), 'yyyy-MM-dd') : '',
            date_to: dateToPicker && dateToPicker.value() ? window.kendo.toString(dateToPicker.value(), 'yyyy-MM-dd') : '',
            numero_nota: $('#filter-numero-nota').val() || '',
            cliente: $('#filter-cliente').data('kendoAutoComplete') ? $('#filter-cliente').data('kendoAutoComplete').value() : ($('#filter-cliente').val() || ''),
            assinante: $('#filter-assinante').data('kendoAutoComplete') ? $('#filter-assinante').data('kendoAutoComplete').value() : ($('#filter-assinante').val() || ''),
            emissor: $('#filter-emissor').data('kendoAutoComplete') ? $('#filter-emissor').data('kendoAutoComplete').value() : ($('#filter-emissor').val() || ''),
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

    function ensureFilterOptions() {
        if (!filterOptionsUrl) {
            return $.Deferred().resolve({
                clientes: [],
                emissores: [],
                assinantes: []
            }).promise();
        }

        if (cachedFilterOptions !== null) {
            return $.Deferred().resolve(cachedFilterOptions).promise();
        }

        return $.getJSON(filterOptionsUrl).then(function (response) {
            cachedFilterOptions = response || {
                clientes: [],
                emissores: [],
                assinantes: []
            };

            return cachedFilterOptions;
        });
    }

    function buildSearchField(selector, values, placeholder) {
        $(selector).kendoAutoComplete({
            dataSource: Array.isArray(values) ? values : [],
            filter: 'contains',
            minLength: 1,
            placeholder: placeholder,
            clearButton: true
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

        ensureFilterOptions().done(function (response) {
            const options = response || cachedFilterOptions || {};
            buildSearchField('#filter-cliente', options.clientes || [], 'Buscar cliente');
            buildSearchField('#filter-assinante', options.assinantes || [], 'Buscar assinante');
            buildSearchField('#filter-emissor', options.emissores || [], 'Buscar emissor');
        }).fail(function () {
            buildSearchField('#filter-cliente', [], 'Buscar cliente');
            buildSearchField('#filter-assinante', [], 'Buscar assinante');
            buildSearchField('#filter-emissor', [], 'Buscar emissor');
        });

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
                width: 165,
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
                width: 110,
                template: function (row) { return escapeHtml(row.numero_nota || ''); },
                filterable: { cell: { operator: 'contains', showOperators: false } }
            },
            { field: 'cliente', title: 'Cliente', width: 220, filterable: { cell: { operator: 'contains', showOperators: false } } },
            { field: 'emitente_nome', title: 'Emissor', width: 220, filterable: { cell: { operator: 'contains', showOperators: false } } },
            { field: 'valor_total_num', title: 'Valor', width: 120, template: function (row) { return formatDecimal(row.valor_total_num); }, filterable: { cell: { operator: 'eq', showOperators: false } } },
            { field: 'status_envio', title: 'Status', width: 130, filterable: { cell: { operator: 'contains', showOperators: false } } },
            { field: 'chave_nfe', title: 'Chave', width: 250, template: '<span class="small">#= chave_nfe || "" #</span>', filterable: { cell: { operator: 'contains', showOperators: false } } },
            { field: 'icms_valor', title: 'ICMS', width: 95, template: function (row) { return formatDecimal(row.icms_valor); }, filterable: { cell: { operator: 'eq', showOperators: false } } },
            { field: 'cofins_valor', title: 'COFINS', width: 95, template: function (row) { return formatDecimal(row.cofins_valor); }, filterable: { cell: { operator: 'eq', showOperators: false } } },
            { field: 'pis_valor', title: 'PIS', width: 95, template: function (row) { return formatDecimal(row.pis_valor); }, filterable: { cell: { operator: 'eq', showOperators: false } } },
            { field: 'ipi_valor', title: 'IPI', width: 95, template: function (row) { return formatDecimal(row.ipi_valor); }, filterable: { cell: { operator: 'eq', showOperators: false } } },
            { field: 'ibs_valor', title: 'IBS', width: 95, template: function (row) { return formatDecimal(row.ibs_valor); }, filterable: { cell: { operator: 'eq', showOperators: false } } },
            { field: 'cbs_valor', title: 'CBS', width: 95, template: function (row) { return formatDecimal(row.cbs_valor); }, filterable: { cell: { operator: 'eq', showOperators: false } } },
            { field: 'is_valor', title: 'IS', width: 95, template: function (row) { return formatDecimal(row.is_valor); }, filterable: { cell: { operator: 'eq', showOperators: false } } },
            {
                title: 'Arquivos',
                width: 160,
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

                    return '<div class="d-flex flex-wrap gap-2">' + buttons.join('') + '</div>';
                }
            }
        ]
    });

    const grid = $grid.data('kendoGrid');

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
                const autoComplete = $(selector).data('kendoAutoComplete');
                if (autoComplete) {
                    autoComplete.value('');
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
