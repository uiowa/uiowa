if (document.getElementById("quick-toggle-d")) {
  document.getElementById("quick-toggle-d").addEventListener("click", quickMenuToggleD);
}

function quickMenuToggleD() {
  const menu = document.querySelector(".quick-nav-d");
  const body = document.body;

  if (menu.classList.contains("is-open")) {
    this.setAttribute("aria-expanded", "false");
    menu.classList.remove("is-open");
    body.classList.remove("qlinks-is-open");
  } else {
    menu.classList.add("is-open");
    this.setAttribute("aria-expanded", "true");
    body.classList.add("qlinks-is-open");
  }
}
