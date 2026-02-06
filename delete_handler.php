<?php
/**
 * SuperStudy - Delete Handler
 * 
 * AJAX endpoint for deleting documents and generated content.
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
$action = $input['action'] ?? '';

switch ($action) {
    case 'delete_document':
        $doc_id = (int)($input['document_id'] ?? 0);
        
        // Verify ownership via project
        $stmt = $mysqli->prepare('
            SELECT d.*, p.user_id 
            FROM documents d 
            JOIN projects p ON d.project_id = p.id 
            WHERE d.id = ? AND p.user_id = ?
        ');
        $stmt->bind_param('ii', $doc_id, $user_id);
        $stmt->execute();
        $doc = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$doc) {
            echo json_encode(['success' => false, 'error' => 'Document not found']);
            exit;
        }
        
        // Delete file from disk
        if (file_exists($doc['file_path'])) {
            unlink($doc['file_path']);
        }
        
        // Delete from database (generated content will be set to NULL via FK)
        $del_stmt = $mysqli->prepare('DELETE FROM documents WHERE id = ?');
        $del_stmt->bind_param('i', $doc_id);
        $del_stmt->execute();
        $del_stmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Document deleted']);
        break;
        
    case 'delete_content':
        $content_id = (int)($input['content_id'] ?? 0);
        
        // Verify ownership via project
        $stmt = $mysqli->prepare('
            SELECT gc.*, p.user_id 
            FROM generated_content gc 
            JOIN projects p ON gc.project_id = p.id 
            WHERE gc.id = ? AND p.user_id = ?
        ');
        $stmt->bind_param('ii', $content_id, $user_id);
        $stmt->execute();
        $content = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$content) {
            echo json_encode(['success' => false, 'error' => 'Content not found']);
            exit;
        }
        
        // Delete from database
        $del_stmt = $mysqli->prepare('DELETE FROM generated_content WHERE id = ?');
        $del_stmt->bind_param('i', $content_id);
        $del_stmt->execute();
        $del_stmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Content deleted']);
        break;
        
    case 'delete_project':
        $project_id = (int)($input['project_id'] ?? 0);
        $redirect = $input['redirect'] ?? null;
        
        // Verify ownership
        $stmt = $mysqli->prepare('SELECT * FROM projects WHERE id = ? AND user_id = ?');
        $stmt->bind_param('ii', $project_id, $user_id);
        $stmt->execute();
        $project = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$project) {
            if ($redirect) {
                header('Location: ' . $redirect);
                exit;
            }
            echo json_encode(['success' => false, 'error' => 'Project not found']);
            exit;
        }
        
        // Get all documents to delete files
        $doc_stmt = $mysqli->prepare('SELECT file_path FROM documents WHERE project_id = ?');
        $doc_stmt->bind_param('i', $project_id);
        $doc_stmt->execute();
        $docs = $doc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $doc_stmt->close();
        
        // Delete files from disk
        foreach ($docs as $doc) {
            if (file_exists($doc['file_path'])) {
                unlink($doc['file_path']);
            }
        }
        
        // Delete project (cascades to documents and generated_content via FK)
        $del_stmt = $mysqli->prepare('DELETE FROM projects WHERE id = ?');
        $del_stmt->bind_param('i', $project_id);
        $del_stmt->execute();
        $del_stmt->close();
        
        if ($redirect) {
            header('Location: ' . $redirect);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'Project deleted']);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
