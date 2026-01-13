<?php
// C:\xampp\htdocs\INVENTORY\scripts\vehicle_due_notify.php

require_once __DIR__ . "/../config/db.php";

// Manual PHPMailer include (no composer)
require_once __DIR__ . "/../lib/PHPMailer/src/Exception.php";
require_once __DIR__ . "/../lib/PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/../lib/PHPMailer/src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;

// =================== CONFIG ===================
$DAYS_BEFORE = 30;

// Gmail SMTP settings
$SMTP_HOST = "smtp.gmail.com";
$SMTP_PORT = 587;
$SMTP_SECURE = PHPMailer::ENCRYPTION_STARTTLS;

// CHANGE THESE:
$GMAIL_USERNAME = "afifhsyzaa@gmail.com";      // sender gmail
$GMAIL_APP_PASS = "dvdj aluv hqbf rimy";     // 16-char app password

$FROM_EMAIL = $GMAIL_USERNAME;
$FROM_NAME  = "Inventory System";

// Get authorized users (based on your table/columns)
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

// 2) Vehicles due within 30 days OR overdue
// (<= today + 30 days includes overdue too)
$dueStmt = $pdo->prepare("
  SELECT vehicleid, platenumber, roadtaxdue, insurancedue
  FROM vehicle
  WHERE
    UPPER(COALESCE(status,'')) = 'ACTIVE'
    AND (
      (roadtaxdue IS NOT NULL AND roadtaxdue <= CURRENT_DATE + (:days || ' days')::interval)
      OR
      (insurancedue IS NOT NULL AND insurancedue <= CURRENT_DATE + (:days || ' days')::interval)
    )
");
$dueStmt->execute([":days" => $DAYS_BEFORE]);
$vehicles = $dueStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$vehicles) {
  echo "No vehicles due within {$DAYS_BEFORE} days or overdue.\n";
  exit;
}

$today = date("Y-m-d");

foreach ($vehicles as $v) {
  $plate = $v["platenumber"] ?: ("VEHICLE #" . $v["vehicleid"]);

  $dueItems = [];
  if (!empty($v["roadtaxdue"])) {
    $dueItems[] = ["type" => "roadtax", "label" => "Road Tax", "date" => $v["roadtaxdue"]];
  }
  if (!empty($v["insurancedue"])) {
    $dueItems[] = ["type" => "insurance", "label" => "Insurance", "date" => $v["insurancedue"]];
  }

foreach ($dueItems as $item) {
  $type = $item["type"];
  $dueDate = $item["date"];

  // Only alert if THIS due date is within 30 days (or overdue)
  $dueTs = strtotime($dueDate);
  $todayTs = strtotime($today);
  $limitTs = strtotime("+{$DAYS_BEFORE} days", $todayTs);

  if ($dueTs === false) continue;
  if ($dueTs > $limitTs) continue; // not due within 30 days, skip

  $isOverdue = ($dueTs < $todayTs);
    foreach ($emails as $to) {
      // prevent duplicate alerts for same due date
      $check = $pdo->prepare("
        SELECT 1
        FROM vehicle_due_alert_log
        WHERE vehicleid = :vid AND duetype = :t AND duedate = :d AND sentto = :to
        LIMIT 1
      ");
      $check->execute([
        ":vid" => $v["vehicleid"],
        ":t"   => $type,
        ":d"   => $dueDate,
        ":to"  => $to,
      ]);
      if ($check->fetchColumn()) continue;

      $statusLine = $isOverdue
        ? "<b style='color:#b91c1c'>OVERDUE</b>"
        : "Due within <b>{$DAYS_BEFORE} days</b>";

      $subject = "[Vehicle Due Alert] {$item['label']} - {$plate}";
      $html = "
        <div style='font-family:Arial,sans-serif;line-height:1.55'>
          <h3 style='margin:0 0 10px'>Vehicle Due Alert</h3>
          <p style='margin:0 0 6px'><b>Plate:</b> {$plate}</p>
          <p style='margin:0 0 6px'><b>{$item['label']} Due Date:</b> {$dueDate}</p>
          <p style='margin:0 0 6px'><b>Status:</b> {$statusLine}</p>
          <hr style='border:none;border-top:1px solid #e5e7eb;margin:14px 0'>
          <p style='margin:0;color:#6b7280'>Auto notification from Inventory System.</p>
        </div>
      ";

      // send
      sendMail([$to], $subject, $html, $smtp, $FROM_EMAIL, $FROM_NAME);

      // log
      $log = $pdo->prepare("
        INSERT INTO vehicle_due_alert_log(vehicleid, duetype, duedate, sentto)
        VALUES(:vid, :t, :d, :to)
      ");
      $log->execute([
        ":vid" => $v["vehicleid"],
        ":t"   => $type,
        ":d"   => $dueDate,
        ":to"  => $to,
      ]);

      echo "Sent {$type} alert for {$plate} to {$to}\n";
    }
  }
}

echo "Done.\n";