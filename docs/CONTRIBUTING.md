# How to contribute

Thanks for contributing to the maintenance of Citation Bot.

## Testing

We use phpunit to test code; please write test case examples for new code you create.
It is helpful if each test case example describes the specific function that it is trying
to test.

## Quality verification

All code is run through several tests.  The primary test is a suite of example pages and citation templates.  There are a variety of static code analysis tests that look for common errors.
The security tainted data tests make sure that all "untrusted input" (data from wikipedia pages) is output wrapped with the echoable() function: this is not done primarily for security, but for proper output formatting.
The GitHub provided CodeQL test suite is also enabled, but that just checks the one JavaScript file.

## Submitting changes

Please send a [GitHub Pull Request](https://github.com/ms609/citation-bot/pull/new/master) with a clear list of what you've done (read more about [pull requests](https://help.github.com/articles/about-pull-requests/)).
Including a test case that demonstrates the bug you are trying to fix in the pull request would be much appreciated, to avoid errors resurfacing.
Please follow our coding conventions (below) and make sure all of your commits are atomic (one feature per commit).

Always write a clear log message for your commits. One-line messages are fine for small changes, but bigger changes should look like this:

    $ git commit -m "A brief summary of the commit
    > 
    > A paragraph describing what changed and its impact."

## API keys

The test suite detects missing keys and skips tests.  A developer should consider getting their own keys set up for development.

## Coding conventions

  - We indent using four spaces (soft tabs - note that many files do not currently match this). No files should have tabs in them
  - Constants are named using CAPITALS, functions and variables using under_scores()
  - We ALWAYS put spaces after list items and method parameters (`[1, 2, 3]`, not `[1,2,3]`) and around operators (`x += 1`, not `x+=1`)
  - Regular expressions are defined using the symbol `~` in place of `/`, to reduce escaping and improve legibility when handling URLs.
  - We prefer `elseif` to `else if`
  - We prefer `===` and `!==` to `==` and `!=`
  - We prefer `bool` to `boolean`, and other such deprecated aliases
  - We prefer `curl` to `file_get_contents` and `get_headers` for easier debugging and greater control.  The curl header size is in bytes not multibyte character length.
  - We use `echo` and `exit` instead of `print` and `die`
  - `echo` should use commas instead of dots to avoid concatenation overhead
  - All code must be valid PHP 8.4
  - We prefer [] to array()
  - in_array should always pass the strict parameter
  - Directly comparing strings to integer with comparison operators is different in PHP 7 and 8, so they should not be used
  - We want 100% code coverage with untestable code flagged in the source -- such as code that handles error conditions.  See the API php files for lots of examples of non-coverage code.
  - All curl_init() should be replaced with bot_curl_init() calls, which sets reasonable defaults.  Also reasonable timeouts should be set depending upon the website.
  - error_reporting(E_ALL) and declare(strict_types=1) are both set
  - Multi-byte functions should be used (even when the strings are obviously not multi-byte for safety and consistency), such as mb_ucwords instead of ucwords (there are a couple of non-standard ones provided within the source code).  Do not use for http headers that are byte counts.
  - Do not use `strtok` since it saves a buffer internally
  - `array_merge` can often be replaced with the PHP spread feature which saves time and memory.
  - `array_unshift` should only be used when adding to the front of the array is needed

## Bot output conventions

The bot reports its activity to users using:
  - A new line beginning with an asterisk `*_` to announce that a new item is being analysed
  - A new line beginning with a space and a right angle bracket `_>_` to announce that it is undertaking an expansion activity
  - A new line beginning with three, five, seven or more spaces to announce sub-steps of the expansion activity
  - A new line beginning with three spaces and a symbol `___X_` to denote that it is changing the value of a parameter:
    - `+` denotes a newly added parameter
    - `-` denotes the removal of a parameter
    - `~` denotes that the name or value of an existing parameter is being modified
    - `.` denotes that a change has been considered but deemed unnecessary or unsuitable
    - `!` is used to denote an outcome that may require review by the user or bot maintainer
      
  We recommend using the "report_" family of functions defined in `user_messages.php` to communicate with the user.

## Defensive programming is necessary

  - External data sources that send unexpected data, including wikipedia - be defensive in your programming.
  - External data sources that suddenly change data - we need tests to detect this
  - CS1/CS2 sometimes changes and what was a good edit yesterday is bad edit today.  Thus the wikipedia talk page for the bot need to be monitored for bugs and suggestions.
  - Do not merge changes without running the test suite. At a minimum, run the full test suite and ensure the non-static tests execute beyond initial startup before merging. “Minor changes” are frequently incorrect and must be validated by tests.  Trust us, we have failed.

