<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\GoToDefinition;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\LocationLink;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;

interface GoToDefinitionProvider
{
    /**
     * @resolve LocationLink[]
     */
    public function getLocations(Document $document, Position $position): \Generator;
}
