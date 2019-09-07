## YAML Samples

### One-Liners

Top-level leaf values (including an empty map) are inlined with a terminating LF:

```yaml
xyz
```

```yaml
null
```

```yaml
{  }
```

```yaml
2016-05-27T00:00:00+00:00
```

### Text Chomping And Indents

```yaml 30
- |
  This string ends with a '\n'
```

```yaml 30
- |2
   This string ends with a '\n' and starts with a space.
```

```yaml 30 4
- |4
      This string also ends with a '\n' and starts with two
    spaces, but has a different indent width.
```

```yaml 50
- |-
  This string does NOT end with
  a '\n', but it does contain one
```

```yaml 50
- |2-
    This string does NOT end with
  a '\n', but it does contain one
```

```yaml
- |+
  This string ends with two '\n' characters
  
```

```yaml
- |2+
   And so does this one.
  
```

### Simple structures w/inlining


```yaml
a: []
b: { c: d }
```

### To-Do

* Tagged values (leaf and non-leaf)
* Correct inline structure fits (list and map)
