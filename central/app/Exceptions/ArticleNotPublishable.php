<?php

namespace App\Exceptions;

use RuntimeException;

class ArticleNotPublishable extends RuntimeException
{
    /**
     * @param  list<string>  $missing
     */
    public function __construct(string $message, public readonly array $missing = [])
    {
        parent::__construct($message);
    }

    /**
     * @param  list<string>  $missing  Croatian labels of the missing parts
     */
    public static function missing(array $missing): self
    {
        return new self('Za objavu nedostaje: '.implode(', ', $missing).'.', $missing);
    }
}
