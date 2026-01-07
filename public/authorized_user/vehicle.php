<?php
session_start();
if (($_SESSION["role"] ?? "") !== "authorized user") { header("Location: ../login.php"); exit; }

require_once __DIR__ . "/../../config/db.php";

$currentPage = basename($_SERVER["PHP_SELF"]);

$q = trim($_GET["q"] ?? "");
$companyId = (int)($_GET["company_id"] ?? 0);

// pagination
$perPage = 10;
$page = (int)($_GET["page"] ?? 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

$companies = $pdo->query("SELECT companyid, companyname FROM company ORDER BY companyname ASC")
    ->fetchAll(PDO::FETCH_ASSOC);

// WHERE + params
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

// total rows
$countSql = "
    SELECT COUNT(*)
    FROM vehicle v
    JOIN company c ON c.companyid = v.companyid
    {$where}
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $perPage);

// page data
$sql = "
    SELECT v.*, c.companyname
    FROM vehicle v
    JOIN company c ON c.companyid = v.companyid
    {$where}
    ORDER BY v.updateddate DESC NULLS LAST, v.createddate DESC NULLS LAST, v.vehicleid DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(":limit", $perPage, PDO::PARAM_INT);
$stmt->bindValue(":offset", $offset, PDO::PARAM_INT);

$stmt->execute();
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

function buildQuery(array $extra = []): string {
    $base = $_GET;
    unset($base["page"]);
    return http_build_query(array_merge($base, $extra));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Vehicles | Authorized</title>
    <link rel="stylesheet" href="../../css/guest_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

</head>
<body>
<div class="app">
<?php include "sidebar.php";?>

    <main class="main">
        <div class="header">
            <div>
                <h2>Vehicles</h2>
                <div class="sub">View all vehicles </div>
            </div>
            <div class="no-print" style="display:flex;gap:.6rem;flex-wrap:wrap">
                <a class="btn secondary" href="add_vehicle.php"><i class="fa-solid fa-plus"></i>&nbsp;Add Vehicle</a>
                <a class="btn" href="approve.php"><i class="fa-solid fa-check"></i>&nbsp;Approvals</a>
                <button class="btn secondary" type="button" onclick="window.print()">
                    <i class="fa-solid fa-print"></i>&nbsp;Print
                </button>
            </div>
        </div>

        <div class="card">
            <form class="filters no-print" method="GET">
                <div>
                    <label>Search</label>
                    <input class="input" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Plate, model, driver, owner..." />
                </div>
                <div>
                    <label>Company</label>
                    <select class="input" name="company_id">
                        <option value="0">All</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= (int)$c["companyid"] ?>" <?= $companyId === (int)$c["companyid"] ? "selected" : "" ?>>
                                <?= htmlspecialchars($c["companyname"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><button class="btn" type="submit">Filter</button></div>
            </form>

            <div style="margin:.8rem 0;color:var(--muted);font-size:.92rem">
                Showing <b><?= count($vehicles) ?></b> record(s) on this page (Total: <b><?= $totalRows ?></b>)
            </div>

            <table>
                <thead>
                <tr>
                    <th>No.</th>
                    <th>Plate</th>
                    <th>Company</th>
                    <th>Model</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Road Tax Due</th>
                    <th>Insurance Due</th>
                    <th class="no-print">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$vehicles): ?>
                    <tr><td colspan="11" style="color:var(--muted)">No vehicles found.</td></tr>
                <?php else: ?>
                    <?php foreach ($vehicles as $idx => $v): ?>
                        <?php $rowNo = $offset + $idx + 1; ?>
                        <tr>
                            <td><?= (int)$rowNo ?></td>
                            <td><b><?= htmlspecialchars($v["platenumber"]) ?></b></td>
                            <td><?= htmlspecialchars($v["companyname"]) ?></td>
                            <td><?= htmlspecialchars($v["model"] ?? "") ?></td>
                            <td><?= htmlspecialchars($v["vehicletype"] ?? "") ?></td>
                            <td><?= htmlspecialchars($v["status"] ?? "") ?></td>
                            <td><?= htmlspecialchars($v["roadtaxdue"] ?? "") ?></td>
                            <td><?= htmlspecialchars($v["insurancedue"] ?? "") ?></td>
                            <td class="no-print">
                                <a class="btn secondary" style="padding:.45rem .75rem;font-size:.82rem"
                                   href="vehicle_info.php?id=<?= (int)$v["vehicleid"] ?>">
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
                <div class="pagination no-print">
                    <div>Page <b><?= $page ?></b> of <b><?= $totalPages ?></b></div>

                    <div class="page-links">
                        <?php $prev = $page - 1; $next = $page + 1; ?>

                        <a class="page-btn <?= $page <= 1 ? "disabled" : "" ?>"
                           href="?<?= buildQuery(["page" => $prev]) ?>">Prev</a>

                        <?php
                        $start = max(1, $page - 3);
                        $end = min($totalPages, $page + 3);
                        for ($p = $start; $p <= $end; $p++):
                        ?>
                            <a class="page-btn <?= $p === $page ? "active" : "" ?>"
                               href="?<?= buildQuery(["page" => $p]) ?>"><?= $p ?></a>
                        <?php endfor; ?>

                        <a class="page-btn <?= $page >= $totalPages ? "disabled" : "" ?>"
                           href="?<?= buildQuery(["page" => $next]) ?>">Next</a>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>
</body>
</html>