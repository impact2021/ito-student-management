# IELTS Membership System - Plugin Summary

## What This Plugin Does

This is a WordPress membership plugin specifically designed for IELTS preparation course websites. Here's what it does:

### Core Functionality
- **Membership Management** - Creates and manages user memberships with automatic expiration tracking
- **Payment Processing** - Handles payments through PayPal and Stripe for membership purchases and renewals
- **Custom User Authentication** - Replaces default WordPress login with custom login/registration pages
- **Content Protection** - Restricts access to IELTS courses, lessons, resources, and quizzes to active members only

### Key Features
- **Free Trial System** - Allows new users to try the platform for 72 hours (configurable) with one trial per email enforcement
- **Course Module System** - Organizes content into "General Training" and "Academic" modules with module-specific access control
- **Membership Plans** - Offers 90-day memberships ($24.95) and extensions (1 week, 1 month, 3 months)
- **Email Notifications** - Sends automated emails for trial enrollment, trial expiration, paid enrollment, and membership expiration
- **Account Dashboard** - Provides users with a personal account page to manage membership, view payment history, and update profile

### Custom Post Types
- **IELTS Courses** - Main course containers
- **IELTS Lessons** - Individual lessons within courses
- **IELTS Resources** - Downloadable materials
- **IELTS Quizzes** - Practice assessments

### Admin Features
- **Member Management** - View and manage all members and their membership status
- **Payment Tracking** - Monitor all payment transactions
- **Settings Configuration** - Configure payment gateways, pricing, trial settings, and email templates
- **Module Management** - Organize courses by General Training or Academic modules

### Technical Details
- Creates custom database tables for memberships, payments, and trial usage tracking
- Implements custom user roles: "active" for active members and "expired" for expired members
- Uses daily cron jobs to automatically expire memberships
- Fully customizable email templates with placeholder support
- Administrators have full access to all content without requiring a membership
