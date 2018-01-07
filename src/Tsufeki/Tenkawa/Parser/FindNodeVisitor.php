<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Parser;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Utils\PositionUtils;

class FindNodeVisitor extends NodeVisitorAbstract
{
    /**
     * @var int
     */
    private $offset;

    /**
     * @var (Node|Comment)[]
     */
    private $nodes = [];

    public function __construct(Document $document, Position $position)
    {
        $this->offset = PositionUtils::offsetFromPosition($position, $document);
    }

    public function enterNode(Node $node)
    {
        /** @var Comment $comment */
        foreach ($node->getAttribute('comments') ?? [] as $comment) {
            if ($comment->getFilePos() <= $this->offset && $this->offset < $comment->getFilePos() + strlen($comment->getText())) {
                $this->nodes[] = $comment;

                return NodeTraverser::DONT_TRAVERSE_CHILDREN;
            }
        }

        if ($node->getAttribute('startFilePos') <= $this->offset && $this->offset <= $node->getAttribute('endFilePos')) {
            $this->nodes[] = $node;
        } else {
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }
    }

    /**
     * @return (Node|Comment)[]
     */
    public function getNodes(): array
    {
        return array_reverse($this->nodes);
    }
}