<?php
/*
 * Shown when config.php has not been filled in for this school (see
 * index.php). Meant for the person installing the site.
 */
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Stranica nije konfigurirana</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body data-theme="<?= e(THEMES[0]) ?>">
<main class="container message message--standalone">
    <h1 class="page-title">Stranica nije konfigurirana</h1>
    <p>Ove mrežne stranice još nisu povezane s domom.</p>
    <p>Administrator treba u datoteci <code>config.php</code> upisati adresu API-ja (<code>API_BASE_URL</code>), oznaku doma (<code>SCHOOL_SLUG</code>) i adresu stranica (<code>SITE_URL</code>).</p>
</main>
</body>
</html>
