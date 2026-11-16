# SiteNow Landing Pages

Adds a "Landing page" toggle to basic pages and ships with the
config split, `landing_pages`. A landing page strips the site
chrome down to the page content and a minimal footer, so a
marketing or campaign page reads on its own without the
surrounding site navigation.

## What the toggle changes

Everything below is removed or altered intentionally. This list is the record
of intent. If one of these reappears, it's a regression, not a feature.

### Search engines
- Robots meta tag set to `noindex, follow`, so the page isn't indexed.
- Page is left out of the XML sitemap.

### Header
- Site search hidden.
- Main navigation menu hidden.
- Top links (gold brand bar links) hidden.
- Parent organization name hidden; header lays out as if the site has no parent.
- Site name hidden, unless "Show site name" is checked. When hidden, the
  header uses the single-bar inline layout.

### Page content
- Breadcrumbs hidden, including a breadcrumb block placed in the page's layout.
- Hawk Alerts and custom site alerts are hidden. (Hiding Hawk Alerts on
  campaign landing pages is approved by Campus Safety; see issue
  https://github.com/uiowa/uiowa/issues/10132#issuecomment-5821799022.)
- The main navigation menu block is removed from the block region.

### Footer
- Only the copyright line and the Privacy Notice, Nondiscrimination Statement,
  and Accessibility links remain (the "socket").
- Footer logo, site name, contact info, social icons, footer menus, and the Admin
  Login link are hidden.

