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
  .qv-root{--primary:#ff6b2c;--primary-light:#ff914b;--text:#0f172a;--text-light:#64748b;--bg:#f8fafc;--card:#fff;--border:#e2e8f0;--muted:#64748b;--shadow:0 1px 3px rgba(0,0,0,0.06),0 1px 2px rgba(0,0,0,0.04);--shadow-md:0 4px 6px -1px rgba(0,0,0,0.08),0 2px 4px -1px rgba(0,0,0,0.04);font-family:'Inter',system-ui,-apple-system,sans-serif}
  body{margin:0;background:var(--bg,#f8fafc)}
  .qv-header{background:linear-gradient(135deg,#ff6b2c 0%,#ff914b 50%,#ffb347 100%);padding:24px 0;box-shadow:0 4px 20px rgba(255,107,44,0.25);position:relative;overflow:hidden}
  .qv-header::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");opacity:.35}
  .qv-header-content{max-width:1100px;margin:0 auto;padding:0 20px;position:relative;z-index:1}
  .qv-title{color:#fff;font-size:28px;font-weight:800;margin:0;letter-spacing:-.5px;display:flex;align-items:center;gap:10px}
  .qv-subtitle{color:rgba(255,255,255,0.95);font-size:14px;margin-top:6px;font-weight:500}
  .qv-container{max-width:1100px;margin:20px auto;padding:20px}
  .qv-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:20px;margin-bottom:16px;box-shadow:var(--shadow);animation:qvFadeIn .5s ease-out}
  .qv-section-title{font-size:16px;font-weight:700;color:var(--text);margin:0 0 12px;display:flex;align-items:center;gap:8px}
  .qv-pill{padding:12px;border-radius:12px;background:#fff;border:1px solid var(--border)}
  .qv-note{color:var(--text-light)}
  .qv-btn{display:inline-block;background:linear-gradient(135deg,var(--primary),var(--primary-light));color:#fff !important;padding:10px 16px;border-radius:10px;font-weight:700;font-size:13px;transition:all .3s;box-shadow:0 2px 8px rgba(255,107,44,.25);border:none;cursor:pointer;text-decoration:none}
  .qv-btn:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(255,107,44,.35)}
  .qv-input, .qv-select, .qv-textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:10px;font-size:14px;color:var(--text);background:#fff}
  .qv-input:focus, .qv-select:focus, .qv-textarea:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(255,107,44,0.12)}
  .qv-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .mb16{margin-bottom:16px}
  .mt8{margin-top:8px}
  .mt16{margin-top:16px}
  @keyframes qvFadeIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
  @media(max-width:768px){.qv-row{grid-template-columns:1fr}.qv-title{font-size:22px}}
  /* Preserve existing IDs used by JS (#thread, #threadBox, #msgKind, #btnAddMsg, #msgBody) */
</style>
</head>
<body>
<div class="qv-root">
  <div class="qv-header">
    <div class="qv-header-content">
      <h1 class="qv-title">📝 Query <span style="opacity:.9">#<?= htmlspecialchars($q['query_code'] ?: $q['id']) ?></span></h1>
      <div class="qv-subtitle">Type: <?= htmlspecialchars($q['query_type']) ?> • Team: <?= htmlspecialchars($q['team_name'] ?: '-') ?> • Priority: <?= htmlspecialchars($currentPriority) ?></div>
    </div>
  </div>

  <div class="qv-container">
  <div class="qv-card">
    <div class="qv-section-title">Overview</div>
    <div class="qv-pill mb16">
    <div><strong>Type:</strong> <?= htmlspecialchars($q['query_type']) ?></div>
    <div><strong>Budget:</strong> <?= htmlspecialchars($q['budget'] ?? '0.00') ?></div>
    <div><strong>Shipping mode:</strong> <?= htmlspecialchars($q['shipping_mode'] ?: 'unknown') ?></div>
  </div>

    <div class="qv-section-title">Product details</div>
    <div class="qv-pill mb16" style="white-space:pre-wrap"><?= htmlspecialchars($q['product_details'] ?: '-') ?></div>

    <div class="qv-section-title">Meta</div>
    <div class="qv-pill mb16">
    <div><strong>Created at:</strong> <?= htmlspecialchars($q['created_at'] ?: '-') ?></div>
    <div><strong>Updated at:</strong> <?= htmlspecialchars($q['updated_at'] ?: '-') ?></div>
    <div><strong>Status:</strong> <?= htmlspecialchars($q['status']) ?></div>
    <div><strong>Team:</strong> <?= htmlspecialchars($q['team_name'] ?: '-') ?></div>
    <div><strong>Priority:</strong> <?= htmlspecialchars($currentPriority) ?></div>
  </div>

    <!-- Forward action is now a simple button that routes to fill.php -->
    <div class="mt8 mb16">
      <a class="qv-btn" href="/app/fill.php?id=<?= (int)$id ?>">Forward</a>
      <a class="qv-btn" style="margin-left:8px;background:linear-gradient(135deg,#64748b,#94a3b8)" href="/app/queries.php">Back to list</a>
    </div>
  </div>

  <div class="qv-card">
    <div class="qv-section-title" id="thread">Message thread</div>
    <div id="threadBox" class="qv-pill mb16">
    <?php if (!$messages): ?>
      <em>No messages yet.</em>
    <?php else: ?>
      <?php foreach ($messages as $msg): ?>
        <?php $who = $msg['sender_clerk_user_id'] ? 'Customer' : ($msg['admin_name'] ?? 'Team'); ?>
        <div style="margin-bottom:10px">
          <div class="qv-note">
            <?= htmlspecialchars($msg['created_at']) ?>
            — <?= htmlspecialchars($who) ?>
            — <?= htmlspecialchars($msg['direction']) ?>/<?= htmlspecialchars($msg['medium']) ?>
          </div>
          <div style="white-space:pre-wrap"><?= htmlspecialchars($msg['body'] ?? '') ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

    <!-- Composer -->
    <div class="qv-row">
      <div>
        <label>Kind</label>
        <select id="msgKind" class="qv-select">
        <option value="internal">Internal note (hidden)</option>
        <option value="outbound">Message to customer</option>
        <option value="whatsapp">Contacted via WhatsApp</option>
        <option value="email">Contacted via Email</option>
        <option value="voice">Contacted via Voice Call</option>
        <option value="other">Contacted via Other</option>
        </select>
      </div>
      <div>
        <label>&nbsp;</label>
        <button id="btnAddMsg" class="qv-btn" type="button">Add</button>
      </div>
    </div>
    <div class="mt8">
      <label>Text</label>
      <textarea id="msgBody" class="qv-textarea" rows="2" placeholder="Write a note or message..."></textarea>
    </div>
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
        location.hash = '#thread';
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
