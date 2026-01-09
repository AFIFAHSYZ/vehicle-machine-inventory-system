<?php
session_start();
if (($_SESSION["role"] ?? "") !== "authorized user") { header("Location: ../login.php"); exit; }

require_once __DIR__ . "/../../config/db.php";
$currentPage = basename($_SERVER["PHP_SELF"]);

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) { die("Invalid vehicle id."); }

$error = "";
$success = "";

/**
 * Handle inline updates (one field at a time)
 * POST: field, value
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $field = (string)($_POST["field"] ?? "");
    $value = $_POST["value"] ?? null;

    $ALLOWED = [
        "platenumber",
        "vehicletype",
        "model",
        "status",
        "driver",
        "owner",
        "owneric",
        "roadtaxdue",
        "insurancedue",
    ];

    $UPPERCASE = ["platenumber","vehicletype","model","status","driver","owner","owneric"];
    $DATE_FIELDS = ["roadtaxdue","insurancedue"];

    // dropdown constraints (optional, but good)
    $STATUS_OPTIONS = ["IN USE", "DAMAGE", "DISPOSAL", "SOLD", "LOST"];
    $TYPE_OPTIONS   = ["CAR", "MOTOR"];

    if (!in_array($field, $ALLOWED, true)) {
        $error = "Invalid field.";
    } else {
        try {
            if (is_string($value)) $value = trim($value);
            if ($value === "") $value = null;

            if ($value !== null && in_array($field, $UPPERCASE, true)) {
                $value = function_exists("mb_strtoupper") ? mb_strtoupper($value, "UTF-8") : strtoupper($value);
            }

            // validate dropdown values
            if ($value !== null && $field === "status" && !in_array($value, $STATUS_OPTIONS, true)) {
                throw new Exception("Invalid Status.");
            }
            if ($value !== null && $field === "vehicletype" && !in_array($value, $TYPE_OPTIONS, true)) {
                throw new Exception("Invalid Vehicle Type.");
            }

            // validate date format
            if ($value !== null && in_array($field, $DATE_FIELDS, true)) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$value)) {
                    throw new Exception("Invalid date format. Use YYYY-MM-DD.");
                }
            }

            $sql = "UPDATE vehicle SET {$field} = :val, updatedby = :uid, updateddate = NOW() WHERE vehicleid = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ":val" => $value,
                ":uid" => ($_SESSION["userid"] ?? null),
                ":id"  => $id,
            ]);

            // PRG: prevent resubmission on refresh
            header("Location: vehicle_info.php?id={$id}&saved=1");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete") {
    try {
        $pdo->prepare("DELETE FROM drill WHERE drillid = :id")->execute([":id" => $id]);
        header("Location: drill.php?msg=deleted");
        exit;
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}


if (($_GET["saved"] ?? "") === "1") {
    $success = "Updated successfully.";
}

// Reload record
$stmt = $pdo->prepare("
    SELECT v.*, c.companyname
    FROM vehicle v
    JOIN company c ON c.companyid = v.companyid
    WHERE v.vehicleid = :id
    LIMIT 1
");
$stmt->execute([":id" => $id]);
$vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$vehicle) { die("Vehicle not found."); }

function h($v){ return htmlspecialchars((string)$v); }
function valOrDash($v){
    $v = (string)($v ?? "");
    return $v === "" ? "—" : h($v);
}

$STATUS_OPTIONS = ["IN USE", "DAMAGE", "DISPOSAL", "SOLD", "LOST"];
$TYPE_OPTIONS   = ["CAR", "MOTOR"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Vehicle Info | Authorized</title>

    <link rel="stylesheet" href="../../css/guest_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

    <style>

        @media (max-width: 980px){
            .dl{grid-template-columns:1fr;}
        }
    </style>
</head>
<body>
<div class="app">
<?php include "sidebar.php";?>

    <main class="main">
        <div class="header">
            <div class="page-title">
                <div>
                    <h2 style="margin:0">Vehicle Details</h2>
                    <div class="top-meta">
                        <span class="chip"><i class="fa-solid fa-hashtag"></i> <b><?= (int)$vehicle["vehicleid"] ?></b></span>
                        <span class="chip"><i class="fa-solid fa-building"></i> <b><?= h($vehicle["companyname"]) ?></b></span>
                        <span class="chip"><i class="fa-solid fa-id-card"></i> <b><?= h($vehicle["platenumber"]) ?></b></span>
                    </div>
                <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1rem;">
                    <a class="btn secondary" href="drill.php"><i class="fa-solid fa-arrow-left"></i>&nbsp;Back</a>

                    <form method="POST" style="margin:0" onsubmit="return confirm('Delete this drill record? This cannot be undone.');">
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
                <h3><i class="fa-solid fa-car-side"></i> Basic Information</h3>

                <div class="dl">
                    <?php
                    function editableTextRow($label, $field, $value, $mono=false){
                        $v = htmlspecialchars((string)$value);
                        $view = ($v === "" ? "—" : $v);
                        $monoClass = $mono ? "mono" : "";
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
                                <input type="text" name="value" value="{$v}" style="text-transform:uppercase">
                                <div class="edit-actions">
                                    <button class="small-btn save-btn" type="submit" title="Save"><i class="fa-solid fa-check"></i></button>
                                    <button class="small-btn cancel-btn" type="button" title="Cancel" onclick="cancelEdit('{$field}')"><i class="fa-solid fa-xmark"></i></button>
                                </div>
                            </form>
                        </div>
                        HTML;
                    }
                    ?>

                    <?php editableTextRow("Plate Number", "platenumber", $vehicle["platenumber"] ?? "", true); ?>

                    <div class="dt">Vehicle Type</div>
                    <div class="dd">
                        <div class="value-wrap">
                            <span class="view"><?= valOrDash($vehicle["vehicletype"] ?? "") ?></span>
                            <button class="icon-btn" type="button" title="Edit" onclick="startEdit('vehicletype')">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                        </div>
                        <form class="edit" id="edit-vehicletype" method="POST">
                            <input type="hidden" name="field" value="vehicletype">
                            <select name="value">
                                <option value="">-- Select --</option>
                                <?php foreach ($TYPE_OPTIONS as $opt): ?>
                                    <option value="<?= h($opt) ?>" <?= strtoupper((string)($vehicle["vehicletype"] ?? "")) === $opt ? "selected" : "" ?>>
                                        <?= h($opt) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="edit-actions">
                                <button class="small-btn save-btn" type="submit" title="Save"><i class="fa-solid fa-check"></i></button>
                                <button class="small-btn cancel-btn" type="button" title="Cancel" onclick="cancelEdit('vehicletype')"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        </form>
                    </div>

                    <?php editableTextRow("Model", "model", $vehicle["model"] ?? ""); ?>

                    <div class="dt">Status</div>
                    <div class="dd">
                        <div class="value-wrap">
                            <span class="view"><?= valOrDash($vehicle["status"] ?? "") ?></span>
                            <button class="icon-btn" type="button" title="Edit" onclick="startEdit('status')">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                        </div>
                        <form class="edit" id="edit-status" method="POST">
                            <input type="hidden" name="field" value="status">
                            <select name="value">
                                <option value="">-- Select --</option>
                                <?php foreach ($STATUS_OPTIONS as $opt): ?>
                                    <option value="<?= h($opt) ?>" <?= strtoupper((string)($vehicle["status"] ?? "")) === $opt ? "selected" : "" ?>>
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
                </div>

                <div class="divider"></div>

                <h3><i class="fa-solid fa-user"></i> Owner / Driver</h3>
                <div class="dl">
                    <?php editableTextRow("Driver", "driver", $vehicle["driver"] ?? ""); ?>
                    <?php editableTextRow("Owner", "owner", $vehicle["owner"] ?? ""); ?>
                    <?php editableTextRow("Owner IC", "owneric", $vehicle["owneric"] ?? "", true); ?>
                </div>

                <div class="divider"></div>

                <h3><i class="fa-solid fa-file-signature"></i> Renewals</h3>
                <div class="dl">
                    <div class="dt">Road Tax Due</div>
                    <div class="dd">
                        <div class="value-wrap">
                            <span class="view"><?= valOrDash($vehicle["roadtaxdue"] ?? "") ?></span>
                            <button class="icon-btn" type="button" title="Edit" onclick="startEdit('roadtaxdue')">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                        </div>
                        <form class="edit" id="edit-roadtaxdue" method="POST">
                            <input type="hidden" name="field" value="roadtaxdue">
                            <input type="date" name="value" value="<?= h($vehicle["roadtaxdue"] ?? "") ?>">
                            <div class="edit-actions">
                                <button class="small-btn save-btn" type="submit" title="Save"><i class="fa-solid fa-check"></i></button>
                                <button class="small-btn cancel-btn" type="button" title="Cancel" onclick="cancelEdit('roadtaxdue')"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        </form>
                    </div>

                    <div class="dt">Insurance Due</div>
                    <div class="dd">
                        <div class="value-wrap">
                            <span class="view"><?= valOrDash($vehicle["insurancedue"] ?? "") ?></span>
                            <button class="icon-btn" type="button" title="Edit" onclick="startEdit('insurancedue')">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                        </div>
                        <form class="edit" id="edit-insurancedue" method="POST">
                            <input type="hidden" name="field" value="insurancedue">
                            <input type="date" name="value" value="<?= h($vehicle["insurancedue"] ?? "") ?>">
                            <div class="edit-actions">
                                <button class="small-btn save-btn" type="submit" title="Save"><i class="fa-solid fa-check"></i></button>
                                <button class="small-btn cancel-btn" type="button" title="Cancel" onclick="cancelEdit('insurancedue')"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        </form>
                    </div>
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