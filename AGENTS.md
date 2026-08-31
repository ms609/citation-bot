# AGENTS.md - Citation Bot

This file provides context for AI assistants working on the Citation Bot project.

## Quick Start for AI Assistants

- Language: PHP 8.4+
- Main logic: Template.php
- Test command: composer run test
- CS1 regression gate (run before and after citation-logic changes): `php tools/cs1_harness.php` and `php tools/cs1_harness.php --slow`
- CLI smoke-test (requires credentials, use --savetofiles to avoid writing to Wikipedia): php src/process_page.php "Page" --savetofiles
- Code style: verbose, explicit, spaced-out, highly formatted style
- First task: Read src/includes/Template.php, src/includes/Parameter.php, and src/includes/constants/parameters.php

## Project Overview

Citation Bot is a Wikipedia maintenance tool that automatically expands and formats bibliographic references. It retrieves metadata from authoritative sources (CrossRef, PubMed, arXiv, JSTOR, NASA ADS, Google Books and more) and generates properly formatted Wikipedia citation templates.

**Key Facts:**

- **Language:** PHP 8.4+
- **License:** GPL-3.0-or-later
- **Status:** Production service is active; repository is classified as stable/inactive (maintenance mode)
- **Repository:** <https://github.com/ms609/citation-bot>
- **Production:** <https://citations.toolforge.org>
- **Platform:** Wikimedia Toolforge (Kubernetes)

## Architecture

The system has three main components:

1. **Web Interface** (`src/index.php` + `src/process_page.php`) - Batch processes entire Wikipedia pages
2. **Gadget API** (`src/gadgetapi.php`) - Real-time in-browser citation expansion during editing
3. **Template Generator** (`src/generate_template.php`) - Creates a single wiki citation from an identifier

### Core Processing Flow

```text
Wikipedia Page → Extract Templates → Query External APIs →
Add Missing Metadata → Clean Formatting → Post to Wikipedia
```

## Key Files

- **`src/includes/setup.php`** - Bootstrap configuration and initialization
- **`src/includes/constants.php`** - Application-wide constants
- **`src/includes/Page.php`** - Page class - Manages Wikipedia page content (fetch, process, write)
- **`src/includes/Template.php`** - Template class - Core citation expansion logic
- **`src/includes/Parameter.php`** - Parameter class - Template parameter handling
- **`src/includes/WikipediaBot.php`** - WikipediaBot class - Wikipedia API client with OAuth
- **`src/includes/WikiThings.php`** - Wiki markup handling (nowiki, comments, etc.) — contains abstract class WikiThings + 9 concrete subclasses
- **`src/includes/URLtools.php`** - URL normalization and metadata extraction (standalone functions, no class)
- **`src/includes/NameTools.php`** - Author name parsing and formatting (standalone functions, no class)
- **`src/includes/MathTools.php`** - MathML to LaTeX conversion (standalone function, no class)

## Code Style Guidelines

**Important characteristics of this codebase:**

- Uses a verbose, explicit, spaced-out, highly formatted style
- Assignments within conditionals are common (to note possible side effects)
- Multi-line if/foreach/else statements (with braces)
- Method calls that modify state often occur within assignments
- Action can happen through method calls in equality checks
- String operations need to be multi-byte versions

**Good (matches project style):**

```php
if ($doi = $template->get('doi')) {
  $crossRef = query_crossref($doi);
  if ($crossRef) {
    $template->add_if_new('title', $crossRef->title);
  }
}
```

**Avoid (too compact for this project):**

```php
if ($doi = $template->get('doi') && $crossRef = query_crossref($doi))
  $template->add_if_new('title', $crossRef->title);
```

## Metadata Sources and Identifier Processors

The bot integrates with multiple external services.  Sometimes these APIs will fail:

| Service | Identifier | Purpose |
| ------- | ---------- | ------- |
| CrossRef | DOI | Journal article metadata |
| PubMed | PMID | Biomedical literature |
| PubMed Central | PMC | Open access articles |
| arXiv | arXiv ID | Scientific preprints |
| JSTOR | JSTOR ID | Scholarly articles |
| Zotero (via Citoid) | URL | Generic URL metadata extraction via Wikimedia's Citoid endpoint |
| SSRN (via Zotero) | SSRN ID | Social Science Research Network metadata |
| NASA ADS | Bibcode | Astrophysical literature via SAO/NASA ADS |
| Semantic Scholar | S2 ID / DOI | Identifier mapping and open-access status lookup |
| Google Books | ISBN / Google ID | Book metadata |
| Unpaywall | DOI | Open-access location finder |
| ISSN (local) | ISSN | Hardcoded ISSN-to-newspaper mapping (no live API) |
| Internet Archive | URL | Archive-hosted page title retrieval (Wayback Machine, Ghostarchive) |
| PII | PII | Publisher Item Identifier to DOI conversion |
| SICI (local) | SICI | Serial Item and Contribution Identifier (local parsing, no external API) |

## Operating Modes

### Fast Mode (Gadget Default)

- Expands existing identifiers (DOI, PMID, etc.)
- Adds missing parameters
- Cleans formatting
- **Excludes:** bibcode searches, URL expansion
- **Reason:** Must complete within the 120-second configured time limit

### Slow Mode (Web Interface Default)

- All fast mode operations
- **Plus:** Bibcode searches, Zotero URL expansion
- Takes longer but more thorough

## Development Environment

### Local Setup with Docker

```bash
docker compose up -d
# Access at http://localhost:8081/src/
docker exec -it citation-bot-php-1 composer install
```

### Toolforge Deployment

```bash
become citations[-dev]
webservice stop
webservice --backend=kubernetes php8.4 start
```

### Command Line Usage

```bash
php src/process_page.php "PageName|Another Page" --slow --savetofiles
```

## Configuration

**Required:** Create `src/env.php` from `src/env.php.example`

Must include:

- Public URL and web-request configuration: `PUBLIC_BASE_URL`, `ALLOWED_HOSTS`, `ALLOWED_ORIGINS`
- OAuth tokens: `PHP_OAUTH_CONSUMER_TOKEN`, `PHP_OAUTH_CONSUMER_SECRET`, `PHP_OAUTH_ACCESS_TOKEN`, `PHP_OAUTH_ACCESS_SECRET` (required for CLI)
- Web user OAuth: `PHP_WP_OAUTH_CONSUMER`, `PHP_WP_OAUTH_SECRET` (required for web interface)
- Optional: `PHP_ADSABSAPIKEY` (ADS), `PHP_S2APIKEY` (Semantic Scholar), `NLM_APIKEY`/`NLM_EMAIL` (NCBI), `DEPLOY_PASSWORD` (deployment endpoint)

**Security:**

```bash
chmod go-rwx src/env.php
```

## Testing & CI

The project uses extensive automated testing:

- **PHPUnit** - Unit tests (via paratest for parallel execution)
- **PHPStan (level 7)** - Static analysis
- **Psalm** - Static analysis for coding quality
- **Psalm (taint analysis)** - Security-focused taint data checks
- **Phan** - PHP static analyzer
- **PHP_CodeSniffer** - Code style enforcement
- **PHPLint** - Basic PHP syntax check
- **DesignSecurity (progpilot)** - Tainted data / design security analysis
- **CodeQL** - Security vulnerability scanning
- **Trivy** - Container security scanning
- **HTML5 and CSS Validator** - Validates HTML/CSS files
- **Validate JSON, YAML, and MD** - Validates JSON, YAML, and Markdown files
- **Action Lint** - Validates GitHub Actions
- **Shellcheck** - Lints shell scripts (e.g. `tools/update_cffconvert_lock.sh`)
- **Link Checker** - Validates URLs in documentation and HTML - runs weekly
- **Composer audit** - Checks Composer dependencies for known vulnerabilities
- **Dependabot** - Automated dependency updates for Composer, GitHub Actions, and pip (cffconvert lockfile)
- **Dependency Review** - Checks dependency changes for known vulnerabilities
- **Harden Runner** - Audits GitHub Actions runner behavior
- **OpenSSF Scorecard** - Evaluates repository security practices
- **Zizmor** - Analyzes GitHub Actions workflows for security issues
- **CITATION.cff validation** - Validates citation metadata using a hash-pinned cffconvert closure (`.github/cffconvert-requirements.txt`)
- **CS1 Harness** - Runs `tools/cs1_harness.php` in fast + slow mode on changes to `src/**` or `tools/**` (`.github/workflows/cs1-harness.yml`), plus a weekly schedule; fails if any must-pass case would trigger a CS1 error, or if a known-gap case unexpectedly resolves (XPASS)

The CS1 harness is the conformance regression gate (Phase 0 of the audit plan): it drives the bot's real expansion on a matrix of citations and flags any output that would trigger a `Help:CS1 errors` message. Run it locally (`php tools/cs1_harness.php` and `--slow`) before and after any citation-expansion change; a new fix should flip one of its documented gap cases to `RESOLVED` and add a matrix case.

**Local PHPUnit note:** `phpunit.xml.dist` aborts without a coverage driver; for local runs use the stripped `phpunit.local.xml` (gitignored): `php -d memory_limit=1G vendor/bin/phpunit --configuration phpunit.local.xml <path>`.

All tests must pass before merging. Some tests are network-dependent (Zotero, PubMed, Unpaywall, JSTOR) and may pass/fail with upstream API availability; those are unrelated to local changes.

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

1. Update the relevant constants or aliases in `src/includes/constants/parameters.php`
2. Add extraction logic in relevant `API*.php` files
3. Update `Template.php` if parameter needs special handling
4. Add validation rules if needed

### Running the CS1 Self-Validation Harness

The harness (`tools/cs1_harness.php`) checks expanded citations against the CS1 error rules. It has no credentials and is deterministic when upstream APIs respond normally (the matrix uses fabricated identifiers/titles that cannot match).

```bash
php tools/cs1_harness.php            # fast mode
php tools/cs1_harness.php --slow     # slow mode (bibcode search + URL expansion)
php tools/cs1_harness.php --list     # print the matrix without running
```

- **Must-pass cases** (29) must satisfy every checker rule; a violation exits 1.
- **Known-gap cases** (14) document current CS1 violations the bot leaves in place; while still a known gap they are reported but don't fail the run. A gap that stops violating prints `RESOLVED` and **fails the run** (XPASS), forcing it to be converted to a must-pass case or confirmed intentional.
- When adding identifier validation or parameter handling, mirror the existing validators in `src/includes/TextTools.php` (`arxiv_id_valid`, `pmid_valid`, `pmc_valid`, `rxiv_id_valid`, `bibcode_valid`, `isbn_valid`) and the `report_inaction` gate pattern in `Template::add_if_new`.

## Important Constraints

### Fast Mode Requirements (Gadget)

The gadget MUST:

- Complete within the 120-second configured time limit
- Not perform slow operations (bibcode search, URL expansion)
- Handle browser timeout gracefully
- Provide useful partial results if API calls fail

### Wikipedia API Guidelines

- Respect rate limits
- Use OAuth authentication
- Include proper User-Agent
- Implement retry with bounded backoff and honor Retry-After headers
- Never post if page hasn't changed

## File Organization

```text
/
├── src/
│   ├── index.php               # Web frontend
│   ├── process_page.php        # Main processor
│   ├── gadgetapi.php           # Gadget endpoint
│   ├── generate_template.php   # Single citation generator
│   ├── env.php.example         # Configuration template
│   ├── authenticate.php        # OAuth authentication
│   ├── category.php            # Category processing
│   ├── linked_pages.php        # Processes pages linking to a given page
│   ├── kill_big_job.php        # Kill large batch jobs
│   ├── gitpull.php             # Password-protected deployment/update endpoint
│   └── includes/
│       ├── setup.php           # Bootstrap configuration
│       ├── constants.php       # Application constants
│       ├── Page.php            # Page management
│       ├── Template.php        # Citation expansion core
│       ├── Parameter.php       # Parameter handling
│       ├── WikipediaBot.php    # Wikipedia API client
│       ├── URLtools.php        # URL normalization & metadata
│       ├── NameTools.php       # Author name parsing
│       ├── MathTools.php       # MathML to LaTeX conversion
│       ├── WikiThings.php      # Wiki markup handling
│       ├── miscTools.php       # Miscellaneous utilities
│       ├── TextTools.php       # String manipulation
│       ├── WebTools.php        # Web interface helpers
│       ├── bot_curl.php        # Curl wrapper with defaults
│       ├── user_messages.php   # Bot activity reporting
│       ├── doiTools.php        # DOI validation & normalization
│       ├── big_jobs.php        # Large batch job handling
│       ├── api/                # External API integrations
│       │   ├── APIarchives.php  # Internet Archive metadata
│       │   ├── APIarXiv.php     # arXiv metadata
│       │   ├── APIBibCode.php   # Bibcode metadata via NASA ADS
│       │   ├── APIdoi.php       # DOI/CrossRef metadata
│       │   ├── APIgoogle.php    # Google Books metadata
│       │   ├── APIissn.php      # ISSN metadata
│       │   ├── APIjstor.php     # JSTOR metadata
│       │   ├── APIpii.php       # PII to DOI conversion
│       │   ├── APIPubMed.php    # PubMed/PMC metadata
│       │   ├── APIS2.php        # Semantic Scholar metadata
│       │   ├── APIsici.php      # SICI parsing
│       │   ├── APIunpaywall.php # Unpaywall open-access lookup
│       │   └── APIzotero.php    # Zotero URL metadata
│       └── constants/          # Sub-constant definitions
│           ├── bad_data.php             # Incorrect data from outside sources
│           ├── capitalization.php       # Title and name capitalization rules
│           ├── free_doi.php             # Known open-access DOI prefixes
│           ├── isbn.php                 # ISBN hyphenation and formatting data
│           ├── italics.php              # Italics
│           ├── math.php                 # MathML tag and entity definitions
│           ├── mistakes.php             # Common misspellings
│           ├── null_bad_doi.php         # Confirmed dead/invalid DOIs
│           ├── null_good_doi.php        # DOIs falsely reported as dead
│           ├── parameters.php           # Citation template and parameter maps
│           ├── regular_expressions.php  # Regular expression constants
│           └── translations.php         # Translations
├── tests/                      # PHPUnit tests
├── .github/workflows/          # CI/CD workflows
├── .github/dependabot.yml      # Automated dependency update configuration
├── .github/cffconvert-requirements.txt  # Hash-pinned cffconvert closure for CITATION.cff validation
├── tools/update_cffconvert_lock.sh      # Regenerates the hash-pinned cffconvert closure
├── vendor/                     # Composer dependencies
├── composer.json               # Dependency configuration
├── docker-compose.yml          # Docker setup
├── Dockerfile                  # Container definition
├── phpunit.xml.dist            # PHPUnit configuration
├── phpstan.neon                # PHPStan configuration
├── psalm.xml                   # Psalm configuration
├── .phpcs.xml                  # Code style configuration
├── progpilot.yml               # Security analysis config
└── ...other config files
```

## Debugging Tips

### Testing Individual Pages

```bash
php src/process_page.php "Article_Name" --savetofiles
# Check output in Article_Name.md
```

### Enabling Debug Output

Check `src/includes/setup.php` for debug flags and logging configuration.

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

- (Historical observation; unverified) Each external API call adds latency (100-500ms)
- Wikipedia API calls are rate-limited
- (Historical observation; unverified) Slow mode can take 30-120 seconds for large pages
- (Historical observation; unverified) Fast mode targets < 10 seconds
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
7. **Always check whether `.github/labeler.yml` needs a new entry** when adding or renaming files (especially new tooling, workflows, or entrypoints); the automatic PR labeling depends on it
8. Submit pull request with clear description
9. **Common Pitfalls:**

   - Forgetting multi-byte string functions
   - Not handling API failures gracefully
   - Violating the verbose code style with compact one-liners
   - Entry points that do not load `setup.php` (e.g. `src/kill_big_job.php`) must define the `CI` and `HTML_OUTPUT` constants themselves: output helpers in `src/includes/user_messages.php` read `HTML_OUTPUT` unguarded, and an undefined constant fatals mid-page, truncating the output to a blank page. Note that tests extending `testBaseClass.php` load `setup.php` and will mask this failure — use the standalone separate-process pattern in `tests/phpunit/killBigJobPageTest.php` when testing such pages.

## Bug Reporting

**Primary channel:** <https://en.wikipedia.org/wiki/User_talk:Citation_bot>

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
# Local runs (phpunit.xml.dist needs a coverage driver): use the stripped config
php -d memory_limit=1G vendor/bin/phpunit --configuration phpunit.local.xml <path>

# CS1 conformance regression gate (fast + slow)
php tools/cs1_harness.php
php tools/cs1_harness.php --slow

# Static analysis
php vendor/bin/phpstan analyze
php vendor/bin/psalm

# Code style check
php vendor/bin/phpcs

# Process single page locally
php src/process_page.php "Wikipedia:Sandbox" --savetofiles

# Update dependencies
composer update
```

## Wikipedia Citation Template Reference

The bot recognizes many CS1/CS2 citation templates and processes them at different levels of depth. See `src/includes/constants/parameters.php` for the authoritative lists of fully, slightly, and barely processed templates. Examples include:

- `{{cite journal}}` - Academic journals
- `{{cite book}}` - Books
- `{{cite web}}` - Websites
- `{{cite news}}` - News articles
- `{{citation}}` - Any reference
- And many more...

## Common Template Parameters

| Parameter | Description |
| --------- | ----------- |
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
| `ssrn` | SSRN identifier (Social Science Research Network) |
| `isbn` | Book identifier |
| `url` | Web URL |
| `access-date` | Date URL accessed |

## Project Status & Maintenance

- **Status:** Production service active; repository classified as stable/inactive (maintenance mode)
- **Maintenance:** Provided as time allows
- **Community:** Open source contributions welcome
- **Response time:** May vary due to volunteer nature

## Resources

- **Documentation:** <https://en.wikipedia.org/wiki/User:Citation_bot/use>
- **Bug reports:** <https://en.wikipedia.org/wiki/User_talk:Citation_bot>
- **Source code:** <https://github.com/ms609/citation-bot>

## Quick Reference for AI Assistants

When helping with this project:

1. **Remember:** Avoid dense compound conditionals; assignments in clearly separated conditions are common in this codebase
2. **Test:** For behavior that depends on real wikitext or APIs, additionally smoke-test a representative page with `--savetofiles`; never rely on this instead of the automated suite
3. **Security:** Never expose OAuth tokens or API keys
4. **Performance:** Consider gadget timeout constraints
5. **Standards:** Follow existing patterns in the codebase.
6. **Testing:** Run full test suite before submitting changes
7. **Documentation:** Update relevant docs with any changes
8. **See also:** [docs/CONTRIBUTING.md](docs/CONTRIBUTING.md) for detailed contribution guidelines
9. **API Stability:** Keep the external API stable - code on Wikipedia relies on this API, and that code is not part of this codebase
10. **No stubs:** Do not implement placeholders or simple implementations.  We want full implementations
11. **Code reuse:** Never assume something is not implemented - search the code base first
12. **Code consistency:**  Before changing behavior, identify the existing pattern elsewhere in the repository and follow it. Do not modernize existing code unless requested.  Avoid unnecessary refactoring.

## Project Philosophy

### Why PHP?

The bot runs on Wikimedia Toolforge, which has excellent PHP support and infrastructure for Wikipedia bots.

### Why Verbose Code Style?

The project prioritizes readability and maintainability over compactness. With 15,000+ commits and multiple contributors over many years, explicit code is easier to debug and modify safely.

### Why Maintenance Mode?

The bot has reached a stable, feature-complete state. It reliably processes Wikipedia pages daily. New features are welcome but the focus is on stability and bug fixes.  Also, the main coders have busy lives.

---

**Last updated:** August 2026
**Maintained by:** Citation Bot community
