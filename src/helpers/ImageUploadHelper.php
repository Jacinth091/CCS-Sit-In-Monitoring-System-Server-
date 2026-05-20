<?php

class ImageUploadHelper {

  private static array $allowedMimes = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/svg+xml',
  ];

  // Map upload type → subdirectory
  private static array $dirs = [
    'icon'      => 'uploads/icons',
    'avatar'    => 'uploads/avatars',
    'lab_image' => 'uploads/lab-images',
    'general'   => 'uploads/general',
  ];

  private static int $maxBytes = 5242880; // 5MB default

  /**
   * Upload an image file and return the relative path.
   *
   * @param array       $file     $_FILES['field_name']
   * @param string      $type     'icon' | 'avatar' | 'lab_image' | 'general'
   * @param string|null $oldPath  Relative path of file to delete on success
   * @return array                ['success' => bool, 'path' => string|null, 'message' => string]
   */
  public static function upload(array $file, string $type = 'general', ?string $oldPath = null): array
  {
    // 1. Check PHP upload error
    if ($file['error'] !== UPLOAD_ERR_OK) {
      return self::fail(self::errorMessage($file['error']));
    }

    // 2. Validate type
    if (!isset(self::$dirs[$type])) {
      return self::fail("Unknown upload type: {$type}");
    }

    // 3. Validate size
    if ($file['size'] > self::$maxBytes) {
      return self::fail("File exceeds the 5MB size limit.");
    }

    // 4. Validate MIME using finfo — never trust $_FILES['type']
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);

    if (!in_array($mime, self::$allowedMimes, true)) {
      return self::fail("File type not allowed. Accepted: JPEG, PNG, GIF, WebP, SVG.");
    }

    // 5. Map MIME to extension
    $ext = match($mime) {
      'image/jpeg'     => 'jpg',
      'image/png'      => 'png',
      'image/gif'      => 'gif',
      'image/webp'     => 'webp',
      'image/svg+xml'  => 'svg',
      default          => 'jpg',
    };

    // 6. Generate a random filename — never use the original name
    $filename = bin2hex(random_bytes(12)) . '.' . $ext;
    $relDir   = self::$dirs[$type];
    $absDir   = self::rootPath($relDir);
    $absPath  = $absDir . DIRECTORY_SEPARATOR . $filename;
    $relPath  = $relDir . '/' . $filename;

    // 7. Ensure directory exists
    if (!is_dir($absDir)) {
      mkdir($absDir, 0755, true);
    }

    // 8. Move file
    if (!move_uploaded_file($file['tmp_name'], $absPath)) {
      return self::fail("Failed to save the file. Check folder permissions.");
    }

    // 9. Delete old file if replacing
    if ($oldPath) {
      self::delete($oldPath);
    }

    return ['success' => true, 'path' => $relPath, 'message' => 'File uploaded successfully.'];
  }

  /**
   * Delete a file by its relative path.
   * Safe — silently ignores missing files.
   */
  public static function delete(string $relPath): void
  {
    $abs = self::rootPath($relPath);
    if (is_file($abs)) {
      unlink($abs);
    }
  }

  // ─── Private ────────────────────────────────────

  private static function rootPath(string $rel): string
  {
    // Two levels up from src/helpers/ → project root
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $rel), DIRECTORY_SEPARATOR);
  }

  private static function fail(string $message): array
  {
    return ['success' => false, 'path' => null, 'message' => $message];
  }

  private static function errorMessage(int $code): string
  {
    return match($code) {
      UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "File is too large.",
      UPLOAD_ERR_PARTIAL    => "File was only partially uploaded.",
      UPLOAD_ERR_NO_FILE    => "No file was uploaded.",
      UPLOAD_ERR_NO_TMP_DIR => "Server is missing a temp folder.",
      UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
      default               => "Upload error (code {$code}).",
    };
  }
}
