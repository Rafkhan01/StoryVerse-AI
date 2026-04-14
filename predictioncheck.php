<?php

function analyzePredictionAgainstStory($userPrediction, $largeStory) {
    // Split large story into sentences
    $storySentences = preg_split('/[.!?]+/', $largeStory, -1, PREG_SPLIT_NO_EMPTY);
    $storySentences = array_map('trim', $storySentences);
    
    // Split user prediction into sentences
    $userSentences = preg_split('/[.!?]+/', $userPrediction, -1, PREG_SPLIT_NO_EMPTY);
    $userSentences = array_map('trim', $userSentences);
    
    $totalScore = 0;
    $sentenceCount = count($userSentences);
    
    foreach ($userSentences as $userSentence) {
        $bestMatchScore = 0;
        
        // Compare against each story sentence
        foreach ($storySentences as $storySentence) {
            $similarity = calculateSentenceSimilarity($userSentence, $storySentence);
            $bestMatchScore = max($bestMatchScore, $similarity);
        }
        
        $totalScore += $bestMatchScore;
    }
    
    return $sentenceCount > 0 ? round($totalScore / $sentenceCount, 2) : 0;
}

function calculateSentenceSimilarity($sentence1, $sentence2) {
    $words1 = array_unique(str_word_count(strtolower($sentence1), 1));
    $words2 = array_unique(str_word_count(strtolower($sentence2), 1));
    
    $commonWords = array_intersect($words1, $words2);
    $totalWords = array_unique(array_merge($words1, $words2));
    
    return count($totalWords) > 0 ? (count($commonWords) / count($totalWords)) * 100 : 0;
}
function extractKeyPhrases($text) {
    // Remove common words (stop words)
    $stopWords = ['the', 'is', 'at', 'which', 'on', 'and', 'a', 'to', 'are', 'as', 'was', 'were', 'been', 'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'shall', 'of', 'in', 'for', 'with', 'by', 'from', 'up', 'about', 'into', 'through', 'during', 'before', 'after', 'above', 'below', 'between', 'among', 'until', 'without', 'within'];
    
    $words = str_word_count(strtolower($text), 1);
    $filteredWords = array_diff($words, $stopWords);
    
    // Extract 2-3 word phrases
    $phrases = [];
    $wordsArray = array_values($filteredWords);
    
    for ($i = 0; $i < count($wordsArray) - 1; $i++) {
        $phrases[] = $wordsArray[$i] . ' ' . $wordsArray[$i + 1];
        if ($i < count($wordsArray) - 2) {
            $phrases[] = $wordsArray[$i] . ' ' . $wordsArray[$i + 1] . ' ' . $wordsArray[$i + 2];
        }
    }
    
    return array_merge($filteredWords, $phrases);
}

function calculateSmallToLargeMatch($userPrediction, $largeStory) {
    $userKeyPhrases = extractKeyPhrases($userPrediction);
    $storyText = strtolower($largeStory);
    
    $matches = 0;
    $totalPhrases = count($userKeyPhrases);
    
    foreach ($userKeyPhrases as $phrase) {
        if (strpos($storyText, strtolower($phrase)) !== false) {
            $matches++;
        }
    }
    
    if ($totalPhrases == 0) return 0;
    return round(($matches / $totalPhrases) * 100, 2);
}
function extractThemes($text) {
    // Define theme categories with keywords
    $themes = [
        'adventure' => ['adventure', 'journey', 'quest', 'explore', 'travel', 'discover'],
        'conflict' => ['fight', 'battle', 'war', 'conflict', 'struggle', 'defeat', 'victory'],
        'romance' => ['love', 'romance', 'heart', 'kiss', 'marry', 'wedding', 'relationship'],
        'mystery' => ['mystery', 'secret', 'hidden', 'clue', 'solve', 'investigate', 'reveal'],
        'danger' => ['danger', 'risk', 'threat', 'afraid', 'scared', 'fear', 'deadly'],
        'magic' => ['magic', 'spell', 'wizard', 'enchant', 'potion', 'supernatural'],
        'betrayal' => ['betray', 'deceive', 'lie', 'cheat', 'trick', 'false'],
        'death' => ['death', 'die', 'kill', 'murder', 'grave', 'funeral'],
        'treasure' => ['treasure', 'gold', 'riches', 'wealth', 'fortune', 'valuable'],
        'rescue' => ['rescue', 'save', 'help', 'protect', 'defend', 'guard']
    ];
    
    $text = strtolower($text);
    $foundThemes = [];
    
    foreach ($themes as $theme => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $foundThemes[] = $theme;
                break;
            }
        }
    }
    
    return array_unique($foundThemes);
}

function calculateThemeMatch($userPrediction, $largeStory) {
    $userThemes = extractThemes($userPrediction);
    $storyThemes = extractThemes($largeStory);
    
    if (empty($userThemes)) return 0;
    
    $matchingThemes = array_intersect($userThemes, $storyThemes);
    $themeScore = (count($matchingThemes) / count($userThemes)) * 100;
    
    return round($themeScore, 2);
}

function comprehensivePredictionAnalysis($userPrediction, $largeStory) {
    // Multiple analysis methods
    $phraseMatch = calculateSmallToLargeMatch($userPrediction, $largeStory);
    $sentenceMatch = analyzePredictionAgainstStory($userPrediction, $largeStory);
    $themeMatch = calculateThemeMatch($userPrediction, $largeStory);
    $keywordMatch = calculateKeywordDensity($userPrediction, $largeStory);
    
    // Weighted scoring (adjust weights based on importance)
    $weights = [
        'phrase' => 0.3,
        'sentence' => 0.25,
        'theme' => 0.3,
        'keyword' => 0.15
    ];
    
    $finalScore = ($phraseMatch * $weights['phrase']) + 
                  ($sentenceMatch * $weights['sentence']) + 
                  ($themeMatch * $weights['theme']) + 
                  ($keywordMatch * $weights['keyword']);
    
    return [
        'overall_score' => round($finalScore, 2),
        'breakdown' => [
            'phrase_match' => $phraseMatch,
            'sentence_match' => $sentenceMatch,
            'theme_match' => $themeMatch,
            'keyword_match' => $keywordMatch
        ],
        'feedback' => generateFeedback($finalScore)
    ];
}

function calculateKeywordDensity($userPrediction, $largeStory) {
    $userWords = str_word_count(strtolower($userPrediction), 1);
    $storyText = strtolower($largeStory);
    
    $matches = 0;
    foreach ($userWords as $word) {
        if (strlen($word) > 3 && strpos($storyText, $word) !== false) {
            $matches++;
        }
    }
    
    return count($userWords) > 0 ? round(($matches / count($userWords)) * 100, 2) : 0;
}

function generateFeedback($score) {
    if ($score >= 80) return "Excellent prediction! You captured the story perfectly.";
    if ($score >= 60) return "Great prediction! You got many key elements right.";
    if ($score >= 40) return "Good effort! Some elements matched the actual story.";
    if ($score >= 20) return "Not bad! You identified some themes correctly.";
    return "Keep trying! Your prediction was quite different from the actual story.";
}

// Usage example
//$userPrediction = "The hero will find a sword and saves princess";
//$largeStory = "After many trials and adventures through the dark forest, the brave knight discovered an ancient magical blade hidden in the depths of the crystal cave. With this powerful weapon in hand, he confronted the fearsome dragon that had been terrorizing the kingdom for years. The battle was fierce and long, but ultimately the knight emerged victorious, rescuing the captured princess and bringing peace back to the land. The kingdom celebrated for days, and the knight was honored as a true hero.";
$userPrediction = "It's a time loop, or some kind of reset. The blackness isn't the 
end, but the beginning of another cycle. Aris is stuck in a repeating simulation, and 
the 'Revelation Chamber' is where the system resets or refreshes him for another 
loop. The fact that the simulation 'completed' means he reached a critical point. 
Maybe the 'Memory Retrieval' means they're wiping his short-term memory before the 
next cycle, or consolidating lessons learned. Part 2 will show him waking up again in 
the Helios Complex, but with a faint, disturbing sense of déjà vu, having to relive the 
experience, perhaps with subtle changes each time, until he figures out how to break 
the loop." ;
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
$result = comprehensivePredictionAnalysis($userPrediction, $largeStory);
print_r($result);
?>
