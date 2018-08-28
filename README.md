# ProfanityFilter
Filter profanity &amp; other bad words from strings.

## Usage

```

require('ProfanityFilter.php');

$pf = new ProfanityFilter\ProfanityFilter();

$test1 = $pf->check('Dude, this project is shit!');

array(1) {
  [0]=>
  array(3) {
    ["base_word"]=>
    string(4) "shit"
    ["word"]=>
    string(4) "shit"
    ["offset"]=>
    int(22)
  }
}
```

```

$pf->substitutionFunction = 'random-symbols';
$test2 = $pf->sanitise('Dude, this project is shit!');

array(5) {
  ["clean"]=>
  bool(false)
  ["bad_words"]=>
  array(1) {
    [0]=>
    array(4) {
      ["base_word"]=>
      string(4) "shit"
      ["word"]=>
      string(4) "shit"
      ["offset"]=>
      int(22)
      ["replacement"]=>
      string(4) "$&@&"
    }
  }
  ["output"]=>
  string(27) "Dude, this project is $&@&!"
  ["total_length"]=>
  int(27)
  ["bad_length"]=>
  int(4)
}
```

## Built-in substitution functions

* Ignore profanity (`ignore`)
* Remove profanity (`empty`)
* Replace with random symbols (`random-symbols`)
* Replace with a fixed symbol (`fixed-symbols`)
* Replace with a fixed symbol, different symbol for each word found (`fixed-random-symbols`)
* Replace with a substitute good-word (`substitute-word`)
* Replace with a random good-word (`random-word`)
* Replace with a fixed good-word (`fixed-word`)

You can use a custom substitution function by setting `$pf->substitutionFunction` to a callable. The function should take 2 arguments:

1. An instance of the ProfanityFilter
2. The input word currently being filtered
