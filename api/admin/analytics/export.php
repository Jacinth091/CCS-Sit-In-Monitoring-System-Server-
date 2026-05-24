<?php

require_once __DIR__ . '/../../../includes/cors.php';
require_once __DIR__ . '/../../../includes/initialize.php';

requireAdmin();

// Accept both GET and POST
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$from = $input['from'] ?? $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to   = $input['to']   ?? $_GET['to']   ?? date('Y-m-d');
$type = $input['type'] ?? $_GET['type'] ?? 'csv';
$days = $input['days'] ?? $_GET['days'] ?? 30;
$aiInsights = $input['ai_insights'] ?? null; // Array of {type, title, description, value}

// Helper: 24h integer to 12h string
function to12h($h) {
    $h = (int)$h;
    if ($h === 0) return '12:00 AM';
    if ($h < 12) return $h . ':00 AM';
    if ($h === 12) return '12:00 PM';
    return ($h - 12) . ':00 PM';
}

try {
    $dashboard = new Dashboard($db);

    $byPurpose  = $dashboard->getSessionsByPurpose($from, $to);
    $byLab      = $dashboard->getSessionsByLab($from, $to);
    $dailyTrend = $dashboard->getDailyTrend($days);
    $peakHours  = $dashboard->getPeakHours($from, $to);

    $query = "
        SELECT s.student_id, CONCAT(s.first_name, ' ', s.last_name) as student_name,
               COALESCE(SUM(EXTRACT(EPOCH FROM (sil.time_out - sil.time_in)) / 3600), 0) as value
        FROM students s
        JOIN sit_in_logs sil ON s.student_id = sil.student_id
        WHERE sil.time_out IS NOT NULL AND sil.deleted_at IS NULL
        GROUP BY s.student_id, s.first_name, s.last_name
        ORDER BY value DESC
        LIMIT 5
    ";
    $stmt = $db->query($query);
    $leaderboard = [];
    $rank = 1;
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $leaderboard[] = [
            'rank' => $rank++,
            'student_name' => $row['student_name'],
            'value' => round($row['value'], 1) . 'h'
        ];
    }

    $totalSessions = array_sum(array_column($byPurpose, 'count'));
    $activeLabsCount = count($byLab);
    $purposeCategoriesCount = count($byPurpose);

    $peakHourStr = '—';
    if (!empty($peakHours)) {
        $peakHourStr = to12h($peakHours[0]['hour']);
    }

    // ─── CSV Export ───
    if ($type === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sitin_analytics_' . $from . '_to_' . $to . '.csv"');

        $output = fopen('php://output', 'w');

        fputcsv($output, ['UNIVERSITY OF CEBU - MAIN CAMPUS']);
        fputcsv($output, ['College of Computer Studies — Sit-In Monitoring System']);
        fputcsv($output, ['ANALYTICS REPORT']);
        fputcsv($output, ['Date Range', $from . ' to ' . $to]);
        fputcsv($output, ['Generated On', date('F j, Y g:i A')]);
        fputcsv($output, []);

        fputcsv($output, ['=== KEY PERFORMANCE INDICATORS ===']);
        fputcsv($output, ['Metric', 'Value']);
        fputcsv($output, ['Total Logged Sessions', $totalSessions]);
        fputcsv($output, ['Active Laboratories', $activeLabsCount]);
        fputcsv($output, ['Purpose Categories', $purposeCategoriesCount]);
        fputcsv($output, ['Busiest Hour', $peakHourStr]);
        if (!empty($leaderboard)) {
            fputcsv($output, ['Top Student Hours', $leaderboard[0]['value'] . ' (' . $leaderboard[0]['student_name'] . ')']);
        }
        fputcsv($output, []);

        fputcsv($output, ['=== LABORATORY TRAFFIC FREQUENCY ===']);
        fputcsv($output, ['Lab Code', 'Laboratory Name', 'Sessions Logged', 'Usage Share (%)']);
        $totalLabLogs = array_sum(array_column($byLab, 'count')) ?: 1;
        foreach ($byLab as $lab) {
            $share = round(($lab['count'] / $totalLabLogs) * 100, 1);
            fputcsv($output, [$lab['lab_code'], $lab['label'], $lab['count'], $share . '%']);
        }
        fputcsv($output, []);

        fputcsv($output, ['=== SIT-IN PURPOSE DISTRIBUTION ===']);
        fputcsv($output, ['Purpose / Activity', 'Sessions Logged', 'Share (%)']);
        $totalPurposeLogs = array_sum(array_column($byPurpose, 'count')) ?: 1;
        foreach ($byPurpose as $purp) {
            $share = round(($purp['count'] / $totalPurposeLogs) * 100, 1);
            fputcsv($output, [$purp['label'], $purp['count'], $share . '%']);
        }
        fputcsv($output, []);

        fputcsv($output, ['=== PEAK ENGAGEMENT HOURS ===']);
        fputcsv($output, ['Hour', 'Sessions Logged', 'Share (%)']);
        $totalHourLogs = array_sum(array_column($peakHours, 'count')) ?: 1;
        foreach ($peakHours as $hr) {
            $share = round(($hr['count'] / $totalHourLogs) * 100, 1);
            fputcsv($output, [to12h($hr['hour']), $hr['count'], $share . '%']);
        }
        fputcsv($output, []);

        fputcsv($output, ['=== LEADERBOARD (TOP VALIDATED STUDENTS) ===']);
        fputcsv($output, ['Rank', 'Student Name', 'Accumulated Hours']);
        foreach ($leaderboard as $student) {
            fputcsv($output, [$student['rank'], $student['student_name'], $student['value']]);
        }

        // AI Insights (if available)
        if (!empty($aiInsights) && is_array($aiInsights)) {
            fputcsv($output, []);
            fputcsv($output, ['=== AI SYSTEM DIAGNOSTIC INSIGHTS ===']);
            fputcsv($output, ['Type', 'Title', 'Description', 'Metric']);
            foreach ($aiInsights as $insight) {
                fputcsv($output, [
                    strtoupper($insight['type'] ?? 'general'),
                    $insight['title'] ?? '',
                    $insight['description'] ?? '',
                    $insight['value'] ?? ''
                ]);
            }
        }

        fclose($output);
        exit();
    }

    // ─── PDF Export ───
    if ($type === 'pdf') {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        $pdf->SetCreator('CCS Sit-In Monitoring System');
        $pdf->SetAuthor('CCS Admin');
        $pdf->SetTitle('System Analytics Executive Report');
        $pdf->SetSubject('Detailed Analytics Report');
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->SetMargins(15, 15, 15);
        
        // Color palette
        $navy = [0, 31, 63];
        $sand = [234, 216, 177];
        $chartColors = [
            [0, 31, 63], [58, 109, 140], [106, 154, 176], [234, 216, 177], [143, 189, 211],
            [200, 160, 120], [100, 140, 100], [180, 120, 100]
        ];

        // ════════════════════════════════════════════
        // PAGE 1: OVERVIEW & KPI
        // ════════════════════════════════════════════
        $pdf->AddPage();

        // ── Header Banner ──
        $pdf->SetFillColor(...$navy);
        $pdf->Rect(0, 0, 210, 42, 'F');
        
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 15);
        $pdf->SetY(8);
        $pdf->Cell(0, 8, 'UNIVERSITY OF CEBU - MAIN CAMPUS', 0, 1, 'C');
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 5, 'COLLEGE OF COMPUTER STUDIES', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(...$sand);
        $pdf->Cell(0, 5, 'CCS Sit-In Monitoring & Analytics Intelligence Report', 0, 1, 'C');
        
        $pdf->SetY(28);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColor(200, 200, 200);
        $pdf->Cell(0, 5, 'DATE RANGE: ' . date('M j, Y', strtotime($from)) . ' — ' . date('M j, Y', strtotime($to)) . '  |  GENERATED: ' . date('M j, Y g:i A'), 0, 1, 'C');

        $pdf->SetY(48);
        $pdf->SetTextColor(0, 0, 0);

        // ── Section 1: KPI Statistics ──
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(...$navy);
        $pdf->Cell(0, 7, '1. OPERATIONAL KEY PERFORMANCE INDICATORS (KPIs)', 0, 1, 'L');
        $pdf->Ln(2);
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        
        $topStudentStr = !empty($leaderboard) ? $leaderboard[0]['student_name'] . ' (' . $leaderboard[0]['value'] . ')' : 'N/A';
        
        $kpiHtml = '
        <table cellpadding="6" border="1" style="border-color:#ccc; background-color:#fafafa;">
            <tr style="background-color:#001f3f; color:#ead8b1; font-weight:bold;">
                <th width="50%">Performance Metric</th>
                <th width="50%" align="center">Measurement / Value</th>
            </tr>
            <tr>
                <td><b>Total Logged Sit-in Sessions</b></td>
                <td align="center">' . $totalSessions . ' sessions</td>
            </tr>
            <tr>
                <td><b>Active Tracked Laboratories</b></td>
                <td align="center">' . $activeLabsCount . ' labs</td>
            </tr>
            <tr>
                <td><b>Purpose Categories Logged</b></td>
                <td align="center">' . $purposeCategoriesCount . ' categories</td>
            </tr>
            <tr>
                <td><b>Peak Busy Session Hour</b></td>
                <td align="center">' . $peakHourStr . '</td>
            </tr>
            <tr>
                <td><b>Top Performing Student (Validations)</b></td>
                <td align="center">' . $topStudentStr . '</td>
            </tr>
        </table>';
        $pdf->writeHTML($kpiHtml, true, false, false, false, '');
        $pdf->Ln(6);

        // ── Section 2: Laboratory Usage Table ──
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(...$navy);
        $pdf->Cell(0, 7, '2. LABORATORY USAGE FREQUENCY & TRAFFIC SHARES', 0, 1, 'L');
        $pdf->Ln(2);
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        
        $labRowsHtml = '';
        $totalLabLogs = array_sum(array_column($byLab, 'count')) ?: 1;
        foreach ($byLab as $lab) {
            $share = round(($lab['count'] / $totalLabLogs) * 100, 1);
            $labRowsHtml .= '
            <tr>
                <td>' . htmlspecialchars($lab['lab_code']) . '</td>
                <td>' . htmlspecialchars($lab['label']) . '</td>
                <td align="center">' . $lab['count'] . '</td>
                <td align="center">' . $share . '%</td>
            </tr>';
        }
        
        $labHtml = '
        <table cellpadding="5" border="1" style="border-color:#ccc;">
            <tr style="background-color:#3a6d8c; color:#fff; font-weight:bold;">
                <th width="20%">Lab Code</th>
                <th width="45%">Laboratory Name</th>
                <th width="17%" align="center">Logs Count</th>
                <th width="18%" align="center">Usage Share</th>
            </tr>
            ' . $labRowsHtml . '
        </table>';
        $pdf->writeHTML($labHtml, true, false, false, false, '');
        $pdf->Ln(6);

        // ════════════════════════════════════════════
        // PAGE 2: LABORATORY VISUALS
        // ════════════════════════════════════════════
        $pdf->AddPage();

        // Laboratory Horizontal Bar Chart (Dedicated Page)
        if (!empty($byLab)) {
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetTextColor(...$navy);
            $pdf->Cell(0, 7, '3. VISUALIZED TRAFFIC SHARE BY LABORATORY', 0, 1, 'L');
            $pdf->Ln(4);

            $barStartX = 40;
            $barStartY = $pdf->GetY();
            $barMaxW = 130;
            $barH = 8;
            $maxL = max(array_column($byLab, 'count')) ?: 1;

            foreach ($byLab as $idx => $lab) {
                // Check for page break within the chart
                if ($pdf->GetY() > 270) {
                    $pdf->AddPage();
                    $barStartY = $pdf->GetY() + 5;
                }
                
                $w = ($lab['count'] / $maxL) * $barMaxW;
                $ci = $idx % count($chartColors);
                
                $pdf->SetTextColor(60, 60, 60);
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetXY(15, $barStartY);
                $pdf->Cell(23, $barH, $lab['lab_code'], 0, 0, 'R');

                $pdf->SetFillColor(...$chartColors[$ci]);
                $pdf->Rect($barStartX, $barStartY + 0.5, $w, $barH - 1, 'F');
                
                $pdf->SetXY($barStartX + $w + 2, $barStartY);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->Cell(20, $barH, $lab['count'] . ' logs', 0, 1, 'L');
                
                $barStartY += $barH + 2;
                $pdf->SetY($barStartY);
            }
        }

        // ════════════════════════════════════════════
        // PAGE 3: DISTRIBUTIONS & INTERPRETATION
        // ════════════════════════════════════════════
        $pdf->AddPage();

        // ── Section 4: Purpose Distribution ──
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(...$navy);
        $pdf->Cell(0, 7, '4. SIT-IN PURPOSE DISTRIBUTION BREAKDOWN', 0, 1, 'L');
        $pdf->Ln(2);
        
        $totalPurposeLogs = array_sum(array_column($byPurpose, 'count')) ?: 1;

        // Pie Chart for Purposes
        $pieCenterX = 55;
        $pieCenterY = $pdf->GetY() + 35;
        $pieRadius = 30;
        $startAngle = 0;
        foreach ($byPurpose as $idx => $purp) {
            if ($idx >= 12) break; // Limit visuals slightly for aesthetics, but table shows all
            $angle = ($purp['count'] / $totalPurposeLogs) * 360;
            $color = $chartColors[$idx % count($chartColors)];
            $pdf->SetFillColor($color[0], $color[1], $color[2]);
            $pdf->PieSector($pieCenterX, $pieCenterY, $pieRadius, $startAngle, $startAngle + $angle, 'FD', false, 0, 2);
            $startAngle += $angle;
        }

        // Legend for Purpose Pie
        $legendX = $pieCenterX + $pieRadius + 15;
        $legendY = $pieCenterY - 25;
        $pdf->SetFont('helvetica', '', 8);
        foreach ($byPurpose as $idx => $purp) {
            if ($idx >= 12) break;
            $ci = $idx % count($chartColors);
            $pdf->SetFillColor(...$chartColors[$ci]);
            $pdf->Rect($legendX, $legendY, 4, 4, 'F');
            $pdf->SetXY($legendX + 6, $legendY - 0.5);
            $pdf->Cell(80, 5, $purp['label'] . ' — ' . $purp['count'] . ' (' . round(($purp['count']/$totalPurposeLogs)*100, 1) . '%)', 0, 1);
            $legendY += 6;
        }
        $pdf->SetY(max($pieCenterY + $pieRadius + 10, $legendY + 5));

        // Purpose Table (Shows ALL)
        $purposeRowsHtml = '';
        foreach ($byPurpose as $purp) {
            $share = round(($purp['count'] / $totalPurposeLogs) * 100, 1);
            $purposeRowsHtml .= '
            <tr>
                <td>' . htmlspecialchars($purp['label']) . '</td>
                <td align="center">' . $purp['count'] . '</td>
                <td align="center">' . $share . '%</td>
            </tr>';
        }
        
        $purposeHtml = '
        <table cellpadding="5" border="1" style="border-color:#ccc;">
            <tr style="background-color:#6a9ab0; color:#fff; font-weight:bold;">
                <th width="60%">Activity / Purpose Category</th>
                <th width="20%" align="center">Logs Count</th>
                <th width="20%" align="center">Percentage Share</th>
            </tr>
            ' . $purposeRowsHtml . '
        </table>';
        $pdf->writeHTML($purposeHtml, true, false, false, false, '');
        $pdf->Ln(6);

        // Data Interpretation Summary
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(...$navy);
        $pdf->Cell(0, 7, 'OPERATIONAL INTELLIGENCE & INTERPRETATION', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(40, 40, 40);

        $peakLab = !empty($byLab) ? $byLab[0]['label'] : 'N/A';
        $peakPurp = !empty($byPurpose) ? $byPurpose[0]['label'] : 'N/A';
        $topShare = !empty($byPurpose) ? round(($byPurpose[0]['count'] / $totalPurposeLogs) * 100, 1) : 0;
        
        $interpretation = "Operational metrics identify <b>" . $peakLab . "</b> as the primary activity hub. Student engagement is predominantly focused on <b>" . $peakPurp . "</b> (" . $topShare . "%), with peak historical engagement clustering around <b>" . $peakHourStr . "</b>. ";
        $interpretation .= "Strategic resource allocation during these high-volume windows is recommended to ensure system stability and student satisfaction.";

        $pdf->SetFillColor(250, 250, 250);
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->writeHTMLCell(0, 0, '', '', '<div style="background-color:#fafafa; border:1px solid #ddd; padding:10px;">' . $interpretation . '</div>', 0, 1, true, true, 'J');
        $pdf->Ln(8);

        // ════════════════════════════════════════════
        // PAGE 4: PEAK HOURS & INSIGHTS
        // ════════════════════════════════════════════
        $pdf->AddPage();

        // ── Section 5: Peak Hours ──
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(...$navy);
        $pdf->Cell(0, 7, '5. BUSY HOURLY DISTRIBUTION (12-HOUR FORMAT)', 0, 1, 'L');
        $pdf->Ln(2);
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        
        $hourRowsHtml = '';
        $totalHourLogs = array_sum(array_column($peakHours, 'count')) ?: 1;
        foreach ($peakHours as $hr) {
            $share = round(($hr['count'] / $totalHourLogs) * 100, 1);
            $hourRowsHtml .= '
            <tr>
                <td align="center">' . to12h($hr['hour']) . '</td>
                <td align="center">' . $hr['count'] . '</td>
                <td align="center">' . $share . '%</td>
            </tr>';
        }
        
        $hourHtml = '
        <table cellpadding="5" border="1" style="border-color:#ccc;">
            <tr style="background-color:#8fbdd3; color:#001f3f; font-weight:bold;">
                <th width="40%" align="center">Hour Interval</th>
                <th width="30%" align="center">Logs Count</th>
                <th width="30%" align="center">Busy Share Ratio</th>
            </tr>
            ' . $hourRowsHtml . '
        </table>';
        $pdf->writeHTML($hourHtml, true, false, false, false, '');
        $pdf->Ln(8);

        // ── Section 6: AI System Diagnostic Insights ──
        if (!empty($aiInsights) && is_array($aiInsights)) {
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetTextColor(...$navy);
            $pdf->Cell(0, 7, '6. STRATEGIC INTELLIGENCE (AI DIAGNOSTICS)', 0, 1, 'L');
            $pdf->Ln(1);

            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 5, 'Behavioral insights generated through cross-correlation of real-time database metrics.', 0, 1, 'L');
            $pdf->Ln(3);

            $insightColors = [
                'utilization' => [79, 70, 229], 'recommendation' => [16, 185, 129],
                'alert' => [239, 68, 68], 'trend' => [58, 109, 140],
            ];

            foreach ($aiInsights as $i) {
                // Check for page break
                if ($pdf->GetY() > 250) $pdf->AddPage();

                $iType = $i['type'] ?? 'general';
                $color = $insightColors[$iType] ?? [100, 100, 100];

                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->SetTextColor(...$color);
                $pdf->Cell(0, 5, strtoupper($iType) . ': ' . ($i['title'] ?? ''), 0, 1, 'L');

                $pdf->SetFont('helvetica', '', 9);
                $pdf->SetTextColor(60, 60, 60);
                $pdf->MultiCell(0, 4, $i['description'] ?? '', 0, 'L');

                if (!empty($i['value'])) {
                    $pdf->SetFont('helvetica', 'B', 8.5);
                    $pdf->SetTextColor(...$color);
                    $pdf->Cell(0, 5, 'Metric: ' . $i['value'], 0, 1, 'L');
                }
                $pdf->Ln(2);
                $pdf->SetDrawColor(240, 240, 240);
                $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
                $pdf->Ln(2);
            }
            $pdf->Ln(6);
        }

        // ── Section 7: Leaderboard ──
        if ($pdf->GetY() > 220) $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(...$navy);
        $pdf->Cell(0, 7, '7. TOP VALIDATED STUDENTS LEADERBOARD', 0, 1, 'L');
        $pdf->Ln(2);
        
        $stuRowsHtml = '';
        foreach ($leaderboard as $student) {
            $stuRowsHtml .= '
            <tr>
                <td align="center"><b>#' . $student['rank'] . '</b></td>
                <td>' . htmlspecialchars($student['student_name']) . '</td>
                <td align="center">' . htmlspecialchars($student['value']) . '</td>
            </tr>';
        }
        
        $stuHtml = '
        <table cellpadding="5" border="1" style="border-color:#ccc;">
            <tr style="background-color:#ead8b1; color:#001f3f; font-weight:bold;">
                <th width="20%" align="center">Rank</th>
                <th width="55%">Student Name</th>
                <th width="25%" align="center">Accumulated Hours</th>
            </tr>
            ' . $stuRowsHtml . '
        </table>';
        $pdf->writeHTML($stuHtml, true, false, false, false, '');

        // Final Footer Note
        $pdf->SetY(-25);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell(0, 10, 'Proprietary Data System — College of Computer Studies — Confidential Executive Report', 0, 0, 'C');

        // Output PDF
        $filename = "sitin_analytics_" . date('Y-m-d') . ".pdf";
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $pdf->Output($filename, 'D');
        exit();
    }

} catch (Exception $e) {
    sendError(500, "Failed to export analytics report", $e);
}
