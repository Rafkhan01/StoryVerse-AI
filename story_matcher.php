<?php
class EnhancedStoryMatcher {
    
    private $stopWords = [
        'the', 'is', 'at', 'which', 'on', 'and', 'a', 'to', 'are', 'as', 'was', 
        'were', 'been', 'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 
        'would', 'could', 'should', 'may', 'might', 'must', 'can', 'shall', 'of', 
        'in', 'for', 'with', 'by', 'from', 'up', 'about', 'into', 'through', 
        'during', 'before', 'after', 'above', 'below', 'between', 'among', 
        'until', 'without', 'within', 'he', 'she', 'it', 'they', 'them', 'his', 
        'her', 'their', 'him', 'this', 'that', 'these', 'those', 'then', 'than'
    ];
    
    private $storyElements = [
        'characters' => [
            'hero', 'princess', 'king', 'queen', 'knight', 'wizard', 'dragon', 
            'witch', 'prince', 'villain', 'warrior', 'thief', 'merchant', 'guard',
            'soldier', 'peasant', 'lord', 'maiden', 'beast', 'giant', 'dwarf',
            'fairy', 'demon', 'angel', 'monster', 'ghost', 'spirit'
        ],
        'objects' => [
            'sword', 'treasure', 'crown', 'ring', 'book', 'key', 'potion', 
            'shield', 'bow', 'arrow', 'gem', 'artifact', 'scroll', 'wand',
            'staff', 'armor', 'helmet', 'dagger', 'gold', 'silver', 'jewel',
            'crystal', 'orb', 'amulet', 'necklace', 'bracelet'
        ],
        'places' => [
            'castle', 'forest', 'cave', 'mountain', 'village', 'kingdom', 
            'tower', 'dungeon', 'bridge', 'river', 'temple', 'palace', 'garden',
            'meadow', 'valley', 'cliff', 'ocean', 'sea', 'lake', 'desert',
            'island', 'mansion', 'cottage', 'tavern', 'church'
        ],
        'actions' => [
            'fight', 'battle', 'kill', 'save', 'rescue', 'find', 'discover',
            'steal', 'escape', 'capture', 'defeat', 'win', 'lose', 'marry',
            'die', 'live', 'travel', 'journey', 'quest', 'search', 'hunt',
            'chase', 'flee', 'hide', 'reveal', 'betray', 'love', 'hate'
        ],
        'magic' => [
            'spell', 'curse', 'enchant', 'magic', 'magical', 'supernatural',
            'transform', 'vanish', 'appear', 'teleport', 'heal', 'summon',
            'conjure', 'bewitch', 'charm', 'hex'
        ]
    ];
    
    public function analyzeStoryPrediction($userPrediction, $actualStory) {
        // Step 1: Extract meaningful content (remove stop words)
        $userContent = $this->extractMeaningfulContent($userPrediction);
        $storyContent = $this->extractMeaningfulContent($actualStory);
        
        // Step 2: Multiple scoring layers with strict penalties
        $scores = [
            'exact_match' => $this->calculateExactMatches($userContent, $storyContent),
            'semantic_match' => $this->calculateSemanticMatches($userPrediction, $actualStory),
            'story_elements' => $this->calculateStoryElementMatches($userPrediction, $actualStory),
            'plot_progression' => $this->calculatePlotProgression($userPrediction, $actualStory),
            'context_relevance' => $this->calculateContextRelevance($userPrediction, $actualStory)
        ];
        
        // Step 3: Apply strict weighting with penalty system
        $finalScore = $this->calculateWeightedScore($scores, $userPrediction, $actualStory);
        
        // Step 4: Apply final calibration
        $calibratedScore = $this->calibrateScore($finalScore, $userPrediction, $actualStory);
        
        return [
            'overall_score' => $calibratedScore,
            'component_scores' => $scores,
            'analysis' => $this->generateAnalysis($scores, $calibratedScore),
            'feedback' => $this->generateFeedback($calibratedScore, $scores)
        ];
    }
    
    private function extractMeaningfulContent($text) {
        $words = str_word_count(strtolower($text), 1);
        $meaningful = [];
        
        foreach ($words as $word) {
            if (strlen($word) > 2 && !in_array($word, $this->stopWords)) {
                $meaningful[] = $word;
            }
        }
        
        return $meaningful;
    }
    
    private function calculateExactMatches($userContent, $storyContent) {
        if (empty($userContent)) return 0;
        
        $matches = array_intersect($userContent, $storyContent);
        $exactScore = (count($matches) / count($userContent)) * 100;
        
        // Bonus for rare/important word matches
        $bonus = 0;
        foreach ($matches as $match) {
            if (strlen($match) > 6) { // Longer words are often more meaningful
                $bonus += 5;
            }
        }
        
        return min($exactScore + $bonus, 100);
    }
    
    private function calculateSemanticMatches($userPrediction, $actualStory) {
        $synonymGroups = [
            ['kill', 'murder', 'slay', 'defeat', 'destroy', 'eliminate'],
            ['find', 'discover', 'locate', 'uncover', 'reveal'],
            ['fight', 'battle', 'combat', 'clash', 'struggle'],
            ['save', 'rescue', 'protect', 'defend', 'help'],
            ['love', 'adore', 'cherish', 'worship'],
            ['treasure', 'gold', 'riches', 'wealth', 'fortune'],
            ['magic', 'spell', 'enchant', 'supernatural', 'mystical'],
            ['evil', 'wicked', 'dark', 'sinister', 'malicious'],
            ['good', 'noble', 'righteous', 'virtuous', 'pure']
        ];
        
        $userWords = str_word_count(strtolower($userPrediction), 1);
        $storyText = strtolower($actualStory);
        
        $semanticMatches = 0;
        $totalMeaningfulWords = 0;
        
        foreach ($userWords as $userWord) {
            if (in_array($userWord, $this->stopWords) || strlen($userWord) <= 2) continue;
            
            $totalMeaningfulWords++;
            
            // Check direct match first (higher weight)
            if (strpos($storyText, $userWord) !== false) {
                $semanticMatches += 1;
                continue;
            }
            
            // Check synonym matches (lower weight)
            foreach ($synonymGroups as $group) {
                if (in_array($userWord, $group)) {
                    foreach ($group as $synonym) {
                        if ($synonym !== $userWord && strpos($storyText, $synonym) !== false) {
                            $semanticMatches += 0.7; // Partial credit for synonyms
                            break 2;
                        }
                    }
                }
            }
        }
        
        return $totalMeaningfulWords > 0 ? ($semanticMatches / $totalMeaningfulWords) * 100 : 0;
    }
    
    private function calculateStoryElementMatches($userPrediction, $actualStory) {
        $userText = strtolower($userPrediction);
        $storyText = strtolower($actualStory);
        
        $totalScore = 0;
        $elementCategories = 0;
        
        foreach ($this->storyElements as $category => $elements) {
            $userElements = [];
            $storyElements = [];
            
            foreach ($elements as $element) {
                if (strpos($userText, $element) !== false) {
                    $userElements[] = $element;
                }
                if (strpos($storyText, $element) !== false) {
                    $storyElements[] = $element;
                }
            }
            
            if (!empty($userElements)) {
                $elementCategories++;
                $matches = array_intersect($userElements, $storyElements);
                $categoryScore = count($userElements) > 0 ? (count($matches) / count($userElements)) * 100 : 0;
                
                // Weight important categories more
                $weight = ($category === 'actions' || $category === 'characters') ? 1.2 : 1.0;
                $totalScore += $categoryScore * $weight;
            }
        }
        
        return $elementCategories > 0 ? $totalScore / $elementCategories : 0;
    }
    
    private function calculatePlotProgression($userPrediction, $actualStory) {
        $plotMarkers = [
            'beginning' => ['start', 'begin', 'once', 'first', 'initially'],
            'conflict' => ['but', 'however', 'suddenly', 'then', 'problem', 'trouble'],
            'action' => ['fight', 'battle', 'search', 'journey', 'quest', 'attempt'],
            'climax' => ['finally', 'ultimate', 'decisive', 'crucial', 'last'],
            'resolution' => ['end', 'victory', 'defeat', 'peace', 'solved', 'finished']
        ];
        
        $userMarkers = $this->findPlotMarkers($userPrediction, $plotMarkers);
        $storyMarkers = $this->findPlotMarkers($actualStory, $plotMarkers);
        
        $matches = array_intersect_key($userMarkers, $storyMarkers);
        $totalUserMarkers = count($userMarkers);
        
        return $totalUserMarkers > 0 ? (count($matches) / $totalUserMarkers) * 100 : 0;
    }
    
    private function findPlotMarkers($text, $plotMarkers) {
        $text = strtolower($text);
        $foundMarkers = [];
        
        foreach ($plotMarkers as $phase => $markers) {
            foreach ($markers as $marker) {
                if (strpos($text, $marker) !== false) {
                    $foundMarkers[$phase] = true;
                    break;
                }
            }
        }
        
        return $foundMarkers;
    }
    
    private function calculateContextRelevance($userPrediction, $actualStory) {
        // Check if prediction is completely off-topic
        $userWords = $this->extractMeaningfulContent($userPrediction);
        $storyWords = $this->extractMeaningfulContent($actualStory);
        
        if (empty($userWords)) return 0;
        
        $relevantWords = 0;
        foreach ($userWords as $word) {
            // Check if word or similar concept exists in story
            if (in_array($word, $storyWords)) {
                $relevantWords++;
            } else {
                // Check for partial matches or word stems
                foreach ($storyWords as $storyWord) {
                    if (strlen($word) > 4 && strlen($storyWord) > 4) {
                        $similarity = similar_text($word, $storyWord);
                        if ($similarity > 0.6 * max(strlen($word), strlen($storyWord))) {
                            $relevantWords += 0.5;
                            break;
                        }
                    }
                }
            }
        }
        
        $relevanceScore = ($relevantWords / count($userWords)) * 100;
        
        // Heavy penalty for completely irrelevant predictions
        if ($relevanceScore < 10) {
            $relevanceScore *= 0.3; // Severe penalty
        }
        
        return $relevanceScore;
    }
    
    private function calculateWeightedScore($scores, $userPrediction, $actualStory) {
        // Strict weighting system
        $weights = [
            'exact_match' => 0.25,
            'semantic_match' => 0.25,
            'story_elements' => 0.25,
            'plot_progression' => 0.15,
            'context_relevance' => 0.10
        ];
        
        $weightedScore = 0;
        foreach ($scores as $component => $score) {
            $weightedScore += $score * $weights[$component];
        }
        
        // Apply penalty for very short predictions
        $wordCount = str_word_count($userPrediction);
        if ($wordCount < 5) {
            $weightedScore *= 0.8;
        }
        
        // Apply penalty for predictions with no story elements
        if ($scores['story_elements'] < 10) {
            $weightedScore *= 0.7;
        }
        
        return $weightedScore;
    }
    
    private function calibrateScore($score, $userPrediction, $actualStory) {
        // Final calibration to ensure proper score distribution
        
        // If context relevance is very low, cap the maximum score
        $contextRelevance = $this->calculateContextRelevance($userPrediction, $actualStory);
        if ($contextRelevance < 15) {
            $score = min($score, 30); // Cap at 30% for irrelevant predictions
        }
        
        // Apply floor and ceiling
        $score = max(0, min(100, $score));
        
        // Curve adjustment for better discrimination
        if ($score < 50) {
            $score = $score * 0.8; // Make bad scores worse
        } else {
            $score = 50 + ($score - 50) * 1.2; // Make good scores better
        }
        
        return round(max(0, min(100, $score)), 2);
    }
    
    private function generateAnalysis($scores, $finalScore) {
        $analysis = [];
        
        if ($finalScore >= 75) {
            $analysis[] = "Excellent prediction with strong alignment to the actual story.";
        } elseif ($finalScore >= 50) {
            $analysis[] = "Good prediction with several correct elements.";
        } elseif ($finalScore >= 25) {
            $analysis[] = "Partial match with some relevant elements.";
        } else {
            $analysis[] = "Poor prediction with minimal relevance to the actual story.";
        }
        
        // Component analysis
        if ($scores['exact_match'] > 60) {
            $analysis[] = "Strong word-level matches found.";
        }
        if ($scores['story_elements'] > 60) {
            $analysis[] = "Good identification of story elements.";
        }
        if ($scores['context_relevance'] < 20) {
            $analysis[] = "Prediction appears largely irrelevant to the story context.";
        }
        
        return implode(' ', $analysis);
    }
    
    private function generateFeedback($score, $scores) {
        if ($score >= 75) {
            return "🎯 Outstanding! Your prediction captured the essence of the story remarkably well.";
        } elseif ($score >= 60) {
            return "👏 Great job! You predicted several key elements correctly.";
        } elseif ($score >= 40) {
            return "👍 Good effort! You got some important details right.";
        } elseif ($score >= 25) {
            return "🤔 Fair attempt, but your prediction missed many key story elements.";
        } else {
            return "💭 Your prediction was quite different from the actual story. Try focusing on the story's themes and context.";
        }
    }
}

// Example usage:
$matcher = new EnhancedStoryMatcher();

// Test with correct prediction
$correctPrediction = "The brave knight will find a magical sword in the ancient cave and use it to defeat the evil dragon, saving the princess and the kingdom.";
$actualStory = "Sir Galahad ventured into the mystical caverns beneath the mountain where he discovered an enchanted blade forged by ancient wizards. Armed with this powerful weapon, he confronted the terrible dragon that had been terrorizing the realm for decades. After a fierce battle, the knight emerged victorious, rescuing Princess Elena and restoring peace to the land.";

$result1 = $matcher->analyzeStoryPrediction($correctPrediction, $actualStory);

// Test with wrong prediction
$wrongPrediction = "The astronaut will travel to Mars and discover alien life forms in underground colonies.";

$result2 = $matcher->analyzeStoryPrediction($wrongPrediction, $actualStory);

echo "CORRECT Prediction Score: " . $result1['overall_score'] . "%\n";
echo "WRONG Prediction Score: " . $result2['overall_score'] . "%\n";
echo "\nCorrect Prediction Analysis: " . $result1['analysis'] . "\n";
echo "Wrong Prediction Analysis: " . $result2['analysis'] . "\n";
?>