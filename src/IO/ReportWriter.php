<?php

namespace OrderReport\IO;

class ReportWriter
{
    public static function writeToConsole(string $report): void
    {
        echo $report;
    }

    public static function writeToFile(string $filePath, string $content): void
    {
        file_put_contents($filePath, $content);
    }

    public static function writeJsonToFile(string $filePath, array $data): void
    {
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
    }
}

