<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1>Einstellungen</h1>
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
            <p class="hint warn">Laut letzter Anmeldung sind Sie in keiner Gruppe Mitglied. Beim Speichern einer Admin-Gruppe gleicht ScanKai Ihre Gruppen automatisch mit Microsoft ab. Voraussetzung ist die Berechtigung <em>Microsoft Graph → User.Read</em> in der App-Registrierung.</p>
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
        Administratoren werden <?= config('ScanKai')->secretWarningDays ?> Tage vorher hier in ScanKai gewarnt.</p>
    <?php endif ?>
    <ol class="hint">
        <li>In Entra unter <strong>App-Registrierungen → ScanKai → Zertifikate &amp; Geheimnisse</strong> einen <strong>neuen geheimen Clientschlüssel</strong> erstellen.</li>
        <li>Den <strong>Wert</strong> und das <strong>Ablaufdatum</strong> hier eintragen. ScanKai prüft den Schlüssel direkt bei Microsoft.</li>
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
