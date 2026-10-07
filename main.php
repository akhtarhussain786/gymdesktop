<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corel Web Technology - IT Solutions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header Styles */
        header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 15px 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo i {
            font-size: 2.5rem;
            margin-right: 15px;
            color: var(--accent);
        }
        
        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .logo-text span {
            color: var(--accent);
        }
        
        nav ul {
            display: flex;
            list-style: none;
        }
        
        nav ul li {
            margin-left: 25px;
        }
        
        nav ul li a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            padding: 5px 10px;
            border-radius: 4px;
        }
        
        nav ul li a:hover {
            color: var(--accent);
            background: rgba(255, 255, 255, 0.1);
        }
        
        /* Hero Section */
        .hero {
            padding: 80px 0;
            text-align: center;
            background: url('https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?ixlib=rb-4.0.3&auto=format&fit=crop&w=1500&q=80') no-repeat center center/cover;
            color: white;
            position: relative;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
        }
        
        .hero-content {
            position: relative;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .hero h1 {
            font-size: 3rem;
            margin-bottom: 20px;
        }
        
        .hero p {
            font-size: 1.2rem;
            margin-bottom: 30px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: var(--accent);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
            border: 2px solid var(--accent);
            margin: 5px;
        }
        
        .btn:hover {
            background: transparent;
            color: var(--accent);
        }
        
        .btn-outline {
            background: transparent;
            color: white;
            border: 2px solid white;
        }
        
        .btn-outline:hover {
            background: white;
            color: var(--primary);
        }
        
        /* Services Section */
        .services {
            padding: 80px 0;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            color: var(--dark);
            margin-bottom: 20px;
        }
        
        .section-title p {
            color: #7f8c8d;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .service-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        
        .service-card:hover {
            transform: translateY(-10px);
        }
        
        .service-icon {
            background: var(--secondary);
            color: white;
            font-size: 2.5rem;
            padding: 30px;
            text-align: center;
        }
        
        .service-content {
            padding: 25px;
        }
        
        .service-content h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .service-content p {
            color: #7f8c8d;
            margin-bottom: 20px;
        }
        
        /* Address Section */
        .address {
            padding: 80px 0;
            background: var(--light);
        }
        
        .address-container {
            display: flex;
            flex-wrap: wrap;
            gap: 40px;
            align-items: center;
        }
        
        .address-map {
            flex: 1;
            min-width: 300px;
            height: 400px;
            background: #ddd;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .address-info {
            flex: 1;
            min-width: 300px;
        }
        
        .address-info h2 {
            font-size: 2rem;
            margin-bottom: 20px;
            color: var(--dark);
        }
        
        .address-details {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .address-details p {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .address-details i {
            margin-right: 15px;
            color: var(--secondary);
            font-size: 1.2rem;
            width: 25px;
        }
        
        /* Footer */
        footer {
            background: var(--dark);
            color: white;
            padding: 60px 0 30px;
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .footer-column h3 {
            font-size: 1.5rem;
            margin-bottom: 20px;
            color: var(--secondary);
        }
        
        .footer-column p, .footer-column li {
            color: #bdc3c7;
            margin-bottom: 10px;
        }
        
        .footer-column ul {
            list-style: none;
        }
        
        .footer-column ul li {
            margin-bottom: 10px;
        }
        
        .footer-column ul li a {
            color: #bdc3c7;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-column ul li a:hover {
            color: var(--secondary);
        }
        
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: white;
            transition: all 0.3s;
        }
        
        .social-links a:hover {
            background: var(--secondary);
            transform: translateY(-5px);
        }
        
        .copyright {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #34495e;
            color: #bdc3c7;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                text-align: center;
            }
            
            nav ul {
                margin-top: 20px;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            nav ul li {
                margin: 10px;
            }
            
            .hero h1 {
                font-size: 2.2rem;
            }
            
            .hero p {
                font-size: 1rem;
            }
            
            .address-container {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="header-container">
                <div class="logo">
                    <i class="fas fa-code"></i>
                    <div class="logo-text">Corel <span>Web Technology</span></div>
                </div>
                <nav>
                    <ul>
                        <li><a href="#">Home</a></li>
                        <li><a href="#">Services</a></li>
                        <li><a href="#">Products</a></li>
                        <li><a href="#">Contact</a></li>
                        <li><a href="#">About Us</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Innovative IT Solutions for Your Business</h1>
                <p>We provide cutting-edge software solutions including Gym Management, Hospital Management, and comprehensive ERP systems tailored to your needs.</p>
                <a href="#services" class="btn">Our Services</a>
                <a href="#contact" class="btn btn-outline">Contact Us</a>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services" id="services">
        <div class="container">
            <div class="section-title">
                <h2>Our Management Solutions</h2>
                <p>Comprehensive software systems designed to streamline your business operations</p>
            </div>
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <div class="service-content">
                        <h3>Gym Management</h3>
                        <h3></h3>
                        <p>Complete solution for fitness centers, member management, class scheduling, billing, and attendance tracking.</p>
                        <a href="./index.php" class="btn">Access System</a>
                    </div>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-hospital"></i>
                    </div>
                    <div class="service-content">
                        <h3>Hospital Management</h3>
                        <p>Comprehensive system for patient records, appointments, billing, inventory management, and staff scheduling.</p>
                        <a href="#" class="btn">Access System</a>
                    </div>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <div class="service-content">
                        <h3>ERP Solutions</h3>
                        <p>Enterprise resource planning system integrating all aspects of your business operations from finance to inventory.</p>
                        <a href="#" class="btn">Access System</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Address Section -->
    <section class="address" id="contact">
        <div class="container">
            <div class="section-title">
                <h2>Our Location</h2>
                <p>Visit us at our office for a consultation</p>
            </div>
            <div class="address-container">
                <div class="address-info">
                    <h2>Corel Web Technology</h2>
                    <div class="address-details">
                        <p><i class="fas fa-building"></i> Chankanta Apartment, 2nd Floor, Room No. 201</p>
                        <p><i class="fas fa-map-marker-alt"></i> Opposite Harior Chamber, Boring Road</p>
                        <p><i class="fas fa-city"></i> Patna, Bihar 800001</p>
                        <p><i class="fas fa-phone"></i> +91 XXXXX XXXXX</p>
                        <p><i class="fas fa-envelope"></i> info@corelwebtechnology.com</p>
                        <p><i class="fas fa-clock"></i> Monday-Saturday: 9:30 AM - 6:30 PM</p>
                    </div>
                </div>
                <div class="address-map">
                    <!-- Google Map placeholder -->
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3597.862263947012!2d85.119260575487!3d25.61297737748024!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x39ed5853a2b1f60d%3A0x37a9a972ce5b982b!2sBoring%20Rd%2C%20Patna%2C%20Bihar!5e0!3m2!1sen!2sin!4v1689845027664!5m2!1sen!2sin" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-column">
                    <h3>Corel Web Technology</h3>
                    <p>Providing innovative IT and software solutions for businesses of all sizes since 2015.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="footer-column">
                    <h3>Our Products</h3>
                    <ul>
                        <li><a href="./index.php">Gym Management System</a></li>
                        <li><a href="#">Hospital Management System</a></li>
                        <li><a href="#">ERP Solutions</a></li>
                        <li><a href="#">Inventory Management</a></li>
                        <li><a href="#">E-Commerce Solutions</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="#">Home</a></li>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Services</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms & Conditions</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Contact Info</h3>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i> Chankanta Apartment, 2nd Floor</li>
                        <li><i class="fas fa-phone"></i> +91 XXXXX XXXXX</li>
                        <li><i class="fas fa-envelope"></i> info@corelweb.com</li>
                        <li><i class="fas fa-clock"></i> Mon-Sat: 9:30AM - 6:30PM</li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; 2023 Corel Web Technology. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Simple JavaScript for interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('.btn');
            
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.05)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
            
            // Service card click handler
            const serviceCards = document.querySelectorAll('.service-card');
            serviceCards.forEach(card => {
                card.addEventListener('click', function() {
                    const link = this.querySelector('a');
                    if (link) {
                        window.location.href = link.href;
                    }
                });
            });
        });
    </script>
</body>
</html>