function setFieldError(input, errorId, message) {
  input.classList.add("error");
  input.setAttribute("aria-invalid", "true");
  input.setAttribute("aria-describedby", errorId);
  if (!document.getElementById(errorId)) {
    var span = document.createElement("span");
    span.id = errorId;
    span.setAttribute("role", "alert");
    span.className = "field-error";
    span.textContent = message;
    input.parentNode.insertBefore(span, input.nextSibling);
  }
}

function clearFieldError(input, errorId) {
  input.classList.remove("error");
  input.removeAttribute("aria-invalid");
  input.removeAttribute("aria-describedby");
  var existing = document.getElementById(errorId);
  if (existing) {
    existing.parentNode.removeChild(existing);
  }
}

function ValidateForm(event) {
  var botPage = document.getElementById("botPage");
  var botCat = document.getElementById("botCat");
  var botLinked = document.getElementById("botLinked");
  var submitButton = event.submitter;

  if (submitButton.id === "PageSubmit") {
    if (botPage.value.trim() === "") {
      setFieldError(botPage, "botPage-error", "Page name is required");
      submitButton.disabled = "disabled";
      return false;
    }
    document.getElementById("PageSpinner").style.display = "inline-block";
    document.getElementById("botStatus").textContent = "Processing, please wait\u2026";
  } else if (submitButton.id === "CatSubmit") {
    if (botCat.value.trim() === "") {
      setFieldError(botCat, "botCat-error", "Category name is required");
      submitButton.disabled = "disabled";
      return false;
    }
    document.getElementById("CatSpinner").style.display = "inline-block";
    document.getElementById("botStatus").textContent = "Processing, please wait\u2026";
  } else if (submitButton.id === "LinkedSubmit") {
    if (botLinked.value.trim() === "") {
      setFieldError(botLinked, "botLinked-error", "Initial page name is required");
      submitButton.disabled = "disabled";
      return false;
    }
    document.getElementById("LinkSpinner").style.display = "inline-block";
    document.getElementById("botStatus").textContent = "Processing, please wait\u2026";
  }
  document.getElementById("PageSubmit").disabled = "disabled";
  document.getElementById("CatSubmit").disabled = "disabled";
  document.getElementById("LinkedSubmit").disabled = "disabled";
  return true;
}

function ValidatePageName() {
  document.getElementById("PageSubmit").innerHTML = "Process page" +
    ((document.getElementById("botPage").value.indexOf("|") > -1) ? "s" : "");
  if (this.value.trim() === "") {
    setFieldError(this, "botPage-error", "Page name is required");
    document.getElementById("PageSubmit").disabled = "disabled";
  } else {
    clearFieldError(this, "botPage-error");
    document.getElementById("PageSubmit").disabled = false;
  }
}

function ValidateCategory() {
  if (this.value.trim() === "") {
    setFieldError(this, "botCat-error", "Category name is required");
    document.getElementById("CatSubmit").disabled = "disabled";
  } else {
    clearFieldError(this, "botCat-error");
    document.getElementById("CatSubmit").disabled = false;
  }
}

function ValidateLinked() {
  if (this.value.trim() === "") {
    setFieldError(this, "botLinked-error", "Initial page name is required");
    document.getElementById("LinkedSubmit").disabled = "disabled";
  } else {
    clearFieldError(this, "botLinked-error");
    document.getElementById("LinkedSubmit").disabled = false;
  }
}

function InitializeForm() {
  var botForm = document.getElementById("botForm");
  var botPage = document.getElementById("botPage");
  var botCat = document.getElementById("botCat");
  var botLinked = document.getElementById("botLinked");
  var catSubmit = document.getElementById("CatSubmit");
  var pageSubmit = document.getElementById("PageSubmit");
  var linkedSubmit = document.getElementById("LinkedSubmit");
  var pageSpinner = document.getElementById("PageSpinner");
  var catSpinner = document.getElementById("CatSpinner");
  var linkSpinner = document.getElementById("LinkSpinner");
  var botStatus = document.getElementById("botStatus");

  if (botForm) botForm.onsubmit = ValidateForm;
  if (botPage) {
    botPage.oninput = ValidatePageName;
    botPage.value = "";
    clearFieldError(botPage, "botPage-error");
  }
  if (botCat) {
    botCat.oninput = ValidateCategory;
    botCat.value = "";
    clearFieldError(botCat, "botCat-error");
  }
  if (botLinked) {
    botLinked.oninput = ValidateLinked;
    botLinked.value = "";
    clearFieldError(botLinked, "botLinked-error");
  }
  if (catSubmit) catSubmit.disabled = false;
  if (pageSubmit) pageSubmit.disabled = false;
  if (linkedSubmit) linkedSubmit.disabled = false;
  if (pageSpinner) pageSpinner.style.display = "none";
  if (catSpinner) catSpinner.style.display = "none";
  if (linkSpinner) linkSpinner.style.display = "none";
  if (botStatus) botStatus.textContent = "";
}

window.onload = InitializeForm;
window.addEventListener('pageshow', InitializeForm);
