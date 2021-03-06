<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Diagnostics;

use Tsufeki\Tenkawa\Server\Feature\Common\Range;

/**
 * Represents a diagnostic, such as a compiler error or warning.
 *
 * Diagnostic objects are only valid in the scope of a resource.
 */
class Diagnostic
{
    /**
     * The range at which the message applies.
     *
     * @var Range
     */
    public $range;

    /**
     * The diagnostic's severity.
     *
     * Can be omitted. If omitted it is up to the client to interpret
     * diagnostics as error, warning, info or hint.
     *
     * @see DiagnosticSeverity
     *
     * @var int|null
     */
    public $severity;

    /**
     * The diagnostic's code.
     *
     * Can be omitted.
     *
     * @var int|string|null
     */
    public $code;

    /**
     * A human-readable string describing the source of this diagnostic.
     *
     * @var string|null
     */
    public $source;

    /**
     * The diagnostic's message.
     *
     * @var string
     */
    public $message;
}
