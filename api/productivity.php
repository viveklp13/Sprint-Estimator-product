<?php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

try {
    if ($method === 'GET' && isset($_GET['feature_id'])) {
        $feature_id = (int)$_GET['feature_id'];
        
        $stmt = $db->prepare("SELECT * FROM productivity_data WHERE feature_id = ?");
        $stmt->bind_param('i', $feature_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'id' => (int)$row['id'],
                'storyId' => (int)$row['story_id'],
                'hoursTaken' => (float)$row['hours_taken'],
                'effortsManDays' => (float)$row['efforts_man_days'],
                'actualStartDate' => $row['actual_start_date'],
                'actualEndDate' => $row['actual_end_date'],
                'isCompleted' => (bool)$row['is_completed'],
                'productivity' => (float)$row['productivity']
            ];
        }
        $stmt->close();
        
        echo json_encode($data);
    }
    elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $db->begin_transaction();
        
        try {
            // Delete existing productivity data for this feature
            $stmt = $db->prepare("DELETE FROM productivity_data WHERE feature_id = ?");
            $stmt->bind_param('i', $data['featureId']);
            $stmt->execute();
            $stmt->close();
            
            // Insert new productivity data
            foreach ($data['stories'] as $story) {
                $stmt = $db->prepare("INSERT INTO productivity_data (feature_id, story_id, hours_taken, efforts_man_days, actual_start_date, actual_end_date, is_completed, productivity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $isCompleted = $story['isCompleted'] ? 1 : 0;
                $stmt->bind_param('iiddssid',
                    $data['featureId'],
                    $story['storyId'],
                    $story['hoursTaken'],
                    $story['effortsManDays'],
                    $story['actualStartDate'],
                    $story['actualEndDate'],
                    $isCompleted,
                    $data['productivity']
                );
                $stmt->execute();
                $stmt->close();
            }
            
            $db->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$db->close();
?>
