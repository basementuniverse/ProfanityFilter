<?php

namespace ProfanityFilter;

class ProfanityFilter {

    /**
     * Bad words that will be replaced or removed
     * @var array
     */
    public $badWords = [
        'provide',
        'your',
        'own',
        'bad',
        'words'
    ];

    /**
     * Good words that can be used to replace bad words
     * @var array
     */
    public $replacementWords = [
        'fiddlesticks',
        'sugar',
        'crackers',
        'bleep',
        'wibble'
    ];

    /**
     * Characters that can be used to replace bad words
     * @var string
     */
    public $replacementCharacters = '#!?$&*%@';

    /**
     * A list of character substitutions that might occur in bad words
     * @var array
     */
    public $alternativeCharacters = [
        'a' => ['4'],
        'e' => ['3'],
        'i' => ['1', '!', 'l'],
        'l' => ['1', '!', 'i'],
        'o' => ['0', 'oo'],
        's' => ['5', '$'],
        't' => ['7'],
        'x' => ['*', 'ks'],
        'z' => ['2']
    ];

    /**
     * A list of possible bad word prefixes
     * @var array
     */
    public $prefixes = [];

    /**
     * A list of possible bad word postfixes
     * @var array
     */
    public $postfixes = [
        'e',
        'er',
        'ing',
        'y',
        'ty',
        'py',
        'head',
        'face'
    ];

    /**
     * Check for alternative character substitutions
     * @var bool
     */
    public $useAlternativeCharacters = true;

    /**
     * Check for possible prefixes
     * @var bool
     */
    public $usePrefixes = true;

    /**
     * Check for possible postfixes
     * @var bool
     */
    public $usePostfixes = true;

    /**
     * Check for bad words surrounded by spaces or string start/end
     * @var bool
     */
    public $useWordBoundaries = true;

    /**
     * Check case when comparing strings
     * @var bool
     */
    public $caseSensitive = false;

    /**
     * The function to use when substituting good words for bad words
     * This should be the name of a built-in static function or a callable function
     * The function should take a ProfanityFilter instance and a result object as arguments
     * A result object is an element of the array returned by $this->check()
     * @var string|callable
     */
    public $substitutionFunction = 'empty';

    /**
     * Collapse any resulting double spaces down to a single space in the output
     * Only applies when using a substitution function that returns an empty string
     * @var bool
     */
    public $collapseDoubleSpaces = true;

    /**
     * List of substitution function names and associations
     * @var array
     */
    private static $substitutionFunctions = [
        'ignore'                => 'ignore',
        'empty'                 => 'empty',
        'random-symbols'        => 'randomSymbols',
        'fixed-symbols'         => 'fixedSymbols',
        'fixed-random-symbols'  => 'fixedRandomSymbols',
        'substitute-word'       => 'substituteWord',
        'random-word'           => 'randomWord',
        'fixed-word'            => 'fixedWord'
    ];

    /**
     * Check if there are any bad words in the input
     *
     * @param string $input The input string
     * @return object|false Information about the bad words found, or false if the string is clean
     */
    public function check($input) {
        $results = [];

        // Create regex for each bad word and find all instances with offsets
        foreach ($this->badWords as $badWord) {
            $matches = [];
            preg_match_all($this->createRegex($badWord), $input, $matches, PREG_OFFSET_CAPTURE);
            $results = array_merge($results, array_map(function($m) use ($badWord) {
                return [
                    'base_word' => $badWord,
                    'word' => $m[0],
                    'offset' => $m[1]
                ];
            }, $matches['word']));
        }
        usort($results, function($a, $b) { return $a['offset'] - $b['offset']; });
        return empty($results) ? false : $results;
    }

    /**
     * Remove or replace bad words in the input and return the sanitised output
     *
     * @param string $input The input string
     * @return object The sanitised output and information about the bad words found
     */
    public function sanitise($input) {
        $results = $this->check($input);
        if ($results === false) {
            return [
                'clean'         => true,
                'bad_words'     => [],
                'output'        => $input,
                'total_length'  => strlen($input),
                'bad_length'    => 0
            ];
        }

        // Prepare the substitution function
        $sub = null;
        if (is_callable($this->substitutionFunction)) {
            $sub = $this->substitutionFunction;
        } else {

            // Substitution function should default to 'empty'
            if (!array_key_exists($this->substitutionFunction, self::$substitutionFunctions)) {
                $this->substitutionFunction = 'empty';
            }
            $sub = function(...$args) {
                return call_user_func(
                    array(__CLASS__, self::$substitutionFunctions[$this->substitutionFunction]),
                    ...$args
                );
            };
        }

        // Replace each bad word using the substitution function
        $output = $input;
        $offset = 0;
        foreach ($results as &$result) {
            $result['replacement'] = $sub($this, $result) ?? '';
            $result['offset'] += $offset;
            $wordLength = strlen($result['word']);
            $replacementLength = strlen($result['replacement']);

            // Collapse double spaces
            if (
                $this->collapseDoubleSpaces &&
                $result['replacement'] == '' &&
                $output[$result['offset'] - 1] == ' ' &&
                $output[$result['offset'] + $wordLength] == ' '
            ) {
                $result['offset']--;
                $wordLength++;
            }

            // Replace the bad word
            $output = substr_replace($output, $result['replacement'], $result['offset'], $wordLength);

            // If the replacment word is a different length to the word being replaced, we need
            // to keep track of the acculumated offset
            $offset += $replacementLength - $wordLength;
        }

        return [
            'clean'         => false,
            'bad_words'     => $results,
            'output'        => $output,
            'total_length'  => strlen($input),
            'bad_length'    => array_reduce($results, function($a, $i) {
                return $a + strlen($i['word']);
            }, 0)
        ];
    }

    /**
     * Create a regex for finding a word and its variants based on the current settings
     *
     * @param string $word The base word to find
     * @return string A regular expression for finding the word and its variants
     */
    private function createRegex($word) {
        
        // Check for boundaries (start/end or whitespace) between words
        $wb = $this->useWordBoundaries ? '\b' : '';

        // Check case-sensitive
        $cs = $this->caseSensitive ? 's' : '';
        
        // Prefixes
        $pre = ($this->usePrefixes && !empty($this->prefixes)) ?
            '(?:' . implode('|', array_map('preg_quote', $this->prefixes)) . ')?' :
            '';

        // Postfixes
        $post = ($this->usePostfixes && !empty($this->postfixes)) ?
            '(?:' . implode('|', array_map('preg_quote', $this->postfixes)) . ')?' :
            '';

        // Substitute alternative characters if enabled
        if ($this->useAlternativeCharacters) {
            $word = implode('', array_map(function($c) {
                if (array_key_exists($c, $this->alternativeCharacters)) {
                    return "(?:$c|" . implode('|', array_map(
                        'preg_quote',
                        $this->alternativeCharacters[$c],
                        ['/']
                    )) . ')';
                }
                return $c;
            }, str_split($word)));
        }
        return "/$wb(?P<word>$pre$word$post)$wb/$cs";
    }

    /*
     * Substitution functions
     *
     * @param ProfanityFilter $processor An instance of the ProfanityFilter string processor
     * @param object $input Information about the word being replaced
     * @return string|null The replacement string or null for an empty string
     */

    /**
     * Ignore bad words
     */
    private static function ignore(ProfanityFilter $processor, $input) {
        return $input['word'];
    }

    /**
     * Remove bad words
     */
    private static function empty(ProfanityFilter $processor, $input) {}

    /**
     * Replace bad words with random symbols
     */
    private static function randomSymbols(ProfanityFilter $processor, $input) {
        if (empty($processor->replacementCharacters)) {
            return self::empty($processor, $input);
        }
        $c = str_split($processor->replacementCharacters);
        return implode('', array_map(
            function() use ($processor, $c) {
                return $processor->replacementCharacters[array_rand($c)];
            },
            array_fill(0, strlen($input['word']), 0)
        ));
    }

    /**
     * Replace bad words with a repeated symbol, uses the first available symbol
     */
    private static function fixedSymbols(ProfanityFilter $processor, $input) {
        if (empty($processor->replacementCharacters)) {
            return self::empty($processor, $input);
        }
        return str_repeat($processor->replacementCharacters[0], strlen($input['word']));
    }

    /**
     * Replace bad words with a repeated symbol randomly selected for each word
     */
    private static function fixedRandomSymbols(ProfanityFilter $processor, $input) {
        if (empty($processor->replacementCharacters)) {
            return self::empty($processor, $input);
        }
        $r = array_rand(str_split($processor->replacementCharacters));
        return str_repeat($processor->replacementCharacters[$r], strlen($input['word']));
    }

    /**
     * Replace bad words with matching-index good words
     */
    private static function substituteWord(ProfanityFilter $processor, $input) {
        if (empty($processor->replacementWords)) {
            return self::empty($processor, $input);
        }
        $i = array_search($input['base_word'], $processor->badWords);
        return $processor->replacementWords[($i ?? 0) % count($processor->replacementWords)];
    }

    /**
     * Replace bad words with a random good word
     */
    private static function randomWord(ProfanityFilter $processor, $input) {
        if (empty($processor->replacementWords)) {
            return self::empty($processor, $input);
        }
        return $processor->replacementWords[array_rand($processor->replacementWords)];
    }

    /**
     * Replace bad words with the first available good word
     */
    private static function fixedWord(ProfanityFilter $processor, $input) {
        if (empty($processor->replacementWords)) {
            return self::empty($processor, $input);
        }
        return $processor->replacementWords[0];
    }
}
