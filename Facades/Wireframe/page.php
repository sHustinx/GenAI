<?php
use axenox\GenAI\Facades\Wireframe\WireframeElementFactory;

/** @var string|null $source Saved UXON supplied by WireframeFacade for the UXON tab. */
/** @var array|null $wireframe Projected widget tree supplied by WireframeFacade for the Wireframe tab. */
/** @var array{node: array, title: string, backUrl: string}|null $dialog The selected dialog preview. */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$renderMenu = static function (array $entries) use (&$renderMenu, $escape, $pageAlias): void {
    if ($entries === []) {
        return;
    }
    echo '<ul>';
    foreach ($entries as $entry) {
        $current = strcasecmp($entry['alias'], $pageAlias) === 0 ? ' aria-current="page"' : '';
        echo '<li><a' . $current . ' href="' . $escape($entry['url']) . '">' . $escape($entry['label']) . '</a>';
        $renderMenu($entry['children']);
        echo '</li>';
    }
    echo '</ul>';
};
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $escape($dialog['title'] ?? $pageName) ?> | Wireframe</title>
<style>
    * { box-sizing: border-box; }
    body { margin: 0; color: #162831; background: #f4f7f8; font: 15px system-ui, sans-serif; }
    a { color: #075671; overflow-wrap: anywhere; }
    a[aria-current] { font-weight: bold; }
    h1 { margin: 0 0 .8rem; font-size: 1.4rem; }
    ul { padding-left: 1.25rem; }
    li { margin: .35rem 0; }
    small { color: #435760; }
    .shell { display: grid; grid-template-columns: minmax(11rem, 18%) minmax(0, 1fr); margin: 1rem; border: 1px solid #859ba4; background: white; min-height: calc(100vh - 2rem); }
    .menu { padding: 1rem; border-right: 1px solid #859ba4; background: #f4f7f8; }
    .top { display: flex; justify-content: space-between; flex-wrap: wrap; align-items: stretch; border-bottom: 1px solid #859ba4; }
    .context { padding: .65rem .9rem; }
    /* Right-hand group: the normal-page link followed by the view tabs, which fill the bar height. */
    .actions { display: flex; align-items: stretch; margin-left: auto; }
    .actions > a, .tabs a { display: flex; align-items: center; padding: .65rem .9rem; }
    .tabs { display: flex; border-left: 1px solid #859ba4; }
    .tabs a[aria-current] { background: #165e75; color: white; text-decoration: none; }
    .body { padding: 1rem; }
    .widget { border: 1px solid #9db0b8; border-radius: .2rem; padding: .55rem; min-width: 0; overflow-wrap: anywhere; }
    .widget header { margin-bottom: .25rem; }
    .group { display: grid; gap: .4rem; margin-top: .4rem; }
    .group-label { margin-top: .55rem; font-weight: 600; }
    /* Data widgets: filter bar, then a table with headers and skeleton rows (no data is ever shown). */
    .filter-bar { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .4rem .8rem; margin: .4rem 0; padding: .5rem; background: #f4f7f8; border: 1px solid #dbe3e6; border-radius: .2rem; }
    .filter-item { display: flex; flex-direction: column; gap: .15rem; flex: 0 1 11rem; min-width: 8rem; font-size: .85rem; color: #435760; }
    .filter-item .field { max-width: none; margin-top: 0; height: 1.6rem; }
    .toolbar-row { margin: .4rem 0; }
    .table-wrap { overflow-x: auto; margin-top: .4rem; }
    .data-table { border-collapse: collapse; min-width: 100%; font-size: .85rem; }
    .data-table th { padding: .35rem .55rem; border: 1px solid #c5d2d7; background: #e8eff1; color: #162831; font-weight: 600; text-align: left; vertical-align: bottom; white-space: nowrap; }
    .data-table th.column-group { text-align: center; background: #dde7ea; }
    .data-table td { padding: .45rem .55rem; border: 1px solid #e1e8eb; }
    .data-table tbody tr:nth-child(even) td { background: #f7f9fa; }
    .skeleton { display: block; width: 70%; height: .5rem; border-radius: 1rem; background: #e1e8eb; }
    .row { display: flex; align-items: stretch; }
    .row > * { flex: 1 1 0; }
    /* Side by side, wrapping once a child would get narrower than ~14rem. */
    .group.flow { display: flex; flex-wrap: wrap; align-items: flex-start; }
    .group.flow > * { flex: 1 1 14rem; }
    /* Value placeholders look like form fields, never like buttons. */
    .field { display: block; max-width: 18rem; height: 1.7rem; margin-top: .2rem; border-radius: .15rem; }
    .field.input { border: 1px solid #9db0b8; background: white; box-shadow: inset 0 1px 2px rgba(22, 40, 49, .08); }
    /* A display value is drawn as a skeleton text line, not a box, so it never looks like an input or a button. */
    .field.display { width: 9rem; height: .55rem; margin: .5rem 0 .25rem; border-radius: 1rem; background: #cfdade; }
    /* Shape hints per value widget; values, colors, options, and images are never shown. */
    .field.select, .field.date { display: flex; justify-content: flex-end; align-items: center; padding-right: .45rem; color: #6b7f88; font-size: .8rem; }
    .field.textarea { height: 3.6rem; }
    .field.checkbox { width: 1.1rem; height: 1.1rem; border: 1px solid #859ba4; background: white; }
    .field.color-indicator { display: flex; align-items: center; gap: .4rem; width: auto; height: auto; margin: .3rem 0 .1rem; background: none; }
    .field.color-indicator .dot { width: .8rem; height: .8rem; border-radius: 50%; background: #9db0b8; }
    .field.color-indicator::after { content: ""; width: 7rem; height: .55rem; border-radius: 1rem; background: #cfdade; }
    .field.progress { height: .8rem; background: #e3eaed; overflow: hidden; }
    .field.progress .fill { display: block; width: 40%; height: 100%; background: #9db0b8; }
    .field.image { height: 5rem; max-width: 8rem; background: repeating-linear-gradient(45deg, #e3eaed 0 .5rem, #edf2f4 .5rem 1rem); }
    /* NavTiles: one wrapping row of tiles per group, like the Core tile menu. */
    .tiles { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .3rem; }
    .tile { display: flex; flex-direction: column; gap: .2rem; width: 11rem; min-height: 4.5rem; padding: .55rem .65rem; border: 1px solid #9db0b8; border-top: 3px solid #165e75; border-radius: .2rem; background: white; color: #162831; text-decoration: none; }
    .tile small { overflow-wrap: anywhere; }
    a.tile:hover, a.tile:focus-visible { background: #e8f1f4; }
    .tile.inert { border-top-color: #9db0b8; background: #f7f9fa; cursor: help; }
    .map-area { display: flex; align-items: center; justify-content: center; height: 8rem; margin-top: .2rem; background: #e8eff1; color: #6b7f88; font-size: .85rem; letter-spacing: .05em; text-transform: uppercase; }
    /* One chip shape for every button: solid when it opens a preview, dashed when inert. */
    .group.buttons { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; }
    .button { display: inline-block; max-width: 100%; padding: .25rem .65rem; border: 1px solid #165e75; border-radius: .2rem; background: white; color: #165e75; font-size: .9rem; line-height: 1.3; text-decoration: none; white-space: normal; overflow-wrap: anywhere; }
    a.button:hover, a.button:focus-visible { background: #165e75; color: white; }
    .button.inert { border-style: dashed; border-color: #859ba4; background: #f2f6f8; color: #40545e; cursor: help; }
    pre { white-space: pre-wrap; overflow-wrap: anywhere; padding: 1rem; background: #f4f7f8; }
    /* Script-free tabs: labels form the strip, the panel after the checked radio is shown. */
    .tabset { display: flex; flex-wrap: wrap; margin-top: .4rem; }
    .tabset > input { position: absolute; opacity: 0; pointer-events: none; }
    .tabset > label { order: 0; padding: .35rem .8rem; border: 1px solid #9db0b8; border-bottom: 0; margin-right: -1px; background: #f4f7f8; cursor: pointer; }
    .tabset > input:checked + label { background: white; font-weight: 600; position: relative; top: 1px; }
    .tabset > input:focus-visible + label { outline: 2px solid #075671; }
    .tabset > .tab-panel { order: 1; width: 100%; display: none; border-top: 1px solid #9db0b8; padding-top: .4rem; }
    .tabset > input:checked + label + .tab-panel { display: block; }
    @media (max-width: 50rem) {
        .shell { grid-template-columns: 1fr; margin: .5rem; }
        .menu { border-right: 0; border-bottom: 1px solid #859ba4; }
        .row { display: grid; }
    }
</style>
</head>
<body>
<div class="shell">
    <nav class="menu" aria-label="App menu">
        <strong><?= $escape($appName) ?></strong>
        <?php $renderMenu($menu); ?>
    </nav>
    <main>
        <div class="top">
            <nav class="context" aria-label="Breadcrumb">
                <?= $escape($appName) ?> / <?= $escape($pageName) ?>
                <?php if ($dialog !== null): ?>
                    / <?= $escape($dialog['title']) ?>
                <?php endif; ?>
            </nav>
            <div class="actions">
                <?php if ($normalPageUrl !== null): ?>
                    <a href="<?= $escape($normalPageUrl) ?>" target="_blank" rel="noopener">Open normal page</a>
                <?php endif; ?>
                <nav class="tabs" aria-label="Page views">
                    <a href="<?= $escape($pageUrl) ?>"<?= $view === 'wireframe' && $dialog === null ? ' aria-current="page"' : '' ?>>Wireframe</a>
                    <?php if ($canViewUxon): ?>
                        <a href="<?= $escape($pageUrl . '?view=uxon') ?>"<?= $view === 'uxon' ? ' aria-current="page"' : '' ?>>UXON</a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
        <div class="body">
            <?php if ($dialog !== null): ?>
                <p><a href="<?= $escape($dialog['backUrl']) ?>">Back to previous preview</a></p>
            <?php endif; ?>
            <h1><?= $escape($dialog['title'] ?? $pageName) ?></h1>
            <?php if ($source !== null): ?>
                <pre><code><?= $escape($source) ?></code></pre>
            <?php elseif ($wireframe !== null): ?>
                <?php /* Reuse the projecting factory so both phases pick the same elements. */ ?>
                <?= ($elements ?? new WireframeElementFactory())->buildHtml($wireframe) ?>
            <?php else: ?>
                <p><?= $isUxon ? 'This page has no visible configured widgets.' : 'This page does not have saved widget UXON to preview.' ?></p>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
