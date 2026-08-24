<?php
$customMap    = $customMap ?? [];
$trTypeIds2   = array_filter(array_map('intval', explode(',', appSetting('test_result_entry_type_ids',''))));
$isTestEntry  = $isTestEntry ?? (!empty($trTypeIds2) && in_array((int)($data['entry_type_id']??0), $trTypeIds2));
?>
<div class="row g-3">
  <!-- Left column -->
  <div class="col-lg-8">
    <!-- Basic fields -->
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small d-flex align-items-center justify-content-between">
        <span>Basic Info</span>
        <div class="d-flex gap-1">
          <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2"
                  data-bs-toggle="modal" data-bs-target="#templateModal">
            <i class="bi bi-layout-text-sidebar me-1"></i><span style="font-size:.75rem">Templates</span>
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2"
                  data-bs-toggle="modal" data-bs-target="#qrModal">
            <i class="bi bi-qr-code-scan me-1"></i><span style="font-size:.75rem">Scan QR</span>
          </button>
        </div>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" value="<?= e($data['title'] ?? '') ?>" placeholder="Short description…">
          </div>
          <div class="col-md-4">
            <?php if ($isTestEntry ?? false): ?>
              <?php
                // Auto-select "Test Result" type
                $trType = null;
                foreach ($entryTypes as $et) {
                    if (strtolower($et['name']) === 'test result') { $trType = $et; break; }
                }
                // Fallback: first configured test result type
                if (!$trType && !empty($trTypeIds2)) {
                    foreach ($entryTypes as $et) {
                        if (in_array((int)$et['id'], $trTypeIds2)) { $trType = $et; break; }
                    }
                }
              ?>
              <label class="form-label">Entry Type</label>
              <div class="form-control bg-body-secondary d-flex align-items-center gap-2" style="pointer-events:none">
                <span class="badge" style="background:<?= e($trType['color'] ?? '#0ea5e9') ?>"><?= e($trType['name'] ?? 'Test Result') ?></span>
              </div>
              <input type="hidden" name="entry_type_id" value="<?= $trType['id'] ?? '' ?>">
            <?php else: ?>
              <label class="form-label">Entry Type <span class="text-danger">*</span></label>
              <select name="entry_type_id" class="form-select" required>
                <option value="">Select type…</option>
                <?php foreach ($entryTypes as $et): ?>
                <option value="<?= $et['id'] ?>" <?= ($data['entry_type_id'] ?? 0) == $et['id'] ? 'selected' : '' ?>>
                  <?= e($et['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </div>
          <div class="col-12">
            <label class="form-label d-flex align-items-center justify-content-between">
              <span>Description</span>
              <button type="button" id="dictBtn" onclick="startDictation()"
                      class="btn btn-sm btn-outline-secondary py-0 px-2" title="Voice dictation (tap to start/stop)">
                <i class="bi bi-mic" id="dictIcon"></i>
              </button>
            </label>
            <textarea name="description" id="descriptionField" class="form-control" rows="5" placeholder="Detailed description of the issue / observation…"><?= e($data['description'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- Epic & Parent Entry -->
    <?php
      $epics   = $epics   ?? [];
      $parents = $parents ?? [];
    ?>
    <?php if (!empty($epics) || !empty($parents)): ?>
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">
        <i class="bi bi-diagram-2 me-2"></i>Verknüpfung
      </div>
      <div class="card-body">
        <div class="row g-3">
          <?php if (!empty($epics)): ?>
          <div class="col-md-6">
            <label class="form-label d-flex align-items-center gap-2">
              <i class="bi bi-lightning-fill text-warning"></i>Epic
              <span class="text-muted fw-normal small">(optional)</span>
            </label>
            <select name="epic_id" class="form-select" id="epicSelect">
              <option value="">— Kein Epic —</option>
              <?php foreach ($epics as $epic): ?>
              <option value="<?= $epic['id'] ?>"
                      data-color="<?= e($epic['color'] ?? '#888') ?>"
                      <?= ($data['epic_id'] ?? 0) == $epic['id'] ? 'selected' : '' ?>>
                <?= e($epic['title']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <?php if (!empty($parents)): ?>
          <div class="col-md-6">
            <label class="form-label d-flex align-items-center gap-2">
              <i class="bi bi-diagram-3 text-info"></i>Parent Entry
              <span class="text-muted fw-normal small">(wird dann Sub-Entry)</span>
            </label>
            <select name="parent_id" class="form-select" id="parentSelect">
              <option value="">— Kein Parent —</option>
              <?php
                $currentProject = (int)($data['project_id'] ?? 0);
              ?>
              <?php foreach ($parents as $pe): ?>
              <option value="<?= $pe['id'] ?>"
                      data-project="<?= $pe['project_id'] ?? 0 ?>"
                      <?= ($data['parent_id'] ?? 0) == $pe['id'] ? 'selected' : '' ?>>
                [<?= e($pe['project_name'] ?? '?') ?>] <?= e(mb_substr($pe['title'], 0, 60)) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Wähle einen bestehenden Eintrag als übergeordneten Eintrag.</div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Test Result Extended Fields -->
    <?php if ($isTestEntry ?? false): ?>
    <?php include __DIR__ . '/_test_result_form.php'; ?>
    <?php endif; ?>

    <!-- Attachments -->
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">Attachments</div>
      <div class="card-body">
        <div class="upload-zone" id="dropZone">
          <i class="bi bi-cloud-upload fs-3 text-muted d-block mb-2"></i>
          <p class="text-muted mb-2">Drag files here or</p>
          <div class="d-flex gap-2 justify-content-center flex-wrap">
            <label class="btn btn-sm btn-outline-secondary mb-0" style="cursor:pointer">
              <i class="bi bi-folder2-open me-1"></i>Add Files / Photos
              <input type="file" name="files[]" multiple id="fileInput"
                     style="position:fixed;left:-9999px;top:-9999px;width:1px;height:1px">
            </label>
            <label class="btn btn-sm btn-outline-info mb-0" style="cursor:pointer">
              <i class="bi bi-camera me-1"></i>Camera
              <input type="file" name="files[]" id="cameraInput" accept="image/*,video/*" capture="environment"
                     style="position:fixed;left:-9999px;top:-9999px;width:1px;height:1px">
            </label>
          </div>
          <div class="mt-1"><small class="text-muted">Images, videos, PDFs, ZIP, logs (max. 100 MB)</small></div>
        </div>
        <div id="filePreview" class="mt-2"></div>
        <?php if (isset($entry) && !empty($attachments)): ?>
        <div class="mt-3 d-flex flex-wrap gap-2">
          <?php foreach ($attachments as $att): ?>
          <div class="text-center" style="width:80px">
            <?php if (isImage($att['mime_type'])): ?>
            <img src="<?= url('attachments/' . $att['id']) ?>" class="media-thumb" alt="">
            <?php else: ?>
            <div class="media-thumb d-flex align-items-center justify-content-center bg-secondary">
              <i class="bi bi-file-earmark fs-4"></i>
            </div>
            <?php endif; ?>
            <small class="text-muted text-truncate d-block" style="font-size:.65rem"><?= e($att['display_name'] ?: $att['original_name']) ?></small>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Custom Fields -->
    <?php if (!empty($customFields)): ?>
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">Custom Fields</div>
      <div class="card-body">
        <div class="row g-3">
          <?php foreach ($customFields as $cf): ?>
          <div class="col-md-6">
            <label class="form-label small"><?= e($cf['name']) ?></label>
            <?php $val = $customMap[$cf['id']] ?? ''; ?>
            <?php if ($cf['field_type'] === 'textarea'): ?>
            <textarea name="custom[<?= $cf['id'] ?>]" class="form-control form-control-sm" rows="2" placeholder="<?= e($cf['placeholder'] ?? '') ?>"><?= e($val) ?></textarea>
            <?php elseif ($cf['field_type'] === 'select'): ?>
            <?php $opts = json_decode($cf['options'] ?? '[]', true) ?: []; ?>
            <select name="custom[<?= $cf['id'] ?>]" class="form-select form-select-sm">
              <option value="">—</option>
              <?php foreach ($opts as $opt): ?>
              <option <?= $val === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
            <?php elseif ($cf['field_type'] === 'number'): ?>
            <input type="number" name="custom[<?= $cf['id'] ?>]" class="form-control form-control-sm" value="<?= e($val) ?>" placeholder="<?= e($cf['placeholder'] ?? '') ?>">
            <?php else: ?>
            <input type="text" name="custom[<?= $cf['id'] ?>]" class="form-control form-control-sm" value="<?= e($val) ?>" placeholder="<?= e($cf['placeholder'] ?? '') ?>">
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right column -->
  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">Classification</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Project <span class="text-danger">*</span></label>
          <select name="project_id" class="form-select" required>
            <option value="">Select project…</option>
            <?php foreach ($projects as $p): ?>
            <option value="<?= $p['id'] ?>" <?= ($data['project_id'] ?? 0) == $p['id'] ? 'selected' : '' ?>>
              <?= e($p['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Error Category</label>
          <select name="error_category_id" class="form-select">
            <option value="">None</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($data['error_category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Priority <span class="text-danger">*</span></label>
          <select name="priority" class="form-select" required>
            <?php foreach (['Low','Medium','High','Highest','Blocker'] as $pv): ?>
            <option value="<?= $pv ?>" <?= ($data['priority'] ?? 'Medium') === $pv ? 'selected' : '' ?>><?= $pv ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Date</label>
          <input type="date" name="entry_date" class="form-control" value="<?= e($data['entry_date'] ?? date('Y-m-d')) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Time</label>
          <input type="time" name="entry_time" class="form-control" value="<?= e(substr($data['entry_time'] ?? date('H:i'), 0, 5)) ?>">
        </div>
        <?php if (!empty($environments)): ?>
        <div class="mb-3">
          <label class="form-label">Test Environment</label>
          <select name="environment_id" class="form-select">
            <option value="">None</option>
            <?php foreach ($environments as $env): ?>
            <option value="<?= $env['id'] ?>" <?= ($data['environment_id'] ?? 0) == $env['id'] ? 'selected' : '' ?>>
              <?= e($env['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="mb-3">
          <label class="form-label">Status</label>
          <?php $entryStatus = $data['status'] ?? 'new'; ?>
          <select name="status" class="form-select">
            <?php foreach (entryStatuses() as $sv => $sl): ?>
            <option value="<?= $sv ?>" <?= $entryStatus === $sv ? 'selected' : '' ?>><?= $sl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if (!empty($users)): ?>
        <div class="mb-3">
          <label class="form-label">Assigned To</label>
          <select name="assigned_to" class="form-select">
            <option value="">— Unassigned —</option>
            <?php foreach ($users as $u): ?>
            <option value="<?= $u['id'] ?>" <?= ($data['assigned_to'] ?? 0) == $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="form-check">
          <input type="checkbox" name="is_report_relevant" class="form-check-input" id="isReportRelevant" value="1"
                 <?= ($data['is_report_relevant'] ?? 1) ? 'checked' : '' ?>>
          <label class="form-check-label small" for="isReportRelevant">
            <i class="bi bi-bar-chart-fill text-success"></i> Relevant for Reporting
          </label>
        </div>
        <div class="form-check">
          <input type="checkbox" name="is_private" class="form-check-input" id="isPrivate" value="1"
                 <?= !empty($data['is_private']) ? 'checked' : '' ?>>
          <label class="form-check-label small" for="isPrivate">Private Entry</label>
        </div>
        <div class="form-check mt-1">
          <input type="checkbox" name="is_key_question" class="form-check-input" id="isKeyQuestion" value="1"
                 <?= !empty($data['is_key_question']) ? 'checked' : '' ?>>
          <label class="form-check-label small" for="isKeyQuestion">Key Question</label>
        </div>
        <?php if (!isset($entry)): ?>
        <div class="form-check mt-2">
          <input type="checkbox" name="mark_todo" class="form-check-input" id="markTodo" value="1">
          <label class="form-check-label small" for="markTodo">Mark as Todo</label>
        </div>
        <?php if (!empty($settings['jira_url'])): ?>
        <div class="mt-3">
          <label class="form-label small"><i class="bi bi-bug me-1 text-warning"></i>Jira</label>
          <div class="mb-2">
            <input type="text" name="jira_issue_key" id="jiraKeyNew" class="form-control form-control-sm font-monospace"
                   placeholder="Link existing issue, e.g. RD-123" oninput="onJiraKeyNew(this)">
            <div class="form-text" style="font-size:.7rem">Enter an existing key to link it, or leave empty and use the checkbox below to create a new one.</div>
          </div>
          <div class="form-check">
            <input type="checkbox" name="jira_auto_create" class="form-check-input" id="jiraAutoCreate" value="1"
                   <?= !empty($data['jira_auto_create']) ? 'checked' : '' ?> onchange="onJiraAutoCreate(this)">
            <label class="form-check-label small" for="jiraAutoCreate">Create new Jira issue on save</label>
          </div>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="mt-3">
          <label class="form-label small"><i class="bi bi-bug me-1 text-warning"></i>Linked Jira Issue</label>
          <div class="input-group input-group-sm">
            <input type="text" name="jira_issue_key" class="form-control font-monospace"
                   value="<?= e($data['jira_issue_key'] ?? '') ?>"
                   placeholder="e.g. RD-123">
            <?php if (!empty($data['jira_issue_key']) && !empty($data['jira_issue_url'])): ?>
            <a href="<?= e($data['jira_issue_url']) ?>" target="_blank" class="btn btn-outline-warning btn-sm" title="Open in Jira">
              <i class="bi bi-box-arrow-up-right"></i>
            </a>
            <?php endif; ?>
          </div>
          <div class="form-text" style="font-size:.7rem">Leave empty to unlink. URL is built from the Jira base URL in Settings.</div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">Device Info</div>
      <div class="card-body">
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label small mb-0">Mower Serial Number</label>
            <div class="d-flex gap-1">
              <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2"
                      onclick="toggleMowerSearch()" title="Search mower by name or serial">
                <i class="bi bi-search me-1"></i><span style="font-size:.75rem">Search</span>
              </button>
              <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2"
                      data-bs-toggle="modal" data-bs-target="#qrModal">
                <i class="bi bi-qr-code-scan me-1"></i><span style="font-size:.75rem">Scan QR</span>
              </button>
            </div>
          </div>
          <div class="position-relative">
            <input type="text" name="mower_serial" id="mowerSerial" class="form-control form-control-sm"
                   value="<?= e($data['mower_serial'] ?? '') ?>"
                   autocomplete="off" oninput="mowerSerialInput(this)">
            <div id="mowerSerialResults" class="list-group position-absolute w-100 shadow"
                 style="z-index:200;display:none;top:100%;left:0"></div>
          </div>
          <!-- Mower search box (shown on demand) -->
          <div id="mowerSearchBox" style="display:none" class="mt-2">
            <div class="input-group input-group-sm">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input type="text" id="mowerSearchInput" class="form-control form-control-sm"
                     placeholder="Search by name or serial (wildcards ok, e.g. last digits)…"
                     autocomplete="off" oninput="mowerSearchQuery(this)">
            </div>
            <div id="mowerSearchResults" class="list-group mt-1" style="max-height:220px;overflow-y:auto"></div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small">Firmware Version</label>
          <input type="text" name="firmware_version" class="form-control form-control-sm" value="<?= e($data['firmware_version'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label small">App Version</label>
          <input type="text" name="app_version" class="form-control form-control-sm" value="<?= e($data['app_version'] ?? '') ?>">
        </div>
        <?php if (!empty($statuses)): ?>
        <div class="mb-3">
          <label class="form-label small">Robot Project Status</label>
          <select name="project_status_robot" class="form-select form-select-sm">
            <option value="">—</option>
            <?php foreach ($statuses as $s): ?>
            <option <?= ($data['project_status_robot'] ?? '') === $s ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">Environmental (optional)</div>
      <div class="card-body">
        <?php if (!empty($activeSession)): ?>
        <div class="alert alert-success py-2 px-3 small mb-3">
          <i class="bi bi-play-circle-fill me-1"></i>
          <strong>Active session:</strong> <?= e($activeSession['title']) ?>
          <?php if ($activeSession['firmware_version']): ?> &middot; <?= e($activeSession['firmware_version']) ?><?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="mb-2">
          <label class="form-label small">Test Area</label>
          <select name="test_area_id" class="form-select form-select-sm">
            <option value="">— None —</option>
            <?php foreach ($testAreas as $ta): ?>
            <option value="<?= $ta['id'] ?>" <?= ($data['test_area_id'] ?? '') == $ta['id'] ? 'selected' : '' ?>><?= e($ta['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label small">Temperature (°C)</label>
            <input type="number" name="temperature" class="form-control form-control-sm" step="0.1"
                   value="<?= e($data['temperature'] ?? ($activeSession['temperature'] ?? '')) ?>"
                   placeholder="e.g. 18.5">
          </div>
          <div class="col-6">
            <label class="form-label small">Weather</label>
            <select name="weather_condition" class="form-select form-select-sm">
              <option value="">—</option>
              <?php
              $selectedWeather = $data['weather_condition'] ?? ($activeSession['weather_condition'] ?? '');
              foreach (['Sunny','Partly Cloudy','Overcast','Light Rain','Heavy Rain','Foggy','Windy'] as $wc):
              ?>
              <option <?= $selectedWeather === $wc ? 'selected' : '' ?>><?= $wc ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <?php if (!empty($activeSession)): ?>
        <input type="hidden" name="session_id" value="<?= (int)$activeSession['id'] ?>">
        <?php endif; ?>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header border-secondary fw-semibold small">GPS Location (optional)</div>
      <div class="card-body">
        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label small">Lat</label>
            <input type="text" id="gps_lat" name="gps_lat" class="form-control form-control-sm" value="<?= e($data['gps_lat'] ?? '') ?>" placeholder="e.g. 48.1234">
          </div>
          <div class="col-6">
            <label class="form-label small">Lon</label>
            <input type="text" id="gps_lon" name="gps_lon" class="form-control form-control-sm" value="<?= e($data['gps_lon'] ?? '') ?>" placeholder="e.g. 11.5678">
          </div>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="captureGPS('gps_lat','gps_lon',this)">
          <i class="bi bi-geo-alt me-1"></i>Capture GPS
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Template Modal -->
<div class="modal fade" id="templateModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary py-2">
        <h6 class="modal-title mb-0"><i class="bi bi-layout-text-sidebar me-2"></i>Entry Templates</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3">
        <div id="templateList" class="mb-3"></div>
        <hr class="border-secondary">
        <p class="small text-muted mb-2">Save current form as new template:</p>
        <div class="input-group input-group-sm">
          <input type="text" id="templateNameInput" class="form-control" placeholder="Template name…">
          <button class="btn btn-outline-success" onclick="saveTemplate()">
            <i class="bi bi-plus-lg me-1"></i>Save
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- QR Scanner Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary py-2">
        <h6 class="modal-title mb-0"><i class="bi bi-qr-code-scan me-2"></i>Scan QR Code</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-3">
        <div style="position:relative;display:inline-block;width:100%">
          <video id="qrVideo" style="width:100%;border-radius:.5rem;background:#000" playsinline muted></video>
          <canvas id="qrCanvas" style="display:none"></canvas>
        </div>
        <p id="qrStatus" class="text-muted small mt-2 mb-0">Starting camera…</p>
      </div>
    </div>
  </div>
</div>

<script>
// Pending file list for the form
let _formFiles = []; // { file }

function _fmtBytes(n) {
  return n >= 1048576 ? (n/1048576).toFixed(1)+' MB' : Math.round(n/1024)+' KB';
}

function _renderFormFiles() {
  const list = document.getElementById('filePreview');
  if (!list) return;
  list.innerHTML = '';
  _formFiles.forEach(({ file }, i) => {
    const chip = document.createElement('div');
    chip.className = 'd-flex align-items-center gap-2 py-1 border-bottom border-secondary';
    chip.innerHTML = `<i class="bi bi-file-earmark text-muted"></i>
      <span class="text-truncate small flex-grow-1" style="max-width:240px">${file.name}</span>
      <span class="text-muted small text-nowrap">${_fmtBytes(file.size)}</span>
      <button type="button" class="btn-close" style="font-size:.7rem" aria-label="Remove" onclick="_removeFormFile(${i})"></button>`;
    list.appendChild(chip);
  });
}

function _removeFormFile(idx) {
  _formFiles.splice(idx, 1);
  _renderFormFiles();
}

// Two-step submit: save entry → upload files to known endpoint → navigate
(function() {
  const form = document.getElementById('entryForm');
  if (!form) return;
  form.addEventListener('submit', async function(e) {
    if (!_formFiles.length) return; // no files pending — browser handles normally
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…'; }
    const csrf = document.querySelector('input[name=_csrf]')?.value || '';
    try {
      // Step 1: save entry fields (no files) — PHP returns JSON {redirect}
      const fd = new FormData(form);
      fd.delete('files[]');
      const res  = await fetch(form.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      let data;
      try { data = await res.json(); } catch { throw new Error('Server returned HTTP ' + res.status); }
      if (data.error) throw new Error(data.error);
      if (!data.redirect) throw new Error('no redirect in response');

      // Step 2: upload files to /entries/{id}/upload
      const ufd = new FormData();
      ufd.append('_csrf', csrf);
      for (const { file } of _formFiles) ufd.append('files[]', file);
      const upRes  = await fetch(data.redirect + '/upload', { method: 'POST', body: ufd, headers: { 'X-CSRF-Token': csrf } });
      let upData;
      try { upData = await upRes.json(); } catch { upData = {}; }
      if (upData.error) {
        if (typeof showToast === 'function') showToast('Entry saved but upload failed: ' + upData.error, 'warning');
      } else if (upData.errors && upData.errors.length) {
        if (typeof showToast === 'function') showToast('Entry saved. Upload issues:<br>' + upData.errors.join('<br>'), 'warning');
      }

      // Step 3: navigate to entry
      window.location.href = data.redirect;
    } catch(err) {
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Save'; }
      if (typeof showToast === 'function') showToast('Save failed: ' + err.message, 'danger');
    }
  });
})();

// QR Scanner
let qrStream = null, qrFrame = null;

const qrModalEl = document.getElementById('qrModal');
if (qrModalEl) {
  qrModalEl.addEventListener('show.bs.modal', startQr);
  qrModalEl.addEventListener('hide.bs.modal', stopQr);
}

function startQr() {
  const status = document.getElementById('qrStatus');
  status.textContent = 'Starting camera…';
  navigator.mediaDevices?.getUserMedia({ video: { facingMode: { ideal: 'environment' } } })
    .then(stream => {
      qrStream = stream;
      const video = document.getElementById('qrVideo');
      video.srcObject = stream;
      video.play();
      if (window.jsQR) { tickQr(); return; }
      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js';
      s.onload = tickQr;
      document.head.appendChild(s);
    })
    .catch(() => { if(document.getElementById('qrStatus')) document.getElementById('qrStatus').textContent = 'Camera not available.'; });
}

function tickQr() {
  document.getElementById('qrStatus').textContent = 'Point at a ROBODOC QR code…';
  const video = document.getElementById('qrVideo');
  const canvas = document.getElementById('qrCanvas');
  const ctx = canvas.getContext('2d');
  function scan() {
    if (video.readyState === video.HAVE_ENOUGH_DATA) {
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      ctx.drawImage(video, 0, 0);
      const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
      const code = jsQR(img.data, img.width, img.height);
      if (code && code.data.startsWith('ROBODOC:')) { handleQr(code.data); return; }
    }
    qrFrame = requestAnimationFrame(scan);
  }
  qrFrame = requestAnimationFrame(scan);
}

function stopQr() {
  if (qrFrame) { cancelAnimationFrame(qrFrame); qrFrame = null; }
  if (qrStream) { qrStream.getTracks().forEach(t => t.stop()); qrStream = null; }
}

function handleQr(data) {
  stopQr();
  const params = {};
  data.replace('ROBODOC:', '').split('&').forEach(p => {
    const [k, v] = p.split('=');
    if (k) params[decodeURIComponent(k)] = decodeURIComponent(v || '');
  });
  if (params.project_id) {
    const sel = document.querySelector('select[name="project_id"]');
    if (sel) sel.value = params.project_id;
  }
  if (params.serial) {
    const inp = document.getElementById('mowerSerial');
    if (inp) inp.value = params.serial;
  }
  const toastLines = [];
  if (params.serial) toastLines.push('Serial: ' + params.serial);
  // Always look up firmware (and project) from inventory by serial — never trust QR for firmware
  if (params.serial) {
    fetch('<?= url('api/inventory/by-serial') ?>?serial=' + encodeURIComponent(params.serial))
      .then(r => r.json())
      .then(item => {
        if (item && item.firmware_version) {
          const fwInp = document.querySelector('input[name="firmware_version"]');
          if (fwInp && !fwInp.value) { fwInp.value = item.firmware_version; toastLines.push('Firmware: ' + item.firmware_version); }
        }
        if (item && item.project_id && !params.project_id) {
          const sel = document.querySelector('select[name="project_id"]');
          if (sel && !sel.value) sel.value = item.project_id;
        }
        if (typeof showToast === 'function') showToast('QR scanned<br>' + toastLines.join(' · '), 'success');
      })
      .catch(() => { if (typeof showToast === 'function') showToast('QR scanned: ' + (params.serial || ''), 'success'); });
  } else {
    if (typeof showToast === 'function') showToast('QR scanned<br>' + toastLines.join(' · '), 'success');
  }
  document.getElementById('qrStatus').textContent = '✓ QR code scanned!';
  setTimeout(() => bootstrap.Modal.getInstance(document.getElementById('qrModal'))?.hide(), 900);
}

// GPS capture
function captureGPS(latId, lonId, btn) {
  if (!navigator.geolocation) { if(typeof showToast==='function') showToast('Geolocation not supported', 'warning'); return; }
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Locating…';
  navigator.geolocation.getCurrentPosition(pos => {
    document.getElementById(latId).value = pos.coords.latitude.toFixed(6);
    document.getElementById(lonId).value = pos.coords.longitude.toFixed(6);
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-geo-alt-fill me-1 text-success"></i>GPS captured';
    if(typeof showToast==='function') showToast('GPS: ' + pos.coords.latitude.toFixed(5) + ', ' + pos.coords.longitude.toFixed(5), 'success');
  }, () => {
    btn.disabled = false;
    btn.innerHTML = orig;
    if(typeof showToast==='function') showToast('GPS not available', 'danger');
  }, { enableHighAccuracy: true, timeout: 15000 });
}

// Image compression — canvas-based resize + JPEG re-encode
const IMG_COMPRESS_THRESHOLD = 2 * 1024 * 1024; // 2 MB
const IMG_MAX_DIM = 2048;

async function maybeCompressImage(file) {
  if (!file.type.startsWith('image/') || file.size <= IMG_COMPRESS_THRESHOLD) return file;
  return new Promise(resolve => {
    const img = new Image();
    const url = URL.createObjectURL(file);
    img.onload = () => {
      URL.revokeObjectURL(url);
      let w = img.naturalWidth, h = img.naturalHeight;
      if (w > IMG_MAX_DIM || h > IMG_MAX_DIM) {
        const scale = Math.min(IMG_MAX_DIM / w, IMG_MAX_DIM / h);
        w = Math.round(w * scale); h = Math.round(h * scale);
      }
      const canvas = document.createElement('canvas');
      canvas.width = w; canvas.height = h;
      canvas.getContext('2d').drawImage(img, 0, 0, w, h);
      canvas.toBlob(blob => {
        if (!blob || blob.size >= file.size) { resolve(file); return; }
        const name = file.name.replace(/\.\w+$/, '') + '.jpg';
        resolve(new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() }));
      }, 'image/jpeg', 0.85);
    };
    img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
    img.src = url;
  });
}

// Video compression — plays video through canvas and re-encodes at lower bitrate
const VIDEO_COMPRESS_THRESHOLD = 80 * 1024 * 1024; // 80 MB
let _compressQueue = [];

async function maybeCompressVideo(file) {
  if (!file.type.startsWith('video/') || file.size <= VIDEO_COMPRESS_THRESHOLD) return file;
  return new Promise(resolve => {
    const sizeMB = (file.size / 1048576).toFixed(0);
    if (!confirm(`Video "${file.name}" is ${sizeMB} MB. Compress before uploading? (takes approx. 1× play time)`)) {
      resolve(file); return;
    }
    const video = document.createElement('video');
    const canvas = document.createElement('canvas');
    const url = URL.createObjectURL(file);
    video.src = url;
    video.muted = true;
    video.preload = 'metadata';
    if(typeof showToast==='function') showToast('Compressing video, please wait…', 'info');
    video.onloadedmetadata = () => {
      const scale = Math.min(1, 1280 / video.videoWidth);
      canvas.width  = Math.round(video.videoWidth  * scale);
      canvas.height = Math.round(video.videoHeight * scale);
      const ctx = canvas.getContext('2d');
      const mime = MediaRecorder.isTypeSupported('video/webm;codecs=vp9') ? 'video/webm;codecs=vp9' : 'video/webm';
      const recorder = new MediaRecorder(canvas.captureStream(30), { mimeType: mime, videoBitsPerSecond: 2_000_000 });
      const chunks = [];
      recorder.ondataavailable = e => { if (e.data.size) chunks.push(e.data); };
      recorder.onstop = () => {
        URL.revokeObjectURL(url);
        const blob = new Blob(chunks, { type: mime });
        const compressed = new File([blob], file.name.replace(/\.\w+$/, '.webm'), { type: mime });
        if(typeof showToast==='function') showToast(`Compressed: ${(blob.size/1048576).toFixed(1)} MB`, 'success');
        resolve(compressed);
      };
      video.ontimeupdate = () => ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
      video.onended = () => recorder.stop();
      recorder.start(1000);
      video.play();
    };
    video.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
  });
}

// File selection with optional image/video compression
['fileInput','cameraInput'].forEach(id => {
  const el = document.getElementById(id);
  if (!el) return;
  el.addEventListener('change', async function() {
    const existing = new Set(_formFiles.map(({file:f}) => f.name + '|' + f.size));
    for (const f of Array.from(this.files)) {
      let out = f;
      if (f.type.startsWith('image/')) out = await maybeCompressImage(f);
      else if (f.type.startsWith('video/')) out = await maybeCompressVideo(f);
      const key = out.name + '|' + out.size;
      if (!existing.has(key)) { _formFiles.push({ file: out }); existing.add(key); }
    }
    // Do NOT clear this.value — clearing invalidates File objects on iOS Safari
    _renderFormFiles();
  });
});

// Entry Templates
const _csrf = document.querySelector('input[name=_csrf]')?.value || '';

function loadTemplates() {
  fetch('<?= url('api/entry-templates') ?>')
    .then(r => r.json())
    .then(list => {
      const div = document.getElementById('templateList');
      if (!div) return;
      if (!list.length) {
        div.innerHTML = '<p class="text-muted small text-center">No templates yet.</p>';
        return;
      }
      div.innerHTML = list.map(t => `
        <div class="d-flex align-items-center gap-2 py-1 border-bottom border-secondary">
          ${t.type_color ? `<span class="badge" style="background:${t.type_color};font-size:.65rem">${t.type_name || ''}</span>` : ''}
          <span class="small flex-grow-1">${t.name}</span>
          ${t.project_name ? `<span class="text-muted" style="font-size:.7rem">${t.project_name}</span>` : ''}
          <button type="button" class="btn btn-link btn-sm p-0 text-primary" onclick="applyTemplate(${t.id})" title="Apply">
            <i class="bi bi-check-lg"></i>
          </button>
          <button type="button" class="btn btn-link btn-sm p-0 text-danger" onclick="deleteTemplate(${t.id}, this)" title="Delete">
            <i class="bi bi-trash"></i>
          </button>
        </div>`).join('');
    });
}

function applyTemplate(id) {
  fetch('<?= url('api/entry-templates') ?>')
    .then(r => r.json())
    .then(list => {
      const t = list.find(x => x.id == id);
      if (!t) return;
      if (t.entry_type_id) { const s = document.querySelector('select[name=entry_type_id]'); if (s) s.value = t.entry_type_id; }
      if (t.project_id)    { const s = document.querySelector('select[name=project_id]');    if (s) s.value = t.project_id; }
      if (t.error_category_id) { const s = document.querySelector('select[name=error_category_id]'); if (s) s.value = t.error_category_id; }
      if (t.description)    { const ta = document.getElementById('descriptionField'); if (ta && !ta.value) ta.value = t.description; }
      if (t.firmware_version) { const inp = document.querySelector('input[name=firmware_version]'); if (inp && !inp.value) inp.value = t.firmware_version; }
      if (t.app_version)    { const inp = document.querySelector('input[name=app_version]'); if (inp && !inp.value) inp.value = t.app_version; }
      bootstrap.Modal.getInstance(document.getElementById('templateModal'))?.hide();
      if (typeof showToast === 'function') showToast('Template "' + t.name + '" applied', 'success');
    });
}

function saveTemplate() {
  const name = document.getElementById('templateNameInput')?.value.trim();
  if (!name) return;
  const data = new URLSearchParams({
    _csrf:              _csrf,
    name:               name,
    entry_type_id:      document.querySelector('select[name=entry_type_id]')?.value || '',
    project_id:         document.querySelector('select[name=project_id]')?.value || '',
    error_category_id:  document.querySelector('select[name=error_category_id]')?.value || '',
    description:        document.getElementById('descriptionField')?.value || '',
    firmware_version:   document.querySelector('input[name=firmware_version]')?.value || '',
    app_version:        document.querySelector('input[name=app_version]')?.value || '',
  });
  fetch('<?= url('api/entry-templates') ?>', { method: 'POST', body: data })
    .then(r => r.json())
    .then(d => {
      if (d.error) { if (typeof showToast==='function') showToast(d.error, 'danger'); return; }
      document.getElementById('templateNameInput').value = '';
      loadTemplates();
      if (typeof showToast === 'function') showToast('Template saved', 'success');
    });
}

function deleteTemplate(id, btn) {
  btn.disabled = true;
  fetch('<?= url('api/entry-templates/') ?>' + id + '/delete', { method: 'POST', body: new URLSearchParams({ _csrf }) })
    .then(r => r.json())
    .then(() => loadTemplates());
}

document.getElementById('templateModal')?.addEventListener('show.bs.modal', loadTemplates);

// Voice dictation
let _dictRec = null;
function startDictation() {
  const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
  const icon = document.getElementById('dictIcon');
  const btn  = document.getElementById('dictBtn');
  if (!SpeechRec) { if (typeof showToast === 'function') showToast('Speech recognition not supported in this browser', 'warning'); return; }
  if (_dictRec) { _dictRec.stop(); return; }
  const ta = document.getElementById('descriptionField');
  _dictRec = new SpeechRec();
  _dictRec.lang = navigator.language || 'de-DE';
  _dictRec.continuous = true;
  _dictRec.interimResults = true;
  let base = ta.value;
  _dictRec.onstart = () => { icon.className = 'bi bi-mic-fill text-danger'; btn.classList.add('active'); };
  _dictRec.onresult = e => {
    let interim = '', final = '';
    for (let i = e.resultIndex; i < e.results.length; i++) {
      if (e.results[i].isFinal) final += e.results[i][0].transcript;
      else interim += e.results[i][0].transcript;
    }
    if (final) { base += (base && !base.endsWith(' ') ? ' ' : '') + final; }
    ta.value = base + interim;
  };
  _dictRec.onend = () => {
    _dictRec = null;
    icon.className = 'bi bi-mic';
    btn.classList.remove('active');
    ta.value = base.trim();
  };
  _dictRec.start();
}

// ── Mower serial search ───────────────────────────────────────────────────────
let _mowerSearchTimer = null;

function toggleMowerSearch() {
  const box = document.getElementById('mowerSearchBox');
  const isHidden = box.style.display === 'none';
  box.style.display = isHidden ? '' : 'none';
  if (isHidden) {
    const inp = document.getElementById('mowerSearchInput');
    inp.focus();
    // Pre-fill search with current serial value
    const cur = document.getElementById('mowerSerial').value.trim();
    if (cur) { inp.value = cur; mowerSearchQuery(inp); }
  }
}

function mowerSerialInput(inp) {
  const q = inp.value.trim();
  const res = document.getElementById('mowerSerialResults');
  clearTimeout(_mowerSearchTimer);
  if (q.length < 2) { res.style.display = 'none'; return; }
  _mowerSearchTimer = setTimeout(() => {
    fetch('<?= url('api/inventory/search') ?>?q=' + encodeURIComponent(q))
      .then(r => r.json())
      .then(items => renderMowerResults(items, res, false));
  }, 250);
}

function mowerSearchQuery(inp) {
  const q = inp.value.trim();
  const res = document.getElementById('mowerSearchResults');
  clearTimeout(_mowerSearchTimer);
  if (q.length < 1) { res.innerHTML = ''; return; }
  _mowerSearchTimer = setTimeout(() => {
    fetch('<?= url('api/inventory/search') ?>?q=' + encodeURIComponent(q))
      .then(r => r.json())
      .then(items => renderMowerResults(items, res, true));
  }, 250);
}

function renderMowerResults(items, container, extended) {
  if (!items.length) {
    container.innerHTML = '<div class="list-group-item list-group-item-dark small text-muted py-2">No mowers found</div>';
    container.style.display = '';
    return;
  }
  container.innerHTML = items.map((_, i) => `<button type="button" data-idx="${i}" class="list-group-item list-group-item-action list-group-item-dark py-2 px-3 small"></button>`).join('');
  container.querySelectorAll('button').forEach((btn, i) => {
    const item = items[i];
    btn.innerHTML = `<div class="fw-semibold">${item.serial_number || '(no serial)'} <span class="text-muted fw-normal">– ${item.name}</span></div>`
      + (extended ? `<div class="text-muted" style="font-size:.75rem">${item.firmware_version ? 'FW: ' + item.firmware_version : ''} ${item.status ? '· ' + item.status : ''} ${item.location ? '· ' + item.location : ''}</div>` : '');
    btn.addEventListener('click', () => selectMower(item));
  });
  container.style.display = '';
}

function selectMower(item) {
  // Fill serial
  const serialInp = document.getElementById('mowerSerial');
  if (serialInp) serialInp.value = item.serial_number || '';

  // Fill firmware if empty
  if (item.firmware_version) {
    const fwInp = document.querySelector('input[name="firmware_version"]');
    if (fwInp && !fwInp.value) fwInp.value = item.firmware_version;
  }

  // Close dropdowns
  document.getElementById('mowerSerialResults').style.display = 'none';
  document.getElementById('mowerSearchBox').style.display = 'none';
  document.getElementById('mowerSearchInput').value = '';
  document.getElementById('mowerSearchResults').innerHTML = '';

  if (typeof showToast === 'function') {
    const parts = ['Serial: ' + (item.serial_number || '–')];
    if (item.firmware_version) parts.push('FW: ' + item.firmware_version);
    showToast(parts.join(' · '), 'success');
  }
}

// Close mower serial dropdown when clicking outside
document.addEventListener('click', e => {
  if (!e.target.closest('#mowerSerial, #mowerSerialResults')) {
    document.getElementById('mowerSerialResults').style.display = 'none';
  }
});

// Jira key / auto-create mutual exclusion (create mode only)
function onJiraKeyNew(inp) {
  const cb = document.getElementById('jiraAutoCreate');
  if (!cb) return;
  const hasKey = inp.value.trim().length > 0;
  cb.disabled = hasKey;
  if (hasKey) cb.checked = false;
}
function onJiraAutoCreate(cb) {
  const inp = document.getElementById('jiraKeyNew');
  if (!inp) return;
  if (cb.checked) { inp.value = ''; inp.disabled = true; }
  else            { inp.disabled = false; }
}
</script>
