<?php $questions = $q['questions'] ?? []; ?>
<style>
body{background:#111;color:#eee;font-family:system-ui,sans-serif;}
.card{background:#1e2125;border:1px solid #333;border-radius:8px;padding:24px;max-width:580px;margin:40px auto;}
.form-control,.form-select{background:#2d2f33;border-color:#444;color:#eee;}
.form-control:focus,.form-select:focus{background:#2d2f33;border-color:#6366f1;color:#eee;box-shadow:none;}
.q-block{background:#252830;border:1px solid #333;border-radius:6px;padding:16px;margin-bottom:16px;}
.star-r{display:flex;gap:8px;font-size:1.8rem;cursor:pointer;}
.star-r span{color:#555;transition:color .1s;}
.star-r span.on{color:#facc15;}
</style>
<div class="card">
  <div class="text-center mb-3">
    <i class="bi bi-list-check text-success" style="font-size:2.5rem"></i>
    <h4 class="mt-2"><?= e($q['title']) ?></h4>
    <p class="text-muted small mb-0"><strong><?= e($q['project_name']) ?></strong> &mdash; <?= e($q['order_title']) ?></p>
  </div>
  <?php if ($q['draft_mode'] ?? 1): ?>
  <div class="alert alert-warning py-2 px-3 small mb-3">
    <i class="bi bi-cone-striped me-2"></i><strong>DRAFT</strong> — Dieser Fragebogen befindet sich noch im Entwurfsmodus. Antworten können beim Veröffentlichen gelöscht werden.
  </div>
  <?php endif; ?>
  <?php if (!empty($q['description'])): ?>
  <div class="alert alert-info py-2 px-3 small mb-4">
    <i class="bi bi-info-circle me-2"></i><?= nl2br(e($q['description'])) ?>
  </div>
  <?php endif; ?>
  <form method="POST" action="<?= url('tc-questionnaire/' . $q['qr_token']) ?>" id="qForm">
    <div style="position:absolute;left:-9999px"><input type="text" name="website" tabindex="-1"></div>
    <?php foreach ($questions as $i => $question): ?>
    <div class="q-block">
      <label class="form-label fw-semibold"><?= e($question['text'] ?? 'Frage ' . ($i+1)) ?></label>
      <?php $type = $question['type'] ?? 'text'; ?>
      <?php if ($type === 'rating'): ?>
        <div class="star-r" id="sr<?= (int)$i ?>">
          <?php for ($s = 1; $s <= 5; $s++): ?>
          <span onclick="setStar(<?= (int)$i ?>,<?= (int)$s ?>)" data-v="<?= (int)$s ?>">&#9733;</span>
          <?php endfor; ?>
        </div>
        <input type="hidden" name="q_<?= (int)$i ?>" id="qr<?= (int)$i ?>">
      <?php elseif ($type === 'yesno'): ?>
        <div class="d-flex gap-3">
          <div class="form-check"><input class="form-check-input" type="radio" name="q_<?= (int)$i ?>" value="Ja" id="q<?= $i ?>y"><label class="form-check-label" for="q<?= $i ?>y">Ja</label></div>
          <div class="form-check"><input class="form-check-input" type="radio" name="q_<?= (int)$i ?>" value="Nein" id="q<?= $i ?>n"><label class="form-check-label" for="q<?= $i ?>n">Nein</label></div>
        </div>
      <?php elseif ($type === 'select' && !empty($question['options'])): ?>
        <select name="q_<?= (int)$i ?>" class="form-select">
          <option value="">-- Bitte wählen --</option>
          <?php foreach ($question['options'] as $opt): ?>
          <option><?= e($opt) ?></option>
          <?php endforeach; ?>
        </select>
      <?php else: ?>
        <textarea name="q_<?= (int)$i ?>" class="form-control" rows="3" placeholder="Ihre Antwort..."></textarea>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <hr style="border-color:#333">
    <div class="row g-2 mb-3">
      <div class="col-6"><label class="form-label small">Ihr Name (optional)</label><input type="text" name="respondent_name" class="form-control"></div>
      <div class="col-6"><label class="form-label small">Kontakt (optional)</label><input type="text" name="respondent_contact" class="form-control"></div>
    </div>
    <div class="mt-4 pt-3" style="border-top:1px solid rgba(255,255,255,.15);text-align:center">
  <p style="font-size:.75rem;color:#9ca3af;margin:0">
    <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="currentColor" style="margin-right:4px;vertical-align:-1px" viewBox="0 0 16 16"><path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/></svg>Ihre Angaben werden ausschlie&szlig;lich zur Produktverbesserung genutzt und nicht an Dritte weitergegeben (Art.&nbsp;13 DSGVO).
  </p>
</div>
<button type="submit" class="btn btn-success w-100"><i class="bi bi-send me-2"></i>Fragebogen absenden</button>
  </form>
</div>
<script>
function setStar(qi, val) {
  document.getElementById('qr'+qi).value = val;
  document.querySelectorAll('#sr'+qi+' span').forEach(function(s,i){ s.classList.toggle('on', i < val); });
}
</script>