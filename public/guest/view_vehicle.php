<?php
session_start();
require_once __DIR__ . "/../../config/db.php"; // adjust path if needed

$q = trim($_GET['q'] ?? '');
$companyId = (int)($_GET['company_id'] ?? 0);

try {
    $companies = $pdo->query("SELECT companyid, companyname FROM company ORDER BY companyname ASC")
        ->fetchAll(PDO::FETCH_ASSOC);

    $sql = "
        SELECT
            v.vehicleid,
            v.platenumber,
            v.model,
            v.vehicletype,
            v.roadtaxdue,
            v.insurancedue,
            v.driver,
            v.owner,
            v.owneric,
            v.status,
            v.createddate,
            v.updateddate,
            c.companyname
        FROM vehicle v
        JOIN company c ON c.companyid = v.companyid
        WHERE 1=1
    ";
    $params = [];

    if ($companyId > 0) {
        $sql .= " AND v.companyid = :companyid";
        $params[":companyid"] = $companyId;
    }

    if ($q !== "") {
        $sql .= " AND (
            v.platenumber ILIKE :q
            OR COALESCE(v.model,'') ILIKE :q
            OR COALESCE(v.vehicletype,'') ILIKE :q
            OR COALESCE(v.status,'') ILIKE :q
            OR COALESCE(v.driver,'') ILIKE :q
            OR COALESCE(v.owner,'') ILIKE :q
        )";
        $params[":q"] = "%{$q}%";
    }

    $sql .= " ORDER BY v.updateddate DESC NULLS LAST, v.createddate DESC NULLS LAST, v.vehicleid DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title> Vehicles | Vehicle & Machine Inventory</title>
        <link rel="stylesheet" href="../../css/guest_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

</head>
<body>
<div class="app">
<?php include "g_sidebar.php"; ?>

    <main class="main">
        <div class="header">
            <div>
                <h2>Vehicles</h2>
                <div class="sub">Browse vehicles in the system (read-only).</div>
            </div>
            <div class="actions">
                <a class="btn secondary" href="guest_dashboard.php">Back</a>
            </div>
        </div>

        <div class="card">
            <form class="filters" method="GET">
                <div>
                    <label for="q">Search</label>
                    <input class="input" id="q" name="q" value="<?= htmlspecialchars($q) ?>"
                           placeholder="Plate, model, type, driver, owner, status..." />
                </div>
                <div>
                    <label for="company_id">Company</label>
                    <select class="input" id="company_id" name="company_id">
                        <option value="0">All companies</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= (int)$c["companyid"] ?>" <?= $companyId === (int)$c["companyid"] ? "selected" : "" ?>>
                                <?= htmlspecialchars($c["companyname"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button class="btn" type="submit">Filter</button>
                </div>
            </form>

            <div class="count">Showing <strong><?= count($vehicles) ?></strong> vehicle(s)</div>

            <div class="tablewrap">
                <table>
                    <thead>
                        <tr>
                            <th>Plate</th>
                            <th>Company</th>
                            <th>Model</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Driver</th>
                            <th>Owner</th>
                            <th>Road Tax Due</th>
                            <th>Insurance Due</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$vehicles): ?>
                        <tr><td colspan="10" class="muted">No vehicles found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($vehicles as $v): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($v["platenumber"]) ?></strong></td>
                                <td><?= htmlspecialchars($v["companyname"] ?? "") ?></td>
                                <td><?= htmlspecialchars($v["model"] ?? "") ?></td>
                                <td><?= htmlspecialchars($v["vehicletype"] ?? "") ?></td>
                                <td>
                                    <?php if (!empty($v["status"])): ?>
                                        <span class="statuspill"><?= htmlspecialchars($v["status"]) ?></span>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($v["driver"] ?? "") ?></td>
                                <td>
                                    <?= htmlspecialchars($v["owner"] ?? "") ?>
                                    <?php if (!empty($v["owneric"])): ?>
                                        <div class="muted">IC: <?= htmlspecialchars($v["owneric"]) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($v["roadtaxdue"] ?? "") ?></td>
                                <td><?= htmlspecialchars($v["insurancedue"] ?? "") ?></td>
                                <td><?= htmlspecialchars($v["updateddate"] ?? $v["createddate"] ?? "") ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="count" style="text-align:center;margin-top:1rem;">
                &copy; <?= date('Y') ?> Vehicle and Machine Inventory System
            </div>
        </div>
    </main>
</div>
</body>
</html>