# Diff-Friendly, Readable YAML Output

While Symfony's yaml component has a terrific parser and mostly-spec-compliant dumper, there are times when you really need your YAML output to be readable by a human being, and produce clean diffs when changed.  For example, both [Postmark](https://github.com/dirtsimple/postmark/) and [Imposer](https://github.com/dirtsimple/imposer/) generate YAML output from arbitrary WordPress data, and their diffs are an important part of both revision control and configuration management.

While JSON (and Symfony's JSON-like output) *can* be diffed to some extent, the diffs tend to be "noisy", filled with extraneous punctuation changes and overly-long lines, especially when strings contain multiline text or HTML content.

So this library provides a replacement for Symfony's YAML dumper, with a different outlining algorithm that prioritizes diffability and readability, while still producing spec-compliant output.  Specifically it:

* Only inlines data structures that can fit on the current line within a specified line length
* Doesn't inline more than one level of nested data structure, unless all the children are empty (e.g. `[]` and `{}`)
* Splits strings containing line feeds into multi-line output, with *correct chomping operators* (Symfony's `DUMP_MULTI_LINE_LITERAL_BLOCK` flag only works correctly for strings that end in *exactly one* `\n` -- no more, no less.)

Following these rules produces output that is still spec-compliant, but which avoids diffing large lines of inlined code, except in those cases where long lines are actually part of the data in question.  (The trade-off is that the outputs produced are invariably larger in both number of lines and total file size, since fewer things are condensed into single lines, and thus more indentation is produced.)

To use this library, require `dirtsimple/clean-yaml` and `use dirtsimple\CleanYaml;`, then `CleanYaml::dump($data, $width=120, $indent=2)`.

Unlike Symfony, there are no flags to control the output: the library's behavior is roughly equivalent to using Symfony's `DUMP_EXCEPTION_ON_INVALID_TYPE | DUMP_OBJECT_AS_MAP | DUMP_MULTI_LINE_LITERAL_BLOCK | DUMP_EMPTY_ARRAY_AS_SEQUENCE`, except that various bugs in the last two flags' behavior are fixed.  (Symfony's output for literal blocks lacks proper chomp settings, and it sometimes dumps nested empty arrays as objects even though you've asked it not to.)

The `$width` setting is the number of characters wide you'd like the output to be.  Data structures will be inlined if they can fit within this space, including the current indentation and key, if any.  Lines will still exceed this size for strings too long or wide to fit the space, or if indentation gets too deep.  The `$indent` is the number of spaces by which each nesting level will indent.

Here's some sample output with the default settings:

```yaml
-
  id: 5509c1f
  elType: widget
  settings:
    title: 'Free download: No credit card required'
    tags: [ these, are, random, tags, that, fit, 'on', this, line ]
    text: |-
      Here is some indented text that is on multiple lines.  It doesn't rewrap
      because the line feeds are as originally given, and clean-yaml doesn't
      do folding, because that would introduce additional noise into diffs that
      didn't directly come from changes to the underlying data.  The last line
      doesn't end with a `\n`, so the `|-` chomp operator is used.
    align: center
    typography_typography: custom
    typography_font_weight: bold
    background_color: '#23a455'
    border_radius: { unit: px, top: '18', right: '18', bottom: '18', left: '18', isLinked: true }
  elements: []
  timestamp: 2016-05-27T00:00:00+00:00
  not_a_timestamp: '2016-05-27T00:00:00+00:00'
```

And now the same data, but with a narrower width (40), and wider indent (4):

```yaml 40 4
-
    id: 5509c1f
    elType: widget
    settings:
        title: 'Free download: No credit card required'
        tags:
            - these
            - are
            - random
            - tags
            - that
            - fit
            - 'on'
            - this
            - line
        text: |-
            Here is some indented text that is on multiple lines.  It doesn't rewrap
            because the line feeds are as originally given, and clean-yaml doesn't
            do folding, because that would introduce additional noise into diffs that
            didn't directly come from changes to the underlying data.  The last line
            doesn't end with a `\n`, so the `|-` chomp operator is used.
        align: center
        typography_typography: custom
        typography_font_weight: bold
        background_color: '#23a455'
        border_radius:
            unit: px
            top: '18'
            right: '18'
            bottom: '18'
            left: '18'
            isLinked: true
    elements: []
    timestamp: 2016-05-27T00:00:00+00:00
    not_a_timestamp: '2016-05-27T00:00:00+00:00'
```

