<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="headline">
    <h1>Scans</h1>
    <?php if (count($accounts) > 1): ?>
    <form method="get" class="inline">
        <select name="account" onchange="this.form.submit()">
            <option value="0">Alle Geräte</option>
            <?php foreach ($accounts as $account): ?>
                <option value="<?= $account['id'] ?>" <?= $filter === (int) $account['id'] ? 'selected' : '' ?>><?= esc($account['label']) ?></option>
            <?php endforeach ?>
        </select>
    </form>
    <?php endif ?>
</div>
<p class="hint">Scans werden nach <?= $expires ?> Tagen automatisch gelöscht.</p>

<?php if ($accounts === []): ?>
    <div class="card"><p>Ihnen ist noch kein Gerät zugeordnet. Bitte wenden Sie sich an Ihren Administrator.</p></div>
<?php elseif ($scans === []): ?>
    <div class="card"><p>Keine Scans vorhanden.</p></div>
<?php else: ?>
<div class="grid">
    <?php foreach ($scans as $scan): ?>
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
            <small><?= esc(date('d.m.Y H:i', strtotime($scan['received_at']))) ?> · <?= number_to_size($scan['size'], 1, 'de') ?>
            <?php if (isset($accounts[$scan['ftp_account_id']])): ?> · <?= esc($accounts[$scan['ftp_account_id']]['label']) ?><?php endif ?></small>
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
<?= $pager?->links() ?>
<?php endif ?>
<?= $this->endSection() ?>
