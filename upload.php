<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$uploadsDir = __DIR__ . '/uploads/';
$thumbsDir = $uploadsDir . 'thumbnails/';

if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}
if (!file_exists($thumbsDir)) {
    mkdir($thumbsDir, 0755, true);
}

// সাইজ ফরম্যাট করার হেল্পার ফাংশন
function formatSizeUnits($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return $bytes . ' byte';
    } else {
        return '0 KB';
    }
}

// প্রোটোকল ও বেস URL তৈরি
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$baseUrl = $protocol . $host . ($basePath ? $basePath : '');

$response = [
    'success' => true,
    'fileUrl' => '',
    'fileSize' => '',
    'thumbUrl' => ''
];

// ১. থাম্বনেইল ইমেজ হ্যান্ডলিং
if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
    $thumbFile = $_FILES['thumbnail'];
    $thumbExt = strtolower(pathinfo($thumbFile['name'], PATHINFO_EXTENSION));
    $allowedImg = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];

    if (in_array($thumbExt, $allowedImg)) {
        $cleanThumbName = time() . '_thumb_' . bin2hex(random_bytes(4)) . '.' . $thumbExt;
        $targetThumb = $thumbsDir . $cleanThumbName;
        if (move_uploaded_file($thumbFile['tmp_name'], $targetThumb)) {
            $response['thumbUrl'] = $baseUrl . '/uploads/thumbnails/' . $cleanThumbName;
        }
    }
}

// ২. মূল ফাইল (HTML, ZIP ইত্যাদি) হ্যান্ডলিং
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $uploadedFile = $_FILES['file'];
    $fileExt = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));

    // নিরাপত্তার জন্য এক্সিকিউটেবল স্ক্রিপ্ট ব্লক
    $blockedExts = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'cgi', 'pl', 'exe', 'sh', 'bash'];
    if (in_array($fileExt, $blockedExts)) {
        echo json_encode(['success' => false, 'message' => 'Security Error: This file type is not allowed.']);
        exit;
    }

    $cleanFileName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
    $targetPath = $uploadsDir . $cleanFileName;

    if (move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
        $response['fileUrl'] = $baseUrl . '/uploads/' . $cleanFileName;
        $response['fileSize'] = formatSizeUnits($uploadedFile['size']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
        exit;
    }
}

echo json_encode($response);