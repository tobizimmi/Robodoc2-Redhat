<?php
$typeColors = ['bug'=>'danger','improvement'=>'warning','question'=>'info','other'=>'secondary'];
$statusColors = ['open'=>'primary','todo'=>'warning','done'=>'success','rejected'=>'secondary'];
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h5 class="mb-0"><i class="bi bi-chat-left-text me-2 text-info"></i>My Feedback</h5>
  <a href="<?= url('tool-feedback/new') ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>Submit Feedback
  </a>
</div>
<?php if (!$items): ?>
<div class="text-center text-muted py-5">
  <i class="bi bi-chat-left-text fs-1 d-block mb-2 opacity-25"></i>
  No feedback submitted yet.
  <div class="mt-3">
    <a href="<?= url('tool-feedback/new') ?>" class="btn btn-primary btn-sm">Submit your first feedback</a>
  </div>
</div>
<?php else: ?>
<div class="list-group">
  <?php foreach ($items as $item): ?>
  <div class="list-group-item list-group-item-action bg-transparent border-secondary mb-2 rounded">
    <div class="d-flex align-items-start gap-3">
      <div class="flex-grow-1">
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="badge bg-<?= $typeColors[$item['type']] ?? 'secondary' ?>">
            <?= ucfirst($item['type']) ?>
          </span>
          <span class="badge bg-<?= $statusColors[$item['status']] ?? 'secondary' ?>">
            <?= ucfirst($item['status']) ?>
          </span>
          <span class="fw-semibold"><?= e($item['title']) ?></span>
        </div>
        <div class="text-muted small"><?= e(mb_substr($item['message'], 0, 120)) ?><?= mb_strlen($item['message'])>120?'…':'' ?></div>
        <div class="d-flex align-items-center gap-3 text-muted" style="font-size:.75rem;margin-top:4px">
          <span><?= date('d.m.Y H:i', strtotime($item['created_at'])) ?></span>
          <?php if (!empty($attCounts[$item['id']])): ?>
          <span><i class="bi bi-paperclip me-1"></i><?= $attCounts[$item['id']] ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
