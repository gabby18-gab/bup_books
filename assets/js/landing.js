<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BUP BOOKS - Bangladesh's Premier Student Book Marketplace</title>
    
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        /* BUP BOOKS - PREMIUM LANDING PAGE
           Color Scheme: Blue (#0A3143, #1C4E6C) | Orange (#FF914D) | Yellow (#FFC107)
        */
        
        :root {
            --bup-blue: #0A3143;
            --bup-blue-light: #1C4E6C;
            --bup-orange: #FF914D;
            --bup-orange-dark: #E67A3A;
            --bup-yellow: #FFC107;
            --bup-white: #ffffff;
            --bup-offwhite: #F8FAFC;
            --bup-gray: #5A6C74;
            --bup-gray-light: #E1E9F0;
            --bup-gradient: linear-gradient(145deg, var(--bup-blue), var(--bup-blue-light));
            --bup-gradient-accent: linear-gradient(145deg, var(--bup-orange), var(--bup-yellow));
            --shadow-sm: 0 10px 30px rgba(10, 49, 67, 0.05);
            --shadow-md: 0 15px 40px rgba(255, 145, 77, 0.15);
            --shadow-lg: 0 25px 50px rgba(10, 49, 67, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bup-white);
            color: var(--bup-blue);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bup-gray-light);
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(var(--bup-orange), var(--bup-yellow));
            border-radius: 10px;
        }

        /* Navigation - Transparent & Elegant */
        .navbar {
            background: transparent;
            padding: 20px 0;
            transition: all 0.3s ease;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        .navbar.scrolled {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
            padding: 12px 0;
        }

        .navbar.scrolled .nav-link {
            color: var(--bup-blue) !important;
        }

        .navbar.scrolled .logo-wrapper {
            background: var(--bup-gradient);
        }

        .logo-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: transparent;
            padding: 8px 18px;
            border-radius: 16px;
            position: relative;
            transition: all 0.3s ease;
        }

        .logo-wrapper::before {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            right: -3px;
            bottom: -3px;
            background: var(--bup-gradient-accent);
            border-radius: 18px;
            z-index: -1;
            opacity: 0.6;
        }

        .logo-text {
            font-size: 32px;
            font-weight: 900;
            background: linear-gradient(135deg, var(--bup-orange) 0%, var(--bup-yellow) 80%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 3px;
            line-height: 1;
            text-transform: uppercase;
        }

        .logo-sub {
            font-size: 14px;
            font-weight: 700;
            color: var(--bup-white);
            letter-spacing: 2px;
            background: rgba(255,255,255,0.15);
            padding: 2px 12px;
            border-radius: 30px;
            margin-top: 2px;
        }

        .nav-link {
            color: var(--bup-white) !important;
            font-weight: 600;
            margin: 0 12px;
            position: relative;
            transition: color 0.3s ease;
            font-size: 15px;
            letter-spacing: 0.5px;
        }

        .nav-link:hover {
            color: var(--bup-yellow) !important;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--bup-gradient-accent);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-link:hover::after {
            width: 80%;
        }

        .btn-auth {
            background: var(--bup-gradient-accent);
            color: var(--bup-blue) !important;
            border-radius: 50px;
            padding: 12px 28px !important;
            font-weight: 800;
            letter-spacing: 1px;
            box-shadow: 0 8px 0 #C7511E, 0 10px 20px rgba(255, 145, 77, 0.3);
            transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: none;
            margin-left: 10px;
        }

        .btn-auth:hover {
            transform: translateY(3px);
            box-shadow: 0 5px 0 #C7511E, 0 15px 25px rgba(255, 145, 77, 0.4);
            color: var(--bup-blue) !important;
        }

        .btn-outline-light {
            border: 2px solid rgba(255,255,255,0.5);
            color: white !important;
            border-radius: 50px;
            padding: 12px 28px !important;
            font-weight: 700;
            transition: all 0.3s ease;
        }

        .btn-outline-light:hover {
            border-color: var(--bup-orange);
            background: var(--bup-orange);
            color: white !important;
        }

        /* Hero Section - Full Screen Impact */
        .hero {
            min-height: 100vh;
            background: var(--bup-gradient);
            position: relative;
            display: flex;
            align-items: center;
            overflow: hidden;
        }

        .hero-bg-shape {
            position: absolute;
            top: 0;
            right: 0;
            width: 70%;
            height: 100%;
            background: radial-gradient(circle at 70% 30%, rgba(255, 145, 77, 0.12) 0%, transparent 50%);
            clip-path: polygon(100% 0, 100% 100%, 30% 100%, 70% 0);
        }

        .hero-bg-shape-2 {
            position: absolute;
            bottom: -100px;
            left: -50px;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(255, 193, 7, 0.08) 0%, transparent 70%);
            border-radius: 50%;
        }

        .hero-content {
            position: relative;
            z-index: 10;
            color: white;
            padding: 120px 0 80px;
        }

        .hero-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 10px 24px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 145, 77, 0.5);
            color: var(--bup-yellow);
        }

        .hero-title {
            font-size: 68px;
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 25px;
            text-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }

        .hero-title span {
            background: linear-gradient(135deg, var(--bup-orange), var(--bup-yellow));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: inline-block;
        }

        .hero-subtitle {
            font-size: 20px;
            margin-bottom: 35px;
            color: rgba(255,255,255,0.9);
            max-width: 600px;
        }

        .hero-stats {
            display: flex;
            gap: 50px;
            margin-top: 60px;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
        }

        .stat-number {
            font-size: 42px;
            font-weight: 900;
            color: var(--bup-yellow);
            line-height: 1;
        }

        .stat-label {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 2.5px;
            color: rgba(255,255,255,0.8);
            margin-top: 8px;
        }

        .hero-image {
            position: relative;
            z-index: 10;
            animation: floating 6s ease-in-out infinite;
        }

        .hero-image-main {
            border-radius: 30px;
            box-shadow: 30px 30px 60px rgba(0,0,0,0.3);
            transform: perspective(1000px) rotateY(-5deg) rotateX(2deg);
            transition: all 0.5s ease;
            max-width: 100%;
        }

        .hero-image-main:hover {
            transform: perspective(1000px) rotateY(0deg) rotateX(0deg);
            box-shadow: 20px 20px 40px rgba(0,0,0,0.4);
        }

        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }

        /* Section Headers */
        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-tag {
            display: inline-block;
            background: linear-gradient(145deg, rgba(255,145,77,0.1), rgba(255,193,7,0.1));
            color: var(--bup-orange);
            padding: 8px 24px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 14px;
            letter-spacing: 2px;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 42px;
            font-weight: 800;
            color: var(--bup-blue);
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .section-title span {
            background: var(--bup-gradient-accent);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-subtitle {
            font-size: 18px;
            color: var(--bup-gray);
            max-width: 700px;
            margin: 0 auto;
        }

        /* Feature Cards */
        .feature-card {
            background: white;
            padding: 40px 30px;
            border-radius: 30px;
            box-shadow: var(--shadow-sm);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            height: 100%;
            border: 1px solid rgba(225, 233, 240, 0.5);
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--bup-gradient-accent);
            transform: scaleX(0);
            transition: transform 0.4s ease;
            transform-origin: left;
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-card:hover {
            transform: translateY(-15px);
            box-shadow: var(--shadow-md);
            border-color: var(--bup-orange);
        }

        .feature-icon {
            width: 90px;
            height: 90px;
            background: linear-gradient(145deg, rgba(255,145,77,0.1), rgba(255,193,7,0.1));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            color: var(--bup-orange);
            margin-bottom: 30px;
            transition: all 0.4s ease;
        }

        .feature-card:hover .feature-icon {
            background: var(--bup-gradient-accent);
            color: white;
            transform: scale(1.1) rotate(5deg);
        }

        .feature-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--bup-blue);
        }

        .feature-text {
            color: var(--bup-gray);
            margin-bottom: 0;
        }

        /* Category Cards */
        .category-card {
            background: white;
            border-radius: 24px;
            padding: 30px 20px;
            text-align: center;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            height: 100%;
            border: 1px solid transparent;
            cursor: pointer;
        }

        .category-card:hover {
            border-color: var(--bup-orange);
            transform: scale(1.05);
            box-shadow: var(--shadow-md);
        }

        .category-icon {
            width: 70px;
            height: 70px;
            background: var(--bup-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            color: white;
        }

        .category-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--bup-blue);
        }

        .category-count {
            color: var(--bup-orange);
            font-weight: 600;
            font-size: 14px;
        }

        /* Book Cards */
        .book-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            height: 100%;
            position: relative;
        }

        .book-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: var(--shadow-lg);
        }

        .book-badge {
            position: absolute;
            top: 20px;
            left: 20px;
            background: var(--bup-gradient-accent);
            color: var(--bup-blue);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 800;
            z-index: 10;
            box-shadow: 0 5px 15px rgba(255,145,77,0.3);
        }

        .book-image {
            height: 220px;
            background: linear-gradient(145deg, #F0F4F8, #E8F0F5);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .book-image i {
            font-size: 70px;
            color: var(--bup-blue);
            opacity: 0.3;
        }

        .book-content {
            padding: 25px 20px;
        }

        .book-category {
            color: var(--bup-orange);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 8px;
        }

        .book-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--bup-blue);
            line-height: 1.4;
        }

        .book-author {
            color: var(--bup-gray);
            font-size: 14px;
            margin-bottom: 15px;
        }

        .book-price {
            font-size: 24px;
            font-weight: 800;
            color: var(--bup-blue);
            margin-bottom: 20px;
        }

        .btn-book {
            background: var(--bup-gradient);
            color: white;
            border: none;
            border-radius: 30px;
            padding: 12px 24px;
            font-weight: 700;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 6px 0 #0A1F2A;
        }

        .btn-book:hover {
            transform: translateY(3px);
            box-shadow: 0 3px 0 #0A1F2A;
            color: white;
            background: var(--bup-gradient);
        }

        /* How It Works */
        .step-card {
            background: white;
            padding: 40px 30px;
            border-radius: 30px;
            text-align: center;
            box-shadow: var(--shadow-sm);
            height: 100%;
            position: relative;
        }

        .step-number {
            width: 70px;
            height: 70px;
            background: var(--bup-gradient);
            color: white;
            font-size: 32px;
            font-weight: 900;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            box-shadow: 0 10px 0 #0A1F2A;
            position: relative;
        }

        .step-icon {
            font-size: 40px;
            color: var(--bup-orange);
            margin-bottom: 20px;
        }

        .step-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--bup-blue);
        }

        .step-description {
            color: var(--bup-gray);
        }

        /* Testimonial */
        .testimonial-card {
            background: white;
            border-radius: 30px;
            padding: 35px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(225, 233, 240, 0.8);
            transition: all 0.3s ease;
        }

        .testimonial-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--bup-orange);
        }

        .testimonial-stars {
            color: var(--bup-yellow);
            font-size: 18px;
            margin-bottom: 20px;
        }

        .testimonial-text {
            font-size: 16px;
            font-style: italic;
            color: var(--bup-gray);
            margin-bottom: 25px;
            line-height: 1.7;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
        }

        .author-avatar {
            width: 55px;
            height: 55px;
            background: var(--bup-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 20px;
            margin-right: 15px;
        }

        .author-info h6 {
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--bup-blue);
        }

        .author-info span {
            color: var(--bup-gray);
            font-size: 13px;
        }

        /* CTA Section */
        .cta-section {
            background: var(--bup-gradient);
            border-radius: 60px;
            padding: 80px;
            position: relative;
            overflow: hidden;
        }

        .cta-shape {
            position: absolute;
            top: -50px;
            right: -50px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,145,77,0.2) 0%, transparent 70%);
            border-radius: 50%;
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
            transition: all 0.3s ease;
        }

        .social-link:hover {
            background: var(--bup-gradient-accent);
            color: var(--bup-blue);
            transform: translateY(-5px);
        }

        .footer-bottom {
            margin-top: 60px;
            padding-top: 30px;
            border-top: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            color: rgba(255,255,255,0.6);
        }

        /* Responsive */
        @media (max-width: 991px) {
            .hero-title {
                font-size: 48px;
            }
            
            .hero-image {
                margin-top: 50px;
            }
            
            .section-title {
                font-size: 36px;
            }
            
            .cta-section {
                padding: 50px 30px;
            }
            
            .cta-title {
                font-size: 36px;
            }
        }

        @media (max-width: 767px) {
            .navbar {
                background: rgba(10, 49, 67, 0.95);
                backdrop-filter: blur(10px);
            }
            
            .nav-link {
                color: white !important;
            }
            
            .hero-stats {
                flex-direction: column;
                gap: 25px;
            }
            
            .hero-title {
                font-size: 38px;
            }
            
            .section-title {
                font-size: 30px;
            }
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg" id="mainNav">
        <div class="container">
            <a class="navbar-brand" href="landing-page.php">
                <div class="logo-wrapper">
                    <span class="logo-text">BUP</span>
                    <span class="logo-sub">Book Resale</span>
                </div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" style="background: var(--bup-orange);">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#categories">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#how-it-works">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="client/index.php" class="btn btn-outline-light me-2">
                            <i class="bi bi-person-circle me-2"></i>Dashboard
                        </a>
                        <a href="logout.php" class="btn btn-auth">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-light me-2">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Login
                        </a>
                        <a href="register.php" class="btn btn-auth">
                            <i class="bi bi-person-plus me-2"></i>Sign Up Free
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="hero-bg-shape"></div>
        <div class="hero-bg-shape-2"></div>
        <div class="container hero-content">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-up" data-aos-duration="1000">
                    <span class="hero-badge">
                        <i class="bi bi-shield-check me-2"></i>Trusted by 1,500+ BUP Students
                    </span>
                    <h1 class="hero-title">
                        Buy, Sell & <span>Trade</span><br>Used Books at BUP
                    </h1>
                    <p class="hero-subtitle">
                        Bangladesh's first peer-to-peer textbook marketplace exclusively for BUP students. Save up to 60% on course materials and earn cash from your old books.
                    </p>
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="books.php" class="btn btn-auth btn-lg px-5 py-3" style="font-size: 18px;">
                            <i class="bi bi-search me-2"></i>Browse Books
                        </a>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="register.php" class="btn btn-outline-light btn-lg px-5 py-3" style="font-size: 18px;">
                            <i class="bi bi-tag me-2"></i>Start Selling
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="hero-stats">
                        <div class="stat-item">
                            <span class="stat-number">1,500+</span>
                            <span class="stat-label">Books Listed</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">800+</span>
                            <span class="stat-label">Happy Buyers</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">50+</span>
                            <span class="stat-label">Active Sellers</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6" data-aos="fade-left" data-aos-duration="1000" data-aos-delay="200">
                    <div class="hero-image">
                        <img src="https://placehold.co/600x500/0A3143/FF914D?text=BUP+BOOKS+HUB" alt="BUP Books Marketplace" class="hero-image-main img-fluid">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5" style="padding: 100px 0; background: white;">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <span class="section-tag">WHY BUP BOOKS</span>
                <h2 class="section-title">Designed for <span>BUP Students</span></h2>
                <p class="section-subtitle">Everything you need to save money and make money from your textbooks</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <h3 class="feature-title">Save Big</h3>
                        <p class="feature-text">Get textbooks at 40-60% less than retail price. All books are verified by our community.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-coin"></i>
                        </div>
                        <h3 class="feature-title">Earn Cash</h3>
                        <p class="feature-text">List your used books for free. Set your own price and earn money from past semesters.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <h3 class="feature-title">Campus Verified</h3>
                        <p class="feature-text">Exclusive to BUP students. Trade safely with verified .edu.bd email addresses.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-recycle"></i>
                        </div>
                        <h3 class="feature-title">Eco-Friendly</h3>
                        <p class="feature-text">Give books a second life. Reduce waste and promote sustainable campus practices.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section id="categories" class="py-5" style="background: var(--bup-offwhite); padding: 100px 0;">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <span class="section-tag">BROWSE BY SUBJECT</span>
                <h2 class="section-title">Find Your <span>Perfect Book</span></h2>
                <p class="section-subtitle">Thousands of books across all departments and disciplines</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-2 col-md-4 col-6" data-aos="zoom-in" data-aos-delay="100">
                    <div class="category-card">
                        <div class="category-icon">
                            <i class="bi bi-journal-code"></i>
                        </div>
                        <h4 class="category-title">CSE</h4>
                        <span class="category-count">320+ books</span>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6" data-aos="zoom-in" data-aos-delay="150">
                    <div class="category-card">
                        <div class="category-icon">
                            <i class="bi bi-briefcase"></i>
                        </div>
                        <h4 class="category-title">BBA</h4>
                        <span class="category-count">280+ books</span>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6" data-aos="zoom-in" data-aos-delay="200">
                    <div class="category-card">
                        <div class="category-icon">
                            <i class="bi bi-flask"></i>
                        </div>
                        <h4 class="category-title">EEE</h4>
                        <span class="category-count">210+ books</span>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6" data-aos="zoom-in" data-aos-delay="250">
                    <div class="category-card">
                        <div class="category-icon">
                            <i class="bi bi-calculator"></i>
                        </div>
                        <h4 class="category-title">Math</h4>
                        <span class="category-count">150+ books</span>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6" data-aos="zoom-in" data-aos-delay="300">
                    <div class="category-card">
                        <div class="category-icon">
                            <i class="bi bi-translate"></i>
                        </div>
                        <h4 class="category-title">English</h4>
                        <span class="category-count">190+ books</span>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6" data-aos="zoom-in" data-aos-delay="350">
                    <div class="category-card">
                        <div class="category-icon">
                            <i class="bi bi-building"></i>
                        </div>
                        <h4 class="category-title">Architecture</h4>
                        <span class="category-count">95+ books</span>
                    </div>
                </div>
            </div>
            <div class="text-center mt-5" data-aos="fade-up">
                <a href="books.php" class="btn btn-outline-auth px-5 py-3" style="border: 2px solid var(--bup-orange); color: var(--bup-orange); border-radius: 50px; font-weight: 700;">
                    View All Departments <i class="bi bi-arrow-right ms-2"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Featured Books -->
    <section class="py-5" style="background: white; padding: 100px 0;">
        <div class="container">
            <div class="d-flex justify-content-between align-items-end mb-5">
                <div data-aos="fade-right">
                    <span class="section-tag" style="margin-bottom: 10px;">HOT OFFERS</span>
                    <h2 class="section-title" style="margin-bottom: 0;">Featured <span>Books</span></h2>
                </div>
                <a href="books.php" class="text-decoration-none" style="color: var(--bup-orange); font-weight: 700; font-size: 16px;" data-aos="fade-left">
                    Browse All <i class="bi bi-arrow-right-circle-fill ms-1"></i>
                </a>
            </div>
            <div class="row g-4">
                <?php
                $featured = [
                    ['title' => 'Introduction to Algorithms', 'author' => 'CLRS', 'price' => 45.00, 'category' => 'Computer Science', 'condition' => 'Like New'],
                    ['title' => 'Principles of Marketing', 'author' => 'Philip Kotler', 'price' => 38.00, 'category' => 'Business', 'condition' => 'Very Good'],
                    ['title' => 'Engineering Mathematics', 'author' => 'Erwin Kreyszig', 'price' => 42.50, 'category' => 'Mathematics', 'condition' => 'Good'],
                    ['title' => 'Microelectronic Circuits', 'author' => 'Sedra/Smith', 'price' => 55.00, 'category' => 'EEE', 'condition' => 'Acceptable'],
                ];
                foreach ($featured as $book):
                ?>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo $loop = ($loop ?? 0) + 100; ?>">
                    <div class="book-card">
                        <span class="book-badge"><?php echo $book['condition']; ?></span>
                        <div class="book-image">
                            <i class="bi bi-book"></i>
                        </div>
                        <div class="book-content">
                            <div class="book-category"><?php echo $book['category']; ?></div>
                            <h4 class="book-title"><?php echo $book['title']; ?></h4>
                            <div class="book-author"><i class="bi bi-person"></i> <?php echo $book['author']; ?></div>
                            <div class="book-price">$<?php echo number_format($book['price'], 2); ?></div>
                            <a href="book-details.php?id=1" class="btn btn-book">
                                <i class="bi bi-eye me-2"></i>View Details
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="py-5" style="background: linear-gradient(145deg, #F8FAFC, #F0F4F8); padding: 100px 0;">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <span class="section-tag">SIMPLE PROCESS</span>
                <h2 class="section-title">How <span>BUP Books</span> Works</h2>
                <p class="section-subtitle">Three simple steps to buy or sell your books</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-4" data-aos="fade-right" data-aos-delay="100">
                    <div class="step-card">
                        <span class="step-number">1</span>
                        <div class="step-icon">
                            <i class="bi bi-person-plus-fill"></i>
                        </div>
                        <h3 class="step-title">Create Account</h3>
                        <p class="step-description">Sign up with your BUP email address. It's free and takes less than 60 seconds.</p>
                    </div>
                </div>
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="step-card">
                        <span class="step-number">2</span>
                        <div class="step-icon">
                            <i class="bi bi-book-half"></i>
                        </div>
                        <h3 class="step-title">List or Browse</h3>
                        <p class="step-description">Sellers list books with photos & price. Buyers browse by department, course, or title.</p>
                    </div>
                </div>
                <div class="col-lg-4" data-aos="fade-left" data-aos-delay="300">
                    <div class="step-card">
                        <span class="step-number">3</span>
                        <div class="step-icon">
                            <i class="bi bi-hand-thumbs-up-fill"></i>
                        </div>
                        <h3 class="step-title">Meet & Trade</h3>
                        <p class="step-description">Connect via BUP chat, arrange campus meetup, exchange books and cash.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="py-5" style="background: white; padding: 100px 0;">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <span class="section-tag">TESTIMONIALS</span>
                <h2 class="section-title">Trusted by <span>BUP Students</span></h2>
                <p class="section-subtitle">Join hundreds of students who've saved money and earned cash</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="testimonial-card">
                        <div class="testimonial-stars">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        </div>
                        <p class="testimonial-text">"Saved over 3,000tk on my CSE books this semester. The books were in excellent condition and the seller was super helpful. Highly recommend!"</p>
                        <div class="testimonial-author">
                            <div class="author-avatar">RA</div>
                            <div class="author-info">
                                <h6>Rafi Ahmed</h6>
                                <span>CSE, Batch 18</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="testimonial-card">
                        <div class="testimonial-stars">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        </div>
                        <p class="testimonial-text">"I sold 5 books from last semester and made 2,200tk. The platform is super easy to use and the verification system makes it safe. Will definitely sell again!"</p>
                        <div class="testimonial-author">
                            <div class="author-avatar">TJ</div>
                            <div class="author-info">
                                <h6>Tasnim Jahan</h6>
                                <span>BBA, Batch 20</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="testimonial-card">
                        <div class="testimonial-stars">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-half"></i>
                        </div>
                        <p class="testimonial-text">"Found a reference book that was out of stock everywhere. The community is amazing and it's great to see students helping each other save money. 10/10!"</p>
                        <div class="testimonial-author">
                            <div class="author-avatar">SK</div>
                            <div class="author-info">
                                <h6>Shahriar Khan</h6>
                                <span>EEE, Batch 19</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-5">
        <div class="container">
            <div class="cta-section" data-aos="zoom-in">
                <div class="cta-shape"></div>
                <div class="cta-shape-2"></div>
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <h2 class="cta-title">Ready to turn your old books into cash?</h2>
                        <p class="cta-text">Join hundreds of BUP sellers who've already earned money from their used textbooks. It's free to list!</p>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="client/sell.php" class="btn btn-cta btn-lg">
                            <i class="bi bi-tag me-2"></i>Start Selling
                        </a>
                        <?php else: ?>
                        <a href="register.php" class="btn btn-cta btn-lg">
                            <i class="bi bi-rocket-takeoff me-2"></i>Get Started Free
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact" class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4" data-aos="fade-right">
                    <div class="footer-logo mb-4">
                        <span class="logo-text">BUP</span>
                        <span class="logo-sub">Book Resale Platform</span>
                    </div>
                    <p style="color: rgba(255,255,255,0.7); margin-bottom: 25px; line-height: 1.8;">
                        Bangladesh University of Professionals' official student-led initiative for sustainable book exchange. Affordable, trustworthy, and campus-exclusive.
                    </p>
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="social-link"><i class="bi bi-twitter-x"></i></a>
                        <a href="#" class="social-link"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="social-link"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 offset-lg-1" data-aos="fade-up" data-aos-delay="100">
                    <h5 class="footer-title">Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="#home">Home</a></li>
                        <li><a href="books.php">Browse Books</a></li>
                        <li><a href="#categories">Categories</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                        <li><a href="faq.php">FAQ</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <h5 class="footer-title">Legal</h5>
                    <ul class="footer-links">
                        <li><a href="terms.php">Terms of Service</a></li>
                        <li><a href="privacy.php">Privacy Policy</a></li>
                        <li><a href="cookies.php">Cookie Policy</a></li>
                        <li><a href="returns.php">Returns Policy</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4" data-aos="fade-left" data-aos-delay="300">
                    <h5 class="footer-title">Contact Us</h5>
                    <ul class="footer-links">
                        <li><i class="bi bi-geo-alt-fill me-2" style="color: var(--bup-orange);"></i> BUP Campus, Mirpur, Dhaka</li>
                        <li><i class="bi bi-envelope-fill me-2" style="color: var(--bup-orange);"></i> support@bupbooks.edu.bd</li>
                        <li><i class="bi bi-telephone-fill me-2" style="color: var(--bup-orange);"></i> +880 1234 567890</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p class="mb-0">� 2026 BUP BOOKS. All rights reserved. Made with <i class="bi bi-heart-fill" style="color: var(--bup-orange);"></i> for BUP Students.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
        // Initialize AOS
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100,
            easing: 'ease-out-cubic'
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const nav = document.getElementById('mainNav');
            if (window.scrollY > 50) {
                nav.classList.add('scrolled');
            } else {
                nav.classList.remove('scrolled');
            }
        });

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
    </script>
</body>
</html>