---
title: "Known Issues"
has_children: false
nav_order: 17
---

# Known Issues

- There are issues with embedding content within the [WYSIWYG text editor](../wysiwyg/index.md) with certain versions of Chrome. We recommend using another browser if you encounter any of the following:
  - After embedding a file into the content area, you can't type or advance the cursor from the file focus.
  - Tabbing or arrow keys also do not work.
  - Pasting text into the editor, pastes over the embedded file link.
  - The embed media window doesn't close after reopening.

- #139: Table resize doesn't work and that allowed classes (wysiwyg) is a workaround.

- #194: Photos taken from an iPhone with orientation data get rotated incorrectly and that saving the image in the right orientation before upload is the workaround.

- #128: Error when selecting multiple images from entity browser in WYSIWYG.
  - Don't try to select more than one image at a time. No workaround for now. Image fields currently only allow one item anyway. wysiwyg is where folks could expect the ability to select multiple.