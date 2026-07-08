(function () {
    if (typeof window.kendo === 'undefined' || typeof window.jQuery === 'undefined') {
        return;
    }

    const $ = window.jQuery;

    function parseDecimal(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }

        const normalized = String(value).replace(',', '.');
        const parsed = Number.parseFloat(normalized);

        return Number.isNaN(parsed) ? null : parsed;
    }

    function formatDecimal(value) {
        return value === null || value === undefined || value === '' ? '' : window.kendo.toString(value, 'n2');
    }

    const itemsGrid = document.getElementById('nfe-output-items-grid');
    if (itemsGrid) {
        let items = [];
        try {
            items = JSON.parse(itemsGrid.dataset.items || '[]');
        } catch (error) {
            items = [];
        }

        items = items.map(function (item) {
            return Object.assign({}, item, {
                quantidade_num: parseDecimal(item.quantidade),
                valor_unitario_num: parseDecimal(item.valor_unitario),
                valor_total_num: parseDecimal(item.valor_total),
                valor_aproximado_tributos_num: parseDecimal(item.valor_aproximado_tributos),
                impostos: Array.isArray(item.impostos) ? item.impostos.map(function (imposto) {
                    return Object.assign({}, imposto, {
                        valor_num: parseDecimal(imposto.valor)
                    });
                }) : []
            });
        });

        $('#nfe-output-items-grid').kendoGrid({
            dataSource: items,
            sortable: true,
            scrollable: true,
            resizable: true,
            pageable: false,
            noRecords: {
                template: 'Nenhum item estruturado para esta nota.'
            },
            detailInit: function (event) {
                $('<div class="monitor-item-tax-grid"></div>')
                    .appendTo(event.detailCell)
                    .kendoGrid({
                        dataSource: event.data.impostos || [],
                        sortable: true,
                        scrollable: false,
                        pageable: false,
                        noRecords: {
                            template: 'Nenhum imposto estruturado para este item.'
                        },
                        columns: [
                            { field: 'nome', title: 'Imposto', width: 140 },
                            { field: 'cst', title: 'CST', width: 100 },
                            { field: 'modalidade', title: 'Modalidade', width: 160 },
                            { field: 'modalidade_api', title: 'Tag API', width: 160 },
                            {
                                field: 'valor_num',
                                title: 'Valor',
                                width: 120,
                                template: function (row) {
                                    return formatDecimal(row.valor_num);
                                }
                            }
                        ]
                    });
            },
            columns: [
                { field: 'numero', title: 'Item', width: 80 },
                { field: 'codigo_produto', title: 'Código', width: 120 },
                { field: 'descricao', title: 'Descrição', width: 340 },
                { field: 'codigo_ncm', title: 'NCM', width: 100 },
                { field: 'cfop', title: 'CFOP', width: 90 },
                {
                    field: 'quantidade_num',
                    title: 'Qtd.',
                    width: 100,
                    template: function (row) {
                        return formatDecimal(row.quantidade_num);
                    }
                },
                { field: 'unidade', title: 'Un.', width: 80 },
                {
                    field: 'valor_unitario_num',
                    title: 'Unitário',
                    width: 110,
                    template: function (row) {
                        return formatDecimal(row.valor_unitario_num);
                    }
                },
                {
                    field: 'valor_total_num',
                    title: 'Total',
                    width: 110,
                    template: function (row) {
                        return formatDecimal(row.valor_total_num);
                    }
                },
                {
                    field: 'valor_aproximado_tributos_num',
                    title: 'Trib. aprox.',
                    width: 120,
                    template: function (row) {
                        return formatDecimal(row.valor_aproximado_tributos_num);
                    }
                }
            ]
        });
    }

    const technicalTabs = document.getElementById('nfe-output-technical-tabs');
    if (technicalTabs) {
        $('#nfe-output-technical-tabs').kendoTabStrip({
            animation: false
        });
    }
}());
