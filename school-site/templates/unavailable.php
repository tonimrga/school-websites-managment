<?php
/*
 * Shown when the API cannot be reached or the school is not active. There is
 * no school data here, so the page stands alone, in the default theme.
 */
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Stranica je privremeno nedostupna</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body data-theme="<?= e(THEMES[0]) ?>">
<main class="container message message--standalone">
    <h1 class="page-title">Stranica je privremeno nedostupna</h1>
    <p>Mrežne stranice doma trenutačno nisu dostupne. Pokušajte ponovno za nekoliko minuta.</p>
    <p>Hvala na strpljenju.</p>
</main>
</body>
</html>
