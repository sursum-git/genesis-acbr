(function () {
  const targetPath = '/nfe/consultas/consultar-com-chave-xml';

  function getXmlExample() {
    const node = document.getElementById('swagger-data');
    if (!node) {
      return null;
    }

    try {
      const data = JSON.parse(node.innerText);
      return data?.spec?.paths?.[targetPath]?.post?.requestBody?.content?.['application/xml']?.example ?? null;
    } catch (_error) {
      return null;
    }
  }

  function isTargetBlock(block) {
    const method = block.querySelector('.opblock-summary-method');
    const path = block.querySelector('.opblock-summary-path');

    return method
      && path
      && method.textContent.trim().toLowerCase() === 'post'
      && path.textContent.indexOf(targetPath) !== -1;
  }

  function isXmlSelected(block) {
    const select = block.querySelector('select.body-param-content-type');
    if (!select) {
      return true;
    }

    return /xml/i.test(select.value || '');
  }

  function normalizeCurrentValue(value) {
    return (value || '').trim().replace(/\r\n/g, '\n');
  }

  function patchBlock(block, xmlExample) {
    if (!isTargetBlock(block) || !isXmlSelected(block)) {
      return;
    }

    const preview = block.querySelector('.body-param__example');
    if (preview && normalizeCurrentValue(preview.textContent) !== normalizeCurrentValue(xmlExample)) {
      preview.textContent = xmlExample;
    }

    const textarea = block.querySelector('textarea.body-param__text');
    if (textarea && normalizeCurrentValue(textarea.value) !== normalizeCurrentValue(xmlExample)) {
      textarea.value = xmlExample;
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
      textarea.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }

  function patchAll() {
    const xmlExample = getXmlExample();
    if (!xmlExample) {
      return;
    }

    document.querySelectorAll('.opblock').forEach((block) => patchBlock(block, xmlExample));
  }

  function start() {
    patchAll();

    const observer = new MutationObserver(() => {
      window.requestAnimationFrame(patchAll);
    });

    observer.observe(document.body, { childList: true, subtree: true });

    document.body.addEventListener('change', function (event) {
      if (event.target instanceof HTMLSelectElement && event.target.classList.contains('body-param-content-type')) {
        window.requestAnimationFrame(patchAll);
      }
    });

    document.body.addEventListener('click', function () {
      window.requestAnimationFrame(patchAll);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    start();
  }
})();
