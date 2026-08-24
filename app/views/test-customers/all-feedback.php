<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <a href="<?= url('test-customers') ?>" class="text-muted small">
      <i class="bi bi-arrow-left me-1"></i>Aufträge
    </a>
    <h5 class="mt-1 mb-0">
      <i class="bi bi-chat-left-text me-2 text-info"></i>Gesamtes Feedback
      <span class="badge bg-secondary ms-2"><?= number_format($total) ?></span>
    </h5>
  </div>
</div>

<!-- Filters -->
<form method="GET" action="<?= url('test-customers/feedback') ?>" class="card border-secondary p-3 mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small mb-1 text-muted">Testauftrag</label>
      <select name="order_id" class="form-select form-select-sm">
        <option value="">Alle Aufträge</option>
        <?php foreach ($orders as $o): ?>
        <option value="<?= $o['id'] ?>" <?= $fOrder == $o['id'] ? 'selected' : '' ?>>
          <?= e($o['title']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-1 text-muted">Testkunde</label>
      <select name="customer_id" class="form-select form-select-sm">
        <option value="">Alle Testkunden</option>
        <?php foreach ($customers as $tc): ?>
        <option value="<?= $tc['id'] ?>" <?= $fCustomer == $tc['id'] ? 'selected' : '' ?>>
          <?= e($tc['customer_number']) ?> &ndash; <?= e($tc['label']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-1 text-muted">Status</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">Alle</option>
        <option value="pending"  <?= $fStatus === 'pending'  ? 'selected' : '' ?>>Neu</option>
        <option value="reviewed" <?= $fStatus === 'reviewed' ? 'selected' : '' ?>>Gesehen</option>
        <option value="imported" <?= $fStatus === 'imported' ? 'selected' : '' ?>>Importiert</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-1 text-muted">Suche</label>
      <input type="text" name="search" class="form-control form-control-sm"
             placeholder="Titel oder Beschreibung..." value="<?= e($fSearch) ?>">
    </div>
    <div class="col-auto d-flex gap-1">
      <button type="submit" class="btn btn-primary btn-sm px-3">
        <i class="bi bi-search"></i>
      </button>
      <?php if ($fOrder || $fCustomer || $fStatus || $fSearch): ?>
      <a href="<?= url('test-customers/feedback') ?>" class="btn btn-outline-secondary btn-sm px-3">
        <i class="bi bi-x"></i>
      </a>
      <?php endif; ?>
    </div>
  </div>
</form>

<?php if (empty($feedback)): ?>
<div class="text-center text-muted py-5">
  <i class="bi bi-inbox" style="font-size:3rem;opacity:.2"></i>
  <p class="mt-3">Kein Feedback gefunden.</p>
</div>
<?php else: ?>

<!-- Cards instead of table for better readability -->
<div class="d-flex flex-column gap-2">
<?php foreach ($feedback as $fb):
  $isPending  = $fb['status'] === 'pending';
  $isImported = $fb['status'] === 'imported';
  $customerLabel = $fb['tc_number'] ?? $fb['resp_number'] ?? null;
  $customerName  = $fb['tc_label']  ?? $fb['resp_label']  ?? ($fb['respondent_name'] ?? null);
?>
<div class="card border-secondary <?= $isPending ? 'border-warning' : '' ?>">
  <div class="card-body py-2 px-3">
    <div class="row align-items-center g-2">

      <!-- Status indicator -->
      <div class="col-auto">
        <?php if ($isPending): ?>
          <span class="badge bg-warning text-dark">NEU</span>
        <?php elseif ($isImported): ?>
          <span class="badge bg-success">Importiert</span>
        <?php else: ?>
          <span class="badge bg-secondary">Gesehen</span>
        <?php endif; ?>
      </div>

      <!-- Main content -->
      <div class="col">
        <a href="<?= url('test-customers/' . $fb['order_id'] . '/feedback/' . $fb['id']) ?>"
           class="fw-semibold text-white text-decoration-none d-block">
          <?= e($fb['title']) ?>
        </a>
        <?php if ($fb['description']): ?>
        <div class="text-muted small mt-1" style="line-height:1.4">
          <?= e(mb_substr($fb['description'], 0, 120)) ?><?= mb_strlen($fb['description']) > 120 ? '...' : '' ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Meta info -->
      <div class="col-md-3">
        <!-- Auftrag -->
        <div class="d-flex align-items-center gap-1 mb-1">
          <?php if ($fb['project_color']): ?>
          <span class="badge" style="background:<?= e($fb['project_color']) ?>;font-size:.6rem">
            <?= e($fb['project_name']) ?>
          </span>
          <?php endif; ?>
          <a href="<?= url('test-customers/' . $fb['order_id']) ?>"
             class="text-muted small text-decoration-none">
            <?= e(mb_substr($fb['order_title'], 0, 30)) ?>
          </a>
        </div>
        <!-- Testkunde -->
        <?php if ($customerLabel): ?>
        <div class="small">
          <i class="bi bi-person-badge text-warning me-1"></i>
          <code class="text-warning"><?= e($customerLabel) ?></code>
          <?php if ($customerName): ?>
          <span class="text-muted ms-1"><?= e(mb_substr($customerName, 0, 20)) ?></span>
          <?php endif; ?>
        </div>
        <?php elseif ($customerName): ?>
        <div class="text-muted small">
          <i class="bi bi-person me-1"></i><?= e($customerName) ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Technical + date -->
      <div class="col-md-2 text-end">
        <?php if ($fb['mower_serial']): ?>
        <div class="text-muted small"><code><?= e($fb['mower_serial']) ?></code></div>
        <?php endif; ?>
        <?php if ($fb['firmware_version']): ?>
        <div class="text-muted small">FW <?= e($fb['firmware_version']) ?></div>
        <?php endif; ?>
        <div class="text-muted small mt-1"><?= date('d.m.Y H:i', strtotime($fb['created_at'])) ?></div>
      </div>

      <!-- Action -->
      <div class="col-auto">
        <a href="<?= url('test-customers/' . $fb['order_id'] . '/feedback/' . $fb['id']) ?>"
           class="btn btn-outline-secondary btn-sm py-0 px-2">
          <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($pag['pages'] > 1): ?>
<nav class="mt-4 d-flex justify-content-center">
  <ul class="pagination pagination-sm">
    <?php if ($pag['has_prev']): ?>
    <li class="page-item">
      <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $pag['page'] - 1])) ?>">&lsaquo;</a>
    </li>
    <?php endif; ?>
    <?php for ($i = max(1, $pag['page']-2); $i <= min($pag['pages'], $pag['page']+2); $i++): ?>
    <li class="page-item <?= $i === $pag['page'] ? 'active' : '' ?>">
      <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
    </li>
    <?php endfor; ?>
    <?php if ($pag['has_next']): ?>
    <li class="page-item">
      <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $pag['page'] + 1])) ?>">&rsaquo;</a>
    </li>
    <?php endif; ?>
  </ul>
</nav>
<?php endif; ?>
<?php endif; ?>
