<?php
$csrf    = Auth::csrfToken();
$canEdit = Auth::canEdit('testing');
$jiraUrl = $jiraUrl ?? '#';
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <a href="#" class="btn btn-outline-secondary btn-sm rd-back me-2" data-fallback="<?= url('synapse') ?>">
      <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="d-inline mb-0"><i class="bi bi-cloud me-2 text-warning"></i>Alle SynapseRT Test Pläne</h4>
    <small class="text-muted ms-2">Projekt: <strong><?= e($project ?? 'BRSQ') ?></strong></small>
  </div>
  <?php if ($canEdit): ?>
  <button class="btn btn-outline-warning btn-sm" onclick="doRefreshCache()">
    <i class="bi bi-arrow-repeat me-1"></i>Cache aktualisieren
  </button>
  <?php endif; ?>
</div>

<?php if ($error): ?>
<div class="alert alert-warning small py-2"><?= e($error) ?></div>
<?php endif; ?>

<?php if (!$allPlans): ?>
<div class="text-center text-muted py-5">
  <i class="bi bi-cloud fs-1 d-block mb-2 opacity-25"></i>
  Noch keine Pläne im Cache. Bitte erst <strong>Cache aktualisieren</strong> klicken.
</div>
<?php else: ?>

<!-- Summary -->
<?php
$total    = count($allPlans);
$linked   = count(array_filter($allPlans, fn($p) => !empty($p['robodoc_id'])));
$unlinked = $total - $linked;
?>
<div class="d-flex gap-3 mb-4 flex-wrap">
  <?php foreach ([['Gesamt in SynapseRT', $total, 'secondary'], ['In RoboDoc verknüpft', $linked, 'success'], ['Nur in SynapseRT', $unlinked, 'warning']] as [$l,$v,$c]): ?>
  <div class="card px-4 py-2 text-center border-<?= $c ?> border-opacity-25">
    <div class="fw-bold fs-4 text-<?= $c ?>"><?= $v ?></div>
    <div class="text-muted small"><?= $l ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filter -->
<div class="d-flex gap-2 mb-3">
  <input type="text" id="filterInput" class="form-control form-control-sm bg-dark text-white border-secondary"
         placeholder="Filtern..." oninput="filterTable(this.value)" style="max-width:280px">
  <select id="filterStatus" class="form-select form-select-sm bg-dark text-white border-secondary"
          onchange="filterTable(document.getElementById('filterInput').value)" style="max-width:180px">
    <option value="">Alle</option>
    <option value="linked">In RoboDoc verknüpft</option>
    <option value="unlinked">Nur in SynapseRT</option>
  </select>
</div>

<div class="table-responsive">
  <table class="table table-dark table-hover align-middle" style="font-size:.83rem" id="planTable">
    <thead class="text-muted" style="font-size:.72rem">
      <tr>
        <th>SynapseRT Key</th>
        <th>Name</th>
        <th>Status</th>
        <th>RoboDoc Plan</th>
        <th>Test Cases</th>
        <th>Zuletzt gesehen</th>
        <th class="text-end">Aktionen</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($allPlans as $p):
        $isLinked = !empty($p['robodoc_id']);
      ?>
      <tr data-linked="<?= $isLinked ? 'linked' : 'unlinked' ?>"
          data-search="<?= strtolower(e($p['jira_key'] . ' ' . $p['summary'])) ?>">
        <td>
          <a href="<?= e($jiraUrl) ?>/browse/<?= e($p['jira_key']) ?>" target="_blank"
             class="badge bg-dark text-warning border border-warning text-decoration-none">
            <i class="bi bi-box-arrow-up-right me-1"></i><?= e($p['jira_key']) ?>
          </a>
        </td>
        <td class="fw-semibold lh-sm"><?= e($p['summary']) ?></td>
        <td>
          <?php $sc = match(strtolower($p['status'] ?? '')) {
            'open','to do' => 'secondary', 'in progress' => 'info',
            'done','closed','resolved' => 'success', default => 'secondary'
          }; ?>
          <span class="badge bg-<?= $sc ?>" style="font-size:.65rem"><?= e($p['status']) ?></span>
        </td>
        <td>
          <?php if ($isLinked): ?>
          <a href="<?= url('test-plans/' . $p['robodoc_id']) ?>" class="text-info text-decoration-none small">
            <i class="bi bi-journal-check me-1"></i><?= e($p['robodoc_name']) ?>
          </a>
          <?php else: ?>
          <span class="text-muted small">—</span>
          <?php endif; ?>
        </td>
        <td class="text-muted small">
          <?php if ($isLinked): ?>
          <?= (int)$p['robodoc_items'] ?> gesamt · <?= (int)$p['linked_items'] ?> verknüpft
          <?php else: ?>—<?php endif; ?>
        </td>
        <td class="text-muted small"><?= $p['synced_at'] ? formatDate($p['synced_at'],'d.m.Y H:i') : '—' ?></td>
        <td class="text-end">
          <div class="d-flex gap-1 justify-content-end">
            <?php if ($isLinked): ?>
            <button class="btn btn-outline-success btn-sm py-0 px-2"
                    onclick="syncPlan(<?= $p['robodoc_id'] ?>, '<?= e($csrf) ?>')"
                    title="Bidirektionaler Sync">
              <i class="bi bi-arrow-repeat"></i>
            </button>
            <a href="<?= url('test-plans/' . $p['robodoc_id']) ?>"
               class="btn btn-outline-secondary btn-sm py-0 px-2" title="In RoboDoc öffnen">
              <i class="bi bi-eye"></i>
            </a>
            <?php else: ?>
            <button class="btn btn-outline-warning btn-sm py-0 px-2"
                    onclick="openImportSingle('<?= e($p['jira_key']) ?>', '<?= e(addslashes($p['summary'])) ?>')"
                    title="In RoboDoc importieren">
              <i class="bi bi-cloud-download"></i>
            </button>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Import Single Modal (same as in index) -->
<?php if ($canEdit): ?>
<div class="modal fade" id="importSingleModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content bg-dark border-secondary">
    <div class="modal-header border-secondary">
      <h5 class="modal-title"><i class="bi bi-cloud-download me-2"></i>In RoboDoc importieren</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <p class="text-muted small mb-3">Importiert <strong id="importSingleName"></strong> inklusive aller Test Cases.</p>
      <input type="hidden" id="importSingleKey">
      <div class="mb-3">
        <label class="form-label">RoboDoc Projekt <span class="text-danger">*</span></label>
        <select id="importSingleProjectId" class="form-select">
          <option value="">— Projekt auswählen —</option>
          <?php
            [$pSql2, $pParams2] = Auth::projectAccessClause('proj2');
            $projs2 = Database::fetchAll("SELECT id, name FROM projects proj2 WHERE proj2.status='active' AND $pSql2 ORDER BY name", $pParams2);
            foreach ($projs2 as $proj2): ?>
          <option value="<?= $proj2['id'] ?>"><?= e($proj2['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div id="importSingleResult"></div>
    </div>
    <div class="modal-footer border-secondary">
      <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
      <button class="btn btn-warning btn-sm" onclick="doImportSingle()">
        <i class="bi bi-cloud-download me-1"></i>Importieren
      </button>
    </div>
  </div></div>
</div>
<?php endif; ?>

<div id="sToast" class="position-fixed bottom-0 end-0 m-3 p-3 rounded bg-dark border-secondary"
     style="display:none;z-index:9999;max-width:380px;font-size:.82rem"></div>

<script>
const _csrf = '<?= e($csrf) ?>';

function _toast(html, ok) {
  const t = document.getElementById('sToast');
  t.innerHTML = '<i class="bi bi-' + (ok?'check-circle text-success':'x-circle text-danger') + ' me-2"></i>' + html;
  t.style.display=''; clearTimeout(t._t); t._t = setTimeout(()=>t.style.display='none', 6000);
}

function filterTable(q) {
  const status = document.getElementById('filterStatus').value;
  q = q.toLowerCase();
  document.querySelectorAll('#planTable tbody tr').forEach(row => {
    const matchQ = !q || row.dataset.search.includes(q);
    const matchS = !status || row.dataset.linked === status;
    row.style.display = (matchQ && matchS) ? '' : 'none';
  });
}

function doRefreshCache() {
  _toast('Aktualisiere Cache...', true);
  fetch('<?= url('synapse/refresh-cache') ?>', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) { _toast(d.count + ' Pläne im Cache aktualisiert.', true); setTimeout(()=>location.reload(), 1500); }
    else _toast(d.error || 'Fehler', false);
  });
}

function syncPlan(planId, csrf) {
  _toast('Synchronisiere...', true);
  fetch('<?= url('synapse/sync-plan') ?>', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf, plan_id: planId, direction: 'both'})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) { _toast('Sync fertig — ' + (d.log||[]).length + ' Aktion(en).', true); setTimeout(()=>location.reload(),2000); }
    else _toast(d.error||'Fehler', false);
  });
}

function openImportSingle(key, name) {
  document.getElementById('importSingleKey').value = key;
  document.getElementById('importSingleName').textContent = name;
  document.getElementById('importSingleResult').innerHTML = '';
  new bootstrap.Modal(document.getElementById('importSingleModal')).show();
}

function doImportSingle() {
  const key = document.getElementById('importSingleKey').value;
  const pid = document.getElementById('importSingleProjectId').value;
  const res = document.getElementById('importSingleResult');
  if (!pid) { res.innerHTML = '<div class="alert alert-warning py-1 small mt-2">Bitte Projekt auswählen</div>'; return; }
  res.innerHTML = '<div class="text-muted small mt-2"><i class="bi bi-hourglass-split me-1"></i>Importiere...</div>';
  fetch('<?= url('synapse/import-single') ?>', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf, jira_key: key, project_id: pid})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      res.innerHTML = '<div class="alert alert-success py-2 small mt-2">' +
        (d.already ? 'Bereits importiert: ' : 'Importiert: ') +
        '"' + d.plan_name + '"' + (d.imported_items ? ' mit ' + d.imported_items + ' Test Cases' : '') + '.<br>' +
        '<a href="<?= url('test-plans/') ?>' + d.plan_id + '" class="btn btn-success btn-sm mt-1">Test Plan öffnen</a></div>';
      setTimeout(()=>location.reload(), 3000);
    } else res.innerHTML = '<div class="alert alert-danger py-1 small mt-2">' + (d.error||'Fehler') + '</div>';
  });
}
</script>
