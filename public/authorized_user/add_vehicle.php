<?php
session_start();
if (($_SESSION["role"] ?? "") !== "authorized user") { header("Location: ../login.php"); exit; }

require_once __DIR__ . "/../../config/db.php";

$currentPage = basename($_SERVER["PHP_SELF"]);
$error = "";
$success = "";

$userId = $_SESSION["userid"] ?? null;

$companies = $pdo->query("SELECT companyid, companyname FROM company ORDER BY companyname ASC")->fetchAll(PDO::FETCH_ASSOC);

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

    // Choose behavior:
    // - If you want authorized user entries auto-approved, set $approval = 'approved'
    // - If you want them still pending, keep 'pending'
    $approval = "approved";

    if ($companyId <= 0 || $plateNumber === "") {
        $error = "Company and Plate Number are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO vehicle
                (platenumber, model, vehicletype, roadtaxdue, insurancedue, driver, owner, owneric, status, companyid,
                 createdby, updatedby, approvalstatus, approvedby, approveddate)
                VALUES
                (:platenumber, :model, :vehicletype, :roadtaxdue, :insurancedue, :driver, :owner, :owneric, :status, :companyid,
                 :uid, :uid, :approval, :approvedby, CASE WHEN :approval = 'approved' THEN NOW() ELSE NULL END)
            ");
            $stmt->execute([
                ":platenumber" => $plateNumber,
                ":model" => ($model===""?null:$model),
                ":vehicletype" => ($vehicleType===""?null:$vehicleType),
                ":roadtaxdue" => ($roadTaxDue===""?null:$roadTaxDue),
                ":insurancedue" => ($insuranceDue===""?null:$insuranceDue),
                ":driver" => ($driver===""?null:$driver),
                ":owner" => ($owner===""?null:$owner),
                ":owneric" => ($ownerIC===""?null:$ownerIC),
                ":status" => ($status===""?null:$status),
                ":companyid" => $companyId,
                ":uid" => $userId,
                ":approval" => $approval,
                ":approvedby" => ($approval === "approved" ? $userId : null),
            ]);

            $success = "Vehicle added successfully ({$approval}).";
            $_POST = [];
        } catch (PDOException $e) {
            $error = "Add failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Add Vehicle | Authorized</title>
    <link rel="stylesheet" href="../../css/guest_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

</head>
<body>
<div class="app">
<?php include "sidebar.php";?>

    <main class="main">
        <div class="header">
            <div>
                <h2>Add Vehicle</h2>
                <div class="sub">Creates a new vehicle record (default: <b>approved</b>).</div>
            </div>
            <a class="btn secondary" href="vehicle.php">Back</a>
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

                    <div><label>Model</label><input class="input" name="model" value="<?= htmlspecialchars($_POST["model"] ?? "") ?>"></div>
                    <div><label>Vehicle Type</label><input class="input" name="vehicletype" value="<?= htmlspecialchars($_POST["vehicletype"] ?? "") ?>"></div>
                    <div><label>Status</label><input class="input" name="status" value="<?= htmlspecialchars($_POST["status"] ?? "") ?>"></div>
                    <div><label>Driver</label><input class="input" name="driver" value="<?= htmlspecialchars($_POST["driver"] ?? "") ?>"></div>
                    <div><label>Owner</label><input class="input" name="owner" value="<?= htmlspecialchars($_POST["owner"] ?? "") ?>"></div>
                    <div><label>Owner IC</label><input class="input" name="owneric" value="<?= htmlspecialchars($_POST["owneric"] ?? "") ?>"></div>
                    <div><label>Road Tax Due</label><input class="input" type="date" name="roadtaxdue" value="<?= htmlspecialchars($_POST["roadtaxdue"] ?? "") ?>"></div>
                    <div><label>Insurance Due</label><input class="input" type="date" name="insurancedue" value="<?= htmlspecialchars($_POST["insurancedue"] ?? "") ?>"></div>
                </div>

                <div style="margin-top:1rem;display:flex;gap:.7rem;flex-wrap:wrap">
                    <button class="btn" type="submit">Save</button>
                    <a class="btn secondary" href="vehicle.php">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</div>
</body>
</html>