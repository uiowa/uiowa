---
title: "Known Issues"
has_children: false
nav_order: 17
---

# Known Issues

- Sometimes content can get saved and immediately result in an "...unexpected error." This could be caused by any number of issues, but if the error is just occuring on one page of the site, an editor can first attempt to resolve this issue by re-saving the content. If that doesn't work, a webmaster can attempt to revert back to a working version of the content. Find the affected content via admin/content and edit. Click on the revision tab and click to view previous revision, if any, to find the last working version. Go back to the revisions overview and revert to that working version.

- **UPDATE**: This issue can no longer be replicated. ~~There are issues with embedding content within the [WYSIWYG text editor](../wysiwyg/index.md) with certain versions of Chrome. We recommend using another browser if you encounter any of the following:~~
  - ~~After embedding a file into the content area, you can't type or advance the cursor from the file focus.~~
  - ~~Tabbing or arrow keys also do not work.~~
  - ~~Pasting text into the editor, pastes over the embedded file link.~~
  - ~~The embed media window doesn't close after reopening.~~

- #139: Table resize doesn't work and that allowed classes (wysiwyg) is a workaround.

- #194: Photos taken from an iPhone with orientation data get rotated incorrectly and that saving the image in the right orientation before upload is the workaround.

- #128: Error when selecting multiple images from entity browser in WYSIWYG.
  - Don't try to select more than one image at a time. No workaround for now. Image fields currently only allow one item anyway. wysiwyg is where folks could expect the ability to select multiple.