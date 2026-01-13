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

/* same filters as machine.php (NO pagination in print) */
$q = trim((string)($_GET["q"] ?? ""));
$type = trim((string)($_GET["type"] ?? ""));

$where = ["1=1"];
$params = [];

if ($type !== "") {
    $where[] = "COALESCE(e.equipmenttype,'') = :type";
    $params[":type"] = $type;
}
if ($q !== "") {
    $where[] = "(
        e.serialno ILIKE :q
        OR COALESCE(e.model,'') ILIKE :q
        OR COALESCE(e.codeno,'') ILIKE :q
        OR COALESCE(e.location,'') ILIKE :q
        OR COALESCE(e.status,'') ILIKE :q
        OR COALESCE(e.equipmenttype,'') ILIKE :q
        OR COALESCE(e.certificationno,'') ILIKE :q
    )";
    $params[":q"] = "%{$q}%";
}
$whereSql = "WHERE " . implode(" AND ", $where);

$sql = "
    SELECT
        e.serialno,
        e.equipmenttype,
        e.model,
        e.codeno,
        e.status,
        e.location,
        e.datecalibration,
        e.nextcalibration,
        e.certificationno
    FROM equipment e
    {$whereSql}
    ORDER BY e.updateddate DESC NULLS LAST, e.createddate DESC NULLS LAST, e.equipmentid DESC
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
  <title>Machine List Print</title>

  <style>
    @page{ size:A4 landscape; margin:10mm; }
    body{ font-family: Arial, Helvetica, sans-serif; color:#111; font-size:11px; margin:0; }
    .head{ display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin:0 0 8px; }
    .title{ font-size:16px; font-weight:900; margin:0; letter-spacing:.2px; text-transform:uppercase; }
    .meta{ font-size:10px; color:#444; text-align:right; white-space:nowrap; }
    .box{ border:1px solid #111; padding:8px; border-radius:6px; }

    table{ width:100%; border-collapse:collapse; margin-top:8px; table-layout:fixed; }
    th,td{ border:1px solid #111; padding:5px 6px; vertical-align:top; }
    th{ background:#f2f2f2; text-align:center; font-weight:900; font-size:10px; text-transform:uppercase; }
    td{ font-size:10.5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .center{ text-align:center; }
    .wraptext{ white-space:normal; word-break:break-word; }
    tr{ page-break-inside:avoid; }
  </style>
</head>

<body onload="window.print()">
  <div class="head">
    <div>
      <p class="title">MACHINES / EQUIPMENT REGISTER</p>
      <div style="font-size:10px;color:#444;margin-top:2px">
        Filter: Search=<b><?= h($q ?: "ALL") ?></b>, Type=<b><?= h($type ?: "ALL") ?></b>
      </div>
    </div>
    <div class="meta">
      Printed at: <b><?= h($printedAt) ?></b><br/>
      Total records: <b><?= count($rows) ?></b>
    </div>
  </div>

  <div class="box">
    <table>
      <thead>
        <tr>
          <th style="width:42px">No.</th>
          <th style="width:140px">Serial</th>
          <th style="width:160px">Type</th>
          <th style="width:140px">Model</th>
          <th style="width:120px">Code</th>
          <th style="width:110px">Status</th>
          <th>Location</th>
          <th style="width:110px">Calibration</th>
          <th style="width:120px">Next Calibration</th>
          <th style="width:150px">Certification</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="10" class="center">No records</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $i => $r): ?>
          <tr>
            <td class="center"><?= (int)($i + 1) ?></td>
            <td><?= h($r["serialno"] ?? "") ?></td>
            <td><?= h($r["equipmenttype"] ?? "") ?></td>
            <td><?= h($r["model"] ?? "") ?></td>
            <td><?= h($r["codeno"] ?? "") ?></td>
            <td class="center"><?= h(strtoupper((string)($r["status"] ?? ""))) ?></td>
            <td><?= h($r["location"] ?? "") ?></td>
            <td class="center"><?= h(fmtDateDMY($r["datecalibration"] ?? null)) ?></td>
            <td class="center"><?= h(fmtDateDMY($r["nextcalibration"] ?? null)) ?></td>
            <td><?= h($r["certificationno"] ?? "") ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>