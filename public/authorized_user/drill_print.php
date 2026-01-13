<?php
session_start();
if (($_SESSION["role"] ?? "") !== "authorized user") { header("Location: ../login.php"); exit; }

require_once __DIR__ . "/../../config/db.php";

function h($v){ return htmlspecialchars((string)$v); }
function fmtDateDMY($v): string {
    if (!$v) return "—";
    try { return (new DateTime((string)$v))->format("d-m-Y"); }
    catch (Throwable $e) { return "—"; }
}

/* filters (same as drill.php) */
$q = trim((string)($_GET["q"] ?? ""));
$status = trim((string)($_GET["status"] ?? ""));
$companyid = (int)($_GET["companyid"] ?? 0);

/* pagination for print pages */
$perPage = 25;                              // adjust if you want more/less per printed page
$page = max(1, (int)($_GET["page"] ?? 1));
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($q !== "") {
    $where[] = "(d.markingno ILIKE :q OR d.location ILIKE :q OR d.status ILIKE :q OR d.itemdescription ILIKE :q OR CAST(d.drillid AS TEXT) ILIKE :q)";
    $params[":q"] = "%{$q}%";
}
if ($status !== "") {
    $where[] = "UPPER(COALESCE(d.status,'')) = UPPER(:status)";
    $params[":status"] = $status;
}
if ($companyid > 0) {
    $where[] = "d.companyid = :companyid";
    $params[":companyid"] = $companyid;
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

/* total for page x/y */
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM drill d {$whereSql}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

/* data for current print page */
$sql = "
    SELECT d.itemdescription, d.markingno, d.dateofpurchase, d.dateofdisposal, d.remark
    FROM drill d
    {$whereSql}
    ORDER BY d.drillid DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$printedAt = (new DateTime())->format("d-m-Y H:i");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Inventory / Asset Register (Print)</title>

  <style>
    /* FORCE LANDSCAPE */
    @page{ size:A4 landscape; margin:10mm; }

    body{ font-family: Arial, Helvetica, sans-serif; color:#111; font-size:11px; margin:0; }
    .head{ display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin:0 0 8px; }
    .title{ font-size:14px; font-weight:900; margin:0; letter-spacing:.2px; text-transform:uppercase; }
    .sub{ font-size:10.5px; color:#222; margin-top:2px; }
    .meta{ font-size:10px; color:#444; text-align:right; white-space:nowrap; }
    .meta b{ color:#111; }
    .box{ border:1px solid #111; padding:8px; border-radius:6px; }

    table{ width:100%; border-collapse:collapse; margin-top:8px; table-layout:fixed; }
    th,td{ border:1px solid #111; padding:5px 6px; vertical-align:top; }
    th{ background:#f2f2f2; text-align:center; font-weight:900; font-size:10px; text-transform:uppercase; }
    td{ font-size:10.5px; overflow:hidden; text-overflow:ellipsis; }
    .center{ text-align:center; }
    .nowrap{ white-space:nowrap; }
    .wraptext{ white-space:normal; word-break:break-word; }

    .footer{ margin-top:10px; display:flex; justify-content:space-between; gap:10px; font-size:10px; color:#444; }
    .sign{ width:32%; }
    .line{ margin-top:18px; border-top:1px solid #111; }
    tr{ page-break-inside:avoid; }
  </style>
</head>

<body onload="window.print()">
  <div class="head">
    <div>
      <p class="title">INVENTORY / ASSET REGISTER</p>
      <div class="sub">CATEGORY: <b>REMEDIAL WORK TOOL &amp; MATERIALS</b></div>
    </div>

    <div class="meta">
      PAGE NO: <b><?= (int)$page ?>/<?= (int)$totalPages ?></b><br/>
      Printed at: <b><?= h($printedAt) ?></b>
    </div>
  </div>

  <div class="box">
    <table>
      <thead>
        <tr>
          <th style="width:42px">No.</th>
          <th>Item Description</th>
          <th style="width:170px">Marking No.</th>
          <th style="width:120px">Date of Purchase</th>
          <th style="width:120px">Date of Disposal</th>
          <th style="width:220px">Remark</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="6" class="center">No records</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $i => $r): ?>
          <tr>
            <td class="center"><?= (int)($offset + $i + 1) ?></td>
            <td class="wraptext"><?= h($r["itemdescription"] ?? "") ?></td>
            <td class="nowrap"><?= h($r["markingno"] ?? "") ?></td>
            <td class="center nowrap"><?= h(fmtDateDMY($r["dateofpurchase"] ?? null)) ?></td>
            <td class="center nowrap"><?= h(fmtDateDMY($r["dateofdisposal"] ?? null)) ?></td>
            <td class="wraptext"><?= h($r["remark"] ?? "") ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>

    <div class="footer">
      <div class="sign">Prepared by<div class="line"></div></div>
      <div class="sign">Checked by<div class="line"></div></div>
      <div class="sign">Approved by<div class="line"></div></div>
    </div>
  </div>
</body>
</html>