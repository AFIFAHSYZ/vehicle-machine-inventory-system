<?php
session_start();

require_once __DIR__ . "../../config/db.php"; 

$error = "";
$success = "";


$companies = [];
try {
    $stmtCompanies = $pdo->query('SELECT companyid, companyname FROM company ORDER BY companyname ASC');
    $companies = $stmtCompanies->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Failed to load companies: " . $e->getMessage();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullName  = trim($_POST["full_name"] ?? "");
    $email     = trim($_POST["email"] ?? "");
    $role      = trim($_POST["role"] ?? "User");
    $companyId = (int)($_POST["company_id"] ?? 0);

    $passwordInput = $_POST["password"] ?? "";
    $confirm       = $_POST["confirm_password"] ?? "";

    if ($email === "" || $passwordInput === "" || $confirm === "" || $role === "" || $companyId <= 0) {
        $error = "Please fill in all required fields (Email, Password, Role, Company).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($passwordInput) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($passwordInput !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        try {
            // Validate CompanyID exists (LOWERCASE table/column)
            $checkCompany = $pdo->prepare('SELECT 1 FROM company WHERE companyid = :companyid LIMIT 1');
            $checkCompany->execute([":companyid" => $companyId]);

            if (!$checkCompany->fetchColumn()) {
                $error = "Selected company is invalid.";
            } else {
                $checkEmail = $pdo->prepare('SELECT 1 FROM "User" WHERE "Email" = :email LIMIT 1');
                $checkEmail->execute([":email" => $email]);

                if ($checkEmail->fetchColumn()) {
                    $error = "That email is already registered. Please login instead.";
                } else {
                    $passwordHash = password_hash($passwordInput, PASSWORD_DEFAULT);

                    $insert = $pdo->prepare('
                        INSERT INTO "User" ("PasswordHash","FullName","Email","Role","CompanyID")
                        VALUES (:passwordhash, :fullname, :email, :role, :companyid)
                    ');

                    $insert->execute([
                        ":passwordhash" => $passwordHash,
                        ":fullname"     => ($fullName === "" ? null : $fullName),
                        ":email"        => $email,
                        ":role"         => $role,
                        ":companyid"    => $companyId,
                    ]);

                    $success = "Account created successfully! You can now log in.";
                    $_POST = [];
                }
            }
        } catch (PDOException $e) {
            $error = "Registration failed. " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Vehicle and Machine Inventory System</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<header>
    <h1>Vehicle and Machine Inventory System</h1>
    <p>Create your account to start managing vehicles and machines.</p>
</header>

<main class="auth-main">
    <section class="auth-card" aria-labelledby="registerTitle">
        <h2 id="registerTitle">Register</h2>
        <p class="auth-subtitle">Create an account in a few seconds.</p>

        <?php if ($error): ?>
            <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
            <div style="height: 0.9rem;"></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success" role="alert"><?= htmlspecialchars($success) ?></div>
            <div style="height: 0.9rem;"></div>
        <?php endif; ?>

        <form method="POST" class="form-grid" autocomplete="on">
            <div class="form-row">
                <label for="full_name">Full Name</label>
                <input class="input" type="text" id="full_name" name="full_name"
                       placeholder="Ali Bin Ahmad"
                       value="<?= htmlspecialchars($_POST["full_name"] ?? "") ?>">
            </div>

            <div class="form-row">
                <label for="email">Email *</label>
                <input class="input" type="email" id="email" name="email"
                       placeholder="you@gmail.com"
                       value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                       required>
            </div>

            <div class="form-row">
                <label for="role">Role *</label>
                <input class="input" type="text" id="role" name="role"
                       placeholder="User"
                       value="<?= htmlspecialchars($_POST["role"] ?? "User") ?>"
                       required>
            </div>

            <div class="form-row">
                <label for="company_id">Company *</label>
                <select class="input" id="company_id" name="company_id" required>
                    <option value="">-- Select Company --</option>
                    <?php foreach ($companies as $c): ?>
                        <option value="<?= (int)$c["companyid"] ?>"
                            <?= ((string)$c["companyid"] === (string)($_POST["company_id"] ?? "")) ? "selected" : "" ?>>
                            <?= htmlspecialchars($c["companyname"]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="password">Password *</label>
                <input class="input" type="password" id="password" name="password"
                       placeholder="Create a password"
                       required>
                <div class="helper">Use at least 6 characters.</div>
            </div>

            <div class="form-row">
                <label for="confirm_password">Confirm Password *</label>
                <input class="input" type="password" id="confirm_password" name="confirm_password"
                       placeholder="Repeat your password"
                       required>
            </div>

            <div class="auth-actions">
                <button type="submit" class="btn-primary">Create account</button>
                <div class="link-row">
                    Already have an account? <a href="login.php">Login</a>
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