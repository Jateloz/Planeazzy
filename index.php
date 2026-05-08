<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/services/Security.php';
Security::startSession();
if (Security::isAuthenticated()) { header('Location: /patients/dashboard.php'); exit; }
$noSidebar = true;
$pageTitle  = 'Planeazzy — Your Direct Path to Better Healthcare';
$csrf       = Security::csrfToken();
include __DIR__ . '/includes/header.php';
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

<style>
:root {
  --ink: #040d1c;
  --ink-80: rgba(4,13,28,.80);
  --ink-50: rgba(4,13,28,.50);
  --body: #3a4a5e;
  --muted: #7289a3;
  --border: #e6ecf5;
  --border-mid: #ccd6e8;
  --bg: #f4f7fc;
  --bg-soft: #f9fbfe;
  --card: #ffffff;
  --p: #0052cc;
  --p-dark: #003d99;
  --p-light: #1a6de8;
  --p-10: rgba(0,82,204,.10);
  --p-06: rgba(0,82,204,.06);
  --teal: #00897b;
  --teal-light: #00a896;
  --green: #0a7c4b;
  --red: #c0392b;
  --amber: #b7600a;
  --violet: #5b21b6;
  --cyan: #0891b2;
  --sh-xs: 0 1px 3px rgba(0,0,0,.04), 0 2px 8px rgba(0,0,0,.03);
  --sh-sm: 0 2px 12px rgba(0,0,0,.06), 0 6px 24px rgba(0,0,0,.04);
  --sh-md: 0 8px 32px rgba(0,0,0,.08), 0 2px 8px rgba(0,0,0,.04);
  --sh-lg: 0 24px 64px rgba(0,0,0,.10), 0 4px 16px rgba(0,0,0,.05);
  --sh-xl: 0 40px 80px rgba(0,0,0,.14), 0 8px 24px rgba(0,0,0,.07);
  --r-sm: 8px;
  --r: 12px;
  --r-lg: 18px;
  --r-xl: 24px;
  --r-2xl: 32px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; -webkit-tap-highlight-color: transparent; }

body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  color: var(--body);
  background: #fff;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  overflow-x: hidden;
}

h1,h2,h3,h4,h5,h6 { font-family: 'Outfit', sans-serif; color: var(--ink); }

/* ── Utilities ── */
.pz-w   { max-width: 1280px; margin: 0 auto; padding: 0 28px; }
.pz-wm  { max-width: 980px;  margin: 0 auto; padding: 0 28px; }
.pz-ws  { max-width: 740px;  margin: 0 auto; padding: 0 28px; }
.sec    { padding: 88px 28px; }
.sec-sm { padding: 60px 28px; }
.text-c { text-align: center; }

.eyebrow {
  display: inline-flex; align-items: center; gap: 7px;
  font-family: 'Outfit', sans-serif;
  font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .14em;
  color: var(--p); padding: 5px 14px;
  background: var(--p-06);
  border: 1px solid rgba(0,82,204,.15);
  border-radius: 999px; margin-bottom: 16px;
}
.eyebrow i { font-size: 9px; }

.section-h {
  font-size: clamp(22px, 2.8vw, 34px);
  font-weight: 800; letter-spacing: -.04em;
  line-height: 1.10; margin-bottom: 12px;
}
.section-sub {
  font-size: 14px; line-height: 1.85;
  color: var(--muted); max-width: 520px;
}
.section-sub.c { margin: 0 auto; }

/* ── Grid helpers ── */
.g2 { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center; }
.g3 { display: grid; grid-template-columns: repeat(3,1fr); gap: 22px; }
.g4 { display: grid; grid-template-columns: repeat(4,1fr); gap: 18px; }

/* ── Buttons ── */
.btn {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 11px 26px; border-radius: var(--r);
  font-family: 'Outfit', sans-serif;
  font-size: 13px; font-weight: 700;
  cursor: pointer; border: none; text-decoration: none;
  transition: all .18s cubic-bezier(.22,.68,0,1.2);
  white-space: nowrap; position: relative; overflow: hidden;
}
.btn::after {
  content: ''; position: absolute; inset: 0;
  background: rgba(255,255,255,0);
  transition: background .18s;
}
.btn:hover::after { background: rgba(255,255,255,.08); }

.btn-primary {
  background: linear-gradient(135deg, var(--p-dark) 0%, var(--p) 60%, var(--p-light) 100%);
  color: #fff; box-shadow: 0 4px 18px rgba(0,82,204,.38), inset 0 1px 0 rgba(255,255,255,.15);
}
.btn-primary:hover { box-shadow: 0 6px 24px rgba(0,82,204,.50); }
.btn-primary:active { }

.btn-outline {
  background: transparent; color: var(--p);
  border: 1.5px solid rgba(0,82,204,.28);
}
.btn-outline:hover { background: var(--p-06); border-color: var(--p); }

.btn-ghost-white {
  background: rgba(255,255,255,.10);
  border: 1.5px solid rgba(255,255,255,.22);
  color: #fff; backdrop-filter: blur(10px);
}
.btn-ghost-white:hover { background: rgba(255,255,255,.20); }

.btn-white {
  background: #fff; color: var(--p);
  box-shadow: 0 4px 18px rgba(0,0,0,.14);
}
.btn-white:hover { box-shadow: 0 6px 24px rgba(0,0,0,.20); }

.btn-sm  { padding: 8px 18px; font-size: 12px; }
.btn-lg  { padding: 15px 36px; font-size: 15px; }
.btn-xl  { padding: 18px 44px; font-size: 15px; border-radius: var(--r-lg); }

/* ── Cards ── */
.card {
  background: var(--card);
  border: 1.5px solid transparent;
  border-radius: var(--r-lg);
  box-shadow: var(--sh-sm);
}
.card-h {
  transition: box-shadow .22s, background .20s;
  cursor: pointer;
}
.card-h:hover {
  box-shadow: 0 10px 36px rgba(0,82,204,.11);
  background: var(--bg-soft);
}

/* ── Icon box ── */
.ic {
  width: 46px; height: 46px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; flex-shrink: 0;
}
.ic-sm { width: 36px; height: 36px; border-radius: 9px; font-size: 14px; }
.ic-lg { width: 56px; height: 56px; border-radius: 14px; font-size: 22px; }

/* HERO */
.hero {
  position: relative; min-height: 100vh;
  display: flex; align-items: center; overflow: hidden;
}
.hero-bg { position: absolute; inset: 0; z-index: 0; }
.hero-bg img {
  width: 100%; height: 100%;
  object-fit: cover; object-position: center 20%;
  display: block;
}
/* Layered overlays for depth */
.hero-overlay {
  position: absolute; inset: 0;
  background:
    linear-gradient(108deg,
      rgba(2,8,24,.97)   0%,
      rgba(4,14,44,.93) 28%,
      rgba(0,30,80,.72) 50%,
      rgba(0,20,55,.28) 70%,
      rgba(0,10,30,.08) 100%),
    linear-gradient(to bottom,
      rgba(0,0,0,.22) 0%,
      transparent 25%,
      transparent 72%,
      rgba(0,0,0,.55) 100%);
}
.hero-grid {
  position: absolute; inset: 0;
  background-image:
    linear-gradient(rgba(0,82,204,.06) 1px, transparent 1px),
    linear-gradient(90deg, rgba(0,82,204,.06) 1px, transparent 1px);
  background-size: 60px 60px;
  mask-image: linear-gradient(135deg, rgba(0,0,0,.4) 0%, transparent 60%);
}
.hero-glow {
  position: absolute; top: -20%; left: -10%;
  width: 700px; height: 700px; border-radius: 50%;
  background: radial-gradient(circle, rgba(0,82,204,.18) 0%, transparent 65%);
  pointer-events: none;
}

.hero-inner {
  position: relative; z-index: 2;
  width: 100%;
  padding: 120px 80px 80px;
  display: flex; flex-direction: column;
  align-items: flex-start;
  min-height: 100vh; justify-content: center;
  max-width: 900px;
}

/* Badge */
.hero-badge {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 6px 16px; border-radius: 999px;
  background: rgba(255,255,255,.07);
  border: 1px solid rgba(255,255,255,.16);
  backdrop-filter: blur(12px);
  font-family: 'Outfit', sans-serif;
  font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .14em;
  color: rgba(180,218,255,.85); margin-bottom: 28px;
}
.live-dot {
  width: 7px; height: 7px; border-radius: 50%;
  background: #4ade80;
  box-shadow: 0 0 0 3px rgba(74,222,128,.22), 0 0 8px #4ade80;
  animation: livepulse 2.4s ease-in-out infinite;
}
@keyframes livepulse {
  0%,100%{ box-shadow: 0 0 0 3px rgba(74,222,128,.22), 0 0 8px #4ade80; }
  50%    { box-shadow: 0 0 0 6px rgba(74,222,128,.08), 0 0 14px #4ade80; }
}

/* Headline */
.hero-h1 {
  font-family: 'Outfit', sans-serif;
  font-size: clamp(30px, 5.2vw, 58px);
  font-weight: 900; letter-spacing: -.055em;
  line-height: 1.04; color: #fff;
  margin-bottom: 10px;
}
.hero-h1 .accent {
  display: block;
  background: linear-gradient(90deg, #eeeeeeff 0%, #ffffffff 40%, #fdfdfdff 80%);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
  background-clip: text;
}
.hero-lead {
  font-size: clamp(13px, 1.5vw, 15.5px);
  color: rgba(185,215,255,.75);
  line-height: 1.85; max-width: 500px;
  margin-bottom: 40px; font-weight: 400;
}

/* ── Search Card ── */
.search-wrap { width: 100%; max-width: 820px; margin-bottom: 32px; }
.search-card {
  background: #fff;
  border-radius: var(--r-xl);
  overflow: hidden;
  box-shadow: 0 40px 100px rgba(0,0,0,.50), 0 8px 30px rgba(0,0,0,.24);
  border: 1px solid rgba(255,255,255,.12);
}

/* Tab strip */
.search-tabs {
  display: flex; align-items: center; gap: 3px;
  padding: 12px 16px 0;
  background: #fafbfe;
  border-bottom: 1px solid var(--border);
  overflow-x: auto; -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}
.search-tabs::-webkit-scrollbar { display: none; }

.stab-label {
  font-family: 'Outfit', sans-serif;
  font-size: 9px; font-weight: 800;
  text-transform: uppercase; letter-spacing: .14em;
  color: #c0cad8; flex-shrink: 0;
  padding: 0 6px 8px;
}
.stab {
  display: flex; align-items: center; gap: 5px;
  padding: 7px 14px 9px; border-radius: 8px 8px 0 0;
  font-family: 'Outfit', sans-serif;
  font-size: 11.5px; font-weight: 700;
  background: none; border: none; border-bottom: 2px solid transparent;
  color: var(--muted); cursor: pointer;
  transition: all .16s; white-space: nowrap; flex-shrink: 0;
  margin-bottom: -1px;
}
.stab.active {
  color: var(--p); border-bottom-color: var(--p);
  background: rgba(0,82,204,.04);
}
.stab:not(.active):hover { color: var(--ink); background: rgba(0,0,0,.03); }
.stab i { font-size: 10px; }

/* Fields */
.search-fields {
  display: grid;
  grid-template-columns: 1fr auto 1fr auto 1fr auto 1fr;
  align-items: stretch;
}
.sf-sep { width: 1px; background: var(--border); margin: 12px 0; }
.sf {
  display: flex; flex-direction: column;
  padding: 14px 20px 12px;
  cursor: text; transition: background .15s;
}
.sf:hover { background: rgba(0,82,204,.03); }
.sf-label {
  display: flex; align-items: center; gap: 5px;
  font-family: 'Outfit', sans-serif;
  font-size: 9px; font-weight: 800;
  text-transform: uppercase; letter-spacing: .13em;
  color: var(--muted); margin-bottom: 5px;
}
.sf-label i { font-size: 9px; }
.sf select, .sf input {
  border: none; outline: none; background: transparent;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 13.5px; font-weight: 600;
  color: var(--ink); width: 100%; padding: 0; cursor: pointer;
}
.sf select { appearance: none; -webkit-appearance: none; }
.sf select option { color: var(--ink); font-weight: 500; }
.sf input::placeholder { color: #b8c8d8; font-weight: 400; }

/* Action row */
.search-actions {
  display: flex; align-items: center;
  gap: 8px; padding: 10px 14px;
  border-top: 1px solid var(--border);
  background: #f9fbfe;
  flex-wrap: wrap;
}
.search-btn-main {
  display: flex; align-items: center; gap: 8px;
  padding: 12px 30px;
  background: linear-gradient(135deg, var(--p-dark), var(--p));
  color: #fff; border: none; border-radius: var(--r);
  font-family: 'Outfit', sans-serif;
  font-size: 13px; font-weight: 800;
  cursor: pointer; transition: all .18s;
  box-shadow: 0 4px 16px rgba(0,82,204,.38);
  white-space: nowrap; flex-shrink: 0;
}
.search-btn-main:hover { box-shadow: 0 6px 22px rgba(0,82,204,.52); }

.search-btn-geo {
  display: flex; align-items: center; gap: 6px;
  padding: 10px 16px; border-radius: var(--r);
  background: transparent; border: 1.5px solid rgba(0,82,204,.22);
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 12px; font-weight: 700;
  color: var(--p); cursor: pointer; transition: all .15s; flex-shrink: 0;
}
.search-btn-geo:hover { background: var(--p-06); border-color: var(--p); }

.quick-pills { flex: 1; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.quick-label {
  font-size: 9px; color: #b8c8d8; font-weight: 800;
  white-space: nowrap; font-family: 'Outfit', sans-serif;
  letter-spacing: .08em; text-transform: uppercase;
}
.qpill {
  padding: 4px 11px; border-radius: 999px;
  font-size: 11px; font-weight: 600;
  background: var(--bg); border: 1.5px solid var(--border);
  color: #5a6e84; cursor: pointer; font-family: inherit;
  transition: all .14s; white-space: nowrap;
}
.qpill:hover { background: #fff; border-color: var(--p); color: var(--p); }

/* Social proof */
.hero-social { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.avs { display: flex; }
.avs img {
  width: 32px; height: 32px; border-radius: 50%;
  border: 2.5px solid rgba(255,255,255,.50);
  object-fit: cover; box-shadow: 0 2px 8px rgba(0,0,0,.22);
}
.avs img+img { margin-left: -10px; }
.trust-stars { display: flex; gap: 2px; margin-bottom: 3px; }
.trust-text { font-size: 12px; color: rgba(185,215,255,.65); line-height: 1.5; font-weight: 400; }

/* Scroll cue */
.scroll-cue {
  position: absolute; bottom: 28px; left: 50%;
  transform: translateX(-50%); z-index: 3;
  display: flex; flex-direction: column; align-items: center; gap: 6px;
  color: rgba(255,255,255,.28);
  font-family: 'Outfit', sans-serif;
  font-size: 8px; font-weight: 700;
  letter-spacing: .18em; text-transform: uppercase;
  pointer-events: none;
}
.scroll-line {
  width: 1px; height: 36px;
  background: linear-gradient(to bottom, rgba(255,255,255,.55), transparent);
  animation: sLine 2.2s ease-in-out infinite;
}
@keyframes sLine {
  0%  { opacity:0; transform: scaleY(0) translateY(-12px); transform-origin: top; }
  40% { opacity:1; transform: scaleY(1) translateY(0); transform-origin: top; }
  100%{ opacity:0; transform: scaleY(1) translateY(14px); transform-origin: top; }
}

/*STATS BAND*/
.stats-band {
  background: var(--ink);
  padding: 0;
  position: relative;
  overflow: hidden;
}
.stats-band::before {
  content: '';
  position: absolute; inset: 0;
  background: linear-gradient(135deg, rgba(0,82,204,.12) 0%, transparent 60%);
  pointer-events: none;
}
.stats-inner {
  max-width: 1280px; margin: 0 auto;
  display: grid; grid-template-columns: repeat(4,1fr);
  color: #ffffff;
}
.stat-item {
  padding: 44px 28px; text-align: center;
  color: #ffffff;
  border-right: 1px solid rgba(255,255,255,.06);
  position: relative; overflow: hidden;
  transition: background .22s;
}
.stat-item::after {
  content: '';
  position: absolute; bottom: 0; left: 50%; transform: translateX(-50%);
  width: 0; height: 2px;
  background: linear-gradient(90deg, var(--p), var(--teal));
  transition: width .35s;
}
.stat-item:hover::after { width: 60%; }
.stat-item:last-child { border-right: none; }
.stat-ic {
  width: 40px; height: 40px;
  background: rgba(255,255,255,.06); border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 16px; color: rgba(255, 255, 255, 1);
  margin: 0 auto 12px;
  border: 1px solid rgba(255,255,255,.08);
}
.stat-n {
  font-family: 'Outfit', sans-serif;
  font-size: clamp(22px, 3vw, 40px);
  font-weight: 900; letter-spacing: -.05em;
  color: #fff; line-height: 1;
  margin-bottom: 6px;
}
.stat-l {
  font-size: 11.5px; color: rgba(255, 255, 255, 1);
  font-weight: 500; letter-spacing: .02em;
}

/*ABOUT*/
.about-num {
  font-family: 'Outfit', sans-serif;
  font-size: 26px; font-weight: 900;
  color: var(--p); letter-spacing: -.04em; line-height: 1;
}
.about-lbl { font-size: 11px; color: var(--muted); margin-top: 4px; font-weight: 500; }
.about-box {
  padding: 20px 16px;
  background: var(--bg-soft); border-radius: var(--r);
  border: 1.5px solid transparent; text-align: center;
  transition: box-shadow .20s;
}
.about-box:hover {
  box-shadow: 0 4px 18px rgba(0,82,204,.09);
}
.chk { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 13px; }
.chk-icon {
  width: 20px; height: 20px; border-radius: 6px;
  background: rgba(0,82,204,.10); display: flex;
  align-items: center; justify-content: center;
  flex-shrink: 0; margin-top: 1px;
}
.chk-icon i { color: var(--p); font-size: 9px; }
.chk span { font-size: 13.5px; line-height: 1.65; color: var(--body); }

/*WHO USES*/
.user-card {
  display: flex; flex-direction: column;
  padding: 26px; gap: 16px;
}
.user-card h3 { font-size: 16px; font-weight: 800; margin-bottom: 6px; }
.user-card p  { font-size: 13px; color: var(--muted); line-height: 1.78; }
.user-card-tag {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 9px; font-weight: 800; font-family: 'Outfit', sans-serif;
  text-transform: uppercase; letter-spacing: .12em;
  padding: 3px 10px; border-radius: 999px;
  background: var(--bg); color: var(--muted);
  margin-bottom: 10px;
}

/*HOW IT WORKS*/
.steps {
  display: grid; grid-template-columns: repeat(4,1fr);
  gap: 22px; position: relative;
}
.steps::before {
  content: '';
  position: absolute; top: 28px;
  left: calc(12.5% + 22px); right: calc(12.5% + 22px);
  height: 1px; z-index: 0; pointer-events: none;
  background: linear-gradient(90deg,
    transparent, rgba(0,82,204,.15) 20%,
    rgba(0,82,204,.15) 80%, transparent);
}
.steps > * { position: relative; z-index: 1; }
.step-num {
  width: 46px; height: 46px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-family: 'Outfit', sans-serif;
  font-size: 17px; font-weight: 900;
  color: #fff; margin-bottom: 16px; flex-shrink: 0;
}
.step-card { padding: 26px; }
.step-card h3 { font-size: 14.5px; font-weight: 800; margin-bottom: 8px; }
.step-card p  { font-size: 12.5px; color: var(--muted); line-height: 1.75; }
.step-icon-box {
  width: 36px; height: 36px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; margin-bottom: 14px;
}

/*FEATURES*/
.feat {
  display: flex; gap: 16px; align-items: flex-start;
  padding: 20px; border-radius: var(--r);
  background: var(--bg-soft); border: 1.5px solid transparent;
  margin-bottom: 12px;
  transition: box-shadow .20s, background .20s;
}
.feat:hover {
  background: #fff;
  box-shadow: 0 4px 20px rgba(0,82,204,.08);
}
.feat-title { font-size: 14px; font-weight: 800; margin-bottom: 4px; color: var(--ink); }
.feat-desc  { font-size: 12.5px; color: var(--muted); line-height: 1.75; }

/*SPECIALTIES*/
.spec-grid { display: grid; grid-template-columns: repeat(6,1fr); gap: 14px; }
.spec-card {
  background: var(--card); border: 1.5px solid transparent;
  border-radius: var(--r-lg); padding: 20px 10px;
  text-align: center; cursor: pointer; font-family: inherit;
  display: flex; flex-direction: column; align-items: center; gap: 10px;
  box-shadow: var(--sh-xs);
  transition: box-shadow .22s, background .20s;
}
.spec-card:hover {
  background: var(--bg-soft);
  box-shadow: 0 6px 24px rgba(0,82,204,.10);
}
.spec-label { font-size: 11.5px; font-weight: 700; color: var(--ink); line-height: 1.3; font-family: 'Outfit', sans-serif; }

/*HOSPITALS*/
.hosp-card { padding: 0; overflow: hidden; }
.hosp-img-wrap { position: relative; overflow: hidden; }
.hosp-card img { width:100%; height: 200px; object-fit: cover; display: block; transition: opacity .3s ease; }
.hosp-card:hover img { opacity: .92; }
.hosp-body { padding: 22px; }
.hosp-body h3 { font-size: 16px; font-weight: 800; margin-bottom: 5px; }
.hosp-loc { display: flex; align-items: center; gap: 5px; color: var(--muted); font-size: 12px; margin-bottom: 14px; }
.chip {
  font-size: 10.5px; padding: 4px 10px; border-radius: 8px;
  background: var(--bg); color: var(--muted);
  font-weight: 700; border: 1px solid var(--border);
  font-family: 'Outfit', sans-serif;
}
.hosp-rating {
  position: absolute; top: 12px; right: 12px;
  background: rgba(255,255,255,.94);
  backdrop-filter: blur(8px);
  padding: 4px 11px; border-radius: 999px;
  font-family: 'Outfit', sans-serif;
  font-size: 12px; font-weight: 800;
  display: flex; align-items: center; gap: 4px;
  box-shadow: 0 2px 8px rgba(0,0,0,.12);
}
.hosp-badge {
  position: absolute; top: 12px; left: 12px;
  background: var(--p); color: #fff;
  padding: 3px 10px; border-radius: 999px;
  font-family: 'Outfit', sans-serif; font-size: 9px; font-weight: 800;
  text-transform: uppercase; letter-spacing: .1em;
}
.hosp-book {
  width: 100%; padding: 10px; border: 1.5px solid var(--p);
  color: var(--p); font-weight: 700; border-radius: var(--r);
  background: transparent; cursor: pointer;
  font-family: 'Outfit', sans-serif; font-size: 13px;
  transition: all .18s; margin-top: 14px;
  display: flex; align-items: center; justify-content: center; gap: 7px;
}
.hosp-book:hover { background: var(--p); color: #fff; box-shadow: 0 4px 14px rgba(0,82,204,.28); }

/*APPOINTMENTS BANNER*/
.appt-banner {
  background: linear-gradient(135deg, #020c20 0%, #031228 40%, #0a2048 80%, #031228 100%);
  border-radius: var(--r-2xl);
  padding: 60px 60px;
  position: relative; overflow: hidden;
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 40px; align-items: center;
}
.appt-banner::before {
  content: '';
  position: absolute; inset: 0;
  background-image:
    linear-gradient(rgba(0,82,204,.07) 1px, transparent 1px),
    linear-gradient(90deg, rgba(0,82,204,.07) 1px, transparent 1px);
  background-size: 44px 44px;
}
.appt-banner::after {
  content: '';
  position: absolute; top: -30%; right: -10%;
  width: 500px; height: 500px; border-radius: 50%;
  background: radial-gradient(circle, rgba(0,82,204,.20) 0%, transparent 65%);
  pointer-events: none;
}
.appt-content { position: relative; z-index: 2; }
.appt-tag {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 5px 14px; border-radius: 999px;
  background: rgba(0,82,204,.22); border: 1px solid rgba(0,82,204,.35);
  font-family: 'Outfit', sans-serif; font-size: 10px; font-weight: 800;
  text-transform: uppercase; letter-spacing: .13em;
  color: #93c5fd; margin-bottom: 20px;
}
.appt-h {
  font-family: 'Outfit', sans-serif;
  font-size: clamp(22px, 2.8vw, 34px);
  font-weight: 900; letter-spacing: -.045em;
  color: #fff; line-height: 1.08; margin-bottom: 14px;
}
.appt-h span {
  background: linear-gradient(90deg, #ffffffff, #ffffffff);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
  background-clip: text;
}
.appt-sub {
  font-size: 13.5px; color: rgba(185,215,255,.65);
  line-height: 1.8; margin-bottom: 28px; max-width: 440px;
}
.appt-steps {
  display: flex; flex-direction: column; gap: 14px;
  margin-bottom: 32px;
}
.appt-step {
  display: flex; align-items: flex-start; gap: 14px;
}
.appt-step-n {
  width: 28px; height: 28px; border-radius: 50%;
  background: rgba(0,82,204,.30); border: 1px solid rgba(0,82,204,.50);
  display: flex; align-items: center; justify-content: center;
  font-family: 'Outfit', sans-serif; font-size: 11px; font-weight: 900;
  color: #93c5fd; flex-shrink: 0;
}
.appt-step-text { font-size: 13px; color: rgba(185,215,255,.78); line-height: 1.6; padding-top: 4px; }
.appt-step-text strong { color: rgba(230,240,255,.92); font-weight: 700; }
.appt-btns { display: flex; gap: 12px; flex-wrap: wrap; }

/* Appointment type cards */
.appt-types { position: relative; z-index: 2; display: flex; flex-direction: column; gap: 14px; }
.appt-type-card {
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.10);
  border-radius: var(--r-lg); padding: 18px 20px;
  display: flex; align-items: center; gap: 16px;
  backdrop-filter: blur(8px);
  transition: background .20s, border-color .20s;
  cursor: pointer; text-decoration: none;
}
.appt-type-card:hover {
  background: rgba(255,255,255,.11);
  border-color: rgba(255,255,255,.22);
}
.appt-type-ic {
  width: 44px; height: 44px; border-radius: 11px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; flex-shrink: 0;
}
.appt-type-info { flex: 1; }
.appt-type-title {
  font-family: 'Outfit', sans-serif; font-size: 14px; font-weight: 800;
  color: #fff; margin-bottom: 2px;
}
.appt-type-desc {
  font-size: 11.5px; color: rgba(185,215,255,.56); line-height: 1.5;
}
.appt-type-arr {
  color: rgba(185,215,255,.35); font-size: 12px;
  transition: color .18s;
}
.appt-type-card:hover .appt-type-arr { color: rgba(185,215,255,.80); }

/*TESTIMONIALS*/
.testi {
  background: var(--bg-soft);
  border-radius: var(--r-lg);
  padding: 28px;
  border: 1.5px solid transparent;
  box-shadow: var(--sh-xs);
  transition: box-shadow .22s, background .20s;
}
.testi:hover {
  background: #fff;
  box-shadow: 0 8px 32px rgba(0,82,204,.09);
}
.testi-quote-mark {
  font-size: 44px; line-height: .8;
  color: var(--p); opacity: .18;
  font-family: Georgia, serif; margin-bottom: 8px;
  display: block;
}
.testi-q {
  font-size: 13.5px; color: var(--body);
  line-height: 1.85; margin-bottom: 22px;
}
.testi-author-av {
  width: 40px; height: 40px; border-radius: 50%;
  background: linear-gradient(135deg, var(--p-dark), var(--p));
  display: flex; align-items: center; justify-content: center;
  font-family: 'Outfit', sans-serif; font-size: 13px;
  font-weight: 900; color: #fff; flex-shrink: 0;
}
.testi-name { font-size: 13.5px; font-weight: 800; color: var(--ink); }
.testi-role { font-size: 11px; color: var(--muted); margin-top: 2px; }

/*FAQ*/
.faq {
  background: var(--card); border-radius: var(--r);
  border: 1.5px solid transparent; overflow: hidden;
  margin-bottom: 10px; box-shadow: var(--sh-xs);
  transition: box-shadow .20s, background .20s;
}
.faq:hover { background: var(--bg-soft); box-shadow: 0 4px 16px rgba(0,82,204,.07); }
.faq-btn {
  width: 100%; padding: 18px 22px;
  display: flex; align-items: center; justify-content: space-between;
  background: none; border: none; cursor: pointer;
  font-family: inherit; text-align: left; gap: 16px;
}
.faq-q {
  font-family: 'Outfit', sans-serif; font-size: 14px;
  font-weight: 700; color: var(--ink); flex: 1;
}
.faq-ic-wrap {
  width: 28px; height: 28px; border-radius: 7px;
  background: var(--bg); display: flex;
  align-items: center; justify-content: center;
  flex-shrink: 0; transition: background .18s;
}
.faq:hover .faq-ic-wrap { background: var(--p-06); }
.faq-ic { color: var(--p); font-size: 12px; transition: transform .24s cubic-bezier(.22,.68,0,1.2); }
.faq-ic.open { transform: rotate(45deg); }
.faq-body { display: none; padding: 0 22px 20px; }
.faq-body p {
  font-size: 13.5px; color: var(--body); line-height: 1.82;
  border-top: 1px solid var(--border); padding-top: 16px;
}

/*SECTION DIVIDER*/
.sec-rule {
  max-width: 1280px; margin: 0 auto;
  height: 1px; background: var(--border);
}

/*TRUST STRIP*/
.trust-strip {
  background: #fff;
  border-top: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
  padding: 18px 28px;
}
.trust-strip-inner {
  max-width: 1280px; margin: 0 auto;
  display: flex; align-items: center;
  justify-content: center; gap: 40px; flex-wrap: wrap;
}
.trust-item {
  display: flex; align-items: center; gap: 8px;
  font-size: 12px; font-weight: 600; color: var(--muted);
  font-family: 'Outfit', sans-serif;
}
.trust-item i { color: var(--p); font-size: 13px; }

/* ═══ RESPONSIVE ════════════════════════════ */
@media(max-width:1100px){
  .hero-inner { padding: 100px 48px 60px; }
  .spec-grid { grid-template-columns: repeat(4,1fr); }
  .appt-banner { padding: 48px 40px; }
}
@media(max-width:960px){
  .g2 { grid-template-columns: 1fr; gap: 36px; }
  .g4 { grid-template-columns: 1fr 1fr; }
  .spec-grid { grid-template-columns: repeat(4,1fr); }
  .appt-banner { grid-template-columns: 1fr; padding: 44px 32px; }
  .appt-banner::after { display: none; }
  .appt-types { flex-direction: row; flex-wrap: wrap; }
  .appt-type-card { flex: 1; min-width: 180px; }
  .steps { grid-template-columns: 1fr 1fr; }
  .step-connector { display: none; }
  .stats-inner { grid-template-columns: 1fr 1fr; }
  .stat-item { border-right: none; border-bottom: 1px solid rgba(255,255,255,.06); }
  .stat-item:nth-child(even) { border-bottom: none; }
}
@media(max-width:768px){
  .hero { min-height: 90vh; }
  .hero-inner { padding: 80px 24px 60px; max-width: 100%; }
  .hero-h1 { font-size: clamp(26px,7.5vw,42px); }
  .hero-lead { font-size: 13.5px; margin-bottom: 26px; }
  .search-fields { grid-template-columns: 1fr; }
  .sf-sep { display: none; }
  .sf { border-bottom: 1px solid var(--border); }
  .sf:last-of-type { border-bottom: none; }
  .search-actions { flex-direction: column; align-items: stretch; }
  .search-btn-main, .search-btn-geo { justify-content: center; }
  .quick-pills { display: none; }
  .g3 { grid-template-columns: 1fr; }
  .spec-grid { grid-template-columns: repeat(3,1fr); }
  .scroll-cue { display: none; }
  .sec { padding: 56px 18px; }
  .sec-sm { padding: 44px 18px; }
  .appt-banner { padding: 36px 24px; border-radius: var(--r-xl); }
  .trust-strip-inner { gap: 20px; }
}
@media(max-width:560px){
  .hero { min-height: 85vh; }
  .hero-inner { padding: 72px 18px 52px; }
  .g4 { grid-template-columns: 1fr; }
  .spec-grid { grid-template-columns: repeat(2,1fr); }
  .steps { grid-template-columns: 1fr; }
  .section-h { font-size: clamp(20px,5.5vw,26px); }
  .stats-inner { grid-template-columns: 1fr 1fr; }
  .appt-types { flex-direction: column; }
}
@media(max-width:380px){
  .spec-grid { grid-template-columns: repeat(2,1fr); }
  .stats-inner { grid-template-columns: 1fr; }
  .stat-item { border: none; }
}
</style>

<!--HERO-->
<section class="hero">
  <div class="hero-bg">
    <img src="/assets/images/twoDoctors.png" alt="Planeazzy Healthcare" loading="eager" fetchpriority="high">
    <div class="hero-overlay"></div>
    <div class="hero-grid"></div>
    <div class="hero-glow"></div>
  </div>

  <div class="hero-inner">

    <h1 class="hero-h1">
      Your Direct Path to
      <span class="accent">Better Healthcare</span>
    </h1>

    <p class="hero-lead">
      Find, compare and instantly book verified doctors, hospitals and emergency services across all 47 counties.
    </p>

    <!-- Search Card -->
    <div class="search-wrap">
      <div class="search-card">
        <!-- Tabs -->
        <div class="search-tabs">
          <span class="stab-label">Find:</span>
          <button class="stab active" onclick="setTab(this,'all')"><i class="fa-solid fa-grid-2"></i> All</button>
          <button class="stab" onclick="setTab(this,'hospital')"><i class="fa-solid fa-hospital"></i> Hospitals</button>
          <button class="stab" onclick="setTab(this,'doctor')"><i class="fa-solid fa-user-doctor"></i> Doctors</button>
          <button class="stab" onclick="setTab(this,'ambulance')"><i class="fa-solid fa-truck-medical"></i> Emergency</button>
          <input type="hidden" id="sType" value="all">
        </div>

        <!-- Fields -->
        <div class="search-fields">
          <div class="sf">
            <div class="sf-label"><i class="fa-solid fa-stethoscope" style="color:var(--p)"></i> Specialty</div>
            <select id="sSpecialty">
              <option value="">Any specialty</option>
              <?php foreach(['General Physician','Cardiologist','Pediatrician','Dentist','Gynecologist','Psychiatrist','Orthopedic Surgeon','Ophthalmologist','Dermatologist','ENT Specialist','Neurologist','Oncologist','Physiotherapist'] as $s): ?>
              <option value="<?=htmlspecialchars($s)?>"><?=htmlspecialchars($s)?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="sf-sep"></div>

          <div class="sf">
            <div class="sf-label"><i class="fa-solid fa-location-dot" style="color:var(--p)"></i> County</div>
            <select id="sCounty">
              <option value="">Any county</option>
              <?php foreach(['Nairobi','Mombasa','Kisumu','Nakuru','Eldoret','Thika','Kitale','Garissa','Kakamega','Nyeri','Meru','Embu','Machakos','Kisii','Kericho','Bungoma','Malindi','Lamu','Isiolo'] as $c): ?>
              <option value="<?=htmlspecialchars($c)?>"><?=htmlspecialchars($c)?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="sf-sep"></div>

          <div class="sf">
            <div class="sf-label"><i class="fa-solid fa-shield-heart" style="color:var(--teal)"></i> Insurance</div>
            <select id="sInsurance">
              <option value="">Any insurance</option>
              <?php foreach(['NHIF'=>'nhif','Jubilee Health'=>'jubilee','AXA Mansard'=>'axa','AAR Healthcare'=>'aar','Britam'=>'britam','CIC'=>'cic','Equity Afia'=>'equity'] as $n=>$v): ?>
              <option value="<?=$v?>"><?=$n?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="sf-sep"></div>

          <div class="sf">
            <div class="sf-label"><i class="fa-solid fa-magnifying-glass" style="color:var(--muted)"></i> Name</div>
            <input id="sQuery" type="text" placeholder="Doctor or hospital…"
              onkeydown="if(event.key==='Enter')doSearch()">
          </div>
        </div>

        <!-- Actions -->
        <div class="search-actions">
          <div class="quick-pills">
            <span class="quick-label">Quick:</span>
            <?php foreach(['Cardiology','Pediatrics','Dentist','GP','Gynecology'] as $sp):?>
            <button class="qpill" onclick="document.getElementById('sSpecialty').value='<?=$sp?>';doSearch()"><?=$sp?></button>
            <?php endforeach;?>
          </div>
          <button class="search-btn-geo" onclick="doGeoSearch()">
            <i class="fa-solid fa-location-crosshairs"></i> Near Me
          </button>
          <button class="search-btn-main" onclick="doSearch()">
            <i class="fa-solid fa-magnifying-glass"></i> Search
          </button>
        </div>
      </div>
    </div>

    <!-- Social proof -->
    <div class="hero-social">
      <div class="avs">
        <?php foreach([
          'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?w=80&q=70',
          'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?w=80&q=70',
          'https://images.unsplash.com/photo-1594824476967-48c8b964273f?w=80&q=70',
          'https://images.unsplash.com/photo-1622253694238-3b22139576c6?w=80&q=70',
        ] as $s): ?>
        <img src="<?=$s?>" alt="">
        <?php endforeach; ?>
      </div>
      <div>
        <div class="trust-stars">
          <?php for($i=0;$i<5;$i++) echo '<i class="fa-solid fa-star" style="color:#fbbf24;font-size:11px"></i>'; ?>
        </div>
        <div class="trust-text">Trusted by patients all over</div>
      </div>
    </div>

  </div>

  <div class="scroll-cue">
    <div class="scroll-line"></div>
    <span>Scroll</span>
  </div>
</section>

<!--STATS-->
<section class="stats-band">
  <div class="stats-inner">
    <?php foreach([
      ['','Verified Providers','fa-hospital'],
      ['','Several Patients Served','fa-users'],
      ['','Many Counties Covered','fa-map-location-dot'],
      ['','Best Patient Satisfaction','fa-star'],
    ] as [$n,$l,$ic]): ?>
    <div class="stat-item">
      <div class="stat-ic"><i class="fa-solid <?=$ic?>"></i></div>
      <div class="stat-n"><?=$n?></div>
      <div class="stat-l"><?=$l?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ABOUT-->
<section class="sec" style="background:#fff">
  <div class="pz-w">
    <div style="text-align:center;margin-bottom:56px">
      <h2 class="section-h">What is Planeazzy?</h2>
      <p class="section-sub c">
        A single digital platform connecting patients with verified doctors, hospitals, clinics and emergency services — instantly and transparently.
      </p>
    </div>

    <div class="g2" style="align-items:start;margin-bottom:56px">
      <div>
        <h3 style="font-size:18px;font-weight:800;margin-bottom:12px;letter-spacing:-.03em">Built to Solve a Real Problem</h3>
        <p style="font-size:13.5px;line-height:1.85;color:var(--body);margin-bottom:16px">
          Before Planeazzy, booking a doctor meant long phone calls, unclear pricing, physical queues, and no way to verify credentials. Insurance documents had to be carried to every appointment.
        </p>
        <p style="font-size:13.5px;line-height:1.85;color:var(--body);margin-bottom:28px">
          We changed that. Any patient can now find verified providers, book in under two minutes, share insurance digitally, consult by video, and summon an ambulance with a single tap.
        </p>
        <?php foreach([
          'Every provider is verified and licensed before listing.',
          'Real patient reviews after every appointment — no fake ratings.',
          'See the consultation fee before confirming — no surprises.',
          'Health data protected under the Data Protection Act 2019.',
        ] as $chk): ?>
        <div class="chk">
          <div class="chk-icon"><i class="fa-solid fa-check"></i></div>
          <span><?=$chk?></span>
        </div>
        <?php endforeach; ?>
      </div>
        <img src="assets/images/femaleDoctor.png" alt="Doctor" loading="lazy"
          style="border-radius:var(--r-xl);box-shadow:var(--sh-lg);width:100%;object-fit:cover;aspect-ratio:16/10">
      </div>
    </div>
  </div>
</section>

<!-- WHO USES-->
<section class="sec" style="background:var(--bg)">
  <div class="pz-w">
    <div style="text-align:center;margin-bottom:48px">
      <h2 class="section-h">One Platform, Everyone in Healthcare</h2>
      <p class="section-sub c">Patients, doctors, hospitals and emergency services — all in one ecosystem.</p>
    </div>
    <div class="g4">
      <?php foreach([
        ['fa-user','#0052cc','Patients','Search verified providers, book instantly, share insurance digitally, and consult by HD video.','/patients/register.php','Get Started Free'],
        ['fa-stethoscope','#00897b','Doctors','Manage your schedule, run secure HD consultations, and grow your patient base digitally.','/doctors/onboarding/register.php','Register as Doctor'],
        ['fa-house-medical','#0a7c4b','Hospitals','Digitise bookings, manage your roster, auto-receive insurance docs, and access full analytics.','/hospital/onboarding/join.php','Register Facility'],
        ['fa-truck-medical','#c0392b','Ambulance','Receive live GPS SOS alerts, dispatch the nearest unit in real time, coordinate responses.','#','Register Service'],
      ] as [$ic,$col,$title,$desc,$link,$cta]): ?>
      <div class="card card-h user-card">
        <div>
          <div class="ic ic-lg" style="background:<?=$col?>12;color:<?=$col?>;border:1.5px solid <?=$col?>22;margin-bottom:16px">
            <i class="fa-solid <?=$ic?>"></i>
          </div>
          <h3><?=$title?></h3>
          <p><?=$desc?></p>
        </div>
        <a href="<?=$link?>" class="btn btn-sm" style="background:<?=$col?>10;color:<?=$col?>;border:1.5px solid <?=$col?>22;margin-top:auto;width:fit-content">
          <?=$cta?> <i class="fa-solid fa-arrow-right" style="font-size:10px"></i>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════
     5 · HOW IT WORKS
═══════════════════════════════════════════ -->
<section class="sec" style="background:#fff">
  <div class="pz-w">
    <div style="text-align:center;margin-bottom:48px">
      <h2 class="section-h">Book Healthcare in 4 Steps</h2>
      <p class="section-sub c">The entire process takes under 2 minutes.</p>
    </div>
    <div class="steps">
      <?php foreach([
        ['1','#0052cc','fa-magnifying-glass','Search','Search by specialty, location or condition. Filter by insurance and visit type.'],
        ['2','#00897b','fa-calendar-check','Choose & Book','Compare profiles and real reviews. Book with one tap.'],
        ['3','#0a7c4b','fa-envelope-circle-check','Get Confirmed','Instant confirmation. Reminder 24 hours before your appointment.'],
        ['4','#5b21b6','fa-location-arrow','Visit or Video','Attend in person or join via secure HD video from anywhere.'],
      ] as [$num,$col,$ic,$title,$desc]): ?>
      <div class="card step-card">
        <div class="step-num" style="background:<?=$col?>;box-shadow:0 4px 16px <?=$col?>44"><?=$num?></div>
        <div class="step-icon-box" style="background:<?=$col?>12;color:<?=$col?>">
          <i class="fa-solid <?=$ic?>"></i>
        </div>
        <h3><?=$title?></h3>
        <p><?=$desc?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════
     6 · FEATURES
═══════════════════════════════════════════ -->
<section class="sec" style="background:var(--bg)">
  <div class="pz-w g2" style="gap:64px">
    <div style="position:relative">
      <img src="assets/images/dashboard.png" alt="Planeazzy Dashboard" loading="lazy"
        style="border-radius:var(--r-xl);box-shadow:var(--sh-xl);width:100%;object-fit:cover;aspect-ratio:4/3">
    </div>
    <div>
      <h2 class="section-h" style="margin-bottom:24px">Everything You Need, One Platform</h2>
      <?php foreach([
        ['fa-shield-heart','#0052cc','Insurance Management','Upload your insurance card once — it auto-shares with providers at every booking.'],
        ['fa-video','#00897b','HD Telehealth Video','Consult any doctor from home via secure HD video. Get diagnosed, receive a prescription, follow-up — without travelling.'],
        ['fa-truck-medical','#c0392b','Emergency SOS Dispatch','One tap activates emergency mode. GPS location sent instantly to the nearest available unit.'],
        ['fa-chart-line','#5b21b6','Appointments Bookings','All your appointments and results in one secure personal health profile.'],
      ] as [$ic,$col,$title,$desc]): ?>
      <div class="feat">
        <div class="ic" style="background:<?=$col?>12;color:<?=$col?>;border:1.5px solid <?=$col?>18">
          <i class="fa-solid <?=$ic?>"></i>
        </div>
        <div>
          <div class="feat-title"><?=$title?></div>
          <div class="feat-desc"><?=$desc?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════
     7 · SPECIALTIES
═══════════════════════════════════════════ -->
<section class="sec" style="background:#fff">
  <div class="pz-w">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:38px;flex-wrap:wrap;gap:14px">
      <div>
        <h2 class="section-h" style="margin-bottom:0">Browse by Specialty</h2>
      </div>
      <a href="/patients/search.php" class="btn btn-outline btn-sm">
        View All <i class="fa-solid fa-arrow-right" style="font-size:10px"></i>
      </a>
    </div>
    <div class="spec-grid">
      <?php foreach([
        ['fa-stethoscope','#0052cc','General Physician'],
        ['fa-tooth','#00897b','Dentist'],
        ['fa-baby','#5b21b6','Pediatrics'],
        ['fa-venus','#be185d','Gynecology'],
        ['fa-heart-pulse','#c0392b','Cardiology'],
        ['fa-brain','#0a7c4b','Psychiatry'],
        ['fa-eye','#b7600a','Ophthalmology'],
        ['fa-bone','#0891b2','Orthopedics'],
        ['fa-lungs','#1d4ed8','Pulmonology'],
        ['fa-syringe','#7c3aed','Oncology'],
        ['fa-person-walking','#00897b','Physiotherapy'],
        ['fa-flask','#92400e','Pathology'],
      ] as [$ic,$col,$label]): ?>
      <button class="spec-card" onclick="location.href='/patients/search.php?q=<?=urlencode($label)?>'">
        <div class="ic" style="background:<?=$col?>12;color:<?=$col?>">
          <i class="fa-solid <?=$ic?>" style="font-size:18px"></i>
        </div>
        <span class="spec-label"><?=$label?></span>
      </button>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════
     8 · TOP-RATED FACILITIES & DOCTORS (live DB)
═══════════════════════════════════════════ -->
<?php
/* ── Fetch up to 6 hospitals + 6 independent doctors, shuffle and take 3 ── */
$featured_cards = [];

try {
  /* DB connection — uses the same PDO instance your config provides.
     If $pdo is not set by config, create it here as a fallback.       */
  if (!isset($pdo)) {
    $pdo = new PDO(
      'mysql:host=' . (DB_HOST ?? '127.0.0.1') . ';dbname=' . (DB_NAME ?? 'planeazzy_db') . ';charset=utf8mb4',
      DB_USER ?? 'root',
      DB_PASS ?? '',
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
  }

  /* ── Hospitals (approved, active) ── */
  $hosp_rows = $pdo->query("
    SELECT
      id,
      facility_name   AS display_name,
      facility_type,
      county,
      sub_county,
      address,
      logo_path,
      services,
      emergency_24h,
      beds,
      'hospital'      AS card_type
    FROM hospital_providers
    WHERE status = 'approved'
      AND is_active = 1
    ORDER BY RAND()
    LIMIT 6
  ")->fetchAll();

  foreach ($hosp_rows as $h) {
    /* Parse JSON services array → chip labels */
    $raw_services = json_decode($h['services'] ?? '[]', true) ?: [];
    $chips = array_slice(array_map('ucfirst', $raw_services), 0, 3);
    if (empty($chips)) {
      $chips = [ucfirst($h['facility_type'] ?? 'Facility')];
    }
    if ($h['emergency_24h']) $chips[] = '24/7 Emergency';

    /* Location string */
    $parts = array_filter([$h['sub_county'], $h['county']]);
    $location = implode(', ', $parts) ?: ($h['address'] ?: 'Kenya');

    /* Logo or placeholder */
    $logo = $h['logo_path']
      ? htmlspecialchars($h['logo_path'])
      : 'https://images.unsplash.com/photo-1586773860418-d37222d8fce3?w=600&q=75';

    $featured_cards[] = [
      'type'      => 'hospital',
      'id'        => $h['id'],
      'name'      => $h['display_name'],
      'subtitle'  => $location,
      'badge'     => ucfirst($h['facility_type'] ?? 'Hospital'),
      'chips'     => $chips,
      'img'       => $logo,
      'rating'    => null,   /* hospitals table has no rating col */
      'extra'     => $h['beds'] > 0 ? $h['beds'] . ' beds' : null,
      'book_url'  => '/patients/book.php?hospital_id=' . $h['id'] . '&type=hospital',
      'icon'      => 'fa-hospital',
      'icon_col'  => '#0052cc',
    ];
  }

  /* ── Independent doctors (active, verified) ── */
  $doc_rows = $pdo->query("
    SELECT
      id,
      CONCAT(first_name,' ',last_name) AS display_name,
      specialty,
      city,
      county,
      consult_fee,
      consultation_fee,
      avatar_path,
      rating,
      review_count,
      accepts_tele,
      years_exp,
      languages,
      'doctor'    AS card_type
    FROM doctors
    WHERE status = 'active'
      AND is_active = 1
      AND is_verified = 1
    ORDER BY RAND()
    LIMIT 6
  ")->fetchAll();

  foreach ($doc_rows as $d) {
    $fee = $d['consultation_fee'] ?? $d['consult_fee'] ?? 0;
    $parts = array_filter([$d['city'], $d['county']]);
    $location = implode(', ', $parts) ?: 'Kenya';

    $chips = array_filter([
      $d['specialty'] ?: null,
      $d['accepts_tele'] ? 'Telehealth' : null,
      $d['years_exp'] > 0 ? $d['years_exp'] . ' yrs exp' : null,
    ]);
    $chips = array_values(array_slice($chips, 0, 3));
    if (empty($chips)) $chips = ['General'];

    $avatar = $d['avatar_path']
      ? htmlspecialchars($d['avatar_path'])
      : 'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?w=600&q=75';

    $featured_cards[] = [
      'type'      => 'doctor',
      'id'        => $d['id'],
      'name'      => 'Dr. ' . $d['display_name'],
      'subtitle'  => $location,
      'badge'     => $d['specialty'] ?: 'Doctor',
      'chips'     => $chips,
      'img'       => $avatar,
      'rating'    => $d['rating'] > 0 ? number_format($d['rating'], 1) : null,
      'extra'     => $fee > 0 ? 'KES ' . number_format($fee, 0) . ' / visit' : null,
      'book_url'  => '/patients/book.php?doctor_id=' . $d['id'] . '&type=doctor',
      'icon'      => 'fa-user-doctor',
      'icon_col'  => '#00897b',
    ];
  }

  /* Shuffle the combined pool and take exactly 3 */
  shuffle($featured_cards);
  $featured_cards = array_slice($featured_cards, 0, 3);

} catch (Exception $e) {
  /* Graceful fallback — show nothing rather than a PHP error on homepage */
  $featured_cards = [];
}

/* ── Fallback stock images per card position ── */
$fallback_imgs = [
  'https://images.unsplash.com/photo-1586773860418-d37222d8fce3?w=600&q=75',
  'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=600&q=75',
  'https://images.unsplash.com/photo-1551076805-e1869033e561?w=600&q=75',
];
?>
<section class="sec" style="background:var(--bg)">
  <div class="pz-w">
    <div style="text-align:center;margin-bottom:38px">
      <h2 class="section-h">Top-Rated Facilities &amp; Doctors</h2>
      <p class="section-sub c">Randomly selected verified hospitals and doctors — refreshed every visit.</p>
    </div>

    <?php if (empty($featured_cards)): ?>
    <!-- Empty state -->
    <div style="text-align:center;padding:48px 24px;background:var(--bg-soft);border-radius:var(--r-xl)">
      <i class="fa-solid fa-hospital" style="font-size:36px;color:var(--border-mid);margin-bottom:14px;display:block"></i>
      <p style="color:var(--muted);font-size:14px">No verified providers yet. <a href="/hospital/onboarding/join.php" style="color:var(--p)">Register your facility →</a></p>
    </div>
    <?php else: ?>

    <div class="g3">
      <?php foreach ($featured_cards as $idx => $card):
        /* Pick a stock image as fallback if path looks like a storage path that may not exist yet */
        $img_src = (str_starts_with($card['img'], 'http'))
          ? $card['img']
          : (file_exists($_SERVER['DOCUMENT_ROOT'] . $card['img'])
              ? htmlspecialchars($card['img'])
              : $fallback_imgs[$idx % 3]);

        $is_doctor = $card['type'] === 'doctor';
      ?>
      <div class="card card-h hosp-card">

        <!-- Image / Avatar -->
        <div class="hosp-img-wrap">
          <?php if ($is_doctor): ?>
          <!-- Doctor: tinted background + centred avatar -->
          <div style="height:200px;background:linear-gradient(135deg,#e8f0fc,#f0f7ff);display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden">
            <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(0,82,204,.06),rgba(0,137,123,.06))"></div>
            <?php if (str_starts_with($card['img'], '/storage') && file_exists($_SERVER['DOCUMENT_ROOT'] . $card['img'])): ?>
              <img src="<?=htmlspecialchars($card['img'])?>" alt="<?=htmlspecialchars($card['name'])?>"
                style="width:110px;height:110px;border-radius:50%;object-fit:cover;border:3px solid #fff;box-shadow:0 4px 16px rgba(0,0,0,.12);position:relative;z-index:1" loading="lazy">
            <?php else: ?>
              <div style="width:100px;height:100px;border-radius:50%;background:linear-gradient(135deg,var(--p-dark),var(--p));display:flex;align-items:center;justify-content:center;font-family:'Outfit',sans-serif;font-size:32px;font-weight:900;color:#fff;box-shadow:0 4px 16px rgba(0,82,204,.30);position:relative;z-index:1">
                <?=strtoupper(substr(explode(' ', ltrim($card['name'], 'Dr. '))[0], 0, 1) . (substr(explode(' ', $card['name'])[-1], 0, 1)))?>
              </div>
            <?php endif; ?>
          </div>
          <?php else: ?>
          <!-- Hospital: full cover image -->
          <?php
            /* If logo is a storage path check existence */
            $show_img = str_starts_with($card['img'], 'http')
              ? $card['img']
              : (file_exists($_SERVER['DOCUMENT_ROOT'] . $card['img'])
                  ? htmlspecialchars($card['img'])
                  : $fallback_imgs[$idx % 3]);
          ?>
          <img src="<?=$show_img?>" alt="<?=htmlspecialchars($card['name'])?>" loading="lazy"
            style="width:100%;height:200px;object-fit:cover;display:block">
          <?php endif; ?>

          <!-- Rating badge -->
          <?php if ($card['rating']): ?>
          <div class="hosp-rating">
            <i class="fa-solid fa-star" style="color:#f59e0b;font-size:10px"></i>
            <?=htmlspecialchars($card['rating'])?>
          </div>
          <?php endif; ?>

          <!-- Type badge -->
          <div class="hosp-badge" style="background:<?=$card['icon_col']?>">
            <i class="fa-solid <?=$card['icon']?>" style="margin-right:4px;font-size:8px"></i>
            <?=htmlspecialchars($card['badge'])?>
          </div>
        </div>

        <!-- Body -->
        <div class="hosp-body">
          <h3><?=htmlspecialchars($card['name'])?></h3>
          <div class="hosp-loc">
            <i class="fa-solid fa-location-dot" style="font-size:11px;color:var(--p)"></i>
            <?=htmlspecialchars($card['subtitle'])?>
          </div>

          <!-- Chips -->
          <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:4px">
            <?php foreach (array_slice($card['chips'], 0, 3) as $chip): ?>
            <span class="chip"><?=htmlspecialchars($chip)?></span>
            <?php endforeach; ?>
          </div>

          <!-- Fee / beds extra info -->
          <?php if ($card['extra']): ?>
          <div style="margin-top:8px;font-size:11.5px;color:var(--p);font-weight:700;font-family:'Outfit',sans-serif">
            <i class="fa-solid <?=$is_doctor ? 'fa-tag' : 'fa-bed'?>" style="font-size:10px;margin-right:4px"></i>
            <?=htmlspecialchars($card['extra'])?>
          </div>
          <?php endif; ?>

          <!-- Book button -->
          <button class="hosp-book"
            onclick="location.href='<?=htmlspecialchars($card['book_url'])?>'">
            <i class="fa-solid fa-calendar-check" style="font-size:11px"></i>
            Book <?=$is_doctor ? 'Appointment' : 'Now'?>
          </button>
        </div>

      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div style="display:flex;justify-content:center;gap:12px;margin-top:32px;flex-wrap:wrap">
      <a href="/patients/search.php?type=hospital" class="btn btn-primary btn-lg">
        <i class="fa-solid fa-hospital"></i>
        View All Hospitals
        <i class="fa-solid fa-arrow-right" style="font-size:11px"></i>
      </a>
      <a href="/patients/search.php?type=doctor" class="btn btn-outline btn-lg">
        <i class="fa-solid fa-user-doctor"></i>
        Browse All Doctors
        <i class="fa-solid fa-arrow-right" style="font-size:11px"></i>
      </a>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════
     9 · APPOINTMENTS (replaces language section)
═══════════════════════════════════════════ -->
<section class="sec" style="background:#fff">
  <div class="pz-w">
    <div class="appt-banner">
      <div class="appt-content">
        <h2 class="appt-h">
          Your Next Appointment<br>is <span>2 Minutes Away</span>
        </h2>
        <p class="appt-sub">
          No phone queues. No paperwork. Choose your doctor, pick a slot that suits you, and get an instant confirmation — from anywhere in Kenya.
        </p>
        <div class="appt-steps">
          <?php foreach([
            ['Search', 'Find verified doctors and hospitals by specialty, county, or insurance.'],
            ['Select a Slot', 'View real-time availability and choose a time that works for you.'],
            ['Confirm & Attend', 'Receive instant confirmation. Visit in person or consult by HD video.'],
          ] as $i => [$title,$desc]): ?>
          <div class="appt-step">
            <div class="appt-step-n"><?=$i+1?></div>
            <div class="appt-step-text"><strong><?=$title?>:</strong> <?=$desc?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="appt-btns">
          <a href="/patients/register.php" class="btn btn-white btn-lg">
            <i class="fa-solid fa-calendar-plus"></i> Book Now — It's Free
          </a>
          <a href="/patients/search.php" class="btn btn-ghost-white">
            Browse Providers <i class="fa-solid fa-arrow-right" style="font-size:11px"></i>
          </a>
        </div>
      </div>

      <div class="appt-types">
        <?php foreach([
          ['fa-user-doctor','rgba(0,82,204,.22)','rgba(0,82,204,.45)','#93c5fd','In-Person Visit','/patients/search.php?visit=in-person','Book a face-to-face consultation at a clinic or hospital near you.'],
          ['fa-video','rgba(0,137,123,.20)','rgba(0,137,123,.40)','#5eead4','Telehealth Video Call','/patients/search.php?visit=telehealth','Consult any doctor online by secure HD video — no travel required.'],
          ['fa-truck-medical','rgba(192,57,43,.22)','rgba(192,57,43,.42)','#fca5a5','Emergency Dispatch','/patients/search.php?type=ambulance','Activate one-tap SOS — GPS dispatches the nearest ambulance unit instantly.'],
          ['fa-file-medical','rgba(91,33,182,.20)','rgba(91,33,182,.40)','#c4b5fd','Follow-Up & Referral','/patients/search.php?visit=followup','Schedule follow-ups or specialist referrals directly from your health profile.'],
        ] as [$ic,$bg,$bdr,$col,$title,$link,$desc]): ?>
        <a href="<?=$link?>" class="appt-type-card">
          <div class="appt-type-ic" style="background:<?=$bg?>;border:1px solid <?=$bdr?>">
            <i class="fa-solid <?=$ic?>" style="color:<?=$col?>"></i>
          </div>
          <div class="appt-type-info">
            <div class="appt-type-title"><?=$title?></div>
            <div class="appt-type-desc"><?=$desc?></div>
          </div>
          <i class="fa-solid fa-chevron-right appt-type-arr"></i>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════
     10 · TESTIMONIALS
═══════════════════════════════════════════ -->
<section class="sec" style="background:var(--bg)">
  <div class="pz-w">
    <div style="text-align:center;margin-bottom:40px">
      <h2 class="section-h">Loved Across the Region</h2>
      <p class="section-sub c">Real experiences from patients, doctors and emergency dispatchers.</p>
    </div>
    <div class="g3">
      <?php foreach([
        ['AW','Amony Wanjiku','Patient, Nairobi','"I found a cardiologist and booked within 5 minutes from my phone. The telehealth session saved me a 2-hour journey. Absolutely brilliant."'],
        ['DK','Dr. David Opyacha','Cardiologist, KNH','"Planeazzy has completely transformed my practice. Scheduling is effortless, telehealth tools are world-class, and I reach patients I could never visit in person."'],
        ['MW','Mercy Wainaina','Ambulance Dispatcher, Mombasa','"The emergency GPS dispatch is incredible. We reached a patient in Likoni within 4 minutes using the live location system. Planeazzy saves lives every day."'],
      ] as [$init,$name,$role,$quote]): ?>
      <div class="testi">
        <span class="testi-quote-mark">"</span>
        <div style="display:flex;gap:3px;margin-bottom:14px">
          <?php for($i=0;$i<5;$i++) echo '<i class="fa-solid fa-star" style="color:#f59e0b;font-size:12px"></i>'; ?>
        </div>
        <p class="testi-q"><?=$quote?></p>
        <div style="display:flex;align-items:center;gap:12px">
          <div class="testi-author-av"><?=$init?></div>
          <div>
            <div class="testi-name"><?=$name?></div>
            <div class="testi-role"><?=$role?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════
     11 · FAQ
═══════════════════════════════════════════ -->
<section class="sec" style="background:#fff">
  <div class="pz-ws">
    <div style="text-align:center;margin-bottom:40px">
      <h2 class="section-h">Everything You Need to Know</h2>
      <p class="section-sub c">Common questions answered clearly.</p>
    </div>
    <?php foreach([
      ['Is Planeazzy free for patients?','Yes — searching, browsing and booking is completely free. You pay only the consultation fee directly to the provider at your appointment. No platform fees, ever.'],
      ['How do I share my insurance when booking?','Upload your insurance card from Dashboard → Insurance. When booking, select that document and tick the consent box — it is sent to the provider instantly.'],
      ['Can I consult a doctor remotely?','Yes. Use Telehealth video to consult any doctor without travelling — from your phone or computer. Works nationwide across all 47 counties.'],
      ['How do I register my hospital?','Click Register Your Facility, complete the form with your details and MOH licence number. Our team reviews applications within 24–48 hours.'],
      ['Is my health data safe?','Yes. All data is encrypted and stored in compliance with the Data Protection Act 2019. You control exactly what is shared through your consent settings.'],
    ] as $i => [$q,$a]): ?>
    <div class="faq">
      <button class="faq-btn" onclick="toggleFaq(<?=$i?>)">
        <span class="faq-q"><?=$q?></span>
        <div class="faq-ic-wrap">
          <i class="fa-solid fa-plus faq-ic" id="fi-<?=$i?>"></i>
        </div>
      </button>
      <div class="faq-body" id="fb-<?=$i?>">
        <p><?=$a?></p>
      </div>
    </div>
    <?php endforeach; ?>

    <div style="text-align:center;margin-top:36px">
      <p style="font-size:13.5px;color:var(--muted);margin-bottom:14px">Still have questions?</p>
      <a href="mailto:info@planeazzy.com" class="btn btn-outline">
        <i class="fa-solid fa-envelope"></i> Contact Our Support Team
      </a>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
/* ── Search ── */
function doSearch() {
  const sp  = document.getElementById('sSpecialty')?.value?.trim() || '';
  const co  = document.getElementById('sCounty')?.value?.trim()    || '';
  const ins = document.getElementById('sInsurance')?.value?.trim() || '';
  const q   = document.getElementById('sQuery')?.value?.trim()     || '';
  const tp  = document.getElementById('sType')?.value?.trim()      || 'all';
  const p   = new URLSearchParams();
  if (sp)  p.set('specialty', sp);
  if (co)  p.set('county', co);
  if (ins) p.set('insurance', ins);
  if (q)   p.set('q', q);
  if (tp && tp !== 'all') p.set('type', tp);
  window.location.href = '/patients/search.php?' + p.toString();
}

function setTab(btn, type) {
  document.querySelectorAll('.stab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('sType').value = type;
}

function doGeoSearch() {
  window.location.href = '/patients/search.php?geo=1';
}

/* ── FAQ ── */
function toggleFaq(i) {
  const body = document.getElementById('fb-' + i);
  const icon = document.getElementById('fi-' + i);
  const isOpen = body.style.display === 'block';
  // Close all
  document.querySelectorAll('.faq-body').forEach(b => b.style.display = 'none');
  document.querySelectorAll('.faq-ic').forEach(ic => ic.classList.remove('open'));
  // Open selected
  if (!isOpen) {
    body.style.display = 'block';
    icon.classList.add('open');
  }
}

/* ── Intersection Observer for fade-in entrance ── */
(function(){
  if (!('IntersectionObserver' in window)) return;
  const els = document.querySelectorAll('.card, .feat, .spec-card, .appt-type-card, .testi, .about-box, .step-card');
  const style = document.createElement('style');
  style.textContent = `
    .pz-reveal { opacity: 0; transition: opacity .55s ease; }
    .pz-reveal.visible { opacity: 1; }
  `;
  document.head.appendChild(style);
  els.forEach((el, idx) => {
    el.classList.add('pz-reveal');
    el.style.transitionDelay = (idx % 4) * 70 + 'ms';
  });
  const obs = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); }});
  }, { threshold: 0.08 });
  els.forEach(el => obs.observe(el));
})();
</script>