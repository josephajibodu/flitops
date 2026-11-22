<?php

namespace App\Concerns;

trait EscapesShell
{
    private function escapeForShell(string $s): string
    {
        return "'".str_replace("'", "'\\''", $s)."'";
    }
}
