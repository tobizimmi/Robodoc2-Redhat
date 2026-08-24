<?php
$tabs = ['pending' => 'Offen', 'approved' => 'Freigegeben', 'rejected' => 'Abgelehnt'];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="bi bi-inbox me-2"></i>Quick Captures</h3>
    <a href="<?= url('quick-capture') ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-box-arrow-up-right me-1"></i>Öffentliches Formular
    </a>
</div>

<ul class="nav nav-pills mb-3">
    <?php foreach ($tabs as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $status === $key ? 'active' : '' ?>"
               href="<?= url('quick-captures?status=' . $key) ?>">
                <?= e($label) ?>
                <?php if (!empty($counts[$key])): ?>
                    <span class="badge bg-secondary ms-1"><?= (int)$counts[$key] ?></span>
                <?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if (!$captures): ?>
    <div class="text-center text-muted py-5">
        <i class="bi bi-inbox" style="font-size:2.5rem"></i>
        <p class="mt-2">Keine Einträge in dieser Ansicht.</p>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Titel</th>
                    <th>Projekt / Bezug</th>
                    <th>Absender</th>
                    <th>Anhänge</th>
                    <th>Eingegangen</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($captures as $c): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($c['title']) ?></td>
                        <td><span class="badge bg-info-subtle text-info-emphasis"><?= e($c['project_hint']) ?></span></td>
                        <td><?= $c['reporter_name'] ? e($c['reporter_name']) : '<span class="text-muted">–</span>' ?></td>
                        <td>
                            <?php if ((int)$c['file_count'] > 0): ?>
                                <i class="bi bi-paperclip"></i> <?= (int)$c['file_count'] ?>
                            <?php else: ?>
                                <span class="text-muted">–</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= formatDateTime($c['created_at']) ?></td>
                        <td class="text-end">
                            <?php if ($c['status'] === 'pending'): ?>
                                <a href="<?= url('quick-captures/' . $c['id']) ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-eye me-1"></i>Prüfen
                                </a>
                            <?php elseif ($c['status'] === 'approved' && $c['entry_id']): ?>
                                <a href="<?= url('entries/' . $c['entry_id']) ?>" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>Eintrag
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">
                                    <?= $c['reviewer_name'] ? 'durch ' . e($c['reviewer_name']) : '' ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
