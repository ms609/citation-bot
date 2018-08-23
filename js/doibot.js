function ValidateForm() {
  var botPage = document.getElementById("botPage");
  if (botPage.value.trim() == "") {
    botPage.classList.add("error");
    return false;
  } else {
  console.log(botPage.value);
    document.getElementById("SubmitButton").disabled = "disabled";
    document.getElementById("WaitSpinner").style.display = "inline-block";
    return true;
  }
}

function ValidatePageName() {
  if (this.value.trim() == "") {
    this.classList.add("error");
    document.getElementById("SubmitButton").disabled = "disabled";
  } else {
    this.classList.remove("error");
    document.getElementById("SubmitButton").disabled = false;
  }
}

function InitializeForm() {
  document.getElementById("botForm").onsubmit = ValidateForm;
  document.getElementById("botPage").onchange = ValidatePageName;
  username = localStorage.getItem('username');
  if (username) {
    document.getElementById("user").value = username;
    document.getElementById("saveUsername").checked = "checked";
  }
}

function SaveUsername() {
  if (document.getElementById("saveUsername").checked) {
    localStorage.setItem("username", document.getElementById("user").value)
  } else {
    localStorage.removeItem("username");
  }
}

window.onload = InitializeForm;