<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\Analyser\NodeScopeResolver;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Reflection\MissingMethodFromReflectionException;
use PHPStan\Reflection\MissingPropertyFromReflectionException;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Utils\SyncAsyncKernel;

class Analyser
{
    /**
     * @var NodeScopeResolver
     */
    private $nodeScopeResolver;

    /**
     * @var DocumentParser
     */
    private $parser;

    /**
     * @var IndexBroker
     */
    private $broker;

    /**
     * @var PhpDocResolver
     */
    private $phpDocResolver;

    /**
     * @var Standard
     */
    private $printer;

    /**
     * @var TypeSpecifier
     */
    private $typeSpecifier;

    /**
     * @var SyncAsyncKernel
     */
    private $syncAsync;

    public function __construct(
        NodeScopeResolver $nodeScopeResolver,
        DocumentParser $parser,
        IndexBroker $broker,
        PhpDocResolver $phpDocResolver,
        Standard $printer,
        TypeSpecifier $typeSpecifier,
        SyncAsyncKernel $syncAsync
    ) {
        $this->nodeScopeResolver = $nodeScopeResolver;
        $this->parser = $parser;
        $this->broker = $broker;
        $this->phpDocResolver = $phpDocResolver;
        $this->printer = $printer;
        $this->typeSpecifier = $typeSpecifier;
        $this->syncAsync = $syncAsync;
    }

    /**
     * @param \Closure $nodeCallback (Node $node, Scope $scope)
     */
    public function analyse(Document $document, \Closure $nodeCallback): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return;
        }

        $path = $document->getUri()->getFilesystemPath();

        yield $this->syncAsync->callSync(
            function () use ($path, $nodeCallback) {
                try {
                    $this->nodeScopeResolver->processNodes(
                        $this->parser->parseFile($path),
                        new Scope($this->broker, $this->printer, $this->typeSpecifier, $path),
                        $nodeCallback
                    );
                } catch (MissingPropertyFromReflectionException $e) {
                    // These exceptions are thrown when analysis begins before
                    // new property/method is indexed.
                    // TODO: find better way of handling such situation.
                } catch (MissingMethodFromReflectionException $e) {
                }
            },
            [],
            function () use ($document, $path) {
                $this->broker->setDocument($document);
                $this->parser->setDocument($document);
                $this->phpDocResolver->setDocument($document);
                $this->nodeScopeResolver->setAnalysedFiles([$path]);
            },
            function () {
                $this->broker->setDocument(null);
                $this->parser->setDocument(null);
                $this->phpDocResolver->setDocument(null);
                $this->nodeScopeResolver->setAnalysedFiles([]);
            }
        );
    }
}
