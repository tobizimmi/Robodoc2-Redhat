<div class="d-flex align-items-center gap-2 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('admin') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Zentao Settings</h5>
</div>

<form method="POST" action="<?= url('admin/zentao') ?>">
  <?= csrfField() ?>

  <!-- Connection -->
  <div class="card mb-4" style="max-width:800px">
    <div class="card-header border-secondary fw-semibold small"><i class="bi bi-plug me-1"></i>Connection</div>
    <div class="card-body p-4">
      <div class="mb-3">
        <label class="form-label">Zentao Base URL</label>
        <input type="url" name="zentao_url" class="form-control" value="<?= e($s['zentao_url'] ?? '') ?>" placeholder="https://zentao.yourcompany.com">
        <div class="form-text">No trailing slash. Example: https://zentao.example.com</div>
      </div>
      <div class="mb-3">
        <label class="form-label">API Token</label>
        <input type="password" name="zentao_token" class="form-control" value="<?= e($s['zentao_token'] ?? '') ?>">
        <div class="form-text">Static API token. Sent as <code>Token: {token}</code> header on every request.</div>
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Default Product ID</label>
          <input type="number" name="zentao_default_product" class="form-control" value="<?= e($s['zentao_default_product'] ?? '') ?>" placeholder="1">
          <div class="form-text">Numeric Zentao product ID. Find it in the Zentao URL when viewing a product.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Default Bug Type</label>
          <select name="zentao_default_type" class="form-select">
            <?php foreach (['codeerror'=>'Code Error','config'=>'Config','install'=>'Install','security'=>'Security','performance'=>'Performance','standard'=>'Standard','automation'=>'Automation','test'=>'Test','others'=>'Others'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= ($s['zentao_default_type'] ?? 'codeerror') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="mt-3">
        <label class="form-label">Default Priority (1=Highest … 4=Low)</label>
        <select name="zentao_default_pri" class="form-select" style="max-width:200px">
          <?php foreach ([1=>'1 — Highest',2=>'2 — High',3=>'3 — Medium',4=>'4 — Low'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= (int)($s['zentao_default_pri'] ?? 3) === $v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <!-- Templates -->
  <div class="card mb-4" style="max-width:800px">
    <div class="card-header border-secondary fw-semibold small"><i class="bi bi-file-text me-1"></i>Bug Templates</div>
    <div class="card-body p-4">
      <div class="mb-3">
        <label class="form-label">Title Template</label>
        <input type="text" name="zentao_title_template" class="form-control font-monospace"
               value="<?= e($s['zentao_title_template'] ?? '{{title}}') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Description / Steps Template</label>
        <textarea name="zentao_desc_template" class="form-control font-monospace" rows="10"><?= e($s['zentao_desc_template'] ?? "*Type:* {{type}}\n*Category:* {{category}}\n*Project:* {{project}}\n*Serial:* {{serial}}\n*Firmware:* {{firmware}}\n*App Version:* {{app_version}}\n*Environment:* {{environment}}\n*Date:* {{date}} {{time}}\n*Creator:* {{creator}}\n*Jira:* {{jira_key}}\n\n{{description}}") ?></textarea>
        <div class="form-text mt-2">
          Available variables:
          <code>{{title}}</code> <code>{{description}}</code> <code>{{type}}</code> <code>{{category}}</code>
          <code>{{serial}}</code> <code>{{firmware}}</code> <code>{{app_version}}</code>
          <code>{{project}}</code> <code>{{project_status}}</code> <code>{{environment}}</code>
          <code>{{test_area}}</code> <code>{{date}}</code> <code>{{time}}</code>
          <code>{{creator}}</code> <code>{{jira_key}}</code> <code>{{sharepoint}}</code> <code>{{temperature}}</code> <code>{{weather}}</code>
        </div>
      </div>
    </div>
  </div>

  <!-- Status Mapping -->
  <div class="card mb-4" style="max-width:800px">
    <div class="card-header border-secondary fw-semibold small"><i class="bi bi-arrow-left-right me-1"></i>Status Mapping (Zentao → RoboDoc)</div>
    <div class="card-body p-4">
      <div class="form-text mb-3">When syncing Zentao status into RoboDoc, map it to the corresponding RoboDoc status.</div>
      <div class="form-text mb-3 text-warning-emphasis">
        <i class="bi bi-info-circle me-1"></i>
        Du kannst pro Zentao-Status <strong>mehrere RoboDoc-Stati</strong> auswählen. Ist der Eintrag in einem der ausgewählten Stati, wird keine Änderung gemeldet.
        Sind mehrere Stati ausgewählt, kann beim Akzeptieren der Änderungen (Review-Seite) einer davon ausgewählt werden — der <strong>erste</strong> ist dabei vorausgewählt.
      </div>
      <?php
      $rawMap         = json_decode($s['zentao_status_to_local'] ?? '{}', true) ?: [];
      // Normalize: old format had strings, new format has arrays
      $statusToLocal  = [];
      foreach (['active','resolved','closed'] as $zs) {
          $v = $rawMap[$zs] ?? null;
          $statusToLocal[$zs] = $v === null ? [] : (is_array($v) ? $v : [$v]);
      }
      if (empty($statusToLocal['active']))   $statusToLocal['active']   = ['internal'];
      if (empty($statusToLocal['resolved'])) $statusToLocal['resolved'] = ['ready_for_test'];
      if (empty($statusToLocal['closed']))   $statusToLocal['closed']   = ['finished'];
      $zentaoStatuses = ['active', 'resolved', 'closed'];
      $localStatuses  = entryStatuses();
      ?>
      <table class="table table-sm small align-middle mb-0">
        <thead class="table-dark"><tr><th style="width:120px">Zentao Status</th><th>Erlaubte RoboDoc Stati (erster = Import-Standard)</th></tr></thead>
        <tbody>
          <?php foreach ($zentaoStatuses as $zs): ?>
          <tr>
            <td class="fw-semibold align-top pt-2"><?= $zs ?></td>
            <td>
              <div class="d-flex flex-wrap gap-2">
                <?php foreach ($localStatuses as $lv => $ll): ?>
                <div class="form-check form-check-inline mb-0">
                  <input type="checkbox" class="form-check-input"
                         name="zentao_status_to_local[<?= $zs ?>][]"
                         value="<?= $lv ?>"
                         id="zsm_<?= $zs ?>_<?= $lv ?>"
                         <?= in_array($lv, $statusToLocal[$zs], true) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="zsm_<?= $zs ?>_<?= $lv ?>"><?= e($ll) ?></label>
                </div>
                <?php endforeach; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Priority + Severity mapping -->
  <div class="card mb-4" style="max-width:800px">
    <div class="card-header border-secondary fw-semibold small"><i class="bi bi-flag me-1"></i>Priority &amp; Severity Mapping (Entry Priority → Zentao)</div>
    <div class="card-body p-4">
      <div class="form-text mb-3">Map each RoboDoc entry priority to a Zentao <strong>priority</strong> (1=highest, 4=low) and <strong>severity</strong> (1=critical, 4=minor).</div>
      <?php
      $priMap      = json_decode($s['zentao_priority_map'] ?? '{}', true) ?: [];
      $priLevels   = ['Low','Medium','High','Highest','Blocker'];
      $priDefaults = ['Low'=>['pri'=>4,'severity'=>4],'Medium'=>['pri'=>3,'severity'=>3],'High'=>['pri'=>2,'severity'=>2],'Highest'=>['pri'=>1,'severity'=>2],'Blocker'=>['pri'=>1,'severity'=>1]];
      $priOptions  = [1=>'1 — Highest',2=>'2 — High',3=>'3 — Medium',4=>'4 — Low'];
      ?>
      <table class="table table-sm small align-middle mb-0" style="max-width:620px">
        <thead class="table-dark">
          <tr><th>Entry Priority</th><th>Zentao Priority (1–4)</th><th>Zentao Severity (1–4)</th></tr>
        </thead>
        <tbody>
          <?php foreach ($priLevels as $pl):
            $savedPri = $priMap[$pl]['pri']      ?? $priDefaults[$pl]['pri'];
            $savedSev = $priMap[$pl]['severity'] ?? $priDefaults[$pl]['severity'];
          ?>
          <tr>
            <td class="fw-semibold"><?= $pl ?></td>
            <td>
              <select name="zentao_priority_map[<?= $pl ?>][pri]" class="form-select form-select-sm">
                <?php foreach ($priOptions as $v=>$l): ?>
                <option value="<?= $v ?>" <?= (int)$savedPri === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td>
              <select name="zentao_priority_map[<?= $pl ?>][severity]" class="form-select form-select-sm">
                <?php foreach ([1=>'1 — Critical',2=>'2 — Major',3=>'3 — Normal',4=>'4 — Minor'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= (int)$savedSev === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Sync configuration -->
  <div class="card mb-4" style="max-width:800px">
    <div class="card-header border-secondary fw-semibold small">
      <i class="bi bi-arrow-repeat me-1"></i>Sync Configuration
    </div>
    <div class="card-body p-4">
      <?php
      $zentaoQuick = json_decode($s['zentao_quick_sync_fields'] ?? '["status","priority"]', true) ?: ['status','priority'];
      $zentaoFull  = json_decode($s['zentao_full_sync_fields']  ?? '["status","priority","description","comments"]', true) ?: ['status','priority','description','comments'];
      $syncFields  = ['status'=>'Status','priority'=>'Priority','description'=>'Description / Steps','comments'=>'Comments / History'];
      ?>
      <div class="row g-4">
        <div class="col-md-6">
          <div class="fw-semibold small mb-2"><i class="bi bi-lightning text-warning me-1"></i>Quick Sync checks</div>
          <?php foreach ($syncFields as $sf => $sl): ?>
          <div class="form-check">
            <input type="checkbox" class="form-check-input" name="zentao_quick_sync_fields[]"
                   value="<?= $sf ?>" id="zqs_<?= $sf ?>" <?= in_array($sf,$zentaoQuick)?'checked':'' ?>>
            <label class="form-check-label small" for="zqs_<?= $sf ?>"><?= $sl ?></label>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="col-md-6">
          <div class="fw-semibold small mb-2"><i class="bi bi-arrow-repeat text-info me-1"></i>Full Sync checks</div>
          <?php foreach ($syncFields as $sf => $sl): ?>
          <div class="form-check">
            <input type="checkbox" class="form-check-input" name="zentao_full_sync_fields[]"
                   value="<?= $sf ?>" id="zfs_<?= $sf ?>" <?= in_array($sf,$zentaoFull)?'checked':'' ?>>
            <label class="form-check-label small" for="zfs_<?= $sf ?>"><?= $sl ?></label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div style="max-width:800px">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Settings</button>
  </div>
</form>
