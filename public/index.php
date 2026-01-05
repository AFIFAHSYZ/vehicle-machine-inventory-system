<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login/Register | Vehicle and Machine Inventory System</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<style>
main .cta-buttons::before{
    content: "Welcome! Log in or register to start managing your inventory.";
    display: block;
    color: var(--muted);
    font-size: 1.05rem;
    line-height: 1.6;
    margin-bottom: 0.25rem;
}

/* ====== Buttons ====== */
.cta-buttons a {width: 100%;max-width: 320px;display: inline-flex;align-items: center;justify-content: center; padding: 0.9rem 1.4rem;border-radius: 999px;font-size: 1rem;
    font-weight: 700; text-decoration: none; color: #fff;background: linear-gradient(135deg, var(--primary1), var(--primary2)); box-shadow: 0 10px 20px rgba(108, 99, 255, 0.25);
    transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;}
.cta-buttons a:hover {transform: translateY(-2px); box-shadow: 0 14px 26px rgba(108, 99, 255, 0.35);filter: brightness(1.02);}
.cta-buttons a:active {transform: translateY(0px) scale(0.99);}
.cta-buttons a:last-child{background: linear-gradient(135deg, #1f2430, #3a3f55);box-shadow: 0 10px 20px rgba(31, 36, 48, 0.20);}
.cta-buttons a:last-child:hover{box-shadow: 0 14px 26px rgba(31, 36, 48, 0.28);}
.cta-buttons a:focus-visible {outline: 3px solid rgba(108, 99, 255, 0.35);outline-offset: 3px;}

@media (max-width: 520px) {header {padding-top: 2.2rem;}
    main .cta-buttons {padding: 1.5rem;border-radius: 18px;}
    main .cta-buttons::before{font-size: 1rem;}
}
</style>

<body>
    <header>
        <h1>Vehicle and Machine Inventory System</h1>
        <p>Effortlessly manage your fleet with a modern touch.</p>
    </header>

    <main>
        <div class="cta-buttons">
            <a href="login.php">Login</a>
            <a href="guest/guest_dashboard.php">Login as Guest</a>
            <a href="register.php">Register</a>
        </div>
    </main>

    <footer>
        <p>&copy; <?= date('Y') ?> Vehicle and Machine Inventory System</p>
    </footer>
</body>
</html>