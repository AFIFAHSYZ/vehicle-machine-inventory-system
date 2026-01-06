<?php
session_start();

$_SESSION['role'] = 'guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Guest Dashboard | Vehicle Inventory System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../css/guest_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
</head>
<body>

<div class="app">
<?php include "g_sidebar.php"; ?>
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