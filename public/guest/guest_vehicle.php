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
    <style>
        :root{
            --bg1:#f6f7ff; --bg2:#f2f6ff; --card:rgba(255,255,255,.85);
            --border:rgba(120,120,160,.20); --text:#1f2430; --muted:#6b7280;
            --primary1:#6c63ff; --primary2:#854af0;
            --sidebar:#101423; --sidebar2:#151a2e;
            --shadow:0 18px 50px rgba(25, 30, 60, 0.14);
            --shadow2:0 10px 22px rgba(25, 30, 60, 0.12);
            --radius:18px;
        }
        *{box-sizing:border-box;margin:0;padding:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}
        body{
            min-height:100vh; color:var(--text);
            background:
                radial-gradient(900px 420px at 20% 10%, rgba(108, 99, 255, 0.16), transparent 60%),
                radial-gradient(900px 420px at 80% 15%, rgba(133, 74, 240, 0.14), transparent 60%),
                linear-gradient(180deg,var(--bg1),var(--bg2));
        }

        /* Layout w/ sidebar */
        .app{min-height:100vh;display:grid;grid-template-columns:280px 1fr;}
        .sidebar{
            position:sticky;top:0;height:100vh;
            padding:1.25rem 1.1rem;color:#e9ecff;
            background:linear-gradient(180deg,var(--sidebar),var(--sidebar2));
            border-right:1px solid rgba(255,255,255,0.06);
        }
        .brand{
            display:flex;align-items:center;gap:.75rem;
            padding:.7rem .8rem;border-radius:14px;
            background:rgba(255,255,255,0.06);
            border:1px solid rgba(255,255,255,0.08);
            margin-bottom:1.1rem;
        }
        .logo{
            width:40px;height:40px;border-radius:14px;
            background:linear-gradient(135deg,var(--primary1),var(--primary2));
            box-shadow:0 12px 30px rgba(108,99,255,.35);
            display:grid;place-items:center;font-weight:900;color:#fff;
        }
        .brand h1{font-size:1.02rem;line-height:1.2;letter-spacing:-0.01em;}
        .brand p{font-size:.85rem;color:rgba(233,236,255,.72);margin-top:.15rem;}
        .nav{margin-top:.8rem;display:grid;gap:.35rem;}
        .nav a{
            display:flex;align-items:center;gap:.7rem;
            padding:.8rem .9rem;border-radius:14px;text-decoration:none;
            color:rgba(233,236,255,.86);border:1px solid transparent;
            transition:transform .12s ease, background .12s ease, border-color .12s ease;
        }
        .nav a:hover{background:rgba(255,255,255,0.08);border-color:rgba(255,255,255,0.10);transform:translateY(-1px);}
        .nav .active{background:rgba(108,99,255,0.18);border-color:rgba(108,99,255,0.25);}
        .nav .icon{
            width:34px;height:34px;border-radius:12px;display:grid;place-items:center;
            background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.08);
            font-size:1rem;
        }
        .sidebar-footer{
            position:absolute;left:1.1rem;right:1.1rem;bottom:1.1rem;
            display:grid;gap:.6rem;
        }
        .pill{
            display:flex;align-items:center;justify-content:space-between;
            padding:.75rem .9rem;border-radius:14px;
            background:rgba(255,255,255,0.06);
            border:1px solid rgba(255,255,255,0.08);
            color:rgba(233,236,255,.86);font-size:.92rem;
        }
        .badge{
            padding:.25rem .55rem;border-radius:999px;font-size:.78rem;font-weight:900;
            color:#0b1020;background:rgba(255,255,255,0.85);
        }

        /* Main */
        .main{padding:1.5rem 1.5rem 2rem;}
        .header{
            background:var(--card);
            border:1px solid var(--border);
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            padding:1.2rem 1.2rem;
            display:flex; gap:1rem; align-items:flex-start; justify-content:space-between;
            flex-wrap:wrap;
        }
        h2{font-size:1.35rem;letter-spacing:-0.02em;}
        .sub{color:var(--muted);margin-top:.25rem;line-height:1.5}
        .actions{display:flex;gap:.6rem;flex-wrap:wrap}
        .btn{
            text-decoration:none; border:none; cursor:pointer;
            display:inline-flex; align-items:center; justify-content:center;
            padding:.7rem 1rem; border-radius:999px; font-weight:900; font-size:.92rem;
            color:#fff; background:linear-gradient(135deg,var(--primary1),var(--primary2));
            box-shadow:0 12px 24px rgba(108,99,255,0.22);
        }
        .btn.secondary{background:#121726; box-shadow:0 12px 24px rgba(18,23,38,0.18)}
        .card{
            margin-top:1rem;
            background:var(--card);
            border:1px solid var(--border);
            border-radius:var(--radius);
            box-shadow:var(--shadow2);
            padding:1rem;
            overflow:hidden;
        }
        .filters{
            display:grid; gap:.8rem;
            grid-template-columns: 1.2fr .8fr auto;
            align-items:end;
        }
        label{font-weight:900;font-size:.9rem}
        .input{
            width:100%; padding:.8rem .9rem;
            border-radius:14px; border:1px solid rgba(120,120,160,.25);
            background:rgba(255,255,255,.92);
        }
        .input:focus{outline:none; border-color:rgba(108,99,255,.55); box-shadow:0 0 0 4px rgba(108,99,255,.18)}
        .tablewrap{overflow:auto; margin-top:1rem; border-radius:14px; border:1px solid rgba(120,120,160,.18)}
        table{width:100%; border-collapse:separate; border-spacing:0; min-width:980px; background:#fff}
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
        .statuspill{
            display:inline-block; padding:.25rem .55rem; border-radius:999px;
            font-weight:900; font-size:.78rem;
            background:rgba(108,99,255,0.12); border:1px solid rgba(108,99,255,0.20);
        }
        .muted{color:var(--muted); font-size:.9rem}
        .count{margin-top:.75rem;color:var(--muted);font-size:.92rem}
        @media (max-width: 980px){
            .app{grid-template-columns:1fr;}
            .sidebar{height:auto;position:relative;border-right:none;border-bottom:1px solid rgba(255,255,255,0.06);}
            .sidebar-footer{position:relative;left:auto;right:auto;bottom:auto;margin-top:1rem;}
            table{min-width:820px}
            .filters{grid-template-columns:1fr;}
        }
    </style>
</head>
<body>
<div class="app">
    <aside class="sidebar" aria-label="Sidebar Navigation">
        <div class="brand">
            <div class="logo">VM</div>
            <div>
                <h1>Inventory System</h1>
                <p>Guest (read-only)</p>
            </div>
        </div>

        <nav class="nav">
            <a href="guest_dashboard.php">
                <span class="icon">🏠</span><span>Dashboard</span>
            </a>
            <a class="active" href="guest_vehicle.php" aria-current="page">
                <span class="icon">🚗</span><span>Vehicles</span>
            </a>
            <a href="guest_machine.php">
                <span class="icon">🏗️</span><span>Machines</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="pill"><span>Access level</span><span class="badge">GUEST</span></div>
            <a class="btn secondary" href="../login.php">Login</a>
            <a class="btn secondary" href="../index.php">Back</a>
        </div>
    </aside>

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