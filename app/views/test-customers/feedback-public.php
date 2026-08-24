<script>
var _fbLang = localStorage.getItem('fbLang') || 'de';
var _fbT = {
  de: {
    title_h:      'Kunden Feedback',
    name_label:   'Dein Name',
    contact_label:'Kontakt fuer Rueckfragen',
    contact_ph:   'E-Mail oder Telefon',
    title_label:  'Titel',
    title_ph:     'Kurze Ueberschrift',
    desc_label:   'Beschreibung',
    desc_ph:      'Was ist aufgefallen? Schritte, Beobachtungen...',
    serial_label: 'Maeher Seriennummer',
    serial_ph:    'z.B. SN-123456',
    fw_label:     'Firmware Version',
    fw_ph:        'z.B. 3.28.4',
    files_label:  'Anhaenge',
    files_hint:   'Max. 5 Dateien, je bis 20 MB.',
    submit:       'Absenden',
    sending:      'Wird gesendet...',
    optional:     'optional',
    lang_btn:     'English',
  },
  en: {
    title_h:      'Customer Feedback',
    name_label:   'Your Name',
    contact_label:'Contact for follow-up',
    contact_ph:   'E-mail or phone',
    title_label:  'Title',
    title_ph:     'Short headline',
    desc_label:   'Description',
    desc_ph:      'What did you notice? Steps, observations...',
    serial_label: 'Mower Serial Number',
    serial_ph:    'e.g. SN-123456',
    fw_label:     'Firmware Version',
    fw_ph:        'e.g. 3.28.4',
    files_label:  'Attachments',
    files_hint:   'Max. 5 files, up to 20 MB each.',
    submit:       'Submit',
    sending:      'Sending...',
    optional:     'optional',
    lang_btn:     'Deutsch',
  }
};
function fbApplyLang() {
  var t = _fbT[_fbLang];
  document.querySelectorAll('[data-fb]').forEach(function(el) {
    var key = el.dataset.fb;
    if (!t[key]) return;
    if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') el.placeholder = t[key];
    else el.textContent = t[key];
  });
  document.getElementById('fbLangBtn').textContent = t.lang_btn;
}
function fbToggleLang() {
  _fbLang = _fbLang === 'de' ? 'en' : 'de';
  localStorage.setItem('fbLang', _fbLang);
  fbApplyLang();
}
document.addEventListener('DOMContentLoaded', fbApplyLang);
</script>

<div class="d-flex justify-content-between align-items-start mb-1">
  <h4 class="mb-0" data-fb="title_h">Kunden Feedback</h4>
  <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" id="fbLangBtn" onclick="fbToggleLang()">English</button>
</div>
<p class="text-muted small mb-1">
  <strong><?= e($order['project_name']) ?></strong> &mdash; <?= e($order['title']) ?>
</p>
<?php if (!empty($order['feedback_instructions'])): ?>
<div class="alert alert-info py-2 px-3 small mb-3">
  <i class="bi bi-info-circle me-2"></i><?= nl2br(e($order['feedback_instructions'])) ?>
</div>
<?php endif; ?>

<form method="POST" action="<?= isset($respondent) ? e(url('tc-respondent/' . $respondent['token'])) : e(url('tc-feedback/' . $order['qr_token'])) ?>"
      enctype="multipart/form-data" id="fbForm">

  <div style="position:absolute;left:-9999px" aria-hidden="true">
    <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
  </div>

  <div class="mb-3">
    <label class="form-label">
      <span data-fb="title_label">Titel</span> <span class="text-danger">*</span>
    </label>
    <input type="text" name="title" class="form-control" required maxlength="200"
           data-fb="title_ph" placeholder="Kurze Ueberschrift">
  </div>

  <div class="mb-3">
    <label class="form-label" data-fb="desc_label">Beschreibung</label>
    <textarea name="description" class="form-control" rows="4" maxlength="5000"
              data-fb="desc_ph" placeholder="Was ist aufgefallen?"></textarea>
  </div>

  <div class="row g-2 mb-3">
    <div class="col-6">
      <label class="form-label">
        <span data-fb="serial_label">Maeher Seriennummer</span>
        <span class="text-muted small">(<span data-fb="optional">optional</span>)</span>
      </label>
      <input type="text" name="mower_serial" class="form-control" maxlength="100"
             data-fb="serial_ph" placeholder="z.B. SN-123456">
    </div>
    <div class="col-6">
      <label class="form-label">
        <span data-fb="fw_label">Firmware Version</span>
        <span class="text-muted small">(<span data-fb="optional">optional</span>)</span>
      </label>
      <input type="text" name="firmware_version" class="form-control" maxlength="50"
             data-fb="fw_ph" placeholder="z.B. 3.28.4">
    </div>
  </div>

  <div class="mb-3">
    <label class="form-label">
      <span data-fb="files_label">Anhaenge</span>
      <span class="text-muted small">(<span data-fb="optional">optional</span>)</span>
    </label>
    <input type="file" name="files[]" class="form-control" multiple
           accept="image/*,video/*,application/pdf">
    <div class="form-text" data-fb="files_hint">Max. 5 Dateien, je bis 20 MB.</div>
  </div>

  <hr class="border-secondary">

  <?php if (!isset($respondent)): ?>
  <div class="mb-3">
    <label class="form-label">
      <span data-fb="name_label">Dein Name</span>
      <span class="text-muted small">(<span data-fb="optional">optional</span>)</span>
    </label>
    <input type="text" name="respondent_name" class="form-control" maxlength="150">
  </div>

  <div class="mb-3">
    <label class="form-label">
      <span data-fb="contact_label">Kontakt fuer Rueckfragen</span>
      <span class="text-muted small">(<span data-fb="optional">optional</span>)</span>
    </label>
    <input type="text" name="respondent_contact" class="form-control" maxlength="200"
           data-fb="contact_ph" placeholder="E-Mail oder Telefon">
  </div>
  <?php endif; ?>

  <button type="submit" class="btn btn-primary w-100" id="fbSubmitBtn">
    <i class="bi bi-send me-2"></i><span data-fb="submit">Absenden</span>
  </button>
  <div id="fbUploadHint" class="text-muted small text-center mt-2" style="display:none">
    Wird gesendet - bitte nicht schliessen.
  </div>
  <div class="mt-4 pt-3" style="border-top:1px solid rgba(255,255,255,.15);text-align:center">
    <p style="font-size:.75rem;color:#9ca3af;margin:0">
      <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="currentColor" style="margin-right:4px;vertical-align:-1px" viewBox="0 0 16 16"><path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/></svg>Ihre Angaben werden ausschlie&szlig;lich zur Produktverbesserung genutzt und nicht an Dritte weitergegeben (Art.&nbsp;13 DSGVO).
    </p>
  </div>
</form>

<script>
(function() {
  var form = document.getElementById('fbForm');
  if (!form) return;
  var submitted = false;
  form.addEventListener('submit', function(e) {
    if (submitted) { e.preventDefault(); return; }
    submitted = true;
    var t = _fbT[_fbLang];
    var btn = document.getElementById('fbSubmitBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + t.sending; }
    if (form.querySelector('input[name="files[]"]')?.files?.length > 0) {
      var hint = document.getElementById('fbUploadHint');
      if (hint) hint.style.display = '';
    }
  });
  if (typeof fbApplyLang === 'function') fbApplyLang();
})();
</script>
