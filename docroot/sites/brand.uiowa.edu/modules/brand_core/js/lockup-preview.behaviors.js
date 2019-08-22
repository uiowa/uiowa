/**
 * @file
 * Lockup Preview.
 */
(function ($, Drupal) {
    Drupal.behaviors.lockupPreview = {
        attach: function () {
            /*
            ----------------------------------------------------------------------
            Initialize necessary values for later use by this javascript file.
            ----------------------------------------------------------------------
            */
            var primaryUnit = $("#edit-field-lockup-primary-unit-0-value");
            var subUnit = $("#edit-field-lockup-sub-unit-0-value");
            var primaryUnitPreviousText = primaryUnit.val();
            var subUnitPreviousText = subUnit.val();
            var primaryValueText;
            var primaryPreviewText;
            var secondaryValueText;
            var secondaryPreviewText;
            var Timer = 0;

            // The maximum number of rows that the textareas are allowed to have.
            var maxRows = 3;

            // The max number of characters per line by primary and sub units.
            var primaryMaxChars = 35;
            var subMaxChars     = 40;

            /*
            ----------------------------------------------------------------------
            Things to be done on initialization of the page.
            ----------------------------------------------------------------------
            */

            // Add warning HTML.
            warningHTML();

            // Do preliminary placement of the divider for the stacked lockup.
            calcDivider();

            // Show preview and inject content based on existing input or live input for both the Primary Unit and Sub Unit.
            if (primaryUnit.val() !== "") {
                $(".lockup-stacked .primary-unit").text(primaryUnit.val());
                $(".lockup-horizontal .primary-unit").text(primaryUnit.val());
                calcDivider();
            }

            if (subUnit.val() !== "") {
                $(".lockup-stacked .sub-unit").text(subUnit.val());
                $(".lockup-horizontal .sub-unit").text(subUnit.val());
            }

            // Set the submit button text based on the 'Save as' option.
            setSubmitButton($("#edit-moderation-state-0-state :selected").val());

            /*
            ----------------------------------------------------------------------
            Attach event handlers to the necessary elements.
            ----------------------------------------------------------------------
            */

            // When either the Primary unit or sub unit are edited, process the input.
            primaryUnit.on("input", function (event) { processInput($(this), event); });
            subUnit.on("input", function (event) { processInput($(this), event); });

            // Set the submit button text whenever the user changes the 'Save as' option.
            $('#edit-moderation-state-0-state').change(function(){
                setSubmitButton($(this).val());
            });

            /*
            ----------------------------------------------------------------------
            Functions begin here and are in alphabetical order.
            ----------------------------------------------------------------------
            */

            // Return true if there are wingDashes.
            function areWingDashes(string) {
                if (string.match(/.*-$/gm)) {
                    return true;
                }
                if (string.match(/^-.*/gm)) {
                    return true;
                }
                return false;
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

            // Determine if the input is valid.
            function isInputValid() {
                if (primaryValueText === primaryPreviewText && secondaryValueText === secondaryPreviewText) {
                    if (Timer) {
                        clearTimeout(Timer);
                    }

                    $('#valid-text-lockup-warning').addClass('warning-hidden');
                    $('#edit-submit').prop('disabled', false);
                }
                else {
                    if (Timer) {
                        clearTimeout(Timer);
                    }

                    Timer = setTimeout(function() {
                        $('#valid-text-lockup-warning').removeClass('warning-hidden');
                        $('#edit-submit').prop('disabled', true);
                    }, 500);
                }
            }

            // Cleans up text with specified acceptable characters.
            // Assistance comes from here: https://stackoverflow.com/questions/13946651/matching-special-characters-and-letters-in-regex.
            function limitCharacters(lockupText) {
                var matchRegex = /[\w :;.,\\\/()|'"‘’“” `\—\-\–\—\‒\–\—\―\n]*/g;
                return lockupText.match(matchRegex).join('');
            }

            // Creates a limit on newlines and deletes any after the limit has been reached.
            // Also limits the number of characters per line.
            function limitLines(selector, nLines, string, unit, eventType) {
                var modText = string;
                var prevLines = [];

                // Limits newlines.
                if (nLines > maxRows) {
                    var toRemove = nLines-maxRows;
                    modText = string.split('').reverse();
                    while (toRemove > 0) {
                        modText.splice(modText.indexOf('\n'), 1);
                        toRemove--;
                    }
                    modText = modText.reverse().join('');
                }

                // Limits characters.
                var maxChars = 1000000;
                switch (unit) {
                    case 'primary':
                        maxChars = primaryMaxChars;
                        prevLines = primaryUnitPreviousText.split('\n');
                        break;
                    case 'sub':
                        maxChars = subMaxChars;
                        prevLines = subUnitPreviousText.split('\n');
                        break;
                }

                /*  This is a little complicated, but it is essentially taking in to account whether the 
                    user pastes or types something in to the box. Upon paste it needs to be trimmed, but
                    we dont want to simply trim things when it comes to typing, because that causes some 
                    odd interactions. we store the previous lines of the current textbox so that we can
                    just take that instead of any new characters as to not cause confusion for the user.
                    The UX is better if they feel like they just cant add any more characters than if the 
                    stuff they are writing is pushing other text off the end of the line to be trimmed.
                */
               var lines = modText.split('\n');
               var cursorMod = 0;

                if (eventType === 'insertFromPaste') {
                    for (var i = 0; i < lines.length; i++) {
                        lines[i] = lines[i].substring(0, maxChars);
                    }
                }
                else {
                    for (var i = 0; i < lines.length; i++) {
                        if(lines[i].length > maxChars) {
                            lines[i] = prevLines[i];
                            cursorMod = -1;
                        }
                    }
                }
                        
                switch (unit) {
                    case 'primary':
                        primaryUnitPreviousText = lines.join('\n');
                        return {
                            text: primaryUnitPreviousText,
                            selectionOffset: cursorMod
                        };
                    case 'sub':
                        subUnitPreviousText = lines.join('\n');
                        return {
                            text: subUnitPreviousText,
                            selectionOffset: cursorMod
                        };
                }
            }
            
            // Sanitizes text for the Preview.
            function previewSanitize(text) {
                var sanitizedText;
                // Remove all non quote, space, or dash special characters.
                sanitizedText = limitCharacters(text);
                // Remove spaces and dashes from sides of lines.
                sanitizedText = wingCharacterRemove(sanitizedText);
                return (sanitizedText);
            }

            // Processes input when it is put in to a lockup textbox.
            function processInput(selector, event) {
                var textarea = selector,
                    text = textarea.val(),
                    numberOfLines = (text.match(/\n/g) || []).length + 1;

                var cursorSelectionStart = textarea[0].selectionStart;
                var cursorSelectionEnd = textarea[0].selectionEnd;

                var primaryValueTextObj = textareaSanitize(textarea, text, numberOfLines, 'primary', event.originalEvent.inputType);
                primaryValueText = primaryValueTextObj.text
                primaryPreviewText  = previewSanitize(primaryValueText);

                textarea.val(primaryValueText);
                $(".lockup-stacked .primary-unit").text(primaryPreviewText);
                $(".lockup-horizontal .primary-unit").text(primaryPreviewText);

                // Resets cursor position.             
                setSelectionRange(
                    textarea[0],
                    cursorSelectionStart + primaryValueTextObj.selectionOffset,
                    cursorSelectionEnd + primaryValueTextObj.selectionOffset
                );

                // Set the divider line width.
                calcDivider();

                // Check if input is valid.
                isInputValid();
            }

            // This function manages the position of the cursor and/or the highlighted selection.
            // This is used to reset the cursor and/or selection position after rewriting the input box.
            function setSelectionRange(input, selectionStart, selectionEnd) {
                if (input.setSelectionRange) {
                  input.focus();
                  input.setSelectionRange(selectionStart, selectionEnd);
                } else if (input.createTextRange) {
                  var range = input.createTextRange();
                  range.collapse(true);
                  range.moveEnd('character', selectionEnd);
                  range.moveStart('character', selectionStart);
                  range.select();
                }
            }

            // Set the submit button text based on the option selected.
            function setSubmitButton(target) {
                switch (target) {
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
            }

            // Sanitizes text for the textarea.
            function textareaSanitize(selector, text, numberOfLines, unit, eventType) {
                var sanitizedText;
                // Change all dumb quotes to smart quotes.
                sanitizedText = dumbQuotes(text);
                // Redundantly limit any newlines and prevent pasting more than the max number of lines.
                sanitizedTextObj = limitLines(selector, numberOfLines , sanitizedText, unit, eventType);
                return (sanitizedTextObj);
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
                                        <ul><li> : ; . , \\ \/ ( ) | \' \" \u2018 \u2019 \u201C \u201D ` - \u2012  \u2013  \u2014  \u2015</li></ul>\
                                </li>\
                                <li>No spaces are allowed at the beginning or end of lines.</li>\
                                <li>No more than three lines of text each are allowed for primary and sub unit names.</li>\
                            </ul>\
                        </div>\
                    </div>\
                ';
                $('.layout-region-node-main').append(warningHTML);
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
        }
    };
})(jQuery, Drupal);