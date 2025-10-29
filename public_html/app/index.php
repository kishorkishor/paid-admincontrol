<?php
require_once __DIR__.'/auth.php'; require_login();
$title='Dashboard';
$stats = db()->query("
  SELECT 
    SUM(status='new') as new_cnt,
    SUM(status='elaborated') as elaborated_cnt,
    SUM(status='in_process') as inproc_cnt,
    SUM(status='red_flag') as red_cnt
  FROM queries
")->fetch();
ob_start(); ?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
body:has(.idx-root){background:#f8fafc !important}
.wrap:has(.idx-root){background:transparent !important;box-shadow:none !important;padding:0 !important;margin:0 !important;max-width:100% !important}
.wrap:has(.idx-root) nav{background:#fff;padding:12px 24px;border-radius:12px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.06)}
.wrap:has(.idx-root) nav a{color:#64748b;font-weight:600;padding:8px 16px;border-radius:8px;transition:all 0.2s;display:inline-block}
.wrap:has(.idx-root) nav a:hover{background:#f1f5f9;color:#ff6b2c}
.wrap:has(.idx-root) hr{display:none}
.idx-root{--primary:#ff6b2c;--primary-light:#ff914b;--primary-dark:#e8551a;--success:#10b981;--warning:#f59e0b;--danger:#ef4444;--text:#0f172a;--text-light:#64748b;--text-lighter:#94a3b8;--bg:#f8fafc;--card:#ffffff;--border:#e2e8f0;--border-light:#f1f5f9;--shadow:0 1px 3px rgba(0,0,0,0.06),0 1px 2px rgba(0,0,0,0.04);--shadow-md:0 4px 6px -1px rgba(0,0,0,0.08),0 2px 4px -1px rgba(0,0,0,0.04);--shadow-lg:0 10px 15px -3px rgba(0,0,0,0.08),0 4px 6px -2px rgba(0,0,0,0.04);font-family:'Inter',system-ui,-apple-system,sans-serif}
.idx-root{background:linear-gradient(180deg,var(--bg) 0%,#ffffff 100%);min-height:100vh;padding:0;margin:0}
.idx-topbar{background:linear-gradient(135deg,#ff6b2c 0%,#ff914b 50%,#ffb347 100%);padding:32px 0;box-shadow:0 4px 20px rgba(255,107,44,0.3);margin-bottom:0;position:relative;overflow:hidden}
.idx-topbar::before{content:'';position:absolute;top:0;left:0;right:0;bottom:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");opacity:0.3}
.idx-topbar-content{max-width:1400px;margin:0 auto;padding:0 24px;position:relative;z-index:1}
.idx-topbar-title{color:#fff;font-size:36px;font-weight:800;margin:0;letter-spacing:-1px;text-shadow:0 2px 10px rgba(0,0,0,0.1);display:flex;align-items:center;gap:12px}
.idx-topbar-emoji{font-size:40px;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.1))}
.idx-topbar-subtitle{color:rgba(255,255,255,0.95);font-size:15px;margin-top:8px;font-weight:500}
.idx-container{max-width:1400px;margin:0 auto;padding:32px 24px}
.idx-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px}
.idx-stat-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px;box-shadow:var(--shadow);transition:all 0.3s cubic-bezier(0.4,0,0.2,1);position:relative;overflow:hidden}
.idx-stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--primary),var(--primary-light));transform:scaleX(0);transition:transform 0.3s;transform-origin:left}
.idx-stat-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-lg);border-color:var(--primary)}
.idx-stat-card:hover::before{transform:scaleX(1)}
.idx-stat-label{font-size:13px;color:var(--text-lighter);font-weight:600;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:12px;display:flex;align-items:center;gap:8px}
.idx-stat-icon{font-size:20px}
.idx-stat-value{font-size:48px;font-weight:800;background:linear-gradient(135deg,var(--primary),var(--primary-light));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin:0;line-height:1.1}
.idx-stat-card.new .idx-stat-value{background:linear-gradient(135deg,#10b981,#34d399);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.idx-stat-card.elaborated .idx-stat-value{background:linear-gradient(135deg,#3b82f6,#60a5fa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.idx-stat-card.inprocess .idx-stat-value{background:linear-gradient(135deg,#f59e0b,#fbbf24);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.idx-stat-card.redflag .idx-stat-value{background:linear-gradient(135deg,#ef4444,#f87171);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
@keyframes fadeInUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.idx-stat-card{animation:fadeInUp 0.6s ease-out backwards}
.idx-stat-card:nth-child(1){animation-delay:0.1s}
.idx-stat-card:nth-child(2){animation-delay:0.2s}
.idx-stat-card:nth-child(3){animation-delay:0.3s}
.idx-stat-card:nth-child(4){animation-delay:0.4s}
@media(max-width:768px){.idx-stats{grid-template-columns:repeat(2,1fr);gap:16px}.idx-container{padding:0 16px 24px}.idx-stat-value{font-size:36px}}
</style>
<div class="idx-root">
<!-- Top Bar -->
<div class="idx-topbar">
  <div class="idx-topbar-content">
    <h1 class="idx-topbar-title"><span class="idx-topbar-emoji">📊</span> Dashboard</h1>
    <p class="idx-topbar-subtitle">Real-time overview of query statistics and team performance</p>
  </div>
</div>

<div class="idx-container">
<!-- Stats Grid -->
<div class="idx-stats">
  <div class="idx-stat-card new">
    <div class="idx-stat-label"><span class="idx-stat-icon">🆕</span> New Queries</div>
    <div class="idx-stat-value"><?= (int)$stats['new_cnt'] ?></div>
  </div>
  <div class="idx-stat-card elaborated">
    <div class="idx-stat-label"><span class="idx-stat-icon">✍️</span> Elaborated</div>
    <div class="idx-stat-value"><?= (int)$stats['elaborated_cnt'] ?></div>
  </div>
  <div class="idx-stat-card inprocess">
    <div class="idx-stat-label"><span class="idx-stat-icon">⚙️</span> In Process</div>
    <div class="idx-stat-value"><?= (int)$stats['inproc_cnt'] ?></div>
  </div>
  <div class="idx-stat-card redflag">
    <div class="idx-stat-label"><span class="idx-stat-icon">🚩</span> Red Flags</div>
    <div class="idx-stat-value"><?= (int)$stats['red_cnt'] ?></div>
  </div>
</div>
</div>
</div>
<?php $content=ob_get_clean(); include __DIR__.'/layout.php';
