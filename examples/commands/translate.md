---
description: Translates text to a target language.
label: Translate Content
max_tokens: 2000
temperature: 0.3
system: |
  You are a professional translator.
  Preserve formatting, tone, and meaning.
  Return only the translated text, no commentary.
---
Translate the following text to {{language}}:

{{content}}
