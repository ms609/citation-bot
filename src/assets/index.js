var botFormSubmitting = false;

function addDescription(input, descriptionId) {
  var descriptions = (input.getAttribute("aria-describedby") || "").split(/\s+/).filter(Boolean);
  if (!descriptions.includes(descriptionId)) {
    descriptions.push(descriptionId);
    input.setAttribute("aria-describedby", descriptions.join(" "));
  }
}

function removeDescription(input, descriptionId) {
  var descriptions = (input.getAttribute("aria-describedby") || "").split(/\s+/).filter(Boolean);
  descriptions = descriptions.filter(function (id) { return id !== descriptionId; });
  if (descriptions.length) {
    input.setAttribute("aria-describedby", descriptions.join(" "));
  } else {
    input.removeAttribute("aria-describedby");
  }
}

function setFieldError(input, errorId, message) {
  input.classList.add("error");
  input.setAttribute("aria-invalid", "true");
  addDescription(input, errorId);
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
  removeDescription(input, errorId);
  var existing = document.getElementById(errorId);
  if (existing) {
    existing.parentNode.removeChild(existing);
  }
}

function setPageButtonText() {
  var botPage = document.getElementById("botPage");
  var pageSubmit = document.getElementById("PageSubmit");
  if (!botPage || !pageSubmit) {
    return;
  }
  pageSubmit.textContent = "Process page" + ((botPage.value.indexOf("|") > -1) ? "s" : "");
}

function setOperationInputsForSubmit(submitButtonId) {
  var botPage = document.getElementById("botPage");
  var botCat = document.getElementById("botCat");
  var botLinked = document.getElementById("botLinked");

  // A single form contains all three operations. Disable unrelated operation
  // fields before native form serialization so stale values cannot influence
  // the selected server endpoint.
  botPage.disabled = submitButtonId !== "PageSubmit";
  botCat.disabled = submitButtonId !== "CatSubmit";
  botLinked.disabled = submitButtonId !== "LinkedSubmit";

  if (botPage.disabled) clearFieldError(botPage, "botPage-error");
  if (botCat.disabled) clearFieldError(botCat, "botCat-error");
  if (botLinked.disabled) clearFieldError(botLinked, "botLinked-error");
}

function setSubmittingState(submitting) {
  var botForm = document.getElementById("botForm");
  botFormSubmitting = submitting;
  if (botForm) {
    if (submitting) {
      botForm.setAttribute("aria-busy", "true");
    } else {
      botForm.removeAttribute("aria-busy");
    }
  }
}

function ValidateForm(event) {
  var botPage = document.getElementById("botPage");
  var botCat = document.getElementById("botCat");
  var botLinked = document.getElementById("botLinked");
  var submitButton = event.submitter;

  // Programmatic form submission without a submitter has no unambiguous
  // Citation Bot operation. Fail closed instead of throwing or using the
  // form's default action.
  if (!submitButton || !["PageSubmit", "CatSubmit", "LinkedSubmit"].includes(submitButton.id)) {
    event.preventDefault();
    return false;
  }

  if (botFormSubmitting) {
    event.preventDefault();
    return false;
  }

  if (submitButton.id === "PageSubmit") {
    if (botPage.value.trim() === "") {
      setFieldError(botPage, "botPage-error", "Page name is required");
      event.preventDefault();
      return false;
    }
    document.getElementById("PageSpinner").style.display = "inline-block";
  } else if (submitButton.id === "CatSubmit") {
    if (botCat.value.trim() === "") {
      setFieldError(botCat, "botCat-error", "Category name is required");
      event.preventDefault();
      return false;
    }
    document.getElementById("CatSpinner").style.display = "inline-block";
  } else if (submitButton.id === "LinkedSubmit") {
    if (botLinked.value.trim() === "") {
      setFieldError(botLinked, "botLinked-error", "Initial page name is required");
      event.preventDefault();
      return false;
    }
    document.getElementById("LinkSpinner").style.display = "inline-block";
  }

  document.getElementById("botStatus").textContent = "Processing, please wait\u2026";
  setSubmittingState(true);
  setOperationInputsForSubmit(submitButton.id);
  document.getElementById("PageSubmit").disabled = true;
  document.getElementById("CatSubmit").disabled = true;
  document.getElementById("LinkedSubmit").disabled = true;
  return true;
}

function ValidatePageName() {
  setPageButtonText();
  if (this.value.trim() === "") {
    setFieldError(this, "botPage-error", "Page name is required");
    document.getElementById("PageSubmit").disabled = true;
  } else {
    clearFieldError(this, "botPage-error");
    document.getElementById("PageSubmit").disabled = botFormSubmitting;
  }
}

function ValidateCategory() {
  if (this.value.trim() === "") {
    setFieldError(this, "botCat-error", "Category name is required");
    document.getElementById("CatSubmit").disabled = true;
  } else {
    clearFieldError(this, "botCat-error");
    document.getElementById("CatSubmit").disabled = botFormSubmitting;
  }
}

function ValidateLinked() {
  if (this.value.trim() === "") {
    setFieldError(this, "botLinked-error", "Initial page name is required");
    document.getElementById("LinkedSubmit").disabled = true;
  } else {
    clearFieldError(this, "botLinked-error");
    document.getElementById("LinkedSubmit").disabled = botFormSubmitting;
  }
}

function submitFieldOnEnter(event, buttonId) {
  if (event.key !== "Enter" || event.isComposing) {
    return;
  }
  event.preventDefault();
  if (botFormSubmitting) {
    return;
  }
  var button = document.getElementById(buttonId);
  if (button && !button.disabled) {
    document.getElementById("botForm").requestSubmit(button);
  }
}

function ResetTransientFormState() {
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

  setSubmittingState(false);
  if (botPage) {
    botPage.disabled = false;
    clearFieldError(botPage, "botPage-error");
  }
  if (botCat) {
    botCat.disabled = false;
    clearFieldError(botCat, "botCat-error");
  }
  if (botLinked) {
    botLinked.disabled = false;
    clearFieldError(botLinked, "botLinked-error");
  }
  if (catSubmit) catSubmit.disabled = false;
  if (pageSubmit) pageSubmit.disabled = false;
  if (linkedSubmit) linkedSubmit.disabled = false;
  if (pageSpinner) pageSpinner.style.display = "none";
  if (catSpinner) catSpinner.style.display = "none";
  if (linkSpinner) linkSpinner.style.display = "none";
  if (botStatus) botStatus.textContent = "";
  setPageButtonText();
}

function InitializeForm() {
  var botForm = document.getElementById("botForm");
  var botPage = document.getElementById("botPage");
  var botCat = document.getElementById("botCat");
  var botLinked = document.getElementById("botLinked");

  if (!botForm || botForm.dataset.uiInitialized === "true") {
    ResetTransientFormState();
    return;
  }

  botForm.dataset.uiInitialized = "true";
  botForm.addEventListener("submit", ValidateForm);
  if (botPage) {
    botPage.addEventListener("input", ValidatePageName);
    botPage.addEventListener("keydown", function (event) { submitFieldOnEnter(event, "PageSubmit"); });
  }
  if (botCat) {
    botCat.addEventListener("input", ValidateCategory);
    botCat.addEventListener("keydown", function (event) { submitFieldOnEnter(event, "CatSubmit"); });
  }
  if (botLinked) {
    botLinked.addEventListener("input", ValidateLinked);
    botLinked.addEventListener("keydown", function (event) { submitFieldOnEnter(event, "LinkedSubmit"); });
  }
  ResetTransientFormState();
}

// This script is loaded with defer, so the DOM is parsed before it runs.
InitializeForm();
window.addEventListener("pageshow", ResetTransientFormState);
