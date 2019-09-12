if (document.querySelector(".search-button")) {
  document.querySelector(".search-button").addEventListener("click", searchToggle);
  document.querySelector(".search-button").addEventListener('keydown', function (event) {
    if (event.key == "Escape") {
      this.setAttribute("aria-expanded", "false");
      document.querySelector(".search-wrapper").classList.remove("is-open");
    }
  });
}

function searchToggle() {
  const wrapper = document.querySelector(".search-wrapper");
  const body = document.body;

  if (wrapper.classList.contains("is-open")) {
    this.setAttribute("aria-expanded", "false");
    wrapper.classList.remove("is-open");
    body.classList.remove("search-is-open");
  } else {
    wrapper.classList.add("is-open");
    this.setAttribute("aria-expanded", "true");
    body.classList.add("search-is-open");
  }
}

if (document.getElementById("search-tabs")) {
  const tabs = new Tabby("[data-tabs]");
}
