<?php
session_start();
if (($_SESSION["role"] ?? "") !== "authorized user") { header("Location: ../login.php"); exit; }

require_once __DIR__ . "/../../config/db.php";

$currentPage = basename($_SERVER["PHP_SELF"]);

$q = trim($_GET["q"] ?? "");
$companyId = (int)($_GET["company_id"] ?? 0);
$type = trim($_GET["type"] ?? "");
$status = trim($_GET["approvalstatus"] ?? "");

$companies = $pdo->query("SELECT companyid, companyname FROM company ORDER BY companyname ASC")->fetchAll(PDO::FETCH_ASSOC);

$types = $pdo->query("
    SELECT DISTINCT equipmenttype
    FROM equipment
    WHERE equipmenttype IS NOT NULL AND equipmenttype <> ''
    ORDER BY equipmenttype ASC
")->fetchAll(PDO::FETCH_COLUMN);

$sql = "
    SELECT
        e.*, c.companyname,
        d.markingno
    FROM equipment e
    JOIN company c ON c.companyid = e.companyid
    LEFT JOIN drillspecifics d ON d.drillid = e.equipmentid
    WHERE 1=1
";
$params = [];

if ($companyId > 0) { $sql .= " AND e.companyid = :companyid"; $params[":companyid"] = $companyId; }
if ($type !== "") { $sql .= " AND COALESCE(e.equipmenttype,'') = :type"; $params[":type"] = $type; }
if ($status !== "" && $status !== "all") { $sql .= " AND e.approvalstatus = :st"; $params[":st"] = $status; }

if ($q !== "") {
    $sql .= " AND (
        e.serialno ILIKE :q OR COALESCE(e.model,'') ILIKE :q OR COALESCE(e.codeno,'') ILIKE :q
        OR COALESCE(e.location,'') ILIKE :q OR COALESCE(e.status,'') ILIKE :q OR COALESCE(e.equipmenttype,'') ILIKE :q
        OR COALESCE(e.certificationno,'') ILIKE :q OR COALESCE(d.markingno,'') ILIKE :q
    )";
    $params[":q"] = "%{$q}%";
}

$sql .= " ORDER BY e.updateddate DESC NULLS LAST, e.createddate DESC NULLS LAST, e.equipmentid DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$machines = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Machines | Authorized</title>
    <link rel="stylesheet" href="../../css/guest_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

</head>
<body>
<div class="app">
<?php include "sidebar.php";?>

    <main class="main">
        <div class="header">
            <div>
                <h2>Machines / Equipment</h2>
                <div class="sub">View all equipment (filter by approval status).</div>
            </div>
            <div style="display:flex;gap:.6rem;flex-wrap:wrap">
                <a class="btn secondary" href="add_machine.php">Add Machine</a>
                <a class="btn" href="approve.php">Approvals</a>
            </div>
        </div>

        <div class="card">
            <form class="filters" method="GET">
                <div>
                    <label>Search</label>
                    <input class="input" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Serial, model, code, location..." />
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
                    <label>Type</label>
                    <select class="input" name="type">
                        <option value="">All</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>" <?= $type === $t ? "selected" : "" ?>>
                                <?= htmlspecialchars($t) ?>
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
                Showing <b><?= count($machines) ?></b> record(s)
            </div>

            <table>
                <thead>
                <tr>
                    <th>ID</th><th>Serial</th><th>Company</th><th>Type</th><th>Model</th><th>Code</th>
                    <th>Status</th><th>Location</th><th>Next Calibration</th><th>Approval</th><th>Marking</th><th>Updated</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$machines): ?>
                    <tr><td colspan="12" style="color:var(--muted)">No machines found.</td></tr>
                <?php else: foreach ($machines as $m): ?>
                    <tr>
                        <td><?= (int)$m["equipmentid"] ?></td>
                        <td><b><?= htmlspecialchars($m["serialno"]) ?></b></td>
                        <td><?= htmlspecialchars($m["companyname"]) ?></td>
                        <td><?= htmlspecialchars($m["equipmenttype"] ?? "") ?></td>
                        <td><?= htmlspecialchars($m["model"] ?? "") ?></td>
                        <td><?= htmlspecialchars($m["codeno"] ?? "") ?></td>
                        <td><?= htmlspecialchars($m["status"] ?? "") ?></td>
                        <td><?= htmlspecialchars($m["location"] ?? "") ?></td>
                        <td><?= htmlspecialchars($m["nextcalibration"] ?? "") ?></td>
                        <td><span class="pill"><?= htmlspecialchars($m["approvalstatus"]) ?></span></td>
                        <td><?= htmlspecialchars($m["markingno"] ?? "") ?></td>
                        <td><?= htmlspecialchars($m["updateddate"] ?? $m["createddate"] ?? "") ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>