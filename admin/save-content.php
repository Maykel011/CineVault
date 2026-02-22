<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../includes/database.php';

$db = getDB();

// Handle file uploads
$uploadDir = '../../uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$posterPath = '';
$coverPath = '';

// Upload poster image
if (isset($_FILES['poster_image']) && $_FILES['poster_image']['error'] === 0) {
    $extension = pathinfo($_FILES['poster_image']['name'], PATHINFO_EXTENSION);
    $filename = 'poster_' . time() . '.' . $extension;
    $targetPath = $uploadDir . $filename;
    
    if (move_uploaded_file($_FILES['poster_image']['tmp_name'], $targetPath)) {
        $posterPath = 'uploads/' . $filename;
    }
}

// Upload cover image
if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === 0) {
    $extension = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
    $filename = 'cover_' . time() . '.' . $extension;
    $targetPath = $uploadDir . $filename;
    
    if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $targetPath)) {
        $coverPath = 'uploads/' . $filename;
    }
}

// Insert content
$query = "INSERT INTO content (
    title, description, release_year, content_type, genre_id,
    poster_image, cover_image, trailer_url, duration_minutes,
    rating, maturity_rating, featured, trending, created_by
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $db->prepare($query);

$title = $_POST['title'];
$description = $_POST['description'];
$releaseYear = $_POST['release_year'];
$contentType = $_POST['content_type'];
$genreId = $_POST['genre_id'];
$trailerUrl = $_POST['trailer_url'] ?? null;
$duration = $contentType === 'movie' ? $_POST['duration_minutes'] : null;
$rating = $_POST['rating'] ?? null;
$maturityRating = $_POST['maturity_rating'] ?? 'TV-14';
$featured = isset($_POST['featured']) ? 1 : 0;
$trending = isset($_POST['trending']) ? 1 : 0;
$createdBy = $_SESSION['admin_id'];

$stmt->bind_param(
    "ssisissssdssii",
    $title, $description, $releaseYear, $contentType, $genreId,
    $posterPath, $coverPath, $trailerUrl, $duration,
    $rating, $maturityRating, $featured, $trending, $createdBy
);

if ($stmt->execute()) {
    $contentId = $db->insert_id;
    
    // Save video sources
    if (isset($_POST['quality']) && isset($_POST['video_url'])) {
        $qualities = $_POST['quality'];
        $videoUrls = $_POST['video_url'];
        
        $videoQuery = "INSERT INTO video_sources (content_id, quality, video_url) VALUES (?, ?, ?)";
        $videoStmt = $db->prepare($videoQuery);
        
        for ($i = 0; $i < count($qualities); $i++) {
            if (!empty($videoUrls[$i])) {
                $videoStmt->bind_param("iss", $contentId, $qualities[$i], $videoUrls[$i]);
                $videoStmt->execute();
            }
        }
    }
    
    // Save cast
    if (isset($_POST['actor_name']) && isset($_POST['character_name'])) {
        $actorNames = $_POST['actor_name'];
        $characterNames = $_POST['character_name'];
        
        for ($i = 0; $i < count($actorNames); $i++) {
            if (!empty($actorNames[$i])) {
                // Insert person
                $personQuery = "INSERT INTO people (name) VALUES (?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)";
                $personStmt = $db->prepare($personQuery);
                $personStmt->bind_param("s", $actorNames[$i]);
                $personStmt->execute();
                $personId = $db->insert_id;
                
                // Link to content
                $castQuery = "INSERT INTO content_cast (content_id, person_id, role, character_name) VALUES (?, ?, 'actor', ?)";
                $castStmt = $db->prepare($castQuery);
                $castStmt->bind_param("iis", $contentId, $personId, $characterNames[$i]);
                $castStmt->execute();
            }
        }
    }
    
    // Save director
    if (!empty($_POST['director'])) {
        $personQuery = "INSERT INTO people (name) VALUES (?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)";
        $personStmt = $db->prepare($personQuery);
        $personStmt->bind_param("s", $_POST['director']);
        $personStmt->execute();
        $personId = $db->insert_id;
        
        $castQuery = "INSERT INTO content_cast (content_id, person_id, role) VALUES (?, ?, 'director')";
        $castStmt = $db->prepare($castQuery);
        $castStmt->bind_param("ii", $contentId, $personId);
        $castStmt->execute();
    }
    
    // Save seasons and episodes for series
    if ($contentType === 'series' && isset($_POST['season_title'])) {
        for ($s = 0; $s < count($_POST['season_title']); $s++) {
            if (!empty($_POST['season_title'][$s])) {
                $seasonQuery = "INSERT INTO seasons (series_id, season_number, title, description) VALUES (?, ?, ?, ?)";
                $seasonStmt = $db->prepare($seasonQuery);
                $seasonNumber = $s + 1;
                $seasonTitle = $_POST['season_title'][$s];
                $seasonDesc = $_POST['season_description'][$s] ?? '';
                $seasonStmt->bind_param("iiss", $contentId, $seasonNumber, $seasonTitle, $seasonDesc);
                $seasonStmt->execute();
                $seasonId = $db->insert_id;
                
                // Save episodes for this season
                if (isset($_POST['episode_title'][$s])) {
                    for ($e = 0; $e < count($_POST['episode_title'][$s]); $e++) {
                        if (!empty($_POST['episode_title'][$s][$e])) {
                            $episodeQuery = "INSERT INTO episodes (season_id, episode_number, title, duration_minutes) VALUES (?, ?, ?, ?)";
                            $episodeStmt = $db->prepare($episodeQuery);
                            $episodeNumber = $e + 1;
                            $episodeTitle = $_POST['episode_title'][$s][$e];
                            $episodeDuration = $_POST['episode_duration'][$s][$e] ?? null;
                            $episodeStmt->bind_param("iisi", $seasonId, $episodeNumber, $episodeTitle, $episodeDuration);
                            $episodeStmt->execute();
                        }
                    }
                }
            }
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Content saved successfully', 'id' => $contentId]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save content: ' . $db->error]);
}
?>