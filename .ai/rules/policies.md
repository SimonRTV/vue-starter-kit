---
paths:
  - '{app/Actions/Media/**,app/Concerns/HasMediaAttachments.php,app/Http/Controllers/*Media*Controller.php,app/Http/Controllers/PageAttachmentController.php,app/Actions/Pages/DeletePage.php,app/Policies/MediaPolicy.php,config/media.php,config/filesystems.php}'
---

# Policies

## Keep media storage private and authorize every file response
Store originals and thumbnails on the private media disk outside the public web root, including publicly shared files. Public visibility is enforced by the file controllers, never direct storage URLs; publishing requires media.publish. Library access is owner-scoped unless media.manage_all is granted, which does not replace operation permissions. Attachments require both parent update and media view permission. Parent deletion must detach links transactionally; never delete shared files. Refuse media deletion while any attachment exists.
