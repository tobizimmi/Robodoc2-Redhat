<?php
$csrf    = Auth::csrfToken();
$canEdit = Auth::canEdit('testing');
$canTr   = Auth::canTestRequests() && Auth::canView('test_requests');
$sc = fn($s) => match(strtolower($s ?? '')) {
    'passed','pass'             => 'success',
    'failed','fail'             => 'danger',
    'skipped','blocked'         => 'secondary',
    'in progress','executing'   => 'info',
    default                     => 'warning',
};

// Build base URL for AJAX calls
$cycleBase = url('synapse/plan/' . urlencode($planKey) . '/cycle/' . urlencode($cycleId));
$trBase    = url('synapse/');
?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back"
     data-fallback="<?= url('synapse/plan/' . urlencode($planKey)) ?>">
    <i class="bi bi-arrow-left"></i>
  </a>
  <div class="flex-grow-1">
    <h5 class="mb-0 lh-sm"><?= e($cycleInfo['name'] ?? 'Cycle ' . $cycleId) ?></h5>
    <div class="d-flex gap-2 align-items-center mt-1 text-muted flex-wrap" style="font-size:.78rem">
      <span>Plan: <a href="<?= url('synapse/plan/' . urlencode($planKey)) ?>" class="text-warning"><?= e($planKey) ?></a></span>
      <?php if ($cycleInfo['environment'] ?? ''): ?>
      <span>· <?= e($cycleInfo['environment']) ?></span>
      <?php endif; ?>
      <?php if ($cycleInfo['build'] ?? ''): ?>
      <span>· Build: <?= e($cycleInfo['build']) ?></span>
      <?php endif; ?>
      <?php if ($localRun): ?>
      <span class="badge bg-info text-dark ms-1"><i class="bi bi-link-45deg me-1"></i>RoboDoc: <?= e($localRun['name']) ?></span>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($canEdit): ?>
  <button class="btn btn-outline-warning btn-sm" onclick="syncCycle('<?= e($csrf) ?>')" title="Bidirektionaler Sync">
    <i class="bi bi-arrow-repeat me-1"></i>Sync
  </button>
  <?php endif; ?>
</div>

<!-- Stats bar -->
<?php if ($total > 0): ?>
<div class="d-flex gap-3 mb-4 flex-wrap align-items-center">
  <?php foreach ([['Passed',$passed,'success'],['Failed',$failed,'danger'],['Skipped',$skipped,'secondary'],['Offen',$notRun,'warning'],['Gesamt',$total,'dark']] as [$label,$n,$color]): ?>
  <div class="card text-center px-3 py-2" style="min-width:80px">
    <div class="fw-bold fs-5 text-<?= $color ?>"><?= $n ?></div>
    <div class="text-muted" style="font-size:.7rem"><?= $label ?></div>
  </div>
  <?php endforeach; ?>
  <?php if ($total > 0): ?>
  <div class="flex-grow-1" style="max-width:200px">
    <div class="progress" style="height:6px">
      <div class="progress-bar bg-success" style="width:<?= round($passed/$total*100) ?>%"></div>
      <div class="progress-bar bg-danger"  style="width:<?= round($failed/$total*100) ?>%"></div>
      <div class="progress-bar bg-secondary" style="width:<?= round($skipped/$total*100) ?>%"></div>
    </div>
    <div class="text-muted mt-1" style="font-size:.68rem"><?= round($passed/$total*100) ?>% bestanden</div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Test Runs table with Test Request linking -->
<div class="card">
  <div class="card-header border-secondary d-flex align-items-center gap-2">
    <span class="fw-semibold small"><i class="bi bi-list-check me-1"></i>Test Ergebnisse</span>
    <span class="badge bg-secondary"><?= $total ?></span>
    <?php if ($canTr): ?>
    <span class="text-muted small ms-auto"><i class="bi bi-info-circle me-1"></i>Klick auf <i class="bi bi-link-45deg"></i> um Testaufträge zu verwalten</span>
    <?php endif; ?>
  </div>
  <?php if ($testRuns): ?>
  <div class="table-responsive">
    <table class="table table-dark table-hover align-middle mb-0" style="font-size:.82rem">
      <thead class="text-muted" style="font-size:.72rem">
        <tr>
          <th class="ps-3" style="width:100px">Test Case</th>
          <th>Zusammenfassung</th>
          <th style="width:100px">Status</th>
          <th style="width:100px">Ausgeführt von</th>
          <th style="width:80px">Datum</th>
          <th style="width:80px">Bugs</th>
          <?php if ($canTr): ?>
          <th style="width:50px" title="Testaufträge">
            <i class="bi bi-file-earmark-check text-warning"></i>
          </th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($testRuns as $run): ?>
        <?php $tcKey = $run['testCaseKey'] ?? ''; ?>
        <tr id="tr-row-<?= e($tcKey) ?>">
          <td class="ps-3">
            <?php if ($tcKey): ?>
            <a href="<?= e($jiraUrl) ?>/browse/<?= e($tcKey) ?>" target="_blank"
               class="badge bg-dark text-warning border border-warning text-decoration-none" style="font-size:.65rem">
              <?= e($tcKey) ?>
            </a>
            <?php endif; ?>
          </td>
          <td class="lh-sm"><?= e($run['summary']) ?></td>
          <td><span class="badge bg-<?= $sc($run['status']) ?>"><?= e($run['status']) ?></span></td>
          <td class="text-muted"><?= e($run['executedBy']) ?></td>
          <td class="text-muted" style="white-space:nowrap">
            <?= $run['executedOn'] ? substr(e($run['executedOn']), 0, 10) : '—' ?>
          </td>
          <td>
            <?php foreach (($run['bugs'] ?? []) as $bug): ?>
            <a href="<?= e($jiraUrl) ?>/browse/<?= e($bug['key']) ?>" target="_blank"
               class="badge bg-danger text-decoration-none me-1" style="font-size:.65rem"><?= e($bug['key']) ?></a>
            <?php endforeach; ?>
          </td>
          <?php if ($canTr && $tcKey): ?>
          <td class="text-center">
            <button class="btn btn-outline-warning btn-sm py-0 px-1"
                    onclick="openTrPanel('<?= e($tcKey) ?>', '<?= e(addslashes($run['summary'])) ?>')"
                    id="tr-btn-<?= e($tcKey) ?>"
                    title="Testaufträge verwalten">
              <i class="bi bi-link-45deg"></i>
              <span class="badge bg-dark ms-1 tr-count" id="tr-count-<?= e($tcKey) ?>" style="font-size:.6rem"></span>
            </button>
          </td>
          <?php elseif ($canTr): ?>
          <td></td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="card-body text-muted small text-center py-4">
    <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>
    Keine Testergebnisse in diesem Zyklus.
  </div>
  <?php endif; ?>
</div>

<!-- RoboDoc link card -->
<?php if (!$localRun && $canEdit): ?>
<div class="card mt-3" style="max-width:380px">
  <div class="card-header border-secondary fw-semibold small"><i class="bi bi-link-45deg me-1"></i>Mit RoboDoc Test Run verknüpfen</div>
  <div class="card-body">
    <select id="linkRdRun" class="form-select form-select-sm mb-2">
      <option value="">— auswählen —</option>
      <?php foreach ($localRdRuns as $r): ?>
      <option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline-info btn-sm w-100" onclick="linkRdRun('<?= e($csrf) ?>')">
      <i class="bi bi-link me-1"></i>Verknüpfen & Sync
    </button>
    <div id="linkRunRes" class="mt-2"></div>
  </div>
</div>
<?php endif; ?>

<?php if ($canTr): ?>
<!-- Test Request Panel (slides in from right) -->
<div id="trPanel" style="display:none;position:fixed;top:0;right:0;bottom:0;width:420px;z-index:1050;background:#1a1d21;border-left:1px solid rgba(255,255,255,.15);box-shadow:-4px 0 20px rgba(0,0,0,.5);overflow-y:auto">
  <div class="d-flex align-items-center gap-2 p-3 border-bottom border-secondary">
    <i class="bi bi-file-earmark-check text-warning fs-5"></i>
    <div class="flex-grow-1">
      <div class="fw-semibold small" id="trPanelTitle">Testaufträge</div>
      <div class="text-muted" style="font-size:.7rem" id="trPanelKey"></div>
    </div>
    <button class="btn-close btn-close-white btn-sm" onclick="closeTrPanel()"></button>
  </div>

  <!-- Existing links -->
  <div class="p-3">
    <div class="small fw-semibold mb-2 text-muted text-uppercase" style="letter-spacing:.04em">Verknüpfte Aufträge</div>
    <div id="trLinkList">
      <div class="text-muted small">Lädt...</div>
    </div>

    <?php if ($canEdit): ?>
    <hr class="border-secondary mt-3 mb-3">
    <ul class="nav nav-tabs nav-sm mb-3" id="trPanelTabs">
      <li class="nav-item">
        <a class="nav-link active py-1 small" data-bs-toggle="tab" href="#trTabLink">
          <i class="bi bi-link-45deg me-1"></i>Verknüpfen
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link py-1 small" data-bs-toggle="tab" href="#trTabCreate">
          <i class="bi bi-plus-lg me-1"></i>Neu erstellen
        </a>
      </li>
    </ul>
    <div class="tab-content">

      <!-- Tab: Link existing -->
      <div class="tab-pane fade show active" id="trTabLink">
        <div class="input-group input-group-sm mb-2">
          <input type="text" id="trSearch" class="form-control bg-dark text-white border-secondary"
                 placeholder="Auftrag suchen..." oninput="searchTr(this.value)">
          <span class="input-group-text border-secondary bg-dark"><i class="bi bi-search text-muted"></i></span>
        </div>
        <div id="trSearchResults" style="max-height:280px;overflow-y:auto">
          <div class="text-muted small">Tippen zum Suchen...</div>
        </div>
      </div>

      <!-- Tab: Create new -->
      <div class="tab-pane fade" id="trTabCreate">
        <p class="text-muted small mb-2">Erstellt einen neuen Testauftrag und verknüpft ihn direkt mit diesem Test Case.</p>
        <a id="trCreateLink" href="#" target="_blank" class="btn btn-outline-success btn-sm w-100">
          <i class="bi bi-plus-lg me-1"></i>Neuen Testauftrag erstellen
        </a>
        <div class="text-muted small mt-2">
          <i class="bi bi-info-circle me-1"></i>Öffnet das Formular vorausgefüllt. Nach dem Speichern hier verknüpfen.
        </div>
      </div>

    </div>
    <?php endif; ?>
  </div>
</div>
<div id="trPanelBg" onclick="closeTrPanel()" style="display:none;position:fixed;inset:0;z-index:1040;background:rgba(0,0,0,.4)"></div>
<?php endif; ?>

<div id="sToast" class="position-fixed bottom-0 end-0 m-3 p-3 rounded bg-dark border-secondary"
     style="display:none;z-index:9999;max-width:380px;font-size:.82rem"></div>

<script>
const _csrf    = '<?= e($csrf) ?>';
const _planKey = '<?= e($planKey) ?>';
const _cycleId = '<?= e($cycleId) ?>';
const _cycleBase = '<?= e($cycleBase) ?>';
const _trBase    = '<?= e(url('synapse/')) ?>';
const _trCreateBase = '<?= e(url('test-requests/create')) ?>';
const _canEdit = <?= $canEdit ? 'true' : 'false' ?>;

let _activeTcKey  = '';
let _activeTcName = '';
let _searchTimer  = null;

function _toast(html, ok) {
  const t = document.getElementById('sToast');
  t.innerHTML = '<i class="bi bi-' + (ok ? 'check-circle text-success' : 'x-circle text-danger') + ' me-2"></i>' + html;
  t.style.display = ''; clearTimeout(t._t);
  t._t = setTimeout(() => t.style.display = 'none', 5000);
}

// ── Sync ───────────────────────────────────────────────────────
function syncCycle(csrf) {
  const lr = <?= $localRun ? (int)$localRun['id'] : 'null' ?>;
  if (!lr) { _toast('Kein RoboDoc Run verknüpft.', false); return; }
  _toast('Synchronisiere...', true);
  fetch('<?= url('synapse/sync') ?>', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf, type: 'run', local_id: lr, synapse_key: _planKey + '/cycle/' + _cycleId, direction: 'both'})
  }).then(r => r.json()).then(d => {
    if (d.success) _toast('Sync abgeschlossen — ' + (d.log || []).length + ' Aktionen.', true);
    else _toast(d.error || 'Fehler', false);
  });
}

function linkRdRun(csrf) {
  const localId = document.getElementById('linkRdRun').value;
  if (!localId) return;
  const res = document.getElementById('linkRunRes');
  res.innerHTML = '<span class="text-muted small">Verknüpfe...</span>';
  fetch('<?= url('synapse/sync') ?>', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf, type: 'run', local_id: localId, synapse_key: _planKey + '/cycle/' + _cycleId, direction: 'both'})
  }).then(r => r.json()).then(d => {
    if (d.success) { res.innerHTML = '<span class="text-success small">Verknüpft!</span>'; setTimeout(() => location.reload(), 1200); }
    else res.innerHTML = '<span class="text-danger small">' + (d.error || 'Fehler') + '</span>';
  });
}

// ── Load link counts on page load ─────────────────────────────
(function() {
  const tcKeys = [...document.querySelectorAll('[id^="tr-count-"]')].map(el => el.id.replace('tr-count-', ''));
  tcKeys.forEach(key => {
    fetch(_cycleBase + '/request-links?tc=' + encodeURIComponent(key), { headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(links => {
      const el = document.getElementById('tr-count-' + key);
      if (el && links.length > 0) {
        el.textContent = links.length;
        el.style.background = '#ffc107';
        el.style.color = '#000';
      }
    });
  });
})();

// ── Open Test Request Panel ───────────────────────────────────
function openTrPanel(tcKey, tcName) {
  _activeTcKey  = tcKey;
  _activeTcName = tcName;
  document.getElementById('trPanelTitle').textContent = 'Testaufträge: ' + tcName.substring(0, 40) + (tcName.length > 40 ? '...' : '');
  document.getElementById('trPanelKey').textContent   = tcKey;

  // Update create link
  const createLink = document.getElementById('trCreateLink');
  if (createLink) {
    createLink.href = _trCreateBase + '?synapse_tc=' + encodeURIComponent(tcKey) + '&synapse_name=' + encodeURIComponent(tcName) + '&synapse_plan=' + encodeURIComponent(_planKey) + '&synapse_cycle=' + encodeURIComponent(_cycleId);
  }

  document.getElementById('trPanel').style.display = '';
  document.getElementById('trPanelBg').style.display = '';
  loadTrLinks(tcKey);
  // Reset search
  const searchEl = document.getElementById('trSearch');
  if (searchEl) { searchEl.value = ''; }
  document.getElementById('trSearchResults').innerHTML = '<div class="text-muted small">Tippen zum Suchen...</div>';
}

function closeTrPanel() {
  document.getElementById('trPanel').style.display = 'none';
  document.getElementById('trPanelBg').style.display = 'none';
  _activeTcKey = '';
}

function loadTrLinks(tcKey) {
  const list = document.getElementById('trLinkList');
  list.innerHTML = '<div class="text-muted small">Lädt...</div>';
  fetch(_cycleBase + '/request-links?tc=' + encodeURIComponent(tcKey), { headers: {'X-Requested-With': 'XMLHttpRequest'} })
  .then(r => r.json())
  .then(links => {
    if (!links.length) {
      list.innerHTML = '<div class="text-muted small">Keine Aufträge verknüpft.</div>';
      updateCount(tcKey, 0);
      return;
    }
    list.innerHTML = links.map(l => renderLink(l)).join('');
    updateCount(tcKey, links.length);
  })
  .catch(() => list.innerHTML = '<div class="text-danger small">Ladefehler</div>');
}

function renderLink(l) {
  const jiraLink = l.jira_issue_key
    ? '<a href="' + (l.jira_issue_url || '#') + '" target="_blank" class="badge bg-dark text-warning border border-warning text-decoration-none me-1" style="font-size:.65rem">' + l.jira_issue_key + '</a>'
    : '';
  const statusBadge = '<span class="badge bg-secondary ms-1" style="font-size:.6rem">' + (l.status || '') + '</span>';
  return '<div class="d-flex align-items-center gap-2 py-2 border-bottom border-secondary" style="font-size:.8rem">' +
    '<div class="flex-grow-1"><div>' + jiraLink + '<span class="text-white">' + l.summary.substring(0, 45) + '</span>' + statusBadge + '</div>' +
    '<div class="text-muted mt-1" style="font-size:.68rem"><a href="<?= url('test-requests/') ?>' + l.test_request_id + '" target="_blank">Auftrag #' + l.test_request_id + '</a></div>' +
    '</div>' +
    (_canEdit ? '<button class="btn btn-outline-danger btn-sm py-0 px-1 flex-shrink-0" onclick="unlinkTr(' + l.test_request_id + ', \'' + l.synapse_test_case_key + '\')" title="Verknüpfung entfernen"><i class="bi bi-x-lg" style="font-size:.7rem"></i></button>' : '') +
    '</div>';
}

function updateCount(tcKey, count) {
  const el = document.getElementById('tr-count-' + tcKey);
  if (!el) return;
  el.textContent = count > 0 ? count : '';
  el.style.background = count > 0 ? '#ffc107' : '';
  el.style.color = count > 0 ? '#000' : '';
}

// ── Search Test Requests ──────────────────────────────────────
function searchTr(q) {
  clearTimeout(_searchTimer);
  _searchTimer = setTimeout(() => {
    const res = document.getElementById('trSearchResults');
    if (!q.trim()) { res.innerHTML = '<div class="text-muted small">Tippen zum Suchen...</div>'; return; }
    res.innerHTML = '<div class="text-muted small">Suche...</div>';
    fetch('<?= url('synapse/search-requests') ?>?q=' + encodeURIComponent(q), { headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(items => {
      if (!items.length) { res.innerHTML = '<div class="text-muted small">Keine Aufträge gefunden.</div>'; return; }
      res.innerHTML = items.map(item => {
        const jira = item.jira_issue_key ? '<span class="badge bg-dark text-warning border border-warning me-1" style="font-size:.6rem">' + item.jira_issue_key + '</span>' : '';
        return '<div class="d-flex align-items-center gap-2 py-2 border-bottom border-secondary" style="font-size:.8rem">' +
          '<div class="flex-grow-1">' + jira + '<span class="text-white">' + item.summary.substring(0, 40) + '</span>' +
          '<div class="text-muted" style="font-size:.68rem">Auftrag #' + item.id + '</div></div>' +
          '<button class="btn btn-outline-success btn-sm py-0 px-2 flex-shrink-0" onclick="linkTr(' + item.id + ', \'' + item.summary.replace(/'/g, "\\'").substring(0, 80) + '\')" title="Verknüpfen"><i class="bi bi-plus-lg"></i></button>' +
          '</div>';
      }).join('');
    })
    .catch(() => res.innerHTML = '<div class="text-danger small">Suchfehler</div>');
  }, 300);
}

// ── Link Test Request ─────────────────────────────────────────
function linkTr(reqId, reqSummary) {
  if (!_activeTcKey) return;
  fetch(_cycleBase + '/link-request', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: _csrf, testcase_key: _activeTcKey, testcase_name: _activeTcName, test_request_id: reqId})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      _toast('"' + reqSummary.substring(0, 30) + '" verknüpft.', true);
      loadTrLinks(_activeTcKey);
      document.getElementById('trSearch').value = '';
      document.getElementById('trSearchResults').innerHTML = '<div class="text-muted small">Tippen zum Suchen...</div>';
    } else {
      _toast(d.error || 'Fehler', false);
    }
  })
  .catch(() => _toast('Netzwerkfehler', false));
}

// ── Unlink Test Request ───────────────────────────────────────
function unlinkTr(reqId, tcKey) {
  if (!confirm('Verknüpfung entfernen?')) return;
  fetch(_cycleBase + '/unlink-request', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: _csrf, testcase_key: tcKey, test_request_id: reqId})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) { _toast('Verknüpfung entfernt.', true); loadTrLinks(_activeTcKey); }
    else _toast(d.error || 'Fehler', false);
  });
}
</script>
