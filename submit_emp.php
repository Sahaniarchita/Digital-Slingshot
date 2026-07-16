<?php
include "db.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request method.");
}

/*
|--------------------------------------------------------------------------
| HARD LOCK: Stop activity if 150 main slots are already filled
|--------------------------------------------------------------------------
*/
$filledCheck = $conn->query("
    SELECT COUNT(*) AS filled_slots
    FROM employees
    WHERE slot_no BETWEEN 1 AND 150
      AND photo IS NOT NULL
      AND TRIM(photo) != ''
");

if (!$filledCheck) {
    die("Slot check failed: " . $conn->error);
}

$filledRow = $filledCheck->fetch_assoc();

if ((int)$filledRow['filled_slots'] >= 150) {
    header("Location: templates/form.php?error=slots_full");
    exit;
}

$input = strtoupper(trim($_POST["emp_id"] ?? ""));
$capturedPhoto = $_POST["captured_photo"] ?? "";

if ($input === "") {
    $input = "VISITOR-" . date("Ymd-His") . "-" . random_int(1000, 9999);
}

$db_emp_id = $input;

if ($capturedPhoto === "") {
    die("Captured photo is required.");
}

if (!preg_match('/^data:image\/(\w+);base64,/', $capturedPhoto, $matches)) {
    die("Invalid captured photo format.");
}

$imageType = strtolower($matches[1]);

if (!in_array($imageType, ['jpg', 'jpeg', 'png', 'webp'], true)) {
    die("Invalid captured photo type.");
}

$imageData = substr($capturedPhoto, strpos($capturedPhoto, ',') + 1);
$imageData = base64_decode($imageData);

if ($imageData === false) {
    die("Failed to decode captured photo.");
}

if ($imageType === 'jpeg') {
    $imageType = 'jpg';
}

$uploadDir = "uploads/employee_photos/";

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        die("Failed to create upload directory.");
    }
}

$safeEmpId = preg_replace('/[^A-Z0-9_-]/', '_', $db_emp_id);
$newFileName = strtolower($safeEmpId) . "_" . time() . "." . $imageType;
$newPhotoPath = $uploadDir . $newFileName;

if (file_put_contents($newPhotoPath, $imageData) === false) {
    die("Failed to save captured photo.");
}

/*
|--------------------------------------------------------------------------
| Check if existing employee/visitor
|--------------------------------------------------------------------------
*/
$checkStmt = $conn->prepare("
    SELECT id, slot_no
    FROM employees
    WHERE emp_id = ?
    LIMIT 1
");

if (!$checkStmt) {
    die("Prepare failed (employee check): " . $conn->error);
}

$checkStmt->bind_param("s", $db_emp_id);
$checkStmt->execute();
$checkStmt->bind_result($existingId, $existingSlotNo);
$employeeExists = $checkStmt->fetch();
$checkStmt->close();

if ($employeeExists) {

    $employeeStmt = $conn->prepare("
        UPDATE employees
        SET emp_name = ?,
            photo = ?,
            is_visible = 1,
            is_revealed = 1
        WHERE emp_id = ?
    ");

    if (!$employeeStmt) {
        die("Prepare failed (employee update): " . $conn->error);
    }

    $employeeStmt->bind_param("sss", $db_emp_id, $newPhotoPath, $db_emp_id);

} else {

    $emptySlotStmt = $conn->prepare("
        SELECT id, slot_no
        FROM employees
        WHERE slot_no BETWEEN 1 AND 150
          AND (photo IS NULL OR TRIM(photo) = '')
        ORDER BY FIELD(slot_no,

          70,71,72,
          90,91,
          115,116,117,

          149,150,
          88,89,
          112,113,114,

          63,64,65,66,67,68,69,
          82,83,84,85,86,87,
          108,109,110,111,
          129,130,131,132,134,135,136,137,

          54,55,56,57,58,59,60,61,62,
          79,80,81,
          104,105,106,107,

          49,50,51,52,53,
          76,77,78,
          96,97,98,99,100,101,102,103,

          44,45,46,47,48,
          73,74,75,
          92,93,94,95,
          124,125,126,127,128,

          34,35,36,37,38,39,40,41,42,43,
          118,119,120,121,122,123,

          21,22,23,24,25,26,27,28,29,30,31,32,33,
          133,138,141,142,143,144,145,146,147,148,

          10,11,12,13,14,15,16,17,18,19,20,
          139,140,

          1,2,3,4,5,6,7,8,9

        ) ASC
        LIMIT 1
    ");

    if (!$emptySlotStmt) {
        die("Prepare failed (empty main slot select): " . $conn->error);
    }

    $emptySlotStmt->execute();
    $emptySlotStmt->bind_result($emptyEmployeeId, $emptySlotNo);
    $hasEmptyMainSlot = $emptySlotStmt->fetch();
    $emptySlotStmt->close();

    if ($hasEmptyMainSlot) {

        $employeeStmt = $conn->prepare("
            UPDATE employees
            SET emp_id = ?,
                emp_name = ?,
                photo = ?,
                slot_type = 'main',
                is_visible = 1,
                is_revealed = 1,
                created_from = 'normal'
            WHERE id = ?
        ");

        if (!$employeeStmt) {
            die("Prepare failed (main slot update): " . $conn->error);
        }

        $employeeStmt->bind_param("sssi", $db_emp_id, $db_emp_id, $newPhotoPath, $emptyEmployeeId);

    } else {
        if (file_exists($newPhotoPath)) {
            unlink($newPhotoPath);
        }

        $conn->close();

        header("Location: templates/form.php?error=slots_full");
        exit;
    }
}

if (!$employeeStmt->execute()) {
    die("Employee save failed: " . $employeeStmt->error);
}

$employeeStmt->close();

/*
|--------------------------------------------------------------------------
| Queue popup entry
|--------------------------------------------------------------------------
*/
$queueStmt = $conn->prepare("
    INSERT INTO sling_popup (emp_id, shown, created_at)
    VALUES (?, 0, NOW())
    ON DUPLICATE KEY UPDATE
        shown = 0,
        created_at = NOW()
");

if (!$queueStmt) {
    die("Prepare failed (queue upsert): " . $conn->error);
}

$queueStmt->bind_param("s", $db_emp_id);

if (!$queueStmt->execute()) {
    die("Queue upsert failed: " . $queueStmt->error);
}

$queueStmt->close();
$conn->close();

header("Location: templates/form.php?queued=1&emp_id=" . urlencode($db_emp_id));
exit;
?>