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
  
- Inline elements do not display correctly within the WYSIWYG text editor resulting in stacked elements, however if aligned (e.g. image setting), the element should display correctly in preview and on content display.

- There are issues concerning with CKEditor'sTableresize. The tableresize (dragging width/height of table/cells) works in the editor but is stripped with filtered_html because it uses the style attribute which is a known XSS vulnerablity.
  - Allowed classes in *wysiwyg* is the workaround.

- There is an error when photos taken from an iPhone with orientation data get rotated incorrectly.
  - Saving the image in the right orientation before upload is the workaround.

- There is an error when selecting two or more images from entity browser in WYSIWYG.
  - To avoid this, don't try to select more than one image at a time. No workaround for now.
  
- There is a known issue that when you add an item to a details container or flexbox container in webform that the 'add element' button on the container will dissapear.
  - Workarounds for this are clicking edit on the container and then closing the edit panel, or by simply creating the element on the top level and dragging it underneath the desired container.
  
- For the the People Manual Sort option, Drag handles are the only way to order people. "Show row weights" does not show the weight fields with integer fallback sorting. Additionally, new people are added to the top of the list instead of the bottom.
