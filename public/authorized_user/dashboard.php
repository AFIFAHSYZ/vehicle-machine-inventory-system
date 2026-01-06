<?php
session_start();

// Only authorized users
if (($_SESSION["role"] ?? "") !== "authorized user") {
    header("Location: ../login.php");
    exit;
}

$fullName = $_SESSION["fullname"] ?? "Authorized User";
$email    = $_SESSION["email"] ?? "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Authorized Dashboard | Vehicle & Machine Inventory</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../css/guest_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

</head>
<body>
<div class="app">
<?php include "sidebar.php";?>

    <main class="main">
        <div class="header">
            <div>
                <h2>Welcome, <?= htmlspecialchars($fullName) ?></h2>
                <p>
                    You are signed in as an <strong>authorized user</strong>.
                    Use the navigation to manage inventory activities.
                    <?php if ($email): ?>Logged in as: <?= htmlspecialchars($email) ?><?php endif; ?>
                </p>
            </div>

            <div style="display:flex; gap:.6rem; flex-wrap:wrap;">
                <a class="btn" href="vehicle.php">Open Vehicles</a>
                <a class="btn secondary" href="machine.php">Open Machines</a>
            </div>
        </div>

        <section class="content">
            <div class="hero">
                <h3>Quick actions</h3>
                <p class="muted">
                    Browse existing approved assets, or submit new entries to be reviewed and approved.
                </p>
            </div>

            <div class="grid">
                <div class="card">
                    <h4>Vehicles</h4>
                    <p>Browse approved vehicle records and submit new vehicle entries.</p>
                    <div class="actions">
                        <a class="btn" href="vehicle.php">View</a>
                        <a class="btn secondary" href="add_vehicle.php">Submit</a>
                    </div>
                </div>

                <div class="card">
                    <h4>Machines / Equipment</h4>
                    <p>Browse approved machines/equipment and submit new equipment entries.</p>
                    <div class="actions">
                        <a class="btn" href="machine.php">View</a>
                        <a class="btn secondary" href="add_machine.php">Submit</a>
                    </div>
                </div>

            </div>

            <div class="footer">
                &copy; <?= date('Y') ?> Vehicle and Machine Inventory System
            </div>
        </section>
    </main>
</div>
</body>
</html>