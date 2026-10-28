<?php
/**
 * Validated image uploader used for the site logo (and reusable elsewhere).
 *
 * Security notes:
 *  - The file extension is derived from the detected MIME type, never from the
 *    client supplied file name.
 *  - Only real raster images are accepted (PNG, JPEG, GIF, WEBP, ICO), so an
 *    uploaded "image" can never contain executable PHP/SVG payloads.
 *  - The destination directory is fixed and the generated file name is random.
 */
final class ImageUploader
{
    /** MIME type => canonical extension */
    const ALLOWED = [
        'image/png'             => 'png',
        'image/jpeg'            => 'jpg',
        'image/gif'             => 'gif',
        'image/webp'            => 'webp',
        'image/x-icon'          => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];

    /** Friendly message for each PHP upload error code. */
    private static function uploadErrorMessage($code, $maxBytes)
    {
        switch ((int) $code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'The file is larger than the server limit (' . format_bytes($maxBytes) . ').';
            case UPLOAD_ERR_PARTIAL:
                return 'The upload was interrupted. Please try again.';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was selected.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'The server has no temporary folder for uploads.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'The server could not save the uploaded file.';
            case UPLOAD_ERR_EXTENSION:
                return 'The upload was blocked by a server extension.';
            default:
                return 'The upload failed. Please try again.';
        }
    }

    /**
     * Handle an entry from $_FILES.
     *
     * @param  array<string, mixed> $file     e.g. $_FILES['logo']
     * @param  string               $directory Absolute destination directory
     * @param  array<string, mixed> $options  max_bytes, prefix, max_dimension
     * @return array{ok:bool, path:string, error:string, meta:array<string,mixed>}
     */
    public static function fromFilesArray(array $file, $directory, array $options = [])
    {
        $maxBytes = isset($options['max_bytes']) ? (int) $options['max_bytes'] : LOGO_MAX_BYTES;

        if (!isset($file['error'])) {
            return self::failure('No file was selected.');
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            return self::failure(self::uploadErrorMessage($file['error'], $maxBytes));
        }
        if (empty($file['tmp_name']) || !is_string($file['tmp_name']) || !is_file($file['tmp_name'])) {
            return self::failure('The uploaded file could not be read.');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return self::failure('The uploaded file could not be verified.');
        }

        $size = isset($file['size']) ? (int) $file['size'] : (int) @filesize($file['tmp_name']);
        $name = isset($file['name']) ? (string) $file['name'] : 'logo';

        return self::storeFile($file['tmp_name'], $name, $size, $directory, $options, true);
    }

    /**
     * Validate and move a file. Kept separate from $_FILES so it can be unit tested.
     *
     * @return array{ok:bool, path:string, error:string, meta:array<string,mixed>}
     */
    public static function storeFile($tmpPath, $originalName, $size, $directory, array $options = [], $isUploaded = false)
    {
        $maxBytes = isset($options['max_bytes']) ? (int) $options['max_bytes'] : LOGO_MAX_BYTES;
        $prefix = isset($options['prefix']) ? preg_replace('/[^a-z0-9_-]/i', '', (string) $options['prefix']) : 'image';
        $maxDimension = isset($options['max_dimension']) ? (int) $options['max_dimension'] : 8000;
        $allowed = isset($options['allowed']) && is_array($options['allowed']) ? $options['allowed'] : self::ALLOWED;

        if (!is_file($tmpPath)) {
            return self::failure('The uploaded file could not be read.');
        }
        if ($size <= 0) {
            return self::failure('The uploaded file is empty.');
        }
        if ($size > $maxBytes) {
            return self::failure('The file is too large. Maximum allowed size is ' . format_bytes($maxBytes) . '.');
        }

        $mime = self::detectMime($tmpPath);
        if ($mime === '' || !isset($allowed[$mime])) {
            return self::failure('Please upload a PNG, JPG, GIF, WEBP or ICO image.');
        }

        $extension = $allowed[$mime];
        $meta = ['width' => 0, 'height' => 0, 'mime' => $mime, 'size' => (int) $size];

        if ($extension === 'ico') {
            $header = (string) file_get_contents($tmpPath, false, null, 0, 4);
            if ($header !== "\x00\x00\x01\x00") {
                return self::failure('The file is not a valid ICO image.');
            }
        } else {
            $info = @getimagesize($tmpPath);
            if ($info === false || empty($info[0]) || empty($info[1])) {
                return self::failure('The file is not a valid image.');
            }
            if ($info[0] > $maxDimension || $info[1] > $maxDimension) {
                return self::failure('The image is too large (maximum ' . $maxDimension . ' pixels per side).');
            }
            $meta['width'] = (int) $info[0];
            $meta['height'] = (int) $info[1];
        }

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return self::failure('The upload folder could not be created. Check folder permissions.');
        }
        if (!is_writable($directory)) {
            return self::failure('The upload folder is not writable. Check folder permissions.');
        }

        $base = preg_replace('/\.[a-z0-9]+$/i', '', (string) $originalName);
        $base = substr(preg_replace('/[^a-z0-9]+/i', '-', strtolower($base)), 0, 30);
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'image';
        }

        $fileName = sprintf('%s_%s_%s.%s', $prefix, date('Ymd-His'), bin2hex(random_bytes(4)), $extension);
        $destination = rtrim($directory, '/') . '/' . $fileName;

        $moved = $isUploaded
            ? @move_uploaded_file($tmpPath, $destination)
            : @rename($tmpPath, $destination);

        if (!$moved) {
            // Fall back to copy + unlink (some hosts restrict rename across mounts).
            if (!@copy($tmpPath, $destination)) {
                return self::failure('The image could not be saved. Check folder permissions.');
            }
            @unlink($tmpPath);
        }

        @chmod($destination, 0644);

        $meta['name'] = $fileName;
        $meta['path'] = $destination;

        return [
            'ok'    => true,
            'path'  => $destination,
            'error' => '',
            'meta'  => $meta,
        ];
    }

    /**
     * Detect the real MIME type of a file.
     */
    public static function detectMime($path)
    {
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($path);
            if (is_string($mime) && $mime !== '') {
                return strtolower($mime);
            }
        }
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($path);
            if (is_string($mime) && $mime !== '') {
                return strtolower($mime);
            }
        }
        if (function_exists('getimagesize')) {
            $info = @getimagesize($path);
            if (is_array($info) && !empty($info['mime'])) {
                return strtolower($info['mime']);
            }
        }
        return '';
    }

    /**
     * Delete an uploaded file, refusing to touch anything outside $uploadsRoot.
     */
    public static function delete($absolutePath, $uploadsRoot)
    {
        if (empty($absolutePath)) {
            return false;
        }
        $root = realpath($uploadsRoot);
        $file = realpath($absolutePath);
        if ($root === false || $file === false) {
            return false;
        }
        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $file = str_replace('\\', '/', $file);
        if (strpos($file, $root) !== 0 || !is_file($file)) {
            return false;
        }
        return @unlink($file);
    }

    /**
     * Convert an absolute path inside the app to a public relative path.
     */
    public static function relativePath($absolutePath)
    {
        $root = str_replace('\\', '/', APP_ROOT);
        $path = str_replace('\\', '/', (string) $absolutePath);
        if (strpos($path, $root) === 0) {
            return ltrim(substr($path, strlen($root)), '/');
        }
        return ltrim($path, '/');
    }

    /**
     * @return array{ok:bool, path:string, error:string, meta:array<string,mixed>}
     */
    private static function failure($message)
    {
        return ['ok' => false, 'path' => '', 'error' => $message, 'meta' => []];
    }
}
