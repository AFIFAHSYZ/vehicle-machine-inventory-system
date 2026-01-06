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
    $companyId       = (int)($_POST["companyid"] ?? 0);
    $serialNo        = trim($_POST["serialno"] ?? "");
    $model           = trim($_POST["model"] ?? "");
    $codeNo          = trim($_POST["codeno"] ?? "");
    $equipmentType   = trim($_POST["equipmenttype"] ?? "");
    $dateCalibration = $_POST["datecalibration"] ?? null;
    $nextCalibration = $_POST["nextcalibration"] ?? null;
    $certNo          = trim($_POST["certificationno"] ?? "");
    $location        = trim($_POST["location"] ?? "");
    $status          = trim($_POST["status"] ?? "");

    // Drill specifics (optional)
    $itemDesc     = trim($_POST["itemdescription"] ?? "");
    $markingNo    = trim($_POST["markingno"] ?? "");
    $datePurchase = $_POST["dateofpurchase"] ?? null;
    $dateDisposal = $_POST["dateofdisposal"] ?? null;
    $remark       = trim($_POST["remark"] ?? "");

    // Choose behavior:
    $approval = "approved";

    if ($companyId <= 0 || $serialNo === "") {
        $error = "Company and Serial No are required.";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO equipment
                (serialno, model, codeno, equipmenttype, datecalibration, nextcalibration, certificationno, location, status,
                 companyid, createdby, updatedby, approvalstatus, approvedby, approveddate)
                VALUES
                (:serialno, :model, :codeno, :equipmenttype, :datecalibration, :nextcalibration, :certificationno, :location, :status,
                 :companyid, :uid, :uid, :approval, :approvedby, CASE WHEN :approval = 'approved' THEN NOW() ELSE NULL END)
                RETURNING equipmentid
            ");
            $stmt->execute([
                ":serialno" => $serialNo,
                ":model" => ($model===""?null:$model),
                ":codeno" => ($codeNo===""?null:$codeNo),
                ":equipmenttype" => ($equipmentType===""?null:$equipmentType),
                ":datecalibration" => ($dateCalibration===""?null:$dateCalibration),
                ":nextcalibration" => ($nextCalibration===""?null:$nextCalibration),
                ":certificationno" => ($certNo===""?null:$certNo),
                ":location" => ($location===""?null:$location),
                ":status" => ($status===""?null:$status),
                ":companyid" => $companyId,
                ":uid" => $userId,
                ":approval" => $approval,
                ":approvedby" => ($approval === "approved" ? $userId : null),
            ]);
            $equipmentId = $stmt->fetchColumn();

            $hasDrill = ($itemDesc !== "" || $markingNo !== "" || $datePurchase !== "" || $dateDisposal !== "" || $remark !== "");
            if ($hasDrill) {
                $drill = $pdo->prepare("
                    INSERT INTO drillspecifics (drillid, itemdescription, markingno, dateofpurchase, dateofdisposal, remark)
                    VALUES (:drillid, :itemdescription, :markingno, :dateofpurchase, :dateofdisposal, :remark)
                ");
                $drill->execute([
                    ":drillid" => $equipmentId,
                    ":itemdescription" => ($itemDesc===""?null:$itemDesc),
                    ":markingno" => ($markingNo===""?null:$markingNo),
                    ":dateofpurchase" => ($datePurchase===""?null:$datePurchase),
                    ":dateofdisposal" => ($dateDisposal===""?null:$dateDisposal),
                    ":remark" => ($remark===""?null:$remark),
                ]);
            }

            $pdo->commit();
            $success = "Machine added successfully ({$approval}).";
            $_POST = [];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
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
    <title>Add Machine | Authorized</title>
    <link rel="stylesheet" href="../../css/guest_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

</head>
<body>
<div class="app">
<?php include "sidebar.php";?>

    <main class="main">
        <div class="header">
            <div>
                <h2>Add Machine / Equipment</h2>
                <div class="sub">Creates a new equipment record (default: <b>approved</b>).</div>
            </div>
            <a class="btn secondary" href="machine.php">Back</a>
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
                        <label>Serial No *</label>
                        <input class="input" name="serialno" value="<?= htmlspecialchars($_POST["serialno"] ?? "") ?>" required>
                    </div>

                    <div><label>Equipment Type</label><input class="input" name="equipmenttype" value="<?= htmlspecialchars($_POST["equipmenttype"] ?? "") ?>"></div>
                    <div><label>Status</label><input class="input" name="status" value="<?= htmlspecialchars($_POST["status"] ?? "") ?>"></div>
                    <div><label>Model</label><input class="input" name="model" value="<?= htmlspecialchars($_POST["model"] ?? "") ?>"></div>
                    <div><label>Code No</label><input class="input" name="codeno" value="<?= htmlspecialchars($_POST["codeno"] ?? "") ?>"></div>
                    <div><label>Location</label><input class="input" name="location" value="<?= htmlspecialchars($_POST["location"] ?? "") ?>"></div>
                    <div><label>Certification No</label><input class="input" name="certificationno" value="<?= htmlspecialchars($_POST["certificationno"] ?? "") ?>"></div>
                    <div><label>Date Calibration</label><input class="input" type="date" name="datecalibration" value="<?= htmlspecialchars($_POST["datecalibration"] ?? "") ?>"></div>
                    <div><label>Next Calibration</label><input class="input" type="date" name="nextcalibration" value="<?= htmlspecialchars($_POST["nextcalibration"] ?? "") ?>"></div>

                    <div class="full">
                        <div style="font-weight:1000;margin:.25rem 0 .35rem">Drill specifics (optional)</div>
                    </div>

                    <div class="full">
                        <label>Item Description</label>
                        <textarea class="input" name="itemdescription"><?= htmlspecialchars($_POST["itemdescription"] ?? "") ?></textarea>
                    </div>
                    <div><label>Marking No</label><input class="input" name="markingno" value="<?= htmlspecialchars($_POST["markingno"] ?? "") ?>"></div>
                    <div><label>Remark</label><input class="input" name="remark" value="<?= htmlspecialchars($_POST["remark"] ?? "") ?>"></div>
                    <div><label>Date of Purchase</label><input class="input" type="date" name="dateofpurchase" value="<?= htmlspecialchars($_POST["dateofpurchase"] ?? "") ?>"></div>
                    <div><label>Date of Disposal</label><input class="input" type="date" name="dateofdisposal" value="<?= htmlspecialchars($_POST["dateofdisposal"] ?? "") ?>"></div>
                </div>

                <div style="margin-top:1rem;display:flex;gap:.7rem;flex-wrap:wrap">
                    <button class="btn" type="submit">Save</button>
                    <a class="btn secondary" href="machine.php">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</div>
</body>
</html>