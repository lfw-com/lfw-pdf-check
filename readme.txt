=== LFW PDF Check ===
Contributors: lfw
Tags: pdf, accessibility, media, wcag, screen reader
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Checks every uploaded PDF for tags, a document language and a title, and flags the ones a screen reader will struggle with, right in the Media Library.

== Description ==

Most inaccessible PDFs on public websites fail in the same few ways: they were printed to PDF without tags, or scanned, so a screen reader has no structure to follow; and they have no document language or title. This plugin checks for those at the moment a PDF is uploaded, when fixing it is cheapest.

For every PDF added to the Media Library it records:

* whether the file is tagged (has a structure tree)
* whether a document language is set
* whether the file has a title
* whether it contains text at all (a PDF with no fonts is usually a scanned image)

The result appears in the attachment details (in the media modal and on the edit screen) and in a "PDF check" column in the Media Library list. `wp lfw-pdf-check` checks PDFs that were already in the library.

The check runs on your own site. The file is never sent anywhere.

= What it does not do =

It does not prove a PDF is accessible. A tagged PDF can still have a wrong reading order, missing image descriptions or unlabeled tables, and those need a person to review. It catches the most common failure early; it does not replace testing.

== Installation ==

1. Upload the `lfw-pdf-check` folder to `/wp-content/plugins/`, or install the zip from Plugins > Add New.
2. Activate it. New PDF uploads are checked from then on.
3. Optional: run `wp lfw-pdf-check` to check the PDFs already in your library.

== Changelog ==

= 1.0.0 =
* First release.
