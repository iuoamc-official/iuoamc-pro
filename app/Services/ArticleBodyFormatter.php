<?php

declare(strict_types=1);

namespace App\Services;

final class ArticleBodyFormatter
{
    public function toHtml(string $body): string
    {
        $body = trim((string) preg_replace("/\r\n?/", "\n", $body));
        if ($body === '') {
            return '';
        }

        $blocks = preg_split('/\n{2,}/u', $body) ?: [$body];

        return collect($blocks)
            ->map(fn (string $block): string => $this->formatBlock(trim($block)))
            ->filter()
            ->implode("\n");
    }

    private function formatBlock(string $block): string
    {
        if ($block === '') {
            return '';
        }

        if (preg_match('/^###\s+(.+)$/us', $block, $matches) === 1) {
            return '<h3>'.$this->escape($matches[1]).'</h3>';
        }

        if (preg_match('/^##\s+(.+)$/us', $block, $matches) === 1) {
            return '<h2>'.$this->escape($matches[1]).'</h2>';
        }

        $lines = preg_split('/\n/u', $block) ?: [$block];
        if (collect($lines)->every(fn (string $line): bool => preg_match('/^(?:-|•)\s+\S/u', trim($line)) === 1)) {
            $items = collect($lines)->map(function (string $line): string {
                return '<li>'.$this->escape((string) preg_replace('/^(?:-|•)\s+/u', '', trim($line))).'</li>';
            })->implode('');

            return '<ul>'.$items.'</ul>';
        }

        if (collect($lines)->every(fn (string $line): bool => str_starts_with(trim($line), '> '))) {
            $quote = collect($lines)->map(fn (string $line): string => mb_substr(trim($line), 2))->implode("\n");

            return '<blockquote>'.$this->withBreaks($quote).'</blockquote>';
        }

        return '<p>'.$this->withBreaks($block).'</p>';
    }

    private function withBreaks(string $value): string
    {
        return nl2br($this->escape($value), false);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
