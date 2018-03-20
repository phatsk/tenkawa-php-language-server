<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Language;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Language\DiagnosticsProvider;
use Tsufeki\Tenkawa\Server\ProcessRunner\ProcessResult;
use Tsufeki\Tenkawa\Server\ProcessRunner\ProcessRunner;
use Tsufeki\Tenkawa\Server\Protocol\Common\Diagnostic;
use Tsufeki\Tenkawa\Server\Protocol\Common\DiagnosticSeverity;
use Tsufeki\Tenkawa\Server\Protocol\Common\Position;
use Tsufeki\Tenkawa\Server\Protocol\Common\Range;

class PhplDiagnosticsProvider implements DiagnosticsProvider
{
    /**
     * @var ProcessRunner
     */
    private $processRunner;

    const MESSAGE_REGEX = '~^(?:(?:PHP +)?(?:Fatal|Parse) error: +)?(.+?)(?: in (?:-|Standard input code) on line ([0-9]+))?$~m';

    public function __construct(ProcessRunner $processRunner)
    {
        $this->processRunner = $processRunner;
    }

    public function getDiagnostics(Document $document): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return;
        }

        $cmd = [
            'php',
            '-n',
            '-d', 'error_reporting=E_ALL',
            '-d', 'display_errors=stderr',
            '-l',
        ];

        /** @var ProcessResult $result */
        $result = yield $this->processRunner->run($cmd, $document->getText());
        $diagnostics = [];

        if ($result->exitCode !== 0 && $result->exitCode !== null && !empty(trim($result->stderr))) {
            if (preg_match(self::MESSAGE_REGEX, trim($result->stderr), $matches)) {
                $diag = new Diagnostic();
                $diag->severity = DiagnosticSeverity::ERROR;
                $diag->source = 'php -l';
                $diag->message = $matches[1];
                $diag->range = new Range(
                    new Position(max(0, (int)($matches[2] ?? 1) - 1), 0),
                    new Position(max(0, (int)($matches[2] ?? 1) - 1), 999)
                );
                $diagnostics[] = $diag;
            }
        }

        return $diagnostics;
    }
}