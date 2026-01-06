<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

/**
 * Guest browse page (approved only)
 * - Shows only equipment.approvalstatus = 'approved'
 * - Includes sidebar
 */

$_SESSION["role"] = $_SESSION["role"] ?? "guest";

$q = trim($_GET["q"] ?? "");
$companyId = (int)($_GET["company_id"] ?? 0);
$type = trim($_GET["type"] ?? "");

try {
    // Company dropdown
    $companies = $pdo->query("SELECT companyid, companyname FROM company ORDER BY companyname ASC")
        ->fetchAll(PDO::FETCH_ASSOC);

    // Equipment types dropdown
    $types = $pdo->query("
        SELECT DISTINCT equipmenttype
        FROM equipment
        WHERE equipmenttype IS NOT NULL AND equipmenttype <> ''
        ORDER BY equipmenttype ASC
    ")->fetchAll(PDO::FETCH_COLUMN);

    $sql = "
        SELECT
            e.equipmentid,
            e.serialno,
            e.model,
            e.codeno,
            e.equipmenttype,
            e.datecalibration,
            e.nextcalibration,
            e.certificationno,
            e.location,
            e.status,
            e.createddate,
            e.updateddate,
            c.companyname,
            d.markingno
        FROM equipment e
        JOIN company c ON c.companyid = e.companyid
        LEFT JOIN drillspecifics d ON d.drillid = e.equipmentid
        WHERE e.approvalstatus = 'approved'
    ";

    $params = [];

    if ($companyId > 0) {
        $sql .= " AND e.companyid = :companyid";
        $params[":companyid"] = $companyId;
    }

    if ($type !== "") {
        $sql .= " AND COALESCE(e.equipmenttype,'') = :type";
        $params[":type"] = $type;
    }

    if ($q !== "") {
        $sql .= " AND (
            e.serialno ILIKE :q
            OR COALESCE(e.model,'') ILIKE :q
            OR COALESCE(e.codeno,'') ILIKE :q
            OR COALESCE(e.location,'') ILIKE :q
            OR COALESCE(e.status,'') ILIKE :q
            OR COALESCE(e.equipmenttype,'') ILIKE :q
            OR COALESCE(e.certificationno,'') ILIKE :q
            OR COALESCE(d.markingno,'') ILIKE :q
        )";
        $params[":q"] = "%{$q}%";
    }

    $sql .= " ORDER BY e.updateddate DESC NULLS LAST, e.createddate DESC NULLS LAST, e.equipmentid DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $machines = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>View Machines | Vehicle & Machine Inventory</title>
        <link rel="stylesheet" href="../../css/guest_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

</head>
<body>
<div class="app">
<?php include "g_sidebar.php"; ?>

    <main class="main">
        <div class="header">
            <div>
                <h2>Machines / Equipment</h2>
                <div class="sub">Browsing approved equipment only.</div>
            </div>
            <div class="actions">
                <a class="btn secondary" href="add_machine.php">Submit New</a>
            </div>
        </div>

        <div class="card">
            <form class="filters" method="GET">
                <div>
                    <label for="q">Search</label>
                    <input class="input" id="q" name="q" value="<?= htmlspecialchars($q) ?>"
                           placeholder="Serial no, model, code no, type, location, status..." />
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
                    <label for="type">Equipment Type</label>
                    <select class="input" id="type" name="type">
                        <option value="">All types</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>" <?= $type === $t ? "selected" : "" ?>>
                                <?= htmlspecialchars($t) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <button class="btn" type="submit">Filter</button>
                </div>
            </form>

            <div class="count">Showing <strong><?= count($machines) ?></strong> approved record(s)</div>

            <div class="tablewrap">
                <table>
                    <thead>
                        <tr>
                            <th>Serial No</th>
                            <th>Company</th>
                            <th>Type</th>
                            <th>Model</th>
                            <th>Code No</th>
                            <th>Status</th>
                            <th>Location</th>
                            <th>Calibration</th>
                            <th>Next Calibration</th>
                            <th>Certification</th>
                            <th>Drill Marking</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$machines): ?>
                        <tr><td colspan="12" class="muted">No approved machines/equipment found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($machines as $m): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($m["serialno"]) ?></strong></td>
                                <td><?= htmlspecialchars($m["companyname"] ?? "") ?></td>
                                <td><?= htmlspecialchars($m["equipmenttype"] ?? "") ?></td>
                                <td><?= htmlspecialchars($m["model"] ?? "") ?></td>
                                <td><?= htmlspecialchars($m["codeno"] ?? "") ?></td>
                                <td>
                                    <?php if (!empty($m["status"])): ?>
                                        <span class="statuspill"><?= htmlspecialchars($m["status"]) ?></span>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($m["location"] ?? "") ?></td>
                                <td><?= htmlspecialchars($m["datecalibration"] ?? "") ?></td>
                                <td><?= htmlspecialchars($m["nextcalibration"] ?? "") ?></td>
                                <td><?= htmlspecialchars($m["certificationno"] ?? "") ?></td>
                                <td><?= htmlspecialchars($m["markingno"] ?? "") ?></td>
                                <td><?= htmlspecialchars($m["updateddate"] ?? $m["createddate"] ?? "") ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="count" style="text-align:center;margin-top:1rem;">
            &copy; <?= date('Y') ?> Vehicle and Machine Inventory System
        </div>
    </main>
</div>
</body>
</html>