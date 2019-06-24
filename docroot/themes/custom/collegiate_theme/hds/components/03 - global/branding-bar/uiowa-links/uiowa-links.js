if (document.getElementById("quick-toggle")) {
  document.getElementById("quick-toggle").addEventListener("click", quickMenuToggle);
}

function quickMenuToggle() {
  var menu = document.querySelector(".quick-nav");

  if (menu.classList.contains("is-open")) {
    this.setAttribute("aria-expanded", "false");
    menu.classList.remove("is-open");
  } else {
    menu.classList.add("is-open");
    this.setAttribute("aria-expanded", "true");
  }
}
