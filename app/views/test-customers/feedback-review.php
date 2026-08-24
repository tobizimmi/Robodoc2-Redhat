<div class="mb-3">
  <a href="<?= url('test-customers/' . $order['id']) ?>" class="text-decoration-none text-muted small">
    <i class="bi bi-arrow-left me-1"></i>Zurück zum Auftrag
  </a>
</div>

<div class="row g-4">
  <!-- LEFT: Feedback details -->
  <div class="col-lg-7">
    <div class="card border-secondary">
      <div class="card-header d-flex justify-content-between align-items-center border-secondary">
        <span><i class="bi bi-chat-left-text me-2"></i>Kunden Feedback</span>
        <span class="text-muted small"><?= date('d.m.Y H:i', strtotime($fb['created_at'])) ?></span>
      </div>
      <div class="card-body">
        <?php if (!empty($respondent)): ?>
          <div class="alert alert-warning py-2 mb-3 d-flex align-items-center gap-3">
            <i class="bi bi-person-badge fs-4 flex-shrink-0"></i>
            <div>
              <div class="fw-semibold">
                <code><?= e($respondent['tc_number'] ?? $respondent['customer_number']) ?></code>
                &nbsp;<?= e($respondent['tc_label'] ?? $respondent['label']) ?>
              </div>
              <?php $respEmail = $respondent['tc_email'] ?? $respondent['email'] ?? null; ?>
              <?php if ($respEmail): ?>
              <div class="small"><a href="mailto:<?= e($respEmail) ?>"><?= e($respEmail) ?></a></div>
              <?php endif; ?>
              <div class="text-muted small mt-1">Feedback via persönlichem Testkunden-Link</div>
            </div>
          </div>
          <?php endif; ?>
          <dl class="row mb-0">
          <dt class="col-sm-3 text-muted">Titel</dt>
          <dd class="col-sm-9 fw-semibold"><?= e($fb['title']) ?></dd>

          <dt class="col-sm-3 text-muted">Beschreibung</dt>
          <dd class="col-sm-9" style="white-space:pre-wrap"><?= e($fb['description']) ?: '<span class="text-muted">–</span>' ?></dd>

          <?php if (empty($respondent)): ?>
          <dt class="col-sm-3 text-muted">Absender</dt>
          <dd class="col-sm-9"><?= $fb['respondent_name'] ? e($fb['respondent_name']) : '<span class="text-muted">anonym</span>' ?></dd>

          <dt class="col-sm-3 text-muted">Kontakt</dt>
          <dd class="col-sm-9"><?= $fb['respondent_contact'] ? e($fb['respondent_contact']) : '<span class="text-muted">–</span>' ?></dd>
          <?php endif; ?>

          <?php if ($fb['mower_serial']): ?>
          <dt class="col-sm-3 text-muted">Seriennummer</dt>
          <dd class="col-sm-9"><code><?= e($fb['mower_serial']) ?></code></dd>
          <?php endif; ?>

          <?php if ($fb['firmware_version']): ?>
          <dt class="col-sm-3 text-muted">Firmware</dt>
          <dd class="col-sm-9"><code><?= e($fb['firmware_version']) ?></code></dd>
          <?php endif; ?>

          <dt class="col-sm-3 text-muted">Auftrag</dt>
          <dd class="col-sm-9">
            <a href="<?= url('test-customers/' . $order['id']) ?>"><?= e($order['title']) ?></a>
            <span class="badge ms-1" style="background:<?= e($order['project_color'] ?? '#666') ?>;font-size:.7rem">
              <?= e($order['project_name']) ?>
            </span>
          </dd>
        </dl>

        <?php if (!empty($attachments)): ?>
        <hr class="border-secondary">
        <h6 class="text-muted"><i class="bi bi-paperclip me-1"></i>Anhänge</h6>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($attachments as $att): ?>
          <?php $isImg = str_starts_with($att['mime'] ?? '', 'image/'); ?>
          <?php if ($isImg): ?>
          <a href="<?= e($att['url']) ?>" target="_blank">
            <img src="<?= e($att['url']) ?>" style="height:80px;width:auto;border-radius:4px;object-fit:cover;border:1px solid #444">
          </a>
          <?php else: ?>
          <a href="<?= e($att['url']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark me-1"></i><?= e($att['name']) ?>
          </a>
          <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- RIGHT: Actions -->
  <div class="col-lg-5">
    <?php if ($fb['status'] === 'imported' && $fb['entry_id']): ?>
    <div class="alert alert-success">
      <i class="bi bi-check-circle me-2"></i>
      Als Entry importiert:
      <a href="<?= url('entries/' . $fb['entry_id']) ?>" class="alert-link">#<?= $fb['entry_id'] ?></a>
    </div>
    <?php else: ?>
    <?php if ($fb['status'] === 'reviewed'): ?>
    <div class="alert alert-secondary mb-3 py-2 small">
      <i class="bi bi-eye me-2"></i>Als gesehen markiert — kann aber noch importiert werden.
    </div>
    <?php endif; ?>
    <div class="card border-secondary mb-3">
      <div class="card-header border-secondary">
        <i class="bi bi-download me-2"></i>Als Entry importieren
      </div>
      <div class="card-body">
        <form method="POST" action="<?= url('test-customers/' . $order['id'] . '/feedback/' . $fb['id'] . '/review') ?>">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="import">
          <div class="mb-3">
            <label class="form-label">Typ <span class="text-danger">*</span></label>
            <select name="entry_type_id" class="form-select" required>
              <option value="">– wählen –</option>
              <?php foreach ($entryTypes as $t): ?>
              <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Titel</label>
            <input type="text" name="title" class="form-control" value="<?= e($fb['title']) ?>" maxlength="200">
          </div>
          <div class="mb-3">
            <label class="form-label">Beschreibung</label>
            <textarea name="description" class="form-control" rows="4"><?= e($fb['description'] ?? '') ?></textarea>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label">Seriennummer</label>
              <input type="text" name="mower_serial" class="form-control" value="<?= e($fb['mower_serial'] ?? '') ?>">
            </div>
            <div class="col-6">
              <label class="form-label">Firmware</label>
              <input type="text" name="firmware_version" class="form-control" value="<?= e($fb['firmware_version'] ?? '') ?>">
            </div>
          </div>
          <button type="submit" class="btn btn-success w-100">
            <i class="bi bi-check-lg me-1"></i>Freigeben &amp; Entry erstellen
          </button>
        </form>

        <hr class="border-secondary">

        <form method="POST" action="<?= url('test-customers/' . $order['id'] . '/feedback/' . $fb['id'] . '/review') ?>">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="review">
          <button type="submit" class="btn btn-outline-danger w-100">
            <i class="bi bi-x-circle me-1"></i>Ablehnen
          </button>
        </form>

        <hr class="border-secondary">

        <form method="POST" action="<?= url('test-customers/' . $order['id'] . '/feedback/' . $fb['id'] . '/delete') ?>"
              onsubmit="return confirm('Feedback unwiderruflich löschen?')">
          <?= csrfField() ?>
          <button type="submit" class="btn btn-outline-danger w-100">
            <i class="bi bi-trash me-1"></i>Löschen (unqualifiziert)
          </button>
        </form>
      </div>
    </div>
    <?php endif; // not imported ?>
  </div>
</div>
