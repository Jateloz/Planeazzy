<?php
/**
 * Hospital Dashboard Sidebar — shared partial (FontAwesome)
 */
$_sbPending = 0;
$_sbUnread  = 0;
$_sbLogo    = $hosp['logo_path'] ?? '';
$_sbFacName = $hosp['facility_name'] ?? ($hosp['admin_name'] ?? 'Hospital');
$_sbInitials= strtoupper(implode('', array_map(fn($w)=>$w[0], array_slice(explode(' ', trim($hosp['admin_name']??'H')), 0, 2))));
try {
    $_sbPending = (int)($db->fetchOne('SELECT COUNT(*) c FROM hospital_appointments WHERE hospital_id=:h AND status="pending"', [':h'=>$hid])['c'] ?? 0);
    $_sbUnread  = (int)($db->fetchOne('SELECT COUNT(*) c FROM hospital_notifications WHERE hospital_id=:h AND is_read=0', [':h'=>$hid])['c'] ?? 0);
} catch(Exception $_e) {}
$_currentPage = $currentPage ?? ($tab ?? '');
?>
<style>
:root{--cp-sb-w:240px}
.cp-sidebar{position:fixed;left:0;top:0;bottom:0;width:240px;background:#fff;border-right:1px solid rgba(0,0,0,.07);display:flex;flex-direction:column;z-index:200;overflow:hidden;transition:transform .25s;box-shadow:2px 0 12px rgba(0,0,0,.04)}
.cp-sidebar-brand{padding:16px 16px 12px;border-bottom:1px solid rgba(0,0,0,.07);flex-shrink:0}
.cp-sidebar-brand-name{font-size:.9375rem;font-weight:900;letter-spacing:-.03em;color:#005ab4}
.cp-sidebar-brand-sub{font-size:.5rem;text-transform:uppercase;letter-spacing:.12em;color:#73777f;margin-bottom:8px}
.cp-nav-item{display:flex;align-items:center;gap:10px;padding:9px 14px;border-radius:10px;margin:1px 8px;font-size:.8125rem;font-weight:500;color:#42474e;cursor:pointer;transition:background .12s,color .12s;text-decoration:none;border:none;background:none;width:calc(100% - 16px);text-align:left}
.cp-nav-item:hover{background:#f1f5f9;color:#0f172a;text-decoration:none}
.cp-nav-item.active{background:rgba(0,90,180,.09);color:#005ab4;font-weight:700}
.cp-nav-item i{font-size:14px;width:16px;text-align:center;flex-shrink:0}
.cp-nav-badge{margin-left:auto;background:#005ab4;color:#fff;font-size:.5rem;font-weight:800;padding:1px 6px;border-radius:9999px;min-width:16px;text-align:center}
.cp-sb-section{font-size:.5625rem;font-weight:800;text-transform:uppercase;letter-spacing:.14em;color:#94a3b8;padding:10px 16px 3px}
.cp-sb-facility{display:flex;align-items:center;gap:8px}
.cp-sb-fac-ic{width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,#005ab4,#0873df);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.cp-sb-footer{padding:8px 8px;border-top:1px solid rgba(0,0,0,.07);flex-shrink:0}
@media(max-width:900px){
  .cp-sidebar{transform:translateX(-100%)}
  .cp-sidebar.open{transform:translateX(0)}
}
</style>
<aside class="cp-sidebar" id="cpSidebar">
  <div class="cp-sidebar-brand">
    <?php if($_sbLogo):?>
    <img src="<?=htmlspecialchars($_sbLogo)?>" alt="" style="height:28px;object-fit:contain;margin-bottom:6px;border-radius:5px;display:block">
    <?php else:?>
    <div class="cp-sidebar-brand-name">Planeazzy</div>
    <?php endif;?>
    <div class="cp-sidebar-brand-sub">Provider Dashboard</div>
    <div class="cp-sb-facility">
      <div class="cp-sb-fac-ic"><i class="fa-solid fa-hospital" style="font-size:12px;color:#fff"></i></div>
      <div style="min-width:0">
        <div style="font-size:.75rem;font-weight:700;color:#005ab4;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:150px"><?=htmlspecialchars($_sbFacName)?></div>
        <div style="font-size:.5625rem;color:#73777f">Provider Admin</div>
      </div>
    </div>
  </div>
  <div style="flex:1;overflow-y:auto;padding:8px 0">
    <div class="cp-sb-section">MAIN</div>
    <?php foreach([
      ['overview',      'fa-gauge',           'Overview',       0],
      ['appointments',  'fa-calendar-check',  'Appointments',   $_sbPending],
      ['doctors',       'fa-user-doctor',     'Doctors',        0],
      ['services',      'fa-briefcase-medical','Services',      0],
    ] as [$k,$ic,$lb,$bd]):$isA=($_currentPage===$k);?>
    <a href="/hospital/onboarding/dashboard.php?tab=<?=$k?>" class="cp-nav-item <?=$isA?'active':''?>">
      <i class="fa-solid <?=$ic?>"></i><span><?=$lb?></span>
      <?php if($bd>0):?><span class="cp-nav-badge"><?=$bd?></span><?php endif;?>
    </a>
    <?php endforeach;?>
    <div class="cp-sb-section" style="margin-top:6px">REPORTS</div>
    <?php foreach([
      ['insurance',     'fa-shield-halved',   'Insurance',      0],
      ['analytics',     'fa-chart-line',      'Analytics',      0],
      ['notifications', 'fa-bell',            'Notifications',  $_sbUnread],
      ['settings',      'fa-gear',            'Settings',       0],
    ] as [$k,$ic,$lb,$bd]):$isA=($_currentPage===$k);?>
    <a href="/hospital/onboarding/dashboard.php?tab=<?=$k?>" class="cp-nav-item <?=$isA?'active':''?>">
      <i class="fa-solid <?=$ic?>"></i><span><?=$lb?></span>
      <?php if($bd>0):?><span class="cp-nav-badge"><?=$bd?></span><?php endif;?>
    </a>
    <?php endforeach;?>
  </div>
  <div class="cp-sb-footer">
    <a href="/hospital/onboarding/logout.php" class="cp-nav-item" style="color:#dc2626">
      <i class="fa-solid fa-right-from-bracket"></i><span>Sign Out</span>
    </a>
  </div>
</aside>
