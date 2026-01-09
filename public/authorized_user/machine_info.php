<?php
session_start();
if (($_SESSION["role"] ?? "") !== "authorized user") { header("Location: ../login.php"); exit; }

require_once __DIR__ . "/../../config/db.php";
$currentPage = basename($_SERVER["PHP_SELF"]);

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) { die("Invalid equipment id."); }

$error = "";
$success = "";

function h($v){ return htmlspecialchars((string)$v); }
function valOrDash($v){
    $v = trim((string)($v ?? ""));
    return $v === "" ? "—" : h($v);
}
function fmtDateDMY($v): string {
    if (!$v) return "—";
    try { return (new DateTime((string)$v))->format("d-m-Y"); }
    catch (Throwable $e) { return "—"; }
}

$STATUS_OPTIONS = ["IN USE", "DAMAGE", "DISPOSAL", "SOLD", "LOST"];
$ALLOWED = [
    "serialno",
    "model",
    "codeno",
    "certificationno",
    "location",
    "status",
    "equipmenttype",
    "datecalibration",
    "nextcalibration",
];

$UPPERCASE = ["serialno","model","codeno","certificationno","location","status","equipmenttype"];
$DATE_FIELDS = ["datecalibration","nextcalibration"];

/* delete */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete") {
    try {
        $pdo->prepare("DELETE FROM equipment WHERE equipmentid = :id")->execute([":id" => $id]);
        header("Location: machine.php?msg=deleted");
        exit;
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

/* inline update */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["field"])) {
    $field = (string)($_POST["field"] ?? "");
    $value = $_POST["value"] ?? null;

    if (!in_array($field, $ALLOWED, true)) {
        $error = "Invalid field.";
    } else {
        try {
            if (is_string($value)) $value = trim($value);
            if ($value === "") $value = null;

            if ($value !== null && in_array($field, $UPPERCASE, true)) {
                $value = function_exists("mb_strtoupper") ? mb_strtoupper($value, "UTF-8") : strtoupper($value);
            }

            if ($value !== null && $field === "status" && !in_array($value, $STATUS_OPTIONS, true)) {
                throw new Exception("Invalid Status.");
            }

            if ($value !== null && in_array($field, $DATE_FIELDS, true) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$value)) {
                throw new Exception("Invalid date format. Use YYYY-MM-DD.");
            }

            $pdo->prepare("UPDATE equipment SET {$field} = :val, updatedby = :uid, updateddate = NOW() WHERE equipmentid = :id")
                ->execute([":val"=>$value, ":uid"=>($_SESSION["userid"] ?? null), ":id"=>$id]);

            header("Location: machine_info.php?id={$id}&saved=1");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

if (($_GET["saved"] ?? "") === "1") $success = "Updated successfully.";

/* load equipment */
$stmt = $pdo->prepare("
    SELECT e.*, c.companyname,
           u1.fullname AS createdby_name,
           u2.fullname AS updatedby_name,
           u3.fullname AS approvedby_name
    FROM equipment e
    LEFT JOIN company c ON c.companyid = e.companyid
    LEFT JOIN \"User\" u1 ON u1.userid = e.createdby
    LEFT JOIN \"User\" u2 ON u2.userid = e.updatedby
    LEFT JOIN \"User\" u3 ON u3.userid = e.approvedby
    WHERE e.equipmentid = :id
    LIMIT 1
");
$stmt->execute([":id" => $id]);
$eq = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$eq) { die("Equipment not found."); }

/* equipment types for dropdown */
$types = [];
try {
    $types = $pdo->query("
        SELECT DISTINCT equipmenttype
        FROM equipment
        WHERE equipmenttype IS NOT NULL AND equipmenttype <> ''
        ORDER BY equipmenttype ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) { $types = []; }

/* render helpers */
function editableTextRow($label, $field, $value, $mono=false, $uppercase=true){
    $v = htmlspecialchars((string)$value);
    $view = ($v === "" ? "—" : $v);
    $monoClass = $mono ? "mono" : "";
    $tx = $uppercase ? "text-transform:uppercase" : "";
    echo <<<HTML
    <div class="dt">{$label}</div>
    <div class="dd {$monoClass}">
        <div class="value-wrap">
            <span class="view {$monoClass}">{$view}</span>
            <button class="icon-btn" type="button" title="Edit" onclick="startEdit('{$field}')">
                <i class="fa-solid fa-pen-to-square"></i>
            </button>
        </div>
        <form class="edit" id="edit-{$field}" method="POST">
            <input type="hidden" name="field" value="{$field}">
            <input type="text" name="value" value="{$v}" style="{$tx}">
            <div class="edit-actions">
                <button class="small-btn save-btn" type="submit" title="Save"><i class="fa-solid fa-check"></i></button>
                <button class="small-btn cancel-btn" type="button" title="Cancel" onclick="cancelEdit('{$field}')"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </form>
    </div>
    HTML;
}
function editableDateRow($label, $field, $rawValue){
    $dmy = h(fmtDateDMY($rawValue));
    $raw = h($rawValue ?? "");
    echo <<<HTML
    <div class="dt">{$label}</div>
    <div class="dd">
        <div class="value-wrap">
            <span class="view">{$dmy}</span>
            <button class="icon-btn" type="button" title="Edit" onclick="startEdit('{$field}')">
                <i class="fa-solid fa-pen-to-square"></i>
            </button>
        </div>
        <form class="edit" id="edit-{$field}" method="POST">
            <input type="hidden" name="field" value="{$field}">
            <input type="date" name="value" value="{$raw}">
            <div class="edit-actions">
                <button class="small-btn save-btn" type="submit" title="Save"><i class="fa-solid fa-check"></i></button>
                <button class="small-btn cancel-btn" type="button" title="Cancel" onclick="cancelEdit('{$field}')"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </form>
    </div>
    HTML;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Machine Info | Authorized</title>

    <link rel="stylesheet" href="../../css/guest_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

    <style>
        @media (max-width: 980px){ .dl{grid-template-columns:1fr;} }
    </style>
</head>
<body>
<div class="app">
<?php include "sidebar.php";?>

<main class="main">
    <div class="header">
        <div class="page-title">
            <div>
                <h2 style="margin:0">Machine / Equipment Details</h2>
                <div class="top-meta">
                    <span class="chip"><i class="fa-solid fa-hashtag"></i> <b><?= (int)$eq["equipmentid"] ?></b></span>
                    <span class="chip"><i class="fa-solid fa-building"></i> <b><?= h($eq["companyname"] ?? "") ?></b></span>
                    <span class="chip"><i class="fa-solid fa-barcode"></i> <b><?= h($eq["serialno"] ?? "") ?></b></span>
                </div>

                <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1rem;">
                    <a class="btn secondary" href="machine.php"><i class="fa-solid fa-arrow-left"></i>&nbsp;Back</a>

                    <form method="POST" style="margin:0" onsubmit="return confirm('Delete this machine/equipment record? This cannot be undone.');">
                        <input type="hidden" name="action" value="delete">
                        <button class="btn danger" type="submit"><i class="fa-solid fa-trash"></i>&nbsp;Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($error): ?><div class="card" style="margin-top:1rem"><div class="alert error"><?= h($error) ?></div></div><?php endif; ?>
    <?php if ($success): ?><div class="card" style="margin-top:1rem"><div class="alert success"><?= h($success) ?></div></div><?php endif; ?>

    <div class="layout">
        <section class="panel">
            <h3><i class="fa-solid fa-gears"></i> Basic Information</h3>

            <div class="dl">
                <?php
                    editableTextRow("Serial No", "serialno", $eq["serialno"] ?? "", true);
                    editableTextRow("Model", "model", $eq["model"] ?? "");
                    editableTextRow("Code No", "codeno", $eq["codeno"] ?? "", true);

                    /* status select */
                    $statusView = valOrDash($eq["status"] ?? "");
                ?>
                <div class="dt">Status</div>
                <div class="dd">
                    <div class="value-wrap">
                        <span class="view"><?= $statusView ?></span>
                        <button class="icon-btn" type="button" title="Edit" onclick="startEdit('status')">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    </div>
                    <form class="edit" id="edit-status" method="POST">
                        <input type="hidden" name="field" value="status">
                        <select name="value">
                            <option value="">-- Select --</option>
                            <?php foreach ($STATUS_OPTIONS as $opt): ?>
                                <option value="<?= h($opt) ?>" <?= strtoupper((string)($eq["status"] ?? "")) === $opt ? "selected" : "" ?>>
                                    <?= h($opt) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="edit-actions">
                            <button class="small-btn save-btn" type="submit" title="Save"><i class="fa-solid fa-check"></i></button>
                            <button class="small-btn cancel-btn" type="button" title="Cancel" onclick="cancelEdit('status')"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                    </form>
                </div>

                <?php
                    editableTextRow("Location", "location", $eq["location"] ?? "", false, true);

                    /* type select */
                    $typeView = valOrDash($eq["equipmenttype"] ?? "");
                ?>
                <div class="dt">Equipment Type</div>
                <div class="dd">
                    <div class="value-wrap">
                        <span class="view"><?= $typeView ?></span>
                        <button class="icon-btn" type="button" title="Edit" onclick="startEdit('equipmenttype')">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    </div>
                    <form class="edit" id="edit-equipmenttype" method="POST">
                        <input type="hidden" name="field" value="equipmenttype">
                        <select name="value">
                            <option value="">-- Select --</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?= h($t) ?>" <?= (string)($eq["equipmenttype"] ?? "") === (string)$t ? "selected" : "" ?>>
                                    <?= h($t) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="edit-actions">
                            <button class="small-btn save-btn" type="submit" title="Save"><i class="fa-solid fa-check"></i></button>
                            <button class="small-btn cancel-btn" type="button" title="Cancel" onclick="cancelEdit('equipmenttype')"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                    </form>
                </div>

                <?php
                    editableTextRow("Certification No", "certificationno", $eq["certificationno"] ?? "", true);
                    editableDateRow("Calibration Date", "datecalibration", $eq["datecalibration"] ?? "");
                    editableDateRow("Next Calibration", "nextcalibration", $eq["nextcalibration"] ?? "");
                ?>
            </div>

            <div class="divider"></div>

            <h3><i class="fa-solid fa-clipboard-check"></i> Approval / Audit</h3>
            <div class="dl">
                <div class="dt">Approval Status</div>
                <div class="dd"><?= h(strtoupper((string)($eq["approvalstatus"] ?? "—"))) ?></div>

                <div class="dt">Approved By</div>
                <div class="dd"><?= h($eq["approvedby_name"] ?? $eq["approvedby"] ?? "—") ?></div>

                <div class="dt">Approved Date</div>
                <div class="dd"><?= h(fmtDateDMY($eq["approveddate"] ?? null)) ?></div>

                <div class="dt">Created By</div>
                <div class="dd"><?= h($eq["createdby_name"] ?? $eq["createdby"] ?? "—") ?></div>

                <div class="dt">Created Date</div>
                <div class="dd"><?= h(fmtDateDMY($eq["createddate"] ?? null)) ?></div>

                <div class="dt">Updated By</div>
                <div class="dd"><?= h($eq["updatedby_name"] ?? $eq["updatedby"] ?? "—") ?></div>

                <div class="dt">Updated Date</div>
                <div class="dd"><?= h(fmtDateDMY($eq["updateddate"] ?? null)) ?></div>
            </div>
        </section>
    </div>
</main>
</div>

<script>
function startEdit(field){
    document.querySelectorAll(".edit").forEach(e => e.style.display = "none");
    document.querySelectorAll(".value-wrap").forEach(v => v.style.display = "flex");
    const form = document.getElementById("edit-" + field);
    if (!form) return;
    if (form.previousElementSibling) form.previousElementSibling.style.display = "none";
    form.style.display = "flex";
    const input = form.querySelector("input, select, textarea");
    if (input) input.focus();
}
function cancelEdit(field){
    const form = document.getElementById("edit-" + field);
    if (!form) return;
    form.style.display = "none";
    if (form.previousElementSibling) form.previousElementSibling.style.display = "flex";
}
</script>
</body>
</html>