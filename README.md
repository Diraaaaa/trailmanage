# 🚆 Train Management System

<p align="center">
  <img src="https://img.shields.io/badge/PHP-Backend-777BB4?style=for-the-badge&logo=php&logoColor=white" />
  <img src="https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white" />
  <img src="https://img.shields.io/badge/HTML5-Frontend-E34F26?style=for-the-badge&logo=html5&logoColor=white" />
  <img src="https://img.shields.io/badge/CSS3-Styling-1572B6?style=for-the-badge&logo=css3&logoColor=white" />
</p>

## 📖 Overview

Train Management System is a web-based application developed to manage railway reservation activities and administrative operations. The system provides an integrated platform for managing train schedules, passenger information, ticket reservations, transactions, and payment records.

Built with PHP and MySQL, this application implements database integration and CRUD (Create, Read, Update, Delete) functionality to support efficient and structured data management.

## ✨ Features

| Feature | Description |
|---|---|
| 🚆 Train Management | Manage train information and schedules |
| 👤 Passenger Management | Manage passenger records |
| 🎫 Reservation Management | Manage ticket reservations |
| 💳 Transaction Management | Record and manage transactions |
| 💰 Payment Management | Manage payment methods and records |
| 📊 Admin Dashboard | Access and monitor application data |
| ⚙️ CRUD Operations | Create, read, update, and delete data |

## 🛠️ Built With

- **PHP** – Backend development
- **MySQL** – Relational database management
- **HTML5** – Web page structure
- **CSS3** – User interface styling
- **JavaScript** – Frontend interaction
- **XAMPP** – Local development environment
- **phpMyAdmin** – Database administration

## 📂 Project Structure

```text
Train-Management-System/
│
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│
├── database/
│   └── kereta_api.sql
│
├── config/
│   └── connect.php
│
├── dashboard/
├── kereta/
├── penumpang/
├── reservasi/
├── transaksi/
├── pembayaran/
│
├── index.php
└── README.md
```

*Note: Project structure may vary depending on the implementation.*

## ⚙️ Installation & Setup

1. Clone this repository:

   ```bash
   git clone https://github.com/USERNAME/Train-Management-System.git
   ```

2. Move the project folder into the XAMPP `htdocs` directory.

3. Start Apache and MySQL through XAMPP Control Panel.

4. Open phpMyAdmin:

   ```text
   http://localhost/phpmyadmin
   ```

5. Create a database named `kereta_api`.

6. Import the SQL file from the `database` folder.

7. Configure the database connection in `connect.php`.

8. Open the application in your browser:

   ```text
   http://localhost/Train-Management-System/
   ```

## 🎯 Project Objectives

- Develop a web-based railway management system.
- Implement PHP for server-side application development.
- Integrate MySQL for relational database management.
- Apply CRUD operations to manage application data.
- Understand backend development and database relationships.

## 🚧 Project Status

**Status:** Development

This project is continuously developed to improve functionality, database integration, and user experience.

---

<p align="center">
  Developed as an Academic Web Development Project
</p>
