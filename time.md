# Development Time Tracking

## Overview

This file tracks the development time and progress for the DevJobs Portal project.

## Current Status

- Current feature being implemented: Feature #16 (Advanced search and filter options)
- Last commit time: 2023-12-05 15:30:00
- Commits left for today: 0

## Progress

### Feature #15: Job categories and tags

- Implemented database tables for categories and tags
- Created junction tables for job-category and job-tag relationships
- Added management pages for administrators to manage categories and tags
- Updated job posting and editing forms to include category and tag selection
- Enhanced job listing page with filtering by categories and tags
- Styled category and tag elements with CSS
- Completed on: 2023-12-05

### Feature #14: Admin dashboard for site management

- Created admin dashboard with overview statistics
- Implemented user management features
- Added job posting approval system
- Created reports section for site activity
- Styled admin interface with CSS
- Completed on: 2023-12-04

### Feature #13: Company profiles for employers

- Implemented company profile creation and editing
- Added company information to job listings
- Created company profile viewing page
- Implemented logo upload functionality
- Added company search and filtering
- Styled company profiles with CSS
- Completed on: 2023-12-03

## Time Management

### Daily Commit Targets

- Target: 1-2 features per day
- Average time per feature: 2-4 hours

### Remaining Features

- Features completed: 15/20
- Features remaining: 5
- Estimated completion date: 2023-12-10

# Commit Tracking Rules

1. The implementation must follow a linear series of commits to construct the project.
2. Each feature must be completed in a single commit
3. No more than 2 features may be implemented per day.
4. The first commit of each day must be at least 9 hours after the last commit of the previous day.
5. Commit times must be on a reasonable schedule, between 07:00 and 22:00.
6. Each feature typically takes 30 mins up to 3 hours after the previous feature.

## Commit Tracking Variables

- `last_commit_date`: 5 Feb, 2020
- `last_commit_time`: 10:01:12
- `commits_left`: 1

### Update Rules:

1. After each commit, decrease `commits_left` by 1 & increase the **minutes** in `last_commit_time` by **3** & increase `last_completed_feature_number` by 1.
2. If `commits_left` reaches `0`:
   - Select a random number between **1 and 5** and assign it to `commits_left`.
   - Advance `last_commit_date` by one day (e.g., `30 Jan, 2020` → `31 Jan, 2020`).
   - Reset `last_commit_time` to `08:27:54`.

### **Commit Rules**

- Use lowercase messages.
- Keep commit messages concise (a short phrase or brief sentence).
- Follow the date from _last_commit_date_ & _last_commit_time_ for doing the commits.
- Use the following commit format:
  ```bash
  GIT_COMMITTER_DATE="YYYY-MM-DDTHH:MM:SS+0000" git commit -m "commit message here" --date="YYYY-MM-DDTHH:MM:SS+0000"
  ```
- Push commits forcefully using:
  ```bash
  git push --force
  ```

### **Repository Details**

- **user.email** = `vkyd1909@gmail.com`
- **user.name** = `mccharliesins`
- **commits_left** = `https://github.com/mccharliesins/devjobs-portal.git`
