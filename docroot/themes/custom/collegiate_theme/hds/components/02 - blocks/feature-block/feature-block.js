let feature = document.getElementById("feature__container");
// moves over the div and remove class
if (document.getElementById("feature__container")) {
  feature.addEventListener("mouseenter", function (event) {
    document.getElementsByClassName('feature')[0].classList.remove("is-open");
  });
}
