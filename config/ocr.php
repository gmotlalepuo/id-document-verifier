<?php
return [
    'tesseract' => env('OCR_TESSERACT_PATH', 'tesseract'),
    'pdftoppm' => env('OCR_PDFTOPPM_PATH', 'pdftoppm'),
    'magick' => env('OCR_IMAGE_MAGICK_PATH', 'magick'),
    'language' => env('OCR_LANGUAGE', 'eng'), 'timeout' => (int) env('OCR_TIMEOUT', 60),
    'min_score' => (float) env('OCR_MIN_FIELD_SCORE', .82),
    'max_download_bytes' => (int) env('OCR_MAX_DOWNLOAD_MB', 15) * 1024 * 1024,
    'allow_private_urls' => (bool) env('OCR_ALLOW_PRIVATE_URLS', false),
    'keep_files' => (bool) env('OCR_KEEP_FILES', false),
    'api_key' => env('DOCUMENT_VERIFICATION_API_KEY'),
];
