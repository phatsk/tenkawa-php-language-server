<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use PhpParser\NodeTraverser;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Parser\FindNodeVisitor;
use Tsufeki\Tenkawa\Parser\Parser;
use Tsufeki\Tenkawa\Protocol\Common\Position;

class GoToDefinitionAggregator
{
    /**
     * @var GoToDefinitionProvider[]
     */
    private $goToProviders;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @param GoToDefinitionProvider[] $goToProviders
     */
    public function __construct(array $goToProviders, Parser $parser)
    {
        $this->goToProviders = $goToProviders;
        $this->parser = $parser;
    }

    /**
     * @resolve Location[]
     */
    public function getLocations(Document $document, Position $position): \Generator
    {
        $ast = yield $this->parser->parse($document);
        $visitor = new FindNodeVisitor($document, $position);
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($visitor);
        $nodeTraverser->traverse($ast->nodes);
        $nodes = $visitor->getNodes();

        return array_merge(
            ...yield array_map(function (GoToDefinitionProvider $provider) use ($document, $position, $nodes) {
                return yield $provider->getLocations($document, $position, $nodes);
            }, $this->goToProviders)
        );
    }

    public function hasProviders(): bool
    {
        return !empty($this->goToProviders);
    }
}