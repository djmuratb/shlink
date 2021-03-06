<?php
declare(strict_types=1);

namespace Shlinkio\Shlink\Core\Exception;

use function sprintf;

class NonUniqueSlugException extends InvalidArgumentException
{
    public static function fromSlug(string $slug): self
    {
        return new self(sprintf('Provided slug "%s" is not unique.', $slug));
    }
}
