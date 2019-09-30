---
title: "UIowa Events"
has_children: false
parent: 'Content Blocks'
nav_order: 1
---
# University of Iowa Events

The ability to display upcoming events from [events.uiowa.edu](//events.uiowa.edu) is available as a content block type. Events are added to the system by going to [content.uiowa.edu](//content.uiowa.edu). Follow the documentation there to get started.

Since this content is pulled from an external source and doesn't actually "live" on the site like an image upload or basic page, it works a bit differently than the other content block types.

When you add an events content block, a configuration form is displayed allowing you to filter events to match your site's or page's criteria. These filters are generated from the same filters available at content.uiowa.edu.

**Note**: Use filters sparingly as events must match the same filters to be displayed.

## Event Feed Settings

### Styles

- **Grid** - This will display events similar to the card content block type and horizontally across three columns.
- **Masonry** - This will display the events like the grid above but will have the "Pintrest" look, eliminating white-space between cards. It will also expand to four columns for large displays.
- **Hide Images, Descriptions** - You can optionally hide images and descriptions that are displayed if the content exists.

### Column Width

Similar to other other content block types you can change the width of the events listing, however, it is recommended to use Full when displaying the events with the Grid or Masonry styles.

## Troubleshooting
- Multiple values within a filter work as an OR. Each filter group works as an AND.

    >_For example, consider selecting 'College of Dentistry' in the unit dropdown with 'Alumni/Friends' and 'Faculty/Staff' selected in the event audience dropdown. That will get events for the unit that are tagged with 'Alumni'Friends' or 'Faculty/Staff' (College of Dentistry AND Alumni/Friends OR College of Dentistry AND Faculty/Staff)._
- Event feed data requests are cached for a certain amount of time, so new events may not display for up to a couple hours.
- Events must be approved/published in the system before they get pulled into the display.



