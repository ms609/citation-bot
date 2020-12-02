# How to contribute

Thanks for contributing to the maintenance of Citation Bot.

## Testing

We use phpunit in Travis CI to test code; please write testcase examples for new code you create.
It is helpful if each testcase example describes the specific function that it is trying
to test.

## Quality verification
All code is run through several tests.  The primary test is a suite of example pages and citation templates.  There are a variety of static code analysis pre-tests that look for common errors.  One test verifies that the code at least looks like valid PHP dsfasdfads8.0. The "PHP psalm Taint" uses a security tainted data test to make sure that all "untrusted input" (data from wikipedia pages) is output wrapped with the echoable() function.  This is not done for security, but for proper output formatting in a web browser.

## Submitting changes

Please send a [GitHub Pull Request](https://github.com/ms609/citation-bot/pull/new/master) with a clear list of what you've done (read more about [pull requests](https://help.github.com/articles/about-pull-requests/)).
Including a test case that demonstrates the bug you are trying to fix in the pull request would be much appreciated, to avoid errors resurfacing.
Please follow our coding conventions (below) and make sure all of your commits are atomic (one feature per commit).

Always write a clear log message for your commits. One-line messages are fine for small changes, but bigger changes should look like this:

    $ git commit -m "A brief summary of the commit
    > 
    > A paragraph describing what changed and its impact."

## Coding conventions

  * We indent using two spaces (soft tabs)
  * Constants are named using CAPITALS, functions and variables using under_scores()
  * We ALWAYS put spaces after list items and method parameters (`[1, 2, 3]`, not `[1,2,3]`) and around operators (`x += 1`, not `x+=1`)
  * Regular expressions are defined using the symbol `~` in place of `/`, to reduce escaping and improve legibility when handling URLs.
  * We prefer `elseif` to `else if`
  * We prefer `curl` to `file_get_contents` for easier debugging and greater control
  * We use `echo` and `exit` for normal code, and `print` and `die` for debug code that is intended to be removed later
  * All code is verified to be valid PHP 7.3, and 8.0 according to static analysis
  * All code is verified to be valid PHP 7.3 at runtime
  * We want 100% code coverage with untestable code flagged in the source -- such as code that handles error conditions.  See the file apiFunctions.php for lots of examples of non-coverage code.
  * All curl_init() calls must also set CURLOPT_TIMEOUT to something reasonable for the importance of the data and the speed of the website

## Bot output conventions
The bot reports its activity to users using:
  * A new line beginning with an asterisk `*_` to announce that a new item is being analysed
  * A new line beginning with a space and a right angle bracket `_>_` to announce that it is undertaking an expansion activity
  * A new line beginning with three, five, seven or more spaces to announce sub-steps of the expansion activity
  * A new line beginning with three spaces and a symbol `___X_` to denote that it is changing the value of a parameter:
    * `+` denotes a newly added parameter
    * `-` denotes the removal of a parameter
    * `~` denotes that the name or value of an existing parameter is being modified
    * `.` denotes that a change has been considered but deemed unnecessary or unsuitable
    * `!` is used to denote an outcome that may require review by the user or bot maintainer
      
  We recommend using the "report_" family of functions defined in `user_messages.php` to communicate with the user.
