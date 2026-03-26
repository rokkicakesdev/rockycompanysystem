<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('ROLE_ADMIN')) require_once __DIR__ . '/../../../config/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_ADMIN) {
    header('Location: ' . BASE_URL . '/index.php'); exit;
}

$pageTitle = 'Holidays';
require_once __DIR__ . '/../layouts/admin_header.php';
require_once __DIR__ . '/../../../core/Validator.php';

$msg = '';

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ── POST: Add Holiday ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_holiday'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'>Invalid security token.</div>";
    } else {
        $v = new Validator($_POST);
        $v->required('name', 'Holiday Name')->maxLen('name', 150, 'Holiday Name')
          ->required('date', 'Date')->date('date', 'Date')
          ->inList('type', ['regular', 'special_non_working', 'special_working'], 'Type');
        if ($v->fails()) {
            $msg = $v->errorHtml();
        } else {
            Model::createHoliday([
                'name'         => trim($_POST['name']),
                'date'         => $_POST['date'],
                'type'         => $_POST['type'],
                'is_recurring' => isset($_POST['is_recurring']) ? 1 : 0,
            ]);
            Model::log($_SESSION['user_id'], 'CREATE_HOLIDAY', trim($_POST['name']) . ' on ' . $_POST['date']);
            $msg = "<div class='alert alert-success alert-auto-dismiss'><i class='fas fa-check-circle mr-2'></i>Holiday added successfully.</div>";
        }
    }
}

// ── POST: Edit Holiday ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_holiday'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'>Invalid security token.</div>";
    } else {
        $v = new Validator($_POST);
        $v->required('name', 'Holiday Name')->maxLen('name', 150, 'Holiday Name')
          ->required('date', 'Date')->date('date', 'Date')
          ->inList('type', ['regular', 'special_non_working', 'special_working'], 'Type');
        if ($v->fails()) {
            $msg = $v->errorHtml();
        } else {
            $editId = (int)$_POST['edit_id'];
            Model::updateHoliday($editId, [
                'name'         => trim($_POST['name']),
                'date'         => $_POST['date'],
                'type'         => $_POST['type'],
                'is_recurring' => isset($_POST['is_recurring']) ? 1 : 0,
            ]);
            Model::log($_SESSION['user_id'], 'EDIT_HOLIDAY', "Edited ID:{$editId} — " . trim($_POST['name']));
            $msg = "<div class='alert alert-success alert-auto-dismiss'><i class='fas fa-check-circle mr-2'></i>Holiday updated successfully.</div>";
        }
    }
}

// ── POST: Delete Holiday ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_holiday'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'>Invalid security token.</div>";
    } else {
        $delId = (int)$_POST['holiday_id'];
        Model::deleteHoliday($delId);
        Model::log($_SESSION['user_id'], 'DELETE_HOLIDAY', "Deleted ID:{$delId}");
        $msg = "<div class='alert alert-success alert-auto-dismiss'><i class='fas fa-check-circle mr-2'></i>Holiday deleted.</div>";
    }
}

// ── Data ──────────────────────────────────────────────────────
$year     = (int)($_GET['year'] ?? date('Y'));
$holidays = Model::getHolidaysByYear($year);

$typeLabels = [
    'regular'              => 'Regular Holiday',
    'special_non_working'  => 'Special Non-Working',
    'special_working'      => 'Special Working',
];
$typeColors = [
    'regular'              => '#dc2626',
    'special_non_working'  => '#d97706',
    'special_working'      => '#2563eb',
];
$typeBadge = [
    'regular'              => 'danger',
    'special_non_working'  => 'warning',
    'special_working'      => 'primary',
];
?>

<div class="page-title-bar">
  <i class="fas fa-calendar-day holiday-page-icon"></i>
  <h1>Holidays</h1>
  <button class="btn btn-sm btn-primary ml-auto" data-toggle="modal" data-target="#holidayModal" id="addHolidayBtn">
    <i class="fas fa-plus mr-1"></i> Add Holiday
  </button>
</div>

<?= $msg ?>

<!-- Quick Stats -->
<?php
  $byType   = array_count_values(array_column($holidays, 'type'));
  $upcoming = array_filter($holidays, fn($h) => strtotime($h['date']) >= strtotime(date('Y-m-d')));
?>
<div class="row mb-3">
  <div class="col-xl-3 col-md-6 mb-3">
    <div class="stat-card">
      <div class="icon-box icon-box-red"><i class="fas fa-calendar-day"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= $byType['regular'] ?? 0 ?></div>
        <div class="stat-label">Regular Holidays</div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6 mb-3">
    <div class="stat-card">
      <div class="icon-box icon-box-yellow"><i class="fas fa-umbrella-beach"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= $byType['special_non_working'] ?? 0 ?></div>
        <div class="stat-label">Special Non-Working</div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6 mb-3">
    <div class="stat-card">
      <div class="icon-box icon-box-blue2"><i class="fas fa-briefcase"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= $byType['special_working'] ?? 0 ?></div>
        <div class="stat-label">Special Working</div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6 mb-3">
    <div class="stat-card">
      <div class="icon-box icon-box-green"><i class="fas fa-clock"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= count($upcoming) ?></div>
        <div class="stat-label">Upcoming in <?= $year ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Year Selector -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="d-flex align-items-center holiday-filter-gap">
      <label class="mb-0 font-weight-bold">Year:</label>
      <select name="year" class="form-control form-control-sm holiday-year-select" onchange="this.form.submit()">
        <?php for ($y = date('Y') - 1; $y <= date('Y') + 2; $y++): ?>
          <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
      <span class="text-muted ml-2"><?= count($holidays) ?> holiday<?= count($holidays) !== 1 ? 's' : '' ?> in <?= $year ?></span>
    </form>
  </div>
</div>

<!-- Legend -->
<div class="d-flex mb-3 holiday-legend-gap">
  <?php foreach ($typeLabels as $key => $label): ?>
    <span class="badge badge-<?= $typeBadge[$key] ?> px-2 py-1 holiday-legend-badge">
      <i class="fas fa-circle mr-1 holiday-legend-dot"></i><?= $label ?>
    </span>
  <?php endforeach; ?>
</div>

<!-- Holidays Table -->
<div class="card">
  <div class="card-body p-0">
    <?php if (empty($holidays)): ?>
      <div class="text-center text-muted py-5">
        <i class="fas fa-calendar-day fa-3x mb-3 d-block holiday-empty-icon"></i>
        No holidays found for <?= $year ?>. Click <strong>+ Add Holiday</strong> to get started.
      </div>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th class="holiday-col-date">Date</th>
          <th>Holiday</th>
          <th>Type</th>
          <th class="text-center holiday-col-recurring">Recurring</th>
          <th class="text-right holiday-col-actions"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($holidays as $h):
          $color = $typeColors[$h['type']] ?? '#6366f1';
          $badge = $typeBadge[$h['type']]  ?? 'secondary';
          $label = $typeLabels[$h['type']]  ?? $h['type'];
          $isPast = strtotime($h['date']) < strtotime(date('Y-m-d'));
        ?>
        <tr class="<?= $isPast ? 'text-muted' : '' ?>">
          <td>
            <strong><?= date('M d', strtotime($h['date'])) ?></strong>
            <small class="d-block text-muted"><?= date('l', strtotime($h['date'])) ?></small>
          </td>
          <td>
            <span class="holiday-name-border holiday-type-<?= htmlspecialchars(strtolower($holiday['type'] ?? 'default')) ?>">
              <?= htmlspecialchars($h['name']) ?>
            </span>
          </td>
          <td>
            <span class="badge badge-<?= $badge ?>">
              <?= $label ?>
            </span>
          </td>
          <td class="text-center">
            <?php if ($h['is_recurring']): ?>
              <i class="fas fa-redo text-success" title="Repeats every year"></i>
            <?php else: ?>
              <i class="fas fa-minus text-muted" title="One-time only"></i>
            <?php endif; ?>
          </td>
          <td class="text-right holiday-actions-cell">
            <button class="btn btn-xs btn-outline-warning edit-holiday-btn"
              data-id="<?= $h['id'] ?>"
              data-name="<?= htmlspecialchars($h['name'], ENT_QUOTES) ?>"
              data-date="<?= $h['date'] ?>"
              data-type="<?= $h['type'] ?>"
              data-recurring="<?= $h['is_recurring'] ?>"
              title="Edit">
              <i class="fas fa-edit"></i>
            </button>
            <form method="POST" class="d-inline">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
              <input type="hidden" name="delete_holiday" value="1">
              <input type="hidden" name="holiday_id" value="<?= $h['id'] ?>">
              <button type="submit" class="btn btn-xs btn-outline-danger"
                onclick="return confirm('Delete <?= htmlspecialchars($h['name'], ENT_QUOTES) ?>?')"
                title="Delete">
                <i class="fas fa-trash"></i>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div><!-- /.table-responsive -->
    <?php endif; ?>
  </div>
</div>

<!-- ══ ADD / EDIT MODAL ════════════════════════════════════════ -->
<div class="modal fade" id="holidayModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" id="holidayForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="add_holiday"  id="formModeAdd"  value="1">
        <input type="hidden" name="edit_holiday" id="formModeEdit" value="">
        <input type="hidden" name="edit_id"      id="formEditId"   value="">

        <div class="modal-header">
          <h5 class="modal-title" id="holidayModalTitle">
            <i class="fas fa-calendar-plus mr-2 text-primary"></i>Add Holiday
          </h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>

        <div class="modal-body">
          <div class="form-group">
            <label>Holiday Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="hName" class="form-control" required maxlength="150"
              placeholder="e.g. Independence Day">
          </div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Date <span class="text-danger">*</span></label>
              <input type="date" name="date" id="hDate" class="form-control" required>
            </div>
            <div class="form-group col-md-6">
              <label>Type <span class="text-danger">*</span></label>
              <select name="type" id="hType" class="form-control">
                <option value="regular">Regular Holiday</option>
                <option value="special_non_working">Special Non-Working</option>
                <option value="special_working">Special Working</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <div class="custom-control custom-checkbox">
              <input type="checkbox" class="custom-control-input" id="hRecurring" name="is_recurring" value="1">
              <label class="custom-control-label" for="hRecurring">
                Recurring yearly <small class="text-muted">(e.g. National holidays)</small>
              </label>
            </div>
          </div>

          <!-- PH Holiday type info -->
          <div class="alert alert-light border mt-2 mb-0 py-2 holiday-help-text">
            <strong>PH Labor Code:</strong>
            <br><i class="fas fa-circle text-danger mr-1 holiday-legend-dot"></i><b>Regular</b> — 100% pay even if not worked (e.g. Christmas, Independence Day)
            <br><i class="fas fa-circle text-warning mr-1 holiday-legend-dot"></i><b>Special Non-Working</b> — no work, no pay unless required to work (+30%)
            <br><i class="fas fa-circle text-primary mr-1 holiday-legend-dot"></i><b>Special Working</b> — treated as ordinary working day
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="holidaySubmitBtn">
            <i class="fas fa-save mr-1"></i> Save Holiday
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraJs = <<<'JS'
$(function () {

  // Reset modal to ADD mode
  $('#addHolidayBtn').on('click', function () {
    $('#holidayModalTitle').html('<i class="fas fa-calendar-plus mr-2 text-primary"></i>Add Holiday');
    $('#holidaySubmitBtn').html('<i class="fas fa-save mr-1"></i> Save Holiday');
    $('#holidayForm')[0].reset();
    $('#formModeAdd').val('1');
    $('#formModeEdit').val('');
    $('#formEditId').val('');
  });

  // Populate modal for EDIT
  $(document).on('click', '.edit-holiday-btn', function () {
    const id        = $(this).data('id');
    const name      = $(this).data('name');
    const date      = $(this).data('date');
    const type      = $(this).data('type');
    const recurring = $(this).data('recurring');

    $('#holidayModalTitle').html('<i class="fas fa-calendar-edit mr-2 text-warning"></i>Edit Holiday');
    $('#holidaySubmitBtn').html('<i class="fas fa-save mr-1"></i> Update Holiday');
    $('#formModeAdd').val('');
    $('#formModeEdit').val('1');
    $('#formEditId').val(id);
    $('#hName').val(name);
    $('#hDate').val(date);
    $('#hType').val(type);
    $('#hRecurring').prop('checked', recurring == 1);

    $('#holidayModal').modal('show');
  });

  // Auto-dismiss success alerts
  setTimeout(function () { $('.alert-auto-dismiss').fadeOut(500); }, 3500);

});
JS;

require_once __DIR__ . '/../layouts/admin_footer.php';
?>