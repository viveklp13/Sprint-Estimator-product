<?php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

try {
    if ($method === 'GET') {
        $result = $db->query("SELECT p.*, 
            COUNT(DISTINCT f.id) as feature_count,
            SUM(f.total_story_points) as total_story_points
            FROM projects p
            LEFT JOIN features f ON p.id = f.project_id
            GROUP BY p.id
            ORDER BY p.created_at DESC");
        
        $projects = [];
        while ($p = $result->fetch_assoc()) {
            // Get all features with their metrics
            $stmt = $db->prepare("
                SELECT f.id, f.name, f.total_man_days,
                    f.actual_total_man_days,
                    f.sit_defects, f.uat_defects, f.defect_removal_efficiency,
                    AVG(pd.productivity) as productivity,
                    COUNT(us.id) as total_stories,
                    SUM(CASE WHEN pd.is_completed = 1 THEN 1 ELSE 0 END) as completed_stories
                FROM features f
                LEFT JOIN user_stories us ON f.id = us.feature_id
                LEFT JOIN productivity_data pd ON f.id = pd.feature_id
                WHERE f.project_id = ?
                GROUP BY f.id
                HAVING productivity IS NOT NULL AND productivity > 0
            ");
            $stmt->bind_param('i', $p['id']);
            $stmt->execute();
            $prodResult = $stmt->get_result();
            
            $featureMetrics = [];
            $sumProductivity = 0;
            $sumEffortVariance = 0;
            $sumOntimeIndex = 0;
            $sumDRE = 0;
            $featureCount = 0;
            $dreFeatureCount = 0; // Count features with defect data
            
            while ($prodRow = $prodResult->fetch_assoc()) {
                $prod = (float)$prodRow['productivity'];
                $estimatedDevMD = (float)$prodRow['total_man_days'];
                $estimatedTotalMD = $estimatedDevMD / 0.3; // Total project MD from dev MD
                $actualTotalMD = (float)$prodRow['actual_total_man_days'];
                $totalStories = (int)$prodRow['total_stories'];
                $completedStories = (int)$prodRow['completed_stories'];
                
                // Calculate Effort Variance using TOTAL project man days (all phases)
                $effortVariance = 0;
                if ($estimatedTotalMD > 0 && $actualTotalMD > 0) {
                    $effortVariance = (($actualTotalMD - $estimatedTotalMD) / $estimatedTotalMD) * 100;
                }
                
                // Calculate Ontime Index for this feature
                $ontimeIndex = 0;
                if ($totalStories > 0) {
                    $ontimeIndex = ($completedStories / $totalStories) * 100;
                }
                
                // Get DRE for this feature
                $dre = (float)$prodRow['defect_removal_efficiency'];
                $sitDefects = (int)$prodRow['sit_defects'];
                $uatDefects = (int)$prodRow['uat_defects'];
                $totalDefects = $sitDefects + $uatDefects;
                
                $featureMetrics[] = [
                    'id' => (int)$prodRow['id'],
                    'name' => $prodRow['name'],
                    'productivity' => $prod,
                    'effortVariance' => $effortVariance,
                    'ontimeIndex' => $ontimeIndex,
                    'defectRemovalEfficiency' => $dre,
                    'sitDefects' => $sitDefects,
                    'uatDefects' => $uatDefects
                ];
                
                // Sum up all feature values for mean calculation
                $sumProductivity += $prod;
                $sumEffortVariance += $effortVariance;
                $sumOntimeIndex += $ontimeIndex;
                
                // Only include features with defect data in DRE calculation
                if ($totalDefects > 0) {
                    $sumDRE += $dre;
                    $dreFeatureCount++;
                }
                
                $featureCount++;
            }
            $stmt->close();
            
            // Calculate means as: Sum of feature values / Number of features
            $avgProductivity = $featureCount > 0 ? $sumProductivity / $featureCount : 0;
            $avgEffortVariance = $featureCount > 0 ? $sumEffortVariance / $featureCount : 0;
            $avgOntimeIndex = $featureCount > 0 ? $sumOntimeIndex / $featureCount : 0;
            $avgDRE = $dreFeatureCount > 0 ? $sumDRE / $dreFeatureCount : 0;
            
            // Calculate standard deviations for control limits
            $prodVariance = 0;
            $effortVarianceVar = 0;
            $ontimeVarianceVar = 0;
            $dreVariance = 0;
            
            if ($featureCount > 1) {
                foreach ($featureMetrics as $fm) {
                    $prodVariance += pow($fm['productivity'] - $avgProductivity, 2);
                    $effortVarianceVar += pow($fm['effortVariance'] - $avgEffortVariance, 2);
                    $ontimeVarianceVar += pow($fm['ontimeIndex'] - $avgOntimeIndex, 2);
                    
                    // Only include features with defect data in DRE variance
                    if ($fm['sitDefects'] + $fm['uatDefects'] > 0) {
                        $dreVariance += pow($fm['defectRemovalEfficiency'] - $avgDRE, 2);
                    }
                }
                $prodVariance = $prodVariance / $featureCount;
                $effortVarianceVar = $effortVarianceVar / $featureCount;
                $ontimeVarianceVar = $ontimeVarianceVar / $featureCount;
                
                if ($dreFeatureCount > 1) {
                    $dreVariance = $dreVariance / $dreFeatureCount;
                }
            }
            
            $prodStdDev = sqrt($prodVariance);
            $effortStdDev = sqrt($effortVarianceVar);
            $ontimeStdDev = sqrt($ontimeVarianceVar);
            $dreStdDev = sqrt($dreVariance);
            
            $projects[] = [
                'id' => (int)$p['id'],
                'name' => $p['name'],
                'description' => $p['description'],
                'featureCount' => (int)$p['feature_count'],
                'totalStoryPoints' => (float)($p['total_story_points'] ?? 0),
                'avgProductivity' => round($avgProductivity, 4),
                'avgEffortVariance' => round($avgEffortVariance, 2),
                'avgOntimeIndex' => round($avgOntimeIndex, 2),
                'avgDefectRemovalEfficiency' => round($avgDRE, 2),
                'productivityStats' => [
                    'mean' => round($avgProductivity, 4),
                    'ucl' => round($avgProductivity + (3 * $prodStdDev), 4),
                    'lcl' => round(max(0, $avgProductivity - (3 * $prodStdDev)), 4),
                    'stdDev' => round($prodStdDev, 4),
                    'features' => $featureMetrics
                ],
                'effortVarianceStats' => [
                    'mean' => round($avgEffortVariance, 2),
                    'ucl' => round($avgEffortVariance + (3 * $effortStdDev), 2),
                    'lcl' => round($avgEffortVariance - (3 * $effortStdDev), 2),
                    'stdDev' => round($effortStdDev, 2),
                    'features' => $featureMetrics
                ],
                'ontimeIndexStats' => [
                    'mean' => round($avgOntimeIndex, 2),
                    'ucl' => round(min(100, $avgOntimeIndex + (3 * $ontimeStdDev)), 2),
                    'lcl' => round(max(0, $avgOntimeIndex - (3 * $ontimeStdDev)), 2),
                    'stdDev' => round($ontimeStdDev, 2),
                    'features' => $featureMetrics
                ],
                'dreStats' => [
                    'mean' => round($avgDRE, 2),
                    'ucl' => round(min(100, $avgDRE + (3 * $dreStdDev)), 2),
                    'lcl' => round(max(0, $avgDRE - (3 * $dreStdDev)), 2),
                    'stdDev' => round($dreStdDev, 2),
                    'featureCount' => $dreFeatureCount,
                    'features' => $featureMetrics
                ],
                'created_at' => $p['created_at']
            ];
        }
        
        echo json_encode($projects);
    }
    elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $db->prepare("INSERT INTO projects (name, description) VALUES (?, ?)");
        $stmt->bind_param('ss', $data['name'], $data['description']);
        $stmt->execute();
        
        echo json_encode([
            'id' => $db->insert_id,
            'name' => $data['name'],
            'description' => $data['description']
        ]);
        $stmt->close();
    }
    elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $db->prepare("UPDATE projects SET name = ?, description = ? WHERE id = ?");
        $stmt->bind_param('ssi', $data['name'], $data['description'], $data['id']);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        $stmt->close();
    }
    elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
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
