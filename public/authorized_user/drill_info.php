<?php
session_start();
if (($_SESSION["role"] ?? "") !== "authorized user") { header("Location: ../login.php"); exit; }

require_once __DIR__ . "/../../config/db.php";
$currentPage = basename($_SERVER["PHP_SELF"]);

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) { die("Invalid drill id."); }

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
$ALLOWED = ["markingno","status","location","itemdescription","remark","dateofpurchase","dateofdisposal"];
$UPPERCASE = ["markingno","status","location"];
$DATE_FIELDS = ["dateofpurchase","dateofdisposal"];

/* delete */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete") {
    try {
        $pdo->prepare("DELETE FROM drill WHERE drillid = :id")->execute([":id" => $id]);
        header("Location: drill.php?msg=deleted");
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

            $pdo->prepare("UPDATE drill SET {$field} = :val, updatedby = :uid, updateddate = NOW() WHERE drillid = :id")
                ->execute([":val"=>$value, ":uid"=>($_SESSION["userid"] ?? null), ":id"=>$id]);

            header("Location: drill_info.php?id={$id}&saved=1");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

if (($_GET["saved"] ?? "") === "1") $success = "Updated successfully.";

/* load drill */
$stmt = $pdo->prepare("
    SELECT d.*, c.companyname,
           u1.fullname AS createdby_name,
           u2.fullname AS updatedby_name,
           u3.fullname AS approvedby_name
    FROM drill d
    LEFT JOIN company c ON c.companyid = d.companyid
    LEFT JOIN \"User\" u1 ON u1.userid = d.createdby
    LEFT JOIN \"User\" u2 ON u2.userid = d.updatedby
    LEFT JOIN \"User\" u3 ON u3.userid = d.approvedby
    WHERE d.drillid = :id
    LIMIT 1
");
$stmt->execute([":id" => $id]);
$drill = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$drill) { die("Drill not found."); }

/* history */
$history = [];
try {
    $hstmt = $pdo->prepare("
        SELECT h.*, u.fullname AS changedby_name
        FROM drillhistory h
        LEFT JOIN \"User\" u ON u.userid = h.changedby
        WHERE h.drillid = :id
        ORDER BY h.changedate DESC
        LIMIT 50
    ");
    $hstmt->execute([":id" => $id]);
    $history = $hstmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $history = []; }

/* render helpers (keeps the SAME HTML structure/behaviour, but avoids repeating blocks) */
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
function editableTextareaRow($label, $field, $value){
    $v = htmlspecialchars((string)$value);
    $view = ($v === "" ? "—" : $v);
    echo <<<HTML
    <div class="dt">{$label}</div>
    <div class="dd">
        <div class="value-wrap">
            <span class="view">{$view}</span>
            <button class="icon-btn" type="button" title="Edit" onclick="startEdit('{$field}')">
                <i class="fa-solid fa-pen-to-square"></i>
            </button>
        </div>
        <form class="edit" id="edit-{$field}" method="POST">
            <input type="hidden" name="field" value="{$field}">
            <textarea name="value">{$v}</textarea>
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
    <title>Drill Info | Authorized</title>

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
                <h2 style="margin:0">Drill Details</h2>
                <div class="top-meta">
                    <span class="chip"><i class="fa-solid fa-hashtag"></i> <b><?= (int)$drill["drillid"] ?></b></span>
                    <span class="chip"><i class="fa-solid fa-building"></i> <b><?= h($drill["companyname"] ?? "") ?></b></span>
                    <span class="chip"><i class="fa-solid fa-screwdriver-wrench"></i> <b><?= h($drill["markingno"] ?? "") ?></b></span>
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
            <h3><i class="fa-solid fa-screwdriver-wrench"></i> Basic Information</h3>

            <div class="dl">
                <?php
                    editableTextRow("Marking No", "markingno", $drill["markingno"] ?? "", true);

                    /* status select */
                    $statusView = valOrDash($drill["status"] ?? "");
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
                                <option value="<?= h($opt) ?>" <?= strtoupper((string)($drill["status"] ?? "")) === $opt ? "selected" : "" ?>>
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
                    editableTextRow("Location", "location", $drill["location"] ?? "", false, true);
                    editableDateRow("Purchase Date", "dateofpurchase", $drill["dateofpurchase"] ?? "");
                    editableDateRow("Disposal Date", "dateofdisposal", $drill["dateofdisposal"] ?? "");
                    editableTextareaRow("Item Description", "itemdescription", $drill["itemdescription"] ?? "");
                    editableTextareaRow("Remark", "remark", $drill["remark"] ?? "");
                ?>
            </div>

            <div class="divider"></div>

            <h3><i class="fa-solid fa-clipboard-check"></i> Approval / Audit</h3>
            <div class="dl">
                <div class="dt">Approval Status</div>
                <div class="dd"><?= h(strtoupper((string)($drill["approvalstatus"] ?? "—"))) ?></div>

                <div class="dt">Approved By</div>
                <div class="dd"><?= h($drill["approvedby_name"] ?? $drill["approvedby"] ?? "—") ?></div>

                <div class="dt">Approved Date</div>
                <div class="dd"><?= h(fmtDateDMY($drill["approveddate"] ?? null)) ?></div>

                <div class="dt">Created By</div>
                <div class="dd"><?= h($drill["createdby_name"] ?? $drill["createdby"] ?? "—") ?></div>

                <div class="dt">Created Date</div>
                <div class="dd"><?= h(fmtDateDMY($drill["createddate"] ?? null)) ?></div>

                <div class="dt">Updated By</div>
                <div class="dd"><?= h($drill["updatedby_name"] ?? $drill["updatedby"] ?? "—") ?></div>

                <div class="dt">Updated Date</div>
                <div class="dd"><?= h(fmtDateDMY($drill["updateddate"] ?? null)) ?></div>
            </div>
        </section>

        <section class="panel">
            <h3><i class="fa-solid fa-clock-rotate-left"></i> History (latest 50)</h3>
            <?php if (!$history): ?>
                <div class="muted">No history records found.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Changed Date</th>
                            <th>Changed By</th>
                            <th>Status</th>
                            <th>Location</th>
                            <th>Marking No</th>
                            <th>Approval</th>
                            <th>Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $hr): ?>
                        <tr>
                            <td><?= h(fmtDateDMY($hr["changedate"] ?? null)) ?></td>
                            <td><?= h($hr["changedby_name"] ?? $hr["changedby"] ?? "") ?></td>
                            <td><?= h($hr["status"] ?? "") ?></td>
                            <td><?= h($hr["location"] ?? "") ?></td>
                            <td><?= h($hr["markingno"] ?? "") ?></td>
                            <td><?= h(strtoupper((string)($hr["approvalstatus"] ?? ""))) ?></td>
                            <td><?= h($hr["remark"] ?? "") ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
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