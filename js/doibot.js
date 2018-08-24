function ValidateForm() {
  var botPage = document.getElementById("botPage");
  var botCat = document.getElementById("botCat");
  console.log(event);
  console.log(Event.target);
  var submitButton;
  if(typeof event.explicitOriginalTarget != 'undefined'){  //
                 submitButton = event.explicitOriginalTarget;
             }else if(typeof document.activeElement.value != 'undefined'){  // IE
                 submitButton = document.activeElement;
             };
             console.log(submitButton);
  if (botPage.value.trim() == "") {
    botPage.classList.add("error");
    return false;
  } else {
    document.getElementById("PageSubmit").disabled = "disabled";
    document.getElementById("CatSubmit").disabled = "disabled";
    document.getElementById("PageSpinner").style.display = "inline-block";
    return true;
  }
}

function ValidatePageName() {
  if (this.value.trim() == "") {
    this.classList.add("error");
    document.getElementById("PageSubmit").disabled = "disabled";
  } else {
    this.classList.remove("error");
    document.getElementById("PageSubmit").disabled = false;
  }
}

function ValidateCategory() {
  if (this.value.trim() == "") {
    this.classList.add("error");
    document.getElementById("CatSubmit").disabled = "disabled";
  } else {
    this.classList.remove("error");
    document.getElementById("CatSubmit").disabled = false;
  }
}

function InitializeForm() {
  document.getElementById("botForm").onsubmit = ValidateForm;
  document.getElementById("botPage").onchange = ValidatePageName;
  document.getElementById("botCat").onchange = ValidateCategory;
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