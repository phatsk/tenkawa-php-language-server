<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Utils;

class SyncCallContext
{
    /**
     * @var callable|null
     */
    public $resumeCallback;

    /**
     * @var callable|null
     */
    public $pauseCallback;

    public function resume(): void
    {
        if ($this->resumeCallback !== null) {
            ($this->resumeCallback)();
        }
    }

    public function pause(): void
    {
        if ($this->pauseCallback !== null) {
            ($this->pauseCallback)();
        }
    }
}
