<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<section class="card">
    <h1>Einrichtung für <?= esc($tenant['name']) ?></h1>
    <?php if ($tenant['status'] === 'active'): ?>
        <div class="flash warn">ScanKai ist für Sie bereits eingerichtet. Über diesen Link können Sie die Entra-Anbindung erneuern, z. B. nach Ablauf des Clientschlüssels. Die Kopierer senden in der Zwischenzeit weiter.</div>
    <?php endif ?>
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
        <li>Beschreibung <code>ScanKai</code>, Ablauf z. B. 24 Monate. Das Ablaufdatum tragen Sie unten mit ein.</li>
        <li>Kopieren Sie den <strong>Wert</strong> (nicht die Geheimnis-ID). Er wird nur einmal angezeigt.</li>
    </ol>

    <h2>4. API-Berechtigung prüfen</h2>
    <ol>
        <li>Unter <strong>API-Berechtigungen</strong> muss <strong>Microsoft Graph → User.Read</strong> (delegiert) eingetragen sein. Bei neuen Registrierungen ist das bereits der Fall.</li>
        <li>Empfohlen: <strong>Administratorzustimmung für &lt;Ihre Organisation&gt; erteilen</strong> klicken. Dann werden Ihre Mitarbeiter bei der ersten Anmeldung nicht nach ihrer Zustimmung gefragt.</li>
    </ol>
    <p class="hint">Mit User.Read liest ScanKai bei jeder Anmeldung nur die Gruppenmitgliedschaften des angemeldeten Benutzers (auch Microsoft-365-Gruppen und verschachtelte Gruppen). Darüber vergeben Sie Rechte (Admin-Gruppe, Zugriff auf FTP-Konten). Weitere Berechtigungen und eine Tokenkonfiguration sind nicht nötig.</p>

    <h2>5. Werte hier eintragen</h2>
    <?php foreach ($errors as $error): ?><div class="flash err"><?= esc($error) ?></div><?php endforeach ?>
    <?= form_open('setup/' . $token, ['class' => 'form']) ?>
        <label>Verzeichnis-ID (Mandant)
            <input name="entra_tenant_id" required value="<?= esc(old('entra_tenant_id', $tenant['entra_tenant_id'] ?? ''), 'attr') ?>" placeholder="00000000-0000-0000-0000-000000000000"></label>
        <label>Anwendungs-ID (Client)
            <input name="entra_client_id" required value="<?= esc(old('entra_client_id', $tenant['entra_client_id'] ?? ''), 'attr') ?>" placeholder="00000000-0000-0000-0000-000000000000"></label>
        <label>Wert des geheimen Clientschlüssels
            <input name="entra_client_secret" type="password" autocomplete="off" <?= $configured ? 'placeholder="gespeichert – leer lassen, um ihn zu behalten"' : 'required' ?>></label>
        <label>Ablaufdatum des Clientschlüssels (Spalte „Läuft ab“ in Entra)
            <input name="entra_secret_expires_at" type="date" <?= empty($tenant['entra_secret_expires_at']) ? 'required' : '' ?> min="<?= date('Y-m-d', strtotime('+1 day')) ?>" max="<?= esc($maxExpiry, 'attr') ?>" value="<?= esc(old('entra_secret_expires_at', $tenant['entra_secret_expires_at'] ?? ''), 'attr') ?>"></label>
        <p class="hint">ScanKai prüft die Werte beim Speichern direkt bei Microsoft und warnt Administratoren rechtzeitig vor dem Ablauf des Schlüssels.</p>
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
