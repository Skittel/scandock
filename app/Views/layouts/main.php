<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'ScanKai') ?><?= isset($tenant['name']) ? ' · ' . esc($tenant['name']) : '' ?></title>
    <link rel="stylesheet" href="/css/app.css?v=<?= esc(config('ScanKai')->version) ?>">
</head>
<body>
<header class="topbar">
    <a class="brand" href="/">ScanKai<?php if (! empty($tenant['name'])): ?> <span><?= esc($tenant['name']) ?></span><?php endif ?></a>
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
    <?= $this->renderSection('content') ?>
</main>
<footer>ScanKai <?= esc(config('ScanKai')->version) ?> · <a href="https://github.com/Skittel/scankai">GitHub</a></footer>
</body>
</html>
