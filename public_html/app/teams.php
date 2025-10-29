<?php
require_once __DIR__.'/auth.php'; require_perm('manage_admins');

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (isset($_POST['toggle'])) {
    db()->prepare("UPDATE teams SET is_active = IF(is_active=1,0,1) WHERE id=?")
      ->execute([(int)$_POST['id']]);
  }
  if (isset($_POST['leader_set'])) {
    db()->prepare("UPDATE teams SET leader_admin_user_id=? WHERE id=?")
      ->execute([($_POST['leader_admin_user_id']? (int)$_POST['leader_admin_user_id'] : null), (int)$_POST['id']]);
  }
  header("Location: /app/teams.php"); exit;
}

$teams = db()->query("SELECT * FROM teams ORDER BY id")->fetchAll();
$admins = db()->query("SELECT id,email FROM admin_users ORDER BY email")->fetchAll();

$title='Teams';
ob_start(); ?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
body:has(.tm-root){background:#f8fafc !important}
.wrap:has(.tm-root){background:transparent !important;box-shadow:none !important;padding:0 !important;margin:0 !important;max-width:100% !important}
.wrap:has(.tm-root) nav{background:#fff;padding:12px 24px;border-radius:12px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.06)}
.wrap:has(.tm-root) nav a{color:#64748b;font-weight:600;padding:8px 16px;border-radius:8px;transition:all 0.2s;display:inline-block}
.wrap:has(.tm-root) nav a:hover{background:#f1f5f9;color:#ff6b2c}
.wrap:has(.tm-root) hr{display:none}
.tm-root{--primary:#ff6b2c;--primary-light:#ff914b;--text:#0f172a;--text-light:#64748b;--bg:#f8fafc;--card:#fff;--border:#e2e8f0;--shadow:0 1px 3px rgba(0,0,0,0.06),0 1px 2px rgba(0,0,0,0.04);--shadow-md:0 4px 6px -1px rgba(0,0,0,0.08);--shadow-lg:0 10px 15px -3px rgba(0,0,0,0.08);font-family:'Inter',system-ui,-apple-system,sans-serif}
.tm-root{min-height:100vh;padding:0;margin:0}
.tm-header{background:linear-gradient(135deg,#ff6b2c 0%,#ff914b 50%,#ffb347 100%);padding:32px 0;box-shadow:0 4px 20px rgba(255,107,44,0.3);margin-bottom:0;position:relative;overflow:hidden}
.tm-header::before{content:'';position:absolute;top:0;left:0;right:0;bottom:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");opacity:0.3}
.tm-header-content{max-width:1400px;margin:0 auto;padding:0 24px;position:relative;z-index:1}
.tm-title{color:#fff;font-size:36px;font-weight:800;margin:0;letter-spacing:-1px;text-shadow:0 2px 10px rgba(0,0,0,0.1);display:flex;align-items:center;gap:12px}
.tm-emoji{font-size:40px;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.1))}
.tm-subtitle{color:rgba(255,255,255,0.95);font-size:15px;margin-top:8px;font-weight:500}
.tm-container{max-width:1400px;margin:0 auto;padding:32px 24px}
.tm-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px;margin-bottom:28px;box-shadow:var(--shadow);animation:fadeInUp 0.6s ease-out}
.tm-card-title{font-size:20px;font-weight:700;color:var(--text);margin:0 0 20px;padding-bottom:16px;border-bottom:2px solid #f1f5f9;display:flex;align-items:center;gap:8px}
.tm-card-title::before{content:'';width:4px;height:24px;background:linear-gradient(180deg,var(--primary),var(--primary-light));border-radius:2px}
.tm-table{width:100%;border-collapse:collapse}
.tm-table thead th{background:linear-gradient(180deg,#f8fafc,#f1f5f9);color:var(--text);font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:0.8px;padding:14px 16px;text-align:left;border-bottom:2px solid var(--border)}
.tm-table tbody tr{transition:all 0.2s;border-bottom:1px solid #f1f5f9}
.tm-table tbody tr:hover{background:linear-gradient(90deg,rgba(255,107,44,0.02),transparent)}
.tm-table tbody td{padding:14px 16px;color:var(--text);font-size:14px;font-weight:500}
.tm-table tbody tr:last-child{border-bottom:none}
.tm-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.tm-btn{display:inline-block;background:linear-gradient(135deg,var(--primary),var(--primary-light));color:#fff !important;padding:8px 16px;border-radius:8px;font-weight:600;font-size:13px;transition:all 0.3s;box-shadow:0 2px 8px rgba(255,107,44,0.25);border:none;cursor:pointer;text-transform:uppercase;letter-spacing:0.5px}
.tm-btn:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(255,107,44,0.35)}
.tm-btn-secondary{background:linear-gradient(135deg,#64748b,#94a3b8);box-shadow:0 2px 8px rgba(100,116,139,0.25)}
.tm-btn-secondary:hover{box-shadow:0 4px 12px rgba(100,116,139,0.35)}
.tm-select{padding:8px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;font-weight:500;color:var(--text);font-family:'Inter',sans-serif;background:#fff;min-width:180px}
.tm-select:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(255,107,44,0.1)}
.tm-badge{display:inline-block;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase}
.tm-badge-active{background:#d1fae5;color:#065f46}
.tm-badge-inactive{background:#fee2e2;color:#991b1b}
.tm-scroll{overflow-x:auto;border-radius:12px}
@keyframes fadeInUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:768px){.tm-container{padding:20px 16px}.tm-title{font-size:28px}.tm-actions{flex-direction:column;align-items:stretch}.tm-select{width:100%}}
</style>
<div class="tm-root">
<!-- Header -->
<div class="tm-header">
  <div class="tm-header-content">
    <h1 class="tm-title"><span class="tm-emoji">👥</span> Teams</h1>
    <p class="tm-subtitle">Manage teams and assign leaders • <?= count($teams) ?> teams</p>
  </div>
</div>

<div class="tm-container">
<!-- Teams Table -->
<div class="tm-card">
<h3 class="tm-card-title">All Teams</h3>
<div class="tm-scroll">
<table class="tm-table">
  <thead><tr><th>ID</th><th>Name</th><th>Code</th><th>Status</th><th>Leader</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach($teams as $t): ?>
    <tr>
      <td><strong>#<?= (int)$t['id'] ?></strong></td>
      <td><?= htmlspecialchars($t['name']) ?></td>
      <td><code style="background:#f1f5f9;padding:4px 8px;border-radius:4px;font-size:12px"><?= htmlspecialchars($t['code']) ?></code></td>
      <td>
        <span class="tm-badge <?= $t['is_active']?'tm-badge-active':'tm-badge-inactive' ?>">
          <?= $t['is_active']?'Active':'Inactive' ?>
        </span>
      </td>
      <td><?= $t['leader_admin_user_id'] ? htmlspecialchars(db()->query("SELECT email FROM admin_users WHERE id=".(int)$t['leader_admin_user_id'])->fetchColumn()): '—' ?></td>
      <td>
        <div class="tm-actions">
          <form method="post" style="margin:0">
            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
            <button class="tm-btn <?= $t['is_active']?'tm-btn-secondary':'' ?>" name="toggle" value="1">
              <?= $t['is_active']?'Deactivate':'Activate' ?>
            </button>
          </form>
          <form method="post" style="display:flex;gap:6px;margin:0;flex-wrap:wrap">
            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
            <select name="leader_admin_user_id" class="tm-select">
              <option value="">— No Leader —</option>
              <?php foreach($admins as $a): ?>
                <option value="<?= (int)$a['id'] ?>" <?= ((int)$t['leader_admin_user_id']===(int)$a['id'])?'selected':'' ?>>
                  <?= htmlspecialchars($a['email']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <button class="tm-btn" name="leader_set" value="1">Set Leader</button>
          </form>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
</div>
</div>
</div>
<?php $content=ob_get_clean(); include __DIR__.'/layout.php';
