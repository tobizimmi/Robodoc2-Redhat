<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <a href="<?= url('test-customers') ?>" class="text-muted small"><i class="bi bi-arrow-left me-1"></i>Aufträge</a>
    <h5 class="mt-1 mb-0"><i class="bi bi-people me-2"></i>Testkunden Verzeichnis</h5>
  </div>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
    <i class="bi bi-plus-lg me-1"></i>Neuer Testkunde
  </button>
</div>

<?php if (empty($customers)): ?>
<div class="text-center text-muted py-5">
  <i class="bi bi-people" style="font-size:3rem;opacity:.3"></i>
  <p class="mt-3">Noch keine Testkunden. Lege Testkunden zentral an und weise sie dann Aufträgen zu.</p>
</div>
<?php else: ?>
<div class="card border-secondary">
  <table class="table table-dark table-hover mb-0">
    <thead class="border-secondary">
      <tr>
        <th>Nr.</th>
        <th>Bezeichnung</th>
        <th>E-Mail</th>
        <th>Notizen</th>
        <th>Aufträge</th>
        <th>Erstellt</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($customers as $tc): ?>
    <tr>
      <td><code><?= e($tc['customer_number']) ?></code></td>
      <td class="fw-semibold"><?= e($tc['label']) ?></td>
      <td>
        <?php if ($tc['email']): ?>
        <a href="mailto:<?= e($tc['email']) ?>" class="text-muted small"><?= e($tc['email']) ?></a>
        <?php else: ?><span class="text-muted">–</span><?php endif; ?>
      </td>
      <td class="text-muted small"><?= e(mb_substr($tc['notes'] ?? '', 0, 60)) ?></td>
      <td><span class="badge bg-secondary"><?= $tc['order_count'] ?></span></td>
      <td class="text-muted small"><?= date('d.m.Y', strtotime($tc['created_at'])) ?></td>
      <td class="text-end">
        <button class="btn btn-outline-secondary btn-sm py-0 px-2"
                onclick="editCustomer(<?= htmlspecialchars(json_encode($tc), ENT_QUOTES) ?>)">
          <i class="bi bi-pencil" style="font-size:.7rem"></i>
        </button>
        <form method="POST" action="<?= url('test-customers/customers/' . $tc['id'] . '/delete') ?>"
              onsubmit="return confirm('Testkunde löschen?')" class="d-inline">
          <?= csrfField() ?>
          <button class="btn btn-outline-danger btn-sm py-0 px-2">
            <i class="bi bi-trash" style="font-size:.7rem"></i>
          </button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Add/Edit Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title" id="customerModalTitle">Neuer Testkunde</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="<?= url('test-customers/customers/save') ?>" id="customerForm">
        <?= csrfField() ?>
        <input type="hidden" name="id" id="customerId" value="">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Testkundennummer <span class="text-danger">*</span></label>
            <input type="text" name="customer_number" id="customerNumber" class="form-control"
                   required maxlength="50" placeholder="z.B. TK-001">
          </div>
          <div class="mb-3">
            <label class="form-label">Bezeichnung <span class="text-danger">*</span></label>
            <input type="text" name="label" id="customerLabel" class="form-control"
                   required maxlength="150" placeholder="Name oder Beschreibung">
          </div>
          <div class="mb-3">
            <label class="form-label">E-Mail <span class="text-muted small">(optional)</span></label>
            <input type="email" name="email" id="customerEmail" class="form-control"
                   maxlength="200" placeholder="z.B. max@firma.de">
          </div>
          <div class="mb-3">
            <label class="form-label">Notizen <span class="text-muted small">(optional)</span></label>
            <textarea name="notes" id="customerNotes" class="form-control" rows="2"
                      placeholder="Interne Notizen..."></textarea>
          </div>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
          <button type="submit" class="btn btn-primary">Speichern</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editCustomer(tc) {
  document.getElementById('customerId').value     = tc.id;
  document.getElementById('customerNumber').value = tc.customer_number;
  document.getElementById('customerLabel').value  = tc.label;
  document.getElementById('customerEmail').value  = tc.email || '';
  document.getElementById('customerNotes').value  = tc.notes || '';
  document.getElementById('customerModalTitle').textContent = 'Testkunde bearbeiten';
  new bootstrap.Modal(document.getElementById('addCustomerModal')).show();
}
</script>
