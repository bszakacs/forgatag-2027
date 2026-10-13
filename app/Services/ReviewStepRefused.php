<?php

namespace App\Services;

use RuntimeException;

/** A jóváhagyási lépés nem hajtható végre — az ok emberi nyelven, a felületen megjelenik. */
class ReviewStepRefused extends RuntimeException {}
