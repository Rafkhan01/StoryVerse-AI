<?php
// Example inputs
$userPrediction = "The hero finds a secret door and escapes the castle.";
$actualStory = "Once upon a time, the hero was trapped. But after hours of searching, the hero discovers a hidden passage under the rug, which leads out of the castle. Guards were patrolling, but the hero managed to escape undetected and reached the forest outside.";

// Simple list of common English stopwords
$stopwords = ['the','and','is','a','on','of','to','in','was','he','she','it','for','with','as','his','her','at','by','from','but','after','out','under','which','were','so','all','had','be','are','an','once','upon'];

// Helper: text to word array, cleaned up and stopwords removed
function get_keywords($text, $stopwords) {
    $text = strtolower($text);
    $text = preg_replace('/[^\w\s]/', '', $text); // Remove punctuation
    $words = explode(' ', $text);
    $keywords = array_diff($words, $stopwords);
    return array_unique(array_filter($keywords));
}

$userWords = get_keywords($userPrediction, $stopwords);
$storyWords = get_keywords($actualStory, $stopwords);

if (empty($userWords)) {
    echo "User prediction is too short or contains only stopwords.\n";
    exit;
}

// Count how many user keywords are in the story
$overlap = array_intersect($userWords, $storyWords);
$overlapCount = count($overlap);
$totalUserWords = count($userWords);

// Similarity: proportion of unique user words found in story
$similarity = $totalUserWords > 0 ? ($overlapCount / $totalUserWords) * 100 : 0;

echo "User's keywords: " . implode(', ', $userWords) . "\n";
echo "Story's keywords: " . implode(', ', $storyWords) . "\n";
echo "Matched keywords: " . implode(', ', $overlap) . "\n";
echo "Prediction match score: " . round($similarity, 2) . "%\n";
?>

