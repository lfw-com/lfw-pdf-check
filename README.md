# LFW PDF Check

A small WordPress plugin that checks every uploaded PDF for tags, a document language and a title, and flags the ones a screen reader will struggle with in the Media Library. Free and open source (GPL-2.0-or-later), from [LFW](https://lfw.com).

- Runs on your own site; the file is sent nowhere.
- Results in the attachment details and a "PDF check" column in the Media Library.
- `wp lfw-pdf-check` checks PDFs already in the library.

It catches the most common PDF accessibility failure (an untagged export or a scan) at upload. It does not prove a PDF is accessible; reading order, image descriptions and tables still need a person.

`test.sh` runs the plugin in a throwaway WordPress in Docker against the two sample PDFs in `testpdfs/`.
