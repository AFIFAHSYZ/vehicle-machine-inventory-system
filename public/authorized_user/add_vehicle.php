<?php
session_start();
if (($_SESSION["role"] ?? "") !== "authorized user") { header("Location: ../login.php"); exit; }

require_once __DIR__ . "/../../config/db.php";

$currentPage = basename($_SERVER["PHP_SELF"]);
$error = "";
$success = "";

$userId = $_SESSION["userid"] ?? null;

$companies = $pdo->query("SELECT companyid, companyname FROM company ORDER BY companyname ASC")
    ->fetchAll(PDO::FETCH_ASSOC);

$STATUS_OPTIONS = ["ACTIVE", "INACTIVE", "DAMAGE", "DISPOSAL", "SOLD", "LOST"];
$TYPE_OPTIONS   = ["CAR", "MOTORCYCLE"];

function upper($s): string {
    $s = trim((string)$s);
    if ($s === "") return "";
    return function_exists("mb_strtoupper") ? mb_strtoupper($s, "UTF-8") : strtoupper($s);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $companyId = (int)($_POST["companyid"] ?? 0);

    $plateNumber = upper($_POST["platenumber"] ?? "");
    $model       = upper($_POST["model"] ?? "");

    // dropdown values
    $vehicleType = upper($_POST["vehicletype"] ?? "");
    $statusText  = upper($_POST["status"] ?? "");

    $driver      = upper($_POST["driver"] ?? "");
    $owner       = upper($_POST["owner"] ?? "");
    $ownerIC     = upper($_POST["owneric"] ?? "");

    $roadTaxDue   = $_POST["roadtaxdue"] ?? null;
    $insuranceDue = $_POST["insurancedue"] ?? null;

    // approvals
    $approval = "approved"; // or "pending"
    $approvedBy = ($approval === "approved") ? $userId : null;
    $approvedDate = ($approval === "approved") ? date("Y-m-d H:i:s") : null;

    if ($companyId <= 0 || $plateNumber === "") {
        $error = "Company and Plate Number are required.";
    } elseif ($vehicleType !== "" && !in_array($vehicleType, $TYPE_OPTIONS, true)) {
        $error = "Invalid Vehicle Type.";
    } elseif ($statusText !== "" && !in_array($statusText, $STATUS_OPTIONS, true)) {
        $error = "Invalid Status.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO vehicle
                (platenumber, model, vehicletype, roadtaxdue, insurancedue, driver, owner, owneric, status, companyid,
                 createdby, updatedby, approvalstatus, approvedby, approveddate)
                VALUES
                (:platenumber, :model, :vehicletype, :roadtaxdue, :insurancedue, :driver, :owner, :owneric, :status, :companyid,
                 :uid, :uid, :approvalstatus, :approvedby, :approveddate)
            ");
            $stmt->execute([
                ":platenumber"     => $plateNumber,
                ":model"           => ($model === "" ? null : $model),
                ":vehicletype"     => ($vehicleType === "" ? null : $vehicleType),
                ":roadtaxdue"      => ($roadTaxDue === "" ? null : $roadTaxDue),
                ":insurancedue"    => ($insuranceDue === "" ? null : $insuranceDue),
                ":driver"          => ($driver === "" ? null : $driver),
                ":owner"           => ($owner === "" ? null : $owner),
                ":owneric"         => ($ownerIC === "" ? null : $ownerIC),
                ":status"          => ($statusText === "" ? null : $statusText),
                ":companyid"       => $companyId,
                ":uid"             => $userId,
                ":approvalstatus"  => $approval,
                ":approvedby"      => $approvedBy,
                ":approveddate"    => $approvedDate,
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
    <style>
        input.uc { text-transform: uppercase; }
    </style>
</head>
<body>
<div class="app">
<?php include "sidebar.php";?>

    <main class="main">
        <div class="header">
            <div>
                <h2>Add Vehicle</h2>
                <div class="sub">Creates a new vehicle record</div>
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
                        <input class="input uc" name="platenumber" value="<?= htmlspecialchars($_POST["platenumber"] ?? "") ?>" required />
                    </div>

                    <div>
                        <label>Model</label>
                        <input class="input uc" name="model" value="<?= htmlspecialchars($_POST["model"] ?? "") ?>">
                    </div>

                    <div>
                        <label>Vehicle Type</label>
                        <select class="input" name="vehicletype">
                            <option value="">-- Select --</option>
                            <?php foreach ($TYPE_OPTIONS as $opt): ?>
                                <option value="<?= htmlspecialchars($opt) ?>"
                                    <?= (upper($_POST["vehicletype"] ?? "") === $opt) ? "selected" : "" ?>>
                                    <?= htmlspecialchars($opt) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Status</label>
                        <select class="input" name="status">
                            <option value="">-- Select --</option>
                            <?php foreach ($STATUS_OPTIONS as $opt): ?>
                                <option value="<?= htmlspecialchars($opt) ?>"
                                    <?= (upper($_POST["status"] ?? "") === $opt) ? "selected" : "" ?>>
                                    <?= htmlspecialchars($opt) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div><label>Driver</label><input class="input uc" name="driver" value="<?= htmlspecialchars($_POST["driver"] ?? "") ?>"></div>
                    <div><label>Owner</label><input class="input uc" name="owner" value="<?= htmlspecialchars($_POST["owner"] ?? "") ?>"></div>
                    <div><label>Owner IC</label><input class="input uc" name="owneric" value="<?= htmlspecialchars($_POST["owneric"] ?? "") ?>"></div>
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