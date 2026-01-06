<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

/**
 * Guest submit page
 * - Inserts into equipment with approvalstatus='pending'
 * - Optionally inserts into drillspecifics
 * - No approval UI here
 */

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
    <style>
        :root{
            --bg1:#f6f7ff; --bg2:#f2f6ff; --card:rgba(255,255,255,.85);
            --border:rgba(120,120,160,.20); --text:#1f2430; --muted:#6b7280;
            --primary1:#6c63ff; --primary2:#854af0;
            --sidebar:#101423; --sidebar2:#151a2e;
            --shadow:0 18px 50px rgba(25, 30, 60, 0.14);
            --shadow2:0 10px 22px rgba(25, 30, 60, 0.12);
            --radius:18px;
        }
        *{box-sizing:border-box;margin:0;padding:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}
        body{
            min-height:100vh; color:var(--text);
            background:
                radial-gradient(900px 420px at 20% 10%, rgba(108, 99, 255, 0.16), transparent 60%),
                radial-gradient(900px 420px at 80% 15%, rgba(133, 74, 240, 0.14), transparent 60%),
                linear-gradient(180deg,var(--bg1),var(--bg2));
        }
        .app{min-height:100vh;display:grid;grid-template-columns:280px 1fr;}
        .sidebar{
            position:sticky;top:0;height:100vh;
            padding:1.25rem 1.1rem;color:#e9ecff;
            background:linear-gradient(180deg,var(--sidebar),var(--sidebar2));
            border-right:1px solid rgba(255,255,255,0.06);
        }
        .brand{display:flex;align-items:center;gap:.75rem;padding:.7rem .8rem;border-radius:14px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.08);margin-bottom:1.1rem;}
        .logo{width:40px;height:40px;border-radius:14px;background:linear-gradient(135deg,var(--primary1),var(--primary2));box-shadow:0 12px 30px rgba(108,99,255,.35);display:grid;place-items:center;font-weight:900;color:#fff;}
        .brand h1{font-size:1.02rem;line-height:1.2;letter-spacing:-0.01em;}
        .brand p{font-size:.85rem;color:rgba(233,236,255,.72);margin-top:.15rem;}
        .nav{margin-top:.8rem;display:grid;gap:.35rem;}
        .nav a{display:flex;align-items:center;gap:.7rem;padding:.8rem .9rem;border-radius:14px;text-decoration:none;color:rgba(233,236,255,.86);border:1px solid transparent;transition:transform .12s ease, background .12s ease, border-color .12s ease;}
        .nav a:hover{background:rgba(255,255,255,0.08);border-color:rgba(255,255,255,0.10);transform:translateY(-1px);}
        .nav .active{background:rgba(108,99,255,0.18);border-color:rgba(108,99,255,0.25);}
        .nav .icon{width:34px;height:34px;border-radius:12px;display:grid;place-items:center;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.08);font-size:1rem;}
        .sidebar-footer{position:absolute;left:1.1rem;right:1.1rem;bottom:1.1rem;display:grid;gap:.6rem;}
        .pill{display:flex;align-items:center;justify-content:space-between;padding:.75rem .9rem;border-radius:14px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.08);color:rgba(233,236,255,.86);font-size:.92rem;}
        .badge{padding:.25rem .55rem;border-radius:999px;font-size:.78rem;font-weight:900;color:#0b1020;background:rgba(255,255,255,0.85);}

        .main{padding:1.5rem 1.5rem 2rem;}
        .header{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.2rem;display:flex;gap:1rem;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;}
        h2{font-size:1.35rem;letter-spacing:-0.02em;}
        .sub{color:var(--muted);margin-top:.25rem;line-height:1.5}
        .btn{border:none;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;padding:.7rem 1rem;border-radius:999px;font-weight:900;font-size:.92rem;color:#fff;background:linear-gradient(135deg,var(--primary1),var(--primary2));box-shadow:0 12px 24px rgba(108,99,255,0.22)}
        .btn.secondary{background:#121726;box-shadow:0 12px 24px rgba(18,23,38,0.18)}
        .card{margin-top:1rem;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow2);padding:1rem;}
        .alert{padding:.85rem 1rem;border-radius:14px;border:1px solid;margin-bottom:.8rem}
        .alert.error{background:rgba(220,38,38,.08);border-color:rgba(220,38,38,.25);color:#991b1b}
        .alert.success{background:rgba(16,185,129,.10);border-color:rgba(16,185,129,.22);color:#065f46}
        .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem}
        .full{grid-column:1/-1}
        label{font-weight:900;font-size:.9rem}
        .input{width:100%;padding:.8rem .9rem;border-radius:14px;border:1px solid rgba(120,120,160,.25);background:rgba(255,255,255,.92)}
        .input:focus{outline:none;border-color:rgba(108,99,255,.55);box-shadow:0 0 0 4px rgba(108,99,255,.18)}
        textarea.input{min-height:90px;resize:vertical}
        .actions{display:flex;gap:.7rem;flex-wrap:wrap;margin-top:1rem}
        .muted{color:var(--muted);font-size:.92rem}
        @media (max-width: 980px){
            .app{grid-template-columns:1fr;}
            .sidebar{height:auto;position:relative;border-right:none;border-bottom:1px solid rgba(255,255,255,0.06);}
            .sidebar-footer{position:relative;left:auto;right:auto;bottom:auto;margin-top:1rem;}
            .grid{grid-template-columns:1fr;}
        }
    </style>
</head>
<body>
<div class="app">
    <aside class="sidebar" aria-label="Sidebar Navigation">
        <div class="brand">
            <div class="logo">VM</div>
            <div>
                <h1>Inventory System</h1>
                <p>Guest (submit pending)</p>
            </div>
        </div>

        <nav class="nav">
            <a href="dashboard.php"><span class="icon">🏠</span><span>Dashboard</span></a>
            <a href="view_machine.php"><span class="icon">🏗️</span><span>Machines</span></a>
            <a class="active" href="add_machine.php" aria-current="page"><span class="icon">➕</span><span>Add Machine</span></a>
        </nav>

        <div class="sidebar-footer">
            <div class="pill"><span>Role</span><span class="badge">GUEST</span></div>
            <a class="btn secondary" href="../login.php">Login</a>
            <a class="btn" href="../register.php">Register</a>
        </div>
    </aside>

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