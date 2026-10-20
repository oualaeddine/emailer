<?php

namespace App\Modules\Automations\Services\StepExecutors;

use RuntimeException;

/** A step could not run (missing template / list, invalid value…): the run fails with this message. */
class StepFailedException extends RuntimeException {}
