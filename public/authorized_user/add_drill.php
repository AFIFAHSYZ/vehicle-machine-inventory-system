<?php
session_start();
if (($_SESSION["role"] ?? "") !== "authorized user") { header("Location: ../login.php"); exit; }

require_once __DIR__ . "/../../config/db.php";
$currentPage = basename($_SERVER["PHP_SELF"]);

$error = "";
$success = "";

// Load companies for dropdown
$companies = [];
try {
    $cStmt = $pdo->query("SELECT companyid, companyname FROM company ORDER BY companyname ASC");
    $companies = $cStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $companies = [];
}

function h($v){ return htmlspecialchars((string)$v); }

// Defaults
$form = [
    "companyid" => (int)($_POST["companyid"] ?? 0),
    "status" => (string)($_POST["status"] ?? ""),
    "location" => (string)($_POST["location"] ?? ""),
    "itemdescription" => (string)($_POST["itemdescription"] ?? ""),
    "markingno" => (string)($_POST["markingno"] ?? ""),
    "dateofpurchase" => (string)($_POST["dateofpurchase"] ?? ""),
    "dateofdisposal" => (string)($_POST["dateofdisposal"] ?? ""),
    "remark" => (string)($_POST["remark"] ?? ""),
];

$STATUS_OPTIONS = ["IN USE", "DAMAGE", "DISPOSAL", "SOLD", "LOST"];

function normalizeUpper(?string $v): ?string {
    if ($v === null) return null;
    $v = trim($v);
    if ($v === "") return null;
    return function_exists("mb_strtoupper") ? mb_strtoupper($v, "UTF-8") : strtoupper($v);
}

function normalizeText(?string $v): ?string {
    if ($v === null) return null;
    $v = trim($v);
    return $v === "" ? null : $v;
}

function normalizeDate(?string $v): ?string {
    if ($v === null) return null;
    $v = trim($v);
    if ($v === "") return null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        throw new Exception("Invalid date format. Use YYYY-MM-DD.");
    }
    return $v;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $companyid = (int)$form["companyid"];
        if ($companyid <= 0) throw new Exception("Please select a company.");

        $status = normalizeUpper($form["status"]);
        if ($status === null) throw new Exception("Please select a status.");
        if (!in_array($status, $STATUS_OPTIONS, true)) throw new Exception("Invalid status selected.");

        $location = normalizeUpper($form["location"]);
        $markingno = normalizeUpper($form["markingno"]);

        $itemdescription = normalizeText($form["itemdescription"]);
        $remark = normalizeText($form["remark"]);

        $dateofpurchase = normalizeDate($form["dateofpurchase"]);
        $dateofdisposal = normalizeDate($form["dateofdisposal"]);

        // Optional: prevent disposal date before purchase date
        if ($dateofpurchase && $dateofdisposal) {
            if (strtotime($dateofdisposal) < strtotime($dateofpurchase)) {
                throw new Exception("Date of disposal cannot be earlier than date of purchase.");
            }
        }

        $createdby = $_SESSION["userid"] ?? null;

        $stmt = $pdo->prepare("
            INSERT INTO drill (
                companyid,
                status,
                location,
                itemdescription,
                markingno,
                dateofpurchase,
                dateofdisposal,
                remark,
                createdby,
                createddate,
                approvalstatus
            ) VALUES (
                :companyid,
                :status,
                :location,
                :itemdescription,
                :markingno,
                :dateofpurchase,
                :dateofdisposal,
                :remark,
                :createdby,
                NOW(),
                'PENDING'
            )
            RETURNING drillid
        ");
        $stmt->execute([
            ":companyid" => $companyid,
            ":status" => $status,
            ":location" => $location,
            ":itemdescription" => $itemdescription,
            ":markingno" => $markingno,
            ":dateofpurchase" => $dateofpurchase,
            ":dateofdisposal" => $dateofdisposal,
            ":remark" => $remark,
            ":createdby" => $createdby,
        ]);

        $newId = (int)$stmt->fetchColumn();
        $success = "Drill record created successfully.";

        // Redirect to list page or details page
        header("Location: drill.php?added=1");
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$added = (string)($_GET["added"] ?? "");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Add Drill | Authorized</title>

    <link rel="stylesheet" href="../../css/guest_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

    <style>
        .header{
            background:var(--card);
            border:1px solid var(--border);
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            padding:1.2rem;
            display:flex; gap:1rem; align-items:flex-start; justify-content:space-between;
            flex-wrap:wrap;
        }
        .sub{color:var(--muted);margin-top:.25rem;line-height:1.5}
        .actions{display:flex;gap:.6rem;flex-wrap:wrap}
        .card{
            margin-top:1rem;
            background:var(--card);
            border:1px solid var(--border);
            border-radius:var(--radius);
            box-shadow:var(--shadow2);
            padding:1rem;
            overflow:hidden;
        }
        .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem}
        .full{grid-column:1/-1}
        label{font-weight:900;font-size:.9rem}
        .input{
            width:100%; padding:.8rem .9rem;
            border-radius:14px; border:1px solid rgba(120,120,160,.25);
            background:rgba(255,255,255,.92);
        }
        .input:focus{outline:none; border-color:rgba(108,99,255,.55); box-shadow:0 0 0 4px rgba(108,99,255,.18)}
        textarea.input{min-height:90px;resize:vertical}
        .alert{padding:.85rem 1rem;border-radius:14px;border:1px solid;margin-bottom:.8rem}
        .alert.error{background:rgba(220,38,38,.08);border-color:rgba(220,38,38,.25);color:#991b1b}
        .alert.success{background:rgba(16,185,129,.10);border-color:rgba(16,185,129,.22);color:#065f46}
        .actions-row{display:flex;gap:.7rem;flex-wrap:wrap;margin-top:1rem}
        @media (max-width: 980px){ .grid{grid-template-columns:1fr;} }
    </style>
</head>
<body>
<div class="app">
<?php include "sidebar.php";?>

    <main class="main">
        <div class="header">
            <div>
                <h2 style="margin:0">Add Drill Machine</h2>
                <div class="sub">Create a new drill record (approval starts as PENDING).</div>
            </div>
            <div class="actions">
                <a class="btn secondary" href="drill.php"><i class="fa-solid fa-arrow-left"></i>&nbsp;Back</a>
            </div>
        </div>

        <div class="card">
            <?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert success"><?= h($success) ?></div><?php endif; ?>

            <form method="POST">
                <div class="grid">
                    <div>
                        <label>Company</label>
                        <select class="input" name="companyid" required>
                            <option value="0">-- Select --</option>
                            <?php foreach ($companies as $c): ?>
                                <option value="<?= (int)$c["companyid"] ?>" <?= $form["companyid"] === (int)$c["companyid"] ? "selected" : "" ?>>
                                    <?= h($c["companyname"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Status</label>
                        <select class="input" name="status" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($STATUS_OPTIONS as $opt): ?>
                                <option value="<?= h($opt) ?>" <?= strtoupper($form["status"]) === $opt ? "selected" : "" ?>>
                                    <?= h($opt) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Marking No</label>
                        <input class="input" type="text" name="markingno" value="<?= h($form["markingno"]) ?>" placeholder="e.g. DRL-001" style="text-transform:uppercase">
                    </div>

                    <div>
                        <label>Location</label>
                        <input class="input" type="text" name="location" value="<?= h($form["location"]) ?>" placeholder="e.g. STORE / SITE" style="text-transform:uppercase">
                    </div>

                    <div>
                        <label>Date of Purchase</label>
                        <input class="input" type="date" name="dateofpurchase" value="<?= h($form["dateofpurchase"]) ?>">
                    </div>

                    <div>
                        <label>Date of Disposal</label>
                        <input class="input" type="date" name="dateofdisposal" value="<?= h($form["dateofdisposal"]) ?>">
                    </div>

                    <div class="full">
                        <label>Item Description</label>
                        <textarea class="input" name="itemdescription" placeholder="Describe the drill machine..."><?= h($form["itemdescription"]) ?></textarea>
                    </div>

                    <div class="full">
                        <label>Remark</label>
                        <textarea class="input" name="remark" placeholder="Any remark..."><?= h($form["remark"]) ?></textarea>
                    </div>
                </div>

                <div class="actions-row">
                    <button class="btn" type="submit"><i class="fa-solid fa-check"></i>&nbsp;Save</button>
                    <a class="btn secondary" href="drill.php"><i class="fa-solid fa-xmark"></i>&nbsp;Cancel</a>
                </div>

                <div class="muted" style="margin-top:.8rem">
                    Note: Approval status will be set to <b>PENDING</b> automatically.
                </div>
            </form>
        </div>
    </main>
</div>
</body>
</html>