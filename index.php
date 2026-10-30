
<?php
session_start();

if (!empty($_SESSION['logged_in'])) {
    $role = strtolower(trim((string)($_SESSION['role'] ?? '')));
    $dashboards = [
        'student' => '/smartmess/student/dashboard.php',
        'manager' => '/smartmess/manager/dashboard.php',
        'admin' => '/smartmess/admin/dashboard.php',
    ];

    if (isset($dashboards[$role])) {
        header('Location: ' . $dashboards[$role]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1E2B37">
    <meta name="description" content="SmartMess brings meal schedules, ratings, meal skipping, feedback and announcements together in one simple mess management system.">
    <title>SmartMess | Smart Mess Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --slate: #2C3E50;
            --deep-slate: #1E2B37;
            --light-bg: #F0F2F8;
            --white: #FFFFFF;
            --cyan: #38BDF8;
            --text: #1A202C;
            --muted: #718096;
            --border: #E3E8F0;
            --shadow: 0 16px 40px rgba(30, 43, 55, .09);
            --content-width: 1160px;
        }

        * { box-sizing: border-box; }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 88px;
        }

        body {
            margin: 0;
            color: var(--text);
            background: var(--light-bg);
            font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif;
            line-height: 1.6;
        }

        a { color: inherit; text-decoration: none; }
        button { font: inherit; }

        .container {
            width: min(var(--content-width), calc(100% - 48px));
            margin: 0 auto;
        }

        .site-header {
            position: absolute;
            z-index: 5;
            top: 0;
            right: 0;
            left: 0;
            color: var(--white);
        }

        .nav-wrap {
            min-height: 82px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 28px;
            border-bottom: 1px solid rgba(255, 255, 255, .13);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            color: var(--white);
            font-size: 1.24rem;
            font-weight: 800;
            letter-spacing: -.04em;
        }

        .brand-mark {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            color: var(--deep-slate);
            background: var(--cyan);
            border-radius: 13px;
            font-size: 1.1rem;
        }

        .brand span span { color: var(--cyan); }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .nav-links a:not(.nav-login) {
            color: rgba(255, 255, 255, .78);
            font-size: .92rem;
            transition: color .2s ease;
        }

        .nav-links a:not(.nav-login):hover,
        .nav-links a:not(.nav-login):focus-visible {
            color: var(--cyan);
        }

        .nav-login {
            padding: 10px 19px;
            color: var(--white);
            border: 1px solid rgba(255, 255, 255, .45);
            border-radius: 9px;
            font-size: .9rem;
            font-weight: 700;
            transition: background .2s ease, border-color .2s ease;
        }

        .nav-login:hover,
        .nav-login:focus-visible {
            background: rgba(255, 255, 255, .1);
            border-color: var(--cyan);
        }

        .nav-toggle { display: none; }

        .hero {
            position: relative;
            overflow: hidden;
            min-height: 640px;
            padding: 158px 0 112px;
            color: var(--white);
            background:
                radial-gradient(ellipse at 82% 30%, rgba(56, 189, 248, .2), transparent 34%),
                radial-gradient(ellipse at 12% 95%, rgba(43, 104, 151, .22), transparent 42%),
                linear-gradient(125deg, var(--deep-slate), var(--slate) 65%, #263E52);
        }

        .hero::after {
            position: absolute;
            right: -150px;
            bottom: -360px;
            width: 650px;
            height: 650px;
            border: 1px solid rgba(56, 189, 248, .15);
            border-radius: 50%;
            content: "";
            box-shadow: 0 0 0 55px rgba(56, 189, 248, .025), 0 0 0 110px rgba(56, 189, 248, .02);
            pointer-events: none;
        }

        /* Centered hero content; demo dashboard removed */
        .hero-layout {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .hero-content {
            width: 100%;
            max-width: 900px;
            min-width: 0;
            margin: 0 auto;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 7px 13px;
            color: #BDEBFF;
            background: rgba(56, 189, 248, .1);
            border: 1px solid rgba(56, 189, 248, .28);
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .eyebrow i { color: var(--cyan); }

        .hero h1 {
            max-width: 900px;
            margin: 22px auto 18px;
            font-size: clamp(2.7rem, 4.55vw, 4.2rem);
            line-height: 1.08;
            letter-spacing: -.055em;
        }

        .hero h1 span { color: var(--cyan); }

        .hero-copy {
            max-width: 650px;
            margin: 0 auto;
            color: rgba(255, 255, 255, .76);
            font-size: 1.1rem;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 14px;
            margin-top: 32px;
        }

        .button {
            min-height: 50px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 0 21px;
            border: 1px solid transparent;
            border-radius: 10px;
            font-size: .95rem;
            font-weight: 750;
            transition: transform .2s ease, background .2s ease, border-color .2s ease, box-shadow .2s ease;
        }

        .button:hover { transform: translateY(-2px); }

        .button-primary {
            color: var(--deep-slate);
            background: var(--cyan);
            box-shadow: 0 8px 22px rgba(56, 189, 248, .2);
        }

        .button-primary:hover { background: #69D0FF; }

        .button-secondary {
            color: var(--white);
            background: rgba(255, 255, 255, .07);
            border-color: rgba(255, 255, 255, .3);
        }

        .button-secondary:hover {
            background: rgba(255, 255, 255, .13);
            border-color: rgba(255, 255, 255, .55);
        }

        .hero-note {
            margin-top: 22px;
            color: rgba(255, 255, 255, .58);
            font-size: .85rem;
        }

        .section { padding: 88px 0; }

        .section-heading {
            max-width: 680px;
            margin: 0 auto 42px;
            text-align: center;
        }

        .section-kicker {
            margin: 0 0 9px;
            color: #1387B8;
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .section-heading h2 {
            margin: 0 0 12px;
            color: var(--text);
            font-size: clamp(2rem, 4vw, 2.8rem);
            line-height: 1.2;
            letter-spacing: -.04em;
        }

        .section-heading > p:last-child {
            margin: 0;
            color: var(--muted);
            font-size: 1.02rem;
        }

        .features-section { background: var(--light-bg); }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .feature-card {
            min-height: 218px;
            padding: 27px;
            background: var(--white);
            border: 1px solid rgba(44, 62, 80, .05);
            border-radius: 17px;
            box-shadow: 0 7px 22px rgba(30, 43, 55, .045);
            transition: transform .22s ease, box-shadow .22s ease;
        }

        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow);
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            color: #1387B8;
            background: #E6F7FF;
            border-radius: 14px;
            font-size: 1.15rem;
        }

        .feature-card h3 {
            margin: 19px 0 7px;
            color: var(--slate);
            font-size: 1.08rem;
        }

        .feature-card p {
            margin: 0;
            color: var(--muted);
            font-size: .92rem;
        }

        .about-section { padding-top: 20px; }

        .about-panel {
            display: grid;
            grid-template-columns: 1fr 1fr;
            align-items: center;
            gap: 52px;
            padding: 52px;
            background: var(--white);
            border: 1px solid rgba(44, 62, 80, .05);
            border-radius: 22px;
            box-shadow: var(--shadow);
        }

        .about-panel .section-kicker { text-align: left; }

        .about-panel h2 {
            margin: 0 0 15px;
            color: var(--text);
            font-size: clamp(1.8rem, 3.5vw, 2.55rem);
            line-height: 1.2;
            letter-spacing: -.04em;
        }

        .about-panel p {
            margin: 0 0 14px;
            color: var(--muted);
        }

        .about-panel p:last-child { margin-bottom: 0; }

        .about-list { display: grid; gap: 15px; }

        .about-point {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            color: var(--slate);
            font-weight: 650;
        }

        .about-point i {
            margin-top: 5px;
            color: #1387B8;
        }

        .steps-section { background: #E8ECF4; }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            counter-reset: steps;
        }

        .step-card {
            position: relative;
            padding: 29px;
            background: var(--white);
            border-radius: 17px;
            box-shadow: 0 7px 22px rgba(30, 43, 55, .045);
            counter-increment: steps;
        }

        .step-number {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            color: var(--deep-slate);
            background: var(--cyan);
            border-radius: 13px;
            font-weight: 850;
        }

        .step-number::before { content: counter(steps, decimal-leading-zero); }

        .step-card h3 {
            margin: 19px 0 8px;
            color: var(--slate);
            font-size: 1.08rem;
        }

        .step-card p {
            margin: 0;
            color: var(--muted);
            font-size: .93rem;
        }

        .contact-section { padding-top: 75px; }

        .contact-panel {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 30px;
            padding: 38px 42px;
            color: var(--white);
            background: linear-gradient(115deg, var(--deep-slate), var(--slate));
            border-radius: 20px;
        }

        .contact-panel h2 {
            margin: 0 0 7px;
            color: var(--white);
            font-size: clamp(1.5rem, 3vw, 2rem);
            letter-spacing: -.03em;
        }

        .contact-panel p {
            margin: 0;
            color: rgba(255, 255, 255, .7);
        }

        .contact-panel .button { flex: 0 0 auto; }

        .site-footer {
            margin-top: 88px;
            color: rgba(255, 255, 255, .7);
            background: #17232E;
        }

        .footer-main {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 28px;
            padding: 34px 0;
        }

        .footer-brand { display: grid; gap: 8px; }
        .footer-brand .brand { font-size: 1.1rem; }

        .footer-brand p {
            margin: 0;
            color: rgba(255, 255, 255, .55);
            font-size: .86rem;
        }

        .footer-links {
            display: flex;
            flex-wrap: wrap;
            gap: 22px;
        }

        .footer-links a {
            font-size: .87rem;
            transition: color .2s ease;
        }

        .footer-links a:hover,
        .footer-links a:focus-visible { color: var(--cyan); }

        .footer-bottom {
            padding: 17px 0;
            border-top: 1px solid rgba(255, 255, 255, .1);
            color: rgba(255, 255, 255, .48);
            font-size: .8rem;
        }

        :focus-visible {
            outline: 3px solid var(--cyan);
            outline-offset: 3px;
        }

        @media (max-width: 920px) {
            .hero {
                min-height: auto;
                padding: 135px 0 92px;
            }

            .hero-content { max-width: 700px; }
        }

        @media (max-width: 800px) {
            .nav-wrap { min-height: 72px; }

            .nav-toggle {
                width: 42px;
                height: 42px;
                display: inline-grid;
                place-items: center;
                color: var(--white);
                background: rgba(255, 255, 255, .08);
                border: 1px solid rgba(255, 255, 255, .22);
                border-radius: 10px;
                cursor: pointer;
            }

            .nav-links {
                position: absolute;
                top: 65px;
                right: 24px;
                left: 24px;
                display: none;
                align-items: stretch;
                gap: 0;
                padding: 10px;
                background: var(--deep-slate);
                border: 1px solid rgba(255, 255, 255, .12);
                border-radius: 14px;
                box-shadow: 0 18px 35px rgba(0, 0, 0, .25);
            }

            .nav-links.is-open { display: grid; }

            .nav-links a:not(.nav-login) { padding: 11px 12px; }

            .nav-login {
                margin-top: 6px;
                text-align: center;
            }

            .about-panel { gap: 32px; padding: 38px; }
        }

        @media (max-width: 620px) {
            .container { width: min(var(--content-width), calc(100% - 36px)); }

            .hero { padding-top: 125px; }

            .hero h1 { font-size: clamp(2.45rem, 11vw, 3.5rem); }

            .hero-copy { font-size: 1rem; }

            .hero-actions {
                align-items: stretch;
                flex-direction: column;
                max-width: 330px;
                margin-right: auto;
                margin-left: auto;
            }

            .section { padding: 68px 0; }

            .feature-grid,
            .steps-grid { grid-template-columns: 1fr; }

            .feature-card { min-height: 0; }

            .about-section { padding-top: 0; }

            .about-panel {
                grid-template-columns: 1fr;
                gap: 28px;
                padding: 29px 24px;
            }

            .contact-section { padding-top: 55px; }

            .contact-panel {
                align-items: flex-start;
                flex-direction: column;
                padding: 30px 24px;
            }

            .footer-main {
                align-items: flex-start;
                flex-direction: column;
            }

            .footer-links { gap: 14px 20px; }

            .site-footer { margin-top: 65px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <nav class="container nav-wrap" aria-label="Main navigation">
            <a class="brand" href="/smartmess/index.php#home" aria-label="SmartMess home">
                <span class="brand-mark" aria-hidden="true"><i class="fa-solid fa-utensils"></i></span>
                <span>Smart<span>Mess</span></span>
            </a>

            <button class="nav-toggle" type="button" aria-label="Open navigation menu" aria-expanded="false" aria-controls="primary-navigation">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>

            <div class="nav-links" id="primary-navigation">
                <a href="/smartmess/index.php#home">Home</a>
                <a href="/smartmess/index.php#about">About</a>
                <a href="/smartmess/index.php#features">Features</a>
                <a href="/smartmess/index.php#how-it-works">How It Works</a>
                <a href="/smartmess/index.php#contact">Contact</a>
                <a class="nav-login" href="/smartmess/login.php">Sign In</a>
            </div>
        </nav>
    </header>

    <main>
        <section class="hero" id="home">
            <div class="container">
                <div class="hero-layout">
                    <div class="hero-content">
                        <p class="eyebrow"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> A smarter way to manage your mess</p>

                        <h1>One Platform.<br><span>Smarter Mess Management.</span></h1>

                        <p class="hero-copy">SmartMess brings students, mess managers and administrators together to make everyday meal services simpler, clearer and more responsive.</p>

                        <div class="hero-actions">
                            <a class="button button-primary" href="/smartmess/login.php">Login <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                            <a class="button button-secondary" href="/smartmess/student/register.php">Student Registration</a>
                        </div>

                        <p class="hero-note">New student accounts require administrator approval before sign-in.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="section features-section" id="features">
            <div class="container">
                <div class="section-heading">
                    <p class="section-kicker">Designed for daily dining</p>
                    <h2>Everything your mess needs, in one place</h2>
                    <p>Purpose-built tools help students stay informed and give mess teams a clearer way to manage service.</p>
                </div>

                <div class="feature-grid">
                    <article class="feature-card">
                        <span class="feature-icon" aria-hidden="true"><i class="fa-solid fa-calendar-days"></i></span>
                        <h3>Menu Management</h3>
                        <p>View meal schedules and keep menu information organised for the mess community.</p>
                    </article>

                    <article class="feature-card">
                        <span class="feature-icon" aria-hidden="true"><i class="fa-solid fa-star"></i></span>
                        <h3>Meal Ratings</h3>
                        <p>Share ratings and feedback about meals to help the team understand student preferences.</p>
                    </article>

                    <article class="feature-card">
                        <span class="feature-icon" aria-hidden="true"><i class="fa-solid fa-person-walking-arrow-right"></i></span>
                        <h3>Meal Skipping</h3>
                        <p>Record planned meal absences through the student portal.</p>
                    </article>

                    <article class="feature-card">
                        <span class="feature-icon" aria-hidden="true"><i class="fa-solid fa-comment-dots"></i></span>
                        <h3>Complaints &amp; Feedback</h3>
                        <p>Submit concerns and suggestions, then follow up through the system.</p>
                    </article>

                    <article class="feature-card">
                        <span class="feature-icon" aria-hidden="true"><i class="fa-solid fa-bullhorn"></i></span>
                        <h3>Announcements</h3>
                        <p>Keep up with notices and important updates shared with students.</p>
                    </article>

                    <article class="feature-card">
                        <span class="feature-icon" aria-hidden="true"><i class="fa-solid fa-chart-column"></i></span>
                        <h3>Reports</h3>
                        <p>Give mess managers and administrators tools to review service information.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="section about-section" id="about">
            <div class="container">
                <div class="about-panel">
                    <div>
                        <p class="section-kicker">About SmartMess</p>
                        <h2>A more connected mess experience</h2>
                        <p>SmartMess is a role-based mess management system for students, managers and administrators. It brings everyday mess communication and service tools together in a single web application.</p>
                        <p>Students can use the available features after signing in, while managers and administrators continue to work from their respective dashboards.</p>
                    </div>

                    <div class="about-list">
                        <div class="about-point"><i class="fa-solid fa-check" aria-hidden="true"></i><span>One common sign-in for every role</span></div>
                        <div class="about-point"><i class="fa-solid fa-check" aria-hidden="true"></i><span>Student registration reviewed by an administrator</span></div>
                        <div class="about-point"><i class="fa-solid fa-check" aria-hidden="true"></i><span>Role-specific tools after authentication</span></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section steps-section" id="how-it-works">
            <div class="container">
                <div class="section-heading">
                    <p class="section-kicker">How it works</p>
                    <h2>Get started in a few simple steps</h2>
                    <p>Registration and access follow the existing SmartMess account approval process.</p>
                </div>

                <div class="steps-grid">
                    <article class="step-card">
                        <span class="step-number" aria-hidden="true"></span>
                        <h3>Create a student account</h3>
                        <p>Submit your details through the student registration page. Your account will be marked for admin approval.</p>
                    </article>

                    <article class="step-card">
                        <span class="step-number" aria-hidden="true"></span>
                        <h3>Wait for administrator review</h3>
                        <p>An administrator reviews pending student registrations and approves or rejects each account.</p>
                    </article>

                    <article class="step-card">
                        <span class="step-number" aria-hidden="true"></span>
                        <h3>Sign in and use your portal</h3>
                        <p>Once approved, use the common login page. SmartMess opens the dashboard for your account role.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="contact-section" id="contact">
            <div class="container">
                <div class="contact-panel">
                    <div>
                        <h2>Ready to use SmartMess?</h2>
                        <p>Sign in to your account or register as a student to begin.</p>
                    </div>

                    <a class="button button-primary" href="/smartmess/student/register.php">Get Started <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-main">
            <div class="footer-brand">
                <a class="brand" href="/smartmess/index.php#home">
                    <span class="brand-mark" aria-hidden="true"><i class="fa-solid fa-utensils"></i></span>
                    <span>Smart<span>Mess</span></span>
                </a>
                <p>A simpler way to bring mess services together.</p>
            </div>

            <nav class="footer-links" aria-label="Footer navigation">
                <a href="/smartmess/index.php#home">Home</a>
                <a href="/smartmess/index.php#about">About</a>
                <a href="/smartmess/index.php#features">Features</a>
                <a href="/smartmess/index.php#how-it-works">How It Works</a>
                <a href="/smartmess/index.php#contact">Contact</a>
                <a href="/smartmess/login.php">Login</a>
                <a href="/smartmess/student/register.php">Student Registration</a>
            </nav>
        </div>

        <div class="footer-bottom">
            <div class="container">&copy; <?php echo date('Y'); ?> SmartMess. Smart Mess Management System.</div>
        </div>
    </footer>

    <script>
        const menuButton = document.querySelector('.nav-toggle');
        const navigation = document.querySelector('#primary-navigation');

        menuButton.addEventListener('click', () => {
            const isOpen = navigation.classList.toggle('is-open');
            menuButton.setAttribute('aria-expanded', String(isOpen));
            menuButton.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
            menuButton.innerHTML = isOpen
                ? '<i class="fa-solid fa-xmark" aria-hidden="true"></i>'
                : '<i class="fa-solid fa-bars" aria-hidden="true"></i>';
        });

        navigation.addEventListener('click', (event) => {
            if (event.target.closest('a')) {
                navigation.classList.remove('is-open');
                menuButton.setAttribute('aria-expanded', 'false');
                menuButton.setAttribute('aria-label', 'Open navigation menu');
                menuButton.innerHTML = '<i class="fa-solid fa-bars" aria-hidden="true"></i>';
            }
        });
    </script>
</body>
</html>
