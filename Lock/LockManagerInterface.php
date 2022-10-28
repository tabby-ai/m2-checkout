<?php
declare(strict_types=1);

namespace Tabby\Checkout\Lock;

interface LockManagerInterface
{
    public function lock(string $name, int $timeout = -1): bool;

    public function unlock(string $name): bool;

    public function isLocked(string $name): bool;
}
