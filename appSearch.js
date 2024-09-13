let apps = [];
let yamlLib = {};
const appNodes = [];
let filter;
requirejs(['./node_modules/js-yaml/dist/js-yaml.js'], function(jsyaml) {
  yamlLib = jsyaml;
  main();
});

function main() {
  setup();
  manifestConstruct();
}

function setup() {
  filter = document.getElementById('filter');
  filter.addEventListener('input', filterChanged);
}

function filterChanged(e) {
  appNodes.forEach((app) => {
    filterApp(app);
  })
}

function manifestConstruct() {
  fetch('./blt/manifest.yml')
    .then((res) => res.text())
    .then((text) => {
      const yamlDoc = yamlLib.load(text);
      apps = Object.keys(yamlDoc);
      const container = document.getElementById('manifest');
      apps.forEach((app) => {
        const appNodes = generateApp(yamlDoc, app);
        container.appendChild(appNodes);
      });

    })
    .catch((e) => console.error(e));
}

function filterApp(app) {
  const sites = app.querySelectorAll('a');
  const appHeader = app.querySelector('h2');
  const filterText = filter.value;
  let min1 = false;

  sites.forEach((site) => {
    const siteText = site.innerText;
    const display = siteText.includes(filterText);

    if (display) {
      site.style.display = 'block';
      min1 = true;
    }
    else {
      site.style.display = 'none';
    }
  });

  if  (min1) {
    appHeader.style.display = 'block';
  }
  else {
    appHeader.style.display = 'none';
  }
}

function generateApp(yaml, appName) {
  const app = yaml[appName];
  const appContainer = newElement('div', '');
  appContainer.id = appName;
  appNodes.push(appContainer);
  appContainer.appendChild(newElement('h2', appName));
  app.forEach((site) => {
    appContainer.appendChild(newElement('a', site));
  });
  return appContainer;
}
function newElement(tag, contents, href = null) {
  // create a new div element
  const newDiv = document.createElement(tag);
  newDiv.style.display = 'block';

  if (contents) {
    // and give it some content
    const newContent = document.createTextNode(contents);

    if (tag === 'a') {
      newDiv.href = 'https://' + (href !== null ? href : contents);
    }

    // add the text node to the newly created div
    newDiv.appendChild(newContent);
  }

  return newDiv;
}
