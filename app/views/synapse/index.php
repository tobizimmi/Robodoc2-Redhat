<?php
$csrf    = Auth::csrfToken();
$canEdit = Auth::canEdit('synapse');
$jiraUrl = $jiraUrl ?? '#';
$project = $project ?? 'BRSQ';
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><i class="bi bi-arrow-repeat me-2 text-warning"></i>SynapseRT Sync</h4>
    <small class="text-muted">
      Projekt: <strong><?= e($project) ?></strong> &middot; RoboDoc ist die primaere Datenquelle
      &middot; <span id="syncStatus" class="text-muted">Bereit</span>
    </small>
  </div>
  <?php if ($canEdit): ?>
  <div class="d-flex gap-2 flex-wrap">
    <button class="btn btn-outline-warning btn-sm" id="btnSyncAll" onclick="syncAll()">
      <i class="bi bi-arrow-repeat me-1"></i>Alle Plaene synchronisieren
    </button>
    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#importAllModal">
      <i class="bi bi-cloud-download me-1"></i>Aus SynapseRT importieren
    </button>
  </div>
  <?php endif; ?>
</div>

<?php if ($error): ?>
<div class="alert alert-warning d-flex gap-2 align-items-start mb-3">
  <i class="bi bi-exclamation-triangle mt-1 flex-shrink-0"></i>
  <div><strong>Verbindungsproblem:</strong> <?= e($error) ?><br>
  <small>Jira URL in <a href="<?= url('admin/settings') ?>">Einstellungen</a> und API-Token im <a href="<?= url('profile') ?>">Profil</a> pruefen.</small></div>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="row g-3 mb-4">
  <?php foreach ([
    ['Test Plaene gesamt', $stats['plans_total'], 'bi-journal-check', 'primary'],
    ['Mit SynapseRT verknuepft', $stats['plans_synced'], 'bi-link-45deg', 'success'],
    ['Test Runs verknuepft', $stats['runs_synced'], 'bi-play-circle', 'info'],
    ['Nur in SynapseRT', $stats['only_in_synapse'], 'bi-cloud', 'warning'],
  ] as [$label, $val, $icon, $color]): ?>
  <div class="col-6 col-lg-3">
    <div class="card border-<?= $color ?> border-opacity-25">
      <div class="card-body py-3 d-flex align-items-center gap-3">
        <i class="bi <?= $icon ?> text-<?= $color ?> fs-3 opacity-75"></i>
        <div><div class="fw-bold fs-4"><?= $val ?></div><div class="text-muted small"><?= $label ?></div></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<ul class="nav nav-tabs mb-3" id="synapseTabs">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabPlans">
    <i class="bi bi-journal-check me-1"></i>Test Plaene</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabRuns">
    <i class="bi bi-play-circle me-1"></i>Test Runs</a></li>
  <li class="nav-item"><a class="nav-link" href="<?= url('synapse/list-all') ?>">
    <i class="bi bi-list-ul me-1"></i>Alle SynapseRT Plaene</a></li>
  <?php if ($onlyInSynapse): ?>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabOnlySynapse">
    <i class="bi bi-cloud me-1 text-warning"></i>Nur in SynapseRT
    <span class="badge bg-warning text-dark ms-1"><?= count($onlyInSynapse) ?></span></a></li>
  <?php endif; ?>
</ul>

<div class="tab-content">

  <!-- Test Plans tab -->
  <div class="tab-pane fade show active" id="tabPlans">
    <div class="table-responsive">
      <table class="table table-dark table-hover align-middle" style="font-size:.83rem">
        <thead class="text-muted" style="font-size:.72rem">
          <tr>
            <th>Name</th><th>Projekt</th><th>Test Cases</th>
            <th>SynapseRT</th><th>Letzter Sync</th><th class="text-end">Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($testPlans as $p): ?>
          <tr data-plan-id="<?= $p['xray_key'] ? $p['id'] : '' ?>">
            <td>
              <a href="<?= url('test-plans/' . $p['id']) ?>" class="text-white fw-semibold text-decoration-none">
                <?= e($p['name']) ?>
              </a>
              <?php if ($p['project_name']): ?>
              <div class="text-muted" style="font-size:.7rem"><?= e($p['project_name']) ?></div>
              <?php endif; ?>
            </td>
            <td class="text-muted small"><?= e($p['project_name'] ?? '?') ?></td>
            <td>
              <span class="badge bg-secondary"><?= (int)$p['item_count'] ?> gesamt</span>
              <?php if ($p['item_count'] > 0): ?>
              <span class="badge bg-success ms-1"><?= (int)$p['synced_count'] ?> verknuepft</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($p['xray_key']): ?>
              <a href="<?= e($jiraUrl) ?>/browse/<?= e($p['xray_key']) ?>" target="_blank"
                 class="badge bg-dark text-warning border border-warning text-decoration-none">
                <i class="bi bi-box-arrow-up-right me-1"></i><?= e($p['xray_key']) ?>
              </a>
              <?php else: ?>
              <span class="text-muted small">Nicht verknuepft</span>
              <?php endif; ?>
            </td>
            <td class="text-muted small"><?= $p['xray_synced_at'] ? formatDate($p['xray_synced_at'],'d.m. H:i') : '?' ?></td>
            <td class="text-end">
              <div class="d-flex gap-1 justify-content-end">
                <?php if ($canEdit && !$p['xray_key']): ?>
                <button class="btn btn-outline-warning btn-sm py-0 px-2"
                        onclick="openLinkPlan(<?= $p['id'] ?>, '<?= e(addslashes($p['name'])) ?>')"
                        title="Mit SynapseRT verknuepfen">
                  <i class="bi bi-link-45deg"></i>
                </button>
                <?php endif; ?>
                <?php if ($canEdit && !$p['xray_key']): ?>
                <button class="btn btn-outline-secondary btn-sm py-0 px-2"
                        onclick="createSynapsePlan(<?= $p['id'] ?>, '<?= e(addslashes($p['name'])) ?>')"
                        title="In SynapseRT erstellen">
                  <i class="bi bi-cloud-upload"></i>
                </button>
                <?php endif; ?>
                <?php if ($p['xray_key']): ?>
                <button class="btn btn-outline-success btn-sm py-0 px-2"
                        onclick="syncPlan(<?= $p['id'] ?>, 'both', '<?= e($csrf) ?>')"
                        title="Bidirektionaler Sync">
                  <i class="bi bi-arrow-repeat"></i>
                </button>
                <button class="btn btn-outline-info btn-sm py-0 px-2"
                        onclick="syncPlan(<?= $p['id'] ?>, 'pull', '<?= e($csrf) ?>')"
                        title="Von SynapseRT ziehen">
                  <i class="bi bi-cloud-download"></i>
                </button>
                <button class="btn btn-outline-warning btn-sm py-0 px-2"
                        onclick="syncPlan(<?= $p['id'] ?>, 'push', '<?= e($csrf) ?>')"
                        title="Nach SynapseRT pushen">
                  <i class="bi bi-cloud-upload"></i>
                </button>
                <?php endif; ?>
                <a href="<?= url('test-plans/' . $p['id']) ?>" class="btn btn-outline-secondary btn-sm py-0 px-2" title="?ffnen">
                  <i class="bi bi-eye"></i>
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$testPlans): ?>
          <tr><td colspan="6" class="text-muted text-center py-4">Noch keine Test Plaene. <a href="<?= url('test-plans/create') ?>">Erstellen</a></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Test Cycles tab -->
  <div class="tab-pane fade" id="tabCycles">
    <?php $testCycles = $testCycles ?? []; ?>
    <?php if (!$testCycles): ?>
    <div class="text-center text-muted py-5">
      <i class="bi bi-arrow-repeat fs-1 d-block mb-2 opacity-25"></i>
      Noch keine Test Cycles importiert. Bitte "Alle Plaene synchronisieren" klicken.
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-dark table-hover align-middle" style="font-size:.83rem">
        <thead class="text-muted" style="font-size:.72rem">
          <tr><th>Name</th><th>Test Plan</th><th>Status</th><th>SynapseRT</th><th>Sync</th><th class="text-end">Aktionen</th></tr>
        </thead>
        <tbody>
          <?php foreach ($testCycles as $cyc):
            $cb = match($cyc['status']??'planned') { 'active'=>'info','completed'=>'success','aborted'=>'danger',default=>'secondary' };
            $cyRun = Database::fetchOne('SELECT id, status FROM test_runs WHERE test_cycle_id=? LIMIT 1', [$cyc['id']]);
          ?>
          <tr>
            <td class="fw-semibold"><?= e($cyc['name']) ?></td>
            <td class="text-muted small">
              <?php if ($cyc['plan_name']): ?>
              <a href="<?= url('test-plans/'.$cyc['test_plan_id']) ?>" class="text-muted"><?= e($cyc['plan_name']) ?></a>
              <?php else: ?>-<?php endif; ?>
            </td>
            <td><span class="badge bg-<?= $cb ?>" style="font-size:.65rem"><?= e($cyc['status']??'') ?></span></td>
            <td>
              <?php if ($cyc['synapse_cycle_id']): ?>
              <span class="badge bg-dark border border-warning text-warning" style="font-size:.65rem">
                <?= e($cyc['synapse_plan_key']??'') ?> / <?= e($cyc['synapse_cycle_id']) ?>
              </span>
              <?php else: ?><span class="text-muted">-</span><?php endif; ?>
            </td>
            <td class="text-muted small"><?= $cyc['synapse_synced_at'] ? formatDate($cyc['synapse_synced_at'],'d.m. H:i') : '-' ?></td>
            <td class="text-end">
              <div class="d-flex gap-1 justify-content-end">
                <a href="<?= url('test-plans/'.$cyc['test_plan_id']) ?>#cycle-<?= $cyc['id'] ?>"
                   class="btn btn-outline-secondary btn-sm py-0 px-2" title="Test Plan oeffnen">
                  <i class="bi bi-journal-check"></i>
                </a>
                <?php if ($cyRun): ?>
                <a href="<?= url('test-runs/'.$cyRun['id']) ?>"
                   class="btn btn-outline-info btn-sm py-0 px-2" title="Test Run oeffnen">
                  <i class="bi bi-play-fill"></i>
                </a>
                <?php else: ?>
                <a href="<?= url('test-runs/create?plan_id='.$cyc['test_plan_id'].'&cycle_id='.$cyc['id']) ?>"
                   class="btn btn-outline-success btn-sm py-0 px-2" title="Test Run starten">
                  <i class="bi bi-play"></i>
                </a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Test Runs tab -->
  <div class="tab-pane fade" id="tabRuns">
    <div class="table-responsive">
      <table class="table table-dark table-hover align-middle" style="font-size:.83rem">
        <thead class="text-muted" style="font-size:.72rem">
          <tr><th>Name</th><th>Test Plan</th><th>Status</th><th>SynapseRT Cycle</th><th>Sync</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($testRuns as $r): ?>
          <tr>
            <td><a href="<?= url('test-runs/' . $r['id']) ?>" class="text-white fw-semibold text-decoration-none"><?= e($r['name']) ?></a></td>
            <td class="text-muted small"><?= e($r['plan_name'] ?? '?') ?></td>
            <td><span class="badge bg-secondary"><?= e($r['status'] ?? '') ?></span></td>
            <td>
              <?php if ($r['synapse_cycle_id'] && $r['synapse_plan_key']): ?>
              <span class="badge bg-info text-dark"><?= e($r['synapse_plan_key']) ?>/<?= e($r['synapse_cycle_id']) ?></span>
              <?php else: ?>
              <span class="text-muted small">Nicht verknuepft</span>
              <?php endif; ?>
            </td>
            <td class="text-muted small"><?= $r['synapse_synced_at'] ? formatDate($r['synapse_synced_at'],'d.m. H:i') : '?' ?></td>
            <td>
              <?php if ($canEdit): ?>
              <div class="d-flex gap-1">
                <button class="btn btn-outline-success btn-sm py-0 px-2"
                        onclick="syncRun(<?= $r['id'] ?>, 'both', '<?= e($csrf) ?>')" title="Sync">
                  <i class="bi bi-arrow-repeat"></i>
                </button>
                <a href="<?= url('test-runs/' . $r['id']) ?>" class="btn btn-outline-secondary btn-sm py-0 px-2">
                  <i class="bi bi-eye"></i>
                </a>
              </div>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$testRuns): ?>
          <tr><td colspan="6" class="text-muted text-center py-4">Noch keine Test Runs.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Only in SynapseRT tab -->
  <?php if ($onlyInSynapse): ?>
  <div class="tab-pane fade" id="tabOnlySynapse">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <p class="text-muted small mb-0">Diese Test Plaene existieren in SynapseRT aber noch nicht in RoboDoc. Klick auf <i class="bi bi-cloud-download"></i> um sie zu importieren.</p>
    </div>
    <div class="table-responsive">
      <table class="table table-dark table-hover align-middle" style="font-size:.83rem">
        <thead class="text-muted" style="font-size:.72rem">
          <tr><th>SynapseRT Key</th><th>Name</th><th>Status</th><th>Zuletzt gesehen</th><th class="text-end">Import</th></tr>
        </thead>
        <tbody>
          <?php foreach ($onlyInSynapse as $sp): ?>
          <tr>
            <td>
              <a href="<?= e($jiraUrl) ?>/browse/<?= e($sp['jira_key']) ?>" target="_blank"
                 class="badge bg-dark text-warning border border-warning text-decoration-none">
                <i class="bi bi-box-arrow-up-right me-1"></i><?= e($sp['jira_key']) ?>
              </a>
            </td>
            <td class="fw-semibold"><?= e($sp['summary']) ?></td>
            <td><span class="badge bg-secondary" style="font-size:.65rem"><?= e($sp['status']) ?></span></td>
            <td class="text-muted small"><?= $sp['synced_at'] ? formatDate($sp['synced_at'],'d.m. H:i') : '?' ?></td>
            <td class="text-end">
              <button class="btn btn-outline-warning btn-sm py-0 px-2"
                      onclick="openImportSingle('<?= e($sp['jira_key']) ?>', '<?= e(addslashes($sp['summary'])) ?>')"
                      title="In RoboDoc importieren">
                <i class="bi bi-cloud-download"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="text-muted small mt-2">
      <i class="bi bi-info-circle me-1"></i>
      Liste basiert auf dem lokalen Cache. <button class="btn btn-link btn-sm p-0" onclick="refreshCache()">Cache aktualisieren</button>
    </p>
  </div>
  <?php endif; ?>

  <!-- Cache tab -->
  <?php if ($cachedPlans): ?>
  <div class="tab-pane fade" id="tabCache">
    <p class="text-muted small mb-3">Zuletzt aus SynapseRT gecachte Daten. Kein Live-API-Aufruf beim Laden.</p>
    <div class="table-responsive">
      <table class="table table-dark table-sm align-middle" style="font-size:.8rem">
        <thead class="text-muted"><tr><th>Key</th><th>Name</th><th>Status</th><th>RoboDoc</th><th>Sync</th></tr></thead>
        <tbody>
          <?php
            // Preload plan names for all cached plans in one query
            $linkedPlanIds = array_filter(array_column($cachedPlans, 'robodoc_plan_id'));
            $linkedPlanNames = [];
            if ($linkedPlanIds) {
                foreach (Database::fetchAll('SELECT id, name FROM test_plans WHERE id IN (' . implode(',', array_fill(0, count($linkedPlanIds), '?')) . ')', array_values($linkedPlanIds)) as $lp) {
                    $linkedPlanNames[$lp['id']] = $lp['name'];
                }
            }
          ?>
          <?php foreach ($cachedPlans as $cp):
            $linked = $cp['robodoc_plan_id'] ? ($linkedPlanNames[$cp['robodoc_plan_id']] ? ['name' => $linkedPlanNames[$cp['robodoc_plan_id']]] : null) : null;
          ?>
          <tr>
            <td><a href="<?= e($jiraUrl) ?>/browse/<?= e($cp['jira_key']) ?>" target="_blank"
                   class="badge bg-dark text-warning border border-warning text-decoration-none"><?= e($cp['jira_key']) ?></a></td>
            <td><?= e($cp['summary']) ?></td>
            <td><span class="badge bg-secondary" style="font-size:.65rem"><?= e($cp['status']) ?></span></td>
            <td><?= $linked ? '<span class="badge bg-info text-dark">' . e($linked['name']) . '</span>' : '<span class="text-muted">?</span>' ?></td>
            <td class="text-muted" style="font-size:.7rem"><?= $cp['synced_at'] ? formatDate($cp['synced_at'],'d.m. H:i') : '?' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Import All Modal -->
<?php if ($canEdit): ?>
<div class="modal fade" id="importAllModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content bg-dark border-secondary">
    <div class="modal-header border-secondary">
      <h5 class="modal-title"><i class="bi bi-cloud-download me-2"></i>Alles aus SynapseRT importieren</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <p class="text-muted small mb-3">
        Importiert alle Test Plaene und Test Cases aus dem SynapseRT Projekt <strong><?= e($project) ?></strong> nach RoboDoc.
        Bereits vorhandene Plaene werden aktualisiert, neue werden angelegt.
      </p>
      <div class="mb-3">
        <label class="form-label">RoboDoc Projekt fuer neue Plaene <span class="text-danger">*</span></label>
        <select id="importProjectId" class="form-select">
          <option value="">? Projekt auswaehlen ?</option>
          <?php
            [$pSql, $pParams] = Auth::projectAccessClause('proj');
            $projs = Database::fetchAll("SELECT id, name FROM projects proj WHERE proj.status='active' AND $pSql ORDER BY name", $pParams);
            foreach ($projs as $proj): ?>
          <option value="<?= $proj['id'] ?>"><?= e($proj['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">Nur fuer neue Plaene die noch nicht in RoboDoc existieren.</div>
      </div>
      <div id="importResult"></div>
    </div>
    <div class="modal-footer border-secondary">
      <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
      <button class="btn btn-warning btn-sm" onclick="doImportAll()">
        <i class="bi bi-cloud-download me-1"></i>Import starten
      </button>
    </div>
  </div></div>
</div>

<!-- Link Plan Modal -->
<div class="modal fade" id="linkPlanModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content bg-dark border-secondary">
    <div class="modal-header border-secondary">
      <h5 class="modal-title"><i class="bi bi-link-45deg me-2"></i>Mit SynapseRT verknuepfen</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <p class="text-muted small mb-3">Verknuepft <strong id="linkPlanName"></strong> mit einem bestehenden SynapseRT Test Plan.</p>
      <input type="hidden" id="linkPlanId">
      <div class="mb-3">
        <label class="form-label small">SynapseRT Key suchen</label>
        <div class="input-group">
          <input type="text" id="linkPlanSearch" class="form-control" placeholder="z.B. BRSQ-10 oder Suchbegriff" oninput="searchCachedPlans(this.value)">
        </div>
      </div>
      <div id="linkPlanResults" style="max-height:220px;overflow-y:auto"></div>
      <div class="mt-3">
        <label class="form-label small">Oder Key direkt eingeben</label>
        <input type="text" id="linkPlanKeyDirect" class="form-control" placeholder="z.B. BRSQ-42">
      </div>
      <div id="linkPlanResult" class="mt-2"></div>
    </div>
    <div class="modal-footer border-secondary">
      <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
      <button class="btn btn-warning btn-sm" onclick="doLinkPlan()">
        <i class="bi bi-link-45deg me-1"></i>Verknuepfen
      </button>
    </div>
  </div></div>
</div>
<?php endif; ?>

<!-- Import Single Plan Modal -->
<?php if ($canEdit): ?>
<div class="modal fade" id="importSingleModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content bg-dark border-secondary">
    <div class="modal-header border-secondary">
      <h5 class="modal-title"><i class="bi bi-cloud-download me-2"></i>Plan aus SynapseRT importieren</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <p class="text-muted small mb-3">Importiert <strong id="importSingleName"></strong> inklusive aller Test Cases nach RoboDoc.</p>
      <input type="hidden" id="importSingleKey">
      <div class="mb-3">
        <label class="form-label">RoboDoc Projekt <span class="text-danger">*</span></label>
        <select id="importSingleProjectId" class="form-select">
          <option value="">? Projekt auswaehlen ?</option>
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
     style="display:none;z-index:9999;max-width:400px;font-size:.82rem"></div>

<script>
const _csrf = '<?= e($csrf) ?>';

function _toast(html, ok) {
  const t = document.getElementById('sToast');
  t.innerHTML = '<i class="bi bi-' + (ok ? 'check-circle text-success' : 'x-circle text-danger') + ' me-2"></i>' + html;
  t.style.display = ''; clearTimeout(t._t);
  t._t = setTimeout(() => t.style.display = 'none', 7000);
}

function syncPlan(planId, direction, csrf) {
  _toast('Synchronisiere Plan...', true);
  fetch('<?= url('synapse/sync-plan') ?>', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf, plan_id: planId, direction})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      const n = (d.log || []).length;
      _toast('Sync fertig ? ' + n + ' Aktion(en).', true);
      if (n > 0) setTimeout(() => location.reload(), 2000);
    } else _toast(d.error || 'Fehler', false);
  })
  .catch(() => _toast('Netzwerkfehler', false));
}

function syncRun(runId, direction, csrf) {
  _toast('Synchronisiere Run...', true);
  fetch('<?= url('synapse/sync-run') ?>', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf, run_id: runId, direction})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      _toast('Sync fertig ? ' + (d.log || []).length + ' Aktion(en).', true);
      setTimeout(() => location.reload(), 2000);
    } else _toast(d.error || 'Fehler', false);
  })
  .catch(() => _toast('Netzwerkfehler', false));
}

function doImportAll() {
  const pid = document.getElementById('importProjectId').value;
  const res = document.getElementById('importResult');
  if (!pid) { res.innerHTML = '<div class="alert alert-warning py-1 small mt-2">Bitte Projekt auswaehlen</div>'; return; }
  res.innerHTML = '<div class="text-muted small mt-2"><i class="bi bi-hourglass-split me-1"></i>Importiere ? das kann 30-60 Sekunden dauern...</div>';
  fetch('<?= url('synapse/import-all') ?>', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf, project_id: pid})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      res.innerHTML = '<div class="alert alert-success py-2 small mt-2"><i class="bi bi-check-circle me-1"></i>' +
        d.imported + ' neu importiert, ' + d.updated + ' aktualisiert.<br>' +
        (d.log || []).slice(0, 5).join('<br>') + (d.log.length > 5 ? '<br>...' : '') + '</div>';
      setTimeout(() => location.reload(), 3000);
    } else res.innerHTML = '<div class="alert alert-danger py-1 small mt-2">' + (d.error || 'Fehler') + '</div>';
  })
  .catch(() => res.innerHTML = '<div class="alert alert-danger py-1 small mt-2">Netzwerkfehler</div>');
}

function openLinkPlan(planId, planName) {
  document.getElementById('linkPlanId').value = planId;
  document.getElementById('linkPlanName').textContent = planName;
  document.getElementById('linkPlanResult').innerHTML = '';
  document.getElementById('linkPlanResults').innerHTML = '';
  document.getElementById('linkPlanSearch').value = '';
  document.getElementById('linkPlanKeyDirect').value = '';
  new bootstrap.Modal(document.getElementById('linkPlanModal')).show();
}

function searchCachedPlans(q) {
  if (!q.trim()) return;
  fetch('<?= url('synapse/search-plans') ?>?q=' + encodeURIComponent(q), { headers: {'X-Requested-With': 'XMLHttpRequest'} })
  .then(r => r.json())
  .then(items => {
    const res = document.getElementById('linkPlanResults');
    if (!items.length) { res.innerHTML = '<div class="text-muted small">Keine Ergebnisse im Cache. Direkt eingeben.</div>'; return; }
    res.innerHTML = '<div class="list-group">' + items.map(i =>
      '<button class="list-group-item list-group-item-action bg-dark border-secondary d-flex align-items-center gap-2 py-2" style="font-size:.8rem" onclick="document.getElementById(\'linkPlanKeyDirect\').value=\'' + i.jira_key + '\'">' +
      '<span class="badge bg-dark text-warning border border-warning">' + i.jira_key + '</span>' + i.summary.substring(0, 50) + '</button>'
    ).join('') + '</div>';
  });
}

function doLinkPlan() {
  const planId = document.getElementById('linkPlanId').value;
  const key    = document.getElementById('linkPlanKeyDirect').value.trim().toUpperCase();
  const res    = document.getElementById('linkPlanResult');
  if (!key) { res.innerHTML = '<div class="text-warning small mt-2">Bitte Key eingeben</div>'; return; }
  res.innerHTML = '<div class="text-muted small mt-2">Verknuepfe...</div>';
  fetch('<?= url('synapse/link-plan') ?>', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf, plan_id: planId, synapse_key: key})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) { res.innerHTML = '<div class="text-success small mt-2">Verknuepft!</div>'; setTimeout(() => location.reload(), 1200); }
    else res.innerHTML = '<div class="text-danger small mt-2">' + (d.error || 'Fehler') + '</div>';
  });
}

function syncAll() {
  const btn = document.getElementById('btnSyncAll');
  const status = document.getElementById('syncStatus');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Synchronisiere...'; }
  status.textContent = 'Synchronisiere alle Plaene...';
  status.className = 'text-warning';

  // Get all plans with synapse key
  const planRows = document.querySelectorAll('[data-plan-id]');
  const planIds = [...planRows].map(r => r.dataset.planId).filter(Boolean);

  if (!planIds.length) {
    // Refresh cache first, then reload
    fetch('<?= url('synapse/refresh-cache') ?>', {
      method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
      body: new URLSearchParams({_csrf})
    })
    .then(r => r.json())
    .then(d => {
      status.textContent = d.success ? (d.count + ' Plaene im Cache.') : (d.error || 'Fehler');
      status.className = d.success ? 'text-success' : 'text-danger';
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Alle Plaene synchronisieren'; }
      if (d.success) setTimeout(() => location.reload(), 1500);
    });
    return;
  }

  // Sync each plan sequentially
  let done = 0;
  let errors = 0;
  function syncNext(idx) {
    if (idx >= planIds.length) {
      const msg = 'Sync fertig: ' + done + ' Aktionen, ' + errors + ' Fehler.';
      status.textContent = msg;
      status.className = errors ? 'text-warning' : 'text-success';
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Alle Plaene synchronisieren'; }
      setTimeout(() => location.reload(), 2000);
      return;
    }
    status.textContent = 'Plan ' + (idx+1) + '/' + planIds.length + '...';
    fetch('<?= url('synapse/sync-plan') ?>', {
      method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
      body: new URLSearchParams({_csrf, plan_id: planIds[idx], direction: 'both'})
    })
    .then(r => r.json())
    .then(d => {
      if (d.success) done += (d.log || []).length;
      else errors++;
      syncNext(idx + 1);
    })
    .catch(() => { errors++; syncNext(idx + 1); });
  }
  syncNext(0);
}

function createSynapsePlan(planId, planName) {
  if (!confirm('Test Plan "' + planName + '" in SynapseRT erstellen?')) return;
  _toast('Erstelle in SynapseRT...', true);
  fetch('<?= url('synapse/create-test-plan') ?>', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf, plan_id: planId})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      _toast('Erstellt: ' + d.key + (d.already ? ' (bereits vorhanden)' : ''), true);
      setTimeout(() => location.reload(), 1500);
    } else _toast(d.error || 'Fehler', false);
  })
  .catch(() => _toast('Netzwerkfehler', false));
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
  if (!pid) { res.innerHTML = '<div class="alert alert-warning py-1 small mt-2">Bitte Projekt auswaehlen</div>'; return; }
  res.innerHTML = '<div class="text-muted small mt-2"><i class="bi bi-hourglass-split me-1"></i>Importiere...</div>';
  fetch('<?= url('synapse/import-single') ?>', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf, jira_key: key, project_id: pid})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      if (d.already) {
        res.innerHTML = '<div class="alert alert-info py-2 small mt-2">Bereits importiert als "' + d.plan_name + '".<br><a href="<?= url('test-plans/') ?>' + d.plan_id + '" class="btn btn-outline-info btn-sm mt-1">?ffnen</a></div>';
      } else {
        res.innerHTML = '<div class="alert alert-success py-2 small mt-2"><i class="bi bi-check-circle me-1"></i>"' + d.plan_name + '" importiert mit ' + d.imported_items + ' Test Cases.<br><a href="<?= url('test-plans/') ?>' + d.plan_id + '" class="btn btn-success btn-sm mt-1">Test Plan oeffnen</a></div>';
      }
      setTimeout(() => location.reload(), 3000);
    } else {
      res.innerHTML = '<div class="alert alert-danger py-1 small mt-2">' + (d.error || 'Fehler') + '</div>';
    }
  })
  .catch(() => res.innerHTML = '<div class="alert alert-danger py-1 small mt-2">Netzwerkfehler</div>');
}

function refreshCache() {
  _toast('Aktualisiere Cache...', true);
  fetch('<?= url('synapse/import-all') ?>', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf, project_id: '0'}) // 0 = don't create new plans, just update cache
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) { _toast('Cache aktualisiert.', true); setTimeout(() => location.reload(), 1500); }
    else _toast(d.error || 'Fehler', false);
  });
}
</script>
