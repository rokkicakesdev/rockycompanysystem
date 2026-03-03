<?php
$pageTitle = 'Recruitment';
require_once __DIR__ . '/../layouts/admin_header.php';

$msg = '';
$view = $_GET['view'] ?? 'postings'; // postings | applicants

// Handle new job posting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_posting'])) {
    Model::createJobPosting([
        'department_id'   => (int)$_POST['department_id'],
        'position_id'     => !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null,
        'title'           => $_POST['title'],
        'description'     => $_POST['description'] ?? null,
        'requirements'    => $_POST['requirements'] ?? null,
        'slots'           => (int)$_POST['slots'],
        'salary_min'      => !empty($_POST['salary_min']) ? (float)$_POST['salary_min'] : null,
        'salary_max'      => !empty($_POST['salary_max']) ? (float)$_POST['salary_max'] : null,
        'employment_type' => $_POST['employment_type'] ?? 'regular',
        'deadline'        => !empty($_POST['deadline']) ? $_POST['deadline'] : null,
        'posted_by'       => $_SESSION['user_id'],
    ]);
    Model::log($_SESSION['user_id'], 'CREATE_JOB_POSTING', "Posted: " . $_POST['title']);
    $msg = "<div class='alert alert-success alert-auto-dismiss'>Job posting created successfully.</div>";
}

// Handle new applicant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_applicant'])) {
    Model::createApplicant([
        'job_posting_id' => (int)$_POST['job_posting_id'],
        'name'           => $_POST['name'],
        'email'          => $_POST['email'] ?? null,
        'phone'          => $_POST['phone'] ?? null,
        'source'         => $_POST['source'] ?? 'walk_in',
        'notes'          => $_POST['notes'] ?? null,
    ]);
    Model::log($_SESSION['user_id'], 'ADD_APPLICANT', "Added applicant: " . $_POST['name']);
    $msg = "<div class='alert alert-success alert-auto-dismiss'>Applicant added successfully.</div>";
}

// Handle applicant status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_applicant'])) {
    Model::updateApplicantStatus(
        (int)$_POST['applicant_id'],
        $_POST['status'],
        $_SESSION['user_id'],
        $_POST['notes'] ?? '',
        !empty($_POST['interview_date']) ? $_POST['interview_date'] : null
    );
    Model::log($_SESSION['user_id'], 'UPDATE_APPLICANT', "Updated applicant ID:" . $_POST['applicant_id'] . " to " . $_POST['status']);
    $msg = "<div class='alert alert-success alert-auto-dismiss'>Applicant status updated.</div>";
}

// Handle job posting status change
if (isset($_GET['toggle_job']) && is_numeric($_GET['toggle_job'])) {
    $newStatus = $_GET['new_status'] ?? 'closed';
    Model::updateJobPostingStatus((int)$_GET['toggle_job'], $newStatus);
    $msg = "<div class='alert alert-success alert-auto-dismiss'>Job posting updated.</div>";
}

$selectedJobId = isset($_GET['job_id']) ? (int)$_GET['job_id'] : null;
$postings      = Model::getAllJobPostings();
$departments   = Model::getAllDepartments();
$positions     = Model::getAllPositions();
$applicants    = $selectedJobId ? Model::getApplicantsByJob($selectedJobId) : [];
$selectedJob   = $selectedJobId ? Model::findJobPostingById($selectedJobId) : null;

$applicantStatuses = [
    'new'        => ['label'=>'New',        'color'=>'#6366f1'],
    'screening'  => ['label'=>'Screening',  'color'=>'#d97706'],
    'interview'  => ['label'=>'Interview',  'color'=>'#2563eb'],
    'exam'       => ['label'=>'Exam',       'color'=>'#7c3aed'],
    'job_offer'  => ['label'=>'Job Offer',  'color'=>'#0891b2'],
    'hired'      => ['label'=>'Hired',      'color'=>'#16a34a'],
    'rejected'   => ['label'=>'Rejected',   'color'=>'#dc2626'],
    'withdrawn'  => ['label'=>'Withdrawn',  'color'=>'#94a3b8'],
];
?>

<div class="page-title-bar">
    <i class="fas fa-briefcase" class="text-primary"></i>
    <h1>Recruitment</h1>
    <button class="btn btn-sm btn-primary ml-auto" data-toggle="modal" data-target="#newPostingModal">
      <i class="fas fa-plus mr-1"></i>New Job Posting
    </button>
  </div>

<?= $msg ?>

    <div class="row">
      <!-- Job Postings List -->
      <div class="col-lg-5">
        <div class="card">
          <div class="card-header">
            <i class="fas fa-clipboard-list mr-2"></i>Job Postings
            <span class="badge badge-primary ml-1"><?= count($postings) ?></span>
          </div>
          <div class="card-body p-0" class="recruitment-jobs-scroll">
            <?php foreach ($postings as $post): ?>
            <div class="p-3 border-bottom <?= ($selectedJobId===$post['id'])?'bg-light':'' ?>"
                 style="cursor:pointer;" onclick="window.location='recruitment.php?job_id=<?= $post['id'] ?>'">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <strong style="font-size:.88rem;"><?= htmlspecialchars($post['title']) ?></strong>
                  <br><small class="text-muted"><?= htmlspecialchars($post['department_name']) ?></small>
                </div>
                <div class="text-right">
                  <span class="status-badge badge-<?= $post['status'] ?> mb-1"><?= ucfirst($post['status']) ?></span>
                  <br><small class="text-muted"><?= $post['applicant_count'] ?> applicants</small>
                </div>
              </div>
              <div class="mt-1">
                <?php if ($post['salary_min'] && $post['salary_max']): ?>
                  <small class="text-success">₱<?= number_format($post['salary_min'],0) ?> – ₱<?= number_format($post['salary_max'],0) ?></small>
                <?php endif; ?>
                <?php if ($post['deadline']): ?>
                  <small class="text-muted ml-2"><i class="fas fa-calendar-alt"></i> <?= date('M d', strtotime($post['deadline'])) ?></small>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Applicants Panel -->
      <div class="col-lg-7">
        <?php if ($selectedJob): ?>
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <strong><?= htmlspecialchars($selectedJob['title']) ?></strong>
              <span class="text-muted ml-2">— <?= htmlspecialchars($selectedJob['department_name']) ?></span>
            </div>
            <div>
              <button class="btn btn-xs btn-primary" data-toggle="modal" data-target="#addApplicantModal">
                <i class="fas fa-user-plus mr-1"></i>Add Applicant
              </button>
              <?php if ($selectedJob['status'] === 'open'): ?>
                <a href="recruitment.php?toggle_job=<?= $selectedJob['id'] ?>&new_status=closed&job_id=<?= $selectedJob['id'] ?>" class="btn btn-xs btn-secondary ml-1">Close Posting</a>
              <?php else: ?>
                <a href="recruitment.php?toggle_job=<?= $selectedJob['id'] ?>&new_status=open&job_id=<?= $selectedJob['id'] ?>" class="btn btn-xs btn-success ml-1">Reopen</a>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body p-0">
            <?php if (empty($applicants)): ?>
              <div class="text-center py-5 text-muted">
                <i class="fas fa-user-plus fa-2x mb-2 d-block" style="color:#cbd5e1;"></i>
                No applicants yet. Click "Add Applicant" to get started.
              </div>
            <?php else: ?>
              <table class="table table-hover mb-0">
                <thead><tr><th>Name</th><th>Contact</th><th>Source</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                  <?php foreach ($applicants as $app):
                    $sc = $applicantStatuses[$app['status']];
                  ?>
                  <tr>
                    <td>
                      <strong style="font-size:.84rem;"><?= htmlspecialchars($app['name']) ?></strong>
                      <?php if ($app['interview_date']): ?>
                        <br><small class="text-primary"><i class="fas fa-calendar-check"></i> <?= date('M d H:i', strtotime($app['interview_date'])) ?></small>
                      <?php endif; ?>
                    </td>
                    <td>
                      <small><?= htmlspecialchars($app['email'] ?? '—') ?></small><br>
                      <small><?= htmlspecialchars($app['phone'] ?? '') ?></small>
                    </td>
                    <td><small><?= ucfirst(str_replace('_',' ',$app['source'])) ?></small></td>
                    <td>
                      <span class="status-badge" style="background:<?= $sc['color'] ?>20;color:<?= $sc['color'] ?>;">
                        <?= $sc['label'] ?>
                      </span>
                    </td>
                    <td>
                      <button class="btn btn-xs btn-outline-primary" data-toggle="modal" data-target="#updateApplicantModal"
                        data-id="<?= $app['id'] ?>" data-name="<?= htmlspecialchars($app['name']) ?>"
                        data-status="<?= $app['status'] ?>">
                        Update
                      </button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
        <?php else: ?>
        <div class="card">
          <div class="card-body text-center py-5 text-muted">
            <i class="fas fa-mouse-pointer fa-2x mb-2 d-block" style="color:#cbd5e1;"></i>
            Select a job posting to view applicants
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
</div>

<!-- New Posting Modal -->
<div class="modal fade" id="newPostingModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="new_posting" value="1">
        <div class="modal-header"><h5 class="modal-title">Create Job Posting</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
        <div class="modal-body">
          <div class="row">
            <div class="col-6">
              <div class="form-group">
                <label>Job Title <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" required>
              </div>
            </div>
            <div class="col-6">
              <div class="form-group">
                <label>Department <span class="text-danger">*</span></label>
                <select name="department_id" id="postDeptId" class="form-control" required>
                  <option value="">-- Select --</option>
                  <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-4">
              <div class="form-group">
                <label>Slots</label>
                <input type="number" name="slots" class="form-control" value="1" min="1">
              </div>
            </div>
            <div class="col-4">
              <div class="form-group">
                <label>Min Salary</label>
                <input type="number" name="salary_min" class="form-control" placeholder="e.g. 25000">
              </div>
            </div>
            <div class="col-4">
              <div class="form-group">
                <label>Max Salary</label>
                <input type="number" name="salary_max" class="form-control" placeholder="e.g. 40000">
              </div>
            </div>
            <div class="col-6">
              <div class="form-group">
                <label>Employment Type</label>
                <select name="employment_type" class="form-control">
                  <?php foreach (EMPLOYMENT_TYPES as $k => $v): ?>
                    <option value="<?= $k ?>"><?= $v ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-6">
              <div class="form-group">
                <label>Application Deadline</label>
                <input type="date" name="deadline" class="form-control">
              </div>
            </div>
            <div class="col-12">
              <div class="form-group">
                <label>Job Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Describe the role..."></textarea>
              </div>
            </div>
            <div class="col-12">
              <div class="form-group">
                <label>Requirements</label>
                <textarea name="requirements" class="form-control" rows="3" placeholder="List qualifications and requirements..."></textarea>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Posting</button>
        </div>
      </form>
    </div>
</div>

<!-- Add Applicant Modal -->
<div class="modal fade" id="addApplicantModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="new_applicant" value="1">
        <input type="hidden" name="job_posting_id" value="<?= $selectedJobId ?>">
        <div class="modal-header"><h5 class="modal-title">Add Applicant</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
        <div class="modal-body">
          <div class="form-group"><label>Full Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required></div>
          <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control"></div>
          <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control"></div>
          <div class="form-group">
            <label>Source</label>
            <select name="source" class="form-control">
              <option value="walk_in">Walk-in</option>
              <option value="referral">Referral</option>
              <option value="jobstreet">JobStreet</option>
              <option value="linkedin">LinkedIn</option>
              <option value="indeed">Indeed</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="form-group"><label>Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Applicant</button>
        </div>
      </form>
    </div>
</div>

<!-- Update Applicant Modal -->
<div class="modal fade" id="updateApplicantModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="update_applicant" value="1">
        <input type="hidden" name="applicant_id" id="updateAppId">
        <div class="modal-header">
          <h5 class="modal-title">Update Applicant: <span id="updateAppName"></span></h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Status</label>
            <select name="status" id="updateAppStatus" class="form-control">
              <?php foreach ($applicantStatuses as $k => $v): ?>
                <option value="<?= $k ?>"><?= $v['label'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Interview Date/Time</label>
            <input type="datetime-local" name="interview_date" class="form-control">
          </div>
          <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" class="form-control" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
$('#updateApplicantModal').on('show.bs.modal', function(e) {
  const btn = $(e.relatedTarget);
  $('#updateAppId').val(btn.data('id'));
  $('#updateAppName').text(btn.data('name'));
  $('#updateAppStatus').val(btn.data('status'));
});
</script>
<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>