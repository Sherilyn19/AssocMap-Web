<?php

namespace App\Support;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UserAccountId
{
    public static function parse(string $value): int
    {
        // Validate before casting or using the ID in SQL. Huge IDs must not overflow
        // into another account number or turn a missing account into a database error.
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new NotFoundHttpException('User account not found.');
        }

        return $id;
    }
}
