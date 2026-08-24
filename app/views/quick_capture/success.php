<script>
(function() {
  var lang = localStorage.getItem('qcLang') || 'de';
  var t = {
    de: {
      title:    'Danke!',
      msg:      'Deine Einsendung ist eingegangen und wird von einem Teammitglied geprueft.',
      warn_hdr: 'Hinweis zu Anhaengen:',
      warn_footer: 'Dein Eintrag wurde trotzdem gespeichert, nur die genannte(n) Datei(en) konnten nicht angehaengt werden.',
      btn:      'Weiteren Eintrag erfassen',
    },
    en: {
      title:    'Thank you!',
      msg:      'Your submission has been received and will be reviewed by a team member.',
      warn_hdr: 'Note regarding attachments:',
      warn_footer: 'Your entry was saved successfully ? only the listed file(s) could not be attached.',
      btn:      'Submit another entry',
    }
  };
  var s = t[lang] || t.de;
  document.addEventListener('DOMContentLoaded', function() {
    var el = function(id) { return document.getElementById(id); };
    if (el('qsTitle'))      el('qsTitle').textContent      = s.title;
    if (el('qsMsg'))        el('qsMsg').textContent        = s.msg;
    if (el('qsWarnHdr'))    el('qsWarnHdr').textContent    = s.warn_hdr;
    if (el('qsWarnFooter')) el('qsWarnFooter').textContent = s.warn_footer;
    if (el('qsBtn'))        el('qsBtn').innerHTML          = '<i class="bi bi-plus-lg me-2"></i>' + s.btn;
  });
})();
</script>

<div class="text-center">
    <i class="bi bi-check-circle text-success" style="font-size:3rem"></i>
    <h4 class="mt-3" id="qsTitle">Danke!</h4>
    <p class="text-muted" id="qsMsg">Deine Einsendung ist eingegangen und wird von einem Teammitglied geprueft.</p>

    <?php $uploadWarnings = $uploadWarnings ?? []; ?>
    <?php if ($uploadWarnings): ?>
    <div class="alert alert-warning text-start mt-3">
        <i class="bi bi-exclamation-triangle me-2"></i><strong id="qsWarnHdr">Hinweis zu Anhaengen:</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($uploadWarnings as $w): ?>
            <li><?= e($w) ?></li>
            <?php endforeach; ?>
        </ul>
        <div class="small mt-2 mb-0" id="qsWarnFooter">Dein Eintrag wurde trotzdem gespeichert, nur die genannte(n) Datei(en) konnten nicht angehaengt werden.</div>
    </div>
    <?php endif; ?>

    <a href="<?= url('quick-capture') ?>" class="btn btn-outline-primary mt-2" id="qsBtn">
        <i class="bi bi-plus-lg me-2"></i>Weiteren Eintrag erfassen
    </a>
</div>
