<?php
// /public_html/app/query.php
error_reporting(E_ALL);
ini_set('display_errors','0');
ini_set('log_errors','1');
ini_set('error_log', __DIR__ . '/../_php_errors.log');

session_start();

// ---- AUTH GUARD ----
if (empty($_SESSION['admin']['id'])) {
  header('Location: /app/login.php');
  exit;
}
if (!in_array('view_queries', $_SESSION['perms'] ?? [], true)) {
  http_response_code(403);
  echo 'Forbidden'; exit;
}

require_once __DIR__ . '/../api/lib.php';
$pdo = db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); echo 'Bad id'; exit; }

/* ---------- fetch query ---------- */
$qs = $pdo->prepare("
  SELECT q.*, t.name AS team_name
  FROM queries q
  LEFT JOIN teams t ON t.id = q.current_team_id
  WHERE q.id = ?
  LIMIT 1
");
$qs->execute([$id]);
$q = $qs->fetch(PDO::FETCH_ASSOC);
if (!$q) { http_response_code(404); echo 'Query not found'; exit; }

/* ---------- load messages with admin name ---------- */
$ms = $pdo->prepare("
  SELECT m.id, m.direction, m.medium, m.body, m.created_at,
         m.sender_admin_id, m.sender_clerk_user_id,
         au.name AS admin_name
  FROM messages m
  LEFT JOIN admin_users au ON au.id = m.sender_admin_id
  WHERE m.query_id = ?
  ORDER BY m.id ASC
");
$ms->execute([$id]);
$messages = $ms->fetchAll(PDO::FETCH_ASSOC);

/* ---------- priority label ---------- */
$currentPriority = $q['priority'] ?: 'default';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Query #<?= htmlspecialchars($q['query_code'] ?: $q['id']) ?> — Backoffice</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
body{margin:0;background:#f8fafc;font-family:'Inter',system-ui,sans-serif}
.qv-root{--primary:#ff6b2c;--primary-light:#ff914b;--text:#0f172a;--text-light:#64748b;--border:#e2e8f0;max-width:1100px;margin:0 auto;padding:20px}
.qv-header{background:linear-gradient(135deg,#ff6b2c,#ff914b);padding:24px;border-radius:16px;color:#fff;margin-bottom:24px;box-shadow:0 4px 20px rgba(255,107,44,0.25)}
.qv-title{font-size:28px;font-weight:800;margin:0 0 8px;display:flex;align-items:center;gap:10px}
.qv-meta{font-size:14px;opacity:0.95}
.qv-card{background:#fff;border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,0.06)}
.qv-card h3{font-size:16px;font-weight:700;color:var(--text);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9}
.qv-info{margin-bottom:8px;line-height:1.6}
.qv-info strong{color:var(--text);font-weight:600;min-width:120px;display:inline-block}
.qv-btn{background:linear-gradient(135deg,var(--primary),var(--primary-light));color:#fff !important;padding:10px 20px;border-radius:10px;font-weight:700;font-size:13px;text-decoration:none;display:inline-block;box-shadow:0 2px 8px rgba(255,107,44,0.25);transition:all 0.3s;border:none;cursor:pointer}
.qv-btn:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(255,107,44,0.35)}
.qv-btn-secondary{background:linear-gradient(135deg,#64748b,#94a3b8);box-shadow:0 2px 8px rgba(100,116,139,0.25)}
.qv-input,.qv-select,.qv-textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:10px;font-size:14px;font-family:'Inter',sans-serif;background:#fff}
.qv-input:focus,.qv-select:focus,.qv-textarea:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(255,107,44,0.1)}
.qv-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
.qv-msg{padding:12px;background:#f8fafc;border-left:3px solid var(--primary);border-radius:8px;margin-bottom:12px}
.qv-msg-meta{font-size:12px;color:var(--text-light);margin-bottom:6px}
.qv-msg-body{white-space:pre-wrap;color:var(--text)}
@media(max-width:768px){.qv-root{padding:12px}.qv-title{font-size:22px}.qv-row{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="qv-root">
  <div class="qv-header">
    <h1 class="qv-title">
      📝 Query #<?= htmlspecialchars($q['query_code'] ?: $q['id']) ?>
    </h1>
    <div class="qv-meta">
      Type: <?= htmlspecialchars($q['query_type']) ?> • 
      Team: <?= htmlspecialchars($q['team_name'] ?: '-') ?> • 
      Priority: <?= htmlspecialchars($currentPriority) ?>
    </div>
  </div>

  <div class="qv-card">
    <h3>Overview</h3>
    <div class="qv-info"><strong>Type:</strong> <?= htmlspecialchars($q['query_type']) ?></div>
    <div class="qv-info"><strong>Budget:</strong> <?= htmlspecialchars($q['budget'] ?? '0.00') ?></div>
    <div class="qv-info"><strong>Shipping mode:</strong> <?= htmlspecialchars($q['shipping_mode'] ?: 'unknown') ?></div>
    <div class="qv-info"><strong>Created at:</strong> <?= htmlspecialchars($q['created_at'] ?: '-') ?></div>
    <div class="qv-info"><strong>Status:</strong> <?= htmlspecialchars($q['status']) ?></div>
  </div>

  <div class="qv-card">
    <h3>Product Details</h3>
    <div style="white-space:pre-wrap;color:var(--text)"><?= htmlspecialchars($q['product_details'] ?: 'No details provided') ?></div>
  </div>

  <div class="qv-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 style="margin:0">Message Thread</h3>
      <div>
        <a class="qv-btn qv-btn-secondary" href="/app/fill.php?id=<?= (int)$id ?>">Forward</a>
        <a class="qv-btn qv-btn-secondary" href="/app/queries.php" style="margin-left:8px">Back to List</a>
      </div>
    </div>
    
    <div id="threadBox">
      <?php if (!$messages): ?>
        <em style="color:var(--text-light)">No messages yet.</em>
      <?php else: ?>
        <?php foreach ($messages as $msg): ?>
          <?php $who = $msg['sender_clerk_user_id'] ? 'Customer' : ($msg['admin_name'] ?? 'Team'); ?>
          <div class="qv-msg">
            <div class="qv-msg-meta">
              <?= htmlspecialchars($msg['created_at']) ?> — 
              <?= htmlspecialchars($who) ?> — 
              <?= htmlspecialchars($msg['direction']) ?>/<?= htmlspecialchars($msg['medium']) ?>
            </div>
            <div class="qv-msg-body"><?= htmlspecialchars($msg['body'] ?? '') ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div style="margin-top:20px;padding-top:20px;border-top:1px solid #f1f5f9">
      <h4 style="margin:0 0 12px;font-size:14px;font-weight:700">Add Message</h4>
      <div class="qv-row">
        <div>
          <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Kind</label>
          <select id="msgKind" class="qv-select">
            <option value="internal">Internal note (hidden)</option>
            <option value="outbound">Message to customer</option>
            <option value="whatsapp">Contacted via WhatsApp</option>
            <option value="email">Contacted via Email</option>
            <option value="voice">Contacted via Voice Call</option>
            <option value="other">Contacted via Other</option>
          </select>
        </div>
        <div style="display:flex;align-items:end">
          <button id="btnAddMsg" class="qv-btn" type="button" style="width:100%">Add Message</button>
        </div>
      </div>
      <div style="margin-top:12px">
        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Message</label>
        <textarea id="msgBody" class="qv-textarea" rows="3" placeholder="Write a note or message..."></textarea>
      </div>
    </div>
  </div>
</div>

<script>
  const QID = <?= (int)$id ?>;

  function kindToDirectionMedium(kind) {
    if (kind === 'internal') return { direction: 'internal', medium: 'note' };
    if (kind === 'outbound') return { direction: 'outbound', medium: 'message' };
    if (['whatsapp','email','voice','other'].includes(kind)) {
      return { direction: 'outbound', medium: kind };
    }
    return { direction: 'internal', medium: 'note' };
  }

  document.getElementById('btnAddMsg').addEventListener('click', async () => {
    const kind = document.getElementById('msgKind').value;
    const body = document.getElementById('msgBody').value.trim();
    if (!body) { alert('Message cannot be empty'); return; }

    const map  = kindToDirectionMedium(kind);
    const fd = new FormData();
    fd.append('id', QID);
    fd.append('direction', map.direction);
    fd.append('medium', map.medium);
    fd.append('body', body);

    try {
      const res = await fetch('/api/add_admin_message.php', {
        method: 'POST',
        body: fd,
        credentials: 'include'
      });
      const data = await res.json();
      if (data.ok) {
        document.getElementById('msgBody').value = '';
        location.reload();
      } else {
        alert(data.error || 'Failed to add message');
      }
    } catch (e) {
      alert('Network error');
    }
  });
</script>
</body>
</html>
