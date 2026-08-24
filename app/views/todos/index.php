<div class="row g-4">
  <div class="col-lg-7">
    <!-- Standalone todos -->
    <div class="card mb-4">
      <div class="card-header border-secondary d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Aufgaben</span>
        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#newTodo">
          <i class="bi bi-plus-lg"></i>
        </button>
      </div>
      <div class="collapse" id="newTodo">
        <div class="card-body border-bottom border-secondary">
          <form method="POST" action="<?= url('todos/create') ?>" class="d-flex gap-2">
            <?= csrfField() ?>
            <input type="text" name="title" class="form-control form-control-sm" placeholder="Neue Aufgabe…" required>
            <input type="date" name="due_date" class="form-control form-control-sm" style="max-width:140px">
            <button class="btn btn-primary btn-sm text-nowrap">Add</button>
          </form>
        </div>
      </div>
      <div class="list-group list-group-flush">
        <?php if (!$standaloneTodos): ?>
        <div class="list-group-item bg-transparent border-secondary text-muted text-center small p-4">Keine Aufgaben.</div>
        <?php endif; ?>
        <?php foreach ($standaloneTodos as $todo): ?>
        <div class="list-group-item bg-transparent border-secondary todo-item <?= $todo['done'] ? 'opacity-50' : '' ?> py-2 px-3">
          <div class="d-flex align-items-center gap-2">
            <button class="btn btn-link todo-toggle p-0" data-url="<?= url('todos/' . $todo['id'] . '/toggle') ?>">
              <i class="bi bi-<?= $todo['done'] ? 'check-circle-fill text-success' : 'circle' ?> fs-5"></i>
            </button>
            <div class="flex-grow-1">
              <span class="<?= $todo['done'] ? 'text-decoration-line-through text-muted' : '' ?>"><?= e($todo['title']) ?></span>
              <?php if ($todo['due_date']): ?>
              <span class="text-muted small ms-2">Due: <?= formatDate($todo['due_date']) ?></span>
              <?php endif; ?>
            </div>
            <form method="POST" action="<?= url('todos/' . $todo['id'] . '/delete') ?>" data-confirm="Delete todo?">
              <?= csrfField() ?><button class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-trash small"></i></button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <!-- Entry todos -->
    <div class="card">
      <div class="card-header border-secondary fw-semibold">
        Eintrags-Todos (<?= count($entryTodos) ?>)
      </div>
      <div class="list-group list-group-flush">
        <?php if (!$entryTodos): ?>
        <div class="list-group-item bg-transparent border-secondary text-muted text-center small p-4">No marked entries.</div>
        <?php endif; ?>
        <?php foreach ($entryTodos as $t): ?>
        <a href="<?= url('entries/' . $t['entry_id']) ?>" class="list-group-item list-group-item-action bg-transparent border-secondary py-2 px-3">
          <div class="d-flex align-items-center gap-2">
            <span class="badge flex-shrink-0" style="background:<?= e($t['type_color']) ?>"><?= e($t['type_name']) ?></span>
            <div class="flex-grow-1">
              <div class="small fw-semibold text-truncate"><?= e($t['title'] ?: substr($t['description'] ?? '', 0, 50)) ?></div>
              <div class="text-muted small"><?= e($t['project_name']) ?> &middot; <?= formatDate($t['entry_date']) ?></div>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
