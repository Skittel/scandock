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
        <tr><th>Passwort</th><td>
            <?php if ($password !== null): ?>
                <code><?= esc($password) ?></code>
                <span class="hint warn">Wird nur jetzt im Klartext angezeigt.</span>
            <?php else: ?>
                <span class="secret" data-url="<?= esc(site_url('admin/ftp/' . $account['id'] . '/password'), 'attr') ?>">
                    <code class="secret-value">••••••••</code>
                    <button type="button" class="icon-button secret-toggle" title="Passwort anzeigen" aria-label="Passwort anzeigen">
                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/><path class="eye-off" d="M3 3l18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" hidden/></svg>
                    </button>
                </span>
            <?php endif ?>
        </td></tr>
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
    <?= form_open('admin/ftp/' . $account['id'] . '/visibility', ['class' => 'form']) ?>
        <label class="check"><input type="radio" name="visibility" value="public" <?= $account['visibility'] === 'public' ? 'checked' : '' ?> onchange="this.form.submit()">
            <strong>Öffentlich</strong> – alle Benutzer sehen die Scans</label>
        <label class="check"><input type="radio" name="visibility" value="private" <?= $account['visibility'] === 'private' ? 'checked' : '' ?> onchange="this.form.submit()">
            <strong>Privat</strong> – nur die unten eingetragenen Personen und Gruppen sehen die Scans</label>
        <noscript><button class="button small">Speichern</button></noscript>
    <?= form_close() ?>

    <?php if ($account['visibility'] === 'private'): ?>
        <p class="hint">Auch Administratoren sehen private Scans nur, wenn sie selbst oder eine ihrer Gruppen eingetragen sind.</p>

        <?php if ($members === []): ?>
            <p class="warn">Noch niemand eingetragen – die Scans dieses Kontos sieht derzeit niemand.</p>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>Typ</th><th>Name</th><th>Kennung</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($members as $member): ?>
                <tr>
                    <td><?= $member['member_type'] === 'group' ? 'Gruppe' : 'Person' ?></td>
                    <td><?= esc($member['label']) ?></td>
                    <td><code><?= esc($member['member_ref']) ?></code></td>
                    <td><?= form_open('admin/ftp/' . $account['id'] . '/members/' . $member['id'] . '/delete', ['class' => 'inline', 'onsubmit' => "return confirm('Berechtigung entfernen?')"]) ?><button class="button small danger">Entfernen</button><?= form_close() ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
        <?php endif ?>

        <h3>Person oder Gruppe hinzufügen</h3>
        <?= form_open('admin/ftp/' . $account['id'] . '/members', ['class' => 'form row member-picker', 'data-search-url' => site_url('admin/directory'), 'autocomplete' => 'off']) ?>
            <input type="hidden" name="member_type" value="">
            <input type="hidden" name="label" value="">
            <div class="picker">
                <input name="member_ref" required placeholder="Name, Anmeldename oder Gruppe suchen …" aria-autocomplete="list">
                <ul class="suggestions" hidden></ul>
            </div>
            <button class="button">Hinzufügen</button>
        <?= form_close() ?>
        <p class="hint picker-hint">Vorschläge kommen aus Ihrem Microsoft-Verzeichnis, sofern ScanKai die Anwendungsberechtigungen <em>User.Read.All</em> und <em>Group.ReadBasic.All</em> hat. Sonst werden Personen vorgeschlagen, die sich schon angemeldet haben. Sie können auch direkt einen Anmeldenamen (name@firma.de) oder die Objekt-ID einer Gruppe eingeben.</p>
    <?php else: ?>
        <p class="hint">Bei öffentlichen Konten ist keine Zuordnung nötig.<?= $members !== [] ? ' Die bisher eingetragenen ' . count($members) . ' Personen/Gruppen bleiben gespeichert und gelten wieder, wenn das Konto privat wird.' : '' ?></p>
    <?php endif ?>
</section>
<script src="/js/secret-reveal.js?v=<?= esc(config('ScanKai')->version) ?>" defer></script>
<script src="/js/member-picker.js?v=<?= esc(config('ScanKai')->version) ?>" defer></script>
<?= $this->endSection() ?>
