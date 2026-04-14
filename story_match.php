<?php
class SemanticStoryMatcher {
    
    // Core story concepts and their semantic relationships
    private $storyUniverseElements = [
        'fantasy_medieval' => [
            'characters' => ['knight', 'princess', 'king', 'queen', 'wizard', 'dragon', 'witch', 'prince', 'hero', 'warrior'],
            'objects' => ['sword', 'castle', 'crown', 'treasure', 'magic', 'spell', 'potion', 'shield', 'bow', 'ring'],
            'actions' => ['rescue', 'save', 'fight', 'battle', 'defeat', 'marry', 'quest', 'journey', 'discover'],
            'places' => ['kingdom', 'forest', 'cave', 'mountain', 'tower', 'dungeon', 'palace', 'village']
        ],
        'modern_scifi' => [
            'characters' => ['astronaut', 'alien', 'robot', 'scientist', 'captain', 'commander'],
            'objects' => ['spaceship', 'laser', 'computer', 'technology', 'weapon', 'device'],
            'actions' => ['travel', 'explore', 'discover', 'colonize', 'contact', 'investigate'],
            'places' => ['mars', 'space', 'planet', 'galaxy', 'station', 'colony', 'ship']
        ],
        'adventure' => [
            'characters' => ['explorer', 'adventurer', 'guide', 'treasure hunter'],
            'objects' => ['map', 'compass', 'rope', 'torch', 'artifact'],
            'actions' => ['climb', 'search', 'find', 'escape', 'survive'],
            'places' => ['jungle', 'desert', 'island', 'ruins', 'temple']
        ]
    ];
    
    // Semantic relationship patterns
    private $semanticPatterns = [
        'hero_journey' => [
            'setup' => ['hero', 'knight', 'warrior', 'adventurer'],
            'challenge' => ['dragon', 'monster', 'villain', 'enemy', 'problem'],
            'tool' => ['sword', 'weapon', 'magic', 'power', 'skill'],
            'goal' => ['save', 'rescue', 'protect', 'defeat', 'find'],
            'reward' => ['princess', 'treasure', 'peace', 'victory', 'kingdom']
        ],
        'rescue_mission' => [
            'victim' => ['princess', 'people', 'kingdom', 'village'],
            'threat' => ['dragon', 'monster', 'villain', 'danger'],
            'hero' => ['knight', 'hero', 'warrior', 'brave'],
            'action' => ['save', 'rescue', 'protect', 'fight'],
            'resolution' => ['peace', 'safety', 'victory', 'freedom']
        ],
        'quest_adventure' => [
            'seeker' => ['hero', 'adventurer', 'knight', 'explorer'],
            'objective' => ['treasure', 'artifact', 'sword', 'crown', 'secret'],
            'location' => ['cave', 'castle', 'mountain', 'forest', 'dungeon'],
            'obstacles' => ['dragon', 'guards', 'traps', 'monsters'],
            'success' => ['find', 'discover', 'obtain', 'achieve']
        ]
    ];
    
    public function analyzeStoryPrediction($userPrediction, $actualStory) {
        // Step 1: Identify the story universe and genre
        $storyUniverse = $this->identifyStoryUniverse($actualStory);
        $predictionUniverse = $this->identifyStoryUniverse($userPrediction);
        
        // Step 2: Extract semantic patterns from both texts
        $storyPatterns = $this->extractSemanticPatterns($actualStory);
        $predictionPatterns = $this->extractSemanticPatterns($userPrediction);
        
        // Step 3: Analyze narrative structure and meaning
        $analysis = [
            'universe_match' => $this->calculateUniverseMatch($predictionUniverse, $storyUniverse),
            'narrative_structure' => $this->analyzeNarrativeStructure($predictionPatterns, $storyPatterns),
            'semantic_coherence' => $this->analyzeSemanticCoherence($userPrediction, $actualStory),
            'plot_logic' => $this->analyzePlotLogic($userPrediction, $actualStory),
            'character_roles' => $this->analyzeCharacterRoles($userPrediction, $actualStory),
            'outcome_prediction' => $this->analyzeOutcomePrediction($userPrediction, $actualStory)
        ];
        
        // Step 4: Calculate meaning-based score
        $meaningScore = $this->calculateMeaningScore($analysis, $userPrediction, $actualStory);
        
        return [
            'overall_score' => $meaningScore,
            'story_universe' => $storyUniverse,
            'prediction_universe' => $predictionUniverse,
            'semantic_analysis' => $analysis,
            'meaning_assessment' => $this->assessMeaningQuality($meaningScore, $analysis),
            'feedback' => $this->generateMeaningBasedFeedback($meaningScore, $analysis)
        ];
    }
    
    private function identifyStoryUniverse($text) {
        $text = strtolower($text);
        $universeScores = [];
        
        foreach ($this->storyUniverseElements as $universe => $categories) {
            $score = 0;
            foreach ($categories as $category => $elements) {
                foreach ($elements as $element) {
                    if (strpos($text, $element) !== false) {
                        // Weight different categories
                        $weight = ($category === 'characters') ? 3 : (($category === 'actions') ? 2 : 1);
                        $score += $weight;
                    }
                }
            }
            $universeScores[$universe] = $score;
        }
        
        if (max($universeScores) == 0) return 'unknown';
        return array_search(max($universeScores), $universeScores);
    }
    
    private function extractSemanticPatterns($text) {
        $text = strtolower($text);
        $foundPatterns = [];
        
        foreach ($this->semanticPatterns as $patternName => $patternElements) {
            $patternScore = 0;
            $foundElements = [];
            
            foreach ($patternElements as $role => $keywords) {
                foreach ($keywords as $keyword) {
                    if (strpos($text, $keyword) !== false) {
                        $foundElements[$role] = $keyword;
                        $patternScore++;
                        break; // Only count first match per role
                    }
                }
            }
            
            if ($patternScore > 0) {
                $foundPatterns[$patternName] = [
                    'score' => $patternScore,
                    'completeness' => $patternScore / count($patternElements),
                    'elements' => $foundElements
                ];
            }
        }
        
        return $foundPatterns;
    }
    
    private function calculateUniverseMatch($predictionUniverse, $storyUniverse) {
        if ($predictionUniverse === 'unknown' && $storyUniverse === 'unknown') {
            return 50; // Neutral if both unclear
        }
        
        if ($predictionUniverse === $storyUniverse) {
            return 100; // Perfect universe match
        }
        
        // Check for compatible universes
        $compatibleUniverses = [
            'fantasy_medieval' => ['adventure'],
            'adventure' => ['fantasy_medieval'],
            'modern_scifi' => []
        ];
        
        if (isset($compatibleUniverses[$storyUniverse]) && 
            in_array($predictionUniverse, $compatibleUniverses[$storyUniverse])) {
            return 70; // Compatible but not exact
        }
        
        return 0; // Completely wrong universe
    }
    
    private function analyzeNarrativeStructure($predictionPatterns, $storyPatterns) {
        if (empty($predictionPatterns) || empty($storyPatterns)) {
            return 0;
        }
        
        $structureScore = 0;
        $maxPossibleScore = 0;
        
        foreach ($predictionPatterns as $patternName => $predictionData) {
            $maxPossibleScore += 100;
            
            if (isset($storyPatterns[$patternName])) {
                $storyData = $storyPatterns[$patternName];
                
                // Compare pattern completeness
                $completenessMatch = min($predictionData['completeness'], $storyData['completeness']) / 
                                   max($predictionData['completeness'], $storyData['completeness']);
                
                // Compare specific roles
                $roleMatches = 0;
                $totalRoles = 0;
                
                foreach ($predictionData['elements'] as $role => $element) {
                    $totalRoles++;
                    if (isset($storyData['elements'][$role])) {
                        // Same role exists in story - check semantic similarity
                        if ($this->areSemanticallySimilar($element, $storyData['elements'][$role])) {
                            $roleMatches++;
                        }
                    }
                }
                
                $roleScore = $totalRoles > 0 ? ($roleMatches / $totalRoles) * 100 : 0;
                $patternScore = ($completenessMatch * 40) + ($roleScore * 60);
                $structureScore += $patternScore;
            }
        }
        
        return $maxPossibleScore > 0 ? $structureScore / $maxPossibleScore * 100 : 0;
    }
    
    private function analyzeSemanticCoherence($userPrediction, $actualStory) {
        // Extract core concepts and relationships
        $predictionConcepts = $this->extractCoreConcepts($userPrediction);
        $storyConcepts = $this->extractCoreConcepts($actualStory);
        
        if (empty($predictionConcepts)) return 0;
        
        $coherenceScore = 0;
        $conceptCount = 0;
        
        foreach ($predictionConcepts as $concept => $importance) {
            $conceptCount++;
            
            if (isset($storyConcepts[$concept])) {
                // Direct concept match
                $coherenceScore += $importance * 100;
            } else {
                // Check for related concepts
                $relatedScore = $this->findRelatedConcepts($concept, $storyConcepts);
                $coherenceScore += $relatedScore * $importance;
            }
        }
        
        return $conceptCount > 0 ? $coherenceScore / $conceptCount : 0;
    }
    
    private function analyzePlotLogic($userPrediction, $actualStory) {
        // Analyze cause-effect relationships and logical flow
        $predictionLogic = $this->extractPlotLogic($userPrediction);
        $storyLogic = $this->extractPlotLogic($actualStory);
        
        $logicScore = 0;
        $logicElements = ['cause', 'action', 'effect', 'resolution'];
        
        foreach ($logicElements as $element) {
            if (isset($predictionLogic[$element]) && isset($storyLogic[$element])) {
                if ($this->isLogicallyConsistent($predictionLogic[$element], $storyLogic[$element])) {
                    $logicScore += 25; // Each element worth 25%
                }
            }
        }
        
        return $logicScore;
    }
    
    private function analyzeCharacterRoles($userPrediction, $actualStory) {
        $predictionRoles = $this->extractCharacterRoles($userPrediction);
        $storyRoles = $this->extractCharacterRoles($actualStory);
        
        if (empty($predictionRoles)) return 0;
        
        $roleScore = 0;
        $totalRoles = count($predictionRoles);
        
        foreach ($predictionRoles as $role => $character) {
            if (isset($storyRoles[$role])) {
                if ($this->areCharactersSimilar($character, $storyRoles[$role])) {
                    $roleScore += 100;
                } else {
                    $roleScore += 30; // Partial credit for having the role
                }
            }
        }
        
        return $totalRoles > 0 ? $roleScore / $totalRoles : 0;
    }
    
    private function analyzeOutcomePrediction($userPrediction, $actualStory) {
        $predictionOutcome = $this->extractStoryOutcome($userPrediction);
        $storyOutcome = $this->extractStoryOutcome($actualStory);
        
        if ($predictionOutcome === null || $storyOutcome === null) {
            return 50; // Neutral if unclear
        }
        
        if ($predictionOutcome === $storyOutcome) {
            return 100; // Perfect outcome match
        }
        
        // Check for compatible outcomes
        $compatibleOutcomes = [
            'hero_victory' => ['rescue_success', 'quest_complete'],
            'rescue_success' => ['hero_victory', 'peace_restored'],
            'quest_complete' => ['hero_victory', 'treasure_found']
        ];
        
        if (isset($compatibleOutcomes[$storyOutcome]) && 
            in_array($predictionOutcome, $compatibleOutcomes[$storyOutcome])) {
            return 80; // Compatible outcome
        }
        
        return 0; // Wrong outcome
    }
    
    private function calculateMeaningScore($analysis, $userPrediction, $actualStory) {
        // Heavily weight universe and narrative structure for meaning
        $weights = [
            'universe_match' => 0.25,      // Is this the right type of story?
            'narrative_structure' => 0.30, // Does the prediction follow story logic?
            'semantic_coherence' => 0.20,  // Do the concepts make sense together?
            'plot_logic' => 0.15,          // Is the cause-effect logical?
            'character_roles' => 0.05,     // Are character roles correct?
            'outcome_prediction' => 0.05   // Is the ending prediction right?
        ];
        
        $weightedScore = 0;
        foreach ($analysis as $component => $score) {
            $weightedScore += $score * $weights[$component];
        }
        
        // Apply dramatic penalties for wrong universe
        if ($analysis['universe_match'] === 0) {
            $weightedScore *= 0.2; // Massive penalty for wrong genre/universe
        }
        
        // Apply penalties for poor narrative structure
        if ($analysis['narrative_structure'] < 20) {
            $weightedScore *= 0.5; // Penalty for nonsensical structure
        }
        
        // Boost scores for high coherence
        if ($analysis['semantic_coherence'] > 70 && $analysis['narrative_structure'] > 60) {
            $weightedScore *= 1.3; // Boost for coherent, well-structured predictions
        }
        
        return round(max(0, min(100, $weightedScore)), 2);
    }
    
    // Helper methods for semantic analysis
    private function extractCoreConcepts($text) {
        $concepts = [];
        $text = strtolower($text);
        
        // Define core concepts with importance weights
        $conceptMap = [
            'conflict' => ['fight', 'battle', 'war', 'struggle', 'combat'],
            'rescue' => ['save', 'rescue', 'protect', 'help', 'defend'],
            'quest' => ['find', 'search', 'seek', 'discover', 'quest'],
            'magic' => ['magic', 'spell', 'enchant', 'supernatural', 'mystical'],
            'evil' => ['evil', 'dark', 'wicked', 'monster', 'villain'],
            'good' => ['good', 'hero', 'noble', 'righteous', 'brave'],
            'love' => ['love', 'romance', 'marry', 'wedding', 'heart'],
            'power' => ['power', 'strong', 'mighty', 'force', 'strength'],
            'treasure' => ['treasure', 'gold', 'riches', 'valuable', 'jewel'],
            'journey' => ['journey', 'travel', 'adventure', 'voyage', 'expedition']
        ];
        
        foreach ($conceptMap as $concept => $keywords) {
            $importance = 0;
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $importance = 1;
                    break;
                }
            }
            if ($importance > 0) {
                $concepts[$concept] = $importance;
            }
        }
        
        return $concepts;
    }
    
    private function findRelatedConcepts($concept, $storyConcepts) {
        $relatedMap = [
            'conflict' => ['evil', 'power'],
            'rescue' => ['good', 'love'],
            'quest' => ['journey', 'treasure'],
            'magic' => ['power'],
            'evil' => ['conflict'],
            'good' => ['rescue', 'love'],
            'love' => ['good', 'rescue'],
            'power' => ['magic', 'conflict'],
            'treasure' => ['quest'],
            'journey' => ['quest']
        ];
        
        $relatedScore = 0;
        if (isset($relatedMap[$concept])) {
            foreach ($relatedMap[$concept] as $relatedConcept) {
                if (isset($storyConcepts[$relatedConcept])) {
                    $relatedScore += 50; // Partial credit for related concepts
                }
            }
        }
        
        return min($relatedScore, 80); // Cap at 80%
    }
    
    private function extractPlotLogic($text) {
        $text = strtolower($text);
        $logic = [];
        
        // Simple pattern matching for plot logic
        if (preg_match('/because|since|due to/', $text)) {
            $logic['cause'] = 'explicit_cause';
        }
        
        if (preg_match('/will|shall|going to/', $text)) {
            $logic['action'] = 'future_action';
        }
        
        if (preg_match('/result|consequence|outcome/', $text)) {
            $logic['effect'] = 'explicit_effect';
        }
        
        if (preg_match('/finally|end|conclusion/', $text)) {
            $logic['resolution'] = 'explicit_resolution';
        }
        
        return $logic;
    }
    
    private function isLogicallyConsistent($prediction, $story) {
        // Simple consistency check
        return $prediction === $story;
    }
    
    private function extractCharacterRoles($text) {
        $text = strtolower($text);
        $roles = [];
        
        $roleMap = [
            'protagonist' => ['hero', 'knight', 'warrior', 'adventurer'],
            'victim' => ['princess', 'people', 'kingdom', 'village'],
            'antagonist' => ['dragon', 'monster', 'villain', 'enemy'],
            'helper' => ['wizard', 'guide', 'ally', 'friend']
        ];
        
        foreach ($roleMap as $role => $characters) {
            foreach ($characters as $character) {
                if (strpos($text, $character) !== false) {
                    $roles[$role] = $character;
                    break;
                }
            }
        }
        
        return $roles;
    }
    
    private function areCharactersSimilar($char1, $char2) {
        $characterGroups = [
            ['hero', 'knight', 'warrior', 'adventurer'],
            ['princess', 'maiden', 'lady'],
            ['dragon', 'monster', 'beast'],
            ['wizard', 'mage', 'sorcerer'],
            ['king', 'ruler', 'monarch']
        ];
        
        foreach ($characterGroups as $group) {
            if (in_array($char1, $group) && in_array($char2, $group)) {
                return true;
            }
        }
        
        return $char1 === $char2;
    }
    
    private function extractStoryOutcome($text) {
        $text = strtolower($text);
        
        $outcomes = [
            'hero_victory' => ['victory', 'win', 'triumph', 'success', 'defeat'],
            'rescue_success' => ['save', 'rescue', 'protect', 'safe'],
            'quest_complete' => ['find', 'discover', 'obtain', 'achieve'],
            'peace_restored' => ['peace', 'harmony', 'order', 'calm'],
            'love_triumph' => ['marry', 'wedding', 'love', 'romance'],
            'tragedy' => ['death', 'die', 'tragedy', 'loss', 'fail']
        ];
        
        foreach ($outcomes as $outcome => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    return $outcome;
                }
            }
        }
        
        return null;
    }
    
    private function areSemanticallySimilar($element1, $element2) {
        return $element1 === $element2 || $this->areCharactersSimilar($element1, $element2);
    }
    
    private function assessMeaningQuality($score, $analysis) {
        $quality = [];
        
        if ($score >= 80) {
            $quality['level'] = 'Excellent';
            $quality['description'] = 'Prediction demonstrates deep understanding of story meaning and structure.';
        } elseif ($score >= 65) {
            $quality['level'] = 'Very Good';
            $quality['description'] = 'Prediction shows good grasp of story concepts and narrative flow.';
        } elseif ($score >= 50) {
            $quality['level'] = 'Good';
            $quality['description'] = 'Prediction captures some key story elements and meaning.';
        } elseif ($score >= 35) {
            $quality['level'] = 'Fair';
            $quality['description'] = 'Prediction has limited understanding of story meaning.';
        } elseif ($score >= 20) {
            $quality['level'] = 'Poor';
            $quality['description'] = 'Prediction misses most story concepts and meaning.';
        } else {
            $quality['level'] = 'Very Poor';
            $quality['description'] = 'Prediction appears unrelated to story meaning and context.';
        }
        
        return $quality;
    }
    
    private function generateMeaningBasedFeedback($score, $analysis) {
        $feedback = [];
        
        if ($analysis['universe_match'] === 0) {
            $feedback[] = "❌ Your prediction is from a completely different story genre/universe.";
        } elseif ($analysis['universe_match'] >= 70) {
            $feedback[] = "✅ You correctly identified the story universe and genre!";
        }
        
        if ($analysis['narrative_structure'] >= 70) {
            $feedback[] = "📖 Excellent understanding of the story's narrative structure!";
        } elseif ($analysis['narrative_structure'] < 30) {
            $feedback[] = "📚 Try to better understand the story's narrative flow and structure.";
        }
        
        if ($analysis['semantic_coherence'] >= 70) {
            $feedback[] = "🎯 Your prediction concepts align well with the story's meaning!";
        } elseif ($analysis['semantic_coherence'] < 30) {
            $feedback[] = "🤔 Your prediction concepts don't align with the story's core meaning.";
        }
        
        if ($score >= 80) {
            $feedback[] = "🌟 Outstanding prediction with excellent semantic understanding!";
        } elseif ($score >= 50) {
            $feedback[] = "👍 Good prediction that captures important story meanings!";
        } else {
            $feedback[] = "💭 Focus on understanding the core story concepts and narrative meaning.";
        }
        
        return implode(' ', $feedback);
    }
}

// Test the semantic system
$matcher = new SemanticStoryMatcher();

// Good prediction - should score high
$correctPrediction = "The brave knight will find a magical sword in the ancient cave and use it to defeat the evil dragon, saving the princess and the kingdom.";

// Wrong prediction - should score very low
$wrongPrediction = "The astronaut will travel to Mars and discover alien life forms in underground colonies.";

$actualStory = "Sir Galahad ventured into the mystical caverns beneath the mountain where he discovered an enchanted blade forged by ancient wizards. Armed with this powerful weapon, he confronted the terrible dragon that had been terrorizing the realm for decades. After a fierce battle, the knight emerged victorious, rescuing Princess Elena and restoring peace to the land.";

$result1 = $matcher->analyzeStoryPrediction($correctPrediction, $actualStory);
$result2 = $matcher->analyzeStoryPrediction($wrongPrediction, $actualStory);

echo "=== SEMANTIC MEANING ANALYSIS ===\n";
echo "CORRECT Prediction Score: " . $result1['overall_score'] . "% (" . $result1['meaning_assessment']['level'] . ")\n";
echo "WRONG Prediction Score: " . $result2['overall_score'] . "% (" . $result2['meaning_assessment']['level'] . ")\n";
echo "\nCorrect Prediction Universe: " . $result1['story_universe'] . " vs " . $result1['prediction_universe'] . "\n";
echo "Wrong Prediction Universe: " . $result2['story_universe'] . " vs " . $result2['prediction_universe'] . "\n";
echo "\nCorrect Prediction Feedback: " . $result1['feedback'] . "\n";
echo "Wrong Prediction Feedback: " . $result2['feedback'] . "\n";
?>