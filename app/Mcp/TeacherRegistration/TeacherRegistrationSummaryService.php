<?php

namespace App\Mcp\TeacherRegistration;

use App\Mcp\Exceptions\McpException;
use App\Mcp\Model\ModelClient;

class TeacherRegistrationSummaryService
{
    public function __construct(private readonly ModelClient $model) {}

    public function summarize(array $payload): array
    {
        $nationalId = (string) $payload['national_id'];
        // Avoid spending two minutes asking the model to summarize an empty
        // record. This is also useful as a deterministic health-check for the
        // integration and still clearly tells the caller that no data exists.
        if ($payload['teacher_registration'] === [] && $payload['comments'] === [] && $payload['audit_trails'] === []) {
            return [
                'national_id' => $nationalId,
                'summary_status' => 'completed',
                'application_journey' => [],
                'summary' => 'No teacher registration, comments, or audit trail data was supplied for this national ID.',
                'screening_flags' => [],
                'missing_documents' => [],
                'recommended_review' => [['status' => 'data required', 'reason' => 'Supply teacher registration data, comments, or audit trails for analysis.']],
                'confidence' => 'low',
                'disclaimer' => 'This summary is based only on the supplied information and does not constitute a final decision or endorsement.',
                'model' => 'deterministic-empty-input',
            ];
        }
        $context = ['national_id' => $nationalId, 'teacher_registration' => $payload['teacher_registration'], 'comments' => $payload['comments'], 'audit_trails' => $payload['audit_trails']];
        $schema = ['type' => 'object', 'required' => ['national_id', 'summary_status', 'summary', 'application_journey', 'screening_flags', 'missing_documents', 'recommended_review', 'confidence', 'disclaimer'], 'properties' => ['national_id' => ['type' => 'string'], 'summary_status' => ['type' => 'string', 'enum' => ['completed']], 'applicant' => ['type' => 'object'], 'registration' => ['type' => 'object'], 'application_journey' => ['type' => 'array'], 'summary' => ['type' => 'string'], 'screening_flags' => ['type' => 'array'], 'missing_documents' => ['type' => 'array'], 'recommended_review' => ['type' => 'array'], 'confidence' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']], 'disclaimer' => ['type' => 'string']]];
        $system = 'You summarize Teacher Registration applications for authorized officers. Use only supplied JSON. national_id joins registration, comments, and audit trails. Treat teacher_registrations as current state, audit_trails as history, and comments as human context. Reconstruct the journey, corrections, document changes, status changes, and decisions. Identify possible name, identity, qualification, citizenship/work-permit, and missing-document concerns as screening flags only. Distinguish confirmed facts from concerns and include evidence references. Never make a final approval, rejection, registration, endorsement, or licensing decision. Never invent values. Return only JSON matching the supplied schema.';
        $response = $this->model->generateStructured([['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]], $schema);
        $result = json_decode($response->content, true);
        if (!is_array($result)) throw new McpException('MODEL_OUTPUT_INVALID', 'The AI model returned an invalid summary.', httpStatus: 502);
        $result['national_id'] = $nationalId; $result['summary_status'] = 'completed'; $result['disclaimer'] ??= 'This summary assists the officer and does not make a registration, approval, rejection, or licensing decision.';
        return $result + ['model' => $response->model];
    }
}
