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

// which form is active: equipment | drill
$mode = $_GET["mode"] ?? "equipment";
if (!in_array($mode, ["equipment", "drill"], true)) $mode = "equipment";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $mode = $_POST["mode"] ?? "equipment";
    if (!in_array($mode, ["equipment", "drill"], true)) $mode = "equipment";

    $companyId = (int)($_POST["companyid"] ?? 0);

    if ($companyId <= 0) {
        $error = "Company is required.";
    } else {
        try {
            if ($mode === "equipment") {
                // Equipment fields
                $serialNo        = trim($_POST["serialno"] ?? "");
                $model           = trim($_POST["model"] ?? "");
                $codeNo          = trim($_POST["codeno"] ?? "");
                $equipmentType   = trim($_POST["equipmenttype"] ?? "");
                $dateCalibration = $_POST["datecalibration"] ?? null;
                $nextCalibration = $_POST["nextcalibration"] ?? null;
                $certNo          = trim($_POST["certificationno"] ?? "");
                $location        = trim($_POST["location"] ?? "");
                $status          = trim($_POST["status"] ?? "");

                if ($serialNo === "") {
                    $error = "Serial No is required for Equipment.";
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO equipment
                        (serialno, model, codeno, datecalibration, nextcalibration, certificationno, location, status, equipmenttype,
                         companyid, createdby, updatedby)
                        VALUES
                        (:serialno, :model, :codeno, :datecalibration, :nextcalibration, :certificationno, :location, :status, :equipmenttype,
                         :companyid, :uid, :uid)
                    ");
                    $stmt->execute([
                        ":serialno" => $serialNo,
                        ":model" => ($model === "" ? null : $model),
                        ":codeno" => ($codeNo === "" ? null : $codeNo),
                        ":datecalibration" => ($dateCalibration === "" ? null : $dateCalibration),
                        ":nextcalibration" => ($nextCalibration === "" ? null : $nextCalibration),
                        ":certificationno" => ($certNo === "" ? null : $certNo),
                        ":location" => ($location === "" ? null : $location),
                        ":status" => ($status === "" ? null : $status),
                        ":equipmenttype" => ($equipmentType === "" ? null : $equipmentType),
                        ":companyid" => $companyId,
                        ":uid" => $userId,
                    ]);

                    $success = "Equipment added successfully.";
                    $_POST = [];
                    $mode = "equipment";
                }

            } else {
                /**
                 * Drill fields (NEW drill table)
                 * User said drill table no longer has: serialno, model, codeno
                 * So this form only saves drill-specific info.
                 */
                $itemDesc     = trim($_POST["itemdescription"] ?? "");
                $markingNo    = trim($_POST["markingno"] ?? "");
                $datePurchase = $_POST["dateofpurchase"] ?? null;
                $dateDisposal = $_POST["dateofdisposal"] ?? null;
                $remark       = trim($_POST["remark"] ?? "");

                // if you want drill to require approval, change to 'pending'
                $approval = "approved";

                // You probably want at least marking no or description to identify a drill.
                if ($markingNo === "" && $itemDesc === "") {
                    $error = "For Drill, please fill at least Marking No or Item Description.";
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO drill
                        (companyid,
                         itemdescription, markingno, dateofpurchase, dateofdisposal, remark,
                         createdby, updatedby,
                         approvalstatus, approvedby, approveddate)
                        VALUES
                        (:companyid,
                         :itemdescription, :markingno, :dateofpurchase, :dateofdisposal, :remark,
                         :uid, :uid,
                         :approval, :approvedby, CASE WHEN :approval = 'approved' THEN NOW() ELSE NULL END)
                    ");
                    $stmt->execute([
                        ":companyid" => $companyId,
                        ":itemdescription" => ($itemDesc === "" ? null : $itemDesc),
                        ":markingno" => ($markingNo === "" ? null : $markingNo),
                        ":dateofpurchase" => ($datePurchase === "" ? null : $datePurchase),
                        ":dateofdisposal" => ($dateDisposal === "" ? null : $dateDisposal),
                        ":remark" => ($remark === "" ? null : $remark),
                        ":uid" => $userId,
                        ":approval" => $approval,
                        ":approvedby" => ($approval === "approved" ? $userId : null),
                    ]);

                    $success = "Drill added successfully ({$approval}).";
                    $_POST = [];
                    $mode = "drill";
                }
            }

        } catch (PDOException $e) {
            $error = "Save failed: " . $e->getMessage();
        }
    }
}

$activeTab = $mode;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Add Equipment / Drill | Authorized</title>
    <link rel="stylesheet" href="../../css/guest_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
    <style>
        .tabs { display:flex; gap:.6rem; flex-wrap:wrap; }
        .tab-btn{
            display:inline-flex; align-items:center; gap:.45rem;
            padding:.65rem 1rem; border-radius:999px; font-weight:900;
            text-decoration:none; border:1px solid rgba(120,120,160,.25);
            background:rgba(255,255,255,.75); color:#121726;
        }
        .tab-btn.active{
            color:#fff;
            border-color:transparent;
            background: linear-gradient(135deg, var(--primary1), var(--primary2));
        }
        .form-section{ margin-top:.8rem; }
        .hidden{ display:none; }
        .note{ color:var(--muted); font-size:.92rem; margin-top:.25rem; line-height:1.5; }
    </style>
</head>
<body>
<div class="app">
    <?php include "sidebar.php"; ?>

    <main class="main">
        <div class="header">
            <div>
                <h2>Add Equipment / Drill</h2>
                <div class="sub">Use tabs to add Equipment or Drill in the same page.</div>
            </div>

            <div class="tabs">
                <a class="tab-btn <?= $activeTab === "equipment" ? "active" : "" ?>" href="add_machine.php?mode=equipment">
                    <i class="fa-solid fa-screwdriver-wrench"></i> Equipment
                </a>
                <a class="tab-btn <?= $activeTab === "drill" ? "active" : "" ?>" href="add_machine.php?mode=drill">
                    <i class="fa-solid fa-person-digging"></i> Drill
                </a>
                <a class="btn secondary" href="machines_list.php">Back</a>
            </div>
        </div>

        <div class="card">
            <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <!-- EQUIPMENT FORM -->
            <div class="form-section <?= $activeTab === "equipment" ? "" : "hidden" ?>">
                <h3 style="margin-bottom:.25rem">Equipment</h3>
                <div class="note">For THEODOLITE, TOTAL STATION, DUMPING LEVEL, etc.</div>

                <form method="POST" style="margin-top:.8rem">
                    <input type="hidden" name="mode" value="equipment" />
                    <div class="grid">
                        <div>
                            <label>Company *</label>
                            <select class="input" name="companyid" required>
                                <option value="">-- Select Company --</option>
                                <?php foreach ($companies as $c): ?>
                                    <option value="<?= (int)$c["companyid"] ?>"
                                        <?= ((string)($activeTab==="equipment" ? ($_POST["companyid"] ?? "") : "") === (string)$c["companyid"]) ? "selected" : "" ?>>
                                        <?= htmlspecialchars($c["companyname"]) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Serial No *</label>
                            <input class="input" name="serialno" value="<?= htmlspecialchars($activeTab==="equipment" ? ($_POST["serialno"] ?? "") : "") ?>" required>
                        </div>

                        <div>
                            <label>Equipment Type</label>
                            <input class="input" name="equipmenttype" value="<?= htmlspecialchars($activeTab==="equipment" ? ($_POST["equipmenttype"] ?? "") : "") ?>"
                                   placeholder="THEODOLITE / TOTAL STATION / DUMPING LEVEL">
                        </div>

                        <div><label>Status</label><input class="input" name="status" value="<?= htmlspecialchars($activeTab==="equipment" ? ($_POST["status"] ?? "") : "") ?>"></div>
                        <div><label>Model</label><input class="input" name="model" value="<?= htmlspecialchars($activeTab==="equipment" ? ($_POST["model"] ?? "") : "") ?>"></div>
                        <div><label>Code No</label><input class="input" name="codeno" value="<?= htmlspecialchars($activeTab==="equipment" ? ($_POST["codeno"] ?? "") : "") ?>"></div>
                        <div><label>Location</label><input class="input" name="location" value="<?= htmlspecialchars($activeTab==="equipment" ? ($_POST["location"] ?? "") : "") ?>"></div>
                        <div><label>Certification No</label><input class="input" name="certificationno" value="<?= htmlspecialchars($activeTab==="equipment" ? ($_POST["certificationno"] ?? "") : "") ?>"></div>
                        <div><label>Date Calibration</label><input class="input" type="date" name="datecalibration" value="<?= htmlspecialchars($activeTab==="equipment" ? ($_POST["datecalibration"] ?? "") : "") ?>"></div>
                        <div><label>Next Calibration</label><input class="input" type="date" name="nextcalibration" value="<?= htmlspecialchars($activeTab==="equipment" ? ($_POST["nextcalibration"] ?? "") : "") ?>"></div>
                    </div>

                    <div style="margin-top:1rem;display:flex;gap:.7rem;flex-wrap:wrap">
                        <button class="btn" type="submit">Save Equipment</button>
                        <a class="btn secondary" href="machines_list.php">Cancel</a>
                    </div>
                </form>
            </div>

            <!-- DRILL FORM (NO serialno/model/codeno anymore) -->
            <div class="form-section <?= $activeTab === "drill" ? "" : "hidden" ?>">
                <h3 style="margin-bottom:.25rem">Drill</h3>
                <div class="note">Saves into the <b>drill</b> table (no Serial/Model/Code columns).</div>

                <form method="POST" style="margin-top:.8rem">
                    <input type="hidden" name="mode" value="drill" />
                    <div class="grid">
                        <div>
                            <label>Company *</label>
                            <select class="input" name="companyid" required>
                                <option value="">-- Select Company --</option>
                                <?php foreach ($companies as $c): ?>
                                    <option value="<?= (int)$c["companyid"] ?>"
                                        <?= ((string)($activeTab==="drill" ? ($_POST["companyid"] ?? "") : "") === (string)$c["companyid"]) ? "selected" : "" ?>>
                                        <?= htmlspecialchars($c["companyname"]) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Markin No</label>
                            <input class="input" name="markingno" value="<?= htmlspecialchars($activeTab==="drill" ? ($_POST["markingno"] ?? "") : "") ?>"
                                   placeholder="Optional but recommended">
                        </div>
                        <div class="full">
                            <label>Item Description</label>
                            <textarea class="input" name="itemdescription"><?= htmlspecialchars($activeTab==="drill" ? ($_POST["itemdescription"] ?? "") : "") ?></textarea>
                        </div>

                        <div>
                            <label>Remark</label>
                            <input class="input" name="remark" value="<?= htmlspecialchars($activeTab==="drill" ? ($_POST["remark"] ?? "") : "") ?>">
                        </div>

                        <div>
                            <label>Date of Purchase</label>
                            <input class="input" type="date" name="dateofpurchase" value="<?= htmlspecialchars($activeTab==="drill" ? ($_POST["dateofpurchase"] ?? "") : "") ?>">
                        </div>

                        <div>
                            <label>Date of Disposal</label>
                            <input class="input" type="date" name="dateofdisposal" value="<?= htmlspecialchars($activeTab==="drill" ? ($_POST["dateofdisposal"] ?? "") : "") ?>">
                        </div>
                    </div>

                    <div style="margin-top:1rem;display:flex;gap:.7rem;flex-wrap:wrap">
                        <button class="btn" type="submit">Save Drill</button>
                        <a class="btn secondary" href="drills_list.php">Cancel</a>
                    </div>
                </form>
            </div>

        </div>
    </main>
</div>
</body>
</html>