<?php
use ITAM\Services\Help;

// Renders a section's blocks. Headings get anchors for the on-page contents.
foreach ($blocks as $n => [$type, $content]):
    switch ($type):
        case 'h': ?>
            <h2 id="s<?= $n ?>"><?= Help::inline($content) ?></h2>
            <?php break;
        case 'p': ?>
            <p><?= Help::inline($content) ?></p>
            <?php break;
        case 'steps': ?>
            <ol class="help-steps"><?php foreach ($content as $s): ?><li><?= Help::inline($s) ?></li><?php endforeach; ?></ol>
            <?php break;
        case 'list': ?>
            <ul class="help-list"><?php foreach ($content as $s): ?><li><?= Help::inline($s) ?></li><?php endforeach; ?></ul>
            <?php break;
        case 'fields': ?>
            <dl class="help-fields"><?php foreach ($content as [$label, $desc]): ?><dt><?= Help::inline($label) ?></dt><dd><?= Help::inline($desc) ?></dd><?php endforeach; ?></dl>
            <?php break;
        case 'tip':
        case 'warn': ?>
            <div class="callout callout-<?= $type === 'tip' ? 'info' : 'warning' ?> help-note"><?= icon($type === 'tip' ? 'info' : 'alert') ?><div><?= Help::inline($content) ?></div></div>
            <?php break;
        case 'faq': ?>
            <div class="help-faq"><?php foreach ($content as [$q, $a]): ?><details><summary><?= Help::inline($q) ?></summary><p><?= Help::inline($a) ?></p></details><?php endforeach; ?></div>
            <?php break;
    endswitch;
endforeach;
