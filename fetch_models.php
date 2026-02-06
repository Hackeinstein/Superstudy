<?php
/**
 * SuperStudy - Fetch Models Handler
 * 
 * AJAX endpoint to fetch available models from AI providers using the user's API key.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

// Get JSON body or POST data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$provider = $input['provider'] ?? '';
$api_key = $input['api_key'] ?? '';

if (empty($provider) || empty($api_key)) {
    echo json_encode(['success' => false, 'error' => 'Provider and API key required']);
    exit;
}

$models = [];
$error = null;

switch ($provider) {
    case 'openai':
        // OpenAI models endpoint
        $ch = curl_init('https://api.openai.com/v1/models');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $api_key
            ],
            CURLOPT_TIMEOUT => 15
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            if (isset($data['data'])) {
                foreach ($data['data'] as $model) {
                    // Filter to only show chat models
                    if (strpos($model['id'], 'gpt') !== false || strpos($model['id'], 'o1') !== false) {
                        $models[] = $model['id'];
                    }
                }
                sort($models);
                $models = array_reverse($models); // Newest first
            }
        } else {
            $error = 'Invalid API key or API error';
        }
        break;
        
    case 'anthropic':
        // Anthropic doesn't have a models list endpoint, return known models
        $models = [
            'claude-3-5-sonnet-20241022',
            'claude-3-5-haiku-20241022',
            'claude-3-opus-20240229',
            'claude-3-sonnet-20240229',
            'claude-3-haiku-20240307'
        ];
        break;
        
    case 'google-gemini':
        // Google Gemini models endpoint
        $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models?key=' . $api_key);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            if (isset($data['models'])) {
                foreach ($data['models'] as $model) {
                    // Extract model name and filter to generative models
                    $name = str_replace('models/', '', $model['name']);
                    if (strpos($name, 'gemini') !== false && 
                        isset($model['supportedGenerationMethods']) && 
                        in_array('generateContent', $model['supportedGenerationMethods'])) {
                        $models[] = $name;
                    }
                }
            }
        } else {
            $error = 'Invalid API key or API error';
        }
        break;
        
    case 'xai-grok':
        // xAI models endpoint
        $ch = curl_init('https://api.x.ai/v1/models');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $api_key
            ],
            CURLOPT_TIMEOUT => 15
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            if (isset($data['data'])) {
                foreach ($data['data'] as $model) {
                    $models[] = $model['id'];
                }
            }
        } else {
            // Fallback to known models
            $models = ['grok-2', 'grok-2-mini', 'grok-beta'];
        }
        break;
        
    case 'openrouter':
        // OpenRouter models endpoint
        $ch = curl_init('https://openrouter.ai/api/v1/models');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $api_key
            ],
            CURLOPT_TIMEOUT => 15
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            if (isset($data['data'])) {
                // Prioritize free models and popular ones
                $free_models = [];
                $paid_models = [];
                
                foreach ($data['data'] as $model) {
                    $id = $model['id'];
                    // Check if it's a free model
                    if (strpos($id, ':free') !== false || 
                        (isset($model['pricing']) && 
                         $model['pricing']['prompt'] == '0' && 
                         $model['pricing']['completion'] == '0')) {
                        $free_models[] = $id;
                    } else {
                        // Only add popular paid models
                        if (strpos($id, 'gpt-4') !== false ||
                            strpos($id, 'claude') !== false ||
                            strpos($id, 'gemini') !== false ||
                            strpos($id, 'llama') !== false ||
                            strpos($id, 'mistral') !== false) {
                            $paid_models[] = $id;
                        }
                    }
                }
                
                // Combine: free models first, then limited paid models
                $models = array_merge($free_models, array_slice($paid_models, 0, 20));
            }
        } else {
            $error = 'Invalid API key or API error';
        }
        break;
        
    default:
        $error = 'Unknown provider';
}

if ($error && empty($models)) {
    echo json_encode(['success' => false, 'error' => $error]);
} else {
    echo json_encode([
        'success' => true,
        'models' => $models,
        'count' => count($models)
    ]);
}
