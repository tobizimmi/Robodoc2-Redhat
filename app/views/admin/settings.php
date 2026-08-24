<div class="d-flex align-items-center gap-2 mb-4">
  <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('admin') ?>"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0">Settings</h5>
</div>
<div class="card" style="max-width:700px"><div class="card-body p-4">
  <form method="POST" action="<?= url('admin/settings') ?>">
    <?= csrfField() ?>
    <div class="mb-3"><label class="form-label">App Name</label>
      <input type="text" name="app_name" class="form-control" value="<?= e($s['app_name'] ?? 'RoboDoc') ?>"></div>
    <div class="mb-3"><label class="form-label">App URL</label>
      <input type="url" name="app_url" class="form-control" value="<?= e($s['app_url'] ?? '') ?>" placeholder="https://zimmimail.de">
      <div class="form-text">Used in email links (password reset, notifications). No trailing slash.</div></div>
    <div class="mb-3"><label class="form-label">Jira URL</label>
      <input type="url" name="jira_url" class="form-control" value="<?= e($s['jira_url'] ?? '') ?>" placeholder="https://yourcompany.atlassian.net"></div>
    <div class="mb-3"><label class="form-label">Jira Default Project</label>
      <input type="text" name="jira_default_project" class="form-control" value="<?= e($s['jira_default_project'] ?? '') ?>" placeholder="e.g. RD"></div>
    <div class="mb-3"><label class="form-label">Xray Project Key</label>
      <input type="text" name="xray_project_key" class="form-control" value="<?= e($s['xray_project_key'] ?? 'BRSQ') ?>" placeholder="e.g. BRSQ">
      <div class="form-text">Jira project key for Xray Test Management (may differ from the main Jira project).</div></div>
    <div class="mb-3 form-check">
      <input type="checkbox" name="xray_sync_enabled" class="form-check-input" id="xraySync" value="1"
             <?= ($s['xray_sync_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
      <label class="form-check-label" for="xraySync">Enable Xray bidirectional sync</label>
    </div>
    <div class="mb-3"><label class="form-label">Jira Test Request Project</label>
      <input type="text" name="jira_test_request_project" class="form-control" value="<?= e($s['jira_test_request_project'] ?? '') ?>" placeholder="e.g. TR">
      <div class="form-text">Project key for Test Requests (issue type: Request).</div></div>
    <div class="mb-3"><label class="form-label">Confluence URL</label>
      <input type="url" name="confluence_url" class="form-control" value="<?= e($s['confluence_url'] ?? '') ?>" placeholder="https://yourcompany.atlassian.net"></div>
    <div class="mb-3"><label class="form-label">Confluence Default Space</label>
      <input type="text" name="confluence_default_space" class="form-control" value="<?= e($s['confluence_default_space'] ?? '') ?>" placeholder="e.g. RD"></div>
    <hr class="border-secondary my-4">
    <h6 class="text-muted mb-3">SharePoint Integration (Microsoft Graph API)</h6>
    <div class="mb-3"><label class="form-label">Tenant ID</label>
      <input type="text" name="sharepoint_tenant_id" class="form-control" value="<?= e($s['sharepoint_tenant_id'] ?? '') ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"></div>
    <div class="mb-3"><label class="form-label">Client ID (App ID)</label>
      <input type="text" name="sharepoint_client_id" class="form-control" value="<?= e($s['sharepoint_client_id'] ?? '') ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"></div>
    <div class="mb-3"><label class="form-label">Client Secret</label>
      <input type="password" name="sharepoint_client_secret" class="form-control" value="<?= e($s['sharepoint_client_secret'] ?? '') ?>">
      <div class="form-text">Register an Azure AD app with Files.ReadWrite.All permission (application type).</div></div>
    <div class="mb-3"><label class="form-label">SharePoint Site URL</label>
      <input type="url" name="sharepoint_site_url" class="form-control" value="<?= e($s['sharepoint_site_url'] ?? '') ?>" placeholder="https://company.sharepoint.com/sites/TeamName"></div>
    <hr class="border-secondary my-4">
    <div class="mb-3"><label class="form-label">Project Status List (JSON array)</label>
      <input type="text" name="project_statuses" class="form-control" value='<?= e($s['project_statuses'] ?? '["Prototype","EP0","EP1","EP2","MP","SOP"]') ?>'>
      <small class="text-muted">Example: ["Prototype","EP0","EP1"]</small></div>
    <div class="mb-3"><label class="form-label">Timezone</label>
      <input type="text" name="timezone" class="form-control" value="<?= e($s['timezone'] ?? 'Europe/Berlin') ?>"></div>
    <div class="mb-3 form-check">
      <input type="checkbox" name="allow_registration" class="form-check-input" id="reg" value="1"
             <?= ($s['allow_registration'] ?? '0') === '1' ? 'checked' : '' ?>>
      <label class="form-check-label" for="reg">Allow self-registration</label>
    </div>
    <hr class="border-secondary my-4">
    <h6 class="text-muted mb-3">Email / SMTP</h6>
    <div class="row g-3 mb-3">
      <div class="col-sm-8"><label class="form-label">SMTP Host</label>
        <input type="text" name="smtp_host" class="form-control" value="<?= e($s['smtp_host'] ?? '') ?>" placeholder="mail.zimmimail.de"></div>
      <div class="col-sm-4"><label class="form-label">Port</label>
        <input type="number" name="smtp_port" class="form-control" value="<?= e($s['smtp_port'] ?? '587') ?>" placeholder="587"></div>
    </div>
    <div class="mb-3"><label class="form-label">SMTP Username</label>
      <input type="text" name="smtp_user" class="form-control" value="<?= e($s['smtp_user'] ?? '') ?>" placeholder="noreply@zimmimail.de"></div>
    <div class="mb-3"><label class="form-label">SMTP Password</label>
      <input type="password" name="smtp_pass" class="form-control" value="<?= e($s['smtp_pass'] ?? '') ?>">
      <div class="form-text">Leave empty to keep current value if already set.</div></div>
    <div class="mb-3"><label class="form-label">From Address</label>
      <input type="email" name="smtp_from" class="form-control" value="<?= e($s['smtp_from'] ?? '') ?>" placeholder="noreply@zimmimail.de"></div>
    <hr class="border-secondary my-4">
    <h6 class="fw-semibold mb-1"><i class="bi bi-funnel me-2"></i>Entry Type Pre-Filter</h6>
    <p class="text-muted small mb-3">
      Define which entry types appear in each list. <strong>Empty = all types shown.</strong>
    </p>
    <?php
      $allTypes   = Database::fetchAll('SELECT id, name, color FROM entry_types ORDER BY sort_order, name');
      $entriesIds = array_filter(array_map('intval', explode(',', $s['entries_type_ids']       ?? '')));
      $testResIds = array_filter(array_map('intval', explode(',', $s['test_results_type_ids']  ?? '')));
      $otherIds   = array_filter(array_map('intval', explode(',', $s['other_entries_type_ids'] ?? '')));
      $lists = [
        ['key'=>'entries_type_ids',      'label'=>'Entries',           'icon'=>'bi-journal-text',     'ids'=>$entriesIds, 'id_prefix'=>'ete'],
        ['key'=>'test_results_type_ids', 'label'=>'Test Results',      'icon'=>'bi-clipboard2-check', 'ids'=>$testResIds, 'id_prefix'=>'etr'],
        ['key'=>'other_entries_type_ids','label'=>'Sonstige Einträge', 'icon'=>'bi-collection',       'ids'=>$otherIds,   'id_prefix'=>'eto'],
      ];
    ?>
    <div class="row g-3 mb-4">
      <?php foreach ($lists as $list): ?>
      <div class="col-12 col-md-4">
        <div class="card h-100" style="border-color:var(--bs-border-color)">
          <div class="card-header py-2 px-3 d-flex align-items-center gap-2">
            <i class="bi <?= $list['icon'] ?> text-primary"></i>
            <span class="fw-semibold small"><?= $list['label'] ?></span>
            <?php if (!empty($list['ids'])): ?>
            <span class="badge bg-primary ms-auto"><?= count($list['ids']) ?></span>
            <?php else: ?>
            <span class="badge bg-secondary ms-auto text-muted" style="font-weight:400">alle</span>
            <?php endif; ?>
          </div>
          <div class="card-body p-2" style="max-height:220px;overflow-y:auto">
            <?php foreach ($allTypes as $t): ?>
            <div class="form-check px-3 py-1">
              <input class="form-check-input" type="checkbox"
                     name="<?= $list['key'] ?>[]"
                     value="<?= $t['id'] ?>"
                     id="<?= $list['id_prefix'].$t['id'] ?>"
                     <?= in_array((int)$t['id'], $list['ids']) ? 'checked' : '' ?>>
              <label class="form-check-label d-flex align-items-center gap-2" for="<?= $list['id_prefix'].$t['id'] ?>">
                <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:<?= e($t['color']??'#888') ?>;flex-shrink:0"></span>
                <span class="small"><?= e($t['name']) ?></span>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="card-footer py-1 px-3">
            <small class="text-muted">Leer = alle Typen werden angezeigt</small>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <hr class="border-secondary my-4">
    <h6 class="fw-semibold mb-1"><i class="bi bi-archive me-2 text-warning"></i>Automatische Archivierung nach Status</h6>
    <p class="text-muted small mb-3">
      Einträge mit einem der hier ausgewählten Stati verschwinden aus der normalen Liste und erscheinen
      nur noch unter dem Tab <strong>„Archiviert"</strong>.
    </p>
    <?php $archivedStatuses = array_filter(array_map('trim', explode(',', $s['archived_statuses'] ?? ''))); ?>
    <div class="d-flex flex-wrap gap-2 mb-4">
      <?php foreach (entryStatuses() as $stKey => $stLabel): ?>
      <div class="form-check form-check-inline mb-0">
        <input type="checkbox" class="form-check-input" name="archived_statuses[]" value="<?= e($stKey) ?>"
               id="arst_<?= e($stKey) ?>" <?= in_array($stKey, $archivedStatuses, true) ? 'checked' : '' ?>>
        <label class="form-check-label" for="arst_<?= e($stKey) ?>"><?= e($stLabel) ?></label>
      </div>
      <?php endforeach; ?>
    </div>
    <hr class="border-secondary my-4">
    <h6 class="fw-semibold mb-1"><i class="bi bi-clipboard2-check me-2 text-info"></i>Test Result Entries</h6>
    <p class="text-muted small mb-3">Configure which entry types trigger the extended Test Result form, and define the available outcome values.</p>
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label fw-semibold">Test Result Entry Types</label>
        <p class="text-muted small mb-2">Entries with these types will automatically show the extended Test Result form with sub-results, test cycle linking, etc.</p>
        <?php
          $allTypes2  = $allTypes ?? Database::fetchAll('SELECT id, name, color FROM entry_types ORDER BY sort_order, name');
          $trTypeIds  = array_filter(array_map('intval', explode(',', $s['test_result_entry_type_ids'] ?? '')));
        ?>
        <div class="border rounded p-3" style="max-height:200px;overflow-y:auto">
          <?php foreach ($allTypes2 as $t): ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="test_result_entry_type_ids[]"
                   value="<?= $t['id'] ?>" id="tret<?= $t['id'] ?>"
                   <?= in_array((int)$t['id'], $trTypeIds) ? 'checked' : '' ?>>
            <label class="form-check-label" for="tret<?= $t['id'] ?>">
              <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= e($t['color']??'#888') ?>;margin-right:5px;vertical-align:middle"></span>
              <?= e($t['name']) ?>
            </label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-md-6">
        <label class="form-label fw-semibold">Test Result Outcome Values</label>
        <p class="text-muted small mb-2">Comma-separated list of outcome values available in the sub-result form.</p>
        <input type="text" name="test_result_outcomes" class="form-control"
               value="<?= e($s['test_result_outcomes'] ?? 'Passed,Failed,Blocked,Partial,Not Run') ?>"
               placeholder="Passed,Failed,Blocked,Partial,Not Run">
        <div class="form-text">Example: Passed, Failed, Blocked, Partial, Not Run</div>
        <div class="mt-3">
          <label class="form-label small fw-semibold">Preview:</label>
          <div class="d-flex flex-wrap gap-1" id="outcomesPreview">
            <?php foreach (array_filter(array_map('trim', explode(',', $s['test_result_outcomes'] ?? 'Passed,Failed,Blocked,Partial,Not Run'))) as $ov): ?>
            <span class="badge" style="background:#0ea5e9"><?= e($ov) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <script>
    document.querySelector('[name="test_result_outcomes"]')?.addEventListener('input', function() {
      var vals = this.value.split(',').map(s=>s.trim()).filter(Boolean);
      document.getElementById('outcomesPreview').innerHTML = vals.map(v=>
        '<span class="badge" style="background:#0ea5e9">'+v+'</span>'
      ).join(' ');
    });
    </script>
    <button type="submit" class="btn btn-primary">Save</button>
  </form>
</div></div>
