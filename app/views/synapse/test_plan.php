<?php
$csrf    = Auth::csrfToken();
$canEdit = Auth::canEdit('testing');
$sc = fn($s) => match(strtolower($s ?? '')) {
  'active'    => 'info',
  'completed' => 'success',
  'aborted'   => 'danger',
  'draft'     => 'secondary',
  default     => 'secondary',
};
?>
<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('synapse') ?>">
    <i class="bi bi-arrow-left"></i>
  </a>
  <div class="flex-grow-1">
    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
      <a href="<?= e($plan['url']) ?>" target="_blank"
         class="badge bg-dark text-warning border border-warning text-decoration-none">
        <i class="bi bi-box-arrow-up-right me-1"></i><?= e($plan['key']) ?>
      </a>
      <?php
        $psc = match(strtolower($plan['status'] ?? '')) {
          'open','to do'         => 'secondary',
          'in progress','active' => 'info',
          'done','closed'        => 'success',
          default                => 'secondary',
        };
      ?>
      <span class="badge bg-<?= $psc ?>"><?= e($plan['status']) ?></span>
      <span class="text-muted small"><i class="bi bi-person me-1"></i><?= e($plan['assignee']) ?></span>
      <?php if ($localPlan): ?>
      <span class="badge bg-info text-dark"><i class="bi bi-link-45deg me-1"></i><?= e($localPlan['name']) ?></span>
      <?php endif; ?>
    </div>
    <h5 class="mb-0 lh-sm"><?= e($plan['summary']) ?></h5>
  </div>
  <?php if ($canEdit): ?>
  <div class="d-flex gap-2 ms-auto">
    <?php if ($localPlan): ?>
    <button class="btn btn-outline-warning btn-sm" onclick="syncPlan('<?= e($plan['key']) ?>','<?= e($csrf) ?>')" title="Bidirektionaler Sync">
      <i class="bi bi-arrow-repeat me-1"></i>Sync
    </button>
    <?php endif; ?>
    <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#createCycleModal">
      <i class="bi bi-plus-lg me-1"></i>Neuer Zyklus
    </button>
    <a href="<?= e($plan['url']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-jira"></i>
    </a>
  </div>
  <?php endif; ?>
</div>

<?php if ($plan['description']): ?>
<div class="alert alert-dark border-secondary small mb-4">
  <?= nl2br(e(mb_substr(is_string($plan['description']) ? $plan['description'] : '', 0, 400))) ?>
</div>
<?php endif; ?>

<div class="row g-4">

  <!-- Left: Test Cycles -->
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header border-secondary d-flex align-items-center gap-2">
        <span class="fw-semibold small"><i class="bi bi-play-circle me-1"></i>Test Zyklen</span>
        <span class="badge bg-secondary"><?= count($testCycles) ?></span>
        <?php if ($canEdit): ?>
        <button class="btn btn-outline-success btn-sm py-0 ms-auto" data-bs-toggle="modal" data-bs-target="#createCycleModal" style="font-size:.75rem">
          <i class="bi bi-plus-lg me-1"></i>Neuer Zyklus
        </button>
        <?php endif; ?>
      </div>
      <?php if ($testCycles): ?>
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="font-size:.82rem">
          <thead class="text-muted" style="font-size:.72rem">
            <tr>
              <th class="ps-3">Name</th>
              <th>Status</th>
              <th>Umgebung</th>
              <th>Build</th>
              <th>Gestartet</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($testCycles as $cycle): ?>
            <?php
              $cycleUrl = url('synapse/plan/'.urlencode($plan['key']).'/cycle/'.urlencode((string)$cycle['id']));
              $cBadge = $sc($cycle['status']);
            ?>
            <tr>
              <td class="ps-3">
                <a href="<?= $cycleUrl ?>" class="text-white text-decoration-none fw-semibold">
                  <?= e($cycle['name']) ?>
                </a>
              </td>
              <td><span class="badge bg-<?= $cBadge ?>"><?= e($cycle['status']) ?></span></td>
              <td class="text-muted"><?= e($cycle['environment'] ?: '—') ?></td>
              <td class="text-muted"><?= e($cycle['build'] ?: '—') ?></td>
              <td class="text-muted" style="white-space:nowrap"><?= e($cycle['startDate'] ?: '—') ?></td>
              <td class="text-end pe-3">
                <a href="<?= $cycleUrl ?>" class="btn btn-outline-secondary btn-sm py-0 px-2">
                  <i class="bi bi-eye"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="card-body text-center text-muted py-4">
        <i class="bi bi-play-circle fs-2 d-block mb-2 opacity-25"></i>
        Noch keine Test Zyklen.
        <?php if ($canEdit): ?>
        <a href="#" data-bs-toggle="modal" data-bs-target="#createCycleModal">Ersten Zyklus erstellen</a>.
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Right: Test Cases + Actions -->
  <div class="col-lg-4">

    <!-- Test Cases -->
    <div class="card mb-3">
      <div class="card-header border-secondary d-flex align-items-center gap-2">
        <span class="fw-semibold small"><i class="bi bi-list-check me-1"></i>Test Cases</span>
        <span class="badge bg-secondary"><?= count($testCases) ?></span>
        <?php if ($canEdit): ?>
        <button class="btn btn-outline-primary btn-sm py-0 ms-auto" data-bs-toggle="modal" data-bs-target="#addTestCaseModal" style="font-size:.75rem">
          <i class="bi bi-plus-lg me-1"></i>Hinzufügen
        </button>
        <?php endif; ?>
      </div>
      <?php if ($testCases): ?>
      <div style="max-height:380px;overflow-y:auto">
        <div class="list-group list-group-flush">
          <?php foreach ($testCases as $tc): ?>
          <div class="list-group-item bg-dark border-secondary d-flex align-items-start gap-2 py-2 px-3" style="font-size:.8rem">
            <?php if ($tc['key']): ?>
            <a href="<?= e($jiraUrl) ?>/browse/<?= e($tc['key']) ?>" target="_blank"
               class="badge bg-dark text-warning border border-warning text-decoration-none flex-shrink-0" style="font-size:.65rem">
              <?= e($tc['key']) ?>
            </a>
            <?php endif; ?>
            <span class="flex-grow-1 lh-sm"><?= e($tc['summary']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php else: ?>
      <div class="card-body text-muted small text-center py-3">
        Keine Test Cases verknüpft.
        <?php if ($canEdit): ?>
        <a href="#" data-bs-toggle="modal" data-bs-target="#addTestCaseModal">Hinzufügen</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- RoboDoc Link -->
    <?php if (!$localPlan): ?>
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">
        <i class="bi bi-link-45deg me-1"></i>Mit RoboDoc Plan verknüpfen
      </div>
      <div class="card-body">
        <select id="linkRdPlan" class="form-select form-select-sm mb-2">
          <option value="">— auswählen —</option>
          <?php foreach ($localRdPlans as $p): ?>
          <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-info btn-sm w-100" onclick="linkAndSync('<?= e($plan['key']) ?>','<?= e($csrf) ?>')">
          <i class="bi bi-link me-1"></i>Verknüpfen & Sync
        </button>
        <div id="linkResult" class="mt-2"></div>
      </div>
    </div>
    <?php else: ?>
    <div class="card mb-3 border-info">
      <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
        <i class="bi bi-check-circle text-info"></i>
        <div class="small">
          <div class="fw-semibold"><?= e($localPlan['name']) ?></div>
          <div class="text-muted" style="font-size:.7rem">
            Synced: <?= $localPlan['xray_synced_at'] ? formatDate($localPlan['xray_synced_at'],'d.m.Y H:i') : 'Noch nicht' ?>
          </div>
        </div>
        <button class="btn btn-outline-warning btn-sm py-0 ms-auto" onclick="syncPlan('<?= e($plan['key']) ?>','<?= e($csrf) ?>')">
          <i class="bi bi-arrow-repeat"></i>
        </button>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php if ($canEdit): ?>

<!-- Add Test Case Modal -->
<div class="modal fade" id="addTestCaseModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content bg-dark border-secondary">
    <div class="modal-header border-secondary">
      <h5 class="modal-title"><i class="bi bi-plus-lg me-2"></i>Test Case hinzufügen</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <!-- Tab: Vorhandenen verknüpfen -->
      <ul class="nav nav-tabs mb-3" id="tcTabs">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tcExisting">
          <i class="bi bi-search me-1"></i>Vorhandenen verknüpfen
        </a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tcNew">
          <i class="bi bi-plus-lg me-1"></i>Neuen erstellen
        </a></li>
      </ul>
      <div class="tab-content">
        <!-- Existing -->
        <div class="tab-pane fade show active" id="tcExisting">
          <div class="mb-3">
            <label class="form-label small">Jira Issue Key <span class="text-muted">(z.B. BRSQ-42)</span></label>
            <div class="input-group">
              <input type="text" id="tcKey" class="form-control" placeholder="BRSQ-42">
              <button class="btn btn-outline-secondary" onclick="searchTestCase()">
                <i class="bi bi-search"></i>
              </button>
            </div>
          </div>
          <div id="tcSearchResult" class="mb-3"></div>
          <div class="mb-2">
            <label class="form-label small text-muted">Oder nach JQL suchen</label>
            <div class="input-group">
              <input type="text" id="tcJql" class="form-control form-control-sm"
                     placeholder="project = BRSQ AND issuetype = &quot;Test Case&quot; AND summary ~ &quot;Login&quot;"
                     value="project = <?= e($project ?? 'BRSQ') ?> AND issuetype = &quot;Test Case&quot;">
              <button class="btn btn-outline-secondary btn-sm" onclick="searchByJql()">
                <i class="bi bi-search"></i>
              </button>
            </div>
          </div>
          <div id="tcJqlResult"></div>
        </div>
        <!-- New -->
        <div class="tab-pane fade" id="tcNew">
          <div class="mb-3">
            <label class="form-label">Titel <span class="text-danger">*</span></label>
            <input type="text" id="tcNewTitle" class="form-control" placeholder="Test Case Titel">
          </div>
          <div class="mb-3">
            <label class="form-label">Beschreibung</label>
            <textarea id="tcNewDesc" class="form-control" rows="3"></textarea>
          </div>
          <div id="tcNewResult"></div>
        </div>
      </div>
    </div>
    <div class="modal-footer border-secondary">
      <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Schließen</button>
    </div>
  </div></div>
</div>

<!-- Create Cycle Modal -->
<div class="modal fade" id="createCycleModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content bg-dark border-secondary">
    <div class="modal-header border-secondary">
      <h5 class="modal-title"><i class="bi bi-plus-lg me-2"></i>Neuer Test Zyklus</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <div class="mb-3">
        <label class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" id="cycName" class="form-control" placeholder="z.B. Sprint 1 Regression">
      </div>
      <div class="row g-2 mb-3">
        <div class="col">
          <label class="form-label small">Umgebung</label>
          <input type="text" id="cycEnv" class="form-control" placeholder="z.B. Chrome">
        </div>
        <div class="col">
          <label class="form-label small">Build</label>
          <input type="text" id="cycBuild" class="form-control" placeholder="z.B. v1.2.0">
        </div>
      </div>
      <div class="row g-2 mb-3">
        <div class="col">
          <label class="form-label small">Startdatum</label>
          <input type="date" id="cycStart" class="form-control">
        </div>
        <div class="col">
          <label class="form-label small">Enddatum</label>
          <input type="date" id="cycEnd" class="form-control">
        </div>
      </div>
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="cycPreload" checked>
        <label class="form-check-label small" for="cycPreload">
          Test Cases aus diesem Plan vorladen
        </label>
      </div>
      <?php if ($localRdPlans): ?>
      <div class="mb-2">
        <label class="form-label small">Mit RoboDoc Test Run verknüpfen</label>
        <select id="cycRdRun" class="form-select form-select-sm">
          <option value="">— keine Verknüpfung —</option>
          <?php $rdRuns = Database::fetchAll('SELECT id,name FROM test_runs ORDER BY name'); ?>
          <?php foreach ($rdRuns as $r): ?>
          <option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div id="cycResult"></div>
    </div>
    <div class="modal-footer border-secondary">
      <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
      <button class="btn btn-success btn-sm" onclick="createCycle('<?= e($plan['key']) ?>','<?= e($csrf) ?>')">
        <i class="bi bi-plus-lg me-1"></i>Zyklus erstellen
      </button>
    </div>
  </div></div>
</div>

<?php endif; ?>

<div id="sToast" class="position-fixed bottom-0 end-0 m-3 p-3 rounded bg-dark border-secondary"
     style="display:none;z-index:9999;max-width:380px;font-size:.82rem"></div>

<script>
const _planKey = '<?= e($plan['key']) ?>';
const _csrf    = '<?= e($csrf) ?>';
const _jiraUrl = '<?= e($jiraUrl) ?>';
const _project = '<?= e($project ?? 'BRSQ') ?>';

function _toast(html, ok) {
  const t = document.getElementById('sToast');
  t.innerHTML = '<i class="bi bi-' + (ok ? 'check-circle text-success' : 'x-circle text-danger') + ' me-2"></i>' + html;
  t.style.display = ''; clearTimeout(t._t);
  t._t = setTimeout(() => t.style.display = 'none', 6000);
}

// ── Sync Plan ─────────────────────────────────────────────────────────────
function syncPlan(key, csrf) {
  const lp = <?= $localPlan ? (int)$localPlan['id'] : 'null' ?>;
  if (!lp) { _toast('Kein RoboDoc Plan verknüpft.', false); return; }
  _toast('Synchronisiere...', true);
  fetch('<?= url('synapse/sync') ?>', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf, type: 'plan', local_id: lp, synapse_key: key, direction: 'both'})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      _toast('Sync abgeschlossen — ' + (d.log || []).length + ' Aktionen.', true);
    } else _toast(d.error || 'Fehler', false);
  })
  .catch(() => _toast('Netzwerkfehler', false));
}

// ── Link RoboDoc Plan ─────────────────────────────────────────────────────
function linkAndSync(key, csrf) {
  const localId = document.getElementById('linkRdPlan')?.value;
  if (!localId) return;
  const res = document.getElementById('linkResult');
  res.innerHTML = '<div class="text-muted small"><i class="bi bi-hourglass-split me-1"></i>Verknüpfe...</div>';
  fetch('<?= url('synapse/sync') ?>', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf, type: 'plan', local_id: localId, synapse_key: key, direction: 'both'})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      res.innerHTML = '<div class="text-success small"><i class="bi bi-check-circle me-1"></i>Verknüpft!</div>';
      setTimeout(() => location.reload(), 1200);
    } else res.innerHTML = '<div class="text-danger small">' + (d.error || 'Fehler') + '</div>';
  });
}

// ── Search Test Case by key ───────────────────────────────────────────────
function searchTestCase() {
  const key = document.getElementById('tcKey').value.trim().toUpperCase();
  if (!key) return;
  const res = document.getElementById('tcSearchResult');
  res.innerHTML = '<div class="text-muted small"><i class="bi bi-hourglass-split me-1"></i>Suche...</div>';
  fetch('<?= url('synapse/search-testcase') ?>?key=' + encodeURIComponent(key) + '&plan=' + encodeURIComponent(_planKey), {
    headers: {'X-Requested-With': 'XMLHttpRequest'}
  })
  .then(r => r.json())
  .then(d => {
    if (d.error) { res.innerHTML = '<div class="alert alert-warning py-1 small">' + d.error + '</div>'; return; }
    res.innerHTML = renderTcResult(d, false);
  })
  .catch(() => res.innerHTML = '<div class="text-danger small">Netzwerkfehler</div>');
}

// ── Search by JQL ─────────────────────────────────────────────────────────
function searchByJql() {
  const jql = document.getElementById('tcJql').value.trim();
  if (!jql) return;
  const res = document.getElementById('tcJqlResult');
  res.innerHTML = '<div class="text-muted small"><i class="bi bi-hourglass-split me-1"></i>Suche...</div>';
  fetch('<?= url('synapse/search-testcase') ?>?jql=' + encodeURIComponent(jql) + '&plan=' + encodeURIComponent(_planKey), {
    headers: {'X-Requested-With': 'XMLHttpRequest'}
  })
  .then(r => r.json())
  .then(d => {
    if (d.error) { res.innerHTML = '<div class="alert alert-warning py-1 small">' + d.error + '</div>'; return; }
    if (d.issues) {
      res.innerHTML = d.issues.length === 0
        ? '<div class="text-muted small">Keine Ergebnisse.</div>'
        : '<div class="list-group">' + d.issues.map(i => renderTcResult(i, true)).join('') + '</div>';
    } else res.innerHTML = renderTcResult(d, false);
  })
  .catch(() => res.innerHTML = '<div class="text-danger small">Netzwerkfehler</div>');
}

function renderTcResult(d, compact) {
  if (!d || !d.key) return '<div class="text-muted small">Nicht gefunden.</div>';
  return `<div class="list-group-item bg-dark border-secondary d-flex align-items-center gap-2 py-2" style="font-size:.82rem">
    <a href="${_jiraUrl}/browse/${d.key}" target="_blank"
       class="badge bg-dark text-warning border border-warning text-decoration-none flex-shrink-0">${d.key}</a>
    <span class="flex-grow-1">${d.summary || ''}</span>
    <button class="btn btn-outline-success btn-sm py-0 px-2 flex-shrink-0"
            onclick="addTestCaseToPlan('${d.key}', this)">
      <i class="bi bi-plus-lg"></i>
    </button>
  </div>`;
}

// ── Add Test Case to Plan ─────────────────────────────────────────────────
function addTestCaseToPlan(tcKey, btn) {
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
  fetch('<?= url('synapse/plan/') ?>' + _planKey + '/add-testcase', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: _csrf, testcase_key: tcKey})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      btn.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
      _toast('Test Case ' + tcKey + ' hinzugefügt.', true);
      setTimeout(() => location.reload(), 1500);
    } else {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-plus-lg"></i>';
      _toast(d.error || 'Fehler', false);
    }
  })
  .catch(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-plus-lg"></i>'; });
}

// ── Create new Test Case ──────────────────────────────────────────────────
function createNewTestCase() {
  const title = document.getElementById('tcNewTitle').value.trim();
  const res   = document.getElementById('tcNewResult');
  if (!title) { res.innerHTML = '<div class="alert alert-warning py-1 small mt-2">Titel erforderlich</div>'; return; }
  res.innerHTML = '<div class="text-muted small mt-2"><i class="bi bi-hourglass-split me-1"></i>Erstelle...</div>';
  fetch('<?= url('synapse/plan/') ?>' + _planKey + '/create-testcase', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({
      _csrf: _csrf,
      title,
      description: document.getElementById('tcNewDesc').value.trim()
    })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      res.innerHTML = '<div class="alert alert-success py-1 small mt-2">Erstellt: <a href="' + _jiraUrl + '/browse/' + d.key + '" target="_blank">' + d.key + '</a></div>';
      setTimeout(() => location.reload(), 2000);
    } else res.innerHTML = '<div class="alert alert-danger py-1 small mt-2">' + JSON.stringify(d.error) + '</div>';
  });
}

// ── Create Cycle ──────────────────────────────────────────────────────────
function createCycle(planKey, csrf) {
  const name = document.getElementById('cycName').value.trim();
  const res  = document.getElementById('cycResult');
  if (!name) { res.innerHTML = '<div class="alert alert-warning py-1 small mt-2">Name erforderlich</div>'; return; }
  res.innerHTML = '<div class="text-muted small mt-2"><i class="bi bi-hourglass-split me-1"></i>Erstelle...</div>';
  fetch('<?= url('synapse/plan/') ?>' + planKey + '/create-cycle', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({
      _csrf: csrf, name,
      environment: document.getElementById('cycEnv').value,
      build:       document.getElementById('cycBuild').value,
      start_date:  document.getElementById('cycStart').value,
      end_date:    document.getElementById('cycEnd').value,
      preload_runs: document.getElementById('cycPreload').checked ? '1' : '',
      robodoc_run_id: document.getElementById('cycRdRun')?.value || '',
    })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      res.innerHTML = '<div class="alert alert-success py-1 small mt-2">Zyklus "' + d.cycleName + '" erstellt!</div>';
      setTimeout(() => location.reload(), 1500);
    } else res.innerHTML = '<div class="alert alert-danger py-1 small mt-2">' + JSON.stringify(d.error) + '</div>';
  });
}
</script>
