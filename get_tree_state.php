<?php
include "db.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");

$sql = "
    SELECT 
        e.id,
        e.emp_id,
        e.emp_name,
        e.photo,
        e.slot_no,
        COALESCE(NULLIF(e.slot_type, ''), 'main') AS slot_type,
        e.is_visible,
        e.is_revealed,
        e.created_from
    FROM employees e
    WHERE e.slot_no IS NOT NULL
      AND TRIM(e.slot_no) != ''
      AND (
        e.slot_type = 'main'
        OR (e.slot_type = 'extra' AND e.is_visible = 1)
      )
    ORDER BY CAST(e.slot_no AS UNSIGNED) ASC
";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode([
        "error" => "SQL failed",
        "details" => $conn->error
    ]);
    exit;
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
$conn->close();
?>