<?php
session_start();
if (($_SESSION["role"] ?? "") !== "authorized user") { header("Location: ../login.php"); exit; }

require_once __DIR__ . "/../../config/db.php";
$currentPage = basename($_SERVER["PHP_SELF"]);

function h($v){ return htmlspecialchars((string)$v); }

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) { header("Location: drill.php"); exit; }

/* Use fullname from "User" table */
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
");
$stmt->execute([":id" => $id]);
$drill = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$drill) { header("Location: drill.php"); exit; }

/* History (with fullname for changedby) */
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
} catch (Throwable $e) {
    $history = [];
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
        .card{margin-top:1rem;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow2);padding:1rem;overflow:hidden;}
        .grid{display:grid;grid-template-columns: 1fr 1fr;gap:1rem;}
        .box{background: rgba(255,255,255,.7);border: 1px solid rgba(120,120,160,.18);border-radius: 14px;padding: 1rem;}
        .kv{display:grid;grid-template-columns: 170px 1fr;gap:.55rem .85rem;align-items:start;}
        .k{font-weight:900;color:#2b3242}
        .v{color:#111827; word-break:break-word}
        .muted{color:var(--muted)}
        table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden}
        thead th{background:#f6f7ff;text-align:left;padding:.7rem;border-bottom:1px solid rgba(120,120,160,.18);font-size:.85rem}
        tbody td{padding:.7rem;border-bottom:1px solid rgba(120,120,160,.12);font-size:.92rem;vertical-align:top}
        tbody tr:hover{background:#fafbff}
        @media (max-width: 980px){ .grid{grid-template-columns:1fr} .kv{grid-template-columns: 140px 1fr} }
    </style>
</head>
<body>
<div class="app">
<?php include "sidebar.php";?>

    <main class="main">
        <div class="header">
            <div>
                <h2>Drill Info</h2>
                <div class="sub">Details for Drill ID: <b><?= (int)$drill["drillid"] ?></b></div>
            </div>
            <div style="display:flex;gap:.6rem;flex-wrap:wrap">
                <a class="btn secondary" href="drill.php"><i class="fa-solid fa-arrow-left"></i>&nbsp;Back</a>
            </div>
        </div>

        <div class="card">
            <div class="grid">
                <div class="box">
                    <h3 style="margin-top:0">Main</h3>
                    <div class="kv">
                        <div class="k">Company</div>
                        <div class="v"><?= h($drill["companyname"] ?? "—") ?></div>

                        <div class="k">Marking No</div>
                        <div class="v"><b><?= h($drill["markingno"] ?? "—") ?></b></div>

                        <div class="k">Status</div>
                        <div class="v"><?= h($drill["status"] ?? "—") ?></div>

                        <div class="k">Location</div>
                        <div class="v"><?= h($drill["location"] ?? "—") ?></div>

                        <div class="k">Purchase Date</div>
                        <div class="v"><?= h($drill["dateofpurchase"] ?? "—") ?></div>

                        <div class="k">Disposal Date</div>
                        <div class="v"><?= h($drill["dateofdisposal"] ?? "—") ?></div>
                                                <div class="k">Disposal Date</div>
                        <div class="v"><?= h($drill["dateofdisposal"] ?? "—") ?></div>

                                                <div class="k">Disposal Date</div>
                        <div class="v"><?= h($drill["dateofdisposal"] ?? "—") ?></div>

                                        <div class="box" style="grid-column:1/-1">
                    <h3 style="margin-top:0">Item Description</h3>
                    <div class="muted"><?= nl2br(h($drill["itemdescription"] ?? "—")) ?></div>
                </div>
                                <div class="box" style="grid-column:1/-1">
                    <h3 style="margin-top:0">Remark</h3>
                    <div class="muted"><?= nl2br(h($drill["remark"] ?? "—")) ?></div>
                </div>

                    </div>
                </div>

                <div class="box">
                    <h3 style="margin-top:0">Approval / Audit</h3>
                    <div class="kv">
                        <div class="k">Approval Status</div>
                        <div class="v"><?= h($drill["approvalstatus"] ?? "—") ?></div>

                        <div class="k">Approved By</div>
                        <div class="v"><?= h($drill["approvedby_name"] ?? $drill["approvedby"] ?? "—") ?></div>

                        <div class="k">Approved Date</div>
                        <div class="v"><?= h($drill["approveddate"] ?? "—") ?></div>

                        <div class="k">Created By</div>
                        <div class="v"><?= h($drill["createdby_name"] ?? $drill["createdby"] ?? "—") ?></div>

                        <div class="k">Created Date</div>
                        <div class="v"><?= h($drill["createddate"] ?? "—") ?></div>

                        <div class="k">Updated By</div>
                        <div class="v"><?= h($drill["updatedby_name"] ?? $drill["updatedby"] ?? "—") ?></div>

                        <div class="k">Updated Date</div>
                        <div class="v"><?= h($drill["updateddate"] ?? "—") ?></div>
                                        </div>

                </div>



                <div class="box" style="grid-column:1/-1">
                    <h3 style="margin-top:0">History (latest 50)</h3>
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
                                    <td><?= h($hr["changedate"] ?? "") ?></td>
                                    <td><?= h($hr["changedby_name"] ?? $hr["changedby"] ?? "") ?></td>
                                    <td><?= h($hr["status"] ?? "") ?></td>
                                    <td><?= h($hr["location"] ?? "") ?></td>
                                    <td><?= h($hr["markingno"] ?? "") ?></td>
                                    <td><?= h($hr["approvalstatus"] ?? "") ?></td>
                                    <td><?= h($hr["remark"] ?? "") ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="text-align:center;color:var(--muted);margin-top:1rem;font-size:.92rem">
            &copy; <?= date('Y') ?> Vehicle and Machine Inventory System
        </div>
    </main>
</div>
</body>
</html>