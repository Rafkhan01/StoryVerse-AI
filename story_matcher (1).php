<?php
class HighAccuracyStoryMatcher {
    
    private $stopWords = [
        'the', 'is', 'at', 'which', 'on', 'and', 'a', 'to', 'are', 'as', 'was', 
        'were', 'been', 'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 
        'would', 'could', 'should', 'may', 'might', 'must', 'can', 'shall', 'of', 
        'in', 'for', 'with', 'by', 'from', 'up', 'about', 'into', 'through', 
        'during', 'before', 'after', 'above', 'below', 'between', 'among', 
        'until', 'without', 'within', 'he', 'she', 'it', 'they', 'them', 'his', 
        'her', 'their', 'him', 'this', 'that', 'these', 'those', 'then', 'than'
    ];
    
    // Enhanced story elements with higher importance scoring
    private $criticalElements = [
        'main_characters' => [
            'knight' => 15, 'princess' => 15, 'king' => 12, 'queen' => 12, 
            'hero' => 15, 'warrior' => 12, 'wizard' => 15, 'dragon' => 20,
            'prince' => 12, 'villain' => 15, 'witch' => 15, 'beast' => 12
        ],
        'key_objects' => [
            'sword' => 15, 'treasure' => 15, 'crown' => 12, 'ring' => 12,
            'key' => 10, 'potion' => 10, 'shield' => 10, 'bow' => 8,
            'gem' => 10, 'artifact' => 12, 'scroll' => 8, 'wand' => 12
        ],
        'important_places' => [
            'castle' => 12, 'kingdom' => 15, 'forest' => 10, 'cave' => 12,
            'mountain' => 10, 'tower' => 10, 'dungeon' => 12, 'palace' => 12
        ],
        'critical_actions' => [
            'defeat' => 20, 'save' => 18, 'rescue' => 18, 'kill' => 15,
            'fight' => 15, 'battle' => 15, 'find' => 12, 'discover' => 12,
            'marry' => 15, 'escape' => 12, 'capture' => 12, 'steal' => 10
        ]
    ];
    
    // Comprehensive synonym mapping for better matching
    private $synonymGroups = [
        'defeat' => ['beat', 'conquer', 'overcome', 'vanquish', 'destroy', 'kill', 'slay', 'eliminate'],
        'save' => ['rescue', 'protect', 'defend', 'help', 'aid', 'assist', 'deliver'],
        'find' => ['discover', 'locate', 'uncover', 'reveal', 'detect', 'come across'],
        'fight' => ['battle', 'combat', 'clash', 'struggle', 'war', 'conflict'],
        'evil' => ['wicked', 'dark', 'sinister', 'malicious', 'bad', 'villainous'],
        'good' => ['noble', 'righteous', 'virtuous', 'pure', 'heroic', 'brave'],
        'magic' => ['magical', 'enchanted', 'mystical', 'supernatural', 'spell', 'enchant'],
        'treasure' => ['gold', 'riches', 'wealth', 'fortune', 'jewels', 'valuables'],
        'love' => ['romance', 'adore', 'cherish', 'affection', 'devotion'],
        'big' => ['large', 'huge', 'enormous', 'massive', 'giant', 'immense'],
        'small' => ['tiny', 'little', 'miniature', 'petite', 'minor']
    ];
    
    public function analyzeStoryPrediction($userPrediction, $actualStory) {
        // Enhanced multi-layer analysis
        $analysis = [
            'critical_elements' => $this->analyzeCriticalElements($userPrediction, $actualStory),
            'semantic_depth' => $this->analyzeSemanticDepth($userPrediction, $actualStory),
            'plot_coherence' => $this->analyzePlotCoherence($userPrediction, $actualStory),
            'character_actions' => $this->analyzeCharacterActions($userPrediction, $actualStory),
            'story_outcome' => $this->analyzeStoryOutcome($userPrediction, $actualStory),
            'contextual_fit' => $this->analyzeContextualFit($userPrediction, $actualStory)
        ];
        
        // Smart weighted combination with boosters
        $finalScore = $this->calculateIntelligentScore($analysis, $userPrediction, $actualStory);
        
        // Apply final enhancement for good predictions
        $enhancedScore = $this->enhanceGoodPredictions($finalScore, $analysis);
        
        return [
            'overall_score' => $enhancedScore,
            'component_scores' => $analysis,
            'prediction_quality' => $this->assessPredictionQuality($enhancedScore),
            'detailed_feedback' => $this->generateDetailedFeedback($enhancedScore, $analysis)
        ];
    }
    
    private function analyzeCriticalElements($userPrediction, $actualStory) {
        $userText = strtolower($userPrediction);
        $storyText = strtolower($actualStory);
        
        $totalPossibleScore = 0;
        $achievedScore = 0;
        
        foreach ($this->criticalElements as $category => $elements) {
            foreach ($elements as $element => $importance) {
                // Check if user mentioned this element
                if (strpos($userText, $element) !== false) {
                    $totalPossibleScore += $importance;
                    
                    // Check if it actually appears in the story
                    if (strpos($storyText, $element) !== false) {
                        $achievedScore += $importance;
                    } else {
                        // Check synonyms in story
                        $synonyms = $this->getSynonyms($element);
                        foreach ($synonyms as $synonym) {
                            if (strpos($storyText, $synonym) !== false) {
                                $achievedScore += $importance * 0.8; // Slight penalty for synonyms
                                break;
                            }
                        }
                    }
                }
            }
        }
        
        return $totalPossibleScore > 0 ? ($achievedScore / $totalPossibleScore) * 100 : 0;
    }
    
    private function analyzeSemanticDepth($userPrediction, $actualStory) {
        $userWords = $this->extractMeaningfulWords($userPrediction);
        $storyWords = $this->extractMeaningfulWords($actualStory);
        
        if (empty($userWords)) return 0;
        
        $semanticScore = 0;
        $totalWords = count($userWords);
        
        foreach ($userWords as $userWord) {
            $wordScore = 0;
            
            // Direct match - highest score
            if (in_array($userWord, $storyWords)) {
                $wordScore = 100;
            } else {
                // Synonym match
                $synonyms = $this->getSynonyms($userWord);
                foreach ($synonyms as $synonym) {
                    if (in_array($synonym, $storyWords)) {
                        $wordScore = 75; // Good score for synonyms
                        break;
                    }
                }
                
                // Partial match for longer words
                if ($wordScore == 0 && strlen($userWord) > 4) {
                    foreach ($storyWords as $storyWord) {
                        if (strlen($storyWord) > 4) {
                            $similarity = $this->calculateStringSimilarity($userWord, $storyWord);
                            if ($similarity > 0.7) {
                                $wordScore = 40;
                                break;
                            }
                        }
                    }
                }
            }
            
            $semanticScore += $wordScore;
        }
        
        return $semanticScore / $totalWords;
    }
    
    private function analyzePlotCoherence($userPrediction, $actualStory) {
        // Enhanced plot element detection
        $plotElements = [
            'setup' => ['begin', 'start', 'once', 'there was', 'lived', 'long ago'],
            'conflict' => ['but', 'however', 'suddenly', 'problem', 'trouble', 'danger', 'threat'],
            'quest' => ['journey', 'quest', 'search', 'seek', 'find', 'looking for'],
            'confrontation' => ['fight', 'battle', 'face', 'confront', 'challenge', 'against'],
            'resolution' => ['finally', 'victory', 'defeat', 'success', 'peace', 'happy ending'],
            'transformation' => ['become', 'transform', 'change', 'turn into', 'marry', 'wedding']
        ];
        
        $userPlotElements = $this->detectPlotElements($userPrediction, $plotElements);
        $storyPlotElements = $this->detectPlotElements($actualStory, $plotElements);
        
        if (empty($userPlotElements)) return 0;
        
        $matches = array_intersect($userPlotElements, $storyPlotElements);
        $coherenceScore = (count($matches) / count($userPlotElements)) * 100;
        
        // Bonus for getting key plot points right
        $keyElements = ['confrontation', 'resolution'];
        foreach ($keyElements as $key) {
            if (in_array($key, $matches)) {
                $coherenceScore += 15; // Bonus for important plot elements
            }
        }
        
        return min($coherenceScore, 100);
    }
    
    private function analyzeCharacterActions($userPrediction, $actualStory) {
        // Extract character-action pairs
        $userPairs = $this->extractCharacterActionPairs($userPrediction);
        $storyPairs = $this->extractCharacterActionPairs($actualStory);
        
        if (empty($userPairs)) return 0;
        
        $matchScore = 0;
        $totalPairs = count($userPairs);
        
        foreach ($userPairs as $userPair) {
            $bestMatch = 0;
            foreach ($storyPairs as $storyPair) {
                $pairSimilarity = $this->compareCharacterActionPairs($userPair, $storyPair);
                $bestMatch = max($bestMatch, $pairSimilarity);
            }
            $matchScore += $bestMatch;
        }
        
        return $matchScore / $totalPairs;
    }
    
    private function analyzeStoryOutcome($userPrediction, $actualStory) {
        $outcomes = [
            'positive' => ['victory', 'success', 'win', 'save', 'rescue', 'peace', 'happy', 'marry', 'wedding'],
            'negative' => ['defeat', 'lose', 'death', 'die', 'fail', 'tragedy', 'sad', 'loss'],
            'neutral' => ['continue', 'journey', 'travel', 'search', 'ongoing']
        ];
        
        $userOutcome = $this->detectOutcome($userPrediction, $outcomes);
        $storyOutcome = $this->detectOutcome($actualStory, $outcomes);
        
        if ($userOutcome === $storyOutcome && $userOutcome !== null) {
            return 100; // Perfect outcome prediction
        } elseif ($userOutcome !== null && $storyOutcome !== null) {
            return 20; // Wrong outcome
        } else {
            return 50; // Neutral/unclear
        }
    }
    
    private function analyzeContextualFit($userPrediction, $actualStory) {
        // Check if prediction makes sense in story context
        $userWords = $this->extractMeaningfulWords($userPrediction);
        $storyWords = $this->extractMeaningfulWords($actualStory);
        
        if (empty($userWords)) return 0;
        
        $contextualWords = 0;
        foreach ($userWords as $word) {
            // Direct context match
            if (in_array($word, $storyWords)) {
                $contextualWords++;
            } else {
                // Thematic context match
                if ($this->isThematicallyRelated($word, $storyWords)) {
                    $contextualWords += 0.7;
                }
            }
        }
        
        $contextScore = ($contextualWords / count($userWords)) * 100;
        
        // Penalty for completely off-topic predictions
        if ($contextScore < 15) {
            $contextScore *= 0.2; // Severe penalty
        }
        
        return $contextScore;
    }
    
    private function calculateIntelligentScore($analysis, $userPrediction, $actualStory) {
        // Dynamic weighting based on prediction content
        $weights = $this->calculateDynamicWeights($userPrediction, $analysis);
        
        $weightedScore = 0;
        foreach ($analysis as $component => $score) {
            $weightedScore += $score * $weights[$component];
        }
        
        return $weightedScore;
    }
    
    private function calculateDynamicWeights($userPrediction, $analysis) {
        $wordCount = str_word_count($userPrediction);
        $baseWeights = [
            'critical_elements' => 0.30,
            'semantic_depth' => 0.25,
            'plot_coherence' => 0.20,
            'character_actions' => 0.15,
            'story_outcome' => 0.05,
            'contextual_fit' => 0.05
        ];
        
        // Adjust weights based on content richness
        if ($wordCount > 15) {
            // Longer predictions - emphasize plot and character actions
            $baseWeights['plot_coherence'] += 0.05;
            $baseWeights['character_actions'] += 0.05;
            $baseWeights['critical_elements'] -= 0.05;
            $baseWeights['semantic_depth'] -= 0.05;
        }
        
        // If critical elements score is very high, boost its weight
        if ($analysis['critical_elements'] > 70) {
            $baseWeights['critical_elements'] += 0.10;
            $baseWeights['semantic_depth'] -= 0.05;
            $baseWeights['contextual_fit'] -= 0.05;
        }
        
        return $baseWeights;
    }
    
    private function enhanceGoodPredictions($score, $analysis) {
        // Apply enhancement for genuinely good predictions
        $enhancementFactors = 0;
        
        // Multiple high-scoring components indicate good prediction
        $highScores = 0;
        foreach ($analysis as $componentScore) {
            if ($componentScore > 60) $highScores++;
        }
        
        if ($highScores >= 3) {
            $enhancementFactors += 15; // Bonus for multiple good components
        }
        
        // Special bonuses
        if ($analysis['critical_elements'] > 70) {
            $enhancementFactors += 10; // Bonus for getting key elements right
        }
        
        if ($analysis['story_outcome'] > 80) {
            $enhancementFactors += 10; // Bonus for correct outcome prediction
        }
        
        if ($analysis['plot_coherence'] > 70) {
            $enhancementFactors += 8; // Bonus for good plot understanding
        }
        
        $enhancedScore = $score + $enhancementFactors;
        
        // Apply score curve for better discrimination
        if ($enhancedScore >= 60) {
            $enhancedScore = 60 + ($enhancedScore - 60) * 1.3; // Boost good scores
        } else if ($enhancedScore < 30) {
            $enhancedScore = $enhancedScore * 0.7; // Reduce poor scores
        }
        
        return round(max(0, min(100, $enhancedScore)), 2);
    }
    
    // Helper methods
    private function extractMeaningfulWords($text) {
        $words = str_word_count(strtolower($text), 1);
        return array_filter($words, function($word) {
            return strlen($word) > 2 && !in_array($word, $this->stopWords);
        });
    }
    
    private function getSynonyms($word) {
        foreach ($this->synonymGroups as $key => $synonyms) {
            if ($key === $word || in_array($word, $synonyms)) {
                return array_merge([$key], $synonyms);
            }
        }
        return [];
    }
    
    private function calculateStringSimilarity($str1, $str2) {
        $len1 = strlen($str1);
        $len2 = strlen($str2);
        $maxLen = max($len1, $len2);
        
        if ($maxLen == 0) return 1;
        
        $similarity = 0;
        similar_text($str1, $str2, $similarity);
        return $similarity / 100;
    }
    
    private function detectPlotElements($text, $plotElements) {
        $text = strtolower($text);
        $detected = [];
        
        foreach ($plotElements as $element => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $detected[] = $element;
                    break;
                }
            }
        }
        
        return array_unique($detected);
    }
    
    private function extractCharacterActionPairs($text) {
        // Simplified character-action extraction
        $characters = ['hero', 'knight', 'princess', 'king', 'queen', 'dragon', 'wizard', 'prince'];
        $actions = ['fight', 'save', 'rescue', 'defeat', 'find', 'marry', 'kill', 'escape'];
        
        $pairs = [];
        $text = strtolower($text);
        
        foreach ($characters as $character) {
            if (strpos($text, $character) !== false) {
                foreach ($actions as $action) {
                    if (strpos($text, $action) !== false) {
                        $pairs[] = ['character' => $character, 'action' => $action];
                    }
                }
            }
        }
        
        return $pairs;
    }
    
    private function compareCharacterActionPairs($pair1, $pair2) {
        $charMatch = ($pair1['character'] === $pair2['character']) ? 50 : 0;
        $actionMatch = ($pair1['action'] === $pair2['action']) ? 50 : 0;
        
        // Check synonyms for actions
        if ($actionMatch == 0) {
            $synonyms = $this->getSynonyms($pair1['action']);
            if (in_array($pair2['action'], $synonyms)) {
                $actionMatch = 40;
            }
        }
        
        return $charMatch + $actionMatch;
    }
    
    private function detectOutcome($text, $outcomes) {
        $text = strtolower($text);
        $outcomeScores = [];
        
        foreach ($outcomes as $outcome => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $score++;
                }
            }
            $outcomeScores[$outcome] = $score;
        }
        
        $maxScore = max($outcomeScores);
        if ($maxScore > 0) {
            return array_search($maxScore, $outcomeScores);
        }
        
        return null;
    }
    
    private function isThematicallyRelated($word, $storyWords) {
        $themes = [
            'fantasy' => ['magic', 'spell', 'enchant', 'mystical', 'supernatural'],
            'adventure' => ['journey', 'quest', 'explore', 'travel', 'discover'],
            'combat' => ['fight', 'battle', 'war', 'conflict', 'struggle'],
            'royal' => ['king', 'queen', 'prince', 'princess', 'crown', 'castle']
        ];
        
        foreach ($themes as $theme => $themeWords) {
            if (in_array($word, $themeWords)) {
                foreach ($themeWords as $themeWord) {
                    if (in_array($themeWord, $storyWords)) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    private function assessPredictionQuality($score) {
        if ($score >= 80) return "Excellent";
        if ($score >= 65) return "Very Good";
        if ($score >= 50) return "Good";
        if ($score >= 35) return "Fair";
        if ($score >= 20) return "Poor";
        return "Very Poor";
    }
    
    private function generateDetailedFeedback($score, $analysis) {
        $feedback = [];
        
        if ($score >= 75) {
            $feedback[] = "🎯 Outstanding prediction! You demonstrated excellent story comprehension.";
        } elseif ($score >= 60) {
            $feedback[] = "👏 Very good prediction! You captured many key story elements.";
        } elseif ($score >= 45) {
            $feedback[] = "👍 Good prediction with several correct elements.";
        } elseif ($score >= 30) {
            $feedback[] = "🤔 Fair attempt, but missed many important story details.";
        } else {
            $feedback[] = "💭 Your prediction needs improvement. Focus on the story's main themes.";
        }
        
        // Specific component feedback
        if ($analysis['critical_elements'] > 70) {
            $feedback[] = "✨ Excellent identification of key story elements!";
        }
        if ($analysis['plot_coherence'] > 70) {
            $feedback[] = "📚 Great understanding of the story structure!";
        }
        if ($analysis['story_outcome'] > 80) {
            $feedback[] = "🎊 Perfect prediction of the story's outcome!";
        }
        
        return implode(' ', $feedback);
    }
}

// Test the enhanced system
$matcher = new HighAccuracyStoryMatcher();

// Good prediction test
$correctPrediction = "The brave knight will find a magical sword in the ancient cave and use it to defeat the evil dragon, saving the princess and the kingdom.";
$actualStory = "Sir Galahad ventured into the mystical caverns beneath the mountain where he discovered an enchanted blade forged by ancient wizards. Armed with this powerful weapon, he confronted the terrible dragon that had been terrorizing the realm for decades. After a fierce battle, the knight emerged victorious, rescuing Princess Elena and restoring peace to the land.";

$result1 = $matcher->analyzeStoryPrediction($correctPrediction, $actualStory);

// Bad prediction test
$wrongPrediction = "The astronaut will travel to Mars and discover alien life forms in underground colonies.";
$result2 = $matcher->analyzeStoryPrediction($wrongPrediction, $actualStory);

echo "CORRECT Prediction Score: " . $result1['overall_score'] . "% (" . $result1['prediction_quality'] . ")\n";
echo "WRONG Prediction Score: " . $result2['overall_score'] . "% (" . $result2['prediction_quality'] . ")\n";
echo "\nCorrect Prediction Feedback: " . $result1['detailed_feedback'] . "\n";
echo "Wrong Prediction Feedback: " . $result2['detailed_feedback'] . "\n";
?>