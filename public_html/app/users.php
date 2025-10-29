<?php
require_once __DIR__.'/auth.php'; require_perm('manage_admins');

$err=''; $ok='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (isset($_POST['create'])) {
    $name=trim($_POST['name']??'');
    $email=trim($_POST['email']??'');
    $pass=$_POST['password']??'';
    if (!$name || !$email || !$pass) $err='All fields required';
    else {
      $st=db()->prepare("INSERT INTO admin_users (name,email,password_hash) VALUES (?,?,?)");
      $st->execute([$name,$email,password_hash($pass, PASSWORD_BCRYPT)]);
      $ok='Admin created';
    }
  }
  if (isset($_POST['role_set'])) {
    $uid=(int)$_POST['uid']; $rid=(int)$_POST['role_id'];
    db()->prepare("DELETE FROM admin_user_roles WHERE admin_user_id=?")->execute([$uid]);
    db()->prepare("INSERT INTO admin_user_roles (admin_user_id, role_id) VALUES (?,?)")->execute([$uid,$rid]);
    $ok='Role updated';
  }
  if (isset($_POST['perms_set'])) {
    $rid=(int)$_POST['rid']; $perms=$_POST['perms']??[];
    db()->prepare("DELETE FROM role_permissions WHERE role_id=?")->execute([$rid]);
    $st=db()->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)");
    foreach ($perms as $pid) $st->execute([$rid,(int)$pid]);
    $ok='Permissions updated';
  }
}

$admins = db()->query("SELECT u.id,u.name,u.email,COALESCE(r.name,'(none)') as role_name, r.id as rid
  FROM admin_users u
  LEFT JOIN admin_user_roles ur ON ur.admin_user_id=u.id
  LEFT JOIN roles r ON r.id=ur.role_id
  ORDER BY u.id DESC")->fetchAll();

$roles = db()->query("SELECT * FROM roles ORDER BY id")->fetchAll();
$perms = db()->query("SELECT * FROM permissions ORDER BY id")->fetchAll();

$title='Admins & Roles';
ob_start(); ?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
body:has(.usr-root){background:#f8fafc !important}
.wrap:has(.usr-root){background:transparent !important;box-shadow:none !important;padding:0 !important;margin:0 !important;max-width:100% !important}
.wrap:has(.usr-root) nav{background:#fff;padding:12px 24px;border-radius:12px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.06)}
.wrap:has(.usr-root) nav a{color:#64748b;font-weight:600;padding:8px 16px;border-radius:8px;transition:all 0.2s;display:inline-block}
.wrap:has(.usr-root) nav a:hover{background:#f1f5f9;color:#ff6b2c}
.wrap:has(.usr-root) hr{display:none}
.usr-root{--primary:#ff6b2c;--primary-light:#ff914b;--text:#0f172a;--text-light:#64748b;--bg:#f8fafc;--card:#fff;--border:#e2e8f0;--shadow:0 1px 3px rgba(0,0,0,0.06),0 1px 2px rgba(0,0,0,0.04);--shadow-md:0 4px 6px -1px rgba(0,0,0,0.08);--shadow-lg:0 10px 15px -3px rgba(0,0,0,0.08);font-family:'Inter',system-ui,-apple-system,sans-serif}
.usr-root{min-height:100vh;padding:0;margin:0}
.usr-header{background:linear-gradient(135deg,#ff6b2c 0%,#ff914b 50%,#ffb347 100%);padding:32px 0;box-shadow:0 4px 20px rgba(255,107,44,0.3);margin-bottom:0;position:relative;overflow:hidden}
.usr-header::before{content:'';position:absolute;top:0;left:0;right:0;bottom:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");opacity:0.3}
.usr-header-content{max-width:1400px;margin:0 auto;padding:0 24px;position:relative;z-index:1}
.usr-title{color:#fff;font-size:36px;font-weight:800;margin:0;letter-spacing:-1px;text-shadow:0 2px 10px rgba(0,0,0,0.1);display:flex;align-items:center;gap:12px}
.usr-emoji{font-size:40px;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.1))}
.usr-subtitle{color:rgba(255,255,255,0.95);font-size:15px;margin-top:8px;font-weight:500}
.usr-container{max-width:1400px;margin:0 auto;padding:32px 24px}
.usr-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px;margin-bottom:28px;box-shadow:var(--shadow);animation:fadeInUp 0.6s ease-out}
.usr-card-title{font-size:20px;font-weight:700;color:var(--text);margin:0 0 20px;padding-bottom:16px;border-bottom:2px solid #f1f5f9;display:flex;align-items:center;gap:8px}
.usr-card-title::before{content:'';width:4px;height:24px;background:linear-gradient(180deg,var(--primary),var(--primary-light));border-radius:2px}
.usr-alert{padding:16px 20px;border-radius:12px;margin-bottom:20px;font-weight:600;font-size:14px;animation:slideDown 0.3s ease-out}
.usr-alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}
.usr-alert-success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7}
.usr-form{display:flex;gap:12px;align-items:end;flex-wrap:wrap}
.usr-input{padding:12px 16px;border:1px solid var(--border);border-radius:10px;font-size:14px;font-weight:500;color:var(--text);font-family:'Inter',sans-serif;background:#fff;min-width:200px;flex:1}
.usr-input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(255,107,44,0.1)}
.usr-table{width:100%;border-collapse:collapse}
.usr-table thead th{background:linear-gradient(180deg,#f8fafc,#f1f5f9);color:var(--text);font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:0.8px;padding:14px 16px;text-align:left;border-bottom:2px solid var(--border)}
.usr-table tbody tr{transition:all 0.2s;border-bottom:1px solid #f1f5f9}
.usr-table tbody tr:hover{background:linear-gradient(90deg,rgba(255,107,44,0.02),transparent)}
.usr-table tbody td{padding:14px 16px;color:var(--text);font-size:14px;font-weight:500}
.usr-table tbody tr:last-child{border-bottom:none}
.usr-btn{display:inline-block;background:linear-gradient(135deg,var(--primary),var(--primary-light));color:#fff !important;padding:10px 20px;border-radius:8px;font-weight:600;font-size:13px;transition:all 0.3s;box-shadow:0 2px 8px rgba(255,107,44,0.25);border:none;cursor:pointer;text-transform:uppercase;letter-spacing:0.5px}
.usr-btn:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(255,107,44,0.35)}
.usr-select{padding:10px 14px;border:1px solid var(--border);border-radius:8px;font-size:13px;font-weight:500;color:var(--text);font-family:'Inter',sans-serif;background:#fff;min-width:160px}
.usr-select:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(255,107,44,0.1)}
.usr-scroll{overflow-x:auto;border-radius:12px}
.usr-perm-card{background:#fff;border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:16px;box-shadow:var(--shadow)}
.usr-perm-title{font-size:16px;font-weight:700;color:var(--text);margin-bottom:16px}
.usr-perm-category{margin-bottom:12px;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden}
.usr-perm-category-title{font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;cursor:pointer;background:#f8fafc;transition:all 0.2s;user-select:none}
.usr-perm-category-title:hover{background:#f1f5f9;color:var(--primary)}
.usr-perm-category-title::after{content:'▼';color:var(--primary);font-size:10px;transition:transform 0.3s}
.usr-perm-category.collapsed .usr-perm-category-title::after{transform:rotate(-90deg)}
.usr-perm-category-content{max-height:500px;overflow:hidden;transition:max-height 0.3s ease-out}
.usr-perm-category.collapsed .usr-perm-category-content{max-height:0}
.usr-perm-grid{display:flex;gap:10px;flex-wrap:wrap;padding:16px;background:#fff}
.usr-checkbox-label{display:flex;align-items:center;gap:6px;border:1px solid #e2e8f0;padding:8px 12px;border-radius:8px;cursor:pointer;transition:all 0.2s;background:#fff}
.usr-checkbox-label:hover{background:#f8fafc;border-color:var(--primary-light)}
.usr-checkbox-label input[type="checkbox"]{width:16px;height:16px;cursor:pointer;accent-color:var(--primary)}
.usr-checkbox-label input[type="checkbox"]:checked + span{color:var(--primary);font-weight:600}
.usr-role-badge{display:inline-block;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:700;background:#f1f5f9;color:#475569}
@keyframes fadeInUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
@keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:768px){.usr-container{padding:20px 16px}.usr-title{font-size:28px}.usr-form{flex-direction:column}.usr-input{width:100%}.usr-perm-grid{flex-direction:column}}
</style>
<div class="usr-root">
<!-- Header -->
<div class="usr-header">
  <div class="usr-header-content">
    <h1 class="usr-title"><span class="usr-emoji">👤</span> Admins & Roles</h1>
    <p class="usr-subtitle">Manage admin users and role permissions • <?= count($admins) ?> admins</p>
  </div>
</div>

<div class="usr-container">
<?php if($err): ?><div class="usr-alert usr-alert-error">⚠️ <?= htmlspecialchars($err) ?></div><?php endif; ?>
<?php if($ok): ?><div class="usr-alert usr-alert-success">✓ <?= htmlspecialchars($ok) ?></div><?php endif; ?>

<!-- Create Admin -->
<div class="usr-card">
  <h3 class="usr-card-title">Create New Admin</h3>
  <form method="post" class="usr-form">
    <input name="name" placeholder="Full Name" required class="usr-input">
    <input type="email" name="email" placeholder="email@example.com" required class="usr-input">
    <input type="password" name="password" placeholder="Password" required class="usr-input">
    <button class="usr-btn" name="create" value="1">Create Admin</button>
  </form>
</div>

<!-- Admins Table -->
<div class="usr-card">
  <h3 class="usr-card-title">All Admins</h3>
  <div class="usr-scroll">
    <table class="usr-table">
      <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Change Role</th></tr></thead>
      <tbody>
        <?php foreach($admins as $a): ?>
          <tr>
            <td><strong>#<?= (int)$a['id'] ?></strong></td>
            <td><?= htmlspecialchars($a['name']) ?></td>
            <td><?= htmlspecialchars($a['email']) ?></td>
            <td><span class="usr-role-badge"><?= htmlspecialchars($a['role_name']) ?></span></td>
            <td>
              <form method="post" style="display:flex;gap:8px;margin:0">
                <input type="hidden" name="uid" value="<?= (int)$a['id'] ?>">
                <select name="role_id" class="usr-select">
                  <?php foreach($roles as $r): ?>
                    <option value="<?= (int)$r['id'] ?>" <?= ($a['rid']==$r['id'])?'selected':'' ?>>
                      <?= htmlspecialchars($r['label']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button class="usr-btn" name="role_set" value="1">Set</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Role Permissions -->
<div class="usr-card">
  <h3 class="usr-card-title">Role Permissions</h3>
  <?php foreach($roles as $r): 
    $rolePerms = db()->prepare("SELECT permission_id FROM role_permissions WHERE role_id=?");
    $rolePerms->execute([$r['id']]);
    $curr = array_column($rolePerms->fetchAll(),'permission_id');
    
    // Organize permissions by category
    $categories = [
      'Query Management' => ['view_queries', 'convert_query_to_order', 'change_status', 'assign_to_team_member'],
      'Order Management' => ['view_orders', 'approve_reject_orders'],
      'Team Management' => ['assign_to_a_team_member_(supervisor)', 'approve_reject_forwarding_to_other_teams', 'view_team_performance_metrics'],
      'Admin Functions' => ['create_update_ledger', 'create_admins_&_set_roles/permissions', 'view_supervisor_notifications_panel'],
      'Country Team' => ['access_country_team_dashboard', 'submit_price_quote', 'approve/reject_price_quote'],
      'Chinese Inbound' => ['access_chinese_inbound_dashboard', 'create_packing_list_(chinese_inbound)', 'update_inbound_order_status', 'forward_to_qc_team'],
      'QC Operations' => ['access_qc_dashboard', 'access_qc_supervisor_dashboard', 'qc_member:_mark_qc_done_&_upload_photos', 'qc_supervisor:_approve_qc'],
      'Order Processing' => ['mark_order_as_shipped', 'mark_order_as_custom_cleared', 'mark_custom_cleared'],
      'BD Operations' => ['handoff_to_bd_inbound_team', 'bangladesh_inbound_access', 'mark_bangladesh_received', 'bangladesh_inbound_supervisor'],
    ];
    
    // Group permissions
    $grouped = [];
    foreach($perms as $p) {
      $found = false;
      foreach($categories as $catName => $catPerms) {
        if (in_array($p['label'], $catPerms)) {
          $grouped[$catName][] = $p;
          $found = true;
          break;
        }
      }
      if (!$found) {
        $grouped['Other'][] = $p;
      }
    }
  ?>
    <form method="post" class="usr-perm-card">
      <div class="usr-perm-title">🔐 <?= htmlspecialchars($r['label']) ?></div>
      <input type="hidden" name="rid" value="<?= (int)$r['id'] ?>">
      
      <?php foreach($grouped as $catName => $catPerms): ?>
        <div class="usr-perm-category collapsed">
          <div class="usr-perm-category-title" onclick="this.parentElement.classList.toggle('collapsed')"><?= htmlspecialchars($catName) ?></div>
          <div class="usr-perm-category-content">
          <div class="usr-perm-grid">
            <?php foreach($catPerms as $p): ?>
              <label class="usr-checkbox-label">
                <input type="checkbox" name="perms[]" value="<?= (int)$p['id'] ?>" <?= in_array($p['id'],$curr)?'checked':'' ?>>
                <span><?= htmlspecialchars($p['label']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          </div>
        </div>
      <?php endforeach; ?>
      
      <button class="usr-btn" name="perms_set" value="1">Save Permissions</button>
    </form>
  <?php endforeach; ?>
</div>
</div>
</div>
<?php $content=ob_get_clean(); include __DIR__.'/layout.php';
