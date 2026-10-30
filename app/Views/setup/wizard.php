<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<section class="card">
    <h1>Einrichtung für <?= esc($tenant['name']) ?></h1>
    <p>Damit sich Ihre Mitarbeiter mit ihrem Microsoft-365-Konto anmelden können, legen Sie in Ihrem Microsoft Entra ID eine App-Registrierung an. Dafür benötigen Sie die Rolle <em>Anwendungsadministrator</em> oder <em>Globaler Administrator</em>.</p>

    <h2>1. App registrieren</h2>
    <ol>
        <li>Öffnen Sie <a href="https://entra.microsoft.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank" rel="noopener">entra.microsoft.com → Identität → Anwendungen → App-Registrierungen</a> und klicken Sie auf <strong>Neue Registrierung</strong>.</li>
        <li>Name: <code>ScanKai</code></li>
        <li>Unterstützte Kontotypen: <strong>Nur Konten in diesem Organisationsverzeichnis</strong> (einzelner Mandant)</li>
        <li>Umleitungs-URI: Plattform <strong>Web</strong>, Wert:
            <div class="copy"><code><?= esc($redirectUri) ?></code></div></li>
        <li>Auf <strong>Registrieren</strong> klicken.</li>
    </ol>

    <h2>2. Werte notieren</h2>
    <p>Auf der Übersichtsseite der App finden Sie die <strong>Anwendungs-ID (Client)</strong> und die <strong>Verzeichnis-ID (Mandant)</strong>.</p>

    <h2>3. Geheimen Clientschlüssel erstellen</h2>
    <ol>
        <li><strong>Zertifikate &amp; Geheimnisse → Geheime Clientschlüssel → Neuer geheimer Clientschlüssel</strong></li>
        <li>Beschreibung <code>ScanKai</code>, Ablauf z. B. 24 Monate. <em>Notieren Sie sich das Ablaufdatum</em>, danach muss ein neuer Schlüssel hinterlegt werden.</li>
        <li>Kopieren Sie den <strong>Wert</strong> (nicht die Geheimnis-ID). Er wird nur einmal angezeigt.</li>
    </ol>

    <h2>4. Gruppen im Token übermitteln</h2>
    <ol>
        <li><strong>Tokenkonfiguration → Gruppenanspruch hinzufügen</strong></li>
        <li><strong>Sicherheitsgruppen</strong> auswählen, beim Token-Typ <em>ID</em> die Option <strong>Gruppen-ID</strong> belassen, speichern.</li>
    </ol>
    <p class="hint">Damit kann ScanKai Rechte über Entra-Gruppen vergeben (Admin-Gruppe, Zugriff auf FTP-Konten).</p>

    <h2>5. Werte hier eintragen</h2>
    <?php foreach ($errors as $error): ?><div class="flash err"><?= esc($error) ?></div><?php endforeach ?>
    <?= form_open('setup/' . $token, ['class' => 'form']) ?>
        <label>Verzeichnis-ID (Mandant)
            <input name="entra_tenant_id" required value="<?= esc(old('entra_tenant_id', $tenant['entra_tenant_id'] ?? ''), 'attr') ?>" placeholder="00000000-0000-0000-0000-000000000000"></label>
        <label>Anwendungs-ID (Client)
            <input name="entra_client_id" required value="<?= esc(old('entra_client_id', $tenant['entra_client_id'] ?? ''), 'attr') ?>" placeholder="00000000-0000-0000-0000-000000000000"></label>
        <label>Wert des geheimen Clientschlüssels
            <input name="entra_client_secret" type="password" autocomplete="off" <?= $configured ? 'placeholder="gespeichert – leer lassen, um ihn zu behalten"' : 'required' ?>></label>
        <button class="button">Speichern</button>
    <?= form_close() ?>

    <h2 id="test">6. Anmeldung testen</h2>
    <?php if ($loginError): ?><div class="flash err">Die Anmeldung hat nicht funktioniert: <?= esc($loginError) ?></div><?php endif ?>
    <?php if ($configured): ?>
        <p>Melden Sie sich jetzt mit Ihrem Microsoft-Konto an. Klappt die Anmeldung, ist die Einrichtung abgeschlossen und dieser Start-Link verliert seine Gültigkeit.</p>
        <?= form_open('setup/' . $token . '/test') ?><button class="button">Mit Microsoft anmelden</button><?= form_close() ?>
    <?php else: ?>
        <p class="hint">Bitte zuerst die Werte oben speichern.</p>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
