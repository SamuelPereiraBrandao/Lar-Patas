<?php

namespace App\Http;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SafeImageStorage
{
    public function store(UploadedFile $file, string $folder): string
    {
        $info = @getimagesize($file->getRealPath());
        if (! $info || $info[0] * $info[1] > 16000000 || max($info[0], $info[1]) > 6000 || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages(['photos' => 'Use uma foto JPG, PNG ou WebP com até 16 megapixels e 6000 pixels por lado.']);
        }
        $bytes = file_get_contents($file->getRealPath());
        $extension = match ($info['mime']) {
            'image/jpeg' => 'jpg','image/png' => 'png',default => 'webp'
        };
        $clean = match ($extension) {
            'jpg' => $this->jpeg($bytes),'png' => $this->png($bytes),default => $this->webp($bytes)
        };
        $path = $folder.'/'.$file->hashName();
        if (! Storage::disk('public')->put($path, $clean)) {
            throw new \RuntimeException('Não foi possível armazenar a foto.');
        }

        return $path;
    }

    private function jpeg(string $bytes): string
    {
        $output = substr($bytes, 0, 2);
        $offset = 2;
        $length = strlen($bytes);
        while ($offset + 4 <= $length) {
            if (ord($bytes[$offset]) !== 255) {
                break;
            }
            $marker = ord($bytes[$offset + 1]);
            if ($marker === 218 || $marker === 217) {
                return $output.substr($bytes, $offset);
            }
            $size = unpack('n', substr($bytes, $offset + 2, 2))[1];
            if ($size < 2 || $offset + 2 + $size > $length) {
                break;
            }
            if (! (($marker >= 225 && $marker <= 239 && $marker !== 238) || $marker === 254)) {
                $output .= substr($bytes, $offset, $size + 2);
            }
            $offset += $size + 2;
        }
        throw ValidationException::withMessages(['photos' => 'A foto JPG está danificada.']);
    }

    private function png(string $bytes): string
    {
        $output = substr($bytes, 0, 8);
        $offset = 8;
        $length = strlen($bytes);
        while ($offset + 12 <= $length) {
            $size = unpack('N', substr($bytes, $offset, 4))[1];
            $type = substr($bytes, $offset + 4, 4);
            if ($size > $length - $offset - 12) {
                break;
            }
            if (in_array($type, ['IHDR', 'PLTE', 'IDAT', 'IEND', 'tRNS', 'sRGB', 'gAMA', 'cHRM'], true)) {
                $output .= substr($bytes, $offset, $size + 12);
            }
            $offset += $size + 12;
            if ($type === 'IEND') {
                return $output;
            }
        }
        throw ValidationException::withMessages(['photos' => 'A foto PNG está danificada.']);
    }

    private function webp(string $bytes): string
    {
        $output = 'WEBP';
        $offset = 12;
        $length = strlen($bytes);
        while ($offset + 8 <= $length) {
            $type = substr($bytes, $offset, 4);
            $size = unpack('V', substr($bytes, $offset + 4, 4))[1];
            $padded = $size + ($size % 2);
            if ($padded > $length - $offset - 8) {
                break;
            }
            if (! in_array($type, ['EXIF', 'XMP ', 'ICCP'], true)) {
                $chunk = substr($bytes, $offset, 8 + $padded);
                if ($type === 'VP8X' && $size >= 1) {
                    $chunk[8] = chr(ord($chunk[8]) & ~44);
                }
                $output .= $chunk;
            }
            $offset += 8 + $padded;
        }
        if ($offset !== $length) {
            throw ValidationException::withMessages(['photos' => 'A foto WebP está danificada.']);
        }

        return 'RIFF'.pack('V', strlen($output)).$output;
    }
}
