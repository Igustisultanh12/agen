<?php

namespace App\Services\AI;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class AttachmentService
{
    /**
     * Process and store an uploaded chat attachment.
     */
    public function processUpload(UploadedFile $file, User $user): array
    {
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $size = $file->getSize();

        $uuid = (string) Str::uuid();
        $safeName = $uuid . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . ($extension ? '.' . $extension : '');
        $dir = "attachments/user_{$user->id}";

        $storedPath = $file->storeAs($dir, $safeName, 'public');
        $publicUrl = '/storage/' . $storedPath;

        $isImage = str_starts_with($mime, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']);
        $textContent = null;
        $base64 = null;

        $fullPath = Storage::disk('public')->path($storedPath);

        if ($isImage) {
            $data = @file_get_contents($fullPath);
            if ($data !== false) {
                $base64 = 'data:' . $mime . ';base64,' . base64_encode($data);
            }
        } elseif ($extension === 'pdf' || $mime === 'application/pdf') {
            $textContent = $this->extractPdfText($fullPath);
        } elseif (in_array($extension, ['docx', 'doc']) || str_contains($mime, 'wordprocessingml')) {
            $textContent = $this->extractDocxText($fullPath);
        } elseif (in_array($extension, ['txt', 'md', 'csv', 'json', 'xml', 'html', 'js', 'ts', 'py', 'php', 'java', 'c', 'cpp', 'css', 'sql', 'sh', 'yaml', 'yml', 'env', 'log']) || str_starts_with($mime, 'text/')) {
            $raw = @file_get_contents($fullPath);
            if ($raw !== false) {
                $textContent = $raw;
            }
        }

        // Limit text length to prevent exceeding token context (~40,000 characters)
        if ($textContent !== null && mb_strlen($textContent) > 40000) {
            $textContent = mb_substr($textContent, 0, 40000) . "\n... [Isi dokumen dipotong karena melebihi batas 40.000 karakter]";
        }

        return [
            'id' => $uuid,
            'name' => $originalName,
            'size' => $size,
            'extension' => $extension,
            'mime_type' => $mime,
            'url' => $publicUrl,
            'storage_path' => $storedPath,
            'is_image' => $isImage,
            'text_content' => $textContent,
            'base64' => $base64,
        ];
    }

    /**
     * Extract readable text from PDF file.
     */
    public function extractPdfText(string $filePath): string
    {
        // 1. Try pdftotext CLI tool if available on Linux/server
        if (function_exists('exec') && (stripos(PHP_OS, 'WIN') === false || file_exists('/usr/bin/pdftotext'))) {
            $escaped = escapeshellarg($filePath);
            $out = [];
            $code = 0;
            @exec("pdftotext {$escaped} -", $out, $code);
            if ($code === 0 && !empty($out)) {
                $cliText = trim(implode("\n", $out));
                if (!empty($cliText)) {
                    return $cliText;
                }
            }
        }

        // 2. Pure PHP stream decompression and text parsing
        $content = @file_get_contents($filePath);
        if (!$content) {
            return '';
        }

        $text = '';
        if (preg_match_all('/stream[\r\n]+(.*?)[\r\n]+endstream/s', $content, $matches)) {
            foreach ($matches[1] as $stream) {
                $decompressed = @gzuncompress($stream);
                if (!$decompressed) {
                    $decompressed = @gzinflate($stream);
                }
                if (!$decompressed) {
                    $decompressed = $stream;
                }

                if (preg_match_all('/\((.*?)\)\s*T[jJ]/s', $decompressed, $textMatches)) {
                    $text .= implode(' ', $textMatches[1]) . ' ';
                } elseif (preg_match_all('/\[(.*?)\]\s*TJ/s', $decompressed, $tjMatches)) {
                    foreach ($tjMatches[1] as $tj) {
                        if (preg_match_all('/\((.*?)\)/s', $tj, $m)) {
                            $text .= implode('', $m[1]) . ' ';
                        }
                    }
                }
            }
        }

        // Clean up escaped PDF characters
        $clean = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $text);
        // Remove non-printable / binary characters
        $clean = preg_replace('/[^\x20-\x7E\t\r\n\p{L}\p{N}\p{P}\p{Z}]/u', '', $clean);

        return trim($clean) ?: 'File PDF berhasil diunggah (teks kosong atau berupa hasil scan dokumen).';
    }

    /**
     * Extract text from DOCX file using ZipArchive.
     */
    public function extractDocxText(string $filePath): string
    {
        if (!class_exists('ZipArchive')) {
            return '';
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) === true) {
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            if ($xml) {
                $xml = preg_replace('/<\/w:p>/', "\n", $xml);
                $xml = preg_replace('/<w:br\/>/', "\n", $xml);
                $text = strip_tags($xml);
                return trim(html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8'));
            }
        }

        return '';
    }
}
