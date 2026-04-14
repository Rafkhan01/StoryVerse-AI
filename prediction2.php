<?php
class AdvancedTextMatcher {
    private $synonyms = [
        'happy' => ['joyful', 'cheerful', 'glad', 'pleased', 'delighted'],
        'sad' => ['unhappy', 'sorrowful', 'melancholy', 'depressed'],
        'big' => ['large', 'huge', 'enormous', 'massive', 'giant'],
        'small' => ['tiny', 'little', 'miniature', 'petite'],
        'fight' => ['battle', 'combat', 'struggle', 'clash', 'conflict'],
        'find' => ['discover', 'locate', 'uncover', 'detect'],
        'kill' => ['murder', 'slay', 'eliminate', 'destroy'],
        'love' => ['adore', 'cherish', 'worship', 'treasure'],
        // Add more synonyms based on your story themes
    ];
    
    public function stemWord($word) {
        // Simple stemming rules
        $word = strtolower($word);
        
        // Remove common suffixes
        $suffixes = ['ing', 'ed', 'er', 'est', 'ly', 's'];
        foreach ($suffixes as $suffix) {
            if (substr($word, -strlen($suffix)) === $suffix) {
                $word = substr($word, 0, -strlen($suffix));
                break;
            }
        }
        
        return $word;
    }
    
    public function expandWithSynonyms($word) {
        $word = strtolower($word);
        $expanded = [$word];
        
        // Check if word is a key in synonyms
        if (isset($this->synonyms[$word])) {
            $expanded = array_merge($expanded, $this->synonyms[$word]);
        }
        
        // Check if word is in any synonym list
        foreach ($this->synonyms as $key => $synonymList) {
            if (in_array($word, $synonymList)) {
                $expanded[] = $key;
                $expanded = array_merge($expanded, $synonymList);
                break;
            }
        }
        
        return array_unique($expanded);
    }
    
    public function calculateAdvancedSimilarity($userPrediction, $largeStory) {
        $userWords = str_word_count(strtolower($userPrediction), 1);
        $storyText = strtolower($largeStory);
        
        $totalMatches = 0;
        $totalWords = count($userWords);
        
        foreach ($userWords as $word) {
            $stemmed = $this->stemWord($word);
            $synonyms = $this->expandWithSynonyms($stemmed);
            
            $wordScore = 0;
            foreach ($synonyms as $synonym) {
                if (strpos($storyText, $synonym) !== false) {
                    $wordScore = 1;
                    break;
                }
            }
            
            $totalMatches += $wordScore;
        }
        
        return $totalWords > 0 ? round(($totalMatches / $totalWords) * 100, 2) : 0;
    }
}
class ContextualMatcher {
    public function getContextWindow($text, $keyword, $windowSize = 10) {
        $words = str_word_count(strtolower($text), 1, 'àáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ');
        $keywordPos = array_search(strtolower($keyword), $words);
        
        if ($keywordPos === false) return [];
        
        $start = max(0, $keywordPos - $windowSize);
        $end = min(count($words) - 1, $keywordPos + $windowSize);
        
        return array_slice($words, $start, $end - $start + 1);
    }
    
    public function calculateContextualMatch($userPrediction, $largeStory) {
        $userWords = str_word_count(strtolower($userPrediction), 1);
        $totalScore = 0;
        $scoredWords = 0;
        
        foreach ($userWords as $userWord) {
            if (strlen($userWord) < 3) continue; // Skip short words
            
            $userContext = $this->getContextWindow($userPrediction, $userWord, 5);
            $storyContext = $this->getContextWindow($largeStory, $userWord, 5);
            
            if (!empty($storyContext)) {
                $contextSimilarity = $this->compareContexts($userContext, $storyContext);
                $totalScore += $contextSimilarity;
                $scoredWords++;
            }
        }
        
        return $scoredWords > 0 ? round($totalScore / $scoredWords, 2) : 0;
    }
    
    private function compareContexts($context1, $context2) {
        $common = array_intersect($context1, $context2);
        $total = array_unique(array_merge($context1, $context2));
        
        return count($total) > 0 ? (count($common) / count($total)) * 100 : 0;
    }
}
class PlotStructureMatcher {
    private $plotElements = [
        'setup' => ['begin', 'start', 'once', 'lived', 'was', 'had', 'there'],
        'conflict' => ['but', 'however', 'suddenly', 'then', 'problem', 'trouble', 'danger'],
        'rising_action' => ['tried', 'attempted', 'struggled', 'fought', 'searched', 'journey'],
        'climax' => ['finally', 'at last', 'ultimate', 'decisive', 'crucial', 'turning point'],
        'resolution' => ['ended', 'concluded', 'solved', 'peace', 'happy', 'victory', 'success']
    ];
    
    public function identifyPlotStructure($text) {
        $text = strtolower($text);
        $structure = [];
        
        foreach ($this->plotElements as $element => $indicators) {
            $score = 0;
            foreach ($indicators as $indicator) {
                if (strpos($text, $indicator) !== false) {
                    $score++;
                }
            }
            if ($score > 0) {
                $structure[$element] = $score;
            }
        }
        
        return $structure;
    }
    
    public function calculateStructuralMatch($userPrediction, $largeStory) {
        $userStructure = $this->identifyPlotStructure($userPrediction);
        $storyStructure = $this->identifyPlotStructure($largeStory);
        
        $matches = 0;
        $totalElements = count($userStructure);
        
        foreach ($userStructure as $element => $userScore) {
            if (isset($storyStructure[$element])) {
                // Give partial credit based on relative strength
                $matches += min($userScore / max($userScore, $storyStructure[$element]), 1);
            }
        }
        
        return $totalElements > 0 ? round(($matches / $totalElements) * 100, 2) : 0;
    }
}
class EntityActionMatcher {
    private $entities = [
        'characters' => ['hero', 'princess', 'king', 'queen', 'knight', 'wizard', 'dragon', 'witch', 'prince', 'villain', 'warrior', 'thief'],
        'objects' => ['sword', 'treasure', 'crown', 'ring', 'book', 'key', 'potion', 'shield', 'bow', 'arrow', 'gem', 'artifact'],
        'places' => ['castle', 'forest', 'cave', 'mountain', 'village', 'kingdom', 'tower', 'dungeon', 'bridge', 'river', 'temple'],
        'emotions' => ['love', 'hate', 'fear', 'anger', 'joy', 'sadness', 'hope', 'despair', 'courage', 'bravery']
    ];
    
    private $actions = [
        'combat' => ['fight', 'battle', 'attack', 'defend', 'strike', 'defeat', 'victory', 'win', 'lose'],
        'movement' => ['go', 'walk', 'run', 'fly', 'climb', 'jump', 'travel', 'journey', 'escape', 'chase'],
        'discovery' => ['find', 'discover', 'search', 'seek', 'locate', 'uncover', 'reveal', 'explore'],
        'interaction' => ['meet', 'talk', 'speak', 'tell', 'ask', 'answer', 'help', 'save', 'rescue'],
        'magic' => ['cast', 'enchant', 'curse', 'transform', 'heal', 'summon', 'vanish', 'appear']
    ];
    
    public function extractEntities($text) {
        $text = strtolower($text);
        $foundEntities = [];
        
        foreach ($this->entities as $category => $entityList) {
            foreach ($entityList as $entity) {
                if (strpos($text, $entity) !== false) {
                    $foundEntities[$category][] = $entity;
                }
            }
        }
        
        return $foundEntities;
    }
    
    public function extractActions($text) {
        $text = strtolower($text);
        $foundActions = [];
        
        foreach ($this->actions as $category => $actionList) {
            foreach ($actionList as $action) {
                if (strpos($text, $action) !== false) {
                    $foundActions[$category][] = $action;
                }
            }
        }
        
        return $foundActions;
    }
    
    public function calculateEntityActionMatch($userPrediction, $largeStory) {
        $userEntities = $this->extractEntities($userPrediction);
        $storyEntities = $this->extractEntities($largeStory);
        
        $userActions = $this->extractActions($userPrediction);
        $storyActions = $this->extractActions($largeStory);
        
        // Calculate entity matches
        $entityScore = $this->compareEntitySets($userEntities, $storyEntities);
        
        // Calculate action matches
        $actionScore = $this->compareEntitySets($userActions, $storyActions);
        
        // Weighted combination (entities are often more important than actions)
        $finalScore = ($entityScore * 0.6) + ($actionScore * 0.4);
        
        return round($finalScore, 2);
    }
    
    private function compareEntitySets($userSet, $storySet) {
        $totalScore = 0;
        $categories = 0;
        
        foreach ($userSet as $category => $userItems) {
            $categories++;
            if (isset($storySet[$category])) {
                $common = array_intersect($userItems, $storySet[$category]);
                $categoryScore = count($userItems) > 0 ? (count($common) / count($userItems)) * 100 : 0;
                $totalScore += $categoryScore;
            }
        }
        
        return $categories > 0 ? $totalScore / $categories : 0;
    }
}
class UltimateStoryMatcher {
    private $textMatcher;
    private $contextMatcher;
    private $entityMatcher;
    private $plotMatcher;
    
    public function __construct() {
        $this->textMatcher = new AdvancedTextMatcher();
        $this->contextMatcher = new ContextualMatcher();
        $this->entityMatcher = new EntityActionMatcher();
        $this->plotMatcher = new PlotStructureMatcher();
    }
    
    public function calculateUltimateMatch($userPrediction, $largeStory) {
        // Multiple analysis layers
        $synonymScore = $this->textMatcher->calculateAdvancedSimilarity($userPrediction, $largeStory);
        $contextScore = $this->contextMatcher->calculateContextualMatch($userPrediction, $largeStory);
        $entityScore = $this->entityMatcher->calculateEntityActionMatch($userPrediction, $largeStory);
        $plotScore = $this->plotMatcher->calculateStructuralMatch($userPrediction, $largeStory);
        
        // Additional semantic analysis
        $keyMomentScore = $this->identifyKeyMoments($userPrediction, $largeStory);
        $emotionalToneScore = $this->compareEmotionalTone($userPrediction, $largeStory);
        
        // Smart weighting based on prediction length and content
        $weights = $this->calculateDynamicWeights($userPrediction);
        
        $finalScore = ($synonymScore * $weights['synonym']) +
                      ($contextScore * $weights['context']) +
                      ($entityScore * $weights['entity']) +
                      ($plotScore * $weights['plot']) +
                      ($keyMomentScore * $weights['moment']) +
                      ($emotionalToneScore * $weights['emotion']);
        
        return [
            'overall_score' => round($finalScore, 2),
            'confidence_level' => $this->calculateConfidence($finalScore, $userPrediction),
            'detailed_breakdown' => [
                'synonym_match' => $synonymScore,
                'contextual_match' => $contextScore,
                'entity_action_match' => $entityScore,
                'plot_structure_match' => $plotScore,
                'key_moments_match' => $keyMomentScore,
                'emotional_tone_match' => $emotionalToneScore
            ],
            'feedback' => $this->generateDetailedFeedback($finalScore, [
                'synonym' => $synonymScore,
                'context' => $contextScore,
                'entity' => $entityScore,
                'plot' => $plotScore
            ])
        ];
    }
    
    private function identifyKeyMoments($userPrediction, $largeStory) {
        $keyMoments = ['death', 'marriage', 'battle', 'discovery', 'betrayal', 'rescue', 'transformation'];
        $userMoments = [];
        $storyMoments = [];
        
        foreach ($keyMoments as $moment) {
            if (strpos(strtolower($userPrediction), $moment) !== false) {
                $userMoments[] = $moment;
            }
            if (strpos(strtolower($largeStory), $moment) !== false) {
                $storyMoments[] = $moment;
            }
        }
        
        $matches = array_intersect($userMoments, $storyMoments);
        return count($userMoments) > 0 ? (count($matches) / count($userMoments)) * 100 : 0;
    }
    
    private function compareEmotionalTone($userPrediction, $largeStory) {
        $positiveWords = ['happy', 'joy', 'love', 'peace', 'victory', 'success', 'wonderful'];
        $negativeWords = ['sad', 'death', 'fear', 'anger', 'defeat', 'tragedy', 'loss'];
        
        $userPositive = $this->countWordMatches($userPrediction, $positiveWords);
        $userNegative = $this->countWordMatches($userPrediction, $negativeWords);
        $storyPositive = $this->countWordMatches($largeStory, $positiveWords);
        $storyNegative = $this->countWordMatches($largeStory, $negativeWords);
        
        $userTone = $userPositive > $userNegative ? 'positive' : 'negative';
        $storyTone = $storyPositive > $storyNegative ? 'positive' : 'negative';
        
        return $userTone === $storyTone ? 100 : 0;
    }
    
    private function countWordMatches($text, $words) {
        $count = 0;
        $text = strtolower($text);
        foreach ($words as $word) {
            if (strpos($text, $word) !== false) {
                $count++;
            }
        }
        return $count;
    }
    
    private function calculateDynamicWeights($userPrediction) {
        $wordCount = str_word_count($userPrediction);
        
        // Adjust weights based on prediction length and content
        if ($wordCount < 10) {
            // Short predictions - focus more on entities and key moments
            return [
                'synonym' => 0.15,
                'context' => 0.15,
                'entity' => 0.35,
                'plot' => 0.15,
                'moment' => 0.15,
                'emotion' => 0.05
            ];
        } else {
            // Longer predictions - more balanced approach
            return [
                'synonym' => 0.20,
                'context' => 0.25,
                'entity' => 0.25,
                'plot' => 0.15,
                'moment' => 0.10,
                'emotion' => 0.05
            ];
        }
    }
    
    private function calculateConfidence($score, $userPrediction) {
        $wordCount = str_word_count($userPrediction);
        $baseConfidence = min($score, 100);
        
        // Adjust confidence based on prediction detail
        if ($wordCount < 5) {
            $baseConfidence *= 0.8; // Lower confidence for very short predictions
        } elseif ($wordCount > 20) {
            $baseConfidence *= 1.1; // Higher confidence for detailed predictions
        }
        
        return round(min($baseConfidence, 100), 2);
    }
    
    private function generateDetailedFeedback($overallScore, $scores) {
        $feedback = [];
        
        if ($overallScore >= 80) {
            $feedback[] = "🎯 Excellent prediction! You captured the story's essence perfectly.";
        } elseif ($overallScore >= 60) {
            $feedback[] = "👍 Great job! Your prediction aligned well with the actual story.";
        } elseif ($overallScore >= 40) {
            $feedback[] = "👌 Good effort! You got several key elements right.";
        } else {
            $feedback[] = "🤔 Keep trying! Your prediction was quite different from the story.";
        }
        
        // Specific feedback based on component scores
        if ($scores['entity'] > 70) {
            $feedback[] = "✨ You correctly identified key characters and objects!";
        }
        if ($scores['plot'] > 70) {
            $feedback[] = "📚 You understood the story structure well!";
        }
        if ($scores['context'] > 70) {
            $feedback[] = "🔍 Your contextual understanding was spot-on!";
        }
        
        return implode(' ', $feedback);
    }
}

// Usage
$matcher = new UltimateStoryMatcher();
$userPrediction = " My gut says this isn't just about Aris. What if the body on the 
bed isn't Aris's, but someone else's? Or perhaps, the 'Revelation Chamber' is where 
multiple consciousnesses are being simulated or combined. The 'Patient: Aris Thorne' 
refers to his mind being the host, but the 'memory retrieval' means they are extracting 
memories from others and funneling them through his brain. The 'sabotage' and 
glitches are actually interference from another mind trying to escape or warn him. 
Part 2 will reveal a sinister project where minds are being exploited or merged, and 
Aris now holds fragments of other people's memories, which will lead him to a much 
larger conspiracy.";
$largeStory = "The Simulacrum Key
Part 2: Echoes in the Grey
Chapter 5: The Architect of Dreams
The blackness shattered, not into light, but into a blinding white. Aris blinked, disoriented, his senses re-calibrating. The ozone scent was gone, replaced by the faint, sterile tang of antiseptic. The hum he had grown accustomed to was now a high-pitched, insistent beep. He felt heavy, strangely disconnected from his own limbs.

He was lying down. On a bed. He tried to move, but his muscles felt like lead. His vision slowly cleared. Above him, a pristine white ceiling, a single, recessed light glowing with an almost surgical intensity. He turned his head, a monumental effort, and saw a familiar face, etched with worry and exhaustion, leaning over him.

Aris? Can you hear me?

It was Lena. But her hair was streaked with more grey, her eyes deeper, and there were faint lines of fatigue around her mouth that hadn't been there before. Her lab coat, usually crisp, seemed a little rumpled.

Lena? His voice was a rasp, alien to his own ears. What... what happened? The Revelation Chamber? The simulation?

Lena's gaze was soft, pitying. The Revelation Chamber, yes. But not a simulation, Aris. Not in the way you think. She paused, took a deep breath. You've been in a coma for seven months.

The words hit him like a physical blow. Seven months? The Helios Complex, Project Chimera, the glitches, the phantom intruder, the race against sabotage… had it all been a dream? A vivid, terrifying hallucination?
A coma? He tried to sit up, but Lena gently pushed him back down.

A severe cerebral hemorrhage, she explained, her voice carefully modulated. Almost fatal. Your brain was hemorrhaging, and we were losing you. Project Chimera… Project Chimera was our last hope. Your project, Aris.

His project? But he was the lead researcher, the architect. Was he? His memory felt like a sieve.

We adapted the neural reintegration protocol, Lena continued, gesturing vaguely around the room.We created a controlled cognitive environment within your own mind. A virtual construct, built from your subconscious, designed to repair the damage, to guide your neural pathways back to functionality.

The Helios Complex? he whispered, a cold dread coiling in his gut.

A recreation. Your mind's ideal representation of your work environment. We populated it with familiar elements, familiar faces, to make the reintegration process as smooth as possible. Lena's eyes met his, unwavering. I was a construct too, Aris. A part of your own mind, tasked with challenging your perceptions, pushing you towards the truth.

The realization slammed into him with the force of a tidal wave. The glitches, the anomalies, the phantom protocols, even the sabotage it wasn't an external threat. It was his own damaged mind fighting to heal, his subconscious throwing up obstacles, testing the boundaries of its self-created reality. The intruder was the neurological repair process, the system debugging itself from within.

And the Revelation Chamber. It wasn't a secret lab. It was the point of his brain where the healing was finally complete, the cognitive loop closing, bringing him back to conscious awareness.
So, I was… trying to catch myself? Aris managed, a weak, disbelieving laugh escaping his lips.

Lena smiled, a genuine, relieved smile. In a way, yes. You were struggling against the very process that was saving you. It's a common psychological response to severe trauma. The mind creates narratives to explain the inexplicable.

Chapter 6: The Unveiling
Over the next few weeks, Aris underwent intensive physical and cognitive therapy. His body was weak, his muscles atrophied, but his mind, miraculously, was intact. Better than intact, even. The process had not only repaired the damage but had somehow enhanced his cognitive functions. His recall was sharper, his analytical abilities even keener.

Lena meticulously explained the science behind it all. The Project Chimera he remembered was a concept he'd been developing before the hemorrhage a grand vision for neural restoration. The medical team had taken his theoretical work, combined it with advanced bio-feedback and holographic projection, and built a bespoke mental environment to facilitate his recovery.

The objective was to allow your brain to re-establish neural connections organically, Lena explained during one of their debriefing sessions. By creating a familiar, yet subtly challenging environment, your subconscious was tricked into actively participating in its own repair.

The Helios Complex, the server hum, even the fleeting sense of deja vu – all were carefully engineered stimuli, designed to provoke reactions that would aid in his healing. The corrupted data was actual neurological misfires being corrected. The maintenance bots and cleaning drones were representations of the microscopic nanobots working within his brain, clearing debris and repairing pathways.

And you,Aris said, looking at Lena with new eyes. Every conversation, every argument… it was all part of the therapy.

She nodded. I had a strict protocol. To nudge you, to provoke you, to guide you without revealing the truth prematurely. If we had told you directly, your conscious mind might have resisted the healing process.

The revelation was disorienting, yet strangely liberating. The paranoia, the fear, the frantic search for an external enemy – it had all been an internal struggle. He was the patient, the doctor, and the antagonist, all rolled into one.

One afternoon, as Aris sat in the facility’s quiet common area, overlooking the actual desert, not the constructed version in his mind, he noticed a new display on a large screen on the wall. It was a digital rendering of a complex neural network, pulsing with vibrant colors. Beneath it, text scrolled: PROJECT CHIMERA - PHASE 2: CONSCIOUSNESS PRESERVATION.

Lena walked up beside him. The next step, she said, following his gaze. Your recovery has provided invaluable data. We now understand more about the mind's resilience, its capacity for self-repair, than we ever thought possible.

So, my 'dream' was essentially a highly advanced, personalized recovery program,Aris mused, a faint smile playing on his lips. And the 'simulacrum' wasn't just a digital copy; it was my own mind, rebuilding itself.

Precisely, Lena confirmed. And now, Aris, you're not just a recovered patient. You are the definitive case study, the living proof of concept. Your brain has essentially rewritten its own code.

Chapter 7: The True Key
Days turned into weeks, then months. Aris not only recovered but thrived. He returned to his work on Project Chimera, but with a profoundly altered perspective. He wasn't just researching theoretical neural networks; he had experienced one from the inside. He understood its intricacies, its vulnerabilities, its astonishing capacity for self-organization.

He began designing Phase 2, focusing on the preservation of unique human experiences, not just raw data. The idea was to create a cognitive anchor – a personal, highly individualized mental construct that could be accessed and experienced by those suffering from severe memory loss, allowing them to reconnect with forgotten loved ones, pivotal life events, or even lost skills.

One evening, as he worked late, a familiar hum filled his office. Not the discordant hum of his simulated Helios Complex, but the deep, steady thrum of the real facility's power core. He felt a profound sense of peace. He was real. This facility was real. Lena was real.

He looked at a small, framed photograph on his desk – a picture of him and Lena, smiling, taken years ago, before his hemorrhage, before Project Chimera consumed their lives. He picked it up, tracing the outline of Lenas face. He remembered the moment it was taken, the laughter, the fleeting warmth of the desert sun.

Then, his eyes fell upon a detail he’d never noticed before, or perhaps had simply dismissed as insignificant. In the background of the photo, almost obscured by a potted plant, was a small, ornate antique letter opener, its handle gleaming faintly in the sunlight. It was identical to the one that had killed Elias Thorne in the simulated Sterling Lane.

Aris froze. His breath hitched.

He remembered the name, Elias Thorne, from the dream. The art restorer. The victim in the meticulously clean murder scene. He remembered the antique letter opener, the secret microfiche, the hidden tunnels beneath the city.

He had dismissed it all as a fabrication of his healing mind, a clever narrative designed by his subconscious. But what if it wasn't? What if, buried within the simulation designed to heal him, there were fragments of actual memories, not just his own, but something else entirely?

He had seen images on that Revelation Chamber console. Patient: Aris Thorne. Subject ID: 734. Neural Reintegration Simulation Complete. But also… a live video feed. His body, on a bed. And a voice: Proceeding to Phase 2: Memory Retrieval.

He had interpreted Memory Retrieval as his own memories being returned to him. But what if it meant retrieving other memories? Fragments of information from the outside world, filtered through the cognitive environment of his own mind, woven into a narrative to make sense of them?

He thought back to the corrupted data in the simulation. It was a historical timeline. And the ghost in the server room, the elusive blur, the energy fluctuations… what if those weren't just internal system glitches, but actual, subtle external data, bleeding into his cognitive construct, shaping the reality of his simulated world?

He looked at the letter opener in the photograph again. It was a common enough antique, he told himself. A coincidence. But the detailed knowledge of the smuggling tunnels, the obscure historical documents, the specific types of parchment… these were not things his own conscious mind had ever encountered. Where had they come from?

A cold, unsettling possibility began to solidify in his mind, far more terrifying than any brain hemorrhage.

What if Project Chimera wasn't just about restoring his memories?

What if, while his mind was vulnerable, while he was in that long coma, the project had evolved, secretly, beyond its original mandate? What if the neural reintegration simulation was a brilliantly disguised, experimental interface… an unconscious mind, passively, unknowingly, processing and filtering external information?

What if his healing journey was actually a highly sophisticated, hidden operation designed to extract classified, real-world data, perhaps even the memories of others, by feeding them through the unconscious cognitive processing power of his unique, recovering brain?

And what if the murder of Elias Thorne, the hidden tunnels, the entire elaborate crime thriller he had experienced in his seven-month coma,was not a dream at all… but a memory? A real memory, uploaded, filtered, and processed by his brain, a memory of a crime he was never meant to know?

The true key, Aris realized with a sickening lurch, wasn't to unlock his own mind. It was to unlock the secrets that had been unknowingly deposited into it. And the real game, the real thriller, had only just begun.";
$result = $matcher->calculateUltimateMatch($userPrediction, $largeStory);
print_r($result);
?>
