<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<p><a href="/admin/ftp">← FTP-Konten</a></p>
<h1><?= esc($account['label']) ?> <?= $account['active'] ? '' : '<span class="badge">gesperrt</span>' ?></h1>

<section class="card">
    <h2>Zugangsdaten für den Kopierer</h2>
    <table class="kv">
        <tr><th>Server</th><td><code><?= esc($host) ?></code></td></tr>
        <tr><th>Port</th><td><code><?= $port ?></code> FTP oder FTPS (explizit, „AUTH TLS“) · <code><?= $tlsPort ?></code> FTPS implizit</td></tr>
        <tr><th>Benutzername</th><td><code><?= esc($account['username']) ?></code></td></tr>
        <tr><th>Passwort</th><td><code><?= esc($password) ?></code></td></tr>
        <tr><th>Verzeichnis / Pfad</th><td><code>/</code> (leer lassen, falls möglich)</td></tr>
        <tr><th>Modus</th><td>Passiv (PASV)</td></tr>
    </table>
    <p class="hint">Wenn der Kopierer es unterstützt, FTPS verwenden. Unverschlüsseltes FTP überträgt Passwort und Dokumente im Klartext. Das Konto kann nur PDF-Dateien hochladen, aber keine Dateien auflisten, lesen oder löschen.</p>
    <div class="row">
        <?= form_open('admin/ftp/' . $account['id'] . '/password', ['class' => 'inline', 'onsubmit' => "return confirm('Neues Passwort erzeugen? Das alte funktioniert dann nicht mehr.')"]) ?><button class="button small">Neues Passwort</button><?= form_close() ?>
        <?= form_open('admin/ftp/' . $account['id'] . '/toggle', ['class' => 'inline']) ?><button class="button small"><?= $account['active'] ? 'Sperren' : 'Aktivieren' ?></button><?= form_close() ?>
        <?= form_open('admin/ftp/' . $account['id'] . '/delete', ['class' => 'inline', 'onsubmit' => "return confirm('FTP-Konto und ALLE zugehörigen Scans löschen?')"]) ?><button class="button small danger">Löschen</button><?= form_close() ?>
    </div>
</section>

<section class="card">
    <h2>Wer sieht die Scans?</h2>
    <p class="hint">Administratoren sehen immer alle Scans. Weitere Personen oder Entra-Gruppen hier hinzufügen.</p>
    <?php if ($members === []): ?>
        <p class="muted">Noch niemand zugeordnet.</p>
    <?php else: ?>
    <table class="table">
        <thead><tr><th>Typ</th><th>Name</th><th>Kennung</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($members as $member): ?>
            <tr>
                <td><?= $member['member_type'] === 'group' ? 'Gruppe' : 'Person' ?></td>
                <td><?= esc($member['label']) ?></td>
                <td><code><?= esc($member['member_ref']) ?></code></td>
                <td><?= form_open('admin/ftp/' . $account['id'] . '/members/' . $member['id'] . '/delete', ['class' => 'inline']) ?><button class="button small danger">Entfernen</button><?= form_close() ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <?php endif ?>

    <h3>Person hinzufügen</h3>
    <?= form_open('admin/ftp/' . $account['id'] . '/members', ['class' => 'form row']) ?>
        <input type="hidden" name="member_type" value="user">
        <input name="member_ref" list="known-users" required placeholder="Anmeldename, z. B. max@firma.de">
        <datalist id="known-users">
            <?php foreach ($users as $u): ?><option value="<?= esc($u['upn'], 'attr') ?>"><?= esc($u['name']) ?></option><?php endforeach ?>
        </datalist>
        <input name="label" placeholder="Name (optional)">
        <button class="button">Hinzufügen</button>
    <?= form_close() ?>

    <h3>Entra-Gruppe hinzufügen</h3>
    <?= form_open('admin/ftp/' . $account['id'] . '/members', ['class' => 'form row']) ?>
        <input type="hidden" name="member_type" value="group">
        <input name="member_ref" required placeholder="Objekt-ID der Gruppe">
        <input name="label" required placeholder="Gruppenname">
        <button class="button">Hinzufügen</button>
    <?= form_close() ?>
    <p class="hint">Die Objekt-ID finden Sie in Entra unter <em>Gruppen → Gruppe → Übersicht</em>.</p>
</section>
<?= $this->endSection() ?>
