<?php
include "db.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn->begin_transaction();

try {
    /*
      1. Get all photo paths before clearing DB
    */
    $photoPaths = [];

    $res = $conn->query("
        SELECT photo
        FROM employees
        WHERE photo IS NOT NULL
          AND photo <> ''
    ");

    if (!$res) {
        throw new Exception("Failed to fetch photo paths: " . $conn->error);
    }

    while ($row = $res->fetch_assoc()) {
        $photo = trim($row['photo']);
        if ($photo !== '') {
            $photoPaths[] = $photo;
        }
    }

    $photoPaths = array_unique($photoPaths);

    /*
      2. Delete actual files from server
    */
    foreach ($photoPaths as $photo) {
        $filePath = __DIR__ . '/' . $photo;

        if (file_exists($filePath) && is_file($filePath)) {
            if (!unlink($filePath)) {
                throw new Exception("Failed to delete file: " . $filePath);
            }
        }
    }

    /*
      3. Clear popup queue
    */
    if (!$conn->query("TRUNCATE TABLE sling_popup")) {
        throw new Exception("Failed to truncate sling_popup: " . $conn->error);
    }

    /*
      4. Reset first 150 slots
    */
    $sql = "
        UPDATE employees
        SET photo = '',
            emp_name = emp_id,
            is_revealed = 0,
            is_visible = 1,
            slot_type = 'main',
            created_from = 'normal'
        WHERE slot_no BETWEEN 1 AND 150
    ";

    if (!$conn->query($sql)) {
        throw new Exception("Reset main slots failed: " . $conn->error);
    }

    /*
      5. Remove extra slots if any
    */
    if (!$conn->query("DELETE FROM employees WHERE slot_no > 150")) {
        throw new Exception("Delete extra slots failed: " . $conn->error);
    }

    $conn->commit();

    echo "Tree reset successful. Image files deleted from server.";

} catch (Exception $e) {
    $conn->rollback();
    die("Reset failed: " . $e->getMessage());
}

$conn->close();
?>