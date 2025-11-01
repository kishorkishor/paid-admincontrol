# 🚀 Modern Admin Control Panel

A complete admin dashboard system with a modern, mobile-responsive UI/UX design. Built with PHP and vanilla CSS, featuring smooth animations, gradient designs, and intuitive interfaces.

## ✨ Features

### 🎨 Modern UI/UX Design
- **Responsive Design**: Mobile-first approach that adapts beautifully to all screen sizes
- **Gradient Buttons**: Eye-catching orange gradient buttons with hover effects
- **Animated Cards**: Smooth fade-in animations with staggered delays
- **Custom Scrollbars**: Styled scrollbars for better aesthetics
- **Inter Font Family**: Modern typography throughout the application

### 📊 Dashboard Pages (Phase 1 - Complete)

1. **Supervisor Dashboard** (`supervisor.php`)
   - Live metrics with auto-refresh
   - Team performance tracking
   - Recent activity feed
   - Pending approvals management

2. **Main Dashboard** (`index.php`)
   - Animated stat cards showing key metrics
   - Gradient 3-color header
   - Quick stats overview

3. **Query Management** (`queries.php`)
   - Modern table layout with badges
   - Priority and status indicators
   - Contact information display
   - Quick action buttons

4. **Team Management** (`teams.php`)
   - Active/Inactive status badges
   - Modern dropdowns for leader assignment
   - Quick toggle buttons

5. **User & Role Management** (`users.php`)
   - Admin creation form
   - Role assignment interface
   - Permission management with checkboxes
   - Success/error alerts

## 🛠️ Technical Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL
- **Frontend**: HTML5, CSS3 (Vanilla)
- **Fonts**: Google Fonts (Inter)
- **Design**: Custom CSS with CSS Variables

## 📦 Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- XAMPP/WAMP (for local development)

### Setup

1. **Clone the repository**
```bash
git clone https://github.com/kishorkishor/paid-admincontrol.git
cd paid-admincontrol
```

2. **Configure Database**
   - Import your database SQL file
   - Update database credentials in `app/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

3. **Configure Web Server**
   - Point your web server to the `public_html` directory for production
   - Or use the `app` directory for development

4. **Access the Application**
   - Development: `http://localhost/admin-a/app/`
   - Production: Configure your domain to point to `public_html/app/`

## 🎯 Key Design Principles

### Page-Scoped CSS
Each page uses its own CSS scope to avoid conflicts:
- `.sv-root` - Supervisor dashboard
- `.idx-root` - Main index
- `.qry-root` - Queries page
- `.tm-root` - Teams page
- `.usr-root` - Users page

### Color Scheme
```css
--primary: #ff6b2c;
--primary-light: #ff914b;
--primary-dark: #e8551a;
--text: #0f172a;
--text-light: #64748b;
--bg: #f8fafc;
--card: #ffffff;
```

### Responsive Breakpoints
- Mobile: < 768px
- Tablet: 768px - 1024px
- Desktop: > 1024px

## 📱 Mobile Features

- Horizontal scrolling tables for better mobile experience
- Stacked forms on small screens
- Touch-friendly buttons and inputs
- Optimized padding and spacing

## 🔐 Security Features

- Session-based authentication
- Role-based access control
- SQL injection prevention (PDO prepared statements)
- XSS protection (htmlspecialchars)
- Password hashing (bcrypt)

## 🚧 Roadmap

### Phase 2: Query Management Pages (Upcoming)
- Individual query view
- Supervisor query detail
- Team member query view
- BD Agent queries list

### Phase 3: Team Supervisor Pages
- BD Supervisor dashboard
- Review interface
- Negotiation view

### Phase 4: Order Management
- Order supervisor interface
- Order agent interface
- Chinese accounts management
- Carton management

### Phase 5: QC & Inbound
- QC agent interface
- QC supervisor dashboard
- Inbound management

### Phase 6: Delivery & Payments
- Delivery management
- Payment processing
- Wallet management

## 📄 License

This is a private/paid admin control panel. All rights reserved.

## 👤 Author

**Kishor Kishor**
- GitHub: [@kishorkishor](https://github.com/kishorkishor)
- Email: kishortarafder@gmail.com

## 🙏 Acknowledgments

- Inter font by Rasmus Andersson
- Design inspiration from modern dashboard patterns
- Built with ❤️ for Cosmic Trading

---

**Note**: This repository does not include database files or configuration files with credentials for security reasons. Please configure these separately for your environment.

