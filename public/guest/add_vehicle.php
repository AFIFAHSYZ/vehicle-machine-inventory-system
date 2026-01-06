<?php
session_start();
require_once __DIR__ . "/../../config/db.php";



$_SESSION["role"] = $_SESSION["role"] ?? "guest";

$error = "";
$success = "";

// Load companies
$companies = [];
try {
    $companies = $pdo->query("SELECT companyid, companyname FROM company ORDER BY companyname ASC")
        ->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Failed to load companies: " . $e->getMessage();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $companyId    = (int)($_POST["companyid"] ?? 0);
    $plateNumber  = trim($_POST["platenumber"] ?? "");
    $model        = trim($_POST["model"] ?? "");
    $vehicleType  = trim($_POST["vehicletype"] ?? "");
    $roadTaxDue   = $_POST["roadtaxdue"] ?? null;
    $insuranceDue = $_POST["insurancedue"] ?? null;
    $driver       = trim($_POST["driver"] ?? "");
    $owner        = trim($_POST["owner"] ?? "");
    $ownerIC      = trim($_POST["owneric"] ?? "");
    $status       = trim($_POST["status"] ?? "");

    if ($companyId <= 0 || $plateNumber === "") {
        $error = "Company and Plate Number are required.";
    } else {
        try {
            // Validate company exists
            $chk = $pdo->prepare("SELECT 1 FROM company WHERE companyid = :id");
            $chk->execute([":id" => $companyId]);
            if (!$chk->fetchColumn()) {
                throw new Exception("Selected company is invalid.");
            }

            // Insert as pending; guest has no createdby
            $stmt = $pdo->prepare("
                INSERT INTO vehicle
                (platenumber, model, vehicletype, roadtaxdue, insurancedue, driver, owner, owneric, status, companyid, createdby, approvalstatus)
                VALUES
                (:platenumber, :model, :vehicletype, :roadtaxdue, :insurancedue, :driver, :owner, :owneric, :status, :companyid, NULL, 'pending')
            ");
            $stmt->execute([
                ":platenumber"  => $plateNumber,
                ":model"        => ($model === "" ? null : $model),
                ":vehicletype"  => ($vehicleType === "" ? null : $vehicleType),
                ":roadtaxdue"   => ($roadTaxDue === "" ? null : $roadTaxDue),
                ":insurancedue" => ($insuranceDue === "" ? null : $insuranceDue),
                ":driver"       => ($driver === "" ? null : $driver),
                ":owner"        => ($owner === "" ? null : $owner),
                ":owneric"      => ($ownerIC === "" ? null : $ownerIC),
                ":status"       => ($status === "" ? null : $status),
                ":companyid"    => $companyId,
            ]);

            $success = "Submitted successfully! Status: Pending approval.";
            $_POST = [];
        } catch (Exception $e) {
            $error = "Submit failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Add Vehicle | Pending Approval</title>
        <link rel="stylesheet" href="../../css/guest_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

</head>
<body>
<div class="app">
<?php include "g_sidebar.php"; ?>
    <main class="main">
        <div class="header">
            <div>
                <h2>Add Vehicle</h2>
                <div class="sub">This will be saved as <b>pending</b> until approved by an authorized user.</div>
            </div>
            <div class="actions">
                <a class="btn secondary" href="view_car.php">Back to List</a>
            </div>
        </div>

        <div class="card">
            <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <form method="POST">
                <div class="grid">
                    <div>
                        <label>Company *</label>
                        <select class="input" name="companyid" required>
                            <option value="">-- Select Company --</option>
                            <?php foreach ($companies as $c): ?>
                                <option value="<?= (int)$c["companyid"] ?>" <?= ((string)($_POST["companyid"] ?? "") === (string)$c["companyid"]) ? "selected" : "" ?>>
                                    <?= htmlspecialchars($c["companyname"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Plate Number *</label>
                        <input class="input" name="platenumber" value="<?= htmlspecialchars($_POST["platenumber"] ?? "") ?>" required />
                    </div>

                    <div>
                        <label>Model</label>
                        <input class="input" name="model" value="<?= htmlspecialchars($_POST["model"] ?? "") ?>" />
                    </div>

                    <div>
                        <label>Vehicle Type</label>
                        <input class="input" name="vehicletype" value="<?= htmlspecialchars($_POST["vehicletype"] ?? "") ?>" placeholder="e.g. B2, D" />
                    </div>

                    <div>
                        <label>Status</label>
                        <input class="input" name="status" value="<?= htmlspecialchars($_POST["status"] ?? "") ?>" />
                    </div>

                    <div>
                        <label>Driver</label>
                        <input class="input" name="driver" value="<?= htmlspecialchars($_POST["driver"] ?? "") ?>" />
                    </div>

                    <div>
                        <label>Owner</label>
                        <input class="input" name="owner" value="<?= htmlspecialchars($_POST["owner"] ?? "") ?>" />
                    </div>

                    <div>
                        <label>Owner IC</label>
                        <input class="input" name="owneric" value="<?= htmlspecialchars($_POST["owneric"] ?? "") ?>" />
                    </div>

                    <div>
                        <label>Road Tax Due</label>
                        <input class="input" type="date" name="roadtaxdue" value="<?= htmlspecialchars($_POST["roadtaxdue"] ?? "") ?>" />
                    </div>

                    <div>
                        <label>Insurance Due</label>
                        <input class="input" type="date" name="insurancedue" value="<?= htmlspecialchars($_POST["insurancedue"] ?? "") ?>" />
                    </div>
                </div>

                <div class="actions">
                    <button class="btn" type="submit">Submit (Pending)</button>
                    <a class="btn secondary" href="view_car.php">Cancel</a>
                </div>
            </form>
        </div>

        <div class="muted" style="text-align:center;margin-top:1rem;">
            &copy; <?= date('Y') ?> Vehicle and Machine Inventory System
        </div>
    </main>
</div>
</body>
</html>