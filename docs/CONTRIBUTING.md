# How to contribute

Thanks for contributing to the maintenance of Citation Bot.

## Testing

We use phpunit in Travis CI to test code; please write testcase examples for new code you create.

## Submitting changes

Please send a [GitHub Pull Request](https://github.com/ms609/citation-bot/pull/new/master) with a clear list of what you've done (read more about [pull requests](http://help.github.com/pull-requests/)).
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
  * We use `echo` for normal print out, and `print` for output that the user should only see if there is a problem (The distinction between a problem and normal behaviour is somewhat arbitrary, because for example a failure of a CrossRef search might be the Bot's fault or it might be a CrossRef error
