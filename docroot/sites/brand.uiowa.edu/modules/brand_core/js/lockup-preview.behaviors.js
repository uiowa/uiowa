/**
 * @file
 * Lockup Preview.
 */
(function ($, Drupal) {
    Drupal.behaviors.lockupPreview = {
        attach: function () {
            var lockupPreview = $("#lockup-preview");
            var primaryUnit = $("#edit-field-lockup-primary-unit-0-value");
            var subUnit = $("#edit-field-lockup-sub-unit-0-value");
            var primaryValueText;
            var primaryPreviewText;
            var secondaryValueText;
            var secondaryPreviewText;
            var Timer = 0;
            var isValid = true;

            // The maximum number of rows that the textareas are allowed to have.
            var maxRows = 3;

            // Add warning HTML.
            warningHTML();

            // Show preview and inject content based on existing input or live input.
            if (primaryUnit.val() !== "") {
                $("#lockup-preview").addClass('show-preview');
                $(".lockup-stacked .primary-unit").text(primaryUnit.val());
                $(".lockup-horizontal .primary-unit").text(primaryUnit.val());
                calcDivider();
            }

            if (subUnit.val() !== "") {
                $("#lockup-preview").addClass('show-preview');
                $(".lockup-stacked .sub-unit").text(subUnit.val());
                $(".lockup-horizontal .sub-unit").text(subUnit.val());
            }

            primaryUnit.on("input", function (event) {
                $("#lockup-preview").addClass('show-preview');

                var textarea = $(this),
                    text = textarea.val(),
                    numberOfLines = (text.match(/\n/g) || []).length + 1;

                primaryValueText = textareaSanitize(text, numberOfLines);
                primaryPreviewText  = previewSanitize(text, numberOfLines);

                textarea.val(primaryValueText);
                $(".lockup-stacked .primary-unit").text(primaryPreviewText);
                $(".lockup-horizontal .primary-unit").text(primaryPreviewText);

                // Set the divider line width.
                calcDivider();

                // Check if input is valid.
                isInputValid();
            });

            subUnit.on("input", function (event) {
                $("#lockup-preview").show();

                var textarea = $(this),
                    text = textarea.val(),
                    numberOfLines = (text.match(/\n/g) || []).length + 1;

                secondaryValueText = textareaSanitize(text, numberOfLines);
                secondaryPreviewText  = previewSanitize(text, numberOfLines);

                textarea.val(secondaryValueText);
                $(".lockup-stacked .sub-unit").text(secondaryPreviewText);
                $(".lockup-horizontal .sub-unit").text(secondaryPreviewText);

                // Check if input is valid.
                isInputValid();
            });

            switch ($("#edit-moderation-state-0-state :selected").val()) {
                case 'draft':
                    $("#edit-submit").prop('value', 'Save draft');
                    break;
                case 'review':
                    $("#edit-submit").prop('value', 'Submit for approval');
                    break;
                default:
                    $("#edit-submit").prop('value', 'Save');
                    break;
            }
            $('#edit-moderation-state-0-state').change(function(){
                switch ($(this).val()) {
                    case 'draft':
                        $("#edit-submit").prop('value', 'Save draft');
                        break;
                    case 'review':
                        $("#edit-submit").prop('value', 'Submit for approval');
                        break;
                    default:
                        $("#edit-submit").prop('value', 'Save');
                        break;
                }
            });

            // Sanitizes text for the textarea.
            function textareaSanitize(text, numberOfLines) {
                var sanitizedText;
                // Change all dumb quotes to smart quotes.
                sanitizedText = dumbQuotes(text);
                // Redundantly limit any newlines and prevent pasting more than the max number of lines.
                sanitizedText = limitNewlines(maxRows, numberOfLines , sanitizedText);
                return (sanitizedText);
            }
            // Sanitizes text for the Preview.
            function previewSanitize(text, numberOfLines) {
                var sanitizedText;
                // Remove all non quote, space, or dash special characters.
                sanitizedText = limitCharacters(text);
                // Remove spaces and dashes from sides of lines.
                sanitizedText = wingCharacterRemove(sanitizedText);
                // Change all dumb quotes to smart quotes.
                sanitizedText = dumbQuotes(sanitizedText);
                // Redundantly limit any newlines and prevent pasting more than the max number of lines.
                sanitizedText = limitNewlines(maxRows, numberOfLines , sanitizedText);
                return (sanitizedText);
            }

            // Creates a limit on newlines and deletes any after the limit has been reached.
            function limitNewlines(limit, nLines, string) {
                if (nLines > limit) {
                    var toRemove = nLines-limit;
                    var modText = string.split('').reverse();
                    while (toRemove > 0) {
                        modText.splice(modText.indexOf('\n'), 1);
                        toRemove--;
                    }
                    return modText.reverse().join('');
                }
                return string;
            }

            // Replace Dumb quotes with Smart Quotes.
            // Help from here: https://stackoverflow.com/questions/14890681/dumb-quotes-into-smart-quotes-javascript-issue.
            function dumbQuotes(string) {
                return string.replace(/'\b/g, "\u2018")     // Opening singles
                    .replace(/\b'/g, "\u2019")     // Closing singles
                    .replace(/"\b/g, "\u201c")     // Opening doubles
                    .replace(/\b"/g, "\u201d")     // Closing doubles
                    .replace(/--/g,  "\u2014")     // em-dashes
                    .replace(/\b\u2018\b/g,  "'");
            }

            // Remove spaces and dashes from sides of lines.
            function wingCharacterRemove(string) {
                var lines = string.split('\n');
                for (var line in lines) {
                    while (areWingDashes(lines[line]) || areWingSpaces(lines[line])) {
                        if(areWingDashes(lines[line])) {
                            lines[line] = wingDashRemove(lines[line]);
                        }
                        if (areWingSpaces(lines[line])) {
                            lines[line] = wingSpacesRemove(lines[line]);
                        }
                    }
                }
                return lines.join('\n');
            }

            // Remove dashes from sides. This should be only used if areWingDashes() returns true.
            function wingDashRemove(string) {
                var noWingDashes = string.split('');
                if (noWingDashes[noWingDashes.length-1] === '-') {
                    noWingDashes.pop();
                }
                if (noWingDashes[0] === '-') {
                    noWingDashes.shift();
                }
                return noWingDashes.join('');
            }

            // Return true if there are wingSpaces.
            function areWingDashes(string) {
                if (string.match(/.*-$/gm)) {
                    return true;
                }
                if (string.match(/^-.*/gm)) {
                    return true;
                }
                return false;
            }

            // Remove spaces from sides. This should be only used if areWingSpaces() returns true.
            function wingSpacesRemove(string) {
                var noWingSpaces = string.split('');
                if (noWingSpaces[noWingSpaces.length-1] === ' ') {
                    noWingSpaces.pop();
                }
                if (noWingSpaces[0] === ' ') {
                    noWingSpaces.shift();
                }
                return noWingSpaces.join('');
            }

            // Return true if there are wingSpaces.
            function areWingSpaces(string) {
                if (string.match(/.*\s$/gm)) {
                    return true;
                }
                if (string.match(/^\s.*/gm)) {
                    return true;
                }
                return false;
            }

            // Determine if the input is valid. Sets isValid boolean.
            function isInputValid() {
                if (primaryValueText === primaryPreviewText && secondaryValueText === secondaryPreviewText) {
                    if (Timer) {
                        clearTimeout(Timer);
                    }

                    isValid = true;
                    $('#valid-text-lockup-warning').addClass('warning-hidden');
                    $('#edit-submit').prop('disabled', false);
                }
                else {
                    if (Timer) {
                        clearTimeout(Timer);
                    }

                    Timer = setTimeout(function() {
                        isValid = false;
                        $('#valid-text-lockup-warning').removeClass('warning-hidden');
                        $('#edit-submit').prop('disabled', true);
                    }, 500);
                }
            }

            // Add warning HTML.
            function warningHTML()  {
                let warningHTML = '\
                    <div id="valid-text-lockup-warning" class="warning-hidden">\
                        <h3><i class="fas fa-exclamation"></i>Invalid text</h3>\
                        <div class="warning-body">\
                            <p>\
                                Correct primary unit or sub unit fields to meet the following criteria:\
                            </p>\
                            <ul>\
                                <li>Only the following special characters are allowed:\
                                        <ul><li> : ; . , \\ \/ ( ) | \' \" \u2018 \u2019 \u201C \u201D ` - \u2013 \u2013\u2013</li></ul>\
                                </li>\
                                <li>No spaces are allowed at the beginning or end of lines.</li>\
                                <li>No more than three lines of text each are allowed for primary and sub unit names.</li>\
                            </ul>\
                        </div>\
                    </div>\
                ';
                $('.layout-region-node-main').append(warningHTML);
            }

            // Create a hidden element that is used for measuring and setting the width of the dividing line.
            function calcDivider() {
                // Create the element to measure for stacked divider.
                if (!$('#primary-first-line-measure-stacked').length) {
                    $('.layout-region-node-main').append('<div id="primary-first-line-measure-stacked"></div>');
                }

                // Create the stacked divider.
                if (!$('#stacked-divider').length) {
                    $('.lockup-stacked .lockup-content-inner').before('<div id="stacked-divider"></div>');
                }

                //grab the first line of the Primary unit text and put it in the measurer.
                $('#primary-first-line-measure-stacked').text($('.lockup-stacked .lockup-content-inner .primary-unit').text().split('\n')[0]);
                let divWidth = $('#primary-first-line-measure-stacked').outerWidth();
                let divPos = ($('.lockup-stacked .lockup-content').outerWidth()/2) - (divWidth/2);

                $('#stacked-divider').css({
                    "width": divWidth - 21,
                    "left": divPos + 10
                });
            }

            // Cleans up text with specified acceptable characters.
            // Assistance comes from here: https://stackoverflow.com/questions/13946651/matching-special-characters-and-letters-in-regex.
            function limitCharacters(lockupText) {
                var matchRegex = /[\w :;.,\\\/()|'"‘’“” `\—\-\–\—\n\x84\x93\x94]*/g;
                return lockupText.match(matchRegex).join('');
            }
        }
    };
})(jQuery, Drupal);