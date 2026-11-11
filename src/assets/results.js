// Results pages stream output for as long as the run lasts, so the server has
// to send the "running" title before it knows how the run will end. This
// script is loaded with defer, so it runs only once the whole response has
// arrived: after a normal finish, an error, or an early exit that skips the
// footer. Then the tab title tells the user the run has stopped.
(function () {
  var main = document.getElementById("main-content");

  // A GET request is turned into a confirmation form instead of a run.
  if (main && main.querySelector("form")) {
    document.title = "Citation Bot: confirm request";
  } else {
    document.title = "Citation Bot: finished";
  }
})();
