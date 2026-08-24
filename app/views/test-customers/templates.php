<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <a href="<?= url('test-customers') ?>" class="text-muted small"><i class="bi bi-arrow-left me-1"></i>Aufträge</a>
    <h5 class="mt-1 mb-0"><i class="bi bi-file-text me-2"></i>Fragebogen Templates</h5>
  </div>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createTplModal">
    <i class="bi bi-plus-lg me-1"></i>Neues Template
  </button>
</div>

<?php if (empty($templates)): ?>
<div class="text-center text-muted py-5">
  <i class="bi bi-file-text" style="font-size:3rem;opacity:.3"></i>
  <p class="mt-3">Noch keine Templates. Erstelle wiederverwendbare Fragebögen.</p>
</div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($templates as $t): ?>
  <?php $qs = json_decode($t['questions'] ?: '[]', true); ?>
  <div class="col-md-6 col-lg-4">
    <div class="card border-secondary h-100">
      <div class="card-body">
        <h6 class="fw-semibold"><?= e($t['title']) ?></h6>
        <?php if ($t['description']): ?>
        <p class="text-muted small"><?= e(mb_substr($t['description'],0,80)) ?></p>
        <?php endif; ?>
        <span class="badge bg-secondary"><?= count($qs) ?> Fragen</span>
        <div class="text-muted" style="font-size:.72rem;margin-top:6px">
          <?php if ($t['creator_name']): ?>von <?= e($t['creator_name']) ?> &middot; <?php endif; ?>
          <?= date('d.m.Y', strtotime($t['created_at'])) ?>
        </div>
      </div>
      <div class="card-footer border-secondary d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm flex-grow-1"
                onclick="editTemplate(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)">
          <i class="bi bi-pencil me-1"></i>Bearbeiten
        </button>
        <form method="POST" action="<?= url('test-customers/templates/' . $t['id'] . '/delete') ?>"
              onsubmit="return confirm('Template löschen?')">
          <?= csrfField() ?>
          <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Create/Edit Template Modal -->
<div class="modal fade" id="createTplModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title" id="tplModalTitle">Neues Template</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="<?= url('test-customers/templates/save') ?>">
        <?= csrfField() ?>
        <input type="hidden" name="template_id" id="tplId" value="">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Titel <span class="text-danger">*</span></label>
            <input type="text" name="title" id="tplTitle" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Beschreibung</label>
            <textarea name="description" id="tplDesc" class="form-control" rows="2"></textarea>
          </div>
          <div class="d-flex align-items-center justify-content-between mb-2">
            <label class="form-label mb-0">Fragen</label>
            <button type="button" class="btn btn-outline-secondary btn-sm py-0" onclick="addTplQuestion()">
              <i class="bi bi-plus-lg me-1"></i>Frage
            </button>
          </div>
          <div id="tplQuestionList"></div>
          <input type="hidden" name="questions" id="tplQuestionsJson" value="[]">
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
          <button type="submit" class="btn btn-primary" onclick="saveTplQuestions()">Speichern</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
var _tplQ = [];

function addTplQuestion(q) {
  _tplQ.push(q || {text:'', type:'text', options:[]});
  renderTplQ();
}

function removeTplQ(i) { _tplQ.splice(i,1); renderTplQ(); }

function renderTplQ() {
  document.getElementById('tplQuestionList').innerHTML = _tplQ.map(function(q,i) {
    return '<div class="d-flex gap-2 align-items-start mb-2 p-2 border border-secondary rounded">' +
      '<div class="flex-grow-1">' +
        '<input type="text" class="form-control form-control-sm mb-1" placeholder="Frage..." value="'+(q.text||'')+'" onchange="_tplQ['+i+'].text=this.value">' +
        '<select class="form-select form-select-sm" onchange="_tplQ['+i+'].type=this.value;renderTplQ()">' +
          '<option value="text"'+(q.type==='text'?' selected':'')+'>Freitext</option>' +
          '<option value="rating"'+(q.type==='rating'?' selected':'')+'>Bewertung (1-5)</option>' +
          '<option value="select"'+(q.type==='select'?' selected':'')+'>Auswahl</option>' +
          '<option value="yesno"'+(q.type==='yesno'?' selected':'')+'>Ja / Nein</option>' +
        '</select>' +
        (q.type==='select'?'<input type="text" class="form-control form-control-sm mt-1" placeholder="Optionen (Komma getrennt)" value="'+((q.options||[]).join(', '))+'" onchange="_tplQ['+i+'].options=this.value.split(',').map(s=>s.trim())">':'') +
      '</div>' +
      '<button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" onclick="removeTplQ('+i+')"><i class="bi bi-trash" style="font-size:.7rem"></i></button>' +
    '</div>';
  }).join('');
}

function saveTplQuestions() {
  document.getElementById('tplQuestionsJson').value = JSON.stringify(_tplQ);
}

function editTemplate(t) {
  document.getElementById('tplId').value    = t.id;
  document.getElementById('tplTitle').value = t.title;
  document.getElementById('tplDesc').value  = t.description || '';
  document.getElementById('tplModalTitle').textContent = 'Template bearbeiten';
  _tplQ = JSON.parse(t.questions || '[]');
  renderTplQ();
  new bootstrap.Modal(document.getElementById('createTplModal')).show();
}

renderTplQ();
</script>
