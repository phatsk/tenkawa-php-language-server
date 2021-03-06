<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Document;

use Tsufeki\Tenkawa\Server\Event\Document\OnChange;
use Tsufeki\Tenkawa\Server\Event\Document\OnClose;
use Tsufeki\Tenkawa\Server\Event\Document\OnOpen;
use Tsufeki\Tenkawa\Server\Event\Document\OnProjectClose;
use Tsufeki\Tenkawa\Server\Event\Document\OnProjectOpen;
use Tsufeki\Tenkawa\Server\Event\EventDispatcher;
use Tsufeki\Tenkawa\Server\Exception\DocumentNotOpenException;
use Tsufeki\Tenkawa\Server\Exception\ProjectNotOpenException;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\Cache;

class DocumentStore
{
    /**
     * @var Document[]
     */
    private $documents = [];

    /**
     * @var Project[]
     */
    private $projects = [];

    /**
     * @var Project|null
     */
    private $defaultProject;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var Cache
     */
    private $cache;

    public function __construct(EventDispatcher $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->cache = new Cache();
    }

    /**
     * @throws DocumentNotOpenException
     */
    public function get(Uri $uri): Document
    {
        $uriString = $uri->getNormalized();
        if (!isset($this->documents[$uriString])) {
            throw new DocumentNotOpenException($uri);
        }

        return $this->documents[$uriString];
    }

    /**
     * @resolve Document
     */
    public function open(Uri $uri, string $language, string $text, ?int $version): \Generator
    {
        $document = new Document($uri, $language);
        $document->update($text, $version);
        $uriString = $document->getUri()->getNormalized();
        $this->documents[$uriString] = $document;
        $this->cache->clear();

        yield $this->eventDispatcher->dispatchAndWait(OnOpen::class, $document);

        return $document;
    }

    /**
     * Get document but don't track it as open.
     *
     * @resolve Document
     */
    public function load(Uri $uri, string $language, string $text): \Generator
    {
        $document = new Document($uri, $language);
        $document->update($text);

        return $document;
        yield;
    }

    public function update(Document $document, string $text, ?int $version): \Generator
    {
        // Check if open
        $this->get($document->getUri());

        $document->update($text, $version);

        yield $this->eventDispatcher->dispatchAndWait(OnChange::class, $document);
    }

    public function close(Document $document): \Generator
    {
        // Check if open
        $this->get($document->getUri());

        yield $this->eventDispatcher->dispatchAndWait(OnClose::class, $document);

        $uriString = $document->getUri()->getNormalized();
        unset($this->documents[$uriString]);
        $document->close();
        $this->cache->clear();
    }

    /**
     * @throws ProjectNotOpenException
     */
    public function getProject(Uri $rootUri): Project
    {
        $uriString = $rootUri->getNormalized();
        if (!isset($this->projects[$uriString])) {
            throw new ProjectNotOpenException($rootUri);
        }

        return $this->projects[$uriString];
    }

    /**
     * @resolve Project
     *
     * @throws ProjectNotOpenException
     */
    public function getProjectForDocument(Document $document): \Generator
    {
        $projects = yield $this->getProjectsForUri($document->getUri());
        $project = $projects[0] ?? $this->defaultProject;

        if ($project === null) {
            throw new ProjectNotOpenException($document->getUri());
        }

        return $project;
    }

    /**
     * @resolve Project[]
     */
    public function getProjectsForUri(Uri $uri): \Generator
    {
        $key = "projects_for_uri.$uri";
        $projects = $this->cache->get($key);
        if ($projects !== null) {
            return $projects;
        }

        $projects = [];
        foreach ($this->projects as $project) {
            if ($project->getRootUri()->equals($uri) || $project->getRootUri()->isParentOf($uri)) {
                $projects[] = $project;
            }
        }
        $this->cache->set($key, $projects);

        return $projects;
        yield;
    }

    /**
     * @resolve Document[]
     */
    public function getDocumentsForProject(Project $project): \Generator
    {
        $key = 'documents_for_project.' . spl_object_hash($project);
        $documents = $this->cache->get($key);
        if ($documents !== null) {
            return $documents;
        }

        $documents = [];
        foreach ($this->documents as $document) {
            if ($project->getRootUri()->isParentOf($document->getUri())) {
                $documents[] = $document;
            }
        }
        $this->cache->set($key, $documents);

        return $documents;
        yield;
    }

    /**
     * @resolve Document[]
     */
    public function getDocuments(): \Generator
    {
        return array_values($this->documents);
        yield;
    }

    /**
     * @resolve Project
     */
    public function openProject(Uri $rootUri): \Generator
    {
        $project = new Project($rootUri);
        $uriString = $project->getRootUri()->getNormalized();
        $this->projects[$uriString] = $project;
        $this->cache->clear();

        yield $this->eventDispatcher->dispatchAndWait(OnProjectOpen::class, $project);

        return $project;
    }

    /**
     * @resolve Project
     */
    public function openDefaultProject(): \Generator
    {
        return $this->defaultProject = yield $this->openProject(Uri::fromString('about:default-project'));
    }

    public function closeProject(Project $project): \Generator
    {
        yield $this->eventDispatcher->dispatchAndWait(OnProjectClose::class, $project);

        $uriString = $project->getRootUri()->getNormalized();
        unset($this->projects[$uriString]);
        $project->close();
        $this->cache->clear();
    }

    /**
     * @resolve Projects[]
     */
    public function getProjects(): \Generator
    {
        return array_values($this->projects);
        yield;
    }

    public function closeAll(): \Generator
    {
        foreach ($this->documents as $document) {
            yield $this->close($document);
        }

        foreach ($this->projects as $project) {
            yield $this->closeProject($project);
        }
    }
}
