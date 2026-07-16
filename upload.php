<?php
include "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request");
}

$user_id = trim($_POST["user_id"] ?? "");
$emp_id = trim($_POST["emp_id"] ?? "");
$name = trim($_POST["name"] ?? "");
$photoPath = "";

if ($user_id === "" || $emp_id === "" || $name === "") {
    die("All text fields are required");
}

if (!empty($_FILES["photo"]["name"])) {
    $ext = pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION);
    $fileName = time() . "_" . rand(1000, 9999) . "." . $ext;
    $targetPath = "uploads/submitted_photos/" . $fileName;

    if (move_uploaded_file($_FILES["photo"]["tmp_name"], $targetPath)) {
        $photoPath = $targetPath;
    } else {
        die("Photo upload failed");
    }
} elseif (!empty($_POST["captured_image"])) {
    $imageData = $_POST["captured_image"];
    $imageData = preg_replace('#^data:image/\w+;base64,#i', '', $imageData);
    $imageData = str_replace(" ", "+", $imageData);

    $fileName = time() . "_" . rand(1000, 9999) . ".png";
    $targetPath = "uploads/submitted_photos/" . $fileName;

    if (file_put_contents($targetPath, base64_decode($imageData))) {
        $photoPath = $targetPath;
    } else {
        die("Captured image save failed");
    }
} else {
    die("Upload or capture one image");
}

$stmt = $conn->prepare("INSERT INTO tree_entries (user_id, emp_id, name, photo, displayed) VALUES (?, ?, ?, ?, 0)");
$stmt->bind_param("ssss", $user_id, $emp_id, $name, $photoPath);

if ($stmt->execute()) {
    echo "<script>alert('Submitted successfully'); window.location.href='templates/form.php';</script>";
} else {
    echo "Database insert failed";
}

$stmt->close();
$conn->close();
?>