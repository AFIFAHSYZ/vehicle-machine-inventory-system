<?php
// C:\xampp\htdocs\INVENTORY\scripts\vehicle_due_notify.php

require_once __DIR__ . "/../config/db.php";

// Manual PHPMailer include (no composer)
require_once __DIR__ . "/../lib/PHPMailer/src/Exception.php";
require_once __DIR__ . "/../lib/PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/../lib/PHPMailer/src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;

// =================== CONFIG ===================
$DAYS_NOTIFY = [7, 30]; // notify 1 week and 30 days before due

$EXCLUDE_COMPANY_IDS = [3]; // company IDs to exclude

// Gmail SMTP settings
$SMTP_HOST = "smtp.gmail.com";
$SMTP_PORT = 587;
$SMTP_SECURE = PHPMailer::ENCRYPTION_STARTTLS;

// CHANGE THESE:
$GMAIL_USERNAME = "afifhsyzaa@gmail.com";      // sender gmail
$GMAIL_APP_PASS = "dvdj aluv hqbf rimy";       // 16-char app password

$FROM_EMAIL = $GMAIL_USERNAME;
$FROM_NAME  = "Inventory System";

// Get authorized users
$USER_SQL = "
  SELECT email
  FROM \"User\"
  WHERE role = 'authorized user'
    AND email IS NOT NULL AND email <> ''
";
// =============================================

function sendMail(array $toEmails, string $subject, string $htmlBody, array $smtp, string $fromEmail, string $fromName): void {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtp["host"];
    $mail->Port = $smtp["port"];
    $mail->SMTPAuth = true;
    $mail->Username = $smtp["user"];
    $mail->Password = $smtp["pass"];
    $mail->SMTPSecure = $smtp["secure"];

    $mail->setFrom($fromEmail, $fromName);
    foreach ($toEmails as $e) $mail->addAddress($e);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $htmlBody;

    $mail->send();
}

$smtp = [
    "host" => $SMTP_HOST,
    "port" => $SMTP_PORT,
    "secure" => $SMTP_SECURE,
    "user" => $GMAIL_USERNAME,
    "pass" => $GMAIL_APP_PASS,
];

// 1) Load recipients
$emails = $pdo->query($USER_SQL)->fetchAll(PDO::FETCH_COLUMN);
if (!$emails) {
    echo "No authorized user emails found.\n";
    exit;
}

// Build optional "NOT IN (...)" clause safely
$excludeSql = "";
$excludeParams = [];
if (!empty($EXCLUDE_COMPANY_IDS)) {
    $placeholders = [];
    foreach (array_values($EXCLUDE_COMPANY_IDS) as $i => $cid) {
        $ph = ":ex_company_" . $i;
        $placeholders[] = $ph;
        $excludeParams[$ph] = (int)$cid;
    }
    $excludeSql = " AND v.companyid NOT IN (" . implode(",", $placeholders) . ") ";
}

// 2) Vehicles with due items within 30 days, 7 days, or overdue
$dueStmt = $pdo->prepare("
    SELECT v.vehicleid, v.platenumber, v.roadtaxdue, v.insurancedue
    FROM vehicle v
    WHERE
        UPPER(COALESCE(v.status,'')) = 'ACTIVE'
        {$excludeSql}
        AND (
            (v.roadtaxdue IS NOT NULL AND v.roadtaxdue <= CURRENT_DATE + '30 days'::interval)
            OR
            (v.insurancedue IS NOT NULL AND v.insurancedue <= CURRENT_DATE + '30 days'::interval)
        )
");
$dueStmt->execute($excludeParams);
$vehicles = $dueStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$vehicles) {
    echo "No vehicles due within notification period or overdue.\n";
    exit;
}

$today = date("Y-m-d");

foreach ($vehicles as $v) {
    $plate = $v["platenumber"] ?: ("VEHICLE #" . $v["vehicleid"]);

    // Collect due items for this vehicle
    $dueItems = [];
    if (!empty($v["roadtaxdue"])) {
        $dueItems[] = ["type" => "roadtax", "label" => "Road Tax", "date" => $v["roadtaxdue"]];
    }
    if (!empty($v["insurancedue"])) {
        $dueItems[] = ["type" => "insurance", "label" => "Insurance", "date" => $v["insurancedue"]];
    }

    $dueHtmlLines = [];
    $logItems = [];
    $todayTs = strtotime($today);

    foreach ($dueItems as $item) {
        $dueTs = strtotime($item['date']);
        if ($dueTs === false) continue;

        // Check if due date is within 7 or 30 days, or overdue
        $notify = false;
        foreach ($DAYS_NOTIFY as $days) {
            $limitTs = strtotime("+{$days} days", $todayTs);
            if ($dueTs <= $limitTs) {
                $notify = true;
                break;
            }
        }

        if (!$notify && $dueTs > $todayTs) continue; // skip items beyond 30 days

        $isOverdue = ($dueTs < $todayTs);
        $statusLine = $isOverdue
            ? "<b style='color:#b91c1c'>OVERDUE</b>"
            : "Due soon";

        $dueHtmlLines[] = "<p><b>{$item['label']} Due Date:</b> {$item['date']} - {$statusLine}</p>";
        $logItems[] = $item;
    }

    if (empty($dueHtmlLines)) continue;

    // Send one email per vehicle
    foreach ($emails as $to) {
        // Check log to prevent duplicate alerts per due item
        $alreadySent = false;
        foreach ($logItems as $item) {
            $check = $pdo->prepare("
                SELECT 1
                FROM vehicle_due_alert_log
                WHERE vehicleid = :vid AND duetype = :t AND duedate = :d AND sentto = :to
                LIMIT 1
            ");
            $check->execute([
                ":vid" => $v["vehicleid"],
                ":t"   => $item["type"],
                ":d"   => $item["date"],
                ":to"  => $to,
            ]);
            if ($check->fetchColumn()) {
                $alreadySent = true;
                break;
            }
        }

        if ($alreadySent) continue;

        $subject = "[Vehicle Due Alert] {$plate}";
        $html = "
            <div style='font-family:Arial,sans-serif;line-height:1.55'>
                <h3 style='margin:0 0 10px'>Vehicle Due Alert</h3>
                <p><b>Plate:</b> {$plate}</p>
                " . implode("", $dueHtmlLines) . "
                <hr style='border:none;border-top:1px solid #e5e7eb;margin:14px 0'>
                <p style='margin:0;color:#6b7280'>Auto notification from Inventory System.</p>
            </div>
        ";

        // send
        sendMail([$to], $subject, $html, $smtp, $FROM_EMAIL, $FROM_NAME);

        echo "Sent combined alert for {$plate} to {$to}\n";

        // log each due item
        foreach ($logItems as $item) {
            $log = $pdo->prepare("
                INSERT INTO vehicle_due_alert_log(vehicleid, duetype, duedate, sentto)
                VALUES(:vid, :t, :d, :to)
            ");
            $log->execute([
                ":vid" => $v["vehicleid"],
                ":t"   => $item["type"],
                ":d"   => $item["date"],
                ":to"  => $to,
            ]);
        }
    }
}

echo "Done.\n";
