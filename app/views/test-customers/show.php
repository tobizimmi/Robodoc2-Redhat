<?php
$feedbackUrl = url('tc-feedback/' . $order['qr_token']);
$csrf = Auth::csrfToken();
?>

<div class="d-flex align-items-start gap-3 mb-4 flex-wrap">
  <div class="flex-grow-1">
    <a href="<?= url('test-customers') ?>" class="text-muted small"><i class="bi bi-arrow-left me-1"></i>All Orders</a>
    <h5 class="mt-1 mb-0"><?= e($order['title']) ?></h5>
    <div class="d-flex align-items-center gap-2 mt-1">
      <span class="badge" style="background:<?= e($order['project_color'] ?? '#666') ?>"><?= e($order['project_name']) ?></span>
      <span class="badge <?= $order['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= e($order['status']) ?></span>
    </div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <!-- QR Code Feedback -->
    <a href="<?= url('test-customers/qr/feedback/' . $order['qr_token']) ?>" class="btn btn-outline-info btn-sm" download>
      <i class="bi bi-qr-code me-1"></i>QR Feedback
    </a>
    <a href="<?= e($feedbackUrl) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-box-arrow-up-right me-1"></i>Open Feedback
    </a>
    <button class="btn btn-outline-secondary btn-sm" onclick="navigator.clipboard.writeText(window.location.origin + '<?= e($feedbackUrl) ?>');this.textContent='Copied!'">
      <i class="bi bi-clipboard me-1"></i>Copy Link
    </button>
    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editOrderModal">
      <i class="bi bi-pencil me-1"></i>Edit
    </button>
    <form method="POST" action="<?= url('test-customers/' . $order['id'] . '/delete') ?>"
          onsubmit="return confirm('Delete order and all data?')" class="d-inline">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <button type="submit" class="btn btn-outline-danger btn-sm">
        <i class="bi bi-trash me-1"></i>Delete
      </button>
    </form>
  </div>
</div>

<?php if ($order['description']): ?>
<div class="alert alert-secondary py-2 small mb-4"><?= e($order['description']) ?></div>
<?php endif; ?>

<div class="row g-4">
  <!-- LEFT: Feedback -->
  <div class="col-lg-6">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h6 class="mb-0"><i class="bi bi-chat-left-text me-2 text-info"></i>Customer Feedback
        <?php $pending = array_filter($feedback, fn($f) => $f['status'] === 'pending'); ?>
        <?php if ($pending): ?>
        <span class="badge bg-warning text-dark ms-2"><?= count($pending) ?> new</span>
        <?php endif; ?>
      </h6>
      <small class="text-muted"><?= count($feedback) ?> total</small>
    </div>

    <?php if (empty($feedback)): ?>
    <div class="text-muted small text-center py-4 border border-secondary rounded">
      No feedback yet. Share the QR code or link with customers.
    </div>
    <?php else: ?>
    <div class="d-flex flex-column gap-2" style="max-height:600px;overflow-y:auto">
      <?php foreach ($feedback as $fb): ?>
      <div class="card border-secondary <?= $fb['status'] === 'pending' ? 'border-warning' : '' ?>">
        <div class="card-body py-2 px-3">
          <div class="d-flex align-items-start gap-2">
            <div class="flex-grow-1">
              <?php if (empty($fb['respondent_id']) && empty($fb['respondent_name'])): ?>
              <span class="badge bg-primary me-1" style="font-size:.6rem">Quick Capture</span>
              <?php endif; ?>
              <a href="<?= url('test-customers/' . $order['id'] . '/feedback/' . $fb['id']) ?>" class="fw-semibold small text-white text-decoration-none"><?= e($fb['title']) ?></a>
              <?php if ($fb['description']): ?>
              <div class="text-muted small mt-1"><?= e(mb_substr($fb['description'], 0, 150)) ?></div>
              <?php endif; ?>
              <div class="d-flex gap-3 mt-1" style="font-size:.75rem">
                <?php if ($fb['rating']): ?>
                <span class="text-warning"><?= str_repeat('★', $fb['rating']) ?><?= str_repeat('☆', 5 - $fb['rating']) ?></span>
                <?php endif; ?>
                <?php if ($fb['respondent_name']): ?>
                <span class="text-muted"><i class="bi bi-person me-1"></i><?= e($fb['respondent_name']) ?></span>
                <?php endif; ?>
                <?php if ($fb['mower_serial']): ?>
                <span class="text-muted"><i class="bi bi-upc me-1"></i><?= e($fb['mower_serial']) ?></span>
                <?php endif; ?>
                <span class="text-muted"><?= date('d.m.Y H:i', strtotime($fb['created_at'])) ?></span>
              </div>
            </div>
            <div class="d-flex flex-column gap-1 flex-shrink-0">
              <?php if ($fb['entry_id']): ?>
              <a href="<?= url('entries/' . $fb['entry_id']) ?>" class="btn btn-outline-success btn-sm py-0 px-1" title="To entry">
                <i class="bi bi-box-arrow-up-right" style="font-size:.7rem"></i>
              </a>
              <?php elseif ($fb['status'] !== 'imported'): ?>
              <form method="POST" action="<?= url('test-customers/' . $order['id'] . '/feedback/' . $fb['id'] . '/review') ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="import">
                <button class="btn btn-outline-primary btn-sm py-0 px-1" title="Import as entry">
                  <i class="bi bi-download" style="font-size:.7rem"></i>
                </button>
              </form>
              <form method="POST" action="<?= url('test-customers/' . $order['id'] . '/feedback/' . $fb['id'] . '/review') ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="review">
                <button class="btn btn-outline-secondary btn-sm py-0 px-1" title="Mark as reviewed">
                  <i class="bi bi-check" style="font-size:.7rem"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($fb['status'] !== 'pending'): ?>
          <div class="mt-1">
            <span class="badge <?= $fb['status'] === 'imported' ? 'bg-success' : 'bg-secondary' ?>" style="font-size:.65rem">
              <?= $fb['status'] === 'imported' ? 'Imported' : 'Rejected' ?>
            </span>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- RIGHT: Questionnaires -->
  <div class="col-lg-6">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h6 class="mb-0"><i class="bi bi-list-check me-2 text-success"></i>Questionnaires</h6>
      <button class="btn btn-outline-success btn-sm py-0" data-bs-toggle="modal" data-bs-target="#createQModal">
        <i class="bi bi-plus-lg me-1"></i>Create
      </button>
    </div>

    <?php if (empty($questionnaires)): ?>
    <div class="text-muted small text-center py-4 border border-secondary rounded">
      No questionnaires yet. Create a new one or use a template.
    </div>
    <?php else: ?>
    <div class="d-flex flex-column gap-2">
      <?php foreach ($questionnaires as $q): ?>
      <div class="card border-secondary">
        <div class="card-body py-2 px-3">
          <div class="d-flex align-items-start gap-2">
            <div class="flex-grow-1">
              <div class="d-flex align-items-center gap-2">
                <span class="fw-semibold small"><?= e($q['title']) ?></span>
                <?php if ($q['draft_mode'] ?? 1): ?>
                <span class="badge bg-warning text-dark" style="font-size:.6rem">DRAFT</span>
                <?php else: ?>
                <span class="badge bg-success" style="font-size:.6rem">LIVE</span>
                <?php endif; ?>
              </div>
              <div class="text-muted small mt-1">
                <i class="bi bi-people me-1"></i><?= $q['response_count'] ?> responses
                <?php if ($q['draft_mode'] ?? 1): ?>
                &nbsp;<span class="text-warning small"><i class="bi bi-exclamation-triangle"></i> Test data</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="d-flex gap-1 flex-shrink-0">
              <a href="<?= e(url('tc-questionnaire/' . $q['qr_token'])) ?>" target="_blank"
                 class="btn btn-outline-secondary btn-sm py-0 px-2" title="Open questionnaire">
                <i class="bi bi-box-arrow-up-right" style="font-size:.7rem"></i>
              </a>
              <button class="btn btn-outline-secondary btn-sm py-0 px-2"
                      onclick="navigator.clipboard.writeText(window.location.origin + '<?= e(url('tc-questionnaire/' . $q['qr_token'])) ?>');this.textContent='✓'"
                      title="Copy link">
                <i class="bi bi-clipboard" style="font-size:.7rem"></i>
              </button>
              <?php if ($q['draft_mode'] ?? 1): ?>
              <button class="btn btn-outline-warning btn-sm py-0 px-2 eq-edit-btn" title="Edit (draft)"
                      data-id="<?= $q['id'] ?>"
                      data-title="<?= e($q['title']) ?>"
                      data-description="<?= e($q['description'] ?? '') ?>"
                      data-questions='<?= htmlspecialchars(json_encode(is_array($q['questions']) ? $q['questions'] : json_decode($q['questions'] ?: '[]', true)), ENT_QUOTES) ?>'>
                <i class="bi bi-pencil" style="font-size:.7rem"></i>
              </button>
              <form method="POST" class="d-inline"
                    action="<?= url('test-customers/' . $order['id'] . '/questionnaires/' . $q['id'] . '/publish') ?>"
                    onsubmit="return confirm('Publish questionnaire?\nExisting test answers will be deleted.\nIt cannot be edited afterwards.')">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-success btn-sm py-0 px-2" title="Publish as live">
                  <i class="bi bi-rocket-takeoff" style="font-size:.7rem"></i>
                </button>
              </form>
              <?php endif; ?>
              <a href="<?= url('test-customers/' . $order['id'] . '/questionnaires/' . $q['id']) ?>" class="btn btn-outline-primary btn-sm py-0 px-2" title="View responses">
                <i class="bi bi-bar-chart" style="font-size:.7rem"></i>
              </a>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Respondents Section -->
<div class="card border-secondary mt-4">
  <div class="card-header border-secondary d-flex align-items-center justify-content-between">
    <span><i class="bi bi-person-badge me-2 text-warning"></i>Individual Test Customers</span>
    <div class="d-flex gap-2">
      <a href="<?= url('test-customers/customers') ?>" class="btn btn-outline-secondary btn-sm py-0">
        <i class="bi bi-people me-1"></i>Directory
      </a>
      <button class="btn btn-outline-warning btn-sm py-0" data-bs-toggle="modal" data-bs-target="#addRespondentModal">
        <i class="bi bi-person-plus me-1"></i>Add
      </button>
    </div>
  </div>
  <div class="card-body p-0">
    <?php if (empty($respondents)): ?>
    <div class="text-muted small text-center py-3">
      No individual test customers yet. Create test customers to generate personal links without data entry.
    </div>
    <?php else: ?>
    <table class="table table-dark table-sm mb-0">
      <thead><tr>
        <th>Test Customer No.</th>
        <th>Label (internal)</th>
        <th>Email (internal)</th>
        <th>Personal Link</th>
        <th>Feedback</th>
        <th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($respondents as $resp):
        $respUrl = url("tc-respondent/" . $resp["token"]);
        $respFbCount = count(array_filter($feedback ?? [], fn($f) => ($f["respondent_id"] ?? null) == $resp["id"]));
      ?>
      <tr>
        <td><code><?= e($resp["customer_number"]) ?></code></td>
        <td><?= e($resp["label"]) ?></td>
        <td>
          <?php if ($resp["email"]): ?>
          <a href="mailto:<?= e($resp['email']) ?>" class="text-muted small"><?= e($resp['email']) ?></a>
          <?php else: ?>
          <span class="text-muted small">–</span>
          <?php endif; ?>
        </td>
        <td>
          <a href="<?= e($respUrl) ?>" target="_blank" class="btn btn-outline-secondary btn-sm py-0 px-2">
            <i class="bi bi-box-arrow-up-right" style="font-size:.7rem"></i>
          </a>
          <button class="btn btn-outline-secondary btn-sm py-0 px-2"
                  onclick="navigator.clipboard.writeText(window.location.origin + '<?= e($respUrl) ?>');this.textContent='✓'"
                  title="Copy link">
            <i class="bi bi-clipboard" style="font-size:.7rem"></i>
          </button>
        </td>
        <td>
          <span class="badge bg-secondary"><?= $respFbCount ?></span>
        </td>
        <td class="text-end">
          <form method="POST"
                action="<?= url("test-customers/" . $order["id"] . "/respondents/" . $resp["id"] . "/delete") ?>"
                onsubmit="return confirm('Delete test customer?')" class="d-inline">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1">
              <i class="bi bi-trash" style="font-size:.7rem"></i>
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Add Respondent Modal — select from central directory -->
<div class="modal fade" id="addRespondentModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add Test Customer to Order</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info py-2 small mb-3">
          <i class="bi bi-shield-check me-2"></i>
          Each test customer receives a personal link without entering a name.
          Not in the directory yet? <a href="<?= url('test-customers/customers') ?>" class="alert-link">Create one first</a>.
        </div>
        <?php
          $allCustomers = Database::fetchAll('SELECT * FROM test_customers ORDER BY customer_number');
          $assignedIds  = array_filter(array_column($respondents ?? [], 'test_customer_id'));
        ?>
        <?php if (empty($allCustomers)): ?>
        <p class="text-muted text-center py-3">
          No test customers in the directory yet.
          <a href="<?= url('test-customers/customers') ?>">Create one now</a>
        </p>
        <?php else: ?>
        <div style="max-height:350px;overflow-y:auto">
          <?php foreach ($allCustomers as $tc): ?>
          <?php $assigned = in_array($tc['id'], $assignedIds); ?>
          <div class="d-flex align-items-center gap-3 p-2 border-bottom border-secondary <?= $assigned ? 'opacity-50' : '' ?>">
            <div class="flex-grow-1">
              <span class="fw-semibold"><?= e($tc['label']) ?></span>
              <code class="ms-2 small"><?= e($tc['customer_number']) ?></code>
              <?php if ($tc['email']): ?>
              <span class="text-muted small ms-2"><?= e($tc['email']) ?></span>
              <?php endif; ?>
            </div>
            <?php if ($assigned): ?>
            <span class="badge bg-success">Already assigned</span>
            <?php else: ?>
            <form method="POST" action="<?= url('test-customers/' . $order['id'] . '/respondents/add-from-customer') ?>">
              <?= csrfField() ?>
              <input type="hidden" name="test_customer_id" value="<?= $tc['id'] ?>">
              <button type="submit" class="btn btn-outline-warning btn-sm py-0 px-2">
                <i class="bi bi-plus-lg me-1"></i>Add
              </button>
            </form>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer border-secondary">
        <a href="<?= url('test-customers/customers') ?>" class="btn btn-outline-secondary">
          <i class="bi bi-people me-1"></i>Manage Directory
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Order Modal -->
<div class="modal fade" id="editOrderModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title">Edit Order</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="<?= url('test-customers/' . $order['id'] . '/update') ?>">
        <?= csrfField() ?>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" value="<?= e($order['title']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="active" <?= $order['status'] === 'active' ? 'selected' : '' ?>>Active</option>
              <option value="draft" <?= $order['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
              <option value="closed" <?= $order['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Description (internal)</label>
            <textarea name="description" class="form-control" rows="2"><?= e($order['description'] ?? '') ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Instructions / Explanation for customers</label>
            <textarea name="feedback_instructions" class="form-control" rows="4"
                      placeholder="This explanation is shown at the top of the feedback form..."><?= e($order['feedback_instructions'] ?? '') ?></textarea>
            <div class="form-text">Shown to the customer at the top of the feedback form.</div>
          </div>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Create Questionnaire Modal -->
<div class="modal fade" id="createQModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title"><i class="bi bi-list-check me-2"></i>Create Questionnaire</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="<?= url('test-customers/' . $order['id'] . '/questionnaires/create') ?>">
        <?= csrfField() ?>
        <div class="modal-body">
          <?php if (!empty($templates)): ?>
          <div class="mb-3">
            <label class="form-label small">Use template (optional)</label>
            <select name="template_id" class="form-select" onchange="loadTemplate(this.value)">
              <option value="">-- No template --</option>
              <?php foreach ($templates as $t): ?>
              <option value="<?= $t['id'] ?>"><?= e($t['title']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <hr class="border-secondary">
          <?php endif; ?>
          <div class="mb-3">
            <label class="form-label">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" id="qTitle" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Instructions / Info text for customers</label>
            <textarea name="description" id="qDesc" class="form-control" rows="3"
                      placeholder="This text is shown at the top of the questionnaire as a notice..."></textarea>
            <div class="form-text">Shown to the customer as a blue info banner at the top of the questionnaire.</div>
          </div>
          <div class="mb-2">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <label class="form-label mb-0">Questions</label>
              <button type="button" class="btn btn-outline-secondary btn-sm py-0" onclick="addQuestion()">
                <i class="bi bi-plus-lg me-1"></i>Add Question
              </button>
            </div>
            <div id="questionList"></div>
            <input type="hidden" name="questions" id="questionsJson" value="[]">
          </div>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success" onclick="saveQuestions()">Create</button>
        </div>
      </form>
    </div>
  </div>

</div>
</div><!-- /createQModal -->
<!-- Edit Questionnaire Modal -->
<div class="modal fade" id="editQModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Questionnaire</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="editQForm">
        <?= csrfField() ?>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" id="eqTitle" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Instructions / Info text for customers</label>
            <textarea name="description" id="eqDesc" class="form-control" rows="3"
                      placeholder="This text is shown at the top of the questionnaire as a blue info banner..."></textarea>
            <div class="form-text">Shown to the customer at the top of the questionnaire.</div>
          </div>
          <div class="d-flex align-items-center justify-content-between mb-2">
            <label class="form-label mb-0">Questions</label>
            <button type="button" class="btn btn-outline-secondary btn-sm py-0" onclick="addEqQuestion()">
              <i class="bi bi-plus-lg me-1"></i>Question
            </button>
          </div>
          <div id="eqQuestionList"></div>
          <input type="hidden" name="questions" id="eqQuestionsJson" value="[]">
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" onclick="saveEqQuestions()">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
var _eqQuestions = [];

document.addEventListener('click', function(e) {
  var btn = e.target.closest('.eq-edit-btn');
  if (!btn) return;
  document.getElementById('eqTitle').value = btn.dataset.title || '';
  document.getElementById('eqDesc').value  = btn.dataset.description || '';
  var baseAction = '<?= url("test-customers/" . $order["id"] . "/questionnaires/") ?>';
  document.getElementById('editQForm').action = baseAction + btn.dataset.id + '/update';
  try { _eqQuestions = JSON.parse(btn.dataset.questions || '[]'); } catch(e) { _eqQuestions = []; }
  renderEqQuestions();
  new bootstrap.Modal(document.getElementById('editQModal')).show();
});

function addEqQuestion(q) {
  _eqQuestions.push(q || {text:'', type:'text', options:[]});
  renderEqQuestions();
}

function removeEqQuestion(i) {
  _eqQuestions.splice(i, 1);
  renderEqQuestions();
}

function renderEqQuestions() {
  var list = document.getElementById('eqQuestionList');
  list.innerHTML = '';
  _eqQuestions.forEach(function(q, i) {
    var wrap = document.createElement('div');
    wrap.className = 'd-flex gap-2 align-items-start mb-2 p-2 border border-secondary rounded';

    var inner = document.createElement('div');
    inner.className = 'flex-grow-1';

    var textInp = document.createElement('input');
    textInp.type = 'text';
    textInp.className = 'form-control form-control-sm mb-1';
    textInp.placeholder = 'Question...';
    textInp.value = q.text || '';
    textInp.addEventListener('input', function() { _eqQuestions[i].text = this.value; });
    inner.appendChild(textInp);

    var sel = document.createElement('select');
    sel.className = 'form-select form-select-sm';
    [['text','Free text'],['rating','Rating (1-5)'],['select','Select'],['yesno','Yes / No']].forEach(function(opt) {
      var o = document.createElement('option');
      o.value = opt[0]; o.textContent = opt[1];
      if (q.type === opt[0]) o.selected = true;
      sel.appendChild(o);
    });
    sel.addEventListener('change', function() { _eqQuestions[i].type = this.value; renderEqQuestions(); });
    inner.appendChild(sel);

    if (q.type === 'select') {
      var optInp = document.createElement('input');
      optInp.type = 'text';
      optInp.className = 'form-control form-control-sm mt-1';
      optInp.placeholder = 'Options (comma separated)';
      optInp.value = (q.options || []).join(', ');
      optInp.addEventListener('input', function() {
        _eqQuestions[i].options = this.value.split(',').map(function(s){ return s.trim(); }).filter(Boolean);
      });
      inner.appendChild(optInp);
    }

    wrap.appendChild(inner);

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-outline-danger btn-sm py-0 px-1';
    btn.innerHTML = '<i class="bi bi-trash" style="font-size:.7rem"></i>';
    btn.addEventListener('click', function() { removeEqQuestion(i); });
    wrap.appendChild(btn);

    list.appendChild(wrap);
  });
}

function saveEqQuestions() {
  document.getElementById('eqQuestionsJson').value = JSON.stringify(_eqQuestions);
}
</script>

<script>
var _questions = [];
var _templates = <?= json_encode(array_column($templates ?? [], null, 'id')) ?>;

function addQuestion(q) {
  _questions.push(q || {text: '', type: 'text', options: []});
  renderQuestions();
}

function removeQuestion(i) {
  _questions.splice(i, 1);
  renderQuestions();
}

function renderQuestions() {
  var list = document.getElementById('questionList');
  list.innerHTML = '';
  _questions.forEach(function(q, i) {
    var wrap = document.createElement('div');
    wrap.className = 'd-flex gap-2 align-items-start mb-2 p-2 border border-secondary rounded';
    wrap.dataset.idx = i;

    var inner = document.createElement('div');
    inner.className = 'flex-grow-1';

    // Text input
    var textInp = document.createElement('input');
    textInp.type = 'text';
    textInp.className = 'form-control form-control-sm mb-1';
    textInp.placeholder = 'Question...';
    textInp.value = q.text || '';
    textInp.addEventListener('input', function() { _questions[i].text = this.value; });
    inner.appendChild(textInp);

    // Type select
    var sel = document.createElement('select');
    sel.className = 'form-select form-select-sm';
    [['text','Free text'],['rating','Rating (1-5)'],['select','Select'],['yesno','Yes / No']].forEach(function(opt) {
      var o = document.createElement('option');
      o.value = opt[0]; o.textContent = opt[1];
      if (q.type === opt[0]) o.selected = true;
      sel.appendChild(o);
    });
    sel.addEventListener('change', function() { _questions[i].type = this.value; renderQuestions(); });
    inner.appendChild(sel);

    // Options input (only for select type)
    if (q.type === 'select') {
      var optInp = document.createElement('input');
      optInp.type = 'text';
      optInp.className = 'form-control form-control-sm mt-1';
      optInp.placeholder = 'Options (comma separated)';
      optInp.value = (q.options || []).join(', ');
      optInp.addEventListener('input', function() {
        _questions[i].options = this.value.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
      });
      inner.appendChild(optInp);
    }

    wrap.appendChild(inner);

    // Remove button
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-outline-danger btn-sm py-0 px-1';
    btn.innerHTML = '<i class="bi bi-trash" style="font-size:.7rem"></i>';
    btn.addEventListener('click', function() { removeQuestion(i); });
    wrap.appendChild(btn);

    list.appendChild(wrap);
  });
}

function saveQuestions() {
  document.getElementById('questionsJson').value = JSON.stringify(_questions);
}

function loadTemplate(id) {
  if (!id || !_templates[id]) return;
  var t = _templates[id];
  document.getElementById('qTitle').value = t.title || '';
  document.getElementById('qDesc').value = t.description || '';
  _questions = JSON.parse(t.questions || '[]');
  renderQuestions();
}

renderQuestions();
</script>
