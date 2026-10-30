<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="headline">
    <h1>Journal</h1>
    <form method="get" class="inline">
        <select name="category" onchange="this.form.submit()" aria-label="Bereich">
            <option value="">Alle Bereiche</option>
            <?php foreach ($categories as $key => $label): ?>
                <option value="<?= $key ?>" <?= $category === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach ?>
        </select>
    </form>
</div>
<p class="hint">Änderungen an Einstellungen und FTP-Konten sowie eingegangene, abgelehnte und gelöschte Scans. Anmeldungen und Downloads werden nicht protokolliert. Einträge werden nach einem Jahr gelöscht.</p>

<?php if ($entries === []): ?>
    <div class="card"><p>Keine Einträge vorhanden.</p></div>
<?php else: ?>
<table class="table journal">
    <thead><tr><th>Zeitpunkt</th><th>Bereich</th><th>Vorgang</th><th>Durch</th><th>IP-Adresse</th></tr></thead>
    <tbody>
    <?php foreach ($entries as $entry): ?>
        <tr>
            <td class="nowrap"><?= esc(date('d.m.Y H:i:s', strtotime($entry['created_at']))) ?></td>
            <td><span class="badge"><?= esc($categories[$entry['category']] ?? $entry['category']) ?></span></td>
            <td><?= esc(\App\Libraries\AuditLog::describe($entry)) ?></td>
            <td><?= esc($entry['actor']) ?></td>
            <td class="muted nowrap"><?= esc($entry['ip'] ?? '') ?></td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?= $pager->links() ?>
<?php endif ?>
<?= $this->endSection() ?>
