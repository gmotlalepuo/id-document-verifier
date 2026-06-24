# ID Document Verifier API

Laravel 12 / PHP 8.2+ API that downloads an official identity document, enhances its pages, extracts Botswana ID fields with Tesseract OCR, and compares them with supplied identity data.

## Result semantics

- `verified`: every required field was extracted and matched.
- `mismatch`: every field was extracted, but one or more values did not match.
- `inconclusive`: at least one required field could not be extracted. This is deliberately not treated as a mismatch.
- `failed`: the document could not be securely downloaded, decoded, enhanced, or OCR-processed.

Every field includes `comparable`, `match`, `score`, and a reason. ID numbers are exact after removing formatting. Names use normalized fuzzy matching; forenames may be in a different order but all supplied names must be present.

## Requirements

- PHP 8.2+ with `fileinfo`, `json`, `mbstring`, `openssl`, and PDO MySQL
- Composer 2
- MySQL/MariaDB (SQLite can be used for tests)
- [Tesseract OCR](https://github.com/tesseract-ocr/tesseract)
- ImageMagick (`magick`) with PNG/JPEG/TIFF support
- Poppler (`pdftoppm`) for PDF documents

On Windows, put all three executable directories on `PATH`, or set their full executable paths in `.env`.

## Setup

```powershell
cd C:\xampp\htdocs\id-document-verifier
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Create the `id_document_verifier` database, set its database credentials and a strong random `DOCUMENT_VERIFICATION_API_KEY` in `.env`, then run:

```powershell
php artisan migrate
php artisan serve
```

Make sure these writable directories exist in deployments: `storage/app/verification`, `storage/framework/cache/data`, `storage/framework/sessions`, `storage/framework/views`, `storage/logs`, and `bootstrap/cache`.

## API

Health check (no authentication):

```http
GET /api/v1/health
```

It returns HTTP `200` only when all OCR executables are available, otherwise `503` with the missing dependency names.

Verify a document:

```http
POST /api/v1/verifications
X-API-Key: YOUR_DOCUMENT_VERIFICATION_API_KEY
Content-Type: application/json

{
  "document_url": "https://twosixdigitalbw.app/api/v2/document/.../download",
  "surname": "Motlalepuo",
  "forenames": "Garenosi",
  "id_number": "436415528"
}
```

Retrieve the audit result:

```http
GET /api/v1/verifications/{request_id}
X-API-Key: YOUR_DOCUMENT_VERIFICATION_API_KEY
```

Example response:

```json
{
  "success": true,
  "request_id": "0f80a65d-0963-4b58-85b2-c78ec65ae50a",
  "status": "verified",
  "verified": true,
  "ocr": {"confidence": 0.91, "quality": "high"},
  "extracted": {"surname": "MOTLALEPUO", "forenames": "GARENOSI", "id_number": "436415528", "document_type": "botswana_identity_document"},
  "comparison": {
    "status": "verified",
    "all_fields_match": true,
    "fields": {
      "surname": {"provided": "Motlalepuo", "extracted": "MOTLALEPUO", "comparable": true, "match": true, "score": 1},
      "forenames": {"provided": "Garenosi", "extracted": "GARENOSI", "comparable": true, "match": true, "score": 1},
      "id_number": {"provided": "436415528", "extracted": "436415528", "comparable": true, "match": true, "score": 1}
    },
    "summary": {"matched": 3, "mismatched": 0, "unable_to_compare": 0}
  }
}
```

## Security and operations

The downloader accepts HTTPS only, blocks credentials in URLs, validates DNS, rejects private/reserved IPs, revalidates redirects, limits redirects, checks file signatures/MIME types, and enforces a size limit. Temporary original/enhanced files are deleted after processing by default. The database stores only a SHA-256 hash of the source URL.

Run this service behind HTTPS. Restrict the API key, database, and logs; define an audit-record retention period appropriate for Botswana privacy requirements. For higher volume, move verification into a Laravel queue and return `202 Accepted`; the current endpoint processes synchronously.

Tune `OCR_MIN_FIELD_SCORE` using a labeled set of real documents before production. OCR confidence describes text recognition quality; it must not be interpreted as proof that a document is genuine. This service checks data consistency, not document authenticity or tampering.
