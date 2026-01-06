<?php
session_start();

/* Optional: mark user as guest */
$_SESSION['role'] = 'guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Guest Dashboard | Vehicle Inventory System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        :root{
            --bg1:#f6f7ff;
            --bg2:#f2f6ff;
            --card:rgba(255,255,255,.82);
            --border:rgba(120,120,160,.20);

            --text:#1f2430;
            --muted:#6b7280;

            --primary1:#6c63ff;
            --primary2:#854af0;

            --sidebar:#101423;
            --sidebar2:#151a2e;

            --shadow:0 18px 50px rgba(25, 30, 60, 0.14);
            --shadow2:0 10px 22px rgba(25, 30, 60, 0.12);

            --radius:18px;
        }

        *{box-sizing:border-box;margin:0;padding:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}
        a{color:inherit}

        body{
            min-height:100vh;
            color:var(--text);
            background:
                radial-gradient(900px 420px at 20% 10%, rgba(108, 99, 255, 0.16), transparent 60%),
                radial-gradient(900px 420px at 80% 15%, rgba(133, 74, 240, 0.14), transparent 60%),
                linear-gradient(180deg,var(--bg1),var(--bg2));
        }

        /* Layout */
        .app{
            min-height:100vh;
            display:grid;
            grid-template-columns: 280px 1fr;
        }

        /* Sidebar */
        .sidebar{
            position:sticky;
            top:0;
            height:100vh;
            padding:1.25rem 1.1rem;
            color:#e9ecff;
            background: linear-gradient(180deg, var(--sidebar), var(--sidebar2));
            border-right:1px solid rgba(255,255,255,0.06);
        }

        .brand{
            display:flex;
            align-items:center;
            gap:.75rem;
            padding:.7rem .8rem;
            border-radius:14px;
            background: rgba(255,255,255,0.06);
            border:1px solid rgba(255,255,255,0.08);
            margin-bottom:1.1rem;
        }

        .logo{
            width:40px;height:40px;border-radius:14px;
            background: linear-gradient(135deg,var(--primary1),var(--primary2));
            box-shadow: 0 12px 30px rgba(108,99,255,.35);
            display:grid;place-items:center;
            font-weight:900;color:#fff;
        }

        .brand h1{
            font-size:1.02rem;
            line-height:1.2;
            letter-spacing:-0.01em;
        }

        .brand p{
            font-size:.85rem;
            color:rgba(233,236,255,.72);
            margin-top:.15rem;
        }

        .nav{
            margin-top:.8rem;
            display:grid;
            gap:.35rem;
        }

        .nav a{
            display:flex;
            align-items:center;
            gap:.7rem;
            padding:.8rem .9rem;
            border-radius:14px;
            text-decoration:none;
            color:rgba(233,236,255,.86);
            border:1px solid transparent;
            transition: transform .12s ease, background .12s ease, border-color .12s ease;
        }

        .nav a:hover{
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.10);
            transform: translateY(-1px);
        }

        .nav .active{
            background: rgba(108,99,255,0.18);
            border-color: rgba(108,99,255,0.25);
        }

        .nav .icon{
            width:34px;height:34px;border-radius:12px;
            display:grid;place-items:center;
            background: rgba(255,255,255,0.08);
            border:1px solid rgba(255,255,255,0.08);
            font-size:1rem;
        }

        .sidebar-footer{
            position:absolute;
            left:1.1rem;
            right:1.1rem;
            bottom:1.1rem;
            display:grid;
            gap:.6rem;
        }

        .pill{
            display:flex;
            align-items:center;
            justify-content:space-between;
            padding:.75rem .9rem;
            border-radius:14px;
            background: rgba(255,255,255,0.06);
            border:1px solid rgba(255,255,255,0.08);
            color:rgba(233,236,255,.86);
            font-size:.92rem;
        }

        .badge{
            padding:.25rem .55rem;
            border-radius:999px;
            font-size:.78rem;
            font-weight:800;
            color:#0b1020;
            background: rgba(255,255,255,0.85);
        }

        /* Main */
        .main{
            padding: 1.5rem 1.5rem 2rem;
        }

        .topbar{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:1rem;
            margin-bottom:1.25rem;
        }

        .title h2{
            font-size: clamp(1.35rem, 2.2vw, 1.8rem);
            letter-spacing:-0.02em;
        }
        .title p{
            margin-top:.2rem;
            color:var(--muted);
            line-height:1.55;
        }

        .cta{
            display:flex;
            gap:.75rem;
            flex-wrap:wrap;
            justify-content:flex-end;
        }

        .btn{
            border:none;
            cursor:pointer;
            text-decoration:none;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:.55rem;

            padding:.75rem 1.1rem;
            border-radius:999px;
            font-weight:800;
            font-size:.95rem;

            color:#fff;
            background: linear-gradient(135deg,var(--primary1),var(--primary2));
            box-shadow: 0 12px 24px rgba(108,99,255,0.22);
            transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
        }

        .btn:hover{
            transform: translateY(-2px);
            box-shadow: 0 16px 28px rgba(108,99,255,0.30);
            filter: brightness(1.02);
        }

        .btn.secondary{
            background: #121726;
            box-shadow: 0 12px 24px rgba(18,23,38,0.18);
        }

        .content{
            display:grid;
            gap:1.2rem;
        }

        .hero{
            background: var(--card);
            border:1px solid var(--border);
            backdrop-filter: blur(10px);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding:1.4rem 1.4rem;
            overflow:hidden;
            position:relative;
        }

        .hero::before{
            content:"";
            position:absolute;
            inset:-120px -120px auto auto;
            width:260px;height:260px;
            background: radial-gradient(circle at 30% 30%, rgba(108,99,255,0.28), transparent 60%);
            transform: rotate(15deg);
        }

        .hero h3{
            font-size:1.2rem;
            letter-spacing:-0.01em;
            margin-bottom:.35rem;
        }

        .hero p{
            color:var(--muted);
            line-height:1.6;
            max-width: 70ch;
        }

        .grid{
            display:grid;
            grid-template-columns: repeat(12, 1fr);
            gap:1.2rem;
        }

        .card{
            grid-column: span 4;
            background: var(--card);
            border:1px solid var(--border);
            backdrop-filter: blur(10px);
            border-radius: var(--radius);
            box-shadow: var(--shadow2);
            padding:1.2rem;
            position:relative;
            overflow:hidden;
        }

        .card .mini{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:1rem;
        }

        .card h4{
            font-size:1.05rem;
            margin-bottom:.25rem;
            letter-spacing:-0.01em;
        }

        .card p{
            color:var(--muted);
            line-height:1.55;
            font-size:.95rem;
        }

        .chip{
            padding:.35rem .65rem;
            border-radius:999px;
            font-size:.78rem;
            font-weight:800;
            color:#2d2d34;
            background: rgba(108,99,255,0.12);
            border:1px solid rgba(108,99,255,0.20);
            white-space:nowrap;
        }

        .card::after{
            content:"";
            position:absolute;
            inset:auto -80px -80px auto;
            width:180px;height:180px;
            background: radial-gradient(circle at 30% 30%, rgba(133,74,240,0.20), transparent 65%);
            transform: rotate(-10deg);
        }

        .footer{
            margin-top:1.4rem;
            text-align:center;
            color:var(--muted);
            font-size:.92rem;
        }

        /* Responsive */
        @media (max-width: 980px){
            .app{ grid-template-columns: 1fr; }
            .sidebar{
                height:auto;
                position:relative;
                border-right:none;
                border-bottom:1px solid rgba(255,255,255,0.06);
            }
            .sidebar-footer{ position:relative; left:auto; right:auto; bottom:auto; margin-top:1rem; }
            .grid{ grid-template-columns: repeat(6, 1fr); }
            .card{ grid-column: span 6; }
        }

        @media (max-width: 520px){
            .main{ padding: 1.1rem 1rem 1.6rem; }
            .grid{ grid-template-columns: repeat(1, 1fr); }
            .card{ grid-column: span 1; }
            .cta{ justify-content:flex-start; }
        }
    </style>
</head>
<body>

<div class="app">
    <aside class="sidebar" aria-label="Sidebar Navigation">
        <div class="brand">
            <div class="logo">VM</div>
            <div>
                <h1>Inventory System</h1>
                <p>Guest (read-only)</p>
            </div>
        </div>

        <nav class="nav">
            <a class="active" href="#" aria-current="page">
                <span class="icon">🏠</span>
                <span>Dashboard</span>
            </a>
            <a href="guest_vehicle.php">
                <span class="icon">🚗</span>
                <span>Vehicles</span>
            </a>
            <a href="guest_machine.php">
                <span class="icon">🏗️</span>
                <span>Machines</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="pill">
                <span>Access level</span>
                <span class="badge">GUEST</span>
            </div>
            <a class="btn secondary" href="../login.php">Login</a>
            <a class="btn secondary" href="../index.php">Back</a>

        </div>
    </aside>

    <main class="main">
        <div class="topbar">
            <div class="title">
                <h2>Guest Dashboard</h2>
                <p>
                    You can browse inventory information, but you can’t add or edit records.
                </p>
            </div>

            <div class="cta">
                <a class="btn secondary" href="../login.php">Login</a>
            </div>
        </div>

        <section class="content">
            <div class="hero">
                <h3>Welcome, Guest</h3>
                <p>
                    Explore vehicles and equipment currently registered in the system.
                    To manage inventory (add/edit/delete), please log in with an authorized account.
                </p>
            </div>

            <div class="grid">
                <article class="card">
                    <div class="mini">
                        <div>
                            <h4>Vehicles</h4>
                            <p>Browse registered vehicles and their status.</p>
                        </div>
                        <span class="chip">Read-only</span>
                    </div>
                </article>

                <article class="card">
                    <div class="mini">
                        <div>
                            <h4>Machines</h4>
                            <p>View machines, calibration dates, and locations.</p>
                        </div>
                        <span class="chip">Read-only</span>
                    </div>
                </article>

                <article class="card">
                    <div class="mini">
                        <div>
                            <h4>Inventory Summary</h4>
                            <p>See a quick overview of inventory data.</p>
                        </div>
                        <span class="chip">Read-only</span>
                    </div>
                </article>
            </div>

            <div class="footer">
                &copy; <?= date('Y') ?> Vehicle and Machine Inventory System
            </div>
        </section>
    </main>
</div>

</body>
</html>