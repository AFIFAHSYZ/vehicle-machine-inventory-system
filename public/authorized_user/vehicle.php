<?php
session_start();
if (($_SESSION["role"] ?? "") !== "authorized user") { header("Location: ../login.php"); exit; }

require_once __DIR__ . "/../../config/db.php";

$currentPage = basename($_SERVER["PHP_SELF"]);

$q = trim($_GET["q"] ?? "");
$companyId = (int)($_GET["company_id"] ?? 0);

/* pagination */
$perPage = 10;
$page = (int)($_GET["page"] ?? 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

$companies = $pdo->query("SELECT companyid, companyname FROM company ORDER BY companyname ASC")
    ->fetchAll(PDO::FETCH_ASSOC);

/* WHERE + params */
$where = " WHERE 1=1 ";
$params = [];

if ($companyId > 0) {
    $where .= " AND v.companyid = :companyid";
    $params[":companyid"] = $companyId;
}

if ($q !== "") {
    $where .= " AND (
        v.platenumber ILIKE :q OR COALESCE(v.model,'') ILIKE :q OR COALESCE(v.vehicletype,'') ILIKE :q
        OR COALESCE(v.status,'') ILIKE :q OR COALESCE(v.driver,'') ILIKE :q OR COALESCE(v.owner,'') ILIKE :q
    )";
    $params[":q"] = "%{$q}%";
}

/* total rows */
$countSql = "
    SELECT COUNT(*)
    FROM vehicle v
    JOIN company c ON c.companyid = v.companyid
    {$where}
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

/* page data */
$sql = "
    SELECT v.*, c.companyname
    FROM vehicle v
    JOIN company c ON c.companyid = v.companyid
    {$where}
    ORDER BY v.updateddate DESC NULLS LAST, v.createddate DESC NULLS LAST, v.vehicleid DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $val) $stmt->bindValue($k, $val);
$stmt->bindValue(":limit", $perPage, PDO::PARAM_INT);
$stmt->bindValue(":offset", $offset, PDO::PARAM_INT);

$stmt->execute();
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

function buildQuery(array $extra = []): string {
    $base = $_GET;
    unset($base["page"]);
    return http_build_query(array_merge($base, $extra));
}
function h($v){ return htmlspecialchars((string)$v); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Vehicles | Authorized</title>
    <link rel="stylesheet" href="../../css/guest_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

    <style>
        /* compact table */
        .tablewrap table{ width:100%; border-collapse:collapse; }
        .tablewrap thead th{padding:.45rem .55rem;font-size:.78rem;white-space:nowrap;}
        .tablewrap tbody td{padding:.38rem .55rem;font-size:.84rem;vertical-align:middle;}
        .tablewrap tbody tr:hover{ background: rgba(108,99,255,.06); }
        .td-ellipsis{max-width: 170px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .btn.compact{padding:.32rem .55rem !important;font-size:.78rem !important;border-radius:12px;white-space:nowrap;}
        /* print: hide controls */
        @media print{.no-print{ display:none !important; }.card{ box-shadow:none !important; }}
        a.btn.secondary.compact{padding: .38rem .65rem;font-size: .84rem;border-radius: 12px;white-space: nowrap;}
        /* Print button */
        .btn.print{ background:rgba(59,130,246,.12); border:1px solid rgba(59,130,246,.25); color:#1e40af; }

    </style>
</head>
<body>
<div class="app">
<?php include "sidebar.php";?>

    <main class="main">
        <div class="header">
            <div>
                <h2>Vehicles</h2>
                <div class="sub">View all vehicles</div>
            </div>
            <div class="no-print" style="display:flex;gap:.6rem;flex-wrap:wrap">
                <a class="btn secondary" href="add_vehicle.php"><i class="fa-solid fa-plus"></i>&nbsp;Add Vehicle</a>
                <a class="btn" href="approve.php"><i class="fa-solid fa-check"></i>&nbsp;Approvals</a>
<a class="btn print" target="_blank" href="vehicle_print.php?<?= h(buildQuery(["page" => null])) ?>">
  <i class="fa-solid fa-print"></i>&nbsp;Print
</a>            </div>
        </div>

        <div class="card">
            <form class="filters no-print" method="GET">
                <div>
                    <label>Search</label>
                    <input class="input" name="q" value="<?= h($q) ?>" placeholder="Plate, model, driver, owner..." />
                </div>
                <div>
                    <label>Company</label>
                    <select class="input" name="company_id">
                        <option value="0">All</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= (int)$c["companyid"] ?>" <?= $companyId === (int)$c["companyid"] ? "selected" : "" ?>>
                                <?= h($c["companyname"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><button class="btn" type="submit">Filter</button></div>
            </form>

            <div style="margin:.8rem 0;color:var(--muted);font-size:.92rem">
                Showing <b><?= count($vehicles) ?></b> record(s) on this page (Total: <b><?= (int)$totalRows ?></b>)
            </div>

            <div class="tablewrap">
                <table>
                    <thead>
                    <tr>
                        <th style="width:55px">No.</th>
                        <th style="width:120px">Plate</th>
                        <th style="width:170px">Company</th>
                        <th style="width:150px">Model</th>
                        <th style="width:120px">Type</th>
                        <th style="width:110px">Status</th>
                        <th style="width:120px">Road Tax Due</th>
                        <th style="width:120px">Insurance Due</th>
                        <th class="no-print" style="width:90px">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$vehicles): ?>
                        <tr><td colspan="9" style="color:var(--muted)">No vehicles found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($vehicles as $idx => $v): ?>
                            <?php $rowNo = $offset + $idx + 1; ?>
                            <tr>
                                <td><?= (int)$rowNo ?></td>
                                <td><b><?= h($v["platenumber"]) ?></b></td>
                                <td class="td-ellipsis"><?= h($v["companyname"]) ?></td>
                                <td class="td-ellipsis"><?= h($v["model"] ?? "") ?></td>
                                <td><?= h($v["vehicletype"] ?? "") ?></td>
                                <td><?= h($v["status"] ?? "") ?></td>
                                <td><?= h($v["roadtaxdue"] ?? "") ?></td>
                                <td><?= h($v["insurancedue"] ?? "") ?></td>
                                <td class="no-print">
                                    <a class="btn secondary compact"
                                       href="vehicle_info.php?id=<?= (int)$v["vehicleid"] ?>">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination no-print">
                    <div>Page <b><?= (int)$page ?></b> of <b><?= (int)$totalPages ?></b></div>

                    <div class="page-links">
                        <?php $prev = $page - 1; $next = $page + 1; ?>

                        <a class="page-btn <?= $page <= 1 ? "disabled" : "" ?>"
                           href="?<?= h(buildQuery(["page" => $prev])) ?>">Prev</a>

                        <?php
                        $start = max(1, $page - 3);
                        $end = min($totalPages, $page + 3);
                        for ($p = $start; $p <= $end; $p++):
                        ?>
                            <a class="page-btn <?= $p === $page ? "active" : "" ?>"
                               href="?<?= h(buildQuery(["page" => $p])) ?>"><?= (int)$p ?></a>
                        <?php endfor; ?>

                        <a class="page-btn <?= $page >= $totalPages ? "disabled" : "" ?>"
                           href="?<?= h(buildQuery(["page" => $next])) ?>">Next</a>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>
</body>
</html>