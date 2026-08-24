<div class="timeline-item mb-3" id="comment-<?= $comment['id'] ?>">
  <div class="timeline-dot"></div>
  <div class="d-flex align-items-start justify-content-between">
    <div>
      <strong class="small"><?= e($comment['user_name']) ?></strong>
      <span class="text-muted small ms-2"><?= formatDateTime($comment['created_at']) ?></span>
    </div>
    <?php if (Auth::isAdmin() || $comment['user_id'] == Auth::id()): ?>
    <form method="POST" action="<?= url('entries/' . $comment['entry_id'] . '/comments/' . $comment['id'] . '/delete') ?>" data-confirm="Delete comment?">
      <?= csrfField() ?>
      <button class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-trash small"></i></button>
    </form>
    <?php endif; ?>
  </div>
  <div class="mt-1" style="font-size:.875rem;white-space:pre-wrap"><?= e($comment['body']) ?></div>
</div>
