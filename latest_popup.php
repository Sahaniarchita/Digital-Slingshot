<?php
include "db.php";

header('Content-Type: application/json');

$rows = [];

$sql = "
    SELECT 
        sp.id,
        sp.emp_id,
        COALESCE(NULLIF(e.emp_name, ''), sp.emp_id) AS emp_name,
        e.photo,
        e.slot_no,
        COALESCE(NULLIF(e.slot_type, ''), 'main') AS slot_type
    FROM sling_popup sp
    INNER JOIN employees e ON e.emp_id = sp.emp_id
    WHERE sp.shown = 0
      AND e.photo IS NOT NULL
      AND TRIM(e.photo) != ''
      AND e.slot_no IS NOT NULL
      AND TRIM(e.slot_no) != ''
    ORDER BY sp.id ASC
    LIMIT 12
";

$query = $conn->query($sql);

if (!$query) {
    http_response_code(500);
    echo json_encode([]);
    exit;
}

while ($row = $query->fetch_assoc()) {
    $rows[] = $row;
}

echo json_encode($rows);
$conn->close();
?>