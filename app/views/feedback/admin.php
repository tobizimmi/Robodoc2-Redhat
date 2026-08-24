<?php
$csrf = Auth::csrfToken();
$typeColors = ['bug'=>'danger','improvement'=>'warning','question'=>'info','other'=>'secondary'];
$statusColors = ['open'=>'primary','todo'=>'warning','done'=>'success','rejected'=>'secondary'];
$typeIcons = ['bug'=>'bi-bug','improvement'=>'bi-lightbulb','question'=>'bi-question-circle','other'=>'bi-three-dots'];
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h5 class="mb-0"><i class="bi bi-inbox me-2 text-info"></i>Feedback Inbox</h5>
  <div class="d-flex gap-1">
    <?php foreach (['open'=>'primary','todo'=>'warning','done'=>'success','rejected'=>'secondary','all'=>'light'] as $s=>$c): ?>
    <a href="<?= url('admin/feedback') ?>?status=<?= $s ?>"
       class="btn btn-sm btn-<?= $status===$s?'':('outline-') ?><?= $c ?>">
      <?= ucfirst($s) ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php if (!$items): ?>
<div class="text-center text-muted py-5">
  <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>No feedback in this category.
</div>
<?php else: ?>
<div class="list-group">
  <?php foreach ($items as $item): ?>
  <a href="<?= url('admin/feedback/'.$item['id']) ?>"
     class="list-group-item list-group-item-action bg-transparent border-secondary mb-2 rounded text-decoration-none">
    <div class="d-flex align-items-start gap-3">
      <i class="bi <?= $typeIcons[$item['type']] ?? 'bi-chat' ?> text-<?= $typeColors[$item['type']] ?? 'secondary' ?> mt-1 fs-5"></i>
      <div class="flex-grow-1">
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="badge bg-<?= $statusColors[$item['status']] ?? 'secondary' ?>"><?= ucfirst($item['status']) ?></span>
          <span class="fw-semibold text-white"><?= e($item['title']) ?></span>
        </div>
        <div class="text-muted small"><?= e(mb_substr($item['message'],0,100)) ?><?= mb_strlen($item['message'])>100?'…':'' ?></div>
        <div class="text-muted" style="font-size:.75rem;margin-top:3px">
          <i class="bi bi-person me-1"></i><?= e($item['user_name'] ?? 'Unknown') ?> &middot;
          <?= date('d.m.Y H:i', strtotime($item['created_at'])) ?>
        </div>
      </div>
      <i class="bi bi-chevron-right text-muted mt-1"></i>
    </div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
