brand.uiowa.edu
===
![GitHub issues by-label](https://img.shields.io/github/issues/uiowa/uiowa/brand.uiowa.edu)

Overview
---

Primary website for University of Iowa Human Resources.

Primary Developer
---

[jwhitsit](https://github.com/joewhitsitt)

Primary Customer Contact
---

jcorliss

Customizations
---

- Collaborator role
- Custom authentication rules (allows any staff, faculty, student to authenticate to the collaborator role)
- Lockup content type
  - Collaborators can create new lockups and transition them to needs review or save to draft.
  - The lockup node form has been customized
    - javascript/css has been added to provide a live preview with live validation on input.
    - latest moderation logs are added
  - Upon approval/publish, SVGs and a zip file are created (with instructions doc) as unmanaged files within the /lockups files directory.
    - easysvg - https://github.com/kartsims/easysvg for svg generation
  - /lockup-system view page.
  - lockup moderation view.
  - Organizations taxonomy
  - Workbench Email sends email to content author upon moderation approval/publish.
    - moderation notes, if any are added to the email.
    - Uses custom token to provide moderation log value prefix, formatting.
  - 7 AM email is sent out for any needs review lockup submissions.
    - acquia cron job/drush command.
- Custom moderation workflow for lockup content type.
- mailsystem, swiftmailer modules for HTML email.
- Custom permissions based on all of the above.

Misc Notes
---


Lastest Development Round
---

_Currently in development_

Original Launch Date
---





