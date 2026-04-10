<?php

namespace App\Services;

use RuntimeException;

class YoutubeDownloadService
{
    private string $ytdlpBin;

    private int $timeoutSeconds;

    public function __construct(string $ytdlpBin = 'yt-dlp', int $timeoutSeconds = 30)
    {
        $this->ytdlpBin = $ytdlpBin;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * Extract video information from a YouTube URL.
     *
     * Returns an array with keys: title, thumbnail, hd, sd
     * where hd/sd are direct stream URLs (or null if unavailable).
     *
     * @throws RuntimeException
     */
    public function getVideoInfo(string $url): array
    {
        $cmd = [
            $this->ytdlpBin,
            '--dump-json',
            '--no-playlist',
            '--no-warnings',
            '--',
            $url,
        ];

        $json = $this->runProcess($cmd);
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new RuntimeException('Failed to parse yt-dlp output.');
        }

        $hd = null;
        $sd = null;

        // Collect combined (video + audio) formats in MP4 container
        $combined = array_filter($data['formats'] ?? [], function ($f) {
            return isset($f['vcodec'], $f['acodec'], $f['url'])
                && $f['vcodec'] !== 'none'
                && $f['acodec'] !== 'none'
                && ($f['ext'] ?? '') === 'mp4';
        });

        // Sort by height descending
        usort($combined, function ($a, $b) {
            return ($b['height'] ?? 0) <=> ($a['height'] ?? 0);
        });

        $combined = array_values($combined);

        if (! empty($combined)) {
            $hd = $combined[0]['url'];

            // Pick a lower quality for SD if more than one format exists
            $sd = isset($combined[1]) ? $combined[1]['url'] : $combined[0]['url'];
        } else {
            // Fallback: best single format regardless of container
            $fallbackCombined = array_filter($data['formats'] ?? [], function ($f) {
                return isset($f['vcodec'], $f['acodec'], $f['url'])
                    && $f['vcodec'] !== 'none'
                    && $f['acodec'] !== 'none';
            });

            usort($fallbackCombined, function ($a, $b) {
                return ($b['height'] ?? 0) <=> ($a['height'] ?? 0);
            });

            $fallbackCombined = array_values($fallbackCombined);

            if (! empty($fallbackCombined)) {
                $hd = $fallbackCombined[0]['url'];
                $sd = isset($fallbackCombined[1]) ? $fallbackCombined[1]['url'] : $fallbackCombined[0]['url'];
            }
        }

        return [
            'title' => $data['title'] ?? 'YouTube Video',
            'thumbnail' => $data['thumbnail'] ?? null,
            'hd' => $hd,
            'sd' => $sd,
        ];
    }

    /**
     * Run an external process safely using proc_open (no shell injection).
     */
    private function runProcess(array $cmd): string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('Failed to start yt-dlp process.');
        }

        // Set non-blocking immediately after proc_open, before any reads
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        fclose($pipes[0]);

        $stdout = '';
        $stderr = '';
        $start = time();
        $pipesClosed = false;

        while (true) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;
            $changed = stream_select($read, $write, $except, 1);

            if ($changed === false) {
                break;
            }

            if (in_array($pipes[1], $read)) {
                $chunk = fread($pipes[1], 8192);
                if ($chunk !== false) {
                    $stdout .= $chunk;
                }
            }

            if (in_array($pipes[2], $read)) {
                $chunk = fread($pipes[2], 8192);
                if ($chunk !== false) {
                    $stderr .= $chunk;
                }
            }

            $status = proc_get_status($process);
            if (! $status['running']) {
                // Drain remaining output
                $stdout .= stream_get_contents($pipes[1]);
                $stderr .= stream_get_contents($pipes[2]);
                break;
            }

            if ((time() - $start) >= $this->timeoutSeconds) {
                proc_terminate($process);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $pipesClosed = true;
                proc_close($process);
                throw new RuntimeException('yt-dlp process timed out.');
            }
        }

        if (! $pipesClosed) {
            fclose($pipes[1]);
            fclose($pipes[2]);
        }
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException('yt-dlp exited with error: '.trim($stderr));
        }

        return $stdout;
    }
}
