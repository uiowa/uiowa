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

const brandIcons = {
  src: `${paths.node}@uiowa/brand-icons`,
  dest: `${__dirname}/brand-icons/`,
  twoColorDest: `${__dirname}/brand-icons/two-color/`,
  blackDest: `${__dirname}/brand-icons/black/`,
};

// Modify Brand Icons.
async function modifySvgFile(filePath) {
  let svgContent = await fs.readFile(filePath, 'utf8');

  svgContent = svgContent
    .replace(/<text[^>]*>.*?<\/text>/gs, '')
    .replace(/viewBox="[^"]*"/, 'viewBox="-10 -10 70 70"')
    .replace(/(width|height)=["']\d+['"]/g, '')
    .replace('<svg', '<svg width="70" height="70"');

  if (!svgContent.includes('fill="white"')) {
    svgContent = svgContent.replace(
      /(<svg[^>]*>)/,
      '$1<rect x="-10" y="-10" width="70" height="70" fill="white"/>'
    );
  }

  svgContent = svgContent
    .replace(/\s+stroke-(?=[\s/>])/g, '')
    .replace(/\s+stroke-width=["']\d+['"]/g, '')
    .replace(/(<(?:path|ellipse)[^>]*?)(\s*\/>)/g, '$1 stroke-width="0"$2')
    .replace(/\s+/g, ' ');
  await fs.writeFile(filePath, svgContent);
}

async function modifySvgFiles() {
  const iconsSourceDir = path.join(brandIcons.dest, 'icons');
  const twoColorDestPath = brandIcons.twoColorDest;
  const blackDestPath = brandIcons.blackDest;

  // Make sure the destination folders exist.
  await Promise.all([
    fs.mkdir(twoColorDestPath, { recursive: true }),
    fs.mkdir(blackDestPath, { recursive: true }),
  ]);

  const files = await fs.readdir(iconsSourceDir);

  // Process all icons at once.
  await Promise.all(files.map(async (filename) => {
    const sourcePath = path.join(iconsSourceDir, filename);

    const destinationPath = filename.endsWith('-two-color.svg')
      ? path.join(twoColorDestPath, filename)
      : path.join(blackDestPath, filename);

    // Copy the original icon to its new location.
    await fs.copyFile(sourcePath, destinationPath);

    // Modify the icon file.
    await modifySvgFile(destinationPath);
  }));
}

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

function copyIcons() {
  return src([
    `${brandIcons.src}/**/*.svg`,
    `${brandIcons.src}/icons.json`,
  ], { encoding: false })
    .pipe(dest(`${brandIcons.dest}`));
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
const compileSvg = series(copyIcons, modifySvgFiles);
const compile = series(clean, copy, compileSvg, css);

exports.copy = copy;
exports.css = css;
exports.svg = modifySvgFiles;
exports.default = compile;
exports.watch = watchFiles;
