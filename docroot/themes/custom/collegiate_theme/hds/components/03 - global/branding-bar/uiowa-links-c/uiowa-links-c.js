if (document.getElementById("quick-toggle-c")) {
  document.getElementById("quick-toggle-c").addEventListener("click", quickMenuToggleC);
}

function quickMenuToggleC() {
  const menu = document.querySelector(".quick-nav-c");
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
