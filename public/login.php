<?php
session_start();

require_once __DIR__ . "../../config/db.php"; 

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $passwordInput = $_POST["password"] ?? "";

    if ($email === "" || $passwordInput === "") {
        $error = "Please enter your email and password.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT id, name, email, password
                FROM users
                WHERE email = :email
                LIMIT 1
            ");
            $stmt->execute([":email" => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = "No account found with that email.";
            } else {
                // Verify hashed password
                if (!password_verify($passwordInput, $user["password"])) {
                    $error = "Incorrect password. Please try again.";
                } else {
                    // Login success
                    session_regenerate_id(true);

                    $_SESSION["user_id"] = $user["id"];
                    $_SESSION["user_name"] = $user["name"];
                    $_SESSION["user_email"] = $user["email"];

                    // Redirect after login (change to your dashboard page if you have one)
                    header("Location: index.php");
                    exit;
                }
            }
        } catch (PDOException $e) {
            $error = "Login failed. " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Vehicle and Machine Inventory System</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<header>
    <h1>Vehicle and Machine Inventory System</h1>
    <p>Securely sign in to manage your inventory.</p>
</header>

<main class="auth-main">
    <section class="auth-card" aria-labelledby="loginTitle">
        <h2 id="loginTitle">Login</h2>
        <p class="auth-subtitle">Welcome back. Please enter your details.</p>

        <?php if ($error): ?>
            <div class="alert alert-error" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
            <div style="height: 0.9rem;"></div>
        <?php endif; ?>

        <form method="POST" class="form-grid" autocomplete="on">
            <div class="form-row">
                <label for="email">Email</label>
                <input class="input" type="email" id="email" name="email"
                       placeholder="you@example.com"
                       value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                       required>
            </div>

            <div class="form-row">
                <label for="password">Password</label>
                <input class="input" type="password" id="password" name="password"
                       placeholder="Enter your password"
                       required>
            </div>

            <div class="auth-actions">
                <button type="submit" class="btn-primary">Sign in</button>

                <div class="link-row">
                    Don’t have an account? <a href="register.php">Create one</a>
                </div>

                <div class="link-row">
                    <a href="index.php">Back to Home</a>
                </div>
            </div>
        </form>
    </section>
</main>

<footer>
    <p>&copy; <?= date('Y') ?> Vehicle and Machine Inventory System</p>
</footer>
</body>
</html>