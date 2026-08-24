<div class="mb-3">
    <a href="<?= url('quick-captures') ?>" class="text-decoration-none text-muted small">
        <i class="bi bi-arrow-left me-1"></i>Zurück zur Liste
    </a>
</div>

<div class="row g-4">
    <!-- Submitted content -->
    <div class="col-lg-7">
        <div class="card border-secondary">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-text me-2"></i>Einsendung</span>
                <span class="text-muted small"><?= formatDateTime($capture['created_at']) ?></span>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3 text-muted">Titel</dt>
                    <dd class="col-sm-9"><?= e($capture['title']) ?></dd>

                    <dt class="col-sm-3 text-muted">Projekt / Bezug</dt>
                    <dd class="col-sm-9"><span class="badge bg-info-subtle text-info-emphasis"><?= e($capture['project_hint']) ?></span></dd>

                    <dt class="col-sm-3 text-muted">Beschreibung</dt>
                    <dd class="col-sm-9" style="white-space:pre-wrap"><?= e($capture['description']) ?: '<span class="text-muted">?</span>' ?></dd>

                    <dt class="col-sm-3 text-muted">Absender</dt>
                    <dd class="col-sm-9"><?= $capture['reporter_name'] ? e($capture['reporter_name']) : '<span class="text-muted">anonym</span>' ?></dd>

                    <dt class="col-sm-3 text-muted">Kontakt</dt>
                    <dd class="col-sm-9"><?= $capture['reporter_contact'] ? e($capture['reporter_contact']) : '<span class="text-muted">?</span>' ?></dd>

                    <?php if (!empty($capture['mower_serial'])): ?>
                    <dt class="col-sm-3 text-muted">Seriennummer</dt>
                    <dd class="col-sm-9"><?= e($capture['mower_serial']) ?></dd>
                    <?php endif; ?>

                    <?php if (!empty($capture['firmware_version'])): ?>
                    <dt class="col-sm-3 text-muted">Firmware</dt>
                    <dd class="col-sm-9"><?= e($capture['firmware_version']) ?></dd>
                    <?php endif; ?>
                </dl>

                <?php if ($files): ?>
                    <hr class="border-secondary">
                    <h6 class="text-muted"><i class="bi bi-paperclip me-1"></i>Anhänge (Quarantäne)</h6>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($files as $f): ?>
                            <li class="mb-1">
                                <i class="bi bi-file-earmark"></i>
                                <?= e($f['original_name']) ?>
                                <span class="text-muted small">(<?= e($f['mime_type']) ?>, <?= formatFileSize((int)$f['file_size']) ?>)</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="form-text">Anhänge werden bei der Freigabe in den Eintrag ?bernommen.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Approve / reject -->
    <div class="col-lg-5">
        <?php if ($capture['status'] !== 'pending'): ?>
            <div class="alert alert-secondary">Dieser Eintrag wurde bereits bearbeitet.</div>
        <?php else: ?>
            <div class="card border-secondary">
                <div class="card-header"><i class="bi bi-check2-circle me-2"></i>Freigeben &amp; ?bernehmen</div>
                <div class="card-body">
                    <?php if (!$projects): ?>
                        <div class="alert alert-warning small mb-0">
                            Du hast keinen Schreibzugriff auf ein aktives Projekt. Bitte einen Administrator,
                            dir Projektrechte zu geben.
                        </div>
                    <?php else: ?>
                        <form method="post" action="<?= url('quick-captures/' . $capture['id'] . '/approve') ?>">
                            <?= csrfField() ?>
                            <div class="mb-3">
                                <label class="form-label">Projekt <span class="text-danger">*</span></label>
                                <select name="project_id" class="form-select" required>
                                    <option value="">– wählen –</option>
                                    <?php foreach ($projects as $p): ?>
                                        <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Typ <span class="text-danger">*</span></label>
                                <select name="entry_type_id" class="form-select" required>
                                    <option value="">– wählen –</option>
                                    <?php foreach ($entryTypes as $t): ?>
                                        <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Titel</label>
                                <input type="text" name="title" class="form-control" maxlength="200" value="<?= e($capture['title']) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Beschreibung</label>
                                <textarea name="description" class="form-control" rows="4"><?= e($capture['description']) ?></textarea>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label">Seriennummer <span class="text-muted small">(optional)</span></label>
                                    <input type="text" name="mower_serial" class="form-control" maxlength="100" value="<?= e($capture['mower_serial'] ?? '') ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Firmware <span class="text-muted small">(optional)</span></label>
                                    <input type="text" name="firmware_version" class="form-control" maxlength="50" value="<?= e($capture['firmware_version'] ?? '') ?>">
                                </div>
                            </div>
                                <div class="mb-3">
                                  <label class="form-label">Zuweisen an <span class="text-muted small">(optional)</span></label>
                                  <select name="assigned_to" class="form-select">
                                    <option value="">? Keinem User zuweisen ?</option>
                                    <?php foreach ($activeUsers as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option>
                                    <?php endforeach; ?>
                                  </select>
                                  <div class="form-text">Der zugewiesene User sieht diesen Eintrag auch bei ?Nur eigene Eintr?ge".</div>
                                </div>
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-check-lg me-1"></i>Freigeben &amp; Eintrag erstellen
                            </button>
                        </form>
                    <?php endif; ?>

                    <hr class="border-secondary">
                    <form method="post" action="<?= url('quick-captures/' . $capture['id'] . '/reject') ?>"
                          onsubmit="return confirm('Diese Einsendung ablehnen? Anh?nge werden gel?scht.');">
                        <?= csrfField() ?>
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="bi bi-x-lg me-1"></i>Ablehnen
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
