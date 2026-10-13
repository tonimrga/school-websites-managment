<?php

use App\Models\Article;
use App\Models\School;
use App\Support\HtmlSanitizer;

/*
| Rich text is sanitized on save with the allow-list from SPEC section 8:
| p, br, h2, h3, h4, strong, em, u, s, ul, ol, li, a[href], blockquote,
| table, thead, tbody, tr, th, td; links only http, https, mailto, tel.
*/

function sanitize(string $html): string
{
    return app(HtmlSanitizer::class)->sanitize($html);
}

it('keeps every allowed element', function (string $html) {
    expect(sanitize($html))->toBe($html);
})->with([
    '<p>Tekst<br />novi red</p>',
    '<h2>Naslov</h2><h3>Podnaslov</h3><h4>Manji naslov</h4>',
    '<p><strong>podebljano</strong> <em>ukošeno</em> <u>podcrtano</u> <s>precrtano</s></p>',
    '<ul><li>jedan</li></ul><ol><li>dva</li></ol>',
    '<blockquote>Citat</blockquote>',
    '<table><thead><tr><th>Razred</th></tr></thead><tbody><tr><td>5.a</td></tr></tbody></table>',
]);

it('keeps links with an allowed scheme', function (string $href) {
    // Characters such as @ and + may come back as HTML entities, which is
    // the same URL to a browser.
    expect(html_entity_decode(sanitize("<a href=\"{$href}\">poveznica</a>")))
        ->toBe("<a href=\"{$href}\">poveznica</a>");
})->with([
    'https://www.skola.hr/upisi',
    'http://www.skola.hr',
    'mailto:ured@skola.hr',
    'tel:+38511234567',
]);

it('removes the href of links with a forbidden or relative target', function (string $href) {
    expect(sanitize("<a href=\"{$href}\">poveznica</a>"))->toBe('<a>poveznica</a>');
})->with([
    'javascript:alert(1)',
    'JaVaScRiPt:alert(1)',
    'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
    'vbscript:msgbox(1)',
    'ftp://example.com/datoteka',
    '/admin/articles',
    '//evil.example.com',
]);

it('drops dangerous elements together with their content', function (string $html) {
    expect(sanitize("<p>prije</p>{$html}<p>poslije</p>"))->toBe('<p>prije</p><p>poslije</p>');
})->with([
    '<script>alert(1)</script>',
    '<style>body { display: none }</style>',
    '<STYLE type="text/css">p { color: red }</STYLE >',
    '<title>Lažni naslov</title>',
    '<iframe src="https://evil.example.com"></iframe>',
    '<object data="x.swf"></object>',
    '<embed src="x.swf">',
    '<svg onload="alert(1)"><circle r="1"></circle></svg>',
    '<img src="x" onerror="alert(1)">',
    '<form action="https://evil.example.com"><input name="lozinka"></form>',
]);

it('strips every attribute that is not allowed', function (string $html, string $expected) {
    expect(sanitize($html))->toBe($expected);
})->with([
    'event handler' => ['<p onclick="alert(1)">tekst</p>', '<p>tekst</p>'],
    'style' => ['<p style="color:red">tekst</p>', '<p>tekst</p>'],
    'class and id' => ['<h2 class="x" id="y">tekst</h2>', '<h2>tekst</h2>'],
    'link target and onmouseover' => ['<a href="https://skola.hr" target="_blank" onmouseover="alert(1)">x</a>', '<a href="https://skola.hr">x</a>'],
    'table cell spans' => ['<table><tbody><tr><td colspan="2" rowspan="2">x</td></tr></tbody></table>', '<table><tbody><tr><td>x</td></tr></tbody></table>'],
]);

it('unwraps other elements but keeps their text', function (string $html, string $expected) {
    expect(sanitize($html))->toBe($expected);
})->with([
    'div and span' => ['<div><span>tekst</span></div>', 'tekst'],
    'h1' => ['<h1>Naslov</h1>', 'Naslov'],
    'code' => ['<p><code>kod</code></p>', '<p>kod</p>'],
]);

it('does not truncate long articles', function () {
    $html = str_repeat('<p>'.str_repeat('Dugačak tekst vijesti. ', 40).'</p>', 120);

    expect(strlen($html))->toBeGreaterThan(100_000)
        ->and(sanitize($html))->toBe($html);
});

it('sanitizes the article body whenever it is saved', function () {
    $article = createArticle(School::factory()->create(), [
        'body' => '<p onclick="alert(1)">Dan škole</p><script>alert(1)</script><a href="javascript:alert(1)">klik</a>',
    ]);

    expect(Article::withoutGlobalScopes()->find($article->id)->body)
        ->toBe('<p>Dan škole</p><a>klik</a>');
});
