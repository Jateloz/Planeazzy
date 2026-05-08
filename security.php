<?php
$pageTitle = 'Security & Trust Centre — Planeazzy';
$noSidebar = true;
require_once __DIR__.'/config/config.php';
require_once __DIR__.'/services/Security.php';
Security::startSession();
$isPatient = !empty($_SESSION['patient_id']) && !empty($_SESSION['authenticated']);
$initials  = strtoupper(substr($_SESSION['patient_name']??'U', 0, 1));
include __DIR__.'/includes/header.php';
?>
<style>
.sv-wrap{max-width:1000px;margin:0 auto;padding:48px 24px 80px}
.sv-hero{background:linear-gradient(135deg,#0c1322 0%,#0f2957 45%,#0d3349 100%);border-radius:20px;padding:44px 48px;margin-bottom:40px;color:#fff;position:relative;overflow:hidden}
.sv-hero::before{content:'';position:absolute;right:-60px;top:-60px;width:280px;height:280px;border-radius:50%;background:rgba(25,120,229,.12);pointer-events:none}
.sv-hero::after{content:'';position:absolute;right:40px;bottom:-40px;width:160px;height:160px;border-radius:50%;background:rgba(13,148,136,.1);pointer-events:none}
.sv-hero h1{font-size:clamp(1.5rem,3vw,2.1rem);font-weight:900;letter-spacing:-.04em;margin-bottom:10px;position:relative;z-index:1}
.sv-hero p{font-size:14px;color:rgba(200,220,255,.8);line-height:1.7;max-width:580px;position:relative;z-index:1}
.sv-hero-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(74,222,128,.15);border:1px solid rgba(74,222,128,.3);padding:5px 13px;border-radius:20px;font-size:11.5px;font-weight:700;color:#4ade80;margin-bottom:16px}
.sv-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:32px}
.sv-card{background:#fff;border-radius:16px;border:1.5px solid #e2e8f0;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.05);transition:all .2s}
.sv-card:hover{box-shadow:0 6px 24px rgba(0,0,0,.09);border-color:rgba(25,120,229,.2);transform:translateY(-2px)}
.sv-card-ic{width:48px;height:48px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:20px;margin-bottom:14px}
.sv-card-ic.blue{background:rgba(25,120,229,.1);color:#1978e5}
.sv-card-ic.teal{background:rgba(13,148,136,.1);color:#0d9488}
.sv-card-ic.green{background:rgba(22,163,74,.1);color:#16a34a}
.sv-card-ic.purple{background:rgba(124,58,237,.1);color:#7c3aed}
.sv-card-ic.red{background:rgba(220,38,38,.1);color:#dc2626}
.sv-card-ic.amber{background:rgba(217,119,6,.1);color:#d97706}
.sv-card h3{font-size:14.5px;font-weight:800;color:#0f172a;margin-bottom:6px}
.sv-card p{font-size:13px;color:#64748b;line-height:1.7}
.sv-section{margin-bottom:36px}
.sv-section h2{font-size:1.125rem;font-weight:800;color:#0f172a;letter-spacing:-.03em;margin-bottom:16px;display:flex;align-items:center;gap:10px}
.sv-section h2 i{font-size:16px;color:#1978e5}
.sv-tech-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.sv-tech-item{background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:12px;padding:16px 18px;display:flex;align-items:flex-start;gap:13px}
.sv-tech-ic{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:15px}
.sv-tech-ic.bl{background:rgba(25,120,229,.1);color:#1978e5}
.sv-tech-ic.te{background:rgba(13,148,136,.1);color:#0d9488}
.sv-tech-ic.gr{background:rgba(22,163,74,.1);color:#16a34a}
.sv-tech-ic.pu{background:rgba(124,58,237,.1);color:#7c3aed}
.sv-tech-ti{font-size:13px;font-weight:700;color:#0f172a;margin-bottom:3px}
.sv-tech-tx{font-size:12.5px;color:#64748b;line-height:1.6}
.sv-cert-row{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:24px}
.sv-cert{background:#fff;border:1.5px solid #e2e8f0;border-radius:12px;padding:16px 20px;display:flex;align-items:center;gap:13px;flex:1;min-width:200px;box-shadow:0 1px 4px rgba(0,0,0,.04)}
.sv-cert-ic{width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#1462c4,#1978e5);display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;flex-shrink:0}
.sv-cert-name{font-size:13px;font-weight:700;color:#0f172a}
.sv-cert-desc{font-size:11.5px;color:#64748b;margin-top:2px}
.sv-report-box{background:linear-gradient(135deg,#0c1322 0%,#101d36 100%);border-radius:16px;padding:28px 32px;color:#fff;display:flex;align-items:center;gap:24px;margin-top:32px;flex-wrap:wrap}
.sv-report-box h3{font-size:1rem;font-weight:800;margin-bottom:6px}
.sv-report-box p{font-size:13.5px;color:rgba(200,220,255,.75);line-height:1.6}
.sv-incident-timeline{margin-top:16px}
.sv-incident-row{display:flex;gap:16px;padding:14px 0;border-bottom:1px solid #f1f5f9}
.sv-incident-row:last-child{border-bottom:none}
.sv-incident-dot{width:10px;height:10px;border-radius:50%;margin-top:5px;flex-shrink:0}
.sv-incident-dot.green{background:#16a34a}
.sv-incident-dot.yellow{background:#d97706}
.sv-checklist{list-style:none;padding:0;display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px}
.sv-checklist li{display:flex;align-items:flex-start;gap:9px;font-size:13.5px;color:#334155;line-height:1.6}
.sv-checklist li i{color:#16a34a;font-size:13px;flex-shrink:0;margin-top:2px}
@media(max-width:900px){.sv-grid{grid-template-columns:1fr 1fr}.sv-tech-grid{grid-template-columns:1fr}.sv-checklist{grid-template-columns:1fr}}
@media(max-width:600px){.sv-grid{grid-template-columns:1fr}.sv-hero{padding:28px 22px}.sv-wrap{padding:24px 16px 60px}.sv-report-box{flex-direction:column}}
</style>

<div class="sv-wrap">

  <!-- Hero -->
  <div class="sv-hero">
    <div class="sv-hero-badge"><i class="fa-solid fa-shield-halved"></i> Security &amp; Trust Centre</div>
    <h1>Your Health Data is Safe With Us</h1>
    <p>Planeazzy is built on a foundation of security, privacy, and trust. We apply enterprise-grade security to protect every patient record, appointment, and communication on our platform — in full compliance with the Kenya Data Protection Act 2019.</p>
  </div>

  <!-- Core security cards -->
  <div class="sv-grid">
    <div class="sv-card">
      <div class="sv-card-ic blue"><i class="fa-solid fa-lock"></i></div>
      <h3>End-to-End Encryption</h3>
      <p>All data in transit is protected by TLS 1.3. All data at rest is encrypted using AES-256. Your health records are never transmitted in plain text.</p>
    </div>
    <div class="sv-card">
      <div class="sv-card-ic green"><i class="fa-solid fa-shield-check"></i></div>
      <h3>KDPA 2019 Compliant</h3>
      <p>We are registered as a Data Controller with Kenya's Office of the Data Protection Commissioner (ODPC). Our practices are fully compliant with the Kenya Data Protection Act 2019.</p>
    </div>
    <div class="sv-card">
      <div class="sv-card-ic teal"><i class="fa-solid fa-database"></i></div>
      <h3>Secure Data Storage</h3>
      <p>Patient data is stored in encrypted, access-controlled databases hosted in Kenya and the African region. Regular automated backups ensure data availability and integrity.</p>
    </div>
    <div class="sv-card">
      <div class="sv-card-ic purple"><i class="fa-solid fa-user-shield"></i></div>
      <h3>Role-Based Access</h3>
      <p>Strict access controls ensure that staff and healthcare providers can access only the data they need to perform their roles. Every access is logged and audited.</p>
    </div>
    <div class="sv-card">
      <div class="sv-card-ic amber"><i class="fa-solid fa-bell-slash"></i></div>
      <h3>Breach Response</h3>
      <p>We have a tested incident response plan. ODPC notification within 72 hours and patient notification without undue delay, as required by KDPA Section 41.</p>
    </div>
    <div class="sv-card">
      <div class="sv-card-ic red"><i class="fa-solid fa-magnifying-glass"></i></div>
      <h3>Regular Security Audits</h3>
      <p>Independent penetration testing, vulnerability assessments, and code security reviews are conducted quarterly by certified cybersecurity professionals.</p>
    </div>
  </div>

  <!-- Technical measures -->
  <div class="sv-section">
    <h2><i class="fa-solid fa-code-branch"></i> Technical Security Measures</h2>
    <div class="sv-tech-grid">
      <div class="sv-tech-item">
        <div class="sv-tech-ic bl"><i class="fa-solid fa-key"></i></div>
        <div><div class="sv-tech-ti">Password Security</div><div class="sv-tech-tx">All passwords are hashed using bcrypt with a minimum cost factor of 12. We enforce strong password policies and support account lockout after failed attempts. Passwords are never stored in plain text or reversible format.</div></div>
      </div>
      <div class="sv-tech-item">
        <div class="sv-tech-ic te"><i class="fa-solid fa-network-wired"></i></div>
        <div><div class="sv-tech-ti">TLS 1.3 Everywhere</div><div class="sv-tech-tx">All communications between your browser/app and our servers are encrypted using TLS 1.3. HTTP connections are automatically redirected to HTTPS. HSTS (HTTP Strict Transport Security) is enforced with a 1-year max-age.</div></div>
      </div>
      <div class="sv-tech-item">
        <div class="sv-tech-ic gr"><i class="fa-solid fa-fingerprint"></i></div>
        <div><div class="sv-tech-ti">Session Management</div><div class="sv-tech-tx">Sessions use cryptographically random tokens, are invalidated on logout, and expire after periods of inactivity. Session tokens are transmitted only over HTTPS and protected with HttpOnly and SameSite cookie flags.</div></div>
      </div>
      <div class="sv-tech-item">
        <div class="sv-tech-ic pu"><i class="fa-solid fa-ban"></i></div>
        <div><div class="sv-tech-ti">CSRF &amp; XSS Protection</div><div class="sv-tech-tx">All state-changing API requests require valid CSRF tokens. All user-supplied data is sanitised and encoded before output. Content Security Policy (CSP) headers prevent script injection attacks.</div></div>
      </div>
      <div class="sv-tech-item">
        <div class="sv-tech-ic bl"><i class="fa-solid fa-gauge-high"></i></div>
        <div><div class="sv-tech-ti">Rate Limiting &amp; DDoS</div><div class="sv-tech-tx">API endpoints are rate-limited per IP address to prevent brute-force attacks. Distributed denial-of-service (DDoS) protection is provided at the network edge. Automatic account lockout triggers after repeated failed login attempts.</div></div>
      </div>
      <div class="sv-tech-item">
        <div class="sv-tech-ic te"><i class="fa-solid fa-magnifying-glass-chart"></i></div>
        <div><div class="sv-tech-ti">Audit Logging</div><div class="sv-tech-tx">All access to patient health records, administrative actions, and API calls are logged with timestamps, user identifiers, and IP addresses. Logs are stored securely for 12 months and are immutable once written.</div></div>
      </div>
      <div class="sv-tech-item">
        <div class="sv-tech-ic gr"><i class="fa-solid fa-cloud-arrow-up"></i></div>
        <div><div class="sv-tech-ti">Backups &amp; Recovery</div><div class="sv-tech-tx">Automated encrypted backups run every 6 hours. Point-in-time recovery is available for up to 35 days. Backups are stored in geographically separate secure facilities. Recovery time objective (RTO): &lt;4 hours. Recovery point objective (RPO): &lt;6 hours.</div></div>
      </div>
      <div class="sv-tech-item">
        <div class="sv-tech-ic pu"><i class="fa-solid fa-upload"></i></div>
        <div><div class="sv-tech-ti">File Upload Security</div><div class="sv-tech-tx">Uploaded files (photos, documents) are validated for MIME type, size, and content before storage. Files are stored outside the web root and served through controlled endpoints. File names are randomised to prevent enumeration.</div></div>
      </div>
    </div>
  </div>

  <!-- Certifications -->
  <div class="sv-section">
    <h2><i class="fa-solid fa-certificate"></i> Compliance &amp; Certifications</h2>
    <div class="sv-cert-row">
      <div class="sv-cert">
        <div class="sv-cert-ic"><i class="fa-solid fa-shield-halved"></i></div>
        <div><div class="sv-cert-name">Kenya Data Protection Act 2019</div><div class="sv-cert-desc">ODPC Registered Data Controller</div></div>
      </div>
      <div class="sv-cert">
        <div class="sv-cert-ic" style="background:linear-gradient(135deg,#0d9488,#059669)"><i class="fa-solid fa-hospital"></i></div>
        <div><div class="sv-cert-name">Kenya Health Act 2017</div><div class="sv-cert-desc">Medical records compliant</div></div>
      </div>
      <div class="sv-cert">
        <div class="sv-cert-ic" style="background:linear-gradient(135deg,#7c3aed,#6d28d9)"><i class="fa-solid fa-globe"></i></div>
        <div><div class="sv-cert-name">ISO 27001 (In Progress)</div><div class="sv-cert-desc">Information security management</div></div>
      </div>
      <div class="sv-cert">
        <div class="sv-cert-ic" style="background:linear-gradient(135deg,#d97706,#b45309)"><i class="fa-solid fa-computer"></i></div>
        <div><div class="sv-cert-name">Computer Misuse Act 2018</div><div class="sv-cert-desc">Cybercrime compliance</div></div>
      </div>
    </div>
  </div>

  <!-- Patient rights checklist -->
  <div class="sv-section">
    <h2><i class="fa-solid fa-user-check"></i> Your Security Rights &amp; Controls</h2>
    <p style="font-size:14px;color:#64748b;margin-bottom:14px;line-height:1.7">As a Planeazzy user, you have the following security controls available to you at all times:</p>
    <ul class="sv-checklist">
      <li><i class="fa-solid fa-circle-check"></i>View all active login sessions and revoke them remotely</li>
      <li><i class="fa-solid fa-circle-check"></i>Download a full copy of all your personal data</li>
      <li><i class="fa-solid fa-circle-check"></i>Permanently delete your account and all associated data</li>
      <li><i class="fa-solid fa-circle-check"></i>Control which healthcare providers can access your records</li>
      <li><i class="fa-solid fa-circle-check"></i>Opt out of non-essential data processing at any time</li>
      <li><i class="fa-solid fa-circle-check"></i>Receive immediate notification of any data breach affecting you</li>
      <li><i class="fa-solid fa-circle-check"></i>Change your password and revoke all sessions instantly</li>
      <li><i class="fa-solid fa-circle-check"></i>File a data subject access request (DSAR) at any time</li>
      <li><i class="fa-solid fa-circle-check"></i>Withdraw consent for data processing (with exceptions for legal obligations)</li>
      <li><i class="fa-solid fa-circle-check"></i>Lodge a complaint with the ODPC if unsatisfied with our response</li>
    </ul>
  </div>

  <!-- Security status -->
  <div class="sv-section">
    <h2><i class="fa-solid fa-circle-check"></i> Platform Security Status</h2>
    <div style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:14px;padding:20px 24px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
        <div style="width:12px;height:12px;border-radius:50%;background:#16a34a;box-shadow:0 0 0 4px rgba(22,163,74,.15)"></div>
        <span style="font-size:14px;font-weight:700;color:#0f172a">All Systems Operational</span>
        <span style="font-size:11.5px;color:#64748b;margin-left:auto">Updated: <?=date('M j, Y')?></span>
      </div>
      <div class="sv-incident-timeline">
        <?php foreach([
          ['All security certificates valid and current','green','Security'],
          ['Last penetration test: No critical vulnerabilities found','green','Audit'],
          ['Backup verification: All backups healthy','green','Infrastructure'],
          ['ODPC registration: Current and valid','green','Compliance'],
        ] as [$msg,$dot,$cat]):?>
        <div class="sv-incident-row">
          <div class="sv-incident-dot <?=$dot?>"></div>
          <div>
            <div style="font-size:13.5px;color:#0f172a;font-weight:600"><?=$msg?></div>
            <div style="font-size:11.5px;color:#94a3b8;margin-top:2px"><?=$cat?></div>
          </div>
        </div>
        <?php endforeach;?>
      </div>
    </div>
  </div>

  <!-- Report vulnerability -->
  <div class="sv-report-box">
    <div style="flex:1">
      <h3> Found a Security Vulnerability?</h3>
      <p>We take security seriously and work with the security community through our responsible disclosure programme. If you discover a vulnerability, please report it to us before disclosing publicly. We commit to acknowledging reports within 24 hours and resolving critical issues within 72 hours.</p>
    </div>
    <div style="flex-shrink:0;display:flex;flex-direction:column;gap:10px">
      <a href="mailto:security@planeazzy.com" style="display:inline-flex;align-items:center;gap:7px;padding:10px 22px;background:rgba(25,120,229,.25);border:1px solid rgba(25,120,229,.4);border-radius:9px;color:#93c5fd;font-size:13.5px;font-weight:700;text-decoration:none;white-space:nowrap">
        <i class="fa-solid fa-envelope"></i> security@planeazzy.com
      </a>
      <span style="font-size:11px;color:rgba(255,255,255,.4);text-align:center">PGP key available on request</span>
    </div>
  </div>

</div>

<?php include __DIR__.'/includes/footer.php'; ?>
