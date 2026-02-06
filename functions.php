<?php
/**
 * SuperStudy Utility Functions
 * 
 * Contains security, AI integration, and helper functions.
 */

require_once __DIR__ . '/config.php';

// ============================================
// SECURITY FUNCTIONS
// ============================================

/**
 * Generate CSRF token
 */
function generate_csrf_token(): string {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token(string $token): bool {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && 
           hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Sanitize input for HTML output
 */
function sanitize_output(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize input for database (use with prepared statements)
 */
function sanitize_input(string $input): string {
    return trim($input);
}

/**
 * Encrypt API key for storage
 */
function encrypt_api_key(string $key): string {
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($key, 'AES-256-CBC', ENCRYPTION_KEY, 0, $iv);
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt API key from storage
 */
function decrypt_api_key(string $encrypted): string {
    $data = base64_decode($encrypted);
    $iv = substr($data, 0, 16);
    $encrypted_data = substr($data, 16);
    return openssl_decrypt($encrypted_data, 'AES-256-CBC', ENCRYPTION_KEY, 0, $iv);
}

// ============================================
// AI INTEGRATION FUNCTIONS
// ============================================

/**
 * Default prompts for content generation
 */
function get_default_prompts(): array {
    return [
        'summary' => "Summarize the following document in clear, concise bullet points for studying. Focus on the key concepts, main ideas, and important details:\n\n",
        'notes' => "Create detailed study notes from the following content. Include:\n- Clear headings and subheadings\n- Key terms with definitions\n- Important concepts explained\n- Relationships between ideas\n\nContent:\n\n",
        'quiz' => "Generate 10 multiple-choice questions based on the following content. For each question:\n- Provide 4 answer options (A, B, C, D)\n- Mark the correct answer with [CORRECT]\n- Make questions test understanding, not just memorization\n\nFormat example:\nQ1: What is...?\nA) Option 1\nB) Option 2 [CORRECT]\nC) Option 3\nD) Option 4\n\nContent:\n\n",
        'flashcards' => "Create 15 flashcard pairs from the following content. Return as JSON array:\n[{\"front\": \"question or term\", \"back\": \"answer or definition\"}]\n\nFocus on key concepts, definitions, and important facts.\n\nContent:\n\n"
    ];
}

/**
 * Call AI API based on provider
 */
function call_ai_api(string $provider, string $model, string $api_key, string $prompt, ?string $image_base64 = null): array {
    switch ($provider) {
        case 'openai':
        case 'xai-grok':
        case 'openrouter':
            return call_openai_compatible($provider, $model, $api_key, $prompt, $image_base64);
        case 'anthropic':
            return call_anthropic($model, $api_key, $prompt, $image_base64);
        case 'google-gemini':
            return call_gemini($model, $api_key, $prompt, $image_base64);
        default:
            return ['success' => false, 'error' => 'Unknown AI provider'];
    }
}

/**
 * Call OpenAI-compatible APIs (OpenAI, xAI Grok, OpenRouter)
 */
function call_openai_compatible(string $provider, string $model, string $api_key, string $prompt, ?string $image_base64 = null): array {
    $endpoint = AI_ENDPOINTS[$provider];
    
    // Build messages array
    $content = [];
    if ($image_base64) {
        $content[] = [
            'type' => 'image_url',
            'image_url' => ['url' => "data:image/jpeg;base64,{$image_base64}"]
        ];
    }
    $content[] = ['type' => 'text', 'text' => $prompt];
    
    $messages = [
        ['role' => 'system', 'content' => 'You are a helpful study assistant that creates educational content.'],
        ['role' => 'user', 'content' => $image_base64 ? $content : $prompt]
    ];
    
    $data = [
        'model' => $model,
        'messages' => $messages,
        'max_tokens' => 4096,
        'temperature' => 0.7
    ];
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ];
    
    // OpenRouter requires additional headers
    if ($provider === 'openrouter') {
        $headers[] = 'HTTP-Referer: http://localhost';
        $headers[] = 'X-Title: SuperStudy';
    }
    
    return make_curl_request($endpoint, $data, $headers);
}

/**
 * Call Anthropic Claude API
 */
function call_anthropic(string $model, string $api_key, string $prompt, ?string $image_base64 = null): array {
    $endpoint = AI_ENDPOINTS['anthropic'];
    
    // Build content array
    $content = [];
    if ($image_base64) {
        $content[] = [
            'type' => 'image',
            'source' => [
                'type' => 'base64',
                'media_type' => 'image/jpeg',
                'data' => $image_base64
            ]
        ];
    }
    $content[] = ['type' => 'text', 'text' => $prompt];
    
    $data = [
        'model' => $model,
        'max_tokens' => 4096,
        'messages' => [
            ['role' => 'user', 'content' => $content]
        ]
    ];
    
    $headers = [
        'Content-Type: application/json',
        'x-api-key: ' . $api_key,
        'anthropic-version: 2023-06-01'
    ];
    
    $response = make_curl_request($endpoint, $data, $headers);
    
    // Parse Anthropic response format
    if ($response['success'] && isset($response['data']['content'][0]['text'])) {
        $response['content'] = $response['data']['content'][0]['text'];
    }
    
    return $response;
}

/**
 * Call Google Gemini API
 */
function call_gemini(string $model, string $api_key, string $prompt, ?string $image_base64 = null): array {
    $endpoint = AI_ENDPOINTS['google-gemini'] . $model . ':generateContent?key=' . $api_key;
    
    // Build parts array
    $parts = [];
    if ($image_base64) {
        $parts[] = [
            'inline_data' => [
                'mime_type' => 'image/jpeg',
                'data' => $image_base64
            ]
        ];
    }
    $parts[] = ['text' => $prompt];
    
    $data = [
        'contents' => [
            ['parts' => $parts]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 4096
        ]
    ];
    
    $headers = ['Content-Type: application/json'];
    
    $response = make_curl_request($endpoint, $data, $headers);
    
    // Parse Gemini response format
    if ($response['success'] && isset($response['data']['candidates'][0]['content']['parts'][0]['text'])) {
        $response['content'] = $response['data']['candidates'][0]['content']['parts'][0]['text'];
    }
    
    return $response;
}

/**
 * Make cURL request to AI API with detailed error handling
 */
function make_curl_request(string $url, array $data, array $headers): array {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HEADER => true
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false, 
            'error' => 'Network error: ' . $error,
            'error_type' => 'network'
        ];
    }
    
    // Separate headers and body
    $response_headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    $decoded = json_decode($body, true);
    
    // Handle different HTTP status codes
    if ($http_code >= 400) {
        return parse_api_error($http_code, $decoded, $response_headers);
    }
    
    // Extract content for OpenAI-compatible responses
    $content = $decoded['choices'][0]['message']['content'] ?? $decoded['content'] ?? null;
    
    return [
        'success' => true,
        'data' => $decoded,
        'content' => $content
    ];
}

/**
 * Parse API error responses with detailed, user-friendly messages
 */
function parse_api_error(int $http_code, ?array $response, string $headers): array {
    $error_type = 'api_error';
    $retry_after = null;
    
    // Extract retry-after header if present
    if (preg_match('/retry-after:\s*(\d+)/i', $headers, $matches)) {
        $retry_after = (int)$matches[1];
    }
    
    // Handle rate limiting (429)
    if ($http_code === 429) {
        $error_type = 'rate_limit';
        $wait_time = $retry_after ?? 60;
        $error_msg = "Rate limit exceeded. Please wait {$wait_time} seconds before trying again.";
        
        // Try to get more specific message from response
        if (isset($response['error']['message'])) {
            $api_msg = $response['error']['message'];
            if (strpos($api_msg, 'quota') !== false) {
                $error_msg = "API quota exceeded. You may need to upgrade your plan or wait until your quota resets.";
            }
        }
        
        return [
            'success' => false,
            'error' => $error_msg,
            'error_type' => $error_type,
            'http_code' => $http_code,
            'retry_after' => $wait_time
        ];
    }
    
    // Handle authentication errors (401, 403)
    if ($http_code === 401 || $http_code === 403) {
        $error_type = 'auth_error';
        $error_msg = 'Invalid or expired API key. Please check your API key in project settings.';
        
        if (isset($response['error']['message'])) {
            if (strpos($response['error']['message'], 'incorrect') !== false) {
                $error_msg = 'Incorrect API key. Please verify your API key is correct.';
            } elseif (strpos($response['error']['message'], 'permission') !== false) {
                $error_msg = 'API key lacks permission for this model. Try a different model.';
            }
        }
        
        return [
            'success' => false,
            'error' => $error_msg,
            'error_type' => $error_type,
            'http_code' => $http_code
        ];
    }
    
    // Handle model not found (404)
    if ($http_code === 404) {
        $error_type = 'model_error';
        $error_msg = 'Model not found. The selected model may not be available for your account.';
        return [
            'success' => false,
            'error' => $error_msg,
            'error_type' => $error_type,
            'http_code' => $http_code
        ];
    }
    
    // Handle content/safety filters (400)
    if ($http_code === 400) {
        $error_type = 'request_error';
        $error_msg = $response['error']['message'] ?? 'Bad request. The content may have triggered safety filters.';
        
        if (isset($response['error']['message'])) {
            $api_msg = strtolower($response['error']['message']);
            if (strpos($api_msg, 'context') !== false || strpos($api_msg, 'token') !== false) {
                $error_msg = 'Document is too large. Try uploading a smaller document.';
            } elseif (strpos($api_msg, 'safety') !== false || strpos($api_msg, 'blocked') !== false) {
                $error_msg = 'Content was blocked by safety filters. Try different document content.';
            }
        }
        
        return [
            'success' => false,
            'error' => $error_msg,
            'error_type' => $error_type,
            'http_code' => $http_code
        ];
    }
    
    // Handle server errors (500+)
    if ($http_code >= 500) {
        $error_type = 'server_error';
        $error_msg = 'AI service is temporarily unavailable. Please try again in a few minutes.';
        return [
            'success' => false,
            'error' => $error_msg,
            'error_type' => $error_type,
            'http_code' => $http_code
        ];
    }
    
    // Generic error fallback
    $error_msg = $response['error']['message'] ?? $response['error'] ?? 'Unknown API error (HTTP ' . $http_code . ')';
    return [
        'success' => false,
        'error' => $error_msg,
        'error_type' => $error_type,
        'http_code' => $http_code
    ];
}

// ============================================
// FILE HANDLING FUNCTIONS
// ============================================

/**
 * Validate uploaded file
 */
function validate_upload(array $file): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
        ];
        return ['valid' => false, 'error' => $errors[$file['error']] ?? 'Unknown upload error'];
    }
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['valid' => false, 'error' => 'File exceeds 10MB limit'];
    }
    
    // Check extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        return ['valid' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS)];
    }
    
    // Verify MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed_mimes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'text/plain'
    ];
    
    if (!in_array($mime, $allowed_mimes)) {
        return ['valid' => false, 'error' => 'Invalid file content type'];
    }
    
    return ['valid' => true, 'extension' => $ext, 'mime' => $mime];
}

/**
 * Generate unique filename
 */
function generate_unique_filename(string $original_name): string {
    $ext = pathinfo($original_name, PATHINFO_EXTENSION);
    return uniqid('doc_', true) . '.' . strtolower($ext);
}

/**
 * Extract text from file
 */
function extract_text_from_file(string $file_path, string $file_type): string {
    switch ($file_type) {
        case 'txt':
            return file_get_contents($file_path);
        case 'pdf':
            return extract_text_from_pdf($file_path);
        case 'jpg':
        case 'jpeg':
        case 'png':
            // For images, we'll use AI vision
            return '[Image document - content will be analyzed via AI]';
        default:
            return '';
    }
}

/**
 * Extract text from PDF using pdftotext (poppler)
 */
function extract_text_from_pdf(string $file_path): string {
    // Check if pdftotext is available
    $pdftotext_path = trim(shell_exec('which pdftotext 2>/dev/null') ?? '');
    
    if (empty($pdftotext_path)) {
        // Fallback: try common paths
        $common_paths = [
            '/usr/local/bin/pdftotext',
            '/opt/homebrew/bin/pdftotext',
            '/usr/bin/pdftotext'
        ];
        foreach ($common_paths as $path) {
            if (file_exists($path) && is_executable($path)) {
                $pdftotext_path = $path;
                break;
            }
        }
    }
    
    if (empty($pdftotext_path)) {
        // pdftotext not available, use AI for extraction
        return '[PDF document - text will be extracted via AI vision]';
    }
    
    // Extract text using pdftotext
    $escaped_path = escapeshellarg($file_path);
    $output = shell_exec("{$pdftotext_path} -layout {$escaped_path} - 2>/dev/null");
    
    if ($output && strlen(trim($output)) > 50) {
        return trim($output);
    }
    
    // If extraction failed or got minimal text, fallback to AI
    return '[PDF document - text will be extracted via AI vision]';
}

/**
 * Get file as base64 for AI processing
 */
function get_file_base64(string $file_path): string {
    $content = file_get_contents($file_path);
    return base64_encode($content);
}

/**
 * Ensure uploads directory exists with proper security
 */
function ensure_upload_dir(): bool {
    if (!is_dir(UPLOAD_DIR)) {
        if (!mkdir(UPLOAD_DIR, 0755, true)) {
            return false;
        }
    }
    
    // Create .htaccess if not exists
    $htaccess_path = UPLOAD_DIR . '.htaccess';
    if (!file_exists($htaccess_path)) {
        $htaccess_content = "# Deny direct access to uploaded files\nDeny from all\n\n# Prevent script execution\nOptions -ExecCGI\nAddHandler cgi-script .php .pl .py .jsp .asp .sh .cgi\n";
        file_put_contents($htaccess_path, $htaccess_content);
    }
    
    return true;
}

// ============================================
// CONTENT FORMATTING FUNCTIONS
// ============================================

/**
 * Parse quiz content for display
 */
function parse_quiz_content(string $content): array {
    $questions = [];
    $pattern = '/Q(\d+):\s*(.+?)(?=Q\d+:|$)/s';
    
    if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $q_num = $match[1];
            $q_content = trim($match[2]);
            
            // Extract question text and options
            $lines = explode("\n", $q_content);
            $question_text = array_shift($lines);
            
            $options = [];
            $correct = null;
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (preg_match('/^([A-D])\)\s*(.+?)(\s*\[CORRECT\])?$/i', $line, $opt_match)) {
                    $is_correct = !empty($opt_match[3]);
                    $options[$opt_match[1]] = [
                        'text' => trim($opt_match[2]),
                        'correct' => $is_correct
                    ];
                    if ($is_correct) {
                        $correct = $opt_match[1];
                    }
                }
            }
            
            if (!empty($question_text) && !empty($options)) {
                $questions[] = [
                    'number' => $q_num,
                    'question' => $question_text,
                    'options' => $options,
                    'correct' => $correct
                ];
            }
        }
    }
    
    return $questions;
}

/**
 * Parse flashcard content (JSON) for display
 */
function parse_flashcard_content(string $content): array {
    // Try to extract JSON from the content
    if (preg_match('/\[[\s\S]*\]/', $content, $matches)) {
        $json = $matches[0];
        $cards = json_decode($json, true);
        if (is_array($cards)) {
            return $cards;
        }
    }
    
    // Fallback: try direct JSON parse
    $cards = json_decode($content, true);
    if (is_array($cards)) {
        return $cards;
    }
    
    return [];
}

/**
 * Format notes content with proper HTML structure
 */
function format_notes_content(string $content): string {
    // Convert markdown-style headers to HTML
    $content = preg_replace('/^### (.+)$/m', '<h5>$1</h5>', $content);
    $content = preg_replace('/^## (.+)$/m', '<h4>$1</h4>', $content);
    $content = preg_replace('/^# (.+)$/m', '<h3>$1</h3>', $content);
    
    // Convert bold and italic
    $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);
    $content = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $content);
    
    // Convert bullet points
    $content = preg_replace('/^- (.+)$/m', '<li>$1</li>', $content);
    $content = preg_replace('/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $content);
    
    // Convert line breaks
    $content = nl2br($content);
    
    return $content;
}
