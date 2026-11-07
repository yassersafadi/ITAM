<?php page_title(t('nav.help')); ?>
<div class="page-head">
    <div>
        <h1><?= icon('book', 'icon icon-title') ?> <?= e(t('nav.help')) ?></h1>
        <p class="muted"><?= e(t('help.subtitle')) ?></p>
    </div>
    <div class="page-actions"><a class="btn btn-ghost" href="<?= url('help/print') ?>" target="_blank"><?= icon('printer') ?> <?= e(t('help.print_all')) ?></a></div>
</div>

<div class="help-search card">
    <?= icon('search') ?>
    <input type="search" placeholder="<?= e(t('help.search')) ?>" data-help-search autofocus>
</div>
<div class="help-results" data-help-results hidden></div>

<div data-help-groups>
    <?php foreach ($groups as $group => $items): ?>
        <section class="help-group">
            <h2><?= e(\ITAM\Services\Help::groupName($group)) ?></h2>
            <div class="help-cards">
                <?php foreach ($items as $it): ?>
                    <a class="card help-card" href="<?= url('help/' . $it['slug']) ?>">
                        <span class="kpi-icon tone-teal"><?= icon($it['icon']) ?></span>
                        <span><strong><?= e($it['title']) ?></strong><small class="muted"><?= e($it['summary']) ?></small></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<script>
    // Instant search across every section (all words must appear; the title counts double).
    (function () {
        var index = <?= json_encode($index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;
        var input = document.querySelector('[data-help-search]'), box = document.querySelector('[data-help-results]'), groups = document.querySelector('[data-help-groups]');
        var base = <?= json_encode(url('help/')) ?>, none = <?= json_encode(t('help.no_results')) ?>;
        var esc = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
        input.addEventListener('input', function () {
            var words = input.value.toLowerCase().trim().split(/\s+/).filter(function (w) { return w.length > 1; });
            if (!words.length) { box.hidden = true; groups.hidden = false; return; }
            var hits = index.map(function (s) {
                var score = 0;
                for (var i = 0; i < words.length; i++) {
                    if (s.text.indexOf(words[i]) === -1) return null;
                    score += s.title.toLowerCase().indexOf(words[i]) !== -1 ? 3 : 1;
                }
                var at = s.text.indexOf(words[0]);
                return { s: s, score: score, snippet: s.text.substring(Math.max(0, at - 60), at + 140) };
            }).filter(Boolean).sort(function (a, b) { return b.score - a.score; });
            box.innerHTML = hits.length ? hits.slice(0, 20).map(function (h) {
                return '<a class="card help-hit" href="' + base + h.s.slug + '"><strong>' + esc(h.s.title) + '</strong><small class="muted">…' + esc(h.snippet) + '…</small></a>';
            }).join('') : '<p class="muted">' + esc(none) + '</p>';
            box.hidden = false; groups.hidden = true;
        });
    })();
</script>
