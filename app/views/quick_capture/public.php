<script>
// Language toggle
var _qcLang = localStorage.getItem('qcLang') || 'de';
var _qcT = {
  de: {
    title_h:        'Quick Capture',
    subtitle:       'Schnell etwas festhalten - kein Login noetig. Ein Teammitglied prueft die Einsendung und uebernimmt sie.',
    project_label:  'Projekt / Bezug',
    project_ph:     'Zu welchem Projekt / Roboter / Bereich gehoert das?',
    project_hint:   'Pflichtfeld - hilft dem Team bei der Zuordnung.',
    title_label:    'Titel',
    title_ph:       'Kurze Ueberschrift',
    desc_label:     'Beschreibung',
    desc_ph:        'Was ist passiert? Schritte, Beobachtungen ...',
    serial_label:   'Maeher Seriennummer',
    serial_ph:      'z.B. SN-123456',
    fw_label:       'Firmware Version',
    fw_ph:          'z.B. 3.28.4',
    files_label:    'Anhaenge',
    files_hint:     'Max. 5 Dateien, je bis 500 MB.',
    name_label:     'Dein Name',
    contact_label:  'Kontakt fuer Rueckfragen',
    contact_ph:     'E-Mail oder Telefon',
    submit:         'Absenden',
    sending:        'Wird gesendet...',
    upload_hint:    'Wird gesendet - bei Anhaengen kann das etwas dauern. Bitte nicht schliessen.',
    optional:       'optional',
    lang_btn:       'English',
  },
  en: {
    title_h:        'Quick Capture',
    subtitle:       'Quickly log something - no login required. A team member will review and process your submission.',
    project_label:  'Project / Reference',
    project_ph:     'Which project / robot / area does this relate to?',
    project_hint:   'Required - helps the team with assignment.',
    title_label:    'Title',
    title_ph:       'Short headline',
    desc_label:     'Description',
    desc_ph:        'What happened? Steps, observations ...',
    serial_label:   'Mower Serial Number',
    serial_ph:      'e.g. SN-123456',
    fw_label:       'Firmware Version',
    fw_ph:          'e.g. 3.28.4',
    files_label:    'Attachments',
    files_hint:     'Max. 5 files, up to 500 MB each.',
    name_label:     'Your Name',
    contact_label:  'Contact for follow-up questions',
    contact_ph:     'E-mail or phone',
    submit:         'Submit',
    sending:        'Sending...',
    upload_hint:    'Sending - with attachments this may take a moment. Please do not close this page.',
    optional:       'optional',
    lang_btn:       'Deutsch',
  }
};
function qcApplyLang() {
  var t = _qcT[_qcLang];
  document.querySelectorAll('[data-qc]').forEach(function(el) {
    var key = el.dataset.qc;
    if (!t[key]) return;
    if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
      el.placeholder = t[key];
    } else {
      el.textContent = t[key];
    }
  });
  document.getElementById('qcLangBtn').textContent = t.lang_btn;
}
function qcToggleLang() {
  _qcLang = _qcLang === 'de' ? 'en' : 'de';
  localStorage.setItem('qcLang', _qcLang);
  qcApplyLang();
}
document.addEventListener('DOMContentLoaded', qcApplyLang);
</script>

<div class="d-flex justify-content-between align-items-start mb-1">
  <h4 class="mb-0" data-qc="title_h">Quick Capture</h4>
    <p class="text-muted small mb-3" style="font-size:.75rem">
    <i class="bi bi-shield-lock me-1"></i>
    Ihre Angaben werden ausschließlich zur Produktverbesserung verwendet und nicht an Dritte weitergegeben.
  </p>
<button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" id="qcLangBtn" onclick="qcToggleLang()">English</button>
</div>
<p class="text-muted small mb-3" data-qc="subtitle">Schnell etwas festhalten - kein Login noetig. Ein Teammitglied prueft die Einsendung und uebernimmt sie.</p>

<form method="post" action="<?= url('quick-capture') ?>" enctype="multipart/form-data" id="qcForm">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <!-- Honeypot -->
    <div style="position:absolute;left:-9999px" aria-hidden="true">
        <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>

    <div class="mb-3">
        <label class="form-label"><span data-qc="project_label">Projekt / Bezug</span> <span class="text-danger">*</span></label>
        <input type="text" name="project_hint" class="form-control" required maxlength="255"
               data-qc="project_ph" placeholder="Zu welchem Projekt / Roboter / Bereich gehoert das?">
        <div class="form-text" data-qc="project_hint">Pflichtfeld - hilft dem Team bei der Zuordnung.</div>
    </div>

    <div class="mb-3">
        <label class="form-label"><span data-qc="title_label">Titel</span> <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control" required maxlength="200"
               data-qc="title_ph" placeholder="Kurze Ueberschrift">
    </div>

    <div class="mb-3">
        <label class="form-label" data-qc="desc_label">Beschreibung</label>
        <textarea name="description" class="form-control" rows="4" maxlength="5000"
                  data-qc="desc_ph" placeholder="Was ist passiert? Schritte, Beobachtungen ..."></textarea>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6">
            <label class="form-label"><span data-qc="serial_label">Maeher Seriennummer</span> <span class="text-muted small">(<span data-qc="optional">optional</span>)</span></label>
            <input type="text" name="mower_serial" class="form-control" maxlength="100"
                   data-qc="serial_ph" placeholder="z.B. SN-123456">
        </div>
        <div class="col-6">
            <label class="form-label"><span data-qc="fw_label">Firmware Version</span> <span class="text-muted small">(<span data-qc="optional">optional</span>)</span></label>
            <input type="text" name="firmware_version" class="form-control" maxlength="50"
                   data-qc="fw_ph" placeholder="z.B. 3.28.4">
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label"><span data-qc="files_label">Anhaenge</span> <span class="text-muted small">(<span data-qc="optional">optional</span>)</span></label>
        <input type="file" name="files[]" class="form-control" multiple
               accept="image/*,video/*,application/pdf">
        <div class="form-text" data-qc="files_hint">Max. 5 Dateien, je bis 500 MB.</div>
    </div>

    <hr class="border-secondary">

    <div class="mb-3">
        <label class="form-label"><span data-qc="name_label">Dein Name</span> <span class="text-muted small">(<span data-qc="optional">optional</span>)</span></label>
        <input type="text" name="reporter_name" class="form-control" maxlength="150">
    </div>

    <div class="mb-3">
        <label class="form-label"><span data-qc="contact_label">Kontakt fuer Rueckfragen</span> <span class="text-muted small">(<span data-qc="optional">optional</span>)</span></label>
        <input type="text" name="reporter_contact" class="form-control" maxlength="200"
               data-qc="contact_ph" placeholder="E-Mail oder Telefon">
    </div>


  <div class="mt-3 pt-3" style="border-top:1px solid rgba(255,255,255,.1)">
    <p class="text-muted mb-0" style="font-size:.75rem">
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="me-1" viewBox="0 0 16 16"><path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/></svg>
      <span data-fb="privacy">Ihre Angaben werden ausschlie&szlig;lich zur Produktverbesserung genutzt und nicht an Dritte weitergegeben. (Art. 13 DSGVO)</span>
    </p>
  </div>
    <button type="submit" class="btn btn-primary w-100" id="qcSubmitBtn">
        <i class="bi bi-send me-2"></i><span data-qc="submit">Absenden</span>
    </button>
    <div id="qcUploadHint" class="text-muted small text-center mt-2" style="display:none" data-qc="upload_hint">
        Wird gesendet - bei Anhaengen kann das etwas dauern. Bitte nicht schliessen.
    </div>
</form>
<script>
(function() {
    var form = document.getElementById('qcForm');
    if (!form) return;
    var submitted = false;
    form.addEventListener('submit', function(e) {
        if (submitted) { e.preventDefault(); return; }
        submitted = true;
        var t = _qcT[_qcLang];
        var btn = document.getElementById('qcSubmitBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + t.sending;
        }
        var hasFiles = form.querySelector('input[name="files[]"]')?.files?.length > 0;
        if (hasFiles) {
            var hint = document.getElementById('qcUploadHint');
            if (hint) hint.style.display = '';
        }
    });
    // Apply language on load
    if (typeof qcApplyLang === 'function') qcApplyLang();
})();
</script>
