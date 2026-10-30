<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<section class="card narrow">
    <h1>ScanKai</h1>
    <p>Scannen vom Kopierer in die Cloud: Geräte senden per FTP oder FTPS, Ihre Mitarbeiter sehen die PDFs nach der Anmeldung mit Microsoft 365.</p>
    <p>Ihr Zugang ist unter <code>&lt;ihr-kürzel&gt;.<?= esc(config('ScanKai')->baseDomain) ?></code> erreichbar.</p>
</section>
<?= $this->endSection() ?>
