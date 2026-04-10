<?php

namespace App\Http\Controllers;

use App\Services\YoutubeDownloadService;
use FbMediaDownloader\Downloader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoDownloadController extends Controller
{
    public function index()
    {
        return view('downloader');
    }

    public function fetch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url' => ['required', 'url', function ($attribute, $value, $fail) {
                if (! preg_match('/facebook\.com|fb\.watch|tiktok\.com|youtube\.com|youtu\.be/i', $value)) {
                    $fail('Please provide a valid Facebook, TikTok, or YouTube video URL.');
                }
            }],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $url = $request->input('url');

        if ($this->isYouTubeUrl($url)) {
            return $this->fetchYouTube($url);
        }

        if (preg_match('/tiktok\.com/i', $url)) {
            return $this->fetchTikTok($url);
        }

        return $this->fetchFacebook($url);
    }

    private function fetchTikTok(string $url)
    {
        try {
            $apiUrl = 'https://www.tikwm.com/api/';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['url' => $url, 'hd' => 1]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false || $httpCode !== 200) {
                return response()->json(['error' => 'Failed to fetch TikTok video. Please try again.'], 422);
            }

            $data = json_decode($response, true);

            if (! isset($data['code']) || $data['code'] !== 0 || empty($data['data'])) {
                return response()->json(['error' => 'Could not extract TikTok video. Make sure the video is public.'], 422);
            }

            $video = $data['data'];
            $hd = ! empty($video['hdplay']) ? $video['hdplay'] : null;
            $sd = ! empty($video['play']) ? $video['play'] : null;

            if (empty($hd) && empty($sd)) {
                return response()->json(['error' => 'Could not extract TikTok video URL.'], 422);
            }

            $author = $video['author']['nickname'] ?? '';
            if (! empty($video['title'])) {
                $title = $video['title'];
            } elseif ($author) {
                $title = $author.' - TikTok Video';
            } else {
                $title = 'TikTok Video';
            }

            return response()->json([
                'hd' => $hd,
                'sd' => $sd,
                'title' => $title,
                'thumbnail' => $video['cover'] ?? null,
                'platform' => 'tiktok',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch the TikTok video. Please check the URL and try again.'], 422);
        }
    }

    public function download(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'video_url' => ['required', 'url'],
            'quality' => ['required', 'in:hd,sd'],
            'platform' => ['nullable', 'in:facebook,tiktok,youtube'],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $videoUrl = $request->input('video_url');
        $quality = in_array($request->input('quality'), ['hd', 'sd']) ? $request->input('quality') : 'sd';
        $platform = $request->input('platform', 'facebook');

        $host = parse_url($videoUrl, PHP_URL_HOST);

        if ($host === false || $host === null) {
            return response()->json(['error' => 'Invalid video URL.'], 422);
        }

        // Allow Facebook, TikTok, and YouTube CDN URLs for security
        $allowedPattern = '/fbcdn\.net|fbsbx\.com|facebook\.com|tiktok\.com|tiktokcdn\.com|tikwm\.com|googlevideo\.com/i';
        if (! preg_match($allowedPattern, $host)) {
            return response()->json(['error' => 'Invalid video URL.'], 422);
        }

        if (preg_match('/googlevideo\.com/i', $host)) {
            $platform = 'youtube';
        } elseif (preg_match('/tiktok\.com|tiktokcdn\.com|tikwm\.com/i', $host)) {
            $platform = 'tiktok';
        } else {
            $platform = 'facebook';
        }
        $filename = $platform.'_video_'.$quality.'_'.time().'.mp4';

        $ch = curl_init($videoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_HEADER, false);
        // Write directly to output buffer
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $data) {
            echo $data;

            return strlen($data);
        });

        return new StreamedResponse(function () use ($ch) {
            curl_exec($ch);
            curl_close($ch);
        }, 200, [
            'Content-Type' => 'video/mp4',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function isYouTubeUrl(string $url): bool
    {
        return (bool) preg_match('/youtube\.com|youtu\.be/i', parse_url($url, PHP_URL_HOST) ?: '');
    }

    private function fetchYouTube(string $url): JsonResponse
    {
        try {
            $service = new YoutubeDownloadService;
            $info = $service->getVideoInfo($url);

            if (empty($info['hd']) && empty($info['sd'])) {
                return response()->json(['error' => 'Could not extract video URL. Make sure the video is publicly accessible.'], 422);
            }

            return response()->json([
                'hd' => $info['hd'],
                'sd' => $info['sd'],
                'title' => $info['title'],
                'thumbnail' => $info['thumbnail'],
                'platform' => 'youtube',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch the video. Please check the URL and try again.'], 422);
        }
    }

    private function fetchFacebook(string $url): JsonResponse
    {
        try {
            $downloader = new Downloader;
            $downloader->set_url($url)->fetch();

            $hd = $downloader->get_hd_link() ?: null;
            $sd = $downloader->get_sd_link() ?: null;

            if (empty($hd) && empty($sd)) {
                return response()->json(['error' => 'Could not extract video URL. Make sure the video is publicly accessible.'], 422);
            }

            return response()->json([
                'hd' => $hd,
                'sd' => $sd,
                'title' => $downloader->extract_title() ?: 'Facebook Video',
                'platform' => 'facebook',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch the video. Please check the URL and try again.'], 422);
        }
    }
}
