<?php
require_once __DIR__.'/auth.php';
require_perm('view_queries');

if (!function_exists('e')) {
  function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$pdo      = db();
$adminId  = (int)($_SESSION['admin']['id']);
$isSupervisor = in_array('assign_team_member', $_SESSION['perms'] ?? [], true);

// We only want "assigned" rows here
const LIST_STATUS = 'assigned';

if ($isSupervisor) {
  // Supervisor: see assigned queries in their team(s)
  $st = $pdo->prepare("SELECT team_id FROM admin_user_teams WHERE admin_user_id=?");
  $st->execute([$adminId]);
  $teamIds = array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'team_id'));
  if (!$teamIds) { $teamIds = [0]; }
  $inTeams = implode(',', $teamIds);

  $rows = $pdo->query("
    SELECT q.id, q.query_code, q.customer_name, q.email, q.phone, q.query_type, q.status, q.priority,
           t.name AS team_name
      FROM queries q
      LEFT JOIN teams t ON t.id = q.current_team_id
     WHERE q.current_team_id IN ($inTeams)
       AND q.status = '" . LIST_STATUS . "'
     ORDER BY q.id DESC
     LIMIT 200
  ")->fetchAll(PDO::FETCH_ASSOC);

} else {
  // Regular agent: only queries assigned to them, and only if status = assigned
  $st = $pdo->prepare("
    SELECT q.id, q.query_code, q.customer_name, q.email, q.phone, q.query_type, q.status, q.priority,
           t.name AS team_name
      FROM queries q
      LEFT JOIN teams t ON t.id = q.current_team_id
     WHERE q.assigned_admin_user_id = ?
       AND q.status = '" . LIST_STATUS . "'
     ORDER BY q.id DESC
     LIMIT 200
  ");
  $st->execute([$adminId]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
}

$title='Queries';
ob_start(); ?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
body:has(.qry-root){background:#f8fafc !important}
.wrap:has(.qry-root){background:transparent !important;box-shadow:none !important;padding:0 !important;margin:0 !important;max-width:100% !important}
.wrap:has(.qry-root) nav{background:#fff;padding:12px 24px;border-radius:12px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.06)}
.wrap:has(.qry-root) nav a{color:#64748b;font-weight:600;padding:8px 16px;border-radius:8px;transition:all 0.2s;display:inline-block}
.wrap:has(.qry-root) nav a:hover{background:#f1f5f9;color:#ff6b2c}
.wrap:has(.qry-root) hr{display:none}
.qry-root{--primary:#ff6b2c;--primary-light:#ff914b;--text:#0f172a;--text-light:#64748b;--text-lighter:#94a3b8;--bg:#f8fafc;--card:#fff;--border:#e2e8f0;--shadow:0 1px 3px rgba(0,0,0,0.06),0 1px 2px rgba(0,0,0,0.04);--shadow-md:0 4px 6px -1px rgba(0,0,0,0.08),0 2px 4px -1px rgba(0,0,0,0.04);--shadow-lg:0 10px 15px -3px rgba(0,0,0,0.08),0 4px 6px -2px rgba(0,0,0,0.04);font-family:'Inter',system-ui,-apple-system,sans-serif}
.qry-root{min-height:100vh;padding:0;margin:0}
.qry-header{background:linear-gradient(135deg,#ff6b2c 0%,#ff914b 50%,#ffb347 100%);padding:32px 0;box-shadow:0 4px 20px rgba(255,107,44,0.3);margin-bottom:0;position:relative;overflow:hidden}
.qry-header::before{content:'';position:absolute;top:0;left:0;right:0;bottom:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");opacity:0.3}
.qry-header-content{max-width:1400px;margin:0 auto;padding:0 24px;position:relative;z-index:1}
.qry-title{color:#fff;font-size:36px;font-weight:800;margin:0;letter-spacing:-1px;text-shadow:0 2px 10px rgba(0,0,0,0.1);display:flex;align-items:center;gap:12px}
.qry-emoji{font-size:40px;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.1))}
.qry-subtitle{color:rgba(255,255,255,0.95);font-size:15px;margin-top:8px;font-weight:500}
.qry-container{max-width:1400px;margin:0 auto;padding:32px 24px}
.qry-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px;margin-bottom:28px;box-shadow:var(--shadow);animation:fadeInUp 0.6s ease-out}
.qry-card-title{font-size:20px;font-weight:700;color:var(--text);margin:0 0 20px;padding-bottom:16px;border-bottom:2px solid #f1f5f9;display:flex;align-items:center;gap:8px}
.qry-card-title::before{content:'';width:4px;height:24px;background:linear-gradient(180deg,var(--primary),var(--primary-light));border-radius:2px}
.qry-table{width:100%;border-collapse:collapse}
.qry-table thead th{background:linear-gradient(180deg,#f8fafc,#f1f5f9);color:var(--text);font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:0.8px;padding:14px 16px;text-align:left;border-bottom:2px solid var(--border);position:sticky;top:0;z-index:10}
.qry-table tbody tr{transition:all 0.2s;border-bottom:1px solid #f1f5f9}
.qry-table tbody tr:hover{background:linear-gradient(90deg,rgba(255,107,44,0.02),transparent);transform:scale(1.001)}
.qry-table tbody td{padding:14px 16px;color:var(--text);font-size:14px;font-weight:500}
.qry-table tbody tr:last-child{border-bottom:none}
.qry-contact-email{color:var(--text-light);font-size:12px;margin-top:4px}
.qry-badge{display:inline-block;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px}
.qry-badge-priority{background:#fef3c7;color:#92400e}
.qry-badge-status{background:#dbeafe;color:#1e40af}
.qry-btn{display:inline-block;background:linear-gradient(135deg,var(--primary),var(--primary-light));color:#fff !important;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:700;font-size:13px;transition:all 0.3s;box-shadow:0 2px 8px rgba(255,107,44,0.25);border:none;cursor:pointer;text-transform:uppercase;letter-spacing:0.5px}
.qry-btn:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(255,107,44,0.35)}
.qry-scroll{overflow-x:auto;border-radius:12px}
.qry-scroll::-webkit-scrollbar{height:8px}
.qry-scroll::-webkit-scrollbar-track{background:#f1f5f9;border-radius:4px}
.qry-scroll::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}
.qry-empty{text-align:center;padding:40px;color:var(--text-light)}
@keyframes fadeInUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:768px){.qry-container{padding:20px 16px}.qry-title{font-size:28px}.qry-table{font-size:13px}.qry-table thead th,.qry-table tbody td{padding:10px 12px}}
</style>
<div class="qry-root">
<!-- Header -->
<div class="qry-header">
  <div class="qry-header-content">
    <h1 class="qry-title"><span class="qry-emoji">📝</span> <?= $isSupervisor ? 'All Team Queries' : 'My Queries' ?></h1>
    <p class="qry-subtitle">Manage assigned queries • <?= count($rows) ?> total</p>
  </div>
</div>

<div class="qry-container">
<!-- Queries Table -->
<div class="qry-card">
<h3 class="qry-card-title">Query List</h3>
<?php if(count($rows) > 0): ?>
<div class="qry-scroll">
<table class="qry-table">
  <thead>
    <tr>
      <th>ID</th>
      <th>Code</th>
      <th>Customer</th>
      <th>Contact</th>
      <th>Type</th>
      <th>Team</th>
      <th>Priority</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($rows as $r): ?>
    <tr>
      <td>#<?= (int)$r['id'] ?></td>
      <td><strong><?= e($r['query_code']) ?></strong></td>
      <td><?= e($r['customer_name']) ?></td>
      <td>
        <?php if(!empty($r['phone'])): ?><?= e($r['phone']) ?><?php endif; ?>
        <?php if(!empty($r['email'])): ?><div class="qry-contact-email"><?= e($r['email']) ?></div><?php endif; ?>
      </td>
      <td><?= e($r['query_type']) ?></td>
      <td><?= e($r['team_name'] ?: '-') ?></td>
      <td><span class="qry-badge qry-badge-priority"><?= e($r['priority']) ?></span></td>
      <td><span class="qry-badge qry-badge-status"><?= e($r['status']) ?></span></td>
      <td>
        <?php
          $viewHref = $isSupervisor
            ? '/app/query_supervisor.php?id='.(int)$r['id']
            : '/app/query.php?id='.(int)$r['id'];
        ?>
        <a class="qry-btn" href="<?= $viewHref ?>">View</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php else: ?>
<div class="qry-empty">
  <p style="font-size:48px;margin:0">📭</p>
  <p style="margin:10px 0 0">No queries assigned yet</p>
</div>
<?php endif; ?>
</div>
</div>
</div>
<?php
$content = ob_get_clean();
include __DIR__.'/layout.php';
