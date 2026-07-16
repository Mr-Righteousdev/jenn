<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Competency Profiling System — Bridge Education & Employment</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --bg: oklch(1.000 0.000 0);
  --surface: oklch(0.97 0.005 260);
  --primary: oklch(0.48 0.14 260);
  --primary-deep: oklch(0.35 0.12 260);
  --primary-light: oklch(0.65 0.10 258);
  --primary-glow: oklch(0.55 0.13 260 / 0.15);
  --ink: oklch(0.12 0.008 260);
  --ink-soft: oklch(0.30 0.008 260);
  --accent: oklch(0.70 0.16 70);
  --accent-deep: oklch(0.55 0.16 70);
  --muted: oklch(0.50 0.01 260);
  --font: 'Sora', system-ui, -apple-system, sans-serif;
  --max-w: 1200px;
  --z-nav: 100;
  --z-dropdown: 200;
  --z-modal: 300;
  --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
  --ease-in-out: cubic-bezier(0.65, 0, 0.35, 1);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; -webkit-font-smoothing: antialiased; }
body {
  font-family: var(--font);
  color: var(--ink);
  background: var(--bg);
  line-height: 1.6;
  overflow-x: hidden;
}
img { max-width: 100%; display: block; }
a { color: var(--primary); text-decoration: none; }
.container { max-width: var(--max-w); margin: 0 auto; padding: 0 1.5rem; }
.section { padding: 5rem 0; }
.section-alt { background: var(--surface); }

/* Typography */
h1, h2, h3 { text-wrap: balance; line-height: 1.2; font-weight: 700; }
h2 { font-size: clamp(1.75rem, 3.5vw, 2.75rem); margin-bottom: 1rem; }
h3 { font-size: clamp(1.15rem, 1.8vw, 1.5rem); }
p { text-wrap: pretty; }

/* Nav */
nav {
  position: fixed; top: 0; left: 0; right: 0;
  z-index: var(--z-nav);
  padding: 1rem 0;
  transition: background 0.4s var(--ease-out), padding 0.4s var(--ease-out);
}
nav.scrolled {
  background: oklch(1.000 0.000 0 / 0.85);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  padding: 0.6rem 0;
  box-shadow: 0 1px 0 oklch(0 0 0 / 0.06);
}
.nav-inner {
  display: flex; align-items: center; justify-content: space-between;
  max-width: var(--max-w); margin: 0 auto; padding: 0 1.5rem;
}
.nav-logo {
  font-weight: 800; font-size: 1.15rem;
  color: var(--primary); letter-spacing: -0.02em;
}
.nav-logo span { color: var(--ink); }
.nav-links { display: flex; align-items: center; gap: 2rem; list-style: none; }
.nav-links a {
  font-size: 0.875rem; font-weight: 500;
  color: var(--ink-soft);
  transition: color 0.2s;
  position: relative;
}
.nav-links a:hover { color: var(--primary); }
.nav-cta {
  display: inline-flex; align-items: center; gap: 0.4rem;
  background: var(--primary); color: #fff !important;
  padding: 0.5rem 1.25rem; border-radius: 100px;
  font-weight: 600; font-size: 0.85rem;
  transition: background 0.2s, transform 0.2s var(--ease-out);
}
.nav-cta:hover { background: var(--primary-deep); transform: translateY(-1px); }
.nav-toggle {
  display: none; flex-direction: column; gap: 4px;
  background: none; border: none; cursor: pointer; padding: 4px;
}
.nav-toggle span {
  display: block; width: 22px; height: 2px;
  background: var(--ink); border-radius: 2px;
  transition: transform 0.3s var(--ease-out), opacity 0.3s;
}
.nav-toggle.open span:nth-child(1) { transform: rotate(45deg) translate(4px, 4px); }
.nav-toggle.open span:nth-child(2) { opacity: 0; }
.nav-toggle.open span:nth-child(3) { transform: rotate(-45deg) translate(4px, -4px); }

/* Hero */
.hero {
  min-height: 100vh;
  display: flex; align-items: center;
  position: relative;
  overflow: hidden;
  background:
    radial-gradient(ellipse 80% 60% at 50% -10%, var(--primary-glow), transparent),
    radial-gradient(ellipse 60% 40% at 80% 80%, oklch(0.70 0.16 70 / 0.06), transparent),
    var(--bg);
}
.hero-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4rem;
  align-items: center;
  padding-top: 4rem;
}
.hero-badge {
  display: inline-flex; align-items: center; gap: 0.5rem;
  background: var(--primary-glow); color: var(--primary);
  padding: 0.35rem 1rem; border-radius: 100px;
  font-size: 0.8rem; font-weight: 600;
  margin-bottom: 1.5rem;
}
.hero h1 {
  font-size: clamp(2.2rem, 5vw, 4rem);
  font-weight: 800;
  letter-spacing: -0.04em;
  line-height: 1.05;
  margin-bottom: 1.25rem;
}
.hero h1 em {
  font-style: normal;
  background: linear-gradient(135deg, var(--primary), var(--primary-light));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.hero p {
  font-size: clamp(1rem, 1.4vw, 1.15rem);
  color: var(--ink-soft);
  max-width: 520px;
  margin-bottom: 2rem;
  line-height: 1.7;
}
.hero-actions {
  display: flex; gap: 1rem; flex-wrap: wrap;
}
.btn {
  display: inline-flex; align-items: center; gap: 0.5rem;
  padding: 0.8rem 1.75rem; border-radius: 100px;
  font-weight: 600; font-size: 0.9rem; font-family: var(--font);
  cursor: pointer; border: none;
  transition: transform 0.2s var(--ease-out), box-shadow 0.2s;
}
.btn:hover { transform: translateY(-2px); }
.btn-primary {
  background: var(--primary); color: #fff;
  box-shadow: 0 4px 16px oklch(0.48 0.14 260 / 0.3);
}
.btn-primary:hover { box-shadow: 0 6px 24px oklch(0.48 0.14 260 / 0.4); }
.btn-outline {
  background: transparent; color: var(--ink);
  border: 1.5px solid oklch(0 0 0 / 0.1);
}
.btn-outline:hover { border-color: oklch(0 0 0 / 0.2); box-shadow: 0 2px 8px oklch(0 0 0 / 0.06); }
.hero-visual {
  position: relative;
  display: flex; justify-content: center; align-items: center;
}
.hero-graphic {
  width: 100%; max-width: 520px;
  aspect-ratio: 4/3;
  position: relative;
}
.hero-graphic svg { width: 100%; height: 100%; }
.hero-stat {
  position: absolute;
  background: oklch(1.000 0.000 0 / 0.95);
  backdrop-filter: blur(8px);
  border-radius: 12px;
  padding: 0.75rem 1rem;
  box-shadow: 0 4px 24px oklch(0 0 0 / 0.08);
  font-size: 0.8rem; font-weight: 500;
  display: flex; align-items: center; gap: 0.5rem;
}
.hero-stat strong { font-size: 1.1rem; color: var(--primary); }
.hero-stat-1 { top: 10%; right: -5%; }
.hero-stat-2 { bottom: 15%; left: -8%; }
.hero-stat .dot {
  width: 8px; height: 8px; border-radius: 50%;
  flex-shrink: 0;
}

/* Problem section */
.problem-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 3rem;
  align-items: center;
}
.problem-card {
  background: var(--bg);
  border-radius: 20px;
  padding: 2.5rem;
  border: 1px solid oklch(0 0 0 / 0.04);
  position: relative;
}
.problem-card::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0;
  height: 4px;
  border-radius: 20px 20px 0 0;
}
.problem-card.grades::before { background: var(--accent); }
.problem-card.skills::before { background: var(--primary); }
.problem-card .icon {
  width: 44px; height: 44px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 1rem;
  font-size: 1.2rem;
}
.problem-card.grades .icon { background: oklch(0.70 0.16 70 / 0.12); }
.problem-card.skills .icon { background: var(--primary-glow); }

/* Process */
.process-steps {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 2rem;
  margin-top: 3rem;
  position: relative;
}
.process-steps::before {
  content: '';
  position: absolute; top: 2.5rem; left: 16%; right: 16%;
  height: 2px;
  background: oklch(0 0 0 / 0.06);
  z-index: 0;
}
.step {
  text-align: center;
  position: relative;
  z-index: 1;
}
.step-number {
  width: 56px; height: 56px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 1.25rem;
  font-weight: 700; font-size: 1.25rem;
  background: var(--bg); border: 2px solid oklch(0 0 0 / 0.06);
  color: var(--muted);
  transition: background 0.3s, border-color 0.3s, color 0.3s;
}
.step.active .step-number {
  background: var(--primary); border-color: var(--primary);
  color: #fff;
  box-shadow: 0 4px 16px var(--primary-glow);
}
.step h3 { margin-bottom: 0.5rem; }
.step p { font-size: 0.9rem; color: var(--muted); line-height: 1.6; }

/* Features grid */
.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
  margin-top: 3rem;
}
.feature-card {
  background: var(--bg);
  border: 1px solid oklch(0 0 0 / 0.04);
  border-radius: 16px;
  padding: 1.75rem;
  transition: transform 0.3s var(--ease-out), box-shadow 0.3s;
}
.feature-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 32px oklch(0 0 0 / 0.06);
}
.feature-icon {
  width: 44px; height: 44px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 1rem;
  background: var(--primary-glow);
  color: var(--primary);
  font-size: 1.2rem;
}
.feature-card h3 { font-size: 1.05rem; margin-bottom: 0.5rem; }
.feature-card p { font-size: 0.875rem; color: var(--muted); line-height: 1.6; }

/* Audience */
.audience-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 2rem;
  margin-top: 3rem;
}
.audience-card {
  background: var(--bg);
  border-radius: 20px;
  padding: 2rem;
  border: 1px solid oklch(0 0 0 / 0.04);
  transition: transform 0.3s var(--ease-out);
}
.audience-card:hover { transform: translateY(-4px); }
.audience-card .icon {
  width: 48px; height: 48px; border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 1rem;
  font-size: 1.3rem;
}
.audience-card:nth-child(1) .icon { background: oklch(0.70 0.16 70 / 0.12); color: var(--accent-deep); }
.audience-card:nth-child(2) .icon { background: var(--primary-glow); color: var(--primary); }
.audience-card:nth-child(3) .icon { background: oklch(0.48 0.14 260 / 0.08); color: var(--primary-deep); }
.audience-card ul {
  list-style: none; margin-top: 1rem;
}
.audience-card li {
  padding: 0.35rem 0; font-size: 0.85rem; color: var(--ink-soft);
  display: flex; align-items: center; gap: 0.5rem;
}
.audience-card li::before {
  content: '\2713';
  color: var(--primary); font-weight: 700;
}

/* Stats */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 2rem;
  text-align: center;
}
.stat-number {
  font-size: clamp(2rem, 3.5vw, 3rem);
  font-weight: 800;
  color: var(--primary);
  line-height: 1;
  margin-bottom: 0.35rem;
}
.stat-label { font-size: 0.85rem; color: var(--muted); }

/* Case study */
.case-card {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 3rem;
  align-items: center;
  background: var(--primary);
  border-radius: 24px;
  padding: 3rem;
  color: #fff;
  margin-top: 3rem;
}
.case-card h2 { color: #fff; }
.case-card p { color: oklch(1 1 1 / 0.7); }
.case-meta {
  display: flex; gap: 2rem; margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid oklch(1 1 1 / 0.15);
}
.case-meta div { font-size: 0.8rem; color: oklch(1 1 1 / 0.6); }
.case-meta strong { display: block; font-size: 1rem; color: #fff; }

/* CTA */
.cta-section {
  text-align: center;
  padding: 6rem 0;
}
.cta-section h2 { max-width: 640px; margin: 0 auto 1rem; }
.cta-section p { color: var(--muted); max-width: 480px; margin: 0 auto 2rem; }

/* Footer */
footer {
  border-top: 1px solid oklch(0 0 0 / 0.06);
  padding: 2.5rem 0;
  font-size: 0.85rem;
  color: var(--muted);
}
.footer-inner {
  display: flex; justify-content: space-between; align-items: center;
  flex-wrap: wrap; gap: 1rem;
}
.footer-links { display: flex; gap: 1.5rem; }
.footer-links a { color: var(--muted); }
.footer-links a:hover { color: var(--ink); }

/* Animations */
@media (prefers-reduced-motion: no-preference) {
  .fade-up {
    opacity: 0; transform: translateY(20px);
    transition: opacity 0.8s var(--ease-out), transform 0.8s var(--ease-out);
  }
  .fade-up.visible {
    opacity: 1; transform: translateY(0);
  }
  .fade-in {
    opacity: 0;
    transition: opacity 1s var(--ease-out);
  }
  .fade-in.visible { opacity: 1; }
  .hero-visual {
    animation: float 6s var(--ease-in-out) infinite;
  }
  @keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-12px); }
  }
  .hero-stat-1 { animation: pulse-stat 4s var(--ease-in-out) infinite 0.5s; }
  .hero-stat-2 { animation: pulse-stat 4s var(--ease-in-out) infinite 1.5s; }
  @keyframes pulse-stat {
    0%, 100% { transform: translateY(0); opacity: 1; }
    50% { transform: translateY(-6px); opacity: 0.8; }
  }
  .step:nth-child(1) .step-number { transition-delay: 0s; }
  .step:nth-child(2) .step-number { transition-delay: 0.2s; }
  .step:nth-child(3) .step-number { transition-delay: 0.4s; }
}
@media (prefers-reduced-motion: reduce) {
  .fade-up, .fade-in { opacity: 1; transform: none; }
  .hero-visual, .hero-stat-1, .hero-stat-2 { animation: none; }
  html { scroll-behavior: auto; }
}

/* Responsive */
@media (max-width: 900px) {
  .hero-grid { grid-template-columns: 1fr; gap: 2rem; padding-top: 6rem; }
  .hero-visual { order: -1; }
  .hero-graphic { max-width: 380px; }
  .hero-stat-1 { right: 0; }
  .hero-stat-2 { left: 0; }
  .problem-grid { grid-template-columns: 1fr; }
  .process-steps { grid-template-columns: 1fr; gap: 1.5rem; }
  .process-steps::before { display: none; }
  .step { display: flex; gap: 1rem; text-align: left; }
  .step-number { margin: 0; flex-shrink: 0; }
  .audience-grid { grid-template-columns: 1fr; }
  .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
  .case-card { grid-template-columns: 1fr; padding: 2rem; }
  .nav-links { display: none; }
  .nav-links.open { display: flex; flex-direction: column; gap: 1rem; position: fixed; top: 60px; left: 0; right: 0; background: var(--bg); padding: 1.5rem; box-shadow: 0 4px 24px oklch(0 0 0 / 0.08); }
  .nav-toggle { display: flex; }
}
@media (max-width: 480px) {
  .section { padding: 3rem 0; }
  .hero-actions { flex-direction: column; }
  .btn { width: 100%; justify-content: center; }
  .stats-grid { grid-template-columns: 1fr 1fr; }
  .features-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<nav id="nav">
  <div class="nav-inner">
    <a href="/" class="nav-logo">Competency<span>System</span></a>
    <button class="nav-toggle" id="navToggle" aria-label="Toggle menu">
      <span></span><span></span><span></span>
    </button>
    <ul class="nav-links" id="navLinks">
      <li><a href="#how-it-works">How it Works</a></li>
      <li><a href="#features">Features</a></li>
      <li><a href="#case-study">Case Study</a></li>
      <li><a href="/login.php">Login</a></li>
      <li><a href="/register.php" class="nav-cta">Get Started</a></li>
    </ul>
  </div>
</nav>

<section class="hero">
  <div class="container hero-grid">
    <div>
      <div class="hero-badge">St. Lawrence University, Uganda</div>
      <h1>Bridge the gap between <em>education</em> and <em>employment</em></h1>
      <p>
        Universities measure grades. Employers need skills. Our platform profiles student competencies,
        matches them to real job market requirements, and delivers actionable recommendations — all aligned
        with the Competency-Based Curriculum (CBC) framework.
      </p>
      <div class="hero-actions">
        <a href="/register.php" class="btn btn-primary">Get Started Free</a>
        <a href="#how-it-works" class="btn btn-outline">How It Works</a>
      </div>
    </div>
    <div class="hero-visual">
      <div class="hero-graphic">
        <svg viewBox="0 0 400 300" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="40" y="80" width="160" height="140" rx="16" fill="oklch(0.48 0.14 260 / 0.06)" stroke="oklch(0.48 0.14 260 / 0.12)" stroke-width="1.5"/>
          <rect x="60" y="100" width="120" height="6" rx="3" fill="oklch(0.48 0.14 260 / 0.25)"/>
          <rect x="60" y="116" width="80" height="6" rx="3" fill="oklch(0.48 0.14 260 / 0.15)"/>
          <rect x="60" y="132" width="100" height="6" rx="3" fill="oklch(0.48 0.14 260 / 0.15)"/>
          <rect x="60" y="148" width="60" height="6" rx="3" fill="oklch(0.48 0.14 260 / 0.15)"/>
          <circle cx="80" cy="180" r="8" fill="oklch(0.48 0.14 260 / 0.3)"/>
          <circle cx="80" cy="180" r="12" fill="oklch(0.48 0.14 260 / 0.1)"/>
          <text x="96" y="183" font-size="8" fill="oklch(0.48 0.14 260 / 0.5)" font-family="Sora, sans-serif">85%</text>
          <rect x="60" y="200" width="30" height="4" rx="2" fill="oklch(0.70 0.16 70 / 0.5)"/>
          <rect x="96" y="200" width="24" height="4" rx="2" fill="oklch(0.70 0.16 70 / 0.3)"/>

          <rect x="220" y="60" width="140" height="120" rx="16" fill="oklch(0.70 0.16 70 / 0.06)" stroke="oklch(0.70 0.16 70 / 0.12)" stroke-width="1.5"/>
          <rect x="240" y="80" width="100" height="5" rx="2.5" fill="oklch(0.70 0.16 70 / 0.25)"/>
          <rect x="240" y="94" width="60" height="5" rx="2.5" fill="oklch(0.70 0.16 70 / 0.15)"/>
          <rect x="240" y="108" width="80" height="5" rx="2.5" fill="oklch(0.70 0.16 70 / 0.15)"/>
          <rect x="240" y="128" width="80" height="20" rx="6" fill="oklch(0.48 0.14 260 / 0.12)"/>
          <text x="252" y="141" font-size="7" fill="oklch(0.48 0.14 260 / 0.6)" font-family="Sora, sans-serif">Match: 72%</text>

          <line x1="200" y1="150" x2="220" y2="120" stroke="oklch(0 0 0 / 0.08)" stroke-width="1.5" stroke-dasharray="4 3"/>
          <line x1="180" y1="80" x2="220" y2="60" stroke="oklch(0 0 0 / 0.08)" stroke-width="1.5" stroke-dasharray="4 3"/>
        </svg>
      </div>
      <div class="hero-stat hero-stat-1">
        <span class="dot" style="background: var(--accent);"></span>
        <span>Job Readiness: <strong>74%</strong></span>
      </div>
  <div class="hero-stat hero-stat-2">
    <span class="dot" style="background: var(--primary);"></span>
    <span>Skills Profiled: <strong>12</strong></span>
  </div>
    </div>
  </div>
</section>

<section class="section section-alt fade-up" id="how-it-works">
  <div class="container">
    <p class="hero-badge" style="display: inline-flex; margin-bottom: 1rem;">How It Works</p>
    <h2>From assessment to opportunity in three steps</h2>
    <p style="color: var(--muted); max-width: 560px; margin-bottom: 0;">
      Students are assessed on a 0–100 scale across IT domains. The system matches their profile against
      real job listings and generates a personalized readiness report.
    </p>
    <div class="process-steps">
      <div class="step active">
        <div class="step-number">1</div>
        <h3>Profile &amp; Assess</h3>
        <p>Lecturers rate student skills across programming, databases, networking, and more. Academic results are imported via CSV.</p>
      </div>
      <div class="step active">
        <div class="step-number">2</div>
        <h3>Match &amp; Score</h3>
        <p>A hybrid algorithm combines weighted skill scoring (60%) with keyword analysis (40%) to calculate job readiness.</p>
      </div>
      <div class="step active">
        <div class="step-number">3</div>
        <h3>Grow &amp; Apply</h3>
        <p>Gap analysis identifies missing skills. Auto-generated recommendations link to courses, tutorials, and resources.</p>
      </div>
    </div>
  </div>
</section>

<section class="section fade-up" id="features">
  <div class="container">
    <p class="hero-badge" style="display: inline-flex; margin-bottom: 1rem;">Platform Capabilities</p>
    <h2>Everything you need to close the skills gap</h2>
    <p style="color: var(--muted); max-width: 560px; margin-bottom: 0;">
      Designed for universities, built for students, and connected to the real job market.
    </p>
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        </div>
        <h3>Competency Profiling</h3>
        <p>Track student skills across 15+ IT domains on a 0–100 scale. Visual radar charts show strengths and weaknesses at a glance.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
        </div>
        <h3>Job Market Integration</h3>
        <p>Live job fetching from the JSearch API. Skills are auto-extracted from job descriptions using keyword matching.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12h6l2-5 3 10 2-7 1 4h6"/><path d="M22 12c0 5.523-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2s10 4.477 10 10z"/></svg>
        </div>
        <h3>Hybrid Matching Engine</h3>
        <p>Weighted skills (60%) + keyword similarity (40%) produces a realisitic readiness score. No black boxes — every score is explainable.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 16l4-8 4 4 4-6"/></svg>
        </div>
        <h3>Gap Analysis &amp; Reports</h3>
        <p>Identify the top 3–5 missing skills per job. Generate printable readiness reports for students and class-wide summaries for lecturers.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
        </div>
        <h3>CBC Alignment</h3>
        <p>Editable competency framework with four levels (Novice → Expert). Admin-configurable to match your institution's curriculum.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
        </div>
        <h3>Smart Recommendations</h3>
        <p>Each gap triggers targeted recommendations: university course codes, YouTube tutorials, and plain-language skill guidance.</p>
      </div>
    </div>
  </div>
</section>

<section class="section section-alt fade-up">
  <div class="container">
    <p class="hero-badge" style="display: inline-flex; margin-bottom: 1rem;">Who It's For</p>
    <h2>Built for the entire university ecosystem</h2>
    <div class="audience-grid">
      <div class="audience-card">
        <div class="icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
        </div>
        <h3>For Students</h3>
        <ul>
          <li>View your competency profile with radar charts</li>
          <li>See how your skills match real job listings</li>
          <li>Get personalized learning recommendations</li>
          <li>Track your progress across CBC levels</li>
        </ul>
      </div>
      <div class="audience-card">
        <div class="icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
        </div>
        <h3>For Lecturers</h3>
        <ul>
          <li>Assess student skills with a simple 0–100 form</li>
          <li>View class-wide competency summaries</li>
          <li>Identify teaching gaps across cohorts</li>
          <li>Export reports for departmental review</li>
        </ul>
      </div>
      <div class="audience-card">
        <div class="icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg>
        </div>
        <h3>For Administrators</h3>
        <ul>
          <li>Configure CBC levels and skill categories</li>
          <li>Import student data via CSV upload</li>
          <li>Manage job listings and API integration</li>
          <li>Access system-wide analytics and usage stats</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<section class="section fade-up">
  <div class="container">
    <div class="stats-grid">
      <div>
        <div class="stat-number">10+</div>
        <div class="stat-label">Skill Categories</div>
      </div>
      <div>
        <div class="stat-number">15+</div>
        <div class="stat-label">Tracked Skills</div>
      </div>
      <div>
        <div class="stat-number">4</div>
        <div class="stat-label">CBC Competency Levels</div>
      </div>
      <div>
        <div class="stat-number">3</div>
        <div class="stat-label">User Roles</div>
      </div>
    </div>
  </div>
</section>

<section class="section section-alt" id="case-study">
  <div class="container">
    <p class="hero-badge" style="display: inline-flex; margin-bottom: 1rem;">Case Study</p>
    <h2>St. Lawrence University, Uganda</h2>
    <p style="color: var(--muted); max-width: 560px; margin-bottom: 0;">
      Department of Informatics and Engineering — bridging the gap between academic training and industry demand in East Africa's growing tech sector.
    </p>
    <div class="case-card">
      <div>
        <p style="margin-bottom: 1rem;">
          The Department of Informatics and Engineering at St. Lawrence University partnered with this platform
          to tackle a fundamental challenge: students graduating with strong grades but low job placement rates.
          The root cause was a mismatch between what exams measure and what employers need.
        </p>
        <p>
          By profiling student competencies across programming, web development, databases, networking, and soft skills —
          and matching them against live job listings from the Ugandan market — the system provides actionable readiness
          scores and targeted learning recommendations.
        </p>
        <div class="case-meta">
          <div><strong>Department</strong>Informatics &amp; Engineering</div>
          <div><strong>Students</strong>50+ profiled</div>
          <div><strong>Jobs</strong>Live market data</div>
        </div>
      </div>
      <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <div style="background: oklch(1 1 1 / 0.08); border-radius: 16px; padding: 1.5rem;">
          <div style="font-size: 0.8rem; color: oklch(1 1 1 / 0.5); margin-bottom: 0.5rem;">Student</div>
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 40px; height: 40px; border-radius: 50%; background: oklch(1 1 1 / 0.15); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem;">J</div>
            <div>
              <div style="font-weight: 600;">Jennifer N.</div>
              <div style="font-size: 0.8rem; color: oklch(1 1 1 / 0.5);">BIT, Year 3</div>
            </div>
          </div>
          <div style="margin-top: 1rem; display: flex; gap: 1.5rem;">
            <div><div style="font-size: 1.25rem; font-weight: 700;">72%</div><div style="font-size: 0.75rem; color: oklch(1 1 1 / 0.5);">Readiness</div></div>
            <div><div style="font-size: 1.25rem; font-weight: 700;">8</div><div style="font-size: 0.75rem; color: oklch(1 1 1 / 0.5);">Skills</div></div>
            <div><div style="font-size: 1.25rem; font-weight: 700;">3</div><div style="font-size: 0.75rem; color: oklch(1 1 1 / 0.5);">Gaps</div></div>
          </div>
        </div>
        <div style="background: oklch(1 1 1 / 0.08); border-radius: 16px; padding: 1.5rem;">
          <div style="font-size: 0.8rem; color: oklch(1 1 1 / 0.5); margin-bottom: 0.5rem;">Top Recommendation</div>
          <div style="font-weight: 600; font-size: 1.1rem;">Web Development</div>
          <div style="font-size: 0.85rem; color: oklch(1 1 1 / 0.6); margin-top: 0.25rem;">Course: WEB201 · YouTube tutorials · Practice projects</div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="cta-section">
  <div class="container">
    <h2>Ready to transform how your university measures readiness?</h2>
    <p>Join St. Lawrence University and other institutions in bridging the gap between academic achievement and career success.</p>
    <div class="hero-actions" style="justify-content: center;">
      <a href="/register.php" class="btn btn-primary">Create Free Account</a>
      <a href="/login.php" class="btn btn-outline">Login</a>
    </div>
  </div>
</section>

<footer>
  <div class="container">
    <div class="footer-inner">
      <span>&copy; 2026 Competency Profiling System. St. Lawrence University, Uganda.</span>
      <div class="footer-links">
        <a href="/login.php">Login</a>
        <a href="/register.php">Register</a>
      </div>
    </div>
  </div>
</footer>

<script>
// Scroll nav
const nav = document.getElementById('nav');
let ticking = false;
window.addEventListener('scroll', () => {
  if (!ticking) {
    requestAnimationFrame(() => {
      nav.classList.toggle('scrolled', window.scrollY > 60);
      ticking = false;
    });
    ticking = true;
  }
});

// Mobile nav toggle
const toggle = document.getElementById('navToggle');
const links = document.getElementById('navLinks');
toggle.addEventListener('click', () => {
  toggle.classList.toggle('open');
  links.classList.toggle('open');
});
links.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
  toggle.classList.remove('open');
  links.classList.remove('open');
}));

// Intersection Observer for fade animations
if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll('.fade-up, .fade-in').forEach(el => observer.observe(el));
}
</script>
</body>
</html>
