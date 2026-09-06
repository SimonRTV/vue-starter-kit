---
paths:
  - '{app/Actions/Pages/**,app/Http/Requests/*PageRequest.php,app/Http/Controllers/PageController.php,app/Http/Controllers/PublicPageController.php,resources/js/components/RichTextEditor.vue,resources/js/components/pages/PageForm.vue,resources/js/pages/pages/Show.vue,resources/js/pages/content/Show.vue}'
---

# Pages Content

## Keep rich page content sanitized and legacy text explicit
Page body_format defaults to text, preserving literal markup and line breaks in existing pages. The Tiptap editor submits HTML with body_format=html. PageContent sanitizes HTML on create/update and again for the body_html response; render only that safe field with v-html, never raw body. Keep the sanitizer allowlist, editor extensions, and shared rich-content typography aligned. Exclude scripts, embeds, images, arbitrary styles/classes and unsafe link schemes; media remains attached through the separate attachment flow.
