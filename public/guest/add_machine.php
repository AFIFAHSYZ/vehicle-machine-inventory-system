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

    if ($companyId <= 0 || $serialNo === "") {
        $error = "Company and Serial No are required.";
    } else {
        try {
            $pdo->beginTransaction();

            // Validate company exists
            $chk = $pdo->prepare("SELECT 1 FROM company WHERE companyid = :id");
            $chk->execute([":id" => $companyId]);
            if (!$chk->fetchColumn()) {
                throw new Exception("Selected company is invalid.");
            }

            // Insert equipment as pending; guest has no createdby
            $stmt = $pdo->prepare("
                INSERT INTO equipment
                (serialno, model, codeno, equipmenttype, datecalibration, nextcalibration, certificationno, location, status, companyid, createdby, approvalstatus)
                VALUES
                (:serialno, :model, :codeno, :equipmenttype, :datecalibration, :nextcalibration, :certificationno, :location, :status, :companyid, NULL, 'pending')
                RETURNING equipmentid
            ");
            $stmt->execute([
                ":serialno"        => $serialNo,
                ":model"           => ($model === "" ? null : $model),
                ":codeno"          => ($codeNo === "" ? null : $codeNo),
                ":equipmenttype"   => ($equipmentType === "" ? null : $equipmentType),
                ":datecalibration" => ($dateCalibration === "" ? null : $dateCalibration),
                ":nextcalibration" => ($nextCalibration === "" ? null : $nextCalibration),
                ":certificationno" => ($certNo === "" ? null : $certNo),
                ":location"        => ($location === "" ? null : $location),
                ":status"          => ($status === "" ? null : $status),
                ":companyid"       => $companyId,
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
                    ":itemdescription" => ($itemDesc === "" ? null : $itemDesc),
                    ":markingno" => ($markingNo === "" ? null : $markingNo),
                    ":dateofpurchase" => ($datePurchase === "" ? null : $datePurchase),
                    ":dateofdisposal" => ($dateDisposal === "" ? null : $dateDisposal),
                    ":remark" => ($remark === "" ? null : $remark),
                ]);
            }

            $pdo->commit();

            $success = "Submitted successfully! Status: Pending approval.";
            $_POST = [];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
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
    <title>Add Machine | Pending Approval</title>
    <link rel="stylesheet" href="../../css/guest_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
</head>
<body>
<div class="app">
<?php include "g_sidebar.php"; ?>

    <main class="main">
        <div class="header">
            <div>
                <h2>Add Machine / Equipment</h2>
                <div class="sub">This will be saved as <b>pending</b> until approved by an authorized user.</div>
            </div>
            <div class="actions">
                <a class="btn secondary" href="view_machine.php">Back to List</a>
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
                        <label>Serial No *</label>
                        <input class="input" name="serialno" value="<?= htmlspecialchars($_POST["serialno"] ?? "") ?>" required />
                    </div>

                    <div>
                        <label>Equipment Type</label>
                        <input class="input" name="equipmenttype" value="<?= htmlspecialchars($_POST["equipmenttype"] ?? "") ?>" />
                    </div>

                    <div>
                        <label>Status</label>
                        <input class="input" name="status" value="<?= htmlspecialchars($_POST["status"] ?? "") ?>" />
                    </div>

                    <div>
                        <label>Model</label>
                        <input class="input" name="model" value="<?= htmlspecialchars($_POST["model"] ?? "") ?>" />
                    </div>

                    <div>
                        <label>Code No</label>
                        <input class="input" name="codeno" value="<?= htmlspecialchars($_POST["codeno"] ?? "") ?>" />
                    </div>

                    <div>
                        <label>Location</label>
                        <input class="input" name="location" value="<?= htmlspecialchars($_POST["location"] ?? "") ?>" />
                    </div>

                    <div>
                        <label>Certification No</label>
                        <input class="input" name="certificationno" value="<?= htmlspecialchars($_POST["certificationno"] ?? "") ?>" />
                    </div>

                    <div>
                        <label>Date Calibration</label>
                        <input class="input" type="date" name="datecalibration" value="<?= htmlspecialchars($_POST["datecalibration"] ?? "") ?>" />
                    </div>

                    <div>
                        <label>Next Calibration</label>
                        <input class="input" type="date" name="nextcalibration" value="<?= htmlspecialchars($_POST["nextcalibration"] ?? "") ?>" />
                    </div>

                    <div class="full">
                        <h3 style="margin:.25rem 0 .25rem">Drill specifics (optional)</h3>
                        <div class="muted" style="margin-bottom:.6rem">Fill only if applicable.</div>
                    </div>

                    <div class="full">
                        <label>Item Description</label>
                        <textarea class="input" name="itemdescription"><?= htmlspecialchars($_POST["itemdescription"] ?? "") ?></textarea>
                    </div>

                    <div>
                        <label>Marking No</label>
                        <input class="input" name="markingno" value="<?= htmlspecialchars($_POST["markingno"] ?? "") ?>" />
                    </div>

                    <div>
                        <label>Remark</label>
                        <input class="input" name="remark" value="<?= htmlspecialchars($_POST["remark"] ?? "") ?>" />
                    </div>

                    <div>
                        <label>Date of Purchase</label>
                        <input class="input" type="date" name="dateofpurchase" value="<?= htmlspecialchars($_POST["dateofpurchase"] ?? "") ?>" />
                    </div>

                    <div>
                        <label>Date of Disposal</label>
                        <input class="input" type="date" name="dateofdisposal" value="<?= htmlspecialchars($_POST["dateofdisposal"] ?? "") ?>" />
                    </div>
                </div>

                <div class="actions">
                    <button class="btn" type="submit">Submit (Pending)</button>
                    <a class="btn secondary" href="view_machine.php">Cancel</a>
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