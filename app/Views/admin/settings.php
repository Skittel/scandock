<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1>Einstellungen</h1>
<section class="card" id="branding">
    <h2>Firma</h2>
    <?= form_open_multipart('admin/settings/branding', ['class' => 'form']) ?>
        <label>Firmenname (Anzeigename)
            <input name="name" required maxlength="190" value="<?= esc(old('name', $tenant['name']), 'attr') ?>"></label>
        <label>Logo (PNG, JPG, WebP oder SVG, max. 1 MB)
            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml"></label>
        <?php if (! empty($tenant['logo_file'])): ?>
            <div class="logo-preview"><img src="/branding/logo?v=<?= esc($tenant['logo_file'], 'url') ?>" alt="Aktuelles Logo"><span class="hint">Aktuelles Logo – eine neue Datei ersetzt es.</span></div>
        <?php endif ?>
        <button class="button">Speichern</button>
    <?= form_close() ?>
    <?php if (! empty($tenant['logo_file'])): ?>
        <?= form_open('admin/settings/logo/delete', ['onsubmit' => "return confirm('Logo entfernen?')"]) ?><button class="button small danger">Logo entfernen</button><?= form_close() ?>
    <?php endif ?>
</section>

<section class="card">
    <?= form_open('admin/settings', ['class' => 'form']) ?>
        <label>Admin-Gruppe (Objekt-ID der Entra-Gruppe)
            <input name="admin_group_id" value="<?= esc(old('admin_group_id', $tenant['admin_group_id'] ?? ''), 'attr') ?>" placeholder="leer = alle angemeldeten Benutzer sind Administratoren"></label>
        <p class="hint">Solange keine Gruppe eingetragen ist, haben alle Benutzer Admin-Rechte. Ist eine Gruppe eingetragen, haben nur deren Mitglieder Admin-Rechte. Gruppenmitgliedschaften werden bei jeder Anmeldung von Microsoft abgerufen.</p>
        <?php if ($userGroups !== []): ?>
            <details><summary>Ihre Gruppen-IDs laut letzter Anmeldung</summary>
                <ul><?php foreach ($userGroups as $g): ?><li><code><?= esc($g) ?></code></li><?php endforeach ?></ul>
            </details>
        <?php else: ?>
            <p class="hint warn">Laut letzter Anmeldung sind Sie in keiner Gruppe Mitglied. Beim Speichern einer Admin-Gruppe gleicht ScanDock Ihre Gruppen automatisch mit Microsoft ab. Voraussetzung ist die Berechtigung <em>Microsoft Graph → User.Read</em> in der App-Registrierung.</p>
        <?php endif ?>
        <label class="check"><input type="checkbox" name="confirm_lockout" value="1"> Ich bin nicht Mitglied der Gruppe und verliere bewusst meine Admin-Rechte</label>

        <label>Aufbewahrung der Scans (Tage)
            <input name="retention_days" type="number" min="1" max="999" required value="<?= esc(old('retention_days', $tenant['retention_days']), 'attr') ?>"></label>
        <button class="button">Speichern</button>
    <?= form_close() ?>
</section>

<section class="card" id="secret">
    <h2>Geheimer Clientschlüssel (Entra)</h2>
    <?php if (empty($tenant['entra_secret_expires_at'])): ?>
        <p class="warn">Das Ablaufdatum des aktuellen Schlüssels ist nicht bekannt. Bitte beim nächsten Erneuern mit angeben.</p>
    <?php else: ?>
        <p>Der aktuelle Schlüssel läuft am <strong><?= esc(date('d.m.Y', strtotime($tenant['entra_secret_expires_at']))) ?></strong> ab<?= $secretDaysLeft !== null && $secretDaysLeft >= 0 ? ' (in ' . $secretDaysLeft . ' Tagen)' : ' – <strong>abgelaufen</strong>' ?>.
        Administratoren werden <?= config('ScanDock')->secretWarningDays ?> Tage vorher hier in ScanDock gewarnt.</p>
    <?php endif ?>
    <ol class="hint">
        <li>In Entra unter <strong>App-Registrierungen → ScanDock → Zertifikate &amp; Geheimnisse</strong> einen <strong>neuen geheimen Clientschlüssel</strong> erstellen.</li>
        <li>Den <strong>Wert</strong> und das <strong>Ablaufdatum</strong> hier eintragen. ScanDock prüft den Schlüssel direkt bei Microsoft.</li>
        <li>Erst danach den alten Schlüssel in Entra löschen.</li>
    </ol>
    <?= form_open('admin/settings/secret', ['class' => 'form']) ?>
        <label>Wert des neuen Clientschlüssels
            <input name="entra_client_secret" type="password" autocomplete="off" required></label>
        <label>Ablaufdatum (laut Entra)
            <input name="entra_secret_expires_at" type="date" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>" max="<?= esc($maxExpiry, 'attr') ?>"></label>
        <button class="button">Schlüssel prüfen und speichern</button>
    <?= form_close() ?>
</section>
<?= $this->endSection() ?>
