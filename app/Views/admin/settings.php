<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1>Einstellungen</h1>
<section class="card">
    <?= form_open('admin/settings', ['class' => 'form']) ?>
        <label>Admin-Gruppe (Objekt-ID der Entra-Gruppe)
            <input name="admin_group_id" value="<?= esc(old('admin_group_id', $tenant['admin_group_id'] ?? ''), 'attr') ?>" placeholder="leer = alle angemeldeten Benutzer sind Administratoren"></label>
        <p class="hint">Solange keine Gruppe eingetragen ist, haben alle Benutzer Admin-Rechte. Ist eine Gruppe eingetragen, haben nur deren Mitglieder Admin-Rechte.</p>
        <?php if ($userGroups !== []): ?>
            <details><summary>Ihre Gruppen-IDs laut letzter Anmeldung</summary>
                <ul><?php foreach ($userGroups as $g): ?><li><code><?= esc($g) ?></code></li><?php endforeach ?></ul>
            </details>
        <?php else: ?>
            <p class="hint warn">Bei Ihrer letzten Anmeldung wurden keine Gruppen übermittelt. Prüfen Sie den Gruppenanspruch in der Tokenkonfiguration der App-Registrierung.</p>
        <?php endif ?>
        <label class="check"><input type="checkbox" name="confirm_lockout" value="1"> Ich bin nicht Mitglied der Gruppe und verliere bewusst meine Admin-Rechte</label>

        <label>Aufbewahrung der Scans (Tage)
            <input name="retention_days" type="number" min="1" max="999" required value="<?= esc(old('retention_days', $tenant['retention_days']), 'attr') ?>"></label>
        <button class="button">Speichern</button>
    <?= form_close() ?>
</section>
<?= $this->endSection() ?>
