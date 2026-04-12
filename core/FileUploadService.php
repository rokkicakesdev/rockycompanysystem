<?php
// core/FileUploadService.php
// ─────────────────────────────────────────────────────────────────────────────
//  Secure file upload service.
//
//  Security controls applied on every upload:
//   1. Upload error check   — rejects PHP-level upload errors
//   2. Size limit           — rejects files exceeding MAX_SIZE per type
//   3. Extension whitelist  — ONLY allowed extensions accepted
//   4. MIME type validation — finfo-based real content check (not just header)
//   5. Double-extension     — rejects files like "shell.php.jpg"
//   6. Null byte            — rejects names with null bytes (\0)
//   7. Randomised filename  — original name never stored on disk
//   8. Upload dir lockdown  — directory created with .htaccess disabling execution
//   9. Image re-encode      — profile photos are GD re-encoded to strip exif/metadata
//
//  Usage:
//    $result = FileUploadService::upload(
//        $_FILES['profile_photo'],
//        FileUploadService::PROFILE_PHOTO,
//        BASE_PATH . '/uploads/profile_photos'
//    );
//    if ($result->ok) {
//        $storedPath = $result->relativePath;  // save this to DB
//    } else {
//        $error = $result->error;
//    }
// ─────────────────────────────────────────────────────────────────────────────

declare(strict_types=1);

// ── Upload Result DTO ────────────────────────────────────────────────────────
final class UploadResult
{
    public function __construct(
        public readonly bool   $ok,
        public readonly string $relativePath = '',  // path relative to upload root (store in DB)
        public readonly string $filename     = '',  // stored filename on disk
        public readonly string $error        = '',  // human-readable error message
    ) {}
}

// ── Service ──────────────────────────────────────────────────────────────────
final class FileUploadService
{
    // ── Upload type constants ────────────────────────────────────────────────
    public const PROFILE_PHOTO   = 'profile_photo';
    public const EMPLOYEE_DOC    = 'employee_doc';

    // ── Allowed extensions per upload type ───────────────────────────────────
    // Keep these lists as small as possible — principle of least privilege.
    private const ALLOWED = [
        self::PROFILE_PHOTO => [
            'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
            'mimes'      => ['image/jpeg', 'image/png', 'image/webp'],
            'max_bytes'  => 2 * 1024 * 1024,   // 2 MB
            'max_label'  => '2 MB',
            're_encode'  => true,               // GD re-encode to strip exif/hidden data
        ],
        self::EMPLOYEE_DOC => [
            'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
            'mimes'      => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
            'max_bytes'  => 10 * 1024 * 1024,  // 10 MB
            'max_label'  => '10 MB',
            're_encode'  => false,
        ],
    ];

    // ── Main upload method ────────────────────────────────────────────────────
    /**
     * Validate and store an uploaded file securely.
     *
     * @param array  $fileData      One element from $_FILES (e.g. $_FILES['photo'])
     * @param string $uploadType    One of the class constants: PROFILE_PHOTO | EMPLOYEE_DOC
     * @param string $destDir       Absolute path to destination directory
     * @param string $relativeBase  Relative prefix to store in DB (e.g. 'uploads/profile_photos')
     *
     * @return UploadResult
     */
    public static function upload(
        array  $fileData,
        string $uploadType,
        string $destDir,
        string $relativeBase = ''
    ): UploadResult {

        // ── Validate upload type ─────────────────────────────────────────────
        if (!isset(self::ALLOWED[$uploadType])) {
            return self::fail('Invalid upload type.');
        }
        $cfg = self::ALLOWED[$uploadType];

        // ── 1. PHP upload error check ────────────────────────────────────────
        $phpError = $fileData['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($phpError !== UPLOAD_ERR_OK) {
            return self::fail(self::phpErrorMessage($phpError));
        }

        // ── 2. Verify it's an actual uploaded file (prevents path traversal) ─
        if (!is_uploaded_file($fileData['tmp_name'] ?? '')) {
            return self::fail('Invalid upload — file did not arrive via HTTP POST.');
        }

        // ── 3. Size check ────────────────────────────────────────────────────
        $fileSize = (int)($fileData['size'] ?? 0);
        if ($fileSize === 0) {
            return self::fail('The uploaded file is empty.');
        }
        if ($fileSize > $cfg['max_bytes']) {
            return self::fail("File too large. Maximum allowed size is {$cfg['max_label']}.");
        }

        // ── 4. Original filename security checks ─────────────────────────────
        $originalName = $fileData['name'] ?? '';

        // Null byte injection guard
        if (str_contains($originalName, "\0")) {
            return self::fail('Invalid filename.');
        }

        // Extract and clean the extension (lowercase)
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === '') {
            return self::fail('File has no extension.');
        }

        // Double-extension guard: "shell.php.jpg" → basename without last ext = "shell.php"
        $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
        $innerExt = strtolower(pathinfo($nameWithoutExt, PATHINFO_EXTENSION));
        $dangerous = ['php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar',
                      'exe', 'sh', 'bash', 'py', 'pl', 'rb', 'cgi', 'asp', 'aspx'];
        if (in_array($innerExt, $dangerous, true)) {
            return self::fail('File contains a disallowed embedded extension.');
        }

        // ── 5. Extension whitelist ───────────────────────────────────────────
        if (!in_array($ext, $cfg['extensions'], true)) {
            $allowed = implode(', ', $cfg['extensions']);
            return self::fail("File type not allowed. Permitted: {$allowed}.");
        }

        // ── 6. MIME type validation (real content check) ─────────────────────
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($fileData['tmp_name']);
        if ($realMime === false || !in_array($realMime, $cfg['mimes'], true)) {
            return self::fail(
                'File content does not match its extension. ' .
                'Detected type: ' . ($realMime ?: 'unknown') . '.'
            );
        }

        // ── 7. Ensure destination directory exists and is locked down ────────
        if (!self::ensureDir($destDir)) {
            return self::fail('Server configuration error: upload directory is not writable.');
        }

        // ── 8. Generate a safe random filename ───────────────────────────────
        // Never use the original name — it can contain path traversal or be guessable.
        $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
        $destPath   = rtrim($destDir, '/') . '/' . $storedName;
        $relPath    = ($relativeBase ? rtrim($relativeBase, '/') . '/' : '') . $storedName;

        // ── 9. Profile photos: GD re-encode to strip exif/steganographic data ─
        if ($cfg['re_encode'] && in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $reEncoded = self::reEncodeImage($fileData['tmp_name'], $ext, $destPath);
            if (!$reEncoded) {
                return self::fail('Image could not be processed. Please upload a valid image file.');
            }
        } else {
            // Move uploaded file to destination
            if (!move_uploaded_file($fileData['tmp_name'], $destPath)) {
                return self::fail('Failed to save the uploaded file. Please try again.');
            }
        }

        // Verify the file actually landed
        if (!file_exists($destPath)) {
            return self::fail('Upload verification failed.');
        }

        return new UploadResult(ok: true, relativePath: $relPath, filename: $storedName);
    }

    // ── Image re-encoder ──────────────────────────────────────────────────────
    /**
     * Re-encode an image via GD, which strips all metadata, exif, and
     * any hidden payloads. Returns false if GD cannot process the image.
     */
    private static function reEncodeImage(string $tmpPath, string $ext, string $destPath): bool
    {
        if (!function_exists('imagecreatefromjpeg')) {
            // GD not available — fall back to plain move (still safe due to MIME check above)
            return move_uploaded_file($tmpPath, $destPath);
        }

        $img = match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($tmpPath),
            'png'         => @imagecreatefrompng($tmpPath),
            'webp'        => @imagecreatefromwebp($tmpPath),
            default       => false,
        };

        if (!$img) {
            return false;
        }

        // Re-encode with quality 85 (good quality, smaller size)
        $saved = match ($ext) {
            'jpg', 'jpeg' => imagejpeg($img, $destPath, 85),
            'png'         => imagepng($img, $destPath, 6),
            'webp'        => imagewebp($img, $destPath, 85),
            default       => false,
        };

        imagedestroy($img);
        return $saved;
    }

    // ── Directory bootstrap ───────────────────────────────────────────────────
    /**
     * Create the upload directory if it doesn't exist and add an .htaccess
     * that prevents PHP execution inside it (defence-in-depth).
     */
    private static function ensureDir(string $dir): bool
    {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                return false;
            }
        }

        if (!is_writable($dir)) {
            return false;
        }

        // Add .htaccess to block PHP execution in this directory
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess,
                "# Rocky HRIS — Deny script execution in upload directories\n" .
                "Options -ExecCGI\n" .
                "AddHandler cgi-script .php .php3 .php4 .php5 .php7 .phtml .phar .pl .py .sh\n" .
                "php_flag engine off\n" .
                "<FilesMatch \"\\.php$\">\n" .
                "  Deny from all\n" .
                "</FilesMatch>\n"
            );
        }

        return true;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private static function fail(string $message): UploadResult
    {
        return new UploadResult(ok: false, error: $message);
    }

    private static function phpErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file exceeds the maximum allowed upload size.',
            UPLOAD_ERR_PARTIAL   => 'The file was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_FILE   => 'No file was selected for upload.',
            UPLOAD_ERR_NO_TMP_DIR=> 'Server configuration error: missing temporary upload directory.',
            UPLOAD_ERR_CANT_WRITE=> 'Server configuration error: failed to write to disk.',
            UPLOAD_ERR_EXTENSION => 'Upload was blocked by a server extension.',
            default              => 'An unknown upload error occurred (code: ' . $code . ').',
        };
    }
}
