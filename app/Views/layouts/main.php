<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'ScanKai') ?><?= isset($tenant['name']) ? ' · ' . esc($tenant['name']) : '' ?></title>
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="stylesheet" href="/css/app.css?v=<?= esc(config('ScanKai')->version) ?>">
</head>
<body>
<header class="topbar">
    <a class="brand" href="/"><img src="/logo-scankai.svg" alt="ScanKai"><?php if (! empty($tenant['name'])): ?> <span><?= esc($tenant['name']) ?></span><?php endif ?></a>
    <?php if (! empty($user)): ?>
    <nav>
        <a href="/scans">Scans</a>
        <?php if (! empty($isAdmin)): ?>
            <a href="/admin/ftp">FTP-Konten</a>
            <a href="/admin/settings">Einstellungen</a>
        <?php endif ?>
        <span class="who"><?= esc($user['name']) ?></span>
        <?= form_open('auth/logout', ['class' => 'inline']) ?><button class="link">Abmelden</button><?= form_close() ?>
    </nav>
    <?php endif ?>
</header>
<main>
    <?php foreach (['notice' => 'ok', 'warning' => 'warn', 'error' => 'err'] as $key => $cls): ?>
        <?php if ($msg = session()->getFlashdata($key)): ?><div class="flash <?= $cls ?>"><?= esc($msg) ?></div><?php endif ?>
    <?php endforeach ?>
    <?php if (isset($secretDaysLeft)): ?>
        <?php if ($secretDaysLeft < 0): ?>
            <div class="flash err">Der geheime Clientschlüssel der Entra-App ist am <?= esc(date('d.m.Y', strtotime($tenant['entra_secret_expires_at']))) ?> abgelaufen. Neue Anmeldungen sind nicht mehr möglich. <a href="/admin/settings#secret">Jetzt erneuern</a></div>
        <?php elseif ($secretDaysLeft <= config('ScanKai')->secretWarningDays): ?>
            <div class="flash warn">Der geheime Clientschlüssel der Entra-App läuft <?= $secretDaysLeft === 0 ? 'heute' : 'in ' . $secretDaysLeft . ' Tag' . ($secretDaysLeft === 1 ? '' : 'en') ?> ab (<?= esc(date('d.m.Y', strtotime($tenant['entra_secret_expires_at']))) ?>). Danach kann sich niemand mehr anmelden. <a href="/admin/settings#secret">Jetzt erneuern</a></div>
        <?php endif ?>
    <?php endif ?>
    <?= $this->renderSection('content') ?>
</main>
<footer>ScanKai <?= esc(config('ScanKai')->version) ?> · <a href="https://github.com/Skittel/scankai">GitHub</a></footer>
</body>
</html>
