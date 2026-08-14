<?php

declare(strict_types=1);

namespace app\components\specifications;

/**
 * Правило, которое можно проверить на произвольном кандидате.
 *
 * Спецификации выносят условия доступа из представлений и контроллеров в одно место:
 * кнопка и само действие проверяются одним и тем же кодом и не могут разойтись.
 */
interface SpecificationInterface
{
    public function isSatisfiedBy(mixed $candidate): bool;
}
