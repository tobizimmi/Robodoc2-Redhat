<?php
$csrf = Auth::csrfToken();
$typeColors  = ['bug'=>'danger','improvement'=>'warning','question'=>'info','other'=>'secondary'];
$statusColors= ['open'=>'primary','todo'=>'warning','done'=>'success','rejected'=>'secondary'];
?>
<div class="mb-3">
  <a href="<?= url('admin/feedback') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
</div>
<div class="row g-4">
  <div class="col-md-8">
    <div class="card border-secondary mb-3">
      <div class="card-header border-secondary d-flex align-items-center gap-2">
        <span class="badge bg-<?= $typeColors[$item['type']] ?? 'secondary' ?>"><?= ucfirst($item['type']) ?></span>
        <span class="fw-semibold"><?= e($item['title']) ?></span>
        <span class="ms-auto text-muted small">#<?= $item['id'] ?></span>
      </div>
      <div class="card-body">
        <div class="text-white" style="white-space:pre-wrap"><?= e($item['message']) ?></div>
        <div class="text-muted small mt-3">
          <i class="bi bi-person me-1"></i><?= e($item['user_name'] ?? 'Unknown') ?> &middot;
          <?= date('d.m.Y H:i', strtotime($item['created_at'])) ?>
        </div>
      </div>
    </div>
    <!-- Attachments -->
    <?php if (!empty($attachments)): ?>
    <div class="card border-secondary mb-3">
      <div class="card-header border-secondary small fw-semibold">
        <i class="bi bi-paperclip me-1"></i>Attachments (<?= count($attachments) ?>)
      </div>
      <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($attachments as $att): ?>
          <a href="<?= url('tool-feedback/attachments/'.$att['id']) ?>" target="_blank"
             class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-paperclip"></i><?= e($att['original_name']) ?>
            <span class="text-muted" style="font-size:.7rem"><?= round($att['file_size']/1024) ?>KB</span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <!-- Comments -->
    <?php if ($comments): ?>
    <div class="card border-secondary mb-3">
      <div class="card-header border-secondary small fw-semibold">Comments</div>
      <div class="card-body p-0">
        <?php foreach ($comments as $i=>$c): ?>
        <div class="px-3 py-2 <?= $i>0?'border-top border-secondary':'' ?>">
          <div class="d-flex gap-2 align-items-start">
            <i class="bi bi-chat-left-quote text-info mt-1"></i>
            <div>
              <div class="small fw-semibold"><?= e($c['user_name'] ?? 'Admin') ?>
                <span class="text-muted fw-normal ms-2"><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></span>
              </div>
              <div class="text-muted small" style="white-space:pre-wrap"><?= e($c['comment']) ?></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <!-- Add comment -->
    <div class="card border-secondary">
      <div class="card-header border-secondary small fw-semibold">Add Comment</div>
      <div class="card-body">
        <form method="POST" action="<?= url('admin/feedback/'.$item['id'].'/comment') ?>">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <textarea name="comment" class="form-control mb-2" rows="3"
                    placeholder="Write a comment…"></textarea>
          <button class="btn btn-primary btn-sm"><i class="bi bi-send me-1"></i>Send</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-secondary">
      <div class="card-header border-secondary small fw-semibold">Status</div>
      <div class="card-body">
        <div class="mb-3">
          <span class="badge bg-<?= $statusColors[$item['status']] ?? 'secondary' ?> fs-6">
            <?= ucfirst($item['status']) ?>
          </span>
        </div>
        <form method="POST" action="<?= url('admin/feedback/'.$item['id'].'/status') ?>">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <div class="d-grid gap-2">
            <?php foreach (['open'=>['primary','inbox'],'todo'=>['warning','clock'],'done'=>['success','check-circle'],'rejected'=>['secondary','x-circle']] as $s=>[$c,$icon]): ?>
            <?php if ($item['status'] !== $s): ?>
            <button type="submit" name="status" value="<?= $s ?>"
                    class="btn btn-outline-<?= $c ?> btn-sm text-start">
              <i class="bi bi-<?= $icon ?> me-2"></i>Mark as <?= ucfirst($s) ?>
            </button>
            <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
