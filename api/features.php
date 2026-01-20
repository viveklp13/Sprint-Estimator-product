<?php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

try {
    if ($method === 'GET' && isset($_GET['project_id'])) {
        $project_id = (int)$_GET['project_id'];
        
        $stmt = $db->prepare("SELECT * FROM features WHERE project_id = ? ORDER BY created_at DESC");
        $stmt->bind_param('i', $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $features = [];
        while ($f = $result->fetch_assoc()) {
            // Get user stories
            $storyStmt = $db->prepare("SELECT * FROM user_stories WHERE feature_id = ?");
            $storyStmt->bind_param('i', $f['id']);
            $storyStmt->execute();
            $storyResult = $storyStmt->get_result();
            
            $stories = [];
            while ($s = $storyResult->fetch_assoc()) {
                $stories[] = [
                    'id' => (int)$s['id'],
                    'title' => $s['title'],
                    'hours' => (float)$s['hours'],
                    'manDays' => (float)$s['man_days'],
                    'storyPoints' => (float)$s['story_points'],
                    'estimatedStartDate' => $s['estimated_start_date'],
                    'targetEndDate' => $s['target_end_date']
                ];
            }
            $storyStmt->close();
            
            // Get productivity data
            $prodStmt = $db->prepare("
                SELECT AVG(productivity) as avg_prod,
                    SUM(efforts_man_days) as actual_dev_md,
                    COUNT(*) as total_tracked,
                    SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_count
                FROM productivity_data 
                WHERE feature_id = ?
            ");
            $prodStmt->bind_param('i', $f['id']);
            $prodStmt->execute();
            $prodResult = $prodStmt->get_result();
            $prodData = $prodResult->fetch_assoc();
            $prodStmt->close();
            
            $productivity = $prodData['avg_prod'] ? (float)$prodData['avg_prod'] : 0;
            $actualDevMD = $prodData['actual_dev_md'] ? (float)$prodData['actual_dev_md'] : 0;
            
            // Get actual phase efforts from features table
            $actualReqMD = (float)$f['actual_req_man_days'];
            $actualDesignMD = (float)$f['actual_design_man_days'];
            $actualTestingMD = (float)$f['actual_testing_man_days'];
            $actualPmMD = (float)$f['actual_pm_man_days'];
            $actualTotalMD = (float)$f['actual_total_man_days'];
            
            // Get defect data
            $sitDefects = (int)$f['sit_defects'];
            $uatDefects = (int)$f['uat_defects'];
            $dre = (float)$f['defect_removal_efficiency'];
            
            // Calculate phase-wise breakdown (ESTIMATED)
            $devManDays = (float)$f['total_man_days'];
            $totalProjectManDays = $devManDays / 0.3;
            
            $estimatedPhases = [
                'requirement' => round(0.15 * $totalProjectManDays, 2),
                'design' => round(0.15 * $totalProjectManDays, 2),
                'development' => round($devManDays, 2),
                'testing' => round(0.25 * $totalProjectManDays, 2),
                'pm' => round(0.15 * $totalProjectManDays, 2),
                'total' => round($totalProjectManDays, 2)
            ];
            
            // Calculate Effort Variance using TOTAL efforts (all phases)
            $effortVariance = 0;
            if ($totalProjectManDays > 0 && $actualTotalMD > 0) {
                $effortVariance = (($actualTotalMD - $totalProjectManDays) / $totalProjectManDays) * 100;
            }
            
            // Calculate Ontime Index
            $totalStories = count($stories);
            $completedStories = (int)$prodData['completed_count'];
            $ontimeIndex = 0;
            if ($totalStories > 0) {
                $ontimeIndex = ($completedStories / $totalStories) * 100;
            }
            
            $features[] = [
                'id' => (int)$f['id'],
                'name' => $f['name'],
                'orgProductivity' => (float)$f['org_productivity'],
                'manDaysHours' => (float)$f['man_days_hours'],
                'totalStoryPoints' => (float)$f['total_story_points'],
                'totalManDays' => $devManDays,
                'estimatedStartDate' => $f['estimated_start_date'],
                'targetEndDate' => $f['target_end_date'],
                'productivity' => round($productivity, 4),
                'effortVariance' => round($effortVariance, 2),
                'ontimeIndex' => round($ontimeIndex, 2),
                'defectRemovalEfficiency' => round($dre, 2),
                'sitDefects' => $sitDefects,
                'uatDefects' => $uatDefects,
                'phaseBreakdown' => $estimatedPhases,
                'actualPhaseEfforts' => [
                    'requirement' => $actualReqMD,
                    'design' => $actualDesignMD,
                    'development' => $actualDevMD,
                    'testing' => $actualTestingMD,
                    'pm' => $actualPmMD,
                    'total' => $actualTotalMD
                ],
                'stories' => $stories,
                'created_at' => $f['created_at']
            ];
        }
        $stmt->close();
        
        echo json_encode($features);
    }
    elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $db->begin_transaction();
        
        try {
            // Insert feature
            $stmt = $db->prepare("INSERT INTO features (project_id, name, org_productivity, man_days_hours, total_story_points, total_man_days, estimated_start_date, target_end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('isddddss', 
                $data['projectId'],
                $data['name'],
                $data['orgProductivity'],
                $data['manDaysHours'],
                $data['totalStoryPoints'],
                $data['totalManDays'],
                $data['estimatedStartDate'],
                $data['targetEndDate']
            );
            $stmt->execute();
            $feature_id = $db->insert_id;
            $stmt->close();
            
            // Insert user stories
            foreach ($data['stories'] as $story) {
                $stmt = $db->prepare("INSERT INTO user_stories (feature_id, title, hours, man_days, story_points, estimated_start_date, target_end_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('isdddss',
                    $feature_id,
                    $story['title'],
                    $story['hours'],
                    $story['manDays'],
                    $story['storyPoints'],
                    $story['estimatedStartDate'],
                    $story['targetEndDate']
                );
                $stmt->execute();
                $stmt->close();
            }
            
            $db->commit();
            echo json_encode(['success' => true, 'id' => $feature_id]);
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $db->begin_transaction();
        
        try {
            // Check if this is a phase efforts update
            if (isset($data['phaseEffortsUpdate']) && $data['phaseEffortsUpdate'] === true) {
                // Update only phase efforts
                $stmt = $db->prepare("UPDATE features SET actual_req_man_days = ?, actual_design_man_days = ?, actual_dev_man_days = ?, actual_testing_man_days = ?, actual_pm_man_days = ?, actual_total_man_days = ? WHERE id = ?");
                $stmt->bind_param('ddddddi',
                    $data['actualReqMD'],
                    $data['actualDesignMD'],
                    $data['actualDevMD'],
                    $data['actualTestingMD'],
                    $data['actualPmMD'],
                    $data['actualTotalMD'],
                    $data['id']
                );
                $stmt->execute();
                $stmt->close();
            } elseif (isset($data['defectsUpdate']) && $data['defectsUpdate'] === true) {
                // Update only defect data
                $sitDefects = (int)$data['sitDefects'];
                $uatDefects = (int)$data['uatDefects'];
                $totalDefects = $sitDefects + $uatDefects;
                
                // Calculate DRE: (SIT / (SIT + UAT)) * 100
                $dre = 0;
                if ($totalDefects > 0) {
                    $dre = ($sitDefects / $totalDefects) * 100;
                }
                
                $stmt = $db->prepare("UPDATE features SET sit_defects = ?, uat_defects = ?, defect_removal_efficiency = ? WHERE id = ?");
                $stmt->bind_param('iidi',
                    $sitDefects,
                    $uatDefects,
                    $dre,
                    $data['id']
                );
                $stmt->execute();
                $stmt->close();
            } else {
                // Update feature details
                $stmt = $db->prepare("UPDATE features SET name = ?, org_productivity = ?, man_days_hours = ?, total_story_points = ?, total_man_days = ?, estimated_start_date = ?, target_end_date = ? WHERE id = ?");
                $stmt->bind_param('sddddssi',
                    $data['name'],
                    $data['orgProductivity'],
                    $data['manDaysHours'],
                    $data['totalStoryPoints'],
                    $data['totalManDays'],
                    $data['estimatedStartDate'],
                    $data['targetEndDate'],
                    $data['id']
                );
                $stmt->execute();
                $stmt->close();
                
                // Delete old stories
                $stmt = $db->prepare("DELETE FROM user_stories WHERE feature_id = ?");
                $stmt->bind_param('i', $data['id']);
                $stmt->execute();
                $stmt->close();
                
                // Insert updated stories
                foreach ($data['stories'] as $story) {
                    $stmt = $db->prepare("INSERT INTO user_stories (feature_id, title, hours, man_days, story_points, estimated_start_date, target_end_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param('isdddss',
                        $data['id'],
                        $story['title'],
                        $story['hours'],
                        $story['manDays'],
                        $story['storyPoints'],
                        $story['estimatedStartDate'],
                        $story['targetEndDate']
                    );
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            $db->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $db->prepare("DELETE FROM features WHERE id = ?");
        $stmt->bind_param('i', $data['id']);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        $stmt->close();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$db->close();
?>
