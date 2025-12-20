<?php

declare(strict_types=1);

namespace App\Service;

class MessageParserService
{
    /**
     * @return list<string> Array of item names
     */
    public function parseItems(string $text): array
    {
        $lines = preg_split('/\r?\n/', $text);

        if ($lines === false) {
            return [];
        }

        $items = [];
        foreach ($lines as $line) {
            if (str_contains($line, ',')) {
                $parts = explode(',', $line);
                foreach ($parts as $part) {
                    $trimmed = trim($part);
                    if ($trimmed !== '') {
                        $items[] = $trimmed;
                    }
                }
            } else {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $items[] = $trimmed;
                }
            }
        }

        return $items;
    }
}
