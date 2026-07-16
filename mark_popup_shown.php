<?php
include "db.php";

header('Content-Type: application/json');

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

$ids = [];

if (isset($data['id']) && is_numeric($data['id'])) {
    $ids[] = (int)$data['id'];
}

if (isset($data['ids']) && is_array($data['ids'])) {
    foreach ($data['ids'] as $id) {
        if (is_numeric($id)) {
            $ids[] = (int)$id;
        }
    }
}

$ids = array_values(array_unique(array_filter($ids)));

if (empty($ids)) {
    echo json_encode([
        "status" => "error",
        "message" => "No valid ids received"
    ]);
    exit;
}

$idList = implode(",", $ids);

$result = $conn->query("UPDATE sling_popup SET shown = 1 WHERE id IN ($idList)");

if (!$result) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => $conn->error
    ]);
    exit;
}

echo json_encode([
    "status" => "ok",
    "updated" => count($ids)
]);

$conn->close();
?>