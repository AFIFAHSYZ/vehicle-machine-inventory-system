<?php
session_start();
if (($_SESSION["role"] ?? "") !== "authorized user") { header("Location: ../login.php"); exit; }

require_once __DIR__ . "/../../config/db.php";

$currentPage = basename($_SERVER["PHP_SELF"]);
$error = "";
$success = "";

$userId = $_SESSION["userid"] ?? null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $entity = $_POST["entity"] ?? ""; // vehicle|equipment|drill
    $action = $_POST["action"] ?? ""; // approve|reject
    $id = (int)($_POST["id"] ?? 0);

    if (!in_array($entity, ["vehicle","equipment","drill"], true) || !in_array($action, ["approve","reject"], true) || $id <= 0) {
        $error = "Invalid request.";
    } else {
        $newStatus = ($action === "approve") ? "approved" : "rejected";
        try {
            if ($entity === "vehicle") {
                $stmt = $pdo->prepare("
                    UPDATE vehicle
                    SET approvalstatus = :st,
                        approvedby = :uid,
                        approveddate = NOW(),
                        updatedby = :uid,
                        updateddate = NOW()
                    WHERE vehicleid = :id
                ");
            } elseif ($entity === "equipment") {
                $stmt = $pdo->prepare("
                    UPDATE equipment
                    SET approvalstatus = :st,
                        approvedby = :uid,
                        approveddate = NOW(),
                        updatedby = :uid,
                        updateddate = NOW()
                    WHERE equipmentid = :id
                ");
            } else { // drill
                $stmt = $pdo->prepare("
                    UPDATE drill
                    SET approvalstatus = :st,
                        approvedby = :uid,
                        approveddate = NOW(),
                        updatedby = :uid,
                        updateddate = NOW()
                    WHERE drillid = :id
                ");
            }

            $stmt->execute([":st"=>$newStatus, ":uid"=>$userId, ":id"=>$id]);
            $success = ucfirst($entity) . " #{$id} {$newStatus}.";
        } catch (PDOException $e) {
            $error = $e->getMessage();
        }
    }
}

$pendingVehicles = $pdo->query("
    SELECT v.vehicleid, v.platenumber, v.createddate, c.companyname
    FROM vehicle v
    JOIN company c ON c.companyid = v.companyid
    WHERE v.approvalstatus = 'PENDING'
    ORDER BY v.createddate DESC, v.vehicleid DESC
")->fetchAll(PDO::FETCH_ASSOC);

$pendingMachines = $pdo->query("
    SELECT e.equipmentid, e.serialno, e.createddate, e.equipmenttype, c.companyname
    FROM equipment e
    JOIN company c ON c.companyid = e.companyid
    WHERE e.approvalstatus = 'PENDING'
    ORDER BY e.createddate DESC, e.equipmentid DESC
")->fetchAll(PDO::FETCH_ASSOC);

$pendingDrills = $pdo->query("
    SELECT d.drillid, d.markingno, d.createddate, d.status, c.companyname
    FROM drill d
    JOIN company c ON c.companyid = d.companyid
    WHERE d.approvalstatus = 'PENDING'
    ORDER BY d.createddate DESC, d.drillid DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Approvals | Authorized</title>
    <link rel="stylesheet" href="../../css/guest_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
    <style>
      .row-actions{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;}
      .btn.sm{padding:.45rem .7rem;font-size:.85rem;border-radius:12px;font-weight:900;}
      .btn.approve{background: linear-gradient(135deg, #16a34a, #22c55e);color:#fff;border:none;
      }
      .btn.reject{
        background: linear-gradient(135deg, #b91c1c, #ef4444);
        color:#fff;
        border:none;
      }
      .btn.approve:hover,
      .btn.reject:hover{
        filter:brightness(0.95);
        transform: translateY(-1px);
        transition: .15s ease;
      }
      .btn.approve:active,
      .btn.reject:active{
        transform: translateY(0px);
      }
      .btn.approve i,
      .btn.reject i{
        margin-right:.35rem;
      }
      form{ margin:0; } /* prevents spacing bugs */
    </style></head>
<body>
<div class="app">
<?php include "sidebar.php";?>

    <main class="main">
        <div class="header">
            <div>
                <h2>Approvals</h2>
                <div class="sub">Approve or reject pending Vehicles and Machines.</div>
            </div>
            <div style="display:flex;gap:.6rem;flex-wrap:wrap">
                <a class="btn" href="vehicle.php">Vehicles</a>
                <a class="btn" href="machine.php">Machines</a>
            </div>
        </div>

        <div class="card">
            <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <div class="grid2">
                <div>
                    <h3 style="margin-bottom:.6rem">Pending Vehicles (<?= count($pendingVehicles) ?>)</h3>
                    <table>
                        <thead>
                        <tr><th>ID</th><th>Plate</th><th>Company</th><th>Submitted</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                        <?php if (!$pendingVehicles): ?>
                            <tr><td colspan="5" style="color:var(--muted)">No pending vehicles.</td></tr>
                        <?php else: foreach ($pendingVehicles as $v): ?>
                            <tr>
                                <td><?= (int)$v["vehicleid"] ?></td>
                                <td><b><?= htmlspecialchars($v["platenumber"]) ?></b></td>
                                <td><?= htmlspecialchars($v["companyname"]) ?></td>
                                <td><?= htmlspecialchars($v["createddate"] ?? "") ?></td>
                                <td>
                                    <div class="row-actions">
                                        <form method="POST">
                                            <input type="hidden" name="entity" value="drill">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="id" value="<?= (int)$d["drillid"] ?>">
                                            <button class="btn sm approve" type="submit">
                                            <i class="fa-solid fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <form method="POST" onsubmit="return confirm('Reject this drill?');">
                                            <input type="hidden" name="entity" value="drill">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="id" value="<?= (int)$d["drillid"] ?>">
                                            <button class="btn sm reject" type="submit">
                                            <i class="fa-solid fa-xmark"></i> Reject
                                            </button>                                          
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <div>
                    <h3 style="margin-bottom:.6rem">Pending Machines (<?= count($pendingMachines) ?>)</h3>
                    <table>
                        <thead>
                        <tr><th>ID</th><th>Serial</th><th>Company</th><th>Type</th><th>Submitted</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                        <?php if (!$pendingMachines): ?>
                            <tr><td colspan="6" style="color:var(--muted)">No pending machines.</td></tr>
                        <?php else: foreach ($pendingMachines as $m): ?>
                            <tr>
                                <td><?= (int)$m["equipmentid"] ?></td>
                                <td><b><?= htmlspecialchars($m["serialno"]) ?></b></td>
                                <td><?= htmlspecialchars($m["companyname"]) ?></td>
                                <td><?= htmlspecialchars($m["equipmenttype"] ?? "") ?></td>
                                <td><?= htmlspecialchars($m["createddate"] ?? "") ?></td>
                                <td>
                                    <div class="row-actions">
                                        <form method="POST">
                                            <input type="hidden" name="entity" value="drill">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="id" value="<?= (int)$d["drillid"] ?>">
                                            <button class="btn sm approve" type="submit">
                                            <i class="fa-solid fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <form method="POST" onsubmit="return confirm('Reject this drill?');">
                                            <input type="hidden" name="entity" value="drill">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="id" value="<?= (int)$d["drillid"] ?>">
                                            <button class="btn sm reject" type="submit">
                                            <i class="fa-solid fa-xmark"></i> Reject
                                            </button>                                          
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>

                    <h3 style="margin:.9rem 0 .6rem">Pending Drills (<?= count($pendingDrills) ?>)</h3>
                    <table>
                        <thead>
                        <tr><th>ID</th><th>Marking No</th><th>Company</th><th>Status</th><th>Submitted</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                        <?php if (!$pendingDrills): ?>
                            <tr><td colspan="6" style="color:var(--muted)">No pending drills.</td></tr>
                        <?php else: foreach ($pendingDrills as $d): ?>
                            <tr>
                                <td><?= (int)$d["drillid"] ?></td>
                                <td><b><?= htmlspecialchars($d["markingno"] ?? "") ?></b></td>
                                <td><?= htmlspecialchars($d["companyname"]) ?></td>
                                <td><?= htmlspecialchars($d["status"] ?? "") ?></td>
                                <td><?= htmlspecialchars($d["createddate"] ?? "") ?></td>
                                <td>
                                    <div class="row-actions">
                                        <form method="POST">
                                            <input type="hidden" name="entity" value="drill">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="id" value="<?= (int)$d["drillid"] ?>">
                                            <button class="btn sm approve" type="submit">
                                            <i class="fa-solid fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <form method="POST" onsubmit="return confirm('Reject this drill?');">
                                            <input type="hidden" name="entity" value="drill">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="id" value="<?= (int)$d["drillid"] ?>">
                                            <button class="btn sm reject" type="submit">
                                            <i class="fa-solid fa-xmark"></i> Reject
                                            </button>                                          
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div style="text-align:center;color:var(--muted);margin-top:1rem;font-size:.92rem">
            &copy; <?= date('Y') ?> Vehicle and Machine Inventory System
        </div>
    </main>
</div>
</body>
</html>