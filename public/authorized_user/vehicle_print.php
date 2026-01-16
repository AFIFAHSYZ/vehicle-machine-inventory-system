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

/* Filters (same as vehicles.php, no pagination) */
$q = trim((string)($_GET["q"] ?? ""));
$companyId = (int)($_GET["company_id"] ?? 0);

$where = [];
$params = [];

if ($companyId > 0) { $where[] = "v.companyid = :companyid"; $params[":companyid"] = $companyId; }

if ($q !== "") {
  $where[] = "(
    v.platenumber ILIKE :q
    OR COALESCE(v.model,'') ILIKE :q
    OR COALESCE(v.vehicletype,'') ILIKE :q
    OR COALESCE(v.status,'') ILIKE :q
    OR COALESCE(v.driver,'') ILIKE :q
    OR COALESCE(v.owner,'') ILIKE :q
    OR COALESCE(v.owneric,'') ILIKE :q
  )";
  $params[":q"] = "%{$q}%";
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

/* Sort by companyid ASC then type */
$sql = "
  SELECT v.*, c.companyname
  FROM vehicle v
  JOIN company c ON c.companyid = v.companyid
  {$whereSql}
  ORDER BY
    v.companyid ASC,
    COALESCE(v.vehicletype,'') ASC,
    v.updateddate DESC NULLS LAST,
    v.createddate DESC NULLS LAST,
    v.vehicleid DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$printedAt = (new DateTime())->format("d-m-Y H:i");
$filterSearchLabel  = $q !== "" ? $q : "ALL";
$filterCompanyLabel = $companyId > 0 ? "ID {$companyId}" : "ALL";

/* Group by CompanyID -> Type */
$groups = [];
foreach ($rows as $r) {
  $cid = (int)($r["companyid"] ?? 0);
  $cname = trim((string)($r["companyname"] ?? "")); if ($cname === "") $cname = "—";
  $type  = trim((string)($r["vehicletype"] ?? "")); if ($type === "") $type = "—";
  if (!isset($groups[$cid])) $groups[$cid] = ["companyname" => $cname, "types" => []];
  $groups[$cid]["types"][$type][] = $r;
}
ksort($groups);
foreach ($groups as $cid => $g) { $t = $g["types"]; ksort($t, SORT_NATURAL | SORT_FLAG_CASE); $groups[$cid]["types"] = $t; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Vehicle Register (Print)</title>

  <style>
@page{size:A4 landscape;margin:10mm}
:root{--ink:#111827;--muted:#4b5563;--line:#111827;--line2:#374151;--head:#f3f4f6;--stripe:#fafafa}
*{box-sizing:border-box}
body{margin:0;color:var(--ink);font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,"Noto Sans","Liberation Sans",sans-serif;font-size:9.8px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.report-header{border:1px solid var(--line);padding:10px 12px;margin-bottom:8px;break-after:avoid;page-break-after:avoid}
.title{text-align:center;margin:0;font-size:22px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;line-height:1.05}
.subtitle{text-align:center;margin:3px 0 0;font-size:11px;color:var(--muted);line-height:1.15}
.meta-row{margin-top:8px;display:flex;justify-content:space-between;align-items:flex-start;gap:10px;color:var(--muted);font-size:9.5px;line-height:1.25}
.meta-row b{color:var(--ink)}
.meta-right{text-align:right;white-space:nowrap}
.section{margin-bottom:10px;break-inside:auto;page-break-inside:auto}
.section-title{border:1px solid var(--line);border-bottom:none;padding:6px 8px;text-align:center;font-size:11px;font-weight:800;letter-spacing:.35px;text-transform:uppercase;break-after:avoid;page-break-after:avoid}
.section-sub{margin-top:2px;font-size:9.5px;font-weight:500;text-transform:none;letter-spacing:0;color:var(--muted)}
.table-wrap{border:1px solid var(--line);border-top:none;overflow:hidden}
table{width:100%;border-collapse:collapse;table-layout:auto}
thead th{background:var(--head);border-bottom:1px solid var(--line);border-right:1px solid var(--line2);padding:4px 4px;text-align:center;font-weight:800;font-size:9px;text-transform:uppercase;letter-spacing:.25px;line-height:1.05;white-space:nowrap}
thead th:last-child{border-right:none}
tbody td{border-bottom:1px solid var(--line2);border-right:1px solid var(--line2);padding:3px 4px;vertical-align:top;font-size:9.6px;line-height:1.15;white-space:normal;word-break:break-word}
tbody td:last-child{border-right:none}
tbody tr:nth-child(even) td{background:var(--stripe)}
tbody tr:last-child td{border-bottom:none}
tr{break-inside:avoid;page-break-inside:avoid}
.center{text-align:center}
.nowrap{white-space:nowrap}
.plate{font-weight:400;letter-spacing:.1px;white-space:normal;overflow-wrap:anywhere;word-break:break-word} /* NOT bold */
.owner-line{font-weight:400} /* NOT bold */
.owner-line .owner-ic{font-weight:400;color:var(--muted);margin-left:2px;white-space:nowrap}
.footer{margin-top:8px;display:flex;justify-content:space-between;gap:10px;color:var(--muted);font-size:9px;border-top:1px solid var(--line);padding-top:5px;line-height:1.15}
@media print{.section:first-of-type{break-before:avoid;page-break-before:avoid}}
  </style>
</head>

<body onload="window.print()">
  <header class="report-header">
    <p class="title">Vehicle Summary</p>
    <p class="subtitle">Grouped by Company (ID order) &amp; Vehicle Type</p>

    <div class="meta-row">
      <div class="meta-left">
        Search: <b><?= h($filterSearchLabel) ?></b><br>
        Company Filter: <b><?= h($filterCompanyLabel) ?></b>
      </div>
      <div class="meta-right">
        Printed at: <b><?= h($printedAt) ?></b><br>
        Total records: <b><?= count($rows) ?></b>
      </div>
    </div>
  </header>

  <?php if (!$rows): ?>
    <div class="table-wrap" style="border-top:1px solid var(--line);">
      <table><tbody><tr><td class="center" style="padding:12px">No records</td></tr></tbody></table>
    </div>
  <?php else: ?>

    <?php foreach ($groups as $cid => $g): ?>
      <?php foreach ($g["types"] as $vehicleType => $items): ?>
        <section class="section">
          <div class="section-title">
            <?= h($g["companyname"]) ?>
            <div class="section-sub">
              Type: <b><?= h($vehicleType) ?></b> • Records: <b><?= count($items) ?></b>
            </div>
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>No.</th>
                  <th>Plate</th>
                  <th>Model</th>
                  <th>Road Tax</th>
                  <th>Insurance</th>
                  <th>Driver</th>
                  <th>Owner</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $i => $r): ?>
                  <?php
                    $owner = trim((string)($r["owner"] ?? ""));
                    $ownerIc = trim((string)($r["owneric"] ?? ""));
                    $ownerDisplay = $owner !== "" ? $owner : "—";
                    $ownerIcDisplay = $ownerIc !== "" ? $ownerIc : "—";
                  ?>
                  <tr>
                    <td class="center nowrap"><?= (int)($i + 1) ?></td>
                    <td><span class="plate"><?= h($r["platenumber"] ?? "") ?></span></td>
                    <td><?= h($r["model"] ?? "") ?></td>
                    <td class="center nowrap"><?= h(fmtDateDMY($r["roadtaxdue"] ?? null)) ?></td>
                    <td class="center nowrap"><?= h(fmtDateDMY($r["insurancedue"] ?? null)) ?></td>
                    <td><?= h($r["driver"] ?? "") ?></td>
                    <td>
                      <div class="owner-line">
                        <?= h($ownerDisplay) ?>
                        <span class="owner-ic">(<?= h($ownerIcDisplay) ?>)</span>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endforeach; ?>
    <?php endforeach; ?>

  <?php endif; ?>

  <div class="footer">
    <div>Generated by Inventory System</div>
  </div>
</body>
</html>