<?php
$bugsByResult = $bugsByResult ?? [];
$allUsers = $allUsers ?? [];
?>
<div class="d-flex align-items-start justify-content-between mb-4">
  <div class="d-flex align-items-center gap-2">
    <a href="#" class="btn btn-outline-secondary btn-sm rd-back" data-fallback="<?= url('test-runs') ?>"><i class="bi bi-arrow-left"></i></a>
    <div>
      <h5 class="mb-0 fw-bold"><?= e($run['name']) ?></h5>
      <small class="text-muted"><?= e($run['plan_name']) ?> &middot; <?= e($run['project_name']) ?></small>
    </div>
  </div>
  <?php if (!empty($run['cycle_id_val']) || !empty($run['plan_xray_key'])): ?>
<button class="btn btn-outline-warning btn-sm" onclick="synapseSync('<?= e(Auth::csrfToken()) ?>')">
  <i class="bi bi-arrow-repeat me-1"></i>Sync
</button>
<?php endif; ?>
<a href="<?= url('test-runs/' . $run['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i></a>
</div>

<?php if ($activeSession): ?>
<div class="alert alert-info py-2 small mb-3">
  <i class="bi bi-camera-video me-1"></i>Active Session: <strong><?= e($activeSession['title']) ?></strong> ? new test entries will be linked to it.
</div>
<?php endif; ?>

<!-- Stats -->
<div class="row g-3 mb-4">
  <?php
  $pct = $stats['total'] ? round($stats['passed'] / $stats['total'] * 100) : 0;
  foreach ([['Gesamt','total','secondary'],['Bestanden','passed','success'],['Fehlgeschlagen','failed','danger'],['Offen','pending','secondary']] as [$l,$k,$c]):
  ?>
  <div class="col-6 col-md-3">
    <div class="card stat-card" style="border-left-color:var(--bs-<?= $c ?>)">
      <div class="card-body p-3">
        <div class="text-muted small"><?= $l ?></div>
        <div class="stat-number"><?= $stats[$k] ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Progress bar -->
<div class="mb-4">
  <div class="d-flex justify-content-between mb-1 small text-muted">
    <span>Fortschritt</span><span><?= $pct ?>%</span>
  </div>
  <div class="progress" style="height:12px">
    <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
  </div>
</div>

<!-- Add missing test cases -->
<?php if ($missingItems): ?>
<div class="card mb-3 border-secondary">
  <div class="card-header border-secondary d-flex align-items-center gap-2 py-2">
    <span class="fw-semibold small"><i class="bi bi-plus-circle me-1 text-info"></i>Add Test Cases to this Run</span>
    <span class="badge bg-secondary"><?= count($missingItems) ?> not yet included</span>
    <button class="btn btn-outline-info btn-sm ms-auto py-0 px-2" data-bs-toggle="collapse" data-bs-target="#addItemsPanel">Show</button>
  </div>
  <div class="collapse" id="addItemsPanel">
    <form method="POST" action="<?= url('test-runs/' . $run['id'] . '/add-items') ?>">
      <?= csrfField() ?>
      <div class="list-group list-group-flush">
        <?php foreach ($missingItems as $mi): ?>
        <label class="list-group-item list-group-item-action bg-transparent border-secondary py-2 px-3" style="cursor:pointer">
          <div class="d-flex align-items-center gap-3">
            <input type="checkbox" class="form-check-input flex-shrink-0" name="item_ids[]" value="<?= $mi['id'] ?>" checked>
            <div>
              <div class="small fw-semibold"><?= e($mi['title']) ?></div>
              <?php if ($mi['description']): ?>
              <div class="text-muted small"><?= e($mi['description']) ?></div>
              <?php endif; ?>
            </div>
            <span class="ms-auto badge bg-secondary"><?= e($mi['priority']) ?></span>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
      <div class="card-body py-2">
        <button class="btn btn-info btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Selected</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Results -->
<div class="card">
  <div class="card-header border-secondary fw-semibold small">Testergebnisse</div>
  <div class="list-group list-group-flush">
    <?php foreach ($results as $r): ?>
    <?php $sc = ['pending'=>'secondary','passed'=>'success','failed'=>'danger','skipped'=>'secondary','blocked'=>'warning']; ?>
    <div class="list-group-item bg-transparent border-secondary py-2 px-3">
      <div class="d-flex align-items-start justify-content-between">
        <div class="flex-grow-1 me-3">
          <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-<?= $sc[$r['status']] ?? 'secondary' ?>"><?= e($r['status']) ?></span>
            <span class="priority-<?= $r['item_priority'] ?>"><i class="bi bi-flag-fill small"></i></span>
            <span class="fw-semibold small"><?= e($r['item_title']) ?></span>
          </div>
          <?php if ($r['expected_result']): ?>
          <div class="text-muted small">Erwartet: <?= e($r['expected_result']) ?></div>
          <?php endif; ?>
          <?php if ($r['notes']): ?>
          <div class="text-info small mt-1">Notiz: <?= e($r['notes']) ?></div>
          <?php endif; ?>
          <!-- Linked test request -->
          <?php if ($r['test_request_id']): ?>
          <div class="mt-1 small">
            <i class="bi bi-clipboard-check text-primary me-1"></i>
            <a href="<?= url('test-requests/' . $r['test_request_id']) ?>" class="text-primary text-decoration-none">
              <?= e($r['req_summary']) ?>
            </a>
            <?php if ($r['req_jira_key']): ?>
            <a href="<?= e($r['req_jira_url']) ?>" target="_blank" class="badge bg-primary ms-1 text-decoration-none"><?= e($r['req_jira_key']) ?></a>
            <?php endif; ?>
            <span class="badge bg-secondary ms-1"><?= e($r['req_status']) ?></span>
          </div>
          <?php endif; ?>
          <!-- Test entries for this result -->
          <?php $entries = $testEntries[$r['id']] ?? []; ?>
          <?php if ($entries): ?>
          <div class="mt-2">
            <div class="text-muted small fw-semibold mb-1"><i class="bi bi-journal-text me-1"></i>Test Entries (<?= count($entries) ?>)</div>
            <?php foreach ($entries as $te): ?>
            <?php $teAtts = $testEntryAttachments[$te['id']] ?? []; ?>
            <div class="py-1 border-top border-secondary">
              <div class="d-flex align-items-center gap-2">
                <span class="rounded-circle d-inline-block flex-shrink-0" style="width:8px;height:8px;background:<?= e($te['type_color'] ?? '#0ea5e9') ?>"></span>
                <a href="<?= url('entries/' . $te['id']) ?>" class="small text-decoration-none flex-grow-1"><?= e($te['title'] ?: substr($te['description'], 0, 80)) ?></a>
                <span class="text-muted small text-nowrap"><?= e($te['entry_date']) ?></span>
              </div>
              <?php if ($teAtts): ?>
              <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(72px,1fr));gap:6px;margin-top:6px;margin-left:16px">
                <?php foreach ($teAtts as $att): ?>
                <div class="text-center">
                  <?php if (isImage($att['mime_type'])): ?>
                  <img src="<?= url('attachments/' . $att['id'] . '/thumb') ?>"
                       style="width:100%;height:60px;object-fit:cover;border-radius:.3rem;cursor:pointer"
                       onclick="teOpenLightbox('<?= url('attachments/' . $att['id']) ?>')"
                       loading="lazy" alt="">
                  <?php elseif (isVideo($att['mime_type'])): ?>
                  <div style="width:100%;height:60px;border-radius:.3rem;cursor:pointer;background:#374151;display:flex;align-items:center;justify-content:center"
                       onclick="teOpenVideo('<?= url('attachments/' . $att['id']) ?>')">
                    <i class="bi bi-play-circle fs-4 text-white"></i>
                  </div>
                  <?php elseif (isPdf($att['mime_type'])): ?>
                  <a href="<?= url('attachments/' . $att['id']) ?>" target="_blank" class="text-decoration-none">
                    <div style="width:100%;height:60px;border-radius:.3rem;background:#374151;display:flex;align-items:center;justify-content:center">
                      <i class="bi bi-file-pdf fs-4 text-danger"></i>
                    </div>
                  </a>
                  <?php else: ?>
                  <a href="<?= url('attachments/' . $att['id']) ?>" download class="text-decoration-none">
                    <div style="width:100%;height:60px;border-radius:.3rem;background:#374151;display:flex;align-items:center;justify-content:center">
                      <i class="bi bi-file-earmark fs-4 text-muted"></i>
                    </div>
                  </a>
                  <?php endif; ?>
                  <small class="text-muted text-truncate d-block mt-1" style="font-size:.6rem;max-width:100%" title="<?= e($att['original_name']) ?>">
                    <?= e($att['display_name'] ?: $att['original_name']) ?>
                  </small>
                  <div class="d-flex justify-content-center gap-1 mt-1">
                    <button class="btn btn-link btn-sm p-0 text-secondary" style="font-size:.65rem" title="Rename"
                            onclick="teOpenAttEdit(<?= $att['id'] ?>, '<?= e(addslashes($att['display_name'] ?: $att['original_name'])) ?>', '<?= e(addslashes($att['comment'] ?? '')) ?>')">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <form method="POST" action="<?= url('attachments/' . $att['id'] . '/delete') ?>"
                          onsubmit="return confirm('Delete this attachment?')">
                      <?= csrfField() ?>
                      <button class="btn btn-link btn-sm text-danger p-0" style="font-size:.65rem"><i class="bi bi-trash"></i></button>
                    </form>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <!-- Tester + Bugs row -->
        <?php
          $tName = '';
          if (!empty($r['assigned_tester'])) {
              foreach ($allUsers as $u) { if ((int)$u['id']===(int)$r['assigned_tester']) { $tName=$u['name']; break; } }
          }
          $rBugs = $bugsByResult[$r['id']] ?? [];
        ?>
        <div class="mt-2 d-flex flex-wrap gap-2 align-items-center" style="font-size:.78rem">
          <div class="d-flex align-items-center gap-1">
            <i class="bi bi-person text-muted"></i>
            <?php if ($tName): ?>
            <span class="badge bg-secondary"><?= e($tName) ?></span>
            <?php else: ?><span class="text-muted small">Kein Tester</span><?php endif; ?>
            <?php if (Auth::canEdit('testing')): ?>
            <button class="btn btn-outline-secondary btn-sm py-0 px-1" onclick="assignTester(<?= $r['id'] ?>,'<?= e(Auth::csrfToken()) ?>')" title="Tester zuweisen"><i class="bi bi-person-plus" style="font-size:.7rem"></i></button>
            <?php endif; ?>
          </div>
          <div class="d-flex align-items-center gap-1 flex-wrap">
            <i class="bi bi-bug text-muted"></i>
            <?php foreach ($rBugs as $bug): ?>
            <span class="badge bg-danger d-flex align-items-center gap-1">
              <?php if ($bug['jira_key'] && $bug['entry_id']): ?>
              <a href="<?= url('entries/'.$bug['entry_id']) ?>" class="text-white text-decoration-none" title="<?= e($bug['entry_title']??'') ?>"><?= e($bug['jira_key']) ?></a>
              <?php elseif ($bug['jira_key']): ?>
              <span><?= e($bug['jira_key']) ?></span>
              <?php elseif ($bug['entry_id']): ?>
              <a href="<?= url('entries/'.$bug['entry_id']) ?>" class="text-white text-decoration-none"><?= e(mb_substr($bug['entry_title']??'Entry',0,20)) ?></a>
              <?php endif; ?>
              <?php if (Auth::canEdit('testing')): ?>
              <button onclick="removeBug(<?= $bug['id'] ?>,<?= $r['id'] ?>,'<?= e(Auth::csrfToken()) ?>')" class="btn-close btn-close-white p-0 ms-1" style="font-size:.5rem"></button>
              <?php endif; ?>
            </span>
            <?php endforeach; ?>
            <?php if (Auth::canEdit('testing')): ?>
            <button class="btn btn-outline-danger btn-sm py-0 px-1" onclick="addBugModal(<?= $r['id'] ?>,'<?= e(Auth::csrfToken()) ?>')" title="Bug verknuepfen"><i class="bi bi-bug" style="font-size:.7rem"></i></button>
            <?php endif; ?>
          </div>
        </div>
        <div class="d-flex gap-1 flex-shrink-0">
          <button class="btn btn-outline-info btn-sm py-0 px-2" data-bs-toggle="collapse" data-bs-target="#new-entry-<?= $r['id'] ?>" title="Create Test Entry">
            <i class="bi bi-journal-plus"></i>
          </button>
          <button class="btn btn-outline-secondary btn-sm py-0 px-2" data-bs-toggle="collapse" data-bs-target="#result-<?= $r['id'] ?>">
            <i class="bi bi-pencil"></i>
          </button>
        </div>
      </div>

      <!-- Create entry collapse (full form) -->
      <div class="collapse mt-2" id="new-entry-<?= $r['id'] ?>">
        <form method="POST" action="<?= url('test-runs/' . $run['id'] . '/results/' . $r['id'] . '/entry') ?>" enctype="multipart/form-data" id="entry-form-<?= $r['id'] ?>">
          <?= csrfField() ?>
          <div class="card bg-transparent border-info">
            <div class="card-header border-info py-1 small text-info fw-semibold">
              <i class="bi bi-journal-plus me-1"></i>New Test Entry ? <?= e($r['item_title']) ?>
            </div>
            <div class="card-body p-2">
              <div class="row g-2">
                <!-- Title -->
                <div class="col-12">
                  <input type="text" name="title" class="form-control form-control-sm" placeholder="Title" value="Test Result: <?= e($r['item_title']) ?>">
                </div>
                <!-- Description -->
                <div class="col-12">
                  <textarea name="description" class="form-control form-control-sm" rows="3" placeholder="Observations / findings?"></textarea>
                </div>
                <!-- Date / Time -->
                <div class="col-md-3">
                  <label class="form-label small mb-1">Date</label>
                  <input type="date" name="entry_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label small mb-1">Time</label>
                  <input type="time" name="entry_time" class="form-control form-control-sm" value="<?= date('H:i') ?>">
                </div>
                <!-- Entry Type -->
                <div class="col-md-3">
                  <label class="form-label small mb-1">Entry Type</label>
                  <select name="entry_type_id" class="form-select form-select-sm">
                    <?php foreach ($entryTypes as $et): ?>
                    <option value="<?= $et['id'] ?>" <?= $et['name']==='Test Result'?'selected':'' ?>><?= e($et['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <!-- Error Category -->
                <div class="col-md-3">
                  <label class="form-label small mb-1">Category</label>
                  <select name="error_category_id" class="form-select form-select-sm">
                    <option value="">? none ?</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <!-- Inventory lookup / QR scan -->
                <div class="col-12">
                  <label class="form-label small mb-1">Find Mower in Inventory <span class="text-muted">(auto-fills serial &amp; firmware)</span></label>
                  <div class="position-relative">
                    <div class="input-group input-group-sm">
                      <span class="input-group-text bg-transparent border-secondary"><i class="bi bi-search text-muted"></i></span>
                      <input type="text" class="form-control form-control-sm inv-search"
                             placeholder="Search by serial number or name?" autocomplete="off"
                             data-rid="<?= $r['id'] ?>">
                      <button type="button" class="btn btn-outline-secondary"
                              onclick="openTestEntryQr(<?= $r['id'] ?>)" title="Scan QR Code">
                        <i class="bi bi-qr-code-scan me-1"></i>Scan QR
                      </button>
                    </div>
                    <div id="inv-dd-<?= $r['id'] ?>"
                         style="display:none;position:absolute;left:0;right:0;top:100%;z-index:9999;
                                background:#1a1d21;border:1px solid #495057;border-radius:.375rem;
                                max-height:180px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.5)"></div>
                  </div>
                </div>
                <!-- Firmware / App / Serial -->
                <div class="col-md-4">
                  <label class="form-label small mb-1">Firmware Version</label>
                  <input type="text" name="firmware_version" class="form-control form-control-sm" placeholder="e.g. 4.2.1">
                </div>
                <div class="col-md-4">
                  <label class="form-label small mb-1">App Version</label>
                  <input type="text" name="app_version" class="form-control form-control-sm" placeholder="e.g. 3.1.0">
                </div>
                <div class="col-md-4">
                  <label class="form-label small mb-1">Mower Serial</label>
                  <input type="text" name="mower_serial" class="form-control form-control-sm" placeholder="Serial number">
                </div>
                <!-- Test Area / Environment -->
                <?php if ($testAreas): ?>
                <div class="col-md-4">
                  <label class="form-label small mb-1">Test Area</label>
                  <select name="test_area_id" class="form-select form-select-sm">
                    <option value="">? none ?</option>
                    <?php foreach ($testAreas as $ta): ?>
                    <option value="<?= $ta['id'] ?>"><?= e($ta['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <?php endif; ?>
                <?php if ($environments): ?>
                <div class="col-md-4">
                  <label class="form-label small mb-1">Environment</label>
                  <select name="environment_id" class="form-select form-select-sm">
                    <option value="">? none ?</option>
                    <?php foreach ($environments as $env): ?>
                    <option value="<?= $env['id'] ?>"><?= e($env['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <?php endif; ?>
                <!-- Temperature / Weather -->
                <div class="col-md-2">
                  <label class="form-label small mb-1">Temp (?C)</label>
                  <input type="number" name="temperature" class="form-control form-control-sm" step="0.1" placeholder="20.0">
                </div>
                <div class="col-md-<?= ($testAreas && $environments) ? '2' : '4' ?>">
                  <label class="form-label small mb-1">Weather</label>
                  <input type="text" name="weather_condition" class="form-control form-control-sm" placeholder="e.g. Sunny, Wet">
                </div>
                <!-- Custom Fields -->
                <?php foreach ($customFields as $cf): ?>
                <div class="col-md-4">
                  <label class="form-label small mb-1"><?= e($cf['name']) ?></label>
                  <?php if ($cf['field_type'] === 'textarea'): ?>
                  <textarea name="cf[<?= $cf['id'] ?>]" class="form-control form-control-sm" rows="2" placeholder="<?= e($cf['placeholder'] ?? '') ?>"></textarea>
                  <?php elseif ($cf['field_type'] === 'select'): ?>
                  <select name="cf[<?= $cf['id'] ?>]" class="form-select form-select-sm">
                    <option value="">?</option>
                    <?php foreach (array_filter(array_map('trim', explode("\n", $cf['options'] ?? ''))) as $opt): ?>
                    <option value="<?= e($opt) ?>"><?= e($opt) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?php else: ?>
                  <input type="<?= $cf['field_type']==='number'?'number':'text' ?>" name="cf[<?= $cf['id'] ?>]" class="form-control form-control-sm" placeholder="<?= e($cf['placeholder'] ?? '') ?>">
                  <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <!-- Mowers from Inventory -->
                <?php if ($inventoryMowers): ?>
                <div class="col-12">
                  <label class="form-label small mb-1">Mowers (select all tested)</label>
                  <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($inventoryMowers as $m): ?>
                    <div class="form-check form-check-sm">
                      <input type="checkbox" class="form-check-input" name="inventory_item_ids[]" value="<?= $m['id'] ?>" id="inv-<?= $r['id'] ?>-<?= $m['id'] ?>">
                      <label class="form-check-label small" for="inv-<?= $r['id'] ?>-<?= $m['id'] ?>">
                        <?= e($m['name']) ?><?php if ($m['serial_number']): ?> <span class="text-muted">(<?= e($m['serial_number']) ?>)</span><?php endif; ?>
                      </label>
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>
                <?php endif; ?>
                <!-- Attachments -->
                <div class="col-12">
                  <label class="form-label small mb-1">Attachments <span class="text-muted">(photos, logs, PDFs?)</span></label>
                  <input type="file" name="files[]" class="form-control form-control-sm" multiple accept="image/*,video/*,.pdf,.zip,.txt,.csv,.json,.log">
                </div>
                <!-- Submit -->
                <div class="col-12">
                  <button class="btn btn-info btn-sm"><i class="bi bi-plus-circle me-1"></i>Create Test Entry</button>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>

      <!-- Update result collapse -->
      <div class="collapse mt-2" id="result-<?= $r['id'] ?>">
        <form method="POST" action="<?= url('test-runs/' . $run['id'] . '/results/' . $r['id']) ?>">
          <?= csrfField() ?>
          <div class="row g-2">
            <div class="col-md-3">
              <select name="status" class="form-select form-select-sm">
                <?php foreach (['pending'=>'Pending','passed'=>'Passed','failed'=>'Failed','skipped'=>'Skipped','blocked'=>'Blocked'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= $r['status']===$v?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-7">
              <input type="text" name="notes" class="form-control form-control-sm" value="<?= e($r['notes']) ?>" placeholder="Notizen?">
            </div>
            <div class="col-md-2"><button class="btn btn-success btn-sm w-100">Save</button></div>
          </div>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Lightbox modal -->
<div class="modal fade" id="teLightboxModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content bg-dark border-0">
      <div class="modal-body p-1 text-center position-relative">
        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-2" data-bs-dismiss="modal" style="z-index:10"></button>
        <img id="teLightboxImg" src="" style="max-width:100%;max-height:85vh;border-radius:.375rem" alt="">
      </div>
    </div>
  </div>
</div>

<!-- Video player modal -->
<div class="modal fade" id="teVideoModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary py-2">
        <h6 class="modal-title mb-0"><i class="bi bi-play-circle me-2"></i>Video</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-2">
        <video id="teVideoEl" controls style="width:100%;border-radius:.375rem;max-height:70vh"></video>
      </div>
    </div>
  </div>
</div>

<!-- Attachment rename modal -->
<div class="modal fade" id="teAttEditModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary py-2">
        <h6 class="modal-title mb-0"><i class="bi bi-pencil me-2"></i>Rename Attachment</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small">Display Name</label>
          <input type="text" id="teAttEditName" class="form-control">
        </div>
        <div class="mb-2">
          <label class="form-label small">Caption / Note</label>
          <input type="text" id="teAttEditCaption" class="form-control" placeholder="Optional">
        </div>
      </div>
      <div class="modal-footer border-secondary py-2">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="teSaveAttEdit()"><i class="bi bi-check-lg me-1"></i>Save</button>
      </div>
    </div>
  </div>
</div>

<!-- QR Scanner Modal (shared for all test entry forms) -->
<div class="modal fade" id="teQrModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary py-2">
        <h6 class="modal-title mb-0"><i class="bi bi-qr-code-scan me-2"></i>Scan QR Code</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-3">
        <div style="position:relative;display:inline-block;width:100%">
          <video id="teQrVideo" style="width:100%;border-radius:.5rem;background:#000" playsinline muted></video>
          <canvas id="teQrCanvas" style="display:none"></canvas>
        </div>
        <p id="teQrStatus" class="text-muted small mt-2 mb-0">Starting camera?</p>
      </div>
    </div>
  </div>
</div>

<script>
const _teCsrf = '<?= e(Auth::csrfToken()) ?>';

// ?? Lightbox ?????????????????????????????????????????????
function teOpenLightbox(url) {
  document.getElementById('teLightboxImg').src = url;
  bootstrap.Modal.getOrCreateInstance(document.getElementById('teLightboxModal')).show();
}

// ?? Video Player ?????????????????????????????????????????
function teOpenVideo(url) {
  const v = document.getElementById('teVideoEl');
  v.src = url; v.load();
  bootstrap.Modal.getOrCreateInstance(document.getElementById('teVideoModal')).show();
}
document.getElementById('teVideoModal')?.addEventListener('hide.bs.modal', () => {
  const v = document.getElementById('teVideoEl');
  v.pause(); v.src = '';
});

// ?? Attachment rename ????????????????????????????????????
let _teAttId = null;
function teOpenAttEdit(id, name, caption) {
  _teAttId = id;
  document.getElementById('teAttEditName').value    = name;
  document.getElementById('teAttEditCaption').value = caption;
  bootstrap.Modal.getOrCreateInstance(document.getElementById('teAttEditModal')).show();
}
function teSaveAttEdit() {
  const body = new URLSearchParams({
    _csrf: _teCsrf,
    display_name: document.getElementById('teAttEditName').value,
    comment:      document.getElementById('teAttEditCaption').value,
  });
  fetch('<?= url('attachments/') ?>' + _teAttId + '/update', {
    method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' }
  }).then(r => r.json()).then(() => {
    bootstrap.Modal.getInstance(document.getElementById('teAttEditModal'))?.hide();
    if (typeof showToast === 'function') showToast('Saved', 'success');
    setTimeout(() => location.reload(), 600);
  }).catch(() => { if (typeof showToast === 'function') showToast('Save failed', 'danger'); });
}

// ?? Inventory typeahead ???????????????????????????????????
let _invTimer = null;

document.addEventListener('input', function(e) {
  if (!e.target.classList.contains('inv-search')) return;
  const rid = e.target.dataset.rid;
  const q   = e.target.value.trim();
  const dd  = document.getElementById('inv-dd-' + rid);
  clearTimeout(_invTimer);
  if (q.length < 1) { dd.style.display = 'none'; dd.innerHTML = ''; return; }
  _invTimer = setTimeout(() => {
    fetch('<?= url('api/inventory/search') ?>?q=' + encodeURIComponent(q))
      .then(r => r.json())
      .then(items => {
        if (!items || !items.length) { dd.style.display = 'none'; dd.innerHTML = ''; return; }
        const statusColor = s => s === 'active' ? 'success' : s === 'repair' ? 'warning' : 'secondary';
        dd.innerHTML = items.map(it => `
          <div class="px-3 py-2 small inv-item"
               style="cursor:pointer;border-bottom:1px solid #495057"
               onmousedown="selectInvItem(event,${JSON.stringify(it).replace(/</g,'\\u003c')},${rid})">
            <strong>${it.serial_number || '?'}</strong>
            ${it.name ? `<span class="text-muted ms-2">${it.name}</span>` : ''}
            ${it.firmware_version ? `<span class="badge bg-secondary ms-1">${it.firmware_version}</span>` : ''}
            ${it.status ? `<span class="badge bg-${statusColor(it.status)} ms-1">${it.status}</span>` : ''}
            ${it.location ? `<span class="text-muted ms-1" style="font-size:.7rem">${it.location}</span>` : ''}
          </div>`).join('');
        dd.style.display = 'block';
      })
      .catch(() => {});
  }, 250);
});

document.addEventListener('blur', function(e) {
  if (!e.target.classList.contains('inv-search')) return;
  setTimeout(() => {
    const dd = document.getElementById('inv-dd-' + e.target.dataset.rid);
    if (dd) dd.style.display = 'none';
  }, 200);
}, true);

function selectInvItem(e, item, rid) {
  e.preventDefault();
  const form = document.getElementById('entry-form-' + rid);
  if (!form) return;
  if (item.serial_number) {
    const inp = form.querySelector('input[name="mower_serial"]');
    if (inp) inp.value = item.serial_number;
  }
  if (item.firmware_version) {
    const inp = form.querySelector('input[name="firmware_version"]');
    if (inp && !inp.value) inp.value = item.firmware_version;
  }
  const searchInp = form.querySelector('.inv-search');
  if (searchInp) searchInp.value = (item.serial_number || '') + (item.name ? ' ? ' + item.name : '');
  const dd = document.getElementById('inv-dd-' + rid);
  if (dd) dd.style.display = 'none';
}

// ?? QR Scanner ???????????????????????????????????????????
let _teQrStream = null, _teQrFrame = null, _teQrRid = null;

function openTestEntryQr(rid) {
  _teQrRid = rid;
  bootstrap.Modal.getOrCreateInstance(document.getElementById('teQrModal')).show();
}

const _teQrModal = document.getElementById('teQrModal');
if (_teQrModal) {
  _teQrModal.addEventListener('show.bs.modal', _startTeQr);
  _teQrModal.addEventListener('hide.bs.modal', _stopTeQr);
}

function _startTeQr() {
  const status = document.getElementById('teQrStatus');
  if (status) status.textContent = 'Starting camera?';
  navigator.mediaDevices?.getUserMedia({ video: { facingMode: { ideal: 'environment' } } })
    .then(stream => {
      _teQrStream = stream;
      const video = document.getElementById('teQrVideo');
      video.srcObject = stream; video.play();
      if (window.jsQR) { _tickTeQr(); return; }
      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js';
      s.onload = _tickTeQr;
      document.head.appendChild(s);
    })
    .catch(() => { const st = document.getElementById('teQrStatus'); if (st) st.textContent = 'Camera not available.'; });
}

function _stopTeQr() {
  if (_teQrFrame) { cancelAnimationFrame(_teQrFrame); _teQrFrame = null; }
  if (_teQrStream) { _teQrStream.getTracks().forEach(t => t.stop()); _teQrStream = null; }
}

function _tickTeQr() {
  const status = document.getElementById('teQrStatus');
  if (status) status.textContent = 'Point at a ROBODOC QR code?';
  const video = document.getElementById('teQrVideo');
  const canvas = document.getElementById('teQrCanvas');
  const ctx = canvas.getContext('2d');
  function scan() {
    if (video.readyState === video.HAVE_ENOUGH_DATA) {
      canvas.width = video.videoWidth; canvas.height = video.videoHeight;
      ctx.drawImage(video, 0, 0);
      const img  = ctx.getImageData(0, 0, canvas.width, canvas.height);
      const code = jsQR(img.data, img.width, img.height);
      if (code && code.data.startsWith('ROBODOC:')) { _handleTeQr(code.data); return; }
    }
    _teQrFrame = requestAnimationFrame(scan);
  }
  _teQrFrame = requestAnimationFrame(scan);
}

function _handleTeQr(data) {
  _stopTeQr();
  const params = {};
  data.replace('ROBODOC:', '').split('&').forEach(p => {
    const [k, v] = p.split('=');
    if (k) params[decodeURIComponent(k)] = decodeURIComponent(v || '');
  });
  const rid  = _teQrRid;
  const form = document.getElementById('entry-form-' + rid);
  if (params.serial && form) {
    const inp = form.querySelector('input[name="mower_serial"]');
    if (inp) inp.value = params.serial;
    const searchInp = form.querySelector('.inv-search');
    if (searchInp) searchInp.value = params.serial;
  }
  const toastLines = [];
  if (params.serial) toastLines.push('Serial: ' + params.serial);
  if (params.serial) {
    fetch('<?= url('api/inventory/by-serial') ?>?serial=' + encodeURIComponent(params.serial))
      .then(r => r.json())
      .then(item => {
        if (item && form) {
          if (item.firmware_version) {
            const inp = form.querySelector('input[name="firmware_version"]');
            if (inp && !inp.value) inp.value = item.firmware_version;
            toastLines.push('Firmware: ' + item.firmware_version);
          }
          if (item.name) {
            const searchInp = form.querySelector('.inv-search');
            if (searchInp) searchInp.value = (params.serial || '') + ' ? ' + item.name;
          }
        }
        if (typeof showToast === 'function') showToast('QR scanned<br>' + toastLines.join(' ? '), 'success');
      })
      .catch(() => { if (typeof showToast === 'function') showToast('QR scanned: ' + (params.serial || ''), 'success'); });
  } else {
    if (typeof showToast === 'function') showToast('QR scanned', 'success');
  }
  const st = document.getElementById('teQrStatus');
  if (st) st.textContent = '? QR code scanned!';
  setTimeout(() => bootstrap.Modal.getInstance(document.getElementById('teQrModal'))?.hide(), 900);
}
</script>

<script>
function synapseSync(runId, csrf) {
  const btn = event.target.closest('button');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Sync...'; }
  fetch('<?= url("synapse/sync-run") ?>', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf, run_id: runId, direction: 'both'})
  })
  .then(r => r.json())
  .then(d => {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Sync'; }
    const msg = d.success ? ('Sync fertig ? ' + (d.log||[]).length + ' Aktion(en).') : (d.error||'Fehler');
    if (typeof showToast === 'function') showToast(msg, d.success ? 'success' : 'danger');
    else alert(msg);
  })
  .catch(() => { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Sync'; } });
}
</script>

<!-- Tester + Bug Modals -->
<?php if (Auth::canEdit('testing')): ?>
<div class="modal fade" id="testerModal" tabindex="-1">
  <div class="modal-dialog modal-sm"><div class="modal-content bg-dark border-secondary">
    <div class="modal-header border-secondary py-2"><h6 class="modal-title">Tester zuweisen</h6>
      <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" id="testerResultId">
      <select id="testerSelect" class="form-select form-select-sm">
        <option value="">-- Kein Tester --</option>
        <?php foreach ($allUsers as $u): ?>
        <option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="modal-footer border-secondary py-2">
      <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
      <button class="btn btn-primary btn-sm" onclick="saveTester()">Speichern</button>
    </div>
  </div></div>
</div>

<div class="modal fade" id="bugModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content bg-dark border-secondary">
    <div class="modal-header border-secondary py-2"><h6 class="modal-title"><i class="bi bi-bug me-1"></i>Bug verknuepfen</h6>
      <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" id="bugResultId">
      <p class="text-muted small mb-3">Verknuepfe einen RoboDoc-Eintrag. Die verlinkte Jira-ID wird beim SynapseRT-Sync exportiert.</p>
      <div class="mb-3">
        <label class="form-label small">RoboDoc Eintrag suchen</label>
        <input type="text" id="bugEntrySearch" class="form-control form-control-sm" placeholder="Titel suchen..." oninput="searchBugEntries(this.value)">
        <div id="bugEntryResults" class="mt-1" style="max-height:180px;overflow-y:auto"></div>
        <div id="bugSelectedEntry" class="mt-2"></div>
        <input type="hidden" id="bugEntryId">
      </div>
      <div>
        <label class="form-label small">Oder direkt Jira Key</label>
        <input type="text" id="bugJiraKey" class="form-control form-control-sm" placeholder="z.B. BRSQ-123">
      </div>
    </div>
    <div class="modal-footer border-secondary py-2">
      <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
      <button class="btn btn-danger btn-sm" onclick="saveBug()"><i class="bi bi-bug me-1"></i>Verknuepfen</button>
    </div>
  </div></div>
</div>
<?php endif; ?>

<script>
const _runId = <?= $run['id'] ?>;
let _tCsrf = '', _bCsrf = '';

function assignTester(rid, csrf) {
  _tCsrf = csrf;
  document.getElementById('testerResultId').value = rid;
  new bootstrap.Modal(document.getElementById('testerModal')).show();
}
function saveTester() {
  const rid = document.getElementById('testerResultId').value;
  const uid = document.getElementById('testerSelect').value;
  fetch('<?= url("test-runs/") ?>' + _runId + '/results/' + rid + '/assign-tester', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: _tCsrf, user_id: uid})
  }).then(r => r.json()).then(d => {
    if (d.success) { bootstrap.Modal.getInstance(document.getElementById('testerModal')).hide(); location.reload(); }
    else alert(d.error || 'Fehler');
  });
}

function addBugModal(rid, csrf) {
  _bCsrf = csrf;
  document.getElementById('bugResultId').value = rid;
  ['bugEntrySearch','bugJiraKey'].forEach(id => document.getElementById(id).value = '');
  ['bugEntryResults','bugSelectedEntry'].forEach(id => document.getElementById(id).innerHTML = '');
  document.getElementById('bugEntryId').value = '';
  new bootstrap.Modal(document.getElementById('bugModal')).show();
}
let _bTimer;
function searchBugEntries(q) {
  clearTimeout(_bTimer);
  if (!q.trim()) { document.getElementById('bugEntryResults').innerHTML = ''; return; }
  _bTimer = setTimeout(() => {
    fetch('<?= url("entries") ?>?q=' + encodeURIComponent(q) + '&json=1', {headers: {'X-Requested-With': 'XMLHttpRequest'}})
    .then(r => r.json()).then(items => {
      if (!Array.isArray(items) || !items.length) {
        document.getElementById('bugEntryResults').innerHTML = '<div class="text-muted small py-1">Keine Eintraege.</div>';
        return;
      }
      document.getElementById('bugEntryResults').innerHTML = '<div class="list-group">' +
        items.slice(0, 8).map(i => {
          const jk = i.jira_issue_key || '';
          const lbl = (jk ? '<span class="badge bg-dark border border-warning text-warning me-1" style="font-size:.6rem">' + jk + '</span>' : '') + (i.title||'').substring(0, 45);
          return '<button class="list-group-item list-group-item-action bg-dark border-secondary py-1 text-start" style="font-size:.8rem" onclick="selectBugEntry(' + i.id + ','' + (i.title||'').replace(/'/g, "\'").substring(0,40) + '','' + jk + '')">' + lbl + '</button>';
        }).join('') + '</div>';
    });
  }, 300);
}
function selectBugEntry(id, title, jiraKey) {
  document.getElementById('bugEntryId').value = id;
  document.getElementById('bugSelectedEntry').innerHTML =
    '<span class="badge bg-secondary">' + title + '</span>' +
    (jiraKey ? '<span class="badge bg-warning text-dark ms-1">' + jiraKey + '</span>' : '<span class="text-muted small ms-1">kein Jira-Key ? wird ignoriert</span>');
  document.getElementById('bugEntryResults').innerHTML = '';
  document.getElementById('bugEntrySearch').value = '';
  if (jiraKey) document.getElementById('bugJiraKey').value = jiraKey;
}
function saveBug() {
  const rid     = document.getElementById('bugResultId').value;
  const entryId = document.getElementById('bugEntryId').value;
  const jiraKey = document.getElementById('bugJiraKey').value.trim().toUpperCase();
  if (!entryId && !jiraKey) { alert('Bitte Eintrag auswaehlen oder Jira Key eingeben.'); return; }
  fetch('<?= url("test-runs/") ?>' + _runId + '/results/' + rid + '/bugs', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: _bCsrf, entry_id: entryId, jira_key: jiraKey})
  }).then(r => r.json()).then(d => {
    if (d.success) { bootstrap.Modal.getInstance(document.getElementById('bugModal')).hide(); location.reload(); }
    else alert(d.error || 'Fehler');
  });
}
function removeBug(bugId, rid, csrf) {
  if (!confirm('Bug-Verknuepfung entfernen?')) return;
  fetch('<?= url("test-runs/") ?>' + _runId + '/results/' + rid + '/bugs/' + bugId + '/delete', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf})
  }).then(r => r.json()).then(d => { if (d.success) location.reload(); });
}
function synapseSync(csrf) {
  const btn = event.target.closest('button');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Sync...'; }
  fetch('<?= url("synapse/sync-run") ?>', {
    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: new URLSearchParams({_csrf: csrf, run_id: _runId, direction: 'both'})
  }).then(r => r.json()).then(d => {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Sync'; }
    const msg = d.success ? ('Sync fertig - ' + (d.log||[]).length + ' Aktionen.') : (d.error || 'Fehler');
    if (typeof showToast === 'function') showToast(msg, d.success ? 'success' : 'danger'); else alert(msg);
  }).catch(() => { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Sync'; } });
}
</script>
