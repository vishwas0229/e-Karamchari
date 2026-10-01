<?php
/**
 * Static regression checks for leave validation and yearly balance restoration.
 */
$source = file_get_contents(__DIR__ . '/../backend/api/leaves.php');

$applyStart = strpos($source, 'function applyLeave()');
$applyEnd = strpos($source, 'function approveLeave()', $applyStart);
$cancelStart = strpos($source, 'function cancelLeave()');
$cancelEnd = strpos($source, 'function getLeaveBalance()', $cancelStart);

if ($applyStart === false || $applyEnd === false || $cancelStart === false || $cancelEnd === false) {
    fwrite(STDERR, "Leave function boundaries are missing.\n");
    exit(1);
}

$apply = substr($source, $applyStart, $applyEnd - $applyStart);
$cancel = substr($source, $cancelStart, $cancelEnd - $cancelStart);

$requiredApply = [
    "validateLeaveDate",
    "DateTimeImmutable",
    "WHERE id = :id AND is_active = 1",
    "Invalid or inactive leave type"
];

foreach ($requiredApply as $needle) {
    if (strpos($apply, $needle) === false) {
        fwrite(STDERR, "Missing applyLeave validation invariant: {$needle}\n");
        exit(1);
    }
}

if (strpos($cancel, "YEAR(CURDATE())") !== false) {
    fwrite(STDERR, "cancelLeave still restores balances using the current year.\n");
    exit(1);
}

foreach (['date(\'Y\', strtotime($lockedLeave[\'start_date\']))', "SET used = GREATEST(0, used - :days)", "status' => 'Cancelled'"] as $needle) {
    if (strpos($cancel, $needle) === false) {
        fwrite(STDERR, "Missing cancellation balance invariant: {$needle}\n");
        exit(1);
    }
}

echo "Leave validation and cancellation regression checks passed.\n";
