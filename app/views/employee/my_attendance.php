<?php
// app/views/employee/my_attendance.php
// Monthly attendance calendar + summary view for the employee self-service portal.
// Supports two view modes: 'list' (table) and 'calendar' (month grid).

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!defined('BASE_URL')) require_once __DIR__ . '/../../../config/config.php';
if (!class_exists('Model')) require_once __DIR__ . '/../../../core/Model.php';

$employeeId  = (int)($_SESSION['employee_id'] ?? 0);
$viewMode    = ($_GET['view'] ?? 'calendar') === 'list' ? 'list' : 'calendar';
$filterMonth = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');

$attendance  = $employeeId ? Model::getAttendanceByEmployee($employeeId, $filterMonth) : [];
$summary     = $employeeId ? Model::getAttendanceSummary($employeeId, $filterMonth) : [];
$employee    = $employeeId ? Model::findEmployeeById($employeeId) : null;

// Build attendance map keyed by date
$attByDate = [];
foreach ($attendance as $row) { $attByDate[$row['date']] = $row; }

// Holidays for the month
$monthStart   = $filterMonth . '-01';
$monthEnd     = date('Y-m-t', strtotime($monthStart));
$holidayRows  = Model::getHolidaysInRange($monthStart, $monthEnd);
$holidays     = [];
foreach ($holidayRows as $h) { $holidays[$h['date']] = $h; }

// Month navigation options from hire date
$monthOptions = [];
$hireDate     = $employee['date_hired'] ?? null;
$startYm      = $hireDate ? date('Y-m', strtotime($hireDate)) : date('Y-m', strtotime('-24 months'));
$cursor       = date('Y-m');
while ($cursor >= $startYm) {
    $monthOptions[$cursor] = date('F Y', strtotime($cursor . '-01'));
    $cursor = date('Y-m', strtotime($cursor . '-01 -1 month'));
}

// Calendar math
$calY     = (int)substr($filterMonth, 0, 4);
$calM     = (int)substr($filterMonth, 5, 2);
$daysInM  = (int)date('t', mktime(0,0,0,$calM,1,$calY));
$firstDow = (int)date('N', mktime(0,0,0,$calM,1,$calY)); // 1=Mon

$empStart = $employee['date_start'] ?? $employee['date_hired'] ?? '';

// Legend
$legend = [
    'present'   => ['Present',    '#22c55e', 'fas fa-check-circle'],
    'late'       => ['Late',       '#f59e0b', 'fas fa-clock'],
    'half_day'   => ['Half Day',   '#a855f7', 'fas fa-adjust'],
    'absent'     => ['Absent',     '#ef4444', 'fas fa-times-circle'],
    'on_leave'   => ['On Leave',   '#3b82f6', 'fas fa-plane-departure'],
    'holiday'    => ['Holiday',    '#14b8a6', 'fas fa-star'],
    'rest_day'   => ['Rest Day',   '#94a3b8', 'fas fa-moon'],
    'no_record'  => ['No Record',  '#fbbf24', 'fas fa-question-circle'],
    'pre_start'  => ['Pre-Start',  '#e5e7eb', 'fas fa-minus-circle'],
];

// Count summary tiles for calendar
$calCounts = array_fill_keys(array_keys($legend), 0);

$pageTitle = 'My Attendance';
require_once __DIR__ . '/../layouts/employee_header.php';
?>

<style>
/* Calendar grid */
.att-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:5px;}
.att-cal-dow{text-align:center;font-weight:700;font-size:.71rem;letter-spacing:.04em;padding:6px 2px;color:#6c757d;text-transform:uppercase;}
.att-cal-dow.wknd{color:#94a3b8;}
.att-cal-cell{border-radius:8px;padding:7px 5px 6px;min-height:80px;background:color-mix(in srgb,var(--cc,#f1f5f9) 12%,white);border:1.5px solid color-mix(in srgb,var(--cc,#e2e8f0) 28%,white);position:relative;transition:box-shadow .15s,transform .1s;cursor:default;}
.att-cal-cell:hover:not(.att-cal-empty){box-shadow:0 3px 10px rgba(0,0,0,.13);transform:translateY(-1px);z-index:2;}
.att-cal-cell.att-cal-empty{background:transparent;border-color:transparent;min-height:80px;}
.att-cal-cell.wknd{opacity:.75;}
.att-cal-cell.today{border-width:2.5px;border-color:#2563eb!important;box-shadow:0 0 0 3px rgba(37,99,235,.15);}
.att-cal-day{font-size:.77rem;font-weight:700;color:#374151;line-height:1;margin-bottom:4px;}
.att-cal-cell.today .att-cal-day{background:#2563eb;color:#fff;width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.71rem;}
.att-cal-ico{font-size:1.05rem;color:var(--cc,#94a3b8);margin-bottom:3px;line-height:1;}
.att-cal-lbl{font-size:.61rem;font-weight:600;color:color-mix(in srgb,var(--cc,#64748b) 80%,#1e293b);line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.att-cal-hol{font-size:.55rem;color:#0f766e;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-style:italic;}
.att-cal-time{font-size:.56rem;color:#6b7280;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.att-cal-leg{display:flex;flex-wrap:wrap;gap:7px 12px;padding-top:10px;border-top:1px solid #e5e7eb;margin-top:10px;}
.att-cal-leg-item{font-size:.72rem;color:#374151;display:flex;align-items:center;gap:5px;}
.att-cal-leg-item i{font-size:.82rem;}
/* Summary tiles */
.sum-tile{text-align:center;border:1px solid #e2e8f0;border-radius:8px;padding:12px 6px;}
.sum-tile .n{font-size:1.4rem;font-weight:800;}
.sum-tile .l{font-size:.72rem;color:#64748b;margin-top:2px;}
/* View toggle */
.view-toggle a{font-size:.85rem;padding:5px 14px;border-radius:6px;border:1.5px solid #e2e8f0;text-decoration:none;color:#475569;font-weight:600;}
.view-toggle a.on{background:#1a2744;color:#fff;border-color:#1a2744;}
@media(max-width:600px){.att-cal-grid{gap:3px;}.att-cal-cell{min-height:60px;padding:5px 3px;}.att-cal-ico{font-size:.9rem;}.att-cal-lbl,.att-cal-time,.att-cal-hol{display:none;}}
</style>

<!-- Page header -->
<div class="page-title-bar">
  <i class="fas fa-clock text-info"></i>
  <h1>My Attendance</h1>
  <div class="ml-auto d-flex align-items-center flex-wrap gap-2">
    <div class="view-toggle d-flex gap-1 mr-2">
      <a href="?month=<?= $filterMonth ?>&view=calendar" class="<?= $viewMode==='calendar'?'on':'' ?>">
        <i class="fas fa-calendar-check mr-1"></i>Calendar
      </a>
      <a href="?month=<?= $filterMonth ?>&view=list" class="<?= $viewMode==='list'?'on':'' ?>">
        <i class="fas fa-list mr-1"></i>List
      </a>
    </div>
    <select onchange="window.location='?month='+this.value+'&view=<?= $viewMode ?>'"
            class="form-control form-control-sm" style="width:auto;">
      <?php foreach ($monthOptions as $val=>$lbl): ?>
        <option value="<?= $val ?>" <?= $filterMonth===$val?'selected':'' ?>><?= $lbl ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<!-- Summary cards -->
<div class="row mb-4">
  <?php
  $tiles = [
    ['Present',  $summary['days_present']  ?? 0, 'success'],
    ['Absent',   $summary['days_absent']   ?? 0, 'danger'],
    ['Late',     $summary['days_late']     ?? 0, 'warning'],
    ['Half Day', $summary['days_half']     ?? 0, 'info'],
    ['On Leave', $summary['days_on_leave'] ?? 0, 'primary'],
    ['Holiday',  $summary['days_holiday']  ?? 0, 'secondary'],
    ['OT Hours', number_format((float)($summary['total_overtime'] ?? 0),1), 'dark'],
  ];
  foreach ($tiles as [$lbl,$val,$clr]): ?>
  <div class="col-xl col-md-3 col-6 mb-3">
    <div class="card sum-tile border-<?= $clr ?>">
      <div class="n text-<?= $clr ?>"><?= $val ?></div>
      <div class="l"><?= $lbl ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php if ($viewMode === 'calendar'): ?>
<!-- ══════════════════════════════════════ CALENDAR VIEW ══ -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <i class="fas fa-calendar-check mr-2 text-success"></i>
      <strong><?= date('F Y', mktime(0,0,0,$calM,1,$calY)) ?></strong>
    </div>
    <div>
      <?php
        $prevM = date('Y-m', mktime(0,0,0,$calM-1,1,$calY));
        $nextM = date('Y-m', mktime(0,0,0,$calM+1,1,$calY));
      ?>
      <a href="?month=<?= $prevM ?>&view=calendar" class="btn btn-sm btn-outline-secondary mr-1">
        <i class="fas fa-chevron-left"></i> <?= date('M', mktime(0,0,0,$calM-1,1,$calY)) ?>
      </a>
      <a href="?month=<?= $nextM ?>&view=calendar" class="btn btn-sm btn-outline-secondary">
        <?= date('M', mktime(0,0,0,$calM+1,1,$calY)) ?> <i class="fas fa-chevron-right"></i>
      </a>
    </div>
  </div>
  <div class="card-body p-3">
    <div class="att-cal-grid">
      <!-- Day-of-week headers -->
      <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dh): ?>
        <div class="att-cal-dow <?= in_array($dh,['Sat','Sun'])?'wknd':'' ?>"><?= $dh ?></div>
      <?php endforeach; ?>

      <!-- Blank cells before first day -->
      <?php for ($b=1;$b<$firstDow;$b++): ?>
        <div class="att-cal-cell att-cal-empty"></div>
      <?php endfor; ?>

      <!-- Day cells -->
      <?php for ($day=1;$day<=$daysInM;$day++):
        $ds      = sprintf('%04d-%02d-%02d',$calY,$calM,$day);
        $dow     = (int)date('N',mktime(0,0,0,$calM,$day,$calY));
        $isWkend = $dow>=6;
        $isHol   = isset($holidays[$ds]);
        $holInfo = $holidays[$ds] ?? null;
        $isToday = ($ds===date('Y-m-d'));
        $isPre   = ($empStart && $ds<$empStart);
        $isFuture= ($ds>date('Y-m-d'));
        $att     = $attByDate[$ds] ?? null;

        if ($isPre) {
            $cellStatus='pre_start'; $cellLabel='Pre-Employment';
        } elseif ($att) {
            $cellStatus=$att['status'];
            $cellLabel=$legend[$cellStatus][0] ?? ucfirst($cellStatus);
            if ($cellStatus==='on_leave'&&!empty($att['leave_type'])) {
                $cellLabel=ucwords(str_replace('_',' ',$att['leave_type'])).' Leave';
            }
        } elseif ($isHol) {
            $cellStatus='holiday'; $cellLabel=$holInfo['name'];
        } elseif ($isWkend) {
            $cellStatus='rest_day'; $cellLabel='Rest Day';
        } elseif ($isFuture) {
            $cellStatus='pre_start'; $cellLabel='—';
        } else {
            $cellStatus='no_record'; $cellLabel='No Record';
        }

        // Count summary
        if (!$isPre && !$isFuture && isset($calCounts[$cellStatus])) {
            $calCounts[$cellStatus]++;
        }

        $cfg   = $legend[$cellStatus] ?? ['—','#e5e7eb','fas fa-circle'];
        $color = $cfg[1];
        $icon  = $cfg[2];
      ?>
        <div class="att-cal-cell <?= $isWkend?'wknd':'' ?> <?= $isToday?'today':'' ?>"
             style="--cc:<?= $color ?>;">
          <div class="att-cal-day"><?= $day ?></div>
          <div class="att-cal-ico"><i class="<?= $icon ?>"></i></div>
          <div class="att-cal-lbl"><?= htmlspecialchars($cellLabel) ?></div>
          <?php if ($isHol&&$holInfo&&!$isPre): ?>
            <div class="att-cal-hol"><?= htmlspecialchars($holInfo['name']) ?></div>
          <?php endif; ?>
          <?php if ($att&&!empty($att['time_in'])): ?>
            <div class="att-cal-time"><?= substr($att['time_in'],0,5) ?>–<?= substr($att['time_out']??'?',0,5) ?></div>
          <?php endif; ?>
        </div>
      <?php endfor; ?>

      <!-- Trailing blank cells -->
      <?php
        $total    = ($firstDow-1)+$daysInM;
        $trailing = ($total%7===0) ? 0 : 7-($total%7);
        for ($b=0;$b<$trailing;$b++): ?>
          <div class="att-cal-cell att-cal-empty"></div>
      <?php endfor; ?>
    </div><!-- /.att-cal-grid -->

    <!-- Legend -->
    <div class="att-cal-leg">
      <?php foreach ($legend as $key=>[$lbl,$clr,$ico]): ?>
        <span class="att-cal-leg-item" style="--cc:<?= $clr ?>;">
          <i class="<?= $ico ?>" style="color:<?= $clr ?>;"></i><?= $lbl ?>
        </span>
      <?php endforeach; ?>
    </div>
  </div><!-- /.card-body -->
</div><!-- /.card -->

<?php else: ?>
<!-- ══════════════════════════════════════════ LIST VIEW ══ -->
<div class="card">
  <div class="card-header">
    <i class="fas fa-list mr-2"></i>Attendance Records — <?= date('F Y', strtotime($filterMonth.'-01')) ?>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Date</th><th>Day</th><th>Status</th>
            <th>Time In</th><th>Time Out</th>
            <th class="text-center">Hours</th><th class="text-center">OT Hrs</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($attendance)): ?>
            <tr><td colspan="7" class="text-center py-4 text-muted">
              <i class="fas fa-calendar-times fa-2x d-block mb-2 opacity-50"></i>
              No attendance records for this month.
            </td></tr>
          <?php else: ?>
            <?php foreach ($attendance as $rec):
              $statusMap = ['present'=>'success','late'=>'warning','absent'=>'danger',
                            'half_day'=>'info','on_leave'=>'primary','holiday'=>'secondary','rest_day'=>'light'];
              $statusLabel = match($rec['status']) {
                'on_leave' => ucwords(str_replace('_',' ',$rec['leave_type']??'')).' Leave',
                default    => $legend[$rec['status']][0] ?? ucfirst($rec['status']),
              };
            ?>
            <tr>
              <td><?= date('M j, Y',strtotime($rec['date'])) ?></td>
              <td><?= date('D',strtotime($rec['date'])) ?></td>
              <td><span class="badge badge-<?= $statusMap[$rec['status']]??'secondary' ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
              <td><?= $rec['time_in'] ? substr($rec['time_in'],0,5) : '—' ?></td>
              <td><?= $rec['time_out'] ? substr($rec['time_out'],0,5) : '—' ?></td>
              <td class="text-center"><?= $rec['hours_worked'] ? number_format((float)$rec['hours_worked'],2) : '—' ?></td>
              <td class="text-center">
                <?php if ((float)($rec['overtime_hours']??0)>0): ?>
                  <span class="badge badge-success"><?= number_format((float)$rec['overtime_hours'],1) ?></span>
                <?php else: ?>—<?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/employee_footer.php'; ?>