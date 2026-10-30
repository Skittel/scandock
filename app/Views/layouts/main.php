<!DOCTYPE html>
<?php $theme = $user['theme'] ?? 'auto' ?>
<html lang="de"<?= $theme !== 'auto' ? ' data-theme="' . esc($theme, 'attr') . '"' : '' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'ScanDock') ?><?= isset($tenant['name']) ? ' · ' . esc($tenant['name']) : '' ?></title>
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <meta name="csrf-header" content="<?= esc(config('Security')->headerName, 'attr') ?>">
    <meta name="csrf-token" content="<?= esc(csrf_hash(), 'attr') ?>">
    <link rel="stylesheet" href="/css/app.css?v=<?= esc(config('ScanDock')->version) ?>">
</head>
<body>
<header class="topbar">
    <?php $scandock = config('ScanDock') ?>
    <div class="brand">
        <a href="/"><img src="/logo-scandock.svg" alt="ScanDock"></a>
        <?php if ($scandock->operatorLogo !== ''): ?>
            <a class="operator" href="<?= esc($scandock->operatorUrl, 'attr') ?>" target="_blank" rel="noopener"><img src="<?= esc($scandock->operatorLogo, 'attr') ?>" alt="<?= esc($scandock->operatorName, 'attr') ?>"></a>
        <?php endif ?>
        <?php if (! empty($tenant['name'])): ?>
            <a class="tenant" href="/">
                <?php if (! empty($tenant['logo_file'])): ?><img src="/branding/logo?v=<?= esc($tenant['logo_file'], 'url') ?>" alt=""><?php endif ?>
                <?= esc($tenant['name']) ?>
            </a>
        <?php endif ?>
    </div>
    <?php if (! empty($user)): ?>
    <details class="user-menu">
        <summary title="<?= esc($user['name'], 'attr') ?>"><?= esc($user['upn'] !== '' ? $user['upn'] : $user['name']) ?></summary>
        <div class="menu">
            <?php if (! empty($isAdmin)): ?>
                <a href="/admin/ftp">FTP-Konten</a>
                <a href="/admin/settings">Einstellungen</a>
                <a href="/admin/journal">Journal</a>
                <hr>
            <?php endif ?>
            <div class="label">Darstellung</div>
            <?= form_open('account/theme', ['class' => 'themes', 'data-theme-form' => '1']) ?>
                <?php foreach (['light' => 'Hell', 'dark' => 'Dunkel', 'auto' => 'System'] as $value => $text): ?>
                    <button name="theme" value="<?= $value ?>" aria-pressed="<?= $theme === $value ? 'true' : 'false' ?>"><?= $text ?></button>
                <?php endforeach ?>
            <?= form_close() ?>
            <hr>
            <?= form_open('auth/logout') ?><button class="item">Abmelden</button><?= form_close() ?>
        </div>
    </details>
    <script src="/js/user-menu.js?v=<?= esc(config('ScanDock')->version) ?>" defer></script>
    <?php endif ?>
</header>
<main>
    <?php foreach (['notice' => 'ok', 'warning' => 'warn', 'error' => 'err'] as $key => $cls): ?>
        <?php if ($msg = session()->getFlashdata($key)): ?><div class="flash <?= $cls ?>"><?= esc($msg) ?></div><?php endif ?>
    <?php endforeach ?>
    <?php if (! empty($ftpBlocked)): ?>
        <div class="flash err"><strong>Der Empfang von Scans ist gesperrt.</strong> Auf dem Server ist zu wenig Speicherplatz frei, der FTP-Server wurde deshalb angehalten<?= isset($ftpBlocked['since']) ? ' (seit ' . esc(date('d.m.Y H:i', strtotime($ftpBlocked['since']))) . ' Uhr)' : '' ?>. Kopierer können zurzeit keine Scans senden. Bereits empfangene Scans sind weiterhin verfügbar. Sobald wieder genug Speicherplatz frei ist, wird der Empfang automatisch freigegeben.</div>
    <?php endif ?>
    <?php if (! empty($groupsStale)): ?>
        <div class="flash warn">Ihre Gruppenmitgliedschaften konnten nicht bei Microsoft aktualisiert werden. Rechte über Gruppen sind deshalb vorübergehend nicht aktiv. <a href="/auth/login">Neu anmelden</a></div>
    <?php endif ?>
    <?php if (! empty($everyoneIsAdmin)): ?>
        <div class="flash warn">Es ist keine Admin-Gruppe festgelegt: <strong>Jeder angemeldete Benutzer hat Admin-Rechte.</strong> <a href="/admin/settings">Admin-Gruppe festlegen</a></div>
    <?php endif ?>
    <?php if (isset($secretDaysLeft)): ?>
        <?php if ($secretDaysLeft < 0): ?>
            <div class="flash err">Der geheime Clientschlüssel der Entra-App ist am <?= esc(date('d.m.Y', strtotime($tenant['entra_secret_expires_at']))) ?> abgelaufen. Neue Anmeldungen sind nicht mehr möglich. <a href="/admin/settings#secret">Jetzt erneuern</a></div>
        <?php elseif ($secretDaysLeft <= config('ScanDock')->secretWarningDays): ?>
            <div class="flash warn">Der geheime Clientschlüssel der Entra-App läuft <?= $secretDaysLeft === 0 ? 'heute' : 'in ' . $secretDaysLeft . ' Tag' . ($secretDaysLeft === 1 ? '' : 'en') ?> ab (<?= esc(date('d.m.Y', strtotime($tenant['entra_secret_expires_at']))) ?>). Danach kann sich niemand mehr anmelden. <a href="/admin/settings#secret">Jetzt erneuern</a></div>
        <?php endif ?>
    <?php endif ?>
    <?= $this->renderSection('content') ?>
</main>
<footer>ScanDock <?= esc(config('ScanDock')->version) ?> · <a href="https://github.com/Skittel/scandock">GitHub</a></footer>
</body>
</html>
