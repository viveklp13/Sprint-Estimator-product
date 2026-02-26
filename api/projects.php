<?php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

try {
    if ($method === 'GET') {
        // Get optional date filter parameters
        $filterStartDate = isset($_GET['filterStartDate']) ? $_GET['filterStartDate'] : null;
        $filterEndDate = isset($_GET['filterEndDate']) ? $_GET['filterEndDate'] : null;
        $filterMode = isset($_GET['filterMode']) ? $_GET['filterMode'] : 'target'; // 'target' or 'actual'
        
        $result = $db->query("SELECT p.*, 
            COUNT(DISTINCT f.id) as feature_count,
            SUM(f.total_story_points) as total_story_points,
            MAX(f.target_end_date) as latest_feature_date
            FROM projects p
            LEFT JOIN features f ON p.id = f.project_id
            GROUP BY p.id
            ORDER BY latest_feature_date DESC, p.created_at DESC");
        
        $projects = [];
        while ($p = $result->fetch_assoc()) {
            // Build date filter based on mode
            $dateFilterClause = '';
            $additionalJoin = '';
            
            if ($filterStartDate && $filterEndDate) {
                $startDateEsc = $db->real_escape_string($filterStartDate);
                $endDateEsc = $db->real_escape_string($filterEndDate);
                
                if ($filterMode === 'actual') {
                    // Filter by ACTUAL completion dates (stories completed in period)
                    // Only include features that have at least one completed story in the date range
                    $dateFilterClause = "
                        AND EXISTS (
                            SELECT 1 FROM productivity_data pd2 
                            WHERE pd2.feature_id = f.id 
                            AND pd2.actual_end_date >= '$startDateEsc'
                            AND pd2.actual_end_date <= '$endDateEsc'
                            AND pd2.is_completed = 1
                        )";
                } else {
                    // Filter by TARGET dates (features planned to complete in period)
                    $dateFilterClause = "
                        AND f.target_end_date >= '$startDateEsc' 
                        AND f.target_end_date <= '$endDateEsc'";
                }
            }
            
            // Get all features with their metrics (filtered by date if applicable)
            $query = "
                SELECT f.id, f.name, f.total_man_days, f.target_end_date,
                    f.actual_total_man_days,
                    f.sit_defects, f.uat_defects, f.defect_removal_efficiency,
                    COUNT(DISTINCT us.id) as total_stories,
                    COUNT(DISTINCT CASE WHEN pd.is_completed = 1 THEN pd.id END) as completed_stories_all";
            
            // Add period-specific counts for actual mode
            if ($filterMode === 'actual' && $filterStartDate && $filterEndDate) {
                $query .= ",
                    COUNT(DISTINCT CASE 
                        WHEN pd.is_completed = 1 
                        AND pd.actual_end_date >= '$startDateEsc'
                        AND pd.actual_end_date <= '$endDateEsc'
                        THEN pd.id 
                    END) as completed_stories_period,
                    SUM(CASE 
                        WHEN pd.is_completed = 1 
                        AND pd.actual_end_date >= '$startDateEsc'
                        AND pd.actual_end_date <= '$endDateEsc'
                        THEN pd.efforts_man_days 
                        ELSE 0 
                    END) as actual_dev_efforts_period,
                    SUM(CASE 
                        WHEN pd.is_completed = 1 
                        AND pd.actual_end_date >= '$startDateEsc'
                        AND pd.actual_end_date <= '$endDateEsc'
                        THEN us.story_points 
                        ELSE 0 
                    END) as story_points_period";
            } else {
                $query .= ",
                    0 as completed_stories_period,
                    0 as actual_dev_efforts_period,
                    0 as story_points_period";
            }
            
            $query .= "
                FROM features f
                LEFT JOIN user_stories us ON f.id = us.feature_id
                LEFT JOIN productivity_data pd ON us.id = pd.story_id AND pd.feature_id = f.id
                WHERE f.project_id = " . (int)$p['id'] . " " . $dateFilterClause . "
                GROUP BY f.id, f.name, f.total_man_days, f.target_end_date, 
                         f.actual_total_man_days, f.sit_defects, f.uat_defects, 
                         f.defect_removal_efficiency";
            
            $featuresResult = $db->query($query);
            
            $featureMetrics = [];
            $sumProductivity = 0;
            $sumEffortVariance = 0;
            $sumOntimeIndex = 0;
            $sumDRE = 0;
            $featureCount = 0;
            $dreFeatureCount = 0;
            
            while ($f = $featuresResult->fetch_assoc()) {
                $featureId = (int)$f['id'];
                
                // Calculate metrics based on filter mode
                if ($filterMode === 'actual' && $filterStartDate && $filterEndDate) {
                    // ACTUAL MODE: Use only productivity data from the period
                    $completedInPeriod = (int)$f['completed_stories_period'];
                    $actualDevEffortsPeriod = (float)$f['actual_dev_efforts_period'];
                    $storyPointsPeriod = (float)$f['story_points_period'];
                    
                    // Skip features with no completed work in period
                    if ($completedInPeriod == 0) {
                        continue;
                    }
                    
                    // Calculate productivity for period
                    $prod = $actualDevEffortsPeriod > 0 ? $storyPointsPeriod / $actualDevEffortsPeriod : 0;
                    
                    // Calculate proportional estimate for completed stories
                    $totalStories = (int)$f['total_stories'];
                    $estimatedDevMD = (float)$f['total_man_days'];
                    $proportionalEstimate = $totalStories > 0 ? 
                        ($completedInPeriod / $totalStories) * $estimatedDevMD : 0;
                    
                    // Effort variance for period (dev only)
                    $effortVariance = $proportionalEstimate > 0 ? 
                        (($actualDevEffortsPeriod - $proportionalEstimate) / $proportionalEstimate) * 100 : 0;
                    
                    // Ontime index for period
                    // Get stories completed in period and check if they were on time
                    $ontimeQuery = "
                        SELECT COUNT(*) as ontime_count
                        FROM productivity_data pd
                        JOIN user_stories us ON pd.story_id = us.id
                        WHERE pd.feature_id = $featureId
                        AND pd.is_completed = 1
                        AND pd.actual_end_date >= '$startDateEsc'
                        AND pd.actual_end_date <= '$endDateEsc'
                        AND pd.actual_end_date <= us.target_end_date";
                    
                    $ontimeResult = $db->query($ontimeQuery);
                    $ontimeRow = $ontimeResult->fetch_assoc();
                    $ontimeCount = (int)$ontimeRow['ontime_count'];
                    
                    $ontimeIndex = $completedInPeriod > 0 ? 
                        ($ontimeCount / $completedInPeriod) * 100 : 0;
                    
                } else {
                    // TARGET MODE: Use all feature data (existing logic)
                    // Get productivity data for entire feature
                    $prodQuery = "
                        SELECT AVG(productivity) as avg_prod,
                            SUM(efforts_man_days) as actual_dev_md,
                            COUNT(*) as total_tracked,
                            SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_count
                        FROM productivity_data 
                        WHERE feature_id = $featureId";
                    
                    $prodResult = $db->query($prodQuery);
                    $prodData = $prodResult->fetch_assoc();
                    
                    $prod = $prodData['avg_prod'] ? (float)$prodData['avg_prod'] : 0;
                    $actualDevMD = $prodData['actual_dev_md'] ? (float)$prodData['actual_dev_md'] : 0;
                    
                    // Skip if no productivity data
                    if ($prod == 0) {
                        continue;
                    }
                    
                    // Calculate effort variance using total project MD
                    $estimatedDevMD = (float)$f['total_man_days'];
                    $estimatedTotalMD = $estimatedDevMD / 0.3;
                    $actualTotalMD = (float)$f['actual_total_man_days'];
                    
                    $effortVariance = 0;
                    if ($estimatedTotalMD > 0 && $actualTotalMD > 0) {
                        $effortVariance = (($actualTotalMD - $estimatedTotalMD) / $estimatedTotalMD) * 100;
                    }
                    
                    // Calculate ontime index
                    $totalStories = (int)$f['total_stories'];
                    $completedStories = (int)$f['completed_stories_all'];
                    $ontimeIndex = $totalStories > 0 ? 
                        ($completedStories / $totalStories) * 100 : 0;
                }
                
                // DRE is same for both modes (feature-level metric)
                $dre = (float)$f['defect_removal_efficiency'];
                $sitDefects = (int)$f['sit_defects'];
                $uatDefects = (int)$f['uat_defects'];
                $totalDefects = $sitDefects + $uatDefects;
                
                $featureMetrics[] = [
                    'id' => $featureId,
                    'name' => $f['name'],
                    'productivity' => $prod,
                    'effortVariance' => $effortVariance,
                    'ontimeIndex' => $ontimeIndex,
                    'defectRemovalEfficiency' => $dre,
                    'sitDefects' => $sitDefects,
                    'uatDefects' => $uatDefects,
                    'targetEndDate' => $f['target_end_date']
                ];
                
                // Sum for mean calculation
                $sumProductivity += $prod;
                $sumEffortVariance += $effortVariance;
                $sumOntimeIndex += $ontimeIndex;
                
                if ($totalDefects > 0) {
                    $sumDRE += $dre;
                    $dreFeatureCount++;
                }
                
                $featureCount++;
            }
            
            // No features with KPI data — still include the project
            // so it appears in the dashboard (and the A-Z navigator)
            // but with zeroed-out metrics.
            if ($featureCount == 0) {
                $projects[] = [
                    'id'               => (int)$p['id'],
                    'name'             => $p['name'],
                    'description'      => $p['description'],
                    'featureCount'     => (int)$p['feature_count'],
                    'totalStoryPoints' => (float)($p['total_story_points'] ?? 0),
                    'latestFeatureDate'=> $p['latest_feature_date'],
                    'avgProductivity'  => 0,
                    'avgEffortVariance'=> 0,
                    'avgOntimeIndex'   => 0,
                    'avgDefectRemovalEfficiency' => 0,
                    'productivityStats'   => ['mean'=>0,'ucl'=>0,'lcl'=>0,'stdDev'=>0,'features'=>[]],
                    'effortVarianceStats' => ['mean'=>0,'ucl'=>0,'lcl'=>0,'stdDev'=>0,'features'=>[]],
                    'ontimeIndexStats'    => ['mean'=>0,'ucl'=>0,'lcl'=>0,'stdDev'=>0,'features'=>[]],
                    'dreStats'            => ['mean'=>0,'ucl'=>0,'lcl'=>0,'stdDev'=>0,'featureCount'=>0,'features'=>[]],
                    'filterMode'       => $filterMode,
                    'created_at'       => $p['created_at']
                ];
                continue;
            }
            
            // Calculate means
            $avgProductivity = $sumProductivity / $featureCount;
            $avgEffortVariance = $sumEffortVariance / $featureCount;
            $avgOntimeIndex = $sumOntimeIndex / $featureCount;
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
                'latestFeatureDate' => $p['latest_feature_date'],
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
                'filterMode' => $filterMode,
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
