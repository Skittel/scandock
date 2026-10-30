<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="headline">
    <h1>Scans</h1>
    <?php if (count($sections) > 1): ?>
    <div class="row">
        <button type="button" class="button small secondary" data-accordion="open">Alle aufklappen</button>
        <button type="button" class="button small secondary" data-accordion="close">Alle zuklappen</button>
    </div>
    <?php endif ?>
</div>
<p class="hint">Scans werden nach <?= $expires ?> Tagen automatisch gelöscht.</p>

<?php if ($sections === []): ?>
    <div class="card"><p>Ihnen ist noch kein Gerät zugeordnet. Bitte wenden Sie sich an Ihren Administrator.</p></div>
<?php else: ?>
<div class="accordion" data-save-url="<?= esc(site_url('scans/collapsed'), 'attr') ?>" data-view-url="<?= esc(site_url('scans/view'), 'attr') ?>" data-status-url="<?= esc(site_url('scans/status'), 'attr') ?>">
    <?php foreach ($sections as $section): $account = $section['account']; $id = (int) $account['id']; $isList = in_array($id, $listViews, true); ?>
    <details class="account <?= $isList ? 'view-list' : 'view-grid' ?>" data-account="<?= esc($account['public_id']) ?>" <?= in_array($id, $collapsed, true) ? '' : 'open' ?>>
        <summary>
            <span class="title"><?= esc($account['label']) ?></span>
            <span class="badge"><?= $account['visibility'] === 'public' ? 'öffentlich' : 'privat' ?></span>
            <span class="view-switch" role="group" aria-label="Ansicht">
                <button type="button" class="icon-button" data-view="grid" title="Vorschau" aria-label="Vorschau" aria-pressed="<?= $isList ? 'false' : 'true' ?>">
                    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><g fill="currentColor"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/></g></svg>
                </button>
                <button type="button" class="icon-button" data-view="list" title="Liste" aria-label="Liste" aria-pressed="<?= $isList ? 'true' : 'false' ?>">
                    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><g fill="currentColor"><rect x="3" y="4" width="3" height="3" rx="1"/><rect x="8" y="4.5" width="13" height="2" rx="1"/><rect x="3" y="10.5" width="3" height="3" rx="1"/><rect x="8" y="11" width="13" height="2" rx="1"/><rect x="3" y="17" width="3" height="3" rx="1"/><rect x="8" y="17.5" width="13" height="2" rx="1"/></g></svg>
                </button>
            </span>
            <span class="count"><?= $section['total'] ?> Scan<?= $section['total'] === 1 ? '' : 's' ?></span>
        </summary>
        <div class="account-body">
            <?php foreach ($rejected[$id] ?? [] as $rejection): ?>
                <div class="flash warn rejection">
                    <strong><?= esc(date('H:i', strtotime($rejection['created_at']))) ?> Uhr – „<?= esc($rejection['original_name']) ?>“ wurde gelöscht:</strong>
                    <?= esc($rejection['reason']) ?>
                </div>
            <?php endforeach ?>
            <?php $waiting = $pending[$id] ?? [] ?>
            <?php if ($section['scans'] === [] && $waiting === []): ?>
                <p class="muted">Keine Scans vorhanden.</p>
            <?php else: ?>
            <div class="grid">
                <?php foreach ($waiting as $file): ?>
                <article class="scan pending">
                    <div class="thumb"><span class="spinner" aria-hidden="true"></span></div>
                    <div class="meta">
                        <strong title="<?= esc($file['name'], 'attr') ?>"><?= esc($file['name']) ?></strong>
                        <small><?= $file['state'] === 'uploading' ? 'Wird übertragen …' : 'Wird verarbeitet …' ?> · <?= number_to_size($file['size'], 1, 'de') ?></small>
                    </div>
                    <div class="actions"><span class="badge">ausstehend</span></div>
                </article>
                <?php endforeach ?>
                <?php foreach ($section['scans'] as $scan): ?>
                <article class="scan">
                    <a href="/scans/<?= esc($scan['public_id']) ?>/view" target="_blank" rel="noopener" class="thumb">
                        <?php if ($scan['has_thumbnail']): ?>
                            <img src="/scans/<?= esc($scan['public_id']) ?>/thumbnail" alt="Vorschau" loading="lazy">
                        <?php else: ?>
                            <span class="nothumb"><?= esc(strtoupper($scan['file_ext'] ?? 'pdf')) ?></span>
                        <?php endif ?>
                    </a>
                    <div class="meta">
                        <strong title="<?= esc($scan['original_name'], 'attr') ?>"><?= esc($scan['original_name']) ?></strong>
                        <small><?= esc(date('d.m.Y H:i', strtotime($scan['received_at']))) ?> · <?= number_to_size($scan['size'], 1, 'de') ?></small>
                    </div>
                    <div class="actions">
                        <a class="button small" href="/scans/<?= esc($scan['public_id']) ?>/download">Herunterladen</a>
                        <?= form_open('scans/' . $scan['public_id'] . '/delete', ['class' => 'inline', 'onsubmit' => "return confirm('Scan wirklich löschen?')"]) ?>
                            <button class="button small danger">Löschen</button>
                        <?= form_close() ?>
                    </div>
                </article>
                <?php endforeach ?>
            </div>

            <?php if ($section['scans'] !== []): ?>
            <div class="list-toolbar">
                <button type="button" class="button small secondary" data-select="all">Alle</button>
                <button type="button" class="button small secondary" data-select="none">Keine</button>
                <?= form_open('scans/delete', ['id' => 'bulk-' . $account['public_id'], 'class' => 'inline bulk-delete']) ?>
                    <button class="button small danger" disabled>Ausgewählte löschen</button>
                <?= form_close() ?>
            </div>
            <?php endif ?>
            <table class="table scan-list">
                <thead><tr><th class="col-check"></th><th class="col-thumb"></th><th>Datei</th><th>Eingang</th><th class="col-size">Größe</th><th class="col-actions"></th></tr></thead>
                <tbody>
                <?php foreach ($waiting as $file): ?>
                    <tr class="pending">
                        <td class="col-check"></td>
                        <td class="col-thumb"><span class="spinner small" aria-hidden="true"></span></td>
                        <td><span class="name" title="<?= esc($file['name'], 'attr') ?>"><?= esc($file['name']) ?></span></td>
                        <td class="muted"><?= $file['state'] === 'uploading' ? 'Wird übertragen …' : 'Wird verarbeitet …' ?></td>
                        <td class="col-size"><?= number_to_size($file['size'], 1, 'de') ?></td>
                        <td class="col-actions"><span class="badge">ausstehend</span></td>
                    </tr>
                <?php endforeach ?>
                <?php foreach ($section['scans'] as $scan): ?>
                    <tr>
                        <td class="col-check"><input type="checkbox" name="ids[]" value="<?= esc($scan['public_id']) ?>" form="bulk-<?= esc($account['public_id']) ?>" aria-label="<?= esc($scan['original_name'], 'attr') ?> auswählen"></td>
                        <td class="col-thumb">
                            <a href="/scans/<?= esc($scan['public_id']) ?>/view" target="_blank" rel="noopener">
                                <?php if ($scan['has_thumbnail']): ?><img src="/scans/<?= esc($scan['public_id']) ?>/thumbnail" alt="" loading="lazy"><?php else: ?><span class="type"><?= esc(strtoupper($scan['file_ext'] ?? 'pdf')) ?></span><?php endif ?>
                            </a>
                        </td>
                        <td><a class="name" href="/scans/<?= esc($scan['public_id']) ?>/view" target="_blank" rel="noopener" title="<?= esc($scan['original_name'], 'attr') ?>"><?= esc($scan['original_name']) ?></a></td>
                        <td><?= esc(date('d.m.Y H:i', strtotime($scan['received_at']))) ?></td>
                        <td class="col-size"><?= number_to_size($scan['size'], 1, 'de') ?></td>
                        <td class="col-actions">
                            <a class="button small" href="/scans/<?= esc($scan['public_id']) ?>/download">Herunterladen</a>
                            <?= form_open('scans/' . $scan['public_id'] . '/delete', ['class' => 'inline', 'onsubmit' => "return confirm('Scan wirklich löschen?')"]) ?>
                                <button class="button small danger">Löschen</button>
                            <?= form_close() ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
            <?= $pager->links($section['group']) ?>
            <?php endif ?>
        </div>
    </details>
    <?php endforeach ?>
</div>
<script src="/js/scans-accordion.js?v=<?= esc(config('ScanDock')->version) ?>" defer></script>
<?php endif ?>
<?= $this->endSection() ?>
