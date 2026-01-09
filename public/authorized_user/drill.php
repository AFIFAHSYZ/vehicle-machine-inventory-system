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
} catch (Throwable $e) {
    // If company table not available, filters will still work with companyid typed manually (but we don't show that)
}

$where = [];
$params = [];

// search over fields
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
$countSql = "
    SELECT COUNT(*)
    FROM drill d
    {$whereSql}
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// Data query
$listSql = "
    SELECT
        d.*,
        c.companyname
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
        /* Minimal page-specific styles, reusing your global style-guide.css/guest_style.css */
        .header{
            background:var(--card);
            border:1px solid var(--border);
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            padding:1.2rem;
            display:flex; gap:1rem; align-items:flex-start; justify-content:space-between;
            flex-wrap:wrap;
        }
        .sub{color:var(--muted);margin-top:.25rem;line-height:1.5}
        .actions{display:flex;gap:.6rem;flex-wrap:wrap}

        .card{
            margin-top:1rem;
            background:var(--card);
            border:1px solid var(--border);
            border-radius:var(--radius);
            box-shadow:var(--shadow2);
            padding:1rem;
            overflow:hidden;
            position:relative;
        }
        .filters{
            display:grid; gap:.8rem;
            grid-template-columns: 1.2fr .8fr .8fr auto;
            align-items:end;
        }
        label{font-weight:900;font-size:.9rem}
        .input{
            width:100%; padding:.8rem .9rem;
            border-radius:14px; border:1px solid rgba(120,120,160,.25);
            background:rgba(255,255,255,.92);
        }
        .input:focus{outline:none; border-color:rgba(108,99,255,.55); box-shadow:0 0 0 4px rgba(108,99,255,.18)}
        .tablewrap{overflow:auto; margin-top:1rem; border-radius:14px; border:1px solid rgba(120,120,160,.18); background:#fff}
        table{width:100%; border-collapse:separate; border-spacing:0; min-width:1200px; background:#fff}
        thead th{
            text-align:left; font-size:.85rem; color:#3a4152;
            background:#f6f7ff; border-bottom:1px solid rgba(120,120,160,.18);
            padding:.85rem .85rem;
            position:sticky; top:0;
        }
        tbody td{
            padding:.85rem .85rem;
            border-bottom:1px solid rgba(120,120,160,.14);
            vertical-align:top;
            font-size:.93rem;
        }
        tbody tr:hover{background:#fafbff}
        .muted{color:var(--muted); font-size:.9rem}
        .count{margin-top:.75rem;color:var(--muted);font-size:.92rem}

        .statuspill{
            display:inline-block; padding:.25rem .55rem; border-radius:999px;
            font-weight:900; font-size:.78rem;
            background:rgba(108,99,255,0.12); border:1px solid rgba(108,99,255,0.20);
            white-space:nowrap;
        }
        .apppill{
            display:inline-block; padding:.25rem .55rem; border-radius:999px;
            font-weight:900; font-size:.78rem;
            background:rgba(16,185,129,.10); border:1px solid rgba(16,185,129,.22);
            white-space:nowrap;
        }

        .pagination{
            margin-top:1rem;
            display:flex;
            gap:.6rem;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
            color:var(--muted);
            font-size:.92rem;
        }
        .page-links{display:flex;gap:.4rem;align-items:center;flex-wrap:wrap}
        .page-btn{
            text-decoration:none;
            padding:.45rem .7rem;
            border-radius:999px;
            border:1px solid rgba(120,120,160,.25);
            background:rgba(255,255,255,.7);
            color:#121726;
            font-weight:900;
            font-size:.88rem;
        }
        .page-btn.active{
            background: linear-gradient(135deg,var(--primary1),var(--primary2));
            border-color:transparent;
            color:#fff;
        }
        .page-btn.disabled{pointer-events:none;opacity:.5;}

        @media (max-width: 980px){
            .filters{grid-template-columns:1fr;}
            table{min-width:980px}
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
                <a class="btn" href="drill_add.php"><i class="fa-solid fa-plus"></i>&nbsp;Add Drill</a>
                <a class="btn secondary" href="machine.php"></i>&nbsp;View Machine</a>
            </div>
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
                            <th>Drill ID</th>
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
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><?= (int)$r["drillid"] ?></td>
                                    <td><?= h($r["companyname"] ?? "—") ?></td>
                                    <td><?= h($r["markingno"] ?? "—") ?></td>
                                    <td><span class="statuspill"><?= h($r["status"] ?? "—") ?></span></td>
                                    <td><?= h($r["location"] ?? "—") ?></td>
                                    <td><?= h($r["dateofpurchase"] ?? "—") ?></td>
                                    <td><?= h($r["dateofdisposal"] ?? "—") ?></td>
                                    <td><span class="apppill"><?= h($r["approvalstatus"] ?? "—") ?></span></td>
                                    <td><?= h($r["updateddate"] ?? $r["createddate"] ?? "—") ?></td>
                                    <td>
                                        <a class="btn secondary" href="drill_info.php?id=<?= (int)$r["drillid"] ?>">View</a>
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
                        // show limited window
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