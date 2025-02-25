<?php

namespace Qase\PHPUnitReporter;

class ExceptionHandler
{
    public function tryBlock(callable $func): void
    {
        try {
            $func();
        } catch (\Throwable $exception) {
            echo 'exception caught in Qase\PHPUnitReporter: ' . PHP_EOL;
            echo json_encode([
                    'message' => $exception->getMessage(),
                    'code' => $exception->getCode(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTrace(),

                ]) . PHP_EOL;
        }
    }
}
