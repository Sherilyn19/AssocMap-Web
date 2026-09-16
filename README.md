# AssocMap Web Application

> **A GIS-Based Program Monitoring System for Community Livelihood Associations**

![Laravel](https://img.shields.io/badge/Laravel-12-red?logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-17-336791?logo=postgresql)
![PostGIS](https://img.shields.io/badge/PostGIS-Enabled-336791)
![Tailwind CSS](https://img.shields.io/badge/TailwindCSS-4-38BDF8?logo=tailwindcss)
![License](https://img.shields.io/badge/License-Academic-blue)

The **AssocMap Web Application** is the administrative platform of the AssocMap system, developed as a Capstone Project for the **Bureau of Fisheries and Aquatic Resources (BFAR) Region VII**. It enables administrators and field officers to efficiently manage community livelihood associations, monitor projects, visualize geospatial data through an interactive GIS map, and generate reports to support data-driven decision-making.

---

## About the Project

AssocMap is designed to digitize the monitoring and management of BFAR Region VII's **Special Area for Agricultural Development (SAAD) Phase II** program. The web application centralizes beneficiary information, project records, training activities, monitoring reports, and geographic data into a single platform.

This repository contains the **Laravel Web Application** only.

## Repository

**Repository Name:** AssocMap-Web

**GitHub Repository:**

https://github.com/Sherilyn19/AssocMap-Web

This repository contains the Laravel-based web application for the AssocMap Capstone Project.

---

## Current Project Status

**Development Phase:** 🚧 Initial Development

Current progress includes:

* Repository initialization
* Development environment setup
* Database schema design
* System architecture planning
* Laravel project configuration

Additional modules will be implemented throughout the development lifecycle.

---

## Planned Features

### Authentication & Authorization

* User Authentication
* Role-Based Access Control (RBAC)
* User Management

### Dashboard

* Program Summary
* Association Statistics
* GIS Overview
* Monitoring Analytics

### Association Management

* Manage Associations
* Assign Field Officers
* Association Profiles
* Archive Associations

### Member Management

* Member Registration
* Membership Approval
* Member Records
* Representative Assignment

### Project Management

* Project Information
* Distributed Materials
* Project Status Monitoring

### Training Management

* Training Records
* Attendance Monitoring
* Participant Management

### Monitoring & Accomplishment

* Production Monitoring
* Income Monitoring
* Materials Monitoring

### GIS Module

* Interactive Map
* Association Locations
* Project Locations
* Spatial Data Visualization

### Reports

* PDF Report Generation
* Monitoring Reports
* Statistical Reports
* Data Export

### Audit Logs

* User Activity Logs
* System Audit Trail

---

## System Users

The web application supports the following authenticated users:

* **System Administrator**
* **Field Officer**
* **Association Member (Shared Association Account)**

Public users will access the public GIS map through a separate interface.

---

## Technology Stack

### Backend

* Laravel 12
* PHP 8.5.7

### Frontend

* Blade Templates
* Tailwind CSS
* Vite
* JavaScript

### Database

* PostgreSQL
* PostGIS

### GIS

* Leaflet.js
* OpenStreetMap

### Cloud Services

* Supabase

### Version Control

* Git
* GitHub

---

## Project Structure

```text
AssocMap-Web/
│
├── app/
├── bootstrap/
├── config/
├── database/
├── public/
├── resources/
├── routes/
├── storage/
├── tests/
├── docs/
├── .env.example
├── composer.json
├── package.json
└── README.md
```

---

## Prerequisites

Before running the project, ensure the following are installed:

* PHP
* Composer
* Node.js
* npm
* PostgreSQL
* PostGIS
* Git

---

## Installation

Clone the repository.

```bash
git clone git clone https://github.com/Sherilyn19/AssocMap-Web
```

Navigate to the project directory.

```bash
cd AssocMap-Web
```

Install PHP dependencies.

```bash
composer install
```

Install JavaScript dependencies.

```bash
npm install
```

Create the environment file.

```bash
cp .env.example .env
```

Generate the Laravel application key.

```bash
php artisan key:generate
```

Configure the database credentials in the `.env` file.

Run the database migrations.

```bash
php artisan migrate
```

Start the Laravel development server.

```bash
php artisan serve
```

Run the Vite development server.

```bash
npm run dev
```

---

## Documentation

Project documentation is located in the `docs/` directory.

Planned documentation includes:

* Development Setup
* Database Design
* System Architecture
* API Documentation
* Deployment Guide
* Coding Standards
* Git Workflow
* Testing Guide
* Troubleshooting Guide

---

## Development Team

**Bachelor of Science in Information Systems**

**Cebu Technological University – Main Campus**

* Krys Dea S. Llesol - Hustler
* Sherilyn Q. Sanchez - Hacker
* Anagel M. Simacas - Hipster

---

## Client

**Bureau of Fisheries and Aquatic Resources (BFAR) Region VII**

---

## Academic Purpose

This project is developed as part of the Capstone Project requirements for the Bachelor of Science in Information Systems program at Cebu Technological University – Main Campus.

---

## License

This repository is intended for academic purposes.

---

## Future Updates

The repository will continue to evolve as development progresses. Upcoming updates include:

* Complete module implementation
* API integration
* GIS enhancements
* Comprehensive testing
* Deployment configuration
* User documentation
* Production release
