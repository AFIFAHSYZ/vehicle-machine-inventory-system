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

/* same filters as vehicles.php (NO pagination for print) */
$q = trim((string)($_GET["q"] ?? ""));
$companyId = (int)($_GET["company_id"] ?? 0);

$where = [];
$params = [];

if ($companyId > 0) {
    $where[] = "v.companyid = :companyid";
    $params[":companyid"] = $companyId;
}

if ($q !== "") {
    $where[] = "(
        v.platenumber ILIKE :q
        OR COALESCE(v.model,'') ILIKE :q
        OR COALESCE(v.vehicletype,'') ILIKE :q
        OR COALESCE(v.status,'') ILIKE :q
        OR COALESCE(v.driver,'') ILIKE :q
        OR COALESCE(v.owner,'') ILIKE :q
    )";
    $params[":q"] = "%{$q}%";
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

/* Load all data (no LIMIT) */
$sql = "
    SELECT v.*, c.companyname
    FROM vehicle v
    JOIN company c ON c.companyid = v.companyid
    {$whereSql}
    ORDER BY v.updateddate DESC NULLS LAST, v.createddate DESC NULLS LAST, v.vehicleid DESC
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
  <title>Vehicles Print</title>

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
    td{ font-size:10.5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .center{ text-align:center; }
    tr{ page-break-inside:avoid; }
  </style>
</head>

<body onload="window.print()">
  <div class="head">
    <div>
      <p class="title">VEHICLE REGISTER</p>
      <div class="sub">
        Filter: Search=<b><?= h($q ?: "ALL") ?></b>,
        Company=<b><?= $companyId > 0 ? (int)$companyId : "ALL" ?></b>
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
          <th style="width:120px">Plate</th>
          <th style="width:180px">Company</th>
          <th style="width:150px">Model</th>
          <th style="width:140px">Type</th>
          <th style="width:120px">Status</th>
          <th style="width:130px">Road Tax Due</th>
          <th style="width:130px">Insurance Due</th>
          <th>Driver</th>
          <th>Owner</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="10" class="center">No records</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $i => $r): ?>
          <tr>
            <td class="center"><?= (int)($i + 1) ?></td>
            <td><b><?= h($r["platenumber"] ?? "") ?></b></td>
            <td><?= h($r["companyname"] ?? "") ?></td>
            <td><?= h($r["model"] ?? "") ?></td>
            <td><?= h($r["vehicletype"] ?? "") ?></td>
            <td class="center"><?= h($r["status"] ?? "") ?></td>
            <td class="center"><?= h(fmtDateDMY($r["roadtaxdue"] ?? null)) ?></td>
            <td class="center"><?= h(fmtDateDMY($r["insurancedue"] ?? null)) ?></td>
            <td><?= h($r["driver"] ?? "") ?></td>
            <td><?= h($r["owner"] ?? "") ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>