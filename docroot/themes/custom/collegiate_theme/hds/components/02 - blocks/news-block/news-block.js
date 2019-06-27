if (document.getElementById("card__button0")) {
  for (i = 0; i < document.getElementsByClassName("card").length; i++) {
    const buttonId = `card__button${i}`;
    document.getElementById(buttonId).addEventListener("click", cardToggle);
  }
}

function cardToggle({
  target
}) {
  const buttonId = target.id;
  const id = buttonId.replace("card__button", "");
  for (i = 0; i < document.getElementsByClassName("card").length; i++) {
    if (id != i) {
      document.getElementsByClassName("card")[i].classList.remove("is-open");
    }
  }
  if (
    document.getElementsByClassName("card")[id].classList.toggle("is-open")
  ) {}
}
