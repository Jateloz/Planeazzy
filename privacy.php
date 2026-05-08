<?php
$pageTitle = 'Privacy Policy — Planeazzy';
$noSidebar = true;
require_once __DIR__.'/config/config.php';
require_once __DIR__.'/services/Security.php';
Security::startSession();
$isPatient = !empty($_SESSION['patient_id']) && !empty($_SESSION['authenticated']);
$initials  = strtoupper(substr($_SESSION['patient_name']??'U', 0, 1));
$updated   = 'April 22, 2025';
$effective = '1 April 2025';
include __DIR__.'/includes/header.php';
?>
<style>
.legal-wrap{max-width:860px;margin:0 auto;padding:48px 24px 80px}
.legal-hero{background:linear-gradient(135deg,#0c1322 0%,#1462c4 55%,#0d9488 100%);border-radius:20px;padding:40px 44px;margin-bottom:40px;color:#fff}
.legal-hero h1{font-size:clamp(1.5rem,3vw,2rem);font-weight:900;letter-spacing:-.04em;margin-bottom:8px}
.legal-hero p{font-size:14px;color:rgba(200,220,255,.8);line-height:1.7}
.legal-meta{display:flex;gap:20px;margin-top:18px;flex-wrap:wrap}
.legal-meta span{display:flex;align-items:center;gap:6px;font-size:12.5px;color:rgba(255,255,255,.65);background:rgba(255,255,255,.08);padding:5px 12px;border-radius:20px}
.legal-meta i{font-size:11px}
.legal-toc{background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:14px;padding:22px 26px;margin-bottom:32px}
.legal-toc h3{font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:12px}
.legal-toc ol{margin:0;padding-left:18px;display:grid;grid-template-columns:1fr 1fr;gap:4px 20px}
.legal-toc li{font-size:13.5px;color:#1978e5;cursor:pointer;line-height:1.7}
.legal-toc a{color:#1978e5;text-decoration:none;font-weight:500}
.legal-toc a:hover{color:#1462c4;}
.legal-s{margin-bottom:38px;scroll-margin-top:80px}
.legal-s h2{font-size:1.075rem;font-weight:800;color:#0f172a;letter-spacing:-.03em;margin-bottom:14px;display:flex;align-items:center;gap:12px;padding-bottom:11px;border-bottom:2px solid #f1f5f9}
.legal-s h2 .num{width:30px;height:30px;border-radius:9px;background:linear-gradient(135deg,rgba(25,120,229,.15),rgba(25,120,229,.08));color:#1978e5;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:900;flex-shrink:0;border:1px solid rgba(25,120,229,.1)}
.legal-s h3{font-size:.8125rem;font-weight:800;color:#1e293b;margin:16px 0 6px;text-transform:uppercase;letter-spacing:.07em;display:flex;align-items:center;gap:6px}
.legal-s h3::before{content:'';width:3px;height:12px;background:#1978e5;border-radius:2px;display:inline-block}
.legal-s p{font-size:14px;color:#334155;line-height:1.85;margin-bottom:10px}
.legal-s ul,.legal-s ol{padding-left:22px;margin-bottom:12px}
.legal-s li{font-size:14px;color:#334155;line-height:1.85;margin-bottom:5px}
.legal-box{background:#f0fdf4;border:1px solid rgba(22,163,74,.25);border-radius:13px;padding:16px 20px;margin:14px 0}
.legal-box.warn{background:#fffbeb;border-color:rgba(217,119,6,.25)}
.legal-box.blue{background:#eff6ff;border-color:rgba(25,120,229,.25)}
.legal-box-ti{font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.1em;margin-bottom:7px;display:flex;align-items:center;gap:6px}
.legal-box.warn .legal-box-ti{color:#92400e}
.legal-box.blue .legal-box-ti{color:#1462c4}
.legal-box .legal-box-ti{color:#065f46}
.legal-box p,.legal-box li{font-size:13.5px;color:#334155;line-height:1.75}
.legal-contact{background:linear-gradient(135deg,#0b1120,#0c1e3a);border-radius:18px;padding:32px 36px;margin-top:44px;color:#e2e8f0;display:grid;grid-template-columns:1fr 1fr;gap:20px;box-shadow:0 4px 32px rgba(0,0,0,.15)}
.legal-contact h3{font-size:15px;font-weight:800;color:#fff;margin-bottom:10px;grid-column:1/-1}
.legal-contact-item{display:flex;align-items:flex-start;gap:12px}
.legal-contact-ic{width:36px;height:36px;border-radius:9px;background:rgba(25,120,229,.2);display:flex;align-items:center;justify-content:center;font-size:14px;color:#60a5fa;flex-shrink:0}
.legal-contact-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.45);margin-bottom:3px}
.legal-contact-val{font-size:13.5px;font-weight:600;color:#e2e8f0}
@media(max-width:600px){.legal-hero{padding:30px 24px}.legal-wrap{padding:28px 16px 64px}.legal-toc ol{grid-template-columns:1fr}.legal-contact{grid-template-columns:1fr;padding:24px 20px}}
</style>

<div class="legal-wrap">

  <!-- Hero -->
  <div class="legal-hero">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:rgba(255,255,255,.5);margin-bottom:10px">Legal &amp; Compliance</div>
    <h1>Privacy Policy</h1>
    <p>How Planeazzy collects, uses, protects, and shares your personal and health information — in compliance with the Kenya Data Protection Act 2019 (No. 24 of 2019).</p>
    <div class="legal-meta">
      <span><i class="fa-solid fa-calendar-check"></i> Effective: <?=$effective?></span>
      <span><i class="fa-solid fa-rotate"></i> Last Updated: <?=$updated?></span>
      <span><i class="fa-solid fa-shield-halved"></i> KDPA 2019 Compliant</span>
      <span><i class="fa-solid fa-certificate"></i> ODPC Registered</span>
    </div>
  </div>

  <!-- Table of Contents -->
  <div class="legal-toc">
    <h3><i class="fa-solid fa-list" style="margin-right:6px"></i> Table of Contents</h3>
    <ol>
      <li><a href="#s1">Who We Are</a></li>
      <li><a href="#s2">Information We Collect</a></li>
      <li><a href="#s3">How We Use Your Information</a></li>
      <li><a href="#s4">Legal Basis for Processing</a></li>
      <li><a href="#s5">Sharing &amp; Disclosure</a></li>
      <li><a href="#s6">Data Retention</a></li>
      <li><a href="#s7">Your Rights Under KDPA 2019</a></li>
      <li><a href="#s8">Health Information (Sensitive Data)</a></li>
      <li><a href="#s9">Data Security</a></li>
      <li><a href="#s10">Cookies &amp; Tracking</a></li>
      <li><a href="#s11">Children's Privacy</a></li>
      <li><a href="#s12">Cross-Border Transfers</a></li>
      <li><a href="#s13">Changes to This Policy</a></li>
      <li><a href="#s14">Contact &amp; Complaints</a></li>
    </ol>
  </div>

  <div class="legal-box blue">
    <div class="legal-box-ti"><i class="fa-solid fa-circle-info"></i> Plain-Language Summary</div>
    <p>Planeazzy is a Kenyan health-tech platform. We collect your name, contact details, and health appointment data so you can book and manage healthcare services. We never sell your data. You have the right to access, correct, delete, and object to how we use your information. For health data, we always ask for your explicit consent.</p>
  </div>

  <!-- Sections -->
  <div class="legal-s" id="s1">
    <h2><span class="num">1</span> Who We Are</h2>
    <p><strong>Planeazzy Health Technologies Ltd</strong> ("Planeazzy", "we", "us", "our") is a technology company incorporated in Kenya, operating the Planeazzy digital health platform at <a href="https://planeazzy.com">planeazzy.com</a>.</p>
    <p>We are registered as a Data Controller with the <strong>Office of the Data Protection Commissioner (ODPC)</strong> of Kenya under the Kenya Data Protection Act, 2019 (KDPA). Our Data Protection Officer (DPO) can be contacted at the details in Section 14.</p>
    <h3>Our Platform Services</h3>
    <ul>
      <li>Online appointment booking with hospitals, clinics, and specialist doctors across Kenya</li>
      <li>Hospital and clinic management dashboards for registered health facilities</li>
      <li>Appointment reminders and health notifications via SMS and email</li>
      <li>Emergency medical service coordination</li>
      <li>Telehealth consultation facilitation</li>
    </ul>
  </div>

  <div class="legal-s" id="s2">
    <h2><span class="num">2</span> Information We Collect</h2>
    <h3>2.1 Information You Provide Directly</h3>
    <ul>
      <li><strong>Account registration:</strong> full name, email address, phone number, date of birth, gender, national ID number</li>
      <li><strong>Health profile:</strong> medical history (optional), insurance details, known allergies, emergency contacts</li>
      <li><strong>Appointment data:</strong> reason for visit, preferred doctor, service type, location preference</li>
      <li><strong>Profile photos:</strong> images you upload to your account (stored securely, not shared publicly)</li>
      <li><strong>Communications:</strong> messages sent to healthcare providers through our platform</li>
      <li><strong>Payment information:</strong> we use third-party processors (M-Pesa, Stripe) and do not store card numbers</li>
    </ul>
    <h3>2.2 Information We Collect Automatically</h3>
    <ul>
      <li>Device information (browser type, operating system, device model)</li>
      <li>IP address and approximate geographic location (county level)</li>
      <li>Platform usage logs: pages visited, features used, session duration</li>
      <li>Referral sources and search queries</li>
    </ul>
    <h3>2.3 Information from Third Parties</h3>
    <ul>
      <li>Healthcare providers may add notes or records to your profile (with your consent)</li>
      <li>Insurance verification partners may confirm coverage status</li>
      <li>Government health databases (NHIF/SHA) with explicit patient consent only</li>
    </ul>
    <div class="legal-box warn">
      <div class="legal-box-ti"><i class="fa-solid fa-triangle-exclamation"></i> Sensitive Personal Data</div>
      <p>Health and medical information is classified as <strong>sensitive personal data</strong> under Section 2 of the KDPA 2019. We collect, process, and store this data only with your <strong>explicit written consent</strong>, or where necessary for the provision of healthcare services you have requested.</p>
    </div>
  </div>

  <div class="legal-s" id="s3">
    <h2><span class="num">3</span> How We Use Your Information</h2>
    <p>We use your information only for specific, declared purposes as required by Section 26 of the KDPA 2019:</p>
    <ul>
      <li><strong>Service delivery:</strong> booking appointments, sending confirmations, reminders (SMS/email), coordinating with healthcare providers</li>
      <li><strong>Account management:</strong> verifying identity, managing your profile, processing password resets</li>
      <li><strong>Safety and emergency:</strong> sharing location/contact data with emergency services when life is at risk</li>
      <li><strong>Platform improvement:</strong> anonymised and aggregated analytics to improve service quality</li>
      <li><strong>Legal compliance:</strong> responding to lawful requests from regulatory bodies, courts, or law enforcement</li>
      <li><strong>Fraud prevention:</strong> detecting and preventing unauthorized access or fraudulent activity</li>
      <li><strong>Communications:</strong> sending service updates, policy changes, and security alerts</li>
    </ul>
    <div class="legal-box">
      <div class="legal-box-ti"><i class="fa-solid fa-circle-check"></i> We Do NOT</div>
      <ul>
        <li>Sell, rent, or trade your personal data to advertisers or data brokers</li>
        <li>Use your health data for insurance underwriting without explicit consent</li>
        <li>Send unsolicited marketing without opt-in consent</li>
        <li>Profile you for political or credit scoring purposes</li>
      </ul>
    </div>
  </div>

  <div class="legal-s" id="s4">
    <h2><span class="num">4</span> Legal Basis for Processing (KDPA 2019 Section 30)</h2>
    <p>We rely on the following lawful grounds for processing personal data:</p>
    <ul>
      <li><strong>Consent (S.30(1)(a)):</strong> For health data, marketing, and optional features — you may withdraw at any time</li>
      <li><strong>Contract (S.30(1)(b)):</strong> Processing necessary to fulfil our service agreement with you (booking appointments)</li>
      <li><strong>Legal obligation (S.30(1)(c)):</strong> Compliance with Kenyan laws including the Health Act 2017, Data Protection Act 2019</li>
      <li><strong>Vital interests (S.30(1)(d)):</strong> Emergency situations where processing is necessary to protect your life or another person's life</li>
      <li><strong>Legitimate interests (S.30(1)(f)):</strong> Fraud prevention, platform security, service improvement (never overrides your fundamental rights)</li>
    </ul>
  </div>

  <div class="legal-s" id="s5">
    <h2><span class="num">5</span> Sharing &amp; Disclosure</h2>
    <h3>We Share Data With:</h3>
    <ul>
      <li><strong>Healthcare providers:</strong> hospitals, clinics, and doctors you book through our platform receive only the data necessary to provide your care</li>
      <li><strong>Service providers:</strong> cloud hosting (AWS/Africa-based), SMS providers (Africa's Talking), email delivery (SendGrid) — all bound by data processing agreements</li>
      <li><strong>Emergency services:</strong> your name, phone, and location may be shared with emergency responders when life is at risk</li>
      <li><strong>Legal authorities:</strong> when required by a court order, subpoena, or statutory obligation under Kenyan law</li>
      <li><strong>Business transfers:</strong> in a merger or acquisition, your data is transferred under equivalent privacy protections — you will be notified</li>
    </ul>
    <div class="legal-box">
      <div class="legal-box-ti"><i class="fa-solid fa-shield-halved"></i> Data Processing Agreements</div>
      <p>All third-party service providers who process personal data on our behalf are bound by <strong>Data Processing Agreements (DPAs)</strong> that require them to handle your data only according to our instructions and with appropriate security measures, as required by Section 43 of the KDPA 2019.</p>
    </div>
  </div>

  <div class="legal-s" id="s6">
    <h2><span class="num">6</span> Data Retention</h2>
    <p>We retain personal data for as long as necessary for the purposes stated, and as required by Kenyan law:</p>
    <ul>
      <li><strong>Active account data:</strong> retained for the duration of your account plus 7 years (Health Act 2017 requirement for medical records)</li>
      <li><strong>Appointment records:</strong> 7 years from appointment date</li>
      <li><strong>Deleted accounts:</strong> anonymised within 30 days; legal records retained for 7 years</li>
      <li><strong>Uploaded profile photos:</strong> deleted within 30 days of account closure</li>
      <li><strong>System logs:</strong> 12 months maximum</li>
      <li><strong>Inactive accounts:</strong> notified after 2 years of inactivity; deleted after 3 years unless you re-activate</li>
    </ul>
  </div>

  <div class="legal-s" id="s7">
    <h2><span class="num">7</span> Your Rights Under KDPA 2019</h2>
    <p>Under the Kenya Data Protection Act 2019 (Sections 26–34), you have the following rights as a data subject:</p>
    <ul>
      <li><strong>Right to be informed (S.26):</strong> Know what data we hold and how it's used — this policy fulfils that obligation</li>
      <li><strong>Right of access (S.26(a)):</strong> Request a copy of all personal data we hold about you within 21 days</li>
      <li><strong>Right to rectification (S.26(b)):</strong> Correct inaccurate or incomplete personal data</li>
      <li><strong>Right to erasure (S.26(e)):</strong> Request deletion of your data (subject to legal retention obligations)</li>
      <li><strong>Right to restrict processing (S.26(c)):</strong> Object to certain types of processing</li>
      <li><strong>Right to data portability (S.26(f)):</strong> Receive your data in a structured, machine-readable format</li>
      <li><strong>Right to object (S.33):</strong> Object to processing based on legitimate interests or direct marketing</li>
      <li><strong>Rights related to automated decision-making:</strong> Not be subject to solely automated decisions that significantly affect you</li>
    </ul>
    <div class="legal-box blue">
      <div class="legal-box-ti"><i class="fa-solid fa-paper-plane"></i> Exercise Your Rights</div>
      <p>Email <a href="mailto:privacy@planeazzy.com">privacy@planeazzy.com</a> with subject "Data Subject Request — [Your Right]". We will respond within <strong>21 days</strong> as required by the KDPA. Identity verification may be required before processing your request. All requests are free of charge.</p>
    </div>
  </div>

  <div class="legal-s" id="s8">
    <h2><span class="num">8</span> Health Information (Sensitive Data)</h2>
    <p>Health data receives the highest level of protection under the KDPA 2019. We apply additional safeguards:</p>
    <ul>
      <li><strong>Explicit consent required:</strong> We obtain separate, specific, informed consent before collecting any health information</li>
      <li><strong>Minimum necessary principle:</strong> We collect only the health data required to facilitate your appointment or care</li>
      <li><strong>Access controls:</strong> Health data is accessible only to the specific healthcare provider(s) involved in your care, and to you</li>
      <li><strong>Encryption:</strong> All health data is encrypted at rest (AES-256) and in transit (TLS 1.3)</li>
      <li><strong>Audit logs:</strong> Every access to health records is logged with timestamp, user, and purpose</li>
      <li><strong>No secondary use:</strong> Health data is never used for research, advertising, or insurance underwriting without explicit separate consent</li>
    </ul>
  </div>

  <div class="legal-s" id="s9">
    <h2><span class="num">9</span> Data Security</h2>
    <p>We implement industry-standard and KDPA-compliant technical and organisational security measures:</p>
    <ul>
      <li>TLS 1.3 encryption for all data in transit</li>
      <li>AES-256 encryption for data at rest</li>
      <li>Bcrypt password hashing (cost factor 12+)</li>
      <li>Multi-factor authentication available for hospital accounts</li>
      <li>Regular security penetration testing by certified professionals</li>
      <li>Role-based access controls — staff access data only on need-to-know basis</li>
      <li>Intrusion detection and anomaly monitoring</li>
      <li>Automated backups with point-in-time recovery</li>
    </ul>
    <h3>Data Breach Response (KDPA Section 41)</h3>
    <p>In the event of a data breach that poses a risk to your rights and freedoms, we will notify the <strong>ODPC within 72 hours</strong> and notify affected data subjects without undue delay, as required by KDPA Section 41.</p>
  </div>

  <div class="legal-s" id="s10">
    <h2><span class="num">10</span> Cookies &amp; Tracking</h2>
    <p>We use the following types of cookies:</p>
    <ul>
      <li><strong>Essential cookies:</strong> Required for login sessions, security tokens, and basic platform functionality — cannot be disabled</li>
      <li><strong>Performance cookies:</strong> Anonymous analytics to understand how the platform is used (opt-out available)</li>
      <li><strong>Preference cookies:</strong> Remember your language and display preferences</li>
    </ul>
    <p>We do not use tracking cookies for advertising or cross-site behavioural profiling. You can manage cookie preferences in your browser settings. Note that disabling essential cookies will prevent login.</p>
  </div>

  <div class="legal-s" id="s11">
    <h2><span class="num">11</span> Children's Privacy</h2>
    <p>Planeazzy is not directed at children under 18. We do not knowingly collect personal data from minors without verified parental or guardian consent as required by Section 32 of the KDPA 2019.</p>
    <p>Parents or guardians may create accounts on behalf of minor dependants. In such cases, parental consent is required for all data processing, and the minor's data is treated with additional protective measures.</p>
    <p>If you believe a minor has registered without consent, please contact <a href="mailto:privacy@planeazzy.com">privacy@planeazzy.com</a> immediately and we will delete the account within 7 days.</p>
  </div>

  <div class="legal-s" id="s12">
    <h2><span class="num">12</span> Cross-Border Data Transfers</h2>
    <p>Your data is primarily stored and processed in Kenya and the African region. Where we transfer data internationally (e.g., cloud infrastructure, email delivery), we ensure:</p>
    <ul>
      <li>Transfers are to countries with adequate data protection laws, as assessed by the ODPC</li>
      <li>Standard contractual clauses or equivalent safeguards are in place as per KDPA Section 48</li>
      <li>Transfer impact assessments are conducted for high-risk transfers</li>
      <li>We document all cross-border transfers in our Records of Processing Activities (RoPA)</li>
    </ul>
  </div>

  <div class="legal-s" id="s13">
    <h2><span class="num">13</span> Changes to This Policy</h2>
    <p>We may update this Privacy Policy to reflect changes in our practices, technology, legal requirements, or other factors. When we make material changes, we will:</p>
    <ul>
      <li>Update the "Last Updated" date at the top of this page</li>
      <li>Send email notification to all registered users at least 30 days before the change takes effect</li>
      <li>Display a prominent in-app notification</li>
      <li>For material changes affecting health data processing, we will obtain fresh explicit consent</li>
    </ul>
  </div>

  <div class="legal-s" id="s14">
    <h2><span class="num">14</span> Contact &amp; Complaints</h2>
    <p>For privacy enquiries, data subject requests, or concerns:</p>
    <div class="legal-contact">
      <h3>Get in Touch</h3>
      <div class="legal-contact-item">
        <div class="legal-contact-ic"><i class="fa-solid fa-user-shield"></i></div>
        <div><div class="legal-contact-lbl">Data Protection Officer</div><div class="legal-contact-val">Planeazzy DPO</div></div>
      </div>
      <div class="legal-contact-item">
        <div class="legal-contact-ic"><i class="fa-solid fa-envelope"></i></div>
        <div><div class="legal-contact-lbl">Email</div><div class="legal-contact-val"><a href="mailto:privacy@planeazzy.com" style="color:#60a5fa">privacy@planeazzy.com</a></div></div>
      </div>
      <div class="legal-contact-item">
        <div class="legal-contact-ic"><i class="fa-solid fa-location-dot"></i></div>
        <div><div class="legal-contact-lbl">Postal Address</div><div class="legal-contact-val">Planeazzy Health Technologies Ltd<br>P.O. Box 12345-00100, Nairobi, Kenya</div></div>
      </div>
      <div class="legal-contact-item">
        <div class="legal-contact-ic"><i class="fa-solid fa-landmark"></i></div>
        <div><div class="legal-contact-lbl">Regulatory Body</div><div class="legal-contact-val">Office of the Data Protection Commissioner<br><a href="https://odpc.go.ke" target="_blank" style="color:#60a5fa">odpc.go.ke</a></div></div>
      </div>
    </div>
    <div class="legal-box" style="margin-top:20px">
      <div class="legal-box-ti"><i class="fa-solid fa-gavel"></i> Filing a Complaint</div>
      <p>If you are not satisfied with our response to a privacy concern, you have the right to file a complaint with the <strong>Office of the Data Protection Commissioner (ODPC)</strong> at <a href="https://odpc.go.ke">odpc.go.ke</a>. We encourage you to contact us first so we can resolve your concern directly.</p>
    </div>
  </div>

</div>

<?php include __DIR__.'/includes/footer.php'; ?>
