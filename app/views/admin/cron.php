<?php
/** @var array $jobs */
$csrfToken = Auth::csrfToken();
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h5 class="mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Cron Jobs</h5>
    <p class="text-muted small mb-0">Verwalte automatische Hintergrundaufgaben</p>
  </div>
</div>

<!-- Server setup info -->
<div class="alert alert-info d-flex gap-3 align-items-start mb-4">
  <i class="bi bi-info-circle-fill fs-5 flex-shrink-0 mt-1"></i>
  <div>
    <strong>Einmalige Server-Einrichtung erforderlich</strong><br>
    Trage diesen <strong>einen</strong> Cron Job auf dem Server ein — er erledigt alles automatisch:
    <code class="d-block mt-2 p-2 bg-dark text-light rounded" style="font-size:12px">
      * * * * * php <?= rtrim($_SERVER['DOCUMENT_ROOT'] ?? '/var/www/...', '/') ?>/app/cron/runner.php
    </code>
    <small class="text-muted">Danach kannst du alle Jobs hier in der UI aktivieren/deaktivieren.</small>
  </div>
</div>

<!-- Jobs table -->
<div class="card border-secondary">
  <div class="card-body p-0">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-dark">
        <tr>
          <th style="width:200px">Job</th>
          <th>Beschreibung</th>
          <th style="width:120px">Intervall</th>
          <th style="width:150px">Letzter Lauf</th>
          <th style="width:100px">Status</th>
          <th style="width:130px">Aktiv</th>
          <th style="width:80px"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($jobs as $job): ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= e($job['label']) ?></div>
            <div class="text-muted" style="font-size:11px"><?= e($job['script']) ?></div>
          </td>
          <td class="text-muted small"><?= e($job['description']) ?></td>
          <td>
            <form method="POST" action="<?= url('admin/cron/'.$job['id'].'/interval') ?>" class="d-flex gap-1 align-items-center">
              <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
              <input type="number" name="interval_min" value="<?= (int)$job['interval_min'] ?>"
                     min="1" max="1440" class="form-control form-control-sm" style="width:65px">
              <span class="text-muted small">min</span>
              <button class="btn btn-outline-secondary btn-sm py-0 px-1" title="Speichern">
                <i class="bi bi-check2"></i>
              </button>
            </form>
          </td>
          <td class="small">
            <?php if ($job['last_run_at']): ?>
              <span title="<?= e($job['last_run_at']) ?>">
                <?= date('d.m.Y H:i', strtotime($job['last_run_at'])) ?>
              </span>
            <?php else: ?>
              <span class="text-muted">Noch nie</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($job['last_run_at'] === null): ?>
              <span class="badge bg-secondary">–</span>
            <?php elseif ($job['last_run_ok']): ?>
              <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>OK</span>
            <?php else: ?>
              <span class="badge bg-danger" style="cursor:pointer"
                    data-bs-toggle="tooltip"
                    title="<?= e(mb_substr($job['last_run_msg'] ?? '', 0, 200)) ?>">
                <i class="bi bi-x-lg me-1"></i>Fehler
              </span>
            <?php endif; ?>
          </td>
          <td>
            <form method="POST" action="<?= url('admin/cron/'.$job['id'].'/toggle') ?>">
              <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch"
                       onchange="this.form.submit()"
                       <?= $job['is_active'] ? 'checked' : '' ?>>
              </div>
            </form>
          </td>
          <td>
            <?php if ($job['last_run_msg']): ?>
            <button class="btn btn-outline-secondary btn-sm py-0"
                    data-bs-toggle="modal" data-bs-target="#logModal<?= $job['id'] ?>">
              <i class="bi bi-terminal me-1"></i>Log
            </button>
            <!-- Log modal -->
            <div class="modal fade" id="logModal<?= $job['id'] ?>" tabindex="-1">
              <div class="modal-dialog modal-lg">
                <div class="modal-content bg-dark">
                  <div class="modal-header border-secondary">
                    <h6 class="modal-title">Log: <?= e($job['label']) ?></h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <pre class="text-light mb-0" style="font-size:12px;max-height:400px;overflow-y:auto"><?= e($job['last_run_msg'] ?? '') ?></pre>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($jobs)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Keine Jobs gefunden</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
  new bootstrap.Tooltip(el);
});
</script>
