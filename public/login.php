<?php
session_start();
require_once __DIR__ . "/../config/db.php"; // adjust if your file location differs

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $pass  = $_POST["password"] ?? "";

    if ($email === "" || $pass === "") {
        $error = "Please enter email and password.";
    } else {
        try {
            // Use lowercase column names that exist in your table
            $stmt = $pdo->prepare('
                SELECT userid, fullname, email, passwordhash, role, companyid
                FROM "User"
                WHERE email = :email
                LIMIT 1
            ');
            $stmt->execute([":email" => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = "Invalid email or password.";
            } else {
                if (!password_verify($pass, $user["passwordhash"])) {
                    $error = "Invalid email or password.";
                } else {
                    // Login OK
                    $_SESSION["userid"]    = (int)$user["userid"];
                    $_SESSION["role"]      = $user["role"];      // 'guest' or 'authorized user'
                    $_SESSION["fullname"]  = $user["fullname"];
                    $_SESSION["email"]     = $user["email"];
                    $_SESSION["companyid"] = (int)$user["companyid"];

                    // Redirect based on role (change destinations as you like)
                    if ($user["role"] === "authorized user") {
                        header("Location: authorized_user/dashboard.php");
                    } else {
                        header("Location: guest/dashboard.php");
                    }
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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
  <main class="auth-main">
    <section class="auth-card">
      <h2>Login</h2>

      <?php if ($error): ?>
        <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
        <div style="height: 0.9rem;"></div>
      <?php endif; ?>

      <form method="POST" class="form-grid" autocomplete="on">
        <div class="form-row">
          <label for="email">Email</label>
          <input class="input" type="email" id="email" name="email" required>
        </div>

        <div class="form-row">
          <label for="password">Password</label>
          <input class="input" type="password" id="password" name="password" required>
        </div>

        <div class="auth-actions">
          <button type="submit" class="btn-primary">Login</button>
        </div>

        <div class="link-row" style="margin-top:.75rem;">
          No account? <a href="register.php">Register</a>
        </div>
      </form>
    </section>
  </main>
</body>
</html>