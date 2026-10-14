<?php

namespace App\Exceptions;

use RuntimeException;

class CategoryHasDocuments extends RuntimeException
{
    public static function make(): self
    {
        return new self('Kategorija sadrži dokumente. Premjestite ih u drugu kategoriju ili ih obrišite prije brisanja kategorije.');
    }
}
