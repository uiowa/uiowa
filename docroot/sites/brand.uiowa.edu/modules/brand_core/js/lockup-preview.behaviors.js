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

            // The input fields.
            var primaryUnit = $("#edit-field-lockup-primary-unit-0-value");
            var subUnit = $("#edit-field-lockup-sub-unit-0-value");
            var primaryUnitStacked = $("#edit-field-lockup-p-unit-stacked-0-value");
            var subUnitStacked = $("#edit-field-lockup-s-unit-stacked-0-value");

            // The initial loaded values that they have, if any,
            // And then the stored value from the last stroke of the keyboard.
            var primaryUnitPreviousText = primaryUnit.val();
            var subUnitPreviousText = subUnit.val();
            var primaryUnitStackedPreviousText = primaryUnitStacked.val();
            var subUnitStackedPreviousText = subUnitStacked.val();

            // Reserved for later calculations of the input field text.
            var primaryValueText;
            var secondaryValueText;
            var primaryStackedValueText;
            var secondaryStackedValueText;

            // Reserved for later calculations of the preview text.
            var primaryPreviewText;
            var secondaryPreviewText;
            var primaryStackedPreviewText;
            var secondaryStackedPreviewText;

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
            requiredWarning();

            // Show preview and inject content based on existing input or live input for
            // both the Primary College and Sub College, Horizontal and Stacked.
            if (primaryUnit.val() !== "") {
                $(".lockup-horizontal .primary-unit").text(primaryUnit.val());
            }

            if (subUnit.val() !== "") {
                $(".lockup-horizontal .sub-unit").text(subUnit.val()).css({"margin-top": "7px"});
            }
            if (primaryUnitStacked.val() !== "") {
                $(".lockup-stacked .primary-unit").text(primaryUnitStacked.val());
            }

            if (subUnitStacked.val() !== "") {
                $(".lockup-stacked .sub-unit").text(subUnitStacked.val());
            }

            // Do preliminary placement of the divider.
            calcDivider();

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
            primaryUnitStacked.on("input", function (event) { processInput($(this), event); });
            subUnitStacked.on("input", function (event) { processInput($(this), event); });

            // Set the submit button text whenever the user changes the 'Save as' option.
            $("#edit-moderation-state-0-state").change(function(){
                setSubmitButton($(this).val());
            });

            // Add warning HTML.
            function requiredWarning()  {
                let emptyRequired = '\
                        <div id="required-warning" class="warning-hidden">\
                            <h3><i class="fas fa-exclamation"></i>Required fields are empty</h3>\
                            <div class="warning-body">\
                                <p>\
                                    Make sure you fill out all required fields within each step.\
                                </p>\
                            </div>\
                        </div>\
                    ';
                $('#edit-actions').once().append(emptyRequired);
            }

            // Add a warning in case any required fields are empty
            $('#edit-submit').click(function(){
                $('#required-warning').addClass('warning-hidden');
                if (
                    $('#edit-title-0-value').val() === "" ||
                    $('#edit-field-lockup-org-0-target-id').val() === "" ||
                    primaryUnit.val() === "" ||
                    primaryUnitStacked.val() === ""
                ) {
                    $('#required-warning').removeClass('warning-hidden');
                    Drupal.announce('Required fields are empty.', 'assertive');
                }
            })

            // Add ability to press enter on submit button for keyboard users.
            $('#edit-submit').keypress(function(e){
                if(e.which === 13){
                    $('#edit-submit').click();
                }
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
                // Get dimensions of the block iowa SVG.
                var blockIowa = $('.lockup-stacked img.block-iowa');
                var blockIowaDims = { width : blockIowa.width(), height : blockIowa.height() };

                // Create the element to measure for stacked divider.
                if (!$('#primary-first-line-measure-stacked').length) {
                    $('#lockup-preview').append('<div id="primary-first-line-measure-stacked"></div>');
                }

                // Create the horizontal divider.
                if (!$('#horizontal-divider').length) {
                    $('.lockup-horizontal .lockup-content-inner').before('<div id="horizontal-divider"></div>');
                }

                // Create the stacked divider.
                if (!$('#stacked-divider').length) {
                    $('.lockup-stacked .lockup-content-inner').before('<div id="stacked-divider"></div>');
                }

                // Set values for horizontal divider line.
                var divHeight = Math.floor(Math.max($('.lockup-horizontal .lockup-content-inner').innerHeight() - 8, blockIowaDims.height + 8));
                var divTop;
                (divHeight > blockIowaDims.height + 8) ? divTop = 4 : divTop = -4;

                // Set the horizontal divider dimensions.
                $('#horizontal-divider').css({
                    "height": divHeight,
                    "top": divTop
                });

                // Grab the text from Primary College to calculate width of divider.
                $('#primary-first-line-measure-stacked').text($('.lockup-stacked .lockup-content .lockup-content-inner .primary-unit').text());
                var divWidth = Math.max($('#primary-first-line-measure-stacked').outerWidth() - 14, blockIowaDims.width + 6);
                var divPos = ($('.lockup-stacked .lockup-content').outerWidth()/2) - (divWidth/2);

                // Set the stacked divider dimensions.
                $('#stacked-divider').css({
                    "width": divWidth,
                    "left": divPos
                });
            }

            // A shortened console log for swifter typing.
            function cl(any) {
                console.log(any);
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
                if (primaryValueText === primaryPreviewText                  &&
                    secondaryValueText === secondaryPreviewText              &&
                    primaryStackedValueText === primaryStackedPreviewText    &&
                    secondaryStackedValueText === secondaryStackedPreviewText
                ) {
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
                        Drupal.announce('Invalid imput.', 'assertive');
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

                // Determine orientation.
                var selectorID = selector.attr('id');
                var orientation = selectorID.split('-')[selectorID.split('-').length-3];
                if(orientation == 'unit') {
                    orientation = 'horizontal';
                }

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

                // Sets the character limit and the previous lines.
                var maxChars = 1000000;
                switch (unit) {
                    case 'primary':
                        maxChars = primaryMaxChars;
                        if (orientation == 'horizontal') {
                            prevLines = primaryUnitPreviousText.split('\n');
                        }else if (orientation == 'stacked') {
                            prevLines = primaryUnitStackedPreviousText.split('\n');
                        }
                        break;
                    case 'sub':
                        maxChars = subMaxChars;
                        if (orientation == 'horizontal') {
                            prevLines = subUnitPreviousText.split('\n');
                        }else if (orientation == 'stacked') {
                            prevLines = subUnitPreviousText.split('\n');
                        }
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

               // This is where the characters actually get hard capped.
                if (eventType === 'insertFromPaste') {
                    for (var i = 0; i < lines.length; i++) {
                        lines[i] = lines[i].substring(0, maxChars);
                    }
                }
                else {
                    for (var i = 0; i < lines.length; i++) {
                        if(lines[i].length > maxChars) {

                            // If it is a backspace or delete, concatenate lines.
                            if (eventType == 'deleteContentBackward' || eventType == 'deleteContentForward') {
                                lines[i] = lines[i].substring(0, maxChars);
                            }

                            // Else, just deny the next character.
                            else {
                                lines[i] = prevLines[i];
                                cursorMod = -1;
                            }
                        }
                    }
                }

                // Detects proper place to put the previous lines and then returns a crafted object.
                switch (unit) {
                    case 'primary':
                        if (orientation == 'horizontal') {
                            primaryUnitPreviousText = lines.join('\n');
                            return {
                                text: primaryUnitPreviousText,
                                selectionOffset: cursorMod
                            };
                        } else if (orientation == 'stacked') {
                            primaryUnitStackedPreviousText = lines.join('\n');
                            return {
                                text: primaryUnitStackedPreviousText,
                                selectionOffset: cursorMod
                            };
                        }
                    case 'sub':

                        if (orientation == 'horizontal') {
                            subUnitPreviousText = lines.join('\n');
                            return {
                                text: subUnitPreviousText,
                                selectionOffset: cursorMod
                            };
                        }else if (orientation == 'stacked') {
                            subUnitStackedPreviousText = lines.join('\n');
                            return {
                                text: subUnitStackedPreviousText,
                                selectionOffset: cursorMod
                            };
                        }
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

                // Declare variables and get them ready to be crafted.
                var unit;
                var orientation;
                var unitSelector = selector.attr('id').split('-').splice(3,selector.attr('id').split('-').length-5).join('-');

                // Set the unit.
                if(unitSelector[0] == 'p') { unit = 'primary'; }
                else if(unitSelector[0] == 's') { unit = 'sub'; }

                // Set either Horizontal or Stacked.
                if(unitSelector.split('-')[unitSelector.split('-').length-1] == 'unit') { orientation = 'horizontal'; }
                else if(unitSelector.split('-')[unitSelector.split('-').length-1] == 'stacked') { orientation = 'stacked'; }


                // Sanitize text area, and set the preview text.
                var ValueObj = textareaSanitize(textarea, text, numberOfLines, unit, event.originalEvent.inputType);
                var ValueText = ValueObj.text
                var PreviewText  = previewSanitize(ValueText);

                // After sanitizing and reformatting the text,
                // reset the text in the input box and put the preview text in the preview areas.
                textarea.val(ValueText);
                $(".lockup-" + orientation + " ." + unit + "-unit").text(PreviewText);

                // Determine where to put different data, and then put it there.
                if(unit == 'primary') {
                    if (orientation == 'horizontal') {
                        primaryPreviewText = PreviewText;
                        primaryValueText   = ValueText;
                    }
                    else if (orientation == 'stacked') {
                        primaryStackedPreviewText = PreviewText;
                        primaryStackedValueText   = ValueText;

                    }
                }
                else if(unit == 'sub') {
                    if (orientation == 'horizontal') {
                        secondaryPreviewText = PreviewText;
                        if (PreviewText !='') {
                          $(".lockup-horizontal .sub-unit").css({"margin-top": "7px"});
                        }
                        else {
                          $(".lockup-horizontal .sub-unit").css({"margin-top": "0px"});
                        }
                        secondaryValueText   = ValueText;
                    }
                    else if (orientation == 'stacked') {
                        secondaryStackedPreviewText = PreviewText;
                        secondaryStackedValueText   = ValueText;
                    }
                }

                // Resets cursor position.
                setSelectionRange(
                    textarea[0],
                    cursorSelectionStart + ValueObj.selectionOffset,
                    cursorSelectionEnd + ValueObj.selectionOffset
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
                        $("#edit-submit").prop('value', 'Submit lockup for approval');
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
                                Correct primary unit or subunit fields to meet the following criteria:\
                            </p>\
                            <ul>\
                                <li>Only the following special characters are allowed:\
                                        <ul><li> : ; . , \\ \/ ( ) | \' \" \u2018 \u2019 \u201C \u201D ` - \u2012  \u2013  \u2014  \u2015</li></ul>\
                                </li>\
                                <li>No spaces are allowed at the beginning or end of lines.</li>\
                                <li>No more than three lines of text each are allowed for primary and subunit names.</li>\
                            </ul>\
                        </div>\
                    </div>\
                ';
                $('.lockup-preview-wrapper').append(warningHTML);
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
