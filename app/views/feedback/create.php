<?php $csrf = Auth::csrfToken(); ?>
<div class="row justify-content-center">
  <div class="col-md-7">
    <div class="card border-secondary">
      <div class="card-header border-secondary">
        <h5 class="mb-0"><i class="bi bi-chat-left-text me-2 text-info"></i>Submit Feedback</h5>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-4">
          Found a bug or have an idea for improvement? Let us know — we'll review it and get back to you.
        </p>
        <form method="POST" action="<?= url('tool-feedback/new') ?>" enctype="multipart/form-data">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <div class="mb-3">
            <label class="form-label">Type</label>
            <div class="d-flex gap-3 flex-wrap">
              <?php foreach (['bug'=>['bi-bug','danger','Bug Report'], 'improvement'=>['bi-lightbulb','warning','Improvement'], 'question'=>['bi-question-circle','info','Question'], 'other'=>['bi-three-dots','secondary','Other']] as $val=>[$icon,$color,$label]): ?>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="type" value="<?= $val ?>"
                       id="type_<?= $val ?>" <?= $val==='bug'?'checked':'' ?>>
                <label class="form-check-label" for="type_<?= $val ?>">
                  <i class="bi <?= $icon ?> text-<?= $color ?> me-1"></i><?= $label ?>
                </label>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" required
                   placeholder="Short description of the issue or idea" maxlength="255">
          </div>
          <div class="mb-4">
            <label class="form-label">Message <span class="text-danger">*</span></label>
            <textarea name="message" class="form-control" rows="6" required
                      placeholder="Please describe in detail. For bugs: steps to reproduce, expected vs actual behaviour."></textarea>
          </div>
          <div class="mb-4 p-3 border border-info rounded">
            <label class="form-label fw-semibold"><i class="bi bi-paperclip me-1 text-info"></i>Attachments <span class="text-muted fw-normal small">(optional, max 5 files)</span></label>
            <input type="file" name="attachments[]" class="form-control" multiple
                   accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt,.log,.mp4,.mov">
            <div class="form-text">Screenshots, logs, or other relevant files.</div>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Submit Feedback</button>
            <a href="<?= url('tool-feedback') ?>" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
