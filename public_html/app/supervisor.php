<?php
// Supervisor dashboard for regular sales team
// Shows team metrics, recent activity, pending forward approvals,
// and a team query list (no inline assignment; only View -> query_supervisor.php).

require_once __DIR__ . '/auth.php';
require_perm('assign_team_member'); // supervisors only

$adminId = $_SESSION['admin']['id'] ?? 0;

// Figure out which teams this supervisor oversees: leader teams + membership teams.
// If none, default to team 1 (Regular Sales).
$teamIds = [];

// Leader teams
$st = db()->prepare("SELECT id FROM teams WHERE leader_admin_user_id=?");
$st->execute([$adminId]);
foreach ($st->fetchAll() as $row) $teamIds[] = (int)$row['id'];

// Membership teams
$st = db()->prepare("SELECT team_id FROM admin_user_teams WHERE admin_user_id=?");
$st->execute([$adminId]);
foreach ($st->fetchAll() as $row) $teamIds[] = (int)$row['team_id'];

$teamIds = array_values(array_unique($teamIds));
if (!$teamIds) $teamIds = [1]; // fallback to Regular Sales

// Helper for redirects
function redirect_back() {
  header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
  exit;
}

// ===== Handle only forward-approval actions here (no assign/auto on this page) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $pdo = db();
  $inTeams = implode(',', array_map('intval', $teamIds));

  // Approve forwarding request
  if (isset($_POST['approve_forward_action'])) {
    $qid = (int)($_POST['qid'] ?? 0);
    if ($qid > 0 && can('approve_forwarding')) {
      // Ensure the query is within supervisor's teams
      $q = $pdo->prepare(
        "SELECT forward_request_team_id, forward_request_priority, forward_request_by
           FROM queries
          WHERE id=? AND current_team_id IN ($inTeams)
          LIMIT 1"
      );
      $q->execute([$qid]);
      $req = $q->fetch(PDO::FETCH_ASSOC);

      if ($req && $req['forward_request_team_id']) {
        $targetTeam = (int)$req['forward_request_team_id'];
        $priority   = $req['forward_request_priority'] ?: 'default';

        // Move query, reset SLA, clear request fields
        $pdo->prepare(
          "UPDATE queries
              SET current_team_id=?,
                  priority=?,
                  status='assigned',
                  sla_due_at=DATE_ADD(NOW(), INTERVAL 24 HOUR),
                  assigned_admin_user_id=NULL,
                  forward_request_team_id=NULL,
                  forward_request_priority=NULL,
                  forward_request_by=NULL,
                  forward_request_at=NULL
            WHERE id=?"
        )->execute([$targetTeam, $priority, $qid]);

        // Record assignment
        $pdo->prepare(
          "INSERT INTO query_assignments (query_id, team_id, assigned_by, assigned_at, priority, note)
           VALUES (?, ?, ?, NOW(), ?, 'Forwarded (approved)')"
        )->execute([$qid, $targetTeam, $adminId, $priority]);

        // Audit
        $pdo->prepare(
          "INSERT INTO audit_logs (entity_type, entity_id, admin_user_id, action, meta, created_at)
           VALUES ('query', ?, ?, 'assigned', JSON_OBJECT('to_team', ?, 'priority', ?), NOW())"
        )->execute([$qid, $adminId, $targetTeam, $priority]);

        // Internal note
        $pdo->prepare(
          "INSERT INTO messages (query_id, sender_admin_id, direction, medium, body, created_at)
           VALUES (?, ?, 'internal', 'note', ?, NOW())"
        )->execute([$qid, $adminId, "Forward approved to team #{$targetTeam} (priority: {$priority})"]);
      }
    }
    redirect_back();
  }

  // Reject forwarding request
  if (isset($_POST['reject_forward_action'])) {
    $qid = (int)($_POST['qid'] ?? 0);
    if ($qid > 0 && can('approve_forwarding')) {
      $pdo->prepare(
        "UPDATE queries
            SET forward_request_team_id=NULL,
                forward_request_priority=NULL,
                forward_request_by=NULL,
                forward_request_at=NULL
          WHERE id=?"
      )->execute([$qid]);

      // Audit
      $pdo->prepare(
        "INSERT INTO audit_logs (entity_type, entity_id, admin_user_id, action, meta, created_at)
         VALUES ('query', ?, ?, 'forward_rejected', JSON_OBJECT('reason','forward_rejected'), NOW())"
      )->execute([$qid, $adminId]);

      // Internal note
      $pdo->prepare(
        "INSERT INTO messages (query_id, sender_admin_id, direction, medium, body, created_at)
         VALUES (?, ?, 'internal', 'note', 'Forward request rejected', NOW())"
      )->execute([$qid, $adminId]);
    }
    redirect_back();
  }
}

// ===== Fetch data for dashboard =====
$pdo = db();
$inTeams = implode(',', array_map('intval', $teamIds));

// Team queries
$sqlQueries = "
  SELECT q.id, q.query_code, q.status, q.priority, q.query_type, q.created_at, q.product_name,
         q.assigned_admin_user_id, au.name AS assigned_name,
         q.current_team_id, t.name AS team_name,
         q.forward_request_team_id, q.forward_request_priority, q.forward_request_by, q.forward_request_at,
         frt.name AS forward_team_name,
         fu.name  AS forward_by_name
    FROM queries q
    LEFT JOIN admin_users au ON au.id = q.assigned_admin_user_id
    LEFT JOIN teams t       ON t.id = q.current_team_id
    LEFT JOIN teams frt     ON frt.id = q.forward_request_team_id
    LEFT JOIN admin_users fu ON fu.id = q.forward_request_by
   WHERE q.current_team_id IN ($inTeams)
   ORDER BY q.id DESC
   LIMIT 500
";
$queries = $pdo->query($sqlQueries)->fetchAll(PDO::FETCH_ASSOC);

// Team members (for metrics)
$teamMembers = [];
$stMem = $pdo->query("
  SELECT au.id, au.name, ut.team_id
    FROM admin_users au
    JOIN admin_user_teams ut ON ut.admin_user_id = au.id
   WHERE ut.team_id IN ($inTeams) AND au.is_active=1
   ORDER BY au.name ASC
");
foreach ($stMem->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $teamMembers[(int)$row['team_id']][] = ['id'=>(int)$row['id'],'name'=>$row['name']];
}

// Metrics per member
$metrics = [];
foreach ($teamMembers as $tid => $members) {
  foreach ($members as $m) {
    $uid = (int)$m['id'];
    $metrics[$uid] = [
      'name' => $m['name'],
      'team_id' => $tid,
      'assigned' => 0,
      'closed' => 0,
      'avg_res_seconds' => null,
    ];
  }
}

if ($metrics) {
  $uids = implode(',', array_keys($metrics));

  // Assigned count
  $resA = $pdo->query("
    SELECT assigned_admin_user_id AS uid, COUNT(*) AS cnt
      FROM queries
     WHERE assigned_admin_user_id IN ($uids)
     GROUP BY assigned_admin_user_id
  ")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($resA as $r) {
    $uid = (int)$r['uid'];
    if (isset($metrics[$uid])) $metrics[$uid]['assigned'] = (int)$r['cnt'];
  }

  // Closed/converted + avg resolution seconds
  $resC = $pdo->query("
    SELECT assigned_admin_user_id AS uid,
           COUNT(*) AS cnt,
           AVG(TIMESTAMPDIFF(SECOND, created_at, IFNULL(updated_at, created_at))) AS avg_sec
      FROM queries
     WHERE assigned_admin_user_id IN ($uids)
       AND status IN ('closed','converted')
     GROUP BY assigned_admin_user_id
  ")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($resC as $r) {
    $uid = (int)$r['uid'];
    if (isset($metrics[$uid])) {
      $metrics[$uid]['closed'] = (int)$r['cnt'];
      $metrics[$uid]['avg_res_seconds'] = $r['avg_sec'] !== null ? (float)$r['avg_sec'] : null;
    }
  }
}

// Summary counts
$counts = ['total'=>0,'new'=>0,'inproc'=>0,'red'=>0,'pending'=>0];
foreach ($queries as $q) {
  $counts['total']++;
  if (in_array($q['status'], ['new','elaborated','assigned'], true)) $counts['new']++;
  elseif ($q['status'] === 'in_process') $counts['inproc']++;
  elseif ($q['status'] === 'red_flag') $counts['red']++;
  if ($q['forward_request_team_id']) $counts['pending']++;
}

// Recent activity logs (latest 20 for these teams)
$logs = $pdo->query("
  SELECT l.id, l.entity_id AS query_id, q.query_code, l.action, l.meta, l.created_at
    FROM audit_logs l
    JOIN queries q ON q.id = l.entity_id
   WHERE l.entity_type='query'
     AND q.current_team_id IN ($inTeams)
   ORDER BY l.id DESC
   LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

$title = 'Supervisor Dashboard';
ob_start();
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
.sv-root{--primary:#ff6b2c;--primary-light:#ff914b;--primary-dark:#e8551a;--accent:#204bff;--success:#10b981;--warning:#f59e0b;--danger:#ef4444;--text:#0f172a;--text-light:#64748b;--text-lighter:#94a3b8;--bg:#f8fafc;--card:#ffffff;--border:#e2e8f0;--border-light:#f1f5f9;--shadow:0 1px 3px rgba(0,0,0,0.06),0 1px 2px rgba(0,0,0,0.04);--shadow-md:0 4px 6px -1px rgba(0,0,0,0.08),0 2px 4px -1px rgba(0,0,0,0.04);--shadow-lg:0 10px 15px -3px rgba(0,0,0,0.08),0 4px 6px -2px rgba(0,0,0,0.04);--shadow-xl:0 20px 25px -5px rgba(0,0,0,0.08),0 10px 10px -5px rgba(0,0,0,0.02);font-family:'Inter',system-ui,-apple-system,sans-serif}
.sv-root{background:linear-gradient(180deg,var(--bg) 0%,#ffffff 100%);min-height:100vh;padding:0;margin:0}
.sv-topbar{background:linear-gradient(135deg,var(--primary),var(--primary-light));padding:16px 0;box-shadow:var(--shadow-md);position:sticky;top:0;z-index:100;backdrop-filter:blur(8px)}
.sv-topbar-content{max-width:1400px;margin:0 auto;padding:0 24px;display:flex;justify-content:space-between;align-items:center}
.sv-topbar-title{color:#fff;font-size:20px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px}
.sv-topbar-badge{background:rgba(255,255,255,0.2);color:#fff;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600}
.sv-container{max-width:1400px;margin:0 auto;padding:32px 24px}
.sv-header{margin-bottom:32px}
.sv-title{font-size:32px;font-weight:800;color:var(--text);margin:0 0 8px;letter-spacing:-0.5px}
.sv-subtitle{color:var(--text-light);font-size:15px;font-weight:500}
.sv-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:32px}
.sv-stat-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:24px;box-shadow:var(--shadow);transition:all 0.3s cubic-bezier(0.4,0,0.2,1);position:relative;overflow:hidden}
.sv-stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--primary),var(--primary-light));transform:scaleX(0);transition:transform 0.3s;transform-origin:left}
.sv-stat-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-lg);border-color:var(--primary)}
.sv-stat-card:hover::before{transform:scaleX(1)}
.sv-stat-label{font-size:12px;color:var(--text-lighter);font-weight:600;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:12px}
.sv-stat-value{font-size:42px;font-weight:800;background:linear-gradient(135deg,var(--primary),var(--primary-light));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin:0;line-height:1.1}
.sv-stat-icon{position:absolute;top:24px;right:24px;width:48px;height:48px;background:linear-gradient(135deg,rgba(255,107,44,0.1),rgba(255,145,75,0.1));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px}
.sv-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px;margin-bottom:28px;box-shadow:var(--shadow);transition:box-shadow 0.3s}
.sv-card:hover{box-shadow:var(--shadow-md)}
.sv-card-title{font-size:20px;font-weight:700;color:var(--text);margin:0 0 20px;padding-bottom:16px;border-bottom:2px solid var(--border-light);display:flex;align-items:center;gap:8px}
.sv-card-title::before{content:'';width:4px;height:24px;background:linear-gradient(180deg,var(--primary),var(--primary-light));border-radius:2px}
.sv-table{width:100%;border-collapse:collapse}
.sv-table thead th{background:linear-gradient(180deg,#f8fafc,#f1f5f9);color:var(--text);font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:0.8px;padding:14px 16px;text-align:left;border-bottom:2px solid var(--border);position:sticky;top:0;z-index:10}
.sv-table tbody tr{transition:all 0.2s;border-bottom:1px solid var(--border-light)}
.sv-table tbody tr:hover{background:linear-gradient(90deg,rgba(255,107,44,0.02),transparent);transform:scale(1.001)}
.sv-table tbody td{padding:14px 16px;color:var(--text);font-size:14px;font-weight:500}
.sv-table tbody tr:last-child{border-bottom:none}
.sv-btn{display:inline-block;background:linear-gradient(135deg,var(--primary),var(--primary-light));color:#fff;padding:10px 24px;border-radius:10px;text-decoration:none;font-weight:700;font-size:13px;transition:all 0.3s;box-shadow:0 4px 12px rgba(255,107,44,0.25);border:none;cursor:pointer;text-transform:uppercase;letter-spacing:0.5px}
.sv-btn:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(255,107,44,0.35);background:linear-gradient(135deg,var(--primary-dark),var(--primary))}
.sv-btn:active{transform:translateY(0)}
.sv-scroll{overflow-x:auto;border-radius:12px}
.sv-scroll::-webkit-scrollbar{height:8px}
.sv-scroll::-webkit-scrollbar-track{background:var(--border-light);border-radius:4px}
.sv-scroll::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}
.sv-scroll::-webkit-scrollbar-thumb:hover{background:var(--text-lighter)}
.sv-activity{max-height:400px;overflow-y:auto;padding:4px}
.sv-activity::-webkit-scrollbar{width:6px}
.sv-activity::-webkit-scrollbar-track{background:transparent}
.sv-activity::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}
.sv-activity-item{padding:14px 16px;margin-bottom:8px;background:linear-gradient(90deg,rgba(255,107,44,0.04),transparent);border-radius:10px;border-left:3px solid var(--primary);font-size:13px;transition:all 0.2s;position:relative}
.sv-activity-item:hover{background:linear-gradient(90deg,rgba(255,107,44,0.08),transparent);transform:translateX(4px);box-shadow:var(--shadow)}
.sv-activity-item strong{color:var(--text);font-weight:700}
.sv-activity-time{float:right;color:var(--text-lighter);font-size:11px;font-weight:600;background:var(--border-light);padding:4px 8px;border-radius:6px}
.sv-badge{display:inline-block;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px}
.sv-badge-success{background:#d1fae5;color:#065f46}
.sv-badge-warning{background:#fef3c7;color:#92400e}
.sv-badge-danger{background:#fee2e2;color:#991b1b}
@keyframes fadeInUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.sv-stat-card{animation:fadeInUp 0.6s ease-out backwards}
.sv-stat-card:nth-child(1){animation-delay:0.1s}
.sv-stat-card:nth-child(2){animation-delay:0.15s}
.sv-stat-card:nth-child(3){animation-delay:0.2s}
.sv-stat-card:nth-child(4){animation-delay:0.25s}
.sv-stat-card:nth-child(5){animation-delay:0.3s}
.sv-card{animation:fadeInUp 0.6s ease-out 0.35s backwards}
@media(max-width:768px){.sv-stats{grid-template-columns:repeat(2,1fr);gap:12px}.sv-container{padding:20px 16px}.sv-title{font-size:24px}.sv-stat-value{font-size:32px}.sv-topbar-title{font-size:16px}}
</style>
<div class="sv-root">
<!-- Sticky Top Bar -->
<div class="sv-topbar">
  <div class="sv-topbar-content">
    <div class="sv-topbar-title">
      📊 Supervisor Dashboard
    </div>
    <div class="sv-topbar-badge">Live Updates</div>
  </div>
</div>

<div class="sv-container">
<!-- Stats -->
<div class="sv-stats">
  <div class="sv-stat-card">
    <div class="sv-stat-icon">📋</div>
    <div class="sv-stat-label">Total Queries</div>
    <div class="sv-stat-value" id="cTotal"><?= (int)$counts['total'] ?></div>
  </div>
  <div class="sv-stat-card">
    <div class="sv-stat-icon">🆕</div>
    <div class="sv-stat-label">New/Assigned</div>
    <div class="sv-stat-value" id="cNew"><?= (int)$counts['new'] ?></div>
  </div>
  <div class="sv-stat-card">
    <div class="sv-stat-icon">⚙️</div>
    <div class="sv-stat-label">In Process</div>
    <div class="sv-stat-value" id="cInproc"><?= (int)$counts['inproc'] ?></div>
  </div>
  <div class="sv-stat-card">
    <div class="sv-stat-icon">🚩</div>
    <div class="sv-stat-label">Red Flags</div>
    <div class="sv-stat-value" id="cRed"><?= (int)$counts['red'] ?></div>
  </div>
  <div class="sv-stat-card">
    <div class="sv-stat-icon">⏳</div>
    <div class="sv-stat-label">Pending Forwards</div>
    <div class="sv-stat-value" id="cPending"><?= (int)$counts['pending'] ?></div>
  </div>
</div>

<!-- Metrics Table -->
<div class="sv-card">
<h3 class="sv-card-title">Team Performance Metrics</h3>
<div class="sv-scroll">
<table class="sv-table">
  <thead>
    <tr>
      <th>User</th>
      <th>Team</th>
      <th>Assigned</th>
      <th>Closed</th>
      <th>Avg Res. Time</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($metrics as $uid => $m): ?>
      <tr data-uid="<?= (int)$uid ?>">
        <td><?= htmlspecialchars($m['name']) ?></td>
        <td><?= htmlspecialchars($m['team_id']) ?></td>
        <td class="assigned"><?= (int)$m['assigned'] ?></td>
        <td class="closed"><?= (int)$m['closed'] ?></td>
        <td class="avg">
          <?php if ($m['avg_res_seconds'] === null): ?>–
          <?php else:
            $days = $m['avg_res_seconds'] / 86400;
            echo number_format($days, 2) . ' days';
          endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
</div>

<!-- Recent Activity -->
<div class="sv-card">
<h3 class="sv-card-title">Recent Activity</h3>
<div id="notifPanel" class="sv-activity">
  <?php foreach ($logs as $lg): ?>
    <?php
      $metaStr = '';
      if (!empty($lg['meta'])) {
        $metaObj = json_decode($lg['meta'], true);
        if (is_array($metaObj)) {
          foreach ($metaObj as $k=>$v) { $metaStr .= $k.':'.$v.' '; }
        } else {
          $metaStr = $lg['meta'];
        }
      }
      $when = htmlspecialchars(date('Y-m-d H:i', strtotime($lg['created_at'])));
    ?>
    <div class="sv-activity-item">
      <strong><?= htmlspecialchars($lg['query_code']) ?></strong> — <?= htmlspecialchars($lg['action']) ?>
      <?php if ($metaStr): ?> (<?= htmlspecialchars(trim($metaStr)) ?>)<?php endif; ?>
      <span class="sv-activity-time"><?= $when ?></span>
    </div>
  <?php endforeach; ?>
</div>

<!-- Pending Forward Requests -->
<?php if ($counts['pending']): ?>
<div class="sv-card">
<h3 class="sv-card-title">Pending Forward Approval</h3>
<div class="sv-scroll">
<table class="sv-table">
    <thead><tr>
      <th>ID</th>
      <th>Code</th>
      <th>Requested by</th>
      <th>Requested at</th>
      <th>Target Team</th>
      <th>Priority</th>
      <th>Actions</th>
    </tr></thead>
    <tbody>
      <?php foreach ($queries as $q): if (!$q['forward_request_team_id']) continue; ?>
  <tr>
    <td>#<?= (int)$q['id'] ?></td>
    <td><?= htmlspecialchars($q['query_code'] ?: '') ?></td>
    <td><?= htmlspecialchars($q['forward_by_name'] ?: ('#' . $q['forward_request_by'])) ?></td>
    <td><?= htmlspecialchars($q['forward_request_at']) ?></td>
    <td><?= htmlspecialchars($q['forward_team_name'] ?: ('#' . $q['forward_request_team_id'])) ?></td>
    <td><?= htmlspecialchars($q['forward_request_priority']) ?></td>
    <td style="display:flex;gap:6px">
      <a class="btn sv-btn" href="/app/query_supervisor_ar.php?id=<?= (int)$q['id'] ?>">View</a>
    </td>
  </tr>
<?php endforeach; ?>

    </tbody>
  </table>
</div>
</div>
<?php endif; ?>

<!-- Team Queries -->
<div class="sv-card">
<h3 class="sv-card-title">Team Queries</h3>
<div class="sv-scroll">
<table class="sv-table">
  <thead>
    <tr>
      <th>ID</th>
      <th>Code</th>
      <th>Type</th>
      <th>Status</th>
      <th>Priority</th>
      <th>Product</th>
      <th>Assigned To</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($queries as $q): ?>
      <tr>
        <td>#<?= (int)$q['id'] ?></td>
        <td><?= htmlspecialchars($q['query_code']) ?></td>
        <td><?= htmlspecialchars($q['query_type']) ?></td>
        <td><?= htmlspecialchars($q['status']) ?></td>
        <td><?= htmlspecialchars($q['priority']) ?></td>
        <td><?= htmlspecialchars($q['product_name'] ?: '-') ?></td>
        <td><?= htmlspecialchars($q['assigned_name'] ?: '-') ?></td>
        <td>
          <a class="btn sv-btn" href="/app/query_supervisor.php?id=<?= (int)$q['id'] ?>">View</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
</div>

</div>
</div>

<script>
// Auto-refresh dashboard counts/metrics/logs every 60s
async function refreshDashboard() {
  try {
    const res = await fetch('/api/supervisor_dashboard_data.php', {credentials:'include'});
    const data = await res.json();
    if (!data.ok) return;

    const c = data.counts || {};
    const set = (id,val)=>{ const n=document.getElementById(id); if(n) n.textContent = val ?? 0; };
    set('cTotal', c.total); set('cNew', c.new); set('cInproc', c.inproc); set('cRed', c.red); set('cPending', c.pending);

    const metrics = data.metrics || {};
    Object.keys(metrics).forEach(uid => {
      const row = document.querySelector('tr[data-uid="'+uid+'"]');
      if (!row) return;
      const m = metrics[uid];
      const a = row.querySelector('.assigned');
      const cl= row.querySelector('.closed');
      const av= row.querySelector('.avg');
      if (a) a.textContent = m.assigned;
      if (cl) cl.textContent = m.closed;
      if (av) {
        av.textContent = (m.avg_res_seconds == null) ? '–' : (m.avg_res_seconds/86400).toFixed(2)+' days';
      }
    });

    // Logs
    if (data.logs) {
      const panel = document.getElementById('notifPanel');
      if (panel) {
        panel.innerHTML = '';
        data.logs.forEach(lg => {
          const when = (new Date(lg.created_at)).toISOString().slice(0,16).replace('T',' ');
          const meta = lg.meta || '';
          const div = document.createElement('div');
          div.className = 'sv-activity-item';
          div.innerHTML = '<strong>'+ (lg.query_code || ('#'+lg.query_id)) +'</strong> — '+ lg.action +
                          (meta ? ' ('+meta+')' : '') +
                          '<span class="sv-activity-time">'+ when +'</span>';
          panel.appendChild(div);
        });
      }
    }
  } catch (e) { console.error('refresh failed', e); }
}
setInterval(refreshDashboard, 60000);
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
