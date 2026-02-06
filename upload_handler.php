<?php
/**
 * SuperStudy - File Upload Handler
 * 
 * AJAX endpoint for document uploads with validation.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

// Ensure logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

$user_id = get_user_id();
$project_id = (int)($_POST['project_id'] ?? 0);

// Verify project ownership
$stmt = $mysqli->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ?');
$stmt->bind_param('ii', $project_id, $user_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Project not found']);
    exit;
}
$stmt->close();

// Check if file was uploaded
if (!isset($_FILES['document']) || $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['document'];

// Validate file
$validation = validate_upload($file);
if (!$validation['valid']) {
    echo json_encode(['success' => false, 'error' => $validation['error']]);
    exit;
}

// Ensure upload directory exists
if (!ensure_upload_dir()) {
    echo json_encode(['success' => false, 'error' => 'Could not create upload directory']);
    exit;
}

// Generate unique filename and move file
$original_name = basename($file['name']);
$unique_name = generate_unique_filename($original_name);
$file_path = UPLOAD_DIR . $unique_name;

if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
    exit;
}

// Extract text based on file type
$file_type = $validation['extension'];
$extracted_text = extract_text_from_file($file_path, $file_type);

// Save to database
$stmt = $mysqli->prepare('
    INSERT INTO documents (project_id, file_name, file_path, file_type, file_size, extracted_text) 
    VALUES (?, ?, ?, ?, ?, ?)
');
$file_size = $file['size'];
$stmt->bind_param('isssis', $project_id, $original_name, $file_path, $file_type, $file_size, $extracted_text);

if ($stmt->execute()) {
    $doc_id = $stmt->insert_id;
    echo json_encode([
        'success' => true,
        'document' => [
            'id' => $doc_id,
            'name' => $original_name,
            'type' => $file_type,
            'size' => $file_size,
            'extracted_text' => substr($extracted_text, 0, 200) . '...'
        ]
    ]);
} else {
    // Clean up file if DB insert failed
    unlink($file_path);
    echo json_encode(['success' => false, 'error' => 'Failed to save document record']);
}
$stmt->close();
