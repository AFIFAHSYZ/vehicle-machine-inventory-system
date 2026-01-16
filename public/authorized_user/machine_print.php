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

/* same filters as machine.php */
$q = trim((string)($_GET["q"] ?? ""));
$typeFilter = trim((string)($_GET["type"] ?? ""));

$where = ["1=1"];
$params = [];

if ($typeFilter !== "") { $where[] = "COALESCE(e.equipmenttype,'') = :type"; $params[":type"] = $typeFilter; }
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
    e.serialno, e.equipmenttype, e.model, e.codeno, e.status, e.location,
    e.datecalibration, e.nextcalibration, e.certificationno
  FROM equipment e
  {$whereSql}
  ORDER BY
    COALESCE(e.equipmenttype,'') ASC,
    e.updateddate DESC NULLS LAST,
    e.createddate DESC NULLS LAST,
    e.equipmentid DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$printedAt = (new DateTime())->format("d-m-Y H:i");

/* group by type */
$groups = [];
foreach ($rows as $r) {
  $t = trim((string)($r["equipmenttype"] ?? ""));
  if ($t === "") $t = "—";
  $groups[$t][] = $r;
}
ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

$totalRecords = count($rows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Machines / Equipment Register (Print)</title>

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
.section{margin-top:10px;break-inside:avoid;page-break-inside:avoid}
.section-title{border:1px solid var(--line);padding:6px 8px;text-align:center;font-size:11px;font-weight:900;letter-spacing:.3px;text-transform:uppercase;background:#fff}
.section-sub{margin-top:2px;text-align:center;font-size:9.5px;color:var(--muted);font-weight:600}
.table-wrap{margin-top:6px;border:1px solid var(--line);overflow:hidden}
table{width:100%;border-collapse:collapse;table-layout:fixed}
thead th{background:var(--head);border-bottom:1px solid var(--line);border-right:1px solid var(--line2);padding:5px 5px;text-align:center;font-weight:900;font-size:9px;text-transform:uppercase;letter-spacing:.25px;white-space:nowrap}
thead th:last-child{border-right:none}
thead th.th-cert{white-space:normal;line-height:1.05;font-size:8.6px}
tbody td{border-bottom:1px solid var(--line2);border-right:1px solid var(--line2);padding:4px 5px;vertical-align:top;font-size:9.6px;line-height:1.2;white-space:normal;word-break:normal;overflow-wrap:break-word}
tbody td:last-child{border-right:none}
tbody tr:nth-child(even) td{background:var(--stripe)}
tbody tr:last-child td{border-bottom:none}
tr{break-inside:avoid;page-break-inside:avoid}
.center{text-align:center}
.nowrap{white-space:nowrap}
td.col-serial,td.col-code,td.col-status,td.col-cal,td.col-next,td.col-cert{white-space:nowrap}
td.col-location{white-space:normal;word-break:normal;overflow-wrap:break-word}
.footer{margin-top:14px;display:flex;justify-content:space-between;gap:18px}
.sign{flex:1;color:var(--muted);font-size:9.5px}
.line{margin-top:26px;border-bottom:1px solid var(--line)}
  </style>
</head>

<body onload="window.print()">
  <div class="header">
    <div class="h-left"></div>

    <div class="h-center">
      <p class="h-title">Machines / Equipment Register</p>
      <div class="h-sub">
        Filter: Search=<b><?= h($q ?: "ALL") ?></b>, Type=<b><?= h($typeFilter ?: "ALL") ?></b>
      </div>
    </div>

    <div class="h-right">
      Printed at: <b><?= h($printedAt) ?></b><br/>
      Total records: <b><?= (int)$totalRecords ?></b>
    </div>
  </div>

  <div class="box">
    <?php if (!$rows): ?>
      <div class="center" style="padding:10px">No records</div>
    <?php else: ?>

      <?php foreach ($groups as $etype => $items): ?>
        <section class="section">
          <div class="section-title"><?= h($etype) ?></div>
          <div class="section-sub">Records: <b><?= count($items) ?></b></div>

          <div class="table-wrap">
            <table>
              <colgroup>
                <col style="width:34px">
                <col style="width:85px">
                <col style="width:110px">
                <col style="width:75px">
                <col style="width:55px">
                <col style="width:65px">
                <col style="width:95px">
                <col style="width:75px">
                <col style="width:85px">
                <col style="width:95px">
              </colgroup>

              <thead>
                <tr>
                  <th>No.</th>
                  <th>Serial No.</th>
                  <th>Equipment Type</th>
                  <th>Model</th>
                  <th>Code No.</th>
                  <th>Status</th>
                  <th>Location</th>
                  <th>Calibration</th>
                  <th>Next Calibration</th>
                  <th class="th-cert">Certification No.</th>
                </tr>
              </thead>

              <tbody>
                <?php foreach ($items as $i => $r): ?>
                  <tr>
                    <td class="center nowrap"><?= (int)($i + 1) ?></td>
                    <td class="col-serial"><?= h($r["serialno"] ?? "") ?></td>
                    <td><?= h($r["equipmenttype"] ?? "") ?></td>
                    <td><?= h($r["model"] ?? "") ?></td>
                    <td class="col-code"><?= h($r["codeno"] ?? "") ?></td>
                    <td class="center col-status"><?= h(strtoupper((string)($r["status"] ?? ""))) ?></td>
                    <td class="col-location"><?= h($r["location"] ?? "") ?></td>
                    <td class="center col-cal"><?= h(fmtDateDMY($r["datecalibration"] ?? null)) ?></td>
                    <td class="center col-next"><?= h(fmtDateDMY($r["nextcalibration"] ?? null)) ?></td>
                    <td class="col-cert"><?= h($r["certificationno"] ?? "") ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endforeach; ?>

    <?php endif; ?>

    <div class="footer">
      <div class="sign">Prepared by<div class="line"></div></div>
      <div class="sign">Checked by<div class="line"></div></div>
      <div class="sign">Approved by<div class="line"></div></div>
    </div>
  </div>
</body>
</html>