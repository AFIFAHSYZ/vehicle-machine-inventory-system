<?php
session_start();
if (($_SESSION["role"] ?? "") !== "authorized user") { header("Location: ../login.php"); exit; }

require_once __DIR__ . "/../../config/db.php";

$currentPage = basename($_SERVER["PHP_SELF"]);

$q = trim($_GET["q"] ?? "");
$companyId = (int)($_GET["company_id"] ?? 0);
$status = trim($_GET["approvalstatus"] ?? ""); // pending/approved/rejected/all(empty)

$companies = $pdo->query("SELECT companyid, companyname FROM company ORDER BY companyname ASC")->fetchAll(PDO::FETCH_ASSOC);

$sql = "
    SELECT v.*, c.companyname
    FROM vehicle v
    JOIN company c ON c.companyid = v.companyid
    WHERE 1=1
";
$params = [];

if ($companyId > 0) { $sql .= " AND v.companyid = :companyid"; $params[":companyid"] = $companyId; }
if ($status !== "" && $status !== "all") { $sql .= " AND v.approvalstatus = :st"; $params[":st"] = $status; }

if ($q !== "") {
    $sql .= " AND (
        v.platenumber ILIKE :q OR COALESCE(v.model,'') ILIKE :q OR COALESCE(v.vehicletype,'') ILIKE :q
        OR COALESCE(v.status,'') ILIKE :q OR COALESCE(v.driver,'') ILIKE :q OR COALESCE(v.owner,'') ILIKE :q
    )";
    $params[":q"] = "%{$q}%";
}

$sql .= " ORDER BY v.updateddate DESC NULLS LAST, v.createddate DESC NULLS LAST, v.vehicleid DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                <div class="sub">View all vehicles (filter by approval status).</div>
            </div>
            <div style="display:flex;gap:.6rem;flex-wrap:wrap">
                <a class="btn secondary" href="add_vehicle.php">Add Vehicle</a>
                <a class="btn" href="approve.php">Approvals</a>
            </div>
        </div>

        <div class="card">
            <form class="filters" method="GET">
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
                <div>
                    <label>Approval</label>
                    <select class="input" name="approvalstatus">
                        <option value="" <?= $status==="" ? "selected":"" ?>>Any</option>
                        <option value="pending" <?= $status==="pending" ? "selected":"" ?>>pending</option>
                        <option value="approved" <?= $status==="approved" ? "selected":"" ?>>approved</option>
                        <option value="rejected" <?= $status==="rejected" ? "selected":"" ?>>rejected</option>
                    </select>
                </div>
                <div><button class="btn" type="submit">Filter</button></div>
            </form>

            <div style="margin:.8rem 0;color:var(--muted);font-size:.92rem">
                Showing <b><?= count($vehicles) ?></b> record(s)
            </div>

            <table>
                <thead>
                <tr>
                    <th>ID</th><th>Plate</th><th>Company</th><th>Model</th><th>Type</th><th>Status</th><th>Approval</th><th>Updated</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$vehicles): ?>
                    <tr><td colspan="8" style="color:var(--muted)">No vehicles found.</td></tr>
                <?php else: foreach ($vehicles as $v): ?>
                    <tr>
                        <td><?= (int)$v["vehicleid"] ?></td>
                        <td><b><?= htmlspecialchars($v["platenumber"]) ?></b></td>
                        <td><?= htmlspecialchars($v["companyname"]) ?></td>
                        <td><?= htmlspecialchars($v["model"] ?? "") ?></td>
                        <td><?= htmlspecialchars($v["vehicletype"] ?? "") ?></td>
                        <td><?= htmlspecialchars($v["status"] ?? "") ?></td>
                        <td><span class="pill"><?= htmlspecialchars($v["approvalstatus"]) ?></span></td>
                        <td><?= htmlspecialchars($v["updateddate"] ?? $v["createddate"] ?? "") ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>