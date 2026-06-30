## Kendo UI local

Esta pasta guarda apenas os assets de runtime do Kendo UI usados pelas telas Symfony/Twig do projeto.

Estrutura esperada:

- `js/`: bundles JavaScript do Kendo
- `styles/`: temas, fontes e estilos do Kendo
- `license-agreements/`: arquivos de licenca distribuidos com o pacote
- `kendo-license.js`: arquivo de licenciamento do vendor, quando aplicavel

Diretrizes para este repositório:

- nao versionar `examples/`, `src/`, `typescript/`, `mjs`, `vsdoc` ou `apptemplates`
- preferir carregar Kendo por tela em `page_styles` e `page_scripts`
- manter CSS/JS especificos da aplicacao fora desta pasta, por exemplo em `catalog-assets/monitor/`

Exemplo de uso em Twig:

```twig
{% block page_styles %}
    <link rel="stylesheet" href="{{ app.request.basePath ~ '/catalog-assets/kendo/styles/kendo.default-v2.min.css' }}">
{% endblock %}

{% block page_scripts %}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ app.request.basePath ~ '/catalog-assets/kendo/js/kendo.all.min.js' }}"></script>
{% endblock %}
```
