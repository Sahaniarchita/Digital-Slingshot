<?php
include "db.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request method.");
}

$emp_id   = strtoupper(trim($_POST["emp_id"] ?? ""));
$emp_name = trim($_POST["emp_name"] ?? "");

if ($emp_id === "" || $emp_name === "") {
    die("Employee ID and Name are required.");
}

$checkStmt = $conn->prepare("
    SELECT id
    FROM employees
    WHERE emp_id = ?
    LIMIT 1
");

if (!$checkStmt) {
    die("Prepare failed (duplicate check): " . $conn->error);
}

$checkStmt->bind_param("s", $emp_id);
$checkStmt->execute();
$checkStmt->bind_result($existing_id);
$alreadyExists = $checkStmt->fetch();
$checkStmt->close();

if ($alreadyExists) {
    echo "<script>alert('Employee ID already exists.'); window.location.href='templates/add_employee.php';</script>";
    exit;
}

if (!isset($_FILES["photo"]) || $_FILES["photo"]["error"] !== 0) {
    die("Photo upload failed.");
}

$uploadDir = "uploads/employee_photos/";
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        die("Failed to create upload folder.");
    }
}

$ext = strtolower(pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION));
$allowed = ["jpg", "jpeg", "png", "webp"];

if (!in_array($ext, $allowed, true)) {
    die("Invalid image format.");
}

$fileName   = strtolower($emp_id) . "." . $ext;
$targetPath = $uploadDir . $fileName;

if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $targetPath)) {
    die("Failed to save uploaded photo.");
}

$slotSql = "
    SELECT t.slot_no
    FROM (
        SELECT 151 AS slot_no UNION ALL SELECT 152 UNION ALL SELECT 153 UNION ALL SELECT 154 UNION ALL SELECT 155
        UNION ALL SELECT 156 UNION ALL SELECT 157 UNION ALL SELECT 158 UNION ALL SELECT 159 UNION ALL SELECT 160
        UNION ALL SELECT 161 UNION ALL SELECT 162 UNION ALL SELECT 163 UNION ALL SELECT 164 UNION ALL SELECT 165
        UNION ALL SELECT 166 UNION ALL SELECT 167 UNION ALL SELECT 168 UNION ALL SELECT 169 UNION ALL SELECT 170
        UNION ALL SELECT 171 UNION ALL SELECT 172 UNION ALL SELECT 173 UNION ALL SELECT 174 UNION ALL SELECT 175
        UNION ALL SELECT 176 UNION ALL SELECT 177 UNION ALL SELECT 178 UNION ALL SELECT 179 UNION ALL SELECT 180
        UNION ALL SELECT 181 UNION ALL SELECT 182 UNION ALL SELECT 183 UNION ALL SELECT 184 UNION ALL SELECT 185
        UNION ALL SELECT 186 UNION ALL SELECT 187 UNION ALL SELECT 188 UNION ALL SELECT 189 UNION ALL SELECT 190
        UNION ALL SELECT 191 UNION ALL SELECT 192 UNION ALL SELECT 193 UNION ALL SELECT 194 UNION ALL SELECT 195
        UNION ALL SELECT 196 UNION ALL SELECT 197 UNION ALL SELECT 198 UNION ALL SELECT 199 UNION ALL SELECT 200
    ) t
    LEFT JOIN employees e ON e.slot_no = t.slot_no
    WHERE e.slot_no IS NULL
    ORDER BY t.slot_no ASC
    LIMIT 1
";

$slotResult = $conn->query($slotSql);

if (!$slotResult) {
    die("Slot query failed: " . $conn->error);
}

$slotRow = $slotResult->fetch_assoc();
$nextSlot = (int)($slotRow["slot_no"] ?? 0);

if ($nextSlot < 151 || $nextSlot > 200) {
    die("No extra frame slots available.");
}

$slot_type     = "extra";
$is_visible    = 1;
$is_revealed   = 0;
$created_from  = "new_entry";

$insertEmp = $conn->prepare("
    INSERT INTO employees
    (emp_id, emp_name, photo, slot_no, slot_type, is_visible, is_revealed, created_from)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$insertEmp) {
    die("Prepare failed (employee insert): " . $conn->error);
}

$insertEmp->bind_param(
    "sssisiis",
    $emp_id,
    $emp_name,
    $targetPath,
    $nextSlot,
    $slot_type,
    $is_visible,
    $is_revealed,
    $created_from
);

if (!$insertEmp->execute()) {
    die("Employee insert failed: " . $insertEmp->error);
}

$insertEmp->close();

$insertQueue = $conn->prepare("
    INSERT INTO sling_popup (emp_id, shown)
    VALUES (?, 0)
");

if (!$insertQueue) {
    die("Prepare failed (popup queue insert): " . $conn->error);
}

$insertQueue->bind_param("s", $emp_id);

if (!$insertQueue->execute()) {
    die("Popup queue insert failed: " . $insertQueue->error);
}

$insertQueue->close();
$conn->close();

echo "<script>alert('New employee added successfully in slot {$nextSlot}'); window.location.href='templates/add_employee.php';</script>";
exit;
?>