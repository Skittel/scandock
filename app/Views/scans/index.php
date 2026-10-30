<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="headline">
    <h1>Scans</h1>
    <?php if (count($sections) > 1): ?>
    <div class="row">
        <button type="button" class="button small secondary" data-accordion="open">Alle aufklappen</button>
        <button type="button" class="button small secondary" data-accordion="close">Alle zuklappen</button>
    </div>
    <?php endif ?>
</div>
<p class="hint">Scans werden nach <?= $expires ?> Tagen automatisch gelöscht.</p>

<?php if ($sections === []): ?>
    <div class="card"><p>Ihnen ist noch kein Gerät zugeordnet. Bitte wenden Sie sich an Ihren Administrator.</p></div>
<?php else: ?>
<div class="accordion" data-save-url="<?= esc(site_url('scans/collapsed'), 'attr') ?>" data-status-url="<?= esc(site_url('scans/status'), 'attr') ?>">
    <?php foreach ($sections as $section): $account = $section['account']; ?>
    <details class="account" data-account="<?= $account['id'] ?>" <?= in_array((int) $account['id'], $collapsed, true) ? '' : 'open' ?>>
        <summary>
            <span class="title"><?= esc($account['label']) ?></span>
            <span class="badge"><?= $account['visibility'] === 'public' ? 'öffentlich' : 'privat' ?></span>
            <span class="count"><?= $section['total'] ?> Scan<?= $section['total'] === 1 ? '' : 's' ?></span>
        </summary>
        <div class="account-body">
            <?php $waiting = $pending[(int) $account['id']] ?? [] ?>
            <?php if ($section['scans'] === [] && $waiting === []): ?>
                <p class="muted">Keine Scans vorhanden.</p>
            <?php else: ?>
            <div class="grid">
                <?php foreach ($waiting as $file): ?>
                <article class="scan pending">
                    <div class="thumb"><span class="spinner" aria-hidden="true"></span></div>
                    <div class="meta">
                        <strong title="<?= esc($file['name'], 'attr') ?>"><?= esc($file['name']) ?></strong>
                        <small><?= $file['state'] === 'uploading' ? 'Wird übertragen …' : 'Wird verarbeitet …' ?> · <?= number_to_size($file['size'], 1, 'de') ?></small>
                    </div>
                    <div class="actions"><span class="badge">ausstehend</span></div>
                </article>
                <?php endforeach ?>
                <?php foreach ($section['scans'] as $scan): ?>
                <article class="scan">
                    <a href="/scans/<?= $scan['id'] ?>/view" target="_blank" rel="noopener" class="thumb">
                        <?php if ($scan['has_thumbnail']): ?>
                            <img src="/scans/<?= $scan['id'] ?>/thumbnail" alt="Vorschau" loading="lazy">
                        <?php else: ?>
                            <span class="nothumb">PDF</span>
                        <?php endif ?>
                    </a>
                    <div class="meta">
                        <strong title="<?= esc($scan['original_name'], 'attr') ?>"><?= esc($scan['original_name']) ?></strong>
                        <small><?= esc(date('d.m.Y H:i', strtotime($scan['received_at']))) ?> · <?= number_to_size($scan['size'], 1, 'de') ?></small>
                    </div>
                    <div class="actions">
                        <a class="button small" href="/scans/<?= $scan['id'] ?>/download">Herunterladen</a>
                        <?= form_open('scans/' . $scan['id'] . '/delete', ['class' => 'inline', 'onsubmit' => "return confirm('Scan wirklich löschen?')"]) ?>
                            <button class="button small danger">Löschen</button>
                        <?= form_close() ?>
                    </div>
                </article>
                <?php endforeach ?>
            </div>
            <?= $pager->links($section['group']) ?>
            <?php endif ?>
        </div>
    </details>
    <?php endforeach ?>
</div>
<script src="/js/scans-accordion.js?v=<?= esc(config('ScanKai')->version) ?>" defer></script>
<?php endif ?>
<?= $this->endSection() ?>
