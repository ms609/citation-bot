<?php
declare(strict_types=1);
if (file_exists(dirname(__DIR__) . '/env.php')) {
    /** @psalm-suppress MissingFile */
    include_once dirname(__DIR__) . '/env.php';
}
require_once __DIR__ . '/includes/PublicConfig.php';
enforce_public_request_configuration(is_string($_SERVER['HTTP_HOST'] ?? null) ? $_SERVER['HTTP_HOST'] : null);
session_start(public_session_start_options());
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
session_write_close();
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
 <head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="license" type="text/html" href="https://www.gnu.org/licenses/gpl-3.0" />
  <link rel="stylesheet" href="assets/results.css" />
  <title>
   Citation Bot
  </title>
  <script src="assets/index.js" defer></script>
 </head>
 <body>
  <a href="#main-form" class="skip-link">Skip to main content</a>
  <header>
    <h1>Wikipedia citation bot</h1>
    <p>Populates empty fields in {{cite journal}} family templates, and fixes other citation issues.</p>
  </header>
  <main id="main-form">
  <form id="botForm" action="process_page.php" method="post">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <p>
      <input type="checkbox" name="slow" id="slow" checked />
      <label for="slow">Thorough mode – a slower but more exhaustive search. Finds bibcodes and expands URLs.</label>
    </p>
    <input type="hidden" name="edit" id="edit" value="webform" />
    <fieldset>
      <legend>Process a single page</legend>
      <p>
        <label for="botPage">Single page:</label>
        <input name="page" id="botPage" value="" placeholder="Page name" autocomplete="off" aria-describedby="botPage-help" />
        <button type="submit" name="pageSubmit" id="PageSubmit" value="Process page" formaction="process_page.php">Process page</button>
        <img class="loading-spinner" src="assets/spinner_18_18.gif" id="PageSpinner" alt="" aria-hidden="true" />
        <span id="botPage-help" class="field-help">Separate multiple pages with a pipe (<code>Page 1|Page 2</code>)</span>
      </p>
    </fieldset>
    <p>– or –</p>
    <fieldset>
      <legend>Process a category</legend>
      <p>
        <label for="botCat">Category:</label>
        <input name="cat" id="botCat" value="" placeholder="Category name" autocomplete="off" />
        <button type="submit" name="catSubmit" id="CatSubmit" value="Process category" formaction="category.php">Process pages in category</button>
        <img class="loading-spinner" src="assets/spinner_18_18.gif" id="CatSpinner" alt="" aria-hidden="true" />
      </p>
    </fieldset>
    <p>– or –</p>
    <fieldset>
      <legend>Process all linked pages</legend>
      <p>
        <label for="botLinked">Linked pages:</label>
        <input name="linkpage" id="botLinked" value="" placeholder="Initial page name" autocomplete="off" aria-describedby="botLinked-help" />
        <button type="submit" name="linkedSubmit" id="LinkedSubmit" value="Process all linked" formaction="linked_pages.php">Process pages linked from</button>
        <img class="loading-spinner" src="assets/spinner_18_18.gif" id="LinkSpinner" alt="" aria-hidden="true" />
      </p>
      <p id="botLinked-help" class="field-help">Only user pages (<code>User:...</code>) can be used with this feature.</p>
    </fieldset>
    <p>
      <label for="wiki_base">Wiki to run on:</label>
      <select name="wiki_base" id="wiki_base">
        <option value="en">en.wikipedia.org</option>
        <option value="simple">simple.wikipedia.org</option>
        <option value="mk">mk.wikipedia.org</option>
        <option value="ru">ru.wikipedia.org</option>
        <option value="sr">sr.wikipedia.org</option>
        <!-- Need to do better international treatment <option value="vi">vi.wikipedia.org</option> -->
        <!-- <option value="mdwiki">MDWiki.org</option> still not authorized -->
      </select>
    </p>
  </form>
  <div role="status" aria-live="polite" id="botStatus" class="sr-only"></div>
  </main>
  <footer>
    <p>
      <a href="https://en.wikipedia.org/wiki/User:Citation_bot/use" title="Using Citation Bot">More&nbsp;details</a> |
      <a href="https://en.wikipedia.org/wiki/User_talk:Citation_bot" title="Report bugs at Wikipedia">Report&nbsp;bugs/suggestions</a> |
      <a href="https://github.com/ms609/citation-bot" title="GitHub repository">Source&nbsp;code</a>
    </p>
    <p>
      Recent edits on:
      &nbsp;<a href="https://en.wikipedia.org/wiki/Special:Contributions/Citation_bot" target="_blank" rel="noopener noreferrer" title="Recent English contributions" aria-label="Recent English contributions (opens in a new tab)" >English</a>,
      &nbsp;<a href="https://simple.wikipedia.org/wiki/Special:Contributions/Citation_bot" target="_blank" rel="noopener noreferrer" title="Recent Simple contributions" aria-label="Recent Simple contributions (opens in a new tab)" >Simple</a>,
      &nbsp;<a href="https://mk.wikipedia.org/wiki/%D0%A1%D0%BF%D0%B5%D1%86%D0%B8%D1%98%D0%B0%D0%BB%D0%BD%D0%B0:%D0%9F%D1%80%D0%B8%D0%B4%D0%BE%D0%BD%D0%B5%D1%81%D0%B8/Citation_bot" target="_blank" rel="noopener noreferrer" title="Recent mk contributions" aria-label="Recent mk contributions (opens in a new tab)" >mk</a>,
      &nbsp;<a href="https://ru.wikipedia.org/wiki/%D0%A1%D0%BB%D1%83%D0%B6%D0%B5%D0%B1%D0%BD%D0%B0%D1%8F:%D0%92%D0%BA%D0%BB%D0%B0%D0%B4/Citation_bot" target="_blank" rel="noopener noreferrer" title="Recent ru contributions" aria-label="Recent ru contributions (opens in a new tab)" >ru</a>,
      &nbsp;<a href="https://sr.wikipedia.org/wiki/%D0%9F%D0%BE%D1%81%D0%B5%D0%B1%D0%BD%D0%BE:%D0%94%D0%BE%D0%BF%D1%80%D0%B8%D0%BD%D0%BE%D1%81%D0%B8/Citation_bot" target="_blank" rel="noopener noreferrer" title="Recent sr contributions" aria-label="Recent sr contributions (opens in a new tab)" >sr</a>,
      &nbsp;<a href="https://vi.wikipedia.org/wiki/%C4%90%E1%BA%B7c_bi%E1%BB%87t:%C4%90%C3%B3ng_g%C3%B3p/Citation_bot" target="_blank" rel="noopener noreferrer" title="Recent vi contributions" aria-label="Recent vi contributions (opens in a new tab)" >vi</a>,
      &nbsp;<a href="https://mdwiki.org/wiki/Special:Contributions/Citation_bot" target="_blank" rel="noopener noreferrer" title="Recent mdwiki contributions" aria-label="Recent mdwiki contributions (opens in a new tab)" >mdwiki</a>
    </p>
    <p>
    Your Wikipedia username will be included in the edit, such as: "Suggested&nbsp;by&nbsp;YourUserID".
    </p>
    <p>
    You will be asked to log in to your Wikipedia account if you have not already authorized this tool.
    </p>
  </footer>
 </body>
</html>
