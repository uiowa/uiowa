document.addEventListener('DOMContentLoaded', doSomething, false);

function doSomething () {
  const queryString = window.location.search;
  const urlParams = new URLSearchParams(queryString);
  const isSignage = urlParams.get('display');
  if (isSignage !== null) {
    document.querySelector('html').classList.add('signage-display-view');
  }
  else {
    // console.log(title, sections);
  }
}
