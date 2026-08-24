<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
  <h5 class="mb-0 flex-grow-1">Test Requests</h5>
  <a href="<?= url('test-requests/templates') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-layout-text-window me-1"></i>Templates
  </a>
  <a href="<?= url('test-requests/import-jira') ?>" class="btn btn-outline-info btn-sm">
    <i class="bi bi-download me-1"></i>Import from Jira
  </a>
  <a href="<?= url('test-requests/create') ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>New Request
  </a>
</div>

<?php if (!$requests): ?>
<div class="text-muted text-center py-5">No test requests yet. <a href="<?= url('test-requests/create') ?>">Create the first one.</a></div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-hover align-middle small">
  <thead class="table-dark">
    <tr>
      <th>#</th>
      <th>Summary</th>
      <th>Product</th>
      <th>Project</th>
      <th>Development Type</th>
      <th>Status</th>
      <th>Jira</th>
      <th>Created</th>
      <th>By</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($requests as $r): ?>
  <tr>
    <td class="text-muted"><?= $r['id'] ?></td>
    <td>
      <a href="<?= url('test-requests/' . $r['id']) ?>" class="text-white text-decoration-none fw-semibold">
        <?= e($r['summary']) ?>
      </a>
      <?php if ($r['labels']): ?>
      <div class="mt-1">
        <?php foreach (array_filter(array_map('trim', explode(',', $r['labels']))) as $lbl): ?>
        <span class="badge bg-secondary"><?= e($lbl) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </td>
    <td><?= e($r['product'] ?? '') ?></td>
    <td><?= e(($r['project_name'] ?? '') . ($r['project_number'] ? ' (' . $r['project_number'] . ')' : '')) ?></td>
    <td><?= e($r['development_type'] ?? '') ?></td>
    <td><?= self_statusBadge($r['status']) ?></td>
    <td>
      <?php if ($r['jira_issue_key']): ?>
      <div class="d-flex align-items-center gap-1">
        <a href="<?= e($r['jira_issue_url']) ?>" target="_blank" class="badge bg-primary text-decoration-none">
          <?= e($r['jira_issue_key']) ?>
        </a>
        <?php if (!empty($r['jira_has_changes'])): ?>
        <a href="<?= url('jira-sync/test-request/' . $r['id']) ?>"
           title="Jira has new changes" class="text-warning">
          <i class="bi bi-arrow-repeat"></i>
        </a>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <span class="text-muted">—</span>
      <?php endif; ?>
    </td>
    <td class="text-muted"><?= formatDate($r['created_at']) ?></td>
    <td class="text-muted"><?= e($r['creator_name'] ?? '') ?></td>
    <td>
      <div class="d-flex gap-1 justify-content-end">
        <a href="<?= url('test-requests/' . $r['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm py-0 px-1">
          <i class="bi bi-pencil"></i>
        </a>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<?php
function self_statusBadge(string $status): string {
    $map = [
        'draft'     => 'secondary',
        'submitted' => 'info',
        'approved'  => 'success',
        'rejected'  => 'danger',
        'closed'    => 'dark',
    ];
    $color = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . ucfirst($status) . '</span>';
}
?>
