<?php

declare(strict_types=1);

namespace Qase\PHPUnitReporter;

use PHPUnit\Event\Code\TestMethod;
use Qase\PhpCommons\Interfaces\ReporterInterface;
use Qase\PhpCommons\Models\Result;
use Qase\PHPUnitReporter\Attributes\AttributeParserInterface;

class QaseReporter implements QaseReporterInterface
{
    private array $testResults = [];
    private AttributeParserInterface $attributeParser;
    private ReporterInterface $reporter;
    private ExceptionHandler $exceptionHandler;

    public function __construct(AttributeParserInterface $attributeParser, ReporterInterface $reporter, ExceptionHandler $exceptionHandler)
    {
        $this->attributeParser = $attributeParser;
        $this->reporter = $reporter;
        $this->exceptionHandler = $exceptionHandler;
    }

    public function startTestRun(): void
    {
        $this->exceptionHandler->tryBlock(fn() => $this->reporter->startRun());
    }

    public function completeTestRun(): void
    {
        $this->exceptionHandler->tryBlock(fn() => $this->reporter->completeRun());
    }

    public function startTest(TestMethod $test): void
    {
        $this->exceptionHandler->tryBlock(function () use ($test) {
            $key = $this->getTestKey($test);

            $metadata = $this->attributeParser->parseAttribute($test->className(), $test->methodName());

            $testResult = new Result();

            $testResult->testOpsId = $metadata->qaseId;

            if (empty($metadata->suites)) {
                $suites = explode('\\', $test->className());
                foreach ($suites as $suite) {
                    $testResult->relations->addSuite($suite);
                }
            } else {
                foreach ($metadata->suites as $suite) {
                    $testResult->relations->addSuite($suite);
                }
            }

            $testResult->fields = $metadata->fields;
            $testResult->params = $metadata->parameters;
            $testResult->signature = $this->createSignature($test);
            $testResult->execution->setThread($this->getThread());

            $testResult->title = $metadata->title ?? $test->methodName();

            $this->testResults[$key] = $testResult;
        });
    }

    public function updateStatus(TestMethod $test, string $status, ?string $message = null, ?string $stackTrace = null): void
    {
        $this->exceptionHandler->tryBlock(function () use ($test, $status, $message, $stackTrace) {
            $key = $this->getTestKey($test);
            if (!array_key_exists($key, $this->testResults)) {
                // test did not run
                return;
            }
            $this->testResults[$key]->execution->setStatus($status);

            if ($message) {
                $this->testResults[$key]->message = $this->testResults[$key]->message . "\n" . $message . "\n";
            }

            if ($stackTrace) {
                $this->testResults[$key]->execution->setStackTrace($stackTrace);
            }
        });
    }

    public function completeTest(TestMethod $test): void
    {
        $this->exceptionHandler->tryBlock(function () use ($test) {
            $key = $this->getTestKey($test);
            $this->testResults[$key]->execution->finish();
            $this->testResults[$key]->title = $this->beautifyTitle($this->testResults[$key]->title);

            $this->reporter->addResult($this->testResults[$key]);
        });
    }

    private function getTestKey(TestMethod $test): string
    {
        return $test->className() . '::' . $test->methodName() . ':' . $test->line();
    }

    private function createSignature(TestMethod $test): string
    {
        return str_replace("\\", "::", $test->className()) . '::' . $test->methodName() . ':' . $test->line();
    }

    private function beautifyTitle(string $oldTitle): string
    {
        if ($oldTitle !== strtolower($oldTitle)) {
            $oldTitle = $this->camelCaseToSnakeCase($oldTitle);
        }

        return str_replace('_', ' ', $oldTitle);
    }

    private function camelCaseToSnakeCase(string $input): string
    {
        $snakeCase = preg_replace_callback('/[A-Z]/', static fn($matches) => '_' . strtolower($matches[0]), $input);
        return ltrim($snakeCase, '_');
    }

    private function getThread(): string
    {
        return $_ENV['TEST_TOKEN'] ?? "default";
    }
}
