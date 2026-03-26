<?php
$pageTitle = 'Announcements';
require_once __DIR__ . '/../layouts/admin_header.php';
if ($_SESSION['role'] !== ROLE_ADMIN) { header('Location: dashboard.php'); exit; }

$msg = '';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ── POST: Create ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_announcement'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'>Invalid security token.</div>";
    } else {
        require_once __DIR__ . '/../../../core/Validator.php';
        $v = new Validator($_POST);
        $v->required('title', 'Title')->maxLen('title', 200, 'Title')
          ->required('content', 'Content')->maxLen('content', 2000, 'Content');
        if ($v->fails()) {
            $msg = $v->errorHtml();
        } else {
            Model::createAnnouncement([
                'title'      => trim($_POST['title']),
                'content'    => trim($_POST['content']),
                'type'       => $_POST['type']      ?? 'general',
                'is_pinned'  => isset($_POST['is_pinned']) ? 1 : 0,
                'expires_at' => !empty($_POST['expires_at']) ? $_POST['expires_at'] : null,
                'posted_by'  => $_SESSION['user_id'],
            ]);
            Model::log($_SESSION['user_id'], 'CREATE_ANNOUNCEMENT', "Posted: " . trim($_POST['title']));
            $msg = "<div class='alert alert-success alert-auto-dismiss'><i class='fas fa-check-circle mr-2'></i>Announcement posted successfully.</div>";
        }
    }
}

// ── POST: Edit ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_announcement'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'>Invalid security token.</div>";
    } else {
        require_once __DIR__ . '/../../../core/Validator.php';
        $v = new Validator($_POST);
        $v->required('title', 'Title')->maxLen('title', 200, 'Title')
          ->required('content', 'Content')->maxLen('content', 2000, 'Content');
        if ($v->fails()) {
            $msg = $v->errorHtml();
        } else {
            $editId = (int)$_POST['edit_id'];
            Model::updateAnnouncement($editId, [
                'title'      => trim($_POST['title']),
                'content'    => trim($_POST['content']),
                'type'       => $_POST['type']      ?? 'general',
                'is_pinned'  => isset($_POST['is_pinned']) ? 1 : 0,
                'expires_at' => !empty($_POST['expires_at']) ? $_POST['expires_at'] : null,
            ]);
            Model::log($_SESSION['user_id'], 'EDIT_ANNOUNCEMENT', "Edited ID:{$editId} — " . trim($_POST['title']));
            $msg = "<div class='alert alert-success alert-auto-dismiss'><i class='fas fa-check-circle mr-2'></i>Announcement updated successfully.</div>";
        }
    }
}

// ── GET: Delete ───────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    Model::deleteAnnouncement($delId);
    Model::log($_SESSION['user_id'], 'DELETE_ANNOUNCEMENT', "Deleted ID:{$delId}");
    $msg = "<div class='alert alert-success alert-auto-dismiss'><i class='fas fa-check-circle mr-2'></i>Announcement deleted.</div>";
}

$announcements = Model::getActiveAnnouncements();
$typeColors    = [
    'general' => '#6366f1',
    'payroll' => '#2563eb',
    'leave'   => '#d97706',
    'holiday' => '#16a34a',
    'urgent'  => '#dc2626',
];
$typeIcons = [
    'general' => 'fa-bullhorn',
    'payroll' => 'fa-peso-sign',
    'leave'   => 'fa-calendar-minus',
    'holiday' => 'fa-umbrella-beach',
    'urgent'  => 'fa-exclamation-circle',
];
?>

<div class="page-title-bar">
  <i class="fas fa-bullhorn text-primary"></i>
  <h1>Announcements</h1>
  <button class="btn btn-sm btn-primary ml-auto" data-toggle="modal" data-target="#announcementModal" id="newAnnouncementBtn">
    <i class="fas fa-plus mr-1"></i> Post Announcement
  </button>
</div>

<?= $msg ?>

<!-- Announcement Cards -->
<div class="row">
  <?php if (empty($announcements)): ?>
    <div class="col-12 text-center text-muted py-5">
      <i class="fas fa-bullhorn fa-3x mb-3 d-block ann-empty-icon"></i>
      No announcements posted yet.
    </div>
  <?php endif; ?>

  <?php foreach ($announcements as $ann):
    $color = $typeColors[$ann['type']] ?? '#6366f1';
    $icon  = $typeIcons[$ann['type']]  ?? 'fa-bullhorn';
  ?>
  <div class="col-md-6 mb-3">
    <div class="card h-100 ann-card-<?= htmlspecialchars($ann['type'] ?? 'general') ?> border-left-type">
      <div class="card-body">

        <!-- Title row -->
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div>
            <?php if ($ann['is_pinned']): ?>
              <i class="fas fa-thumbtack mr-1 ann-pin-icon" title="Pinned"></i>
            <?php endif; ?>
            <strong><?= htmlspecialchars($ann['title']) ?></strong>
            <span class="badge ml-1 ann-type-<?= htmlspecialchars($ann['type'] ?? 'general') ?>">
              <i class="fas <?= $icon ?> mr-1"></i><?= ucfirst($ann['type']) ?>
            </span>
            <?php if ($ann['is_pinned']): ?>
              <span class="badge badge-warning ml-1"><i class="fas fa-thumbtack mr-1"></i>Pinned</span>
            <?php endif; ?>
          </div>
          <!-- Action buttons -->
          <div class="action-btn-group ml-2 flex-shrink-0">
            <button class="btn btn-xs btn-outline-primary edit-ann-btn"
              data-id="<?= $ann['id'] ?>"
              data-title="<?= htmlspecialchars($ann['title'], ENT_QUOTES) ?>"
              data-content="<?= htmlspecialchars($ann['content'], ENT_QUOTES) ?>"
              data-type="<?= htmlspecialchars($ann['type']) ?>"
              data-pinned="<?= $ann['is_pinned'] ?>"
              data-expires="<?= htmlspecialchars($ann['expires_at'] ?? '') ?>"
              title="Edit">
              <i class="fas fa-edit"></i>
            </button>
            <a href="announcements.php?delete=<?= $ann['id'] ?>"
               class="btn btn-xs btn-outline-danger"
               onclick="return confirm('Delete this announcement?')"
               title="Delete">
              <i class="fas fa-trash"></i>
            </a>
          </div>
        </div>

        <!-- Content -->
        <p class="mb-2 ann-content-text"><?= nl2br(htmlspecialchars($ann['content'])) ?></p>

        <!-- Meta -->
        <small class="text-muted">
          <i class="fas fa-user mr-1"></i><?= htmlspecialchars($ann['posted_by_name'] ?? 'System') ?>
          &bull; <i class="fas fa-calendar mr-1"></i><?= date('M d, Y', strtotime($ann['created_at'])) ?>
          <?php if ($ann['expires_at']): ?>
            &bull; <i class="fas fa-clock mr-1"></i>Expires: <?= date('M d, Y', strtotime($ann['expires_at'])) ?>
          <?php endif; ?>
        </small>

      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ══════════════════════════════════════════════
     CREATE / EDIT MODAL (shared)
     ══════════════════════════════════════════════ -->
<div class="modal fade" id="announcementModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" id="announcementForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <!-- Switches between new_announcement and edit_announcement -->
        <input type="hidden" name="new_announcement" id="formModeNew" value="1">
        <input type="hidden" name="edit_announcement" id="formModeEdit" value="">
        <input type="hidden" name="edit_id" id="formEditId" value="">

        <div class="modal-header">
          <h5 class="modal-title" id="announcementModalTitle">
            <i class="fas fa-bullhorn mr-2 text-primary"></i>Post Announcement
          </h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>

        <div class="modal-body">
          <div class="form-group">
            <label>Title <span class="text-danger">*</span></label>
            <input type="text" name="title" id="annTitle" class="form-control" required maxlength="255">
          </div>
          <div class="form-group">
            <label>Content <span class="text-danger">*</span></label>
            <textarea name="content" id="annContent" class="form-control" rows="4" required></textarea>
          </div>
          <div class="row">
            <div class="col-6">
              <div class="form-group">
                <label>Type</label>
                <select name="type" id="annType" class="form-control">
                  <option value="general">General</option>
                  <option value="payroll">Payroll</option>
                  <option value="leave">Leave</option>
                  <option value="holiday">Holiday</option>
                  <option value="urgent">Urgent</option>
                </select>
              </div>
            </div>
            <div class="col-6">
              <div class="form-group">
                <label>Expires On <small class="text-muted">(optional)</small></label>
                <input type="date" name="expires_at" id="annExpires" class="form-control">
              </div>
            </div>
          </div>
          <div class="form-check mt-1">
            <input type="checkbox" name="is_pinned" class="form-check-input" id="annPinned">
            <label class="form-check-label" for="annPinned">
              <i class="fas fa-thumbtack mr-1 ann-pin-icon"></i> Pin this announcement
            </label>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="annSubmitBtn">
            <i class="fas fa-paper-plane mr-1"></i> Post
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraJs = <<<'JS'
// New announcement — reset form to create mode
$('#newAnnouncementBtn').on('click', function() {
  $('#announcementModalTitle').html('<i class="fas fa-bullhorn mr-2 text-primary"></i>Post Announcement');
  $('#announcementForm')[0].reset();
  $('#formModeNew').attr('name', 'new_announcement').val('1');
  $('#formModeEdit').attr('name', '').val('');
  $('#formEditId').val('');
  $('#annSubmitBtn').html('<i class="fas fa-paper-plane mr-1"></i> Post');
});

// Edit announcement — populate form with existing data
$('.edit-ann-btn').on('click', function() {
  const d = $(this).data();
  $('#announcementModalTitle').html('<i class="fas fa-edit mr-2 text-primary"></i>Edit Announcement');
  $('#annTitle').val(d.title);
  $('#annContent').val(d.content);
  $('#annType').val(d.type);
  $('#annExpires').val(d.expires || '');
  $('#annPinned').prop('checked', d.pinned == 1);
  $('#formModeNew').attr('name', '').val('');
  $('#formModeEdit').attr('name', 'edit_announcement').val('1');
  $('#formEditId').val(d.id);
  $('#annSubmitBtn').html('<i class="fas fa-save mr-1"></i> Save Changes');
  $('#announcementModal').modal('show');
});
JS;

require_once __DIR__ . '/../layouts/admin_footer.php';
?>