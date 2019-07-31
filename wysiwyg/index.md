---
title: "WYSIWYG (What-You-See-Is-What-You-Get) Text Editor"
has_children: false
nav_order: 7
---

# WYSIWYG (What-You-See-Is-What-You-Get) Text Editor

The What-You-See-Is-What-You-Get (WYSIWYG) text editor gives you the ability to format your content and add links/media. This text editor is available on most text areas around the site.

## Basic Formatting

With the WYSIWYG text editor, it is easy to style your text with the bold, italic, strike-through, superscript, subscript options.

![bold](assets/images/strong.png) ![italic](assets/images/i.png)  ![strike-through](assets/images/s.png)  ![superscript](assets/images/sup.png)  ![subscript](assets/images/sub.png) 

Additionally, you can insert special characters, and horizontal rules.

![special characters](assets/images/special-char.png) ![horizontal rule](assets/images/hr.png) 

In most cases, the easiest way to change the text style is to select the text first and then to select one or more of the buttons on the editor toolbar.

These can be used in conjunction with each other or other buttons on the toolbar.

You can quickly remove all formatting from highlighted text using the "Remove Format" button.

![remove format](assets/images/remove-format.png) 

### Styles

Using the "Block Styles" drop-down, you can add paragraph styles within your content. The paragraph style will be applied to the current line/paragraph. Hit Enter/Return to break out and return to to the normal format.

![style](assets/images/styles.png)

### Headings

Using the "Paragraph Format" drop-down, you can add headings within your content. The heading will be applied to the current line/paragraph. Hit Enter/Return to break out and return to the normal format.

![paragraph format](assets/images/paragraph-format.png)

It is important for accessibility and usability that headings are used sequentially and not for style purposes. 

### Alignment

There are three options for text alignment. Left, center and right. Text is left-aligned by default. The alignment will continue until the next line break similar to headings.

![left align](assets/images/left.png) ![center align](assets/images/center.png) ![right align](assets/images/right.png) 

### Lists

There are two types of lists you can create. Unordered (bullet points) and ordered (numbered).

![unordered list](assets/images/ul.png) ![ordered list](assets/images/ol.png) 

To create a list, click on one of the list buttons. This will insert the first list item and you can start typing. For each additional item, press Return/Enter on your keyboard.

If you want to add another line to the same list item, press Shift+Return/Enter.

Additionally you can increase and decrease the indentation of the list.

![increase indentation](assets/images/increase-indent.png) ![decrease indentation](assets/images/decrease-indent.png) 

To break out of the list and continue with other content, press Return/Enter twice.

## Links

Links to internal or external paths can be added with the link button. A separate configuration window will load.

![link](assets/images/a.png) 

You can edit an existing link by placing the cursor on the link and clicking the link button.

Links can also be removed easily with the "Unlink" button.

![remove link](assets/images/a-remove.png) 

### Checking for broken links

Free online tools that can check your website for broken links and links that redirect:

- [https://validator.w3.org/checklink] (to check more than one page, check the box "Check linked documents recursively" and enter a number in the "recursion depth" field)
- [http://www.brokenlinkcheck.com/broken-links.php]

### URL

Add the link's connecting URL. Start typing to find content.

### Title

Add a name that populates the title attribute of the link, usually shown as a small tooltip on hover.

### Upload A File

Allows you to add a file that doesn't already exist on the site. Insert a name and browse to find a file from your computer.

### Advanced

Provides additional, though optional, configuration for the link. Configurations such as, CSS classes, ID, open in a new window button, and Relation (rel).

### Buttons

To quick add a button style to a link create a link and under Add Link > Advanced > CSS classes, select one of the given styles.

#### CSS Classes

List of CSS classes to add to the link, separated by spaces. Alternatively, add one or more of these predefined *button* styles: (Primary, Secondary, Success, Info, Warning, Danger, Small, Large, Full-Width).

#### ID

Allows linking to this content using a *URL fragment*. Must be unique.

## Blockquotes

A blockquote element defines a section of the webpage that is quoted from another source. The blockquote visually indicates that the text is a quote.

![blockquote](assets/images/blockquote.png)

## Tables

A table is a structured set of data made up of of rows and columns (tabular data). A separate configuration window will load.

![table](assets/images/table.png) 

You can set initial row and column counts, designate the headers for the table and provide a table caption.

**Note**: Though not required, a caption is strongly recommended to provide an overall description of the data found in the table.

After initializing the table, you can enter data and/or right-click on the table to add additional rows/columns or edit table properties.

## Media (Images)

The "Media Entity Embed" (Image) button allows you to add new or existing images to your content. A separate configuration window will load.

![Entity media embed](assets/images/media.png) 

When uploading an image, specify a name that can be used later on to find the image in the media library and choose an image.

After upload, provide alternative text for the image to aid visually impaired users.

Optionally, clicking on the image thumbnail allows you to specify the portion of an image that is most important. This information can be used when the image is cropped so that you don't, for example, end up with an image that cuts off the subject's head.

After saving the entity, another window will load allowing you to change the size of the image used, the alignment and allow you to provide a caption that is displayed with the image.

You can read more about media images and files within the [Media section of our documentation](../media/index.md).

## Media (Remote Video)

The "Media Entity Embed" (Remote Video) button allows you to add remote videos to your content. A separate configuration window will load.

When uploading a remote video, add an existing Youtube or Vimeo video URL.

After saving entity, another window will load allowing you to change the display size of the video used, the alignment and allow you to provide a caption that is displayed with the image.

You can read more about media remote videos within the [Media section of our documentation](../media/index.md).

## Editor Options

There are several other buttons available within the WYSIWYG text editor that allow you to control aspects of the editor window itself.

### Show Borders

This button will display borders around container HTML elements like paragraphs and divisions.

![show borders](assets/images/borders.png) 

### Source

You can look under the hood at anytime to see the markup for the content. If you know your stuff you can write your own HTML staying within the restrictions of the filtered_html format.

![View Source](assets/images/source.png) 

You can see the available HTML tags/options on your site at /filter/tips.

HTML that is not allowed will be stripped out before rendering to site visitors.

### Maximize

This button will expand the text editor to use the full screen. This is helpful for distraction free writing, or working with a lot of content at once.

![maximize](assets/images/maximize.png) 

### Allowed Classes

To extend formatting of content within the WYSIWYG, certain HTML classes are allowed on the following elements: a, blockquote, p (text-align-* only), div, table, thead, tbody, tfoot, th, tr and td.

Some of those allowed classes are:

- w-25, w-50, w-75, w-100: Element widths of 25%, 50%, 75% and 100%.
- align-baseline, align-top, align-middle, align-bottom: Vertical alignment of inline and table cell elements.

#### Example Usage

```
<table>
  <thead>
    <tr>
      <th class="w-50">Header Half 01</th>
      <th class="w-50">Header Half 02</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="align-middle">middle</td>
      <td class="align-bottom">bottom</td>
    </tr>
  </tbody>
</table>
```
