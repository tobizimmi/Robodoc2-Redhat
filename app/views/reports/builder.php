<?php
$csrf    = Auth::csrfToken();
$saveUrl = url('reports/templates/save');
$baseUrl = url('reports/templates/');
$repUrl  = url('reports');
?>

<!-- ── Page header ─────────────────────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h5 class="mb-0"><i class="bi bi-layout-text-window me-2 text-primary"></i>Report Builder</h5>
    <p class="text-muted small mb-0">Templates anlegen · Berichte generieren</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= $repUrl ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-bar-chart me-1"></i>Klassische Reports
    </a>
    <button class="btn btn-primary btn-sm" id="rdBtnNew">
      <i class="bi bi-plus-lg me-1"></i>Neues Template
    </button>
  </div>
</div>

<!-- ── Template cards ──────────────────────────────────────────────────────── -->
<div id="rdTplList">
<?php if (empty($templates)): ?>
<div class="text-center py-5 border border-secondary rounded text-muted">
  <i class="bi bi-layout-text-window" style="font-size:3rem;opacity:.2"></i>
  <p class="mt-3 mb-3">Noch keine Templates. Erstelle dein erstes Template.</p>
  <button class="btn btn-primary" id="rdBtnNew2">
    <i class="bi bi-plus-lg me-1"></i>Template erstellen
  </button>
</div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($templates as $tpl): ?>
  <?php $cfg = json_decode($tpl['config'] ?? '{}', true) ?: []; ?>
  <div class="col-md-4">
    <div class="card border-secondary h-100">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div>
            <h6 class="mb-1"><?= e($tpl['name']) ?></h6>
            <p class="text-muted small mb-1"><?= e($tpl['description'] ?: '–') ?></p>
          </div>
          <span class="badge bg-secondary ms-2" style="font-size:9px">
            <?= count($cfg['blocks'] ?? []) ?> Blöcke
          </span>
        </div>
        <div class="text-muted small mt-1">
          <i class="bi bi-clock me-1"></i><?= date('d.m.Y', strtotime($tpl['created_at'])) ?>
          <?php if (!empty($cfg['preview']['project_id'])): ?>
          &nbsp;·&nbsp;<i class="bi bi-eye me-1"></i>Vorschau-Projekt konfiguriert
          <?php endif; ?>
        </div>
      </div>
      <div class="card-footer border-secondary">
        <!-- Bericht erstellen — primary action -->
        <button class="btn btn-primary btn-sm w-100 mb-2 rd-gen-btn"
                data-id="<?= $tpl['id'] ?>"
                data-name="<?= e($tpl['name']) ?>">
          <i class="bi bi-file-earmark-bar-graph me-1"></i>Bericht erstellen
        </button>
        <div class="d-flex gap-1">
          <button class="btn btn-outline-secondary btn-sm flex-grow-1 rd-edit-btn"
                  data-editid="<?= $tpl['id'] ?>"
                  data-editname="<?= e($tpl['name']) ?>"
                  data-editdesc="<?= e($tpl['description'] ?? '') ?>"
                  data-editcfg="<?= base64_encode($tpl['config'] ?? '{}') ?>"
                  title="Template bearbeiten">
            <i class="bi bi-pencil me-1"></i>Template bearbeiten
          </button>
          <form method="POST" action="<?= $baseUrl.$tpl['id'].'/delete' ?>"
                onsubmit="return confirm('Template löschen?')" class="d-inline">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <button class="btn btn-outline-danger btn-sm" title="Löschen">
              <i class="bi bi-trash"></i>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<!-- ── "Bericht erstellen" modal ───────────────────────────────────────────── -->
<div class="modal fade" id="rdGenModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title">
          <i class="bi bi-file-earmark-bar-graph me-2 text-primary"></i>
          Bericht erstellen: <span id="rdGenTplName"></span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="rdGenForm" method="GET" target="_blank">
        <div class="modal-body">
          <p class="text-muted small mb-3">
            Wähle Projekt und Zeitraum für diesen Bericht. Die Struktur und das Layout kommen aus dem Template.
          </p>
          <div class="row g-3">
            <div class="col-md-12">
              <label class="form-label">Projekt <span class="text-danger">*</span></label>
              <select name="project_id" id="rdGenProject" class="form-select">
                <option value="">Alle Projekte</option>
                <?php foreach ($projects as $p): ?>
                <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Datum von</label>
              <input type="date" name="date_from" id="rdGenFrom" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Datum bis</label>
              <input type="date" name="date_to" id="rdGenTo" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label">Eintragstypen <span class="text-muted small">(leer = alle)</span></label>
              <div class="d-flex flex-wrap gap-2">
                <?php foreach ($entryTypes as $et): ?>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="type_ids[]"
                         value="<?= $et['id'] ?>" id="rgg<?= $et['id'] ?>">
                  <label class="form-check-label small" for="rgg<?= $et['id'] ?>">
                    <?= e($et['name']) ?>
                  </label>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <hr class="border-secondary mt-3 mb-2">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="rdGenAutoprint">
            <label class="form-check-label small text-muted" for="rdGenAutoprint">
              PDF-Druckdialog automatisch öffnen
            </label>
          </div>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-file-earmark-bar-graph me-1"></i>Bericht generieren
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Full-screen Template Editor ─────────────────────────────────────────── -->
<div id="rdEditor" style="display:none;position:fixed;inset:0;z-index:2000;background:#111;flex-direction:column">

  <!-- Topbar -->
  <div style="background:#1a1d23;border-bottom:1px solid #2d3748;padding:10px 16px;display:flex;align-items:center;gap:10px;flex-shrink:0">
    <button class="btn btn-outline-secondary btn-sm" id="rdClose">
      <i class="bi bi-x-lg"></i> Schließen
    </button>
    <div style="width:2px;height:24px;background:#2d3748;flex-shrink:0"></div>
    <input type="text" id="rdName" class="form-control form-control-sm" style="max-width:220px" placeholder="Template-Name *">
    <input type="text" id="rdDesc" class="form-control form-control-sm" style="max-width:280px" placeholder="Beschreibung (optional)">
    <div class="ms-auto d-flex gap-2">
      <button class="btn btn-outline-info btn-sm" id="rdPreviewBtn" title="Vorschau mit Beispieldaten">
        <i class="bi bi-eye me-1"></i>Vorschau
      </button>
      <button class="btn btn-success btn-sm" id="rdSaveBtn">
        <i class="bi bi-floppy me-1"></i>Speichern
      </button>
    </div>
  </div>

  <!-- Body: 3 columns -->
  <div style="display:flex;flex:1;overflow:hidden;min-height:0">

    <!-- LEFT: Palette + Config -->
    <div style="width:280px;flex-shrink:0;background:#1a1d23;border-right:1px solid #2d3748;overflow-y:auto">
      <div style="padding:12px">

        <!-- Block palette -->
        <div class="rd-section-title">Blöcke hinzufügen</div>
        <div id="rdPalette"></div>

        <hr class="rd-hr">

        <!-- Branding -->
        <div class="rd-section-title">Branding</div>
        <label class="rd-lbl">Logo URL</label>
        <input type="text" id="rdLogo" class="form-control form-control-sm mb-2" placeholder="https://...">
        <label class="rd-lbl">Primärfarbe</label>
        <div class="d-flex gap-2 mb-2">
          <input type="color" id="rdColor" class="form-control form-control-color form-control-sm" value="#1e3a5f" style="width:36px;padding:2px">
          <input type="text" id="rdColorHex" class="form-control form-control-sm" value="#1e3a5f" style="font-family:monospace">
        </div>
        <label class="rd-lbl">Schriftart</label>
        <select id="rdFont" class="form-select form-select-sm mb-2">
          <option>Arial</option><option>Helvetica</option><option>Georgia</option><option>Calibri</option>
        </select>

        <hr class="rd-hr">

        <!-- Report Header -->
        <div class="rd-section-title">Report Header</div>
        <input type="text" id="rdHTitle" class="form-control form-control-sm mb-2" placeholder="Berichtstitel (z.B. Test Report)">
        <input type="text" id="rdHSub"   class="form-control form-control-sm mb-2" placeholder="Untertitel (z.B. Pixie SILENO)">
        <div class="d-flex align-items-center gap-2 mb-2">
          <input type="color" id="rdHBg" class="form-control form-control-color form-control-sm" value="#1e3a5f" style="width:36px;padding:2px">
          <span class="rd-lbl mb-0">Header-Hintergrundfarbe</span>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="rdHDate" checked>
          <label class="form-check-label" style="font-size:12px;color:#d1d5db" for="rdHDate">Erstelldatum anzeigen</label>
        </div>
        <hr class="rd-hr">
        <div class="rd-section-title"><i class="bi bi-phone-landscape me-1"></i>Seitenformat</div>
        <select id="rdOrientation" class="form-select form-select-sm mb-2">
          <option value="portrait">Hochformat (A4)</option>
          <option value="landscape">Querformat (A4)</option>
        </select>
        <p style="font-size:10px;color:#6b7280;margin:0">Gilt für den gesamten Bericht. Einzelne Blöcke können überschrieben werden.</p>

        <hr class="rd-hr">

        <!-- Preview filters (for template preview only) -->
        <div class="rd-section-title" title="Nur für die Vorschau — beim Bericht generieren wählst du die echten Daten">
          <i class="bi bi-eye me-1 text-info"></i>Vorschau-Daten
          <span class="badge bg-info ms-1" style="font-size:8px">nur Vorschau</span>
        </div>
        <p style="font-size:10px;color:#6b7280;margin-bottom:8px">Diese Einstellungen gelten nur für die Template-Vorschau. Beim Bericht generieren wählst du die echten Daten.</p>
        <label class="rd-lbl">Beispiel-Projekt</label>
        <select id="rdPvProject" class="form-select form-select-sm mb-2">
          <option value="">Kein Beispiel-Projekt</option>
          <?php foreach ($projects as $p): ?>
          <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="row g-1 mb-2">
          <div class="col-6"><label class="rd-lbl">Von</label><input type="date" id="rdPvFrom" class="form-control form-control-sm"></div>
          <div class="col-6"><label class="rd-lbl">Bis</label><input type="date" id="rdPvTo"   class="form-control form-control-sm"></div>
        </div>
        <label class="rd-lbl">Eintragstypen</label>
        <div style="max-height:90px;overflow-y:auto;margin-bottom:8px">
          <?php foreach ($entryTypes as $et): ?>
          <div class="form-check py-0">
            <input class="form-check-input rd-pv-etype" type="checkbox" value="<?= $et['id'] ?>" id="rdpve<?= $et['id'] ?>">
            <label class="form-check-label" style="font-size:11px;color:#d1d5db" for="rdpve<?= $et['id'] ?>"><?= e($et['name']) ?></label>
          </div>
          <?php endforeach; ?>
        </div>

        <hr class="rd-hr">

        <!-- Footer -->
        <div class="rd-section-title">Footer</div>
        <input type="text" id="rdFooter" class="form-control form-control-sm mb-2" placeholder="z.B. Vertraulich · Husqvarna Group">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="rdFPage" checked>
          <label class="form-check-label" style="font-size:12px;color:#d1d5db" for="rdFPage">Seitenzahl anzeigen</label>
        </div>

      </div>
    </div>

    <!-- CENTER: Canvas -->
    <div style="flex:1;background:#1e2028;overflow-y:auto;padding:20px;min-width:0"
         ondragover="event.preventDefault()" ondrop="rdOnDrop(event)">
      <div id="rdDropHint" style="border:2px dashed #374151;border-radius:8px;padding:40px 20px;text-align:center;color:#6b7280;margin-bottom:12px">
        <i class="bi bi-arrow-left" style="font-size:1.5rem;display:block;margin-bottom:8px"></i>
        <p class="mb-0" style="font-size:13px">Klicke auf einen Block links oder ziehe ihn hierher</p>
      </div>
      <div id="rdCanvas"></div>
    </div>

    <!-- RIGHT: Live preview -->
    <div style="width:340px;flex-shrink:0;background:#f8f9fa;border-left:1px solid #dee2e6;display:flex;flex-direction:column">
      <div style="background:#1e3a5f;color:#fff;padding:8px 14px;font-size:11px;font-weight:700;flex-shrink:0;display:flex;align-items:center;gap:6px">
        LIVE-VORSCHAU
        <span style="font-size:9px;opacity:.7;margin-left:auto">mit Vorschau-Daten</span>
      </div>
      <div id="rdPreview" style="padding:12px;font-size:11px;overflow-y:auto;flex:1"></div>
    </div>

  </div>
</div>

<style>
.rd-section-title{font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px}
.rd-lbl{font-size:11px;color:#9ca3af;display:block;margin-bottom:3px}
.rd-hr{border-color:#2d3748;margin:12px 0}
.rd-pal-item{display:flex;align-items:center;gap:8px;padding:8px 10px;background:#262b36;border:1px solid #374151;border-radius:6px;cursor:pointer;margin-bottom:6px;user-select:none;transition:border-color .15s}
.rd-pal-item:hover{border-color:#6366f1;background:#2d3348}
.rd-cv-block{background:#262b36;border:1px solid #374151;border-radius:8px;margin-bottom:10px}
.rd-cv-hdr{display:flex;align-items:center;gap:8px;padding:9px 12px;border-bottom:1px solid #2d3748;cursor:grab}
.rd-cv-hdr:active{cursor:grabbing}
.rd-cv-block.drag-over{border-color:#6366f1;box-shadow:0 0 0 2px rgba(99,102,241,.2)}
.rd-cv-body{padding:12px}
</style>

<script>
var _rdId     = null;
var _rdBlocks = [];
var _rdDragSrc  = null;
var _rdDragType = null;

var RD_STATUSES    = <?= json_encode(entryStatuses()) ?>;
var RD_PRIORITIES  = ['Blocker','Highest','High','Medium','Low'];
var RD_ENTRY_TYPES = <?= json_encode(array_values(array_column($entryTypes, 'name'))) ?>;
// Block types whose content is built from the entry list, so a per-block
// filter (status/priority/type) makes sense on them — project_header/text/
// divider/page_break don't consume entries directly.
var RD_FILTERABLE = {summary:1,chart_type:1,chart_status:1,chart_priority:1,chart_firmware:1,table:1,top_issues:1,timeline:1};

var RD_DEFS = {
  project_header: {l:'Projektinfo-Header',    i:'bi-building',             c:'#6366f1', d:'Projektname, Status, Beschreibung'},
  summary:        {l:'Kennzahlen',             i:'bi-grid-1x2',             c:'#0ea5e9', d:'Gesamt, offen, erledigt, Typen'},
  chart_type:     {l:'Chart: Nach Typ',        i:'bi-pie-chart',            c:'#10b981', d:'Balken nach Eintragstyp'},
  chart_status:   {l:'Chart: Nach Status',     i:'bi-bar-chart',            c:'#f59e0b', d:'Statusverteilung'},
  chart_priority: {l:'Chart: Priorität',       i:'bi-exclamation-triangle', c:'#ef4444', d:'Prioritätsverteilung'},
  chart_firmware: {l:'Chart: Firmware',        i:'bi-cpu',                  c:'#8b5cf6', d:'Gruppiert nach Firmware-Version'},
  table:          {l:'Eintrags-Tabelle',        i:'bi-table',                c:'#0d6efd', d:'Konfigurierbare Eintrags-Tabelle'},
  top_issues:     {l:'Top Issues',             i:'bi-fire',                 c:'#f97316', d:'Wichtigste Einträge nach Priorität'},
  timeline:       {l:'Timeline',               i:'bi-calendar-week',        c:'#06b6d4', d:'Chronologische Ansicht'},
  text:           {l:'Textblock',              i:'bi-text-paragraph',       c:'#6b7280', d:'Freier Textabsatz'},
  divider:        {l:'Trennlinie',             i:'bi-dash-lg',              c:'#4b5563', d:'Horizontale Trennlinie'},
  page_break:     {l:'Seitenumbruch',          i:'bi-file-break',           c:'#4b5563', d:'Umbruch beim PDF-Druck'},
};

function rdGv(id){ var e=document.getElementById(id); return e?e.value:''; }
function rdGc(id){ var e=document.getElementById(id); return e?e.checked:false; }
function rdSv(id,v){ var e=document.getElementById(id); if(e)e.value=(v!=null?v:''); }
function rdSc(id,v){ var e=document.getElementById(id); if(e)e.checked=!!v; }
function rdEsc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// ── Open / Close ──────────────────────────────────────────────────────────────
function rdEditorOpen(id, cfgJson, fbName, fbDesc) {
  _rdId = id || null;
  _rdBlocks = [];
  rdReset();
  if (cfgJson && cfgJson.length > 2) {
    try {
      var decoded = cfgJson;
      try { decoded = atob(cfgJson); } catch(e) {}
      var cfg = JSON.parse(decoded);
      rdLoadCfg(cfg);
      _rdBlocks = (cfg.blocks||[]).map(function(b){
        return {type:b.type, id:b.id||Date.now()+Math.random(), cfg:b.cfg||b.config||{}};
      });
    } catch(err) { console.error('Config parse error:', err); }
  }
  if (fbName) rdSv('rdName', fbName);
  if (fbDesc) rdSv('rdDesc', fbDesc);
  rdBuildPalette();
  rdRenderCanvas();
  rdUpdatePreview();
  var ed = document.getElementById('rdEditor');
  ed.style.display = 'flex';
  ed.style.flexDirection = 'column';
  document.body.style.overflow = 'hidden';
}

function rdEditorClose() {
  document.getElementById('rdEditor').style.display = 'none';
  document.body.style.overflow = '';
}

function rdReset() {
  ['rdName','rdDesc','rdLogo','rdHTitle','rdHSub','rdFooter','rdOrientation',
   'rdPvProject','rdPvFrom','rdPvTo'].forEach(function(id){ rdSv(id,''); });
  rdSv('rdColor','#1e3a5f'); rdSv('rdColorHex','#1e3a5f'); rdSv('rdFont','Arial');
  rdSv('rdHBg','#1e3a5f');
  rdSc('rdHDate',true); rdSc('rdFPage',true);
  document.querySelectorAll('.rd-pv-etype').forEach(function(cb){cb.checked=false;});
}

function rdLoadCfg(cfg) {
  rdSv('rdName',     cfg.name);
  rdSv('rdDesc',     cfg.description);
  var b=cfg.branding||{};
  rdSv('rdOrientation', cfg.orientation || 'portrait');
  rdSv('rdLogo',     b.logo);
  rdSv('rdColor',    b.primaryColor||'#1e3a5f');
  rdSv('rdColorHex', b.primaryColor||'#1e3a5f');
  rdSv('rdFont',     b.font||'Arial');
  var h=cfg.header||{};
  rdSv('rdHTitle',   h.title);
  rdSv('rdHSub',     h.subtitle);
  rdSv('rdHBg',      h.bg||'#1e3a5f');
  rdSc('rdHDate',    h.showDate!==false);
  // Preview filters
  var pv=cfg.preview||{};
  rdSv('rdPvProject',pv.project_id);
  rdSv('rdPvFrom',   pv.date_from);
  rdSv('rdPvTo',     pv.date_to);
  var ptids=pv.type_ids||[];
  document.querySelectorAll('.rd-pv-etype').forEach(function(cb){
    cb.checked=ptids.indexOf(+cb.value)>=0;
  });
  var ft=cfg.footer||{};
  rdSv('rdFooter',   ft.text);
  rdSc('rdFPage',    ft.showPage!==false);
}

function rdGetCfg() {
  var pvtids=Array.from(document.querySelectorAll('.rd-pv-etype:checked')).map(function(cb){return +cb.value;});
  return {
    name:        rdGv('rdName').trim(),
    description: rdGv('rdDesc').trim(),
    orientation: rdGv('rdOrientation') || 'portrait',
    branding:    {logo:rdGv('rdLogo'),primaryColor:rdGv('rdColor'),font:rdGv('rdFont')},
    header:      {title:rdGv('rdHTitle'),subtitle:rdGv('rdHSub'),bg:rdGv('rdHBg'),showDate:rdGc('rdHDate')},
    footer:      {text:rdGv('rdFooter'),showPage:rdGc('rdFPage')},
    preview:     {project_id:rdGv('rdPvProject'),date_from:rdGv('rdPvFrom'),date_to:rdGv('rdPvTo'),type_ids:pvtids},
    blocks:      _rdBlocks,
  };
}

// ── Palette ───────────────────────────────────────────────────────────────────
function rdBuildPalette() {
  var pal=document.getElementById('rdPalette');
  if(!pal)return;
  pal.innerHTML='';
  Object.keys(RD_DEFS).forEach(function(type){
    var def=RD_DEFS[type];
    var el=document.createElement('div');
    el.className='rd-pal-item'; el.draggable=true; el.dataset.type=type;
    el.innerHTML='<i class="bi '+def.i+'" style="color:'+def.c+';font-size:14px;width:18px"></i>'
      +'<div style="flex:1"><div style="font-size:12px;color:#e5e7eb">'+def.l+'</div>'
      +'<div style="font-size:10px;color:#6b7280">'+def.d+'</div></div>'
      +'<i class="bi bi-plus-circle" style="color:#6b7280;font-size:12px;flex-shrink:0"></i>';
    el.addEventListener('click',function(){rdAddBlock(type);});
    el.addEventListener('dragstart',function(e){_rdDragType=type;_rdDragSrc=null;e.dataTransfer.effectAllowed='copy';e.dataTransfer.setData('text/plain',type);});
    pal.appendChild(el);
  });
}

function rdOnDrop(e){e.preventDefault();if(_rdDragType){rdAddBlock(_rdDragType);_rdDragType=null;}}

// ── Canvas ────────────────────────────────────────────────────────────────────
function rdAddBlock(type){
  _rdBlocks.push({type:type,id:Date.now(),cfg:{limit:50,cols:'basic',text:'',columns:['entry_date','title','status','priority','type_name']}});
  rdRenderCanvas(); rdUpdatePreview();
}
function rdRemoveBlock(id){_rdBlocks=_rdBlocks.filter(function(b){return b.id!==id;});rdRenderCanvas();rdUpdatePreview();}
function rdMoveBlock(id,dir){
  var i=_rdBlocks.findIndex(function(b){return b.id===id;});
  var j=i+dir; if(j<0||j>=_rdBlocks.length)return;
  var t=_rdBlocks[i];_rdBlocks[i]=_rdBlocks[j];_rdBlocks[j]=t;
  rdRenderCanvas();rdUpdatePreview();
}

function rdRenderCanvas(){
  var cv=document.getElementById('rdCanvas');
  var hint=document.getElementById('rdDropHint');
  if(!cv)return;
  hint.style.display=_rdBlocks.length?'none':'';
  cv.innerHTML='';
  _rdBlocks.forEach(function(b){
    var def=RD_DEFS[b.type]||{l:b.type,i:'bi-square',c:'#666',d:''};
    var wrap=document.createElement('div');
    wrap.className='rd-cv-block';
    var allCols=[
      {k:'entry_date',l:'Datum'},{k:'title',l:'Titel'},{k:'status',l:'Status'},
      {k:'priority',l:'Priorität'},{k:'type_name',l:'Typ'},{k:'project_name',l:'Projekt'},
      {k:'creator',l:'Ersteller'},{k:'description',l:'Beschreibung'},
      {k:'mower_serial',l:'Seriennummer'},{k:'firmware_version',l:'Firmware'},
      {k:'app_version',l:'App Version'},{k:'epic_title',l:'Epic'},
      {k:'parent_title',l:'Parent Ticket'},{k:'tag_names',l:'Tags'},
      {k:'jira_issue_key',l:'Jira Key'},{k:'zentao_bug_id',l:'Zentao ID'},
      {k:'project_status_robot',l:'Robot Status'},
    ];
    var h='<div class="rd-cv-hdr">'
      +'<i class="bi bi-grip-vertical" style="color:#6b7280;font-size:13px"></i>'
      +'<i class="bi '+def.i+'" style="color:'+def.c+';font-size:13px"></i>'
      +'<span style="font-size:12px;font-weight:600;color:#e5e7eb">'+def.l+'</span>'
      +'<span style="font-size:10px;color:#6b7280;margin-left:4px;flex:1">'+def.d+'</span>'
      +'<div class="d-flex gap-1 flex-shrink-0">'
      +'<button type="button" class="btn btn-sm py-0 px-1 text-muted rdUp" data-bid="'+b.id+'"><i class="bi bi-chevron-up" style="font-size:11px"></i></button>'
      +'<button type="button" class="btn btn-sm py-0 px-1 text-muted rdDn" data-bid="'+b.id+'"><i class="bi bi-chevron-down" style="font-size:11px"></i></button>'
      +'<button type="button" class="btn btn-sm py-0 px-1 text-danger rdRm" data-bid="'+b.id+'"><i class="bi bi-x" style="font-size:16px"></i></button>'
      +'</div></div>';
    var body='<div class="rd-cv-body">';
    if(b.type==='text'){
      body+='<textarea class="form-control form-control-sm rdTxt" data-bid="'+b.id+'" rows="3"'
        +' style="background:#1a1d23;color:#e5e7eb;border-color:#374151"'
        +' placeholder="Text eingeben...">'+rdEsc(b.cfg.text||'')+'</textarea>';
    } else if(b.type==='table'){
      var sc   = b.cfg.columns   || ['entry_date','title','status','priority','type_name'];
      var cw   = b.cfg.colWidths || {};
      var rows = b.cfg.rowGroups || [sc]; // array of arrays — each sub-array = one display row
      var defW = {entry_date:1,title:3,status:1,priority:1,type_name:1,project_name:2,creator:1,
                  description:4,mower_serial:2,firmware_version:1,app_version:1,epic_title:2,
                  parent_title:2,tag_names:2,jira_issue_key:1,zentao_bug_id:1,project_status_robot:1};
      var mr   = b.cfg.multiRow || false;

      // Page orientation override
      var blockOrient = b.cfg.orientation || 'inherit';
      body += '<div class="d-flex gap-2 align-items-center mb-2">';
      body += '<label class="rd-lbl mb-0" style="min-width:80px">Seitenformat</label>';
      body += '<select class="form-select form-select-sm rdBlockOrient" data-bid="'+b.id+'" style="width:auto;background:#1a1d23;color:#e5e7eb;border-color:#374151">';
      body += '<option value="inherit"'+(blockOrient==='inherit'?' selected':'')+'>Wie Vorlage</option>';
      body += '<option value="portrait"'+(blockOrient==='portrait'?' selected':'')+'>Hochformat</option>';
      body += '<option value="landscape"'+(blockOrient==='landscape'?' selected':'')+'>Querformat</option>';
      body += '</select></div>';
      // Max rows setting
      body += '<div class="d-flex gap-3 align-items-end mb-3">'
        +'<div><label class="rd-lbl">Max. Zeilen</label>'
        +'<input type="number" class="form-control form-control-sm rdLim" data-bid="'+b.id+'"'
        +' value="'+(b.cfg.limit||50)+'" min="5" max="500"'
        +' style="width:70px;background:#1a1d23;color:#e5e7eb;border-color:#374151"></div>'
        +'</div>';

      // Row-group editor
      body += '<div style="font-size:10px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.3px;margin-bottom:8px">'
        +'Zeilen-Gruppen <span style="font-weight:400;opacity:.7">(Spalten pro Zeile definieren)</span>'
        +'</div>';
      body += '<div id="rdRowGroups_'+b.id+'" style="background:#1a1d23;border:1px solid #374151;border-radius:6px;padding:8px;margin-bottom:8px">';

      // Render each row group
      rows.forEach(function(grp, grpIdx) {
        body += '<div class="rdRowGroup" data-bid="'+b.id+'" data-grp="'+grpIdx+'"'
          +' style="background:#262b36;border:1px solid #374151;border-radius:4px;padding:7px;margin-bottom:6px">';
        body += '<div style="display:flex;align-items:center;gap:6px;margin-bottom:6px">'
          +'<span style="font-size:10px;color:#9ca3af;font-weight:600">Zeile '+(grpIdx+1)+'</span>';
        if (grpIdx > 0) {
          body += '<button type="button" class="btn btn-sm py-0 px-1 text-danger rdRmGrp ms-auto" data-bid="'+b.id+'" data-grp="'+grpIdx+'" style="font-size:11px">✕ Zeile entfernen</button>';
        }
        body += '</div>';
        // Column checkboxes for this row
        body += '<div style="display:flex;flex-wrap:wrap;gap:4px">';
        allCols.forEach(function(col) {
          var inGrp = grp.indexOf(col.k) >= 0;
          body += '<label style="display:flex;align-items:center;gap:3px;background:'+(inGrp?'#6366f1':'#374151')+';'
            +'color:#fff;padding:2px 7px;border-radius:12px;font-size:10px;cursor:pointer;user-select:none">'
            +'<input type="checkbox" class="rdGrpCol" data-bid="'+b.id+'" data-grp="'+grpIdx+'" data-col="'+col.k+'"'
            +(inGrp?' checked':'')
            +' style="width:11px;height:11px;margin-right:2px;cursor:pointer">'
            +col.l+'</label>';
        });
        body += '</div>';
        // Width sliders for cols in this group
        if (grp.length > 0) {
          body += '<div style="margin-top:6px;display:flex;gap:4px;align-items:center">';
          grp.forEach(function(k) {
            var colDef = allCols.find(function(c){return c.k===k;});
            var w = cw[k] || defW[k] || 2;
            body += '<div style="flex:'+(w)+';background:#6366f1;border-radius:3px;padding:2px 4px;'
              +'text-align:center;font-size:8px;color:#fff;min-width:24px;position:relative">'
              +(colDef?colDef.l.substring(0,4):k.substring(0,4))
              +'<input type="range" class="rdColRange" data-bid="'+b.id+'" data-col="'+k+'"'
              +' min="1" max="12" value="'+w+'"'
              +' style="position:absolute;bottom:-14px;left:0;right:0;width:100%;height:12px;opacity:.8;cursor:pointer">'
              +'</div>';
          });
          body += '</div><div style="height:18px"></div>';
        }
        body += '</div>';
      });

      // Add row button
      body += '<button type="button" class="rdAddGrp btn btn-sm w-100 mt-1" data-bid="'+b.id+'"'
        +' style="background:#374151;color:#9ca3af;border:1px dashed #4b5563;font-size:11px">'
        +'+ Weitere Zeile hinzufügen</button>';
      body += '</div>';

      // Preview bar
      body += '<div style="display:flex;gap:1px;height:18px;border-radius:3px;overflow:hidden;margin-bottom:3px">';
      rows[0].forEach(function(k){
        var w2=cw[k]||defW[k]||2;
        var lbl=allCols.find(function(c){return c.k===k;});
        body += '<div style="flex:'+w2+';background:#6366f1;display:flex;align-items:center;justify-content:center;font-size:7px;color:#fff;overflow:hidden">'+(lbl?lbl.l.substring(0,3):'?')+'</div>';
      });
      body += '</div>';
      if (rows.length > 1) {
        body += '<div style="font-size:9px;color:#6b7280">+ '+(rows.length-1)+' weitere Zeile(n)</div>';
      }
    } else if(b.type==='top_issues'){
      body+='<div><label class="rd-lbl">Anzahl</label>'
        +'<input type="number" class="form-control form-control-sm rdLim" data-bid="'+b.id+'"'
        +' value="'+(b.cfg.limit||10)+'" min="3" max="50"'
        +' style="width:70px;background:#1a1d23;color:#e5e7eb;border-color:#374151"></div>';
    } else if(b.type==='project_header'){
      body+='<div style="font-size:11px;color:#9ca3af;margin-bottom:6px">Projektdaten kommen aus den Berichts-Einstellungen</div>';
      body+='<div class="form-check"><input class="form-check-input rdPhDesc" type="checkbox" data-bid="'+b.id+'"'+(b.cfg.showDesc!==false?' checked':'')+'>'
        +'<label class="form-check-label" style="font-size:11px;color:#d1d5db">Projektbeschreibung</label></div>';
      body+='<div class="form-check"><input class="form-check-input rdPhStatus" type="checkbox" data-bid="'+b.id+'"'+(b.cfg.showStatus!==false?' checked':'')+'>'
        +'<label class="form-check-label" style="font-size:11px;color:#d1d5db">Projektstatus</label></div>';
      body+='<div class="form-check"><input class="form-check-input rdPhStats" type="checkbox" data-bid="'+b.id+'"'+(b.cfg.showStats!==false?' checked':'')+'>'
        +'<label class="form-check-label" style="font-size:11px;color:#d1d5db">Eintragszahl</label></div>';
    } else if(b.type==='divider'||b.type==='page_break'){
      body+='<p style="font-size:11px;color:#6b7280;margin:0">'+def.d+'</p>';
    } else {
      body+='<p style="font-size:11px;color:#6b7280;margin:0">'+def.d+'</p>';
    }
    // Width & layout selector (all blocks except divider/page_break)
    if(b.type!=='divider'&&b.type!=='page_break'){
      var w=b.cfg.width||'full';
      var kpiCols=b.cfg.kpiCols||4;
      var barHeight=b.cfg.barHeight||16;
      body+='<div style="border-top:1px solid #374151;margin-top:10px;padding-top:10px">';
      body+='<div style="font-size:10px;color:#6b7280;margin-bottom:6px;font-weight:600;text-transform:uppercase;letter-spacing:.3px">Layout &amp; Breite</div>';
      // Width buttons
      body+='<div style="display:flex;gap:4px;margin-bottom:8px">';
      [{v:'full',l:'Voll',t:'100%'},{v:'half',l:'½',t:'50%'},{v:'third',l:'⅓',t:'33%'},{v:'two-third',l:'⅔',t:'67%'}].forEach(function(opt){
        var active=w===opt.v;
        body+='<button type="button" class="rdWidth btn btn-sm" data-bid="'+b.id+'" data-width="'+opt.v+'"'
          +' style="font-size:11px;padding:3px 8px;background:'+(active?'#6366f1':'#374151')+';color:#fff;border:none;border-radius:4px;flex:1"'
          +' title="Breite: '+opt.t+'">'+opt.l+'</button>';
      });
      body+='</div>';
      // Block-specific extra options
      if(b.type==='summary'){
        body+='<div style="display:flex;align-items:center;gap:8px"><label style="font-size:11px;color:#9ca3af">Kennzahlen pro Zeile:</label>'
          +'<select class="form-select form-select-sm rdKpiCols" data-bid="'+b.id+'" style="width:60px;background:#1a1d23;color:#e5e7eb;border-color:#374151">'
          +[2,3,4].map(function(n){return '<option value="'+n+'"'+(kpiCols===n?' selected':'')+'>'+n+'</option>';}).join('')
          +'</select></div>';
      }
      if(b.type.indexOf('chart')===0){
        body+='<div style="display:flex;align-items:center;gap:8px"><label style="font-size:11px;color:#9ca3af">Balkenhöhe (px):</label>'
          +'<input type="number" class="form-control form-control-sm rdBarH" data-bid="'+b.id+'" value="'+barHeight+'" min="10" max="40"'
          +' style="width:60px;background:#1a1d23;color:#e5e7eb;border-color:#374151"></div>';
      }
      body+='</div>';
    }
    // Per-block filter: narrows just this block's entries (e.g. a priority chart
    // that should only count "New"/"Reviewed" bugs) without affecting the rest
    // of the report, which still uses the full entry set.
    if(RD_FILTERABLE[b.type]){
      var filt=b.cfg.filter||{};
      var fSt=filt.statuses||[], fPr=filt.priorities||[], fTy=filt.types||[];
      function rdChip(kind,val,label,active){
        return '<label class="rd-filter-chip" style="display:inline-flex;align-items:center;gap:3px;background:'+(active?'#6366f1':'#374151')+';'
          +'color:#fff;padding:2px 7px;border-radius:12px;font-size:10px;cursor:pointer;user-select:none">'
          +'<input type="checkbox" class="rdFiltChip" data-bid="'+b.id+'" data-kind="'+kind+'" data-val="'+rdEsc(val)+'"'
          +(active?' checked':'')+' style="width:11px;height:11px;margin:0;cursor:pointer">'+rdEsc(label)+'</label>';
      }
      body+='<div style="border-top:1px solid #374151;margin-top:10px;padding-top:10px">';
      body+='<div style="font-size:10px;color:#6b7280;margin-bottom:2px;font-weight:600;text-transform:uppercase;letter-spacing:.3px">Filter für diesen Bereich</div>';
      body+='<div style="font-size:10px;color:#6b7280;margin-bottom:6px">Leer = alle Einträge. Der restliche Bericht bleibt davon unberührt.</div>';
      body+='<div class="rd-lbl">Status</div>';
      body+='<div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:8px">';
      Object.keys(RD_STATUSES).forEach(function(slug){ body+=rdChip('statuses',slug,RD_STATUSES[slug],fSt.indexOf(slug)>=0); });
      body+='</div>';
      body+='<div class="rd-lbl">Priorität</div>';
      body+='<div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:8px">';
      RD_PRIORITIES.forEach(function(p){ body+=rdChip('priorities',p,p,fPr.indexOf(p)>=0); });
      body+='</div>';
      if(RD_ENTRY_TYPES.length){
        body+='<div class="rd-lbl">Eintragstyp</div>';
        body+='<div style="display:flex;flex-wrap:wrap;gap:4px">';
        RD_ENTRY_TYPES.forEach(function(t){ body+=rdChip('types',t,t,fTy.indexOf(t)>=0); });
        body+='</div>';
      }
      body+='</div>';
    }
    body+='</div>';
    wrap.innerHTML=h+body;
    // Drag reorder
    var hdrEl=wrap.querySelector('.rd-cv-hdr');
    hdrEl.addEventListener('dragstart',function(e){_rdDragSrc=b.id;_rdDragType=null;e.dataTransfer.effectAllowed='move';e.stopPropagation();});
    wrap.addEventListener('dragover',function(e){e.preventDefault();e.stopPropagation();wrap.classList.add('drag-over');});
    wrap.addEventListener('dragleave',function(){wrap.classList.remove('drag-over');});
    wrap.addEventListener('drop',function(e){
      e.preventDefault();e.stopPropagation();wrap.classList.remove('drag-over');
      if(_rdDragSrc===null||_rdDragSrc===b.id)return;
      var fi=_rdBlocks.findIndex(function(x){return x.id===_rdDragSrc;});
      var ti=_rdBlocks.findIndex(function(x){return x.id===b.id;});
      if(fi<0||ti<0)return;
      var moved=_rdBlocks.splice(fi,1)[0];_rdBlocks.splice(ti,0,moved);
      _rdDragSrc=null;rdRenderCanvas();rdUpdatePreview();
    });
    cv.appendChild(wrap);
  });
  // Events
  cv.querySelectorAll('.rdUp').forEach(function(btn){btn.addEventListener('click',function(){rdMoveBlock(+btn.dataset.bid,-1);});});
  cv.querySelectorAll('.rdDn').forEach(function(btn){btn.addEventListener('click',function(){rdMoveBlock(+btn.dataset.bid,1);});});
  cv.querySelectorAll('.rdRm').forEach(function(btn){btn.addEventListener('click',function(){rdRemoveBlock(+btn.dataset.bid);});});
  cv.querySelectorAll('.rdTxt').forEach(function(ta){ta.addEventListener('input',function(){var b=_rdBlocks.find(function(x){return x.id===+ta.dataset.bid;});if(b){b.cfg.text=ta.value;}rdUpdatePreview();});});
  cv.querySelectorAll('.rdBlockOrient').forEach(function(sel){
    sel.addEventListener('change',function(){
      var b=_rdBlocks.find(function(x){return x.id===+sel.dataset.bid;});
      if(b){b.cfg.orientation=sel.value;}
    });
  });
  cv.querySelectorAll('.rdLim').forEach(function(inp){inp.addEventListener('change',function(){var b=_rdBlocks.find(function(x){return x.id===+inp.dataset.bid;});if(b){b.cfg.limit=+inp.value;}rdUpdatePreview();});});
  cv.querySelectorAll('.rdFiltChip').forEach(function(cb){
    cb.addEventListener('change',function(){
      var b=_rdBlocks.find(function(x){return x.id===+cb.dataset.bid;});
      if(!b)return;
      if(!b.cfg.filter)b.cfg.filter={statuses:[],priorities:[],types:[]};
      var kind=cb.dataset.kind, val=cb.dataset.val;
      if(!b.cfg.filter[kind])b.cfg.filter[kind]=[];
      var idx=b.cfg.filter[kind].indexOf(val);
      if(cb.checked){ if(idx<0)b.cfg.filter[kind].push(val); }
      else if(idx>=0){ b.cfg.filter[kind].splice(idx,1); }
      rdRenderCanvas(); rdUpdatePreview();
    });
  });
  cv.querySelectorAll('.rdColCb').forEach(function(cb){
    cb.addEventListener('change',function(){
      var b=_rdBlocks.find(function(x){return x.id===+cb.dataset.bid;});
      if(!b)return;
      if(!b.cfg.columns)b.cfg.columns=['entry_date','title','status','priority','type_name'];
      if(cb.checked){if(b.cfg.columns.indexOf(cb.dataset.col)<0)b.cfg.columns.push(cb.dataset.col);}
      else b.cfg.columns=b.cfg.columns.filter(function(c){return c!==cb.dataset.col;});
      rdUpdatePreview();
    });
  });
  cv.querySelectorAll('.rdPhDesc,.rdPhStatus,.rdPhStats').forEach(function(cb){
    cb.addEventListener('change',function(){
      var b=_rdBlocks.find(function(x){return x.id===+cb.dataset.bid;});
      if(!b)return;
      if(cb.classList.contains('rdPhDesc'))   b.cfg.showDesc=cb.checked;
      if(cb.classList.contains('rdPhStatus')) b.cfg.showStatus=cb.checked;
      if(cb.classList.contains('rdPhStats'))  b.cfg.showStats=cb.checked;
      rdUpdatePreview();
    });
  });
  // Column width sliders
  cv.querySelectorAll('.rdColSlider').forEach(function(slider){
    function updateSlider(e){
      var rect=slider.getBoundingClientRect();
      var pct=Math.max(0,Math.min(1,(e.clientX-rect.left)/rect.width));
      var units=Math.max(1,Math.min(12,Math.round(pct*12)));
      var b=_rdBlocks.find(function(x){return x.id===+slider.dataset.bid;});
      if(!b)return;
      if(!b.cfg.colWidths)b.cfg.colWidths={};
      b.cfg.colWidths[slider.dataset.col]=units;
      // Update visuals without re-render
      var bar=slider.querySelector('.rdColBar');
      if(bar)bar.style.width=Math.round(units/12*100)+'%';
      var row=slider.closest('[data-colrow]');
      if(row){var vw=row.querySelector('.rdColWVal');if(vw)vw.textContent=units;}
      // Update preview bar
      var prev=document.getElementById('rdTblPreview_'+slider.dataset.bid);
      if(prev){
        var cols=b.cfg.columns||[];
        var cw2=b.cfg.colWidths||{};
        var defW2={entry_date:1,title:3,status:1,priority:1,type_name:1,project_name:2,creator:1,description:4,mower_serial:2,firmware_version:1,app_version:1,epic_title:2,parent_title:2,tag_names:2,jira_issue_key:1,zentao_bug_id:1,project_status_robot:1};
        var allColsMap={entry_date:'Datu',title:'Tite',status:'Stat',priority:'Prio',type_name:'Typ',project_name:'Proj',creator:'Erst',description:'Besc',mower_serial:'Seri',firmware_version:'Firm',app_version:'App',epic_title:'Epic',parent_title:'Pare',tag_names:'Tags',jira_issue_key:'Jira',zentao_bug_id:'Zent',project_status_robot:'Robo'};
        prev.innerHTML=cols.map(function(k){var w2=cw2[k]||defW2[k]||2;return '<div style="flex:'+w2+';background:#6366f1;display:flex;align-items:center;justify-content:center;font-size:8px;color:#fff;overflow:hidden;white-space:nowrap;padding:0 2px">'+(allColsMap[k]||k.substring(0,4))+'</div>';}).join('');
        var tot=document.querySelector('.rdTblTotal_'+slider.dataset.bid);
        if(tot)tot.textContent=cols.reduce(function(s,k){return s+(cw2[k]||defW2[k]||2);},0);
      }
      rdUpdatePreview();
    }
    var dragging=false;
    slider.addEventListener('mousedown',function(e){dragging=true;updateSlider(e);e.preventDefault();});
    document.addEventListener('mousemove',function(e){if(dragging)updateSlider(e);});
    document.addEventListener('mouseup',function(){dragging=false;});
    slider.addEventListener('click',updateSlider);
  });
  // Row-group col checkboxes
  cv.querySelectorAll('.rdGrpCol').forEach(function(cb){
    cb.addEventListener('change',function(){
      var b=_rdBlocks.find(function(x){return x.id===+cb.dataset.bid;});
      if(!b) return;
      var gi=+cb.dataset.grp;
      if(!b.cfg.rowGroups) b.cfg.rowGroups=[b.cfg.columns||['entry_date','title','status','priority','type_name']];
      while(b.cfg.rowGroups.length<=gi) b.cfg.rowGroups.push([]);
      if(cb.checked){ if(b.cfg.rowGroups[gi].indexOf(cb.dataset.col)<0) b.cfg.rowGroups[gi].push(cb.dataset.col); }
      else b.cfg.rowGroups[gi]=b.cfg.rowGroups[gi].filter(function(c){return c!==cb.dataset.col;});
      // Sync columns = all cols across all rows (for backwards compat)
      b.cfg.columns=b.cfg.rowGroups.reduce(function(a,r){return a.concat(r.filter(function(c){return a.indexOf(c)<0;}));}, []);
      rdRenderCanvas(); rdUpdatePreview();
    });
  });
  // Row-group range sliders
  cv.querySelectorAll('.rdColRange').forEach(function(inp){
    inp.addEventListener('input',function(){
      var b=_rdBlocks.find(function(x){return x.id===+inp.dataset.bid;});
      if(!b) return;
      if(!b.cfg.colWidths) b.cfg.colWidths={};
      b.cfg.colWidths[inp.dataset.col]=+inp.value;
      rdRenderCanvas(); rdUpdatePreview();
    });
  });
  // Add row group
  cv.querySelectorAll('.rdAddGrp').forEach(function(btn){
    btn.addEventListener('click',function(){
      var b=_rdBlocks.find(function(x){return x.id===+btn.dataset.bid;});
      if(!b) return;
      if(!b.cfg.rowGroups) b.cfg.rowGroups=[b.cfg.columns||['entry_date','title','status']];
      b.cfg.rowGroups.push([]);
      rdRenderCanvas(); rdUpdatePreview();
    });
  });
  // Remove row group
  cv.querySelectorAll('.rdRmGrp').forEach(function(btn){
    btn.addEventListener('click',function(){
      var b=_rdBlocks.find(function(x){return x.id===+btn.dataset.bid;});
      if(!b||!b.cfg.rowGroups) return;
      b.cfg.rowGroups.splice(+btn.dataset.grp,1);
      b.cfg.columns=b.cfg.rowGroups.reduce(function(a,r){return a.concat(r.filter(function(c){return a.indexOf(c)<0;}));}, []);
      rdRenderCanvas(); rdUpdatePreview();
    });
  });
  // Width buttons
  cv.querySelectorAll('.rdWidth').forEach(function(btn){
    btn.addEventListener('click',function(){
      var b=_rdBlocks.find(function(x){return x.id===+btn.dataset.bid;});
      if(!b)return;
      b.cfg.width=btn.dataset.width;
      rdRenderCanvas(); rdUpdatePreview();
    });
  });
  // KPI columns
  cv.querySelectorAll('.rdKpiCols').forEach(function(sel){
    sel.addEventListener('change',function(){
      var b=_rdBlocks.find(function(x){return x.id===+sel.dataset.bid;});
      if(b){b.cfg.kpiCols=+sel.value;rdUpdatePreview();}
    });
  });
  // Bar height
  cv.querySelectorAll('.rdBarH').forEach(function(inp){
    inp.addEventListener('change',function(){
      var b=_rdBlocks.find(function(x){return x.id===+inp.dataset.bid;});
      if(b){b.cfg.barHeight=+inp.value;rdUpdatePreview();}
    });
  });
}

// ── Live preview ──────────────────────────────────────────────────────────────
function rdUpdatePreview(){
  var pv=document.getElementById('rdPreview'); if(!pv)return;
  var color=rdGv('rdColor')||'#1e3a5f';
  var font=rdGv('rdFont')||'Arial';
  var hbg=rdGv('rdHBg')||color;
  var h='<div style="font-family:'+font+'">';
  // Header
  h+='<div style="background:'+hbg+';color:#fff;padding:12px 14px;margin-bottom:10px;border-radius:4px">';
  var logo=rdGv('rdLogo');
  if(logo) h+='<img src="'+rdEsc(logo)+'" style="height:24px;margin-bottom:4px;display:block" onerror="this.style.display=\'none\'">';
  h+='<div style="font-size:14px;font-weight:700">'+(rdGv('rdHTitle')||'(Kein Titel)')+'</div>';
  var sub=rdGv('rdHSub'); if(sub) h+='<div style="font-size:11px;opacity:.8;margin-top:2px">'+rdEsc(sub)+'</div>';
  if(rdGc('rdHDate')) h+='<div style="font-size:10px;opacity:.6;margin-top:3px">'+new Date().toLocaleDateString('de-DE')+'</div>';
  h+='</div>';
  // Preview filter hint
  var pvProj=document.getElementById('rdPvProject');
  var pvProjName=pvProj&&pvProj.value?(pvProj.options[pvProj.selectedIndex]||{}).text:'';
  if(pvProjName) h+='<div style="background:#0ea5e91a;border:1px solid #0ea5e933;border-radius:4px;padding:5px 10px;font-size:10px;color:#0ea5e9;margin-bottom:8px"><i class="bi bi-eye"></i> Vorschau: '+rdEsc(pvProjName)+(rdGv('rdPvFrom')?' · '+rdGv('rdPvFrom')+' – '+(rdGv('rdPvTo')||'heute'):'')+'</div>';
  // Blocks
  if(!_rdBlocks.length) h+='<p style="color:#aaa;text-align:center;padding:16px;font-size:11px">Füge links Blöcke hinzu</p>';
  _rdBlocks.forEach(function(b){
    var def=RD_DEFS[b.type]||{l:b.type,c:color};
    if(b.type==='divider'){h+='<hr style="border-color:#ddd;margin:6px 0">';return;}
    if(b.type==='page_break'){h+='<div style="border-top:2px dashed #ccc;text-align:center;font-size:9px;color:#bbb;padding:2px 0;margin:6px 0">— Seitenumbruch —</div>';return;}
    var bc=def.c||color;
    var bw=b.cfg.width||'full';
    var wPct=bw==='half'?'48%':bw==='third'?'31%':bw==='two-third'?'65%':'100%';
    var wDisp=bw==='half'?'inline-block':bw==='third'?'inline-block':bw==='two-third'?'inline-block':'block';
    h+='<div style="width:'+wPct+';display:'+wDisp+';vertical-align:top;margin-bottom:8px;margin-right:2%;background:#fff;border:1px solid #e0e0e0;border-radius:5px;overflow:hidden">';
    h+='<div style="background:'+bc+'1a;border-bottom:1px solid '+bc+'33;padding:5px 10px;font-size:9px;font-weight:700;color:'+bc+';text-transform:uppercase;letter-spacing:.3px">'+def.l+'</div>';
    h+='<div style="padding:7px 10px;font-size:11px;color:#333">';
    if(b.type==='text') h+='<div style="white-space:pre-wrap;color:#333">'+(b.cfg.text?rdEsc(b.cfg.text):'<em style="color:#bbb">Kein Text</em>')+'</div>';
    else if(b.type==='summary') h+='<div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:4px">'+['Gesamt','Offen','Erledigt','Typen'].map(function(l){return '<div style="background:#f4f6ff;padding:5px;border-radius:3px;text-align:center"><div style="font-size:14px;font-weight:700;color:'+color+'">–</div><div style="font-size:9px;color:#888">'+l+'</div></div>';}).join('')+'</div>';
    else if(b.type==='project_header') h+='<div style="border-left:3px solid '+color+';padding:5px 10px;background:'+color+'0d;font-size:11px"><strong>'+rdEsc(pvProjName||'(Beispielprojekt)')+'</strong>'+(b.cfg.showStats!==false?' <span style="font-size:10px;color:'+color+'">– Einträge</span>':'')+'</div>';
    else if(b.type.indexOf('chart')===0) h+='<div style="height:40px;background:#f8f9fa;border-radius:3px;display:flex;align-items:center;justify-content:center;font-size:10px;color:#aaa">Diagramm (mit echten Daten)</div>';
    else if(b.type==='table') h+='<span style="color:#888;font-size:11px">Tabelle · max. '+(b.cfg.limit||50)+' Zeilen · Spalten: '+(b.cfg.columns||[]).join(', ')+'</span>';
    else if(b.type==='top_issues') h+='<span style="color:#888;font-size:11px">Top '+(b.cfg.limit||10)+' Issues nach Priorität</span>';
    else if(b.type==='timeline') h+='<span style="color:#888;font-size:11px">Chronologische Timeline</span>';
    if(RD_FILTERABLE[b.type]){
      var pfilt=b.cfg.filter||{};
      var pfParts=[];
      if((pfilt.statuses||[]).length)   pfParts.push('Status: '+pfilt.statuses.map(function(s){return RD_STATUSES[s]||s;}).join(', '));
      if((pfilt.priorities||[]).length) pfParts.push('Prio: '+pfilt.priorities.join(', '));
      if((pfilt.types||[]).length)      pfParts.push('Typ: '+pfilt.types.join(', '));
      if(pfParts.length) h+='<div style="font-size:9px;color:#c2410c;margin-top:4px">🔍 '+rdEsc(pfParts.join(' · '))+'</div>';
    }
    h+='</div></div>';
  });
  // Footer
  var ftxt=rdGv('rdFooter'); var fpage=rdGc('rdFPage');
  if(ftxt||fpage){
    h+='<div style="border-top:1px solid #ddd;padding-top:5px;display:flex;justify-content:space-between;font-size:9px;color:#999;margin-top:6px">';
    h+='<span>'+rdEsc(ftxt)+'</span>';
    if(fpage) h+='<span>Seite 1 / N</span>';
    h+='</div>';
  }
  h+='</div>';
  pv.innerHTML=h;
}

// ── Save ──────────────────────────────────────────────────────────────────────
async function rdSave(){
  var name=rdGv('rdName').trim();
  if(!name){alert('Bitte Template-Namen eingeben.');document.getElementById('rdName').focus();return false;}
  var cfg=rdGetCfg();
  var fd=new FormData();
  fd.append('_csrf','<?= $csrf ?>');
  fd.append('name',name);
  fd.append('description',cfg.description);
  fd.append('config',JSON.stringify(cfg));
  if(_rdId)fd.append('id',_rdId);
  try{
    var res=await fetch('<?= $saveUrl ?>',{method:'POST',body:fd});
    var data=await res.json();
    if(data.ok){_rdId=data.id;return true;}
    alert('Fehler: '+(data.error||'Unbekannt'));return false;
  }catch(err){alert('Fehler: '+err.message);return false;}
}

// ── Wire up ───────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function(){
  // New template buttons
  document.getElementById('rdBtnNew').addEventListener('click',function(){rdEditorOpen(null,null);});
  var b2=document.getElementById('rdBtnNew2');
  if(b2)b2.addEventListener('click',function(){rdEditorOpen(null,null);});

  // Edit template buttons
  document.querySelectorAll('.rd-edit-btn').forEach(function(btn){
    btn.addEventListener('click',function(){
      rdEditorOpen(+btn.dataset.editid, btn.dataset.editcfg, btn.dataset.editname, btn.dataset.editdesc);
    });
  });

  // Close editor
  document.getElementById('rdClose').addEventListener('click',rdEditorClose);

  // Save
  document.getElementById('rdSaveBtn').addEventListener('click',async function(){
    var ok=await rdSave();
    if(ok){rdEditorClose();location.reload();}
  });

  // Preview — uses preview filters from config
  document.getElementById('rdPreviewBtn').addEventListener('click',async function(){
    var ok=await rdSave();
    if(ok&&_rdId){
      var cfg=rdGetCfg();
      var pv=cfg.preview||{};
      var params=new URLSearchParams();
      if(pv.project_id) params.set('project_id',pv.project_id);
      if(pv.date_from)  params.set('date_from',pv.date_from);
      if(pv.date_to)    params.set('date_to',pv.date_to);
      (pv.type_ids||[]).forEach(function(id){params.append('type_ids[]',id);});
      window.open('<?= $baseUrl ?>'+_rdId+'/report?'+params.toString(),'_blank');
    }
  });

  // "Bericht erstellen" buttons
  document.querySelectorAll('.rd-gen-btn').forEach(function(btn){
    btn.addEventListener('click',function(){
      var id=btn.dataset.id;
      document.getElementById('rdGenTplName').textContent=btn.dataset.name;
      document.getElementById('rdGenForm').action='<?= $baseUrl ?>'+id+'/report';
      new bootstrap.Modal(document.getElementById('rdGenModal')).show();
    });
  });

  // Autoprint checkbox
  document.getElementById('rdGenAutoprint').addEventListener('change',function(){
    var form=document.getElementById('rdGenForm');
    if(this.checked) form.action=form.action.split('?')[0]+'?autoprint=1';
    else form.action=form.action.split('?')[0];
  });

  // Config → live preview
  var ed=document.getElementById('rdEditor');
  ed.addEventListener('input',function(e){
    if(e.target.id==='rdColor')    rdSv('rdColorHex',e.target.value);
    if(e.target.id==='rdColorHex'){var v=e.target.value;if(/^#[0-9a-f]{6}$/i.test(v))rdSv('rdColor',v);}
    rdUpdatePreview();
  });
  ed.addEventListener('change',function(){rdUpdatePreview();});
});
</script>
