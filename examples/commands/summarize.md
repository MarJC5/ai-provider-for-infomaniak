---
description: Generates a concise summary of the given content.
label: Summarize Content
temperature: 0.5
max_tokens: 500
system: |
  You are a professional content editor.
  Write clear, concise text that captures the main points.
  Use the same language as the source content.
---
Summarize the following content:

{{content}}

Requirements:
- Maximum {{max_sentences}} sentences.
- Focus on the main points.
- Preserve the original tone.
