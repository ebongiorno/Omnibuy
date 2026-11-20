# OmniBuy

OmniBuy is a web-based e-commerce marketplace developed for **CPSC 491 Senior Capstone Project in Computer Science** at California State University, Fullerton.

OmniBuy combines useful features from modern online marketplaces into one platform. Users can buy and sell items, search and filter listings, message sellers, save items, choose between shipping and local meetup, get personalized recommendations, and build trust through reviews and safety features.

---

## Table of Contents

- [Team](#team)
- [Project Goals](#project-goals)
- [Core Features](#core-features)
- [Technology Stack](#technology-stack)
- [Architecture](#architecture)
- [Development Environment](#development-environment)
- [Getting Started](#getting-started)
- [Docker Reference](#docker-reference)
- [Troubleshooting](#troubleshooting)
- [Project Structure](#project-structure)
- [Database](#database)
- [Sample Data](#sample-data)
- [Testing](#testing)
- [CI/CD](#cicd)
- [Demonstration Preparation](#demonstration-preparation)
- [Development Dependency Order](#development-dependency-order)
- [Git Workflow](#git-workflow)
- [Pull Request Requirements](#pull-request-requirements)
- [Definition of Done](#definition-of-done)
- [Security Considerations](#security-considerations)
- [Documentation](#documentation)
- [Project Status](#project-status)
- [License](#license)

---

## Team

- Bryant Huynh
- Chantal Le
- Emily Bongiorno
- Kenneth Ly
- Riley Baltes

---

## Project Goals

OmniBuy is designed to be:

- Easy to use
- Accessible to buyers and sellers
- Suitable for local and shipped transactions
- Safe for user-to-user interactions
- Maintainable and scalable
- Responsive across different devices

It is particularly intended to support students, local communities, casual sellers, and small businesses.

---

## Core Features

### Accounts and Profiles

- User registration
- Login and logout
- Password recovery
- User and seller profiles
- Profile editing
- Privacy settings

### Listings

- Create, edit, and delete listings
- Upload listing images
- Hide or pause listings
- Renew listings
- Mark listings as sold
- View individual listing details

### Search and Discovery

- Homepage listing feed
- Personalized recommendations based on search and listing-view activity
- Keyword search
- Seller search
- Category browsing (parent categories and subcategories)
- Filters: price, condition, delivery method, meetup radius
- Result sorting

### Shopping

- Watchlist / favorites
- Shopping cart
- Checkout workflow
- Order creation and order history
- Shipping or meetup fulfillment

### Messaging

- Message sellers directly from listings
- Buyer/seller conversations
- Inbox and message threads
- Read/unread status

### Trust and Safety

- Block users
- Report users
- Moderation workflow
- Reviews after transactions
- Seller ratings and reputation

### Future / Advanced Features

- Seller analytics
- Listing performance analytics
- More advanced search and recommendation algorithms

---

## Technology Stack

| Layer | Technology |
|---|---|
| Frontend | HTML5, CSS3, JavaScript |
| Backend | PHP 8.2 on Apache |
| Database | MySQL 8 |
| Database admin | phpMyAdmin |
| Testing | PHPUnit (installed via Composer) |
| CI/CD | GitHub Actions |
| Environment | Docker, Docker Compose |
| Tools | Git, GitHub, VS Code, Postman, Chrome DevTools |

---

## Architecture

OmniBuy follows a layered architecture that keeps the user interface, application logic, and database operations separated.

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
PHP / SQL (PDO, prepared statements)
        |
        v
MySQL Database
```

Example request flow:

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

## Development Environment

Docker gives all five teammates the same PHP + MySQL environment regardless of host OS, so "it works on my machine" isn't a recurring problem. It doesn't replace application architecture decisions; it just runs PHP/Apache and MySQL consistently.

```text
Docker Compose
    |
    +-- omnibuy-web         Apache + PHP 8.2   (serves the app, runs PHPUnit)
    |
    +-- omnibuy-db          MySQL 8            (users, listings, orders, etc.)
    |
    +-- omnibuy-phpmyadmin  phpMyAdmin         (browser-based DB inspection)
```

PHP, MySQL, and Apache run inside containers and do **not** need to be installed locally.

---

## Getting Started

### Prerequisites

- **Docker Desktop** with Docker Compose support (on Windows, use the **WSL2 backend**)
- **Git**
- A modern browser (Chrome, Edge, or Firefox)
- A code editor such as VS Code

Verify Docker is installed:

```bash
docker --version
docker compose version
```

### 1. Clone the repository

```bash
git clone <repository-url>
cd omnibuy
```

Replace `<repository-url>` with the team's GitHub repository URL. Make sure the project contains the Docker configuration, application files, database scripts, and Composer configuration.

### 2. Configure environment variables

```bash
cp .env.example .env
```

Edit `.env` and set local values. Docker uses the `MYSQL_*` variables to create the database; the PHP application connects using the `DB_*` variables.

```env
# Used by the MySQL container
MYSQL_DATABASE=omnibuy
MYSQL_USER=omnibuy_user
MYSQL_PASSWORD=change_me
MYSQL_ROOT_PASSWORD=change_me_too

# Used by the PHP application (PDO)
DB_HOST=db
DB_NAME=omnibuy
DB_USER=omnibuy_user
DB_PASSWORD=change_me
```

> **Never commit `.env`.** Real passwords, credentials, API keys, and other secrets must be obtained through the team's approved private configuration process and must not be committed to GitHub. Any new environment variable must be added to `.env.example` (with a placeholder value).

### 3. Build and start Docker

```bash
docker compose up -d --build
```

This builds the images and starts all services. On later runs, `docker compose up -d` is enough unless the Dockerfile changed.

### 4. Check running containers

```bash
docker compose ps
```

You should see `omnibuy-web`, `omnibuy-db`, and `omnibuy-phpmyadmin` all `Up`. The database may briefly show `starting` while its healthcheck runs.

### 5. Database initialization

Any `.sql` files in `./database/init` are executed automatically by MySQL **the first time** the `mysql_data` volume is created. This loads the schema (tables, relationships, constraints) and the seed data.

- Existing volumes keep their data and are **not** re-initialized on startup.
- If initialization didn't run, import the schema and seed scripts manually (e.g., through phpMyAdmin).
- Adjust the `./database/init` path in `docker-compose.yml` if init scripts are moved.

### 6. Open OmniBuy

| Service | URL |
|---|---|
| Application | http://localhost:8080 |
| phpMyAdmin | http://localhost:8081 |

Log in to phpMyAdmin with `MYSQL_USER` / `MYSQL_PASSWORD` (or `root` / `MYSQL_ROOT_PASSWORD`) from your `.env`.

> If the app's entry point changes (for example, it moves into a subfolder), update this URL.

### 7. Verify your setup

1. `docker compose up -d` starts all three containers with no errors.
2. `docker compose ps` shows all three as `Up`.
3. http://localhost:8080 loads the homepage with marketplace navigation, categories, and active seeded listings.
4. http://localhost:8081 loads phpMyAdmin and shows the `omnibuy` database.
5. A PHP-to-MySQL connectivity test (e.g., `SELECT NOW()` through PDO) returns a timestamp.
6. Login and primary navigation work, and no unexpected PHP or database errors appear.
7. The PHPUnit suite passes (see [Testing](#testing)).

### 8. Stop the environment

```bash
docker compose down
```

---

## Docker Reference

```bash
docker compose up -d                      # start services
docker compose up -d --build              # rebuild images and start
docker compose down                       # stop services (data is kept)
docker compose ps                         # list container status
docker compose logs -f web                # tail PHP/Apache logs
docker compose logs -f db                 # tail MySQL logs
docker compose exec web bash              # shell into the PHP container
docker compose exec db mysql -u root -p   # MySQL shell
docker compose build                      # rebuild the web image after Dockerfile changes
docker compose restart web                # restart only the PHP container
```

> ⚠️ **Warning:** `docker compose down -v`
>
> The `-v` flag deletes the `mysql_data` volume, which **permanently deletes all local database data**. Use it only when you intentionally want a fresh database (this is also how you re-run the init scripts).

---

## Troubleshooting

**Port already in use (8080 / 8081 / 3306)**
Another process is using that port. Stop it, or change the host-side mapping in `docker-compose.yml` (e.g., `"8082:80"`).

**`web` keeps restarting or never becomes healthy**
Run `docker compose logs web`. A common cause is a PHP syntax error. (`web` waits on the `db` healthcheck, so connecting before MySQL is ready should not happen.)

**PHP can't connect to MySQL / "Connection refused"**
- The app must connect to host `db`, not `localhost` or `127.0.0.1`. Inside Docker, `localhost` refers to the PHP container itself.
- Confirm `.env` values match what the PDO connection expects (`DB_HOST=db`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`).

**Code changes aren't showing up**
Confirm the repo root is bind-mounted into the container (`./:/var/www/html` in `docker-compose.yml`) and that you're editing files in the repo, not inside the container.

**"no configuration file provided" or similar Compose errors**
Run `docker compose` commands from the repository root, where `docker-compose.yml` lives.

**Seed data or schema changes are missing**
Init scripts run only on a brand-new volume. Run `docker compose down -v` (this deletes local data), then `docker compose up -d --build`.

---

## Project Structure

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
│   ├── init/            # auto-run on first DB volume creation
│   ├── schema.sql
│   ├── seed_data.sql
│   └── migrations/
│
├── tests/               # PHPUnit tests
├── docs/
├── .github/workflows/   # GitHub Actions
│
├── Dockerfile
├── docker-compose.yml
├── composer.json
├── .env.example
├── .gitignore
└── README.md
```

The exact structure may change as development continues.

---

## Database

### Core entities

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

Relationships between these tables associate buyers, sellers, listings, messages, transactions, and reviews. User search and listing-view activity is also recorded to drive personalized recommendations.

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
 |
 +------ Search / View Activity  (recommendations)
```

Database changes must be documented (see `docs/database.md`).

---

## Sample Data

`seed_data.sql` provides sample records for development, automated testing, and demonstrations, including:

- User accounts and seller profiles
- Parent categories and subcategories
- Listings with varied prices, conditions, quantities, and statuses
- Listings supporting both shipping and local meetup
- Listing images and associated marketplace information
- Supporting data required for application features and tests

The dataset intentionally includes both **active and inactive** listings to verify marketplace visibility rules.

---

## Testing

Testing happens throughout development, not only at the end of the semester.

### Running the test suite

```bash
docker compose exec -T web vendor/bin/phpunit --testdox
```

The PHPUnit suite includes unit, integration, functional, and security tests covering marketplace discovery and other features.

### Testing areas

- Unit testing
- Integration testing
- User-interface testing
- Authentication testing
- Database testing
- Validation / error testing
- End-to-end workflow testing
- Regression testing

### Key end-to-end workflow

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

## CI/CD

GitHub Actions runs automatically as part of the pull request workflow and validates:

- PHP syntax checks
- Docker image builds
- Database initialization
- The automated PHPUnit test suite

Code changes must be peer reviewed **and** pass the required CI checks before merging into the shared repository.

---

## Demonstration Preparation

### Demonstration test account

The final demonstration uses one shared account so every team member's feature is shown with a consistent experience.

- **Username:** `omnibuy_tester`
- The account is already created and verified.
- Credentials are shared with authorized team members through an approved private method and are **never** included in project documentation.
- The same authenticated account stays in use throughout the demo with no repeated logins or account switching.

It is used to demonstrate searching, listing details, personalized recommendations, wishlisting, messaging, and other authenticated features.

### Personalized recommendation demo

The account must start with **no recorded item-search or listing-view activity**, so the demo begins with the general marketplace feed and then shows recorded interactions shaping a category-based personalized feed.

Required eligible listings:

- At least **two** distinct **Electronics** listings matching `keyboard`
- At least **three** distinct **Home & Furniture** listings matching `chair`

### Pre-demo checklist

- [ ] Docker containers are running and the app connects to MySQL
- [ ] Homepage and marketplace search load without errors
- [ ] Active seeded listings appear in the marketplace
- [ ] `omnibuy_tester` is active and can log in
- [ ] `omnibuy_tester` activity history is cleared or confirmed empty
- [ ] Required keyboard and chair listings are active, available, and owned by **other** accounts
- [ ] Seed data supports the planned demos for all team features
- [ ] Database is in a consistent state with no leftover test activity
- [ ] The latest integrated code passes the full PHPUnit suite and CI

---

## Development Dependency Order

Features are implemented according to their dependencies.

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

Authentication and user identity come early because most later features require a valid user. Listings are another major dependency, since search, filters, favorites, messaging, checkout, reviews, and recommendations all depend on listings existing.

---

## Git Workflow

Development is never done directly on `main`.

```text
feature branch
      |
      v
Pull Request  →  CI checks (GitHub Actions)
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

Example branch names:

```text
feature/user-registration
feature/login
feature/create-listing
feature/search
feature/messaging
fix/session-authentication
```

### Creating a feature branch

```bash
git checkout develop
git pull
git checkout -b feature/feature-name
```

After making changes:

```bash
git add .
git commit -m "Implement feature description"
git push -u origin feature/feature-name
```

Then open a Pull Request into `develop` on GitHub.

---

## Pull Request Requirements

Before merging:

- Code runs locally
- Relevant functionality is tested and the PHPUnit suite passes
- GitHub Actions CI checks pass
- No credentials are committed
- Code follows project conventions
- Database changes are documented
- New environment variables are added to `.env.example`
- Another team member has reviewed the change
- Integration issues are resolved

---

## Definition of Done

A feature is complete when:

- The required functionality is implemented
- Frontend and backend communicate successfully
- Database changes work correctly
- Input validation exists
- Expected error conditions are handled
- The feature has automated and/or manual tests
- The code is committed through a feature branch
- A Pull Request has been created, passes CI, and has been reviewed
- The feature works after integration with the rest of OmniBuy

---

## Security Considerations

OmniBuy handles user accounts and marketplace interactions, so security is considered throughout development:

- Password hashing
- Server-side input validation
- Prepared SQL statements
- Authentication and authorization checks
- Secure session handling
- Protection against duplicate or unauthorized actions
- Validating uploaded files
- Restricting actions involving blocked users
- Keeping credentials and secrets out of source control and documentation

Security-focused PHPUnit tests are part of the automated suite.

---

## Documentation

Project documentation is maintained throughout development:

```text
docs/
├── api.md
├── database.md
├── setup.md
└── testing.md
```

This README remains the primary entry point for developers joining or reviewing the project.

---

## Project Status

**Status:** In Development

OmniBuy is being developed throughout the Fall 2026 CPSC 491 semester. The team is working toward a functional marketplace implementation, followed by integration testing, stabilization, deployment, documentation, and the final demonstration.

### Initial priorities

1. Repository and Git workflow
2. Docker development environment
3. SQL database schema
4. PHP/database connectivity
5. Authentication
6. User profiles
7. Listing creation
8. First complete buyer/seller workflow

**First major milestone:**

> A user can register, log in, create a listing, store that listing in the database, and view the listing through OmniBuy.

---

## License

This repository is an academic senior capstone project developed for CPSC 491 at California State University, Fullerton. Unless otherwise specified, the project is intended for educational use.
