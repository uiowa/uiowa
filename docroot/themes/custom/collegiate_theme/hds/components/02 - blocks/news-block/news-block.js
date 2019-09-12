if (document.getElementById("news-card__button0")) {
  for (i = 0; i < document.getElementsByClassName("news-card").length; i++) {
    const buttonId = `news-card__button${i}`;
    document.getElementById(buttonId).addEventListener("click", cardToggle);
  }
}

function cardToggle({
  target
}) {
  const buttonId = target.id;
  const id = buttonId.replace("news-card__button", "");
  for (i = 0; i < document.getElementsByClassName("news-card").length; i++) {
    if (id != i) {
      document.getElementsByClassName("news-card")[i].classList.remove("is-open");
      document.getElementById(buttonId).setAttribute("aria-expanded", "false");
    }
  }
  if (document.getElementsByClassName("news-card")[id].classList.toggle("is-open")) {
    document.getElementById(buttonId).setAttribute("aria-expanded", "true")
  }
}
