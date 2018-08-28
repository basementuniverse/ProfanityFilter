<?php

require_once('ProfanityFilter.php');

use ProfanityFilter\ProfanityFilter;

// TESTS
$tests = [
    [
        'name' => 'Sanitise with default parameters',
        'input' => 'This is a fucking string with some shitty words in it.',
        'test' => function($input) {
            $bw = new ProfanityFilter();
            $a = $bw->sanitise($input);
            return assert($a['output'] == 'This is a string with some words in it.');
        }
    ],
    [
        'name' => 'Detect words using alternative characters',
        'input' => 'shit sh1t shi7 sh17 5hit',
        'test' => function($input) {
            $bw = new ProfanityFilter();
            $a = $bw->check($input);
            return assert(count($a) == 5);
        }
    ],
    [
        'name' => 'Replace bad words with substitute words',
        'input' => 'fuck shit cunt',
        'test' => function($input) {
            $bw = new ProfanityFilter();
            $bw->substitutionFunction = 'substitute-word';
            $a = $bw->sanitise($input);
            return assert($a['output'] == 'fiddlesticks sugar crackers');
        }
    ]
];

// Run tests
echo "Running tests...\n";
for ($i = 0, $length = count($tests); $i < $length; $i++) {
    $test = $tests[$i];
    $result = "Test $i ({$test['name']}) - ";
    if (call_user_func($test['test'], $test['input'])) {
        echo $result . "PASSED\n";
    } else {
        echo $result . "FAILED\n";
        break;
    }
}
echo "Done\n\n";
