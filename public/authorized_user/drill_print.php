<?php
session_start();
if (($_SESSION["role"] ?? "") !== "authorized user") { header("Location: ../login.php"); exit; }

require_once __DIR__ . "/../../config/db.php";

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); }
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
$perPage = 25;
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
@page{size:A4 landscape;margin:10mm}
:root{--ink:#111827;--muted:#4b5563;--line:#111827;--line2:#374151;--head:#f3f4f6;--stripe:#fafafa}
*{box-sizing:border-box}
body{margin:0;color:var(--ink);font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,"Noto Sans","Liberation Sans",sans-serif;font-size:10px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.header{border:1px solid var(--line);padding:10px 12px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:flex-end;gap:12px}
.h-title{margin:0;text-align:center;font-size:20px;font-weight:900;letter-spacing:.6px;text-transform:uppercase;line-height:1.05}
.h-sub{margin:4px 0 0;text-align:center;font-size:10.5px;color:var(--muted);font-weight:600}
.h-left{flex:1}
.h-center{flex:2}
.h-right{flex:1;text-align:right;color:var(--muted);font-size:9.5px;line-height:1.3;white-space:nowrap}
.h-right b{color:var(--ink)}
.box{border:1px solid var(--line);padding:10px}
.table-wrap{margin-top:8px;border:1px solid var(--line);overflow:hidden}
table{width:100%;border-collapse:collapse;table-layout:fixed}
thead th{background:var(--head);border-bottom:1px solid var(--line);border-right:1px solid var(--line2);padding:5px 5px;text-align:center;font-weight:900;font-size:9px;text-transform:uppercase;letter-spacing:.25px;white-space:nowrap}
thead th:last-child{border-right:none}
tbody td{border-bottom:1px solid var(--line2);border-right:1px solid var(--line2);padding:4px 5px;vertical-align:top;font-size:9.6px;line-height:1.2;white-space:normal;word-break:break-word}
tbody td:last-child{border-right:none}
tbody tr:nth-child(even) td{background:var(--stripe)}
tbody tr:last-child td{border-bottom:none}
tr{break-inside:avoid;page-break-inside:avoid}
.center{text-align:center}
.nowrap{white-space:nowrap}
.footer{margin-top:14px;display:flex;justify-content:space-between;gap:18px}
.sign{flex:1;color:var(--muted);font-size:9.5px}
.line{margin-top:26px;border-bottom:1px solid var(--line)}
  </style>
</head>

<body onload="window.print()">
  <div class="header">
    <div class="h-left"></div>

    <div class="h-center">
      <p class="h-title">Inventory / Asset Register</p>
      <div class="h-sub">Category: <b>Remedial Work Tool &amp; Materials</b></div>
    </div>

    <div class="h-right">
      Page No: <b><?= (int)$page ?>/<?= (int)$totalPages ?></b><br/>
      Printed at: <b><?= h($printedAt) ?></b>
    </div>
  </div>

  <div class="box">
    <div class="table-wrap">
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
              <td class="center nowrap"><?= (int)($offset + $i + 1) ?></td>
              <td><?= h($r["itemdescription"] ?? "") ?></td>
              <td class="nowrap"><?= h($r["markingno"] ?? "") ?></td>
              <td class="center nowrap"><?= h(fmtDateDMY($r["dateofpurchase"] ?? null)) ?></td>
              <td class="center nowrap"><?= h(fmtDateDMY($r["dateofdisposal"] ?? null)) ?></td>
              <td><?= h($r["remark"] ?? "") ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="footer">
      <div class="sign">Prepared by<div class="line"></div></div>
      <div class="sign">Checked by<div class="line"></div></div>
      <div class="sign">Approved by<div class="line"></div></div>
    </div>
  </div>
</body>
</html>