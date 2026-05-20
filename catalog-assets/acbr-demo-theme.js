(function () {
    function getModuleDescription(moduleName) {
        var descriptions = {
            ACBrBoleto: 'Demo unificada para configuracao, emissao e operacoes de boletos.',
            ACBrCEP: 'Consulta de CEP e logradouro com configuracao de WebService.',
            ACBrConsultaCNPJ: 'Consulta cadastral com retorno estruturado da empresa.',
            ACBrCTe: 'Operacoes de configuracao, consulta, eventos e emissao de CTe.',
            ACBrExtratoAPI: 'Operacoes de extrato e integracao com servicos HTTP do modulo.',
            ACBrGTIN: 'Consulta e validacao de GTIN com configuracao compartilhada.',
            ACBrIBGE: 'Consulta de municipios, UFs e dados do IBGE.',
            ACBrMDFe: 'Configuracao, emissao e consultas do manifesto eletronico.',
            ACBrNCMs: 'Consulta de NCMs com configuracao centralizada do modulo.',
            ACBrNFCom: 'Operacoes fiscais do NFCom com interface padronizada.',
            ACBrNFe: 'Configuracao, envio, consultas, eventos e distribuicao de NFe.',
            ACBrNFSe: 'Configuracao, envio e consultas de NFSe em provedores padrao e especificos.',
            ACBrReinf: 'Operacoes de configuracao e consulta para eventos Reinf.'
        };

        return descriptions[moduleName] || 'Interface demo padronizada para o modulo ACBr selecionado.';
    }

    function splitTitleParts(title) {
        var parts = String(title || '').split(' - ');
        return {
            moduleName: parts[0] || 'ACBr Demo',
            mode: parts[1] || ''
        };
    }

    function classifyButton(button) {
        var text = (button.value || button.textContent || '').toLowerCase();

        if (button.closest('.tabAbas')) {
            button.classList.add('acbr-demo-skip-button-style');
            return;
        }

        button.classList.add('btn');

        if (text.indexOf('carregar') !== -1) {
            button.classList.add('btn-outline-secondary');
            return;
        }

        if (text.indexOf('salvar') !== -1) {
            button.classList.add('btn-warning');
            return;
        }

        if (text.indexOf('consult') !== -1 || text.indexOf('valid') !== -1) {
            button.classList.add('btn-primary');
            return;
        }

        if (text.indexOf('imprimir') !== -1 || text.indexOf('pdf') !== -1) {
            button.classList.add('btn-outline-primary');
            return;
        }

        if (text.indexOf('cancel') !== -1 || text.indexOf('inutil') !== -1) {
            button.classList.add('btn-danger');
            return;
        }

        if (text.indexOf('enviar') !== -1 || text.indexOf('gerar') !== -1 || text.indexOf('emit') !== -1) {
            button.classList.add('btn-success');
            return;
        }

        button.classList.add('btn-primary');
    }

    function buildHero(titleParts) {
        var hero = document.createElement('header');
        hero.className = 'acbr-demo-hero';
        hero.innerHTML =
            '<img src="https://svn.code.sf.net/p/acbr/code/trunk2/Exemplos/ACBrTEFD/Android/ACBr_96_96.png" alt="ACBr Logo">' +
            '<div>' +
            '<h1>' + titleParts.moduleName + '</h1>' +
            '<p>' + getModuleDescription(titleParts.moduleName) + '</p>' +
            (titleParts.mode ? '<span class="acbr-demo-hero-badge">' + titleParts.mode + '</span>' : '') +
            '</div>';

        return hero;
    }

    function enhanceSimpleSections(scope) {
        var form = scope.querySelector('form');
        if (form && !form.classList.contains('acbr-demo-card') && !form.closest('.cfgPanelEsquerda')) {
            form.classList.add('acbr-demo-card');
        }

        var detailsGrid = scope.querySelector('.retornoCampos');
        if (detailsGrid && !detailsGrid.classList.contains('acbr-demo-card') && !detailsGrid.closest('.cfgPanelDireita')) {
            var detailsCard = document.createElement('section');
            detailsCard.className = 'acbr-demo-card';
            var heading = scope.querySelector('h2');
            if (heading) {
                detailsCard.appendChild(heading);
            } else {
                var fallbackHeading = document.createElement('h2');
                fallbackHeading.textContent = 'Detalhes';
                detailsCard.appendChild(fallbackHeading);
            }
            detailsGrid.parentNode.insertBefore(detailsCard, detailsGrid);
            detailsCard.appendChild(detailsGrid);
        }

        var result = scope.querySelector('#result');
        if (result && !result.closest('.cfgPanelDireita')) {
            var resultLabel = scope.querySelector('label[for="result"]');
            var resultCard = document.createElement('section');
            resultCard.className = 'acbr-demo-card';
            if (resultLabel) {
                resultCard.appendChild(resultLabel);
            } else {
                var fallbackLabel = document.createElement('label');
                fallbackLabel.setAttribute('for', 'result');
                fallbackLabel.textContent = 'Retorno';
                resultCard.appendChild(fallbackLabel);
            }
            result.parentNode.insertBefore(resultCard, result);
            resultCard.appendChild(result);
        }
    }

    function applyTheme() {
        var body = document.body;
        if (!body || body.dataset.acbrDemoThemeApplied === '1') {
            return;
        }

        body.dataset.acbrDemoThemeApplied = '1';
        body.classList.add('acbr-demo-theme');

        var titleParts = splitTitleParts(document.title);
        var page = document.createElement('div');
        page.className = 'acbr-demo-page';

        var layout = document.createElement('div');
        layout.className = 'acbr-demo-layout';

        var existingHero = body.querySelector('.page-header, .tituloColunas');
        if (existingHero) {
            existingHero.classList.add('acbr-demo-hero');
        } else {
            page.appendChild(buildHero(titleParts));
        }

        var nodes = Array.prototype.slice.call(body.childNodes);
        var firstScript = body.querySelector('script');

        nodes.forEach(function (node) {
            if (node.nodeType === Node.ELEMENT_NODE && node.tagName === 'SCRIPT') {
                return;
            }
            if (node.nodeType === Node.TEXT_NODE && !node.textContent.trim()) {
                return;
            }
            layout.appendChild(node);
        });

        if (existingHero && existingHero.parentNode === layout) {
            page.appendChild(existingHero);
        }

        if (layout.querySelector('.cfgPanelEsquerda, .cfgPanelDireita')) {
            layout.classList.remove('acbr-demo-layout-stack');
        } else {
            layout.classList.add('acbr-demo-layout-stack');
        }

        page.appendChild(layout);
        body.insertBefore(page, firstScript);

        layout.querySelectorAll('input[type="button"], button').forEach(classifyButton);
        layout.querySelectorAll('select').forEach(function (select) {
            select.classList.add('form-select');
        });
        layout.querySelectorAll('input[type="text"], input[type="password"], input[type="number"], input[type="email"], input[type="date"], input[type="time"], input[type="search"], textarea').forEach(function (field) {
            field.classList.add('form-control');
        });
        layout.querySelectorAll('.buttons').forEach(function (container) {
            container.classList.add('d-flex', 'flex-wrap', 'gap-2');
        });

        enhanceSimpleSections(layout);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyTheme);
    } else {
        applyTheme();
    }
})();
