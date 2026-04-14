<?php
// Inputs: user prediction and actual story
$userPrediction = "The hero finds a secret door and escapes the castle.";
$actualStory = "Once upon a time, the hero was trapped. But after hours of searching, the hero discovers a hidden passage under the rug, which leads out of the castle. Guards were patrolling, but the hero managed to escape undetected and reached the forest outside.";

// Prepare JSON input for Python script
$inputArray = [
    'prediction' => $userPrediction,
    'story' => $actualStory
];

$inputJson = json_encode($inputArray);
$escapedInput = escapeshellarg($inputJson);

$pythonExecutable = 'C:\\Users\\ACER\\AppData\\Local\\Programs\\Python\\Python313\\python.exe';
$pythonScript = 'C:\\Users\\ACER\\text_similarity.py';

$command = "\"$pythonExecutable\" \"$pythonScript\" \"$inputJson\"";
echo "Executing commaand : ".$command."\n";
exec($command . " 2>&1", $output, $returnVar);

if($returnVar === 0 && count($output) > 0){
    $resultJson = implode("\n", $output);
    $resultData = json_decode($resultJson, true);
    if($resultData && isset($resultData['similarity_percent'])) {
        echo "Similarity: " . $resultData['similarity_percent'] . "%\n";
    } else {
        echo "Failed to decode Python output.\n";
    }
} else {
    echo "Python script execution failed.\n";
    echo "Output:\n" . implode("\n", $output) . "\n";
    echo "Return code: $returnVar\n";
}
?>

