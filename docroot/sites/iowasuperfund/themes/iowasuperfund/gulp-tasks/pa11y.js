/**
 * @file
 * Task: test:pa11y.
 * Pa11y tests websites for accessibility issues. http://pa11y.org/.
 */

module.exports = function (gulp, plugins, options) {
  'use strict';

  const pa11y = plugins.pa11y;
  const gutil = plugins.gutil;

  gulp.task('test:pa11y', (cb) => {

    // Initialising the initial values.
    let errors = 0, warnings = 0, passed = false,  isCi = false, baseUrl = "";

    isCi =  process.env['CI'] == 'true';
    baseUrl = isCi ? 'http://127.0.0.1:8888' : 'http://localhost';

    // Adjust the base URL for CI and VM.
    // Need to come up with a more elegant approach that uses the base url from BLT.
    options.pa11y.urls = options.pa11y.urls.reduce((arr, url) => arr.concat(baseUrl + url), []);

    // Starting the audit.
    plugins.gutil.log('Accessibility Audit starts');

    // Initialising the test urls using pa11y function and options passed.
    const testpa11y = options.pa11y.urls.reduce((arr, el) => el.length ? [...arr, pa11y(el, options.pa11y)] : arr, []); // pure fn - immutable.

    // Using ES6 Promise to fetch all pa11y results.
    Promise.all(testpa11y)
    // success response.
    .then(results => {
      // Iterating through results array.
      results.map((result) => {
        // If results has issues.
        if (Object.keys(result.issues).length) {
          // Iterating through issues
          result.issues.map((issue) => {
            // Defining the message template for issues
            const message = `\n================================================================================\n
                      ${result.pageUrl}\n
                      ${issue.type}\n
                      ${issue.code}\n
                      ${issue.context}\n
                      ${issue.message}\n
                      ${issue.selector}
                      \n================================================================================\n`;
            // Logging errors in red
            if (issue.type === 'error') {
              errors += 1;
              gutil.log(gutil.colors.red(message));
            }
            // Logging warnings in magenta
            else if(issue.type === 'warning') {
              warnings += 1;
              gutil.log(gutil.colors.magenta(message));
            }
          });
        } else {
          // If the obj type has different data type.
          gutil.log(result);
        }
      });
      // Logging of issues finishes

      // Updating the build response as per the promise response.
      // If error crosses threshold.
      if (options.pa11y.threshold.errors > -1 && errors > options.pa11y.threshold.errors) {
        cb(new gutil.PluginError('pa11y',
          gutil.colors.red(
            `\n================================================================================\n
            Build failed due to accessibility errors exceeding threshold ( ${errors} errors) with a threshold of ${options.pa11y.threshold.errors}
            \n================================================================================\n
            ${errors} errors\n
            ${warnings} warnings\n`
          )
        ));
      }
      // If warnings crosses threshold.
      else if (options.pa11y.threshold.warnings > -1 && warnings > options.pa11y.threshold.warnings) {
        cb(new gutil.PluginError('pa11y',
          gutil.colors.magenta(
            `\n================================================================================\n
            Build failed due to accessibility warnings exceeding threshold (${warnings} warnings) with a threshold of ${options.pa11y.threshold.warnings}
            \n================================================================================\n
            ${errors} errors\n
            ${warnings} warnings\n`
          )
        ));
      }
      // In case of no warning and no error pass the build.
      else {
        gutil.log('pa11y', gutil.colors.cyan(
          `\n================================================================================\n
          Build succeeded.
          \n================================================================================\n
          ${errors} errors\n
          ${warnings} warnings\n`
        ));

        // In case of pass, updating the passed status to true in case of passing
        passed = true;
      }

      // Breaking the gulp task if status is not true;
      if (!passed) {
        return 0;
      }
    })
    // Error handling code.
    .catch((error) => {
      gutil.log(error.message);
        return 0;
    });
  });
}