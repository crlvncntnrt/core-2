<?php
require_once(__DIR__ . '/../initialize_coreT2.php');
date_default_timezone_set('Asia/Manila');

// ===== SETTINGS =====
const INTEREST_RATE = 0.025;

// IMPORTANT:
// set this to a real existing user_id in your users table (Admin/System account).
// If your savings.recorded_by allows NULL, you can set this to null and adjust bind.
const SYSTEM_USER_ID = 1;

// If you want it to apply for the PREVIOUS month (recommended if cron runs every 1st day)
$target = new DateTime('first day of last month');
$targetYm = $target->format('Y-m');            // ex: 2026-01
$postDate = (new DateTime('first day of this month'))->format('Y-m-d'); // ex: 2026-02-01

// 1) get latest savings row per member (latest by transaction_date then saving_id)
$sqlMembers = "
    SELECT s1.member_id, s1.balance
    FROM savings s1
    INNER JOIN (
        SELECT member_id, MAX(CONCAT(transaction_date, LPAD(saving_id, 10, '0'))) AS mx
        FROM savings
        GROUP BY member_id
    ) s2
    ON s1.member_id = s2.member_id
    AND CONCAT(s1.transaction_date, LPAD(s1.saving_id, 10, '0')) = s2.mx
";

$res = $conn->query($sqlMembers);
if (!$res) {
    http_response_code(500);
    echo "FAILED: " . $conn->error;
    exit;
}

$applied = 0;
$skipped = 0;

$checkStmt = $conn->prepare("
    SELECT 1
    FROM savings
    WHERE member_id = ?
      AND transaction_type = 'Interest'
      AND DATE_FORMAT(transaction_date, '%Y-%m') = ?
    LIMIT 1
");

$insertStmt = $conn->prepare("
    INSERT INTO savings (member_id, transaction_date, transaction_type, amount, balance, recorded_by)
    VALUES (?, ?, 'Interest', ?, ?, ?)
");

while ($row = $res->fetch_assoc()) {
    $memberId = intval($row['member_id']);
    $lastBalance = floatval($row['balance']);

    // skip if no balance or negative
    if ($lastBalance <= 0) {
        $skipped++;
        continue;
    }

    // 2) block duplicates for the target month (YYYY-MM)
    $checkStmt->bind_param("is", $memberId, $targetYm);
    $checkStmt->execute();
    $exists = $checkStmt->get_result()->fetch_assoc();

    if ($exists) {
        $skipped++;
        continue;
    }

    // 3) compute interest + new balance
    $interest = round($lastBalance * INTEREST_RATE, 2);
    if ($interest <= 0) {
        $skipped++;
        continue;
    }

    $newBalance = round($lastBalance + $interest, 2);
    $recordedBy = SYSTEM_USER_ID;

    // 4) insert interest row
    $insertStmt->bind_param("issdi", $memberId, $postDate, $interest, $newBalance, $recordedBy);
    if ($insertStmt->execute()) {
        $applied++;
    } else {
        // if a member fails, continue others
        error_log("Interest insert failed for member {$memberId}: " . $insertStmt->error);
        $skipped++;
    }
}

$checkStmt->close();
$insertStmt->close();

echo "OK. TargetMonth={$targetYm} PostDate={$postDate} Applied={$applied} Skipped={$skipped}\n";
