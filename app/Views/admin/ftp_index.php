<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1>FTP-Konten</h1>
<p class="hint">Jeder Kopierer (oder Standort) erhält ein eigenes FTP-Konto. Über das Konto können ausschließlich PDF-Dateien hochgeladen werden.</p>

<?= form_open('admin/ftp', ['class' => 'form row']) ?>
    <input name="label" required maxlength="190" placeholder="Bezeichnung, z. B. Kopierer Empfang">
    <button class="button">FTP-Konto anlegen</button>
<?= form_close() ?>

<?php if ($accounts === []): ?>
    <div class="card"><p>Noch keine FTP-Konten vorhanden.</p></div>
<?php else: ?>
<table class="table">
    <thead><tr><th>Bezeichnung</th><th>Benutzername</th><th>Status</th><th>Scans</th></tr></thead>
    <tbody>
    <?php foreach ($accounts as $account): ?>
        <tr>
            <td><a href="/admin/ftp/<?= $account['id'] ?>"><?= esc($account['label']) ?></a></td>
            <td><code><?= esc($account['username']) ?></code></td>
            <td><?= $account['active'] ? 'aktiv' : '<span class="muted">gesperrt</span>' ?></td>
            <td><?= $counts[$account['id']] ?></td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>
<?= $this->endSection() ?>
