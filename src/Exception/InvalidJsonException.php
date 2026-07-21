<?php

declare(strict_types=1);

namespace Survos\LocBundle\Exception;

/** LOC returned a 200 whose body was not valid JSON — typically an HTML page (rate-limit interstitial, maintenance page, or a URL that isn't a `fo=json` endpoint). */
class InvalidJsonException extends LocApiException
{
}
