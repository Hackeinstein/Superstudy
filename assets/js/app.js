/**
 * SuperStudy - Application JavaScript
 * 
 * Handles file uploads, content generation, and interactive elements.
 */

document.addEventListener('DOMContentLoaded', function () {
    // Initialize components
    initUploadZone();
    initProviderSelector();
    initApiKeyToggle();
    initGenerateButtons();
    initDeleteButtons();
    initContentFilters();
});

// ============================================
// File Upload Handler
// ============================================
function initUploadZone() {
    const uploadZone = document.getElementById('uploadZone');
    const fileInput = document.getElementById('fileInput');
    const uploadProgress = document.getElementById('uploadProgress');
    const uploadResult = document.getElementById('uploadResult');

    if (!uploadZone || !fileInput) return;

    // Click to upload
    uploadZone.addEventListener('click', () => fileInput.click());

    // Drag and drop
    uploadZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadZone.classList.add('dragover');
    });

    uploadZone.addEventListener('dragleave', () => {
        uploadZone.classList.remove('dragover');
    });

    uploadZone.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadZone.classList.remove('dragover');

        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            handleFileUpload(fileInput.files[0]);
        }
    });

    // File input change
    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) {
            handleFileUpload(fileInput.files[0]);
        }
    });
}

function handleFileUpload(file) {
    const uploadProgress = document.getElementById('uploadProgress');
    const uploadResult = document.getElementById('uploadResult');
    const uploadStatus = document.getElementById('uploadStatus');
    const progressBar = uploadProgress.querySelector('.progress-bar');

    // Validate file size
    if (file.size > 10 * 1024 * 1024) {
        showUploadError('File exceeds 10MB limit');
        return;
    }

    // Validate file type
    const allowedTypes = ['pdf', 'jpg', 'jpeg', 'png', 'txt'];
    const ext = file.name.split('.').pop().toLowerCase();
    if (!allowedTypes.includes(ext)) {
        showUploadError('Invalid file type. Allowed: ' + allowedTypes.join(', '));
        return;
    }

    // Show progress
    uploadProgress.classList.remove('d-none');
    uploadResult.classList.add('d-none');
    progressBar.style.width = '0%';
    uploadStatus.textContent = 'Uploading ' + file.name + '...';

    // Create FormData
    const formData = new FormData();
    formData.append('document', file);
    formData.append('csrf_token', csrfToken);
    formData.append('project_id', projectId);

    // Upload via AJAX
    const xhr = new XMLHttpRequest();

    xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
            const percent = (e.loaded / e.total) * 100;
            progressBar.style.width = percent + '%';
        }
    });

    xhr.addEventListener('load', () => {
        uploadProgress.classList.add('d-none');

        try {
            const response = JSON.parse(xhr.responseText);

            if (response.success) {
                showUploadSuccess('Document uploaded successfully!');
                // Reload page to show new document
                setTimeout(() => location.reload(), 1000);
            } else {
                showUploadError(response.error || 'Upload failed');
            }
        } catch (e) {
            showUploadError('Invalid server response');
        }
    });

    xhr.addEventListener('error', () => {
        uploadProgress.classList.add('d-none');
        showUploadError('Network error during upload');
    });

    xhr.open('POST', 'upload_handler.php');
    xhr.send(formData);
}

function showUploadError(message) {
    const uploadResult = document.getElementById('uploadResult');
    uploadResult.innerHTML = `<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle me-2"></i>${escapeHtml(message)}</div>`;
    uploadResult.classList.remove('d-none');
}

function showUploadSuccess(message) {
    const uploadResult = document.getElementById('uploadResult');
    uploadResult.innerHTML = `<div class="alert alert-success mb-0"><i class="bi bi-check-circle me-2"></i>${escapeHtml(message)}</div>`;
    uploadResult.classList.remove('d-none');
}

// ============================================
// Provider Selector (Create Project) - Dynamic Model Fetching
// ============================================
function initProviderSelector() {
    const providerSelect = document.getElementById('ai_provider');
    const modelSelect = document.getElementById('model_name');
    const apiKeyInput = document.getElementById('api_key');
    const fetchBtn = document.getElementById('fetchModelsBtn');
    const modelStatus = document.getElementById('modelStatus');
    const fetchIcon = document.getElementById('fetchIcon');

    if (!providerSelect || !modelSelect || !apiKeyInput) return;

    // Enable fetch button when API key is entered
    apiKeyInput.addEventListener('input', function () {
        const hasKey = this.value.trim().length > 10;
        fetchBtn.disabled = !hasKey;
        if (hasKey) {
            modelStatus.textContent = 'Click refresh button to load models';
            modelStatus.className = 'form-text text-info';
        } else {
            modelStatus.textContent = 'Enter API key to load available models';
            modelStatus.className = 'form-text';
        }
    });

    // Fetch models when button clicked
    fetchBtn.addEventListener('click', function () {
        fetchModels();
    });

    // Also trigger fetch when provider changes (if API key exists)
    providerSelect.addEventListener('change', function () {
        if (apiKeyInput.value.trim().length > 10) {
            fetchModels();
        }
    });

    function fetchModels() {
        const provider = providerSelect.value;
        const apiKey = apiKeyInput.value.trim();

        if (!apiKey) {
            modelStatus.textContent = 'Please enter your API key first';
            modelStatus.className = 'form-text text-warning';
            return;
        }

        // Show loading state
        fetchBtn.disabled = true;
        fetchIcon.className = 'bi bi-arrow-clockwise spin';
        modelSelect.disabled = true;
        modelSelect.innerHTML = '<option value="">Loading models...</option>';
        modelStatus.textContent = 'Fetching available models...';
        modelStatus.className = 'form-text text-info';

        fetch('fetch_models.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                provider: provider,
                api_key: apiKey
            })
        })
            .then(response => response.json())
            .then(data => {
                fetchBtn.disabled = false;
                fetchIcon.className = 'bi bi-arrow-clockwise';
                modelSelect.disabled = false;

                if (data.success && data.models && data.models.length > 0) {
                    modelSelect.innerHTML = '';
                    data.models.forEach(model => {
                        const option = document.createElement('option');
                        option.value = model;
                        option.textContent = model;
                        modelSelect.appendChild(option);
                    });
                    modelStatus.textContent = `Found ${data.models.length} available models`;
                    modelStatus.className = 'form-text text-success';
                } else {
                    modelSelect.innerHTML = '<option value="">-- No models found --</option>';
                    modelStatus.textContent = data.error || 'Could not fetch models. Check your API key.';
                    modelStatus.className = 'form-text text-danger';
                }
            })
            .catch(error => {
                fetchBtn.disabled = false;
                fetchIcon.className = 'bi bi-arrow-clockwise';
                modelSelect.disabled = false;
                modelSelect.innerHTML = '<option value="">-- Error loading models --</option>';
                modelStatus.textContent = 'Network error: ' + error.message;
                modelStatus.className = 'form-text text-danger';
            });
    }
}

// ============================================
// API Key Toggle
// ============================================
function initApiKeyToggle() {
    const toggleBtn = document.getElementById('toggleKey');
    const keyInput = document.getElementById('api_key');

    if (!toggleBtn || !keyInput) return;

    toggleBtn.addEventListener('click', function () {
        const icon = this.querySelector('i');
        if (keyInput.type === 'password') {
            keyInput.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            keyInput.type = 'password';
            icon.className = 'bi bi-eye';
        }
    });
}

// ============================================
// Content Generation
// ============================================
function initGenerateButtons() {
    document.querySelectorAll('.generate-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const docId = this.dataset.docId;
            const type = this.dataset.type;
            generateContent(docId, type);
        });
    });
}

function generateContent(documentId, type) {
    const modal = new bootstrap.Modal(document.getElementById('generatingModal'));
    const typeText = document.getElementById('generatingType');

    const typeLabels = {
        'summary': 'Creating summary...',
        'notes': 'Generating study notes...',
        'quiz': 'Creating quiz questions...',
        'flashcards': 'Building flashcards...'
    };

    typeText.textContent = typeLabels[type] || 'Generating...';
    modal.show();

    fetch('generate_content.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            csrf_token: csrfToken,
            project_id: projectId,
            document_id: documentId,
            type: type
        })
    })
        .then(response => response.json())
        .then(data => {
            modal.hide();

            if (data.success) {
                showToast('Content generated successfully!', 'success');
                // Reload to show new content
                setTimeout(() => location.reload(), 500);
            } else {
                showToast('Error: ' + (data.error || 'Generation failed'), 'danger');
            }
        })
        .catch(error => {
            modal.hide();
            showToast('Network error: ' + error.message, 'danger');
        });
}

// ============================================
// Delete Handlers
// ============================================
function initDeleteButtons() {
    // Delete document
    document.querySelectorAll('.delete-doc-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            if (!confirm('Delete this document and its generated content?')) return;

            const docId = this.dataset.docId;
            deleteItem('delete_document', { document_id: docId });
        });
    });

    // Delete content
    document.querySelectorAll('.delete-content-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            if (!confirm('Delete this generated content?')) return;

            const contentId = this.dataset.contentId;
            deleteItem('delete_content', { content_id: contentId });
        });
    });
}

function deleteItem(action, params) {
    fetch('delete_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            csrf_token: csrfToken,
            action: action,
            ...params
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Deleted successfully', 'success');
                setTimeout(() => location.reload(), 500);
            } else {
                showToast('Error: ' + (data.error || 'Delete failed'), 'danger');
            }
        })
        .catch(error => {
            showToast('Network error: ' + error.message, 'danger');
        });
}

// ============================================
// Content Filters
// ============================================
function initContentFilters() {
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            // Update active state
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const filter = this.dataset.filter;

            // Filter content items
            document.querySelectorAll('.content-item').forEach(item => {
                if (filter === 'all' || item.dataset.type === filter) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
}

// ============================================
// Utility Functions
// ============================================
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showToast(message, type = 'info') {
    // Create toast container if not exists
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(container);
    }

    // Create toast
    const toastId = 'toast-' + Date.now();
    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">${escapeHtml(message)}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', toastHtml);

    // Show toast
    const toastEl = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
    toast.show();

    // Remove from DOM after hidden
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}
