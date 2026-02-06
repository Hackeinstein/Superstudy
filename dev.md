# SuperStudy - Development Notes

This file tracks development progress and future enhancements.

---

## Current Status: ✅ MVP Complete

**Version**: 1.0.0  
**Last Updated**: 2026-02-06

### Implemented Features
- [x] User authentication (register/login/logout)
- [x] Project management with AI provider selection
- [x] Document upload (PDF, JPG, PNG, TXT)
- [x] AI content generation (summary, notes, quiz, flashcards)
- [x] Dynamic model fetching from API keys
- [x] Encrypted API key storage
- [x] Dark theme UI with Bootstrap 5

---

## Roadmap / Future Enhancements

### High Priority
- [ ] **PDF Text Extraction** - Add server-side PDF parsing (pdftotext or Smalot library)
- [ ] **Better Error Messages** - Show specific API errors to users
- [ ] **Rate Limit Handling** - Detect and display rate limit errors gracefully
- [ ] **Project Settings** - Allow editing project name, description, API key

### Medium Priority
- [ ] **Batch Generation** - Generate all content types for all documents at once
- [ ] **Export Options** - Export flashcards to Anki, notes to Markdown
- [ ] **Custom Prompts** - Let users customize generation prompts
- [ ] **Document Preview** - Show uploaded images/PDFs inline
- [ ] **Content Editing** - Allow users to edit generated content

### Low Priority / Nice to Have
- [ ] **Telegram Bot Integration** - Send quiz/flashcards via Telegram
- [ ] **Spaced Repetition** - Track flashcard progress
- [ ] **Collaborative Projects** - Share projects with other users
- [ ] **Multiple Documents per Generation** - Combine docs for context
- [ ] **Voice Input** - Record audio and transcribe to text
- [ ] **Mobile App** - PWA or native mobile wrapper

---

## Known Issues

| Issue | Status | Notes |
|-------|--------|-------|
| Large PDFs slow to process | Open | Consider async processing |
| Anthropic no model list API | Workaround | Using hardcoded known models |

---

## Development Commands

```bash
# Start XAMPP
sudo /Applications/XAMPP/xamppfiles/xampp start

# Run database migrations
/Applications/XAMPP/xamppfiles/bin/mysql -u root < schema.sql

# Check PHP syntax
php -l *.php

# View error logs
tail -f /Applications/XAMPP/xamppfiles/logs/php_error_log
```

---

## API Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `index.php` | GET/POST | Login/Register |
| `dashboard.php` | GET | List projects |
| `project.php` | GET/POST | View/Create project |
| `upload_handler.php` | POST | Upload document |
| `generate_content.php` | POST | Generate AI content |
| `delete_handler.php` | POST | Delete doc/content |
| `fetch_models.php` | POST | Fetch available models |

---

## Tech Stack

- **Backend**: PHP 7.4+ (vanilla, no framework)
- **Database**: MySQL/MariaDB via mysqli
- **Frontend**: Bootstrap 5, vanilla JS
- **AI**: OpenAI, Anthropic, Google, xAI, OpenRouter APIs

---

## Contributing

1. Create a feature branch: `git checkout -b feature/name`
2. Make changes and test locally
3. Commit with clear messages
4. Push and create PR

---

## Session Notes

### 2026-02-06
- Initial MVP complete
- Added dynamic model fetching from API keys
- Fixed uploads folder permissions
- Created git repo
