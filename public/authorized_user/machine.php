<?php
session_start();
if (($_SESSION["role"] ?? "") !== "authorized user") { header("Location: ../login.php"); exit; }

require_once __DIR__ . "/../../config/db.php";

$currentPage = basename($_SERVER["PHP_SELF"]);

$q = trim($_GET["q"] ?? "");
$type = trim($_GET["type"] ?? "");
$msg = trim((string)($_GET["msg"] ?? ""));

/* PAGINATION (10 per page) */
$page = max(1, (int)($_GET["page"] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

function h($v){ return htmlspecialchars((string)$v); }
function fmtDateDMY($v): string {
    if (!$v) return "—";
    try { return (new DateTime((string)$v))->format("d-m-Y"); }
    catch (Throwable $e) { return "—"; }
}
function badgeClassStatus(string $status): string {
    $s = strtoupper(trim($status));
    return match ($s) {
        "IN USE"   => "badge green",
        "DAMAGE"   => "badge red",
        "DISPOSAL" => "badge gray",
        "SOLD"     => "badge blue",
        "LOST"     => "badge orange",
        default    => "badge",
    };
}
function buildQuery(array $extra = []): string {
    $base = $_GET;
    foreach ($extra as $k => $v) {
        if ($v === null) unset($base[$k]); else $base[$k] = $v;
    }
    return http_build_query($base);
}

try {
    $types = $pdo->query("
        SELECT DISTINCT equipmenttype
        FROM equipment
        WHERE equipmenttype IS NOT NULL AND equipmenttype <> ''
        ORDER BY equipmenttype ASC
    ")->fetchAll(PDO::FETCH_COLUMN);

    /* WHERE + PARAMS */
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

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM equipment e {$whereSql}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));

    $sql = "
        SELECT
            e.equipmentid,
            e.serialno,
            e.model,
            e.codeno,
            e.datecalibration,
            e.nextcalibration,
            e.certificationno,
            e.location,
            e.status,
            e.equipmenttype,
            e.createddate,
            e.updateddate
        FROM equipment e
        {$whereSql}
        ORDER BY e.updateddate DESC NULLS LAST, e.createddate DESC NULLS LAST, e.equipmentid DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";
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
    <title>Machines | Authorized</title>

    <link rel="stylesheet" href="../../css/guest_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

    <style>
        .badge{display:inline-block;padding:.18rem .55rem;border-radius:999px;font-size:.78rem;font-weight:900;letter-spacing:.3px;text-transform:uppercase;border:1px solid rgba(120,120,160,.22);background:rgba(17,24,39,.06);color:#111827;white-space:nowrap;}
        .badge.green{ background:rgba(16,185,129,.12); border-color:rgba(16,185,129,.25); color:#065f46; }
        .badge.red{ background:rgba(239,68,68,.12); border-color:rgba(239,68,68,.25); color:#991b1b; }
        .badge.orange{ background:rgba(245,158,11,.14); border-color:rgba(245,158,11,.30); color:#92400e; }
        .badge.blue{ background:rgba(59,130,246,.12); border-color:rgba(59,130,246,.25); color:#1e40af; }
        .badge.gray{ background:rgba(231, 34, 4, 0.33); border-color:rgba(218, 78, 19, 0.25); color:#374151; }

        a.btn.secondary.compact{padding: .38rem .65rem;font-size: .84rem;border-radius: 12px;white-space: nowrap;}
        /* Print button */
        .btn.print{ background:rgba(59,130,246,.12); border:1px solid rgba(59,130,246,.25); color:#1e40af; }

        .alert{padding:.85rem 1rem;border-radius:14px;border:1px solid;margin-bottom:.8rem}
        .alert.success{background:rgba(16,185,129,.10);border-color:rgba(16,185,129,.22);color:#065f46}
        .alert.error{background:rgba(220,38,38,.08);border-color:rgba(220,38,38,.25);color:#991b1b}

        .pagination{display:flex;justify-content:space-between;align-items:center;gap:.8rem;margin-top:1rem;flex-wrap:wrap}
        .page-links{display:flex;gap:.35rem;flex-wrap:wrap}
        .page-btn{padding:.38rem .65rem;border-radius:12px;border:1px solid rgba(120,120,160,.25);text-decoration:none;color:#111827;background:rgba(255,255,255,.75);font-weight:800}
        .page-btn.active{background: linear-gradient(135deg, var(--primary1), var(--primary2));color:#fff;border-color:transparent}
        .page-btn.disabled{opacity:.45;pointer-events:none}
    </style>
</head>
<body>
<div class="app">
    <?php include "sidebar.php"; ?>

    <main class="main">
        <div class="header">
            <div>
                <h2>Machines / Equipment</h2>
                <div class="sub">Equipment list (Theodolite, Total Station, Dumping Level, etc.).</div>
            </div>

            <div style="display:flex;gap:.6rem;flex-wrap:wrap">
                <a class="btn secondary" href="add_machine.php">Add Machine</a>
                <a class="btn" href="drill.php">View Drills</a>
<a class="btn print" target="_blank" href="machine_print.php?<?= h(buildQuery(["page" => null])) ?>">
  <i class="fa-solid fa-print"></i>&nbsp;Print
</a>                       </div>
        </div>

        <div class="card">
            <?php if ($msg === "deleted"): ?>
                <div class="alert success">Deleted successfully.</div>
            <?php elseif ($msg === "delete_failed"): ?>
                <div class="alert error">Delete failed. Please try again.</div>
            <?php endif; ?>

            <form class="filters" method="GET">
                <div>
                    <label>Search</label>
                    <input class="input" name="q" value="<?= h($q) ?>"
                           placeholder="Serial, model, code, certification, location..." />
                </div>

                <div>
                    <label>Type</label>
                    <select class="input" name="type">
                        <option value="">All</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= h($t) ?>" <?= $type === $t ? "selected" : "" ?>>
                                <?= h($t) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <button class="btn" type="submit">Filter</button>
                </div>
            </form>

            <div style="margin:.8rem 0;color:var(--muted);font-size:.92rem">
                Showing <b><?= count($machines) ?></b> of <b><?= (int)$total ?></b> record(s)
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <div>Page <b><?= (int)$page ?></b> of <b><?= (int)$totalPages ?></b></div>
                    <div class="page-links">
                        <?php
                          $prevDisabled = $page <= 1;
                          $nextDisabled = $page >= $totalPages;

                          $prevHref = "machine.php?" . buildQuery(["page" => max(1, $page - 1)]);
                          $nextHref = "machine.php?" . buildQuery(["page" => min($totalPages, $page + 1)]);
                        ?>
                        <a class="page-btn <?= $prevDisabled ? "disabled" : "" ?>" href="<?= $prevDisabled ? "#" : h($prevHref) ?>">Prev</a>

                        <?php
                          $start = max(1, $page - 2);
                          $end = min($totalPages, $page + 2);
                          for ($p = $start; $p <= $end; $p++):
                            $href = "machine.php?" . buildQuery(["page" => $p]);
                        ?>
                          <a class="page-btn <?= $p === $page ? "active" : "" ?>" href="<?= h($href) ?>"><?= (int)$p ?></a>
                        <?php endfor; ?>

                        <a class="page-btn <?= $nextDisabled ? "disabled" : "" ?>" href="<?= $nextDisabled ? "#" : h($nextHref) ?>">Next</a>
                    </div>
                </div>
            <?php endif; ?>

            <br>

            <table>
                <thead>
                <tr>
                    <th>No.</th>
                    <th>Serial</th>
                    <th>Type</th>
                    <th>Model</th>
                    <th>Code</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Calibration</th>
                    <th>Next Calibration</th>
                    <th>Certification</th>
                    <th>Updated</th>
                    <th style="width:120px">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$machines): ?>
                    <tr><td colspan="12" class="muted">No records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($machines as $idx => $m): ?>
                        <?php $st = strtoupper((string)($m["status"] ?? "")); ?>
                        <tr>
                            <td><?= (int)($offset + $idx + 1) ?></td>
                            <td><b><?= h($m["serialno"] ?? "") ?></b></td>
                            <td><?= h($m["equipmenttype"] ?? "") ?></td>
                            <td><?= h($m["model"] ?? "") ?></td>
                            <td><?= h($m["codeno"] ?? "") ?></td>
                            <td><span class="<?= h(badgeClassStatus($st)) ?>"><?= h($st ?: "—") ?></span></td>
                            <td><?= h($m["location"] ?? "") ?></td>
                            <td><?= h(fmtDateDMY($m["datecalibration"] ?? null)) ?></td>
                            <td><?= h(fmtDateDMY($m["nextcalibration"] ?? null)) ?></td>
                            <td><?= h($m["certificationno"] ?? "") ?></td>
                            <td><?= h(fmtDateDMY($m["updateddate"] ?? $m["createddate"] ?? null)) ?></td>
                            <td><a class="btn secondary compact" href="machine_info.php?id=<?= (int)$m["equipmentid"] ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <div>Page <b><?= (int)$page ?></b> of <b><?= (int)$totalPages ?></b></div>
                    <div class="page-links">
                        <?php
                          $prevDisabled = $page <= 1;
                          $nextDisabled = $page >= $totalPages;

                          $prevHref = "machine.php?" . buildQuery(["page" => max(1, $page - 1)]);
                          $nextHref = "machine.php?" . buildQuery(["page" => min($totalPages, $page + 1)]);
                        ?>
                        <a class="page-btn <?= $prevDisabled ? "disabled" : "" ?>" href="<?= $prevDisabled ? "#" : h($prevHref) ?>">Prev</a>

                        <?php
                          $start = max(1, $page - 2);
                          $end = min($totalPages, $page + 2);
                          for ($p = $start; $p <= $end; $p++):
                            $href = "machine.php?" . buildQuery(["page" => $p]);
                        ?>
                          <a class="page-btn <?= $p === $page ? "active" : "" ?>" href="<?= h($href) ?>"><?= (int)$p ?></a>
                        <?php endfor; ?>

                        <a class="page-btn <?= $nextDisabled ? "disabled" : "" ?>" href="<?= $nextDisabled ? "#" : h($nextHref) ?>">Next</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>