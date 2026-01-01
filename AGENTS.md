# AGENTS.md - Citation Bot

This file provides context for AI assistants working on the Citation Bot project.

## Quick Start for AI Assistants
   - Language: PHP 8.4+
   - Main logic: Template.php
   - Test command: php process_page.php "Page"
   - Code style: verbose, explicit, spaced-out, highly formatted style, assignments in conditionals are normal

## Project Overview

Citation Bot is a Wikipedia maintenance tool that automatically expands and formats bibliographic references. It retrieves metadata from authoritative sources (CrossRef, PubMed, arXiv, JSTOR) and generates properly formatted Wikipedia citation templates.

**Key Facts:**
- **Language:** PHP 8.4+
- **License:** GPL-3.0
- **Status:** Stable/Inactive (maintenance mode)
- **Repository:** https://github.com/ms609/citation-bot
- **Production:** https://citations.toolforge.org/
- **Platform:** Wikimedia Toolforge (Kubernetes)

## Architecture

The system has two main components:

1. **Web Interface** (`index.html` + `process_page.php`) - Batch processes entire Wikipedia pages
2. **Gadget API** (`gadgetapi.php`) - Real-time in-browser citation expansion during editing

### Core Processing Flow

```
Wikipedia Page → Extract Templates → Query External APIs → 
Add Missing Metadata → Clean Formatting → Post to Wikipedia
```

## Key Classes

- **`Page.php`** - Manages Wikipedia page content (fetch, process, write)
- **`Template.php`** - Core citation expansion logic
- **`Parameter.php`** - Template parameter handling
- **`WikipediaBot.php`** - Wikipedia API client with OAuth
- **`URLtools.php`** - URL normalization and metadata extraction
- **`NameTools.php`** - Author name parsing and formatting
- **`MathTools.php`** - MathML to LaTeX conversion
- **`WikiThings.php`** - Wiki markup handling (nowiki, comments, etc.)

## Code Style Guidelines

**Important characteristics of this codebase:**

- Uses a verbose, explicit, spaced-out, highly formatted style
- Assignments within conditionals are common
- Multi-line if/foreach/else statements (with braces)
- Method calls that modify state often occur within assignments
- Do not use `else if` but do use `elseif` (they behave differently)
- Action can happen through method calls in equality checks
- String operations need to be multi-byte versions

**Example patterns you'll see:**
```php
if ($value = $this->get_something()) process($value);
foreach ($items as $item) if ($item->valid()) $item->process();
```

## External API Integration

The bot integrates with multiple external services:

| Service | Identifier | Purpose |
|---------|------------|---------|
| CrossRef | DOI | Journal article metadata |
| PubMed | PMID | Biomedical literature |
| PubMed Central | PMC | Open access articles |
| arXiv | arXiv ID | Scientific preprints |
| JSTOR | JSTOR ID | Scholarly articles |
| Zotero | URL | Generic URL metadata extraction |

## Operating Modes

### Fast Mode (Gadget Default)
- Expands existing identifiers (DOI, PMID, etc.)
- Adds missing parameters
- Cleans formatting
- **Excludes:** bibcode searches, URL expansion
- **Reason:** Must complete within browser timeout

### Slow Mode (Web Interface Default)
- All fast mode operations
- **Plus:** Bibcode searches, Zotero URL expansion
- Takes longer but more thorough

## Development Environment

### Local Setup with Docker
```bash
docker compose up -d
# Access at http://localhost:8081
docker exec -it citation-bot-php-1 composer update
```

### Toolforge Deployment
```bash
become citations
webservice stop
webservice --backend=kubernetes php8.4 start
```

### Command Line Usage
```bash
php process_page.php "PageName|Another Page" --slow --savetofiles
```

## Configuration

**Required:** Create `env.php` from `env.php.example`

Must include:
- OAuth tokens (consumer token/secret, access token/secret)
- Bot username
- API keys (CrossRef, JSTOR, etc.)

**Security:**
```bash
chmod go-rwx env.php
```

## Testing & CI

The project uses extensive automated testing:

- **PHPUnit** - Unit tests
- **PHPStan** - Static analysis (strict mode)
- **Psalm** - Static analysis with security checks
- **Phan** - PHP static analyzer
- **PHP_CodeSniffer** - Code style enforcement
- **CodeQL** - Security vulnerability scanning
- **Trivy** - Container security scanning

All tests must pass before merging.

## Common Development Tasks

### Adding Support for a New API

1. Create new file `API[ServiceName].php`
2. Implement metadata retrieval function
3. Add identifier extraction to `URLtools.php`
4. Update `Template.php` expansion logic
5. Add tests in `tests/`
6. Update documentation

### Modifying Citation Expansion Logic

1. Main logic is in `Template.php`
2. Use `Template::add_if_new($param, $value)` to add parameters
3. `Template::tidy()` handles cleanup and normalization
4. Test with real Wikipedia pages using `--savetofiles` flag

### Adding New Template Parameters

1. Update parameter mapping in `Parameter.php`
2. Add extraction logic in relevant `API*.php` files
3. Update `Template.php` if parameter needs special handling
4. Add validation rules if needed

## Important Constraints

### Browser Storage Limitation
**NEVER use localStorage or sessionStorage** - not supported in artifacts/web interface. Use:
- React state (useState) for React components
- JavaScript variables for HTML artifacts
- In-memory storage only

### Fast Mode Requirements (Gadget)
The gadget MUST:
- Complete within 10 seconds
- Not perform slow operations (bibcode search, URL expansion)
- Handle browser timeout gracefully
- Provide useful partial results if API calls fail

### Wikipedia API Guidelines
- Respect rate limits
- Use OAuth authentication
- Include proper User-Agent
- Implement retry with exponential backoff
- Never post if page hasn't changed

## File Organization

```
/
├── src/                    # Source code directory
├── tests/                  # PHPUnit tests
├── .github/workflows/      # CI/CD workflows
├── constants.php           # Application constants
├── setup.php              # Bootstrap configuration
├── env.php                # Configuration (not in repo)
├── Page.php               # Page management
├── Template.php           # Citation expansion core
├── Parameter.php          # Parameter handling
├── WikipediaBot.php       # Wikipedia API client
├── URLtools.php           # URL utilities
├── NameTools.php          # Name parsing
├── API*.php               # External API integrations
├── index.html             # Web interface
├── process_page.php       # Main processor
├── gadgetapi.php          # Gadget endpoint
└── generate_template.php  # Single citation generator
```

## Debugging Tips

### Testing Individual Pages
```bash
php process_page.php "Article_Name" --savetofiles
# Check output in Article_Name.md
```

### Enabling Debug Output
Check `setup.php` for debug flags and logging configuration.

### Common Issues

**OAuth failures:**
- Verify tokens in `env.php`
- Check token permissions on Wikipedia
- Ensure OAuth client is approved

**API timeouts:**
- Check network connectivity
- Verify API keys are valid
- Check API rate limits

**Template not expanding:**
- Verify template syntax is correct
- Check if identifier is valid
- Look for errors in logs
- Test identifier in `generate_template.php`

## Performance Considerations

- Each external API call adds latency (100-500ms)
- Wikipedia API calls are rate-limited
- Slow mode can take 30-120 seconds for large pages
- Fast mode targets < 10 seconds
- Batch processing is more efficient than individual pages

## Security Best Practices

- Never commit `env.php` or credentials
- Validate all external API responses
- Sanitize user input before Wikipedia API calls
- Keep dependencies updated
- Review security scan results (Trivy, Psalm)
- Use OAuth, never passwords

## Contributing Guidelines

1. Fork the repository
2. Create a feature branch
3. Write tests for new functionality
4. Ensure all CI checks pass
5. Follow existing code style
6. Update documentation
7. Submit pull request with clear description

## Bug Reporting

**Primary channel:** https://en.wikipedia.org/wiki/User_talk:Citation_bot

Include:
- Page name or URL
- Expected behavior
- Actual behavior
- Error messages (if any)
- Screenshot (if applicable)

## Useful Commands

```bash
# Run tests
php vendor/bin/phpunit

# Static analysis
php vendor/bin/phpstan analyze
php vendor/bin/psalm

# Code style check
php vendor/bin/phpcs

# Process single page locally
php process_page.php "Wikipedia:Sandbox" --savetofiles

# Update dependencies
composer update
```

## Wikipedia Citation Template Reference

The bot supports all standard Wikipedia citation templates:

- `{{cite journal}}` - Academic journals
- `{{cite book}}` - Books
- `{{cite web}}` - Websites
- `{{cite news}}` - News articles
- `{{cite conference}}` - Conference papers
- `{{cite thesis}}` - Theses and dissertations
- `{{citation}}` - Any reference
- And many more...

## Common Template Parameters

| Parameter | Description |
|-----------|-------------|
| `title` | Article/book title |
| `author`, `last`, `first` | Author names |
| `journal` | Journal name |
| `volume`, `issue` | Journal volume/issue |
| `pages`, `page` | Page numbers |
| `date`, `year` | Publication date |
| `doi` | Digital Object Identifier |
| `pmid` | PubMed ID |
| `pmc` | PubMed Central ID |
| `arxiv`, `eprint` | arXiv identifier |
| `isbn` | Book identifier |
| `url` | Web URL |
| `access-date` | Date URL accessed |

## Project Status & Maintenance

- **Status:** Inactive/stable - not under active development
- **Maintenance:** Provided as time allows
- **Community:** Open source contributions welcome
- **Response time:** May vary due to volunteer nature

## Resources

- **Documentation:** https://en.wikipedia.org/wiki/User:Citation_bot/use
- **Bug reports:** https://en.wikipedia.org/wiki/User_talk:Citation_bot
- **Source code:** https://github.com/ms609/citation-bot

## Quick Reference for AI Assistants

When helping with this project:

1. **Remember:** Dense code style with inline assignments
2. **Test:** Always test with real Wikipedia pages
3. **Security:** Never expose OAuth tokens or API keys
4. **Performance:** Consider gadget timeout constraints
5. **Standards:** Follow existing patterns in the codebase
6. **Testing:** Run full test suite before submitting changes
7. **Documentation:** Update relevant docs with any changes

---

**Last updated:** January 2026  
**Maintained by:** Citation Bot community
