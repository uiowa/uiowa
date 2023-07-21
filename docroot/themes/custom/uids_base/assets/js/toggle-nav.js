const drawerContainer = document.querySelector(".o-canvas__wrapper");
const toggleButtons = document.querySelectorAll("button.toggle-nav__bttn");
const canvasDrawer = document.querySelector('.o-canvas__drawer');
const iowaHeader = document.querySelector('[data-uids-header]');

// Set positioning of canvasDrawer based on iowaBar height
let iowaBarHeight = iowaHeader.offsetHeight;
canvasDrawer.style.top = `${iowaBarHeight}px`;

// drawer open/close functionality
document.addEventListener("click", (e) => {
  if (!e.target.classList.contains("toggle-nav__bttn")) return;
  // Add the active/open class
  e.target.classList.toggle("active");
  e.target.parentNode.classList.toggle("o-canvas--open");

  const isActive = e.target.classList.contains("active");
  e.target.setAttribute("aria-expanded", isActive ? "true" : "false");
  e.target.classList.toggle("inactive", !isActive);
  e.target.nextElementSibling.setAttribute("aria-hidden", !isActive);

  // go through each link
  toggleButtons.forEach((button) => {
    if (button !== e.target) {
      button.classList.remove("active", "inactive");
      button.setAttribute("aria-expanded", "false");
      button.parentNode.classList.remove("o-canvas--open");
      button.nextElementSibling.setAttribute("aria-hidden", "true");
    }
  });

  document.body.classList.toggle("o-canvas--complete", !isActive);
  document.body.classList.toggle("o-canvas--lock", isActive);
});

// click outside of menu drawer
document.addEventListener("click", (event) => {
  if (!event.target.closest(".o-canvas__group")) {
    document.body.classList.remove("o-canvas--lock");
    document.body.classList.add("o-canvas--complete");
    drawerContainer.classList.remove("o-canvas--open");
    toggleButtons.forEach((button) => {
      button.classList.remove("active");
      button.setAttribute("aria-expanded", "false");
      button.nextElementSibling.setAttribute("aria-hidden", "true");
    });
  }
});

// esc key
window.addEventListener("keydown", (event) => {
  if (event.key === "Escape") {
    document.body.classList.remove("o-canvas--lock");
    document.body.classList.add("o-canvas--complete");
    drawerContainer.classList.remove("o-canvas--open");
    toggleButtons.forEach((button) => {
      button.classList.remove("active");
    });
  }
});

// Function to calculate canvasDrawer position based on headerPosition
const updateCanvasDrawerPosition = () => {
  const headerPosition = iowaHeader.getBoundingClientRect().top;
  canvasDrawer.style.top = `${Math.max(iowaBarHeight + headerPosition, 0)}px`;
};

// Call the function initially to set the initial position
updateCanvasDrawerPosition();

// Function to handle resizing and updating iowaBarHeight and canvasDrawer position
const handleResize = () => {
  iowaBarHeight = iowaHeader.offsetHeight;
  updateCanvasDrawerPosition();
};

// Update canvasDrawer position on window resize
window.addEventListener('resize', handleResize);

// Create a MutationObserver to detect changes in the header position
const observer = new MutationObserver(updateCanvasDrawerPosition);

// Observe changes in the header's attributes or subtree
observer.observe(iowaHeader, { attributes: true, subtree: true });
