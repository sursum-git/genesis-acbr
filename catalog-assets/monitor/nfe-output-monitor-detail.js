(function () {
    if (typeof window.kendo === 'undefined' || typeof window.jQuery === 'undefined') {
        return;
    }

    const $ = window.jQuery;

    const itemsGrid = document.getElementById('nfe-output-items-grid');
    if (itemsGrid) {
        let items = [];
        try {
            items = JSON.parse(itemsGrid.dataset.items || '[]');
        } catch (error) {
            items = [];
        }

        $('#nfe-output-items-grid').kendoGrid({
            dataSource: items,
            sortable: true,
            scrollable: true,
            resizable: true,
            pageable: false,
            noRecords: {
                template: 'Nenhum item estruturado para esta nota.'
            },
            detailTemplate: function (row) {
                if (!row.impostos || row.impostos.length === 0) {
                    return '<div class="p-3 text-muted">Nenhum imposto estruturado para este item.</div>';
                }

                const lines = row.impostos.map(function (imposto) {
                    const parts = [
                        '<strong>' + window.kendo.htmlEncode(imposto.nome || '') + '</strong>'
                    ];

                    if (imposto.cst) {
                        parts.push('CST: ' + window.kendo.htmlEncode(imposto.cst));
                    }

                    if (imposto.base_calculo) {
                        parts.push('Base: ' + window.kendo.htmlEncode(imposto.base_calculo));
                    }

                    if (imposto.aliquota) {
                        parts.push('Alíquota: ' + window.kendo.htmlEncode(imposto.aliquota));
                    }

                    if (imposto.valor) {
                        parts.push('Valor: ' + window.kendo.htmlEncode(imposto.valor));
                    }

                    return '<div class="monitor-item-tax-line">' + parts.join(' | ') + '</div>';
                });

                return '<div class="p-3">' + lines.join('') + '</div>';
            },
            columns: [
                { field: 'numero', title: 'Item', width: 80 },
                { field: 'codigo_produto', title: 'Código', width: 120 },
                { field: 'descricao', title: 'Descrição', width: 340 },
                { field: 'codigo_ncm', title: 'NCM', width: 100 },
                { field: 'cfop', title: 'CFOP', width: 90 },
                { field: 'quantidade', title: 'Qtd.', width: 100 },
                { field: 'unidade', title: 'Un.', width: 80 },
                { field: 'valor_unitario', title: 'Unitário', width: 110 },
                { field: 'valor_total', title: 'Total', width: 110 },
                { field: 'valor_aproximado_tributos', title: 'Trib. aprox.', width: 120 }
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
