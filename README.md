# OmniBuy

OmniBuy is a web-based marketplace application developed for **CPSC 491 Senior Capstone Project in Computer Science** at California State University, Fullerton.

The goal of OmniBuy is to combine useful features from modern online marketplaces into one platform. Users can buy and sell items, search and filter listings, communicate with sellers, save items, choose between shipping and local meetup options, and build trust through reviews and safety features.

---

## Team

- Bryant Huynh
- Chantal Le
- Emily Bongiorno
- Kenneth Ly
- Riley Baltes

---

## Project Goals

OmniBuy is designed to provide a marketplace that is:

- Easy to use
- Accessible to buyers and sellers
- Suitable for local and shipped transactions
- Safe for user-to-user interactions
- Maintainable and scalable
- Responsive across different devices

The application is particularly intended to support students, local communities, casual sellers, and small businesses.

---

## Core Features

### Accounts and Profiles

- User registration
- Login and logout
- Password recovery
- User profiles
- Seller profiles
- Profile editing
- Privacy settings

### Listings

- Create listings
- Upload listing images
- Edit listings
- Delete listings
- Hide or pause listings
- Renew listings
- Mark listings as sold
- View individual listing details

### Search and Discovery

- Homepage listing feed
- Keyword search
- Seller search
- Category browsing
- Price filtering
- Condition filtering
- Delivery-method filtering
- Meetup-radius filtering
- Result sorting

### Shopping

- Watchlist / favorites
- Shopping cart
- Checkout workflow
- Order creation
- Order history
- Shipping or meetup fulfillment

### Messaging

- Message sellers directly from listings
- Create buyer/seller conversations
- Inbox
- Message threads
- Read/unread status

### Trust and Safety

- Block users
- Report users
- Moderation workflow
- Leave reviews after transactions
- Seller ratings and reputation

### Future / Advanced Features

- Personalized listing recommendations
- Seller analytics
- Listing performance analytics
- More advanced search and recommendation algorithms

---

# Technology Stack

## Frontend

- HTML5
- CSS3
- JavaScript

## Backend

- PHP

## Database

- MySQL / SQL

## Development Tools

- Git
- GitHub
- Docker
- Docker Compose
- VS Code
- phpMyAdmin
- Postman
- Chrome DevTools

---

# Architecture

OmniBuy follows a layered architecture.

```text
Presentation Layer
HTML / CSS / JavaScript
        |
        v
Application / Business Logic
PHP
        |
        v
Data Access Layer
PHP / SQL
        |
        v
MySQL Database
```

The goal of this architecture is to keep the user interface, application logic, and database operations separated.

For example:

```text
Create Listing Form
        |
        v
JavaScript / HTTP Request
        |
        v
PHP Listing Logic
        |
        v
SQL Database
        |
        v
Listing Saved
        |
        v
Frontend Updated
```

---

# Development Environment

OmniBuy uses Docker to provide a consistent development environment for all team members.

The development environment is intended to contain:

```text
Docker Compose
    |
    +-- PHP / Apache
    |
    +-- MySQL
    |
    +-- phpMyAdmin (optional)
```

Using Docker allows each developer to run the same PHP and database configuration regardless of their local operating system.

---

# Getting Started

## Prerequisites

Install the following:

- Git
- Docker Desktop
- VS Code or another code editor

Verify Docker is installed:

```bash
docker --version
docker compose version
```

---

## 1. Clone the Repository

```bash
git clone <repository-url>
cd omnibuy
```

Replace `<repository-url>` with the GitHub repository URL.

---

## 2. Configure Environment Variables

If the repository contains an `.env.example` file:

```bash
cp .env.example .env
```

Configure the local database credentials as needed.

Example:

```env
DB_HOST=db
DB_NAME=omnibuy
DB_USER=omnibuy_user
DB_PASSWORD=change_me
```

Do not commit real passwords or private credentials to GitHub.

---

## 3. Start Docker

Run:

```bash
docker compose up -d
```

This starts the services required by OmniBuy.

Check that the containers are running:

```bash
docker compose ps
```

---

## 4. Initialize the Database

The database schema should be stored in the repository under the database directory.

Example:

```text
database/
├── schema.sql
├── seed.sql
└── migrations/
```

If initialization is not automatic, import `schema.sql` into the OmniBuy database.

---

## 5. Open OmniBuy

Once the containers are running, open the local application address configured in `docker-compose.yml`.

Example:

```text
http://localhost
```

The actual port may differ depending on the Docker configuration.

---

## 6. Stop the Environment

```bash
docker compose down
```

To restart:

```bash
docker compose up -d
```

---

# Suggested Project Structure

```text
omnibuy/
│
├── frontend/
│   ├── pages/
│   ├── css/
│   ├── js/
│   ├── components/
│   └── assets/
│
├── backend/
│   ├── api/
│   ├── auth/
│   ├── controllers/
│   ├── models/
│   ├── services/
│   └── config/
│
├── database/
│   ├── schema.sql
│   ├── seed.sql
│   └── migrations/
│
├── tests/
│
├── docs/
│
├── Dockerfile
├── docker-compose.yml
├── .env.example
├── .gitignore
└── README.md
```

The exact structure may change as development continues.

---

# Core Database Entities

The planned database includes entities such as:

```text
users
profiles
listings
listing_images
categories
favorites
conversations
messages
orders
reviews
reports
blocked_users
```

Relationships between these tables allow the system to associate buyers, sellers, listings, messages, transactions, and reviews.

Example:

```text
User
 |
 +------ Listings
 |
 +------ Favorites
 |
 +------ Messages
 |
 +------ Orders
 |
 +------ Reviews
```

---

# Development Dependency Order

Features should be implemented according to their dependencies.

```text
Development Environment
        |
        v
Database
        |
        v
Authentication
        |
        v
Users / Profiles
        |
        v
Listings
        |
        v
Browse / Search
        |
        v
Filters / Sorting
        |
        +----------------+
        |                |
        v                v
     Favorites        Delivery
        |                |
        +-------+--------+
                |
                v
            Messaging
                |
                v
           Transactions
                |
                v
             Reviews
```

Authentication and user identity are established early because most later features require a valid user.

Listings are another major dependency because search, filters, favorites, messaging, checkout, and reviews all depend on marketplace listings existing.

---

# Git Workflow

Development should not be performed directly on the production branch.

The project uses a workflow similar to:

```text
feature branch
      |
      v
Pull Request
      |
      v
Peer Review
      |
      v
develop
      |
      v
Integration Testing
      |
      v
main
```

Example branches:

```text
feature/user-registration
feature/login
feature/create-listing
feature/search
feature/messaging
fix/session-authentication
```

---

## Creating a Feature Branch

Update your local development branch:

```bash
git checkout develop
git pull
```

Create a new feature branch:

```bash
git checkout -b feature/feature-name
```

After making changes:

```bash
git add .
git commit -m "Implement feature description"
git push -u origin feature/feature-name
```

Then create a Pull Request on GitHub.

---

# Pull Request Requirements

Before merging a Pull Request:

- Code should run locally
- Relevant functionality should be tested
- No credentials should be committed
- Code should follow project conventions
- Database changes should be documented
- New environment variables should be added to `.env.example`
- Another team member should review the change
- Integration issues should be resolved

---

# Definition of Done

A feature is considered complete when:

- The required functionality has been implemented
- Frontend and backend communicate successfully
- Database changes work correctly
- Input validation exists
- Expected error conditions are handled
- The feature has been tested
- The code is committed through a feature branch
- A Pull Request has been created
- Another team member has reviewed the Pull Request
- The feature works after being integrated with the rest of OmniBuy

---

# Testing

Testing will occur throughout development rather than only at the end of the semester.

Testing areas include:

- Unit testing
- Integration testing
- User-interface testing
- Authentication testing
- Database testing
- Validation/error testing
- End-to-end workflow testing
- Regression testing

Important workflows include:

```text
Register
→ Login
→ Create Profile
→ Create Listing
→ Browse/Search Listing
→ Message Seller
→ Add to Cart
→ Checkout
→ Complete Transaction
→ Leave Review
```

---

# Current Development Priorities

The initial project foundation focuses on:

1. Repository and Git workflow
2. Docker development environment
3. SQL database schema
4. PHP/database connectivity
5. Authentication
6. User profiles
7. Listing creation
8. First complete buyer/seller workflow

The first major technical milestone is:

> A user can register, log in, create a listing, store that listing in the database, and view the listing through OmniBuy.

---

# Security Considerations

OmniBuy handles user accounts and marketplace interactions, so security should be considered throughout development.

Important practices include:

- Password hashing
- Server-side input validation
- Prepared SQL statements
- Authentication checks
- Authorization checks
- Secure session handling
- Protection against duplicate or unauthorized actions
- Avoiding credentials in source control
- Validating uploaded files
- Restricting actions involving blocked users

---

# Documentation

Project documentation should be maintained throughout development.

Documentation may include:

```text
docs/
├── api.md
├── database.md
├── setup.md
└── testing.md
```

The README should remain the primary entry point for developers joining or reviewing the project.

---

# Project Status

**Status:** In Development

OmniBuy is being developed throughout the Fall 2026 CPSC 491 semester.

The team is currently working toward a functional marketplace implementation followed by integration testing, stabilization, deployment, documentation, and final demonstration.

---

# License

This repository is an academic senior capstone project developed for CPSC 491 at California State University, Fullerton.

Unless otherwise specified, the project is intended for educational use.
