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
                if (! preg_match('/facebook\.com|fb\.watch|youtube\.com|youtu\.be/i', $value)) {
                    $fail('Please provide a valid Facebook or YouTube video URL.');
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

        return $this->fetchFacebook($url);
    }

    public function download(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'video_url' => ['required', 'url'],
            'quality' => ['required', 'in:hd,sd'],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $videoUrl = $request->input('video_url');
        $quality = in_array($request->input('quality'), ['hd', 'sd']) ? $request->input('quality') : 'sd';

        $host = parse_url($videoUrl, PHP_URL_HOST) ?: '';

        // Allow Facebook CDN and YouTube CDN (googlevideo.com)
        if (! preg_match('/fbcdn\.net|fbsbx\.com|facebook\.com|googlevideo\.com/i', $host)) {
            return response()->json(['error' => 'Invalid video URL.'], 422);
        }

        $platform = preg_match('/googlevideo\.com/i', $host) ? 'youtube' : 'facebook';
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
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch the video. Please check the URL and try again.'], 422);
        }
    }
}
