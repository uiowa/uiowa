# Collegiate

Collegiate is a Drupal 8+ profile forked from [Acquia Lightning][github]. It is intended for building new Drupal-based websites at the University of Iowa.

## Installing Collegiate

Documentation coming soon.

#### Installing from exported config
Collegiate can be installed from a set of exported configuration (e.g., using the
--existing-config option with `drush site:install`).

## Features

### Media
The current version of media includes the following functionality:

* A preconfigured Text Format (Rich Text) with CKEditor WYSIWYG.
* A media button (indicated by a star -- for now) within the WYSIWYG that
  launches a custom media widget.
* The ability to place media into the text area and have it fully embedded as it
  will appear in the live entity. The following media types are currently
  supported:
  * Tweets
  * Instagram posts
  * Videos (YouTube and Vimeo supported out of the box)
  * Images
* Drag-and-drop bulk image uploads.
* Image cropping.
* Ability to create new media through the media library (/media/add)
* Ability to embed tweets, Instagrams, and YouTube/Vimeo videos directly into
  CKEditor by pasting the video URL 

### Layout
Lightning includes a Landing Page content type which allows editors to create
and place discrete blocks of content in any order and layout they wish using an
intuitive, accessible interface. Lightning also allows site builders to define
default layouts for content types using the same interface - or define multiple
layouts and allow editors to choose which one to use for each post.

### Workflow
Lightning includes tools for building organization-specific content workflows.
Out of the box, Lightning gives you the ability to manage content in one of four
workflow states (draft, needs review, published, and archived). You can create
as many additional states as you like and define transitions between them. It's
also possible to schedule content to be transitioned between states at a
specific future date and time.

### API-First
Lightning ships with several modules which, together, quickly set up Drupal to
deliver data to decoupled applications via a standardized API. By default,
Lightning installs the OpenAPI and JSON:API modules, plus the Simple OAuth
module, as a toolkit for authentication, authorization, and delivery of data
to API consumers. Currently, Lightning includes no default configuration for
any of these modules, because it does not make any assumptions about how the
API data will be consumed, but we might add support for standard use cases as
they present themselves.

If you have PHP's OpenSSL extension enabled, Lightning can automatically create
an asymmetric key pair for use with OAuth.

## Known Issues

### Media
* If you upload an image into an image field using the new image browser, you
  can set the image's alt text at upload time, but that text will not be
  replicated to the image field. This is due to a limitation of Entity Browser's
  API.
* Some of the Lightning contributed media modules listed above might not yet be
  compatible with the Core Media entity.
* Using the bulk upload feature in environments with a load balancer might
  result in some images not being saved.

### Local Development
Documentation coming soon.

[github]: https://github.com/acquia/lightning "GitHub clone"
