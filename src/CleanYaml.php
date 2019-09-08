<?php
namespace dirtsimple;

use Symfony\Component\Yaml\Yaml as Yaml;
use Symfony\Component\Yaml\Inline;

class CleanYaml {
	const
		ROOT_FLAGS = Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE,
		DUMP_FLAGS = Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE |
		             Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE;

	static function dump($data, $width=120, $indent=2) {
		return static::_dump($data, $width, str_repeat(' ',$indent));
	}

	# Like Symfony's dump, but w/fixes for block chomping on multiline literals,
	# and nested data structures are only inlined if they can fit on the current
	# line without wrapping, and have no non-empty containers as children.
	#
	# The overall goal is to optimize for revision control, such that diffs
	# don't contain extraneous changes, and line breaks follow the source data
	# (allowing in-line change highlighting where tooling allows).  For this
	# reason, we don't do line folding, even though it would improve readability,
	# because even a small change to such a string could rewrap the rest of the
	# string, polluting the diff with semantically-irrelevant changes.
	#
	protected static function _dump($data, $width=120, $indent='  ', $prefix='', $key=null) {

		# $key is null at the root of a dump, but for child nodes it's either
		# "somekey:" (if the parent is a map) or "" (if a list).  The return
		# value for child calls is either "$key $val" for inlined values,
		# or "$key\n$val" (with $val indented and ending with \n) if the
		# value can't be inlined.  The parent data structure is then inlined
		# if all the children were inlined and they fit within the available
		# width.  (Otherwise, the return values are indented and given newlines
		# if needed.)  This dynamic approach produces a more readable result
		# than simply inlining at a fixed depth, but still avoids backtracking
		# or duplication of effort: every key and scalar value is rendered
		# exactly once, then joined with commas or newlines and/or prefixed
		# afterwards.

		# See if $data can be rendered as a simple (leaf) value
		if ( \is_string($data) ) {
			# Check for multi-line literal compatibility
			if (
				( $lines = \substr_count($data, "\n") ) && # multi-line
				false === \strpos($data, "\r\n") &&        # no lossy CRLFs
				($lines > 1 || \strlen($data) > $width - \strlen($key)) # two LFs or too wide
				&& isset($key)  # not at the root
			) {
				# If the string starts with a space, explicit indent depth is needed
				$indicator = \substr_compare($data, ' ', 0, 1) ? '': \strlen($indent);

				if ( \substr_compare($data, "\n", -1) ) {
					# String doesn't end in \n, it needs one
					$data .= "\n";
					$indicator .= '-';  # tell parser to strip the \n we added
				} else if ( ! \substr_compare($data, "\n\n", -2) ) {
					# String ends in multiple \n, tell parser to keep them
					$indicator .= '+';
				}

				if ( isset($key) ) $key .= ' '; # separator after '-' or ':'

				# Add $prefix to the start of every line in the string
				return "$key|$indicator\n" . \preg_replace('/^/m', "$prefix", $data);
			}

			# if we get here, the string wasn't suitable for a literal block,
			# so we intentionally fall through to the misc. scalar handling
			# at the bottom of the function

		} elseif (
			( \is_array($data) && ! empty($data) ) ||
			( \is_object($data) &&
				( $data instanceof \stdClass || $data instanceof \ArrayObject ) &&
				! empty((array)$data)
			)
		) {
			# Not a leaf, it's a non-empty array (or array-like object)
			$out = array();  # collect 'key: val' or ' val' strings

			# Room left on the current line if inlining (inlcudes parent indent
			# since if we fit, we will be on a less-indented line)
			$room = $width - \strlen($key) + \strlen($indent);
			$width -= \strlen($indent);     # width available for indented children
			$nested = "$prefix$indent";     # prefix string for children

			if ( Inline::isHash($data) ) {
				# Map: render "key: val" pairs to be joined w/", " or "\n"
				foreach ($data as $k => $v) {
					$k = Inline::dump($k, self::DUMP_FLAGS);
					$out[] = $v = static::_dump($v, $width, $indent, $nested, "$k:");
					# Track room, or drop to 0 if result ends with LF (can't inline if a value is multiline)
					$room = \substr_compare($v, "\n", -1) ? $room - \strlen($v) - 2: 0;
				}
				$k = "%s { %s }"; $v = ', ';
			} else {
				# List: render " val" strings, to be joined w/ "," or "\n-"
				foreach ($data as $v) {
					$out[] = $v = static::_dump($v, $width, $indent, $nested, '');
					# Track room, or drop to 0 if result ends with LF (can't inline if a value is multiline)
					$room = \substr_compare($v, "\n", -1) ? $room - \strlen($v) - 1: 0;
				}
				$prefix .= '-';  # Add a '-' in front of items if we can't render inline
				$k = "%s [%s ]"; $v = ',';  # items already have a leading space
			}

			# If there's room and this is not a root rendering, join with commas
			if ( $room >= 3 && isset($key) ) {  # allow room for [ ] / { }
				return \sprintf( $k, $key, \implode($v, $out) );
			}

			# Otherwise, add a LF to the end of any entries that don't have them
			$out = \preg_replace('/([^\n])$/D', "\\1\n", $out);

			# And prefix them all, optionally with the parent "-" or "key:" and a newline
			return (isset($key) ? "$key\n" : '') . $prefix . \implode($prefix, $out);
		}

		# It's a leaf value - just inline it
		if ( isset($key) ) {
			# Recursive call: return everything on one line
			return $key . " " . Inline::dump($data, self::DUMP_FLAGS);
		} else {
			# Root call: include prefix and trailing LF
			return $prefix . Inline::dump($data, self::ROOT_FLAGS) . "\n";
		}
	}
}