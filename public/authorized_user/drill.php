<?php
session_start();
if (($_SESSION["role"] ?? "") !== "authorized user") { header("Location: ../login.php"); exit; }

require_once __DIR__ . "/../../config/db.php";
$currentPage = basename($_SERVER["PHP_SELF"]);

// --- Filters ---
$q = trim((string)($_GET["q"] ?? ""));
$status = trim((string)($_GET["status"] ?? ""));
$companyid = (int)($_GET["companyid"] ?? 0);

$page = max(1, (int)($_GET["page"] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Status options (edit if you have a fixed set)
$STATUS_OPTIONS = ["IN USE", "DAMAGE", "DISPOSAL", "SOLD", "LOST"];

// Load companies for dropdown (if you have company table)
$companies = [];
try {
    $cStmt = $pdo->query("SELECT companyid, companyname FROM company ORDER BY companyname ASC");
    $companies = $cStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* ignore */ }

$where = [];
$params = [];

if ($q !== "") {
    $where[] = "(d.markingno ILIKE :q OR d.location ILIKE :q OR d.status ILIKE :q OR d.itemdescription ILIKE :q OR CAST(d.drillid AS TEXT) ILIKE :q)";
    $params[":q"] = "%{$q}%";
}

if ($status !== "") {
    $where[] = "UPPER(COALESCE(d.status,'')) = UPPER(:status)";
    $params[":status"] = $status;
}

if ($companyid > 0) {
    $where[] = "d.companyid = :companyid";
    $params[":companyid"] = $companyid;
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// Count for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM drill d {$whereSql}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// Data query
$listSql = "
    SELECT d.*, c.companyname
    FROM drill d
    LEFT JOIN company c ON c.companyid = d.companyid
    {$whereSql}
    ORDER BY d.drillid DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

function h($v){ return htmlspecialchars((string)$v); }

function buildQuery(array $extra = []): string {
    $base = $_GET;
    foreach ($extra as $k => $v) {
        if ($v === null) unset($base[$k]); else $base[$k] = $v;
    }
    return http_build_query($base);
}

/* Badge helpers */
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
function badgeClassApproval(string $st): string {
    $s = strtoupper(trim($st));
    return match ($s) {
        "APPROVED" => "badge green",
        "REJECTED" => "badge red",
        "PENDING"  => "badge orange",
        default    => "badge",
    };
}
function fmtDateDMY($v): string {
    if (!$v) return "—";
    try {
        $dt = new DateTime((string)$v);
        return $dt->format("d-m-Y");
    } catch (Throwable $e) {
        return "—";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Drill | Authorized</title>

    <link rel="stylesheet" href="../../css/guest_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

    <style>
        .tablewrap table{border-collapse: collapse;}
        .tablewrap thead th{padding: .55rem .65rem;font-size: .82rem;white-space: nowrap;}
        .tablewrap tbody td{padding: .45rem .65rem;font-size: .88rem;vertical-align: middle;}
        .tablewrap tbody tr:hover{background: rgba(108,99,255,.06);}
        .badge{display:inline-block;padding: .18rem .55rem;border-radius: 999px;font-size: .78rem;font-weight: 900;letter-spacing: .3px;text-transform: uppercase;border: 1px solid rgba(120,120,160,.22);background: rgba(17,24,39,.06);color: #111827;white-space: nowrap;}
        .badge.green{ background: rgba(16,185,129,.12); border-color: rgba(16,185,129,.25); color:#065f46; }
        .badge.red{ background: rgba(239,68,68,.12); border-color: rgba(239,68,68,.25); color:#991b1b; }
        .badge.orange{ background: rgba(245,158,11,.14); border-color: rgba(245,158,11,.30); color:#92400e; }
        .badge.blue{ background: rgba(59,130,246,.12); border-color: rgba(59,130,246,.25); color:#1e40af; }
        .badge.gray{ background: rgba(107,114,128,.12); border-color: rgba(107,114,128,.25); color:#374151; }
        a.btn.secondary.compact{padding: .38rem .65rem;font-size: .84rem;border-radius: 12px;white-space: nowrap;}
        /* Print button */
        .btn.print{ background:rgba(59,130,246,.12); border:1px solid rgba(59,130,246,.25); color:#1e40af; }

        /* Print only the table area */
        @media print{
            body{ background:#fff !important; }
            .app, .main{ padding:0 !important; }
            .sidebar, .filters, .actions, .pagination, .btn, .header .sub, .count{ display:none !important; }
            .card{ box-shadow:none !important; border:none !important; }
            .tablewrap{ display:block !important; }
            .tablewrap table{ width:100% !important; }
        }

        
    </style>
</head>
<body>
<div class="app">
<?php include "sidebar.php";?>

    <main class="main">
        <div class="header">
            <div>
                <h2 style="margin:0">Drill Machine</h2>
                <div class="sub">Manage drilling machine records (search/filter view)</div>
            </div>

            <div class="actions">
                <a class="btn" href="add_drill.php"><i class="fa-solid fa-plus"></i>&nbsp;Add Drill</a>
                <a class="btn secondary" href="machine.php">&nbsp;View Machine</a>

<a class="btn print" target="_blank" href="drill_print.php?<?= h(buildQuery(["page" => null])) ?>">
  <i class="fa-solid fa-print"></i>&nbsp;Print
</a>            </div>
        </div>

        <div class="card">
            <form method="GET" class="filters">
                <div>
                    <label>Search</label>
                    <input class="input" type="text" name="q" value="<?= h($q) ?>" placeholder="Marking No / Location / Status / Description">
                </div>

                <div>
                    <label>Status</label>
                    <select class="input" name="status">
                        <option value="">All</option>
                        <?php foreach ($STATUS_OPTIONS as $opt): ?>
                            <option value="<?= h($opt) ?>" <?= strtoupper($status) === strtoupper($opt) ? "selected" : "" ?>>
                                <?= h($opt) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Company</label>
                    <select class="input" name="companyid">
                        <option value="0">All</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= (int)$c["companyid"] ?>" <?= $companyid === (int)$c["companyid"] ? "selected" : "" ?>>
                                <?= h($c["companyname"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <button class="btn" type="submit"><i class="fa-solid fa-magnifying-glass"></i>&nbsp;Filter</button>
                </div>
            </form>

            <div class="count">
                Showing <b><?= count($rows) ?></b> of <b><?= $total ?></b> record(s).
            </div>

            <div class="tablewrap">
                <table>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Company</th>
                            <th>Marking No</th>
                            <th>Status</th>
                            <th>Location</th>
                            <th>Date Purchase</th>
                            <th>Date Disposal</th>
                            <th>Approval</th>
                            <th>Updated</th>
                            <th style="width:120px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="10" class="muted">No records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $idx => $r): ?>
                                <?php
                                  $rowNo = $offset + $idx + 1;
                                  $statusTxt = strtoupper((string)($r["status"] ?? ""));
                                  $approvalTxt = strtoupper((string)($r["approvalstatus"] ?? ""));
                                ?>
                                <tr>
                                    <td><?= (int)$rowNo ?></td>
                                    <td><?= h($r["companyname"] ?? "—") ?></td>
                                    <td><?= h($r["markingno"] ?? "—") ?></td>
                                    <td><span class="<?= h(badgeClassStatus($statusTxt)) ?>"><?= h($statusTxt ?: "—") ?></span></td>
                                    <td><?= h($r["location"] ?? "—") ?></td>
                                    <td><?= h(fmtDateDMY($r["dateofpurchase"] ?? null)) ?></td>
                                    <td><?= h(fmtDateDMY($r["dateofdisposal"] ?? null)) ?></td><td>
                                    <?php $approvalTxt = strtoupper((string)($r["approvalstatus"] ?? "")); ?>
                                    <span class="<?= h(badgeClassApproval($approvalTxt)) ?>"><?= h($approvalTxt ?: "—") ?></span>
                                    </td>
                                    <td><?= h(fmtDateDMY($r["updateddate"] ?? $r["createddate"] ?? null)) ?></td>
                                    <td>
                                        <a class="btn secondary compact" href="drill_info.php?id=<?= (int)$r["drillid"] ?>">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <div>
                        Page <b><?= $page ?></b> of <b><?= $totalPages ?></b>
                    </div>
                    <div class="page-links">
                        <?php
                        $prevDisabled = $page <= 1;
                        $nextDisabled = $page >= $totalPages;

                        $prevHref = "drill.php?" . buildQuery(["page" => max(1, $page - 1)]);
                        $nextHref = "drill.php?" . buildQuery(["page" => min($totalPages, $page + 1)]);
                        ?>
                        <a class="page-btn <?= $prevDisabled ? "disabled" : "" ?>" href="<?= $prevDisabled ? "#" : h($prevHref) ?>">Prev</a>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        for ($p = $start; $p <= $end; $p++):
                            $href = "drill.php?" . buildQuery(["page" => $p]);
                        ?>
                            <a class="page-btn <?= $p === $page ? "active" : "" ?>" href="<?= h($href) ?>"><?= $p ?></a>
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