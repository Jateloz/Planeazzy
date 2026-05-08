<?php
$pageTitle = 'Terms of Service — Planeazzy';
$noSidebar = true;
require_once __DIR__.'/config/config.php';
require_once __DIR__.'/services/Security.php';
Security::startSession();
$isPatient = !empty($_SESSION['patient_id']) && !empty($_SESSION['authenticated']);
$initials  = strtoupper(substr($_SESSION['patient_name']??'U', 0, 1));
$effective = '1 April 2025';
include __DIR__.'/includes/header.php';
?>
<style>
.legal-wrap{max-width:860px;margin:0 auto;padding:48px 24px 80px}
.legal-hero{background:linear-gradient(135deg,#0c1322 0%,#1462c4 55%,#0d9488 100%);border-radius:20px;padding:40px 44px;margin-bottom:40px;color:#fff}
.legal-hero h1{font-size:clamp(1.5rem,3vw,2rem);font-weight:900;letter-spacing:-.04em;margin-bottom:8px}
.legal-hero p{font-size:14px;color:rgba(200,220,255,.8);line-height:1.7}
.legal-meta{display:flex;gap:14px;margin-top:16px;flex-wrap:wrap}
.legal-meta span{font-size:12px;color:rgba(255,255,255,.6);background:rgba(255,255,255,.08);padding:4px 11px;border-radius:20px;display:flex;align-items:center;gap:6px}
.legal-toc{background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:14px;padding:22px 26px;margin-bottom:32px}
.legal-toc h3{font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:12px}
.legal-toc ol{margin:0;padding-left:18px;display:grid;grid-template-columns:1fr 1fr;gap:4px 20px}
.legal-toc a{color:#1978e5;text-decoration:none;font-size:13.5px;font-weight:500;line-height:1.7}
.legal-toc a:hover{color:#1462c4;}
.legal-s{margin-bottom:38px;scroll-margin-top:80px}
.legal-s h2{font-size:1.075rem;font-weight:800;color:#0f172a;letter-spacing:-.03em;margin-bottom:14px;display:flex;align-items:center;gap:12px;padding-bottom:11px;border-bottom:2px solid #f1f5f9}
.legal-s h2 .num{width:30px;height:30px;border-radius:9px;background:linear-gradient(135deg,rgba(25,120,229,.15),rgba(25,120,229,.08));color:#1978e5;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:900;flex-shrink:0;border:1px solid rgba(25,120,229,.1)}
.legal-s h3{font-size:.8125rem;font-weight:800;color:#1e293b;margin:16px 0 6px;text-transform:uppercase;letter-spacing:.07em;display:flex;align-items:center;gap:6px}
.legal-s h3::before{content:'';width:3px;height:12px;background:var(--blue,#1978e5);border-radius:2px;display:inline-block}
.legal-s p{font-size:14px;color:#334155;line-height:1.85;margin-bottom:10px}
.legal-s li{font-size:14px;color:#334155;line-height:1.85;margin-bottom:5px}
.legal-s ul,.legal-s ol{padding-left:22px;margin-bottom:12px}
.legal-box{background:#f0fdf4;border:1px solid rgba(22,163,74,.25);border-radius:13px;padding:16px 20px;margin:14px 0}
.legal-box.warn{background:#fef2f2;border-color:rgba(220,38,38,.2)}
.legal-box.blue{background:#eff6ff;border-color:rgba(25,120,229,.2)}
.legal-box-ti{font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.1em;margin-bottom:7px;display:flex;align-items:center;gap:6px}
.legal-box .legal-box-ti{color:#065f46}
.legal-box.warn .legal-box-ti{color:#991b1b}
.legal-box.blue .legal-box-ti{color:#1462c4}
@media(max-width:600px){.legal-hero{padding:30px 24px}.legal-wrap{padding:28px 16px 64px}.legal-toc ol{grid-template-columns:1fr}}
</style>

<div class="legal-wrap">
  <div class="legal-hero">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:rgba(255,255,255,.5);margin-bottom:10px">Legal</div>
    <h1>Terms of Service</h1>
    <p>These Terms govern your use of Planeazzy's healthcare booking platform. By using our services, you agree to these Terms. Please read them carefully.</p>
    <div class="legal-meta">
      <span><i class="fa-solid fa-calendar-check"></i> Effective: <?=$effective?></span>
      <span><i class="fa-solid fa-gavel"></i> Governed by Kenyan Law</span>
      <span><i class="fa-solid fa-location-dot"></i> Nairobi, Kenya</span>
    </div>
  </div>

  <div class="legal-toc">
    <h3><i class="fa-solid fa-list" style="margin-right:6px"></i> Contents</h3>
    <ol>
      <li><a href="#t1">Acceptance of Terms</a></li>
      <li><a href="#t2">Description of Services</a></li>
      <li><a href="#t3">Account Registration</a></li>
      <li><a href="#t4">Patient Obligations</a></li>
      <li><a href="#t5">Healthcare Provider Obligations</a></li>
      <li><a href="#t6">Medical Disclaimer</a></li>
      <li><a href="#t7">Appointments &amp; Cancellations</a></li>
      <li><a href="#t8">Payments &amp; Fees</a></li>
      <li><a href="#t9">Intellectual Property</a></li>
      <li><a href="#t10">Prohibited Conduct</a></li>
      <li><a href="#t11">Limitation of Liability</a></li>
      <li><a href="#t12">Termination</a></li>
      <li><a href="#t13">Dispute Resolution</a></li>
      <li><a href="#t14">Governing Law</a></li>
    </ol>
  </div>

  <div class="legal-s" id="t1">
    <h2><span class="num">1</span> Acceptance of Terms</h2>
    <p>By accessing or using the Planeazzy platform ("Platform", "Service") operated by <strong>Planeazzy Health Technologies Ltd</strong>, a company incorporated in Kenya, you agree to be bound by these Terms of Service ("Terms").</p>
    <p>These Terms apply to all users of the Platform including patients, healthcare providers (hospitals, clinics, doctors), and visitors. If you are using the Platform on behalf of an organisation, you represent that you have authority to bind that organisation to these Terms.</p>
    <div class="legal-box warn">
      <div class="legal-box-ti"><i class="fa-solid fa-circle-exclamation"></i> Important</div>
      <p>If you do not agree to these Terms, you must not use Planeazzy. Continued use of the Platform after changes to these Terms constitutes acceptance of the updated Terms.</p>
    </div>
  </div>

  <div class="legal-s" id="t2">
    <h2><span class="num">2</span> Description of Services</h2>
    <p>Planeazzy provides a digital health services platform that enables:</p>
    <ul>
      <li>Online booking of appointments with registered hospitals, clinics, and specialist doctors across Kenya</li>
      <li>Appointment management, rescheduling, and cancellation</li>
      <li>SMS and email appointment reminders and notifications</li>
      <li>Telehealth consultation facilitation between patients and healthcare providers</li>
      <li>Hospital and clinic management dashboards for registered health facilities</li>
      <li>Emergency medical service requests and coordination</li>
      <li>Secure patient-provider messaging</li>
    </ul>
    <p>Planeazzy is a <strong>technology platform and not a healthcare provider</strong>. We facilitate connections between patients and licensed healthcare providers but do not provide medical advice, diagnosis, or treatment. See Section 6 (Medical Disclaimer).</p>
  </div>

  <div class="legal-s" id="t3">
    <h2><span class="num">3</span> Account Registration</h2>
    <h3>Eligibility</h3>
    <ul>
      <li>You must be at least 18 years old to create an account (or have parental/guardian consent if under 18)</li>
      <li>You must provide accurate, current, and complete registration information</li>
      <li>You must maintain the confidentiality of your account credentials</li>
      <li>You are responsible for all activity occurring under your account</li>
    </ul>
    <h3>Account Security</h3>
    <p>You agree to: (a) use a strong, unique password; (b) notify us immediately at <a href="mailto:security@planeazzy.com">security@planeazzy.com</a> if you suspect unauthorised access; (c) not share your credentials with any third party; and (d) log out of shared or public devices.</p>
    <h3>One Account Policy</h3>
    <p>Each individual may maintain one patient account. Healthcare facilities may create one account per registered facility. Multiple accounts created to circumvent bans or restrictions will be terminated.</p>
  </div>

  <div class="legal-s" id="t4">
    <h2><span class="num">4</span> Patient Obligations</h2>
    <p>As a patient using Planeazzy, you agree to:</p>
    <ul>
      <li>Provide accurate personal and health information when booking appointments</li>
      <li>Attend booked appointments or cancel/reschedule with reasonable notice (at least 2 hours in advance)</li>
      <li>Not misuse the Platform to book appointments you do not intend to attend</li>
      <li>Treat healthcare providers and platform staff with respect</li>
      <li>Not share other patients' information obtained through the Platform</li>
      <li>Comply with the healthcare provider's own policies and terms</li>
      <li>Use the emergency feature only for genuine medical emergencies</li>
    </ul>
  </div>

  <div class="legal-s" id="t5">
    <h2><span class="num">5</span> Healthcare Provider Obligations</h2>
    <p>Registered hospitals, clinics, and doctors ("Providers") agree to:</p>
    <ul>
      <li>Maintain valid registration and licensure with the Kenya Medical and Dentists Council (KMDC) or relevant regulatory body</li>
      <li>Provide accurate facility information, services, and availability</li>
      <li>Honour confirmed appointments except in documented emergencies</li>
      <li>Protect patient data in accordance with the Kenya Data Protection Act 2019 and the Health Act 2017</li>
      <li>Not use patient contact information obtained through Planeazzy for purposes other than providing the booked healthcare services</li>
      <li>Comply with Planeazzy's Provider Code of Conduct</li>
      <li>Notify Planeazzy within 24 hours of any data breach affecting patient records</li>
    </ul>
  </div>

  <div class="legal-s" id="t6">
    <h2><span class="num">6</span> Medical Disclaimer</h2>
    <div class="legal-box warn">
      <div class="legal-box-ti"><i class="fa-solid fa-triangle-exclamation"></i> Critical Disclaimer</div>
      <p><strong>Planeazzy is not a medical service provider.</strong> The Platform is a technology service for booking and managing healthcare appointments. Planeazzy does not:</p>
      <ul style="margin-top:8px">
        <li>Provide medical advice, diagnosis, or treatment</li>
        <li>Verify the clinical competence of individual healthcare providers</li>
        <li>Guarantee the quality of care provided by listed facilities</li>
        <li>Recommend specific treatments, medications, or procedures</li>
      </ul>
    </div>
    <p>If you are experiencing a medical emergency, call <strong>999</strong> or go to your nearest emergency room immediately. Do not use the Planeazzy booking system in a life-threatening emergency unless specifically using the Emergency Request feature.</p>
    <p>Always consult a qualified, licensed healthcare professional for medical advice. Never delay seeking professional medical advice because of something you have read or seen on the Planeazzy platform.</p>
  </div>

  <div class="legal-s" id="t7">
    <h2><span class="num">7</span> Appointments &amp; Cancellations</h2>
    <h3>Booking</h3>
    <p>A booking is confirmed when you receive a confirmation email and/or SMS. Confirmations are subject to availability and final acceptance by the healthcare provider.</p>
    <h3>Cancellation Policy</h3>
    <ul>
      <li>Patients may cancel or reschedule via their Planeazzy dashboard at no charge with at least 2 hours' notice</li>
      <li>Late cancellations or no-shows may attract cancellation fees set by the individual healthcare provider</li>
      <li>Healthcare providers may cancel or reschedule appointments due to emergencies or unforeseen circumstances — patients will be notified immediately</li>
      <li>Repeated no-shows (3 or more within 6 months) may result in temporary booking restrictions</li>
    </ul>
    <h3>Rescheduling</h3>
    <p>Both patients and healthcare providers may reschedule appointments. The rescheduling party must give reasonable notice and both parties will be notified by email and SMS.</p>
  </div>

  <div class="legal-s" id="t8">
    <h2><span class="num">8</span> Payments &amp; Fees</h2>
    <p>Planeazzy may charge service fees for certain features. Where fees apply:</p>
    <ul>
      <li>All fees are displayed in Kenyan Shillings (KES) inclusive of applicable taxes (VAT)</li>
      <li>Payment is processed through secure third-party processors (M-Pesa, Stripe) — Planeazzy does not store payment card details</li>
      <li>Refunds for cancellations are subject to the individual provider's refund policy</li>
      <li>Consultation fees and facility charges are set independently by healthcare providers and are not Planeazzy fees</li>
    </ul>
  </div>

  <div class="legal-s" id="t9">
    <h2><span class="num">9</span> Intellectual Property</h2>
    <p>All content on the Planeazzy Platform including the software, design, logos, trademarks, text, images, and documentation are the intellectual property of Planeazzy Health Technologies Ltd or its licensors and are protected by Kenyan and international copyright, trademark, and other intellectual property laws.</p>
    <p>You may not reproduce, distribute, modify, create derivative works of, publicly display, transmit, or otherwise exploit any content from the Platform without express written permission from Planeazzy.</p>
    <p>By uploading content (profile photos, messages) to the Platform, you grant Planeazzy a limited, non-exclusive licence to store and display that content solely for the purpose of providing the Service to you.</p>
  </div>

  <div class="legal-s" id="t10">
    <h2><span class="num">10</span> Prohibited Conduct</h2>
    <p>You must not:</p>
    <ul>
      <li>Use the Platform for any unlawful purpose or in violation of any Kenyan law or regulation</li>
      <li>Impersonate any person, entity, or healthcare provider</li>
      <li>Book appointments with false information or for the purpose of harassment</li>
      <li>Attempt to gain unauthorised access to any part of the Platform or its systems</li>
      <li>Engage in any form of scraping, crawling, or automated data extraction</li>
      <li>Upload malicious code, viruses, or any destructive content</li>
      <li>Harass, threaten, or abuse any user, healthcare provider, or Planeazzy staff member</li>
      <li>Misuse the Emergency Request feature for non-emergencies</li>
      <li>Circumvent or attempt to bypass security measures</li>
    </ul>
    <p>Violation may result in immediate account termination and may be reported to relevant authorities.</p>
  </div>

  <div class="legal-s" id="t11">
    <h2><span class="num">11</span> Limitation of Liability</h2>
    <p>To the maximum extent permitted by Kenyan law:</p>
    <ul>
      <li>Planeazzy's total liability for any claim arising from your use of the Platform shall not exceed KES 10,000 or the amount you paid us in the 3 months preceding the claim, whichever is greater</li>
      <li>Planeazzy is not liable for the quality, safety, or appropriateness of medical care provided by listed healthcare providers</li>
      <li>Planeazzy is not liable for any loss or damage arising from system downtime, errors, or unavailability</li>
      <li>We are not liable for indirect, incidental, special, consequential, or punitive damages</li>
    </ul>
    <p>Nothing in these Terms excludes liability for death or personal injury caused by Planeazzy's negligence, fraud, or any other liability that cannot be excluded under Kenyan law.</p>
  </div>

  <div class="legal-s" id="t12">
    <h2><span class="num">12</span> Termination</h2>
    <p>Either party may terminate the account at any time:</p>
    <ul>
      <li><strong>By you:</strong> Close your account in Settings → Account → Delete Account. Your data will be anonymised within 30 days and legal records retained for 7 years</li>
      <li><strong>By Planeazzy:</strong> We may suspend or terminate accounts that violate these Terms, without prior notice for serious violations, or with 30 days' notice otherwise</li>
    </ul>
    <p>Upon termination, your right to use the Platform ceases immediately. Provisions that by nature survive termination (including liability, dispute resolution, and governing law) will continue to apply.</p>
  </div>

  <div class="legal-s" id="t13">
    <h2><span class="num">13</span> Dispute Resolution</h2>
    <p>If you have a dispute with Planeazzy:</p>
    <ul>
      <li><strong>Step 1 — Contact Us:</strong> Email <a href="mailto:legal@planeazzy.com">legal@planeazzy.com</a>. Most issues can be resolved within 14 business days.</li>
      <li><strong>Step 2 — Mediation:</strong> If unresolved, disputes shall be referred to mediation under the Nairobi Centre for International Arbitration (NCIA) Mediation Rules.</li>
      <li><strong>Step 3 — Arbitration:</strong> Disputes unresolved by mediation shall be finally settled by arbitration in Nairobi under the NCIA Arbitration Rules. The language of arbitration is English. The arbitral award is final and binding.</li>
    </ul>
  </div>

  <div class="legal-s" id="t14">
    <h2><span class="num">14</span> Governing Law</h2>
    <p>These Terms are governed by and construed in accordance with the laws of the Republic of Kenya, including:</p>
    <ul>
      <li>The Kenya Data Protection Act, 2019 (No. 24 of 2019)</li>
      <li>The Health Act, 2017</li>
      <li>The Consumer Protection Act, 2012</li>
      <li>The Computer Misuse and Cybercrimes Act, 2018</li>
      <li>The Kenya Information and Communications Act (Cap 411A)</li>
    </ul>
    <p>For any questions about these Terms, contact: <a href="mailto:legal@planeazzy.com">legal@planeazzy.com</a> · Planeazzy Health Technologies Ltd, Nairobi, Kenya.</p>
  </div>
</div>

<?php include __DIR__.'/includes/footer.php'; ?>
