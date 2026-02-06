<?php
/**
 * SuperStudy - Content Generation Handler
 * 
 * AJAX endpoint for AI-powered content generation.
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

// Get JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Verify CSRF token
if (!verify_csrf_token($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

$user_id = get_user_id();
$project_id = (int)($input['project_id'] ?? 0);
$document_id = (int)($input['document_id'] ?? 0);
$type = $input['type'] ?? '';
$custom_prompt = $input['custom_prompt'] ?? '';

// Validate type
$valid_types = ['summary', 'notes', 'quiz', 'flashcards'];
if (!in_array($type, $valid_types)) {
    echo json_encode(['success' => false, 'error' => 'Invalid content type']);
    exit;
}

// Load project and verify ownership
$stmt = $mysqli->prepare('SELECT * FROM projects WHERE id = ? AND user_id = ?');
$stmt->bind_param('ii', $project_id, $user_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$project) {
    echo json_encode(['success' => false, 'error' => 'Project not found']);
    exit;
}

// Load document(s)
$text_content = '';
$image_base64 = null;
$doc_name = '';

if ($document_id > 0) {
    // Single document
    $doc_stmt = $mysqli->prepare('SELECT * FROM documents WHERE id = ? AND project_id = ?');
    $doc_stmt->bind_param('ii', $document_id, $project_id);
    $doc_stmt->execute();
    $document = $doc_stmt->get_result()->fetch_assoc();
    $doc_stmt->close();
    
    if (!$document) {
        echo json_encode(['success' => false, 'error' => 'Document not found']);
        exit;
    }
    
    $doc_name = $document['file_name'];
    
    // Handle based on file type
    if ($document['file_type'] === 'txt') {
        // Read text file
        $text_content = $document['extracted_text'];
        if (empty($text_content) && file_exists($document['file_path'])) {
            $text_content = file_get_contents($document['file_path']);
        }
    } elseif (in_array($document['file_type'], ['jpg', 'jpeg', 'png', 'pdf'])) {
        // For images and PDFs, use multimodal (send as base64)
        if (file_exists($document['file_path'])) {
            $image_base64 = get_file_base64($document['file_path']);
            $text_content = $document['extracted_text'] ?: '';
        }
    }
} else {
    // Use all documents in project
    $all_docs = $mysqli->prepare('SELECT extracted_text, file_name FROM documents WHERE project_id = ?');
    $all_docs->bind_param('i', $project_id);
    $all_docs->execute();
    $docs = $all_docs->get_result();
    
    while ($doc = $docs->fetch_assoc()) {
        if (!empty($doc['extracted_text'])) {
            $text_content .= "\n\n--- " . $doc['file_name'] . " ---\n" . $doc['extracted_text'];
        }
    }
    $all_docs->close();
}

// Check if we have content to process
if (empty($text_content) && empty($image_base64)) {
    echo json_encode(['success' => false, 'error' => 'No content available to process. Please upload a document first.']);
    exit;
}

// Build prompt
$default_prompts = get_default_prompts();
$base_prompt = $custom_prompt ?: $default_prompts[$type];

// For images/PDFs, modify prompt to request text extraction first
if ($image_base64 && empty($text_content)) {
    $base_prompt = "First, extract and read the text/content from this document image. Then, " . lcfirst($base_prompt);
}

$full_prompt = $base_prompt . $text_content;

// Call AI API
$api_key = decrypt_api_key($project['api_key']);
$result = call_ai_api(
    $project['ai_provider'],
    $project['model_name'],
    $api_key,
    $full_prompt,
    $image_base64
);

if (!$result['success']) {
    echo json_encode([
        'success' => false, 
        'error' => 'AI API Error: ' . ($result['error'] ?? 'Unknown error')
    ]);
    exit;
}

$generated_text = $result['content'] ?? '';

if (empty($generated_text)) {
    echo json_encode(['success' => false, 'error' => 'AI returned empty response']);
    exit;
}

// Save to database
$save_stmt = $mysqli->prepare('
    INSERT INTO generated_content (project_id, document_id, type, content_text, prompt_used) 
    VALUES (?, ?, ?, ?, ?)
');
$doc_id_save = $document_id ?: null;
$prompt_summary = substr($base_prompt, 0, 500);
$save_stmt->bind_param('iisss', $project_id, $doc_id_save, $type, $generated_text, $prompt_summary);

if ($save_stmt->execute()) {
    $content_id = $save_stmt->insert_id;
    
    // Format response based on type
    $formatted_content = $generated_text;
    $parsed_data = null;
    
    if ($type === 'quiz') {
        $parsed_data = parse_quiz_content($generated_text);
    } elseif ($type === 'flashcards') {
        $parsed_data = parse_flashcard_content($generated_text);
    }
    
    echo json_encode([
        'success' => true,
        'content' => [
            'id' => $content_id,
            'type' => $type,
            'text' => $generated_text,
            'parsed' => $parsed_data,
            'document_name' => $doc_name,
            'generated_at' => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save generated content']);
}
$save_stmt->close();
