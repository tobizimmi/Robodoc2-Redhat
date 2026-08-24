<?php
// Count totals for tabs
$activeItems  = array_filter($items ?? [], fn($i) => $i['status'] === 'pending');
$archiveItems = array_filter($items ?? [], fn($i) => in_array($i['status'], ['rejected','imported']));
$fTab = $fTab ?? 'active';
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0"><i class="bi bi-chat-left-text me-2 text-info"></i>Feedback
    <span class="badge bg-secondary ms-2"><?= number_format($total) ?></span>
  </h5>
  <div class="d-flex gap-2">
    <a href="<?= url('quick-capture') ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-box-arrow-up-right me-1"></i>Quick Capture Formular
    </a>
    <button class="btn btn-outline-secondary btn-sm"
            onclick="navigator.clipboard.writeText(window.location.origin + '<?= e(url('quick-capture')) ?>');this.innerHTML='<i class=&quot;bi bi-check&quot;></i> Kopiert'">
      <i class="bi bi-clipboard me-1"></i>Link kopieren
    </button>
  </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs border-secondary mb-3">
  <li class="nav-item">
    <a class="nav-link <?= $fTab === 'active' ? 'active' : 'text-muted' ?>"
       href="?<?= http_build_query(array_merge($_GET, ['tab'=>'active','page'=>1])) ?>">
      <i class="bi bi-inbox me-1"></i>Aktiv
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $fTab === 'archive' ? 'active' : 'text-muted' ?>"
       href="?<?= http_build_query(array_merge($_GET, ['tab'=>'archive','page'=>1])) ?>">
      <i class="bi bi-archive me-1"></i>Archiv
      <span class="badge bg-secondary ms-1" style="font-size:.65rem">Abgelehnt &amp; Importiert</span>
    </a>
  </li>
</ul>

<!-- Filters -->
<form method="GET" action="<?= url('feedback') ?>" class="card border-secondary p-3 mb-4">
  <input type="hidden" name="tab" value="<?= e($fTab) ?>">
  <div class="row g-2 align-items-end">
    <div class="col-md-2">
      <label class="form-label small mb-1 text-muted">Typ</label>
      <select name="type" class="form-select form-select-sm">
        <option value="">Alle</option>
        <option value="qc" <?= ($fType??'')==='qc' ? 'selected':'' ?>>Quick Capture</option>
        <option value="tc" <?= ($fType??'')==='tc' ? 'selected':'' ?>>Testkunden</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-1 text-muted">Testauftrag</label>
      <select name="order_id" class="form-select form-select-sm">
        <option value="">Alle Aufträge</option>
        <?php foreach ($orders as $o): ?>
        <option value="<?= $o['id'] ?>" <?= ($fOrder??0)==$o['id'] ? 'selected':'' ?>><?= e($o['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-1 text-muted">Testkunde</label>
      <select name="customer_id" class="form-select form-select-sm">
        <option value="">Alle Kunden</option>
        <?php foreach ($customers as $tc): ?>
        <option value="<?= $tc['id'] ?>" <?= ($fCustomer??0)==$tc['id'] ? 'selected':'' ?>>
          <?= e($tc['customer_number']) ?> &ndash; <?= e($tc['label']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label small mb-1 text-muted">Suche</label>
      <input type="text" name="search" class="form-control form-control-sm"
             placeholder="Titel oder Beschreibung..." value="<?= e($fSearch ?? '') ?>">
    </div>
    <div class="col-auto d-flex gap-1">
      <button type="submit" class="btn btn-primary btn-sm px-3"><i class="bi bi-search"></i></button>
      <?php if (($fType??'')||($fOrder??0)||($fCustomer??0)||($fSearch??'')): ?>
      <a href="?tab=<?= e($fTab) ?>" class="btn btn-outline-secondary btn-sm px-3"><i class="bi bi-x"></i></a>
      <?php endif; ?>
    </div>
  </div>
</form>

<?php if (empty($items)): ?>
<div class="text-center text-muted py-5">
  <i class="bi bi-inbox" style="font-size:3rem;opacity:.2"></i>
  <p class="mt-3"><?= $fTab === 'archive' ? 'Kein archiviertes Feedback.' : 'Kein aktives Feedback — alles erledigt!' ?></p>
</div>
<?php else: ?>
<div class="d-flex flex-column gap-2">
<?php foreach ($items as $item):
  $isPending  = $item['status'] === 'pending';
  $isImported = $item['status'] === 'imported';
  $isRejected = $item['status'] === 'rejected';
  $isQC       = $item['type']   === 'qc';
?>
<div class="card <?= $isPending ? 'border-warning' : 'border-secondary' ?>">
  <div class="card-body py-2 px-3">
    <div class="row align-items-center g-2">

      <!-- Type + Status -->
      <div class="col-auto d-flex flex-column gap-1 align-items-start" style="min-width:80px">
        <span class="badge <?= $isQC ? 'bg-primary' : 'bg-warning text-dark' ?>" style="font-size:.6rem">
          <?= $isQC ? 'Quick Capture' : 'Testkunde' ?>
        </span>
        <?php if ($isPending): ?>
          <span class="badge bg-warning text-dark" style="font-size:.6rem">NEU</span>
        <?php elseif ($isImported): ?>
          <span class="badge bg-success" style="font-size:.6rem">Importiert</span>
        <?php elseif ($isRejected): ?>
          <span class="badge bg-danger" style="font-size:.6rem">Abgelehnt</span>
        <?php endif; ?>
      </div>

      <!-- Content -->
      <div class="col">
        <a href="<?= e($item['detail_url']) ?>" class="fw-semibold text-white text-decoration-none">
          <?= e($item['title'] ?: '(kein Titel)') ?>
        </a>
        <?php if ($item['description']): ?>
        <div class="text-muted small mt-1" style="line-height:1.4">
          <?= e(mb_substr($item['description'], 0, 120)) ?><?= mb_strlen($item['description'] ?? '') > 120 ? '...' : '' ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Source info -->
      <div class="col-md-3">
        <?php if ($item['order_title']): ?>
        <div class="d-flex align-items-center gap-1 mb-1">
          <?php if ($item['project_color']): ?>
          <span class="badge" style="background:<?= e($item['project_color']) ?>;font-size:.6rem">
            <?= e($item['project_name']) ?>
          </span>
          <?php endif; ?>
          <a href="<?= url('test-customers/' . $item['order_id']) ?>"
             class="text-muted small text-decoration-none">
            <?= e(mb_substr($item['order_title'], 0, 30)) ?>
          </a>
        </div>
        <?php endif; ?>
        <?php if ($item['customer_num']): ?>
        <div class="small">
          <i class="bi bi-person-badge text-warning me-1"></i>
          <code class="text-warning"><?= e($item['customer_num']) ?></code>
          <?php if ($item['customer_name']): ?>
          <span class="text-muted ms-1"><?= e(mb_substr($item['customer_name'], 0, 20)) ?></span>
          <?php endif; ?>
        </div>
        <?php elseif ($item['sender']): ?>
        <div class="text-muted small"><i class="bi bi-person me-1"></i><?= e($item['sender']) ?></div>
        <?php endif; ?>
      </div>

      <!-- Technical + date -->
      <div class="col-md-2 text-end">
        <?php if ($item['mower_serial']): ?>
        <div class="text-muted small"><code><?= e($item['mower_serial']) ?></code></div>
        <?php endif; ?>
        <?php if ($item['firmware']): ?>
        <div class="text-muted small">FW <?= e($item['firmware']) ?></div>
        <?php endif; ?>
        <?php if ($item['file_count'] > 0): ?>
        <div class="text-muted small"><i class="bi bi-paperclip me-1"></i><?= $item['file_count'] ?></div>
        <?php endif; ?>
        <div class="text-muted small mt-1"><?= date('d.m.Y H:i', strtotime($item['created_at'])) ?></div>
      </div>

      <!-- Action -->
      <div class="col-auto d-flex gap-1">
        <?php if ($isRejected && $item['type'] === 'tc' && $item['order_id']): ?>
        <form method="POST" action="<?= url('test-customers/'.$item['order_id'].'/feedback/'.$item['id'].'/reopen') ?>">
          <?= csrfField() ?>
          <button type="submit" class="btn btn-outline-warning btn-sm py-0 px-2" title="Wieder öffnen">
            <i class="bi bi-arrow-counterclockwise"></i>
          </button>
        </form>
        <?php endif; ?>
        <a href="<?= e($item['detail_url']) ?>" class="btn btn-outline-secondary btn-sm py-0 px-2">
          <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php if ($pag['pages'] > 1): ?>
<nav class="mt-4 d-flex justify-content-center">
  <ul class="pagination pagination-sm">
    <?php if ($pag['has_prev']): ?>
    <li class="page-item">
      <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$pag['page']-1])) ?>">&lsaquo;</a>
    </li>
    <?php endif; ?>
    <?php for ($i=max(1,$pag['page']-2);$i<=min($pag['pages'],$pag['page']+2);$i++): ?>
    <li class="page-item <?= $i===$pag['page']?'active':'' ?>">
      <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"><?= $i ?></a>
    </li>
    <?php endfor; ?>
    <?php if ($pag['has_next']): ?>
    <li class="page-item">
      <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$pag['page']+1])) ?>">&rsaquo;</a>
    </li>
    <?php endif; ?>
  </ul>
</nav>
<?php endif; ?>
<?php endif; ?>
