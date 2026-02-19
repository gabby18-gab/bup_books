<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BUP CAMPUS - Buy & Sell Used Books</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bup-blue: #0A3143;
            --bup-blue-light: #1C4E6C;
            --bup-orange: #FF914D;
            --bup-yellow: #FFC107;
            --bup-white: #ffffff;
            --bup-gray: #6c757d;
            --bup-light: #f8f9fa;
            --shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--bup-blue);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Navigation */
        .navbar {
            background: white;
            box-shadow: 0 2px 20px rgba(10,49,67,0.08);
            padding: 15px 0;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
        }

        .logo-wrapper {
            display: flex;
            flex-direction: column;
            background: linear-gradient(145deg, var(--bup-blue), var(--bup-blue-light));
            padding: 8px 20px;
            border-radius: 12px;
            position: relative;
        }

        .logo-wrapper::before {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            right: -3px;
            bottom: -3px;
            background: linear-gradient(145deg, var(--bup-orange), var(--bup-yellow));
            border-radius: 14px;
            z-index: -1;
            opacity: 0.6;
        }

        .logo-text {
            font-size: 24px;
            font-weight: 900;
            background: linear-gradient(135deg, var(--bup-orange), var(--bup-yellow));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 2px;
            line-height: 1;
        }

        .logo-sub {
            font-size: 12px;
            font-weight: 700;
            color: white;
            letter-spacing: 1.5px;
        }

        .nav-link {
            color: var(--bup-blue) !important;
            font-weight: 600;
            margin: 0 12px;
            position: relative;
            transition: color 0.3s;
        }

        .nav-link:hover {
            color: var(--bup-orange) !important;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 50%;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--bup-orange), var(--bup-yellow));
            transition: all 0.3s;
            transform: translateX(-50%);
        }

        .nav-link:hover::after {
            width: 80%;
        }

        .btn-signin {
            background: transparent;
            border: 2px solid var(--bup-orange);
            color: var(--bup-blue) !important;
            border-radius: 30px;
            padding: 8px 24px !important;
            font-weight: 700;
            transition: all 0.3s;
        }

        .btn-signin:hover {
            background: var(--bup-orange);
            color: white !important;
        }

        .btn-signup {
            background: linear-gradient(145deg, var(--bup-orange), var(--bup-yellow));
            color: var(--bup-blue) !important;
            border-radius: 30px;
            padding: 8px 24px !important;
            font-weight: 700;
            box-shadow: 0 6px 0 #C7511E;
            transition: all 0.2s;
            margin-left: 10px;
        }

        .btn-signup:hover {
            transform: translateY(2px);
            box-shadow: 0 4px 0 #C7511E;
            color: var(--bup-blue) !important;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(145deg, var(--bup-blue), var(--bup-blue-light));
            padding: 100px 0;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,145,77,0.2) 0%, transparent 70%);
            border-radius: 50%;
        }

        .hero h1 {
            font-size: 52px;
            font-weight: 900;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .hero h1 span {
            background: linear-gradient(135deg, var(--bup-orange), var(--bup-yellow));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero p {
            font-size: 18px;
            margin-bottom: 30px;
            opacity: 0.95;
        }

        .btn-hero {
            background: linear-gradient(145deg, var(--bup-orange), var(--bup-yellow));
            color: var(--bup-blue);
            font-weight: 800;
            padding: 14px 40px;
            border-radius: 50px;
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: 2px;
            box-shadow: 0 8px 0 #C7511E;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
            display: inline-block;
        }

        .btn-hero:hover {
            transform: translateY(4px);
            box-shadow: 0 4px 0 #C7511E;
            color: var(--bup-blue);
        }

        /* Book Cards Section */
        .section-title {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 20px;
            color: var(--bup-blue);
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, var(--bup-orange), var(--bup-yellow));
            border-radius: 2px;
        }

        .book-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s;
            height: 100%;
            border: 1px solid rgba(225,233,240,0.5);
        }

        .book-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(255,145,77,0.2);
            border-color: var(--bup-orange);
        }

        .book-image {
            height: 200px;
            background: linear-gradient(145deg, #f0f4f8, #e8f0f5);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: var(--bup-blue);
            opacity: 0.5;
        }

        .book-content {
            padding: 25px;
        }

        .book-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--bup-blue);
        }

        .book-price {
            font-size: 24px;
            font-weight: 800;
            color: var(--bup-orange);
            margin-bottom: 15px;
        }

        .btn-book {
            background: linear-gradient(145deg, var(--bup-blue), var(--bup-blue-light));
            color: white;
            border: none;
            border-radius: 30px;
            padding: 10px 20px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-book:hover {
            background: var(--bup-orange);
            color: white;
        }

        /* About Section */
        .about-section {
            background: var(--bup-light);
            padding: 80px 0;
        }

        .about-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--shadow);
            height: 100%;
        }

        .about-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(145deg, rgba(255,145,77,0.1), rgba(255,193,7,0.1));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: var(--bup-orange);
            margin-bottom: 25px;
        }

        .about-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--bup-blue);
        }

        .btn-about {
            color: var(--bup-orange);
            text-decoration: none;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 15px;
        }

        .btn-about:hover {
            color: var(--bup-blue);
        }

        /* Chat Section */
        .chat-section {
            background: white;
            padding: 60px 0;
        }

        .chat-card {
            background: linear-gradient(145deg, var(--bup-blue), var(--bup-blue-light));
            border-radius: 30px;
            padding: 50px;
            color: white;
            text-align: center;
        }

        .chat-icon {
            font-size: 60px;
            color: var(--bup-yellow);
            margin-bottom: 20px;
        }

        .chat-card h2 {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 15px;
        }

        .chat-card p {
            font-size: 18px;
            margin-bottom: 30px;
            opacity: 0.9;
        }

        .btn-chat {
            background: var(--bup-orange);
            color: white;
            padding: 14px 40px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            display: inline-block;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .btn-chat:hover {
            background: transparent;
            border-color: white;
            color: white;
        }

        /* Blog Section */
        .blog-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow);
            height: 100%;
            border: 1px solid rgba(225,233,240,0.5);
            transition: all 0.3s;
        }

        .blog-card:hover {
            transform: translateY(-5px);
            border-color: var(--bup-orange);
        }

        .blog-date {
            color: var(--bup-orange);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .blog-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--bup-blue);
        }

        /* Footer */
        .footer {
            background: var(--bup-blue);
            color: white;
            padding: 60px 0 30px;
        }

        .footer-logo {
            display: flex;
            flex-direction: column;
            margin-bottom: 20px;
        }

        .footer-logo .logo-text {
            font-size: 28px;
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: var(--bup-orange);
            padding-left: 5px;
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .social-link {
            width: 44px;
            height: 44px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .social-link:hover {
            background: linear-gradient(145deg, var(--bup-orange), var(--bup-yellow));
            color: var(--bup-blue);
            transform: translateY(-5px);
        }

        .footer-bottom {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            color: rgba(255,255,255,0.6);
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 36px;
            }
            
            .section-title {
                font-size: 28px;
            }
            
            .chat-card {
                padding: 30px;
            }
            
            .chat-card h2 {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <div class="logo-wrapper">
                    <span class="logo-text">BUP</span>
                    <span class="logo-sub">Campus</span>
                </div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#browse">Browse</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#categories">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#how-to-buy">How to Buy</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#works">Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="user/index.php" class="btn btn-signin">
                            <i class="bi bi-person-circle me-2"></i>Dashboard
                        </a>
                        <a href="logout.php" class="btn btn-signup">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-signin">Sign In</a>
                        <a href="register.php" class="btn btn-signup">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1>Book Resale <span>Platform</span><br>at BUP Campus</h1>
                    <p>Join the largest second-hand book marketplace for BUP students. Save money, earn money, and reduce waste.</p>
                    <a href="books.php" class="btn-hero">
                        <i class="bi bi-book me-2"></i>BUY BOOKS
                    </a>
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <img src="assets/img/hero-books.png" alt="BUP Books" class="img-fluid" onerror="this.src='https://placehold.co/600x400/0A3143/FF914D?text=BUP+BOOKS'">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Books Section -->
    <section id="browse" class="py-5" style="padding: 80px 0;">
        <div class="container">
            <h2 class="section-title">Buy Books</h2>
            <p class="text-secondary mb-5 fs-5">Ready to sell your old books? BUP Campus offers a wide range of used books, including textbooks and reference materials.</p>
            
            <div class="row g-4">
                <?php
                $featured_books = [
                    ['title' => 'Introduction to Algorithms', 'price' => 45.00],
                    ['title' => 'The Great Gatsby', 'price' => 12.50],
                    ['title' => 'Physics for Scientists', 'price' => 38.00],
                    ['title' => 'Calculus: Early Transcendentals', 'price' => 42.00],
                ];
                
                foreach ($featured_books as $book):
                ?>
                <div class="col-lg-3 col-md-6">
                    <div class="book-card">
                        <div class="book-image">
                            <i class="bi bi-journal-bookmark-fill"></i>
                        </div>
                        <div class="book-content">
                            <h3 class="book-title"><?php echo $book['title']; ?></h3>
                            <div class="book-price">$<?php echo number_format($book['price'], 2); ?></div>
                            <a href="book-details.php" class="btn-book">
                                <i class="bi bi-cart me-2"></i>Buy Now
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-5">
                <a href="books.php" class="btn btn-signin px-5 py-3">Learn More</a>
            </div>
        </div>
    </section>

    <!-- About/Platform Section -->
    <section class="about-section">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="about-card">
                        <div class="about-icon">
                            <i class="bi bi-shop"></i>
                        </div>
                        <h3 class="about-title">Book Resale Platform</h3>
                        <p class="text-secondary mb-4">BUP Campus is the largest online marketplace for used books, selling over 1 million titles. Join thousands of students who save money and earn cash from their old textbooks.</p>
                        <a href="about.php" class="btn-about">
                            Learn More <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="about-card">
                        <div class="about-icon">
                            <i class="bi bi-building"></i>
                        </div>
                        <h3 class="about-title">BUP Campus</h3>
                        <p class="text-secondary mb-4">Book Resale Platform - Your trusted marketplace for affordable textbooks. Connect with fellow students, trade safely, and make the most of your campus experience.</p>
                        <a href="about.php" class="btn-about">
                            Learn More <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Chat Section -->
    <section class="chat-section">
        <div class="container">
            <div class="chat-card">
                <div class="chat-icon">
                    <i class="bi bi-chat-dots-fill"></i>
                </div>
                <h2>Chat</h2>
                <p>Connect directly with sellers and buyers. Ask questions, negotiate prices, and arrange meetups - all within our secure messaging system.</p>
                <a href="#" class="btn-chat">
                    <i class="bi bi-chat me-2"></i>Start Chatting
                </a>
            </div>
        </div>
    </section>

    <!-- Blog Section -->
    <section class="py-5" style="padding: 80px 0;">
        <div class="container">
            <h2 class="section-title">BUP Campus Blog</h2>
            <p class="text-secondary mb-5 fs-5">Latest news, tips, and stories from our community</p>
            
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="blog-card">
                        <div class="blog-date">March 15, 2026</div>
                        <h4 class="blog-title">How to Save 60% on Textbooks</h4>
                        <p class="text-secondary mb-4">Learn the best strategies for finding affordable used books this semester.</p>
                        <a href="blog.php" class="btn-about">
                            Read More <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="blog-card">
                        <div class="blog-date">March 10, 2026</div>
                        <h4 class="blog-title">Top 10 Books Every CSE Student Needs</h4>
                        <p class="text-secondary mb-4">Essential reading for computer science students at BUP.</p>
                        <a href="blog.php" class="btn-about">
                            Read More <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="blog-card">
                        <div class="blog-date">March 5, 2026</div>
                        <h4 class="blog-title">Seller Spotlight: Meet Our Top Sellers</h4>
                        <p class="text-secondary mb-4">How these students turned their old books into extra cash.</p>
                        <a href="blog.php" class="btn-about">
                            Read More <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="footer-logo">
                        <span class="logo-text">BUP</span>
                        <span class="logo-sub">Campus</span>
                    </div>
                    <p style="color: rgba(255,255,255,0.7); margin: 20px 0;">
                        Book Resale Platform - The largest online marketplace for used books at BUP, selling over 1 million titles.
                    </p>
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="social-link"><i class="bi bi-twitter-x"></i></a>
                        <a href="#" class="social-link"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="social-link"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4">
                    <h5 style="color: var(--bup-yellow); font-weight: 700; margin-bottom: 25px;">Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#browse">Browse</a></li>
                        <li><a href="#categories">Categories</a></li>
                        <li><a href="#how-to-buy">How to Buy</a></li>
                        <li><a href="#works">Works</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-4">
                    <h5 style="color: var(--bup-yellow); font-weight: 700; margin-bottom: 25px;">About Us</h5>
                    <p style="color: rgba(255,255,255,0.7);">BUP Campus is the largest online marketplace for used books, selling over 1 million titles to students across Bangladesh.</p>
                    <a href="about.php" style="color: var(--bup-orange); text-decoration: none; font-weight: 600;">
                        Learn More <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                
                <div class="col-lg-3 col-md-4">
                    <h5 style="color: var(--bup-yellow); font-weight: 700; margin-bottom: 25px;">Contact Us</h5>
                    <p style="color: rgba(255,255,255,0.7); margin-bottom: 10px;">
                        <i class="bi bi-envelope me-2" style="color: var(--bup-orange);"></i>
                        contact@bupcampus.edu.bd
                    </p>
                    <p style="color: rgba(255,255,255,0.7); margin-bottom: 10px;">
                        <i class="bi bi-telephone me-2" style="color: var(--bup-orange);"></i>
                        +880 1234 567890
                    </p>
                    <p style="color: rgba(255,255,255,0.7); margin-bottom: 10px;">
                        <i class="bi bi-geo-alt me-2" style="color: var(--bup-orange);"></i>
                        BUP Campus, Mirpur, Dhaka
                    </p>
                    <a href="contact.php" style="color: var(--bup-orange); text-decoration: none; font-weight: 600;">
                        Learn More <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p class="mb-0">Copyright © 2026 by BUP Campus. Designed for BUP Students.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Navbar active link highlighting
        const sections = document.querySelectorAll('section');
        const navLinks = document.querySelectorAll('.nav-link');

        window.addEventListener('scroll', () => {
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (pageYOffset >= sectionTop - 200) {
                    current = section.getAttribute('id');
                }
            });

            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === `#${current}`) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>