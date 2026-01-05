<div align="center">

# 🎧 CRM Helpdesk System

### Hệ Thống Quản Lý Hỗ Trợ Khách Hàng

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat&logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=flat&logo=php)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat&logo=mysql)](https://mysql.com)

Một giải pháp quản lý support ticket toàn diện với real-time messaging, SLA tracking và intelligent escalation.

</div>

---

## Mục Lục

- [Giới Thiệu](#giới-thiệu)
- [Tính Năng](#tính-năng)
- [Công Nghệ Sử Dụng](#công-nghệ-sử-dụng)
- [Database Schema](#database-schema)
- [Cài Đặt](#cài-đặt)
- [Cấu Trúc Project](#cấu-trúc-project)
- [API Documentation](#api-documentation)

---

## Giới Thiệu

**CRM Helpdesk** là hệ thống quản lý hỗ trợ khách hàng full-stack được xây dựng để quản lý support tickets một cách hiệu quả. Hệ thống cho phép giao tiếp real-time giữa khách hàng và nhân viên support, tự động monitor SLA với intelligent escalation, track satisfaction của khách hàng, và cung cấp comprehensive audit logging.

Hệ thống phù hợp cho các doanh nghiệp cần quản lý inquiries khách hàng ở quy mô lớn, thể hiện các best practices của modern full-stack development với real-time capabilities, background job processing, và role-based access control.

---

## Tính Năng

### 🎫 Quản Lý Ticket
- **Workflow Đa Trạng Thái**: Mới → Đang xử lý → Chờ phản hồi → Đã giải quyết → Đã đóng
- **Mức Độ Ưu Tiên**: Thấp, Trung bình, Cao, Khẩn cấp với màu sắc trực quan
- **Tự Động Tạo Mã Ticket**: Định dạng duy nhất `TKT-YYYY-XXXXXX`
- **Hệ Thống Danh Mục**: Phân loại ticket theo danh mục phân cấp
- **Phân Công**: Gán ticket cho nhân viên CSKH cụ thể
- **Bộ Lọc Nâng Cao**: Lọc theo trạng thái, ưu tiên, danh mục, khoảng thời gian, tìm kiếm

### 💬 Nhắn Tin Real-time
- **Live Chat**: WebSocket-based real-time communication qua Laravel Reverb
- **Typing Indicators**: Hiển thị khi someone đang nhập tin nhắn
- **Read Receipts**: Track khi messages được đọc (`read_at` timestamp)
- **Internal Notes**: Tin nhắn nội bộ chỉ nhân viên thấy
- **File Attachments**: Upload images và documents qua Cloudinary
- **Message History**: Thread cuộc trò chuyện hoàn chỉnh cho mỗi ticket

### ⏱️ SLA & Escalation System
- **Response Time Tracking**: Monitor tự động dựa trên priority của ticket
  - Thấp: 60 phút | Trung bình: 30 phút | Cao: 15 phút | Khẩn cấp: 5 phút
- **Two-Tier Escalation**:
  - **Warning Level**: Thông báo khi approaching SLA limit
  - **Escalated Level**: Alert admin khi SLA bị breach
- **Telegram Notifications**: Automated alerts đến admin channel
- **Escalation History**: Audit trail hoàn chỉnh cho SLA violations
- **Auto-Resolution**: Escalations clear khi ticket status thay đổi

### ⭐ Đánh Giá Sự Hài Lòng
- **Rating System**: 1-5 sao với **half-star precision** (4.5 sao)
- **Post-Resolution Ratings**: Khách hàng chỉ rate sau khi ticket được resolve
- **Thống Kê Nhân Viên**: Track average ratings và rating distribution cho mỗi staff
- **Rating Analytics**: Visual breakdown của customer satisfaction

### 📝 Template System (Canned Responses)
- **Quick Replies**: Response templates cho common inquiries
- **Variable Substitution**: Placeholders động như `{customer_name}`, `{ticket_number}`
- **Category-Based**: Organize templates theo ticket categories
- **Usage Tracking**: Track templates phổ biến và recently used
- **Live Preview**: Xem template trông như thế nào trước khi gửi

### 📊 Activity Logging & Audit
- **Comprehensive Logging**: Track tất cả user actions (login, CRUD, status changes)
- **Field-Level Change Tracking**: Xem old → new values cho important fields
- **Searchable & Filterable**: Lọc theo user, action, date range, log level
- **Export Functionality**: Export logs ra CSV cho compliance
- **Auto-Cleanup**: Scheduled removal của logs older hơn 90 ngày

### 🔔 Notifications
- **Real-time Notifications**: Instant alerts cho new messages và assignments
- **Unread Counter**: Badge hiển thị số unread notifications
- **Mass Operations**: Mark all as read, delete all
- **Telegram Integration**: Receive critical alerts qua Telegram bot

### 📈 Dashboard & Analytics
- **Statistics Cards**: Total, open, pending, resolved, closed tickets
- **Performance Metrics**: Resolution rate, average response time
- **SLA Compliance**: Track SLA breach percentage
- **Rating Summary**: Customer satisfaction overview
- **Visual Charts**: Bar charts và trend lines cho data visualization

### 👥 User & Role Management
- **Role-Based Access Control** (sử dụng Spatie Laravel Permission):
  - **Admin**: Full system access, user management, settings
  - **CSKH**: Handle tickets, access templates, view analytics
  - **User**: Create tickets, view own tickets, rate services
- **User Profiles**: Name, email, phone, avatar
- **Activity Tracking**: Complete audit trail per user

---

## Công Nghệ Sử Dụng

### Backend
| Công Nghệ | Mục Đích |
|-----------|----------|
| **Laravel 12.x** | PHP Framework |
| **PHP 8.2+** | Runtime |
| **MySQL 8.0** | Database |
| **Laravel Sanctum** | API Authentication |
| **Spatie Laravel Permission** | Role-Based Access Control |
| **Laravel Reverb** | WebSocket Server |
| **Laravel Queues** | Background Job Processing |
| **Telegram Bot SDK** | External Notifications |
| **Cloudinary** | File Storage |

### Frontend
| Công Nghệ | Mục Đích |
|-----------|----------|
| **Vue 3** | JavaScript Framework (Composition API) |
| **Pinia** | State Management |
| **Vue Router 4** | Client-Side Routing |
| **Tailwind CSS** | Styling |
| **Axios** | HTTP Client |
| **Laravel Echo** | Real-time Event Broadcasting |
| **Vite** | Build Tool |

---

## Database Schema

```
users
├── id, name, email, password
├── phone, avatar
├── avg_rating, total_ratings, rating_distribution
└── roles (Admin, CSKH, User)

tickets
├── id, ticket_number, title, description
├── status (open, processing, pending, resolved, closed)
├── priority (low, medium, high, urgent)
├── user_id (customer), assigned_to (staff)
├── category_id
├── sla_response_deadline, last_status_change_at
└── timestamps, soft_deletes

messages
├── id, ticket_id, user_id
├── content, is_internal (boolean)
├── read_at (cho read receipts)
└── timestamps

categories
├── id, name, description
├── parent_id (cho hierarchy)
└── timestamps

ratings
├── id, ticket_id
├── giver_id, receiver_id
├── rating (1-5 với 0.5 precision)
└── timestamps

canned_responses
├── id, title, content
├── category_id, variables
├── usage_count
└── timestamps

ticket_escalations
├── id, ticket_id
├── level (warning, escalated)
├── triggered_at, resolved_at
└── timestamps

activity_logs
├── id, user_id, action
├── subject_type, subject_id
├── description, log_level
├── ip_address, user_agent
├── tags, properties (JSON)
└── timestamps, soft_deletes

activity_log_details
├── id, activity_log_id
├── field_name, old_value, new_value
└── timestamps

notifications
├── id, user_id, type
├── data (JSON), read_at
└── timestamps

attachments
├── id, ticket_id, message_id
├── file_path, file_type, file_size
└── timestamps
```

### Key Relationships
```
User 1:N Ticket (created)
User 1:N Ticket (assigned)
User 1:N Message
User 1:N Rating (given)
User 1:N Rating (received)
Ticket 1:N Message
Ticket 1:N Rating
Ticket 1:N TicketEscalation
Category 1:N Category (self-referential)
Category 1:N Ticket
Category 1:N CannedResponse
```

---

## Cài Đặt

### Yêu Cầu
- PHP 8.2 hoặc cao hơn
- Composer
- Node.js & NPM
- MySQL 8.0 hoặc cao hơn
- Cloudinary account (cho file uploads)
- Telegram Bot (optional, cho notifications)

### Backend Setup

```bash
# Clone repository
git clone <repository-url>
cd crm-helpdesk

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure .env với database và API credentials của bạn
# DB_DATABASE=crm_helpdesk
# DB_USERNAME=your_username
# DB_PASSWORD=your_password

# Run migrations
php artisan migrate

# Seed database (optional)
php artisan db:seed

# Start Laravel development server
php artisan serve

# Start queue worker (trong terminal riêng)
php artisan queue:work

# Start Reverb WebSocket server (trong terminal riêng)
php artisan reverb:start
```

### Frontend Setup

```bash
# Navigate đến frontend directory
cd ../crm-helpdesk-frontend

# Install dependencies
npm install

# Copy environment file
cp .env.example .env

# Update VITE_API_BASE_URL nếu cần

# Run development server
npm run dev
```

### Quick Start (All Services)

```bash
# Sử dụng convenience script
npm run dev
```

Lệnh này start Laravel server, queue worker, và Reverb server đồng thời.

---

## Cấu Trúc Project

### Backend (Laravel)
```
app/
├── Controllers/
│   └── Api/
│       └── V1/
│           ├── AuthController.php          # Authentication
│           ├── TicketController.php        # Ticket CRUD
│           ├── MessageController.php       # Real-time messaging
│           ├── RatingController.php        # Rating system
│           ├── CannedResponseController.php # Template management
│           ├── DashboardController.php     # Analytics
│           ├── ActivityLogController.php   # Audit logs
│           └── ...
├── Models/
│   ├── User.php                            # Enhanced user model
│   ├── Ticket.php                          # Main ticket entity
│   ├── Message.php                         # Chat messages
│   ├── Rating.php                          # Customer ratings
│   ├── TicketEscalation.php                # SLA tracking
│   ├── ActivityLog.php                     # Audit logs
│   └── ...
├── Services/
│   ├── ActivityLogService.php              # Centralized logging
│   └── FileUploadService.php               # Cloudinary integration
├── Observers/                              # Model event handlers
│   ├── TicketObserver.php
│   ├── MessageObserver.php
│   └── ...
├── Jobs/                                   # Background jobs
│   ├── CheckTicketEscalation.php           # SLA monitoring
│   └── SendTelegramNotification.php
├── Events/                                 # Real-time events
│   ├── NewMessage.php
│   ├── MessageRead.php
│   └── ...
└── Repositories/                           # Data access layer
```

---

## API Documentation

### Authentication

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/register` | POST | Đăng ký user mới |
| `/api/v1/login` | POST | Đăng nhập & lấy token |
| `/api/v1/logout` | POST | Invalidate token |
| `/api/v1/me` | GET | Lấy user hiện tại |

### Tickets

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/tickets` | GET | List tickets (paginated, filtered) |
| `/api/v1/tickets` | POST | Tạo ticket mới |
| `/api/v1/tickets/{id}` | GET | Lấy chi tiết ticket |
| `/api/v1/tickets/{id}` | PUT | Cập nhật ticket |
| `/api/v1/tickets/{id}/assign` | POST | Gán cho staff |
| `/api/v1/tickets/{id}/status` | PUT | Thay đổi trạng thái |
| `/api/v1/tickets/statistics` | GET | Thống kê dashboard |

### Messages

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/tickets/{id}/messages` | GET | Lấy conversation |
| `/api/v1/tickets/{id}/messages` | POST | Gửi message |
| `/api/v1/messages/{id}/read` | POST | Mark as read |
| `/api/v1/messages/typing` | POST | Broadcast typing |

### Ratings

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/tickets/{id}/rating` | POST | Gửi đánh giá |
| `/api/v1/users/{id}/ratings` | GET | Lấy ratings của user |

### Admin

| Endpoint | Method | Description | Auth |
|----------|--------|-------------|------|
| `/api/v1/admin/users` | GET/POST | Quản lý users | Admin |
| `/api/v1/admin/roles` | GET/POST | Quản lý roles | Admin |
| `/api/v1/admin/activity-logs` | GET | Xem audit logs | Admin |
| `/api/v1/admin/activity-logs/export` | GET | Export logs | Admin |

---

## Technical Highlights

### 1. Real-time Architecture
Sử dụng **Laravel Reverb** cho WebSocket communication, hệ thống đạt được:
- Instant message delivery mà không cần refresh trang
- Live typing indicators
- Real-time ticket status updates
- Instant notification delivery

### 2. SLA Automation
Background jobs (`CheckTicketEscalation`) chạy mỗi phút để:
- Calculate time since ticket creation
- Compare với priority-based response time thresholds
- Tự động tạo escalation records khi thresholds exceeded
- Gửi Telegram notifications đến admins

### 3. Audit Trail System
Comprehensive logging sử dụng **Observer Pattern**:
- Model observers tự động log CRUD operations
- Field-level change tracking với old/new values
- Centralized `ActivityLogService` cho consistent logging
- IP address và user agent capture

### 4. Rating với Half-Star Precision
Custom rating system hỗ trợ:
- Database storage dưới dạng decimal (4.5, 3.5, etc.)
- Visual star display với half-star rendering
- Automatic calculation của user rating averages
- Rating distribution statistics

### 5. Security
- **API Authentication**: Laravel Sanctum với token-based auth
- **Role-Based Access**: Spatie Permission package
- **Request Validation**: Form request validation trên tất cả endpoints
- **File Upload Validation**: Type và size restrictions
- **CSRF Protection**: Enabled cho web routes

---

## Author

Built with ❤️ sử dụng Laravel & Vue 3
