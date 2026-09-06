[README.md](https://github.com/user-attachments/files/31880662/README.md)
# OCC Performance Management System (OPCR-IPCR)

A web-based **Performance Management System** developed for **Opol Community College (OCC)** to digitalize the Office Performance Commitment and Review (OPCR) and Individual Performance Commitment and Review (IPCR) processes.

The system provides a structured, role-based workflow for target setting, OPCR approval, cascading of performance commitments, IPCR preparation, accomplishment and evidence submission, review, rating, and finalization.

> **Project Status:** Academic/Capstone Project  
> **Initial Client:** Opol Community College

---

## Overview

The OCC Performance Management System is designed to reduce the manual workload involved in managing performance commitments and reviews.

The system separates responsibilities among authorized users:

1. **Administrator** configures users, offices, school years, rating periods, and workflow settings.
2. **President** prepares the College OPCR and assigns performance targets.
3. **Quality Assurance (QA)** reviews and approves OPCR targets and performs performance ratings.
4. **Vice Presidents and Heads** manage their own IPCRs and review IPCRs under their responsibility.
5. **Employees** prepare their IPCRs, submit accomplishments, and attach supporting evidence.
6. Submitted forms move through the configured review and rating workflow until they are finalized.

The OPCR is maintained on a **yearly basis**, while IPCRs are maintained according to the configured **rating periods**.

---

## Key Features

- Role-based authentication and authorization
- User and account management
- Organizational unit and office hierarchy management
- School year and rating period management
- College OPCR creation and management
- OPCR target and success-indicator management
- OPCR-to-IPCR cascading and assignment
- IPCR creation and submission
- Accomplishment tracking
- Supporting evidence/attachment submission
- Head and Vice President review workflow
- Quality Assurance review and rating
- Quality, Efficiency, and Timeliness (Q/E/T) rating
- Automatic average/adjectival rating computation
- Return and resubmission workflow
- Notifications
- Comments and status history
- Audit trail
- Reports
- PDF/Excel reporting support
- Configurable workflow settings

---

## User Roles

| Role | Main Responsibility |
|---|---|
| **Administrator** | Manages users, offices, school years, rating periods, workflow settings, and system configuration |
| **President** | Creates and manages the College OPCR, assigns targets, and oversees institutional performance |
| **Quality Assurance (QA)** | Approves OPCR targets and rates submitted performance forms |
| **Vice President** | Maintains an IPCR and reviews IPCRs from offices under their supervision |
| **Head** | Maintains an IPCR, reviews team members' IPCRs, and may assign indicators to personnel |
| **Employee** | Creates and submits an individual IPCR, accomplishments, and supporting evidence |

---

## Performance Management Workflow

### OPCR Workflow

```text
Administrator
     ↓
Set up users, offices, school year, rating periods
     ↓
President creates College OPCR
     ↓
President assigns OPCR targets
     ↓
QA reviews OPCR targets
     ↓
Approved?
 ┌───┴───┐
No      Yes
↓        ↓
Return   President publishes OPCR
         ↓
         Targets become available for IPCR cascading
```

### IPCR Workflow

```text
Employee / Head / VP
        ↓
Prepare IPCR
        ↓
Submit IPCR
        ↓
Head Review
        ↓
VP Review
        ↓
QA Rating
        ↓
Finalization
```

Review stages may be skipped when the corresponding reviewer is not applicable or is not configured for the organizational unit.

---

## Rating System

The system supports rating based on:

- **Q – Quality**
- **E – Efficiency**
- **T – Timeliness**

The three ratings are averaged to determine the overall rating for the indicator.

| Average Rating | Adjectival Rating |
|---:|---|
| 4.50 – 5.00 | Outstanding |
| 3.50 – 4.49 | Very Satisfactory |
| 2.50 – 3.49 | Satisfactory |
| 1.50 – 2.49 | Unsatisfactory |
| Below 1.50 | Poor |

---

## Technology Stack

### Backend

- PHP 8.2+
- Laravel 12
- Laravel Passport
- Laravel UI
- DomPDF
- PhpSpreadsheet

### Frontend

- React 18
- Vite
- Ant Design
- React Router
- TanStack React Query
- Axios
- Tiptap
- Day.js

### Database

The project is configured to use **MySQL**.

Example configuration:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=occ_pms
DB_USERNAME=root
DB_PASSWORD=
```

---

## System Requirements

- PHP 8.2 or higher
- Composer
- Node.js and npm
- MySQL
- Git
- Modern web browser

XAMPP or another PHP/MySQL development environment may be used for local development.

---

## Installation

### 1. Clone the repository

```bash
git clone <YOUR-GITHUB-REPOSITORY-URL>
cd OPCR-IPCR
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install frontend dependencies

```bash
npm install
```

### 4. Create the environment file

Copy `.env.example` and rename it to `.env`.

### 5. Configure the database

Create a MySQL database named:

```text
occ_pms
```

Then update the database settings in `.env` if necessary.

### 6. Generate the application key

```bash
php artisan key:generate
```

### 7. Run migrations

```bash
php artisan migrate
```

If seed data is available:

```bash
php artisan db:seed
```

### 8. Build frontend assets

For development:

```bash
npm run dev
```

For production:

```bash
npm run build
```

### 9. Start the Laravel server

```bash
php artisan serve
```

The application will normally be available at:

```text
http://localhost:8000
```

---

## Demo Data

The project includes demo-data functionality for testing the system workflow. The available roles include:

- Administrator
- President
- Quality Assurance
- Vice President
- Head
- Employee




---

## Documentation

The repository includes `SYSTEM_FLOW.md`, which documents:

- User roles
- Organizational hierarchy
- OPCR lifecycle
- IPCR lifecycle
- Target cascading
- Review responsibilities
- Rating process
- Role-specific system access
- Form structure

Refer to **SYSTEM_FLOW.md** for the detailed workflow implemented by the system.

---

## Project Purpose

The system aims to provide a centralized digital platform for managing performance commitments and reviews, replacing manual and fragmented processes with a structured workflow.

It is initially developed for **Opol Community College** and uses configurable organizational units, roles, and workflow settings to support possible adaptation to other organizations implementing OPCR/IPCR-based performance management.

---

## License

This project is released under the **MIT License**, as specified by the project configuration.

---

## Academic Project

Developed as an academic/capstone project for the digitalization of the OPCR/IPCR performance management process of Opol Community College.
