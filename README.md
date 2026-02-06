# SuperStudy - AI-Powered Study Tool

A PHP web application that helps students upload documents and generate AI-powered study materials using their own API keys.

![SuperStudy Login](/docs/login_screenshot.png)

## Features

- **🔐 User Authentication** - Secure registration and login with password hashing
- **📁 Project Management** - Create projects with different AI providers
- **📄 Document Upload** - Support for PDF, JPG, PNG, TXT files (max 10MB)
- **🤖 AI Content Generation**:
  - 📋 **Summaries** - Bullet-point summaries
  - 📝 **Notes** - Detailed study notes with headings  
  - ❓ **Quizzes** - Multiple-choice questions with hidden answers
  - 🎴 **Flashcards** - Interactive flip cards
- **🔑 Dynamic Model Selection** - Fetches available models from your API key

## Supported AI Providers

| Provider | Free Tier | Model Selection |
|----------|-----------|-----------------|
| OpenAI | Limited | ✅ Dynamic |
| Anthropic (Claude) | Limited | Known models |
| Google Gemini | ~15 RPM | ✅ Dynamic |
| xAI Grok | Varies | ✅ Dynamic |
| OpenRouter | Pay-as-you-go | ✅ Dynamic (free models first) |

## Quick Start

### 1. Requirements
- XAMPP (Apache + MySQL + PHP 7.4+)
- PHP extensions: mysqli, openssl, curl, fileinfo

### 2. Database Setup
```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root < schema.sql
```

### 3. Configuration
Edit `config.php`:
```php
define('DB_PASS', 'your_password');  // Set MySQL password
define('ENCRYPTION_KEY', 'change-this-32-char-key-in-prod!');
```

### 4. Set Permissions
```bash
chmod 777 uploads/
```

### 5. Access
Open: **http://localhost/pages/superstudy/**

## File Structure

```
superstudy/
├── schema.sql              # Database schema
├── config.php              # Configuration
├── functions.php           # Utility functions & AI
├── index.php               # Login/Register
├── dashboard.php           # Project list
├── project.php             # Project view/create
├── upload_handler.php      # File upload
├── generate_content.php    # AI generation
├── delete_handler.php      # Delete items
├── fetch_models.php        # Dynamic model fetch
├── uploads/                # Uploaded files
└── assets/
    ├── css/style.css       # Dark theme
    └── js/app.js           # Interactive JS
```

## Security

- **SQL Injection**: Prepared statements
- **XSS**: Output escaping
- **CSRF**: Token-based protection
- **Passwords**: bcrypt hashing
- **API Keys**: AES-256-CBC encryption
- **Uploads**: Type validation + .htaccess

## API Key Sources

- [OpenAI](https://platform.openai.com/api-keys)
- [Anthropic](https://console.anthropic.com/)
- [Google AI](https://aistudio.google.com/apikey)
- [xAI](https://console.x.ai/)
- [OpenRouter](https://openrouter.ai/keys) (recommended for free models)

## License

MIT License
