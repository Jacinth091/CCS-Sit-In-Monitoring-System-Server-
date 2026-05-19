<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

requireAdmin();

$filters = [
    'from'   => $_GET['from'] ?? null,
    'to'     => $_GET['to'] ?? null,
    'lab_id' => $_GET['lab_id'] ?? null,
    'purpose'=> $_GET['purpose'] ?? null,
];

$type = $_GET['type'] ?? 'json'; // json | csv | pdf

try {
    $reportModel = new Report($db);
    $rows = $reportModel->getSitinReport($filters);
    $total = $reportModel->getSitinCount($filters);

    // ─── CSV Export ───
    if ($type === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sitin_report_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        // Header row
        fputcsv($output, ['Student ID', 'Student Name', 'Laboratory', 'Purpose', 'Time In', 'Time Out', 'Duration (min)', 'Status']);
        
        foreach ($rows as $row) {
            fputcsv($output, [
                $row['student_id'],
                $row['student_name'],
                $row['name'],
                $row['purpose'],
                $row['time_in'],
                $row['time_out'] ?? '',
                $row['duration_minutes'] ?? '',
                $row['status']
            ]);
        }
        
        fclose($output);
        exit();
    }

    // ─── PDF Export ───
    if ($type === 'pdf') {
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        
        $pdf->SetCreator('CCS Sit-In Monitoring System');
        $pdf->SetAuthor('CCS Admin');
        $pdf->SetTitle('Sit-In Usage Report');
        $pdf->SetSubject('Laboratory Usage Report');
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->SetMargins(12, 12, 12);
        $pdf->AddPage();

        // ── Title ──
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'CCS Sit-In Monitoring — Usage Report', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, 'Generated: ' . date('F j, Y \a\t g:i A'), 0, 1, 'C');
        $pdf->Ln(3);

        // ── Filter Summary ──
        $filterParts = [];
        if (!empty($filters['from'])) $filterParts[] = 'From: ' . $filters['from'];
        if (!empty($filters['to']))   $filterParts[] = 'To: ' . $filters['to'];
        if (!empty($filters['lab_id'])) $filterParts[] = 'Lab ID: ' . $filters['lab_id'];
        if (!empty($filters['purpose'])) $filterParts[] = 'Purpose: ' . $filters['purpose'];
        
        if (count($filterParts) > 0) {
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->Cell(0, 5, 'Filters: ' . implode(' | ', $filterParts), 0, 1, 'L');
        }
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 5, 'Total Records: ' . $total, 0, 1, 'L');
        $pdf->Ln(4);

        // ── Table Header ──
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(0, 31, 63);    // Navy
        $pdf->SetTextColor(234, 216, 177); // Sand
        
        $w = [30, 50, 35, 40, 40, 40, 20, 20];
        $headers = ['Student ID', 'Name', 'Lab', 'Purpose', 'Time In', 'Time Out', 'Duration', 'Status'];
        
        for ($i = 0; $i < count($headers); $i++) {
            $pdf->Cell($w[$i], 8, $headers[$i], 1, 0, 'C', true);
        }
        $pdf->Ln();

        // ── Table Body ──
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(0, 0, 0);
        $fill = false;

        foreach ($rows as $row) {
            if ($fill) {
                $pdf->SetFillColor(253, 251, 247); // Warm white
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }

            $timeIn = $row['time_in'] ? date('M j, g:i A', strtotime($row['time_in'])) : '';
            $timeOut = $row['time_out'] ? date('M j, g:i A', strtotime($row['time_out'])) : '—';
            $duration = $row['duration_minutes'] ? $row['duration_minutes'] . 'm' : '—';

            $pdf->Cell($w[0], 7, $row['student_id'], 1, 0, 'C', true);
            $pdf->Cell($w[1], 7, $row['student_name'], 1, 0, 'L', true);
            $pdf->Cell($w[2], 7, $row['name'], 1, 0, 'C', true);
            $pdf->Cell($w[3], 7, $row['purpose'], 1, 0, 'L', true);
            $pdf->Cell($w[4], 7, $timeIn, 1, 0, 'C', true);
            $pdf->Cell($w[5], 7, $timeOut, 1, 0, 'C', true);
            $pdf->Cell($w[6], 7, $duration, 1, 0, 'C', true);
            $pdf->Cell($w[7], 7, ucfirst($row['status']), 1, 0, 'C', true);
            $pdf->Ln();

            $fill = !$fill;
        }

        // ── Output ──
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="sitin_report_' . date('Y-m-d') . '.pdf"');
        $pdf->Output('sitin_report_' . date('Y-m-d') . '.pdf', 'D');
        exit();
    }

    // ─── Default: JSON ───
    sendSuccess(200, "Report generated successfully", $rows, ['total_records' => $total]);

} catch (Exception $e) {
    sendError(500, "Failed to generate report", $e);
}
