<?php
namespace dirtsimple\yaml\tests;

use dirtsimple\CleanYaml;
use Symfony\Component\Yaml\Yaml as SYaml;
use Symfony\Component\Yaml\Tag\TaggedValue;

function extract_suite($mdfile) {
	describe("$mdfile examples", function() use($mdfile) {
		$matches = array();
		preg_match_all(
			'/(^|.*?\n) ```yaml (?: \s+(\d+) (?: \s+(\d+))? )?\n (.*?)```/sx',
			file_get_contents($mdfile), $matches, PREG_SET_ORDER
		);
		$line_base = 1;
		$count = 1;
		foreach ($matches as $m) {
			$line_no = $line_base + substr_count($m[1], "\n");
			$line_base += substr_count($m[0], "\n");
			$w = $m[2] ?: 120;
			$i = $m[3] ?: 2;
			$src = $m[4];
			it("#$count at line $line_no ($w:$i)", function() use($src, $w, $i){
				$data = SYaml::parse($src, SYaml::PARSE_DATETIME | SYaml::PARSE_CUSTOM_TAGS);
				expect(CleanYaml::dump($data,$w,$i))->to->equal($src);
			});
			$count++;
		}
	});
}

describe("YAML dumper", function(){
	it("inlines empty stdClass and ArrayObject instances", function(){
		$std = (object) array();
		expect(CleanYaml::dump(array('x'=>$std)))->to->equal("x: {  }\n");
		$std = new \ArrayObject;
		expect(CleanYaml::dump(array('x'=>$std)))->to->equal("x: {  }\n");
	});
	it("sees tagged leaf values as leaves");
	it("sees tagged non-leaf values as non-leaves");
});

extract_suite('README.md');
extract_suite('specs/Specs.md');