<?php
$csrf = Auth::csrfToken();
$tabs = [
    'all'          => ['label' => 'All Entries',          'icon' => 'bi-journal-text',    'color' => 'secondary'],
    'robodoc_only' => ['label' => 'RoboDoc only',         'icon' => 'bi-robot',           'color' => 'info'],
    'jira_only'    => ['label' => 'RoboDoc + Jira',       'icon' => 'bi-bug-fill',        'color' => 'warning'],
    'zentao_only'  => ['label' => 'RoboDoc + Zentao',     'icon' => 'bi-bug',             'color' => 'primary'],
    'both'         => ['label' => 'Jira + Zentao',        'icon' => 'bi-diagram-3',       'color' => 'success'],
];
$statusColors = ['open'=>'secondary','in_progress'=>'info','resolved'=>'success','closed'=>'dark'];
$prioColors   = ['critical'=>'danger','high'=>'warning','low'=>'secondary','medium'=>'info'];
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Integration Overview</h5>
    <small class="text-muted">See which entries exist in RoboDoc, Jira and/or Zentao</small>
  </div>
  <a href="<?= url('entries') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Entries</a>
</div>

<!-- Filter tabs with counts -->
<ul class="nav nav-tabs mb-4">
  <?php foreach ($tabs as $key => $tab): ?>
  <li class="nav-item">
    <a class="nav-link <?= $filter===$key?'active':'' ?>" href="?filter=<?= $key ?>">
      <i class="bi <?= $tab['icon'] ?> me-1"></i>
      <?= $tab['label'] ?>
      <span class="badge ms-1 <?= $filter===$key?'bg-primary':'bg-secondary' ?>"><?= $counts[$key] ?? 0 ?></span>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<!-- Legend -->
<?php if ($filter === 'all'): ?>
<div class="d-flex flex-wrap gap-3 mb-4 small text-muted">
  <span><i class="bi bi-robot me-1 text-info"></i>RoboDoc only ? no Jira/Zentao ticket</span>
  <span><i class="bi bi-bug-fill me-1 text-warning"></i>Linked to Jira</span>
  <span><i class="bi bi-bug me-1 text-primary"></i>Linked to Zentao</span>
  <span><i class="bi bi-diagram-3 me-1 text-success"></i>Linked to both</span>
</div>
<?php endif; ?>

<?php if (!$entries): ?>
<div class="text-center text-muted py-5">
  <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
  No entries found for this filter.
</div>
<?php else: ?>
<div class="table-responsive">
  <table class="table table-dark table-hover align-middle" style="font-size:.83rem">
    <thead class="text-muted" style="font-size:.72rem">
      <tr>
        <th>Title</th>
        <th>Project</th>
        <th>Status</th>
        <th>Jira</th>
        <th>Zentao</th>
        <th>Created</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($entries as $e):
        $hasJira   = !empty($e['jira_issue_key']);
        $hasZentao = !empty($e['zentao_bug_id']);
        $integIcon = match(true) {
          $hasJira && $hasZentao => '<i class="bi bi-diagram-3 text-success" title="Jira + Zentao"></i>',
          $hasJira               => '<i class="bi bi-bug-fill text-warning" title="Jira"></i>',
          $hasZentao             => '<i class="bi bi-bug text-primary" title="Zentao"></i>',
          default                => '<i class="bi bi-robot text-info opacity-50" title="RoboDoc only"></i>',
        };
      ?>
      <tr>
        <td>
          <div class="d-flex align-items-center gap-2">
            <?= $integIcon ?>
            <a href="<?= url('entries/'.$e['id']) ?>" class="text-white text-decoration-none fw-semibold">
              <?= e(mb_substr($e['title'],0,60)) ?>
            </a>
          </div>
        </td>
        <td>
          <span class="d-flex align-items-center gap-1">
            <span style="width:8px;height:8px;border-radius:50%;background:<?= e($e['project_color']??'#6c757d') ?>;flex-shrink:0"></span>
            <span class="text-muted small"><?= e($e['project_name']??'') ?></span>
          </span>
        </td>
        <td>
          <?php $sc = $statusColors[$e['status']??''] ?? 'secondary'; ?>
          <span class="badge bg-<?= $sc ?>" style="font-size:.65rem"><?= e($e['status']??'') ?></span>
        </td>
        <td>
          <?php if ($hasJira): ?>
          <a href="<?= e($e['jira_issue_url']??'#') ?>" target="_blank"
             class="badge <?= $e['jira_has_changes']?'bg-warning text-dark':'bg-dark border border-warning text-warning' ?> text-decoration-none">
            <i class="bi bi-bug-fill me-1"></i><?= e($e['jira_issue_key']) ?>
          </a>
          <?php else: ?>
          <span class="text-muted small">-</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($hasZentao): ?>
          <a href="<?= e($e['zentao_bug_url']??'#') ?>" target="_blank"
             class="badge <?= $e['zentao_has_changes']?'bg-warning text-dark':'bg-dark border border-info text-info' ?> text-decoration-none">
            <i class="bi bi-bug me-1"></i>#<?= e($e['zentao_bug_id']) ?>
            <?php if ($e['zentao_status']): ?>
            <span class="ms-1 opacity-75"><?= e($e['zentao_status']) ?></span>
            <?php endif; ?>
          </a>
          <?php else: ?>
          <span class="text-muted small">-</span>
          <?php endif; ?>
        </td>
        <td class="text-muted small"><?= formatDate($e['created_at'],'d.m.Y') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<div class="text-muted small mt-2"><?= count($entries) ?> entries shown</div>
<?php endif; ?>
