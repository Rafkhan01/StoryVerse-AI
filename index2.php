<?php
session_start();
require_once 'db_connect.php'; // Your database connection

// Check if the user is logged in. If not, redirect to the signin page.
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

// User data from session
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];
$email = $_SESSION['email'];
$total_score = $_SESSION['total_score'];

// --- Featured Story Logic ---
$featured_story = null;

try {
    // SQL query to find the single active story with the nearest prediction deadline
    $sql = "
        SELECT
            s.*,
            sp.part_number,
            sp.content AS part_content,
            sp.prediction_deadline,
            sp.upload_date
        FROM
            stories s
        JOIN
            story_parts sp ON s.story_id = sp.story_id
        WHERE
            sp.upload_date <= NOW() AND sp.prediction_deadline >= NOW()
        ORDER BY
            sp.prediction_deadline ASC
        LIMIT 1;
    ";
    
    $stmt = $pdo->query($sql);
    $featured_story = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching featured story: " . $e->getMessage());
    $featured_story = null; // Ensure no data is displayed on error
}

// Helper function to calculate time left for the countdown timer
function getTimeLeft($deadline) {
    $now = new DateTime();
    $deadline_dt = new DateTime($deadline);
    $interval = $now->diff($deadline_dt);

    $parts = [];
    if ($interval->d > 0) {
        $parts[] = $interval->d . ' day' . ($interval->d > 1 ? 's' : '');
    }
    if ($interval->h > 0) {
        $parts[] = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '');
    }
    if ($interval->i > 0) {
        $parts[] = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
    }
    if (empty($parts)) {
        return 'Less than 1 minute';
    }
    return implode(', ', $parts) . ' left';
}

// Helper function to check for the story's status
function getStoryStatus($upload_date, $prediction_deadline) {
    $current_time = time();
    $upload_timestamp = strtotime($upload_date);
    $deadline_timestamp = strtotime($prediction_deadline);

    if ($current_time < $upload_timestamp) {
        return 'coming_soon';
    } elseif ($current_time >= $upload_timestamp && $current_time <= $deadline_timestamp) {
        return 'active';
    } else {
        return 'completed';
    }
}

// Get story status
$story_status = $featured_story ? getStoryStatus($featured_story['upload_date'], $featured_story['prediction_deadline']) : 'none';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Read... Predict! Win! - StoryPulse</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #48ff48, #00cc00);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            color: #333;
            overflow-x: hidden;
            min-height: 100vh;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Navigation */
        nav {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 20px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .navtitle {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
        }

        .navcta {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .navlinks {
            border: none;
            background: transparent;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        nav a {
            text-decoration: none;
            color: #2c3e50;
        }

        .navlinks:hover {
            background-color: rgba(102, 126, 234, 0.1);
        }

        .cta {
            padding: 14px 32px;
            background: var(--primary-gradient);
            border-radius: 8px;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .cta a {
            color: white !important;
            text-decoration: none;
        }

        /* Hero Section */
        .hero-section {
            padding: 120px 25px 60px;
            text-align: center;
            color: #fff;
            position: relative;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.2);
            pointer-events: none;
        }

        .hero-section h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.5em, 5vw, 3.5em);
            margin-bottom: 20px;
            font-weight: 600;
            text-shadow: 2px 2px 20px rgba(0,0,0,0.3);
            position: relative;
            z-index: 2;
        }

        .hero-section span {
            font-size: clamp(1em, 2vw, 1.2em);
            line-height: 1.6;
            display: block;
            margin-bottom: 30px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            opacity: 0.95;
            position: relative;
            z-index: 2;
        }

        /* Welcome Section */
        .welcome-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            margin: 40px 5%;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .welcome-section h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2.5em;
            color: #2c3e50;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .welcome-section span {
            font-size: 1.2em;
            color: #667eea;
            font-weight: 600;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-cta {
            margin-top: 30px;
        }

        /* Section Headers */
        h2 {
            text-align: center;
            margin: 60px 0 40px;
            color: #fff;
            font-family: 'Playfair Display', serif;
            font-size: clamp(2em, 4vw, 2.5em);
            font-weight: 600;
            text-shadow: 2px 2px 10px rgba(0,0,0,0.3);
            position: relative;
            display: block;
            visibility: visible;
        }

        h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--primary-gradient);
            border-radius: 2px;
        }

        /* Featured Story */
        .featured-story {
            margin: 40px 5%;
            padding: 40px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.1);
        }

        .featured-story-title {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .story-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .story-cover {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            object-fit: cover;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .story-text h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.8em;
            color: #2c3e50;
            margin: 0 0 8px 0;
            font-weight: 600;
        }

        .story-author {
            font-size: 14px;
            color: #7f8c8d;
            font-weight: 500;
        }

        .details {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .status {
            background: var(--success-gradient);
            height: 40px;
            border-radius: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 8px 16px;
            color: #fff;
            font-weight: 600;
            font-size: 14px;
        }

        .status-ping {
            background: #fff;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.2); }
        }

        .count-down {
            font-size: 14px;
            color: #7f8c8d;
            font-weight: 500;
            background: rgba(102, 126, 234, 0.1);
            padding: 10px 16px;
            border-radius: 20px;
        }

        .featured-story p {
            line-height: 1.7;
            color: #555;
            margin: 25px 0;
            font-size: 16px;
        }

        .part-container {
            display: flex;
            width: 100%;
            height: 8px;
            background: rgba(0,0,0,0.08);
            border-radius: 4px;
            overflow: hidden;
            margin: 25px 0;
        }

        .part-segment {
            flex-grow: 1;
            height: 100%;
            background: rgba(0,0,0,0.08);
            margin-right: 1px;
            transition: all 0.6s ease;
        }

        .part-segment:last-child {
            margin-right: 0;
        }

        .part-segment.filled {
            background: var(--primary-gradient);
        }

        hr {
            border: none;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            margin: 30px 0;
        }

        .no-story {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
            font-size: 18px;
        }

        
        /* How It Works Section */
        .short-working {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            padding: 0 10%;
            margin-top: 40px;
            margin-bottom: 50px;
        }
        
        .step-box {
            background-color: white; 
            border: none;
            border-radius: 15px;
            padding: 30px 20px;
            text-align: center;            
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .step-box:hover {
            transform: translateY(-8px); 
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .step-box .step-number {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #6c63ff, #463fff);
            color: white;
            border-radius: 50%; 
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 28px;
            font-weight: 700;
            margin: 0 auto 25px; 
            box-shadow: 0 5px 15px rgba(108, 99, 255, 0.4);
        }

        .step-box h4 {
            font-size: 1.3em;
            font-weight: 600;
            color: #333;
            margin-top: 0;
        }

        hr {
            border: none;
            border-top: 1px solid #eee;
            margin: 25px 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-section, .welcome-section {
                padding: 100px 20px 40px;
            }
            
            .featured-story {
                margin: 20px 3%;
                padding: 25px;
            }
            
            .steps-grid {
                padding: 0 3%;
                grid-template-columns: 1fr;
            }
            
            .featured-story-title {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .story-info {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <nav id="navbar">
        <div class="navtitle">StoryPulse</div>
        <div class="navcta">
            <button class="navlinks"><a href="index.php">Home</a></button>
            <button class="navlinks"><a href="stories.php">Stories</a></button>
            <button class="navlinks"><a href="#">Leaderboard</a></button>
            <button class="navlinks"><a href="#">How it works</a></button>
            <button class="cta"><a href="logout.php">Logout</a></button>
        </div>
    </nav>

    <div class="hero-section" id="hero">
        <h1>Predict the story. Win Rewards.</h1>
        <span>
            Immerse yourself in captivating stories and test your intuition<br> by predicting what happens next. Match the author's continuation and win...!
        </span>
    </div>  

    <div class="welcome-section" id="welcomeSection">
        <h1>Welcome, <?php echo htmlspecialchars($first_name); ?>!</h1>
        <span>Your total score: <?php echo htmlspecialchars($total_score); ?> pts</span>
        <div class="hero-cta">
            <button class="cta"><a href="stories.php">Start Reading!</a></button>
        </div>
    </div>
    
    <h2 id="featuredTitle">Featured Story</h2>
    <div class="featured-story" id="featuredStory">
        <?php if ($featured_story): ?>
            <div class="featured-story-title">
                <div class="story-info">
                    <img src="<?php echo htmlspecialchars($featured_story['cover_image_url']); ?>" 
                         alt="<?php echo htmlspecialchars($featured_story['title']); ?> Cover" 
                         class="story-cover">
                    <div class="story-text">
                        <h3><?php echo htmlspecialchars($featured_story['title']); ?> - Part <?php echo htmlspecialchars($featured_story['part_number']); ?></h3>
                        <div class="story-author">By: <?php echo htmlspecialchars($featured_story['created_by']); ?></div>
                    </div>
                </div>
                <div class="details">
                    <div class="status">
                        <div class="status-ping"></div>
                        Active
                    </div>
                    <div class="count-down">
                        <?php echo getTimeLeft($featured_story['prediction_deadline']); ?>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <p><?php echo nl2br(htmlspecialchars($featured_story['description'])); ?></p>
            
            <div class="part-container">
                <?php
                    // Display progress bar based on total parts and current part number
                    for ($i = 1; $i <= $featured_story['total_parts']; $i++) {
                        $class = ($i <= $featured_story['part_number']) ? 'filled' : '';
                        echo '<div class="part-segment ' . $class . '"></div>';
                    }
                ?>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="story_detail.php?story_id=<?php echo htmlspecialchars($featured_story['story_id']); ?>&part_number=<?php echo htmlspecialchars($featured_story['part_number']); ?>" class="cta">Read Now & Predict!</a>
            </div>
        <?php else: ?>
            <div class="no-story">
                <p>No active stories at the moment. Check back soon!</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- HOW IT WORKS - COMPLETELY REBUILT WITH SIMPLE STRUCTURE -->
   <h2>How it works</h2>
    <div class="short-working">
        <div class="step-box">
            <div class="step-number">1</div>
            <h4>Read the Story</h4>
                         
        </div>
        <div class="step-box">
            <div class="step-number">2</div>
            <h4>Make Your Prediction</h4>
                         
        </div>    
        <div class="step-box">
            <div class="step-number">3</div>
            <h4>Match the Author</h4>
                         
        </div>
        <div class="step-box">
            <div class="step-number">4</div>
            <h4>Win Rewards!</h4>
                         
        </div>
    </div>

    <script>
        // Register ScrollTrigger plugin
        gsap.registerPlugin(ScrollTrigger);

        document.addEventListener('DOMContentLoaded', function() {
            console.log('Page loaded successfully');
            
            // Hero animations
            gsap.from("#hero h1", {
                duration: 1,
                y: 60,
                opacity: 0,
                ease: "power3.out"
            });

            gsap.from("#hero span", {
                duration: 0.8,
                y: 40,
                opacity: 0,
                delay: 0.2,
                ease: "power3.out"
            });

            // Welcome section
            gsap.from("#welcomeSection", {
                scrollTrigger: {
                    trigger: "#welcomeSection",
                    start: "top 85%"
                },
                duration: 0.8,
                y: 50,
                opacity: 0,
                ease: "power3.out"
            });

            // Featured story
            gsap.from("#featuredStory", {
                scrollTrigger: {
                    trigger: "#featuredStory",
                    start: "top 85%"
                },
                duration: 1,
                y: 60,
                opacity: 0,
                ease: "power3.out"
            });

            // How it works - Simple animation
            gsap.from(".step-card", {
                scrollTrigger: {
                    trigger: ".steps-grid",
                    start: "top 85%"
                },
                duration: 0.6,
                y: 50,
                opacity: 0,
                stagger: 0.15,
                ease: "power3.out"
            });

            // Button hover effects
            document.querySelectorAll('.cta').forEach(button => {
                button.addEventListener('mouseenter', function() {
                    gsap.to(this, {
                        duration: 0.3,
                        scale: 1.05,
                        ease: "power2.out"
                    });
                });

                button.addEventListener('mouseleave', function() {
                    gsap.to(this, {
                        duration: 0.3,
                        scale: 1,
                        ease: "power2.out"
                    });
                });
            });

            // Step card hover effects
            document.querySelectorAll('.step-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    gsap.to(this.querySelector('.step-number'), {
                        duration: 0.4,
                        rotation: 360,
                        ease: "power2.out"
                    });
                });
            });
        });
    </script>
</body>
</html>