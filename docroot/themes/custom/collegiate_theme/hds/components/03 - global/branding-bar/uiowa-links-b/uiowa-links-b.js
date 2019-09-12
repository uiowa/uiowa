if (document.getElementById("quick-toggle-b")) {
  document.getElementById("quick-toggle-b").addEventListener("click", quickMenuToggleB);
}

function quickMenuToggleB() {
  var menu = document.querySelector(".quick-nav-b");

  if (menu.classList.contains("is-open")) {
    this.setAttribute("aria-expanded", "false");
    menu.classList.remove("is-open");
  } else {
    menu.classList.add("is-open");
    this.setAttribute("aria-expanded", "true");
  }
}
