# EventEase - School Event Management System

A comprehensive web-based event management system designed for schools, allowing students and teachers to create events with admin approval workflow.

## 🚀 Features

### Core Functionality
- **User Authentication**: Secure login/register system with role-based access
- **Event Creation**: Students and teachers can create events (pending admin approval)
- **Admin Approval**: Admins can approve, decline, or delete events
- **RSVP System**: Users can RSVP to approved events with capacity management
- **Dashboard**: Separate dashboards for admins and users

### User Roles
- **Admin**: Full system access, event approval, user management
- **Teacher**: Create events, RSVP to events, manage own events
- **Student**: Create events, RSVP to events, manage own events

### Key Features
- Modern, responsive design
- Real-time event status tracking
- Search and filter functionality
- Capacity management for events
- Flash message notifications
- Form validation
- Mobile-friendly interface

## 🛠️ Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Server**: Apache (XAMPP)
- **Security**: Password hashing, SQL injection prevention, XSS protection

## 📋 Prerequisites

- XAMPP (Apache + MySQL + PHP)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web browser (Chrome, Firefox, Safari, Edge)

## 🚀 Installation & Setup

### 1. Clone/Download the Project
```bash
# If using git
git clone <repository-url>
# Or download and extract to your XAMPP htdocs folder
```

### 2. Set Up XAMPP
1. Start XAMPP Control Panel
2. Start Apache and MySQL services
3. Ensure both services are running (green status)

### 3. Database Setup
1. Open your web browser and go to `http://localhost/phpmyadmin`
2. Create a new database (optional - the script will create it automatically)
3. Import the database schema:
   - Go to the SQL tab
   - Copy and paste the contents of `sql/schema.sql`
   - Click "Go" to execute

### 4. Configure Database Connection
1. Open `config.php`
2. Update database settings if needed:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'eventease');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

### 5. Access the Application
1. Open your web browser
2. Navigate to `http://localhost/WS/eventEase` (adjust path as needed)
3. The application should now be running!

## 👥 Default Login Credentials

### Admin Account
- **Username**: admin
- **Password**: password
- **Email**: admin@school.edu

### Teacher Account
- **Username**: teacher1
- **Password**: password
- **Email**: teacher1@school.edu

### Student Account
- **Username**: student1
- **Password**: password
- **Email**: student1@school.edu

## 📁 Project Structure

```
eventEase/
├── admin/                 # Admin panel files
│   ├── dashboard.php     # Admin dashboard
│   ├── events.php        # Event management
│   └── users.php         # User management
├── user/                  # User panel files
│   ├── dashboard.php     # User dashboard
│   ├── create_event.php  # Event creation handler
│   └── rsvp.php          # RSVP functionality
├── assets/               # Static assets
│   ├── css/
│   │   └── style.css     # Main stylesheet
│   └── js/
│       └── main.js       # JavaScript functionality
├── sql/
│   └── schema.sql        # Database schema
├── config.php            # Configuration file
├── index.php             # Home page
├── login.php             # Login page
├── register.php          # Registration page
├── logout.php            # Logout handler
└── README.md             # This file
```

## 🔧 Configuration

### Database Configuration
Edit `config.php` to modify database settings:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'eventease');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### Site Configuration
```php
define('SITE_NAME', 'EventEase');
define('SITE_URL', 'http://localhost/WS/eventEase');
```

## 🎯 Usage Guide

### For Admins
1. **Login** with admin credentials
2. **Dashboard**: View system statistics and recent events
3. **Manage Events**: Approve, decline, or delete pending events
4. **Manage Users**: View all users, change roles, delete users

### For Teachers/Students
1. **Register** a new account or **login** with existing credentials
2. **Dashboard**: View your events and upcoming events
3. **Create Events**: Fill out the event form (requires admin approval)
4. **Browse Events**: RSVP to approved events
5. **Track RSVPs**: See events you're attending

## 🔒 Security Features

- **Password Hashing**: All passwords are hashed using PHP's `password_hash()`
- **SQL Injection Prevention**: Prepared statements for all database queries
- **XSS Protection**: Input sanitization using `htmlspecialchars()`
- **Session Management**: Secure session handling
- **Role-based Access**: Proper authorization checks

## 🎨 Customization

### Styling
- Edit `assets/css/style.css` to customize the appearance
- The design uses CSS Grid and Flexbox for responsive layouts
- Color scheme can be modified in the CSS variables

### Functionality
- Add new features by extending the existing PHP classes
- Modify database schema in `sql/schema.sql`
- Add JavaScript functionality in `assets/js/main.js`

## 🐛 Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Ensure MySQL is running in XAMPP
   - Check database credentials in `config.php`
   - Verify database exists

2. **Page Not Found (404)**
   - Ensure Apache is running in XAMPP
   - Check file paths and permissions
   - Verify .htaccess configuration

3. **Login Issues**
   - Clear browser cache and cookies
   - Check if sessions are working
   - Verify database tables exist

4. **Event Creation Fails**
   - Check form validation
   - Ensure all required fields are filled
   - Verify database permissions

### Debug Mode
To enable debug mode, add this to `config.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 📝 API Endpoints

The application uses standard HTTP forms, but here are the main endpoints:

- `POST /user/create_event.php` - Create new event
- `POST /user/rsvp.php` - RSVP to events
- `POST /admin/events.php` - Admin event actions
- `POST /admin/users.php` - Admin user actions

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This project is open source and available under the [MIT License](LICENSE).

## 🆘 Support

For support or questions:
1. Check the troubleshooting section
2. Review the code comments
3. Create an issue in the repository

## 🔄 Updates

### Version 1.0.0
- Initial release
- Basic event management functionality
- User authentication system
- Admin approval workflow
- RSVP system

---

**EventEase** - Making school event management simple and efficient! 🎉 