# Sprint Estimator V4 - with Excel Import Feature

## 🎯 New Features

### Excel Import Functionality
- ✅ **Bulk Import**: Import projects, features, and user stories from Excel
- ✅ **Template Download**: Download pre-formatted Excel template with sample data
- ✅ **Data Validation**: Comprehensive validation before import
- ✅ **Error Handling**: Clear error messages with line numbers
- ✅ **Import Summary**: Shows count of projects, features, and stories created

---

## 📦 What's Included

### Files:
- `index.html` - Main application with import functionality
- `api/import.php` - Backend import handler
- `api/projects.php` - Project management API
- `api/features.php` - Feature management API
- `api/productivity.php` - Productivity tracking API
- `config.php` - Database configuration
- `database.sql` - Complete database schema
- `database-update.sql` - Phase efforts columns
- `database-defects-update.sql` - Defect tracking columns
- `generate_template.html` - Standalone template generator
- `EXCEL_TEMPLATE_STRUCTURE.md` - Detailed template documentation

---

## 🚀 Installation

### Step 1: Upload Files
```bash
# Extract and upload to your web server
unzip sprint-estimator-v4-excel.zip
# Upload to: /var/www/html/sprint-estimator/
```

### Step 2: Configure Database
```bash
# Edit config.php with your database credentials
nano config.php

# Update these values:
define('DB_HOST', 'localhost');
define('DB_NAME', 'sprint_estimator');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### Step 3: Create Database
```sql
-- Login to MySQL
mysql -u root -p

-- Create database
CREATE DATABASE sprint_estimator CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user (optional)
CREATE USER 'sprint_estimator'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON sprint_estimator.* TO 'sprint_estimator'@'localhost';
FLUSH PRIVILEGES;
```

### Step 4: Import Database Schema
```bash
# Import main schema
mysql -u root -p sprint_estimator < database.sql

# Import updates (if upgrading from older version)
mysql -u root -p sprint_estimator < database-update.sql
mysql -u root -p sprint_estimator < database-defects-update.sql
```

### Step 5: Set Permissions
```bash
chmod 755 /var/www/html/sprint-estimator
chmod 644 /var/www/html/sprint-estimator/*.php
chmod 755 /var/www/html/sprint-estimator/api
```

### Step 6: Access Application
```
http://your-domain.com/sprint-estimator/
```

---

## 📊 Using Excel Import

### Step 1: Download Template

1. Go to Dashboard
2. Click **"📊 Import from Excel"** button
3. In the modal, click **"⬇ Download Excel Template"**
4. Save file as: `sprint_estimator_template.csv`

### Step 2: Fill Template in Excel

Open the template in Excel or Google Sheets and fill in your data:

| Column | Description | Example |
|--------|-------------|---------|
| Project Name | Your project name | "E-Commerce Platform" |
| Project Description | Brief description | "Online shopping system" |
| Feature Name | Feature within project | "User Authentication" |
| Org Productivity | Productivity value (1-3) | 2 |
| Man Days Hours | Hours per man day | 8 |
| Feature Start Date | YYYY-MM-DD format | 2025-01-10 |
| Feature End Date | YYYY-MM-DD format | 2025-02-15 |
| Story Title | User story title | "Login page UI" |
| Story Hours | Estimated hours | 16 |
| Story Start Date | YYYY-MM-DD format | 2025-01-10 |
| Story End Date | YYYY-MM-DD format | 2025-01-15 |

**Important**: 
- Keep stories of same feature together
- Use same Org Productivity and Man Days Hours for all stories in a feature
- Ensure story dates fall within feature date range

### Step 3: Save as CSV

**In Excel**:
1. Click File → Save As
2. Choose **"CSV (Comma delimited) (*.csv)"**
3. Save the file

**In Google Sheets**:
1. Click File → Download
2. Choose **"Comma Separated Values (.csv)"**

### Step 4: Upload and Import

1. Go back to the application
2. Click **"📊 Import from Excel"** button
3. Click **"Choose File"** and select your CSV
4. Click **"📤 Upload & Import"**
5. Wait for processing
6. See import summary showing:
   - Projects Created
   - Features Created
   - Stories Created

### Step 5: Verify Import

1. Click **"View Projects"** in success message
2. Check that all your data imported correctly
3. Open projects to see features and stories

---

## 📝 Excel Template Structure

### Example Data Format

```csv
Project Name,Project Description,Feature Name,Org Prod,MD Hours,Feature Start,Feature End,Story Title,Story Hours,Story Start,Story End
E-Commerce,Online shop,User Auth,2,8,2025-01-10,2025-02-15,Login page,16,2025-01-10,2025-01-15
E-Commerce,Online shop,User Auth,2,8,2025-01-10,2025-02-15,Registration,24,2025-01-16,2025-01-22
E-Commerce,Online shop,Product List,2,8,2025-02-01,2025-03-15,List products,20,2025-02-01,2025-02-08
Mobile App,Customer app,Dashboard,1.8,8,2025-01-20,2025-02-28,Home screen,12,2025-01-20,2025-01-24
```

### How Data is Grouped

**Same Project Name** = Stories belong to same project
```
Row 1: Project A | Feature X | Story 1  ← Creates Project A
Row 2: Project A | Feature X | Story 2  ← Adds to existing Project A
Row 3: Project A | Feature Y | Story 3  ← Adds new Feature Y to Project A
Row 4: Project B | Feature Z | Story 4  ← Creates new Project B
```

### Auto-Calculated Fields

The system automatically calculates:
- **Story Man Days** = Story Hours ÷ Man Days Hours
- **Story Points** = Story Man Days × Org Productivity
- **Feature Total SP** = Sum of all story points in feature
- **Feature Total MD** = Sum of all man days in feature
- **Phase Breakdown**:
  - Requirements: 15% of total
  - Design: 15% of total
  - Development: 30% of total (your input)
  - Testing: 25% of total
  - Project Management: 15% of total

---

## ✅ Validation Rules

The import will fail if:

1. **Missing Required Fields**:
   - Project Name is empty
   - Feature Name is empty
   - Org Productivity is empty or ≤ 0
   - Man Days Hours is empty or ≤ 0
   - Story Title is empty
   - Story Hours is empty or ≤ 0

2. **Invalid Date Format**:
   - Dates not in YYYY-MM-DD format
   - Invalid dates (e.g., 2025-02-30)

3. **Date Range Violations**:
   - Story start date before feature start date
   - Story end date after feature end date

4. **Invalid Numbers**:
   - Org Productivity must be positive (typically 1.0-3.0)
   - Man Days Hours must be positive (typically 6-10)
   - Story Hours must be positive

---

## 🎨 Excel Template Tips

### Good Organization
```
✅ RECOMMENDED:
Project A | Feature 1 | Story 1
Project A | Feature 1 | Story 2
Project A | Feature 1 | Story 3
Project A | Feature 2 | Story 4
Project B | Feature 3 | Story 5
```

### Avoid Scattered Data
```
❌ AVOID:
Project A | Feature 1 | Story 1
Project B | Feature 3 | Story 5  ← Scattered
Project A | Feature 1 | Story 2  ← Hard to read
Project A | Feature 2 | Story 4
```

### Date Planning
```
Feature: 2025-01-10 to 2025-02-15
  ✅ Story 1: 2025-01-10 to 2025-01-15 (within range)
  ✅ Story 2: 2025-01-16 to 2025-01-22 (within range)
  ❌ Story 3: 2025-01-05 to 2025-01-09 (starts before feature)
  ❌ Story 4: 2025-02-10 to 2025-02-20 (ends after feature)
```

---

## 🔄 Import Behavior

### New vs Existing Projects

- **New Project Name**: Creates brand new project
- **Existing Project Name**: Adds features to existing project
- **Duplicate Feature Name**: Creates separate feature (allows duplicate names)
- **Productivity Data**: Not imported (must be entered manually after import)

### What Gets Imported

✅ **Imported**:
- Project name and description
- Feature name and settings
- User story details
- Estimated dates
- Story hours and calculations

❌ **Not Imported**:
- Actual productivity data
- Phase efforts (actual)
- Completion status
- Defect tracking data

These must be entered using the Productivity Tracker after import.

---

## 🐛 Troubleshooting

### Error: "Invalid template format"
**Solution**: Make sure you're using the exact template downloaded from the app

### Error: "Story dates must fall within feature date range"
**Solution**: Check that all story dates are between feature start and end dates

### Error: "Only CSV files are supported"
**Solution**: Save your Excel file as CSV before uploading

### Error: "Row X: Story Hours must be a positive number"
**Solution**: Check row X in your CSV - make sure Story Hours column has a valid number

### Import shows 0 projects created
**Solution**: Check that your CSV has data beyond the header row

### Database connection error
**Solution**: Verify config.php has correct database credentials

---

## 📚 Additional Resources

### Sample Import File
See the downloaded template for a complete working example with:
- 2 Projects
- 5 Features  
- 11 User Stories
- Proper date sequencing
- Varied story sizes

### Template Documentation
See `EXCEL_TEMPLATE_STRUCTURE.md` for comprehensive documentation on:
- Column structure
- Validation rules
- Data grouping
- Best practices
- Troubleshooting

### Standalone Template Generator
Open `generate_template.html` in a browser to download template without accessing the main app.

---

## 🎯 Complete Workflow

1. **Setup**: Install application and configure database
2. **Template**: Download Excel template
3. **Planning**: Fill template with your project data
4. **Import**: Upload CSV and import data
5. **Verify**: Check that data imported correctly
6. **Track**: Use Productivity Tracker to record actual efforts
7. **Analyze**: View control charts and metrics

---

## 💡 Best Practices

### Data Entry
- Group stories by feature for easier management
- Use consistent org productivity within features
- Plan dates realistically
- Leave optional fields empty if not needed

### After Import
- Review imported projects immediately
- Add any missing details manually
- Start tracking productivity as work begins
- Update dates as needed

### Template Management
- Keep a master template with your standard settings
- Save project-specific versions
- Version control your templates
- Document any custom columns (for reference only)

---

## 🔐 Security Notes

1. **File Upload**: Only CSV files accepted
2. **Validation**: All input validated before database insertion
3. **Transactions**: Database uses transactions (rollback on error)
4. **Error Handling**: No sensitive data in error messages
5. **Access Control**: Consider adding .htaccess authentication

---

## 📞 Support

If you encounter issues:

1. Check error message carefully (includes row numbers)
2. Verify template format matches downloaded version
3. Review `EXCEL_TEMPLATE_STRUCTURE.md` for detailed rules
4. Test with sample data first
5. Check browser console for JavaScript errors

---

## 🎉 Success!

After import, you'll see:
- All projects on dashboard
- Features within each project
- User stories within each feature
- Calculated story points and man days
- Phase breakdowns ready

Now you can start using the Productivity Tracker to record actual efforts and track performance!

---

**Sprint Estimator V4 with Excel Import**  
*Version 4.4 - December 2025*
