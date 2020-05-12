const elements = document.getElementsByClassName("feature");
for (let i = 0; i < elements.length; i++) {
  elements[i].onmouseover = elements[i].onfocus = function () {
    // remove attribute from sibling
    let el = elements[0];
    while (el) {
      if (el.tagName === "A") {
        //remove attribute
        el.setAttribute("aria-expanded", "false");
      }
      // pass the attribute to sibling
      el = el.nextSibling;
    }
    this.setAttribute("aria-expanded", "true");
  };
  elements[i].onmouseout = elements[i].onfocus = function () {
    let el = elements[0];
     while (el) {
       if (el.tagName === "A") {
         //remove attribute
         el.setAttribute("aria-expanded", "false");
       }
       // pass the attribute to sibling
       el = el.nextSibling;
     }
 elements[0].setAttribute("aria-expanded", "true");
  };
}

if (document.getElementById("video-btn")) {
  document.getElementById("video-btn").addEventListener("click", pausePlay);
}

const video = document.getElementById("video-container");
const btn = document.getElementById("video-btn");

// Pause and play
function pausePlay() {
  if (video.paused) {
    video.play();
    btn.innerHTML = "<span class='element-invisible'>" + "Pause" + "</span>";
    btn.classList.remove("video-btn__play");
    btn.classList.add("video-btn__pause");
  } else {
    video.pause();
    btn.innerHTML = "<span class='element-invisible'>" + "Play" + "</span>";
    btn.classList.remove("video-btn__pause");
    btn.classList.add("video-btn__play");
  }
}
