<?php
require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';
require_once '../../config/ai.php';
require_once '../../src/helpers/AiContextBuilder.php';
require_once '../../src/helpers/gemini.php';

require_once '../../src/middleware/AiAuthMiddleware.php';

// Get POST data
$input = json_decode(file_get_contents("php://input"), true) ?? [];
$records = $input['records'] ?? [];

// Guard the AI endpoint
$auth = AiAuthMiddleware::guard('report_summary', $db, $input);
$userId = $auth['user_id'];
$role   = $auth['role'];

// Auth: Admins only
$currentUser = requireAdmin();

if (!is_array($records)) {
    sendError(400, 'Invalid request. Records array is required.');
}

$recordCount = count($records);

// Sandbox Mode Check
if (!AI_ENABLED) {
    $sandboxSummary = [
        'headline' => 'Laboratory Usage Report Compiled',
        'summary'  => 'Analyzed ' . $recordCount . ' records. Traffic is heavily concentrated in programming and computer science slots, with average study sessions lasting approximately 90 minutes. Lab capacity remains stable.',
        'metrics'  => [
            [
                'label' => 'Total Volume',
                'value' => (string) $recordCount,
                'desc'  => 'logs analyzed'
            ],
            [
                'label' => 'Primary Purpose',
                'value' => 'Coding',
                'desc'  => 'most frequent reason'
            ],
            [
                'label' => 'Peak Lab Hub',
                'value' => 'Lab 5',
                'desc'  => 'highest traffic volume'
            ]
        ]
    ];
    AiAuthMiddleware::logUsage($userId, $role, 'report_summary', $db);
    sendSuccess(200, 'Sandbox report summary generated', $sandboxSummary, ['_sandbox' => true]);
}

// Live AI Inference
try {
    $contextBuilder = new AiContextBuilder($db);
    $reportContext = $contextBuilder->forReport(array_slice($records, 0, 50)); // slice to avoid payload limits

    $prompt = "You are a professional administrative reporting assistant for a university computer laboratory.\n";
    $prompt .= "Analyze these report rows and generate a summarized view as a raw JSON object.\n";
    $prompt .= "Do not include any conversational text, code blocks, or markdown formatting. Just raw valid JSON.\n\n";

    $prompt .= "## Report Data Payload\n";
    $prompt .= "Total records in full report: " . $recordCount . "\n";
    $prompt .= "Sample of report rows:\n";
    foreach ($reportContext['report_rows'] as $r) {
        $prompt .= "- Student ID: " . ($r['student_id'] ?? 'N/A') . ", Lab: " . ($r['name'] ?? $r['lab_code'] ?? 'N/A') . ", PC: " . ($r['pc_number'] ?? 'N/A') . ", Date: " . ($r['date'] ?? 'N/A') . ", Time: " . ($r['time_in'] ?? '') . " - " . ($r['time_out'] ?? '') . ", Purpose: " . ($r['purpose'] ?? 'N/A') . ", Status: " . ($r['status'] ?? 'N/A') . "\n";
    }

    $prompt .= "\n## Instructions for output format:\n";
    $prompt .= "Output exactly a JSON object containing:\n";
    $prompt .= "- 'headline': High-level report overview (max 6 words, e.g. 'Lab 5 Leads Weekly Utilization')\n";
    $prompt .= "- 'summary': One concise summarizing paragraph (max 40 words, professional, outlining patterns/insights)\n";
    $prompt .= "- 'metrics': Array of exactly 3 objects representing core metrics. Each metric must contain:\n";
    $prompt .= "  - 'label': Short card name (max 3 words, e.g. 'Peak Hour' or 'Busy Lab')\n";
    $prompt .= "  - 'value': Data value (max 8 characters, e.g. 'CS-202', 'Coding', 'Lab 5')\n";
    $prompt .= "  - 'desc': Detail caption (max 5 words, e.g. 'most active student year' or 'hours logged')\n\n";

    $prompt .= "Example structure:\n";
    $prompt .= '{\n';
    $prompt .= '  "headline": "High Programming Lab Utilization",\n';
    $prompt .= '  "summary": "Laboratory reports indicate significant engagement in programming sessions, with Lab 5 being the primary hub. Average session times align with standard laboratory schedules.",\n';
    $prompt .= '  "metrics": [\n';
    $prompt .= '    {"label": "Total Count", "value": "' . $recordCount . '", "desc": "active logs analyzed"},\n';
    $prompt .= '    {"label": "Top Program", "value": "BSCS", "desc": "most frequent course logged"},\n';
    $prompt .= '    {"label": "Peak Hub", "value": "Lab 5", "desc": "highest traffic laboratory"}\n';
    $prompt .= '  ]\n';
    $prompt .= '}\n';

    $rawResponse = callGemini($prompt);

    if ($rawResponse === null) {
        $aiError = getLastGeminiError();
        if ($aiError && $aiError['http_code'] === 429) {
            // Rate limited! Fallback to sandbox/mock summary gracefully
            $sandboxSummary = [
                'headline' => 'Laboratory Usage Report Compiled',
                'summary'  => 'Analyzed ' . $recordCount . ' records. Traffic is heavily concentrated in programming and computer science slots, with average study sessions lasting approximately 90 minutes. Lab capacity remains stable.',
                'metrics'  => [
                    [
                        'label' => 'Total Volume',
                        'value' => (string) $recordCount,
                        'desc'  => 'logs analyzed'
                    ],
                    [
                        'label' => 'Primary Purpose',
                        'value' => 'Coding',
                        'desc'  => 'most frequent reason'
                    ],
                    [
                        'label' => 'Peak Lab Hub',
                        'value' => 'Lab 5',
                        'desc'  => 'highest traffic volume'
                    ]
                ],
                'is_fallback' => true,
                'fallback_reason' => 'Rate limit cooldown active'
            ];
            AiAuthMiddleware::logUsage($userId, $role, 'report_summary', $db);
            sendSuccess(200, 'AI provider rate-limited. Serving local report summary.', $sandboxSummary);
        }

        $isDev = ($_ENV['APP_ENV'] ?? 'production') !== 'production';
        sendError(502, 'Failed to summarize report from AI provider.', $isDev ? $aiError : null);
    }

    $cleanedJson = stripMarkdownFences($rawResponse);
    $summary = json_decode($cleanedJson, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($summary)) {
        error_log("Gemini Report Summary Parse Error on: " . $rawResponse);
        sendError(500, 'AI response format was invalid. Please try again.');
    }

    AiAuthMiddleware::logUsage($userId, $role, 'report_summary', $db);
    sendSuccess(200, 'AI report summary generated successfully', $summary);

} catch (Exception $e) {
    sendError(500, 'Server error during report summary extraction.', $e);
}
