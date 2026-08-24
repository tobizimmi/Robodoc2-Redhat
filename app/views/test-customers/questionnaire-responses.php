<div class="mb-4">
  <a href="<?= url('test-customers/' . $order['id']) ?>" class="text-muted small">
    <i class="bi bi-arrow-left me-1"></i><?= e($order['title']) ?>
  </a>
  <h5 class="mt-1 mb-0"><i class="bi bi-bar-chart me-2 text-success"></i><?= e($q['title']) ?></h5>
  <div class="d-flex align-items-center gap-2 mt-1">
  <p class="text-muted small mb-0"><?= count($responses) ?> Antworten</p>
  <?php if ($q['draft_mode'] ?? 1): ?>
  <span class="badge bg-warning text-dark">DRAFT — Antworten sind Testdaten</span>
  <?php endif; ?>
</div>
</div>

<?php if (empty($responses)): ?>
<div class="text-center text-muted py-5">
  <i class="bi bi-inbox" style="font-size:3rem;opacity:.3"></i>
  <p class="mt-3">Noch keine Antworten.</p>
</div>
<?php else: ?>

<!-- Summary per question -->
<div class="card border-secondary mb-4">
  <div class="card-header border-secondary fw-semibold">
    <i class="bi bi-pie-chart me-2"></i>Zusammenfassung
  </div>
  <div class="card-body">
    <?php foreach ($q['questions'] as $qi => $question): ?>
    <div class="mb-5">
      <div class="fw-semibold mb-2"><?= ($qi+1) ?>. <?= e($question['text'] ?? '') ?></div>
      <?php
        $type = $question['type'] ?? 'text';
        $allAnswers = [];
        foreach ($responses as $r) {
            $ans = $r['answers'][$qi] ?? '';
            if ($ans !== '') $allAnswers[] = ['id' => $r['id'], 'name' => $r['respondent_name'] ?? '', 'val' => $ans];
        }
      ?>

      <?php if ($type === 'rating'): ?>
        <?php
          $vals = array_column($allAnswers, 'val');
          $avg  = count($vals) ? array_sum($vals) / count($vals) : 0;
        ?>
        <div class="d-flex align-items-center gap-3 mb-3">
          <span class="text-warning fs-5"><?= str_repeat('★', round($avg)) ?><?= str_repeat('☆', 5-round($avg)) ?></span>
          <span class="fw-semibold"><?= number_format($avg, 1) ?> / 5</span>
          <span class="text-muted small">(<?= count($allAnswers) ?> Antworten)</span>
        </div>
        <!-- Individual ratings with ID -->
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($allAnswers as $a): ?>
          <a href="#resp-<?= $a['id'] ?>" class="text-decoration-none">
            <span class="badge border border-secondary text-muted py-1 px-2" style="font-size:.75rem"
                  title="<?= e($a['name'] ?: 'Anonym') ?>">
              #<?= $a['id'] ?>
              <span class="text-warning ms-1"><?= str_repeat('★', (int)$a['val']) ?></span>
            </span>
          </a>
          <?php endforeach; ?>
        </div>

      <?php elseif ($type === 'yesno' || $type === 'select'): ?>
        <?php $counts = array_count_values(array_column($allAnswers, 'val')); arsort($counts); ?>
        <div class="d-flex flex-column gap-2 mb-3">
          <?php foreach ($counts as $val => $cnt): ?>
          <?php $pct = round($cnt / count($allAnswers) * 100); ?>
          <div class="d-flex align-items-center gap-2">
            <span class="text-muted" style="min-width:120px;font-size:.85rem"><?= e($val) ?></span>
            <div class="progress flex-grow-1" style="height:8px">
              <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
            </div>
            <span class="text-muted small"><?= $cnt ?> (<?= $pct ?>%)</span>
          </div>
          <?php endforeach; ?>
        </div>
        <!-- IDs per option -->
        <?php
          $byVal = [];
          foreach ($allAnswers as $a) $byVal[$a['val']][] = $a;
        ?>
        <div class="d-flex flex-column gap-1">
          <?php foreach ($byVal as $val => $entries): ?>
          <div class="d-flex align-items-start gap-2 flex-wrap">
            <span class="text-muted small" style="min-width:120px"><?= e($val) ?>:</span>
            <div class="d-flex flex-wrap gap-1">
              <?php foreach ($entries as $a): ?>
              <a href="#resp-<?= $a['id'] ?>" class="text-decoration-none">
                <span class="badge border border-secondary text-muted py-0 px-1" style="font-size:.7rem"
                      title="<?= e($a['name'] ?: 'Anonym') ?>">#<?= $a['id'] ?></span>
              </a>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

      <?php else: /* Freitext */ ?>
        <div class="text-muted small mb-2"><?= count($allAnswers) ?> Textantworten</div>
        <!-- Show all text answers with ID directly -->
        <div class="d-flex flex-column gap-2">
          <?php foreach ($allAnswers as $a): ?>
          <div class="d-flex gap-2 align-items-start">
            <a href="#resp-<?= $a['id'] ?>" class="text-decoration-none flex-shrink-0">
              <span class="badge border border-secondary text-muted" style="font-size:.7rem;white-space:nowrap"
                    title="<?= e($a['name'] ?: 'Anonym') ?>">#<?= $a['id'] ?></span>
            </a>
            <span class="small text-light"><?= e($a['val']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Individual responses -->
<h6 class="mb-3">Einzelne Antworten</h6>
<div class="d-flex flex-column gap-3">
  <?php foreach ($responses as $r): ?>
  <div class="card border-secondary" id="resp-<?= $r['id'] ?>">
    <div class="card-header border-secondary d-flex align-items-center gap-2 py-2">
      <span class="badge bg-secondary">#<?= $r['id'] ?></span>
      <i class="bi bi-person text-muted"></i>
      <span class="small fw-semibold"><?= e($r['respondent_name'] ?: 'Anonym') ?></span>
      <?php if ($r['respondent_contact']): ?>
      <span class="text-muted small">&middot; <?= e($r['respondent_contact']) ?></span>
      <?php endif; ?>
      <span class="ms-auto text-muted small"><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></span>
    </div>
    <div class="card-body py-2">
      <?php foreach ($q['questions'] as $qi => $question): ?>
      <?php $ans = $r['answers'][$qi] ?? ''; if ($ans === '') continue; ?>
      <div class="mb-2">
        <div class="text-muted small mb-1"><?= e($question['text'] ?? '') ?></div>
        <?php if (($question['type'] ?? '') === 'rating'): ?>
        <span class="text-warning"><?= str_repeat('★', (int)$ans) ?><?= str_repeat('☆', 5-(int)$ans) ?></span>
        <span class="text-muted small ms-1">(<?= $ans ?>)</span>
        <?php else: ?>
        <div class="small"><?= e($ans) ?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
