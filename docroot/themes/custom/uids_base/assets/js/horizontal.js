

var menuItems = document.querySelectorAll('li.menu-item--expanded');
Array.prototype.forEach.call(menuItems, function(el, i){
  el.querySelector('a').addEventListener("click",  function(event){
    if (this.parentNode.className == "menu-item--expanded") {
      this.parentNode.className = "menu-item--expanded open";
      this.setAttribute('aria-expanded', "true");
    } else {
      this.parentNode.className = "menu-item--expanded";
      this.setAttribute('aria-expanded', "false");
    }
    event.preventDefault();
    return false;
  });
});

