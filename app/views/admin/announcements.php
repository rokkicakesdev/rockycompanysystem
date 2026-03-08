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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_announcement'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'>Invalid security token. Please refresh and try again.</div>";
    } else {
        Model::createAnnouncement([
            'title'      => trim($_POST['title']),
            'content'    => trim($_POST['content']),
            'type'       => $_POST['type']       ?? 'general',
            'is_pinned'  => isset($_POST['is_pinned']) ? 1 : 0,
            'expires_at' => !empty($_POST['expires_at']) ? $_POST['expires_at'] : null,
            'posted_by'  => $_SESSION['user_id'],
        ]);
        $msg = "<div class='alert alert-success alert-auto-dismiss'>Announcement posted.</div>";
    }
}
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    Model::deleteAnnouncement((int)$_GET['delete']);
    $msg = "<div class='alert alert-success alert-auto-dismiss'>Announcement deleted.</div>";
}

$announcements = Model::getActiveAnnouncements();
$typeColors = ['general'=>'#6366f1','payroll'=>'#2563eb','leave'=>'#d97706','holiday'=>'#16a34a','urgent'=>'#dc2626'];
?>

<div class="page-title-bar">
    <i class="fas fa-bullhorn" class="text-primary"></i>
    <h1>Announcements</h1>
    <button class="btn btn-sm btn-primary ml-auto" data-toggle="modal" data-target="#newAnnouncementModal">
      <i class="fas fa-plus mr-1"></i>Post Announcement
    </button>
  </div>

<?= $msg ?>
    <div class="row">
      <?php foreach ($announcements as $ann):
        $color = $typeColors[$ann['type']] ?? '#6366f1';
      ?>
      <div class="col-md-6 mb-3">
        <div class="card h-100" style="border-left:4px solid <?= $color ?> !important;">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <?php if ($ann['is_pinned']): ?>
                  <i class="fas fa-thumbtack mr-1" style="color:#d97706;" title="Pinned"></i>
                <?php endif; ?>
                <strong><?= htmlspecialchars($ann['title']) ?></strong>
                <span class="badge ml-2" style="background:<?= $color ?>20;color:<?= $color ?>;"><?= ucfirst($ann['type']) ?></span>
              </div>
              <a href="announcements.php?delete=<?= $ann['id'] ?>" class="btn btn-xs btn-outline-danger"
                 onclick="return confirm('Delete this announcement?')">
                <i class="fas fa-trash"></i>
              </a>
            </div>
            <p class="mt-2 mb-1" style="font-size:.85rem;"><?= nl2br(htmlspecialchars($ann['content'])) ?></p>
            <small class="text-muted">
              By <?= htmlspecialchars($ann['posted_by_name'] ?? 'System') ?> &bull;
              <?= date('M d, Y', strtotime($ann['created_at'])) ?>
              <?php if ($ann['expires_at']): ?> &bull; Expires: <?= date('M d, Y', strtotime($ann['expires_at'])) ?><?php endif; ?>
            </small>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($announcements)): ?>
        <div class="col-12 text-center text-muted py-5">No announcements posted yet.</div>
      <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="newAnnouncementModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="POST">
      <input type="hidden" name="new_announcement" value="1">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <div class="modal-header"><h5 class="modal-title">Post Announcement</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
      <div class="modal-body">
        <div class="form-group"><label>Title *</label><input type="text" name="title" class="form-control" required></div>
        <div class="form-group"><label>Content *</label><textarea name="content" class="form-control" rows="4" required></textarea></div>
        <div class="row">
          <div class="col-6">
            <div class="form-group">
              <label>Type</label>
              <select name="type" class="form-control">
                <option value="general">General</option>
                <option value="payroll">Payroll</option>
                <option value="leave">Leave</option>
                <option value="holiday">Holiday</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>
          </div>
          <div class="col-6">
            <div class="form-group"><label>Expires On</label><input type="date" name="expires_at" class="form-control"></div>
          </div>
        </div>
        <div class="form-check">
          <input type="checkbox" name="is_pinned" class="form-check-input" id="isPinned">
          <label class="form-check-label" for="isPinned">Pin this announcement</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Post</button>
      </div>
    </form>
  </div></div>
</div>
<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>