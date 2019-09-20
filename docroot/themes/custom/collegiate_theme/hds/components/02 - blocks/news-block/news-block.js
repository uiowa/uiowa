// Listen for all clicks on the document

document.addEventListener(
  "click",
  function (e) {
    // Leave if it's not a .toggle-bttn
    if (!e.target.classList.contains("toggle-bttn")) return;

    // Add the active/open class
    e.target.classList.toggle("active");
    e.target.parentNode.parentNode.classList.toggle("is-open");

    if (e.target.classList.contains("active")) {
      e.target.setAttribute("aria-expanded", "true");
    } else {
      e.target.setAttribute("aria-expanded", "false");
    }

    // Get all toggle bttn links
    var links = document.querySelectorAll(".toggle-bttn");

    // Loop through each link
    for (var i = 0; i < links.length; i++) {
      // If the link is the one clicked, skip it
      if (links[i] === e.target) continue;

      // Remove the .active/.is-open class
      links[i].classList.remove("active");
      links[i].setAttribute("aria-expanded", "false");
      links[i].parentNode.parentNode.classList.remove("is-open");
    }
  },
  false
);
