<style>
body { background: #0f1117; color: #e5e7eb; font-family: system-ui, sans-serif; }
.success-card { max-width: 420px; margin: 80px auto; text-align: center; padding: 32px;
                background: #1e2125; border-radius: 12px; border: 1px solid #374151; }
.btn-outline-light { border-color: #6b7280; color: #d1d5db; }
.btn-outline-light:hover { background: #374151; color: #fff; }
</style>
<div class="success-card">
  <i class="bi bi-check-circle-fill text-success" style="font-size:3.5rem"></i>
  <h4 class="mt-3 mb-1">Vielen Dank!</h4>
  <p class="text-muted mb-4">Ihr Feedback wurde erfolgreich übermittelt.</p>
  <?php
    // Determine "send more" link — respondent or general
    $moreUrl = null;
    if (!empty($respondentToken)) {
      $moreUrl = url('tc-respondent/' . $respondentToken);
      $moreTxt = 'Weiteres Feedback senden';
    } elseif (!empty($order) && !empty($order['qr_token'])) {
      $moreUrl = url('tc-feedback/' . $order['qr_token']);
      $moreTxt = 'Weiteres Feedback senden';
    }
  ?>
  <?php if ($moreUrl): ?>
  <a href="<?= e($moreUrl) ?>" class="btn btn-outline-light btn-sm"><?= e($moreTxt) ?></a>
  <?php endif; ?>
</div>
