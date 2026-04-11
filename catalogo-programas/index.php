<?php

$queryString = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? '?' . $_SERVER['QUERY_STRING']
    : '';

header('Location: /index.php/catalogo-programas' . $queryString, true, 302);
exit;
