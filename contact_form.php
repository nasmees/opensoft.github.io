<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// ตั้งค่าโฟลเดอร์สำหรับเก็บข้อมูล
$dataDir = 'contact_submissions';
if (!file_exists($dataDir)) {
    mkdir($dataDir, 0755, true);
}

// ฟังก์ชันสำหรับ sanitize ข้อมูล
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// ฟังก์ชันสำหรับ validate อีเมล
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Method check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

// Validate required fields
if (!isset($data['name']) || !isset($data['email']) || !isset($data['message'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Sanitize and validate input
$name = sanitizeInput($data['name']);
$email = sanitizeInput($data['email']);
$message = sanitizeInput($data['message']);

// Basic validation
if (empty($name) || empty($email) || empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (!validateEmail($email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

if (strlen($name) < 2 || strlen($name) > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name must be between 2 and 100 characters']);
    exit;
}

if (strlen($message) < 10 || strlen($message) > 2000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message must be between 10 and 2000 characters']);
    exit;
}

// Create submission data
$submission = [
    'timestamp' => date('Y-m-d H:i:s'),
    'date' => date('d/m/Y H:i:s'),
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    'name' => $name,
    'email' => $email,
    'message' => $message,
    'status' => 'new'
];

// Generate filename with timestamp
$filename = $dataDir . '/submission_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.json';

// Save to file
if (file_put_contents($filename, json_encode($submission, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    // Also append to a summary file for easy reading
    $summaryFile = $dataDir . '/submissions_summary.txt';
    $summaryEntry = sprintf(
        "[%s] %s (%s) - %s\n%s\n%s\n\n",
        $submission['date'],
        $name,
        $email,
        $submission['ip_address'],
        $message,
        str_repeat('-', 50)
    );
    file_put_contents($summaryFile, $summaryEntry, FILE_APPEND | LOCK_EX);
    
    // Success response
    echo json_encode([
        'success' => true, 
        'message' => 'ข้อความของคุณถูกส่งเรียบร้อยแล้ว! เราจะติดต่อกลับโดยเร็ว',
        'data' => $submission
    ]);
} else {
    // Error response
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save submission']);
}
?>
