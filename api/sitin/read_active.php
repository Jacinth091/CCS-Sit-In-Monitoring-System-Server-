<?php
// api/sitin/read_active.php
require_once '../../includes/cors.php';
require_once '../../includes/initialize.php';

$query = "SELECT s.id as log_id, s.purpose, s.time_in, s.status, 
                 st.student_id, st.first_name, st.last_name, st.profile_pic, st.session,
                 l.lab_name 
          FROM sit_in_logs s
          INNER JOIN students st ON s.student_id = st.student_id
          INNER JOIN laboratories l ON s.lab_id = l.id
          WHERE s.status = 'Active'
          ORDER BY s.time_in DESC";

$stmt = $db->prepare($query);
$stmt->execute();

$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

http_response_code(200);
echo json_encode($logs);
