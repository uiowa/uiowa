/**
 * @file
 * Include gulp.
 */
const { src, dest, parallel, series, watch } = require('gulp');

// Include plugins.
const gulpSass = require('gulp-sass')(require('sass'));
const del = require('del');
const postcss = require('gulp-postcss');
const autoprefixer = require('autoprefixer');
const cssnano = require('cssnano');
const glob = require('gulp-sass-glob');
const sourcemaps = require('gulp-sourcemaps');
const mode = require('gulp-mode')();
const fs = require('fs').promises;
const path = require('path');

const paths = {
  src: `${__dirname}/scss/**/*.scss`,
  dest: `${__dirname}/assets`,
  node: `../../../../node_modules/`,
};

const uids = {
  src: `${paths.node}@uiowa/uids4/src`,
  dest: `${__dirname}/uids/`,
  readylist: [
    'button',
  ],
}

const brandIconSrc = `${paths.node}@uiowa/brand-icons`;

const iconSets = [
  'black',
  'two-color',
];

// Clean.
function clean() {
  return del([
    `${paths.dest}/css/**`,
    `${uids.dest}/**/*`,
  ]);
}

function copyUids() {
  return src([
    `${uids.src}/**/*.scss`,
    `${uids.src}/**/*.js`,
    `${uids.src}/**/*.{jpg,png,svg}`,
  ], { encoding: false })
    .pipe(dest(`${uids.dest}`));
}

// Copy (and modify) icons from the brand-icons package.
async function copyIcons() {
  // Make sure the destination folders exist.
  await Promise.all(
    iconSets.map(dir =>
      fs.mkdir(path.join(__dirname, '/assets/icons/brand/', dir), { recursive: true })
    )
  );

  const files = await fs.readdir(path.join(brandIconSrc, 'icons'));

  // Process all icons at once.
  await Promise.all(files.map(async (filename) => {
    const sourcePath = path.join(brandIconSrc, 'icons', filename);

    const dir = filename.endsWith('-two-color.svg')
      ? iconSets[1] : iconSets[0];

    const destinationPath = path.join(__dirname, '/assets/icons/brand/' + dir, filename);

    // Read the SVG file.
    let svg = await fs.readFile(sourcePath, 'utf-8');

    // Modify the SVG.
    svg = modifySvg(svg);

    // Write the file to the destination.
    await fs.writeFile(destinationPath, svg);
  }));
}

// Perform modifications to the SVG contents.
function modifySvg(svg) {
  svg = svg
    .replace(/<text[^>]*>.*?<\/text>/gs, '')
    .replace(/viewBox="[^"]*"/, 'viewBox="-10 -10 70 70"')
    .replace(/(width|height)=["']\d+['"]/g, '')
    .replace('<svg', '<svg width="70" height="70"');

  if (!svg.includes('fill="transparent"')) {
    svg = svg.replace(
      /(<svg[^>]*>)/,
      '$1<rect x="-10" y="-10" width="70" height="70" fill="transparent"/>'
    );
  }

  svg = svg
    .replace(/\s+stroke-(?=[\s/>])/g, '')
    .replace(/\s+stroke-width=["']\d+['"]/g, '')
    .replace(/(<(?:path|ellipse)[^>]*?)(\s*\/>)/g, '$1 stroke-width="0"$2')
    .replace(/\s+/g, ' ');

  return svg;
}

function css() {
  return src(`${paths.src}`)
    .pipe((mode.development(sourcemaps.init())))
    .pipe(glob())
    .pipe(gulpSass({
      includePaths: [
        "./node_modules",
      ],
      silenceDeprecations: ['import', 'legacy-js-api']
    }).on('error', gulpSass.logError))
    .pipe(postcss([ autoprefixer(), cssnano()]))
    .pipe((mode.development(sourcemaps.write('./'))))
    .pipe(dest(`${paths.dest}/css`));
}

function watchFiles() {
  watch(paths.src, compile);
}

const copy = parallel(copyUids, copyIcons);
const compile = series(clean, copy, css);

exports.copy = copy;
exports.css = css;
exports.icons = copyIcons;
exports.default = compile;
exports.watch = watchFiles;
