<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Queue;

/**
 * Holds the output of one process run, bounded in memory: past twice the maximum only the last maximum bytes stay.
 */
final class CronOutputBuffer
{
    public const string START_LEFT_OUT_MARKER = "… (start left out)\n";

    private string $output = '';

    private bool $startLeftOut = false;

    private bool $changed = false;

    public function __construct(private readonly int $maxBytes)
    {
    }

    /**
     * Appends a chunk of process output; usable as a Process callback.
     *
     * @param string $type  The Process output type (stdout or stderr), not used.
     * @param string $chunk The output to append.
     *
     * @return void
     */
    public function append(string $type, string $chunk): void
    {
        $this->output .= $chunk;
        $this->changed = true;

        if (strlen($this->output) > 2 * $this->maxBytes) {
            $this->output = $this->lastBytes();
            $this->startLeftOut = true;
        }
    }

    /**
     * Whether output came in since the last time it was taken.
     *
     * @return bool
     */
    public function hasChanged(): bool
    {
        return $this->changed;
    }

    /**
     * The number of bytes that a report of the output holds, without the marker.
     *
     * @return int
     */
    public function reportableBytes(): int
    {
        return min(strlen($this->output), $this->maxBytes);
    }

    /**
     * Takes the output for a report: at most the last maximum bytes, prefixed with a marker when the start is left out.
     *
     * @return string
     */
    public function take(): string
    {
        $this->changed = false;

        if (strlen($this->output) > $this->maxBytes) {
            return self::START_LEFT_OUT_MARKER . $this->lastBytes();
        }

        return $this->startLeftOut ? self::START_LEFT_OUT_MARKER . $this->output : $this->output;
    }

    /**
     * The last maximum bytes of the output, cut on a character boundary.
     *
     * @return string
     */
    private function lastBytes(): string
    {
        // Cut on bytes, cheap for large output; mb_strcut moves the cut to the start of a character.
        return mb_strcut($this->output, strlen($this->output) - $this->maxBytes, null, 'UTF-8');
    }
}
