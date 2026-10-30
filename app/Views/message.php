<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<section class="card narrow">
    <h1><?= esc($title) ?></h1>
    <p><?= esc($message) ?></p>
    <?php if (! empty($link)): ?><p><a class="button" href="<?= esc($link['url'], 'attr') ?>"><?= esc($link['label']) ?></a></p><?php endif ?>
</section>
<?= $this->endSection() ?>
