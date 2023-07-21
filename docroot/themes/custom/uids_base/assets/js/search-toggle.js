const wrapper = document.querySelector(".search-overlay");
const body = document.body;
const button = document.querySelector("button.search-button");

if (document.querySelector(".search-button")) {
  document
    .querySelector(".search-button")
    .addEventListener("click", searchToggle);
  document
    .querySelector(".search-button")
    .addEventListener("keydown", function (event) {
      if (event.key == "Escape") {
        this.setAttribute("aria-expanded", "false");
        document
          .querySelector(".search-overlay")
          .setAttribute("aria-hidden", "true");
      }
    });
}

function searchToggle() {
  if (document.getElementById("search-button-label")) {
    if (body.classList.contains("search-is-open")) {
      this.setAttribute("aria-expanded", "false");
      wrapper.setAttribute("aria-hidden", "true");
      body.classList.remove("search-is-open");
    } else {
      wrapper.setAttribute("aria-hidden", "false");
      this.setAttribute("aria-expanded", "true");
      body.classList.add("search-is-open");
    }
  }
}

// click outside of menu drawer

document.addEventListener(
  "click",
  function (event) {
    if (!event.target.closest(".search-wrapper")) {
      if (document.getElementById("search-button-label")) {
        document.body.classList.remove("search-is-open");
        document.getElementById("search-button-label").innerHTML = "Search";
        wrapper.setAttribute("aria-hidden", "true");
        button.setAttribute("aria-expanded", "false");
      }
    }
  },
  false
);
